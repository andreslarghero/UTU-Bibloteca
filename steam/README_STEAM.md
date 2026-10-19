# STEAM — Biblioteca Digital (NEXORA)

Parte STEAM del proyecto: **Lector + PC + Arduino**.

La PC (PHP) consulta la base de datos y le envía el resultado al Arduino por el puerto Serial.
El Arduino **no consulta la base**: solo recibe el resultado y lo muestra con la pantalla LCD, los LED y el buzzer.

> **Estado: COMPLETADO Y VALIDADO FÍSICAMENTE.**
> El circuito se armó y probó primero en Tinkercad (10/10/2026) y después con la placa física.
> La cadena completa Caja → puente → Arduino funciona; la prueba física fue confirmada por el equipo el 14/10/2026.

Código del Arduino: [`arduino/biblioteca_steam.ino`](arduino/biblioteca_steam.ino)
Puente PC → Arduino: [`puente/puente_arduino.py`](puente/puente_arduino.py) (ver [`puente/README_PUENTE.md`](puente/README_PUENTE.md))

### El lector es del mostrador

El lector de códigos de barras se usa en la **Caja del mostrador**, que solo puede abrir el bibliotecario. Funciona como un teclado: escribe el código en el campo de escaneo (que tiene `autofocus`) y pulsa Enter. La Caja **solo vende libros físicos**: un código de un libro solo digital no se agrega al ticket y no genera ningún aviso al Arduino.

---

## 1. Componentes del circuito

| Cantidad | Componente | Detalle |
|---|---|---|
| 1 | Arduino Uno R3 | Placa principal |
| 1 | LCD 16x2 I2C | Módulo PCF8574, dirección `0x27` |
| 1 | LED verde | Indica libro encontrado |
| 1 | LED rojo | Indica error o sin stock |
| 2 | Resistencia de 220 ohm | Una para cada LED |
| 1 | Buzzer (piezo) | Avisos sonoros |
| 12 | Cables | Sin protoboard |

Es la lista de componentes del circuito de Tinkercad y del armado físico. No se registró la marca ni el modelo exacto de cada pieza física; el código usa la dirección I2C `0x27` para el módulo del LCD.

---

## 2. Conexiones

### LCD I2C

| Pin del LCD | Pin del Arduino |
|---|---|
| VCC | 5V |
| GND | GND |
| SDA | A4 |
| SCL | A5 |

### LED verde

```
D8 -> 220 ohm -> ánodo
cátodo -> GND
```

### LED rojo

```
D9 -> 220 ohm -> ánodo
cátodo -> GND
```

### Buzzer

```
D10 -> positivo
negativo -> GND
```

---

## 3. Comunicación Serial

- Velocidad: **9600 baud**.
- Cada comando es una línea de texto.
- Las partes del comando se separan con el carácter `|`.
- El Arduino quita `\r`, `\n` y espacios sobrantes antes de interpretar la línea.

---

## 4. Comandos

| Comando | Significado |
|---|---|
| `OK\|titulo\|precio` | El libro existe y hay stock |
| `NO_ENCONTRADO` | El código no existe |
| `AGOTADO\|titulo` | El libro existe pero no hay stock |

Ejemplos:

```
OK|1984|590
NO_ENCONTRADO
AGOTADO|Dracula
```

---

## 5. Comportamiento de cada comando

### Estado de espera (al iniciar y después de cada comando)

| Elemento | Resultado |
|---|---|
| LCD línea 1 | `Biblioteca` |
| LCD línea 2 | `Esperando...` |
| LED verde | Apagado |
| LED rojo | Apagado |
| Buzzer | En silencio |

### `OK|titulo|precio`

| Elemento | Resultado |
|---|---|
| LCD línea 1 | Título recibido |
| LCD línea 2 | `$` + precio recibido |
| LED verde | Encendido |
| LED rojo | Apagado |
| Buzzer | Un bip corto y agudo (1500 Hz, 150 ms) |
| Duración | 3 segundos y vuelve al estado de espera |

Ejemplo: `OK|1984|590` muestra `1984` y `$590`.

### `NO_ENCONTRADO`

| Elemento | Resultado |
|---|---|
| LCD línea 1 | `No encontrado` |
| LCD línea 2 | Vacía |
| LED verde | Apagado |
| LED rojo | Encendido |
| Buzzer | Un bip largo y grave (400 Hz, 1 segundo) |
| Duración | 3 segundos y vuelve al estado de espera |

### `AGOTADO|titulo`

| Elemento | Resultado |
|---|---|
| LCD línea 1 | Título recibido |
| LCD línea 2 | `Sin stock` |
| LED verde | Apagado |
| LED rojo | Encendido |
| Buzzer | Tres bips cortos seguidos (800 Hz, 150 ms cada uno) |
| Duración | 3 segundos y vuelve al estado de espera |

Ejemplo: `AGOTADO|Dracula` muestra `Dracula` y `Sin stock`.

Este comando cubre además el aviso de "no queda stock".

### Otros detalles del código

- Al iniciar, el Arduino envía `LISTO` por Serial.
- Por cada línea recibida responde `RECIBIDO: <línea>`.
- Si llega un comando que no reconoce, muestra `Comando` / `no valido` con el LED rojo durante 1,5 segundos.
- El LCD muestra como máximo 16 caracteres por línea; los textos más largos se cortan.

---

## 6. Integración con la Caja

### Arquitectura

```
Caja PHP / MySQL
        ↓
resultado del escaneo
        ↓
archivo de comando local (comando_steam.txt)
        ↓
puente_arduino.py
        ↓
Serial USB
        ↓
Arduino
        ↓
LCD + LEDs + buzzer
```

El Arduino **no consulta MySQL**. La Caja hace la consulta, y el resultado viaja como una línea de texto.

### Archivos que participan

| Archivo | Función |
|---|---|
| `controladores/CajaController.php` | Después de cada escaneo llama a un método de la clase `Steam` |
| `modelos/Steam.php` | Arma el comando y lo escribe en el archivo. Tiene la configuración (ruta y encendido/apagado) |
| `steam/puente/comando_steam.txt` | Archivo de comando. Lo crea la Caja sola en el primer escaneo |
| `steam/puente/puente_arduino.py --archivo` | Lee el archivo y envía cada evento nuevo al Arduino |

### Qué comando genera la Caja

| Resultado del escaneo en la Caja | Comando |
|---|---|
| Libro físico con stock, se agrega al ticket | `OK\|titulo\|precio` |
| El código no existe | `NO_ENCONTRADO` |
| Libro físico con stock 0 | `AGOTADO\|titulo` |
| Libro físico inactivo (dado de baja) | `NO_ENCONTRADO` |
| Libro físico cuyo stock ya está todo en el ticket ("No hay más stock disponible") | Ninguno |
| El código es de un libro solo digital | Ninguno |
| Campo vacío | Ninguno |
| Quitar, eliminar, vaciar o finalizar la venta | Ninguno |

### Formato del archivo de comando

Una sola línea con el último evento:

```
identificador + espacio + comando
```

Ejemplo:

```
6ac9c58c0fb0f7.14654495 OK|1984|590
```

El identificador lo genera PHP y es distinto en cada escaneo. El puente recuerda el último identificador que procesó:

- mismo identificador → es el mismo evento, no se vuelve a enviar;
- identificador nuevo → es un evento nuevo, aunque el comando sea igual al anterior (dos escaneos seguidos del mismo libro).

Al arrancar, el puente toma lo que ya estaba escrito como evento viejo y no lo envía.

### Título y precio que se envían

- **Título:** se envía una copia adaptada al LCD: sin tildes ni ñ y con 16 caracteres como máximo. Ejemplo: `Cien años de soledad` se envía como `Cien anos de sol`. El título guardado en MySQL y el que muestra la web no cambian.
- **Precio:** es el precio real del libro en la base, sin símbolo. `590.00` se envía como `590`; si tuviera centavos, `590,50`. El `$` lo agrega el Arduino.

### STEAM nunca frena la Caja

Si la carpeta no existe, no hay permiso de escritura, el archivo está ocupado o el puente está apagado, `Steam` no hace nada y no muestra errores. La Caja sigue vendiendo igual.

Para apagar el aviso sin tocar la Caja: en `modelos/Steam.php`, poner la constante `ACTIVO` en `false`.

**Ruta del archivo.** Por defecto, `modelos/Steam.php` calcula la ruta desde su propia ubicación: `<carpeta del proyecto>/steam/puente/comando_steam.txt`. Así funciona al clonar el proyecto en cualquier PC, sin depender de una letra de unidad, y el puente la lee desde su misma carpeta. Si una PC necesita otra ruta, se crea `config/steam_config_local.php` (no se sube a GitHub) con `define("STEAM_ARCHIVO_COMANDO", "...");`. En la PC del equipo ese archivo apunta a `E:/PROYECTO/steam/puente/comando_steam.txt`, que es la configuración con la que se validó la placa física.

### Limitaciones conocidas

- El archivo guarda solo el último evento. Si dos escaneos llegan con menos de 0,2 segundos de diferencia, el puente puede ver solo el último. Con un lector de mano y la recarga de la página entre escaneos no es un caso habitual.
- Si la Caja (en `htdocs`) y el puente usan carpetas distintas, el Arduino no recibe avisos: hay que lanzar el puente con `--archivo "ruta"` o ajustar `config/steam_config_local.php` (riesgo R19 de la matriz).
- El LCD no muestra tildes ni ñ y corta en 16 caracteres; por eso se envía una copia adaptada del título.

---

## 7. Cómo ponerlo en marcha

1. Cargar `arduino/biblioteca_steam.ino` en el Arduino Uno (Arduino IDE, placa "Arduino Uno").
2. Conectar el Arduino a la PC por USB.
3. Iniciar Apache y MySQL en XAMPP.
4. En una consola, dentro de la carpeta `steam/puente` del proyecto (en la PC del equipo, `E:\PROYECTO\steam\puente`):

   ```
   python puente_arduino.py --archivo
   ```

   Elegir el puerto del Arduino (o indicarlo con `--puerto COM3`) y esperar el mensaje `Arduino -> LISTO`.
5. Entrar a la Caja como bibliotecario y escanear un libro. El puente muestra `CAJA -> ...`, `PC -> ...` y `Arduino -> RECIBIDO: ...`, y la placa muestra el resultado.

El puente lee el archivo de comando de su propia carpeta. En una instalación nueva (clonando el repositorio en `htdocs`) la Caja escribe ahí mismo y no hay que configurar nada. En la PC del equipo, la Caja de `htdocs` escribe en `E:\PROYECTO\steam\puente` (por `config/steam_config_local.php`), así que el puente se ejecuta desde esa carpeta.

---

## 8. Validación

| Etapa | Fecha | Cómo se validó | Resultado |
|---|---|---|---|
| Circuito y programa en Tinkercad | 10/10/2026 | Simulación: comandos enviados desde el Monitor en serie | Correcto (tabla de abajo) |
| Integración Caja → archivo → puente | 10/10/2026 y 14/10/2026 | Puente en `--archivo --simular`, en una copia temporal del sitio y de la base | Correcto: cada escaneo genera un solo evento |
| Puente PC → Arduino (software) | 10/10/2026 | Simulación, listado de puertos, puerto inexistente y `--debug` | Correcto |
| Placa física y cadena completa | Confirmada el 14/10/2026 | Prueba física hecha por el equipo con la Caja, el puente y el Arduino | Confirmada por el equipo |

Pruebas de la planilla: PR109 a PR123.

**Evidencia:** el circuito de Tinkercad está en `Entregable/Recursos/STEAM_Circuito_Tinkercad.png`. Evidencia visual física (fotos o video de la placa) no adjunta: la prueba física fue confirmada por el equipo.

Pruebas hechas en la simulación de Tinkercad, enviando los comandos desde el Monitor en serie:

| Comando enviado | LCD | LED | Resultado |
|---|---|---|---|
| (inicio) | `Biblioteca` / `Esperando...` | Ambos apagados | Correcto |
| `OK\|1984\|590` | `1984` / `$590` | Verde | Correcto |
| `NO_ENCONTRADO` | `No encontrado` | Rojo | Correcto |
| `AGOTADO\|Dracula` | `Dracula` / `Sin stock` | Rojo | Correcto |
