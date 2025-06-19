<?php
/**
 * Teste de Conexão Redis
 */

require_once 'config/config.php';

echo "=== TESTE DE CONEXÃO REDIS ===\n";
echo "Host: " . REDIS_HOST . "\n";
echo "Port: " . REDIS_PORT . "\n";
echo "Password: " . (REDIS_PASS ? '***' : 'none') . "\n\n";

// Teste 1: Verificar se a extensão Redis está disponível
echo "1. Verificando extensão Redis...\n";
if (!extension_loaded('redis')) {
    echo "❌ Extensão Redis não está instalada!\n";
    echo "Para instalar no Windows: baixe php_redis.dll para sua versão do PHP\n";
    exit(1);
}
echo "✅ Extensão Redis disponível\n\n";

// Teste 2: Tentar conectar
echo "2. Testando conexão...\n";
try {
    $redis = new Redis();
    echo "Tentando conectar em " . REDIS_HOST . ":" . REDIS_PORT . "...\n";
    
    $connected = $redis->connect(REDIS_HOST, REDIS_PORT, 5); // 5 segundos timeout
    
    if (!$connected) {
        echo "❌ Falha na conexão\n";
        exit(1);
    }
    
    echo "✅ Conectado com sucesso!\n";
    
    // Teste 3: Autenticação se necessário
    if (REDIS_PASS) {
        echo "3. Testando autenticação...\n";
        $auth = $redis->auth(REDIS_PASS);
        if (!$auth) {
            echo "❌ Falha na autenticação\n";
            exit(1);
        }
        echo "✅ Autenticado com sucesso!\n";
    }
    
    // Teste 4: Ping
    echo "4. Testando ping...\n";
    $pong = $redis->ping();
    if ($pong === '+PONG' || $pong === 'PONG') {
        echo "✅ Ping OK: $pong\n";
    } else {
        echo "❌ Ping falhou: $pong\n";
    }
    
    // Teste 5: Operações básicas
    echo "5. Testando operações básicas...\n";
    $redis->set('test_key', 'test_value');
    $value = $redis->get('test_key');
    
    if ($value === 'test_value') {
        echo "✅ Set/Get funcionando\n";
        $redis->del('test_key'); // Limpar
    } else {
        echo "❌ Set/Get falhou\n";
    }
    
    // Teste 6: Informações do servidor
    echo "6. Informações do servidor Redis...\n";
    $info = $redis->info('server');
    if (isset($info['redis_version'])) {
        echo "✅ Redis version: " . $info['redis_version'] . "\n";
    }
    
    $redis->close();
    echo "\n🎉 Todos os testes passaram! Redis está funcionando corretamente.\n";
    
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
    echo "\nDicas para resolver:\n";
    echo "- Verifique se o contêiner Redis está rodando: docker ps\n";
    echo "- Verifique se a porta 6380 está acessível: netstat -an | findstr 6380\n";
    echo "- Teste conexão manual: redis-cli -h localhost -p 6380\n";
    exit(1);
}
?>
