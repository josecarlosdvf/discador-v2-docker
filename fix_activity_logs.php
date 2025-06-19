<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4', 'root', 'root123');
    $pdo->exec("ALTER TABLE activity_logs ADD COLUMN usuario varchar(100) DEFAULT 'sistema'");
    echo "✅ Tabela activity_logs corrigida\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
