@echo off
chcp 65001 >nul
title Agente de Impressao - Bella Bistro
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo.
    echo PHP nao encontrado neste PC.
    echo Instale o PHP para Windows e marque "Add PHP to PATH":
    echo   https://windows.php.net/download/
    echo.
    echo Ou use o XAMPP / Laragon e rode pelo terminal deles.
    echo.
    pause
    exit /b 1
)

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

echo Iniciando agente...
echo Deixe esta janela aberta. Para parar: Ctrl+C
echo.
php "%~dp0print-agent.php"
echo.
echo Agente encerrado.
pause
