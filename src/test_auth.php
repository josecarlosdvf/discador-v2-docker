<?php
/**
 * Teste básico da autenticação
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';

// Configurar sessão para o teste
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    $auth = new Auth();
    
    echo "<h1>Teste de Autenticação - Sistema Discador v2.0</h1>";
    
    // Verificar conexão com banco
    echo "<h2>1. Teste de Conexão com Banco de Dados</h2>";
    $db = Database::getInstance();
    $connection = $db->getConnection();
    echo "✅ Conexão com banco de dados: OK<br>";
    
    // Verificar se usuário admin existe
    echo "<h2>2. Verificação do Usuário Admin</h2>";
    $stmt = $connection->prepare("SELECT id, username, email, permissions FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ Usuário admin encontrado:<br>";
        echo "- ID: {$admin['id']}<br>";
        echo "- Username: {$admin['username']}<br>";
        echo "- Email: {$admin['email']}<br>";
        echo "- Permissões: {$admin['permissions']}<br>";
    } else {
        echo "❌ Usuário admin não encontrado<br>";
    }
      // Testar validação de senha
    echo "<h2>3. Teste de Validação de Senha</h2>";
    $testPassword = 'admin123';
    $isValid = verifyPassword($testPassword, $admin['password'] ?? '');
    echo $isValid ? "✅ Validação de senha: OK" : "❌ Validação de senha: FALHOU";
    echo "<br>";
    
    // Verificar Redis (se disponível)
    echo "<h2>4. Teste de Conexão com Redis</h2>";
    try {
        $redisInstance = RedisManager::getInstance();
        $redis = $redisInstance->getConnection();
        if ($redis && $redis->ping()) {
            echo "✅ Conexão com Redis: OK<br>";
        } else {
            echo "⚠️ Redis não está disponível<br>";
        }
    } catch (Exception $e) {
        echo "⚠️ Redis não está disponível: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>5. Informações do Sistema</h2>";
    echo "- Nome: " . APP_NAME . "<br>";
    echo "- Versão: " . APP_VERSION . "<br>";
    echo "- Timezone: " . TIMEZONE . "<br>";
    echo "- Data/Hora: " . date('d/m/Y H:i:s') . "<br>";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Erro no Teste</h1>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    echo "<p>Trace: " . $e->getTraceAsString() . "</p>";
}
?>
