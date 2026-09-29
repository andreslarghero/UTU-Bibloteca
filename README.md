# Biblioteca Digital

Página web básica de una biblioteca digital hecha con **HTML, CSS, PHP y MySQL**.

## Funcionalidades

- Registro e inicio de sesión de usuarios (nombre, correo y contraseña cifrada).
- Catálogo de 10 libros con portada, autor, año y disponibilidad.
- Buscador de libros por título o autor.
- Página de detalle de cada libro con género y descripción.
- Reseñas y valoraciones de 1 a 5 estrellas (cada usuario puede escribir, editar y eliminar su reseña).
- Panel de administrador:
  - **Gestionar stock**: cambiar las copias disponibles de cada libro (0 = agotado).
  - **Gestionar reseñas**: ver y eliminar cualquier reseña.

## Estructura

```
index.html            Página de inicio
css/estilos.css       Estilos de todas las páginas
php/conexion.php      Conexión a la base de datos
php/registro.php      Crear cuenta
php/login.php         Iniciar sesión
php/logout.php        Cerrar sesión
php/libros.php        Catálogo y buscador
php/libro.php         Detalle del libro y reseñas
php/stock.php         Gestión de stock (solo admin)
php/resenas.php       Gestión de reseñas (solo admin)
sql/biblioteca.sql    Base de datos: tablas y libros
```

## Instalación (con XAMPP)

1. Instala [XAMPP](https://www.apachefriends.org/) y arranca **Apache** y **MySQL** desde el panel de control.
2. Copia la carpeta del proyecto dentro de `C:\xampp\htdocs\` con el nombre `PROYECTO`.
3. Abre phpMyAdmin (`http://localhost/phpmyadmin`), ve a la pestaña **Importar** y sube el archivo `sql/biblioteca.sql`.
   Se crea la base de datos `biblioteca` con las tablas `usuarios`, `libros` y `resenas`.
4. Abre `http://localhost/PROYECTO/index.html` en el navegador.

Si tu MySQL usa un usuario o contraseña distintos a los de XAMPP (`root` sin contraseña), cámbialos en `php/conexion.php`.

## Crear un administrador

Todas las cuentas nuevas se crean como usuarios normales. Para convertir una en administrador:

1. Regístrate en la web.
2. En phpMyAdmin, entra en la base `biblioteca`, abre la pestaña **SQL** y ejecuta (con tu correo):

   ```sql
   UPDATE usuarios SET admin = 1 WHERE correo = 'tucorreo@gmail.com';
   ```

3. Cierra sesión y vuelve a entrar. Aparecerán en el menú **Gestionar stock** y **Gestionar reseñas**.

## Notas

- Las portadas se cargan desde [Open Library](https://openlibrary.org/), así que hace falta conexión a internet para verlas.
- Si vuelves a importar `sql/biblioteca.sql`, los usuarios y las reseñas se mantienen, pero la tabla de libros se vuelve a crear y el stock regresa a los valores del archivo.
