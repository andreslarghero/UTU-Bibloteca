-- ============================================================
-- CHECKLIST ANTES Y DESPUÉS DE UN BACKUP
-- Se ejecuta con una cuenta de administración (en XAMPP, "root").
-- Sirve para revisar que la base está lista para copiarse y que, después de
-- restaurar una copia, quedó igual. Cada punto corresponde a una línea del
-- checklist visto en clase.
-- ============================================================

USE biblioteca;

-- 1. Motor de almacenamiento: tiene que ser InnoDB (transacciones, claves
--    foráneas y copia en caliente con --single-transaction).
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'biblioteca' AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

-- 2 y 3. Juego de caracteres y collation: utf8mb4 y utf8mb4_unicode_ci.
SELECT TABLE_NAME, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'biblioteca' AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

SELECT DEFAULT_CHARACTER_SET_NAME AS charset_base, DEFAULT_COLLATION_NAME AS collation_base
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'biblioteca';

-- 4. Claves primarias, únicas y foráneas (con su acción referencial).
SELECT TABLE_NAME, CONSTRAINT_TYPE, CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'biblioteca' AND CONSTRAINT_TYPE IN ('PRIMARY KEY', 'UNIQUE', 'FOREIGN KEY')
ORDER BY TABLE_NAME, CONSTRAINT_TYPE, CONSTRAINT_NAME;

SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'biblioteca'
ORDER BY TABLE_NAME;

-- 5. Objetos programados: vistas, triggers, procedimientos y eventos.
SELECT TABLE_NAME AS vista FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'biblioteca';
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'biblioteca';
SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'biblioteca';
SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'biblioteca';

-- 6. Seguridad y accesos: usuarios, permisos y roles (se respaldan aparte).
SELECT user, host FROM mysql.user WHERE user LIKE 'nexora%';
SHOW GRANTS FOR 'nexora_app'@'localhost';
SHOW GRANTS FOR 'nexora_consulta'@'localhost';
SHOW GRANTS FOR 'lector_vistas';

-- 7. Consistencia de datos: no tiene que haber transacciones abiertas.
SELECT COUNT(*) AS transacciones_abiertas FROM information_schema.INNODB_TRX;

-- 8. Tablas temporales: no se respaldan. Si alguna tiene datos importantes,
--    hay que convertirla en una tabla permanente antes de la copia.

-- ------------------------------------------------------------
-- DESPUÉS DE RESTAURAR: comparar con lo que se esperaba.
-- UNION ALL junta las cantidades de filas de todas las tablas en una sola
-- lista. Se anotan antes del backup y se comparan después de restaurar.
-- ------------------------------------------------------------
SELECT 'usuarios' AS tabla, COUNT(*) AS filas FROM usuarios
UNION ALL SELECT 'libros', COUNT(*) FROM libros
UNION ALL SELECT 'ventas', COUNT(*) FROM ventas
UNION ALL SELECT 'venta_lineas', COUNT(*) FROM venta_lineas
UNION ALL SELECT 'resenias', COUNT(*) FROM resenias;

-- Integridad referencial: ninguna de estas consultas debe devolver filas
-- (filas "huérfanas" que apuntan a algo que no existe).
SELECT venta_lineas.id AS linea_huerfana
FROM venta_lineas
LEFT JOIN ventas ON ventas.id = venta_lineas.venta_id
WHERE ventas.id IS NULL;

SELECT libros.id AS libro_sin_categoria
FROM libros
WHERE libros.categoria IS NULL OR TRIM(libros.categoria) = '';
