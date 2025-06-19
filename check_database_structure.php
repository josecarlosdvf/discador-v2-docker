<?php
/**
 * Verificação da Estrutura do Banco - Sistema Discador v2.0
 */

echo "🔍 Verificando estrutura atual do banco...\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4',
        'root',
        'root123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Conectado ao banco 'discador'\n\n";
    
    // Listar todas as tabelas
    echo "📊 Tabelas existentes:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tabelas)) {
        echo "❌ Nenhuma tabela encontrada!\n";
    } else {
        foreach ($tabelas as $tabela) {
            echo "✅ $tabela\n";
        }
    }
    
    echo "\n📋 Detalhes das tabelas principais:\n";
    
    $tabelas_verificar = ['usuarios', 'empresas', 'campanhas', 'admin_global'];
    
    foreach ($tabelas_verificar as $tabela) {
        echo "\n--- Tabela: $tabela ---\n";
        
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            // Mostrar estrutura
            echo "Estrutura:\n";
            $stmt = $pdo->query("DESCRIBE `$tabela`");
            $colunas = $stmt->fetchAll();
            
            foreach ($colunas as $coluna) {
                echo "  {$coluna['Field']} - {$coluna['Type']} ({$coluna['Key']})\n";
            }
            
            // Contar registros
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$tabela`");
            $count = $stmt->fetchColumn();
            echo "Registros: $count\n";
            
        } else {
            echo "❌ Tabela não existe\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
