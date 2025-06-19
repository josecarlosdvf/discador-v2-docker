# Script PowerShell para instalar schema multi-tenant
Write-Host "=== INSTALAÇÃO MULTI-TENANT DISCADOR V2 ===" -ForegroundColor Cyan
Write-Host ""

# Verificar se MySQL está acessível
try {
    $mysqlVersion = mysql --version
    Write-Host "MySQL encontrado: $mysqlVersion" -ForegroundColor Green
} catch {
    Write-Host "ERRO: MySQL não encontrado no PATH" -ForegroundColor Red
    Write-Host "Instale o MySQL ou adicione ao PATH do sistema" -ForegroundColor Yellow
    Read-Host "Pressione Enter para sair"
    exit 1
}

Write-Host ""
Write-Host "Conectando ao MySQL..." -ForegroundColor Yellow

# Parâmetros de conexão
$dbHost = "localhost"
$dbUser = "root"
$dbName = "discador_v2"
$sqlFile = "scripts\sql\02_multi_tenant_schema.sql"

# Solicitar senha do MySQL
$password = Read-Host "Digite a senha do MySQL (ou pressione Enter se não houver senha)" -AsSecureString
$plaintextPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))

Write-Host ""
Write-Host "Criando banco de dados se não existir..." -ForegroundColor Yellow

# Criar banco se não existir
if ($plaintextPassword -eq "") {
    $createDbCmd = "mysql -u $dbUser -h $dbHost -e `"CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`""
} else {
    $createDbCmd = "mysql -u $dbUser -p$plaintextPassword -h $dbHost -e `"CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`""
}

try {
    Invoke-Expression $createDbCmd
    Write-Host "Banco de dados verificado/criado com sucesso!" -ForegroundColor Green
} catch {
    Write-Host "ERRO ao criar banco de dados: $_" -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}

Write-Host ""
Write-Host "Executando script SQL multi-tenant..." -ForegroundColor Yellow

# Executar o script SQL
if ($plaintextPassword -eq "") {
    $sqlCmd = "mysql -u $dbUser -h $dbHost $dbName"
} else {
    $sqlCmd = "mysql -u $dbUser -p$plaintextPassword -h $dbHost $dbName"
}

try {
    Get-Content $sqlFile | & cmd /c "$sqlCmd"
    
    Write-Host ""
    Write-Host "============================================" -ForegroundColor Green
    Write-Host "SUCESSO: Schema multi-tenant instalado!" -ForegroundColor Green
    Write-Host "============================================" -ForegroundColor Green
    Write-Host ""
    
    Write-Host "PRÓXIMOS PASSOS:" -ForegroundColor Cyan
    Write-Host "1. Acesse: http://localhost/discador_v2/src/login.php?type=admin" -ForegroundColor White
    Write-Host "2. Login Admin Global:" -ForegroundColor White
    Write-Host "   Email: admin@discador.com" -ForegroundColor Yellow
    Write-Host "   Senha: password" -ForegroundColor Yellow
    Write-Host "3. Gerencie empresas em: http://localhost/discador_v2/src/admin-companies.php" -ForegroundColor White
    Write-Host "4. Cadastre nova empresa em: http://localhost/discador_v2/src/register-company.php" -ForegroundColor White
    Write-Host ""
    
} catch {
    Write-Host ""
    Write-Host "============================================" -ForegroundColor Red
    Write-Host "ERRO: Falha ao executar o script SQL" -ForegroundColor Red
    Write-Host "============================================" -ForegroundColor Red
    Write-Host "Erro: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "VERIFIQUE:" -ForegroundColor Yellow
    Write-Host "1. Se o MySQL está rodando" -ForegroundColor White
    Write-Host "2. Se as credenciais estão corretas" -ForegroundColor White
    Write-Host "3. Se o arquivo SQL existe: $sqlFile" -ForegroundColor White
}

Write-Host ""
Read-Host "Pressione Enter para continuar"
