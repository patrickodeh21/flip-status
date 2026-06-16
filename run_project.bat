@echo off
cd /d "%~dp0"
echo ========================================================
echo   Starting Project Servers
echo   Keep these windows open to keep the site online
echo ========================================================

:: Check if PHP exists in bin
if exist "bin\php\php.exe" (
    set PHP_BIN=bin\php\php.exe
) else (
    set PHP_BIN=php
)

echo Starting Laravel Backend...
start "Laravel Backend" %PHP_BIN% artisan serve

echo Starting Vite Frontend...
start "Vite Dev Server" npm run dev

echo Starting Public Tunnel...
echo Waiting for servers to initialize...
timeout /t 5 >nul
start "Public URL (DO NOT CLOSE)" cmd /k "npx localtunnel --port 8000"

echo.
echo ========================================================
echo   Servers are running!
echo   1. Check the 'Public URL' window for your live link.
echo      (Format: https://something-random.loca.lt)
echo   2. To stop: Close the opened terminal windows.
echo ========================================================
pause
