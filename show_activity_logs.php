<?php
$pdo = new PDO('mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4', 'root', 'root123');
$stmt = $pdo->query('DESCRIBE activity_logs');
echo "Estrutura da tabela activity_logs:\n";
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
