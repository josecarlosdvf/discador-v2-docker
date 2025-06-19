<?php
require_once 'src/config/pdo.php';

echo "🔍 Verificando conexão com banco de dados...\n";

try {
    $pdo = $GLOBALS['pdo'];
    echo "✅ MySQL conectado com sucesso usando PDO!\n";
    
    // Verificar versão do MySQL
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "📋 Versão MySQL: " . $result['version'] . "\n";
    
    // Verificar se as tabelas multi-tenant existem
    $tabelas = [
        'empresas' => 'Sistema Multi-Tenant',
        'usuarios' => 'Gestão de Usuários',
        'billing_faturas' => 'Sistema de Billing',
        'tarifas_empresa' => 'Centro de Custos'
    ];
    
    echo "\n📊 Verificando estrutura do banco:\n";
    
    foreach ($tabelas as $tabela => $descricao) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tabela]);
        
        if ($stmt->rowCount() > 0) {
            // Contar registros
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$tabela`");
            $stmt->execute();
            $result = $stmt->fetch();
            echo "✅ $descricao ($tabela): {$result['count']} registros\n";
        } else {
            echo "❌ $descricao ($tabela): TABELA NÃO EXISTE\n";
        }
    }
    
    echo "\n🎯 Teste de conexão concluído!\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    
    // Tentar criar o banco se não existir
    echo "Tentando criar o banco de dados...\n";
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Banco de dados criado/verificado com sucesso!\n";
    } catch (Exception $e2) {
        echo "Erro ao criar banco: " . $e2->getMessage() . "\n";
    }
    
    exit(1);
}
?>
