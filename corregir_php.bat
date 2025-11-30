@echo off
echo ========================================
echo   CORRECCION DE PHP - ZEUS
echo ========================================
echo.

:: Verificar que estamos en el directorio correcto
if not exist "php\php.exe" (
    echo ERROR: No se encuentra php\php.exe
    echo Asegurate de ejecutar este script desde C:\InventarioZeus
    pause
    exit /b 1
)

echo [1/4] Verificando php.ini...
if not exist "php\php.ini" (
    echo Creando php.ini desde php.ini-development...
    copy "php\php.ini-development" "php\php.ini"
)

echo [2/4] Configurando rutas de extensiones...
:: Ruta actual del directorio
set "CURRENT_DIR=%CD%"

:: Crear un php.ini temporal con las configuraciones correctas
(
echo ; Configuracion PHP para Zeus Hogar
echo ; Generado automaticamente
echo.
echo [PHP]
echo extension_dir = "%CURRENT_DIR%\php\ext"
echo.
echo ; Extensiones necesarias
echo extension=curl
echo extension=fileinfo
echo extension=gd
echo extension=mbstring
echo extension=openssl
echo extension=pdo_mysql
echo extension=pdo_sqlite
echo extension=sqlite3
echo.
echo ; Configuraciones basicas
echo memory_limit = 256M
echo upload_max_filesize = 50M
echo post_max_size = 50M
echo max_execution_time = 300
echo date.timezone = America/Mexico_City
echo.
echo ; Errores
echo display_errors = On
echo error_reporting = E_ALL
) > "php\php.ini.new"

echo [3/4] Aplicando nueva configuracion...
move /Y "php\php.ini.new" "php\php.ini"

echo [4/4] Verificando extensiones disponibles...
echo.
dir "php\ext\php_*.dll" /b

echo.
echo ========================================
echo   CONFIGURACION COMPLETADA
echo ========================================
echo.
echo Las siguientes extensiones fueron configuradas:
echo - curl (para ngrok y peticiones HTTP)
echo - fileinfo (para deteccion de tipos de archivo)
echo - gd (para imagenes)
echo - mbstring (para caracteres especiales)
echo - openssl (para HTTPS)
echo - pdo_sqlite (para base de datos)
echo - sqlite3 (para base de datos)
echo.
echo Ahora puedes iniciar Zeus normalmente
echo.
pause