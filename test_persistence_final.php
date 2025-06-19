<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4', 'root', 'root123');
      $test_value = 'teste_' . time();
    $pdo->exec("INSERT INTO activity_logs (usuario, action, details, created_at) VALUES ('sistema', 'teste_persistencia', '$test_value', NOW())");
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE details = ?");
    $stmt->execute([$test_value]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "✅ Persistência: Funcionando perfeitamente!\n";
        $pdo->prepare("DELETE FROM activity_logs WHERE details = ?")->execute([$test_value]);
        echo "✅ Limpeza: Concluída\n";
    } else {
        echo "❌ Persistência: Falhou\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
