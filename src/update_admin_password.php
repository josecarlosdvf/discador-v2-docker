<?php
require_once 'config/config.php';

echo "<h2>Atualizando Senha do Admin</h2>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Gerar novo hash para a senha admin123
    $new_password = 'admin123';
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "Nova senha: <strong>{$new_password}</strong><br>";
    echo "Novo hash: <code>" . htmlspecialchars($new_hash) . "</code><br><br>";
    
    // Atualizar no banco de dados
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$new_hash]);
    
    if ($result) {
        echo "✅ <strong>Senha atualizada com sucesso!</strong><br><br>";
        
        // Verificar a atualização
        $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Hash armazenado: <code>" . htmlspecialchars($user['password']) . "</code><br><br>";
        
        // Testar a verificação
        $verify_result = password_verify($new_password, $user['password']);
        echo "Teste de verificação: " . ($verify_result ? "✅ SUCESSO" : "❌ FALHA") . "<br><br>";
        
        if ($verify_result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; border: 1px solid #c3e6cb;'>";
            echo "<strong>🎉 Tudo pronto!</strong><br>";
            echo "Agora você pode fazer login com:<br>";
            echo "👤 <strong>Usuário:</strong> admin<br>";
            echo "🔑 <strong>Senha:</strong> admin123<br>";
            echo "<br><a href='login.php' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>🔐 Ir para Login</a>";
            echo "</div>";
        }
        
    } else {
        echo "❌ <strong>Erro ao atualizar senha!</strong><br>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Erro:</strong> " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString() . "<br>";
}
?>
