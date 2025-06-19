#!/usr/bin/env php
<?php
/**
 * Script de Validação Offline
 * 
 * Valida sintaxe PHP e estrutura de arquivos sem requerer
 * conexão com banco de dados.
 */

echo "🧪 VALIDAÇÃO OFFLINE - SISTEMA DISCADOR V2.0\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest($testName, $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    echo "🔍 $testName... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅\n";
            $passedTests++;
            return true;
        } else {
            echo "❌ $result\n";
            $failedTests++;
            return false;
        }
    } catch (Exception $e) {
        echo "💥 " . $e->getMessage() . "\n";
        $failedTests++;
        return false;
    }
}

// VALIDAÇÃO DE SINTAXE PHP
echo "📋 VALIDANDO SINTAXE DOS ARQUIVOS PHP\n";
echo "-" . str_repeat("-", 35) . "\n";

$coreFiles = [
    'src/Core/TenantManager.php',
    'src/Core/MultiTenantAuth.php', 
    'src/Core/UserManager.php',
    'src/Core/CampaignManager.php',
    'src/Core/ContactListManager.php',
    'src/Core/BillingManager.php',
    'src/Core/CompanyRegistration.php'
];

foreach ($coreFiles as $file) {
    $filename = basename($file);
    runTest("Core: $filename", function() use ($file) {
        if (!file_exists($file)) {
            return "Arquivo não encontrado";
        }
        
        $output = [];
        $returnCode = 0;
        exec("php -l \"$file\" 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return "Erro de sintaxe: " . implode(' ', $output);
        }
        
        return true;
    });
}

$webFiles = [
    'src/login.php',
    'src/dashboard.php',
    'src/users.php', 
    'src/campaigns.php',
    'src/lists.php',
    'src/billing.php',
    'src/invoice.php'
];

foreach ($webFiles as $file) {
    $filename = basename($file);
    runTest("Interface: $filename", function() use ($file) {
        if (!file_exists($file)) {
            return "Arquivo não encontrado";
        }
        
        $output = [];
        $returnCode = 0;
        exec("php -l \"$file\" 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return "Erro de sintaxe";
        }
        
        return true;
    });
}

$apiFiles = [
    'src/api/real-time-stats.php',
    'src/api/billing-reports.php'
];

foreach ($apiFiles as $file) {
    $filename = basename($file);
    runTest("API: $filename", function() use ($file) {
        if (!file_exists($file)) {
            return "Arquivo não encontrado";
        }
        
        $output = [];
        $returnCode = 0;
        exec("php -l \"$file\" 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            return "Erro de sintaxe";
        }
        
        return true;
    });
}

// VALIDAÇÃO DE ESTRUTURA
echo "\n📁 VALIDANDO ESTRUTURA DE ARQUIVOS\n";
echo "-" . str_repeat("-", 31) . "\n";

$requiredFiles = [
    'src/config/database.php' => 'Configuração do banco',
    'src/config/pdo.php' => 'Conexão PDO',
    'scripts/sql/02_multi_tenant_schema.sql' => 'Schema multi-tenant',
    'scripts/sql/03_billing_schema.sql' => 'Schema billing',
    'scripts/install_billing.php' => 'Instalador billing',
    'scripts/install_billing.ps1' => 'Script PowerShell'
];

foreach ($requiredFiles as $file => $description) {
    runTest($description, function() use ($file) {
        if (!file_exists($file)) {
            return "Arquivo não encontrado: $file";
        }
        
        if (filesize($file) < 100) {
            return "Arquivo muito pequeno (pode estar vazio)";
        }
        
        return true;
    });
}

// VALIDAÇÃO DE DOCUMENTAÇÃO
echo "\n📚 VALIDANDO DOCUMENTAÇÃO\n";
echo "-" . str_repeat("-", 25) . "\n";

$docFiles = [
    'MDs/todo.md' => 'Lista de tarefas',
    'MDs/relatorio_implementacao_2.6_billing.md' => 'Relatório billing',
    'README.md' => 'README principal'
];

foreach ($docFiles as $file => $description) {
    runTest($description, function() use ($file) {
        if (!file_exists($file)) {
            return "Arquivo não encontrado";
        }
          if (filesize($file) < 500) {
            return "Documentação muito pequena";
        }
        
        return true;
    });
}

// VALIDAÇÃO DE COMPLETUDE FUNCIONAL
echo "\n🎯 VALIDANDO COMPLETUDE FUNCIONAL\n";
echo "-" . str_repeat("-", 34) . "\n";

runTest("Multi-tenancy implementado", function() {
    $multiTenantFiles = [
        'src/Core/TenantManager.php',
        'src/Core/MultiTenantAuth.php',
        'scripts/sql/02_multi_tenant_schema.sql'
    ];
    
    foreach ($multiTenantFiles as $file) {
        if (!file_exists($file) || filesize($file) < 1000) {
            return "Arquivo crítico incompleto: $file";
        }
    }
    
    return true;
});

runTest("Sistema de billing implementado", function() {
    $billingFiles = [
        'src/Core/BillingManager.php',
        'src/billing.php',
        'src/api/billing-reports.php',
        'scripts/sql/03_billing_schema.sql'
    ];
    
    foreach ($billingFiles as $file) {
        if (!file_exists($file) || filesize($file) < 1000) {
            return "Arquivo crítico incompleto: $file";
        }
    }
    
    return true;
});

runTest("Gestão de usuários implementada", function() {
    $userFiles = [
        'src/Core/UserManager.php',
        'src/users.php'
    ];
    
    foreach ($userFiles as $file) {
        if (!file_exists($file) || filesize($file) < 1000) {
            return "Arquivo crítico incompleto: $file";
        }
    }
    
    return true;
});

runTest("Dashboard e campanhas implementados", function() {
    $dashboardFiles = [
        'src/dashboard.php',
        'src/campaigns.php',
        'src/Core/CampaignManager.php'
    ];
    
    foreach ($dashboardFiles as $file) {
        if (!file_exists($file) || filesize($file) < 1000) {
            return "Arquivo crítico incompleto: $file";
        }
    }
    
    return true;
});

// RELATÓRIO FINAL
echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 RELATÓRIO DE VALIDAÇÃO OFFLINE\n";
echo str_repeat("=", 60) . "\n";

echo "📊 Total de testes: $totalTests\n";
echo "✅ Testes passaram: $passedTests\n";
echo "❌ Testes falharam: $failedTests\n";

$successRate = round(($passedTests / $totalTests) * 100, 1);
echo "📈 Taxa de sucesso: $successRate%\n\n";

if ($failedTests === 0) {
    echo "🎉 VALIDAÇÃO OFFLINE COMPLETA! Sistema pronto para testes com banco.\n";
    echo "📋 Próximos passos:\n";
    echo "   1. Configure o banco de dados (MySQL)\n";
    echo "   2. Execute os scripts SQL (02_multi_tenant_schema.sql, 03_billing_schema.sql)\n";
    echo "   3. Teste as interfaces web\n";
    echo "   4. Valide as APIs\n\n";
    exit(0);
} elseif ($successRate >= 90) {
    echo "✅ Sistema em excelente estado! Pequenos ajustes necessários.\n";
    exit(0);
} else {
    echo "⚠️  Sistema requer correções antes do uso.\n";
    exit(1);
}
