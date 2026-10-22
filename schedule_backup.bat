@echo off
setlocal
cd /d "%~dp0"
set "PHP=%~dp0..\..\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"
if exist "%PHP%" goto havephp
echo Could not find PHP at C:\xampp\php\php.exe
echo Open this file in Notepad and change the line that starts with  set "PHP=C:\xampp...
pause
exit /b 1

:havephp
for %%I in ("%PHP%") do set "PHP=%%~fI"
echo ==============================================
echo   TCM Law Library - DAILY AUTOMATIC BACKUP
echo ==============================================
echo.
echo This makes Windows run a backup every day, even when nobody
echo opens the system. The computer must be on and signed in to Windows.
echo.
set "T=17:00"
set /p T=At what time each day? Use 24-hour time like 17:00 [17:00]: 
echo.
schtasks /Create /SC DAILY /TN "TCM Library Backup" /TR "\"%PHP%\" \"%~dp0backup_cli.php\" backup --scheduled" /ST %T% /F
if errorlevel 1 goto failed
echo.
echo Done. A backup will run every day at %T%.
echo To stop it later, run this in a Command Prompt:
echo     schtasks /Delete /TN "TCM Library Backup" /F
pause
exit /b 0

:failed
echo.
echo The task could not be created. Right-click this file and choose
echo "Run as administrator", then try again.
pause
exit /b 1
