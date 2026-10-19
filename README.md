# Biblioteca Digital - NEXORA

Aplicación web de una biblioteca que vende libros **físicos** (en el mostrador) y **digitales** (por la web), con una parte **STEAM**: un Arduino con pantalla LCD, LED y zumbador que muestra el resultado de cada libro escaneado en la Caja.

Proyecto académico del grupo **NEXORA** (grupo de clase TH2, 2026).

> **Estado:** proyecto terminado. Diseño Web, Programación, Base de Datos, Ingeniería de Software y STEAM completos. STEAM validado con la placa física.

---

## Tecnologías

- PHP 8 (extensión `mysqli`)
- MySQL / MariaDB
- HTML
- CSS (una sola hoja: `css/estilos.css`)
- JavaScript puro, sin librerías (`js/main.js`)
- Arduino (programa para Arduino Uno)
- Python 3
- pyserial

No usa frameworks ni servicios externos: funciona en una computadora local, sin internet.

---

## Requisitos

- **XAMPP** (Apache, PHP 8 y MySQL/MariaDB) en Windows.
- Un navegador actual.
- Para STEAM:
  - **Python 3** con `pyserial`.
  - **Arduino IDE**, si se usa la placa física.
  - Arduino Uno, LCD 16x2 con módulo I2C, LED verde, LED rojo, dos resistencias de 220 ohm y un zumbador (piezo).

---

## Arquitectura: Modelo - Vista - Controlador

El sitio sigue el patrón MVC. Cada dirección (por ejemplo `php/caja.php`) es un punto de entrada de 3 líneas que llama a su controlador; así las direcciones no cambian.

```
Navegador ──► php/caja.php ──► controladores/caja.php ──► modelos/venta.php ──► MySQL
                                       │                        │
                                       └──► vistas/caja.php ◄───┘  (datos como arreglos)
```

| Capa            | Carpeta          | Qué hace                                                                                                                                 | Qué NO hace                                      |
| --------------- | ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| **Modelo**      | `modelos/`       | Todo el SQL (consultas preparadas), las transacciones de venta y la validación de archivos de portada. Devuelve arreglos.                | No lee `$_POST` ni `$_SESSION`, no dibuja HTML.  |
| **Vista**       | `vistas/`        | El HTML de cada página y sus partes comunes (`parciales/`). Muestra los datos que le entrega el controlador.                             | No consulta la base de datos ni decide permisos. |
| **Controlador** | `controladores/` | Verifica sesión y rol, lee y valida lo que envía el navegador, llama a los modelos, elige el mensaje y la redirección, y carga la vista. | No escribe SQL ni HTML.                          |

Reglas del proyecto: ninguna vista usa `mysqli_*`; ningún controlador escribe SQL; las consultas del cliente (mis compras, descargas) y las del bibliotecario (operaciones) están separadas a propósito en `modelos/venta.php` para no exponer compras ajenas. Las carpetas `modelos/`, `controladores/` y `vistas/` tienen un `.htaccess` que impide abrirlas desde el navegador.

---

## Estructura del repositorio

```
PROYECTO/
├── index.php                 Punto de entrada de la página de inicio
├── index.html                Dirección anterior: lleva a index.php
├── .htaccess                 Impide abrir los archivos .md desde el navegador
├── css/estilos.css           Estilos de todo el sitio
├── js/main.js                JavaScript de todo el sitio
├── img/portadas/             20 portadas locales (.webp) de los libros físicos
├── digitales/                10 PDF de demostración (protegidos con .htaccess)
├── modelos/                  MODELO: acceso a la base de datos y reglas de los datos
│   ├── conexion.php          Conexión a la base de datos y funciones de consulta
│   ├── usuario.php, libro.php, categoria.php, venta.php, resena.php
│   └── portada.php           Validación de los archivos de portada
├── vistas/                   VISTA: HTML de cada página
│   ├── caja.php, libros.php, libro.php, ... (una por página)
│   ├── ayudas.php            Precios, estrellas y portadas
│   └── parciales/            Cabecera, menú, pie y bloque visual de login
├── controladores/            CONTROLADOR: recibe la petición, valida y decide
│   ├── caja.php, libros.php, libro.php, ... (uno por página)
│   ├── verificar_usuario.php, verificar_bibliotecario.php   Control de acceso
│   ├── ayudas.php            Lectura de datos del navegador, validaciones y avisos
│   └── steam.php             Aviso al Arduino (STEAM)
├── php/                      Puntos de entrada: cada URL (php/caja.php...) solo llama a su controlador
├── sql/biblioteca.sql        Instalación limpia de la base de datos
└── steam/
    ├── README_STEAM.md       Circuito, conexiones, comandos y validación
    ├── arduino/biblioteca_steam.ino
    └── puente/
        ├── puente_arduino.py Puente PC → Arduino
        ├── requirements.txt
        └── README_PUENTE.md
```

Los documentos de la entrega (documento final, planilla de pruebas, matriz de riesgos, actas, DER y documentación de Ingeniería y de Base de Datos) se entregan aparte y **no forman parte de este repositorio**.

---

## Instalación

1. **Clonar** el repositorio dentro de la carpeta web de XAMPP:

   ```
   cd C:\xampp\htdocs
   git clone https://github.com/andreslarghero/UTU-Bibloteca.git PROYECTO
   ```

   También se puede clonar en otro lugar y copiar la carpeta a `C:\xampp\htdocs\PROYECTO`, incluidos los archivos `.htaccess`.

2. **Iniciar Apache y MySQL** desde el panel de control de XAMPP.

3. **Crear la base de datos** importando `sql/biblioteca.sql`: abrir `http://localhost/phpmyadmin`, ir a **Importar**, elegir `sql/biblioteca.sql` y pulsar **Importar**.

   `biblioteca.sql` es una **instalación limpia de demostración** (cumple la función de `schema.sql`): crea la base `biblioteca`, sus 6 tablas y datos de ejemplo. No es una copia de ninguna base real.

   > **Atención:** si ya existe una base `biblioteca` en uso, el script borra y vuelve a crear sus tablas. Hacer un backup antes.

4. **Abrir** en el navegador:

   ```
   http://localhost/PROYECTO/
   ```

### Conexión a la base

Los datos de conexión están en `modelos/conexion.php` y vienen con los valores por defecto de XAMPP para un **entorno local**: servidor `localhost`, usuario `root` sin contraseña y base `biblioteca`. Si el MySQL de la computadora usa otros datos, se cambian en ese archivo. **No usar esta configuración en un servidor público.**

### Cuentas de demostración

La instalación limpia trae dos cuentas ficticias: `bibliotecario@example.com` (bibliotecario) y `cliente@example.com` (cliente). Sus contraseñas no se publican (en el SQL están cifradas) y se entregan por separado al docente. También se puede crear una cuenta nueva desde **Registrarse** (queda como cliente) y cambiar su rol desde **Gestionar usuarios** con una cuenta de bibliotecario.

---

## Funcionalidades

| Rol           | Qué puede hacer                                                                                                                       |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| Visitante     | Ver el inicio, registrarse e iniciar sesión                                                                                           |
| Cliente       | Catálogo, búsqueda, filtro por categoría, ficha del libro, reseñas, perfil, compra digital, Mis compras y descarga del libro comprado |
| Bibliotecario | Todo lo del cliente, más Caja del mostrador, Operaciones, Stock, Libros, Usuarios y Reseñas                                           |

- El catálogo requiere haber iniciado sesión.
- La compra digital es **simulada** (no hay medio de pago).
- Los libros y los usuarios no se borran: se dan de **baja lógica** y se pueden reactivar.
- El sitio es **responsive** (de 320 px a 1920 px) y funciona también sin JavaScript.

---

## STEAM

Lector + PC + Arduino. El lector de códigos de barras se usa en la **Caja** (solo el bibliotecario) y lee **solo libros físicos**. La Caja busca el código en la base con PHP; el Arduino **no consulta la base**: solo muestra el resultado.

```
Caja PHP (busca en MySQL)
        ↓
comando local (steam/puente/comando_steam.txt)
        ↓
puente Python (puente_arduino.py)
        ↓  USB Serial, 9600 baud
Arduino Uno
        ↓
LCD 16x2 I2C + LED verde / rojo + zumbador
```

| Comando              | Cuándo                   | LCD                  | LED   | Sonido    |
| -------------------- | ------------------------ | -------------------- | ----- | --------- |
| `OK\|titulo\|precio` | Libro físico con stock   | Título / `$precio`   | Verde | Bip corto |
| `NO_ENCONTRADO`      | El código no existe      | `No encontrado`      | Rojo  | Bip largo |
| `AGOTADO\|titulo`    | Libro físico con stock 0 | Título / `Sin stock` | Rojo  | Tres bips |

**Conexiones:** LCD I2C (VCC → 5V, GND → GND, SDA → A4, SCL → A5) · LED verde: D8 → 220 ohm → LED → GND · LED rojo: D9 → 220 ohm → LED → GND · zumbador: D10 → positivo, negativo → GND.

### Puesta en marcha

1. Cargar `steam/arduino/biblioteca_steam.ino` en el Arduino Uno con el Arduino IDE y conectarlo por USB.
2. Instalar la dependencia del puente (una sola vez), desde la carpeta del proyecto:

   ```
   pip install -r steam/puente/requirements.txt
   ```

3. Ejecutar el puente en modo archivo (recibe los escaneos de la Caja):

   ```
   python steam/puente/puente_arduino.py --archivo
   ```

   Elegir el puerto del Arduino de la lista (o indicarlo con `--puerto COM3`) y esperar `Arduino -> LISTO`.

4. Entrar a la Caja como bibliotecario y escanear un libro.

Sin Arduino, el mismo flujo se puede ver en la consola con el modo simulación:

```
python steam/puente/puente_arduino.py --archivo --simular
```

### Ruta del archivo de comando

La Caja escribe el archivo de comando en `steam/puente/comando_steam.txt`, **dentro del propio proyecto** (la ruta se calcula desde `php/steam.php`), y el puente lo lee de esa misma carpeta. Por eso funciona en cualquier PC sin configurar nada.

Si en una computadora el puente se ejecuta desde otra carpeta, se puede crear `php/steam_config_local.php` (no se sube al repositorio) con:

```php
<?php define("STEAM_ARCHIVO_COMANDO", "D:/otra/carpeta/steam/puente/comando_steam.txt"); ?>
```

o lanzar el puente con `--archivo "ruta\comando_steam.txt"`.

Si STEAM no está disponible (puente apagado, sin Arduino o sin permiso de escritura), la Caja sigue vendiendo igual.

Más detalle: [`steam/README_STEAM.md`](steam/README_STEAM.md) y [`steam/puente/README_PUENTE.md`](steam/puente/README_PUENTE.md).

---

## Base de datos

- **6 tablas:** `usuarios`, `categorias`, `libros`, `ventas`, `venta_lineas` y `resenias`.
- Claves primarias y foráneas (con `ON DELETE RESTRICT`), `UNIQUE` en el correo y en el código de barras, y restricciones `CHECK` (stock no negativo, puntuación de 1 a 5, cliente o vendedor en cada venta, etc.).
- Datos de demostración: **30 libros** (20 físicos y 10 digitales), **9 categorías**, 2 usuarios, 2 ventas con 3 líneas y 2 reseñas.
- Los 10 archivos de `digitales/` son PDF de demostración propios, de una página: **no contienen las obras originales**.

---

## Seguridad

- Contraseñas cifradas con `password_hash` y comprobadas con `password_verify`.
- **Consultas preparadas** (prepared statements) en todas las consultas con datos del usuario.
- **Roles y estado** (activo o dado de baja) comprobados en la base **en cada petición**.
- Ventas dentro de **transacciones**, con bloqueo de filas para el stock.
- **Descargas protegidas:** los PDF no se pueden abrir por su dirección (`digitales/.htaccess`); solo se entregan a quien compró el libro.
- Textos mostrados con `htmlspecialchars`.
- `.htaccess` en `sql/` y `steam/` (y regla para los `.md`) para que no se puedan abrir desde el navegador.

---

## Equipo

- Andrés Larghero Pankovoski
- Juan Manuel Núñez Pereira
- Bruno Nicolás Mariño Perdomo
- Santiago Falero

Los cuatro integrantes participaron de forma compartida en todas las materias.
