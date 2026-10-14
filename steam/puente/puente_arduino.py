# ============================================================
# BIBLIOTECA DIGITAL - NEXORA   |   Parte STEAM
# PUENTE PC -> ARDUINO
#
# Este programa se conecta al Arduino por USB (puerto serie)
# y le envia los comandos que el Arduino sabe mostrar:
#
#   OK|titulo|precio     -> libro encontrado
#   NO_ENCONTRADO        -> el codigo no existe
#   AGOTADO|titulo       -> el libro existe pero no hay stock
#
# El Arduino NO consulta la base de datos: solo muestra lo
# que le manda la PC.
#
# Uso:
#   python puente_arduino.py                  (elige el puerto de una lista)
#   python puente_arduino.py --puerto COM3    (usa ese puerto directamente)
#   python puente_arduino.py --simular        (sin Arduino: no abre ningun puerto)
#   python puente_arduino.py --archivo        (recibe los comandos de la Caja web)
#   python puente_arduino.py --archivo --simular   (lo mismo, sin Arduino)
# ============================================================

import argparse
import os
import sys
import time
import traceback

# ---------- BLOQUE 1: configuracion ----------
BAUD_POR_DEFECTO = 9600      # misma velocidad que usa el Arduino
ESPERA_REINICIO = 2          # segundos: el Arduino se reinicia al abrir el puerto
ESPERA_LISTO = 3             # segundos que se espera el mensaje "LISTO"
ESPERA_RESPUESTA = 4         # segundos que se escuchan respuestas tras un comando
TIMEOUT_LECTURA = 0.2        # segundos que espera cada lectura (para no trabarse)
TIMEOUT_ESCRITURA = 2        # segundos maximos para enviar un comando

# Modo --archivo: la Caja web escribe aqui el resultado de cada escaneo
ARCHIVO_POR_DEFECTO = os.path.join(os.path.dirname(os.path.abspath(__file__)), "comando_steam.txt")
PAUSA_ARCHIVO = 0.2              # segundos entre una revision del archivo y la siguiente
ESPERA_RESPUESTA_ARCHIVO = 1.5   # segundos que se escucha al Arduino tras cada comando de la Caja

# Textos que suelen aparecer en la descripcion de una placa Arduino o compatible
PISTAS_ARDUINO = ["arduino", "ch340", "usb serial", "usb-serial", "wch.cn"]

PALABRAS_SALIR = ["salir", "exit"]

# Se pone en True cuando el programa se ejecuta con --debug
MODO_DEBUG = False

# pyserial se carga recien cuando hace falta (el modo --simular no lo necesita)
serial = None
list_ports = None


def cargar_pyserial():
    """Carga la libreria pyserial. Devuelve True si se pudo."""
    global serial, list_ports
    try:
        import serial as modulo_serial
        from serial.tools import list_ports as modulo_puertos
    except ImportError:
        print("ERROR: falta la libreria pyserial.")
        print("Instalela con:  pip install -r requirements.txt")
        print("(Para probar sin Arduino puede usar:  python puente_arduino.py --simular)")
        return False
    serial = modulo_serial
    list_ports = modulo_puertos
    return True


# ---------- BLOQUE 2: puertos ----------
def parece_arduino(puerto):
    """Devuelve True si la descripcion del puerto se parece a la de un Arduino."""
    texto = " ".join([
        puerto.description or "",
        puerto.manufacturer or "",
        puerto.hwid or "",
    ]).lower()
    for pista in PISTAS_ARDUINO:
        if pista in texto:
            return True
    return False


def listar_puertos():
    """Busca los puertos serie de la PC, los muestra y devuelve la lista."""
    puertos = sorted(list_ports.comports(), key=lambda p: p.device)

    if len(puertos) == 0:
        print("No se detecto ningun puerto serie.")
        return puertos

    print("Puertos detectados:")
    numero = 1
    for puerto in puertos:
        linea = "[" + str(numero) + "] " + puerto.device + " - " + (puerto.description or "sin descripcion")
        if puerto.manufacturer:
            linea = linea + " (" + puerto.manufacturer + ")"
        if parece_arduino(puerto):
            linea = linea + "   <- posible Arduino"
        print(linea)
        numero = numero + 1
    return puertos


def seleccionar_puerto():
    """Muestra los puertos y deja elegir uno. Devuelve el nombre (ej: COM3) o None."""
    puertos = listar_puertos()
    print()
    if len(puertos) == 0:
        print("Revise que el Arduino este conectado por USB.")
        print("Si igual quiere probar, escriba el puerto a mano (ej: COM3).")
    else:
        print("Escriba el numero de la lista, o el puerto a mano (ej: COM3).")
    print("Para cancelar escriba: salir")

    while True:
        try:
            respuesta = input("Seleccione puerto: ").strip()
        except EOFError:
            # Fin de la entrada (Ctrl+Z y Enter): se toma como cancelar
            print()
            return None

        if respuesta.lower() in PALABRAS_SALIR:
            return None
        if respuesta == "":
            continue

        # Opcion 1: un numero de la lista
        if respuesta.isdigit():
            indice = int(respuesta)
            if 1 <= indice <= len(puertos):
                return puertos[indice - 1].device
            print("Ese numero no esta en la lista.")
            continue

        # Opcion 2: el nombre del puerto escrito a mano
        if respuesta.upper().startswith("COM") and respuesta[3:].isdigit():
            return respuesta.upper()

        print("No se entendio. Escriba un numero de la lista o un puerto como COM3.")


# ---------- BLOQUE 3: conexion ----------
def explicar_error(error, puerto):
    """Convierte un error del puerto serie en un mensaje claro en espanol."""
    texto = str(error).lower()
    if "filenotfounderror" in texto or "no se puede encontrar" in texto or "cannot find" in texto:
        return "El puerto " + puerto + " no existe. Revise el nombre y que el Arduino este conectado."
    if "permissionerror" in texto or "acceso denegado" in texto or "access is denied" in texto:
        return ("No se pudo abrir " + puerto + ": permiso denegado o puerto ocupado. "
                "Cierre el Monitor Serie del Arduino IDE u otro programa que lo este usando.")
    if "semaforo" in texto or "semáforo" in texto or "semaphore" in texto:
        return "El puerto " + puerto + " no respondio a tiempo (timeout)."
    return "Problema con el puerto " + puerto + ". Puede que el Arduino se haya desconectado. Detalle: " + str(error)


def mostrar_detalle(error):
    """En modo --debug muestra el detalle tecnico del error. En modo normal no muestra nada."""
    if MODO_DEBUG:
        print("Detalle tecnico:")
        print("  " + repr(error))


def conectar(puerto, baud):
    """Abre el puerto serie. Devuelve la conexion o None si no se pudo."""
    print("Conectando a " + puerto + "...")
    try:
        conexion = serial.Serial(
            port=puerto,
            baudrate=baud,
            timeout=TIMEOUT_LECTURA,
            write_timeout=TIMEOUT_ESCRITURA,
        )
    except serial.SerialException as error:
        print("ERROR: " + explicar_error(error, puerto))
        mostrar_detalle(error)
        return None
    except ValueError as error:
        print("ERROR: configuracion de puerto no valida. Detalle: " + str(error))
        mostrar_detalle(error)
        return None

    print("Conectado.")
    return conexion


def leer_respuestas(conexion, segundos, cortar_en=None):
    """Lee y muestra lo que envia el Arduino durante unos segundos.

    Si se indica cortar_en, deja de leer cuando llega esa linea.
    Devuelve la lista de lineas recibidas (no inventa nada).
    """
    recibidas = []
    fin = time.time() + segundos
    while time.time() < fin:
        datos = conexion.readline()          # espera como mucho TIMEOUT_LECTURA
        if not datos:
            continue
        linea = datos.decode("utf-8", errors="replace").strip()
        if linea == "":
            continue
        print("Arduino -> " + linea)
        recibidas.append(linea)
        if cortar_en is not None and linea == cortar_en:
            break
    return recibidas


# ---------- BLOQUE 4: comandos ----------
def validar_comando(comando):
    """Revisa el formato del comando.

    Devuelve "" si esta bien, o un texto de advertencia si parece incorrecto.
    No bloquea el envio: el Arduino ya avisa "Comando no valido".
    """
    partes = comando.split("|")
    nombre = partes[0]

    if nombre == "OK":
        if len(partes) != 3 or partes[1] == "" or partes[2] == "":
            return "el formato esperado es OK|titulo|precio"
        return ""

    if nombre == "NO_ENCONTRADO":
        if len(partes) != 1:
            return "NO_ENCONTRADO se escribe solo, sin datos"
        return ""

    if nombre == "AGOTADO":
        if len(partes) != 2 or partes[1] == "":
            return "el formato esperado es AGOTADO|titulo"
        return ""

    return "comando desconocido (se aceptan OK|titulo|precio, NO_ENCONTRADO y AGOTADO|titulo)"


def enviar_comando(conexion, comando):
    """Envia el comando al Arduino como texto UTF-8 terminado en salto de linea."""
    datos = (comando + "\n").encode("utf-8")
    conexion.write(datos)
    conexion.flush()
    print("PC -> " + comando)


def mostrar_ayuda_comandos():
    print("Comandos que entiende el Arduino:")
    print("  OK|titulo|precio      ejemplo: OK|1984|590")
    print("  NO_ENCONTRADO")
    print("  AGOTADO|titulo        ejemplo: AGOTADO|Dracula")
    print("Para terminar escriba: salir")
    print()


# ---------- BLOQUE 5: modo interactivo ----------
def modo_interactivo(conexion, simular):
    """Pide comandos por teclado y los envia (o los muestra si es simulacion)."""
    mostrar_ayuda_comandos()

    while True:
        try:
            # strip() quita espacios; lstrip quita una marca invisible (BOM)
            # que algunas consolas de Windows agregan al principio
            comando = input("Comando > ").strip().lstrip("﻿")
        except EOFError:
            print()
            break

        if comando == "":
            continue
        if comando.lower() in PALABRAS_SALIR:
            break

        advertencia = validar_comando(comando)
        if advertencia != "":
            print("ADVERTENCIA: " + advertencia + ".")

        if simular:
            # En simulacion no hay Arduino: solo se muestra lo que se enviaria
            print("SIMULADO -> " + comando)
            continue

        enviar_comando(conexion, comando)
        recibidas = leer_respuestas(conexion, ESPERA_RESPUESTA)
        if len(recibidas) == 0:
            print("(El Arduino no respondio en " + str(ESPERA_RESPUESTA) + " segundos.)")


# ---------- BLOQUE 6: modo archivo (comandos que llegan de la Caja) ----------
def leer_evento(ruta):
    """Lee el archivo de comando que escribe la Caja.

    El archivo tiene UNA linea:  identificador + espacio + comando
    Ejemplo:  6718a3c5e4b0f1.12345678 OK|1984|590

    Devuelve (identificador, comando), o None si todavia no hay nada para leer.
    """
    try:
        with open(ruta, "r", encoding="utf-8", errors="replace") as archivo:
            contenido = archivo.read()
    except OSError:
        return None              # el archivo no existe todavia o esta ocupado

    # Si la linea no termina en salto de linea, la Caja aun la esta escribiendo
    if not contenido.endswith("\n"):
        return None

    linea = contenido.splitlines()[0].strip().lstrip("﻿")
    partes = linea.split(" ", 1)
    if len(partes) != 2 or partes[1].strip() == "":
        return None
    return partes[0], partes[1].strip()


def modo_archivo(conexion, simular, ruta):
    """Queda esperando comandos de la Caja y envia cada evento nuevo una sola vez."""
    print("Esperando comandos de la Caja en:")
    print("  " + ruta)
    print("Para terminar pulse Ctrl+C.")
    print()

    # Lo que ya estaba escrito antes de arrancar es un evento viejo: no se envia
    anterior = leer_evento(ruta)
    ultimo_id = None
    if anterior is not None:
        ultimo_id = anterior[0]

    while True:
        time.sleep(PAUSA_ARCHIVO)

        evento = leer_evento(ruta)
        if evento is None:
            continue

        identificador = evento[0]
        comando = evento[1]

        # Mismo identificador = mismo evento: ya se proceso, no se repite.
        # Dos escaneos iguales seguidos traen identificadores distintos.
        if identificador == ultimo_id:
            continue
        ultimo_id = identificador

        print("CAJA -> " + comando)

        advertencia = validar_comando(comando)
        if advertencia != "":
            print("ADVERTENCIA: " + advertencia + ".")

        if simular:
            # En simulacion no hay Arduino: solo se muestra lo que se enviaria
            print("SIMULADO -> " + comando)
            continue

        enviar_comando(conexion, comando)
        leer_respuestas(conexion, ESPERA_RESPUESTA_ARCHIVO)


# ---------- BLOQUE 7: programa principal ----------
def leer_argumentos():
    parser = argparse.ArgumentParser(
        description="Puente PC -> Arduino de la Biblioteca Digital (NEXORA). "
                    "Envia comandos al Arduino por el puerto serie.",
    )
    parser.add_argument("--puerto", help="puerto serie a usar, por ejemplo COM3 "
                                         "(si no se indica, se elige de una lista)")
    parser.add_argument("--baud", type=int, default=BAUD_POR_DEFECTO,
                        help="velocidad en baudios (por defecto: 9600)")
    parser.add_argument("--simular", action="store_true",
                        help="modo sin Arduino: no abre ningun puerto, solo muestra lo que enviaria")
    parser.add_argument("--archivo", nargs="?", const=ARCHIVO_POR_DEFECTO, metavar="RUTA",
                        help="recibe los comandos de la Caja web desde un archivo "
                             "(por defecto: comando_steam.txt, junto a este programa)")
    parser.add_argument("--debug", action="store_true",
                        help="muestra el detalle tecnico completo si ocurre un error")
    return parser.parse_args()


def ejecutar(argumentos):
    """Hace todo el trabajo. Devuelve 0 si termino bien y 1 si hubo un error."""
    print("=== Puente PC -> Arduino | Biblioteca Digital (NEXORA) ===")
    print()

    # --- Modo simulacion: no se usa ningun puerto ---
    if argumentos.simular:
        print("MODO SIMULACION: no se abre ningun puerto y no se envia nada al Arduino.")
        print()
        if argumentos.archivo:
            modo_archivo(None, True, argumentos.archivo)
        else:
            modo_interactivo(None, True)
        print("Fin de la simulacion.")
        return 0

    # --- Modo real ---
    if not cargar_pyserial():
        return 1

    puerto = argumentos.puerto
    if puerto:
        puerto = puerto.strip().upper()
    else:
        puerto = seleccionar_puerto()
        if puerto is None:
            print("Cancelado.")
            return 0
        print()

    conexion = conectar(puerto, argumentos.baud)
    if conexion is None:
        return 1

    try:
        # Al abrir el puerto el Arduino se reinicia: hay que darle tiempo
        time.sleep(ESPERA_REINICIO)
        print()
        recibidas = leer_respuestas(conexion, ESPERA_LISTO, cortar_en="LISTO")
        if "LISTO" not in recibidas:
            print("AVISO: no llego el mensaje LISTO. Revise que la placa tenga cargado")
            print("       biblioteca_steam.ino y que la velocidad sea " + str(argumentos.baud) + " baud.")
        print()
        if argumentos.archivo:
            modo_archivo(conexion, False, argumentos.archivo)
        else:
            modo_interactivo(conexion, False)
    except serial.SerialTimeoutException as error:
        print()
        print("ERROR: se agoto el tiempo de espera al enviar (timeout). El Arduino no esta recibiendo.")
        mostrar_detalle(error)
        return 1
    except serial.SerialException as error:
        print()
        print("ERROR: " + explicar_error(error, puerto))
        mostrar_detalle(error)
        return 1
    finally:
        # Pase lo que pase, el puerto se cierra
        if conexion.is_open:
            conexion.close()
        print("Puerto " + puerto + " cerrado.")

    return 0


def main():
    global MODO_DEBUG
    argumentos = leer_argumentos()
    MODO_DEBUG = argumentos.debug
    try:
        codigo = ejecutar(argumentos)
    except KeyboardInterrupt:
        # Ctrl+C: el puerto ya se cerro en el bloque finally de ejecutar()
        print()
        print("Interrumpido con Ctrl+C.")
        codigo = 0
    except Exception as error:
        if argumentos.debug:
            traceback.print_exc()
        else:
            print("ERROR inesperado: " + str(error))
            print("(Ejecute con --debug para ver el detalle tecnico.)")
        codigo = 1
    sys.exit(codigo)


if __name__ == "__main__":
    main()
