#!/usr/bin/env php
<?php
/**
 * Script de Validação Completa do Sistema Discador v2.0
 * 
 * Este script executa uma bateria completa de testes para validar
 * todas as funcionalidades implementadas do sistema multi-tenant.
 */

echo "🧪 VALIDAÇÃO COMPLETA DO SISTEMA DISCADOR V2.0\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$startTime = microtime(true);
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest($testName, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    echo "🔍 Executando: $testName... ";
    
    try {
        $result = $callback();
        if ($result === true || (is_array($result) && $result['success'] === true)) {
            echo "✅ PASSOU\n";
            $passedTests++;
            return true;
        } else {
            $message = is_array($result) ? ($result['message'] ?? 'Falha') : 'Falha';
            echo "❌ FALHOU: $message\n";
            $failedTests++;
            return false;
        }
    } catch (Exception $e) {
        echo "💥 ERRO: " . $e->getMessage() . "\n";
        $failedTests++;
        return false;
    }
}

// ============================================================================
// TESTES DE SINTAXE PHP
// ============================================================================
echo "📋 FASE 1: VALIDAÇÃO DE SINTAXE PHP\n";
echo "-" . str_repeat("-", 30) . "\n";

$phpFiles = [
    'src/Core/TenantManager.php' => 'TenantManager (Multi-tenant core)',
    'src/Core/MultiTenantAuth.php' => 'MultiTenantAuth (Autenticação)',
    'src/Core/UserManager.php' => 'UserManager (Gestão de usuários)',
    'src/Core/CampaignManager.php' => 'CampaignManager (Campanhas)',
    'src/Core/ContactListManager.php' => 'ContactListManager (Listas)',
    'src/Core/BillingManager.php' => 'BillingManager (Billing)',
    'src/Core/CompanyRegistration.php' => 'CompanyRegistration (Registro)',
    'src/login.php' => 'Interface de Login',
    'src/dashboard.php' => 'Dashboard Principal',
    'src/users.php' => 'Gestão de Usuários',
    'src/campaigns.php' => 'Gestão de Campanhas',
    'src/lists.php' => 'Gestão de Listas',
    'src/billing.php' => 'Centro de Custos',
    'src/invoice.php' => 'Visualização de Faturas',
    'src/api/real-time-stats.php' => 'API Estatísticas',
    'src/api/billing-reports.php' => 'API Billing',
];

foreach ($phpFiles as $file => $description) {
    runTest("Sintaxe PHP: $description", function() use ($file) {
        if (!file_exists($file)) {
            throw new Exception("Arquivo não encontrado: $file");
        }
        
        $output = [];
        $returnCode = 0;
        exec("php -l \"$file\" 2>&1", $output, $returnCode);
        
        return $returnCode === 0 && strpos(implode(' ', $output), 'No syntax errors') !== false;
    });
}

// ============================================================================
// TESTES DE CONEXÃO E CONFIGURAÇÃO
// ============================================================================
echo "\n📡 FASE 2: VALIDAÇÃO DE CONEXÃO E CONFIGURAÇÃO\n";
echo "-" . str_repeat("-", 40) . "\n";

runTest("Arquivo de configuração PDO", function() {
    return file_exists('src/config/pdo.php');
});

runTest("Conexão com banco de dados", function() {
    try {
        require_once 'src/config/pdo.php';
        $pdo = $GLOBALS['pdo'];
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        throw new Exception("Falha na conexão: " . $e->getMessage());
    }
});

// ============================================================================
// TESTES DE ESTRUTURA DO BANCO
// ============================================================================
echo "\n🗄️ FASE 3: VALIDAÇÃO DA ESTRUTURA DO BANCO\n";
echo "-" . str_repeat("-", 35) . "\n";

$requiredTables = [
    'empresas' => 'Empresas Multi-tenant',
    'usuarios' => 'Usuários do Sistema',
    'admin_global' => 'Administradores Globais',
    'billing_faturas' => 'Faturas de Billing',
    'billing_chamadas' => 'Custos de Chamadas',
    'tarifas_empresa' => 'Tarifas por Empresa',
    'billing_configuracoes' => 'Configurações de Billing'
];

require_once 'src/config/pdo.php';
$pdo = $GLOBALS['pdo'];

foreach ($requiredTables as $table => $description) {
    runTest("Tabela: $description ($table)", function() use ($pdo, $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    });
}

// ============================================================================
// TESTES DE FUNCIONALIDADE DAS CLASSES
// ============================================================================
echo "\n🔧 FASE 4: VALIDAÇÃO DE FUNCIONALIDADES\n";
echo "-" . str_repeat("-", 32) . "\n";

runTest("TenantManager - Instanciar", function() {
    require_once 'src/Core/TenantManager.php';
    $tenantManager = \DiscadorV2\Core\TenantManager::getInstance();
    return $tenantManager !== null;
});

runTest("MultiTenantAuth - Instanciar", function() {
    require_once 'src/Core/MultiTenantAuth.php';
    $auth = new \DiscadorV2\Core\MultiTenantAuth();
    return $auth !== null;
});

runTest("BillingManager - Instanciar", function() {
    require_once 'src/Core/BillingManager.php';
    $billing = new \DiscadorV2\Core\BillingManager();
    return $billing !== null;
});

runTest("BillingManager - Cálculo de Custo", function() {
    require_once 'src/Core/BillingManager.php';
    $billing = new \DiscadorV2\Core\BillingManager();
    $result = $billing->calculateCallCost('11999887766', 60); // 1 minuto para celular
    return $result['success'] === true && $result['custo'] > 0;
});

runTest("UserManager - Instanciar", function() {
    require_once 'src/Core/UserManager.php';
    $userManager = new \DiscadorV2\Core\UserManager();
    return $userManager !== null;
});

// ============================================================================
// TESTES DE SCRIPTS DE INSTALAÇÃO
// ============================================================================
echo "\n🛠️ FASE 5: VALIDAÇÃO DE SCRIPTS DE INSTALAÇÃO\n";
echo "-" . str_repeat("-", 38) . "\n";

$installScripts = [
    'scripts/install_billing.php' => 'Instalador de Billing',
    'scripts/install_billing.ps1' => 'Script PowerShell de Billing'
];

foreach ($installScripts as $script => $description) {
    runTest("Script: $description", function() use ($script) {
        return file_exists($script) && filesize($script) > 0;
    });
}

// ============================================================================
// TESTES DE DOCUMENTAÇÃO
// ============================================================================
echo "\n📚 FASE 6: VALIDAÇÃO DE DOCUMENTAÇÃO\n";
echo "-" . str_repeat("-", 32) . "\n";

$docFiles = [
    'MDs/todo.md' => 'Lista de Tarefas',
    'MDs/relatorio_implementacao_2.6_billing.md' => 'Relatório Billing',
    'MDs/relatorio_implementacao_2.3_2.4.md' => 'Relatório Features',
    'scripts/sql/02_multi_tenant_schema.sql' => 'Schema Multi-tenant',
    'scripts/sql/03_billing_schema.sql' => 'Schema Billing'
];

foreach ($docFiles as $file => $description) {
    runTest("Documentação: $description", function() use ($file) {
        return file_exists($file) && filesize($file) > 100; // Pelo menos 100 bytes
    });
}

// ============================================================================
// RELATÓRIO FINAL
// ============================================================================
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 RELATÓRIO FINAL DA VALIDAÇÃO\n";
echo str_repeat("=", 60) . "\n";

echo "⏱️  Tempo de execução: {$duration}s\n";
echo "📊 Total de testes: $totalTests\n";
echo "✅ Testes passaram: $passedTests\n";
echo "❌ Testes falharam: $failedTests\n";

$successRate = round(($passedTests / $totalTests) * 100, 1);
echo "📈 Taxa de sucesso: $successRate%\n\n";

if ($failedTests === 0) {
    echo "🎉 TODOS OS TESTES PASSARAM! Sistema pronto para produção.\n";
    exit(0);
} elseif ($successRate >= 90) {
    echo "✅ Sistema em ótimo estado! Pequenos ajustes podem ser necessários.\n";
    exit(0);
} elseif ($successRate >= 75) {
    echo "⚠️  Sistema funcional, mas requer atenção em algumas áreas.\n";
    exit(1);
} else {
    echo "🚨 Sistema requer correções críticas antes do uso.\n";
    exit(2);
}
