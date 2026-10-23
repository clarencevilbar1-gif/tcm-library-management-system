@echo off
setlocal EnableExtensions
title Library System
cd /d "%~dp0"

rem ---------------------------------------------------------------
rem  TCM Law Library - START LIBRARY
rem  Starts the web server and the database (if they are not running)
rem  and opens the system in the browser.
rem ---------------------------------------------------------------

for %%I in ("%~dp0..\..") do set "XAMPP=%%~fI"
if not exist "%XAMPP%\mysql\bin\mysqld.exe" set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql\bin\mysqld.exe" goto :noxampp

set "MYSQLBIN=%XAMPP%\mysql\bin"
set "PHP=%XAMPP%\php\php.exe"
set "URL=http://localhost/law-library/"

cls
echo ==============================================
echo   TCM Law Library - starting the system
echo ==============================================
echo.

rem --- database
call :ping
if not errorlevel 1 goto :dbready
echo  Starting the database...
sc query mysql >nul 2>&1
if not errorlevel 1 (
    net start mysql >nul 2>&1
) else (
    powershell -NoProfile -Command "Start-Process -FilePath '%MYSQLBIN%\mysqld.exe' -ArgumentList '--defaults-file=%MYSQLBIN%\my.ini','--standalone' -WindowStyle Hidden" >nul 2>&1
)
set /a TRIES=0
:waitdb
call :ping
if not errorlevel 1 goto :dbready
set /a TRIES+=1
if %TRIES% GEQ 30 goto :dbfail
timeout /t 1 /nobreak >nul
goto :waitdb

:dbready
rem --- web server
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if not errorlevel 1 goto :webready
echo  Starting the web server...
sc query Apache2.4 >nul 2>&1
if not errorlevel 1 (
    net start Apache2.4 >nul 2>&1
) else (
    powershell -NoProfile -Command "Start-Process -FilePath '%XAMPP%\apache\bin\httpd.exe' -WindowStyle Hidden" >nul 2>&1
)
timeout /t 4 /nobreak >nul
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if errorlevel 1 goto :webfail

:webready
rem --- can the library data be read?
set "DBOK="
if exist "%PHP%" for /f "delims=" %%R in ('"%PHP%" "%~dp0db_check.php" 2^>nul') do if "%%R"=="DBOK" set "DBOK=1"
if defined DBOK goto :open
echo.
echo  The database is running, but the library records cannot be read.
echo  Please double-click  FIX LIBRARY  (fix_mysql.bat).
echo.
pause
exit /b 1

:open
echo  Ready. Opening the system...
start "" "%URL%"
timeout /t 2 /nobreak >nul
exit /b 0

:dbfail
echo.
echo  ==============================================
echo   The database could not be started.
echo  ==============================================
echo  Please double-click  FIX LIBRARY  (fix_mysql.bat)  and wait.
echo.
pause
exit /b 1

:webfail
echo.
echo  ==============================================
echo   The web server could not be started.
echo  ==============================================
echo  Please restart the computer and try again.
echo  If it still does not work, open the XAMPP Control Panel and press
echo  Start beside Apache, then call the system developer.
echo.
pause
exit /b 1

:noxampp
echo.
echo  XAMPP could not be found on this computer. Please call the system developer.
echo.
pause
exit /b 1

:ping
"%MYSQLBIN%\mysqladmin.exe" -u root ping >nul 2>&1
exit /b %errorlevel%
