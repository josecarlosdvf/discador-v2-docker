@echo off
REM Deploy Script for Discador v2.0 on Windows
REM This script sets up the complete Discador v2.0 environment

setlocal EnableDelayedExpansion

echo ========================================
echo   Discador v2.0 - Deploy Script
echo ========================================
echo.

REM Get script directory
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "SRC_DIR=%PROJECT_ROOT%\src"

echo Project Root: %PROJECT_ROOT%
echo.

REM Check if PHP is available
php --version >nul 2>&1
if !errorlevel! neq 0 (
    echo [ERROR] PHP not found in PATH
    echo Please install PHP and add it to your PATH
    pause
    exit /b 1
)

echo [OK] PHP found
php --version | findstr "PHP"
echo.

REM Check if Composer is available
composer --version >nul 2>&1
if !errorlevel! neq 0 (
    echo [WARNING] Composer not found
    echo Some dependencies might not be available
) else (
    echo [OK] Composer found
    composer --version | findstr "Composer"
)
echo.

REM Check required PHP extensions
echo Checking PHP extensions...

php -m | findstr "redis" >nul
if !errorlevel! equ 0 (
    echo [OK] Redis extension available
) else (
    echo [WARNING] Redis extension not found
    echo Please install php-redis extension for better performance
)

php -m | findstr "pdo_mysql" >nul
if !errorlevel! equ 0 (
    echo [OK] PDO MySQL extension available
) else (
    echo [ERROR] PDO MySQL extension not found
    echo Please install php-pdo-mysql extension
    pause
    exit /b 1
)

php -m | findstr "curl" >nul
if !errorlevel! equ 0 (
    echo [OK] cURL extension available
) else (
    echo [WARNING] cURL extension not found
)

echo.

REM Create necessary directories
echo Creating directory structure...

if not exist "%PROJECT_ROOT%\logs" mkdir "%PROJECT_ROOT%\logs"
if not exist "%PROJECT_ROOT%\backup" mkdir "%PROJECT_ROOT%\backup"
if not exist "%PROJECT_ROOT%\tmp" mkdir "%PROJECT_ROOT%\tmp"
if not exist "%PROJECT_ROOT%\data" mkdir "%PROJECT_ROOT%\data"

echo [OK] Directories created
echo.

REM Set permissions (Windows equivalent)
echo Setting permissions...
icacls "%PROJECT_ROOT%\logs" /grant Everyone:(OI)(CI)F >nul 2>&1
icacls "%PROJECT_ROOT%\backup" /grant Everyone:(OI)(CI)F >nul 2>&1
icacls "%PROJECT_ROOT%\tmp" /grant Everyone:(OI)(CI)F >nul 2>&1
icacls "%PROJECT_ROOT%\data" /grant Everyone:(OI)(CI)F >nul 2>&1
echo [OK] Permissions set
echo.

REM Test Redis connection if available
echo Testing Redis connection...
php "%SCRIPT_DIR%\redis_config.php" test >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Redis connection successful
    
    REM Configure Redis
    echo Configuring Redis for Discador v2.0...
    php "%SCRIPT_DIR%\redis_config.php" configure
    
    if !errorlevel! equ 0 (
        echo [OK] Redis configured successfully
    ) else (
        echo [WARNING] Redis configuration failed
    )
) else (
    echo [WARNING] Redis connection failed
    echo Please ensure Redis server is running
)
echo.

REM Create systemd service file (Windows Task Scheduler equivalent script)
echo Creating Windows service scripts...

REM Create start script
echo @echo off > "%PROJECT_ROOT%\start_discador.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\start_discador.bat"
echo php scripts\discador_control.php start >> "%PROJECT_ROOT%\start_discador.bat"
echo pause >> "%PROJECT_ROOT%\start_discador.bat"

REM Create stop script
echo @echo off > "%PROJECT_ROOT%\stop_discador.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\stop_discador.bat"
echo php scripts\discador_control.php stop >> "%PROJECT_ROOT%\stop_discador.bat"
echo pause >> "%PROJECT_ROOT%\stop_discador.bat"

REM Create status script
echo @echo off > "%PROJECT_ROOT%\status_discador.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\status_discador.bat"
echo php scripts\discador_control.php status >> "%PROJECT_ROOT%\status_discador.bat"
echo pause >> "%PROJECT_ROOT%\status_discador.bat"

REM Create monitor script
echo @echo off > "%PROJECT_ROOT%\monitor_discador.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\monitor_discador.bat"
echo php scripts\discador_monitor.php >> "%PROJECT_ROOT%\monitor_discador.bat"
echo pause >> "%PROJECT_ROOT%\monitor_discador.bat"

echo [OK] Service scripts created
echo.

REM Test configuration
echo Testing configuration...
php -f "%SRC_DIR%\config\config.php" >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Configuration file valid
) else (
    echo [ERROR] Configuration file has issues
    echo Please check %SRC_DIR%\config\config.php
)
echo.

REM Run diagnostic
echo Running system diagnostic...
php "%SCRIPT_DIR%\discador_diagnostic.php" >"%PROJECT_ROOT%\logs\deploy_diagnostic.log" 2>&1
if !errorlevel! equ 0 (
    echo [OK] System diagnostic passed
) else (
    echo [WARNING] System diagnostic found issues
    echo Check %PROJECT_ROOT%\logs\deploy_diagnostic.log for details
)
echo.

REM Create shortcuts
echo Creating desktop shortcuts...

REM Create VBS script for shortcuts (no console window)
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%PROJECT_ROOT%\create_shortcut.vbs"
echo sLinkFile = "%USERPROFILE%\Desktop\Discador v2.0 - Start.lnk" >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo oLink.TargetPath = "%PROJECT_ROOT%\start_discador.bat" >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo oLink.IconLocation = "%SystemRoot%\System32\shell32.dll,25" >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo oLink.Save >> "%PROJECT_ROOT%\create_shortcut.vbs"

cscript //nologo "%PROJECT_ROOT%\create_shortcut.vbs" >nul 2>&1
del "%PROJECT_ROOT%\create_shortcut.vbs" >nul 2>&1

echo [OK] Desktop shortcuts created
echo.

REM Summary
echo ========================================
echo   Deploy Summary
echo ========================================
echo.
echo [✓] Directory structure created
echo [✓] Service scripts created
echo [✓] Permissions configured
echo [✓] Desktop shortcuts created

php -m | findstr "redis" >nul
if !errorlevel! equ 0 (
    echo [✓] Redis extension available
) else (
    echo [!] Redis extension missing
)

php "%SCRIPT_DIR%\redis_config.php" test >nul 2>&1
if !errorlevel! equ 0 (
    echo [✓] Redis server accessible
) else (
    echo [!] Redis server not accessible
)

echo.
echo Available commands:
echo   - start_discador.bat     : Start the system
echo   - stop_discador.bat      : Stop the system  
echo   - status_discador.bat    : Check status
echo   - monitor_discador.bat   : Monitor dashboard
echo.
echo Web interface available at: http://localhost/discador_v2/src/
echo Monitor dashboard at: http://localhost/discador_v2/src/monitor-dashboard.php
echo.

if exist "%USERPROFILE%\Desktop\Discador v2.0 - Start.lnk" (
    echo Desktop shortcut created: "Discador v2.0 - Start"
    echo.
)

echo Next steps:
echo 1. Ensure Redis server is running
echo 2. Configure database connection in config/config.php
echo 3. Run: start_discador.bat to start the system
echo 4. Access web interface to manage campaigns
echo.

echo Deploy completed successfully!
echo.
pause
