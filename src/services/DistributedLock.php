<?php
/**
 * Sistema de Locks Distribuído para Discador v2.0
 * Gerenciamento de concorrência e recursos compartilhados
 */

require_once __DIR__ . '/../config/config.php';

class DistributedLock {
    private $redis;
    private $db;
    private $locks = [];
    
    const LOCK_TTL = 300; // 5 minutos
    const HEARTBEAT_INTERVAL = 30; // 30 segundos
    
    public function __construct() {
        $this->redis = RedisManager::getInstance()->getConnection();
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Adquire um lock exclusivo
     */
    public function acquire($resource, $ttl = self::LOCK_TTL) {
        $lockKey = "lock:{$resource}";
        $lockValue = uniqid(gethostname() . '_' . getmypid() . '_', true);
        
        // Tentar adquirir o lock com TTL
        $acquired = $this->redis->set($lockKey, $lockValue, ['NX', 'EX' => $ttl]);
        
        if ($acquired) {
            $this->locks[$resource] = [
                'key' => $lockKey,
                'value' => $lockValue,
                'acquired_at' => time(),
                'ttl' => $ttl
            ];
            
            $this->logLockOperation('ACQUIRE', $resource, $lockValue);
            return $lockValue;
        }
        
        return false;
    }
    
    /**
     * Libera um lock
     */
    public function release($resource, $lockValue = null) {
        if (!isset($this->locks[$resource])) {
            return false;
        }
        
        $lock = $this->locks[$resource];
        $lockValue = $lockValue ?: $lock['value'];
        
        // Script Lua para liberação atômica
        $script = "
            if redis.call('GET', KEYS[1]) == ARGV[1] then
                return redis.call('DEL', KEYS[1])
            else
                return 0
            end
        ";
        
        $result = $this->redis->eval($script, [$lock['key'], $lockValue], 1);
        
        if ($result) {
            unset($this->locks[$resource]);
            $this->logLockOperation('RELEASE', $resource, $lockValue);
            return true;
        }
        
        return false;
    }
    
    /**
     * Renova um lock existente
     */
    public function renew($resource, $ttl = self::LOCK_TTL) {
        if (!isset($this->locks[$resource])) {
            return false;
        }
        
        $lock = $this->locks[$resource];
        
        $script = "
            if redis.call('GET', KEYS[1]) == ARGV[1] then
                return redis.call('EXPIRE', KEYS[1], ARGV[2])
            else
                return 0
            end
        ";
        
        $result = $this->redis->eval($script, [$lock['key'], $lock['value'], $ttl], 1);
        
        if ($result) {
            $this->locks[$resource]['ttl'] = $ttl;
            $this->logLockOperation('RENEW', $resource, $lock['value']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Verifica se possui um lock
     */
    public function hasLock($resource) {
        return isset($this->locks[$resource]);
    }
    
    /**
     * Lista todos os locks ativos
     */
    public function getActiveLocks() {
        $pattern = "lock:*";
        $keys = $this->redis->keys($pattern);
        $locks = [];
        
        foreach ($keys as $key) {
            $value = $this->redis->get($key);
            $ttl = $this->redis->ttl($key);
            
            $locks[] = [
                'resource' => str_replace('lock:', '', $key),
                'holder' => $value,
                'ttl' => $ttl,
                'expires_at' => time() + $ttl
            ];
        }
        
        return $locks;
    }
    
    /**
     * Força liberação de locks expirados
     */
    public function cleanupExpiredLocks() {
        $pattern = "lock:*";
        $keys = $this->redis->keys($pattern);
        $cleaned = 0;
        
        foreach ($keys as $key) {
            $ttl = $this->redis->ttl($key);
            if ($ttl <= 0) {
                $this->redis->del($key);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->logLockOperation('CLEANUP', "expired_locks", $cleaned);
        }
        
        return $cleaned;
    }
    
    /**
     * Heartbeat para locks de longa duração
     */
    public function startHeartbeat($resource) {
        if (!$this->hasLock($resource)) {
            return false;
        }
        
        // Registrar processo para heartbeat automático
        $pid = getmypid();
        $heartbeatKey = "heartbeat:{$resource}:{$pid}";
        
        $this->redis->setex($heartbeatKey, self::HEARTBEAT_INTERVAL * 2, time());
        
        return true;
    }
    
    /**
     * Registra operações de lock para auditoria
     */
    private function logLockOperation($operation, $resource, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (action, details, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            $logData = [
                'operation' => $operation,
                'resource' => $resource,
                'details' => $details,
                'pid' => getmypid(),
                'hostname' => gethostname()
            ];
            
            $stmt->execute(['lock_operation', json_encode($logData)]);
            
        } catch (Exception $e) {
            error_log("Failed to log lock operation: " . $e->getMessage());
        }
    }
    
    /**
     * Libera todos os locks ao destruir o objeto
     */
    public function __destruct() {
        foreach ($this->locks as $resource => $lock) {
            $this->release($resource, $lock['value']);
        }
    }
}

/**
 * Queue Manager para tarefas distribuídas
 */
class TaskQueue {
    private $redis;
    private $queueName;
    
    public function __construct($queueName = 'discador_tasks') {
        $this->redis = RedisManager::getInstance()->getConnection();
        $this->queueName = $queueName;
    }
    
    /**
     * Adiciona tarefa à fila
     */
    public function push($task, $priority = 0) {
        $taskData = [
            'id' => uniqid('task_', true),
            'data' => $task,
            'priority' => $priority,
            'created_at' => time(),
            'attempts' => 0
        ];
        
        $this->redis->lpush($this->queueName, json_encode($taskData));
        return $taskData['id'];
    }
    
    /**
     * Remove tarefa da fila
     */
    public function pop($timeout = 30) {
        $result = $this->redis->brpop([$this->queueName], $timeout);
        
        if ($result) {
            return json_decode($result[1], true);
        }
        
        return null;
    }
    
    /**
     * Rejeita tarefa (volta para a fila)
     */
    public function reject($task, $delay = 60) {
        $task['attempts'] = ($task['attempts'] ?? 0) + 1;
        $task['retry_after'] = time() + $delay;
        
        if ($task['attempts'] < 5) {
            $this->redis->lpush($this->queueName . ':delayed', json_encode($task));
        } else {
            $this->redis->lpush($this->queueName . ':failed', json_encode($task));
        }
    }
    
    /**
     * Processa tarefas atrasadas
     */
    public function processDelayed() {
        $delayedQueue = $this->queueName . ':delayed';
        $processed = 0;
        
        while (true) {
            $task = $this->redis->rpop($delayedQueue);
            if (!$task) break;
            
            $taskData = json_decode($task, true);
            
            if (time() >= ($taskData['retry_after'] ?? 0)) {
                $this->redis->lpush($this->queueName, $task);
                $processed++;
            } else {
                $this->redis->rpush($delayedQueue, $task);
                break;
            }
        }
        
        return $processed;
    }
    
    /**
     * Estatísticas da fila
     */
    public function stats() {
        return [
            'pending' => $this->redis->llen($this->queueName),
            'delayed' => $this->redis->llen($this->queueName . ':delayed'),
            'failed' => $this->redis->llen($this->queueName . ':failed')
        ];
    }
}
?>
