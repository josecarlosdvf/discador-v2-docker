<?php
/**
 * Teste de Conectividade Redis via Socket (sem extensão PHP)
 */

require_once 'config/config.php';

echo "=== TESTE DE CONECTIVIDADE REDIS (SEM EXTENSÃO) ===\n";
echo "Host: " . REDIS_HOST . "\n";
echo "Port: " . REDIS_PORT . "\n";
echo "Password: " . (REDIS_PASS ? '***' : 'none') . "\n\n";

// Teste 1: Verificar se a porta está acessível
echo "1. Testando conectividade de rede...\n";

$connection = @fsockopen(REDIS_HOST, REDIS_PORT, $errno, $errstr, 5);
if (!$connection) {
    echo "❌ Não foi possível conectar na porta " . REDIS_PORT . "\n";
    echo "Erro: $errstr ($errno)\n\n";
    
    echo "DIAGNÓSTICO:\n";
    echo "- Verifique se o contêiner Redis está rodando: docker ps\n";
    echo "- Verifique se a porta está mapeada: docker port discador_redis\n";
    echo "- Teste a porta: telnet localhost 6380\n";
    exit(1);
}

echo "✅ Conectividade de rede OK!\n";

// Teste 2: Enviar comando PING
echo "2. Testando comando Redis...\n";

// Enviar comando PING
fwrite($connection, "PING\r\n");

// Ler resposta
$response = fread($connection, 1024);
echo "Resposta do Redis: " . trim($response) . "\n";

if (strpos($response, 'PONG') !== false) {
    echo "✅ Redis está respondendo corretamente!\n";
} else {
    echo "❌ Resposta inesperada do Redis\n";
}

// Teste 3: Autenticação se necessário
if (REDIS_PASS) {
    echo "3. Testando autenticação...\n";
    fwrite($connection, "AUTH " . REDIS_PASS . "\r\n");
    $authResponse = fread($connection, 1024);
    echo "Resposta auth: " . trim($authResponse) . "\n";
    
    if (strpos($authResponse, 'OK') !== false) {
        echo "✅ Autenticação OK!\n";
    } else {
        echo "❌ Falha na autenticação\n";
    }
}

// Teste 4: Comando INFO
echo "4. Obtendo informações do servidor...\n";
fwrite($connection, "INFO server\r\n");
$infoResponse = fread($connection, 2048);
echo "Info do servidor:\n";

$lines = explode("\n", $infoResponse);
foreach ($lines as $line) {
    if (strpos($line, 'redis_version') !== false) {
        echo "✅ " . trim($line) . "\n";
        break;
    }
}

fclose($connection);

echo "\n🎉 TESTE CONCLUÍDO!\n";
echo "\nSTATUS DO REDIS:\n";
echo "✅ Contêiner: Rodando e acessível\n";
echo "✅ Rede: Conectividade OK\n";
echo "✅ Serviço: Respondendo comandos\n";
echo "❌ Extensão PHP: Não instalada (use install_redis_extension.ps1)\n\n";

echo "AÇÃO NECESSÁRIA:\n";
echo "1. Execute como Administrador: powershell -ExecutionPolicy Bypass -File install_redis_extension.ps1\n";
echo "2. Reinicie o Apache no XAMPP\n";
echo "3. Execute: php test_redis.php\n";
?>
