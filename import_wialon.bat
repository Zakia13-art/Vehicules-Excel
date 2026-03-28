@echo off
REM ========================================
REM WIALON AUTO IMPORT - Every 5 seconds
REM ========================================

REM Changer vers le dossier du projet
cd /d "C:\xampp\htdocs\vehicules"

setlocal enabledelayedexpansion

set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=sync_wialon_cron.php"
set "LOG=logs\import_wialon.log"

if not exist "logs" mkdir "logs"

REM Infinite loop - run every 5 seconds
:loop
echo [%date% %time%] === IMPORT START === >> "%LOG%"

"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1

if %errorlevel% equ 0 (
    echo [%date% %time%] === SUCCESS === >> "%LOG%"
) else (
    echo [%date% %time%] === FAILED === >> "%LOG%"
)

echo [%date% %time%] === IMPORT END === >> "%LOG%"
echo. >> "%LOG%"

REM Wait 5 seconds
timeout /t 5 /nobreak

REM Go back to loop
goto loop
