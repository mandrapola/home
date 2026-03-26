/*
  Arduino Uno -> Smart Home API client
  Sends measurements to:
    POST /api/controller/report
  every 5 minutes.

  Hardware target:
    Arduino Uno + Ethernet Shield (W5100/W5500)
*/

#include <SPI.h>
#include <Ethernet.h>

// ===== Network settings =====
byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0x01 };
char serverHost[] = "192.168.1.50";  // Set your server LAN IP
int serverPort = 3000;

// ===== Controller settings =====
const int controllerId = 1;
const unsigned long sendIntervalMs = 300000UL;  // 5 minutes

// Optional: replace with real sensor pins/libraries
const int TEMP_PIN = A0;
const int PRESSURE_PIN = A1;
const int HUMIDITY_PIN = A2;

EthernetClient client;
unsigned long lastSendMs = 0;

float readTemperature() {
  int raw = analogRead(TEMP_PIN);
  // Placeholder conversion (replace with real formula for your sensor)
  return (raw / 1023.0) * 50.0;
}

float readPressure() {
  int raw = analogRead(PRESSURE_PIN);
  // Placeholder conversion to ~730..770 range
  return 730.0 + (raw / 1023.0) * 40.0;
}

float readHumidity() {
  int raw = analogRead(HUMIDITY_PIN);
  // Placeholder conversion to 0..100%
  return (raw / 1023.0) * 100.0;
}

void postMeasurements() {
  float temperature = readTemperature();
  float pressure = readPressure();
  float humidity = readHumidity();

  String payload = "{";
  payload += "\"controller_id\":";
  payload += String(controllerId);
  payload += ",\"thermometer\":";
  payload += String(temperature, 2);
  payload += ",\"pressure\":";
  payload += String(pressure, 2);
  payload += ",\"humidity\":";
  payload += String(humidity, 2);
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
  while (client.connected() && millis() < timeoutAt) {
    while (client.available()) {
      char c = client.read();
      Serial.write(c);
      timeoutAt = millis() + 3000UL;
    }
  }

  client.stop();
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
}

void loop() {
  unsigned long now = millis();

  if (lastSendMs == 0 || (now - lastSendMs) >= sendIntervalMs) {
    postMeasurements();
    lastSendMs = now;
  }
}
