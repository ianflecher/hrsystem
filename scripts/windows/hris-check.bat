@echo off
setlocal
cd /d "%~dp0..\.."
echo ============================================
echo Imprint Customs HRIS 6.2 Production Check
echo ============================================
echo Project: %CD%

echo.
where php >nul 2>&1 && (php -v | findstr /B /C:"PHP") || echo [WARN] PHP not found on PATH
where composer >nul 2>&1 && composer --version || echo [WARN] Composer not found on PATH
where node >nul 2>&1 && node --version || echo [WARN] Node not found on PATH
where npm >nul 2>&1 && npm --version || echo [WARN] npm not found on PATH
where mysql >nul 2>&1 && mysql --version || echo [WARN] mysql not found on PATH

echo.
if not exist artisan (
  echo [FAIL] artisan not found. Run this script from inside the extracted hris folder.
  exit /b 1
)

echo [OK] artisan found.
php artisan --version
php artisan hris:health
if errorlevel 1 exit /b 1
php artisan route:list --except-vendor > "%TEMP%\hris_routes.txt"
if errorlevel 1 exit /b 1
findstr /I "employee/benefits hr/operations/approval-center hr/operations/approvals" "%TEMP%\hris_routes.txt" >nul
if errorlevel 1 echo [WARN] Expected workflow routes were not all found.

echo.
echo Running application tests...
php artisan test
if errorlevel 1 exit /b 1

echo.
if exist package.json (
  echo Running frontend production build...
  call npm run build
  if errorlevel 1 exit /b 1
)

echo.
echo [PASS] HRIS 6.2 validation commands completed.
endlocal
