@echo off
echo ==========================================
echo Laravel Storage Link Creator (Windows)
echo ==========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo.
    echo Right-click this file and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

cd /d "%~dp0"

echo Current directory: %CD%
echo.

REM Check if public\storage exists and is not a symlink
if exist "public\storage\" (
    echo Found existing public\storage directory
    echo Checking if it's a symbolic link...
    
    REM Try to detect if it's a symlink
    dir "public\storage" | find "<SYMLINKD>" >nul 2>&1
    if %errorLevel% equ 0 (
        echo public\storage is already a symbolic link!
        echo.
        pause
        exit /b 0
    )
    
    echo It's a regular directory, not a symbolic link.
    echo.
    echo Backing up existing directory to public\storage_backup...
    if exist "public\storage_backup\" (
        echo Removing old backup...
        rmdir /s /q "public\storage_backup"
    )
    move "public\storage" "public\storage_backup"
    echo Backup complete.
    echo.
)

echo Creating symbolic link...
echo From: public\storage
echo To:   storage\app\public
echo.

mklink /D "public\storage" "..\storage\app\public"

if %errorLevel% equ 0 (
    echo.
    echo SUCCESS! Symbolic link created.
    echo.
    echo Your uploaded images should now be visible at:
    echo http://localhost:8000/storage/task-media/filename.jpg
    echo.
    echo Testing the link...
    if exist "public\storage\task-media\" (
        echo VERIFIED: task-media is accessible through the symlink!
    ) else (
        echo WARNING: Could not verify task-media accessibility
    )
) else (
    echo.
    echo ERROR: Failed to create symbolic link!
    echo Make sure you ran this as Administrator.
    echo.
)

echo.
pause
