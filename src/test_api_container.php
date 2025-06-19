<?php
/**
 * Teste da API de Controle do Discador no Container
 */

// Simular dados de entrada JSON
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simular sessão
session_start();
$_SESSION['user_id'] = 1;

// Simular dados JSON
$jsonData = json_encode([
    'action' => 'control',
    'command' => 'status'
]);

// Simular stream de entrada
file_put_contents('php://memory', $jsonData);

echo "=== TESTE API DISCADOR NO CONTAINER ===\n";

try {
    // Capturar output da API
    ob_start();
    
    // Simular função file_get_contents('php://input')
    $GLOBALS['HTTP_RAW_POST_DATA'] = $jsonData;
    
    include '/var/www/html/api/discador-control.php';
    $output = ob_get_clean();
    
    echo "Resposta da API:\n";
    echo $output . "\n";
    
    // Limpar warnings e extrair JSON
    $lines = explode("\n", $output);
    $jsonLine = '';
    foreach ($lines as $line) {
        if (trim($line) && (strpos(trim($line), '{') === 0)) {
            $jsonLine = trim($line);
            break;
        }
    }
    
    if ($jsonLine) {
        echo "\nJSON encontrado: $jsonLine\n";
        $decoded = json_decode($jsonLine, true);
        if ($decoded) {
            echo "\nJSON válido!\n";
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
            echo "Erro de JSON: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "Nenhum JSON encontrado na resposta\n";
    }
    
} catch (Exception $e) {
    echo "Erro durante execução: " . $e->getMessage() . "\n";
}
?>
