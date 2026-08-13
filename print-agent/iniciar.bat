@echo off
setlocal EnableExtensions
title Agente de Impressao - Bella Bistro
cd /d "%~dp0"

set "CFG=%~dp0config.ini"
set "EXAMPLE=%~dp0config.example.ini"
set "PS1=%~dp0print-agent.ps1"

if exist "%CFG%" goto :run

if exist "%EXAMPLE%" (
    copy /y "%EXAMPLE%" "%CFG%" >nul
    echo.
    echo Criei config.ini a partir do exemplo.
    echo Abra o arquivo, cole o TOKEN e o IP da impressora, salve.
    echo Depois rode este iniciar.bat de novo.
    echo.
    notepad "%CFG%"
    pause
    exit /b 0
)

echo.
echo Falta config.ini na pasta:
echo   %~dp0
echo.
echo Copie config.example.ini para config.ini e preencha url/token/printer.
echo.
pause
exit /b 1

:run
echo.
echo Pasta: %~dp0
echo Config: %CFG%
echo.
echo Iniciando agente PowerShell - sem PHP...
echo Deixe esta janela aberta. Para parar: Ctrl+C
echo.

if not exist "%PS1%" (
    echo Nao achei print-agent.ps1 nesta pasta.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%"
set "ERR=%ERRORLEVEL%"

echo.
if not "%ERR%"=="0" (
    echo Agente encerrou com erro %ERR%.
) else (
    echo Agente encerrado.
)
pause
exit /b %ERR%
