/*
  Arduino Uno + ESP8266 AT bridge.

  Wiring:
    ESP TX -> Arduino D2
    ESP RX <- Arduino D3
    GND    -> GND

  Serial Monitor:
    9600 baud, send AT commands with newline.
*/

#include <SoftwareSerial.h>

static const uint8_t ESP_RX_PIN = 0; // Arduino receives from ESP TX.
static const uint8_t ESP_TX_PIN = 1; // Arduino sends to ESP RX.
static const unsigned long SERIAL_BAUD = 9600;
static const unsigned long ESP_BAUD = 9600;

SoftwareSerial esp8266(ESP_RX_PIN, ESP_TX_PIN);

void setup()
{
  Serial.begin(SERIAL_BAUD);
  esp8266.begin(ESP_BAUD);

  delay(300);
  Serial.println();
  Serial.println(F("ESP8266 AT bridge ready."));
  Serial.println(F("Type AT and press Enter."));
}

void loop()
{
  while (esp8266.available() > 0) {
    Serial.write((char) esp8266.read());
  }

  while (Serial.available() > 0) {
    esp8266.write((char) Serial.read());
  }
}
