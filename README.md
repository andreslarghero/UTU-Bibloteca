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

## Arquitectura: programación orientada a objetos con MVC

El sitio sigue la estructura vista en clase: **un solo punto de entrada (enrutador)**, controladores como clases, modelos con sus repositorios y vistas separadas por entidad. Los datos se guardan en MySQL y se leen con **PDO** y consultas preparadas.

```
Navegador ──► index.php (enrutador) ──► controladores/LibroController.php ──► modelos/RepositorioLibros.php ──► MySQL
                                                  │                                    │
                                                  └──► vistas/libros/ver.php ◄─────────┘   (objetos Libro, Resena...)
```

Las direcciones se arman con el controlador y la acción:

```
index.php?controlador=libro&accion=ver&id=3        ficha de un libro
index.php?controlador=caja                         caja (acción por defecto: index)
index.php?controlador=libroadmin&accion=editar&id=3
```

### Estructura del repositorio

```
PROYECTO/
├── index.php                 Enrutador: lee controlador y acción y llama al controlador
├── index.html                Dirección anterior: lleva a index.php
├── .htaccess                 Impide abrir los archivos .md desde el navegador
├── config/
│   ├── Conexion.php          Clase Conexion: una sola conexión PDO para toda la aplicación
│   ├── Ruta.php              Arma las direcciones (controlador + acción)
│   └── Texto.php             Utilidades de texto (precio, sin tildes)
├── controladores/            CONTROLADORES: una clase por tema, una acción por método
│   ├── Controller.php        Clase abstracta base: sesión, permisos, avisos, redirigir, render
│   ├── InicioController.php, AuthController.php, LibroController.php, PerfilController.php,
│   │   CompraController.php, CajaController.php, StockController.php, ResenaController.php,
│   │   LibroAdminController.php, UsuarioAdminController.php, OperacionController.php
├── modelos/                  MODELO: entidades y repositorios
│   ├── Repositorio.php       Interfaz: todos() y buscar($id)
│   ├── Usuario.php (abstracta) → Cliente.php, Bibliotecario.php
│   ├── Venta.php (abstracta)   → VentaMostrador.php, VentaDigital.php
│   ├── Libro.php, Resena.php, LineaVenta.php, Ticket.php, ResultadoVenta.php
│   ├── RepositorioUsuarios.php, RepositorioLibros.php,
│   │   RepositorioResenas.php, RepositorioVentas.php
│   └── Portada.php, ArchivoDigital.php, Steam.php   Archivos de portada, PDF/EPUB y aviso al Arduino
├── vistas/                   VISTAS: el HTML, una carpeta por entidad
│   ├── libros/, compras/, caja/, stock/, resenas/, operaciones/, usuarios/, perfil/, auth/, inicio/
│   ├── parciales/            Cabecera, menú, pie y bloque visual de login
│   └── ayudas.php            Precios, estrellas y portadas
├── css/estilos.css           Estilos de todo el sitio
├── js/main.js                JavaScript de todo el sitio
├── img/portadas/             20 portadas locales (.webp) de los libros físicos
├── digitales/                10 PDF de demostración (protegidos con .htaccess)
├── sql/biblioteca.sql        Instalación limpia de la base de datos
└── steam/
    ├── README_STEAM.md       Circuito, conexiones, comandos y validación
    ├── arduino/biblioteca_steam.ino
    └── puente/
        ├── puente_arduino.py Puente PC → Arduino
        ├── requirements.txt
        └── README_PUENTE.md
```

### Qué hace cada capa

| Capa | Qué hace | Qué NO hace |
|---|---|---|
| **Modelo** (`modelos/`) | Las entidades (`Libro`, `Usuario`, `Venta`...) guardan sus datos en atributos privados y validan sus reglas. Los repositorios traducen entre las tablas y los objetos con PDO. | No lee `$_POST` ni `$_SESSION`, no dibuja HTML. |
| **Vista** (`vistas/`) | Dibuja el HTML con los objetos que le entrega el controlador. | No consulta la base de datos ni decide permisos. |
| **Controlador** (`controladores/`) | Comprueba la sesión y el rol, lee y valida lo que envía el navegador, llama a los repositorios, elige el mensaje y la redirección y carga la vista. | No escribe SQL ni HTML. |

### Programación orientada a objetos aplicada

| Concepto | Dónde se usa |
|---|---|
| Clases, atributos privados, constructor, getters | todas las entidades (`Libro`, `Resena`, `LineaVenta`...) |
| Encapsulamiento y validación en la clase | `Libro::validar()`, `Usuario::validarNombreCorreo()`, `Resena::nueva()` (limita la puntuación entre 1 y 5) |
| Herencia (`extends`, `parent::`) | `Cliente` y `Bibliotecario` heredan de `Usuario`; `VentaMostrador` y `VentaDigital` heredan de `Venta`; todos los controladores heredan de `Controller` |
| Clases abstractas | `Usuario`, `Venta` y `Controller` no se instancian |
| Polimorfismo (sobrescritura) | `enlacesMenu()`: el menú del `Bibliotecario` agrega los enlaces de gestión con `parent::enlacesMenu()`. `RepositorioVentas::registrar()` guarda cualquier `Venta` y cada clase hija decide cómo comprobar el libro y qué pasa con el stock |
| Interfaz | `Repositorio` (contrato: `todos()` y `buscar()`) la implementan los cinco repositorios |
| `__toString()` | `Usuario`, `Libro` y `Resena` (las estrellas) |
| Miembros estáticos | `Conexion::getConexion()` (una sola conexión), `Ruta::a()`, `Portada`, `Texto`, `Steam`, `Usuario::desdeFila()` |

Las consultas del cliente (mis compras, descargas) y las del bibliotecario (operaciones) están separadas a propósito en `RepositorioVentas` para no exponer compras ajenas. Las carpetas `config/`, `modelos/`, `controladores/` y `vistas/` tienen un `.htaccess` que impide abrirlas desde el navegador.

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

   `biblioteca.sql` es una **instalación limpia de demostración** (cumple la función de `schema.sql`): crea la base `biblioteca`, sus 5 tablas y datos de ejemplo. No es una copia de ninguna base real.

   El script también crea los usuarios de la base (`nexora_app` y `nexora_consulta`), por eso hay que importarlo con `root`. Para ver ejemplos de consultas de clase se ejecuta después `sql/consultas_clase.sql`.

   > **Atención:** si ya existe una base `biblioteca` en uso, el script borra y vuelve a crear sus tablas. Hacer un backup antes (ver "Copias de seguridad").

4. **Abrir** en el navegador:

   ```
   http://localhost/PROYECTO/
   ```

### Conexión a la base

La web **no usa `root`**: se conecta con la cuenta `nexora_app`, que crea `sql/biblioteca.sql` y solo tiene los permisos que la aplicación necesita (principio de mínimo privilegio). Los datos están en `config/Conexion.php`: servidor `localhost`, usuario `nexora_app`, contraseña de demostración `Nexora_App_2026` y base `biblioteca`. Son valores para un **entorno local**: si el sitio se instala en un servidor hay que cambiar la contraseña (`ALTER USER` en MySQL y la constante en `Conexion.php`).

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

La Caja escribe el archivo de comando en `steam/puente/comando_steam.txt`, **dentro del propio proyecto** (la ruta se calcula desde `modelos/Steam.php`), y el puente lo lee de esa misma carpeta. Por eso funciona en cualquier PC sin configurar nada.

Si en una computadora el puente se ejecuta desde otra carpeta, se puede crear `config/steam_config_local.php` (no se sube al repositorio) con:

```php
<?php define("STEAM_ARCHIVO_COMANDO", "D:/otra/carpeta/steam/puente/comando_steam.txt"); ?>
```

o lanzar el puente con `--archivo "ruta\comando_steam.txt"`.

Si STEAM no está disponible (puente apagado, sin Arduino o sin permiso de escritura), la Caja sigue vendiendo igual.

Más detalle: [`steam/README_STEAM.md`](steam/README_STEAM.md) y [`steam/puente/README_PUENTE.md`](steam/puente/README_PUENTE.md).

---

## Base de datos

Se instala con `sql/biblioteca.sql` y aplica los temas vistos en la materia de Base de Datos:

| Tema | Dónde |
|---|---|
| **DDL**: `IF [NOT] EXISTS`, `AUTO_INCREMENT`, `PRIMARY KEY`, `NOT NULL`, `UNIQUE` (también compuesto), `DEFAULT`, `CHECK` (stock no negativo, puntuación de 1 a 5, reglas entre columnas), `ENUM` (`rol` y `formato`) | las 5 tablas: `usuarios`, `libros`, `ventas`, `venta_lineas` y `resenias` (la categoría es una columna de `libros`) |
| **Claves foráneas y acciones referenciales** | `RESTRICT` (no borrar libros vendidos ni usuarios con ventas) y `CASCADE` (las líneas de una venta y las reseñas dependen de su padre); todas con `ON UPDATE CASCADE` |
| **`ALTER TABLE`** | agrega los índices `idx_libros_titulo` e `idx_ventas_fecha` |
| **`INSERT` de varias filas** | datos de demostración: **30 libros** (20 físicos y 10 digitales, en **9 categorías**), 2 usuarios, 2 ventas con 3 líneas y 2 reseñas |
| **Vistas** | `vw_catalogo` (libros activos con categoría y valoración, sin el archivo digital) y `vw_ventas_detalle` (ventas con el total calculado con sus líneas, el cliente y el vendedor). La web las usa para el catálogo, "Mis compras" y "Operaciones" |
| **Usuarios, permisos y roles** | `nexora_app` (la web: lee todo y escribe solo donde corresponde; no puede borrar libros, ventas ni usuarios, ni cambiar la estructura), `nexora_consulta` (solo ve las vistas, mediante el rol `lector_vistas`) |
| **Motor y juego de caracteres** | InnoDB, `utf8mb4` y `utf8mb4_unicode_ci` |
| **`JOIN`**, **`UNION` / `INTERSECT` / `EXCEPT`**, tablas temporales, `UPDATE` / `DELETE`, `DESCRIBE` | ejemplos ejecutables en `sql/consultas_clase.sql` |

Los 10 archivos de `digitales/` son PDF de demostración propios, de una página: **no contienen las obras originales**.

### Copias de seguridad

En `sql/respaldos/`:

- `respaldar.bat`: crea con `mysqldump` una copia de la base (estructura, vistas y datos, `--single-transaction`, utf8mb4) y un archivo con los permisos de los usuarios, que `mysqldump` no incluye. Las copias quedan en `sql/respaldos/copias/` (no se suben al repositorio).
- `restaurar.bat "archivo.sql"`: restaura una copia.
- `verificar_checklist.sql`: el checklist de clase (motor, charset, claves, vistas, permisos, tablas temporales) y comparaciones antes y después de restaurar.

---

## Seguridad

- Contraseñas cifradas con `password_hash` y comprobadas con `password_verify`.
- **Consultas preparadas** (prepared statements) en todas las consultas con datos del usuario.
- **Roles y estado** (activo o dado de baja) comprobados en la base **en cada petición**.
- Ventas dentro de **transacciones**, con bloqueo de filas para el stock.
- **Descargas protegidas:** los PDF no se pueden abrir por su dirección (`digitales/.htaccess`); solo se entregan a quien compró el libro.
- Textos mostrados con `htmlspecialchars`.
- **Mínimo privilegio en la base:** la web usa la cuenta `nexora_app` (no `root`), que no puede borrar libros, ventas ni usuarios ni cambiar la estructura; la vista `vw_catalogo` no expone el nombre del archivo digital.
- `.htaccess` en `sql/` y `steam/` (y regla para los `.md`) para que no se puedan abrir desde el navegador.

---

## Equipo

- Andrés Larghero Pankovoski
- Juan Manuel Núñez Pereira
- Bruno Nicolás Mariño Perdomo
- Santiago Falero

Los cuatro integrantes participaron de forma compartida en todas las materias.

---

## Modelo de cinco tablas

La base tiene **5 tablas**: `usuarios`, `libros`, `ventas`, `venta_lineas` y `resenias`. La categoría de cada libro es una columna de texto de `libros` (`categoria VARCHAR(50) NOT NULL`): una categoría solo tiene un nombre, así que no necesita tabla propia para cumplir la 3FN. El filtro del catálogo se arma con las categorías que hay en los libros activos. Al guardar un libro, si la categoría ya existe (aunque se escriba sin mayúsculas ni tildes) se usa la grafía guardada.

`ventas` es la cabecera (cliente, vendedor, fecha) y `venta_lineas` el detalle (libro, cantidad, precio histórico y formato). El total no se guarda: lo calcula la vista `vw_ventas_detalle`. El canal no necesita columna: una venta sin vendedor es una compra por la web y una con vendedor es del mostrador.

> **Pendiente de aceptación expresa del profesor:** la grilla original indica seis tablas. Esta versión elimina `categorias` (el profesor autorizó reducir a cinco unificando las ventas; aquí se llega a cinco conservando la 3FN).

### Migrar una base existente (esquema anterior de 6 tablas)

En `sql/migraciones/` (con `root`, **siempre sobre una copia primero**):

1. Respaldo con `sql/respaldos/respaldar.bat` y comprobar que restaura.
2. `001_cinco_tablas_categoria_en_libros.sql`: copia el nombre de cada categoría a `libros.categoria`, quita `categoria_id`, la tabla `categorias`, `ventas.total` (solo si coincide con la suma de las líneas) y la tabla sobrante `resenas` (solo si está vacía), y crea las vistas y las cuentas `nexora_*`.
3. `002_verificar_migracion.sql`: compara cantidades, importes y referencias; debe terminar con `estado = OK`.

Para volver atrás se restaura el respaldo.