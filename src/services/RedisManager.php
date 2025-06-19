<?php
/**
 * Redis Manager - Gerenciamento centralizado do Redis
 * Fornece métodos auxiliares para operações Redis no sistema
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class RedisManager {
    private static $instance = null;
    private $redis;
    private $isConnected = false;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        try {
            $this->redis = new Redis();
            $this->redis->connect(REDIS_HOST, REDIS_PORT, 5); // 5s timeout
            
            if (REDIS_PASS) {
                $this->redis->auth(REDIS_PASS);
            }
            
            $this->redis->select(REDIS_DB ?? 0);
            $this->isConnected = true;
            
        } catch (Exception $e) {
            $this->isConnected = false;
            error_log("RedisManager: Erro de conexão - " . $e->getMessage());
            throw new Exception("Falha na conexão Redis: " . $e->getMessage());
        }
    }
    
    public function getRedis(): Redis {
        if (!$this->isConnected) {
            $this->connect();
        }
        return $this->redis;
    }
    
    public function isConnected(): bool {
        return $this->isConnected && $this->redis->ping() === '+PONG';
    }
    
    /**
     * Operações de Lock Distribuído
     */
    public function acquireLock(string $lockKey, int $ttl = 300): bool {
        try {
            $lockValue = uniqid(gethostname() . '-', true);
            $result = $this->redis->set($lockKey, $lockValue, ['nx', 'ex' => $ttl]);
            
            if ($result) {
                // Armazena valor do lock para liberação posterior
                $this->redis->hSet('lock_values', $lockKey, $lockValue);
                return true;
            }
            return false;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao adquirir lock '$lockKey' - " . $e->getMessage());
            return false;
        }
    }
    
    public function releaseLock(string $lockKey): bool {
        try {
            $lockValue = $this->redis->hGet('lock_values', $lockKey);
            if (!$lockValue) {
                return false;
            }
            
            // Script Lua para release atômico
            $script = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    redis.call('del', KEYS[1])
                    redis.call('hdel', 'lock_values', KEYS[1])
                    return 1
                else
                    return 0
                end
            ";
            
            $result = $this->redis->eval($script, [$lockKey, $lockValue], 1);
            return $result === 1;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao liberar lock '$lockKey' - " . $e->getMessage());
            return false;
        }
    }
    
    public function extendLock(string $lockKey, int $ttl = 300): bool {
        try {
            $lockValue = $this->redis->hGet('lock_values', $lockKey);
            if (!$lockValue) {
                return false;
            }
            
            // Verifica se ainda possui o lock antes de estender
            if ($this->redis->get($lockKey) === $lockValue) {
                return $this->redis->expire($lockKey, $ttl);
            }
            return false;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao estender lock '$lockKey' - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Operações de Fila de Tarefas
     */
    public function pushTask(string $queue, array $task): bool {
        try {
            $taskData = json_encode([
                'id' => uniqid('task_', true),
                'created_at' => date('Y-m-d H:i:s'),
                'data' => $task
            ]);
            
            return $this->redis->rPush($queue, $taskData) > 0;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao adicionar tarefa na fila '$queue' - " . $e->getMessage());
            return false;
        }
    }
    
    public function popTask(string $queue, int $timeout = 10): ?array {
        try {
            $result = $this->redis->blPop([$queue], $timeout);
            if ($result && isset($result[1])) {
                $taskData = json_decode($result[1], true);
                return $taskData ?: null;
            }
            return null;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao recuperar tarefa da fila '$queue' - " . $e->getMessage());
            return null;
        }
    }
    
    public function getQueueSize(string $queue): int {
        try {
            return $this->redis->lLen($queue);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter tamanho da fila '$queue' - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Operações de Cache/Estado
     */
    public function set(string $key, $value, int $ttl = null): bool {
        try {
            $options = [];
            if ($ttl !== null) {
                $options['ex'] = $ttl;
            }
            
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            
            return $this->redis->set($key, $value, $options);
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao definir chave '$key' - " . $e->getMessage());
            return false;
        }
    }
    
    public function get(string $key) {
        try {
            $value = $this->redis->get($key);
            if ($value === false) {
                return null;
            }
            
            // Tenta decodificar JSON
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter chave '$key' - " . $e->getMessage());
            return null;
        }
    }
    
    public function delete(string $key): bool {
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao deletar chave '$key' - " . $e->getMessage());
            return false;
        }
    }
    
    public function exists(string $key): bool {
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao verificar existência da chave '$key' - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Operações de Hash (para dados estruturados)
     */
    public function hSet(string $key, string $field, $value): bool {
        try {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            return $this->redis->hSet($key, $field, $value) !== false;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao definir hash '$key:$field' - " . $e->getMessage());
            return false;
        }
    }
    
    public function hGet(string $key, string $field) {
        try {
            $value = $this->redis->hGet($key, $field);
            if ($value === false) {
                return null;
            }
            
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter hash '$key:$field' - " . $e->getMessage());
            return null;
        }
    }
    
    public function hGetAll(string $key): array {
        try {
            $data = $this->redis->hGetAll($key);
            $result = [];
            
            foreach ($data as $field => $value) {
                $decoded = json_decode($value, true);
                $result[$field] = $decoded !== null ? $decoded : $value;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter todos os campos do hash '$key' - " . $e->getMessage());
            return [];
        }
    }
    
    public function hDel(string $key, string $field): bool {
        try {
            return $this->redis->hDel($key, $field) > 0;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao deletar hash '$key:$field' - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Operações de Estatísticas e Métricas
     */
    public function incrementCounter(string $key, int $amount = 1): int {
        try {
            return $this->redis->incrBy($key, $amount);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao incrementar contador '$key' - " . $e->getMessage());
            return 0;
        }
    }
    
    public function decrementCounter(string $key, int $amount = 1): int {
        try {
            return $this->redis->decrBy($key, $amount);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao decrementar contador '$key' - " . $e->getMessage());
            return 0;
        }
    }
    
    public function getCounter(string $key): int {
        try {
            $value = $this->redis->get($key);
            return $value !== false ? (int) $value : 0;
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter contador '$key' - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Operações de Limpeza e Manutenção
     */
    public function flushDatabase(): bool {
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao limpar banco - " . $e->getMessage());
            return false;
        }
    }
    
    public function getMemoryInfo(): array {
        try {
            $info = $this->redis->info('memory');
            return $info ?: [];
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter informações de memória - " . $e->getMessage());
            return [];
        }
    }
    
    public function getKeysMatching(string $pattern): array {
        try {
            return $this->redis->keys($pattern);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao buscar chaves com padrão '$pattern' - " . $e->getMessage());
            return [];
        }
    }
    
    public function setExpire(string $key, int $ttl): bool {
        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao definir expiração para chave '$key' - " . $e->getMessage());
            return false;
        }
    }
    
    public function getTTL(string $key): int {
        try {
            return $this->redis->ttl($key);
        } catch (Exception $e) {
            error_log("RedisManager: Erro ao obter TTL da chave '$key' - " . $e->getMessage());
            return -1;
        }
    }
    
    /**
     * Health Check
     */
    public function healthCheck(): array {
        $health = [
            'connected' => false,
            'ping' => false,
            'memory_usage' => 0,
            'keys_count' => 0,
            'uptime' => 0
        ];
        
        try {
            $health['connected'] = $this->isConnected();
            
            if ($health['connected']) {
                $health['ping'] = $this->redis->ping() === '+PONG';
                
                $info = $this->redis->info();
                $health['memory_usage'] = isset($info['used_memory']) ? (int) $info['used_memory'] : 0;
                $health['uptime'] = isset($info['uptime_in_seconds']) ? (int) $info['uptime_in_seconds'] : 0;
                $health['keys_count'] = $this->redis->dbSize();
            }
            
        } catch (Exception $e) {
            error_log("RedisManager: Erro no health check - " . $e->getMessage());
        }
        
        return $health;
    }
    
    public function __destruct() {
        if ($this->isConnected && $this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignora erros na destruição
            }
        }
    }
}
