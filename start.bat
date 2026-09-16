@echo off
rem ============================================================================
rem  IMPRINT CUSTOMS HRIS - START
rem
rem  Works on a fresh download: installs what's missing, prepares the database,
rem  then opens the system. Safe to run again any time - each step is skipped
rem  when it has already been done, and it never touches existing data.
rem
rem  Port 8003, so it can run alongside Imprint Production on 8000.
rem ============================================================================
setlocal
title Imprint Customs HRIS - Start

set "APP=%~dp0"
set "APP=%APP:~0,-1%"
set "LOGS=%APP%\logs"
set "PORT=8003"
set "DBNAME=tgif_hris"

rem ---- Find PHP and MySQL.
rem      C:\xampp is tried first. An older C:\xampp1 can still be sitting on
rem      disk after a reinstall, and existing is not the same as working:
rem      preferring it pointed the app at a dead database.
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp1\php\php.exe"
if not exist "%PHP%" (
    where php >nul 2>&1 && set "PHP=php"
)

set "MYSQLD=C:\xampp\mysql\bin\mysqld.exe"
if not exist "%MYSQLD%" set "MYSQLD=C:\xampp1\mysql\bin\mysqld.exe"
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" set "MYSQL=C:\xampp1\mysql\bin\mysql.exe"
set "MYSQLADMIN=C:\xampp\mysql\bin\mysqladmin.exe"
if not exist "%MYSQLADMIN%" set "MYSQLADMIN=C:\xampp1\mysql\bin\mysqladmin.exe"
set "MYSQL_INI=C:\xampp\mysql\bin\my.ini"
if not exist "%MYSQL_INI%" set "MYSQL_INI=C:\xampp1\mysql\bin\my.ini"

if not exist "%LOGS%" mkdir "%LOGS%"

echo ==========================================================
echo   IMPRINT CUSTOMS - HUMAN RESOURCES
echo ==========================================================
echo.

rem ---------- 0. Is PHP there at all? ----------
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP was not found.
    echo.
    echo Install XAMPP ^(https://www.apachefriends.org^) and run this again.
    echo Expected at C:\xampp\php\php.exe
    pause
    exit /b 1
)

rem ---------- 1. Dependencies ----------
if exist "%APP%\vendor\autoload.php" (
    echo [1/6] Dependencies already installed.
) else (
    echo [1/6] Installing dependencies ^(first run, this takes a minute^)...
    where composer >nul 2>&1
    if errorlevel 1 (
        echo.
        echo ERROR: Composer was not found.
        echo Install it from https://getcomposer.org/download and run this again.
        pause
        exit /b 1
    )
    pushd "%APP%"
    call composer install --no-interaction --prefer-dist
    popd
    if not exist "%APP%\vendor\autoload.php" (
        echo ERROR: Dependencies failed to install. See the messages above.
        pause
        exit /b 1
    )
)

rem ---------- 1b. Writable folders Laravel needs at runtime ----------
rem A zip download or an over-zealous cleanup can lose these, and without
rem storage\framework\views every page 500s.
for %%d in (
    "storage\framework\views"
    "storage\framework\sessions"
    "storage\framework\cache\data"
    "storage\logs"
    "storage\app\public"
    "storage\app\backups"
    "bootstrap\cache"
) do if not exist "%APP%\%%~d" mkdir "%APP%\%%~d" 2>nul

rem ---------- 2. Settings file ----------
if exist "%APP%\.env" (
    echo [2/6] Settings file already present.
) else (
    echo [2/6] Creating the settings file from .env.example...
    copy /Y "%APP%\.env.example" "%APP%\.env" >nul
    "%PHP%" "%APP%\artisan" key:generate --force
    powershell -NoProfile -Command "$p='%APP%\.env'; $t=Get-Content $p -Raw; $t=$t -replace '(?m)^DB_DATABASE=.*', 'DB_DATABASE=%DBNAME%'; $t=$t -replace '(?m)^APP_URL=.*', 'APP_URL=http://127.0.0.1:%PORT%'; Set-Content $p $t -NoNewline"
)

rem ---------- 3. MySQL ----------
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | "%SystemRoot%\System32\find.exe" /I "mysqld.exe" >nul
if errorlevel 1 (
    echo [3/6] Starting MySQL...
    if not exist "%MYSQLD%" (
        echo.
        echo ERROR: MySQL was not found. Install XAMPP and run this again.
        pause
        exit /b 1
    )
    start "Imprint MySQL" /MIN "%MYSQLD%" --defaults-file="%MYSQL_INI%" --standalone
) else (
    echo [3/6] MySQL is already running.
)

set /a TRIES=0
:waitmysql
"%MYSQLADMIN%" -u root ping >nul 2>&1
if not errorlevel 1 goto mysqlok
set /a TRIES+=1
if %TRIES% GEQ 30 (
    echo ERROR: MySQL did not start. Open XAMPP and start MySQL manually.
    pause
    exit /b 1
)
timeout /t 1 /nobreak >nul
goto waitmysql
:mysqlok
echo       MySQL is up.

rem ---------- 4. Database ----------
echo [4/6] Preparing the database...
"%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS %DBNAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
"%PHP%" "%APP%\artisan" migrate --force
if errorlevel 1 (
    echo ERROR: The database could not be prepared. See the messages above.
    pause
    exit /b 1
)

rem ---------- 5. Accounts + uploads link ----------
rem Only seed an EMPTY system. The seeder would otherwise reset the two
rem starting accounts on every run, including a password somebody has changed.
echo [5/6] Checking accounts...
rem Through a file rather than a for/f: the nested quoting a one-liner needs
rem breaks on the parentheses in COUNT(*).
set "USERCOUNT=0"
"%MYSQL%" -u root -N -e "SELECT COUNT(*) FROM %DBNAME%.users;" > "%TEMP%\hris-users.txt" 2>nul
if exist "%TEMP%\hris-users.txt" set /p USERCOUNT=<"%TEMP%\hris-users.txt"
del "%TEMP%\hris-users.txt" >nul 2>&1
if "%USERCOUNT%"=="" set "USERCOUNT=0"

if "%USERCOUNT%"=="0" (
    echo       First run - creating the admin and HR accounts...
    "%PHP%" "%APP%\artisan" db:seed --force
) else (
    echo       %USERCOUNT% account^(s^) already set up - left alone.
)
if not exist "%APP%\public\storage" (
    "%PHP%" "%APP%\artisan" storage:link >nul 2>&1
)

rem ---------- 6. Serve ----------
powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalPort %PORT% -State Listen -ErrorAction SilentlyContinue) { exit 1 } else { exit 0 }"
if errorlevel 1 (
    echo [6/6] Already running on port %PORT%.
) else (
    echo [6/6] Starting the system on port %PORT%...
    start "Imprint HRIS" /MIN cmd /c ""%PHP%" "%APP%\artisan" serve --host=0.0.0.0 --port=%PORT% > "%LOGS%\serve.log" 2>&1"
)

powershell -NoProfile -Command "foreach($i in 1..30){ try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:%PORT%/up' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -eq 200) { exit 0 } } catch {}; Start-Sleep -Seconds 1 }; exit 1"
if errorlevel 1 (
    echo ERROR: The system did not start. See %LOGS%\serve.log
    pause
    exit /b 1
)

set "LANIP="
for /f "usebackq delims=" %%i in (`powershell -NoProfile -ExecutionPolicy Bypass -File "%APP%\get-lan-ip.ps1" 2^>nul`) do set "LANIP=%%i"
if "%LANIP%"=="ERROR" set "LANIP="

echo.
echo ==========================================================
echo   RUNNING
echo ==========================================================
echo.
echo   On this computer :  http://127.0.0.1:%PORT%
if not "%LANIP%"=="" echo   On the network   :  http://%LANIP%:%PORT%
echo.
echo   HR back office   :  http://127.0.0.1:%PORT%/admin/login
echo   Staff sign-in    :  http://127.0.0.1:%PORT%/login
echo.
echo   Sign in with:  hr
echo   Password    :  imprint123
echo.
echo   Change that password after the first sign-in - it is in
echo   the public repository, so it is not a secret.
echo.
echo   Keep the minimised "Imprint MySQL" and "Imprint HRIS"
echo   windows open - closing them stops the system.
echo ==========================================================
echo.

start "" "http://127.0.0.1:%PORT%/admin/login"
pause
exit /b 0
