<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4', 'root', 'root123');
    
    // Teste simples com a tabela configuracoes
    $test_value = 'teste_persistencia_' . time();
    $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('teste_persistencia', '$test_value') ON DUPLICATE KEY UPDATE valor = '$test_value'");
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'teste_persistencia'");
    $stmt->execute();
    $result = $stmt->fetchColumn();
    
    if ($result === $test_value) {
        echo "✅ Persistência: Funcionando perfeitamente!\n";
        $pdo->exec("DELETE FROM configuracoes WHERE chave = 'teste_persistencia'");
        echo "✅ Limpeza: Concluída\n";
    } else {
        echo "❌ Persistência: Falhou\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
