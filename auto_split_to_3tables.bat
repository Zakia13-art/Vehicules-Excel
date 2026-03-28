@echo off
REM ========================================
REM AUTO SPLIT TEMPLATE 1 -> 3 TABLES
REM ========================================

cd /d "C:\xampp\htdocs\vehicules"

set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=auto_split_to_3tables.php"
set "LOG=logs\auto_split.log"

if not exist "logs" mkdir "logs"

echo. >> "%LOG%"
echo ========================================= >> "%LOG%"
echo [%date% %time%] DEBUT AUTO SPLIT >> "%LOG%"
echo ========================================= >> "%LOG%"

"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1

if %errorlevel% equ 0 (
    echo [%date% %time%] SUCCES >> "%LOG%"
) else (
    echo [%date% %time%] ERREUR - Code: %errorlevel% >> "%LOG%"
)

echo [%date% %time%] FIN >> "%LOG%"
echo ========================================= >> "%LOG%"

echo Split termine - voir log: %LOG%
timeout /t 3
