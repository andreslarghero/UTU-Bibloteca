@echo off
rem ============================================================
rem COPIA DE SEGURIDAD (BACKUP) DE LA BASE "biblioteca"
rem Usa mysqldump, el programa de XAMPP que exporta una base a un archivo .sql.
rem Doble clic para ejecutarlo. Crea dos archivos en la carpeta "copias":
rem   biblioteca_FECHA.sql   estructura, vistas y datos de la base
rem   permisos_FECHA.txt     usuarios y permisos de la web, para auditoria
rem                          (mysqldump NO incluye los usuarios: viven en la
rem                          base de sistema "mysql"; se vuelven a crear con
rem                          la seccion "Usuarios y permisos" de biblioteca.sql)
rem Si MySQL tiene contrasena para "root", escribirla en la variable CLAVE.
rem ============================================================

set MYSQLBIN=C:\xampp\mysql\bin
set USUARIO=root
set CLAVE=
set BASE=biblioteca
set DESTINO=%~dp0copias

if not exist "%DESTINO%" mkdir "%DESTINO%"

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set FECHA=%%i

set PARAM_CLAVE=
if not "%CLAVE%"=="" set PARAM_CLAVE=-p%CLAVE%

rem --single-transaction: copia consistente sin bloquear la base (tablas InnoDB).
rem --routines --triggers --events: incluye los objetos programados (si los hubiera).
rem --default-character-set=utf8mb4: respeta acentos y enies.
rem --databases: agrega CREATE DATABASE y USE, asi se restaura en un servidor vacio.
"%MYSQLBIN%\mysqldump.exe" -u %USUARIO% %PARAM_CLAVE% --single-transaction --routines --triggers --events --default-character-set=utf8mb4 --databases %BASE% > "%DESTINO%\biblioteca_%FECHA%.sql"
if errorlevel 1 (
    echo ERROR: no se pudo crear la copia de la base.
    pause
    exit /b 1
)

rem Usuarios y permisos de la web (SHOW GRANTS).
"%MYSQLBIN%\mysql.exe" -u %USUARIO% %PARAM_CLAVE% -N -e "SHOW GRANTS FOR 'nexora_app'@'localhost'; SHOW GRANTS FOR 'nexora_consulta'@'localhost'; SHOW GRANTS FOR 'lector_vistas'" > "%DESTINO%\permisos_%FECHA%.txt"

echo.
echo Copia creada en: %DESTINO%\biblioteca_%FECHA%.sql
echo Permisos en:     %DESTINO%\permisos_%FECHA%.txt
echo.
echo Para comprobar que se puede restaurar: restaurar.bat "%DESTINO%\biblioteca_%FECHA%.sql"
pause
