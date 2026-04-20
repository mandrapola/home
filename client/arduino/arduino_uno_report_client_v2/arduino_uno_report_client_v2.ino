/*
  Smart Home Arduino client (v2, CSV protocol)
  Hardware: Arduino Uno + Ethernet Shield + DHT11 on D2

  CSV request:
    controller_id=<uuid>;relay_1=0;relay_2=1;...;air_humidity=41.0;air_temperature=23.5

  CSV response:
    send_interval_seconds=5;relay_1=1;relay_2=0;relay_3=1;relay_4=0
*/

#include <SPI.h>
#include <Ethernet.h>
#include <DHT.h>
#include <TM1637Display.h>
#include <stdarg.h>
#include <stdlib.h>

// ===== Network =====
byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0x01 };
char serverHost[] = "192.168.0.201";
const uint16_t serverPort = 3001;
const char reportPath[] = "/api/controller/report";

// ===== Controller =====
const char controllerId[] = "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f";
unsigned long sendIntervalMs = 30000UL;

// ===== Sensors =====
const uint8_t DHT_PIN = 2;
#define DHTTYPE DHT11
DHT dht(DHT_PIN, DHTTYPE);
bool hasLastDhtValues = false;
float lastHumidity = NAN;
float lastTemperature = NAN;
const uint8_t TM1637_DIO_PIN = 7;
const uint8_t TM1637_CLK_PIN = 8;
TM1637Display pairingDisplay(TM1637_CLK_PIN, TM1637_DIO_PIN);

const uint8_t DIGITAL_PINS[] = { 3, 9, 5, 6 };
const char *DIGITAL_KEYS[] = { "relay_1", "relay_2", "relay_3", "relay_4" };
const uint8_t ANALOG_PINS[] = { A0, A1, A2, A3, A4, A5 };
const char *ANALOG_KEYS[] = {
  "soil_moisture_raw",
  "light_level_raw",
  "tank_level_raw",
  "water_pressure_raw",
  "analog_spare_1_raw",
  "analog_spare_2_raw"
};

const size_t DIGITAL_COUNT = sizeof(DIGITAL_PINS) / sizeof(DIGITAL_PINS[0]);
const size_t ANALOG_COUNT = sizeof(ANALOG_PINS) / sizeof(ANALOG_PINS[0]);

EthernetClient client;
unsigned long lastSendMs = 0;

char requestPayload[320];
char responseBody[220];

static bool appendFmt(char *dst, size_t dstSize, size_t *used, const char *fmt, ...) {
  if (*used >= dstSize) {
    return false;
  }

  va_list args;
  va_start(args, fmt);
  int written = vsnprintf(dst + *used, dstSize - *used, fmt, args);
  va_end(args);

  if (written < 0 || (size_t) written >= (dstSize - *used)) {
    return false;
  }

  *used += (size_t) written;
  return true;
}

static bool buildRequestPayload(char *out, size_t outSize, float humidity, float temperature) {
  size_t used = 0;
  bool first = true;

  if (!appendFmt(out, outSize, &used, "controller_id=%s", controllerId)) {
    return false;
  }
  first = false;

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    int v = digitalRead(DIGITAL_PINS[i]) == HIGH ? 1 : 0;
    if (!appendFmt(out, outSize, &used, "%s%s=%d", first ? "" : ";", DIGITAL_KEYS[i], v)) {
      return false;
    }
    first = false;
  }

  for (size_t i = 0; i < ANALOG_COUNT; i++) {
    int v = analogRead(ANALOG_PINS[i]);
    if (!appendFmt(out, outSize, &used, "%s%s=%d", first ? "" : ";", ANALOG_KEYS[i], v)) {
      return false;
    }
    first = false;
  }

  if (!isnan(humidity)) {
    char humidityBuf[16];
    dtostrf(humidity, 0, 1, humidityBuf);
    char *humidityValue = humidityBuf;
    while (*humidityValue == ' ') {
      humidityValue++;
    }
    if (!appendFmt(out, outSize, &used, "%sair_humidity=%s", first ? "" : ";", humidityValue)) {
      return false;
    }
    first = false;
  }

  if (!isnan(temperature)) {
    char temperatureBuf[16];
    dtostrf(temperature, 0, 1, temperatureBuf);
    char *temperatureValue = temperatureBuf;
    while (*temperatureValue == ' ') {
      temperatureValue++;
    }
    if (!appendFmt(out, outSize, &used, "%sair_temperature=%s", first ? "" : ";", temperatureValue)) {
      return false;
    }
  }

  return true;
}

static int keyToDigitalPin(const char *key) {
  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    if (strcasecmp(key, DIGITAL_KEYS[i]) == 0) {
      return DIGITAL_PINS[i];
    }
  }
  return -1;
}

static long findCsvLong(const char *csv, const char *key, long fallback) {
  const size_t keyLen = strlen(key);
  const char *p = csv;

  while (*p) {
    while (*p == ';' || *p == ' ' || *p == '\r' || *p == '\n' || *p == '\t') {
      p++;
    }
    if (*p == '\0') {
      break;
    }

    const char *eq = strchr(p, '=');
    if (!eq) {
      break;
    }

    size_t tokenKeyLen = (size_t) (eq - p);
    if (tokenKeyLen == keyLen && strncasecmp(p, key, keyLen) == 0) {
      char *endPtr;
      long v = strtol(eq + 1, &endPtr, 10);
      if (endPtr == eq + 1) {
        return fallback;
      }
      return v;
    }

    const char *sep = strchr(eq + 1, ';');
    if (!sep) {
      break;
    }
    p = sep + 1;
  }

  return fallback;
}

static bool findCsvString(const char *csv, const char *key, char *out, size_t outSize) {
  if (outSize == 0) {
    return false;
  }

  const size_t keyLen = strlen(key);
  const char *p = csv;

  while (*p) {
    while (*p == ';' || *p == ' ' || *p == '\r' || *p == '\n' || *p == '\t') {
      p++;
    }
    if (*p == '\0') {
      break;
    }

    const char *eq = strchr(p, '=');
    if (!eq) {
      break;
    }

    size_t tokenKeyLen = (size_t) (eq - p);
    if (tokenKeyLen == keyLen && strncasecmp(p, key, keyLen) == 0) {
      const char *valueStart = eq + 1;
      const char *sep = strchr(valueStart, ';');
      size_t valueLen = sep ? (size_t) (sep - valueStart) : strlen(valueStart);
      if (valueLen + 1 > outSize) {
        valueLen = outSize - 1;
      }
      memcpy(out, valueStart, valueLen);
      out[valueLen] = '\0';
      return true;
    }

    const char *sep = strchr(eq + 1, ';');
    if (!sep) {
      break;
    }
    p = sep + 1;
  }

  return false;
}

static void applyDigitalOutputsFromCsv(const char *csv) {
  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    long v = findCsvLong(csv, DIGITAL_KEYS[i], -1);
    if (v < 0) {
      continue;
    }
    digitalWrite(DIGITAL_PINS[i], v > 0 ? HIGH : LOW);
  }
}

static void updatePairingDisplayFromCsv(const char *csv) {
  char monitorValue[24];
  if (!findCsvString(csv, "monitor", monitorValue, sizeof(monitorValue))) {
    pairingDisplay.clear();
    return;
  }

  if (monitorValue[0] == '\0' || strcasecmp(monitorValue, "null") == 0) {
    pairingDisplay.clear();
    return;
  }

  bool allDigits = true;
  size_t monitorLen = strlen(monitorValue);
  if (monitorLen == 4) {
    for (size_t i = 0; i < monitorLen; i++) {
      if (monitorValue[i] < '0' || monitorValue[i] > '9') {
        allDigits = false;
        break;
      }
    }
    if (allDigits) {
      int codeValue = atoi(monitorValue);
      pairingDisplay.showNumberDecEx(codeValue, 0, true, 4, 0);
      return;
    }
  }

  int hh = 0;
  int mm = 0;
  if (sscanf(monitorValue, "%d:%d", &hh, &mm) == 2 && hh >= 0 && hh <= 23 && mm >= 0 && mm <= 59) {
    pairingDisplay.showNumberDecEx((hh * 100) + mm, 0x40, true, 4, 0);
    return;
  }

  bool hasDigit = false;
  for (size_t i = 0; monitorValue[i] != '\0'; i++) {
    if (monitorValue[i] >= '0' && monitorValue[i] <= '9') {
      hasDigit = true;
      break;
    }
  }
  if (!hasDigit) {
    pairingDisplay.clear();
    return;
  }

  float numeric = atof(monitorValue);
  int shown = (int) (numeric >= 0.0f ? (numeric + 0.5f) : (numeric - 0.5f));

  if (shown > 9999) shown = 9999;
  if (shown < -999) shown = -999;
  pairingDisplay.showNumberDec(shown, false, 4, 0);
}

static void showConnectionErrorOnDisplay() {
  // "Err " on 4-digit TM1637.
  const uint8_t SEG_E_CHAR = (uint8_t) (SEG_A | SEG_D | SEG_E | SEG_F | SEG_G);
  const uint8_t SEG_r_CHAR = (uint8_t) (SEG_E | SEG_G);
  const uint8_t segments[] = { SEG_E_CHAR, SEG_r_CHAR, SEG_r_CHAR, 0x00 };
  pairingDisplay.setSegments(segments);
}

static bool readHttpBody(EthernetClient &c, char *out, size_t outSize) {
  bool inBody = false;
  size_t bodyLen = 0;
  char window[4] = { 0, 0, 0, 0 };

  unsigned long startedAt = millis();
  while ((millis() - startedAt) < 5000UL) {
    while (c.available()) {
      char ch = (char) c.read();
      if (!inBody) {
        window[0] = window[1];
        window[1] = window[2];
        window[2] = window[3];
        window[3] = ch;
        if (window[0] == '\r' && window[1] == '\n' && window[2] == '\r' && window[3] == '\n') {
          inBody = true;
        }
      } else {
        if (bodyLen + 1 < outSize) {
          out[bodyLen++] = ch;
        }
      }
    }

    if (!c.connected()) {
      break;
    }
  }

  out[bodyLen] = '\0';
  return inBody;
}

static bool readDht11Stable(float *humidity, float *temperature) {
  for (uint8_t attempt = 0; attempt < 3; attempt++) {
    bool force = attempt > 0;
    float h = dht.readHumidity(force);
    float t = dht.readTemperature(force);

    if (!isnan(h) && !isnan(t)) {
      *humidity = h;
      *temperature = t;
      return true;
    }

    // DHT11 often needs an extra moment after a failed frame.
    delay(1200);
  }

  return false;
}

static void postMeasurements() {
  float humidity = NAN;
  float temperature = NAN;
  bool dhtOk = readDht11Stable(&humidity, &temperature);

  if (!dhtOk) {
    Serial.println(F("DHT11 read failed"));
    if (hasLastDhtValues) {
      humidity = lastHumidity;
      temperature = lastTemperature;
      Serial.println(F("Using last valid DHT11 values"));
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

  if (!client.connect(serverHost, serverPort)) {
    Serial.println(F("Connection failed"));
    showConnectionErrorOnDisplay();
    return;
  }

  const size_t payloadLen = strlen(requestPayload);

  client.print(F("POST "));
  client.print(reportPath);
  client.println(F(" HTTP/1.1"));
  client.print(F("Host: "));
  client.println(serverHost);
  client.println(F("Connection: close"));
  client.println(F("X-SmartHome-Format: csv"));
  client.println(F("Accept: text/plain"));
  client.println(F("Content-Type: text/plain"));
  client.print(F("Content-Length: "));
  client.println((unsigned long) payloadLen);
  client.println();
  client.print(requestPayload);

  Serial.println(F("Request sent"));

  if (!readHttpBody(client, responseBody, sizeof(responseBody))) {
    Serial.println(F("Invalid HTTP response"));
    client.stop();
    return;
  }

  client.stop();

  Serial.println(F("Response CSV:"));
  Serial.println(responseBody);

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
  while (!Serial) {
    ;
  }

  Serial.println(F("Starting Ethernet..."));
  if (Ethernet.begin(mac) == 0) {
    Serial.println(F("DHCP failed. Static fallback disabled."));
  }

  delay(1000);
  Serial.print(F("Local IP: "));
  Serial.println(Ethernet.localIP());
  Serial.print(F("Server: "));
  Serial.print(serverHost);
  Serial.print(F(":"));
  Serial.println(serverPort);
  Serial.print(F("Default send interval (ms): "));
  Serial.println(sendIntervalMs);

  for (size_t i = 0; i < DIGITAL_COUNT; i++) {
    pinMode(DIGITAL_PINS[i], OUTPUT);
    digitalWrite(DIGITAL_PINS[i], LOW);
  }

  pairingDisplay.setBrightness(0x0f, true);
  pairingDisplay.clear();

  dht.begin();
  delay(1500);
}

void loop() {
  Ethernet.maintain();
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
