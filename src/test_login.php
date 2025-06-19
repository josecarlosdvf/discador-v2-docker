<?php
/**
 * Teste de Login Direto
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';

// Configurar sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Teste de Login - Sistema Discador v2.0</h1>";

try {
    $auth = new Auth();
    
    // Testar login com credenciais admin
    echo "<h2>Testando Login com Admin</h2>";
    $result = $auth->login('admin', 'admin123');
    
    if ($result['success']) {
        echo "✅ Login realizado com sucesso!<br>";
        echo "- Usuário: " . $result['user']['username'] . "<br>";
        echo "- Email: " . $result['user']['email'] . "<br>";
        echo "- Permissões: " . implode(', ', $result['user']['permissions']) . "<br>";
        
        // Verificar se está autenticado
        echo "<h2>Verificando Status de Autenticação</h2>";
        if ($auth->isAuthenticated()) {
            echo "✅ Usuário está autenticado<br>";
            
            $currentUser = $auth->getCurrentUser();
            if ($currentUser) {
                echo "- Usuário atual: " . $currentUser['username'] . "<br>";
            }
            
            // Testar algumas permissões
            echo "<h2>Testando Permissões</h2>";
            $permissions = ['admin', 'ramais', 'filas', 'inexistente'];
            foreach ($permissions as $perm) {
                $hasPermission = $auth->hasPermission($perm);
                echo ($hasPermission ? "✅" : "❌") . " Permissão '$perm': " . ($hasPermission ? "OK" : "NEGADA") . "<br>";
            }
            
        } else {
            echo "❌ Usuário não está autenticado após login<br>";
        }
        
    } else {
        echo "❌ Login falhou: " . $result['message'] . "<br>";
        echo "- Código: " . ($result['code'] ?? 'N/A') . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Erro no Teste</h1>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}

echo "<br><a href='login.php'>← Voltar para Login</a>";
echo " | <a href='index.php'>Dashboard →</a>";
?>
