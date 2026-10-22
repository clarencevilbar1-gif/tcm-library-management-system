@echo off
setlocal
cd /d "%~dp0"
set "PHP=%~dp0..\..\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
echo ==============================================
echo   TCM Law Library - BACKUP
echo   (MySQL must be running in the XAMPP Control Panel)
echo ==============================================
echo.
"%PHP%" backup_cli.php backup %*
echo.
pause
