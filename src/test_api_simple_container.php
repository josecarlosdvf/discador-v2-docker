<?php
/**
 * Teste simples da API usando GET
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'control';
$_GET['command'] = 'status';

session_start();
$_SESSION['user_id'] = 1;

echo "=== TESTE API SIMPLES NO CONTAINER ===\n";

ob_start();
include '/var/www/html/api/discador-control.php';
$output = ob_get_clean();

echo "Resposta completa:\n$output\n\n";

// Extrair JSON da resposta
if (preg_match('/\{.*\}/', $output, $matches)) {
    $json = $matches[0];
    echo "JSON extraído: $json\n\n";
    
    $data = json_decode($json, true);
    if ($data) {
        echo "✅ Parsing OK!\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        if (isset($data['output'])) {
            echo "Output:\n" . $data['output'] . "\n";
        }
        if (isset($data['error'])) {
            echo "Error: " . $data['error'] . "\n";
        }
    }
}
?>
