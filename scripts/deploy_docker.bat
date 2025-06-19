@echo off
REM Deploy Script for Discador v2.0 with Docker
REM This script sets up the complete Discador v2.0 environment using Docker

setlocal EnableDelayedExpansion

echo ========================================
echo   Discador v2.0 - Docker Deploy Script
echo ========================================
echo.

REM Get script directory
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."

echo Project Root: %PROJECT_ROOT%
echo.

REM Check if Docker is available
docker --version >nul 2>&1
if !errorlevel! neq 0 (
    echo [ERROR] Docker not found
    echo Please install Docker Desktop for Windows
    echo Download from: https://docs.docker.com/desktop/windows/install/
    pause
    exit /b 1
)

echo [OK] Docker found
docker --version
echo.

REM Check if Docker Compose is available
docker compose version >nul 2>&1
if !errorlevel! neq 0 (
    echo [ERROR] Docker Compose not found
    echo Please ensure Docker Desktop includes Docker Compose
    pause
    exit /b 1
)

echo [OK] Docker Compose found
docker compose version
echo.

REM Check if Docker is running
docker info >nul 2>&1
if !errorlevel! neq 0 (
    echo [ERROR] Docker is not running
    echo Please start Docker Desktop
    pause
    exit /b 1
)

echo [OK] Docker is running
echo.

REM Change to project directory
cd /d "%PROJECT_ROOT%"

REM Check if .env file exists
if not exist ".env" (
    echo Creating .env file from template...
    if exist ".env.example" (
        copy ".env.example" ".env" >nul
        echo [OK] .env file created from template
        echo [WARNING] Please edit .env file and set your passwords!
        echo.
    ) else (
        echo [ERROR] .env.example not found
        echo Please create .env file manually
        pause
        exit /b 1
    )
) else (
    echo [OK] .env file already exists
    echo.
)

REM Create necessary directories
echo Creating directory structure...
if not exist "logs" mkdir "logs"
if not exist "logs\php" mkdir "logs\php"
if not exist "logs\nginx" mkdir "logs\nginx"
if not exist "logs\asterisk" mkdir "logs\asterisk"
if not exist "backup" mkdir "backup"
if not exist "tmp" mkdir "tmp"
if not exist "data" mkdir "data"

echo [OK] Directories created
echo.

REM Stop any existing containers
echo Stopping existing containers...
docker compose down >nul 2>&1
echo [OK] Existing containers stopped
echo.

REM Build and start containers
echo Building and starting Docker containers...
echo This may take several minutes on first run...
echo.

docker compose up -d --build
if !errorlevel! neq 0 (
    echo [ERROR] Failed to start containers
    echo Please check the error messages above
    pause
    exit /b 1
)

echo [OK] Containers started successfully
echo.

REM Wait for services to be ready
echo Waiting for services to be ready...
timeout /t 10 >nul

REM Check container status
echo Checking container status...
docker compose ps

echo.
echo Testing services...

REM Test database connection
echo Testing database connection...
docker compose exec -T php php -r "
try {
    \$pdo = new PDO('mysql:host=database;dbname=' . (\$_ENV['DB_NAME'] ?? 'discador'), \$_ENV['DB_USER'] ?? 'discador', \$_ENV['DB_PASSWORD'] ?? 'discador123');
    echo 'Database: OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database: FAILED - ' . \$e->getMessage() . PHP_EOL;
}
"

REM Test Redis connection
echo Testing Redis connection...
docker compose exec -T php php -r "
try {
    if (class_exists('Redis')) {
        \$redis = new Redis();
        \$redis->connect('redis', 6379);
        if (!empty(\$_ENV['REDIS_PASSWORD'])) {
            \$redis->auth(\$_ENV['REDIS_PASSWORD']);
        }
        \$redis->ping();
        echo 'Redis: OK' . PHP_EOL;
    } else {
        echo 'Redis: Extension not found' . PHP_EOL;
    }
} catch (Exception \$e) {
    echo 'Redis: FAILED - ' . \$e->getMessage() . PHP_EOL;
}
"

echo.

REM Configure Redis for Discador
echo Configuring Redis for Discador v2.0...
docker compose exec -T php php /var/www/html/../scripts/redis_config.php configure
echo.

REM Create service scripts for Docker environment
echo Creating service scripts...

REM Start script
echo @echo off > "%PROJECT_ROOT%\start_discador_docker.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo echo Starting Discador v2.0 with Docker... >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo docker compose up -d >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo echo. >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo echo Waiting for services... >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo timeout /t 10 ^>nul >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo echo. >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo echo Starting Discador Master Process... >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo docker compose exec php php /var/www/html/../scripts/discador_control.php start >> "%PROJECT_ROOT%\start_discador_docker.bat"
echo pause >> "%PROJECT_ROOT%\start_discador_docker.bat"

REM Stop script
echo @echo off > "%PROJECT_ROOT%\stop_discador_docker.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo echo Stopping Discador Master Process... >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo docker compose exec php php /var/www/html/../scripts/discador_control.php stop >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo echo. >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo echo Stopping Docker containers... >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo docker compose down >> "%PROJECT_ROOT%\stop_discador_docker.bat"
echo pause >> "%PROJECT_ROOT%\stop_discador_docker.bat"

REM Status script
echo @echo off > "%PROJECT_ROOT%\status_discador_docker.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo echo Docker Containers Status: >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo docker compose ps >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo echo. >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo echo Discador System Status: >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo docker compose exec php php /var/www/html/../scripts/discador_control.php status >> "%PROJECT_ROOT%\status_discador_docker.bat"
echo pause >> "%PROJECT_ROOT%\status_discador_docker.bat"

REM Monitor script
echo @echo off > "%PROJECT_ROOT%\monitor_discador_docker.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\monitor_discador_docker.bat"
echo docker compose exec php php /var/www/html/../scripts/discador_monitor.php >> "%PROJECT_ROOT%\monitor_discador_docker.bat"
echo pause >> "%PROJECT_ROOT%\monitor_discador_docker.bat"

REM Logs script
echo @echo off > "%PROJECT_ROOT%\logs_discador_docker.bat"
echo cd /d "%PROJECT_ROOT%" >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo Choose log to view: >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo 1. PHP Application >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo 2. Nginx Web Server >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo 3. MariaDB Database >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo 4. Redis Cache >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo echo 5. All containers >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo set /p choice="Enter choice (1-5): " >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo if "!choice!"=="1" docker compose logs -f php >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo if "!choice!"=="2" docker compose logs -f nginx >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo if "!choice!"=="3" docker compose logs -f database >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo if "!choice!"=="4" docker compose logs -f redis >> "%PROJECT_ROOT%\logs_discador_docker.bat"
echo if "!choice!"=="5" docker compose logs -f >> "%PROJECT_ROOT%\logs_discador_docker.bat"

echo [OK] Service scripts created
echo.

REM Create desktop shortcuts
echo Creating desktop shortcuts...

REM Create VBS script for shortcuts (no console window)
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%PROJECT_ROOT%\create_shortcut.vbs"
echo sLinkFile = "%USERPROFILE%\Desktop\Discador v2.0 Docker - Start.lnk" >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%PROJECT_ROOT%\create_shortcut.vbs"
echo oLink.TargetPath = "%PROJECT_ROOT%\start_discador_docker.bat" >> "%PROJECT_ROOT%\create_shortcut.vbs"
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

REM Get container ports
for /f "tokens=2 delims=:" %%a in ('docker compose port nginx 80 2^>nul') do set "WEB_PORT=%%a"
for /f "tokens=2 delims=:" %%a in ('docker compose port nginx 443 2^>nul') do set "SSL_PORT=%%a"

if not defined WEB_PORT set "WEB_PORT=8080"
if not defined SSL_PORT set "SSL_PORT=8443"

echo [✓] Docker containers running
echo [✓] Services configured
echo [✓] Directory structure created
echo [✓] Service scripts created
echo [✓] Desktop shortcuts created
echo [✓] Redis configured for Discador v2.0
echo.

echo ========================================
echo   Access Information
echo ========================================
echo.
echo Web Interface:
echo   HTTP:  http://localhost:%WEB_PORT%
echo   HTTPS: https://localhost:%SSL_PORT% (if SSL configured)
echo.
echo Management Dashboard:
echo   http://localhost:%WEB_PORT%/monitor-dashboard.php
echo.
echo Docker Management:
echo   - start_discador_docker.bat     : Start system
echo   - stop_discador_docker.bat      : Stop system  
echo   - status_discador_docker.bat    : Check status
echo   - monitor_discador_docker.bat   : Monitor dashboard
echo   - logs_discador_docker.bat      : View logs
echo.

echo Container URLs (internal):
echo   - PHP App:    php:9000
echo   - Database:   database:3306
echo   - Redis:      redis:6379
echo   - Asterisk:   asterisk:5060
echo.

echo Docker Commands:
echo   docker compose up -d           : Start all services
echo   docker compose down            : Stop all services
echo   docker compose ps              : Check container status
echo   docker compose logs -f         : View all logs
echo   docker compose exec php bash   : Access PHP container
echo.

echo Next steps:
echo 1. Edit .env file with your specific passwords
echo 2. Access web interface at http://localhost:%WEB_PORT%
echo 3. Configure Asterisk connection in the admin panel
echo 4. Create your first campaign
echo.

if exist "%USERPROFILE%\Desktop\Discador v2.0 Docker - Start.lnk" (
    echo Desktop shortcut created: "Discador v2.0 Docker - Start"
    echo.
)

echo Deploy completed successfully!
echo The system is now running in Docker containers.
echo.
pause
