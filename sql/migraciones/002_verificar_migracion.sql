-- ============================================================
-- VERIFICACIÓN de la migración 001 (solo SELECT: no modifica nada)
--
-- Se ejecuta DESPUÉS de 001_cinco_tablas_categoria_en_libros.sql.
-- Compara el resultado con los valores que se anotaron ANTES de migrar
-- (los de ANTES se pasan con las variables de abajo, o se leen del respaldo).
-- Todo tiene que dar diferencia 0 y terminar con estado = OK.
--
-- Cómo anotar los valores de antes (sobre la base anterior):
--   SELECT COUNT(*) FROM libros;  SELECT COUNT(*) FROM ventas;
--   SELECT COUNT(*) FROM venta_lineas;  SELECT SUM(total) FROM ventas;
--   SELECT SUM(cantidad * precio) FROM venta_lineas;
--   SELECT COUNT(*) FROM resenias;  SELECT COUNT(*) FROM usuarios;
-- ============================================================

USE biblioteca;

-- Valores de ANTES (se cambian por los reales de la base que se migra).
SET @antes_libros = 30;
SET @antes_ventas = 7;
SET @antes_lineas = 8;
SET @antes_importe = 4430.00;     -- suma de ventas.total (= suma de cantidad x precio)
SET @antes_resenias = 2;
SET @antes_usuarios = 4;

-- 1. Cantidades y importes
SELECT 'libros' AS control, @antes_libros AS antes, (SELECT COUNT(*) FROM libros) AS despues,
       (SELECT COUNT(*) FROM libros) - @antes_libros AS diferencia
UNION ALL SELECT 'ventas', @antes_ventas, (SELECT COUNT(*) FROM ventas), (SELECT COUNT(*) FROM ventas) - @antes_ventas
UNION ALL SELECT 'venta_lineas', @antes_lineas, (SELECT COUNT(*) FROM venta_lineas), (SELECT COUNT(*) FROM venta_lineas) - @antes_lineas
UNION ALL SELECT 'importe total de las ventas', @antes_importe,
       (SELECT COALESCE(SUM(total), 0) FROM vw_ventas_detalle),
       (SELECT COALESCE(SUM(total), 0) FROM vw_ventas_detalle) - @antes_importe
UNION ALL SELECT 'resenias', @antes_resenias, (SELECT COUNT(*) FROM resenias), (SELECT COUNT(*) FROM resenias) - @antes_resenias
UNION ALL SELECT 'usuarios', @antes_usuarios, (SELECT COUNT(*) FROM usuarios), (SELECT COUNT(*) FROM usuarios) - @antes_usuarios;

-- 2. Tablas que quedan (tienen que ser exactamente 5)
SELECT table_name AS tabla FROM information_schema.tables
WHERE table_schema = 'biblioteca' AND table_type = 'BASE TABLE' ORDER BY table_name;

-- 3. Libros sin categoría (tiene que dar 0 filas)
SELECT id AS libro_sin_categoria FROM libros WHERE categoria IS NULL OR TRIM(categoria) = '';

-- 4. Categorías que quedaron (se tienen que conservar los 9 nombres de antes)
SELECT categoria, COUNT(*) AS libros FROM libros GROUP BY categoria ORDER BY categoria;

-- 5. Líneas huérfanas y ventas sin líneas (las dos tienen que dar 0 filas)
SELECT venta_lineas.id AS linea_huerfana FROM venta_lineas
LEFT JOIN ventas ON ventas.id = venta_lineas.venta_id WHERE ventas.id IS NULL;
SELECT ventas.id AS venta_sin_lineas FROM ventas
LEFT JOIN venta_lineas ON venta_lineas.venta_id = ventas.id WHERE venta_lineas.id IS NULL;

-- 6. Referencias rotas a usuarios y libros (tienen que dar 0 filas)
SELECT ventas.id AS venta_con_cliente_inexistente FROM ventas
LEFT JOIN usuarios ON usuarios.id = ventas.id_cliente
WHERE ventas.id_cliente IS NOT NULL AND usuarios.id IS NULL;
SELECT venta_lineas.id AS linea_con_libro_inexistente FROM venta_lineas
LEFT JOIN libros ON libros.id = venta_lineas.libro_id WHERE libros.id IS NULL;

-- 7. Resultado
SELECT IF(
    (SELECT COUNT(*) FROM libros) = @antes_libros
    AND (SELECT COUNT(*) FROM ventas) = @antes_ventas
    AND (SELECT COUNT(*) FROM venta_lineas) = @antes_lineas
    AND (SELECT COALESCE(SUM(total), 0) FROM vw_ventas_detalle) = @antes_importe
    AND (SELECT COUNT(*) FROM resenias) = @antes_resenias
    AND (SELECT COUNT(*) FROM usuarios) = @antes_usuarios
    AND (SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = 'biblioteca' AND table_type = 'BASE TABLE') = 5
    AND (SELECT COUNT(*) FROM libros WHERE categoria IS NULL OR TRIM(categoria) = '') = 0
    AND (SELECT COUNT(*) FROM venta_lineas LEFT JOIN ventas ON ventas.id = venta_lineas.venta_id
         WHERE ventas.id IS NULL) = 0,
    'OK', 'REVISAR') AS estado;
