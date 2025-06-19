@echo off
REM Redis Installation Script for Windows
REM Downloads and configures Redis for Discador v2.0

setlocal EnableDelayedExpansion

echo ========================================
echo   Redis Installation for Windows
echo ========================================
echo.

set "REDIS_VERSION=5.0.14.1"
set "INSTALL_DIR=%~dp0..\tools\redis"
set "REDIS_URL=https://github.com/microsoftarchive/redis/releases/download/win-3.0.504/Redis-x64-3.0.504.zip"
set "REDIS_ZIP=%TEMP%\redis.zip"

REM Check if Redis is already installed
if exist "%INSTALL_DIR%\redis-server.exe" (
    echo Redis is already installed at: %INSTALL_DIR%
    echo.
    goto :configure
)

echo Checking if Redis is already available in PATH...
redis-server --version >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Redis is already available in PATH
    redis-server --version
    echo.
    goto :configure
)

echo Redis not found. Installing Redis...
echo.

REM Create installation directory
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"

REM Download Redis (using PowerShell)
echo Downloading Redis from GitHub...
powershell -Command "& {[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri '%REDIS_URL%' -OutFile '%REDIS_ZIP%'}" 2>nul

if not exist "%REDIS_ZIP%" (
    echo [ERROR] Failed to download Redis
    echo Please download Redis manually from: %REDIS_URL%
    echo Extract to: %INSTALL_DIR%
    pause
    exit /b 1
)

echo [OK] Redis downloaded
echo.

REM Extract Redis
echo Extracting Redis...
powershell -Command "Expand-Archive -Path '%REDIS_ZIP%' -DestinationPath '%INSTALL_DIR%' -Force" 2>nul

REM Clean up
del "%REDIS_ZIP%" >nul 2>&1

if not exist "%INSTALL_DIR%\redis-server.exe" (
    echo [ERROR] Redis extraction failed
    echo Please extract manually to: %INSTALL_DIR%
    pause
    exit /b 1
)

echo [OK] Redis extracted to: %INSTALL_DIR%
echo.

REM Add to PATH (optional)
echo Do you want to add Redis to your PATH? (y/n)
set /p "ADD_PATH="
if /i "!ADD_PATH!"=="y" (
    setx PATH "%PATH%;%INSTALL_DIR%" >nul 2>&1
    echo [OK] Redis added to PATH
    echo Please restart your command prompt for PATH changes to take effect
    echo.
)

:configure
echo Configuring Redis for Discador v2.0...
echo.

REM Create Redis configuration file
set "REDIS_CONF=%INSTALL_DIR%\discador.conf"

echo # Redis Configuration for Discador v2.0 > "%REDIS_CONF%"
echo port 6379 >> "%REDIS_CONF%"
echo bind 127.0.0.1 >> "%REDIS_CONF%"
echo timeout 300 >> "%REDIS_CONF%"
echo tcp-keepalive 300 >> "%REDIS_CONF%"
echo databases 16 >> "%REDIS_CONF%"
echo save 900 1 >> "%REDIS_CONF%"
echo save 300 10 >> "%REDIS_CONF%"
echo save 60 10000 >> "%REDIS_CONF%"
echo maxmemory-policy allkeys-lru >> "%REDIS_CONF%"
echo appendonly yes >> "%REDIS_CONF%"
echo appendfsync everysec >> "%REDIS_CONF%"
echo dir "%INSTALL_DIR%\data" >> "%REDIS_CONF%"
echo logfile "%INSTALL_DIR%\redis.log" >> "%REDIS_CONF%"
echo loglevel notice >> "%REDIS_CONF%"

REM Create data directory
if not exist "%INSTALL_DIR%\data" mkdir "%INSTALL_DIR%\data"

echo [OK] Redis configuration created: %REDIS_CONF%
echo.

REM Create Redis service scripts
echo Creating Redis service scripts...

REM Start Redis script
echo @echo off > "%INSTALL_DIR%\start_redis.bat"
echo cd /d "%INSTALL_DIR%" >> "%INSTALL_DIR%\start_redis.bat"
echo echo Starting Redis server... >> "%INSTALL_DIR%\start_redis.bat"
echo redis-server.exe discador.conf >> "%INSTALL_DIR%\start_redis.bat"

REM Stop Redis script  
echo @echo off > "%INSTALL_DIR%\stop_redis.bat"
echo echo Stopping Redis server... >> "%INSTALL_DIR%\stop_redis.bat"
echo taskkill /F /IM redis-server.exe >nul 2>&1 >> "%INSTALL_DIR%\stop_redis.bat"
echo echo Redis server stopped >> "%INSTALL_DIR%\stop_redis.bat"
echo pause >> "%INSTALL_DIR%\stop_redis.bat"

REM Redis CLI script
echo @echo off > "%INSTALL_DIR%\redis_cli.bat"
echo cd /d "%INSTALL_DIR%" >> "%INSTALL_DIR%\redis_cli.bat"
echo redis-cli.exe >> "%INSTALL_DIR%\redis_cli.bat"

echo [OK] Service scripts created
echo.

REM Test Redis installation
echo Testing Redis installation...
echo.

REM Start Redis in background for testing
echo Starting Redis for testing...
start "Redis Server" /min "%INSTALL_DIR%\redis-server.exe" "%REDIS_CONF%"

REM Wait for Redis to start
timeout /t 3 >nul

REM Test connection
echo Testing Redis connection...
echo ping | "%INSTALL_DIR%\redis-cli.exe" >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Redis is running and responding to ping
) else (
    echo [WARNING] Redis test failed
    echo Please check the Redis log: %INSTALL_DIR%\redis.log
)

REM Stop test Redis
taskkill /F /IM redis-server.exe >nul 2>&1

echo.
echo ========================================
echo   Redis Installation Complete
echo ========================================
echo.
echo Installation directory: %INSTALL_DIR%
echo Configuration file: %REDIS_CONF%
echo.
echo Available scripts:
echo   - %INSTALL_DIR%\start_redis.bat  : Start Redis server
echo   - %INSTALL_DIR%\stop_redis.bat   : Stop Redis server
echo   - %INSTALL_DIR%\redis_cli.bat    : Redis command line
echo.
echo To start Redis manually:
echo   cd "%INSTALL_DIR%"
echo   redis-server.exe discador.conf
echo.
echo Redis is now ready for Discador v2.0!
echo.

REM Create desktop shortcut for Redis
echo Creating desktop shortcut...
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%TEMP%\redis_shortcut.vbs"
echo sLinkFile = "%USERPROFILE%\Desktop\Start Redis.lnk" >> "%TEMP%\redis_shortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%TEMP%\redis_shortcut.vbs"
echo oLink.TargetPath = "%INSTALL_DIR%\start_redis.bat" >> "%TEMP%\redis_shortcut.vbs"
echo oLink.IconLocation = "%SystemRoot%\System32\shell32.dll,13" >> "%TEMP%\redis_shortcut.vbs"
echo oLink.Save >> "%TEMP%\redis_shortcut.vbs"

cscript //nologo "%TEMP%\redis_shortcut.vbs" >nul 2>&1
del "%TEMP%\redis_shortcut.vbs" >nul 2>&1

echo Desktop shortcut created: "Start Redis"
echo.
pause
