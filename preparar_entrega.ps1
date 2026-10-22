# ============================================================
# PREPARAR LA ENTREGA LOCAL DE NEXORA (sin .htaccess ni .gitignore)
#
# Crea DOS carpetas NUEVAS (nunca sobrescribe ni borra nada):
#
#   SITIO    C:\xampp\htdocs\NEXORA_ENTREGA            (se abre en el navegador)
#   PRIVADO  C:\xampp\NEXORA_ENTREGA_PRIVADO           (FUERA de htdocs: no tiene URL)
#              sql\biblioteca.sql                       instalacion de la base (para el profesor)
#              sql\consultas_clase.sql                  consultas de clase
#              digitales\*.pdf                          libros digitales (solo los entrega PHP)
#              LEEME_INSTALACION.txt                    instrucciones
#
# En el SITIO NO se copia: .git, .gitignore, .htaccess, los .md, sql,
# steam, respaldos, este script, archivos temporales ni configuracion
# local. Los .htaccess se reemplazan asi:
#   - ebooks y SQL: viven en la carpeta PRIVADA, fuera de htdocs;
#   - listado de carpetas: cada carpeta lleva un index.html vacio;
#   - archivos internos (config, modelos, controladores, vistas): si se
#     abren por su direccion no muestran nada (ver pruebas en el informe).
#
# Este script NO modifica el proyecto original, ni Git, ni la base de datos,
# ni C:\xampp\htdocs\UTU, ni la demo STEAM.
#
# Uso (en PowerShell, desde la carpeta del proyecto):
#   powershell -ExecutionPolicy Bypass -File .\preparar_entrega.ps1
#   powershell -ExecutionPolicy Bypass -File .\preparar_entrega.ps1 -Sitio C:\xampp\htdocs\OTRO -Privado C:\xampp\OTRO_PRIVADO
# Si alguna de las dos carpetas ya existe, el script se detiene sin tocar nada.
# ============================================================
param(
    [string]$Sitio = "C:\xampp\htdocs\NEXORA_ENTREGA",
    [string]$Privado = "C:\xampp\NEXORA_ENTREGA_PRIVADO"
)

$origen = $PSScriptRoot.TrimEnd('\')
$sitio = $Sitio.TrimEnd('\')
$privado = $Privado.TrimEnd('\')

function Dentro($ruta, $carpeta) {
    return ($ruta -eq $carpeta) -or $ruta.StartsWith($carpeta + '\')
}

# ---------- Controles de seguridad: no sobrescribir nada ----------
$protegidas = @($origen, "C:\xampp\htdocs\UTU", "C:\Proyectos\UTU\UTU STEAM")
foreach ($p in $protegidas) {
    if ((Dentro $sitio $p) -or (Dentro $privado $p)) {
        Write-Host "ERROR: el destino no puede estar dentro de $p."
        exit 1
    }
}
if ((Dentro $privado $sitio) -or (Dentro $sitio $privado)) {
    Write-Host "ERROR: la carpeta privada tiene que estar FUERA de la carpeta del sitio."
    exit 1
}
foreach ($c in @($sitio, $privado)) {
    if (Test-Path $c) {
        Write-Host "ERROR: ya existe $c. Este script no sobrescribe carpetas. Elija otra ruta o borre la entrega anterior a mano."
        exit 1
    }
}

# ---------- SITIO: copia sin lo que no debe quedar ----------
$carpetasFuera = @(".git", "sql", "steam", "digitales", "copias")
$archivosFuera = @(".gitignore", ".gitattributes", ".htaccess", "*.md", "preparar_entrega.ps1",
                   "comando_steam.txt", "steam_config_local.php", "ruta_digitales.php",
                   "*.log", "*.tmp", "*.bak", "*~", "Thumbs.db", "desktop.ini")

robocopy $origen $sitio /E /XD $carpetasFuera /XF $archivosFuera /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) {
    Write-Host "ERROR: no se pudo copiar el proyecto (robocopy $LASTEXITCODE)."
    exit 1
}
# La pagina de redireccion antigua (index.html de la raiz) ya no hace falta: la entrada es index.php.
$viejo = Join-Path $sitio "index.html"
if (Test-Path $viejo) { [System.IO.File]::Delete($viejo) }

# ---------- PRIVADO: SQL, digitales e instrucciones (fuera de htdocs) ----------
New-Item -ItemType Directory -Path (Join-Path $privado "sql") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $privado "digitales") -Force | Out-Null

Copy-Item (Join-Path $origen "sql\biblioteca.sql") (Join-Path $privado "sql") -Force
Copy-Item (Join-Path $origen "sql\consultas_clase.sql") (Join-Path $privado "sql") -Force

$archivos = Get-ChildItem (Join-Path $origen "digitales") -File -ErrorAction SilentlyContinue | Where-Object { $_.Extension -in ".pdf", ".epub" }
foreach ($a in $archivos) {
    Copy-Item -LiteralPath $a.FullName -Destination (Join-Path $privado "digitales") -Force
}

# Le dice al sitio donde estan los libros digitales (ver modelos/ArchivoDigital.php).
$rutaDigitales = (Join-Path $privado "digitales").Replace('\', '/')
Set-Content -Path (Join-Path $sitio "config\ruta_digitales.php") -Encoding ASCII -Value "<?php return '$rutaDigitales';"

$url = "http://localhost/" + (Split-Path $sitio -Leaf) + "/"
$leeme = @"
NEXORA - BIBLIOTECA DIGITAL - ENTREGA LOCAL
============================================

Que hay en esta entrega
  Sitio (se abre en el navegador):  $sitio
  Carpeta privada (esta carpeta, SIN direccion web):
      sql\biblioteca.sql          instalacion de la base de datos (5 tablas)
      sql\consultas_clase.sql     consultas de clase
      digitales\                  libros digitales (PDF) que descargan los compradores
      LEEME_INSTALACION.txt       este archivo

Requisitos
  - XAMPP (Apache + MySQL/MariaDB) con PHP 8.
  - Un navegador actual. No necesita internet.

Instalacion (equipo nuevo o limpio)
  1. Abrir el panel de XAMPP e iniciar Apache y MySQL.
  2. Abrir http://localhost/phpmyadmin, ir a "Importar", elegir
         $privado\sql\biblioteca.sql
     y pulsar "Importar" (con el usuario root, porque crea la base "biblioteca",
     sus 5 tablas, 30 libros de demostracion y las cuentas de la base).
     ATENCION: si ya existe una base llamada "biblioteca", este archivo BORRA sus
     tablas y las vuelve a crear. Hacer una copia antes o usar un equipo limpio.
  3. Abrir en el navegador:
         $url
  4. Cuentas de demostracion (las contrasenas se entregan por separado):
         bibliotecario@example.com   (bibliotecario)
         cliente@example.com         (cliente)

Los libros digitales
  Estan en la carpeta privada (digitales\), fuera de htdocs, por eso nadie puede
  abrirlos escribiendo una direccion: solo los entrega el sitio a quien los compro.
  La ruta esta escrita en $sitio\config\ruta_digitales.php.
  Si se mueve esta carpeta, hay que corregir esa ruta.

Notas
  - No incluye STEAM (Arduino): la Caja funciona igual sin el.
  - Recomendado para la presentacion: en C:\xampp\php\php.ini poner display_errors=Off
    (el sitio tambien lo desactiva por su cuenta) y reiniciar Apache.
  - Funciones del proyecto: registro y login, catalogo con busqueda y filtro por
    categoria, resenas, perfil, compras digitales con descarga, caja del mostrador,
    operaciones, y gestion de libros, usuarios y stock para el bibliotecario.
"@
Set-Content -Path (Join-Path $privado "LEEME_INSTALACION.txt") -Encoding UTF8 -Value $leeme

# ---------- SITIO: que Apache no muestre la lista de archivos de cada carpeta ----------
Get-ChildItem $sitio -Directory -Recurse | ForEach-Object {
    $indice = Join-Path $_.FullName "index.html"
    if (-not (Test-Path $indice)) {
        Set-Content -Path $indice -Encoding ASCII -Value "<!-- -->"
    }
}

# ---------- Comprobacion final ----------
$malos = Get-ChildItem $sitio -Recurse -Force -File | Where-Object {
    $_.Name -in ".htaccess", ".gitignore", ".gitattributes", "Thumbs.db", "desktop.ini" -or
    $_.Extension -in ".md", ".sql", ".pdf", ".epub", ".bat", ".ino", ".py", ".log", ".tmp", ".bak", ".ps1" -or
    $_.FullName -match '\\\.git\\'
}
if ($malos) {
    Write-Host "ATENCION: quedaron archivos que no deberian estar en el sitio:"
    $malos | ForEach-Object { Write-Host "  " $_.FullName }
    exit 1
}

Write-Host "Sitio listo:      $sitio"
Write-Host "  Direccion:      $url"
Write-Host "Carpeta privada:  $privado"
Write-Host "  sql\biblioteca.sql, sql\consultas_clase.sql, digitales\ ($($archivos.Count) archivos), LEEME_INSTALACION.txt"
Write-Host "El sitio no tiene .htaccess, .gitignore, .git, .md, .sql ni PDF."
