@echo off
setlocal
cd /d "%~dp0"
set "PHP=%~dp0..\..\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
echo ==============================================
echo   TCM Law Library - RESTORE FROM A BACKUP
echo   (MySQL must be running in the XAMPP Control Panel.
echo    Apache is NOT needed for this.)
echo ==============================================
echo.
"%PHP%" backup_cli.php restore %*
echo.
pause
