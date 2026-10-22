-- ============================================================
-- CONSULTAS DE LA BIBLIOTECA DIGITAL, SEGÚN LOS TEMAS DE CLASE
-- Se ejecuta DESPUÉS de importar biblioteca.sql, con una cuenta de
-- administración (en XAMPP, "root"), por ejemplo desde phpMyAdmin
-- (pestaña SQL) o desde la consola de MySQL.
--
-- Cada sección trae un título, qué muestra y la consulta. Todo se puede
-- ejecutar varias veces: lo que cambia datos se hace dentro de una
-- transacción que termina con ROLLBACK (no deja ningún cambio), y lo que
-- crea objetos de práctica los borra al terminar.
--
-- Secciones:
--   1. JOIN (INNER, LEFT, RIGHT, FULL con UNION, CROSS)
--   2. Operaciones de conjuntos (UNION, UNION ALL, INTERSECT, EXCEPT)
--   3. Vistas (consultar, ver la definición, vista actualizable)
--   4. Tablas temporales
--   5. UPDATE, DELETE y acciones referenciales
--   6. DDL: DESCRIBE y ALTER TABLE
--   7. Usuarios y permisos
-- ============================================================

USE biblioteca;

-- ============================================================
-- 1. JOIN
-- ============================================================

-- INNER JOIN: solo las filas que coinciden en las dos tablas.
-- Cada reseña con el título del libro y el nombre de quien la escribió.
SELECT libros.titulo, usuarios.nombre AS usuario, resenias.puntuacion
FROM resenias
INNER JOIN libros ON libros.id = resenias.libro_id
INNER JOIN usuarios ON usuarios.id = resenias.usuario_id
ORDER BY libros.titulo;

-- LEFT JOIN: todas las filas de la tabla izquierda; si no hay coincidencia,
-- la derecha queda en NULL. Libros con su cantidad de reseñas (los que no
-- tienen ninguna salen con 0).
SELECT libros.titulo, COUNT(resenias.id) AS total_resenas
FROM libros
LEFT JOIN resenias ON resenias.libro_id = libros.id
GROUP BY libros.id, libros.titulo
ORDER BY total_resenas DESC, libros.titulo;

-- LEFT JOIN + IS NULL: las filas de la izquierda SIN pareja.
-- Libros que nunca se vendieron.
SELECT libros.id, libros.titulo
FROM libros
LEFT JOIN venta_lineas ON venta_lineas.libro_id = libros.id
WHERE venta_lineas.id IS NULL
ORDER BY libros.titulo;

-- RIGHT JOIN: todas las filas de la tabla derecha. Cada libro con las veces que
-- se vendió (los que nunca se vendieron salen con 0).
SELECT libros.titulo, COUNT(venta_lineas.id) AS veces_vendido
FROM venta_lineas
RIGHT JOIN libros ON libros.id = venta_lineas.libro_id
GROUP BY libros.id, libros.titulo
ORDER BY veces_vendido DESC, libros.titulo;

-- FULL JOIN: todas las filas de las dos tablas. MySQL y MariaDB no lo tienen:
-- se arma con un LEFT JOIN y un RIGHT JOIN unidos con UNION.
SELECT usuarios.nombre AS usuario, ventas.id AS venta
FROM usuarios
LEFT JOIN ventas ON ventas.id_cliente = usuarios.id
UNION
SELECT usuarios.nombre, ventas.id
FROM usuarios
RIGHT JOIN ventas ON ventas.id_cliente = usuarios.id;

-- Unir la MISMA tabla dos veces, con alias: ventas con el cliente y el vendedor
-- (es lo que hace la vista vw_ventas_detalle).
SELECT ventas.id, clientes.nombre AS cliente, vendedores.nombre AS vendedor, ventas.fecha
FROM ventas
LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor;

-- JOIN de tres tablas: qué libros tiene cada venta, con su categoría.
SELECT ventas.id AS venta, libros.titulo, libros.categoria,
       venta_lineas.cantidad, venta_lineas.precio
FROM venta_lineas
JOIN ventas ON ventas.id = venta_lineas.venta_id
JOIN libros ON libros.id = venta_lineas.libro_id
ORDER BY ventas.id, libros.titulo;

-- CROSS JOIN: todas las combinaciones posibles (producto cartesiano).
-- Cada formato con cada categoría (la categoría es un dato de libros): 2 x 9 = 18 filas.
SELECT formatos.formato, categorias.categoria
FROM (SELECT 'fisico' AS formato UNION SELECT 'digital') AS formatos
CROSS JOIN (SELECT DISTINCT categoria FROM libros) AS categorias
ORDER BY formatos.formato, categorias.categoria;

-- ============================================================
-- 2. OPERACIONES DE CONJUNTOS
-- Reglas: las consultas tienen que devolver la misma cantidad de columnas,
-- con tipos compatibles; los nombres de columna salen de la primera consulta;
-- ORDER BY va solo al final.
-- INTERSECT y EXCEPT: MariaDB 10.3 o superior (MySQL 8.0.31 o superior).
-- ============================================================

-- UNION: junta los resultados y elimina los repetidos.
-- Usuarios que participaron en alguna venta, como cliente o como vendedor.
SELECT usuarios.id, usuarios.nombre, 'cliente' AS participo_como
FROM usuarios
JOIN ventas ON ventas.id_cliente = usuarios.id
UNION
SELECT usuarios.id, usuarios.nombre, 'vendedor'
FROM usuarios
JOIN ventas ON ventas.id_vendedor = usuarios.id
ORDER BY id;

-- UNION ALL: igual, pero conserva los repetidos (es más rápido).
-- Una fila por cada venta, indicando si fue de mostrador o por la web.
SELECT id AS venta, fecha, total, 'mostrador' AS origen FROM vw_ventas_detalle WHERE id_vendedor IS NOT NULL
UNION ALL
SELECT id, fecha, total, 'web' FROM vw_ventas_detalle WHERE id_vendedor IS NULL
ORDER BY fecha;

-- INTERSECT: solo lo que está en los dos resultados.
-- Libros que tienen reseñas Y que se vendieron.
SELECT libro_id FROM resenias
INTERSECT
SELECT libro_id FROM venta_lineas;

-- EXCEPT: lo del primer resultado que NO está en el segundo.
-- Libros activos que nunca se vendieron.
SELECT id, titulo FROM libros WHERE activo = 1
EXCEPT
SELECT libros.id, libros.titulo
FROM libros
JOIN venta_lineas ON venta_lineas.libro_id = libros.id
ORDER BY titulo;

-- ============================================================
-- 3. VISTAS
-- ============================================================

-- Una vista se consulta como una tabla.
SELECT id, titulo, categoria, media, total_resenas FROM vw_catalogo ORDER BY id LIMIT 5;
SELECT id, fecha, total, cliente, vendedor FROM vw_ventas_detalle ORDER BY id;

-- Con un filtro, como la usa la página "Mis compras" del cliente 2.
SELECT id, fecha, total, vendedor FROM vw_ventas_detalle WHERE id_cliente = 2 ORDER BY fecha DESC;

-- Ver la definición guardada de una vista.
SHOW CREATE VIEW vw_catalogo;

-- Vista SIMPLE (una sola tabla, sin agrupar): es actualizable.
CREATE VIEW vw_libros_fisicos AS
SELECT id, titulo, autor, stock
FROM libros
WHERE formato_fisico = 1;

START TRANSACTION;
UPDATE vw_libros_fisicos SET stock = stock + 10 WHERE id = 1;   -- funciona: modifica la tabla libros
SELECT id, titulo, stock FROM libros WHERE id = 1;
ROLLBACK;                                                        -- deshace el cambio

-- Vista COMPLEJA (JOIN y GROUP BY): NO es actualizable. Esta sentencia da error:
--   UPDATE vw_catalogo SET total_resenas = 5 WHERE id = 1;
-- Error: la vista usa GROUP BY y funciones de agregación.

DROP VIEW IF EXISTS vw_libros_fisicos;

-- ============================================================
-- 4. TABLAS TEMPORALES
-- Una tabla temporal existe solo durante la sesión (la conexión). Se borra
-- sola al cerrar la conexión y NO entra en las copias de seguridad.
-- OJO: phpMyAdmin puede abrir una conexión nueva por cada ejecución y borrarla
-- enseguida; para probarla conviene la consola o MySQL Workbench.
-- ============================================================
DROP TEMPORARY TABLE IF EXISTS tmp_ventas_por_libro;

CREATE TEMPORARY TABLE tmp_ventas_por_libro (
    libro_id INT,
    copias INT,
    importe DECIMAL(10,2)
);

INSERT INTO tmp_ventas_por_libro
SELECT libro_id, SUM(cantidad), SUM(cantidad * precio)
FROM venta_lineas
GROUP BY libro_id;

SELECT libros.titulo, tmp_ventas_por_libro.copias, tmp_ventas_por_libro.importe
FROM tmp_ventas_por_libro
JOIN libros ON libros.id = tmp_ventas_por_libro.libro_id
ORDER BY tmp_ventas_por_libro.importe DESC;

DROP TEMPORARY TABLE tmp_ventas_por_libro;

-- ============================================================
-- 5. UPDATE, DELETE Y ACCIONES REFERENCIALES
-- Todo dentro de una transacción con ROLLBACK, para no modificar los datos.
-- ============================================================

START TRANSACTION;

-- UPDATE con WHERE: cambia solo las filas que cumplen la condición.
UPDATE libros SET precio = 700.00, stock = 3 WHERE codigo_barras = '9780307474728';
SELECT titulo, precio, stock FROM libros WHERE codigo_barras = '9780307474728';

-- UPDATE masivo (sin WHERE sería sobre todas las filas): aumenta 10 % los libros físicos.
UPDATE libros SET precio = ROUND(precio * 1.10, 2) WHERE formato_fisico = 1;
SELECT COUNT(*) AS libros_actualizados FROM libros WHERE formato_fisico = 1;

-- Baja lógica: es un UPDATE, no un DELETE.
UPDATE libros SET activo = 0 WHERE id = 1;
SELECT COUNT(*) AS en_el_catalogo FROM vw_catalogo;

ROLLBACK;

-- ON DELETE CASCADE: al borrar una venta se borran sus líneas (cabecera y detalle).
START TRANSACTION;
SELECT COUNT(*) AS lineas_antes FROM venta_lineas WHERE venta_id = 1;
DELETE FROM ventas WHERE id = 1;
SELECT COUNT(*) AS lineas_despues FROM venta_lineas WHERE venta_id = 1;   -- 0: se borraron en cascada
ROLLBACK;

-- ON DELETE RESTRICT: no se puede borrar algo que tiene datos que dependen de él.
-- Estas sentencias dan el error 1451 (Cannot delete or update a parent row):
--   DELETE FROM libros WHERE id = 2;          -- el libro ya se vendió
--   DELETE FROM usuarios WHERE id = 2;        -- el usuario tiene ventas

-- DELETE vs TRUNCATE vs DROP (no se ejecutan acá):
--   DELETE FROM resenias WHERE id = 1;   borra filas con una condición (DML, se puede deshacer)
--   TRUNCATE TABLE resenias;             vacía la tabla y reinicia AUTO_INCREMENT (DDL, no se deshace)
--   DROP TABLE resenias;                 borra la tabla completa (DDL, no se deshace)

-- ============================================================
-- 6. DDL: DESCRIBE Y ALTER TABLE
-- ============================================================

-- La estructura de una tabla: tipos, si admite NULL, claves (PRI, UNI, MUL).
DESCRIBE libros;
DESC ventas;
SHOW CREATE TABLE venta_lineas;

-- ALTER TABLE se practica sobre una COPIA, para no tocar las tablas de la web.
DROP TABLE IF EXISTS libros_practica;
CREATE TABLE libros_practica (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL UNIQUE);

ALTER TABLE libros_practica ADD COLUMN descripcion VARCHAR(100) NOT NULL DEFAULT '';   -- agregar una columna
ALTER TABLE libros_practica MODIFY COLUMN descripcion VARCHAR(200);                    -- cambiar tipo o restricciones
ALTER TABLE libros_practica CHANGE COLUMN descripcion detalle VARCHAR(250);            -- cambiar nombre y tipo
ALTER TABLE libros_practica ADD COLUMN padre_id INT NULL;
ALTER TABLE libros_practica ADD CONSTRAINT fk_practica_padre                           -- agregar una clave foránea
    FOREIGN KEY (padre_id) REFERENCES libros_practica(id) ON DELETE SET NULL;           -- SET NULL: la columna admite NULL
ALTER TABLE libros_practica DROP FOREIGN KEY fk_practica_padre;                        -- quitar la clave foránea
ALTER TABLE libros_practica DROP COLUMN padre_id;                                      -- quitar una columna
ALTER TABLE libros_practica RENAME TO copias_practica;                                 -- renombrar la tabla
DESCRIBE copias_practica;

DROP TABLE IF EXISTS copias_practica;
DROP TABLE IF EXISTS libros_practica;

-- ============================================================
-- 7. USUARIOS Y PERMISOS
-- ============================================================
SELECT user, host FROM mysql.user WHERE user LIKE 'nexora%';
SHOW GRANTS FOR 'nexora_app'@'localhost';
SHOW GRANTS FOR 'nexora_consulta'@'localhost';
SHOW GRANTS FOR 'lector_vistas';
