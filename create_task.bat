@echo off
echo Creation de la tache planifiee...
echo.

schtasks /create /tn "AutoSplitDaily" /tr "C:\xampp\htdocs\vehicules\auto_split_daily.bat" /sc daily /st 01:00 /ru SYSTEM /f

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCES ! Tache planifiee creee
    echo ========================================
    echo Nom: AutoSplitDaily
    echo Heure: 01:00 tous les jours
    echo Script: C:\xampp\htdocs\vehicules\auto_split_daily.bat
    echo ========================================
    echo.
    echo Pour voir les taches planifiees:
    echo schtasks /query /fo list ^| find "AutoSplit"
    echo.
    echo Pour supprimer la tache:
    echo schtasks /delete /tn "AutoSplitDaily" /f
) else (
    echo.
    echo ERREUR: Impossible de creer la tache
    echo Essayez d'executer ce fichier en tant qu'ADMINISTRATEUR
)

pause
