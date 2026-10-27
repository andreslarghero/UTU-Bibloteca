# Biblioteca Digital NEXORA

Sistema web (PHP 8, MVC con POO y PDO, MariaDB/MySQL) para consultar, vender y entregar libros físicos y digitales.
Proyecto del grupo NEXORA — Tecnicatura en Redes y Software, UTU.

## Cómo instalarlo en otra PC (XAMPP)

1. Instalar [XAMPP](https://www.apachefriends.org/) (Apache, PHP 8 y MariaDB) y encender **Apache** y **MySQL** desde su panel.
2. Descargar este repositorio (botón verde **Code → Download ZIP**, o `git clone`) y copiar la carpeta a `C:\xampp\htdocs\`, por ejemplo como `C:\xampp\htdocs\NEXORA`.
3. Abrir <http://localhost/phpmyadmin>, ir a la pestaña **Importar**, elegir el archivo `sql/biblioteca.sql` y pulsar **Importar**.
   Ese único archivo crea la base `biblioteca` con las **6 tablas** (`usuarios`, `categorias`, `libros`, `ventas`, `venta_lineas`, `resenias`),
   las vistas, las cuentas de la base que usa la web y los datos de demostración (30 libros, 2 usuarios).
4. Abrir <http://localhost/NEXORA/> (el nombre de la carpeta que se haya elegido en el paso 2).

Cuentas de demostración: `cliente@example.com` y `bibliotecario@example.com` (las contraseñas las tiene el equipo).

## Carpetas

| Carpeta | Contenido |
|---|---|
| `index.php` | Enrutador: único punto de entrada (`?controlador=...&accion=...`) |
| `config/` | Conexión a la base (`Conexion`), sesión (`Sesion`) y direcciones (`Ruta`) |
| `controladores/` | Un controlador por tema; cada acción es un método |
| `modelos/` | Entidades y repositorios (consultas con PDO) |
| `vistas/` | Páginas HTML (solo muestran datos) |
| `css/`, `js/`, `img/` | Estilos, JavaScript e imágenes (incluye el video de fondo del inicio) |
| `digitales/` | Libros digitales de demostración (PDF) |
| `sql/` | `biblioteca.sql` (base completa), `consultas_clase.sql` (consultas de clase) y `respaldos/` (backup y restauración) |

## Copia de seguridad (backup)

Con MySQL encendido, doble clic en `sql/respaldos/respaldar.bat` (usa `mysqldump`). Para restaurar: `sql/respaldos/restaurar.bat "ruta/a/la/copia.sql"` (por defecto la restaura en una base de prueba, `biblioteca_restore`).
Las copias se guardan fuera del proyecto, porque traen datos de personas.
