<?php
/**
 * Correção de Caminhos - Sistema de Autenticação
 */

echo "🔧 CORRIGINDO CAMINHOS DO SISTEMA DE AUTENTICAÇÃO\n\n";

// Arquivos que precisam de correção de path
$files_to_fix = [
    '/var/www/html/login.php',
    '/var/www/html/dashboard.php',
    '/var/www/html/campaigns.php', 
    '/var/www/html/users.php',
    '/var/www/html/billing.php'
];

foreach ($files_to_fix as $file) {
    echo "🔧 Corrigindo: " . basename($file) . "\n";
    
    // Ler conteúdo
    $cmd = "docker exec discador_nginx cat '$file'";
    $content = shell_exec($cmd);
    
    if (empty($content)) {
        echo "❌ Arquivo não encontrado: $file\n";
        continue;
    }
    
    // Fazer correções comuns
    $fixes = [
        "__DIR__ . '/Core/" => "'/var/www/html/Core/",
        "__DIR__ . '/config/" => "'/var/www/html/config/",
        "require_once __DIR__ . '/Core/" => "require_once '/var/www/html/Core/",
        "require_once __DIR__ . '/config/" => "require_once '/var/www/html/config/",
        "include_once __DIR__ . '/Core/" => "include_once '/var/www/html/Core/",
        "include_once __DIR__ . '/config/" => "include_once '/var/www/html/config/"
    ];
    
    $original_content = $content;
    
    foreach ($fixes as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $original_content) {
        echo "✅ Correções aplicadas\n";
        
        // Salvar arquivo corrigido
        $temp_file = tempnam(sys_get_temp_dir(), 'fixed_');
        file_put_contents($temp_file, $content);
        
        // Copiar de volta para o container
        $cmd = "docker cp \"$temp_file\" discador_nginx:$file";
        shell_exec($cmd);
        
        unlink($temp_file);
        echo "✅ Arquivo atualizado no container\n";
    } else {
        echo "ℹ️ Nenhuma correção necessária\n";
    }
    
    echo "\n";
}

echo "🎯 CORREÇÃO DE CAMINHOS CONCLUÍDA\n";
echo "🔍 Testando páginas após correção...\n\n";

// Testar páginas após correção
$test_pages = ['login.php', 'dashboard.php'];

foreach ($test_pages as $page) {
    echo "🌐 Testando: $page\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $url = "http://localhost:8080/$page";
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        echo "✅ Resposta HTTP recebida (" . strlen($response) . " bytes)\n";
        
        if (strpos($response, 'Fatal error') !== false || 
            strpos($response, 'Parse error') !== false) {
            echo "❌ Ainda há erros PHP\n";
        } else {
            echo "✅ Sem erros PHP detectados\n";
        }
    } else {
        echo "❌ Ainda retorna erro 500\n";
    }
    
    echo "\n";
}
?>
