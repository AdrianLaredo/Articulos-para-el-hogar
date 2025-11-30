@echo off
echo ========================================
echo   DIAGNOSTICO PHP - ZEUS
echo ========================================
echo.

echo [1] Verificando PHP...
if exist "php\php.exe" (
    echo [OK] PHP instalado
    php\php.exe -v
) else (
    echo [ERROR] PHP no encontrado
)

echo.
echo [2] Verificando extensiones DLL...
if exist "php\ext\php_pdo_sqlite.dll" (
    echo [OK] php_pdo_sqlite.dll existe
) else (
    echo [ERROR] php_pdo_sqlite.dll NO existe
)

if exist "php\ext\php_sqlite3.dll" (
    echo [OK] php_sqlite3.dll existe
) else (
    echo [ERROR] php_sqlite3.dll NO existe
)

echo.
echo [3] Verificando php.ini...
if exist "php\php.ini" (
    echo [OK] php.ini existe
    echo.
    echo Extension dir configurado:
    type php\php.ini | findstr "extension_dir"
    echo.
    echo Extensiones SQLite:
    type php\php.ini | findstr "sqlite"
) else (
    echo [ERROR] php.ini NO existe
)

echo.
echo [4] Probando carga de extensiones...
php\php.exe -m | findstr -i "pdo_sqlite sqlite"

echo.
echo [5] Verificando Visual C++ Runtime...
php\php.exe -i | findstr "MSVC"

echo.
pause