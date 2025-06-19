@echo off
echo Executando script de migracao multi-tenant...
echo.

REM Verificar se MySQL está instalado
mysql --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRO: MySQL não encontrado no PATH
    echo Instale o MySQL ou adicione ao PATH do sistema
    pause
    exit /b 1
)

echo MySQL encontrado! Conectando...
echo.

REM Executar o script SQL
echo Executando 02_multi_tenant_schema.sql...
mysql -u root -p discador_v2 < "scripts\sql\02_multi_tenant_schema.sql"

if %errorlevel% eq 0 (
    echo.
    echo ============================================
    echo SUCESSO: Schema multi-tenant criado!
    echo ============================================
    echo.
    echo Proximos passos:
    echo 1. Acesse http://localhost/discador_v2/src/login.php?type=admin
    echo 2. Use: admin@discador.com / password
    echo 3. Gerencie empresas em: http://localhost/discador_v2/src/admin-companies.php
    echo.
) else (
    echo.
    echo ============================================
    echo ERRO: Falha ao executar o script SQL
    echo ============================================
    echo Verifique:
    echo 1. Se o banco 'discador_v2' existe
    echo 2. Se as credenciais estao corretas
    echo 3. Se o MySQL esta rodando
    echo.
)

pause
