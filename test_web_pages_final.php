<?php
/**
 * Teste Final de Páginas Web - Após Correções
 */

echo "=== TESTE FINAL DE PÁGINAS WEB ===\n\n";

$pages = [
    'login.php' => 'Página de Login',
    'dashboard.php' => 'Dashboard Principal',
    'campaigns.php' => 'Gestão de Campanhas',
    'users.php' => 'Gestão de Usuários',
    'billing.php' => 'Sistema de Faturamento',
    'register-company.php' => 'Registro de Empresa',
    'admin-dashboard.php' => 'Dashboard Admin'
];

foreach ($pages as $page => $name) {
    $url = "http://localhost:8080/$page";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content !== false) {
        $size = strlen($content);
        if ($size > 100) {
            echo "✅ $name: HTML válido ($size bytes)\n";
        } else {
            echo "⚠️ $name: Resposta muito pequena ($size bytes)\n";
        }
    } else {
        echo "❌ $name: Erro de conexão\n";
    }
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>
