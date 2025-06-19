<?php
$output = "[2025-06-18 19:47:01] Executando comando: status\n[2025-06-18 19:47:01] Verificando status do sistema...\n[2025-06-18 19:47:01] Redis: ERRO Offline\n[2025-06-18 19:47:01] Processos: OK Processos ativos\n[2025-06-18 19:47:01] Comando 'status' executado com sucesso";

$data = [
    'success' => true,
    'output' => $output,
    'action' => 'control',
    'command' => 'status',
    'timestamp' => '2025-06-18 19:47:01'
];

$json = json_encode($data);
echo "JSON válido: " . (($json !== false) ? "SIM" : "NÃO") . "\n";

if ($json === false) {
    echo "Erro: " . json_last_error_msg() . "\n";
} else {
    echo "JSON:\n" . $json . "\n";
}

// Teste decodificação
$decoded = json_decode($json, true);
echo "Decodificação: " . (($decoded !== null) ? "OK" : "ERRO") . "\n";
?>
