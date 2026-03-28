@echo off
REM ========================================
REM ALL AUTO - Execute tous les scripts
REM Ce fichier lance tous les .bat automatiquement
REM ========================================

cd /d "C:\xampp\htdocs\vehicules"

echo ========================================
echo LANCEMENT TOUS LES SCRIPTS AUTO
echo Date: %date% %time%
echo ========================================
echo.

echo [1/4] Auto Save 3 Tables...
call auto_save_3tables.bat

echo.
echo [2/4] Auto Save Trajets...
call auto_save_trajets.bat

echo.
echo [3/4] Auto Wialon...
call auto_wialon.bat

echo.
echo [4/4] Auto Split Daily...
call auto_split_daily.bat

echo.
echo ========================================
echo TERMINE ! Tous les scripts ont ete executes
echo Date: %date% %time%
echo ========================================

timeout /t 5
