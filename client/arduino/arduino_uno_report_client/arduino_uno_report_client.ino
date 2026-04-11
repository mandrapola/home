/*
  Arduino Uno -> Smart Home API client
  Sends port states to:
    POST /api/controller/report
  every 30 seconds by default.

  Hardware target:
    Arduino Uno + Ethernet Shield (W5100/W5500)
*/

#include <SPI.h>
#include <Ethernet.h>
#include <DHT.h>

// ===== Network settings =====
byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0x01 };
char serverHost[] = "192.168.0.201";  // IP сервера/gateway в локальной сети
const int serverPort = 3001;          // API port: /api/controller/report (getaway)
const char reportPath[] = "/api/controller/report";

// ===== Controller settings =====
const char controllerId[] = "019d5529-ceee-7748-b9a8-a2e3ce1e8b8f";
unsigned long sendIntervalMs = 30000UL;  // 30 seconds by default

// ===== DHT11 settings =====
const uint8_t DHT_PIN = 2;
#define DHTTYPE DHT11
DHT dht(DHT_PIN, DHTTYPE);

// ===== TM1637 (4-digit display) =====
const uint8_t TM1637_DIO_PIN = 7;
const uint8_t TM1637_CLK_PIN = 8;
#define USE_TM1637 0  // TM1637 не установлен

// Telemetry map for greenhouse controller.
// D0/D1 are reserved for USB Serial.
// D10-D13 are reserved by the Ethernet shield SPI bus.
// D7/D8 are used by TM1637 display.
const uint8_t DIGITAL_PINS_TO_REPORT[] = { 3, 9, 5, 6 };
const char *DIGITAL_READING_KEYS[] = {
  "relay_1",
  "relay_2",
  "relay_3",
  "relay_4"
};
const uint8_t ANALOG_PINS_TO_REPORT[] = { A0, A1, A2, A3, A4, A5 };
const char *ANALOG_READING_KEYS[] = {
  "soil_moisture_raw",
  "light_level_raw",
  "tank_level_raw",
  "water_pressure_raw",
  "analog_spare_1_raw",
  "analog_spare_2_raw"
};
const size_t DIGITAL_PIN_COUNT = sizeof(DIGITAL_PINS_TO_REPORT) / sizeof(DIGITAL_PINS_TO_REPORT[0]);
const size_t ANALOG_PIN_COUNT = sizeof(ANALOG_PINS_TO_REPORT) / sizeof(ANALOG_PINS_TO_REPORT[0]);

EthernetClient client;
unsigned long lastSendMs = 0;


String lastShownPairingCode = "";

const uint8_t TM1637_DIGITS[10] = {
  0x3F,  // 0
  0x06,  // 1
  0x5B,  // 2
  0x4F,  // 3
  0x66,  // 4
  0x6D,  // 5
  0x7D,  // 6
  0x07,  // 7
  0x7F,  // 8
  0x6F   // 9
};

void tm1637Delay() {
  delayMicroseconds(5);
}

void tm1637Start() {
  if (!USE_TM1637) {
    return;
  }
  digitalWrite(TM1637_DIO_PIN, HIGH);
  digitalWrite(TM1637_CLK_PIN, HIGH);
  tm1637Delay();
  digitalWrite(TM1637_DIO_PIN, LOW);
}

void tm1637Stop() {
  if (!USE_TM1637) {
    return;
  }
  digitalWrite(TM1637_CLK_PIN, LOW);
  tm1637Delay();
  digitalWrite(TM1637_DIO_PIN, LOW);
  tm1637Delay();
  digitalWrite(TM1637_CLK_PIN, HIGH);
  tm1637Delay();
  digitalWrite(TM1637_DIO_PIN, HIGH);
}

bool tm1637WriteByte(uint8_t value) {
  if (!USE_TM1637) {
    return false;
  }
  for (uint8_t i = 0; i < 8; i++) {
    digitalWrite(TM1637_CLK_PIN, LOW);
    if (value & 0x01) {
      digitalWrite(TM1637_DIO_PIN, HIGH);
    } else {
      digitalWrite(TM1637_DIO_PIN, LOW);
    }
    tm1637Delay();
    digitalWrite(TM1637_CLK_PIN, HIGH);
    tm1637Delay();
    value >>= 1;
  }

  digitalWrite(TM1637_CLK_PIN, LOW);
  pinMode(TM1637_DIO_PIN, INPUT_PULLUP);
  tm1637Delay();
  digitalWrite(TM1637_CLK_PIN, HIGH);
  tm1637Delay();
  const bool ack = (digitalRead(TM1637_DIO_PIN) == 0);
  digitalWrite(TM1637_CLK_PIN, LOW);
  pinMode(TM1637_DIO_PIN, OUTPUT);
  return ack;
}

void tm1637SetBrightness(uint8_t brightness) {
  if (!USE_TM1637) {
    return;
  }
  tm1637Start();
  tm1637WriteByte(0x88 | (brightness & 0x07));
  tm1637Stop();
}

void tm1637SetSegments(const uint8_t segments[4]) {
  if (!USE_TM1637) {
    return;
  }
  tm1637Start();
  tm1637WriteByte(0x40);
  tm1637Stop();

  tm1637Start();
  tm1637WriteByte(0xC0);
  for (uint8_t i = 0; i < 4; i++) {
    tm1637WriteByte(segments[i]);
  }
  tm1637Stop();
}

void displayFourDigits(const char *digits) {
  uint8_t segments[4] = { 0, 0, 0, 0 };
  for (uint8_t i = 0; i < 4; i++) {
    const char c = digits[i];
    if (c >= '0' && c <= '9') {
      segments[i] = TM1637_DIGITS[c - '0'];
    } else if (c == '-') {
      segments[i] = 0x40;
    } else {
      segments[i] = 0x00;
    }
  }
  tm1637SetSegments(segments);
}

void displayPairingCode(const String &pairingCode) {
  if (!USE_TM1637) {
    return;
  }
  if (pairingCode.length() == 4) {
    char digits[5];
    pairingCode.toCharArray(digits, sizeof(digits));
    displayFourDigits(digits);
    return;
  }

  displayFourDigits("----");
}

size_t intReadingJsonLength(const char *pinLabel, int value) {
  char item[64];
  const int written = snprintf(item, sizeof(item), "{\"pin\":\"%s\",\"value\":%d}", pinLabel, value);
  return written > 0 ? (size_t)written : 0;
}

size_t floatReadingJsonLength(const char *pinLabel, float value) {
  char valueBuffer[16];
  dtostrf(value, 0, 1, valueBuffer);
  char *trimmedValue = valueBuffer;
  while (*trimmedValue == ' ') {
    trimmedValue++;
  }

  char item[72];
  const int written = snprintf(item, sizeof(item), "{\"pin\":\"%s\",\"value\":%s}", pinLabel, trimmedValue);
  return written > 0 ? (size_t)written : 0;
}

void emitPayloadChunk(Print *netOut, Print *logOut, const char *chunk, size_t len) {
  if (len == 0) {
    return;
  }
  if (netOut != NULL) {
    netOut->write((const uint8_t *)chunk, len);
  }
  if (logOut != NULL) {
    logOut->write((const uint8_t *)chunk, len);
  }
}

void emitPayload(
  Print *netOut,
  Print *logOut,
  const char *requestHeader,
  const int *digitalValues,
  const int *analogValues,
  float airHumidity,
  float airTemperature,
  bool hasHumidity,
  bool hasTemperature
) {
  emitPayloadChunk(netOut, logOut, requestHeader, strlen(requestHeader));

  bool isFirst = true;
  char item[80];

  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    const int written = snprintf(item, sizeof(item), "{\"pin\":\"%s\",\"value\":%d}", DIGITAL_READING_KEYS[i], digitalValues[i]);
    if (written <= 0 || (size_t)written >= sizeof(item)) {
      return;
    }
    if (!isFirst) {
      emitPayloadChunk(netOut, logOut, ",", 1);
    }
    emitPayloadChunk(netOut, logOut, item, (size_t)written);
    isFirst = false;
  }

  for (size_t i = 0; i < ANALOG_PIN_COUNT; i++) {
    const int written = snprintf(item, sizeof(item), "{\"pin\":\"%s\",\"value\":%d}", ANALOG_READING_KEYS[i], analogValues[i]);
    if (written <= 0 || (size_t)written >= sizeof(item)) {
      return;
    }
    if (!isFirst) {
      emitPayloadChunk(netOut, logOut, ",", 1);
    }
    emitPayloadChunk(netOut, logOut, item, (size_t)written);
    isFirst = false;
  }

  if (hasHumidity) {
    char humidityBuffer[16];
    dtostrf(airHumidity, 0, 1, humidityBuffer);
    char *trimmedHumidity = humidityBuffer;
    while (*trimmedHumidity == ' ') {
      trimmedHumidity++;
    }
    const int written = snprintf(item, sizeof(item), "{\"pin\":\"air_humidity\",\"value\":%s}", trimmedHumidity);
    if (written <= 0 || (size_t)written >= sizeof(item)) {
      return;
    }
    if (!isFirst) {
      emitPayloadChunk(netOut, logOut, ",", 1);
    }
    emitPayloadChunk(netOut, logOut, item, (size_t)written);
    isFirst = false;
  }

  if (hasTemperature) {
    char temperatureBuffer[16];
    dtostrf(airTemperature, 0, 1, temperatureBuffer);
    char *trimmedTemperature = temperatureBuffer;
    while (*trimmedTemperature == ' ') {
      trimmedTemperature++;
    }
    const int written = snprintf(item, sizeof(item), "{\"pin\":\"air_temperature\",\"value\":%s}", trimmedTemperature);
    if (written <= 0 || (size_t)written >= sizeof(item)) {
      return;
    }
    if (!isFirst) {
      emitPayloadChunk(netOut, logOut, ",", 1);
    }
    emitPayloadChunk(netOut, logOut, item, (size_t)written);
    isFirst = false;
  }

  emitPayloadChunk(netOut, logOut, "]}", 2);
}

int findMatchingBracket(const String &text, int openIndex) {
  if (openIndex < 0 || openIndex >= text.length()) {
    return -1;
  }

  int depth = 0;
  for (int index = openIndex; index < text.length(); index++) {
    const char current = text.charAt(index);
    if (current == '[') {
      depth++;
    } else if (current == ']') {
      depth--;
      if (depth == 0) {
        return index;
      }
    }
  }

  return -1;
}

int findMatchingCurlyBracket(const String &text, int openIndex) {
  if (openIndex < 0 || openIndex >= text.length()) {
    return -1;
  }

  int depth = 0;
  for (int index = openIndex; index < text.length(); index++) {
    const char current = text.charAt(index);
    if (current == '{') {
      depth++;
    } else if (current == '}') {
      depth--;
      if (depth == 0) {
        return index;
      }
    }
  }

  return -1;
}

unsigned long parseSendIntervalMs(const String &response) {
  const long secondsRaw = extractJsonLongField(response, "\"send_interval_seconds\"", -1);
  if (secondsRaw <= 0) {
    return 0;
  }

  const unsigned long seconds = (unsigned long)secondsRaw;
  if (seconds == 0) {
    return 0;
  }

  return seconds * 1000UL;
}

int parseDigitalPinNumber(const String &pinLabel) {
  String normalizedPinLabel = pinLabel;
  normalizedPinLabel.trim();

  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    if (normalizedPinLabel.equalsIgnoreCase(DIGITAL_READING_KEYS[i])) {
      return DIGITAL_PINS_TO_REPORT[i];
    }
  }

  return -1;
}

bool writeDigitalOutputAndLog(int pinNumber, int value, const String &pinLabel) {
  (void)pinLabel;
  if (pinNumber < 0) {
    return false;
  }

  pinMode(pinNumber, OUTPUT);
  digitalWrite(pinNumber, value);
  Serial.print("Applied digital output ");
  Serial.print(pinLabel);
  Serial.print(" = ");
  Serial.println(value == HIGH ? 1 : 0);
  return true;
}

String extractJsonStringField(const String &json, const String &fieldName) {
  const int fieldIndex = json.indexOf(fieldName);
  if (fieldIndex < 0) {
    return "";
  }

  const int colonIndex = json.indexOf(':', fieldIndex + fieldName.length());
  if (colonIndex < 0) {
    return "";
  }

  int firstQuote = json.indexOf('"', colonIndex + 1);
  if (firstQuote < 0) {
    return "";
  }

  const int secondQuote = json.indexOf('"', firstQuote + 1);
  if (secondQuote < 0 || secondQuote <= firstQuote) {
    return "";
  }

  return json.substring(firstQuote + 1, secondQuote);
}

long extractJsonLongField(const String &json, const String &fieldName, long fallbackValue) {
  const int fieldIndex = json.indexOf(fieldName);
  if (fieldIndex < 0) {
    return fallbackValue;
  }

  int colonIndex = json.indexOf(':', fieldIndex + fieldName.length());
  if (colonIndex < 0) {
    return fallbackValue;
  }

  int valueStart = colonIndex + 1;
  while (valueStart < json.length()) {
    const char c = json.charAt(valueStart);
    if (c == '-' || isDigit(c)) {
      break;
    }
    valueStart++;
  }

  if (valueStart >= json.length()) {
    return fallbackValue;
  }

  int valueEnd = valueStart;
  if (json.charAt(valueEnd) == '-') {
    valueEnd++;
  }

  while (valueEnd < json.length() && isDigit(json.charAt(valueEnd))) {
    valueEnd++;
  }

  if (valueEnd <= valueStart) {
    return fallbackValue;
  }

  return json.substring(valueStart, valueEnd).toInt();
}

long extractJsonLongFieldInRange(
  const String &json,
  const String &fieldName,
  int searchStart,
  int searchEnd,
  long fallbackValue
) {
  if (searchStart < 0) {
    searchStart = 0;
  }
  if (searchEnd >= json.length()) {
    searchEnd = json.length() - 1;
  }
  if (searchEnd < searchStart) {
    return fallbackValue;
  }

  const int fieldIndex = json.indexOf(fieldName, searchStart);
  if (fieldIndex < 0 || fieldIndex > searchEnd) {
    return fallbackValue;
  }

  const int colonIndex = json.indexOf(':', fieldIndex + fieldName.length());
  if (colonIndex < 0 || colonIndex > searchEnd) {
    return fallbackValue;
  }

  int valueStart = colonIndex + 1;
  while (valueStart <= searchEnd) {
    const char c = json.charAt(valueStart);
    if (c == '-' || isDigit(c)) {
      break;
    }
    valueStart++;
  }

  if (valueStart > searchEnd) {
    return fallbackValue;
  }

  int valueEnd = valueStart;
  if (json.charAt(valueEnd) == '-') {
    valueEnd++;
  }

  while (valueEnd <= searchEnd && isDigit(json.charAt(valueEnd))) {
    valueEnd++;
  }

  if (valueEnd <= valueStart) {
    return fallbackValue;
  }

  return json.substring(valueStart, valueEnd).toInt();
}

String extractHttpBody(const String &response) {
  int bodyStart = response.indexOf("\r\n\r\n");
  int separatorLength = 4;

  if (bodyStart < 0) {
    bodyStart = response.indexOf("\n\n");
    separatorLength = 2;
  }

  if (bodyStart < 0) {
    return response;
  }

  return response.substring(bodyStart + separatorLength);
}
void applyDigitalOutputs(const String &response) {
  const String &body = response;
  const String mapKey = "\"digital_outputs\"";
  const int keyIndex = body.indexOf(mapKey);
  int appliedCount = 0;

  if (keyIndex >= 0) {
    const int objectStart = body.indexOf('{', keyIndex + mapKey.length());
    const int objectEnd = findMatchingCurlyBracket(body, objectStart);
    if (objectStart >= 0 && objectEnd > objectStart) {
      for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
        const uint8_t pin = DIGITAL_PINS_TO_REPORT[i];
        const String semanticToken = "\"" + String(DIGITAL_READING_KEYS[i]) + "\"";
        const long rawValue = extractJsonLongFieldInRange(body, semanticToken, objectStart, objectEnd, -9999);
        if (rawValue == -9999) {
          continue;
        }

        const int value = rawValue > 0 ? HIGH : LOW;
        if (writeDigitalOutputAndLog(pin, value, String(DIGITAL_READING_KEYS[i]))) {
          appliedCount++;
        }
      }
    }
  }

  (void)appliedCount;
}

void postMeasurements() {
  int digitalValues[DIGITAL_PIN_COUNT];
  int analogValues[ANALOG_PIN_COUNT];
  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    digitalValues[i] = digitalRead(DIGITAL_PINS_TO_REPORT[i]);
  }
  for (size_t i = 0; i < ANALOG_PIN_COUNT; i++) {
    analogValues[i] = analogRead(ANALOG_PINS_TO_REPORT[i]);
  }

  float airHumidity = NAN;
  float airTemperature = NAN;
  bool hasHumidity = false;
  bool hasTemperature = false;
  for (uint8_t attempt = 0; attempt < 3; attempt++) {
    airHumidity = dht.readHumidity();
    airTemperature = dht.readTemperature();
    hasHumidity = !isnan(airHumidity);
    hasTemperature = !isnan(airTemperature);
    if (hasHumidity && hasTemperature) {
      break;
    }
    delay(250);
  }
  if (!hasHumidity) {
    Serial.println(F("DHT11 humidity read failed"));
  }
  if (!hasTemperature) {
    Serial.println(F("DHT11 temperature read failed"));
  }

  char requestHeader[88];
  int written = snprintf(
    requestHeader,
    sizeof(requestHeader),
    "{\"controller_id\":\"%s\",\"readings\":[",
    controllerId
  );
  if (written <= 0 || (size_t)written >= sizeof(requestHeader)) {
    Serial.println(F("Payload build failed: header overflow"));
    return;
  }

  size_t payloadLength = 0;
  payloadLength += (size_t)written;
  bool isFirstReading = true;
  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    if (!isFirstReading) {
      payloadLength += 1;  // comma
    }
    payloadLength += intReadingJsonLength(DIGITAL_READING_KEYS[i], digitalValues[i]);
    isFirstReading = false;
  }

  for (size_t i = 0; i < ANALOG_PIN_COUNT; i++) {
    if (!isFirstReading) {
      payloadLength += 1;  // comma
    }
    payloadLength += intReadingJsonLength(ANALOG_READING_KEYS[i], analogValues[i]);
    isFirstReading = false;
  }

  if (hasHumidity) {
    if (!isFirstReading) {
      payloadLength += 1;  // comma
    }
    payloadLength += floatReadingJsonLength("air_humidity", airHumidity);
    isFirstReading = false;
  }

  if (hasTemperature) {
    if (!isFirstReading) {
      payloadLength += 1;  // comma
    }
    payloadLength += floatReadingJsonLength("air_temperature", airTemperature);
    isFirstReading = false;
  }

  payloadLength += 2;  // "]}"

  bool connected = client.connect(serverHost, serverPort);
  if (!connected) {
    Ethernet.maintain();
    delay(100);
    connected = client.connect(serverHost, serverPort);
  }
  if (!connected) {
    Serial.println(F("Connection failed"));
    return;
  }

  client.print(F("POST "));
  client.print(reportPath);
  client.println(F(" HTTP/1.1"));
  client.print(F("Host: "));
  client.print(serverHost);
  client.print(F(":"));
  client.println(serverPort);
  client.println(F("Content-Type: application/json"));
  client.print(F("Content-Length: "));
  client.println(payloadLength);
  client.println(F("Connection: close"));
  client.println();

  Serial.println(F("Request JSON:"));
  emitPayload(&client, &Serial, requestHeader, digitalValues, analogValues, airHumidity, airTemperature, hasHumidity, hasTemperature);
  Serial.println();

  Serial.println(F("Request sent"));
 
  unsigned long timeoutAt = millis() + 3000UL;
  String responseBody = "";
  responseBody.reserve(192);
  bool inBody = false;
  uint8_t newlineStreak = 0;
  while (client.connected() && millis() < timeoutAt) {
    while (client.available()) {
      char c = client.read();
      if (!inBody) {
        if (c == '\n') {
          newlineStreak++;
          if (newlineStreak >= 2) {
            inBody = true;
          }
        } else {
          if (c != '\r') {
            newlineStreak = 0;
          }
        }
      } else if (responseBody.length() < 240) {
        responseBody += c;
      }
      timeoutAt = millis() + 3000UL;
    }
  }

  client.stop();

  const unsigned long nextIntervalMs = parseSendIntervalMs(responseBody);
  if (responseBody.length() > 0) {
    Serial.println(F("Response JSON:"));
    Serial.println(responseBody);
  } else {
    Serial.println(F("Response JSON: <empty>"));
  }
  if (nextIntervalMs > 0) {
    if (sendIntervalMs != nextIntervalMs) {
      Serial.print(F("Updated send interval (ms): "));
      Serial.println(nextIntervalMs);
    }
    sendIntervalMs = nextIntervalMs;
  }

  const String pairingCode = extractJsonStringField(responseBody, "\"pairing_code\"");
  if (pairingCode != lastShownPairingCode) {
    if (pairingCode.length() == 4) {
      Serial.print(F("Pairing code received: "));
      Serial.println(pairingCode);
    } else if (lastShownPairingCode.length() == 4) {
      Serial.println(F("Pairing code cleared"));
    }
    displayPairingCode(pairingCode);
    lastShownPairingCode = pairingCode;
  }

  applyDigitalOutputs(responseBody);

  Serial.println();
  Serial.println(F("Request finished"));
}

void setup() {
  Serial.begin(9600);
  while (!Serial) {
    ;  // Wait for serial port
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

  dht.begin();
  delay(1500);

  // Initialize relay pins to a known state to avoid floating outputs.
  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    pinMode(DIGITAL_PINS_TO_REPORT[i], OUTPUT);
    digitalWrite(DIGITAL_PINS_TO_REPORT[i], LOW);
  }

    pinMode(TM1637_CLK_PIN, OUTPUT);
    pinMode(TM1637_DIO_PIN, OUTPUT);
    digitalWrite(TM1637_CLK_PIN, HIGH);
    digitalWrite(TM1637_DIO_PIN, HIGH);
    tm1637SetBrightness(0x04);
    displayFourDigits("----");
  }

}

void loop() {
  Ethernet.maintain();
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
