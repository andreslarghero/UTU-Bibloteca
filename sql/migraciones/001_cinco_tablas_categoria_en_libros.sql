-- ============================================================
-- MIGRACIÓN 001: DE 7 TABLAS (esquema anterior) A 5 TABLAS
--
-- Esquema anterior: usuarios, categorias, libros, ventas, venta_lineas,
--                   resenias (+ la tabla sobrante "resenas", vacía).
-- Esquema nuevo:    usuarios, libros, ventas, venta_lineas, resenias.
--
-- Qué hace (en este orden):
--   1. Agrega libros.categoria (texto) y copia en ella el nombre de la
--      categoría de cada libro.
--   2. Comprueba que ningún libro quedó sin categoría.
--   3. Quita la clave foránea y la columna libros.categoria_id, y la tabla
--      categorias.
--   4. Quita ventas.total (el total se calcula con las líneas) SOLO si el
--      total guardado coincide con la suma de las líneas en todas las ventas.
--   5. Quita la tabla "resenas" SOLO si está vacía y nada depende de ella.
--   6. Crea las vistas vw_catalogo y vw_ventas_detalle del esquema nuevo y las
--      cuentas nexora_app / nexora_consulta (las crea si no existen; si ya existen,
--      solo ajusta sus permisos).
--
-- CÓMO USARLO (con "root"):
--   a) Hacer un respaldo con sql/respaldos/respaldar.bat y comprobar que restaura.
--   b) PROBARLO PRIMERO sobre una COPIA de la base (otra instancia o base temporal).
--   c) Ejecutar 002_verificar_migracion.sql; debe terminar con estado = OK.
-- NO ejecutar sobre la base real sin autorización.
--
-- La migración está pensada para ejecutarse UNA sola vez. Si algo no cumple
-- (una categoría vacía, un total distinto), se detiene con un error y la
-- parte ya hecha queda a medias: por eso se prueba antes en una copia.
-- Para volver atrás se restaura el respaldo.
-- ============================================================

USE biblioteca;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Controles previos (solo informan)
-- ------------------------------------------------------------
SELECT 'libros' AS tabla, COUNT(*) AS filas FROM libros
UNION ALL SELECT 'categorias', COUNT(*) FROM categorias
UNION ALL SELECT 'ventas', COUNT(*) FROM ventas
UNION ALL SELECT 'venta_lineas', COUNT(*) FROM venta_lineas
UNION ALL SELECT 'resenias', COUNT(*) FROM resenias
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios;

-- ------------------------------------------------------------
-- 1. La categoría pasa a ser un texto de la tabla libros
-- ------------------------------------------------------------
ALTER TABLE libros ADD COLUMN categoria VARCHAR(50) NULL AFTER portada;

UPDATE libros
JOIN categorias ON categorias.id = libros.categoria_id
SET libros.categoria = categorias.nombre;

-- 2. Ningún libro puede quedar sin categoría. Si esta consulta devuelve
--    filas, hay que revisar los datos antes de seguir (el paso 3 fallaría).
SELECT id AS libro_sin_categoria FROM libros WHERE categoria IS NULL OR TRIM(categoria) = '';

ALTER TABLE libros MODIFY COLUMN categoria VARCHAR(50) NOT NULL;
ALTER TABLE libros ADD CONSTRAINT chk_libros_categoria CHECK (CHAR_LENGTH(TRIM(categoria)) > 0);

-- ------------------------------------------------------------
-- 3. Se quitan la clave foránea, la columna y la tabla de categorías
--    (las vistas que usan categorias se recrean más abajo)
-- ------------------------------------------------------------
DROP VIEW IF EXISTS vw_catalogo;
DROP VIEW IF EXISTS vw_ventas_detalle;

ALTER TABLE libros DROP FOREIGN KEY fk_libros_categoria;
ALTER TABLE libros DROP COLUMN categoria_id;
DROP TABLE categorias;

-- ------------------------------------------------------------
-- 4. ventas.total: se quita solo si coincide con la suma de las líneas
-- ------------------------------------------------------------
-- Las ventas cuyo total guardado es distinto de la suma de sus líneas.
-- Tiene que devolver 0 filas; si devuelve alguna, NO se debe quitar el total.
SELECT ventas.id AS venta_con_total_distinto, ventas.total AS total_guardado,
       COALESCE(SUM(venta_lineas.cantidad * venta_lineas.precio), 0) AS total_de_lineas
FROM ventas
LEFT JOIN venta_lineas ON venta_lineas.venta_id = ventas.id
GROUP BY ventas.id, ventas.total
HAVING ventas.total <> COALESCE(SUM(venta_lineas.cantidad * venta_lineas.precio), 0);

ALTER TABLE ventas DROP COLUMN total;

-- ------------------------------------------------------------
-- 5. La tabla sobrante "resenas" (sin "i"): se quita solo si está vacía.
--    Ningún código la usa (la web usa "resenias") y no tiene claves foráneas.
-- ------------------------------------------------------------
SET @filas_resenas = (SELECT COUNT(*) FROM resenas);
SET @orden = IF(@filas_resenas = 0, 'DROP TABLE resenas',
                'SELECT ''resenas tiene filas: NO se borra, revisar a mano'' AS aviso');
PREPARE paso FROM @orden;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- ------------------------------------------------------------
-- 6. Vistas del esquema nuevo
-- ------------------------------------------------------------
CREATE VIEW vw_catalogo AS
SELECT libros.id, libros.titulo, libros.autor, libros.anio, libros.portada, libros.descripcion,
       libros.categoria, libros.isbn, libros.precio,
       libros.codigo_barras, libros.stock, libros.activo, libros.formato_fisico,
       libros.formato_digital, libros.precio_digital,
       ROUND(AVG(resenias.puntuacion), 1) AS media, COUNT(resenias.id) AS total_resenas
FROM libros
LEFT JOIN resenias ON resenias.libro_id = libros.id
WHERE libros.activo = 1
GROUP BY libros.id, libros.titulo, libros.autor, libros.anio, libros.portada, libros.descripcion,
         libros.categoria, libros.isbn, libros.precio, libros.codigo_barras,
         libros.stock, libros.activo, libros.formato_fisico, libros.formato_digital, libros.precio_digital;

CREATE VIEW vw_ventas_detalle AS
SELECT ventas.id, ventas.fecha, COALESCE(SUM(venta_lineas.cantidad * venta_lineas.precio), 0) AS total,
       ventas.id_cliente, ventas.id_vendedor,
       clientes.nombre AS cliente, vendedores.nombre AS vendedor
FROM ventas
LEFT JOIN venta_lineas ON venta_lineas.venta_id = ventas.id
LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
GROUP BY ventas.id, ventas.fecha, ventas.id_cliente, ventas.id_vendedor, clientes.nombre, vendedores.nombre;

-- Índices del esquema nuevo (si ya existen, estas dos líneas dan error y se pueden saltar)
-- ALTER TABLE libros ADD INDEX idx_libros_titulo (titulo);
-- ALTER TABLE ventas ADD INDEX idx_ventas_fecha (fecha);

-- ------------------------------------------------------------
-- Cuentas de la base (las mismas que crea sql/biblioteca.sql)
-- ------------------------------------------------------------
CREATE USER IF NOT EXISTS 'nexora_app'@'localhost' IDENTIFIED BY 'Nexora_App_2026';
GRANT SELECT ON biblioteca.* TO 'nexora_app'@'localhost';
GRANT INSERT, UPDATE ON biblioteca.usuarios TO 'nexora_app'@'localhost';
GRANT INSERT, UPDATE ON biblioteca.libros TO 'nexora_app'@'localhost';
GRANT INSERT ON biblioteca.ventas TO 'nexora_app'@'localhost';
GRANT INSERT ON biblioteca.venta_lineas TO 'nexora_app'@'localhost';
GRANT INSERT, UPDATE, DELETE ON biblioteca.resenias TO 'nexora_app'@'localhost';

CREATE USER IF NOT EXISTS 'nexora_consulta'@'localhost' IDENTIFIED BY 'Nexora_Consulta_2026';
CREATE ROLE IF NOT EXISTS 'lector_vistas';
GRANT SELECT ON biblioteca.vw_catalogo TO 'lector_vistas';
GRANT SELECT ON biblioteca.vw_ventas_detalle TO 'lector_vistas';
GRANT 'lector_vistas' TO 'nexora_consulta'@'localhost';
SET DEFAULT ROLE 'lector_vistas' FOR 'nexora_consulta'@'localhost';
FLUSH PRIVILEGES;

-- ------------------------------------------------------------
-- Controles finales (solo informan; el detalle está en 002_verificar_migracion.sql)
-- ------------------------------------------------------------
SELECT table_name AS tabla FROM information_schema.tables
WHERE table_schema = 'biblioteca' AND table_type = 'BASE TABLE' ORDER BY table_name;
