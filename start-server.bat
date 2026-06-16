@echo off
cd /d "%~dp0"
echo ========================================================
echo   STARTING PROJECT WITH FIXES
echo ========================================================
echo.

:: Set PHP path
if exist "bin\php\php.exe" (
    set PHP_BIN=bin\php\php.exe
) else (
    set PHP_BIN=php
)

echo Step 1: Checking storage symlink...
echo.

:: Check if public\storage is a symlink or regular folder
dir public | find "<SYMLINKD>" | find "storage" >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Storage symlink already exists
) else (
    echo [WARNING] Storage is not a symlink!
    echo.
    echo To fix broken images, you need to:
    echo 1. Right-click fix-storage-symlink.bat
    echo 2. Select "Run as administrator"
    echo.
    echo Continuing server startup anyway...
    timeout /t 3 >nul
)

echo.
echo Step 2: Starting Laravel Backend on http://localhost:8000
echo.
start "Laravel Backend" %PHP_BIN% artisan serve

echo.
echo Step 3: Starting Vite Frontend (for CSS/JS)...
echo.
start "Vite Dev Server" npm run dev

echo.
echo ========================================================
echo   SERVERS STARTED!
echo ========================================================
echo.
echo   Your app should be available at:
echo   http://localhost:8000
echo.
echo   If images are broken:
echo   1. Close this window
echo   2. Right-click fix-storage-symlink.bat
echo   3. Run as administrator
echo   4. Then run this script again
echo.
echo   Press any key to open browser...
echo ========================================================
pause

:: Open browser
start http://localhost:8000

echo.
echo Servers are running in the background windows.
echo Keep those windows open!
echo Close this window when done.
pause
