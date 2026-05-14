/*
  AiDvor greenhouse controller over ESP8266 AT.

  Hardware:
    Arduino Uno
    ESP8266 connected as an external AT modem
    DHT22 on D2
    Relay outputs: D3, D9, D5, D6
    Soil moisture raw: A0
    Light level raw: A1
    TM1637 display: D7/D8

  ESP8266 wiring:
    ESP TX -> Arduino D10
    ESP RX <- Arduino D11 through a 5V-to-3.3V divider or level shifter
    GND    -> GND

  ESP8266 UART:
    9600 baud is recommended for SoftwareSerial on Arduino Uno.
*/

#include <SoftwareSerial.h>
#include <DHT.h>
#include <TM1637Display.h>
#include <stdarg.h>
#include <stdlib.h>

// ===== Wi-Fi / server config =====
const char wifiSsid[] = "VPN_2G";
const char wifiPassword[] = "wifi-V3.14N";
const char serverHost[] = "192.168.0.1";
const uint16_t serverPort = 3001;
const char connectionType[] = "TCP";
const char reportPath[] = "/api/controller/report";

// ===== Controller =====
const char controllerId[] = "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f";
unsigned long sendIntervalMs = 30000UL;

// ===== ESP8266 AT UART =====
const uint8_t ESP_RX_PIN = 10; // Arduino receives from ESP TX.
const uint8_t ESP_TX_PIN = 11; // Arduino sends to ESP RX.
const unsigned long ESP_BAUD = 9600;
SoftwareSerial esp(ESP_RX_PIN, ESP_TX_PIN);

// ===== Sensors =====
const uint8_t DHT_PIN = 2;
#define DHTTYPE DHT22
DHT dht(DHT_PIN, DHTTYPE);
bool hasLastDhtValues = false;
float lastHumidity = NAN;
float lastTemperature = NAN;

const uint8_t TM1637_DIO_PIN = 7;
const uint8_t TM1637_CLK_PIN = 8;
TM1637Display pairingDisplay(TM1637_CLK_PIN, TM1637_DIO_PIN);

const uint8_t DIGITAL_PINS[] = { 3, 9, 5, 6 };
const char *DIGITAL_KEYS[] = { "relay_1", "relay_2", "relay_3", "relay_4" };
const bool RELAY_ACTIVE_LOW[] = { true, true, true, true };

const uint8_t ANALOG_PINS[] = { A0, A1 };
const char *ANALOG_KEYS[] = { "soil_moisture_raw", "light_level_raw" };

const size_t DIGITAL_COUNT = sizeof(DIGITAL_PINS) / sizeof(DIGITAL_PINS[0]);
const size_t ANALOG_COUNT = sizeof(ANALOG_PINS) / sizeof(ANALOG_PINS[0]);

unsigned long lastSendMs = 0;
char requestPayload[190];
char atBuffer[300];
char responseBody[130];
char commandBuffer[112];

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

static uint8_t relayLogicalToWire(size_t relayIndex, int logicalValue) {
  int logical = logicalValue > 0 ? 1 : 0;
  if (!RELAY_ACTIVE_LOW[relayIndex]) return logical > 0 ? HIGH : LOW;
  return logical > 0 ? LOW : HIGH;
}

static int relayWireToLogical(size_t relayIndex, int wireLevel) {
  int wire = wireLevel == HIGH ? 1 : 0;
  if (!RELAY_ACTIVE_LOW[relayIndex]) return wire;
  return wire > 0 ? 0 : 1;
}

static void setAllRelaysOff() {
  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    digitalWrite(DIGITAL_PINS[i], relayLogicalToWire(i, 0));
  }
}

static void showConnectionErrorOnDisplay() {
  const uint8_t SEG_E_CHAR = (uint8_t) (SEG_A | SEG_D | SEG_E | SEG_F | SEG_G);
  const uint8_t SEG_r_CHAR = (uint8_t) (SEG_E | SEG_G);
  const uint8_t segments[] = { SEG_E_CHAR, SEG_r_CHAR, SEG_r_CHAR, 0x00 };
  pairingDisplay.setSegments(segments);
}

static void showHttpStatusErrorOnDisplay(int statusCode) {
  const uint8_t SEG_E_CHAR = (uint8_t) (SEG_A | SEG_D | SEG_E | SEG_F | SEG_G);

  if (statusCode == 403 || statusCode == 404 || statusCode == 500) {
    uint8_t digits[3];
    digits[0] = pairingDisplay.encodeDigit((uint8_t) ((statusCode / 100) % 10));
    digits[1] = pairingDisplay.encodeDigit((uint8_t) ((statusCode / 10) % 10));
    digits[2] = pairingDisplay.encodeDigit((uint8_t) (statusCode % 10));
    const uint8_t segments[] = { SEG_E_CHAR, digits[0], digits[1], digits[2] };
    pairingDisplay.setSegments(segments);
    return;
  }

  showConnectionErrorOnDisplay();
}

static long findCsvLong(const char *csv, const char *key, long fallback) {
  const size_t keyLen = strlen(key);
  const char *p = csv;

  while (*p) {
    while (*p == ';' || *p == ' ' || *p == '\r' || *p == '\n' || *p == '\t') p++;
    if (*p == '\0') break;

    const char *eq = strchr(p, '=');
    if (!eq) break;

    size_t tokenKeyLen = (size_t) (eq - p);
    if (tokenKeyLen == keyLen && strncasecmp(p, key, keyLen) == 0) {
      char *endPtr;
      long v = strtol(eq + 1, &endPtr, 10);
      return endPtr == eq + 1 ? fallback : v;
    }

    const char *sep = strchr(eq + 1, ';');
    if (!sep) break;
    p = sep + 1;
  }

  return fallback;
}

static bool findCsvString(const char *csv, const char *key, char *out, size_t outSize) {
  if (outSize == 0) return false;

  const size_t keyLen = strlen(key);
  const char *p = csv;

  while (*p) {
    while (*p == ';' || *p == ' ' || *p == '\r' || *p == '\n' || *p == '\t') p++;
    if (*p == '\0') break;

    const char *eq = strchr(p, '=');
    if (!eq) break;

    size_t tokenKeyLen = (size_t) (eq - p);
    if (tokenKeyLen == keyLen && strncasecmp(p, key, keyLen) == 0) {
      const char *valueStart = eq + 1;
      const char *sep = strchr(valueStart, ';');
      size_t valueLen = sep ? (size_t) (sep - valueStart) : strlen(valueStart);
      if (valueLen + 1 > outSize) valueLen = outSize - 1;
      memcpy(out, valueStart, valueLen);
      out[valueLen] = '\0';
      return true;
    }

    const char *sep = strchr(eq + 1, ';');
    if (!sep) break;
    p = sep + 1;
  }

  return false;
}

static void applyDigitalOutputsFromCsv(const char *csv) {
  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    long v = findCsvLong(csv, DIGITAL_KEYS[i], -1);
    if (v >= 0) digitalWrite(DIGITAL_PINS[i], relayLogicalToWire(i, (int) v));
  }
}

static void updatePairingDisplayFromCsv(const char *csv) {
  char monitorValue[24];
  if (!findCsvString(csv, "monitor", monitorValue, sizeof(monitorValue))) {
    showConnectionErrorOnDisplay();
    return;
  }

  if (monitorValue[0] == '\0' || strcasecmp(monitorValue, "null") == 0) {
    showConnectionErrorOnDisplay();
    return;
  }

  int hh = 0;
  int mm = 0;
  if (sscanf(monitorValue, "%d:%d", &hh, &mm) == 2 && hh >= 0 && hh <= 23 && mm >= 0 && mm <= 59) {
    pairingDisplay.showNumberDecEx((hh * 100) + mm, 0x40, true, 4, 0);
    return;
  }

  int shown = atoi(monitorValue);
  if (shown > 9999) shown = 9999;
  if (shown < -999) shown = -999;
  pairingDisplay.showNumberDec(shown, false, 4, 0);
}

static bool buildRequestPayload(char *out, size_t outSize, float humidity, float temperature) {
  size_t used = 0;
  bool first = true;

  if (!appendFmt(out, outSize, &used, "controller_id=%s", controllerId)) return false;
  first = false;

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    int v = relayWireToLogical(i, digitalRead(DIGITAL_PINS[i]));
    if (!appendFmt(out, outSize, &used, "%s%s=%d", first ? "" : ";", DIGITAL_KEYS[i], v)) return false;
    first = false;
  }

  for (size_t i = 0; i < ANALOG_COUNT; i++) {
    int v = analogRead(ANALOG_PINS[i]);
    if (!appendFmt(out, outSize, &used, "%s%s=%d", first ? "" : ";", ANALOG_KEYS[i], v)) return false;
    first = false;
  }

  if (!isnan(humidity)) {
    char buf[16];
    dtostrf(humidity, 0, 1, buf);
    char *value = buf;
    while (*value == ' ') value++;
    if (!appendFmt(out, outSize, &used, "%sair_humidity=%s", first ? "" : ";", value)) return false;
    first = false;
  }

  if (!isnan(temperature)) {
    char buf[16];
    dtostrf(temperature, 0, 1, buf);
    char *value = buf;
    while (*value == ' ') value++;
    if (!appendFmt(out, outSize, &used, "%sair_temperature=%s", first ? "" : ";", value)) return false;
  }

  return true;
}

static void drainEsp() {
  while (esp.available() > 0) esp.read();
}

static size_t readEsp(char *out, size_t outSize, unsigned long timeoutMs) {
  size_t used = 0;
  unsigned long startedAt = millis();

  while ((millis() - startedAt) < timeoutMs) {
    while (esp.available() > 0) {
      char c = (char) esp.read();
      if (used + 1 < outSize) {
        out[used++] = c;
        out[used] = '\0';
      }
      Serial.write(c);
    }
  }

  if (outSize > 0) out[used] = '\0';
  return used;
}

static bool sendAt(const char *command, const char *expected, unsigned long timeoutMs) {
  drainEsp();
  Serial.print(F("AT> "));
  Serial.println(command);
  esp.print(command);
  esp.print("\r\n");
  readEsp(atBuffer, sizeof(atBuffer), timeoutMs);
  return strstr(atBuffer, expected) != NULL;
}

static bool waitForPrompt(unsigned long timeoutMs) {
  readEsp(atBuffer, sizeof(atBuffer), timeoutMs);
  return strchr(atBuffer, '>') != NULL;
}

static bool configureSsl() {
  if (strcasecmp(connectionType, "SSL") != 0) {
    return true;
  }

  // SNI is required by many HTTPS virtual hosts, including common nginx setups.
  sendAt("AT+CIPSSLSIZE=4096", "OK", 1000);
  snprintf(commandBuffer, sizeof(commandBuffer), "AT+CIPSSLCSNI=1,\"%s\"", serverHost);
  sendAt(commandBuffer, "OK", 1000);
  return true;
}

static bool ensureWifi() {
  if (!sendAt("AT", "OK", 1000)) return false;
  sendAt("ATE0", "OK", 1000);
  sendAt("AT+CIPMODE=0", "OK", 1000);
  sendAt("AT+CWMODE=1", "OK", 1200);

  if (sendAt("AT+CIPSTATUS", "STATUS:2", 1500) || sendAt("AT+CIPSTATUS", "STATUS:3", 1500)) {
    return true;
  }

  snprintf(commandBuffer, sizeof(commandBuffer), "AT+CWJAP=\"%s\",\"%s\"", wifiSsid, wifiPassword);
  return sendAt(commandBuffer, "WIFI GOT IP", 12000) || strstr(atBuffer, "OK") != NULL;
}

static bool extractHttpBody(const char *raw, char *body, size_t bodySize, int *statusCode) {
  if (statusCode) *statusCode = 0;
  if (bodySize > 0) body[0] = '\0';

  const char *http = strstr(raw, "HTTP/1.");
  if (!http) return false;

  int parsedStatus = 0;
  if (sscanf(http, "HTTP/%*d.%*d %d", &parsedStatus) == 1 && statusCode) {
    *statusCode = parsedStatus;
  }

  const char *bodyStart = strstr(http, "\r\n\r\n");
  if (!bodyStart) return false;
  bodyStart += 4;

  size_t len = strlen(bodyStart);
  const char *closed = strstr(bodyStart, "\r\nCLOSED");
  if (closed) len = (size_t) (closed - bodyStart);
  if (len + 1 > bodySize) len = bodySize - 1;

  memcpy(body, bodyStart, len);
  body[len] = '\0';
  return true;
}

static unsigned int countDigits(unsigned int value) {
  unsigned int digits = 1;
  while (value >= 10) {
    value /= 10;
    digits++;
  }
  return digits;
}

static unsigned int httpRequestLength(const char *payload) {
  unsigned int payloadLen = (unsigned int) strlen(payload);

  return
    strlen("POST ") + strlen(reportPath) + strlen(" HTTP/1.1\r\n") +
    strlen("Host: ") + strlen(serverHost) + strlen("\r\n") +
    strlen("Connection: close\r\n") +
    strlen("X-SmartHome-Format: csv\r\n") +
    strlen("Accept: text/plain\r\n") +
    strlen("Content-Type: text/plain\r\n") +
    strlen("Content-Length: ") + countDigits(payloadLen) + strlen("\r\n\r\n") +
    payloadLen;
}

static void writeHttpRequest(const char *payload) {
  esp.print(F("POST "));
  esp.print(reportPath);
  esp.print(F(" HTTP/1.1\r\n"));
  esp.print(F("Host: "));
  esp.print(serverHost);
  esp.print(F("\r\n"));
  esp.print(F("Connection: close\r\n"));
  esp.print(F("X-SmartHome-Format: csv\r\n"));
  esp.print(F("Accept: text/plain\r\n"));
  esp.print(F("Content-Type: text/plain\r\n"));
  esp.print(F("Content-Length: "));
  esp.print((unsigned int) strlen(payload));
  esp.print(F("\r\n\r\n"));
  esp.print(payload);
}

static bool postViaEsp(const char *payload, char *body, size_t bodySize, int *statusCode) {
  if (!ensureWifi()) {
    Serial.println(F("ESP/Wi-Fi init failed"));
    return false;
  }

  sendAt("AT+CIPCLOSE", "OK", 800);

  configureSsl();

  snprintf(commandBuffer, sizeof(commandBuffer), "AT+CIPSTART=\"%s\",\"%s\",%u", connectionType, serverHost, serverPort);
  if (!sendAt(commandBuffer, "CONNECT", 12000) && strstr(atBuffer, "ALREADY CONNECTED") == NULL) {
    Serial.println(F("CIPSTART failed"));
    return false;
  }
  if (strstr(atBuffer, "CLOSED") != NULL) {
    Serial.println(F("CIPSTART closed"));
    return false;
  }

  unsigned int requestLen = httpRequestLength(payload);

  snprintf(commandBuffer, sizeof(commandBuffer), "AT+CIPSEND=%u", requestLen);
  drainEsp();
  esp.print(commandBuffer);
  esp.print("\r\n");
  Serial.print(F("AT> "));
  Serial.println(commandBuffer);
  if (!waitForPrompt(5000)) {
    Serial.println(F("CIPSEND prompt failed"));
    sendAt("AT+CIPCLOSE", "OK", 800);
    return false;
  }

  drainEsp();
  writeHttpRequest(payload);
  readEsp(atBuffer, sizeof(atBuffer), 10000);
  sendAt("AT+CIPCLOSE", "OK", 800);

  return extractHttpBody(atBuffer, body, bodySize, statusCode);
}

static bool readDhtStable(float *humidity, float *temperature) {
  for (uint8_t attempt = 0; attempt < 3; attempt++) {
    bool force = attempt > 0;
    float h = dht.readHumidity(force);
    float t = dht.readTemperature(force);

    if (!isnan(h) && !isnan(t)) {
      *humidity = h;
      *temperature = t;
      return true;
    }

    delay(1200);
  }

  return false;
}

static void postMeasurements() {
  float humidity = NAN;
  float temperature = NAN;
  bool dhtOk = readDhtStable(&humidity, &temperature);

  if (!dhtOk) {
    Serial.println(F("DHT22 read failed"));
    if (hasLastDhtValues) {
      humidity = lastHumidity;
      temperature = lastTemperature;
      Serial.println(F("Using last valid DHT22 values"));
    }
  } else {
    hasLastDhtValues = true;
    lastHumidity = humidity;
    lastTemperature = temperature;
  }

  if (!buildRequestPayload(requestPayload, sizeof(requestPayload), humidity, temperature)) {
    Serial.println(F("Payload build failed"));
    return;
  }

  Serial.println(F("Request CSV:"));
  Serial.println(requestPayload);

  int httpStatusCode = 0;
  if (!postViaEsp(requestPayload, responseBody, sizeof(responseBody), &httpStatusCode)) {
    Serial.println(F("HTTP via ESP failed"));
    setAllRelaysOff();
    showConnectionErrorOnDisplay();
    return;
  }

  Serial.println(F("Response CSV:"));
  Serial.println(responseBody);

  if (httpStatusCode >= 400) {
    Serial.print(F("HTTP error: "));
    Serial.println(httpStatusCode);
    setAllRelaysOff();
    showHttpStatusErrorOnDisplay(httpStatusCode);
    return;
  }

  long nextIntervalSec = findCsvLong(responseBody, "send_interval_seconds", -1);
  if (nextIntervalSec >= 1 && nextIntervalSec <= 3600) {
    sendIntervalMs = (unsigned long) nextIntervalSec * 1000UL;
  }

  applyDigitalOutputsFromCsv(responseBody);
  updatePairingDisplayFromCsv(responseBody);
  Serial.println(F("Request finished"));
  Serial.println();
}

void setup() {
  Serial.begin(9600);
  esp.begin(ESP_BAUD);

  delay(1000);
  Serial.println(F("Starting greenhouse ESP8266 client..."));
  Serial.print(F("Server: "));
  Serial.print(serverHost);
  Serial.print(F(":"));
  Serial.println(serverPort);
  Serial.print(F("Default send interval (ms): "));
  Serial.println(sendIntervalMs);

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    pinMode(DIGITAL_PINS[i], OUTPUT);
  }
  setAllRelaysOff();

  pairingDisplay.setBrightness(0x0f, true);
  showConnectionErrorOnDisplay();

  dht.begin();
  lastSendMs = millis();
  delay(1500);
}

void loop() {
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
