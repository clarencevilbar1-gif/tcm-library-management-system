@echo off
setlocal EnableExtensions
title Fix Library - repair the database service
cd /d "%~dp0"

rem ---------------------------------------------------------------
rem  TCM Law Library - FIX LIBRARY
rem  Repairs MySQL when it will not start after a power cut or a
rem  sudden shutdown. Nothing is deleted: the old data folder is
rem  always kept next to the new one.
rem ---------------------------------------------------------------

rem --- 1. ask Windows for administrator permission (needed to stop/start MySQL)
net session >nul 2>&1
if errorlevel 1 (
    echo Windows will now ask for permission. Please click YES.
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs" >nul 2>&1
    exit /b
)

rem --- 2. find XAMPP (two folders above this one, or C:\xampp)
for %%I in ("%~dp0..\..") do set "XAMPP=%%~fI"
if not exist "%XAMPP%\mysql\bin\mysqld.exe" set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql\bin\mysqld.exe" goto :noxampp

set "MYSQLBIN=%XAMPP%\mysql\bin"
set "DATA=%XAMPP%\mysql\data"
set "PHP=%XAMPP%\php\php.exe"
for /f %%T in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "STAMP=%%T"
set "LOGDIR=%XAMPP%\law-library-backups"
if not exist "%LOGDIR%" mkdir "%LOGDIR%" >nul 2>&1
set "LOG=%LOGDIR%\fix-library-log.txt"

cls
echo ==============================================
echo   TCM Law Library - FIX LIBRARY
echo ==============================================
echo.
echo  This will repair the database service. It takes about one minute.
echo  Please do not close this window.
echo.
echo [%date% %time%] fix started >> "%LOG%"

rem --- 3. is everything already fine?
call :ping
if errorlevel 1 goto :level1
call :dbcheck
if not errorlevel 1 goto :alreadyok
rem the service runs but the library data is not readable
goto :needrestore

rem ================================================================
rem  LEVEL 1 - light repair: remove the leftovers of the bad shutdown
rem ================================================================
:level1
echo  Step 1 of 3: stopping anything left running...
call :stopmysql
echo  Step 2 of 3: clearing the leftovers of the sudden shutdown...
del /q "%DATA%\*.pid" >nul 2>&1
del /q "%DATA%\aria_log.*" >nul 2>&1
del /q "%DATA%\aria_log_control" >nul 2>&1
echo  Step 3 of 3: starting the database service...
call :startmysql
call :waitping
if errorlevel 1 goto :level2
call :dbcheck
if errorlevel 1 goto :needrestore
echo [%date% %time%] level 1 repair worked >> "%LOG%"
goto :success

rem ================================================================
rem  LEVEL 2 - rebuild the system part, keep the library's own data
rem ================================================================
:level2
echo.
echo  The light repair was not enough. Trying a deeper repair...
echo  (Your old data is kept safe in a folder named data_damaged_%STAMP%)
call :stopmysql
if not exist "%XAMPP%\mysql\backup\mysql" goto :level3
ren "%DATA%" "data_damaged_%STAMP%"
if errorlevel 1 goto :failed
mkdir "%DATA%" >nul 2>&1
robocopy "%XAMPP%\mysql\backup" "%DATA%" /E /NFL /NDL /NJH /NJS /NP >nul
rem put the library's own databases back (every folder except XAMPP's own)
for /d %%D in ("%XAMPP%\mysql\data_damaged_%STAMP%\*") do (
    if /i not "%%~nxD"=="mysql" if /i not "%%~nxD"=="performance_schema" if /i not "%%~nxD"=="phpmyadmin" if /i not "%%~nxD"=="sys" if /i not "%%~nxD"=="test" (
        robocopy "%%D" "%DATA%\%%~nxD" /E /NFL /NDL /NJH /NJS /NP >nul
    )
)
if exist "%XAMPP%\mysql\data_damaged_%STAMP%\ibdata1" copy /y "%XAMPP%\mysql\data_damaged_%STAMP%\ibdata1" "%DATA%\ibdata1" >nul
call :startmysql
call :waitping
if errorlevel 1 goto :level3
call :dbcheck
if errorlevel 1 goto :needrestore
echo [%date% %time%] level 2 repair worked >> "%LOG%"
goto :success

rem ================================================================
rem  LEVEL 3 - clean service, library data comes back from a backup
rem ================================================================
:level3
echo.
echo  Trying the last repair: a clean database service...
call :stopmysql
if exist "%DATA%" ren "%DATA%" "data_failed_%STAMP%"
if exist "%DATA%" goto :failed
mkdir "%DATA%" >nul 2>&1
robocopy "%XAMPP%\mysql\backup" "%DATA%" /E /NFL /NDL /NJH /NJS /NP >nul
call :startmysql
call :waitping
if errorlevel 1 goto :failed
echo [%date% %time%] level 3: clean service, restore needed >> "%LOG%"
goto :needrestore

rem ================================================================
:alreadyok
echo.
echo  Everything is working. Nothing needed repair.
echo  If the website still does not open, start Apache in the XAMPP Control Panel.
echo.
pause
exit /b 0

:success
echo.
echo  ==============================================
echo   DONE. The database service is working again.
echo  ==============================================
echo  Opening the library system...
start "" "http://localhost/law-library/"
echo.
pause
exit /b 0

:needrestore
echo.
echo  ==============================================
echo   The database service is working again, BUT the library's
echo   records must be brought back from a backup.
echo  ==============================================
echo.
echo  A page will open now. On that page:
echo    1. Choose the NEWEST backup in the list.
echo    2. Type the word RESTORE and press the button.
echo  Your damaged files are kept safe in:
echo    %XAMPP%\mysql
echo  If you need help, call the system developer.
echo.
start "" "http://localhost/law-library/emergency_restore.php"
echo [%date% %time%] restore from backup needed >> "%LOG%"
pause
exit /b 0

:failed
echo.
echo  ==============================================
echo   The repair did not work on this computer.
echo  ==============================================
echo  Please restart the computer and double-click Fix Library once more.
echo  If it still fails, call the system developer. Your data files are
echo  kept safe in the folder:  %XAMPP%\mysql
echo  Please show the developer this file:  %LOG%
echo [%date% %time%] repair failed >> "%LOG%"
echo.
pause
exit /b 1

:noxampp
echo.
echo  XAMPP could not be found on this computer.
echo  Please call the system developer.
echo.
pause
exit /b 1

rem ================================================================
rem  helper routines
rem ================================================================
:ping
"%MYSQLBIN%\mysqladmin.exe" -u root ping >nul 2>&1
exit /b %errorlevel%

:waitping
set /a TRIES=0
:waitloop
call :ping
if not errorlevel 1 exit /b 0
set /a TRIES+=1
if %TRIES% GEQ 30 exit /b 1
timeout /t 1 /nobreak >nul
goto :waitloop

:dbcheck
set "DBOK="
if not exist "%PHP%" exit /b 0
"%PHP%" "%~dp0db_check.php" > "%TEMP%\tcm_dbcheck.txt" 2>nul
findstr /c:"DBOK" "%TEMP%\tcm_dbcheck.txt" >nul 2>&1
if not errorlevel 1 set "DBOK=1"
del "%TEMP%\tcm_dbcheck.txt" >nul 2>&1
if defined DBOK exit /b 0
exit /b 1

:stopmysql
sc query mysql >nul 2>&1
if not errorlevel 1 net stop mysql >nul 2>&1
taskkill /f /im mysqld.exe >nul 2>&1
timeout /t 2 /nobreak >nul
exit /b 0

:startmysql
sc query mysql >nul 2>&1
if not errorlevel 1 (
    net start mysql >nul 2>&1
) else (
    powershell -NoProfile -Command "Start-Process -FilePath '%MYSQLBIN%\mysqld.exe' -ArgumentList '--defaults-file=%MYSQLBIN%\my.ini','--standalone' -WindowStyle Hidden" >nul 2>&1
)
exit /b 0
