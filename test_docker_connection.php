<?php
/**
 * Teste de Conectividade para Ambiente Docker
 * 
 * Este script testa a conectividade com os serviços Docker
 * usando as portas mapeadas do host.
 */

echo "🔍 Testando conectividade com containers Docker...\n\n";

// Configurações para acesso externo aos containers
$docker_config = [
    'mariadb' => [
        'host' => 'localhost',
        'port' => 3307,
        'user' => 'root',
        'password' => 'root123',
        'database' => 'discador'
    ],
    'redis' => [
        'host' => 'localhost',
        'port' => 6380,
        'password' => 'redis123'
    ]
];

// Teste MariaDB
echo "📊 Testando MariaDB (localhost:3307)...\n";
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=utf8mb4',
        $docker_config['mariadb']['host'],
        $docker_config['mariadb']['port']
    );
    
    $pdo = new PDO(
        $dsn,
        $docker_config['mariadb']['user'],
        $docker_config['mariadb']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ Conexão MariaDB estabelecida com sucesso!\n";
    
    // Verificar versão
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "📋 Versão MariaDB: " . $result['version'] . "\n";
    
    // Verificar se o banco discador existe
    $stmt = $pdo->query("SHOW DATABASES LIKE 'discador'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Database 'discador' existe\n";
        
        // Conectar ao banco específico
        $pdo->exec("USE discador");
        
        // Verificar tabelas principais
        $tabelas_importantes = [
            'empresas',
            'usuarios', 
            'campanhas',
            'billing_faturas'
        ];
        
        echo "\n📊 Verificando estrutura do banco:\n";
        foreach ($tabelas_importantes as $tabela) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$tabela`");
                $result = $stmt->fetch();
                echo "✅ Tabela '$tabela': {$result['count']} registros\n";
            } else {
                echo "❌ Tabela '$tabela': NÃO EXISTE\n";
            }
        }
    } else {
        echo "❌ Database 'discador' não existe\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro MariaDB: " . $e->getMessage() . "\n";
}

echo "\n";

// Teste Redis
echo "🔴 Testando Redis (localhost:6380)...\n";
try {
    if (!extension_loaded('redis')) {
        echo "❌ Extensão Redis não está instalada no PHP\n";
    } else {
        $redis = new Redis();
        $connected = $redis->connect(
            $docker_config['redis']['host'], 
            $docker_config['redis']['port'],
            2.0 // timeout
        );
        
        if (!$connected) {
            throw new Exception("Não foi possível conectar");
        }
        
        // Autenticar se necessário
        if (!empty($docker_config['redis']['password'])) {
            $redis->auth($docker_config['redis']['password']);
        }
        
        echo "✅ Conexão Redis estabelecida com sucesso!\n";
        
        // Teste básico
        $redis->set('test_key', 'test_value');
        $value = $redis->get('test_key');
        
        if ($value === 'test_value') {
            echo "✅ Teste de escrita/leitura Redis passou\n";
        } else {
            echo "❌ Teste de escrita/leitura Redis falhou\n";
        }
        
        // Informações do Redis
        $info = $redis->info();
        echo "📋 Versão Redis: " . $info['redis_version'] . "\n";
        echo "📋 Clientes conectados: " . $info['connected_clients'] . "\n";
        
        $redis->close();
    }
    
} catch (Exception $e) {
    echo "❌ Erro Redis: " . $e->getMessage() . "\n";
}

echo "\n";

// Teste HTTP (Nginx)
echo "🌐 Testando Nginx (http://localhost:8080)...\n";
try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    
    $response = file_get_contents('http://localhost:8080', false, $context);
    
    if ($response !== false) {
        echo "✅ Nginx responde corretamente\n";
        echo "📋 Resposta: " . strlen($response) . " bytes\n";
    } else {
        echo "❌ Nginx não está respondendo\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro HTTP: " . $e->getMessage() . "\n";
}

echo "\n";

// Teste Asterisk AMI
echo "☎️ Testando Asterisk AMI (localhost:5038)...\n";
try {
    $socket = fsockopen('localhost', 5038, $errno, $errstr, 5);
    
    if ($socket) {
        echo "✅ Conexão AMI estabelecida\n";
        
        // Ler banner do Asterisk
        $banner = fgets($socket);
        echo "📋 Banner AMI: " . trim($banner) . "\n";
        
        fclose($socket);
    } else {
        echo "❌ Não foi possível conectar ao AMI: $errstr ($errno)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro AMI: " . $e->getMessage() . "\n";
}

echo "\n🎯 Teste de conectividade Docker concluído!\n";
echo "💡 Use este script para validar se todos os serviços estão funcionando\n";
?>
