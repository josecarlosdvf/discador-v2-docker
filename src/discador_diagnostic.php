<?php
/**
 * Script de Diagnóstico Simplificado - Discador v2.0
 */

echo "=== Diagnóstico do Sistema Discador v2.0 ===\n\n";

$status = ['success' => true, 'errors' => [], 'warnings' => []];

// 1. Verificar PHP
echo "1. Verificando PHP...\n";
echo "   Versão: " . PHP_VERSION . "\n";

$required_extensions = ['pdo_mysql', 'json', 'mbstring', 'curl'];
$optional_extensions = ['redis', 'opcache'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ {$ext}: OK\n";
    } else {
        echo "   ✗ {$ext}: FALTANDO\n";
        $status['errors'][] = "Extensão PHP obrigatória faltando: {$ext}";
        $status['success'] = false;
    }
}

foreach ($optional_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ {$ext}: OK\n";
    } else {
        echo "   ! {$ext}: Recomendado\n";
        $status['warnings'][] = "Extensão PHP recomendada: {$ext}";
    }
}

// 2. Verificar Redis
echo "\n2. Verificando Redis...\n";
if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $host = $_ENV['REDIS_HOST'] ?? 'redis';
        $password = $_ENV['REDIS_PASSWORD'] ?? 'redis123';
        
        if ($redis->connect($host, 6379, 2)) {
            if ($password) {
                $redis->auth($password);
            }
            echo "   ✓ Conexão: OK\n";
            echo "   ✓ Host: {$host}\n";
            
            $info = $redis->info();
            echo "   ✓ Versão: " . ($info['redis_version'] ?? 'unknown') . "\n";
            echo "   ✓ Memória: " . ($info['used_memory_human'] ?? 'unknown') . "\n";
        } else {
            echo "   ✗ Conexão: FALHOU\n";
            $status['errors'][] = "Não foi possível conectar ao Redis";
            $status['success'] = false;
        }
    } catch (Exception $e) {
        echo "   ✗ Erro: " . $e->getMessage() . "\n";
        $status['errors'][] = "Erro Redis: " . $e->getMessage();
        $status['success'] = false;
    }
} else {
    echo "   ! Extensão Redis não encontrada\n";
    $status['warnings'][] = "Extensão Redis não instalada";
}

// 3. Verificar Banco de Dados
echo "\n3. Verificando Banco de Dados...\n";
try {
    $host = $_ENV['DB_HOST'] ?? 'database';
    $dbname = $_ENV['DB_NAME'] ?? 'discador';
    $user = $_ENV['DB_USER'] ?? 'discador';
    $password = $_ENV['DB_PASSWORD'] ?? 'discador123';
    
    $pdo = new PDO("mysql:host={$host};dbname={$dbname}", $user, $password);
    echo "   ✓ Conexão: OK\n";
    echo "   ✓ Host: {$host}\n";
    echo "   ✓ Banco: {$dbname}\n";
    
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "   ✓ Versão: {$version}\n";
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
    $status['errors'][] = "Erro Banco de Dados: " . $e->getMessage();
    $status['success'] = false;
}

// 4. Verificar Diretórios
echo "\n4. Verificando Diretórios...\n";
$required_dirs = ['logs', 'tmp', 'backup'];
$base_path = realpath(__DIR__ . '/..');

foreach ($required_dirs as $dir) {
    $path = $base_path . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "   ✓ {$dir}: OK (escrita permitida)\n";
        } else {
            echo "   ! {$dir}: OK (sem permissão de escrita)\n";
            $status['warnings'][] = "Diretório sem permissão de escrita: {$dir}";
        }
    } else {
        echo "   ! {$dir}: NÃO EXISTE (será criado automaticamente)\n";
        $status['warnings'][] = "Diretório será criado automaticamente: {$dir}";
    }
}

// Verificar diretório config
$config_dir = __DIR__ . '/config';
if (is_dir($config_dir)) {
    echo "   ✓ config: OK\n";
} else {
    echo "   ✗ config: NÃO EXISTE\n";
    $status['errors'][] = "Diretório de configuração não existe";
    $status['success'] = false;
}

// 5. Verificar Configurações
echo "\n5. Verificando Configurações...\n";
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    echo "   ✓ config.php: OK\n";
} else {
    echo "   ✗ config.php: NÃO ENCONTRADO\n";
    $status['errors'][] = "Arquivo de configuração não encontrado";
    $status['success'] = false;
}

// 6. Teste de Performance
echo "\n6. Teste de Performance...\n";
$start = microtime(true);
for ($i = 0; $i < 10000; $i++) {
    $dummy = md5($i);
}
$end = microtime(true);
$time = round(($end - $start) * 1000, 2);
echo "   ✓ Teste CPU: {$time}ms (10K md5)\n";

// 7. Verificar Asterisk (se configurado)
echo "\n7. Verificando Asterisk...\n";
$asterisk_host = $_ENV['ASTERISK_HOST'] ?? 'asterisk';
$asterisk_port = 5038;

$socket = @fsockopen($asterisk_host, $asterisk_port, $errno, $errstr, 2);
if ($socket) {
    echo "   ✓ Asterisk AMI: OK ({$asterisk_host}:{$asterisk_port})\n";
    fclose($socket);
} else {
    echo "   ! Asterisk AMI: Não acessível ({$asterisk_host}:{$asterisk_port})\n";
    $status['warnings'][] = "Asterisk AMI não acessível";
}

// 8. Resumo
echo "\n" . str_repeat("=", 50) . "\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo str_repeat("=", 50) . "\n";

if ($status['success']) {
    echo "✅ STATUS GERAL: OK\n";
} else {
    echo "❌ STATUS GERAL: PROBLEMAS ENCONTRADOS\n";
}

if (!empty($status['errors'])) {
    echo "\n🔴 ERROS:\n";
    foreach ($status['errors'] as $error) {
        echo "   - {$error}\n";
    }
}

if (!empty($status['warnings'])) {
    echo "\n🟡 AVISOS:\n";
    foreach ($status['warnings'] as $warning) {
        echo "   - {$warning}\n";
    }
}

echo "\nDiagnóstico concluído em " . date('Y-m-d H:i:s') . "\n";

// Retornar código de saída apropriado
exit($status['success'] ? 0 : 1);
?>
