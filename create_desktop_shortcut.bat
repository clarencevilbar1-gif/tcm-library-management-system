@echo off
title Create Desktop Shortcut
cd /d "%~dp0"
echo.
echo  Creating the "College of Law Library" shortcut on the Desktop...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0create_desktop_shortcut.ps1"
if errorlevel 1 (
    echo.
    echo  The shortcut could not be created. Please call the system developer.
) else (
    echo.
    echo  Double-click "College of Law Library" on the Desktop to open the system.
)
echo.
pause
