-- ============================================================
-- BASE DE DATOS DE LA BIBLIOTECA DIGITAL
-- Este archivo se importa (en phpMyAdmin, pestaña Importar, o con el
-- cliente mysql de XAMPP) para instalar la base desde cero: crea la base de datos, sus tablas y los datos de
-- prueba que necesita la web.
--
-- Cumple la función de "schema.sql": contiene la creación del
-- esquema (6 tablas con sus claves y restricciones) y los datos
-- iniciales.
--
-- Es una INSTALACIÓN LIMPIA DE DEMOSTRACIÓN. Trae el catálogo
-- completo (30 libros: 20 físicos y 10 digitales, en 9 categorías)
-- y datos de ejemplo: 2 usuarios ficticios, 2 ventas y 2 reseñas.
-- NO es una copia de la base de trabajo del equipo: no contiene
-- sus cuentas, sus ventas ni sus reseñas.
--
-- OJO: si la base ya existe, al importar este archivo se borran
-- y se vuelven a crear TODAS las tablas. Se pierden los usuarios
-- registrados, las ventas, las reseñas y los cambios de stock, y
-- la base vuelve a quedar con los datos de prueba de abajo.
--
-- Contenido (en este orden):
--   1. Base de datos, motor y juego de caracteres.
--   2. Tablas (DDL): usuarios, categorias, libros, ventas, venta_lineas y
--      resenias, con sus restricciones y claves.
--   3. Datos de ejemplo (INSERT).
--   4. Índices (ALTER TABLE ... ADD INDEX).
--   5. Vistas: vw_catalogo y vw_ventas_detalle.
--   6. Usuarios, permisos y roles (CREATE USER, GRANT).
--
-- Temas de clase que aplica este script:
--   DDL: IF [NOT] EXISTS, AUTO_INCREMENT, PRIMARY KEY, NOT NULL,
--        UNIQUE, DEFAULT, CHECK, ENUM, FOREIGN KEY con acciones
--        referenciales (RESTRICT, CASCADE), ALTER TABLE.
--   DML: INSERT de varias filas.
--   Vistas, usuarios y permisos (mínimo privilegio), roles.
--   Checklist de backups: motor InnoDB, utf8mb4 y utf8mb4_unicode_ci.
-- Otros archivos de esta carpeta:
--   consultas_clase.sql          JOIN, UNION, INTERSECT, EXCEPT, vistas,
--                                tablas temporales, UPDATE y DELETE.
--   respaldos/                   respaldar.bat y restaurar.bat (mysqldump) y
--                                verificar_checklist.sql (checklist de backups).
-- ============================================================

-- Para importar este archivo hace falta una cuenta con permiso para crear
-- bases de datos y usuarios (en XAMPP, "root").

-- Crea la base de datos "biblioteca" (si no existe) y la selecciona
-- para que las siguientes instrucciones se ejecuten dentro de ella.
-- utf8mb4 es el juego de caracteres que permite guardar acentos, eñes y emojis
-- (el "utf8" de MySQL solo guarda 3 bytes por letra). utf8mb4_unicode_ci es la
-- collation (el orden y la comparación de textos) recomendada.
-- Todas las tablas usan el motor InnoDB: es el único que admite transacciones y
-- claves foráneas, que esta web necesita (la caja y las compras usan transacciones).
CREATE DATABASE IF NOT EXISTS biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioteca;

-- ------------------------------------------------------------
-- BORRADO DE VISTAS Y TABLAS
-- Primero las vistas (dependen de las tablas). Después las tablas, en
-- orden: primero las que dependen de otras (por sus claves foráneas) y al
-- final las tablas "padre".
--   resenias      depende de libros y de usuarios
--   venta_lineas  depende de ventas y de libros
--   ventas        depende de usuarios
--   libros        depende de categorias
-- (resenas ya no existe: se borra por si la base venía de un esquema anterior
-- que tenía una tabla "resenas" sobrante.)
-- ------------------------------------------------------------
DROP VIEW IF EXISTS vw_catalogo;
DROP VIEW IF EXISTS vw_ventas_detalle;
DROP TABLE IF EXISTS resenias;
DROP TABLE IF EXISTS venta_lineas;
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS libros;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS resenas;
DROP TABLE IF EXISTS usuarios;

-- ------------------------------------------------------------
-- TABLA USUARIOS
-- Guarda las cuentas que se crean desde la página de registro
-- y desde la gestión de usuarios.
-- Los usuarios no se borran: se dan de baja con activo = 0, para
-- conservar sus ventas y sus reseñas. Un usuario dado de baja no
-- puede iniciar sesión.
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada usuario (se asigna solo)
    nombre VARCHAR(100) NOT NULL,          -- Nombre de la persona
    correo VARCHAR(150) NOT NULL UNIQUE,   -- Correo electrónico; UNIQUE impide repetirlo
    clave VARCHAR(255) NOT NULL,           -- Contraseña cifrada (nunca en texto normal)
    rol ENUM('cliente', 'bibliotecario') NOT NULL DEFAULT 'cliente',   -- Tipo de usuario: ENUM es una lista cerrada de textos permitidos
    activo TINYINT(1) NOT NULL DEFAULT 1,  -- Baja lógica: 1 = puede iniciar sesión, 0 = dado de baja
    CONSTRAINT chk_usuarios_activo CHECK (activo IN (0, 1))                    -- Solo se permite 0 o 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- USUARIOS DE DEMOSTRACIÓN
-- Un usuario de cada rol, para poder entrar a la web recién instalada.
-- Son cuentas ficticias, creadas solo para mostrar el proyecto: no
-- pertenecen a ninguna persona real (los correos usan example.com,
-- un dominio reservado para ejemplos).
-- Las contraseñas se guardan cifradas (las generó password_hash de PHP)
-- y no se escriben en este archivo.
--
--   Rol            Correo
--   bibliotecario  bibliotecario@example.com
--   cliente        cliente@example.com
--
-- Cámbialas o dalas de baja si la web se usa de verdad.
-- ------------------------------------------------------------
-- Los dos quedan activos (el último valor, 1, es "activo").
INSERT INTO usuarios (nombre, correo, clave, rol, activo) VALUES
('Bibliotecario de prueba', 'bibliotecario@example.com', '$2y$10$.Qz7KS59CAsFQ0xLM8wsweUM3AgdrR79iBETC3Qe925.Ij9MI3eB2', 'bibliotecario', 1),   -- id 1
('Cliente de prueba', 'cliente@example.com', '$2y$10$Ka52N4wv./iZEY/sSi87A.k1Qns2oVBDXP22Y71QqIU3BsUaymm8i', 'cliente', 1);                 -- id 2

-- Las cuentas que se crean desde la página de registro quedan como 'cliente'.
-- Un bibliotecario puede cambiar el rol de cualquier cuenta desde la web,
-- en "Gestionar usuarios".

-- ------------------------------------------------------------
-- TABLA CATEGORIAS
-- Lista de categorías (géneros) a las que puede pertenecer un libro.
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada categoría (se asigna solo)
    nombre VARCHAR(50) NOT NULL UNIQUE     -- Nombre de la categoría; UNIQUE impide repetirlo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las categorías quedan con los id 1 a 9, en este orden.
INSERT INTO categorias (nombre) VALUES
('Ciencia ficción'),   -- 1
('Distopía'),          -- 2
('Fábula'),            -- 3
('Fantasía'),          -- 4
('Ficción'),           -- 5
('Novela'),            -- 6
('Novela clásica'),    -- 7
('Realismo mágico'),   -- 8
('Romance');           -- 9

-- ------------------------------------------------------------
-- TABLA LIBROS
-- ------------------------------------------------------------
CREATE TABLE libros (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada libro (se asigna solo)
    titulo VARCHAR(150) NOT NULL,          -- Título del libro
    autor VARCHAR(100) NOT NULL,           -- Autor del libro
    anio INT,                              -- Año de publicación
    portada VARCHAR(255),                  -- Portada: ruta del archivo en el proyecto (img/portadas/...)
    categoria_id INT NOT NULL,             -- Categoría del libro (id de la tabla categorias)
    stock INT NOT NULL DEFAULT 0,          -- Copias del formato físico (0 = agotado)
    isbn VARCHAR(20) UNIQUE,               -- ISBN del libro; puede quedar vacío (NULL), pero no repetirse
    precio DECIMAL(8,2) NOT NULL DEFAULT 0,       -- Precio del formato físico
    codigo_barras VARCHAR(20) NULL UNIQUE,        -- Código de barras del libro físico (el ISBN, si tiene); no se repite. Vacío (NULL) si el libro no tiene formato físico
    descripcion TEXT,                      -- Resumen que se muestra en la página de detalle
    activo TINYINT(1) NOT NULL DEFAULT 1,  -- Baja lógica: 1 = visible en el catálogo, 0 = dado de baja
    formato_fisico TINYINT(1) NOT NULL DEFAULT 1,     -- 1 = el libro se vende en papel (usa precio, stock y codigo_barras)
    formato_digital TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = el libro se vende en digital (usa precio_digital y archivo_digital)
    precio_digital DECIMAL(8,2) NULL,                 -- Precio del formato digital (NULL si no tiene)
    archivo_digital VARCHAR(255) NULL,                -- Nombre del archivo PDF o EPUB guardado en la carpeta "digitales" (NULL si no tiene)
    CONSTRAINT chk_libros_stock CHECK (stock >= 0),    -- El stock nunca puede ser negativo
    -- La categoría tiene que existir. ON DELETE RESTRICT: no se puede borrar una categoría que
    -- tiene libros. ON UPDATE CASCADE: si cambia el id de la categoría, se actualiza aquí.
    CONSTRAINT fk_libros_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_libros_formato_fisico CHECK (formato_fisico IN (0, 1)),      -- Solo se permite 0 o 1
    CONSTRAINT chk_libros_formato_digital CHECK (formato_digital IN (0, 1)),    -- Solo se permite 0 o 1
    CONSTRAINT chk_libros_algun_formato CHECK (formato_fisico = 1 OR formato_digital = 1),   -- Todo libro tiene al menos un formato
    CONSTRAINT chk_libros_fisico CHECK (formato_fisico = 0 OR codigo_barras IS NOT NULL),   -- Si es físico, tiene que tener código de barras
    CONSTRAINT chk_libros_digital CHECK (formato_digital = 0 OR (precio_digital IS NOT NULL AND archivo_digital IS NOT NULL)),   -- Si es digital, tiene que tener precio y archivo
    CONSTRAINT chk_libros_precio_digital CHECK (precio_digital IS NULL OR precio_digital >= 0)   -- El precio digital no puede ser negativo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LIBROS DEL CATÁLOGO
-- El catálogo de demostración tiene 30 libros: 20 físicos y 10
-- digitales. Se cargan en tres bloques:
--   id  1 a 10  libros físicos
--   id 11       libro digital de prueba
--   id 12 a 21  libros físicos
--   id 22 a 30  libros digitales
--
-- PRIMER BLOQUE (id 1 a 10)
-- Las portadas son archivos del proyecto, en la carpeta img/portadas.
-- Después de la portada van: id de la categoría, stock, ISBN,
-- precio y código de barras (el mismo ISBN, que es el código
-- impreso en el libro físico).
-- Quedan solo en formato físico (los valores por defecto:
-- formato_fisico = 1 y formato_digital = 0).
-- ------------------------------------------------------------
INSERT INTO libros (titulo, autor, anio, portada, categoria_id, stock, isbn, precio, codigo_barras, descripcion) VALUES
('Cien años de soledad', 'Gabriel García Márquez', 1967, 'img/portadas/cien-anos-de-soledad.webp', 8, 5, '9780307474728', 650.00, '9780307474728',
 'La historia de siete generaciones de la familia Buendía en el pueblo ficticio de Macondo, desde su fundación hasta su desaparición. Una de las obras más importantes de la literatura latinoamericana.'),
('Don Quijote de la Mancha', 'Miguel de Cervantes', 1605, 'img/portadas/don-quijote-de-la-mancha.webp', 7, 3, '9780060934347', 890.00, '9780060934347',
 'Un hidalgo que ha leído demasiados libros de caballerías decide convertirse en caballero andante y recorre España junto a su escudero Sancho Panza. Considerada la primera novela moderna.'),
('1984', 'George Orwell', 1949, 'img/portadas/1984.webp', 2, 4, '9780451524935', 590.00, '9780451524935',
 'En un estado totalitario vigilado por el Gran Hermano, Winston Smith trabaja reescribiendo la historia hasta que empieza a cuestionar el sistema. Una advertencia sobre el control y la manipulación.'),
('El principito', 'Antoine de Saint-Exupéry', 1943, 'img/portadas/el-principito.webp', 3, 6, '9780156012195', 390.00, '9780156012195',
 'Un aviador perdido en el desierto conoce a un pequeño príncipe llegado de otro planeta. A través de sus viajes, el libro reflexiona sobre la amistad, el amor y la forma de ver el mundo de los adultos.'),
('Harry Potter y la piedra filosofal', 'J. K. Rowling', 1997, 'img/portadas/harry-potter-y-la-piedra-filosofal.webp', 4, 0, '9780590353427', 750.00, '9780590353427',
 'Harry descubre en su undécimo cumpleaños que es un mago y entra en el colegio Hogwarts, donde hace amigos, aprende magia y se enfrenta al misterio de la piedra filosofal.'),
('El hobbit', 'J. R. R. Tolkien', 1937, 'img/portadas/el-hobbit.webp', 4, 2, '9780547928227', 690.00, '9780547928227',
 'Bilbo Bolsón, un hobbit tranquilo, se une a un mago y a trece enanos en un viaje para recuperar un tesoro custodiado por el dragón Smaug.'),
('Orgullo y prejuicio', 'Jane Austen', 1813, 'img/portadas/orgullo-y-prejuicio.webp', 9, 3, '9780141439518', 490.00, '9780141439518',
 'Elizabeth Bennet y el señor Darcy superan sus primeras impresiones y sus diferencias sociales en la Inglaterra rural del siglo XIX.'),
('El alquimista', 'Paulo Coelho', 1988, 'img/portadas/el-alquimista.webp', 5, 1, '9780062315007', 550.00, '9780062315007',
 'Santiago, un joven pastor andaluz, viaja hasta Egipto en busca de un tesoro que vio en sueños y aprende a seguir su propia leyenda personal.'),
('Fahrenheit 451', 'Ray Bradbury', 1953, 'img/portadas/fahrenheit-451.webp', 1, 0, '9781451673319', 620.00, '9781451673319',
 'En una sociedad donde los libros están prohibidos, el bombero Guy Montag se encarga de quemarlos, hasta que empieza a preguntarse qué contienen.'),
('Matar a un ruiseñor', 'Harper Lee', 1960, 'img/portadas/matar-a-un-ruisenor.webp', 6, 4, '9780061120084', 580.00, '9780061120084',
 'Narrada por la pequeña Scout Finch, cuenta cómo su padre, el abogado Atticus Finch, defiende a un hombre negro acusado injustamente en el sur de Estados Unidos.');

-- ------------------------------------------------------------
-- LIBROS DIGITALES Y SUS ARCHIVOS
-- Un libro digital no tiene código de barras ni stock, y su precio
-- físico queda en 0. Tiene precio_digital y archivo_digital.
--
-- archivo_digital es el NOMBRE de un archivo PDF que tiene que
-- estar en la carpeta "digitales" del proyecto. Los 10 archivos
-- vienen con el proyecto: son PDF de demostración de una página,
-- creados para este trabajo. NO contienen las obras originales.
-- Si falta el archivo, el libro se ve en el catálogo pero no se
-- puede descargar.
--
-- SEGUNDO BLOQUE (id 11): libro digital de prueba.
-- ------------------------------------------------------------
INSERT INTO libros (titulo, autor, anio, portada, categoria_id, stock, isbn, precio, codigo_barras, descripcion, activo, formato_fisico, formato_digital, precio_digital, archivo_digital) VALUES
('Libro Digital de Prueba', 'Proyecto Biblioteca Digital', NULL, NULL, 5, 0, NULL, 0, NULL,
 'Libro de muestra creado para el proyecto. Sirve para demostrar la compra y la descarga de libros digitales. No es un libro real.', 1, 0, 1, 300.00, 'libro_c5c0d7a1bcc430e9a4408df25da84966.pdf');

-- ------------------------------------------------------------
-- TERCER BLOQUE (id 12 a 21): 10 libros físicos más.
-- Igual que los primeros: el código de barras es el ISBN. Hay
-- stocks variados y uno agotado (Drácula, stock 0).
-- ------------------------------------------------------------
INSERT INTO libros (titulo, autor, anio, portada, categoria_id, stock, isbn, precio, codigo_barras, descripcion, activo, formato_fisico, formato_digital, precio_digital, archivo_digital) VALUES
('Crónica de una muerte anunciada', 'Gabriel García Márquez', 1981, 'img/portadas/cronica-de-una-muerte-anunciada.webp', 6, 5, '9781400034956', 480.00, '9781400034956',
 'En un pueblo del Caribe todos saben que van a matar a Santiago Nasar, pero nadie lo impide. La novela reconstruye, como una investigación, las horas previas al crimen.', 1, 1, 0, NULL, NULL),
('Rayuela', 'Julio Cortázar', 1963, 'img/portadas/rayuela.webp', 6, 3, '9788437604572', 720.00, '9788437604572',
 'La historia de Horacio Oliveira entre París y Buenos Aires, contada en capítulos que se pueden leer en más de un orden. Una de las novelas más originales de la literatura en español.', 1, 1, 0, NULL, NULL),
('La metamorfosis', 'Franz Kafka', 1915, 'img/portadas/la-metamorfosis.webp', 5, 8, '9780553213690', 350.00, '9780553213690',
 'Gregorio Samsa despierta una mañana convertido en un enorme insecto. Su familia y él mismo deben aprender a convivir con esa transformación.', 1, 1, 0, NULL, NULL),
('El retrato de Dorian Gray', 'Oscar Wilde', 1890, 'img/portadas/el-retrato-de-dorian-gray.webp', 7, 4, '9780141439570', 520.00, '9780141439570',
 'Un joven desea conservar para siempre su belleza y es su retrato el que envejece y refleja sus actos. Una reflexión sobre la vanidad y la conciencia.', 1, 1, 0, NULL, NULL),
('Drácula', 'Bram Stoker', 1897, 'img/portadas/dracula.webp', 7, 0, '9780141439846', 560.00, '9780141439846',
 'A través de diarios y cartas se cuenta la llegada del conde Drácula a Inglaterra y la lucha de un grupo de personas por detenerlo. El clásico de las novelas de vampiros.', 1, 1, 0, NULL, NULL),
('Frankenstein', 'Mary Shelley', 1818, 'img/portadas/frankenstein.webp', 1, 6, '9780141439471', 540.00, '9780141439471',
 'El científico Víctor Frankenstein da vida a una criatura y luego la abandona. Considerada una de las primeras novelas de ciencia ficción.', 1, 1, 0, NULL, NULL),
('Los juegos del hambre', 'Suzanne Collins', 2008, 'img/portadas/los-juegos-del-hambre.webp', 2, 10, '9780439023528', 690.00, '9780439023528',
 'En un país dividido en distritos, Katniss Everdeen ocupa el lugar de su hermana en una competencia televisada en la que solo puede quedar un sobreviviente.', 1, 1, 0, NULL, NULL),
('El nombre del viento', 'Patrick Rothfuss', 2007, 'img/portadas/el-nombre-del-viento.webp', 4, 2, '9780756404741', 850.00, '9780756404741',
 'Kvothe, músico, mago y aventurero, cuenta su propia historia: su infancia entre artistas ambulantes, sus años en la Universidad y el origen de su leyenda.', 1, 1, 0, NULL, NULL),
('La sombra del viento', 'Carlos Ruiz Zafón', 2001, 'img/portadas/la-sombra-del-viento.webp', 6, 7, '9780143034902', 780.00, '9780143034902',
 'En la Barcelona de posguerra, el joven Daniel encuentra un libro en el Cementerio de los Libros Olvidados y descubre que alguien está destruyendo todas las obras de su autor.', 1, 1, 0, NULL, NULL),
('Rebelión en la granja', 'George Orwell', 1945, 'img/portadas/rebelion-en-la-granja.webp', 3, 1, '9780451526342', 420.00, '9780451526342',
 'Los animales de una granja expulsan a los humanos para gobernarse solos, pero poco a poco los cerdos se quedan con el poder. Una fábula sobre la corrupción de los ideales.', 1, 1, 0, NULL, NULL);

-- ------------------------------------------------------------
-- CUARTO BLOQUE (id 22 a 30): 9 libros digitales.
-- Son obras clásicas de dominio público, usadas solo como datos
-- del catálogo. Sus archivos son los PDF de demostración.
-- ------------------------------------------------------------
INSERT INTO libros (titulo, autor, anio, portada, categoria_id, stock, isbn, precio, codigo_barras, descripcion, activo, formato_fisico, formato_digital, precio_digital, archivo_digital) VALUES
('La Odisea', 'Homero', NULL, NULL, 7, 0, NULL, 0, NULL,
 'El largo viaje de regreso de Odiseo a Ítaca después de la guerra de Troya. Edición digital de demostración.', 1, 0, 1, 180.00, 'libro_e7b6252be74fa26954bb2594f3902a93.pdf'),
('Lazarillo de Tormes', 'Anónimo', 1554, NULL, 7, 0, NULL, 0, NULL,
 'Lázaro cuenta, con humor e ironía, su vida al servicio de distintos amos. La primera novela picaresca. Edición digital de demostración.', 1, 0, 1, 150.00, 'libro_48d13210ec36c63e16e157b3740775fd.pdf'),
('Martín Fierro', 'José Hernández', 1872, NULL, 5, 0, NULL, 0, NULL,
 'Poema narrativo sobre la vida del gaucho Martín Fierro, obligado a dejar su hogar. Obra fundamental de la literatura rioplatense. Edición digital de demostración.', 1, 0, 1, 160.00, 'libro_9299340935913ba14e628433fa68b438.pdf'),
('La vuelta al mundo en ochenta días', 'Julio Verne', 1872, NULL, 6, 0, NULL, 0, NULL,
 'Phileas Fogg apuesta que puede dar la vuelta al mundo en ochenta días y parte de Londres junto a su ayudante. Edición digital de demostración.', 1, 0, 1, 220.00, 'libro_97c776407634e8f306f96673a25f60b1.pdf'),
('Viaje al centro de la Tierra', 'Julio Verne', 1864, NULL, 1, 0, NULL, 0, NULL,
 'Un profesor, su sobrino y un guía descienden por un volcán de Islandia en busca del centro de la Tierra. Edición digital de demostración.', 1, 0, 1, 220.00, 'libro_62ebc9b8f65f694c79cbf107c5a6a18b.pdf'),
('La isla del tesoro', 'Robert Louis Stevenson', 1883, NULL, 6, 0, NULL, 0, NULL,
 'El joven Jim Hawkins encuentra el mapa de un tesoro pirata y se embarca en una expedición llena de traiciones. Edición digital de demostración.', 1, 0, 1, 200.00, 'libro_9dc4f841fa0063f97c7a16198b1f7c63.pdf'),
('Alicia en el país de las maravillas', 'Lewis Carroll', 1865, NULL, 4, 0, NULL, 0, NULL,
 'Alicia cae por una madriguera y llega a un mundo de personajes extraños y lógica absurda. Edición digital de demostración.', 1, 0, 1, 190.00, 'libro_cd7975ebe0f23365ed30c1d43dce491c.pdf'),
('Cuentos de la selva', 'Horacio Quiroga', 1918, NULL, 3, 0, NULL, 0, NULL,
 'Ocho cuentos protagonizados por animales de la selva misionera, escritos por el autor uruguayo para sus hijos. Edición digital de demostración.', 1, 0, 1, 170.00, 'libro_bae438c40723616b811cca8597f53e7f.pdf'),
('María', 'Jorge Isaacs', 1867, NULL, 9, 0, NULL, 0, NULL,
 'La historia de amor entre Efraín y María en el Valle del Cauca. Una de las novelas románticas más importantes de la literatura hispanoamericana. Edición digital de demostración.', 1, 0, 1, 180.00, 'libro_621aeba858890ce54b344dd53a4b809f.pdf');

-- ------------------------------------------------------------
-- TABLA VENTAS
-- Cada fila es una venta (la cabecera): quién compró, quién vendió y
-- cuándo. Los libros vendidos van en la tabla venta_lineas, y el total
-- no se guarda: se calcula sumando sus líneas (vista vw_ventas_detalle),
-- así nunca puede quedar distinto de lo que se vendió.
-- El cliente o el vendedor pueden quedar vacíos (NULL), por
-- ejemplo en una venta de mostrador a alguien sin cuenta, pero
-- no los dos a la vez. El canal no necesita una columna: una venta
-- sin vendedor es una compra por la web (de un libro digital o de un libro
-- físico, que el cliente retira en el mostrador) y una con vendedor es de
-- mostrador (caja). Los libros físicos descuentan stock en los dos canales.
-- Las claves foráneas usan ON DELETE RESTRICT: no se puede borrar
-- un usuario que tiene ventas (el historial no se pierde). Los
-- usuarios se dan de baja (activo = 0) en lugar de borrarse.
-- ------------------------------------------------------------
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- Número único de cada venta (se asigna solo)
    id_cliente INT NULL,                         -- Usuario que compró (id de la tabla usuarios); puede quedar vacío
    id_vendedor INT NULL,                        -- Bibliotecario que vendió (id de la tabla usuarios); puede quedar vacío
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- Fecha y hora de la venta (se pone sola)
    CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente) REFERENCES usuarios(id)      -- El cliente tiene que existir
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ventas_vendedor FOREIGN KEY (id_vendedor) REFERENCES usuarios(id)    -- El vendedor tiene que existir
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ventas_cliente_o_vendedor CHECK (id_cliente IS NOT NULL OR id_vendedor IS NOT NULL)   -- No pueden faltar los dos
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA VENTA_LINEAS
-- Cada fila es un libro dentro de una venta, con la cantidad y
-- el precio al que se vendió (se guarda aparte del precio del
-- libro para que la venta no cambie si el libro sube de precio).
--
-- Acciones referenciales de las dos claves foráneas:
--   * libro_id: ON DELETE RESTRICT. No se puede borrar un libro que
--     ya fue vendido. Para quitarlo del catálogo se usa la baja
--     lógica (activo = 0).
--   * venta_id: ON DELETE CASCADE. Las líneas son el "detalle" de la
--     venta (la "cabecera"): no tienen sentido sin ella, así que al
--     borrar una venta se borran también sus líneas.
-- ------------------------------------------------------------
CREATE TABLE venta_lineas (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- Número único de cada línea (se asigna solo)
    venta_id INT NOT NULL,                       -- Venta a la que pertenece (id de la tabla ventas)
    libro_id INT NOT NULL,                       -- Libro vendido (id de la tabla libros)
    cantidad INT NOT NULL,                       -- Cuántas copias se vendieron
    precio DECIMAL(8,2) NOT NULL,                -- Precio de cada copia en el momento de la venta
    formato ENUM('fisico', 'digital') NOT NULL DEFAULT 'fisico',   -- En qué formato se vendió (lista cerrada)
    CONSTRAINT fk_venta_lineas_venta FOREIGN KEY (venta_id) REFERENCES ventas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_venta_lineas_libro FOREIGN KEY (libro_id) REFERENCES libros(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_venta_lineas_cantidad CHECK (cantidad > 0),    -- La cantidad tiene que ser positiva
    CONSTRAINT chk_venta_lineas_precio CHECK (precio >= 0)        -- El precio no puede ser negativo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- VENTAS DE PRUEBA
-- Venta 1: el bibliotecario de prueba (usuario 1) le vende al
--          cliente de prueba (usuario 2).
-- Venta 2: venta de mostrador a alguien sin cuenta (sin cliente).
-- El total de cada venta es la suma de cantidad x precio de sus líneas
-- (la vista vw_ventas_detalle lo calcula).
-- ------------------------------------------------------------
INSERT INTO ventas (id_cliente, id_vendedor, fecha) VALUES
(2, 1, '2026-09-30 10:15:00'),      -- venta 1
(NULL, 1, '2026-10-01 16:40:00');   -- venta 2

INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES
(1, 2, 1, 890.00, 'fisico'),   -- venta 1: 1 x Don Quijote de la Mancha   =  890.00
(1, 4, 2, 390.00, 'fisico'),   -- venta 1: 2 x El principito              =  780.00  (total 1670.00)
(2, 3, 1, 590.00, 'fisico');   -- venta 2: 1 x 1984                       =  590.00  (total  590.00)

-- ------------------------------------------------------------
-- TABLA RESENIAS
-- Guarda las valoraciones (1 a 5 estrellas) y comentarios que
-- los usuarios dejan en cada libro.
-- Una reseña depende del libro y del usuario: ON DELETE CASCADE
-- (si se borra el libro o el usuario, se borran sus reseñas).
-- ------------------------------------------------------------
CREATE TABLE resenias (
    id INT AUTO_INCREMENT PRIMARY KEY,           -- Número único de cada reseña
    libro_id INT NOT NULL,                       -- Libro reseñado (id de la tabla libros)
    usuario_id INT NOT NULL,                     -- Quién la escribió (id de la tabla usuarios)
    puntuacion TINYINT NOT NULL,                 -- Estrellas, de 1 a 5
    comentario TEXT,                             -- Opinión escrita del usuario
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,    -- Fecha en que se escribió (se pone sola)
    UNIQUE (libro_id, usuario_id),               -- Cada usuario solo puede tener una reseña por libro
    CONSTRAINT fk_resenias_libro FOREIGN KEY (libro_id) REFERENCES libros(id)          -- El libro tiene que existir
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resenias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)    -- El usuario tiene que existir
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_resenias_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)              -- La puntuación va de 1 a 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- RESEÑAS DE PRUEBA
-- Dos reseñas del cliente de prueba (usuario 2):
-- una de Don Quijote (libro 2) y otra de 1984 (libro 3).
-- ------------------------------------------------------------
INSERT INTO resenias (libro_id, usuario_id, puntuacion, comentario) VALUES
(2, 2, 5, 'Un clásico que vale la pena leer. Muy divertido.'),
(3, 2, 4, 'Una historia que hace pensar. Muy recomendable.');

-- ============================================================
-- ÍNDICES (ALTER TABLE ... ADD INDEX)
-- ALTER TABLE cambia la estructura de una tabla ya creada. Aquí agrega
-- dos índices secundarios (en DESCRIBE aparecen como "MUL"): aceleran
-- las búsquedas y los ordenamientos que usa la web.
--   idx_libros_titulo  el catálogo y "Gestionar libros" ordenan por título.
--   idx_ventas_fecha   "Mis compras" y "Operaciones" ordenan por fecha.
-- Las claves foráneas y los campos UNIQUE ya crean su propio índice.
-- ============================================================
ALTER TABLE libros ADD INDEX idx_libros_titulo (titulo);
ALTER TABLE ventas ADD INDEX idx_ventas_fecha (fecha);

-- ============================================================
-- VISTAS
-- Una vista es una consulta guardada con un nombre: no guarda datos
-- propios, cada vez que se consulta se ejecuta el SELECT que la define.
-- Convención: el nombre empieza con "vw_". Se escriben las columnas una
-- por una (sin SELECT *) para que un cambio en la tabla no cambie la vista.
-- Las dos son vistas "complejas" (usan JOIN y GROUP BY): sirven
-- para consultar, no para modificar datos.
-- ============================================================

-- Catálogo: los libros activos con su categoría, la valoración media y la
-- cantidad de reseñas (LEFT JOIN para incluir libros sin reseñas).
-- No incluye el nombre del archivo digital: la vista oculta esa columna.
-- La usa la página del catálogo.
CREATE VIEW vw_catalogo AS
SELECT libros.id, libros.titulo, libros.autor, libros.anio, libros.portada, libros.descripcion,
       libros.categoria_id, categorias.nombre AS categoria, libros.isbn, libros.precio,
       libros.codigo_barras, libros.stock, libros.activo, libros.formato_fisico,
       libros.formato_digital, libros.precio_digital,
       ROUND(AVG(resenias.puntuacion), 1) AS media, COUNT(resenias.id) AS total_resenas
FROM libros
JOIN categorias ON categorias.id = libros.categoria_id
LEFT JOIN resenias ON resenias.libro_id = libros.id
WHERE libros.activo = 1
GROUP BY libros.id, libros.titulo, libros.autor, libros.anio, libros.portada, libros.descripcion,
         libros.categoria_id, categorias.nombre, libros.isbn, libros.precio, libros.codigo_barras,
         libros.stock, libros.activo, libros.formato_fisico, libros.formato_digital, libros.precio_digital;

-- Ventas con el total (suma de cantidad x precio de sus líneas) y el nombre del
-- cliente y del vendedor. La tabla usuarios se une dos veces (una para cada
-- uno), por eso lleva dos nombres. LEFT JOIN incluye las ventas sin cliente
-- (de mostrador) o sin vendedor (compras por la web).
-- La usan "Mis compras" y "Operaciones" (cada consulta agrega su propio filtro).
CREATE VIEW vw_ventas_detalle AS
SELECT ventas.id, ventas.fecha, COALESCE(SUM(venta_lineas.cantidad * venta_lineas.precio), 0) AS total,
       ventas.id_cliente, ventas.id_vendedor,
       clientes.nombre AS cliente, vendedores.nombre AS vendedor
FROM ventas
LEFT JOIN venta_lineas ON venta_lineas.venta_id = ventas.id
LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
GROUP BY ventas.id, ventas.fecha, ventas.id_cliente, ventas.id_vendedor, clientes.nombre, vendedores.nombre;

-- ============================================================
-- USUARIOS Y PERMISOS (principio de mínimo privilegio)
-- La web NO se conecta como "root": usa una cuenta propia que solo puede
-- hacer lo que la aplicación necesita. Un usuario nace sin permisos; cada
-- GRANT agrega uno. Cada cuenta es "usuario"@"host": 'localhost' significa
-- que solo puede conectarse desde la misma computadora.
-- Las contraseñas de abajo son para uso local y de demostración: hay que
-- cambiarlas si el sitio se instala en un servidor.
-- ============================================================

-- 1. Cuenta de la aplicación web (config/Conexion.php).
CREATE USER IF NOT EXISTS 'nexora_app'@'localhost' IDENTIFIED BY 'Nexora_App_2026';
ALTER USER 'nexora_app'@'localhost' IDENTIFIED BY 'Nexora_App_2026';

-- Puede leer todo (tablas y vistas)...
GRANT SELECT ON biblioteca.* TO 'nexora_app'@'localhost';
-- ...y escribir solo en lo que la web escribe:
GRANT INSERT, UPDATE ON biblioteca.usuarios TO 'nexora_app'@'localhost';       -- registro, perfil y gestión de usuarios
GRANT INSERT, UPDATE ON biblioteca.libros TO 'nexora_app'@'localhost';         -- gestión de libros, stock y ventas
GRANT INSERT ON biblioteca.ventas TO 'nexora_app'@'localhost';                 -- caja y compras digitales
GRANT INSERT ON biblioteca.venta_lineas TO 'nexora_app'@'localhost';           -- caja y compras digitales
GRANT INSERT, UPDATE, DELETE ON biblioteca.resenias TO 'nexora_app'@'localhost';   -- reseñas (la única tabla donde se borra)
-- No tiene CREATE, DROP, ALTER ni GRANT: la web no puede cambiar la estructura de la base.
-- Tampoco puede borrar usuarios, libros o ventas.

-- 2. Cuenta de solo consulta, que ve únicamente las vistas (por ejemplo, para
-- reportes). No necesita acceso directo a las tablas: ve lo que la vista devuelve.
-- Se usa un ROL: un conjunto de permisos que se le asigna a varios usuarios.
CREATE USER IF NOT EXISTS 'nexora_consulta'@'localhost' IDENTIFIED BY 'Nexora_Consulta_2026';
ALTER USER 'nexora_consulta'@'localhost' IDENTIFIED BY 'Nexora_Consulta_2026';
CREATE ROLE IF NOT EXISTS 'lector_vistas';
GRANT SELECT ON biblioteca.vw_catalogo TO 'lector_vistas';
GRANT SELECT ON biblioteca.vw_ventas_detalle TO 'lector_vistas';
GRANT 'lector_vistas' TO 'nexora_consulta'@'localhost';
-- En MariaDB (XAMPP) el rol por defecto se indica así. En MySQL 8 la sentencia es:
--   SET DEFAULT ROLE 'lector_vistas' TO 'nexora_consulta'@'localhost';
SET DEFAULT ROLE 'lector_vistas' FOR 'nexora_consulta'@'localhost';

FLUSH PRIVILEGES;

-- Para auditar los permisos:
--   SHOW GRANTS FOR 'nexora_app'@'localhost';
--   SHOW GRANTS FOR 'nexora_consulta'@'localhost';
--   SHOW GRANTS FOR 'lector_vistas';
--   SELECT user, host FROM mysql.user;
