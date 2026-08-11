@echo off
chcp 65001 >nul
title Baixar Agente de Impressao
set "DIR=%USERPROFILE%\Desktop\bella-print-agent"
set "BASE=https://raw.githubusercontent.com/megaflood-br/restaurant-system/main/print-agent"

echo.
echo Vai baixar so o agente (PowerShell, sem PHP) para:
echo   %DIR%
echo.
mkdir "%DIR%" 2>nul

powershell -NoProfile -Command ^
  "Invoke-WebRequest -UseBasicParsing '%BASE%/print-agent.ps1' -OutFile '%DIR%\print-agent.ps1'; ^
   Invoke-WebRequest -UseBasicParsing '%BASE%/print-agent.php' -OutFile '%DIR%\print-agent.php'; ^
   Invoke-WebRequest -UseBasicParsing '%BASE%/config.example.ini' -OutFile '%DIR%\config.example.ini'; ^
   Invoke-WebRequest -UseBasicParsing '%BASE%/iniciar.bat' -OutFile '%DIR%\iniciar.bat'; ^
   Invoke-WebRequest -UseBasicParsing '%BASE%/iniciar-php.bat' -OutFile '%DIR%\iniciar-php.bat'; ^
   Invoke-WebRequest -UseBasicParsing '%BASE%/LEIA-ME.txt' -OutFile '%DIR%\LEIA-ME.txt'"

if errorlevel 1 (
    echo Falha no download. Verifique a internet.
    pause
    exit /b 1
)

echo.
echo Pronto! Pasta: %DIR%
echo 1^) Abra config.example.ini, salve como config.ini com seu token
echo 2^) Duplo clique em iniciar.bat  ^(nao precisa de PHP^)
echo.
explorer "%DIR%"
pause
