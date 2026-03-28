@echo off
cd /d "C:\xampp\htdocs\vehicules"
echo Import 30 derniers jours - debut: %date% %time%
C:\xampp\php\php.exe auto_split_to_3tables_30j.php
echo Fin: %date% %time%
pause
