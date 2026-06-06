/*
  AiDvor station controller v1, Uno side.

  Uno responsibilities:
    - read station sensors
    - report CSV telemetry to ESP8266 over UART
    - apply relay commands returned by ESP8266
    - show monitor value on TM1637

  Wiring:
    D2  -> DHT22 DATA
    D3  -> relay_1
    D4  -> relay_2
    D5  -> relay_3
    D6  -> relay_4
    D7  -> relay_5
    D8  -> relay_6
    D9  -> relay_7
    D10 -> ESP TX (Arduino RX)
    D11 -> ESP RX (Arduino TX through 5V-to-3.3V divider)
    D12 -> relay_8
    D13 -> FS-IR02 tank level digital output
    A0  -> DS18B20 data
    A1  -> photoresistor analog output
    A2  -> TM1637 DIO
    A3  -> TM1637 CLK
    A4  -> I2C SDA (BMP280)
    A5  -> I2C SCL (BMP280)

  Serial Monitor:
    9600 baud
*/

#include <SoftwareSerial.h>
#include <DHT.h>
#include <TM1637Display.h>
#include <Wire.h>
#include <Adafruit_BMP280.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <stdarg.h>
#include <stdlib.h>

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

const uint8_t DS18B20_PIN = A0;
OneWire oneWire(DS18B20_PIN);
DallasTemperature waterTemperatureSensor(&oneWire);
bool hasLastWaterTemperature = false;
float lastWaterTemperature = NAN;

Adafruit_BMP280 bmp;
bool bmpReady = false;
bool hasLastAtmPressure = false;
float lastAtmPressure = NAN;

const uint8_t TANK_LEVEL_PIN = 13;
const bool TANK_LEVEL_ACTIVE_LOW = true;
const uint8_t LIGHT_LEVEL_PIN = A1;

const uint8_t TM1637_DIO_PIN = A2;
const uint8_t TM1637_CLK_PIN = A3;
TM1637Display pairingDisplay(TM1637_CLK_PIN, TM1637_DIO_PIN);

const uint8_t DIGITAL_PINS[] = { 3, 4, 5, 6, 7, 8, 9, 12 };
const char *DIGITAL_KEYS[] = {
  "relay_1", "relay_2", "relay_3", "relay_4",
  "relay_5", "relay_6", "relay_7", "relay_8"
};
const bool RELAY_ACTIVE_LOW[] = { true, true, true, true, true, true, true, true };

const size_t DIGITAL_COUNT = sizeof(DIGITAL_PINS) / sizeof(DIGITAL_PINS[0]);
const unsigned long RELAY_MIN_WATCHDOG_MS = 300000UL;
const unsigned long RELAY_RESPONSE_GRACE_MS = 30000UL;

unsigned long lastSendMs = 0;
unsigned long relayTurnedOnAtMs[DIGITAL_COUNT] = { 0 };
char requestPayload[210];
char responseBody[150];
char asyncEspLine[150];

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

static int tankLevelLogical() {
  int wire = digitalRead(TANK_LEVEL_PIN) == HIGH ? 1 : 0;
  if (!TANK_LEVEL_ACTIVE_LOW) return wire;
  return wire > 0 ? 0 : 1;
}

static void setAllRelaysOff() {
  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    digitalWrite(DIGITAL_PINS[i], relayLogicalToWire(i, 0));
    relayTurnedOnAtMs[i] = 0;
  }
}

static void setRelayLogical(size_t relayIndex, int logicalValue) {
  if (relayIndex >= DIGITAL_COUNT) return;

  int logical = logicalValue > 0 ? 1 : 0;
  digitalWrite(DIGITAL_PINS[relayIndex], relayLogicalToWire(relayIndex, logical));
  relayTurnedOnAtMs[relayIndex] = logical > 0 ? millis() : 0;
}

static unsigned long relayWatchdogMs() {
  unsigned long requiredResponseTimeMs = sendIntervalMs + RELAY_RESPONSE_GRACE_MS;
  return requiredResponseTimeMs > RELAY_MIN_WATCHDOG_MS
    ? requiredResponseTimeMs
    : RELAY_MIN_WATCHDOG_MS;
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
    if (v == 0 || v == 1) {
      setRelayLogical(i, (int) v);
    }
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

static bool appendFloatReading(char *out, size_t outSize, size_t *used, bool *first, const char *key, float value, uint8_t decimals) {
  if (isnan(value)) return true;

  char buf[18];
  dtostrf(value, 0, decimals, buf);
  char *formatted = buf;
  while (*formatted == ' ') formatted++;

  if (!appendFmt(out, outSize, used, "%s%s=%s", *first ? "" : ";", key, formatted)) return false;
  *first = false;
  return true;
}

static bool buildRequestPayload(char *out, size_t outSize, float humidity, float airTemperature, float atmPressure, float waterTemperature) {
  size_t used = 0;
  bool first = true;

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    int v = relayWireToLogical(i, digitalRead(DIGITAL_PINS[i]));
    if (!appendFmt(out, outSize, &used, "%s%s=%d", first ? "" : ";", DIGITAL_KEYS[i], v)) return false;
    first = false;
  }

  if (!appendFmt(out, outSize, &used, "%stank_level=%d", first ? "" : ";", tankLevelLogical())) return false;
  first = false;

  if (!appendFmt(out, outSize, &used, "%slight_level_raw=%d", first ? "" : ";", analogRead(LIGHT_LEVEL_PIN))) return false;
  first = false;

  if (!appendFloatReading(out, outSize, &used, &first, "air_humidity", humidity, 1)) return false;
  if (!appendFloatReading(out, outSize, &used, &first, "air_temperature", airTemperature, 1)) return false;
  if (!appendFloatReading(out, outSize, &used, &first, "atm_pressure", atmPressure, 1)) return false;
  if (!appendFloatReading(out, outSize, &used, &first, "water_temperature", waterTemperature, 1)) return false;

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

static bool readBmpPressureStable(float *pressureHpa) {
  if (!bmpReady) return false;

  for (uint8_t attempt = 0; attempt < 3; attempt++) {
    float pressure = bmp.readPressure() / 100.0F;
    if (!isnan(pressure) && pressure > 300.0F && pressure < 1200.0F) {
      *pressureHpa = pressure;
      return true;
    }
    delay(150);
  }

  return false;
}

static bool readWaterTemperatureStable(float *temperature) {
  for (uint8_t attempt = 0; attempt < 3; attempt++) {
    waterTemperatureSensor.requestTemperatures();
    float t = waterTemperatureSensor.getTempCByIndex(0);
    if (t != DEVICE_DISCONNECTED_C && !isnan(t)) {
      *temperature = t;
      return true;
    }
    delay(250);
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

static bool readEspLineNonBlocking(char *out, size_t outSize) {
  static size_t used = 0;

  if (outSize == 0) return false;

  while (esp.available() > 0) {
    char c = (char) esp.read();
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
      return false;
    }
  }

  return false;
}

static void applyEspCsvCommand(const char *csv) {
  long httpStatus = findCsvLong(csv, "http_status", 200);
  if (httpStatus >= 400) {
    showHttpStatusErrorOnDisplay((int) httpStatus);
    return;
  }

  long nextIntervalSec = findCsvLong(csv, "send_interval_seconds", -1);
  if (nextIntervalSec >= 1 && nextIntervalSec <= 3600) {
    sendIntervalMs = (unsigned long) nextIntervalSec * 1000UL;
  }

  applyDigitalOutputsFromCsv(csv);
  updatePairingDisplayFromCsv(csv);
}

static bool exchangeWithEsp(const char *request, char *response, size_t responseSize) {
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
  float airTemperature = NAN;
  float atmPressure = NAN;
  float waterTemperature = NAN;

  if (!readDhtStable(&humidity, &airTemperature)) {
    Serial.println(F("DHT22 read failed"));
    if (hasLastDhtValues) {
      humidity = lastHumidity;
      airTemperature = lastTemperature;
      Serial.println(F("Using last valid DHT22 values"));
    }
  } else {
    hasLastDhtValues = true;
    lastHumidity = humidity;
    lastTemperature = airTemperature;
  }

  if (!readBmpPressureStable(&atmPressure)) {
    Serial.println(F("BMP280 read failed"));
    if (hasLastAtmPressure) {
      atmPressure = lastAtmPressure;
      Serial.println(F("Using last valid BMP280 pressure"));
    }
  } else {
    hasLastAtmPressure = true;
    lastAtmPressure = atmPressure;
  }

  if (!readWaterTemperatureStable(&waterTemperature)) {
    Serial.println(F("DS18B20 read failed"));
    if (hasLastWaterTemperature) {
      waterTemperature = lastWaterTemperature;
      Serial.println(F("Using last valid DS18B20 temperature"));
    }
  } else {
    hasLastWaterTemperature = true;
    lastWaterTemperature = waterTemperature;
  }

  if (!buildRequestPayload(requestPayload, sizeof(requestPayload), humidity, airTemperature, atmPressure, waterTemperature)) {
    Serial.println(F("Payload build failed"));
    return;
  }

  if (!exchangeWithEsp(requestPayload, responseBody, sizeof(responseBody))) {
    showConnectionErrorOnDisplay();
    return;
  }

  applyEspCsvCommand(responseBody);
}

static void checkRelayWatchdogs() {
  unsigned long now = millis();
  unsigned long watchdogMs = relayWatchdogMs();

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    if (relayWireToLogical(i, digitalRead(DIGITAL_PINS[i])) <= 0) {
      relayTurnedOnAtMs[i] = 0;
      continue;
    }

    if (relayTurnedOnAtMs[i] == 0) {
      relayTurnedOnAtMs[i] = now;
      continue;
    }

    if ((now - relayTurnedOnAtMs[i]) >= watchdogMs) {
      setRelayLogical(i, 0);
      showConnectionErrorOnDisplay();
    }
  }
}

void setup() {
  Serial.begin(9600);
  esp.begin(ESP_BAUD);

  delay(1000);
  Serial.println(F("Starting station UART controller v1..."));
  Serial.print(F("Default send interval (ms): "));
  Serial.println(sendIntervalMs);

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    pinMode(DIGITAL_PINS[i], OUTPUT);
  }
  setAllRelaysOff();

  pinMode(TANK_LEVEL_PIN, INPUT_PULLUP);

  pairingDisplay.setBrightness(0x0f, true);
  showConnectionErrorOnDisplay();

  dht.begin();
  waterTemperatureSensor.begin();
  Wire.begin();
  bmpReady = bmp.begin(0x76) || bmp.begin(0x77);
  if (!bmpReady) {
    Serial.println(F("BMP280 not found"));
  }

  lastSendMs = millis();
}

void loop() {
  unsigned long now = millis();

  if (readEspLineNonBlocking(asyncEspLine, sizeof(asyncEspLine))) {
    Serial.println(F("ESP async->UNO:"));
    Serial.println(asyncEspLine);
    applyEspCsvCommand(asyncEspLine);
  }

  checkRelayWatchdogs();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
