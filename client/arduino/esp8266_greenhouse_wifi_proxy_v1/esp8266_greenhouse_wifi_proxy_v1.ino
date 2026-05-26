/*
  AiDvor greenhouse Wi-Fi proxy, ESP8266 side.

  ESP8266 responsibilities:
    - receive CSV telemetry from Uno over Serial
    - send HTTPS POST to AiDvor server using provisioning or controller bearer tokens
    - return response CSV to Uno over Serial

  Wiring:
    Uno TX -> ESP RX through a 5V-to-3.3V divider or level shifter
    Uno RX <- ESP TX
    GND    -> GND

  Serial:
    9600 baud
*/

#include <ESP8266WiFi.h>
#include <ESP8266WebServer.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>
#include <LittleFS.h>

const unsigned long WIFI_CONNECT_TIMEOUT_MS = 30000UL;
const size_t SETUP_AP_MIN_PASSWORD_LENGTH = 8;
const size_t REQUEST_MAX = 260;
const size_t JSON_MAX = 620;
const size_t RESPONSE_MAX = 220;
const char CONFIG_PATH[] = "/config.txt";
const char PROVISION_PATH[] = "/api/controller/provision";
const char REPORT_PATH[] = "/api/controller/report";
const char DASHBOARD_PATH[] = "/dashboard";

struct ProxyConfig {
  char wifiSsid[33];
  char wifiPassword[65];
  char serverUrl[128];
  char provisioningToken[96];
  char apiToken[96];
  char tlsFingerprint[60];
  char localLogin[33];
  char localPassword[49];
  char setupApPassword[65];
  char controllerId[37];  // UUID or empty
  unsigned long cloudGraceSeconds;
  bool tlsInsecure;
};

ProxyConfig config;
ESP8266WebServer webServer(80);
bool configLoaded = false;
bool fallbackApActive = false;
bool cloudOnline = false;
bool safeOffActive = false;
bool restartPending = false;
int lastHttpStatus = 0;
unsigned long lastTelemetryMs = 0;
unsigned long lastCloudSuccessMs = 0;
unsigned long cloudLostAtMs = 0;
unsigned long restartAtMs = 0;
char requestLine[REQUEST_MAX];
char jsonPayload[JSON_MAX];
char lastError[40] = "not_started";
char lastTelemetryCsv[REQUEST_MAX] = "";
char relayState[4] = { '0', '0', '0', '0' };
char soilMoistureRaw[16] = "";
char lightLevelRaw[16] = "";
char airHumidity[16] = "";
char airTemperature[16] = "";
char fallbackApSsid[33] = "";

static bool appendFmt(char *dst, size_t dstSize, size_t *used, const char *fmt, ...) {
  if (*used >= dstSize) return false;

  va_list args;
  va_start(args, fmt);
  int written = vsnprintf(dst + *used, dstSize - *used, fmt, args);
  va_end(args);

  if (written < 0 || (size_t) written >= (dstSize - *used)) return false;
  *used += (size_t) written;
  return true;
}

static void copyConfigValue(char *dst, size_t dstSize, const String &value) {
  if (dstSize == 0) return;

  size_t len = value.length();
  if (len >= dstSize) len = dstSize - 1;

  memcpy(dst, value.c_str(), len);
  dst[len] = '\0';
}

static void setConfigValue(const String &key, const String &value) {
  if (key == "wifi_ssid") {
    copyConfigValue(config.wifiSsid, sizeof(config.wifiSsid), value);
  } else if (key == "wifi_password") {
    copyConfigValue(config.wifiPassword, sizeof(config.wifiPassword), value);
  } else if (key == "server_url") {
    copyConfigValue(config.serverUrl, sizeof(config.serverUrl), value);
  } else if (key == "provisioning_token") {
    copyConfigValue(config.provisioningToken, sizeof(config.provisioningToken), value);
  } else if (key == "api_token") {
    copyConfigValue(config.apiToken, sizeof(config.apiToken), value);
  } else if (key == "tls_fingerprint") {
    copyConfigValue(config.tlsFingerprint, sizeof(config.tlsFingerprint), value);
  } else if (key == "tls_insecure") {
    config.tlsInsecure = value == "1" || value.equalsIgnoreCase("true");
  } else if (key == "local_login") {
    copyConfigValue(config.localLogin, sizeof(config.localLogin), value);
  } else if (key == "local_password") {
    copyConfigValue(config.localPassword, sizeof(config.localPassword), value);
  } else if (key == "setup_ap_password") {
    copyConfigValue(config.setupApPassword, sizeof(config.setupApPassword), value);
  } else if (key == "controller_id") {
    copyConfigValue(config.controllerId, sizeof(config.controllerId), value);
  } else if (key == "cloud_grace_seconds") {
    long seconds = value.toInt();
    config.cloudGraceSeconds = seconds > 0 ? (unsigned long) seconds : 300UL;
  }
}

static bool validateConfigValues(ProxyConfig &candidate) {
  if (candidate.cloudGraceSeconds == 0) {
    candidate.cloudGraceSeconds = 300UL;
  }

  if (candidate.setupApPassword[0] != '\0'
      && strlen(candidate.setupApPassword) < SETUP_AP_MIN_PASSWORD_LENGTH) {
    return false;
  }

  return candidate.wifiSsid[0] != '\0'
    && candidate.wifiPassword[0] != '\0'
    && candidate.serverUrl[0] != '\0'
    && candidate.localLogin[0] != '\0'
    && candidate.localPassword[0] != '\0';
}

static bool validateConfig() {
  return validateConfigValues(config);
}

static bool loadConfig() {
  memset(&config, 0, sizeof(config));

  if (!LittleFS.begin()) {
    return false;
  }

  if (!LittleFS.exists(CONFIG_PATH)) {
    return false;
  }

  File file = LittleFS.open(CONFIG_PATH, "r");
  if (!file) {
    return false;
  }

  while (file.available()) {
    String line = file.readStringUntil('\n');
    line.trim();
    if (line.length() == 0 || line.charAt(0) == '#') {
      continue;
    }

    int separator = line.indexOf('=');
    if (separator <= 0) {
      continue;
    }

    String key = line.substring(0, separator);
    String value = line.substring(separator + 1);
    key.trim();
    value.trim();
    setConfigValue(key, value);
  }

  file.close();
  return validateConfig();
}

static bool writeConfigFile(const ProxyConfig &nextConfig) {
  File file = LittleFS.open(CONFIG_PATH, "w");
  if (!file) {
    return false;
  }

  file.print(F("wifi_ssid="));
  file.println(nextConfig.wifiSsid);
  file.print(F("wifi_password="));
  file.println(nextConfig.wifiPassword);
  file.print(F("server_url="));
  file.println(nextConfig.serverUrl);
  file.print(F("provisioning_token="));
  file.println(nextConfig.provisioningToken);
  file.print(F("api_token="));
  file.println(nextConfig.apiToken);
  file.print(F("tls_insecure="));
  file.println(nextConfig.tlsInsecure ? F("1") : F("0"));
  file.print(F("tls_fingerprint="));
  file.println(nextConfig.tlsFingerprint);
  file.print(F("local_login="));
  file.println(nextConfig.localLogin);
  file.print(F("local_password="));
  file.println(nextConfig.localPassword);
  file.print(F("setup_ap_password="));
  file.println(nextConfig.setupApPassword);
  file.print(F("controller_id="));
  file.println(nextConfig.controllerId);
  file.print(F("cloud_grace_seconds="));
  file.println(nextConfig.cloudGraceSeconds);
  file.close();
  return true;
}

static bool ensureProvisioningToken() {
  if (config.apiToken[0] != '\0' || config.provisioningToken[0] != '\0') {
    return true;
  }

  const char hex[] = "0123456789abcdef";
  char token[65];
  for (size_t i = 0; i < 32; i++) {
    uint8_t value = (uint8_t) (ESP.random() & 0xff);
    token[i * 2] = hex[(value >> 4) & 0x0f];
    token[(i * 2) + 1] = hex[value & 0x0f];
  }
  token[64] = '\0';
  copyConfigValue(config.provisioningToken, sizeof(config.provisioningToken), String(token));

  return writeConfigFile(config);
}

static bool readCsvLine(char *out, size_t outSize) {
  static size_t used = 0;

  while (Serial.available() > 0) {
    char c = (char) Serial.read();
    if (c == '\r') continue;

    if (c == '\n') {
      out[used] = '\0';
      bool hasLine = used > 0;
      used = 0;
      return hasLine;
    }

    if (used + 1 < outSize) {
      out[used++] = c;
      out[used] = '\0';
    } else {
      used = 0;
      out[0] = '\0';
      Serial.println(F("error=serial_line_too_long"));
      return false;
    }
  }

  return false;
}

static bool readCsvToken(const char **cursor, char *key, size_t keySize, char *value, size_t valueSize) {
  const char *p = *cursor;
  while (*p == ';' || *p == ' ' || *p == '\r' || *p == '\n' || *p == '\t') p++;
  if (*p == '\0') {
    *cursor = p;
    return false;
  }

  const char *eq = strchr(p, '=');
  if (!eq) {
    *cursor = p + strlen(p);
    return false;
  }

  const char *sep = strchr(eq + 1, ';');
  const char *end = sep ? sep : p + strlen(p);

  size_t keyLen = (size_t) (eq - p);
  size_t valueLen = (size_t) (end - (eq + 1));
  if (keyLen + 1 > keySize) keyLen = keySize - 1;
  if (valueLen + 1 > valueSize) valueLen = valueSize - 1;

  memcpy(key, p, keyLen);
  key[keyLen] = '\0';
  memcpy(value, eq + 1, valueLen);
  value[valueLen] = '\0';

  *cursor = sep ? sep + 1 : end;
  return key[0] != '\0';
}

static void setLastError(const char *value) {
  strncpy(lastError, value, sizeof(lastError) - 1);
  lastError[sizeof(lastError) - 1] = '\0';
}

static void rememberValue(char *dst, size_t dstSize, const char *value) {
  if (dstSize == 0) return;
  strncpy(dst, value, dstSize - 1);
  dst[dstSize - 1] = '\0';
}

static void updateRelayStateFromKey(const char *key, const char *value) {
  if (strlen(key) != 7 || strncmp(key, "relay_", 6) != 0) {
    return;
  }

  int relayIndex = key[6] - '1';
  if (relayIndex < 0 || relayIndex > 3) {
    return;
  }

  relayState[relayIndex] = atoi(value) > 0 ? '1' : '0';
}

static void updateStateFromCsv(const char *csv) {
  char key[32];
  char value[56];
  const char *cursor = csv;

  rememberValue(lastTelemetryCsv, sizeof(lastTelemetryCsv), csv);
  lastTelemetryMs = millis();

  while (readCsvToken(&cursor, key, sizeof(key), value, sizeof(value))) {
    updateRelayStateFromKey(key, value);

    if (strcmp(key, "soil_moisture_raw") == 0) {
      rememberValue(soilMoistureRaw, sizeof(soilMoistureRaw), value);
    } else if (strcmp(key, "light_level_raw") == 0) {
      rememberValue(lightLevelRaw, sizeof(lightLevelRaw), value);
    } else if (strcmp(key, "air_humidity") == 0) {
      rememberValue(airHumidity, sizeof(airHumidity), value);
    } else if (strcmp(key, "air_temperature") == 0) {
      rememberValue(airTemperature, sizeof(airTemperature), value);
    }
  }
}

static void markCloudFailure(const char *error, int statusCode) {
  lastHttpStatus = statusCode;
  setLastError(error);

  if (cloudOnline || cloudLostAtMs == 0) {
    cloudLostAtMs = millis();
  }

  cloudOnline = false;
}

static void markCloudSuccess(int statusCode) {
  cloudOnline = true;
  safeOffActive = false;
  cloudLostAtMs = 0;
  lastHttpStatus = statusCode;
  lastCloudSuccessMs = millis();
  setLastError("none");
}

static unsigned long cloudOfflineSeconds() {
  if (cloudOnline || cloudLostAtMs == 0) {
    return 0;
  }

  return (millis() - cloudLostAtMs) / 1000UL;
}

static void sendAllRelaysOffToUno() {
  int status = lastHttpStatus > 0 ? lastHttpStatus : 0;
  snprintf(requestLine, sizeof(requestLine), "relay_1=0;relay_2=0;relay_3=0;relay_4=0;monitor=E%03d", status);
  Serial.println(requestLine);
  for (size_t i = 0; i < 4; i++) {
    relayState[i] = '0';
  }
}

static void checkSafeOff() {
  if (cloudOnline || cloudLostAtMs == 0 || safeOffActive || !configLoaded) {
    return;
  }

  if (cloudOfflineSeconds() >= config.cloudGraceSeconds) {
    sendAllRelaysOffToUno();
    safeOffActive = true;
    setLastError("cloud_grace_expired");
  }
}

static bool isNumericCsvValue(const char *value) {
  if (*value == '-' || *value == '+') value++;

  bool hasDigit = false;
  bool hasDot = false;
  while (*value) {
    if (*value >= '0' && *value <= '9') {
      hasDigit = true;
    } else if (*value == '.' && !hasDot) {
      hasDot = true;
    } else {
      return false;
    }
    value++;
  }

  return hasDigit;
}

static bool csvToJson(const char *csv, char *out, size_t outSize) {
  char key[32];
  char value[56];
  char deviceUid[48] = "";
  const char *cursor = csv;

  snprintf(deviceUid, sizeof(deviceUid), "esp_%06lx", (unsigned long) ESP.getChipId());

  if (deviceUid[0] == '\0') {
    return false;
  }

  size_t used = 0;
  bool firstReading = true;
  if (!appendFmt(
    out,
    outSize,
    &used,
    "{\"controller_id\":\"%s\",\"device_uid\":\"%s\",\"readings\":[",
    config.controllerId,
    deviceUid
  )) {
    return false;
  }

  cursor = csv;
  while (readCsvToken(&cursor, key, sizeof(key), value, sizeof(value))) {
    if (strcmp(key, "controller_id") == 0 || !isNumericCsvValue(value)) {
      continue;
    }

    if (!appendFmt(
      out,
      outSize,
      &used,
      "%s{\"pin\":\"%s\",\"value\":%s}",
      firstReading ? "" : ",",
      key,
      value
    )) {
      return false;
    }
    firstReading = false;
  }

  if (firstReading) {
    return false;
  }

  return appendFmt(out, outSize, &used, "]}");
}

static long extractJsonLongField(const String &json, const char *fieldName, long fallbackValue) {
  String token = "\"";
  token += fieldName;
  token += "\"";

  int fieldIndex = json.indexOf(token);
  if (fieldIndex < 0) return fallbackValue;

  int colonIndex = json.indexOf(':', fieldIndex + token.length());
  if (colonIndex < 0) return fallbackValue;

  int valueStart = colonIndex + 1;
  while (valueStart < json.length()) {
    char c = json.charAt(valueStart);
    if (c == '-' || (c >= '0' && c <= '9')) break;
    valueStart++;
  }

  if (valueStart >= json.length()) return fallbackValue;

  int valueEnd = valueStart;
  if (json.charAt(valueEnd) == '-') valueEnd++;
  while (valueEnd < json.length()) {
    char c = json.charAt(valueEnd);
    if (c < '0' || c > '9') break;
    valueEnd++;
  }

  if (valueEnd <= valueStart) return fallbackValue;
  return json.substring(valueStart, valueEnd).toInt();
}

static String extractJsonStringField(const String &json, const char *fieldName) {
  String token = "\"";
  token += fieldName;
  token += "\"";

  int fieldIndex = json.indexOf(token);
  if (fieldIndex < 0) return "";

  int colonIndex = json.indexOf(':', fieldIndex + token.length());
  if (colonIndex < 0) return "";

  int firstQuote = json.indexOf('"', colonIndex + 1);
  if (firstQuote < 0) return "";

  int secondQuote = json.indexOf('"', firstQuote + 1);
  if (secondQuote <= firstQuote) return "";

  return json.substring(firstQuote + 1, secondQuote);
}

static void saveProvisioningTokenFromResponse(const String &json) {
  String controllerId = extractJsonStringField(json, "controller_id");
  String apiToken = extractJsonStringField(json, "api_token");
  controllerId.trim();
  apiToken.trim();

  bool changed = false;

  if (controllerId.length() > 0 && controllerId.length() < sizeof(config.controllerId) && !controllerId.equals(config.controllerId)) {
    copyConfigValue(config.controllerId, sizeof(config.controllerId), controllerId);
    changed = true;
  }

  if (apiToken.length() > 0 && apiToken.length() < sizeof(config.apiToken) && !apiToken.equals(config.apiToken)) {
    copyConfigValue(config.apiToken, sizeof(config.apiToken), apiToken);
    config.provisioningToken[0] = '\0';
    changed = true;
  }

  if (changed && !writeConfigFile(config)) {
    setLastError("credentials_save_failed");
  }
}

static bool jsonToUnoCsv(const String &json, char *out, size_t outSize) {
  size_t used = 0;
  bool hasValue = false;

  long interval = extractJsonLongField(json, "send_interval_seconds", -1);
  if (interval > 0) {
    if (!appendFmt(out, outSize, &used, "send_interval_seconds=%ld", interval)) return false;
    hasValue = true;
  }

  const char *digitalKeys[] = { "relay_1", "relay_2", "relay_3", "relay_4" };
  for (size_t i = 0; i < sizeof(digitalKeys) / sizeof(digitalKeys[0]); i++) {
    long value = extractJsonLongField(json, digitalKeys[i], -1);
    if (value < 0) continue;
    if (!appendFmt(out, outSize, &used, "%s%s=%ld", hasValue ? ";" : "", digitalKeys[i], value > 0 ? 1 : 0)) return false;
    hasValue = true;
  }

  String monitor = extractJsonStringField(json, "monitor");
  if (monitor.length() > 0) {
    if (!appendFmt(out, outSize, &used, "%smonitor=%s", hasValue ? ";" : "", monitor.c_str())) return false;
    hasValue = true;
  }

  return hasValue;
}

static const char *fallbackApPassword() {
  if (strlen(config.setupApPassword) >= SETUP_AP_MIN_PASSWORD_LENGTH) {
    return config.setupApPassword;
  }

  if (strlen(config.localPassword) >= SETUP_AP_MIN_PASSWORD_LENGTH) {
    return config.localPassword;
  }

  return NULL;
}

static bool startFallbackAp() {
  const char *password = fallbackApPassword();
  if (password == NULL) {
    setLastError("setup_ap_password_missing");
    Serial.println(F("fallback_ap=failed;error=setup_ap_password_missing"));
    return false;
  }

  snprintf(fallbackApSsid, sizeof(fallbackApSsid), "AiDvor-ESP-%06X", ESP.getChipId());
  WiFi.disconnect();
  WiFi.mode(WIFI_AP);
  if (!WiFi.softAP(fallbackApSsid, password)) {
    setLastError("fallback_ap_failed");
    Serial.println(F("fallback_ap=failed;error=softap_start_failed"));
    return false;
  }

  fallbackApActive = true;
  Serial.print(F("fallback_ap=active;ssid="));
  Serial.print(fallbackApSsid);
  Serial.print(F(";ip="));
  Serial.println(WiFi.softAPIP());
  return true;
}

static bool ensureWifi() {
  if (fallbackApActive) {
    return false;
  }

  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  WiFi.mode(WIFI_STA);
  WiFi.begin(config.wifiSsid, config.wifiPassword);

  unsigned long startedAt = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - startedAt) < WIFI_CONNECT_TIMEOUT_MS) {
    delay(250);
  }

  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  startFallbackAp();
  return false;
}

static String buildServerUrl(const char *path) {
  String url = config.serverUrl;
  url.trim();

  while (url.endsWith("/")) {
    url.remove(url.length() - 1);
  }

  if (path[0] != '/') {
    url += '/';
  }
  url += path;

  return url;
}

static bool configureTlsClient(WiFiClientSecure &client) {
  String serverUrl = config.serverUrl;
  serverUrl.trim();
  if (!serverUrl.startsWith("https://")) {
    return true;
  }

  if (config.tlsInsecure) {
    client.setInsecure();  // Explicitly permitted for a local development server only.
    return true;
  }

  if (config.tlsFingerprint[0] == '\0') {
    return false;
  }

  client.setFingerprint(config.tlsFingerprint);
  return true;
}

static void postToServer(const char *payload) {
  if (!configLoaded) {
    markCloudFailure("config_missing", 0);
    Serial.println(F("http_status=0;error=config_missing"));
    return;
  }

  if (!csvToJson(payload, jsonPayload, sizeof(jsonPayload))) {
    markCloudFailure("csv_to_json_failed", 0);
    Serial.println(F("http_status=0;error=csv_to_json_failed"));
    return;
  }

  if (!ensureWifi()) {
    markCloudFailure("wifi_connect_failed", 0);
    Serial.println(F("http_status=0;error=wifi_connect_failed"));
    return;
  }

  if (!ensureProvisioningToken()) {
    markCloudFailure("token_save_failed", 0);
    Serial.println(F("http_status=0;error=token_save_failed"));
    return;
  }
  bool provisionMode = config.apiToken[0] == '\0';

  WiFiClientSecure client;
  if (!configureTlsClient(client)) {
    markCloudFailure("tls_config_missing", 0);
    Serial.println(F("http_status=0;error=tls_config_missing"));
    return;
  }

  HTTPClient http;
  http.setTimeout(15000);
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);

  String reportUrl = buildServerUrl(provisionMode ? PROVISION_PATH : REPORT_PATH);
  if (!http.begin(client, reportUrl)) {
    markCloudFailure("http_begin_failed", 0);
    Serial.println(F("http_status=0;error=http_begin_failed"));
    return;
  }

  http.addHeader(F("Accept"), F("application/json"));
  http.addHeader(F("Content-Type"), F("application/json"));
  http.addHeader(F("Connection"), F("close"));
  http.addHeader(F("User-Agent"), F("AiDvor-ESP8266/1.0"));
  String authorization = F("Bearer ");
  authorization += provisionMode ? config.provisioningToken : config.apiToken;
  http.addHeader(F("Authorization"), authorization);

  int statusCode = http.POST((uint8_t *) jsonPayload, strlen(jsonPayload));
  String body = http.getString();
  http.end();

  body.trim();
  if (statusCode < 0) {
    markCloudFailure("http_post_failed", statusCode);
    Serial.print(F("http_status=0;error=http_post_failed;code="));
    Serial.println(statusCode);
    return;
  }

  if (statusCode != 200) {
    markCloudFailure("http_not_200", statusCode);
    Serial.print(F("http_status="));
    Serial.print(statusCode);
    Serial.println(F(";error=http_not_200"));
    return;
  }

  markCloudSuccess(statusCode);
  if (body.length() == 0) {
    return;
  }

  saveProvisioningTokenFromResponse(body);

  if (jsonToUnoCsv(body, requestLine, sizeof(requestLine))) {
    updateStateFromCsv(requestLine);
    Serial.println(requestLine);
  }
}

static bool requireLocalAuth() {
  if (config.localLogin[0] == '\0' || config.localPassword[0] == '\0') {
    webServer.send(503, F("text/plain"), F("Local access credentials are missing"));
    return false;
  }

  if (webServer.authenticate(config.localLogin, config.localPassword)) {
    return true;
  }

  webServer.requestAuthentication(BASIC_AUTH, "AiDvor ESP");
  return false;
}

static String buildStatusJson() {
  String json;
  json.reserve(620);
  json += F("{\"device\":\"esp8266_proxy\"");
  json += F(",\"config_loaded\":");
  json += configLoaded ? F("true") : F("false");
  json += F(",\"cloud_online\":");
  json += cloudOnline ? F("true") : F("false");
  json += F(",\"safe_off_active\":");
  json += safeOffActive ? F("true") : F("false");
  json += F(",\"wifi_mode\":\"");
  json += fallbackApActive ? F("fallback_ap") : F("station");
  json += F(",\"ip\":\"");
  json += fallbackApActive ? WiFi.softAPIP().toString() : WiFi.localIP().toString();
  json += F("\",\"fallback_ap_ssid\":\"");
  if (fallbackApActive) {
    json += fallbackApSsid;
  }
  json += F("\",\"controller_id\":\"");
  json += config.controllerId;
  json += F("\",\"last_http_status\":");
  json += String(lastHttpStatus);
  json += F(",\"last_error\":\"");
  json += lastError;
  json += F("\",\"cloud_grace_seconds\":");
  json += String(config.cloudGraceSeconds);
  json += F(",\"cloud_offline_seconds\":");
  json += String(cloudOfflineSeconds());
  json += F(",\"last_telemetry_ms\":");
  json += String(lastTelemetryMs);
  json += F(",\"last_cloud_success_ms\":");
  json += String(lastCloudSuccessMs);
  json += F(",\"readings\":{");
  json += F("\"soil_moisture_raw\":\"");
  json += soilMoistureRaw;
  json += F("\",\"light_level_raw\":\"");
  json += lightLevelRaw;
  json += F("\",\"air_humidity\":\"");
  json += airHumidity;
  json += F("\",\"air_temperature\":\"");
  json += airTemperature;
  json += F("\"},\"relays\":{");
  for (size_t i = 0; i < 4; i++) {
    if (i > 0) json += ',';
    json += F("\"relay_");
    json += String(i + 1);
    json += F("\":");
    json += relayState[i];
  }
  json += F("}}");
  return json;
}

static void sendLocalPage() {
  if (!requireLocalAuth()) {
    return;
  }

  String html;
  html.reserve(2600);
  html += F("<!doctype html><html><head><meta charset='utf-8'>");
  html += F("<meta name='viewport' content='width=device-width,initial-scale=1'>");
  html += F("<title>AiDvor ESP Local</title>");
  html += F("<style>body{margin:0;font-family:Arial,sans-serif;background:#071a13;color:#f4fff8}");
  html += F(".wrap{max-width:760px;margin:0 auto;padding:24px}.card{background:#10281d;border:1px solid #28543d;border-radius:18px;padding:18px;margin:14px 0}");
  html += F("h1{margin:0 0 10px;font-size:28px}.muted{color:#a8c8b8}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}");
  html += F(".item{background:#173728;border-radius:12px;padding:12px}.ok{color:#62dc91}.bad{color:#ff8a8a}");
  html += F("button{border:0;border-radius:10px;padding:10px 14px;margin:4px;font-weight:700;cursor:pointer}.on{background:#62dc91}.off{background:#ffcf70}button:disabled{opacity:.45;cursor:not-allowed}");
  html += F("a{color:#62dc91}</style></head><body><div class='wrap'>");
  html += F("<h1>AiDvor ESP Local</h1><p class='muted'>Локальное управление доступно независимо от облака.</p>");
  html += F("<div class='card'><div id='summary'>Загрузка...</div></div>");
  html += F("<div class='card'><h2>Телеметрия</h2><div class='grid' id='readings'></div></div>");
  html += F("<div class='card'><h2>Реле</h2><p class='muted' id='control-note'></p><div id='relays'></div></div>");
  html += F("<div class='card'><a href='/status'>JSON status</a> &nbsp; <a href='/config'>Настройки ESP</a></div>");
  html += F("<script>");
  html += F("async function api(p,o){const r=await fetch(p,o);return r.json()}");
  html += F("async function relay(pin,value){await api('/relay',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'pin='+pin+'&value='+value});load()}");
  html += F("async function load(){const s=await api('/status');");
  html += F("document.getElementById('summary').innerHTML='Cloud: <b class=\"'+(s.cloud_online?'ok':'bad')+'\">'+(s.cloud_online?'online':'offline')+'</b><br>Wi-Fi mode: '+s.wifi_mode+'<br>IP: '+s.ip+(s.fallback_ap_ssid?'<br>Setup AP: '+s.fallback_ap_ssid:'')+'<br>HTTP: '+s.last_http_status+' / '+s.last_error+'<br>Safe off: '+s.safe_off_active+'<br>Offline seconds: '+s.cloud_offline_seconds+' / '+s.cloud_grace_seconds;");
  html += F("const labels={soil_moisture_raw:'Влажность почвы',light_level_raw:'Освещенность',air_humidity:'Влажность воздуха',air_temperature:'Температура воздуха'};");
  html += F("let rh='';for(const k in s.readings){rh+='<div class=item><b>'+(labels[k]||k)+'</b><br>'+s.readings[k]+'</div>'}document.getElementById('readings').innerHTML=rh;");
  html += F("document.getElementById('control-note').textContent=s.cloud_online?'Управление выполняется сервером. Локальные кнопки доступны при потере связи.':'Сервер недоступен. Локальное управление активно.';");
  html += F("let d=s.cloud_online?' disabled':'';let rr='';for(const k in s.relays){rr+='<div class=item><b>'+k+': '+s.relays[k]+'</b><br><button class=on '+d+' onclick=\"relay(\\''+k+'\\',1)\">ON</button><button class=off '+d+' onclick=\"relay(\\''+k+'\\',0)\">OFF</button></div>'}document.getElementById('relays').innerHTML=rr}");
  html += F("load();setInterval(load,5000)</script></div></body></html>");

  webServer.send(200, F("text/html"), html);
}

static void addTextInput(String &html, const char *name, const char *label, const char *help, const char *value) {
  html += F("<label>");
  html += label;
  html += F("<span class='help'>");
  html += help;
  html += F("</span>");
  html += F("<input name='");
  html += name;
  html += F("' value='");
  html += value;
  html += F("'></label>");
}

static void addSecretInput(String &html, const char *name, const char *label, const char *help) {
  html += F("<label>");
  html += label;
  html += F("<span class='help'>");
  html += help;
  html += F("</span>");
  html += F("<input name='");
  html += name;
  html += F("' type='password' placeholder='оставьте пустым, чтобы не менять'></label>");
}

static void sendConfigPage(const char *message = "") {
  if (!requireLocalAuth()) {
    return;
  }

  String html;
  html.reserve(3600);
  html += F("<!doctype html><html><head><meta charset='utf-8'>");
  html += F("<meta name='viewport' content='width=device-width,initial-scale=1'>");
  html += F("<title>AiDvor ESP Config</title>");
  html += F("<style>body{margin:0;font-family:Arial,sans-serif;background:#071a13;color:#f4fff8}");
  html += F(".wrap{max-width:760px;margin:0 auto;padding:24px}.card{background:#10281d;border:1px solid #28543d;border-radius:18px;padding:18px;margin:14px 0}");
  html += F("label{display:block;margin:12px 0;color:#a8c8b8}input{box-sizing:border-box;width:100%;padding:10px;margin-top:5px;border-radius:10px;border:1px solid #28543d;background:#071a13;color:#f4fff8}");
  html += F(".help{display:block;margin-top:4px;font-size:13px;line-height:1.4;color:#7fa794}button{border:0;border-radius:10px;padding:12px 16px;font-weight:700;background:#62dc91;cursor:pointer}.msg{color:#62dc91}a{color:#62dc91}</style></head><body><div class='wrap'>");
  html += F("<h1>Настройки ESP</h1><p><a href='/local'>Назад</a></p>");
  html += F("<p class='help'>После сохранения ESP перезапустится автоматически. Новые настройки применятся после перезапуска.</p>");
  if (message[0] != '\0') {
    html += F("<div class='card msg'>");
    html += message;
    html += F("</div>");
  }
  html += F("<form class='card' method='post' action='/config'>");
  addTextInput(html, "wifi_ssid", "Wi-Fi сеть", "Имя Wi-Fi сети, к которой подключается ESP8266.", config.wifiSsid);
  addSecretInput(html, "wifi_password", "Пароль Wi-Fi", "Оставьте пустым, если не хотите менять текущий пароль.");
  addTextInput(html, "server_url", "Адрес сервера AiDvor", "Базовый адрес сервера без пути. Пример: http://192.168.0.201:3000 или https://home.aidvor.ru.", config.serverUrl);
  addTextInput(html, "controller_id", "ID контроллера", "Заполняется сервером после регистрации. Оставьте пустым перед первой регистрацией.", config.controllerId);
  addSecretInput(html, "api_token", "Токен контроллера", "Автоматически выдается сервером после регистрации. Оставьте пустым, если не хотите менять.");
  addTextInput(html, "tls_insecure", "Небезопасный HTTPS (dev)", "Значение 1 разрешает HTTPS без проверки сертификата только в локальной разработке; для production укажите 0.", config.tlsInsecure ? "1" : "0");
  addTextInput(html, "tls_fingerprint", "TLS fingerprint", "SHA-1 fingerprint сертификата для проверки HTTPS при tls_insecure=0.", config.tlsFingerprint);
  addTextInput(html, "local_login", "Логин локальной админки", "Логин для входа на страницы ESP: /local, /status, /config.", config.localLogin);
  addSecretInput(html, "local_password", "Пароль локальной админки", "Оставьте пустым, если не хотите менять текущий пароль.");
  addSecretInput(html, "setup_ap_password", "Пароль точки восстановления", "Пароль Wi-Fi точки AiDvor-ESP, которая включается, если домашняя сеть недоступна. Минимум 8 символов. Если не задан, используется пароль локальной админки.");
  char grace[16];
  snprintf(grace, sizeof(grace), "%lu", config.cloudGraceSeconds);
  addTextInput(html, "cloud_grace_seconds", "Задержка отключения реле, секунд", "Сколько секунд ESP сохраняет состояние реле после потери связи с сервером. После истечения времени все реле выключаются.", grace);
  html += F("<p><button type='submit'>Сохранить</button></p>");
  html += F("</form></div></body></html>");

  webServer.send(200, F("text/html"), html);
}

static void handleConfigPost() {
  if (!requireLocalAuth()) {
    return;
  }

  ProxyConfig nextConfig = config;
  if (webServer.hasArg("wifi_ssid")) copyConfigValue(nextConfig.wifiSsid, sizeof(nextConfig.wifiSsid), webServer.arg("wifi_ssid"));
  if (webServer.hasArg("server_url")) copyConfigValue(nextConfig.serverUrl, sizeof(nextConfig.serverUrl), webServer.arg("server_url"));
  if (webServer.hasArg("controller_id")) copyConfigValue(nextConfig.controllerId, sizeof(nextConfig.controllerId), webServer.arg("controller_id"));
  if (webServer.hasArg("tls_insecure")) nextConfig.tlsInsecure = webServer.arg("tls_insecure") == "1";
  if (webServer.hasArg("tls_fingerprint")) copyConfigValue(nextConfig.tlsFingerprint, sizeof(nextConfig.tlsFingerprint), webServer.arg("tls_fingerprint"));
  if (webServer.hasArg("local_login")) copyConfigValue(nextConfig.localLogin, sizeof(nextConfig.localLogin), webServer.arg("local_login"));

  if (webServer.hasArg("wifi_password") && webServer.arg("wifi_password").length() > 0) {
    copyConfigValue(nextConfig.wifiPassword, sizeof(nextConfig.wifiPassword), webServer.arg("wifi_password"));
  }
  if (webServer.hasArg("api_token") && webServer.arg("api_token").length() > 0) {
    copyConfigValue(nextConfig.apiToken, sizeof(nextConfig.apiToken), webServer.arg("api_token"));
  }
  if (webServer.hasArg("local_password") && webServer.arg("local_password").length() > 0) {
    copyConfigValue(nextConfig.localPassword, sizeof(nextConfig.localPassword), webServer.arg("local_password"));
  }
  if (webServer.hasArg("setup_ap_password") && webServer.arg("setup_ap_password").length() > 0) {
    copyConfigValue(nextConfig.setupApPassword, sizeof(nextConfig.setupApPassword), webServer.arg("setup_ap_password"));
  }
  if (webServer.hasArg("cloud_grace_seconds")) {
    long seconds = webServer.arg("cloud_grace_seconds").toInt();
    nextConfig.cloudGraceSeconds = seconds > 0 ? (unsigned long) seconds : config.cloudGraceSeconds;
  }

  if (!validateConfigValues(nextConfig)) {
    webServer.send(400, F("text/plain"), F("Invalid config"));
    return;
  }

  bool saved = writeConfigFile(nextConfig);
  if (!saved) {
    sendConfigPage("Config save failed.");
    return;
  }

  webServer.send(200, F("text/html"), F("<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><meta http-equiv='refresh' content='4;url=/config'><title>AiDvor ESP</title></head><body><h1>Config saved</h1><p>ESP is restarting. The config page will reopen automatically.</p></body></html>"));
  restartPending = true;
  restartAtMs = millis() + 1000UL;
}

static void handleRoot() {
  if (cloudOnline && configLoaded) {
    String dashboardUrl = buildServerUrl(DASHBOARD_PATH);
    webServer.sendHeader(F("Location"), dashboardUrl);
    webServer.send(302, F("text/plain"), F("Redirecting to AiDvor"));
    return;
  }

  sendLocalPage();
}

static void handleStatus() {
  if (!requireLocalAuth()) {
    return;
  }

  webServer.send(200, F("application/json"), buildStatusJson());
}

static void handleRelay() {
  if (!requireLocalAuth()) {
    return;
  }

  if (cloudOnline) {
    webServer.send(409, F("application/json"), F("{\"error\":\"cloud_online\",\"message\":\"Local relay control is available only when cloud is offline.\"}"));
    return;
  }

  String pin = webServer.arg("pin");
  String value = webServer.arg("value");
  int relayIndex = -1;
  if (pin.length() == 7 && pin.startsWith("relay_")) {
    relayIndex = pin.charAt(6) - '1';
  }

  if (relayIndex < 0 || relayIndex > 3 || (value != "0" && value != "1")) {
    webServer.send(400, F("application/json"), F("{\"error\":\"bad_relay_request\"}"));
    return;
  }

  relayState[relayIndex] = value == "1" ? '1' : '0';
  snprintf(requestLine, sizeof(requestLine), "relay_1=%c;relay_2=%c;relay_3=%c;relay_4=%c;monitor=local", relayState[0], relayState[1], relayState[2], relayState[3]);
  Serial.println(requestLine);
  safeOffActive = false;

  webServer.send(200, F("application/json"), buildStatusJson());
}

static void setupWebServer() {
  webServer.on("/", HTTP_GET, handleRoot);
  webServer.on("/local", HTTP_GET, sendLocalPage);
  webServer.on("/status", HTTP_GET, handleStatus);
  webServer.on("/relay", HTTP_POST, handleRelay);
  webServer.on("/config", HTTP_GET, []() {
    sendConfigPage();
  });
  webServer.on("/config", HTTP_POST, handleConfigPost);
  webServer.onNotFound([]() {
    webServer.send(404, F("text/plain"), F("Not found"));
  });
  webServer.begin();
}

void setup() {
  Serial.begin(9600);
  configLoaded = loadConfig();
  WiFi.persistent(false);
  WiFi.setAutoReconnect(true);
  if (configLoaded) {
    ensureWifi();
  }
  setupWebServer();
  delay(500);
  Serial.println(F("ready=esp8266_proxy"));
  Serial.print(F("config_loaded="));
  Serial.println(configLoaded ? F("1") : F("0"));
}

void loop() {
  webServer.handleClient();
  checkSafeOff();

  if (restartPending && millis() >= restartAtMs) {
    ESP.restart();
  }

  if (readCsvLine(requestLine, sizeof(requestLine))) {
    updateStateFromCsv(requestLine);
    postToServer(requestLine);
    checkSafeOff();
  }
}
