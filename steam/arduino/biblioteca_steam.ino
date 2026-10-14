// ============================================================
// BIBLIOTECA DIGITAL - NEXORA   |   Parte STEAM
// Lector + PC + Arduino
//
// La PC (PHP) busca el libro en la base de datos y le manda
// el resultado al Arduino por el puerto Serial.
// El Arduino NO consulta la base: solo muestra el resultado
// con la pantalla LCD, los LED y el buzzer.
//
// Comandos que entiende (a 9600 baud):
//   OK|titulo|precio     -> libro encontrado
//   NO_ENCONTRADO        -> el codigo no existe
//   AGOTADO|titulo       -> el libro existe pero no hay stock
// ============================================================

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ---------- BLOQUE 1: pines y pantalla ----------
const int PIN_LED_VERDE = 8;    // D8  -> resistencia 220 -> LED verde
const int PIN_LED_ROJO  = 9;    // D9  -> resistencia 220 -> LED rojo
const int PIN_BUZZER    = 10;   // D10 -> positivo del buzzer

// LCD 16x2 por I2C: SDA = A4, SCL = A5, direccion 0x27
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Tiempo (en milisegundos) que se muestra cada resultado
const int TIEMPO_MENSAJE = 3000;


// ---------- BLOQUE 2: configuracion inicial ----------
void setup() {
  pinMode(PIN_LED_VERDE, OUTPUT);
  pinMode(PIN_LED_ROJO, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);

  Serial.begin(9600);       // misma velocidad que usa la PC
  Serial.setTimeout(200);   // espera corta para completar la linea

  lcd.init();               // inicia la pantalla
  lcd.backlight();          // enciende la luz de fondo

  mostrarEsperando();
  Serial.println("LISTO");  // avisa a la PC que ya puede enviar
}


// ---------- BLOQUE 3: lectura del Serial ----------
void loop() {
  if (Serial.available() > 0) {
    // Lee una linea completa (hasta el salto de linea)
    String linea = Serial.readStringUntil('\n');
    linea.trim();           // quita \r, \n y espacios sobrantes

    if (linea.length() > 0) {
      procesarComando(linea);
      mostrarEsperando();   // al terminar vuelve al estado de espera
    }
  }
}


// ---------- BLOQUE 4: separar el comando usando | ----------
void procesarComando(String linea) {
  Serial.println("RECIBIDO: " + linea);   // eco para comprobar

  String comando = linea;   // parte antes del primer |
  String dato1 = "";        // parte entre el primer y el segundo |
  String dato2 = "";        // parte despues del segundo |

  int barra1 = linea.indexOf('|');
  if (barra1 >= 0) {
    comando = linea.substring(0, barra1);
    int barra2 = linea.indexOf('|', barra1 + 1);
    if (barra2 >= 0) {
      dato1 = linea.substring(barra1 + 1, barra2);
      dato2 = linea.substring(barra2 + 1);
    } else {
      dato1 = linea.substring(barra1 + 1);
    }
  }

  if (comando == "OK") {
    mostrarEncontrado(dato1, dato2);    // dato1 = titulo, dato2 = precio
  } else if (comando == "NO_ENCONTRADO") {
    mostrarNoEncontrado();
  } else if (comando == "AGOTADO") {
    mostrarAgotado(dato1);              // dato1 = titulo
  } else {
    mostrarComandoInvalido();
  }
}


// ---------- BLOQUE 5: funciones de cada estado ----------

// Estado de espera: pantalla de inicio y todo apagado
void mostrarEsperando() {
  digitalWrite(PIN_LED_VERDE, LOW);
  digitalWrite(PIN_LED_ROJO, LOW);
  noTone(PIN_BUZZER);
  escribirLCD("Biblioteca", "Esperando...");
}

// Libro encontrado: titulo + precio, LED verde, bip corto
void mostrarEncontrado(String titulo, String precio) {
  escribirLCD(titulo, "$" + precio);
  digitalWrite(PIN_LED_ROJO, LOW);
  digitalWrite(PIN_LED_VERDE, HIGH);
  tone(PIN_BUZZER, 1500, 150);          // bip corto y agudo
  delay(TIEMPO_MENSAJE);
}

// Codigo inexistente: mensaje de error, LED rojo, bip largo
void mostrarNoEncontrado() {
  escribirLCD("No encontrado", "");
  digitalWrite(PIN_LED_VERDE, LOW);
  digitalWrite(PIN_LED_ROJO, HIGH);
  tone(PIN_BUZZER, 400, 1000);          // bip largo y grave
  delay(TIEMPO_MENSAJE);
}

// Libro sin stock: titulo + "Sin stock", LED rojo, tres bips
void mostrarAgotado(String titulo) {
  escribirLCD(titulo, "Sin stock");
  digitalWrite(PIN_LED_VERDE, LOW);
  digitalWrite(PIN_LED_ROJO, HIGH);
  for (int i = 0; i < 3; i++) {         // tres bips cortos seguidos
    tone(PIN_BUZZER, 800, 150);
    delay(300);
  }
  delay(TIEMPO_MENSAJE - 900);
}

// Comando que no se reconoce: se avisa sin encender el LED verde
void mostrarComandoInvalido() {
  escribirLCD("Comando", "no valido");
  digitalWrite(PIN_LED_VERDE, LOW);
  digitalWrite(PIN_LED_ROJO, HIGH);
  delay(1500);
}


// ---------- BLOQUE 6: ayuda para escribir en el LCD ----------
// Borra la pantalla y escribe las dos filas (maximo 16 letras cada una)
void escribirLCD(String fila1, String fila2) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(fila1.substring(0, 16));
  lcd.setCursor(0, 1);
  lcd.print(fila2.substring(0, 16));
}
