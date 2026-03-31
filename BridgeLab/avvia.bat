@echo off
title BridgeLab
cd /d "%~dp0"

taskkill /F /IM BridgeLab.exe >nul 2>&1

echo Avvio Reverb (background)...
cd /d "C:\laragon\www\bridge-test"
start /B php artisan reverb:start >nul 2>&1
timeout /t 2 /nobreak >nul

cd /d "%~dp0"
echo Avvio BridgeLab...
dotnet run
pause
