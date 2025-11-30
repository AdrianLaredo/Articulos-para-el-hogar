@echo off
title Instalador PHP para ZEUS (Manual)
color 0B

echo ========================================
echo   INSTALADOR PHP - ZEUS
echo ========================================
echo.

REM Verificar si existe el archivo php.zip
if not exist "php.zip" (
    echo [ERROR] No se encontro el archivo php.zip
    echo.
    echo Por favor:
    echo 1. Descarga PHP desde: https://windows.php.net/download/
    echo 2. Guardalo como php.zip en esta carpeta
    echo 3. Ejecuta este script nuevamente
    echo.
    pause
    exit /b
)

echo [OK] Archivo php.zip encontrado
echo.

REM Crear carpeta para PHP si no existe
if not exist "php" (
    mkdir php
    echo [1/3] Carpeta php creada
) else (
    echo [1/3] Carpeta php ya existe
    echo Limpiando carpeta anterior...
    rmdir /s /q php
    mkdir php
)

echo [2/3] Descomprimiendo archivos...
echo Por favor espera...
echo.

REM Descomprimir usando PowerShell
powershell -Command "$ProgressPreference = 'SilentlyContinue'; Expand-Archive -Path 'php.zip' -DestinationPath 'php' -Force"

if not exist "php\php.exe" (
    echo [ERROR] Error al descomprimir o archivo incorrecto
    echo Verifica que descargaste la version correcta:
    echo VS17 x64 Thread Safe
    pause
    exit /b
)

echo [OK] PHP descomprimido correctamente
echo.
echo [3/3] Configurando PHP...

REM Configurar PHP para desarrollo web
cd php
if not exist "php.ini" (
    if exist "php.ini-development" (
        copy "php.ini-development" "php.ini" >nul
        echo [OK] php.ini creado
    )
)

REM Habilitar extensiones comunes necesarias para tu proyecto
if exist "php.ini" (
    echo Habilitando extensiones necesarias...
    powershell -Command "(Get-Content php.ini) -replace ';extension=mbstring', 'extension=mbstring' -replace ';extension=openssl', 'extension=openssl' -replace ';extension=pdo_mysql', 'extension=pdo_mysql' -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite' -replace ';extension=sqlite3', 'extension=sqlite3' -replace ';extension=curl', 'extension=curl' -replace ';extension=fileinfo', 'extension=fileinfo' -replace ';extension=gd', 'extension=gd' | Set-Content php.ini"
    echo [OK] Extensiones habilitadas
)
cd ..

echo.
echo ========================================
echo   INSTALACION COMPLETADA
echo ========================================
echo.
echo Ubicacion: %CD%\php\
echo Ejecutable: %CD%\php\php.exe
echo.
echo Extensiones habilitadas:
echo  [√] mbstring (manejo de texto)
echo  [√] openssl (seguridad/HTTPS)
echo  [√] pdo_mysql (MySQL)
echo  [√] pdo_sqlite (SQLite)
echo  [√] sqlite3 (SQLite)
echo  [√] curl (peticiones HTTP)
echo  [√] fileinfo (info de archivos)
echo  [√] gd (imagenes)
echo.
echo ========================================
echo     SISTEMA LISTO PARA USAR
echo ========================================
echo.
echo Ahora ejecuta: iniciar_zeus.bat
echo.
pause