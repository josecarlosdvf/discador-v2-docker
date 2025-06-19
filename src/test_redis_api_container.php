<?php
/**
 * Teste API com Redis funcionando no container
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'control';
$_GET['command'] = 'status';

session_start();
$_SESSION['user_id'] = 1;

echo "=== TESTE API COM REDIS NO CONTAINER ===\n";

// Primeiro, vamos forçar o uso do script principal (não fallback)
echo "1. Verificando Redis...\n";
try {
    $redis = new Redis();
    $redis->connect('redis', 6379);
    $redis->auth('redis123');
    echo "✅ Redis conectado!\n";
} catch (Exception $e) {
    echo "❌ Redis erro: " . $e->getMessage() . "\n";
}

echo "\n2. Testando API...\n";
ob_start();
include '/var/www/html/api/discador-control.php';
$output = ob_get_clean();

echo "Resposta:\n$output\n\n";

// Extrair e analisar JSON
if (preg_match('/(\{.*\})/', $output, $matches)) {
    $json = $matches[1];
    $data = json_decode($json, true);
    if ($data) {
        echo "✅ API funcionando!\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        if (isset($data['output'])) {
            echo "Output do comando:\n" . $data['output'] . "\n";
        }
    }
}
?>
