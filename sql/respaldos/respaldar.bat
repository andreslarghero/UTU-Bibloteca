@echo off
rem ============================================================
rem COPIA DE SEGURIDAD (BACKUP) DE LA BASE "biblioteca"
rem Es el comando de clase (Backups en MySQL): mysqldump con las opciones
rem profesionales, ejecutado desde XAMPP.
rem Doble clic para ejecutarlo. Crea dos archivos en la carpeta
rem NEXORA_BACKUPS de tu usuario de Windows (FUERA del proyecto y de htdocs,
rem porque la copia trae correos y contrasenas cifradas de las personas):
rem   biblioteca_FECHA.sql   estructura, vistas y datos de la base
rem   permisos_FECHA.txt     usuarios y permisos de la web
rem                          (mysqldump NO incluye los usuarios: viven en la
rem                          base de sistema "mysql"; se vuelven a crear con
rem                          la seccion "Usuarios y permisos" de biblioteca.sql)
rem Si MySQL tiene contrasena para "root", escribirla en la variable CLAVE.
rem NO subir esta carpeta a Git ni copiarla a htdocs.
rem ============================================================

set MYSQLBIN=C:\xampp\mysql\bin
set USUARIO=root
set CLAVE=
set BASE=biblioteca
set DESTINO=%USERPROFILE%\NEXORA_BACKUPS

if not exist "%DESTINO%" mkdir "%DESTINO%"

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set FECHA=%%i

set PARAM_CLAVE=
if not "%CLAVE%"=="" set PARAM_CLAVE=-p%CLAVE%

rem --default-character-set=utf8mb4: respeta acentos y enies.
rem --single-transaction: copia consistente sin bloquear la base (tablas InnoDB).
rem --routines --triggers --events: incluye los objetos programados (si los hubiera).
rem --add-drop-table: agrega DROP TABLE IF EXISTS antes de recrear cada tabla.
rem Las VISTAS (vw_catalogo y vw_ventas_detalle) se incluyen solas en la copia.
"%MYSQLBIN%\mysqldump.exe" -u %USUARIO% %PARAM_CLAVE% --default-character-set=utf8mb4 --single-transaction --routines --triggers --events --add-drop-table %BASE% > "%DESTINO%\biblioteca_%FECHA%.sql"
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
echo Un backup no sirve si no se puede restaurar. Para probarlo en una base de prueba
echo (no toca "biblioteca"):  restaurar.bat "%DESTINO%\biblioteca_%FECHA%.sql"
pause
