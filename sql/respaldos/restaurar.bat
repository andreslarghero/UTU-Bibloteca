@echo off
rem ============================================================
rem RESTAURAR UNA COPIA DE SEGURIDAD DE LA BASE "biblioteca"
rem Uso:  restaurar.bat "ruta\a\biblioteca_FECHA.sql"
rem (o arrastrar el archivo .sql encima de este programa)
rem
rem OJO: la copia trae DROP TABLE de cada tabla: lo que hay ahora en la base se
rem reemplaza por lo que tenia la copia. Hacer antes una copia nueva con
rem respaldar.bat si los datos actuales importan.
rem Si MySQL tiene contrasena para "root", escribirla en la variable CLAVE.
rem ============================================================

set MYSQLBIN=C:\xampp\mysql\bin
set USUARIO=root
set CLAVE=

if "%~1"=="" (
    echo Falta el archivo de la copia. Uso: restaurar.bat "ruta\biblioteca_FECHA.sql"
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

rem Se restaura con utf8mb4. La copia ya trae CREATE DATABASE y USE.
"%MYSQLBIN%\mysql.exe" -u %USUARIO% %PARAM_CLAVE% --default-character-set=utf8mb4 < "%~1"
if errorlevel 1 (
    echo ERROR: no se pudo restaurar la copia.
    pause
    exit /b 1
)

echo Copia restaurada. Para comprobar que quedo bien, ejecutar verificar_checklist.sql
pause
