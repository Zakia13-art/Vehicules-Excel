@echo off
REM Changer vers le dossier du projet
cd /d "C:\xampp\htdocs\vehicules"

setlocal enabledelayedexpansion
set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=auto_save_trajets.php"
set "LOG=logs\auto_save_trajets.log"
if not exist "logs" mkdir "logs"
echo. >> "%LOG%"
echo ========================================= >> "%LOG%"
echo [%date% %time%] DEBUT AUTO SAVE >> "%LOG%"
echo ========================================= >> "%LOG%"
"%PHP%" "%SCRIPT%" >> "%LOG%" 2>&1
if %errorlevel% equ 0 (
    echo [%date% %time%] SUCCES >> "%LOG%"
) else (
    echo [%date% %time%] ERREUR >> "%LOG%"
)
echo [%date% %time%] FIN >> "%LOG%"
echo ========================================= >> "%LOG%"
echo Save termine!
timeout /t 2
