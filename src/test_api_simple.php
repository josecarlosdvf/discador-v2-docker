<?php
/**
 * Teste Simples da API de Controle do Discador
 */

// Simular ambiente HTTP
$_SERVER['REQUEST_METHOD'] = 'GET';

// Simular uma sessão para teste
session_start();
$_SESSION['user_id'] = 1;

// Definir dados via GET
$_GET['action'] = 'control';
$_GET['command'] = 'status';

echo "Testando API com GET...\n";

// Capturar output
ob_start();

try {
    include __DIR__ . '/api/discador-control.php';
    $output = ob_get_clean();
    
    echo "Output:\n";
    echo $output . "\n";
    
    // Verificar se é JSON válido
    $decoded = json_decode($output, true);
    if ($decoded) {
        echo "\n✓ JSON válido!\n";
        echo "Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
        echo "Action: " . ($decoded['action'] ?? 'N/A') . "\n";
        echo "Command: " . ($decoded['command'] ?? 'N/A') . "\n";
        if (isset($decoded['output'])) {
            echo "Output:\n" . $decoded['output'] . "\n";
        }
        if (isset($decoded['error'])) {
            echo "Error: " . $decoded['error'] . "\n";
        }
    } else {
        echo "\n✗ Erro de JSON: " . json_last_error_msg() . "\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "Erro durante execução: " . $e->getMessage() . "\n";
}
?>
