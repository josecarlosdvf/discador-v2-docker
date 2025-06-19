<?php
echo "=== TESTE REDIS NO CONTAINER ===\n";

echo "1. Verificando extensao Redis...\n";
if (!extension_loaded('redis')) {
    echo "ERRO: Extensao Redis nao carregada\n";
    exit(1);
}
echo "OK: Extensao Redis carregada\n";

echo "2. Testando conexao...\n";
try {
    $redis = new Redis();
    echo "Conectando em redis:6379...\n";
    
    $connected = $redis->connect('redis', 6379, 5);
    if (!$connected) {
        echo "ERRO: Falha na conexao\n";
        exit(1);
    }
    echo "OK: Conectado\n";
    
    echo "3. Testando autenticacao...\n";
    $auth = $redis->auth('redis123');
    if (!$auth) {
        echo "ERRO: Falha na autenticacao\n";
        exit(1);
    }
    echo "OK: Autenticado\n";
    
    echo "4. Testando ping...\n";
    $pong = $redis->ping();
    echo "Ping result: " . $pong . "\n";
    
    echo "5. Testando operacoes basicas...\n";
    $redis->set('test_key', 'test_value');
    $value = $redis->get('test_key');
    echo "Set/Get test: " . $value . "\n";
    
    $redis->del('test_key');
    
    echo "SUCESSO: Todas as funcoes Redis funcionando!\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
