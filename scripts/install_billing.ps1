# Script de Instalação do Billing Multi-Tenant
# Executa a instalação completa do schema de billing

Write-Host "🚀 INSTALAÇÃO DO BILLING E CENTRO DE CUSTOS" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se está no diretório correto
if (-not (Test-Path "src\Core\BillingManager.php")) {
    Write-Host "❌ Execute este script a partir do diretório raiz do projeto" -ForegroundColor Red
    exit 1
}

# Verificar se PHP está disponível
try {
    $phpVersion = php -v 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ PHP não encontrado. Certifique-se de que PHP está instalado e no PATH" -ForegroundColor Red
        exit 1
    }
    Write-Host "✅ PHP encontrado" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro ao verificar PHP: $_" -ForegroundColor Red
    exit 1
}

# Verificar conexão com banco
Write-Host "🔍 Verificando conexão com banco de dados..."
$testDb = php -f "test_db_connection.php"
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Falha na conexão com banco de dados" -ForegroundColor Red
    Write-Host "   Verifique as configurações em src/config/database.php" -ForegroundColor Yellow
    exit 1
}
Write-Host "✅ Conexão com banco OK" -ForegroundColor Green

# Executar instalação do schema
Write-Host ""
Write-Host "📦 Instalando schema de billing..." -ForegroundColor Yellow
$installResult = php -f "scripts\install_billing.php"
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Falha na instalação do schema" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎯 INSTALAÇÃO CONCLUÍDA COM SUCESSO!" -ForegroundColor Green
Write-Host "====================================" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Próximos passos:" -ForegroundColor Cyan
Write-Host "   1. Acesse o painel de billing: http://localhost/src/billing.php" -ForegroundColor White
Write-Host "   2. Configure tarifas personalizadas por empresa" -ForegroundColor White
Write-Host "   3. Teste as APIs: http://localhost/src/api/billing-reports.php" -ForegroundColor White
Write-Host "   4. Configure alertas automáticos" -ForegroundColor White
Write-Host ""
Write-Host "🔧 Comandos úteis:" -ForegroundColor Cyan
Write-Host "   - Processar custos: POST /src/api/billing-reports.php?action=process_costs" -ForegroundColor White
Write-Host "   - Gerar fatura: POST /src/api/billing-reports.php?action=generate_invoice" -ForegroundColor White
Write-Host "   - Ver estatísticas: GET /src/api/billing-reports.php?action=company_stats" -ForegroundColor White
Write-Host ""

# Opção de teste rápido
$teste = Read-Host "Deseja executar um teste rápido das APIs? (y/N)"
if ($teste.ToLower() -eq "y") {
    Write-Host ""
    Write-Host "🧪 Executando testes básicos..." -ForegroundColor Yellow
    
    # Teste básico da API
    try {
        $testResult = Invoke-WebRequest -Uri "http://localhost/src/api/billing-reports.php?action=stats" -Method GET -ErrorAction Stop
        if ($testResult.StatusCode -eq 200) {
            Write-Host "✅ API de billing respondendo" -ForegroundColor Green
        } else {
            Write-Host "⚠️  API retornou status: $($testResult.StatusCode)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "❌ Erro ao testar API: $_" -ForegroundColor Red
        Write-Host "   Certifique-se de que o servidor web está rodando" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "✨ Sistema de Billing Multi-Tenant está pronto para uso!" -ForegroundColor Green
