@echo off
cd /d "C:\xampp\htdocs\vehicules"

set "LOG=logs\auto_split_daily.log"
if not exist "logs" mkdir "logs"

echo. >> "%LOG%"
echo ========================================= >> "%LOG%"
echo [%date% %time%] DEBUT AUTO SPLIT QUOTIDIEN >> "%LOG%"
echo ========================================= >> "%LOG%"

C:\xampp\php\php.exe auto_split_daily.php >> "%LOG%" 2>&1

if %errorlevel% equ 0 (
    echo [%date% %time%] SUCCES >> "%LOG%"
) else (
    echo [%date% %time%] ERREUR - Code: %errorlevel% >> "%LOG%"
)

echo [%date% %time%] FIN >> "%LOG%"
echo ========================================= >> "%LOG%"
