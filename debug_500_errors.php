<?php
/**
 * Diagnóstico de Erro 500 - Páginas com Problema
 */

echo "🔍 DIAGNÓSTICO DE ERRO 500 - PÁGINAS PROBLEMÁTICAS\n\n";

$problem_pages = [
    'login.php',
    'dashboard.php', 
    'campaigns.php',
    'users.php',
    'billing.php'
];

foreach ($problem_pages as $page) {
    echo "🔧 Testando: $page\n";
    echo "----------------------------------------\n";
    
    // Testar dentro do container PHP
    $cmd = "docker exec discador_php php -f /var/www/html/$page 2>&1";
    $output = shell_exec($cmd);
    
    if (empty($output)) {
        echo "✅ Executou sem erros PHP\n";
    } else {
        echo "❌ ERRO ENCONTRADO:\n";
        echo $output . "\n";
    }
    
    // Testar via HTTP
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    
    $url = "http://localhost:8080/$page";
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        echo "✅ HTTP: Resposta recebida (" . strlen($response) . " bytes)\n";
        
        // Verificar se há erros PHP na saída
        if (strpos($response, 'Fatal error') !== false || 
            strpos($response, 'Parse error') !== false ||
            strpos($response, 'Warning:') !== false) {
            echo "⚠️ Erro PHP detectado na resposta HTTP\n";
            
            // Mostrar as primeiras linhas do erro
            $lines = explode("\n", $response);
            foreach ($lines as $line) {
                if (strpos($line, 'error') !== false || strpos($line, 'Warning') !== false) {
                    echo "  📋 $line\n";
                }
            }
        }
    } else {
        echo "❌ HTTP: Erro 500 ou falha na requisição\n";
    }
    
    echo "\n";
}

echo "🎯 DIAGNÓSTICO CONCLUÍDO\n";
?>
