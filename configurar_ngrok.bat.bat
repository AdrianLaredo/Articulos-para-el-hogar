@echo off
echo ========================================
echo   CONFIGURANDO NGROK - PRIMERA VEZ
echo ========================================
echo.

cd /d "%~dp0"

if exist "ngrok.exe" (
    echo Configurando authtoken de NGROK...
    ngrok.exe config add-authtoken 35Cqokd76N5CNOBtXJY8ydt9N9S_aajdtM86BVW7ArsGwPNf
    echo.
    echo Configuracion completada!
    echo Ahora puedes usar el archivo principal para iniciar el sistema.
) else (
    echo ERROR: ngrok.exe no encontrado en la carpeta raiz
)

echo.
pause