@echo off
REM ========================================
REM AUTO IMPORT WIALON - Quotidien
REM Execute tous les jours a 01:00 du matin
REM Importe les donnees d'HIER
REM ========================================

setlocal enabledelayedexpansion

set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\htdocs\vehicules\auto_import_wialon.php"
set "LOG=C:\xampp\htdocs\vehicules\logs\auto_wialon.log"

if not exist "C:\xampp\htdocs\vehicules\logs" mkdir "C:\xampp\htdocs\vehicules\logs"

echo. >> "%LOG%"
echo ========================================= >> "%LOG%"
echo [%date% %time%] DEBUT IMPORT AUTOMATIQUE >> "%LOG%"
echo Importe les donnees d'HIER >> "%LOG%"
echo ========================================= >> "%LOG%"

"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1

if %errorlevel% equ 0 (
    echo [%date% %time%] SUCCES >> "%LOG%"
) else (
    echo [%date% %time%] ERREUR - Code: %errorlevel% >> "%LOG%"
)

echo [%date% %time%] FIN IMPORT >> "%LOG%"
echo ========================================= >> "%LOG%"

echo Import termine - voir log: %LOG%
timeout /t 3
