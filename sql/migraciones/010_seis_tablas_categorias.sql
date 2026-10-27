-- ============================================================
-- MIGRACIÓN 010: VOLVER A SEIS TABLAS (la categoría vuelve a ser una tabla)
--
-- La grilla de requisitos dice: «Las seis tablas: usuarios · categorias ·
-- libros · ventas · venta_lineas · resenias».
--
-- Esquema de partida (cinco tablas):  libros.categoria VARCHAR(50)
-- Esquema de llegada (seis tablas):   categorias(id, nombre UNIQUE) y
--                                     libros.categoria_id con clave foránea
--
-- Cada categoría distinta que ya usan los libros pasa a ser una fila de
-- "categorias", y cada libro apunta a la suya con categoria_id. Los datos de
-- los libros no cambian: solo se reemplaza el texto por el id.
--
-- ANTES DE EJECUTAR:
--   1. Respaldo completo y verificado (sql/respaldos/respaldar.bat).
--   2. Probarlo primero sobre una copia (base temporal).
--   3. Importar con una cuenta de administrador (root).
-- Se vuelve atrás restaurando ese respaldo.
--
-- DESPUÉS: ejecutar 011_verificar_seis_tablas.sql (todo debe dar 0 / 'OK').
-- Es un script de una sola ejecución: si algo falla en el medio, se restaura
-- el respaldo y se corrige antes de volver a intentar.
-- ============================================================

USE biblioteca;

-- Paso 1: las vistas que usan libros.categoria se apartan (se vuelven a crear al final).
DROP VIEW IF EXISTS vw_catalogo;

-- Paso 2: la tabla de categorías (la misma definición que en sql/biblioteca.sql).
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,     -- Número único de cada categoría (se asigna solo)
    nombre VARCHAR(50) NOT NULL UNIQUE     -- Nombre de la categoría; UNIQUE impide repetirlo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paso 3: una fila por cada categoría distinta que ya existe en los libros.
INSERT INTO categorias (nombre)
SELECT DISTINCT categoria FROM libros ORDER BY categoria;

-- Paso 4: cada libro recibe el id de su categoría (columna nueva, todavía vacía).
ALTER TABLE libros ADD COLUMN categoria_id INT NULL AFTER portada;

UPDATE libros
JOIN categorias ON categorias.nombre = libros.categoria
SET libros.categoria_id = categorias.id;

-- Paso 5: la columna pasa a ser obligatoria. Si algún libro quedó sin categoría
-- esta sentencia falla y el script se detiene ANTES de borrar el texto viejo.
ALTER TABLE libros MODIFY COLUMN categoria_id INT NOT NULL;

-- Paso 6: clave foránea (no se puede borrar una categoría que tiene libros) y
-- retiro de la columna de texto con su restricción.
ALTER TABLE libros
    ADD CONSTRAINT fk_libros_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE libros DROP CONSTRAINT chk_libros_categoria;
ALTER TABLE libros DROP COLUMN categoria;

-- Paso 7: la vista del catálogo vuelve a unir libros con categorias.
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
