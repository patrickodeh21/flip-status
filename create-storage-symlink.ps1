# Create Storage Symlink for Laravel
# Run this as Administrator

$projectRoot = "c:\Users\shash\Downloads\fre1"
$symlinkPath = Join-Path $projectRoot "public\storage"
$targetPath = Join-Path $projectRoot "storage\app\public"

Write-Host "Laravel Storage Symlink Creator" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
$isAdmin = $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Right-click on PowerShell and select 'Run as Administrator', then run this script again." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "Project Root: $projectRoot" -ForegroundColor White
Write-Host "Symlink Path: $symlinkPath" -ForegroundColor White
Write-Host "Target Path:  $targetPath" -ForegroundColor White
Write-Host ""

# Check if target directory exists
if (-not (Test-Path $targetPath)) {
    Write-Host "Creating target directory: $targetPath" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $targetPath -Force | Out-Null
}

# Check if symlink already exists
if (Test-Path $symlinkPath) {
    $item = Get-Item $symlinkPath
    if ($item.LinkType -eq "SymbolicLink") {
        Write-Host "Symlink already exists!" -ForegroundColor Green
        Write-Host "Target: $($item.Target)" -ForegroundColor Gray
        
        $response = Read-Host "`nDo you want to recreate it? (y/n)"
        if ($response -ne 'y') {
            Write-Host "Exiting..." -ForegroundColor Yellow
            exit 0
        }
        
        Write-Host "Removing existing symlink..." -ForegroundColor Yellow
        Remove-Item $symlinkPath -Force
    } else {
        Write-Host "WARNING: $symlinkPath exists but is not a symlink!" -ForegroundColor Red
        Write-Host "It's a regular $($item.GetType().Name)" -ForegroundColor Red
        
        $response = Read-Host "`nDo you want to delete it and create a symlink? (y/n)"
        if ($response -ne 'y') {
            Write-Host "Exiting..." -ForegroundColor Yellow
            exit 0
        }
        
        Write-Host "Removing existing directory/file..." -ForegroundColor Yellow
        Remove-Item $symlinkPath -Recurse -Force
    }
}

# Create the symlink
try {
    Write-Host ""
    Write-Host "Creating symbolic link..." -ForegroundColor Cyan
    New-Item -ItemType SymbolicLink -Path $symlinkPath -Target $targetPath | Out-Null
    Write-Host "SUCCESS! Storage symlink created." -ForegroundColor Green
    Write-Host ""
    Write-Host "You can now upload images and they will be accessible at:" -ForegroundColor White
    Write-Host "http://your-domain/storage/task-media/filename.jpg" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "ERROR: Failed to create symlink!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host ""
    Write-Host "Make sure you:" -ForegroundColor Yellow
    Write-Host "1. Ran PowerShell as Administrator" -ForegroundColor Yellow
    Write-Host "2. Have permissions to create symlinks" -ForegroundColor Yellow
    Write-Host "3. The target path exists and is accessible" -ForegroundColor Yellow
    Write-Host ""
}

# Verify the symlink
if (Test-Path $symlinkPath) {
    $item = Get-Item $symlinkPath
    if ($item.LinkType -eq "SymbolicLink") {
        Write-Host "Verification: Symlink is working!" -ForegroundColor Green
        Write-Host "  Link: $symlinkPath" -ForegroundColor Gray
        Write-Host "  Points to: $($item.Target)" -ForegroundColor Gray
    }
}

Write-Host ""
Read-Host "Press Enter to exit"
