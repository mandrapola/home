/*
  AiDvor greenhouse Wi-Fi proxy, ESP8266 side.

  ESP8266 responsibilities:
    - receive CSV telemetry from Uno over Serial
    - send HTTPS POST to AiDvor server
    - return response CSV to Uno over Serial

  Wiring:
    Uno TX -> ESP RX through a 5V-to-3.3V divider or level shifter
    Uno RX <- ESP TX
    GND    -> GND

  Serial:
    9600 baud
*/

#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>
#include <bearssl/bearssl.h>
#include <time.h>

const char wifiSsid[] = "VPN_2G";
const char wifiPassword[] = "wifi-V3.14N";
const char reportUrl[] = "https://home.aidvor.ru/api/controller/report";
const char reportPath[] = "/api/controller/report";
const char proxyId[] = "getaway";
const char proxySecret[] = "e6d85ea4ef7e4cc3a9ff6d20c05681a5b58f6f438c8049353f1457d07abf98ef";

const unsigned long WIFI_CONNECT_TIMEOUT_MS = 20000UL;
const unsigned long TIME_SYNC_TIMEOUT_MS = 15000UL;
const size_t REQUEST_MAX = 260;
const size_t JSON_MAX = 520;
const size_t RESPONSE_MAX = 220;

char requestLine[REQUEST_MAX];
char jsonPayload[JSON_MAX];
char bodyHashHex[65];
char signatureHex[65];
char timestampBuffer[16];
char nonceBuffer[32];
char canonicalBuffer[220];

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

static void bytesToHex(const uint8_t *bytes, size_t len, char *out, size_t outSize) {
  const char hex[] = "0123456789abcdef";
  if (outSize < (len * 2) + 1) {
    if (outSize > 0) out[0] = '\0';
    return;
  }

  for (size_t i = 0; i < len; i++) {
    out[i * 2] = hex[(bytes[i] >> 4) & 0x0f];
    out[(i * 2) + 1] = hex[bytes[i] & 0x0f];
  }
  out[len * 2] = '\0';
}

static void sha256Hex(const char *text, char *out, size_t outSize) {
  uint8_t digest[32];
  br_sha256_context ctx;

  br_sha256_init(&ctx);
  br_sha256_update(&ctx, text, strlen(text));
  br_sha256_out(&ctx, digest);
  bytesToHex(digest, sizeof(digest), out, outSize);
}

static void hmacSha256Hex(const char *secret, const char *text, char *out, size_t outSize) {
  uint8_t digest[32];
  br_hmac_key_context keyCtx;
  br_hmac_context hmacCtx;

  br_hmac_key_init(&keyCtx, &br_sha256_vtable, secret, strlen(secret));
  br_hmac_init(&hmacCtx, &keyCtx, 0);
  br_hmac_update(&hmacCtx, text, strlen(text));
  br_hmac_out(&hmacCtx, digest);
  bytesToHex(digest, sizeof(digest), out, outSize);
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
  char controllerId[48] = "";
  const char *cursor = csv;

  while (readCsvToken(&cursor, key, sizeof(key), value, sizeof(value))) {
    if (strcmp(key, "controller_id") == 0) {
      strncpy(controllerId, value, sizeof(controllerId) - 1);
      controllerId[sizeof(controllerId) - 1] = '\0';
      break;
    }
  }

  if (controllerId[0] == '\0') {
    return false;
  }

  size_t used = 0;
  bool firstReading = true;
  if (!appendFmt(out, outSize, &used, "{\"controller_id\":\"%s\",\"readings\":[", controllerId)) {
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

static bool ensureWifi() {
  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  WiFi.mode(WIFI_STA);
  WiFi.begin(wifiSsid, wifiPassword);

  unsigned long startedAt = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - startedAt) < WIFI_CONNECT_TIMEOUT_MS) {
    delay(250);
  }

  return WiFi.status() == WL_CONNECTED;
}

static bool ensureTime() {
  time_t now = time(nullptr);
  if (now > 1700000000) {
    return true;
  }

  configTime(0, 0, "pool.ntp.org", "time.google.com", "time.cloudflare.com");

  unsigned long startedAt = millis();
  while ((millis() - startedAt) < TIME_SYNC_TIMEOUT_MS) {
    now = time(nullptr);
    if (now > 1700000000) {
      return true;
    }
    delay(250);
  }

  return false;
}

static bool buildHmacHeaders(const char *payload) {
  if (proxyId[0] == '\0' || proxySecret[0] == '\0') {
    return false;
  }

  if (!ensureTime()) {
    return false;
  }

  snprintf(timestampBuffer, sizeof(timestampBuffer), "%lu", (unsigned long) time(nullptr));
  snprintf(nonceBuffer, sizeof(nonceBuffer), "%06lx%06lx", (unsigned long) ESP.getChipId(), (unsigned long) millis());

  sha256Hex(payload, bodyHashHex, sizeof(bodyHashHex));
  snprintf(canonicalBuffer, sizeof(canonicalBuffer), "POST\n%s\n%s\n%s\n%s\n%s", reportPath, bodyHashHex, timestampBuffer, nonceBuffer, proxyId);
  hmacSha256Hex(proxySecret, canonicalBuffer, signatureHex, sizeof(signatureHex));

  return bodyHashHex[0] != '\0' && signatureHex[0] != '\0';
}

static void postToServer(const char *payload) {
  if (!csvToJson(payload, jsonPayload, sizeof(jsonPayload))) {
    Serial.println(F("http_status=0;error=csv_to_json_failed"));
    return;
  }

  if (!ensureWifi()) {
    Serial.println(F("http_status=0;error=wifi_connect_failed"));
    return;
  }

  if (!buildHmacHeaders(jsonPayload)) {
    Serial.println(F("http_status=0;error=hmac_build_failed"));
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(15000);
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);

  if (!http.begin(client, reportUrl)) {
    Serial.println(F("http_status=0;error=http_begin_failed"));
    return;
  }

  http.addHeader(F("Accept"), F("application/json"));
  http.addHeader(F("Content-Type"), F("application/json"));
  http.addHeader(F("Connection"), F("close"));
  http.addHeader(F("User-Agent"), F("AiDvor-ESP8266/1.0"));
  http.addHeader(F("X-Proxy-Id"), proxyId);
  http.addHeader(F("X-Timestamp"), timestampBuffer);
  http.addHeader(F("X-Nonce"), nonceBuffer);
  http.addHeader(F("X-Signature"), signatureHex);

  int statusCode = http.POST((uint8_t *) jsonPayload, strlen(jsonPayload));
  String body = http.getString();
  http.end();

  body.trim();
  if (statusCode < 0) {
    Serial.print(F("http_status=0;error=http_post_failed;code="));
    Serial.println(statusCode);
    return;
  }

  if (body.length() == 0) {
    Serial.print(F("http_status="));
    Serial.print(statusCode);
    Serial.println(F(";error=empty_response"));
    return;
  }

  if (statusCode >= 300 && statusCode < 400) {
    Serial.print(F("http_status="));
    Serial.print(statusCode);
    Serial.println(F(";error=redirect_response"));
    return;
  }

  if (body.charAt(0) == '<') {
    Serial.print(F("http_status="));
    Serial.print(statusCode);
    Serial.println(F(";error=html_response"));
    return;
  }

  if (statusCode >= 400 || body.indexOf('=') < 0) {
    if (statusCode >= 200 && statusCode < 300) {
      if (jsonToUnoCsv(body, requestLine, sizeof(requestLine))) {
        Serial.println(requestLine);
        return;
      }
    }

    Serial.print(F("http_status="));
    Serial.print(statusCode);
    Serial.print(F(";"));
    if (body.length() + 16 > RESPONSE_MAX) {
      body = body.substring(0, RESPONSE_MAX - 17);
    }
  }

  Serial.println(body);
}

void setup() {
  Serial.begin(9600);
  WiFi.persistent(false);
  WiFi.setAutoReconnect(true);
  delay(500);
  Serial.println(F("ready=esp8266_proxy"));
}

void loop() {
  if (readCsvLine(requestLine, sizeof(requestLine))) {
    postToServer(requestLine);
  }
}
