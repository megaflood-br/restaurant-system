@echo off
setlocal EnableExtensions
title Agente de Impressao PHP - Bella Bistro
cd /d "%~dp0"

set "CFG=%~dp0config.ini"

where php >nul 2>nul
if errorlevel 1 (
    echo PHP nao encontrado. Prefira iniciar.bat ^(PowerShell, sem instalar nada^).
    pause
    exit /b 1
)

if not exist "%CFG%" (
    echo Falta config.ini em:
    echo   %~dp0
    pause
    exit /b 1
)

php "%~dp0print-agent.php"
pause
