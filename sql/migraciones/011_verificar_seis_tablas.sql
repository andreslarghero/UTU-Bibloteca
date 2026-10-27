-- ============================================================
-- VERIFICACIÓN DE LA MIGRACIÓN 010 (seis tablas)
-- Solo consulta (SELECT). Se ejecuta DESPUÉS de 010_seis_tablas_categorias.sql.
-- Resultado esperado: todos los controles en 0 y estado = 'OK'.
-- ============================================================
USE biblioteca;

-- 1. Las tablas que tiene que haber.
SELECT table_name AS tabla
FROM information_schema.tables
WHERE table_schema = 'biblioteca' AND table_type = 'BASE TABLE'
ORDER BY table_name;

-- 2. Cantidades.
SELECT 'libros' AS tabla, COUNT(*) AS filas FROM libros
UNION ALL SELECT 'categorias', COUNT(*) FROM categorias
UNION ALL SELECT 'ventas', COUNT(*) FROM ventas
UNION ALL SELECT 'venta_lineas', COUNT(*) FROM venta_lineas
UNION ALL SELECT 'resenias', COUNT(*) FROM resenias
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios;

-- 3. Libros sin categoría válida (debe devolver 0 filas).
SELECT libros.id AS libro_sin_categoria
FROM libros
LEFT JOIN categorias ON categorias.id = libros.categoria_id
WHERE categorias.id IS NULL;

-- 4. Categorías que ningún libro usa (informativo; en una instalación normal no hay).
SELECT categorias.id, categorias.nombre AS categoria_sin_libros
FROM categorias
LEFT JOIN libros ON libros.categoria_id = categorias.id
WHERE libros.id IS NULL;

-- 5. La columna de texto vieja ya no existe (debe devolver 0).
SELECT COUNT(*) AS columna_categoria_vieja
FROM information_schema.columns
WHERE table_schema = 'biblioteca' AND table_name = 'libros' AND column_name = 'categoria';

-- 6. La clave foránea y la vista del catálogo existen (deben devolver 1 y 1).
SELECT COUNT(*) AS fk_libros_categoria
FROM information_schema.referential_constraints
WHERE constraint_schema = 'biblioteca' AND constraint_name = 'fk_libros_categoria'
  AND delete_rule = 'RESTRICT';
SELECT COUNT(*) AS vista_catalogo
FROM information_schema.views
WHERE table_schema = 'biblioteca' AND table_name = 'vw_catalogo';

-- 7. El catálogo muestra los libros activos con su categoría.
SELECT COUNT(*) AS libros_activos,
       (SELECT COUNT(*) FROM vw_catalogo) AS en_la_vista
FROM libros WHERE activo = 1;

-- 8. Resumen.
SELECT IF(
    (SELECT COUNT(*) FROM libros LEFT JOIN categorias ON categorias.id = libros.categoria_id WHERE categorias.id IS NULL) = 0
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'biblioteca' AND table_name = 'libros' AND column_name = 'categoria') = 0
    AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'biblioteca' AND table_type = 'BASE TABLE'
         AND table_name IN ('usuarios', 'categorias', 'libros', 'ventas', 'venta_lineas', 'resenias')) = 6
    AND (SELECT COUNT(*) FROM vw_catalogo) = (SELECT COUNT(*) FROM libros WHERE activo = 1),
    'OK', 'REVISAR') AS estado;
