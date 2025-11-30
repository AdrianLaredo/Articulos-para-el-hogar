@echo off
echo.
echo ========================================
echo   SISTEMA ZEUS - INICIANDO...
echo ========================================
echo.

timeout /t 2 /nobreak >nul

cd /d "%~dp0"

if exist "php\php.exe" (
    echo PHP encontrado! Iniciando servidor...
    echo.
    
    :: Verificar si ngrok existe
    if exist "ngrok.exe" (
        echo NGROK encontrado! Iniciando tunel...
        echo.
        
        :: Iniciar PHP en segundo plano
        echo Iniciando servidor PHP en segundo plano...
        start /B "" "php\php.exe" -S localhost:8000
        
        :: Esperar un momento para que PHP se inicie
        timeout /t 3 /nobreak >nul
        
        :: Iniciar ngrok en segundo plano
        echo Iniciando tunel NGROK en segundo plano...
        start /B "" "ngrok.exe" http 8000
        
        :: Abrir navegador local
        echo Abriendo navegador local...
        timeout /t 2 /nobreak >nul
        start "" "http://localhost:8000" >nul 2>&1
        
        echo.
        echo ========================================
        echo   SISTEMA INICIADO CORRECTAMENTE
        echo ========================================
        echo - Servidor local: http://localhost:8000
        echo - Tunnel NGROK: http://localhost:4040
        echo.
        echo Servicios ejecutandose en segundo plano
        echo Para detener: Ejecuta Detener_Zeus.bat
        echo.
        
        :: Crear archivo para detener servicios
        echo @echo off > Detener_Zeus.bat
        echo taskkill /f /im php.exe /im ngrok.exe >> Detener_Zeus.bat
        echo echo Servicios detenidos. >> Detener_Zeus.bat
        echo pause >> Detener_Zeus.bat
        
        :: Cerrar esta ventana automaticamente
        timeout /t 5 /nobreak >nul
        exit
        
    ) else (
        echo NGROK no encontrado, iniciando solo servidor local...
        echo.
        echo Presiona Ctrl+C para detener
        echo.
        timeout /t 2 /nobreak >nul
        start "" "http://localhost:8000"
        php\php.exe -S localhost:8000
    )
    
) else (
    echo ERROR: PHP no encontrado
    echo Ejecuta: instalar_php_manual.bat
    pause
)