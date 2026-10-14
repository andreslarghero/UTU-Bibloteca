# Puente PC → Arduino — Biblioteca Digital (NEXORA)

## 1. Para qué sirve

`puente_arduino.py` es un programa de consola para Windows que se conecta al Arduino por USB (puerto serie) y le envía los comandos que el Arduino sabe mostrar en el LCD, los LED y el buzzer.

Tiene dos formas de uso:

- **Modo manual:** los comandos se escriben a mano en la consola.
- **Modo archivo (`--archivo`):** los comandos llegan solos desde la Caja web, cada vez que el bibliotecario escanea un libro.

**El Arduino NO consulta MySQL.** La consulta a la base la hace la PC; el Arduino solo recibe el resultado y lo muestra.

## 2. Arquitectura

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

## 3. Requisitos

- Windows.
- Python 3.
- La librería `pyserial` (solo hace falta para el modo real; el modo simulación funciona sin ella).
- Un Arduino Uno conectado por USB, con el código `steam\arduino\biblioteca_steam.ino` cargado.

> El Arduino físico debe usar exactamente el código `steam\arduino\biblioteca_steam.ino`.
> Ese código trabaja a **9600 baud**, responde `LISTO` al iniciar y `RECIBIDO: <comando>` por cada línea que recibe.

## 4. Cómo instalar Python

1. Entrar a <https://www.python.org/downloads/> y descargar Python 3 para Windows.
2. Ejecutar el instalador y marcar la casilla **"Add python.exe to PATH"** antes de instalar.
3. Abrir una consola (PowerShell o Símbolo del sistema) y comprobar:

```
python --version
```

## 5. Cómo instalar las dependencias

Desde la carpeta `steam\puente\`:

```
pip install -r requirements.txt
```

## 6. Cómo ejecutar

### Modo normal (elige el puerto de una lista)

```
python puente_arduino.py
```

El programa muestra los puertos encontrados y marca los que parecen un Arduino. Se puede escribir el número de la lista o el nombre del puerto a mano (por ejemplo `COM3`).

Formato de lo que se ve (los puertos son solo un ejemplo):

```
Puertos detectados:
[1] COM3 - Arduino Uno   <- posible Arduino
[2] COM5 - USB Serial   <- posible Arduino

Seleccione puerto: 1

Conectando a COM3...
Conectado.

Arduino -> LISTO

Comando >
```

### Elegir el puerto directamente

```
python puente_arduino.py --puerto COM3
```

Con otra velocidad (por defecto es 9600):

```
python puente_arduino.py --puerto COM3 --baud 9600
```

### Modo simulación (sin Arduino)

```
python puente_arduino.py --simular
```

En este modo **no se abre ningún puerto y no se envía nada al Arduino**. El programa solo muestra lo que enviaría:

```
Comando > OK|1984|590
SIMULADO -> OK|1984|590
```

Sirve para probar el programa en una PC que no tiene el Arduino conectado.

### Modo archivo (comandos que llegan de la Caja)

```
python puente_arduino.py --archivo
```

El programa queda esperando. Cada vez que la Caja escribe un evento nuevo en `comando_steam.txt`, lo muestra y lo envía al Arduino:

```
CAJA -> OK|1984|590
PC -> OK|1984|590
Arduino -> RECIBIDO: OK|1984|590
```

Se puede combinar con el puerto:

```
python puente_arduino.py --archivo --puerto COM3
```

Por defecto lee `comando_steam.txt`, que está en la misma carpeta que el programa. Para usar otro archivo:

```
python puente_arduino.py --archivo "C:\otra\carpeta\comando_steam.txt"
```

Se termina con **Ctrl+C**.

### Modo archivo simulado (sin Arduino)

```
python puente_arduino.py --archivo --simular
```

No abre ningún puerto y no envía nada al Arduino. Muestra lo que llegó de la Caja y lo que enviaría:

```
CAJA -> OK|1984|590
SIMULADO -> OK|1984|590
```

### Cómo evita repetir comandos

El archivo tiene una sola línea: `identificador + espacio + comando`. Ejemplo:

```
6ac9c58c0fb0f7.14654495 OK|1984|590
```

- El puente revisa el archivo cada 0,2 segundos.
- Si el identificador es el mismo que el último procesado, no hace nada.
- Si el identificador es nuevo, envía el comando una sola vez. Por eso dos escaneos seguidos del mismo libro generan dos envíos.
- Lo que ya estaba escrito cuando el puente arranca se toma como evento viejo y no se envía.
- Si la línea todavía no terminó de escribirse (no tiene salto de línea al final), espera a la revisión siguiente.

El archivo guarda solo el último evento: si dos escaneos llegan con menos de 0,2 segundos de diferencia, el puente puede ver solo el último.

### Ver la ayuda

```
python puente_arduino.py --help
```

### Opciones

| Opción | Para qué sirve |
|---|---|
| `--puerto COM3` | Usa ese puerto sin mostrar la lista |
| `--baud 9600` | Velocidad en baudios (por defecto 9600) |
| `--simular` | Modo sin Arduino |
| `--archivo` | Recibe los comandos de la Caja desde `comando_steam.txt` |
| `--archivo RUTA` | Lo mismo, leyendo otro archivo |
| `--debug` | Además del mensaje corto, muestra el detalle técnico del error (errores de puerto y errores inesperados) |

## 7. Comandos

| Comando | Significado |
|---|---|
| `OK\|titulo\|precio` | Libro encontrado |
| `NO_ENCONTRADO` | El código no existe |
| `AGOTADO\|titulo` | El libro existe pero no hay stock |

Ejemplos:

```
OK|1984|590
NO_ENCONTRADO
AGOTADO|Dracula
```

Cada comando se envía como texto UTF-8 terminado en salto de línea, por ejemplo `b"OK|1984|590\n"`.

Si el formato parece incorrecto, el programa muestra una **advertencia** pero igual lo envía (el Arduino ya sabe mostrar "Comando no valido").

Después de enviar, el programa escucha al Arduino durante 4 segundos y muestra lo que llegue:

```
PC -> OK|1984|590
Arduino -> RECIBIDO: OK|1984|590
```

## 8. Cómo salir

Escribir `salir` o `exit`. El puerto se cierra antes de terminar.

También se puede cortar con **Ctrl+C**: el puerto se cierra igual.

## 9. Errores comunes

| Mensaje | Qué revisar |
|---|---|
| Falta la librería pyserial | Ejecutar `pip install -r requirements.txt` |
| No se detectó ningún puerto serie | Que el Arduino esté conectado por USB y con su driver instalado |
| El puerto no existe | El nombre del puerto (ver el Administrador de dispositivos de Windows) |
| Permiso denegado o puerto ocupado | Cerrar el Monitor Serie del Arduino IDE u otro programa que use el puerto |
| No llegó el mensaje LISTO | Que la placa tenga cargado `biblioteca_steam.ino` y que la velocidad sea 9600 |
| El Arduino no respondió | Que el cable siga conectado |

## 10. Estado

**Completado y validado físicamente.**

| Prueba | Fecha | Resultado |
|---|---|---|
| Modo simulación (`--simular`), `--help`, listado de puertos, puerto inexistente y `--debug` | 10/10/2026 | Correcto |
| Integración con la Caja en `--archivo --simular` (copia temporal del sitio y de la base) | 10/10/2026 y 14/10/2026 | Correcto: cada escaneo se envía una sola vez |
| Conexión con el Arduino físico y cadena completa Caja → puente → Arduino | Confirmada el 14/10/2026 | Confirmada por el equipo |

Evidencia visual física (fotos o video) no adjunta.

**Dónde ejecutarlo:** el puente lee `comando_steam.txt` de su propia carpeta. En una instalación nueva, la Caja escribe en la carpeta `steam/puente` del mismo proyecto, así que alcanza con `python steam/puente/puente_arduino.py --archivo`. En la PC del equipo, la Caja escribe en `E:\PROYECTO\steam\puente` (configurado en `php/steam_config_local.php`, que no se sube a GitHub), así que para la defensa el puente se ejecuta desde esa carpeta.
