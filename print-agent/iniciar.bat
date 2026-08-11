@echo off
chcp 65001 >nul
title Agente de Impressao - Bella Bistro
cd /d "%~dp0"

if not exist "config.ini" (
    if exist "config.example.ini" (
        copy /y "config.example.ini" "config.ini" >nul
        echo Criei config.ini — abra o arquivo, cole o TOKEN e salve.
        echo Depois rode este iniciar.bat de novo.
        echo.
        notepad "config.ini"
        pause
        exit /b 0
    )
    echo Falta config.ini
    pause
    exit /b 1
)

echo Iniciando agente (PowerShell — sem PHP)...
echo Deixe esta janela aberta. Para parar: Ctrl+C
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0print-agent.ps1"
set "ERR=%ERRORLEVEL%"

echo.
if not "%ERR%"=="0" (
    echo Agente encerrou com erro %ERR%.
) else (
    echo Agente encerrado.
)
pause
exit /b %ERR%
