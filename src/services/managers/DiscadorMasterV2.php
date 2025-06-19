<?php
/**
 * Master Manager - Cérebro Principal do Sistema Discador v2.0
 * Coordena todos os workers e gerencia recursos do sistema
 * Versão compatível com Windows e Linux
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../DistributedLock.php';
require_once __DIR__ . '/../RedisManager.php';
require_once __DIR__ . '/../TaskQueue.php';

class DiscadorMaster {
    private $db;
    private $redisManager;
    private $lock;
    private $taskQueue;
    private $workers = [];
    private $isRunning = false;
    private $pid;
    private $startTime;
    private $isWindows;
    
    // Configurações
    const MAX_WORKERS = 10;
    const WORKER_TIMEOUT = 300; // 5 minutos
    const HEALTH_CHECK_INTERVAL = 30; // 30 segundos
    const STATS_UPDATE_INTERVAL = 60; // 1 minuto
    
    public function __construct() {
        $this->pid = getmypid();
        $this->startTime = time();
        $this->isWindows = (PHP_OS_FAMILY === 'Windows');
        
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->redisManager = RedisManager::getInstance();
            
            $this->lock = new DistributedLock();
            $this->taskQueue = new TaskQueue('discador_tasks');
            
            $this->setupSignalHandlers();
            $this->logMessage("DiscadorMaster iniciado - PID: {$this->pid} - OS: " . PHP_OS_FAMILY, 'info');
            
        } catch (Exception $e) {
            $this->logMessage("Erro na inicialização: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
    
    /**
     * Loop principal do Master
     */
    public function run(): void {
        if (!$this->acquireMasterLock()) {
            $this->logMessage("Não foi possível adquirir o lock de master. Outro master pode estar rodando.", 'error');
            exit(1);
        }
        
        $this->isRunning = true;
        $lastHealthCheck = 0;
        $lastStatsUpdate = 0;
        
        $this->logMessage("Master iniciado com sucesso", 'info');
        
        while ($this->isRunning) {
            $currentTime = time();
            
            // Health check dos workers
            if (($currentTime - $lastHealthCheck) >= self::HEALTH_CHECK_INTERVAL) {
                $this->performHealthCheck();
                $lastHealthCheck = $currentTime;
            }
            
            // Atualização de estatísticas
            if (($currentTime - $lastStatsUpdate) >= self::STATS_UPDATE_INTERVAL) {
                $this->updateStatistics();
                $lastStatsUpdate = $currentTime;
            }
            
            // Processa comandos
            $this->processCommands();
            
            // Processa retries de tarefas
            $this->taskQueue->processRetries();
            
            // Recupera tarefas órfãs
            $this->taskQueue->recoverOrphanTasks();
            
            // Mantém workers ativos
            $this->maintainWorkers();
            
            // Atualiza heartbeat do master
            $this->updateMasterHeartbeat();
            
            // Sleep curto para não sobrecarregar CPU
            sleep(1);
        }
        
        $this->shutdown();
    }
    
    /**
     * Inicia um worker específico
     */
    private function startWorker(string $type, int $workerId): bool {
        try {
            $workerClass = ucfirst($type) . 'Worker';
            $workerFile = __DIR__ . "/../workers/{$workerClass}.php";
            
            if (!file_exists($workerFile)) {
                $this->logMessage("Arquivo do worker não encontrado: $workerFile", 'error');
                return false;
            }
            
            $pid = $this->createWorkerProcess($workerFile, $workerId, $type);
            
            if ($pid) {
                $this->workers[$workerId] = [
                    'type' => $type,
                    'pid' => $pid,
                    'started_at' => time(),
                    'last_heartbeat' => time()
                ];
                
                // Registra worker no Redis
                $this->redisManager->hSet('active_workers', (string)$workerId, [
                    'type' => $type,
                    'pid' => $pid,
                    'started_at' => date('Y-m-d H:i:s'),
                    'master_pid' => $this->pid,
                    'hostname' => gethostname()
                ]);
                
                $this->logMessage("Worker $type #$workerId iniciado com PID $pid", 'info');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao iniciar worker $type #$workerId: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Cria processo do worker (compatível com Windows e Linux)
     */
    private function createWorkerProcess(string $workerFile, int $workerId, string $type): ?int {
        if ($this->isWindows) {
            // Windows - usar start com processo em background
            $command = "start /B php \"$workerFile\" $workerId";
            $process = proc_open(
                $command,
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ],
                $pipes,
                dirname($workerFile)
            );
            
            if (is_resource($process)) {
                $processInfo = proc_get_status($process);
                $pid = $processInfo['pid'] ?? null;
                
                // Fecha pipes
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                
                proc_close($process);
                return $pid;
            }
            
        } else {
            // Linux/Unix
            if (function_exists('pcntl_fork')) {
                // Usar fork se disponível
                $pid = pcntl_fork();
                
                if ($pid == -1) {
                    return null;
                } elseif ($pid == 0) {
                    // Processo filho - executa worker
                    $this->executeWorker($workerFile, $workerId, $type);
                    exit(0);
                } else {
                    // Processo pai - retorna PID
                    return $pid;
                }
            } else {
                // Fallback - usar exec em background
                $command = "php \"$workerFile\" $workerId > /dev/null 2>&1 & echo $!";
                $output = shell_exec($command);
                return $output ? (int)trim($output) : null;
            }
        }
        
        return null;
    }
    
    /**
     * Executa worker específico (chamado no processo filho)
     */
    private function executeWorker(string $workerFile, int $workerId, string $type): void {
        require_once $workerFile;
        
        try {
            switch ($type) {
                case 'campaign':
                    require_once __DIR__ . '/../workers/CampaignWorker.php';
                    $worker = new CampaignWorker($workerId);
                    break;
                case 'monitoring':
                    require_once __DIR__ . '/../workers/MonitoringWorker.php';
                    $worker = new MonitoringWorker($workerId);
                    break;
                case 'statistics':
                    // Worker de estatísticas será criado posteriormente
                    $this->logMessage("StatisticsWorker ainda não implementado", 'warning');
                    exit(1);
                    break;
                default:
                    $this->logMessage("Tipo de worker desconhecido: $type", 'error');
                    exit(1);
            }
            
            $worker->run();
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao executar worker $type #$workerId: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
    
    /**
     * Verifica saúde dos workers
     */
    private function performHealthCheck(): void {
        foreach ($this->workers as $workerId => $worker) {
            $heartbeatKey = "worker_heartbeat:$workerId";
            $lastHeartbeat = $this->redisManager->get($heartbeatKey);
            
            if ($lastHeartbeat) {
                $timeSinceHeartbeat = time() - strtotime($lastHeartbeat);
                
                if ($timeSinceHeartbeat > self::WORKER_TIMEOUT) {
                    $this->logMessage("Worker #{$workerId} não responde há {$timeSinceHeartbeat}s - reiniciando", 'warning');
                    $this->restartWorker($workerId);
                }
            } else {
                // Primeiro heartbeat perdido - aguarda mais um ciclo
                $timeSinceStart = time() - $worker['started_at'];
                if ($timeSinceStart > 60) { // 1 minuto de tolerância
                    $this->logMessage("Worker #{$workerId} nunca enviou heartbeat - reiniciando", 'warning');
                    $this->restartWorker($workerId);
                }
            }
        }
    }
    
    /**
     * Verifica se processo está rodando
     */
    private function isProcessRunning(int $pid): bool {
        if ($this->isWindows) {
            // Windows - usar tasklist
            $output = shell_exec("tasklist /FI \"PID eq $pid\" 2>NUL");
            return $output && strpos($output, (string)$pid) !== false;
        } else {
            // Linux/Unix
            return file_exists("/proc/$pid") || (function_exists('posix_kill') && posix_kill($pid, 0));
        }
    }
    
    /**
     * Para um worker
     */
    private function stopWorker(int $workerId): bool {
        if (!isset($this->workers[$workerId])) {
            return false;
        }
        
        $worker = $this->workers[$workerId];
        $pid = $worker['pid'];
        
        if ($this->isWindows) {
            // Windows - usar taskkill
            $result = shell_exec("taskkill /PID $pid /F 2>NUL");
            $success = $result !== null;
        } else {
            // Linux/Unix
            if (function_exists('posix_kill')) {
                $success = posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
            } else {
                $result = shell_exec("kill -TERM $pid 2>/dev/null");
                $success = $result !== null;
            }
        }
        
        if ($success) {
            // Remove do Redis
            $this->redisManager->hDel('active_workers', (string)$workerId);
            $this->redisManager->delete("worker_heartbeat:$workerId");
            
            // Remove da lista local
            unset($this->workers[$workerId]);
            
            $this->logMessage("Worker #{$workerId} parado", 'info');
        }
        
        return $success;
    }
    
    /**
     * Reinicia um worker
     */
    private function restartWorker(int $workerId): bool {
        $worker = $this->workers[$workerId] ?? null;
        
        if (!$worker) {
            return false;
        }
        
        $type = $worker['type'];
        
        // Para o worker atual
        $this->stopWorker($workerId);
        
        // Aguarda um pouco
        sleep(2);
        
        // Inicia novo worker
        return $this->startWorker($type, $workerId);
    }
    
    /**
     * Mantém número ideal de workers
     */
    private function maintainWorkers(): void {
        // Configuração de workers por tipo
        $workerConfig = [
            'campaign' => 3,
            'monitoring' => 2,
            // 'statistics' => 1
        ];
        
        foreach ($workerConfig as $type => $count) {
            $activeCount = $this->countWorkersByType($type);
            
            if ($activeCount < $count) {
                $needed = $count - $activeCount;
                $this->logMessage("Iniciando $needed worker(s) do tipo $type", 'info');
                
                for ($i = 0; $i < $needed; $i++) {
                    $workerId = $this->getNextWorkerId();
                    $this->startWorker($type, $workerId);
                }
            }
        }
    }
    
    /**
     * Conta workers por tipo
     */
    private function countWorkersByType(string $type): int {
        $count = 0;
        foreach ($this->workers as $worker) {
            if ($worker['type'] === $type) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Obtém próximo ID de worker disponível
     */
    private function getNextWorkerId(): int {
        $maxId = 0;
        foreach (array_keys($this->workers) as $workerId) {
            if ($workerId > $maxId) {
                $maxId = $workerId;
            }
        }
        return $maxId + 1;
    }
    
    /**
     * Atualiza estatísticas do sistema
     */
    private function updateStatistics(): void {
        try {
            $stats = [
                'master_pid' => $this->pid,
                'uptime' => time() - $this->startTime,
                'workers_active' => count($this->workers),
                'workers_by_type' => [],
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'last_update' => date('Y-m-d H:i:s')
            ];
            
            // Conta workers por tipo
            foreach ($this->workers as $worker) {
                $type = $worker['type'];
                $stats['workers_by_type'][$type] = ($stats['workers_by_type'][$type] ?? 0) + 1;
            }
            
            // Estatísticas da fila
            $queueStats = $this->taskQueue->getStats();
            $stats['queue'] = $queueStats;
            
            // Armazena no Redis
            $this->redisManager->set('master_stats', $stats, 300); // TTL 5 minutos
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao atualizar estatísticas: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Processa comandos de controle
     */
    private function processCommands(): void {
        $command = $this->redisManager->get('master_command');
        
        if ($command) {
            $this->redisManager->delete('master_command');
            $this->executeCommand($command);
        }
    }
    
    /**
     * Executa comando de controle
     */
    private function executeCommand(array $command): void {
        $action = $command['action'] ?? '';
        $params = $command['params'] ?? [];
        
        switch ($action) {
            case 'stop':
                $this->logMessage("Comando de parada recebido", 'info');
                $this->isRunning = false;
                break;
                
            case 'restart_worker':
                $workerId = (int)($params['worker_id'] ?? 0);
                if ($workerId && isset($this->workers[$workerId])) {
                    $this->restartWorker($workerId);
                }
                break;
                
            case 'add_worker':
                $type = $params['type'] ?? '';
                if ($type) {
                    $workerId = $this->getNextWorkerId();
                    $this->startWorker($type, $workerId);
                }
                break;
                
            case 'remove_worker':
                $workerId = (int)($params['worker_id'] ?? 0);
                if ($workerId && isset($this->workers[$workerId])) {
                    $this->stopWorker($workerId);
                }
                break;
                
            case 'clear_queue':
                $this->taskQueue->clear();
                $this->logMessage("Fila de tarefas limpa", 'info');
                break;
                
            default:
                $this->logMessage("Comando desconhecido: $action", 'warning');
        }
    }
    
    /**
     * Atualiza heartbeat do master
     */
    private function updateMasterHeartbeat(): void {
        $this->redisManager->set('master_heartbeat', date('Y-m-d H:i:s'), 120);
    }
    
    /**
     * Adquire lock de master
     */
    private function acquireMasterLock(): bool {
        return $this->lock->acquire('discador_master', 300); // TTL 5 minutos
    }
    
    /**
     * Configura handlers de sinal (apenas Unix/Linux)
     */
    private function setupSignalHandlers(): void {
        if (!$this->isWindows && function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGCHLD, [$this, 'handleSignal']);
        }
    }
    
    /**
     * Manipula sinais do sistema
     */
    public function handleSignal(int $signal): void {
        switch ($signal) {
            case defined('SIGTERM') ? SIGTERM : 15:
            case defined('SIGINT') ? SIGINT : 2:
                $this->logMessage("Sinal de parada recebido", 'info');
                $this->isRunning = false;
                break;
                
            case defined('SIGCHLD') ? SIGCHLD : 17:
                // Recolhe processos filho mortos
                if (function_exists('pcntl_waitpid')) {
                    $status = 0;
                    while (pcntl_waitpid(-1, $status, defined('WNOHANG') ? WNOHANG : 1) > 0);
                }
                break;
        }
    }
    
    /**
     * Shutdown graceful
     */
    private function shutdown(): void {
        $this->logMessage("Iniciando shutdown do master", 'info');
        
        // Para todos os workers
        foreach ($this->workers as $workerId => $worker) {
            $this->stopWorker($workerId);
        }
        
        // Libera lock
        $this->lock->release('discador_master');
        
        // Remove dados do master do Redis
        $this->redisManager->delete('master_heartbeat');
        $this->redisManager->delete('master_stats');
        
        $this->logMessage("Master finalizado", 'info');
    }
    
    /**
     * Log de mensagens
     */
    private function logMessage(string $message, string $level = 'info'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [MASTER] [$level] $message" . PHP_EOL;
        
        // Log em arquivo
        $logFile = __DIR__ . '/../../../logs/discador_master.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Log no Redis para dashboard
        $this->redisManager->set("master_log_latest", [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message
        ], 3600);
        
        // Console output
        echo $logEntry;
    }
    
    /**
     * Obtém status do master
     */
    public function getStatus(): array {
        return [
            'pid' => $this->pid,
            'uptime' => time() - $this->startTime,
            'is_running' => $this->isRunning,
            'workers_count' => count($this->workers),
            'workers' => $this->workers,
            'memory_usage' => memory_get_usage(true),
            'os' => PHP_OS_FAMILY
        ];
    }
    
    /**
     * Comando para parar o master externamente
     */
    public static function stopMaster(): bool {
        try {
            $redisManager = RedisManager::getInstance();
            return $redisManager->set('master_command', ['action' => 'stop']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Comando para reiniciar worker externamente
     */
    public static function restartWorker(int $workerId): bool {
        try {
            $redisManager = RedisManager::getInstance();
            return $redisManager->set('master_command', [
                'action' => 'restart_worker',
                'params' => ['worker_id' => $workerId]
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// CLI para execução direta
if (php_sapi_name() === 'cli' && __FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    $master = new DiscadorMaster();
    $master->run();
}
