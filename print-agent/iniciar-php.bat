@echo off
chcp 65001 >nul
title Agente de Impressao (PHP) - Bella Bistro
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo PHP nao encontrado. Prefira iniciar.bat (PowerShell, sem instalar nada).
    pause
    exit /b 1
)

if not exist "config.ini" (
    echo Falta config.ini
    pause
    exit /b 1
)

php "%~dp0print-agent.php"
pause
