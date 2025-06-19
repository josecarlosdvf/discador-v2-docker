<?php
/**
 * Task Queue - Sistema de Filas de Tarefas Distribuídas
 * Gerencia filas de tarefas para workers usando Redis
 */

declare(strict_types=1);

require_once __DIR__ . '/RedisManager.php';

class TaskQueue {
    private $redisManager;
    private $queueName;
    private $priorityQueues = [];
    
    const PRIORITY_HIGH = 'high';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_LOW = 'low';
    
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 30; // segundos
    
    public function __construct(string $queueName) {
        $this->queueName = $queueName;
        $this->redisManager = RedisManager::getInstance();
        
        // Define filas por prioridade
        $this->priorityQueues = [
            self::PRIORITY_HIGH => $queueName . ':high',
            self::PRIORITY_NORMAL => $queueName . ':normal', 
            self::PRIORITY_LOW => $queueName . ':low'
        ];
    }
    
    /**
     * Adiciona uma tarefa na fila
     */
    public function push(array $taskData, string $priority = self::PRIORITY_NORMAL): bool {
        try {
            $task = [
                'id' => uniqid('task_', true),
                'created_at' => date('Y-m-d H:i:s'),
                'priority' => $priority,
                'retries' => 0,
                'max_retries' => self::MAX_RETRIES,
                'data' => $taskData
            ];
            
            $queue = $this->priorityQueues[$priority] ?? $this->priorityQueues[self::PRIORITY_NORMAL];
            
            if ($this->redisManager->pushTask($queue, $task)) {
                // Atualiza estatísticas
                $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:pushed");
                $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:pending");
                
                $this->logTask($task['id'], 'pushed', $priority);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao adicionar tarefa - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recupera uma tarefa da fila (respeitando prioridades)
     */
    public function pop(int $timeout = 10): ?array {
        try {
            // Verifica filas por ordem de prioridade
            $queues = [
                $this->priorityQueues[self::PRIORITY_HIGH],
                $this->priorityQueues[self::PRIORITY_NORMAL],
                $this->priorityQueues[self::PRIORITY_LOW]
            ];
            
            foreach ($queues as $queue) {
                $task = $this->redisManager->popTask($queue, 1); // timeout baixo para verificar próxima fila
                if ($task) {
                    // Marca como processando
                    $this->markAsProcessing($task);
                    $this->redisManager->decrementCounter("queue_stats:{$this->queueName}:pending");
                    $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:processing");
                    
                    $this->logTask($task['id'], 'popped', $task['data']['priority'] ?? 'normal');
                    return $task;
                }
            }
            
            // Se não há tarefas em nenhuma fila, aguarda com timeout completo na fila normal
            $task = $this->redisManager->popTask($this->priorityQueues[self::PRIORITY_NORMAL], $timeout);
            if ($task) {
                $this->markAsProcessing($task);
                $this->redisManager->decrementCounter("queue_stats:{$this->queueName}:pending");
                $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:processing");
                
                $this->logTask($task['id'], 'popped', $task['data']['priority'] ?? 'normal');
            }
            
            return $task;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao recuperar tarefa - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Marca tarefa como concluída
     */
    public function complete(array $task, array $result = []): bool {
        try {
            $taskId = $task['id'];
            
            // Remove da lista de processamento
            $this->redisManager->hDel("queue_processing:{$this->queueName}", $taskId);
            
            // Armazena resultado se fornecido
            if (!empty($result)) {
                $this->redisManager->hSet("queue_results:{$this->queueName}", $taskId, [
                    'task_id' => $taskId,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'result' => $result
                ]);
                
                // Define TTL para resultados (24 horas)
                $this->redisManager->setExpire("queue_results:{$this->queueName}", 86400);
            }
            
            // Atualiza estatísticas
            $this->redisManager->decrementCounter("queue_stats:{$this->queueName}:processing");
            $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:completed");
            
            $this->logTask($taskId, 'completed');
            return true;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao marcar tarefa como concluída - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca tarefa como falhada e agenda retry se possível
     */
    public function fail(array $task, string $error = ''): bool {
        try {
            $taskId = $task['id'];
            $retries = $task['retries'] ?? 0;
            $maxRetries = $task['max_retries'] ?? self::MAX_RETRIES;
            
            // Remove da lista de processamento
            $this->redisManager->hDel("queue_processing:{$this->queueName}", $taskId);
            
            if ($retries < $maxRetries) {
                // Agenda retry
                $task['retries'] = $retries + 1;
                $task['last_error'] = $error;
                $task['retry_at'] = date('Y-m-d H:i:s', time() + (int)(self::RETRY_DELAY * $task['retries']));
                
                // Adiciona na fila de retry
                $this->scheduleRetry($task);
                
                $this->logTask($taskId, 'retry_scheduled', null, $error);
                
            } else {
                // Falha definitiva - armazena na lista de falhas
                $this->redisManager->hSet("queue_failed:{$this->queueName}", $taskId, [
                    'task' => $task,
                    'failed_at' => date('Y-m-d H:i:s'),
                    'error' => $error,
                    'retries' => $retries
                ]);
                
                // Define TTL para falhas (7 dias)
                $this->redisManager->setExpire("queue_failed:{$this->queueName}", 604800);
                
                $this->redisManager->incrementCounter("queue_stats:{$this->queueName}:failed");
                $this->logTask($taskId, 'failed', null, $error);
            }
            
            $this->redisManager->decrementCounter("queue_stats:{$this->queueName}:processing");
            return true;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao marcar tarefa como falhada - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Processa tarefas agendadas para retry
     */
    public function processRetries(): int {
        try {
            $retryQueue = "queue_retry:{$this->queueName}";
            $now = date('Y-m-d H:i:s');
            $processed = 0;
            
            // Recupera tarefas agendadas para retry
            $retryTasks = $this->redisManager->hGetAll($retryQueue);
            
            foreach ($retryTasks as $taskId => $taskData) {
                $task = is_array($taskData) ? $taskData : json_decode($taskData, true);
                
                if (isset($task['retry_at']) && $task['retry_at'] <= $now) {
                    // Remove da fila de retry
                    $this->redisManager->hDel($retryQueue, $taskId);
                    
                    // Recoloca na fila principal
                    $priority = $task['data']['priority'] ?? self::PRIORITY_NORMAL;
                    if ($this->push($task['data'], $priority)) {
                        $processed++;
                        $this->logTask($taskId, 'retrying');
                    }
                }
            }
            
            return $processed;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao processar retries - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtém estatísticas da fila
     */
    public function getStats(): array {
        try {
            return [
                'queue_name' => $this->queueName,
                'pending' => $this->redisManager->getCounter("queue_stats:{$this->queueName}:pending"),
                'processing' => $this->redisManager->getCounter("queue_stats:{$this->queueName}:processing"),
                'completed' => $this->redisManager->getCounter("queue_stats:{$this->queueName}:completed"),
                'failed' => $this->redisManager->getCounter("queue_stats:{$this->queueName}:failed"),
                'pushed' => $this->redisManager->getCounter("queue_stats:{$this->queueName}:pushed"),
                'sizes' => [
                    'high' => $this->redisManager->getQueueSize($this->priorityQueues[self::PRIORITY_HIGH]),
                    'normal' => $this->redisManager->getQueueSize($this->priorityQueues[self::PRIORITY_NORMAL]),
                    'low' => $this->redisManager->getQueueSize($this->priorityQueues[self::PRIORITY_LOW])
                ]
            ];
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao obter estatísticas - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Limpa filas e estatísticas
     */
    public function clear(): bool {
        try {
            // Limpa todas as filas
            foreach ($this->priorityQueues as $queue) {
                $this->redisManager->delete($queue);
            }
            
            // Limpa dados auxiliares
            $this->redisManager->delete("queue_processing:{$this->queueName}");
            $this->redisManager->delete("queue_retry:{$this->queueName}");
            $this->redisManager->delete("queue_results:{$this->queueName}");
            $this->redisManager->delete("queue_failed:{$this->queueName}");
            
            // Limpa estatísticas
            $statsKeys = [
                "queue_stats:{$this->queueName}:pending",
                "queue_stats:{$this->queueName}:processing", 
                "queue_stats:{$this->queueName}:completed",
                "queue_stats:{$this->queueName}:failed",
                "queue_stats:{$this->queueName}:pushed"
            ];
            
            foreach ($statsKeys as $key) {
                $this->redisManager->delete($key);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao limpar fila - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recupera tarefas órfãs (processando há muito tempo)
     */
    public function recoverOrphanTasks(int $timeoutMinutes = 10): int {
        try {
            $processingQueue = "queue_processing:{$this->queueName}";
            $processingTasks = $this->redisManager->hGetAll($processingQueue);
            $timeoutSeconds = $timeoutMinutes * 60;
            $recovered = 0;
            
            foreach ($processingTasks as $taskId => $taskData) {
                $task = is_array($taskData) ? $taskData : json_decode($taskData, true);
                
                if (isset($task['started_at'])) {
                    $startTime = strtotime($task['started_at']);
                    if ((time() - $startTime) > $timeoutSeconds) {
                        // Remove da lista de processamento
                        $this->redisManager->hDel($processingQueue, $taskId);
                        
                        // Recoloca na fila (incrementa tentativas)
                        $task['retries'] = ($task['retries'] ?? 0) + 1;
                        $task['recovered'] = true;
                        $task['recovered_at'] = date('Y-m-d H:i:s');
                        
                        $priority = $task['data']['priority'] ?? self::PRIORITY_NORMAL;
                        if ($this->push($task['data'], $priority)) {
                            $recovered++;
                            $this->logTask($taskId, 'recovered');
                        }
                    }
                }
            }
            
            return $recovered;
            
        } catch (Exception $e) {
            error_log("TaskQueue: Erro ao recuperar tarefas órfãs - " . $e->getMessage());
            return 0;
        }
    }
    
    // Métodos auxiliares privados
    
    private function markAsProcessing(array $task): void {
        $task['started_at'] = date('Y-m-d H:i:s');
        $task['worker_pid'] = getmypid();
        $task['worker_host'] = gethostname();
        
        $this->redisManager->hSet("queue_processing:{$this->queueName}", $task['id'], $task);
    }
    
    private function scheduleRetry(array $task): void {
        $this->redisManager->hSet("queue_retry:{$this->queueName}", $task['id'], $task);
    }
    
    private function logTask(string $taskId, string $action, string $priority = null, string $error = null): void {
        $logData = [
            'task_id' => $taskId,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
            'queue' => $this->queueName
        ];
        
        if ($priority) {
            $logData['priority'] = $priority;
        }
        
        if ($error) {
            $logData['error'] = $error;
        }
        
        error_log("TaskQueue: " . json_encode($logData));
    }
}
