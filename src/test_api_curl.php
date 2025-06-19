<?php
/**
 * Teste da API via cURL (simulando chamada web real)
 */

// Definir URL da API
$url = 'http://localhost/discador_v2/src/api/discador-control.php';

// Dados para enviar
$data = [
    'action' => 'control',
    'command' => 'status'
];

echo "Testando API via cURL...\n";
echo "URL: $url\n";
echo "Dados: " . json_encode($data) . "\n\n";

// Verificar se curl está disponível
if (!function_exists('curl_init')) {
    echo "ERRO: cURL não está disponível\n";
    exit(1);
}

// Fazer requisição
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Código HTTP: $httpCode\n";

if ($error) {
    echo "Erro cURL: $error\n";
    exit(1);
}

echo "Resposta:\n";
echo $response . "\n\n";

// Verificar se é JSON válido
$decoded = json_decode($response, true);
if ($decoded) {
    echo "✓ JSON válido!\n";
    echo "Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    if (isset($decoded['output'])) {
        echo "Output:\n" . $decoded['output'] . "\n";
    }
    if (isset($decoded['error'])) {
        echo "Error: " . $decoded['error'] . "\n";
    }
} else {
    echo "✗ JSON inválido: " . json_last_error_msg() . "\n";
}
?>
