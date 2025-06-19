<?php
/**
 * Teste da API de Controle do Discador
 */

// Simular ambiente HTTP
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simular uma sessão para teste
session_start();
$_SESSION['user_id'] = 1;

// Simular dados de entrada JSON
$jsonData = json_encode([
    'action' => 'control',
    'command' => 'status'
]);

// Capturar output
ob_start();

// Simular input stream
file_put_contents('php://memory', $jsonData);

// Incluir e executar a API
try {
    include __DIR__ . '/api/discador-control.php';
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

echo "Resultado da API:\n";
echo $output;
echo "\n";

// Verificar se é JSON válido
$decoded = json_decode($output, true);
if ($decoded) {
    echo "JSON válido!\n";
    print_r($decoded);
} else {
    echo "Erro de JSON: " . json_last_error_msg() . "\n";
    echo "Output raw: " . var_export($output, true) . "\n";
}
?>
