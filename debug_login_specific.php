<?php
/**
 * Teste Específico de Erro no Login.php
 */

echo "🔍 TESTE ESPECÍFICO - LOGIN.PHP\n\n";

// Testar cada require do login.php separadamente
$test_commands = [
    "session_start()" => "docker exec discador_php php -r \"session_start(); echo 'Session OK\\n';\"",
    "require MultiTenantAuth" => "docker exec discador_php php -r \"require_once '/var/www/html/Core/MultiTenantAuth.php'; echo 'MultiTenantAuth OK\\n';\"",
    "require TenantManager" => "docker exec discador_php php -r \"require_once '/var/www/html/Core/TenantManager.php'; echo 'TenantManager OK\\n';\"",
    "instantiate Auth" => "docker exec discador_php php -r \"require_once '/var/www/html/Core/MultiTenantAuth.php'; \\\$auth = new \\DiscadorV2\\Core\\MultiTenantAuth(); echo 'Auth instantiated OK\\n';\"",
    "instantiate TenantManager" => "docker exec discador_php php -r \"require_once '/var/www/html/Core/TenantManager.php'; \\\$tm = \\DiscadorV2\\Core\\TenantManager::getInstance(); echo 'TenantManager instantiated OK\\n';\""
];

foreach ($test_commands as $test => $cmd) {
    echo "🔧 Testando: $test\n";
    
    $output = shell_exec($cmd . " 2>&1");
    
    if (strpos($output, 'OK') !== false) {
        echo "✅ $output";
    } else {
        echo "❌ ERRO:\n$output\n";
    }
    
    echo "\n";
}

// Agora vamos verificar se o problema é de namespace ou path
echo "🔍 VERIFICANDO ESTRUTURA DE CLASSES\n\n";

$cmd = "docker exec discador_php php -r \"
require_once '/var/www/html/Core/MultiTenantAuth.php';
require_once '/var/www/html/Core/TenantManager.php';

if (class_exists('\\\\DiscadorV2\\\\Core\\\\MultiTenantAuth')) {
    echo 'MultiTenantAuth class found\\n';
} else {
    echo 'MultiTenantAuth class NOT found\\n';
}

if (class_exists('\\\\DiscadorV2\\\\Core\\\\TenantManager')) {
    echo 'TenantManager class found\\n';
} else {
    echo 'TenantManager class NOT found\\n';
}
\"";

$output = shell_exec($cmd . " 2>&1");
echo $output;

echo "\n🎯 TESTE ESPECÍFICO CONCLUÍDO\n";
?>
