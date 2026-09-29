-- ============================================================
-- BASE DE DATOS DE LA BIBLIOTECA DIGITAL
-- Este archivo se importa en phpMyAdmin para crear todo lo
-- que necesita la web: la base de datos y sus tablas.
-- ============================================================

-- Crea la base de datos "biblioteca" (si no existe) y la selecciona
-- para que las siguientes instrucciones se ejecuten dentro de ella.
CREATE DATABASE IF NOT EXISTS biblioteca;
USE biblioteca;

-- ------------------------------------------------------------
-- TABLA USUARIOS
-- Guarda las cuentas que se crean desde la página de registro.
-- "IF NOT EXISTS" hace que, al volver a importar el archivo,
-- no se borren los usuarios ya registrados.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada usuario (se asigna solo)
    nombre VARCHAR(100) NOT NULL,          -- Nombre de la persona
    correo VARCHAR(150) NOT NULL UNIQUE,   -- Correo (Gmail); UNIQUE impide repetirlo
    clave VARCHAR(255) NOT NULL,           -- Contraseña cifrada (nunca en texto normal)
    admin TINYINT(1) NOT NULL DEFAULT 0    -- 1 = administrador (puede gestionar el stock), 0 = usuario normal
);

-- Para convertir una cuenta en administrador, ejecuta esto en phpMyAdmin
-- cambiando el correo por el de la cuenta:
-- UPDATE usuarios SET admin = 1 WHERE correo = 'tucorreo@gmail.com';

-- ------------------------------------------------------------
-- TABLA RESEÑAS
-- Guarda las valoraciones (1 a 5 estrellas) y comentarios que
-- los usuarios dejan en cada libro. "IF NOT EXISTS" hace que,
-- al volver a importar el archivo, no se borren las reseñas.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resenas (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- Número único de cada reseña
    libro_id INT NOT NULL,                       -- Libro reseñado (id de la tabla libros)
    usuario_id INT NOT NULL,                     -- Quién la escribió (id de la tabla usuarios)
    puntuacion TINYINT NOT NULL,                 -- Estrellas, de 1 a 5
    comentario TEXT,                             -- Opinión escrita del usuario
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,    -- Fecha en que se escribió (se pone sola)
    UNIQUE (libro_id, usuario_id)                -- Cada usuario solo puede tener una reseña por libro
);

-- ------------------------------------------------------------
-- TABLA LIBROS
-- Se borra y se vuelve a crear para que, al importar de nuevo,
-- el catálogo quede siempre con los libros de abajo sin repetir.
-- Ojo: al importar de nuevo, el stock vuelve a los valores de abajo.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS libros;

CREATE TABLE libros (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada libro (se asigna solo)
    titulo VARCHAR(150) NOT NULL,          -- Título del libro
    autor VARCHAR(100) NOT NULL,           -- Autor del libro
    anio INT,                              -- Año de publicación
    portada VARCHAR(255),                  -- Dirección (URL) de la imagen de portada
    genero VARCHAR(50),                    -- Género literario
    descripcion TEXT,                      -- Resumen que se muestra en la página de detalle
    stock INT NOT NULL DEFAULT 0           -- Copias disponibles (0 = agotado)
);

-- ------------------------------------------------------------
-- LIBROS DEL CATÁLOGO
-- Inserta los 10 libros que se muestran en la web.
-- Las portadas se cargan desde Open Library usando el ISBN.
-- El número después del género es el stock (copias disponibles).
-- ------------------------------------------------------------
INSERT INTO libros (titulo, autor, anio, portada, genero, stock, descripcion) VALUES
('Cien años de soledad', 'Gabriel García Márquez', 1967, 'https://covers.openlibrary.org/b/isbn/9780307474728-L.jpg', 'Realismo mágico', 5,
 'La historia de siete generaciones de la familia Buendía en el pueblo ficticio de Macondo, desde su fundación hasta su desaparición. Una de las obras más importantes de la literatura latinoamericana.'),
('Don Quijote de la Mancha', 'Miguel de Cervantes', 1605, 'https://covers.openlibrary.org/b/isbn/9780060934347-L.jpg', 'Novela clásica', 3,
 'Un hidalgo que ha leído demasiados libros de caballerías decide convertirse en caballero andante y recorre España junto a su escudero Sancho Panza. Considerada la primera novela moderna.'),
('1984', 'George Orwell', 1949, 'https://covers.openlibrary.org/b/isbn/9780451524935-L.jpg', 'Distopía', 4,
 'En un estado totalitario vigilado por el Gran Hermano, Winston Smith trabaja reescribiendo la historia hasta que empieza a cuestionar el sistema. Una advertencia sobre el control y la manipulación.'),
('El principito', 'Antoine de Saint-Exupéry', 1943, 'https://covers.openlibrary.org/b/isbn/9780156012195-L.jpg', 'Fábula', 6,
 'Un aviador perdido en el desierto conoce a un pequeño príncipe llegado de otro planeta. A través de sus viajes, el libro reflexiona sobre la amistad, el amor y la forma de ver el mundo de los adultos.'),
('Harry Potter y la piedra filosofal', 'J. K. Rowling', 1997, 'https://covers.openlibrary.org/b/isbn/9780590353427-L.jpg', 'Fantasía', 0,
 'Harry descubre en su undécimo cumpleaños que es un mago y entra en el colegio Hogwarts, donde hace amigos, aprende magia y se enfrenta al misterio de la piedra filosofal.'),
('El hobbit', 'J. R. R. Tolkien', 1937, 'https://covers.openlibrary.org/b/isbn/9780547928227-L.jpg', 'Fantasía', 2,
 'Bilbo Bolsón, un hobbit tranquilo, se une a un mago y a trece enanos en un viaje para recuperar un tesoro custodiado por el dragón Smaug.'),
('Orgullo y prejuicio', 'Jane Austen', 1813, 'https://covers.openlibrary.org/b/isbn/9780141439518-L.jpg', 'Romance', 3,
 'Elizabeth Bennet y el señor Darcy superan sus primeras impresiones y sus diferencias sociales en la Inglaterra rural del siglo XIX.'),
('El alquimista', 'Paulo Coelho', 1988, 'https://covers.openlibrary.org/b/isbn/9780062315007-L.jpg', 'Ficción', 1,
 'Santiago, un joven pastor andaluz, viaja hasta Egipto en busca de un tesoro que vio en sueños y aprende a seguir su propia leyenda personal.'),
('Fahrenheit 451', 'Ray Bradbury', 1953, 'https://covers.openlibrary.org/b/isbn/9781451673319-L.jpg', 'Ciencia ficción', 0,
 'En una sociedad donde los libros están prohibidos, el bombero Guy Montag se encarga de quemarlos, hasta que empieza a preguntarse qué contienen.'),
('Matar a un ruiseñor', 'Harper Lee', 1960, 'https://covers.openlibrary.org/b/isbn/9780061120084-L.jpg', 'Novela', 4,
 'Narrada por la pequeña Scout Finch, cuenta cómo su padre, el abogado Atticus Finch, defiende a un hombre negro acusado injustamente en el sur de Estados Unidos.');
