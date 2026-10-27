@echo off
REM ============================================================
REM Aplica la migracion a SEIS TABLAS sobre la base de trabajo (XAMPP, puerto 3306)
REM y actualiza el codigo de las dos carpetas de htdocs.
REM
REM Que hace, en orden (se detiene si algo falla):
REM   1. Respaldo completo de la base (sql\respaldos\copias\...antes_de_seis_tablas_...sql)
REM   2. Migracion 010 (crea categorias y libros.categoria_id)
REM   3. Verificacion 011 (debe terminar con estado OK)
REM   4. Copia los archivos PHP nuevos a htdocs\NEXORA_ENTREGA y htdocs\UTU\UTU-Bibloteca
REM
REM Se ejecuta con XAMPP (MySQL) encendido, desde la carpeta del proyecto.
REM Para volver atras: restaurar el respaldo con sql\respaldos\restaurar.bat y
REM copiar de nuevo los archivos viejos (hay copia en C:\Proyectos\UTU\RESPALDO_ENTREGA_ANTES_SEIS_TABLAS_*).
REM ============================================================
setlocal
set MYSQL=C:\xampp\mysql\bin
set PROYECTO=%~dp0..\..
cd /d "%PROYECTO%"
if not exist sql\respaldos\copias mkdir sql\respaldos\copias
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set TS=%%i
set RESPALDO=sql\respaldos\copias\biblioteca_REAL_antes_de_seis_tablas_%TS%.sql

echo [1/4] Respaldo de la base...
"%MYSQL%\mysqldump.exe" -uroot --single-transaction --routines --triggers --events --default-character-set=utf8mb4 --databases biblioteca > "%RESPALDO%"
if errorlevel 1 goto error
echo      guardado en %RESPALDO%

echo [2/4] Migracion 010...
"%MYSQL%\mysql.exe" -uroot --default-character-set=utf8mb4 < sql\migraciones\010_seis_tablas_categorias.sql
if errorlevel 1 goto error

echo [3/4] Verificacion 011 (el ultimo resultado debe ser OK)...
"%MYSQL%\mysql.exe" -uroot --default-character-set=utf8mb4 -t < sql\migraciones\011_verificar_seis_tablas.sql
if errorlevel 1 goto error

echo [4/4] Copiando el codigo a htdocs...
set ARCHIVOS=controladores\LibroAdminController.php controladores\LibroController.php modelos\Libro.php modelos\RepositorioLibros.php modelos\Categoria.php modelos\RepositorioCategorias.php vistas\libros\administrar.php vistas\libros\catalogo.php
for %%D in ("C:\xampp\htdocs\NEXORA_ENTREGA" "C:\xampp\htdocs\UTU\UTU-Bibloteca") do (
  for %%F in (%ARCHIVOS%) do copy /Y "%%F" "%%~D\%%F" > nul
)
echo.
echo Listo: la base y el codigo estan en seis tablas.
goto fin

:error
echo.
echo *** ALGO FALLO: no sigas. Si la base quedo a medias, restaurala con el respaldo %RESPALDO% ***
:fin
endlocal
pause
