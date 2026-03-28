@echo off
REM ========================================
REM AUTO SAVE 3 TABLES - Quotidien
REM Execute tous les jours a 01:00
REM ========================================

setlocal enabledelayedexpansion

REM Changer vers le dossier du projet
cd /d "C:\xampp\htdocs\vehicules"

set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=auto_save_3tables.php"
set "LOG=logs\auto_save_3tables.log"

if not exist "logs" mkdir "logs"

echo. >> "%LOG%"
echo ========================================= >> "%LOG%"
echo [%date% %time%] DEBUT AUTO SAVE 3 TABLES >> "%LOG%"
echo ========================================= >> "%LOG%"

"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1

if %errorlevel% equ 0 (
    echo [%date% %time%] SUCCES >> "%LOG%"
) else (
    echo [%date% %time%] ERREUR - Code: %errorlevel% >> "%LOG%"
)

echo [%date% %time%] FIN SAVE >> "%LOG%"
echo ========================================= >> "%LOG%"

echo Save termine - voir log: %LOG%
timeout /t 3
