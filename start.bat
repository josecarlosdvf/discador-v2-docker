@echo off
REM Script batch simples para inicializar o Discador v2.0

echo.
echo ================================
echo   Sistema Discador v2.0
echo ================================
echo.

REM Verificar se Docker está rodando
docker info >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERRO] Docker nao esta rodando!
    echo        Inicie o Docker Desktop e tente novamente.
    pause
    exit /b 1
)

echo [OK] Docker esta rodando

REM Verificar se .env existe
if not exist ".env" (
    echo [ERRO] Arquivo .env nao encontrado!
    echo        Copie .env.example para .env e configure.
    pause
    exit /b 1
)

echo [OK] Arquivo .env encontrado

echo.
echo Iniciando servicos...
echo.

REM Iniciar serviços
docker-compose up -d --build

if %ERRORLEVEL% equ 0 (
    echo.
    echo ================================
    echo   Sistema iniciado com sucesso!
    echo ================================
    echo.
    echo Acessos:
    echo   Web: http://localhost
    echo   Portainer: http://localhost:9000
    echo.
    echo Para parar: docker-compose down
    echo Para logs: docker-compose logs -f
    echo.
) else (
    echo.
    echo [ERRO] Falha ao iniciar servicos!
)

pause
