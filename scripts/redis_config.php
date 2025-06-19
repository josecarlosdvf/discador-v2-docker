<?php
/**
 * Redis Configuration and Setup Script
 * Configures Redis server for Discador v2.0
 */

require_once __DIR__ . '/../src/config/config.php';

class RedisConfigurator {
    private $redis;
    private $config;
      public function __construct() {
        $this->config = [
            'host' => $_ENV['REDIS_HOST'] ?? REDIS_HOST ?? 'redis',
            'port' => $_ENV['REDIS_PORT'] ?? REDIS_PORT ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? REDIS_DB ?? 0,
            'timeout' => 5.0
        ];
    }
    
    public function configure() {
        echo "=== Configurando Redis para Discador v2.0 ===\n\n";
        
        try {
            // Test connection
            echo "1. Testando conexão com Redis...\n";
            $this->testConnection();
            echo "   ✓ Conexão estabelecida com sucesso\n\n";
            
            // Configure Redis settings
            echo "2. Configurando parâmetros do Redis...\n";
            $this->configureRedisSettings();
            echo "   ✓ Parâmetros configurados\n\n";
            
            // Set up Redis data structures
            echo "3. Inicializando estruturas de dados...\n";
            $this->initializeDataStructures();
            echo "   ✓ Estruturas inicializadas\n\n";
            
            // Create indexes
            echo "4. Configurando índices e TTLs...\n";
            $this->setupIndexesAndTtls();
            echo "   ✓ Índices configurados\n\n";
            
            // Test all operations
            echo "5. Testando operações...\n";
            $this->testOperations();
            echo "   ✓ Todos os testes passaram\n\n";
            
            echo "✅ Redis configurado com sucesso para Discador v2.0!\n";
            echo "Banco de dados: {$this->config['database']}\n";
            echo "Host: {$this->config['host']}:{$this->config['port']}\n";
            
            return true;
            
        } catch (Exception $e) {
            echo "❌ Erro na configuração: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function testConnection() {
        $this->redis = new Redis();
        
        if (!$this->redis->connect($this->config['host'], $this->config['port'], $this->config['timeout'])) {
            throw new Exception("Não foi possível conectar ao Redis em {$this->config['host']}:{$this->config['port']}");
        }
        
        if ($this->config['password']) {
            if (!$this->redis->auth($this->config['password'])) {
                throw new Exception("Falha na autenticação Redis");
            }
        }
        
        if (!$this->redis->select($this->config['database'])) {
            throw new Exception("Não foi possível selecionar o banco de dados {$this->config['database']}");
        }
        
        // Test ping
        if (!$this->redis->ping()) {
            throw new Exception("Redis não está respondendo ao ping");
        }
    }
    
    private function configureRedisSettings() {
        // Configure Redis for optimal performance
        $configs = [
            'maxmemory-policy' => 'allkeys-lru',
            'tcp-keepalive' => '300',
            'timeout' => '300'
        ];
        
        foreach ($configs as $key => $value) {
            try {
                $this->redis->config('SET', $key, $value);
                echo "   - {$key}: {$value}\n";
            } catch (Exception $e) {
                echo "   ! Aviso: Não foi possível configurar {$key}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function initializeDataStructures() {
        // Initialize master info
        $this->redis->hMSet('discador:master:info', [
            'status' => 'stopped',
            'pid' => '',
            'started_at' => '',
            'version' => '2.0',
            'configured_at' => date('Y-m-d H:i:s')
        ]);
        echo "   - Master info inicializada\n";
        
        // Initialize queue stats
        $this->redis->hMSet('discador:queue:stats', [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total_processed' => 0
        ]);
        echo "   - Estatísticas da fila inicializadas\n";
        
        // Initialize system stats
        $this->redis->hMSet('discador:system:stats', [
            'calls_today' => 0,
            'calls_successful' => 0,
            'calls_failed' => 0,
            'uptime' => 0,
            'last_reset' => date('Y-m-d')
        ]);
        echo "   - Estatísticas do sistema inicializadas\n";
        
        // Initialize configuration
        $this->redis->hMSet('discador:config', [
            'max_workers' => 5,
            'worker_timeout' => 300,
            'heartbeat_interval' => 30,
            'queue_max_retries' => 3,
            'log_level' => 'INFO'
        ]);
        echo "   - Configuração padrão definida\n";
    }
    
    private function setupIndexesAndTtls() {
        // Set TTL for heartbeat keys (workers)
        $workers = $this->redis->keys('discador:worker:*:heartbeat');
        foreach ($workers as $key) {
            $this->redis->expire($key, 300); // 5 minutes
        }
        
        // Set TTL for temporary locks
        $locks = $this->redis->keys('discador:lock:*');
        foreach ($locks as $key) {
            $this->redis->expire($key, 3600); // 1 hour
        }
        
        echo "   - TTLs configurados para heartbeats e locks\n";
        
        // Create sorted sets for task priority
        $this->redis->zAdd('discador:queue:priority', 0, 'placeholder');
        $this->redis->zRem('discador:queue:priority', 'placeholder');
        echo "   - Sorted set para prioridades criado\n";
    }
    
    private function testOperations() {
        // Test basic operations
        $testKey = 'discador:test:' . uniqid();
        
        // Test string operations
        $this->redis->set($testKey, 'test_value', 10);
        if ($this->redis->get($testKey) !== 'test_value') {
            throw new Exception("Falha no teste de operações string");
        }
        echo "   - Operações string: OK\n";
        
        // Test hash operations
        $this->redis->hSet($testKey . ':hash', 'field1', 'value1');
        if ($this->redis->hGet($testKey . ':hash', 'field1') !== 'value1') {
            throw new Exception("Falha no teste de operações hash");
        }
        echo "   - Operações hash: OK\n";
        
        // Test list operations
        $this->redis->lPush($testKey . ':list', 'item1');
        if ($this->redis->lPop($testKey . ':list') !== 'item1') {
            throw new Exception("Falha no teste de operações list");
        }
        echo "   - Operações list: OK\n";
        
        // Test set operations
        $this->redis->sAdd($testKey . ':set', 'member1');
        if (!$this->redis->sIsMember($testKey . ':set', 'member1')) {
            throw new Exception("Falha no teste de operações set");
        }
        echo "   - Operações set: OK\n";
        
        // Test sorted set operations
        $this->redis->zAdd($testKey . ':zset', 1, 'member1');
        if ($this->redis->zScore($testKey . ':zset', 'member1') !== 1.0) {
            throw new Exception("Falha no teste de operações sorted set");
        }
        echo "   - Operações sorted set: OK\n";
        
        // Clean up test keys
        $this->redis->del($testKey);
        $this->redis->del($testKey . ':hash');
        $this->redis->del($testKey . ':list');
        $this->redis->del($testKey . ':set');
        $this->redis->del($testKey . ':zset');
    }
    
    public function getInfo() {
        echo "\n=== Informações do Redis ===\n\n";
        
        if (!$this->redis) {
            echo "Redis não conectado\n";
            return;
        }
        
        $info = $this->redis->info();
        
        echo "Versão Redis: " . ($info['redis_version'] ?? 'N/A') . "\n";
        echo "Modo: " . ($info['redis_mode'] ?? 'N/A') . "\n";
        echo "Uptime: " . ($info['uptime_in_seconds'] ?? 0) . " segundos\n";
        echo "Clientes conectados: " . ($info['connected_clients'] ?? 0) . "\n";
        echo "Memória usada: " . ($info['used_memory_human'] ?? 'N/A') . "\n";
        echo "Memória máxima: " . ($info['maxmemory_human'] ?? 'N/A') . "\n";
        echo "Total de comandos: " . ($info['total_commands_processed'] ?? 0) . "\n";
        echo "Total de chaves: " . $this->redis->dbSize() . "\n";
        
        echo "\n=== Chaves do Discador ===\n\n";
        $keys = $this->redis->keys('discador:*');
        if (empty($keys)) {
            echo "Nenhuma chave do discador encontrada\n";
        } else {
            foreach ($keys as $key) {
                $type = $this->redis->type($key);
                $ttl = $this->redis->ttl($key);
                $ttlInfo = $ttl > 0 ? " (TTL: {$ttl}s)" : ($ttl === -1 ? " (sem TTL)" : " (expirada)");
                echo "- {$key} [{$type}]{$ttlInfo}\n";
            }
        }
    }
    
    public function cleanup() {
        echo "\n=== Limpeza do Redis ===\n\n";
        
        if (!$this->redis) {
            echo "Redis não conectado\n";
            return;
        }
        
        // Remove all discador keys
        $keys = $this->redis->keys('discador:*');
        if (!empty($keys)) {
            $deleted = $this->redis->del($keys);
            echo "Removidas {$deleted} chaves do discador\n";
        } else {
            echo "Nenhuma chave do discador para remover\n";
        }
        
        echo "Limpeza concluída\n";
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'configure';
    
    $configurator = new RedisConfigurator();
    
    switch ($command) {
        case 'configure':
        case 'setup':
            $configurator->configure();
            break;
            
        case 'info':
        case 'status':
            $configurator->getInfo();
            break;
            
        case 'cleanup':
        case 'clean':
            $configurator->cleanup();
            break;
            
        case 'test':
            $configurator->testConnection();
            echo "✅ Conexão Redis OK\n";
            break;
            
        default:
            echo "Uso: php redis_config.php [configure|info|cleanup|test]\n";
            echo "\n";
            echo "Comandos:\n";
            echo "  configure  - Configura Redis para o Discador v2.0\n";
            echo "  info       - Mostra informações do Redis\n";
            echo "  cleanup    - Remove todas as chaves do discador\n";
            echo "  test       - Testa a conexão Redis\n";
            exit(1);
    }
} else {
    // Web interface
    header('Content-Type: text/plain');
    
    $action = $_GET['action'] ?? 'info';
    $configurator = new RedisConfigurator();
    
    switch ($action) {
        case 'configure':
            $configurator->configure();
            break;
        case 'info':
            $configurator->getInfo();
            break;
        case 'cleanup':
            $configurator->cleanup();
            break;
        default:
            $configurator->getInfo();
    }
}
?>
