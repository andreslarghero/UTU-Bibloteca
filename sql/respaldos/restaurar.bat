@echo off
rem ============================================================
rem RESTAURAR UNA COPIA DE SEGURIDAD DE LA BASE "biblioteca"
rem Es el procedimiento de clase (Backups en MySQL): 1) crear la base destino
rem vacia, 2) restaurar el archivo con "mysql ... < archivo.sql", 3) verificar.
rem
rem Uso:  restaurar.bat "ruta\a\biblioteca_FECHA.sql" [base_destino]
rem (sin base_destino se usa "biblioteca_restore", una base de PRUEBA: asi se
rem comprueba que la copia sirve sin tocar la base "biblioteca").
rem
rem Para recuperar la base de verdad, indicar "biblioteca" como destino:
rem     restaurar.bat "ruta\biblioteca_FECHA.sql" biblioteca
rem OJO: la copia trae DROP TABLE de cada tabla; lo que hay ahora en esa base se
rem reemplaza por lo que tenia la copia. Hacer antes una copia nueva con
rem respaldar.bat si los datos actuales importan.
rem Si MySQL tiene contrasena para "root", escribirla en la variable CLAVE.
rem ============================================================

set MYSQLBIN=C:\xampp\mysql\bin
set USUARIO=root
set CLAVE=
set DESTINO=%~2
if "%DESTINO%"=="" set DESTINO=biblioteca_restore

if "%~1"=="" (
    echo Falta el archivo de la copia. Uso: restaurar.bat "ruta\biblioteca_FECHA.sql" [base_destino]
    pause
    exit /b 1
)
if not exist "%~1" (
    echo No se encontro el archivo: %~1
    pause
    exit /b 1
)

set PARAM_CLAVE=
if not "%CLAVE%"=="" set PARAM_CLAVE=-p%CLAVE%

rem 1. Crea la base destino (vacia, si no existia) con utf8mb4.
"%MYSQLBIN%\mysql.exe" -u %USUARIO% %PARAM_CLAVE% -e "CREATE DATABASE IF NOT EXISTS %DESTINO% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo ERROR: no se pudo crear la base %DESTINO%.
    pause
    exit /b 1
)

rem 2. Restaura la copia con redireccion de entrada (<) y utf8mb4.
"%MYSQLBIN%\mysql.exe" -u %USUARIO% %PARAM_CLAVE% --default-character-set=utf8mb4 %DESTINO% < "%~1"
if errorlevel 1 (
    echo ERROR: no se pudo restaurar la copia.
    pause
    exit /b 1
)

echo Copia restaurada en la base %DESTINO%.
echo Para comprobar que quedo bien (3. verificar), desde la carpeta sql\respaldos:
echo     "%MYSQLBIN%\mysql.exe" -u %USUARIO% %DESTINO% ^< verificar_checklist.sql
pause
