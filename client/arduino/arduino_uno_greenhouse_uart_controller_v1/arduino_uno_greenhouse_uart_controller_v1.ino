/*
  AiDvor greenhouse controller, Uno side.

  Uno responsibilities:
    - read greenhouse sensors
    - report CSV telemetry to ESP8266 over UART
    - apply relay commands returned by ESP8266

  ESP8266 responsibilities:
    - Wi-Fi
    - HTTPS request to AiDvor server

  Wiring:
    ESP TX -> Arduino D10
    ESP RX <- Arduino D11 through a 5V-to-3.3V divider or level shifter
    GND    -> GND

  Serial Monitor:
    9600 baud
*/

#include <SoftwareSerial.h>
#include <DHT.h>
#include <TM1637Display.h>
#include <stdarg.h>
#include <stdlib.h>

const char controllerId[] = "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f";
unsigned long sendIntervalMs = 30000UL;

const uint8_t ESP_RX_PIN = 10; // Arduino receives from ESP TX.
const uint8_t ESP_TX_PIN = 11; // Arduino sends to ESP RX.
const unsigned long ESP_BAUD = 9600;
SoftwareSerial esp(ESP_RX_PIN, ESP_TX_PIN);

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
char responseBody[160];

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

static bool readEspLine(char *out, size_t outSize, unsigned long timeoutMs) {
  size_t used = 0;
  unsigned long startedAt = millis();

  if (outSize == 0) return false;
  out[0] = '\0';

  while ((millis() - startedAt) < timeoutMs) {
    while (esp.available() > 0) {
      char c = (char) esp.read();
      if (c == '\r') continue;
      if (c == '\n') {
        out[used] = '\0';
        return used > 0;
      }
      if (used + 1 < outSize) {
        out[used++] = c;
        out[used] = '\0';
      }
    }
  }

  return false;
}

static bool exchangeWithEsp(const char *request, char *response, size_t responseSize) {
  while (esp.available() > 0) esp.read();

  Serial.println(F("UNO->ESP:"));
  Serial.println(request);
  esp.println(request);

  if (!readEspLine(response, responseSize, 20000UL)) {
    Serial.println(F("ESP response timeout"));
    return false;
  }

  Serial.println(F("ESP->UNO:"));
  Serial.println(response);
  return true;
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

  if (!exchangeWithEsp(requestPayload, responseBody, sizeof(responseBody))) {
    setAllRelaysOff();
    showConnectionErrorOnDisplay();
    return;
  }

  long httpStatus = findCsvLong(responseBody, "http_status", 200);
  if (httpStatus >= 400) {
    showHttpStatusErrorOnDisplay((int) httpStatus);
    setAllRelaysOff();
    return;
  }

  long nextIntervalSec = findCsvLong(responseBody, "send_interval_seconds", -1);
  if (nextIntervalSec >= 1 && nextIntervalSec <= 3600) {
    sendIntervalMs = (unsigned long) nextIntervalSec * 1000UL;
  }

  applyDigitalOutputsFromCsv(responseBody);
  updatePairingDisplayFromCsv(responseBody);
}

void setup() {
  Serial.begin(9600);
  esp.begin(ESP_BAUD);

  delay(1000);
  Serial.println(F("Starting greenhouse UART controller..."));
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
}

void loop() {
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
