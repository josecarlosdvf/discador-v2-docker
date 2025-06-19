<?php
/**
 * Teste das APIs REST - Sistema Discador v2.0
 */

echo "🔍 Testando APIs REST do Sistema Discador v2.0...\n\n";

$base_url = 'http://localhost:8080/api';

$apis = [
    'discador-status.php' => 'Status do Sistema',
    'dashboard-stats.php' => 'Estatísticas do Dashboard',
    'real-time-stats.php' => 'Estatísticas em Tempo Real',
    'recent-activity.php' => 'Atividades Recentes',
    'billing-reports.php' => 'Relatórios de Billing'
];

foreach ($apis as $api => $descricao) {
    echo "📊 Testando: $descricao ($api)...\n";
    
    $url = "$base_url/$api";
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'User-Agent: API-Test-Script'
                ]
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "❌ Falha na requisição\n";
        } else {
            $data = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "✅ Resposta JSON válida\n";
                echo "📋 Status: " . ($data['status'] ?? 'N/A') . "\n";
                echo "📋 Tamanho: " . strlen($response) . " bytes\n";
                
                // Mostrar algumas chaves importantes
                if (isset($data['data'])) {
                    echo "📋 Dados: " . count($data['data']) . " itens\n";
                }
                if (isset($data['message'])) {
                    echo "📋 Mensagem: " . $data['message'] . "\n";
                }
            } else {
                echo "❌ Resposta não é JSON válido\n";
                echo "📋 Resposta: " . substr($response, 0, 200) . "...\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "🎯 Teste de APIs concluído!\n";
?>
