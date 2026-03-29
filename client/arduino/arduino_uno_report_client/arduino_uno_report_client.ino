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
char serverHost[] = "192.168.0.201";  // Set your server LAN IP
int serverPort = 3000;

// ===== Controller settings =====
const int controllerId = 1;
unsigned long sendIntervalMs = 30000UL;  // 30 seconds by default

// ===== DHT11 settings =====
const uint8_t DHT_PIN = 2;
#define DHTTYPE DHT11
DHT dht(DHT_PIN, DHTTYPE);

// User-accessible pins to report.
// D0/D1 are reserved for USB Serial.
// D10-D13 are reserved by the Ethernet shield SPI bus.
const uint8_t DIGITAL_PINS_TO_REPORT[] = { 3, 4, 5, 6, 7, 8, 9 };
const uint8_t ANALOG_PINS_TO_REPORT[] = { A0, A1, A2, A3, A4, A5 };
const size_t DIGITAL_PIN_COUNT = sizeof(DIGITAL_PINS_TO_REPORT) / sizeof(DIGITAL_PINS_TO_REPORT[0]);
const size_t ANALOG_PIN_COUNT = sizeof(ANALOG_PINS_TO_REPORT) / sizeof(ANALOG_PINS_TO_REPORT[0]);

EthernetClient client;
unsigned long lastSendMs = 0;

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
  const String key = "\"send_interval_seconds\":";
  const int keyIndex = response.indexOf(key);
  if (keyIndex < 0) {
    return 0;
  }

  int valueStart = keyIndex + key.length();
  while (valueStart < response.length() && response.charAt(valueStart) == ' ') {
    valueStart++;
  }

  int valueEnd = valueStart;
  while (valueEnd < response.length() && isDigit(response.charAt(valueEnd))) {
    valueEnd++;
  }

  if (valueEnd == valueStart) {
    return 0;
  }

  const unsigned long seconds = response.substring(valueStart, valueEnd).toInt();
  if (seconds == 0) {
    return 0;
  }

  return seconds * 1000UL;
}

int parseDigitalPinNumber(const String &pinLabel) {
  if (pinLabel.length() < 2) {
    return -1;
  }

  const char prefix = toupper(pinLabel.charAt(0));
  if (prefix != 'D') {
    return -1;
  }

  const int pinNumber = pinLabel.substring(1).toInt();
  return pinNumber >= 0 ? pinNumber : -1;
}

bool isSupportedDigitalOutputPin(int pinNumber) {
  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    if (DIGITAL_PINS_TO_REPORT[i] == pinNumber) {
      return true;
    }
  }

  return false;
}

bool writeDigitalOutputAndLog(int pinNumber, int value, const String &pinLabel) {
  if (pinNumber < 0 || !isSupportedDigitalOutputPin(pinNumber)) {
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

int extractDigitalPinNumberFallback(const String &jsonObject) {
  for (int i = 0; i < jsonObject.length() - 1; i++) {
    if (toupper(jsonObject.charAt(i)) == 'D' && isDigit(jsonObject.charAt(i + 1))) {
      int end = i + 2;
      while (end < jsonObject.length() && isDigit(jsonObject.charAt(end))) {
        end++;
      }
      return jsonObject.substring(i + 1, end).toInt();
    }
  }

  return -1;
}

bool applySingleDigitalOutputInstruction(const String &jsonObject) {
  String pinLabel = extractJsonStringField(jsonObject, "\"pin\"");
  pinLabel.trim();
  int pinNumber = parseDigitalPinNumber(pinLabel);
  if (pinNumber < 0) {
    pinNumber = extractDigitalPinNumberFallback(jsonObject);
    if (pinNumber >= 0) {
      pinLabel = "D" + String(pinNumber);
    }
  }
  const long rawValue = extractJsonLongField(jsonObject, "\"value\"", 0);
  const int value = rawValue > 0 ? HIGH : LOW;
  return writeDigitalOutputAndLog(pinNumber, value, pinLabel);
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
        const String pinLabel = "D" + String(pin);
        const String token = "\"" + pinLabel + "\"";
        const long rawValue = extractJsonLongFieldInRange(body, token, objectStart, objectEnd, -9999);
        if (rawValue == -9999) {
          continue;
        }

        const int value = rawValue > 0 ? HIGH : LOW;
        if (writeDigitalOutputAndLog(pin, value, pinLabel)) {
          appliedCount++;
        }
      }
    }
  }

  if (appliedCount == 0) {
    Serial.println("No digital output instructions applied");
  } else {
    Serial.print("Applied outputs count: ");
    Serial.println(appliedCount);
  }
}

String buildPinLabel(uint8_t pin, bool analogPin) {
  if (analogPin) {
    return "A" + String(pin - A0);
  }

  return "D" + String(pin);
}

void appendReading(String &payload, bool &isFirstReading, const String &pinLabel, int value) {
  if (!isFirstReading) {
    payload += ",";
  }

  payload += "{\"pin\":\"";
  payload += pinLabel;
  payload += "\",\"value\":";
  payload += String(value);
  payload += "}";
  isFirstReading = false;
}

void appendReading(String &payload, bool &isFirstReading, const String &pinLabel, float value) {
  if (!isFirstReading) {
    payload += ",";
  }

  char valueBuffer[16];
  dtostrf(value, 0, 1, valueBuffer);

  payload += "{\"pin\":\"";
  payload += pinLabel;
  payload += "\",\"value\":";
  payload += valueBuffer;
  payload += "}";
  isFirstReading = false;
}

void postMeasurements() {
  String payload = "{";
  payload += "\"controller_id\":";
  payload += String(controllerId);
  payload += ",\"readings\":[";

  bool isFirstReading = true;

  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    const uint8_t pin = DIGITAL_PINS_TO_REPORT[i];
    appendReading(payload, isFirstReading, buildPinLabel(pin, false), digitalRead(pin));
  }

  for (size_t i = 0; i < ANALOG_PIN_COUNT; i++) {
    const uint8_t pin = ANALOG_PINS_TO_REPORT[i];
    appendReading(payload, isFirstReading, buildPinLabel(pin, true), analogRead(pin));
  }

  const float airHumidity = dht.readHumidity();
  const float airTemperature = dht.readTemperature();

  if (!isnan(airHumidity)) {
    appendReading(payload, isFirstReading, "air_humidity", airHumidity);
  } else {
    Serial.println("DHT11 humidity read failed");
  }

  if (!isnan(airTemperature)) {
    appendReading(payload, isFirstReading, "air_temperature", airTemperature);
  } else {
    Serial.println("DHT11 temperature read failed");
  }

  payload += "]";
  payload += "}";

  if (!client.connect(serverHost, serverPort)) {
    Serial.println("Connection failed");
    return;
  }

  client.println("POST /api/controller/report HTTP/1.1");
  client.print("Host: ");
  client.print(serverHost);
  client.print(":");
  client.println(serverPort);
  client.println("Content-Type: application/json");
  client.print("Content-Length: ");
  client.println(payload.length());
  client.println("Connection: close");
  client.println();
  client.println(payload);

  Serial.println("Request sent:");
  Serial.println(payload);

  unsigned long timeoutAt = millis() + 3000UL;
  String responseBody = "";
  responseBody.reserve(384);
  bool inBody = false;
  uint8_t newlineStreak = 0;
  while (client.connected() && millis() < timeoutAt) {
    while (client.available()) {
      char c = client.read();
      Serial.write(c);
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
      } else if (responseBody.length() < 512) {
        responseBody += c;
      }
      timeoutAt = millis() + 3000UL;
    }
  }

  client.stop();

  const unsigned long nextIntervalMs = parseSendIntervalMs(responseBody);
  if (nextIntervalMs > 0) {
    sendIntervalMs = nextIntervalMs;
    Serial.print("Updated send interval (ms): ");
    Serial.println(sendIntervalMs);
  }

  applyDigitalOutputs(responseBody);

  Serial.println();
  Serial.println("Request finished");
}

void setup() {
  Serial.begin(9600);
  while (!Serial) {
    ;  // Wait for serial port
  }

  Serial.println("Starting Ethernet...");

  if (Ethernet.begin(mac) == 0) {
    Serial.println("DHCP failed. Stop.");
    while (true) {
      delay(1000);
    }
  }

  delay(1000);
  Serial.print("Local IP: ");
  Serial.println(Ethernet.localIP());
  Serial.print("Default send interval (ms): ");
  Serial.println(sendIntervalMs);

  dht.begin();

  for (size_t i = 0; i < DIGITAL_PIN_COUNT; i++) {
    pinMode(DIGITAL_PINS_TO_REPORT[i], INPUT);
  }
}

void loop() {
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
