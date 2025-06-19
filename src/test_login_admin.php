<?php
require_once 'config/config.php';
require_once 'classes/Auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$result = $auth->login('admin', 'admin123');

echo "<h2>Teste de Login Admin</h2>";
if ($result['success']) {
    echo "✅ <strong>Login realizado com sucesso!</strong><br>";
    echo "👤 Usuário: " . $result['user']['username'] . "<br>";
    echo "📧 Email: " . $result['user']['email'] . "<br>";
    echo "🔑 Permissões: " . implode(', ', $result['user']['permissions']) . "<br>";
    echo "<br><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>🏠 Ir para Dashboard</a>";
} else {
    echo "❌ Falha no login: " . $result['message'];
}
?>
