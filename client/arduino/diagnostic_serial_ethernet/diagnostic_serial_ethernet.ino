#include <SPI.h>
#include <Ethernet.h>

byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0x01 };
unsigned long lastBeat = 0;

void setup() {
  Serial.begin(9600);
  delay(300);
  Serial.println();
  Serial.println(F("[diag] boot"));

  Serial.println(F("[diag] ethernet begin..."));
  if (Ethernet.begin(mac) == 0) {
    Serial.println(F("[diag] DHCP failed"));
  } else {
    delay(500);
    Serial.print(F("[diag] ip: "));
    Serial.println(Ethernet.localIP());
  }

  Serial.println(F("[diag] setup done"));
}

void loop() {
  Ethernet.maintain();

  unsigned long now = millis();
  if (lastBeat == 0 || (now - lastBeat) >= 2000UL) {
    lastBeat = now;
    Serial.print(F("[diag] alive ms="));
    Serial.println(now);
  }
}
