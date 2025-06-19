<?php
/**
 * Teste Automatizado da Interface de Gestão
 */

echo "🌐 Testando Interface de Gestão do Sistema Discador v2.0...\n\n";

$base_url = 'http://localhost:8080';

// Função para fazer requests HTTP
function makeRequest($url, $post_data = null) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => $post_data ? 'POST' : 'GET',
            'header' => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Interface-Test-Script'
            ],
            'content' => $post_data ? http_build_query($post_data) : null
        ]
    ]);
    
    return file_get_contents($url, false, $context);
}

// Lista de páginas para testar
$pages = [
    'login.php' => 'Página de Login',
    'register-company.php' => 'Registro de Empresa',
    'dashboard.php' => 'Dashboard Principal', 
    'campaigns.php' => 'Gestão de Campanhas',
    'users.php' => 'Gestão de Usuários',
    'billing.php' => 'Sistema de Billing',
    'admin-dashboard.php' => 'Dashboard Admin',
    'admin-companies.php' => 'Gestão de Empresas'
];

foreach ($pages as $page => $descricao) {
    echo "📊 Testando: $descricao ($page)...\n";
    
    $url = "$base_url/$page";
    
    try {
        $response = makeRequest($url);
        
        if ($response === false) {
            echo "❌ Falha na requisição\n";
        } else {
            $length = strlen($response);
            
            // Verificar se é uma página HTML válida
            if (strpos($response, '<!DOCTYPE') !== false || strpos($response, '<html') !== false) {
                echo "✅ Página HTML válida\n";
                echo "📋 Tamanho: $length bytes\n";
                
                // Verificar elementos essenciais
                if (strpos($response, '<title>') !== false) {
                    preg_match('/<title>(.*?)<\/title>/i', $response, $matches);
                    $title = $matches[1] ?? 'N/A';
                    echo "📋 Título: $title\n";
                }
                
                // Verificar se há erros PHP visíveis
                if (strpos($response, 'Fatal error') !== false || 
                    strpos($response, 'Parse error') !== false ||
                    strpos($response, 'Warning:') !== false) {
                    echo "⚠️ Possível erro PHP detectado na página\n";
                }
                
            } else {
                echo "⚠️ Resposta não é HTML válido\n";
                echo "📋 Conteúdo: " . substr($response, 0, 200) . "...\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Testar uma funcionalidade específica: verificar se login está configurado
echo "🔐 Testando funcionalidade de login...\n";
try {
    $login_url = "$base_url/login.php";
    $response = makeRequest($login_url);
    
    if (strpos($response, 'name="email"') !== false && 
        strpos($response, 'name="password"') !== false) {
        echo "✅ Formulário de login detectado corretamente\n";
    } else {
        echo "❌ Formulário de login não encontrado\n";
    }
    
    // Verificar se há suporte multi-tenant
    if (strpos($response, 'admin_global') !== false || 
        strpos($response, 'empresa') !== false) {
        echo "✅ Interface multi-tenant detectada\n";
    } else {
        echo "⚠️ Interface multi-tenant não detectada claramente\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no teste de login: " . $e->getMessage() . "\n";
}

echo "\n🎯 Teste de interface concluído!\n";
echo "💡 Acesse http://localhost:8080/login.php para testar manualmente\n";
?>
