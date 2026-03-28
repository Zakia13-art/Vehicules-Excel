@echo off
echo ========================================
echo GESTION TACHE PLANIFIEE
echo ========================================
echo.
echo 1. Voir les taches planifiees
echo 2. Supprimer la tache AutoSplitDaily
echo 3. Quitter
echo.
set /p choix="Choisissez une option (1-3): "

if "%choix%"=="1" goto voir
if "%choix%"=="2" goto supprimer
goto fin

:voir
echo.
echo --- Taches planifiees ---
schtasks /query /fo list | find "AutoSplit"
echo.
echo Pour voir toutes les taches: schtasks /query
goto fin

:supprimer
echo.
schtasks /delete /tn "AutoSplitDaily" /f
if %errorlevel% equ 0 (
    echo Tache AutoSplitDaily supprimee avec succes
) else (
    echo Erreur lors de la suppression ou tache inexistante
)
goto fin

:fin
pause
