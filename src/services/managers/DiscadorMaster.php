<?php
/**
 * Master Manager - Cérebro Principal do Sistema Discador v2.0
 * Coordena todos os workers e gerencia recursos do sistema
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
    
    // Configurações
    const MAX_WORKERS = 10;
    const WORKER_TIMEOUT = 300; // 5 minutos
    const HEALTH_CHECK_INTERVAL = 30; // 30 segundos
    const STATS_UPDATE_INTERVAL = 60; // 1 minuto
    
    public function __construct() {
        $this->pid = getmypid();
        $this->startTime = time();
        
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->redisManager = RedisManager::getInstance();
            
            $this->lock = new DistributedLock();
            $this->taskQueue = new TaskQueue('discador_tasks');
            
            $this->setupSignalHandlers();
            $this->logMessage("DiscadorMaster iniciado - PID: {$this->pid}", 'info');
            
        } catch (Exception $e) {
            $this->logMessage("Erro na inicialização: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
    
    /**
     * Inicia o processo master
     */
    public function start() {
        // Tentar adquirir lock master
        $masterLock = $this->lock->acquire('discador_master', 3600);
        if (!$masterLock) {
            $this->logMessage("Outro processo master já está executando", 'warning');
            exit(0);
        }
        
        $this->isRunning = true;
        $this->logMessage("Master iniciado com PID {$this->pid}", 'info');
        
        // Registrar processo master
        $this->registerMaster();
        
        // Loop principal
        $lastHealthCheck = 0;
        $lastStatsUpdate = 0;
        
        while ($this->isRunning) {
            $currentTime = time();
            
            try {
                // Health check dos workers
                if ($currentTime - $lastHealthCheck >= self::HEALTH_CHECK_INTERVAL) {
                    $this->healthCheckWorkers();
                    $this->renewMasterLock();
                    $lastHealthCheck = $currentTime;
                }
                
                // Atualizar estatísticas
                if ($currentTime - $lastStatsUpdate >= self::STATS_UPDATE_INTERVAL) {
                    $this->updateSystemStats();
                    $lastStatsUpdate = $currentTime;
                }
                
                // Gerenciar workers
                $this->manageWorkers();
                
                // Processar comandos de controle
                $this->processControlCommands();
                
                // Aguardar próximo ciclo
                usleep(500000); // 0.5 segundos
                
            } catch (Exception $e) {
                $this->logMessage("Erro no loop principal: " . $e->getMessage(), 'error');
                usleep(5000000); // 5 segundos antes de tentar novamente
            }
        }
        
        $this->shutdown();
    }
    
    /**
     * Gerencia workers (criar, monitorar, destruir)
     */
    private function manageWorkers() {
        // Verificar campanhas ativas
        $activeCampaigns = $this->getActiveCampaigns();
        $neededWorkers = min(count($activeCampaigns), self::MAX_WORKERS);
        
        // Remover workers desnecessários
        $currentWorkers = count($this->workers);
        if ($currentWorkers > $neededWorkers) {
            $this->stopExcessWorkers($currentWorkers - $neededWorkers);
        }
        
        // Criar workers necessários
        if ($currentWorkers < $neededWorkers) {
            $this->startNewWorkers($neededWorkers - $currentWorkers);
        }
        
        // Verificar workers mortos
        $this->cleanupDeadWorkers();
    }
    
    /**
     * Inicia novos workers
     */
    private function startNewWorkers($count) {
        for ($i = 0; $i < $count; $i++) {
            $workerId = uniqid('worker_', true);
            $workerType = $this->selectWorkerType();
            
            $pid = pcntl_fork();
            
            if ($pid == -1) {
                $this->logMessage("Falha ao criar worker", 'error');
                continue;
                
            } elseif ($pid == 0) {
                // Processo filho (worker)
                $this->startWorkerProcess($workerId, $workerType);
                exit(0);
                
            } else {
                // Processo pai (master)
                $this->workers[$workerId] = [
                    'pid' => $pid,
                    'type' => $workerType,
                    'started_at' => time(),
                    'last_heartbeat' => time(),
                    'status' => 'starting'
                ];
                
                $this->logMessage("Worker {$workerId} ({$workerType}) iniciado com PID {$pid}", 'info');
            }
        }
    }
    
    /**
     * Inicia processo worker
     */
    private function startWorkerProcess($workerId, $workerType) {
        // Redefinir título do processo
        cli_set_process_title("discador-worker-{$workerType}");
        
        switch ($workerType) {
            case 'campaign':
                require_once __DIR__ . '/workers/CampaignWorker.php';
                $worker = new CampaignWorker($workerId);
                break;
                
            case 'monitoring':
                require_once __DIR__ . '/workers/MonitoringWorker.php';
                $worker = new MonitoringWorker($workerId);
                break;
                
            case 'statistics':
                require_once __DIR__ . '/workers/StatisticsWorker.php';
                $worker = new StatisticsWorker($workerId);
                break;
                
            default:
                $this->logMessage("Tipo de worker desconhecido: {$workerType}", 'error');
                return;
        }
        
        $worker->run();
    }
    
    /**
     * Seleciona tipo de worker baseado na necessidade
     */
    private function selectWorkerType() {
        $campaignWorkers = $this->countWorkersByType('campaign');
        $monitoringWorkers = $this->countWorkersByType('monitoring');
        $statsWorkers = $this->countWorkersByType('statistics');
        
        // Sempre ter pelo menos 1 worker de monitoramento
        if ($monitoringWorkers == 0) {
            return 'monitoring';
        }
        
        // Sempre ter pelo menos 1 worker de estatísticas
        if ($statsWorkers == 0) {
            return 'statistics';
        }
        
        // Resto são workers de campanha
        return 'campaign';
    }
    
    /**
     * Conta workers por tipo
     */
    private function countWorkersByType($type) {
        $count = 0;
        foreach ($this->workers as $worker) {
            if ($worker['type'] === $type) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Health check dos workers
     */
    private function healthCheckWorkers() {
        $currentTime = time();
        
        foreach ($this->workers as $workerId => $worker) {
            // Verificar se processo ainda existe
            if (!$this->isProcessRunning($worker['pid'])) {
                $this->logMessage("Worker {$workerId} (PID {$worker['pid']}) morreu", 'warning');
                unset($this->workers[$workerId]);
                continue;
            }
            
            // Verificar heartbeat
            $heartbeatKey = "worker:heartbeat:{$workerId}";
            $lastHeartbeat = $this->redis->get($heartbeatKey);
            
            if ($lastHeartbeat && ($currentTime - $lastHeartbeat) > self::WORKER_TIMEOUT) {
                $this->logMessage("Worker {$workerId} sem resposta há " . ($currentTime - $lastHeartbeat) . " segundos", 'warning');
                $this->stopWorker($workerId);
            }
        }
    }
    
    /**
     * Verifica se processo está rodando
     */
    private function isProcessRunning($pid) {
        return file_exists("/proc/{$pid}") || posix_kill($pid, 0);
    }
    
    /**
     * Para workers excessivos
     */
    private function stopExcessWorkers($count) {
        $stopped = 0;
        foreach ($this->workers as $workerId => $worker) {
            if ($stopped >= $count) break;
            
            if ($worker['type'] === 'campaign') { // Parar campanhas primeiro
                $this->stopWorker($workerId);
                $stopped++;
            }
        }
    }
    
    /**
     * Para um worker específico
     */
    private function stopWorker($workerId) {
        if (!isset($this->workers[$workerId])) {
            return false;
        }
        
        $worker = $this->workers[$workerId];
        
        // Sinal graceful
        posix_kill($worker['pid'], SIGTERM);
        
        // Aguardar 5 segundos
        sleep(5);
        
        // Forçar se necessário
        if ($this->isProcessRunning($worker['pid'])) {
            posix_kill($worker['pid'], SIGKILL);
            $this->logMessage("Worker {$workerId} forçadamente terminado", 'warning');
        } else {
            $this->logMessage("Worker {$workerId} terminado graciosamente", 'info');
        }
        
        unset($this->workers[$workerId]);
        return true;
    }
    
    /**
     * Remove workers mortos da lista
     */
    private function cleanupDeadWorkers() {
        foreach ($this->workers as $workerId => $worker) {
            if (!$this->isProcessRunning($worker['pid'])) {
                $this->logMessage("Removendo worker morto {$workerId}", 'info');
                unset($this->workers[$workerId]);
            }
        }
    }
    
    /**
     * Obtém campanhas ativas
     */
    private function getActiveCampaigns() {
        $stmt = $this->db->query("
            SELECT id, nome, status, max_canais, ativo
            FROM campanhas 
            WHERE ativo = 1 
            AND status IN ('running', 'starting')
            ORDER BY prioridade DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Renova lock master
     */
    private function renewMasterLock() {
        if (!$this->lock->renew('discador_master', 3600)) {
            $this->logMessage("Falha ao renovar lock master", 'error');
            $this->isRunning = false;
        }
    }
    
    /**
     * Registra processo master
     */
    private function registerMaster() {
        $masterData = [
            'pid' => $this->pid,
            'hostname' => gethostname(),
            'started_at' => $this->startTime,
            'last_update' => time()
        ];
        
        $this->redis->setex('discador:master', 3600, json_encode($masterData));
    }
    
    /**
     * Atualiza estatísticas do sistema
     */
    private function updateSystemStats() {
        $stats = [
            'master_pid' => $this->pid,
            'workers_count' => count($this->workers),
            'workers_by_type' => [
                'campaign' => $this->countWorkersByType('campaign'),
                'monitoring' => $this->countWorkersByType('monitoring'),
                'statistics' => $this->countWorkersByType('statistics')
            ],
            'uptime' => time() - $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'last_update' => time()
        ];
        
        $this->redis->setex('discador:stats', 300, json_encode($stats));
        
        // Registrar em banco para histórico
        $stmt = $this->db->prepare("
            INSERT INTO system_stats (
                workers_count, memory_usage, cpu_usage, uptime, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $cpuUsage = $this->getCpuUsage();
        $stmt->execute([
            count($this->workers),
            $stats['memory_usage'],
            $cpuUsage,
            $stats['uptime']
        ]);
    }
    
    /**
     * Obtém uso de CPU
     */
    private function getCpuUsage() {
        $load = sys_getloadavg();
        return $load ? round($load[0] * 100, 2) : 0;
    }
    
    /**
     * Processa comandos de controle
     */
    private function processControlCommands() {
        $command = $this->redis->rpop('discador:commands');
        if (!$command) return;
        
        $cmd = json_decode($command, true);
        if (!$cmd) return;
        
        $this->logMessage("Processando comando: " . $cmd['action'], 'info');
        
        switch ($cmd['action']) {
            case 'stop':
                $this->isRunning = false;
                break;
                
            case 'restart_workers':
                $this->restartAllWorkers();
                break;
                
            case 'reload_config':
                $this->reloadConfiguration();
                break;
                
            case 'status':
                $this->sendStatusReport();
                break;
        }
    }
    
    /**
     * Reinicia todos os workers
     */
    private function restartAllWorkers() {
        $this->logMessage("Reiniciando todos os workers", 'info');
        
        foreach ($this->workers as $workerId => $worker) {
            $this->stopWorker($workerId);
        }
        
        $this->workers = [];
    }
    
    /**
     * Recarrega configuração
     */
    private function reloadConfiguration() {
        $this->logMessage("Recarregando configuração", 'info');
        // Implementar reload de configurações
    }
    
    /**
     * Envia relatório de status
     */
    private function sendStatusReport() {
        $status = [
            'master_pid' => $this->pid,
            'uptime' => time() - $this->startTime,
            'workers' => $this->workers,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time()
        ];
        
        $this->redis->setex('discador:status_report', 60, json_encode($status));
    }
    
    /**
     * Configura handlers de sinais
     */
    private function setupSignalHandlers() {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGCHLD, [$this, 'handleSignal']);
    }
    
    /**
     * Manipula sinais do sistema
     */
    public function handleSignal($signal) {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->logMessage("Recebido sinal de parada", 'info');
                $this->isRunning = false;
                break;
                
            case SIGCHLD:
                // Recolher processos filhos mortos
                while (pcntl_waitpid(-1, $status, WNOHANG) > 0);
                break;
        }
    }
    
    /**
     * Shutdown gracioso
     */
    private function shutdown() {
        $this->logMessage("Iniciando shutdown", 'info');
        
        // Parar todos os workers
        foreach ($this->workers as $workerId => $worker) {
            $this->stopWorker($workerId);
        }
        
        // Liberar lock master
        $this->lock->release('discador_master');
        
        // Remover registro master
        $this->redis->del('discador:master');
        
        $this->logMessage("Shutdown concluído", 'info');
    }
    
    /**
     * Log de mensagens
     */
    private function logMessage($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $pid = $this->pid;
        $logLine = "[{$timestamp}] [MASTER:{$pid}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents('/var/log/discador/master.log', $logLine, FILE_APPEND | LOCK_EX);
        
        if ($level === 'error') {
            error_log($logLine);
        }
    }
}

// Verificar se está sendo executado diretamente
if (basename($_SERVER['SCRIPT_NAME']) === 'DiscadorMaster.php') {
    // Configurar título do processo
    cli_set_process_title('discador-master');
    
    // Iniciar master
    $master = new DiscadorMaster();
    $master->start();
}
?>
