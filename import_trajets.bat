@echo off
REM ========================================
REM WIALON AUTO IMPORT - Every 5 seconds
REM ========================================

setlocal enabledelayedexpansion

set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\htdocs\vehicules\lesgets.php"
set "LOG=C:\xampp\htdocs\vehicules\logs\import.log"

if not exist "C:\xampp\htdocs\vehicules\logs" mkdir "C:\xampp\htdocs\vehicules\logs"

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