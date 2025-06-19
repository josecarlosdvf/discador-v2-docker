<?php
/**
 * Script de Controle do Sistema Discador v2.0
 * Gerencia master e workers
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/services/RedisManager.php';

class DiscadorController {
    private $redisManager;
    
    public function __construct() {
        try {
            $this->redisManager = RedisManager::getInstance();
        } catch (Exception $e) {
            echo "Erro: Não foi possível conectar ao Redis - " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }
    
    public function run(array $args): void {
        if (count($args) < 2) {
            $this->showHelp();
            return;
        }
        
        $command = $args[1];
        
        switch ($command) {
            case 'start':
                $this->startMaster();
                break;
                
            case 'stop':
                $this->stopMaster();
                break;
                
            case 'status':
                $this->showStatus();
                break;
                
            case 'restart':
                $this->restartMaster();
                break;
                
            case 'workers':
                $this->manageWorkers($args);
                break;
                
            case 'queue':
                $this->manageQueue($args);
                break;
                
            case 'logs':
                $this->showLogs($args);
                break;
                
            case 'health':
                $this->healthCheck();
                break;
                
            case 'stats':
                $this->showStats();
                break;
                
            case 'clear':
                $this->clearData($args);
                break;
                
            default:
                echo "Comando desconhecido: $command" . PHP_EOL;
                $this->showHelp();
        }
    }
    
    private function startMaster(): void {
        echo "Verificando se master já está rodando..." . PHP_EOL;
        
        $masterHeartbeat = $this->redisManager->get('master_heartbeat');
        if ($masterHeartbeat) {
            $lastHeartbeat = strtotime($masterHeartbeat);
            if ((time() - $lastHeartbeat) < 120) {
                echo "Master já está rodando (último heartbeat: $masterHeartbeat)" . PHP_EOL;
                return;
            }
        }
        
        echo "Iniciando DiscadorMaster..." . PHP_EOL;
        
        $masterFile = __DIR__ . '/../src/services/managers/DiscadorMasterV2.php';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $command = "start /B php \"$masterFile\"";
            pclose(popen($command, 'r'));
        } else {
            $command = "php \"$masterFile\" > /dev/null 2>&1 &";
            exec($command);
        }
        
        // Aguarda inicialização
        sleep(3);
        
        $masterHeartbeat = $this->redisManager->get('master_heartbeat');
        if ($masterHeartbeat) {
            echo "Master iniciado com sucesso!" . PHP_EOL;
        } else {
            echo "Erro: Master não iniciou corretamente" . PHP_EOL;
        }
    }
    
    private function stopMaster(): void {
        echo "Parando DiscadorMaster..." . PHP_EOL;
        
        if ($this->redisManager->set('master_command', ['action' => 'stop'])) {
            echo "Comando de parada enviado" . PHP_EOL;
            
            // Aguarda parada
            for ($i = 0; $i < 30; $i++) {
                sleep(1);
                $heartbeat = $this->redisManager->get('master_heartbeat');
                if (!$heartbeat) {
                    echo "Master parado com sucesso!" . PHP_EOL;
                    return;
                }
            }
            
            echo "Master ainda está rodando - pode precisar ser forçado" . PHP_EOL;
        } else {
            echo "Erro ao enviar comando de parada" . PHP_EOL;
        }
    }
    
    private function restartMaster(): void {
        echo "Reiniciando DiscadorMaster..." . PHP_EOL;
        $this->stopMaster();
        sleep(2);
        $this->startMaster();
    }
    
    private function showStatus(): void {
        echo "=== Status do Sistema Discador ===" . PHP_EOL . PHP_EOL;
        
        // Status do Master
        $masterHeartbeat = $this->redisManager->get('master_heartbeat');
        if ($masterHeartbeat) {
            $lastHeartbeat = strtotime($masterHeartbeat);
            $timeSince = time() - $lastHeartbeat;
            
            echo "Master: ATIVO" . PHP_EOL;
            echo "Último heartbeat: $masterHeartbeat ($timeSince segundos atrás)" . PHP_EOL;
            
            $stats = $this->redisManager->get('master_stats');
            if ($stats) {
                echo "PID: " . $stats['master_pid'] . PHP_EOL;
                echo "Uptime: " . $this->formatUptime($stats['uptime']) . PHP_EOL;
                echo "Workers ativos: " . $stats['workers_active'] . PHP_EOL;
                echo "Uso de memória: " . $this->formatBytes($stats['memory_usage']) . PHP_EOL;
            }
        } else {
            echo "Master: INATIVO" . PHP_EOL;
        }
        
        echo PHP_EOL;
        
        // Status dos Workers
        $workers = $this->redisManager->hGetAll('active_workers');
        if (!empty($workers)) {
            echo "=== Workers Ativos ===" . PHP_EOL;
            foreach ($workers as $workerId => $workerData) {
                echo "Worker #$workerId:" . PHP_EOL;
                echo "  Tipo: " . $workerData['type'] . PHP_EOL;
                echo "  PID: " . $workerData['pid'] . PHP_EOL;
                echo "  Iniciado em: " . $workerData['started_at'] . PHP_EOL;
                
                $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
                if ($heartbeat) {
                    $timeSince = time() - strtotime($heartbeat);
                    echo "  Último heartbeat: $heartbeat ($timeSince segundos atrás)" . PHP_EOL;
                } else {
                    echo "  Heartbeat: AUSENTE" . PHP_EOL;
                }
                echo PHP_EOL;
            }
        } else {
            echo "Nenhum worker ativo" . PHP_EOL;
        }
        
        // Status da fila
        echo "=== Status da Fila ===" . PHP_EOL;
        $queueSizes = [
            'discador_tasks:high' => $this->redisManager->getQueueSize('discador_tasks:high'),
            'discador_tasks:normal' => $this->redisManager->getQueueSize('discador_tasks:normal'),
            'discador_tasks:low' => $this->redisManager->getQueueSize('discador_tasks:low')
        ];
        
        foreach ($queueSizes as $queue => $size) {
            echo "  $queue: $size tarefas" . PHP_EOL;
        }
        
        // Estatísticas Redis
        echo PHP_EOL . "=== Redis ===" . PHP_EOL;
        $redisHealth = $this->redisManager->healthCheck();
        echo "Status: " . ($redisHealth['connected'] ? 'CONECTADO' : 'DESCONECTADO') . PHP_EOL;
        echo "Uso de memória: " . $this->formatBytes($redisHealth['memory_usage']) . PHP_EOL;
        echo "Chaves: " . $redisHealth['keys_count'] . PHP_EOL;
    }
    
    private function manageWorkers(array $args): void {
        if (count($args) < 3) {
            echo "Uso: workers <add|remove|restart> [tipo] [worker_id]" . PHP_EOL;
            return;
        }
        
        $action = $args[2];
        
        switch ($action) {
            case 'add':
                $type = $args[3] ?? '';
                if (!$type) {
                    echo "Tipo de worker obrigatório (campaign, monitoring)" . PHP_EOL;
                    return;
                }
                
                if ($this->redisManager->set('master_command', [
                    'action' => 'add_worker',
                    'params' => ['type' => $type]
                ])) {
                    echo "Comando para adicionar worker $type enviado" . PHP_EOL;
                } else {
                    echo "Erro ao enviar comando" . PHP_EOL;
                }
                break;
                
            case 'remove':
                $workerId = (int)($args[3] ?? 0);
                if (!$workerId) {
                    echo "ID do worker obrigatório" . PHP_EOL;
                    return;
                }
                
                if ($this->redisManager->set('master_command', [
                    'action' => 'remove_worker',
                    'params' => ['worker_id' => $workerId]
                ])) {
                    echo "Comando para remover worker #$workerId enviado" . PHP_EOL;
                } else {
                    echo "Erro ao enviar comando" . PHP_EOL;
                }
                break;
                
            case 'restart':
                $workerId = (int)($args[3] ?? 0);
                if (!$workerId) {
                    echo "ID do worker obrigatório" . PHP_EOL;
                    return;
                }
                
                if ($this->redisManager->set('master_command', [
                    'action' => 'restart_worker',
                    'params' => ['worker_id' => $workerId]
                ])) {
                    echo "Comando para reiniciar worker #$workerId enviado" . PHP_EOL;
                } else {
                    echo "Erro ao enviar comando" . PHP_EOL;
                }
                break;
                
            default:
                echo "Ação desconhecida: $action" . PHP_EOL;
        }
    }
    
    private function manageQueue(array $args): void {
        if (count($args) < 3) {
            echo "Uso: queue <clear|stats|add>" . PHP_EOL;
            return;
        }
        
        $action = $args[2];
        
        switch ($action) {
            case 'clear':
                if ($this->redisManager->set('master_command', ['action' => 'clear_queue'])) {
                    echo "Comando para limpar fila enviado" . PHP_EOL;
                } else {
                    echo "Erro ao enviar comando" . PHP_EOL;
                }
                break;
                
            case 'stats':
                require_once __DIR__ . '/../src/services/TaskQueue.php';
                $taskQueue = new TaskQueue('discador_tasks');
                $stats = $taskQueue->getStats();
                
                echo "=== Estatísticas da Fila ===" . PHP_EOL;
                echo "Pendentes: " . $stats['pending'] . PHP_EOL;
                echo "Processando: " . $stats['processing'] . PHP_EOL;
                echo "Concluídas: " . $stats['completed'] . PHP_EOL;
                echo "Falhadas: " . $stats['failed'] . PHP_EOL;
                echo "Total enviadas: " . $stats['pushed'] . PHP_EOL;
                echo PHP_EOL;
                echo "Por prioridade:" . PHP_EOL;
                foreach ($stats['sizes'] as $priority => $size) {
                    echo "  $priority: $size" . PHP_EOL;
                }
                break;
                
            case 'add':
                // Exemplo de adição de tarefa
                require_once __DIR__ . '/../src/services/TaskQueue.php';
                $taskQueue = new TaskQueue('discador_tasks');
                
                $task = [
                    'type' => 'test_task',
                    'data' => ['message' => 'Tarefa de teste adicionada via CLI'],
                    'priority' => 'normal'
                ];
                
                if ($taskQueue->push($task)) {
                    echo "Tarefa de teste adicionada à fila" . PHP_EOL;
                } else {
                    echo "Erro ao adicionar tarefa" . PHP_EOL;
                }
                break;
                
            default:
                echo "Ação desconhecida: $action" . PHP_EOL;
        }
    }
    
    private function showLogs(array $args): void {
        $lines = (int)($args[2] ?? 20);
        $logFile = __DIR__ . '/../logs/discador_master.log';
        
        if (!file_exists($logFile)) {
            echo "Arquivo de log não encontrado: $logFile" . PHP_EOL;
            return;
        }
        
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec("powershell Get-Content \"$logFile\" -Tail $lines");
        } else {
            $output = shell_exec("tail -n $lines \"$logFile\"");
        }
        
        echo "=== Últimas $lines linhas do log ===" . PHP_EOL;
        echo $output;
    }
    
    private function healthCheck(): void {
        echo "=== Health Check Completo ===" . PHP_EOL . PHP_EOL;
        
        // Redis
        echo "Redis:" . PHP_EOL;
        $redisHealth = $this->redisManager->healthCheck();
        echo "  Conectado: " . ($redisHealth['connected'] ? 'SIM' : 'NÃO') . PHP_EOL;
        echo "  Ping: " . ($redisHealth['ping'] ? 'OK' : 'FALHA') . PHP_EOL;
        echo "  Memória: " . $this->formatBytes($redisHealth['memory_usage']) . PHP_EOL;
        echo "  Uptime: " . $this->formatUptime($redisHealth['uptime']) . PHP_EOL;
        echo PHP_EOL;
        
        // Banco de dados
        echo "Banco de dados:" . PHP_EOL;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT 1");
            echo "  Conexão: OK" . PHP_EOL;
            
            $stmt = $db->query("SELECT COUNT(*) as total FROM campanhas");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  Campanhas: " . $result['total'] . PHP_EOL;
            
        } catch (Exception $e) {
            echo "  Conexão: FALHA - " . $e->getMessage() . PHP_EOL;
        }
        echo PHP_EOL;
        
        // Sistema
        echo "Sistema:" . PHP_EOL;
        echo "  OS: " . PHP_OS_FAMILY . PHP_EOL;
        echo "  PHP: " . PHP_VERSION . PHP_EOL;
        echo "  Memória limite: " . ini_get('memory_limit') . PHP_EOL;
        echo "  Tempo execução: " . ini_get('max_execution_time') . "s" . PHP_EOL;
        
        // Extensões necessárias
        $extensions = ['pdo', 'pdo_mysql', 'json', 'redis'];
        echo "  Extensões:" . PHP_EOL;
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            echo "    $ext: " . ($loaded ? 'CARREGADA' : 'AUSENTE') . PHP_EOL;
        }
    }
    
    private function showStats(): void {
        $stats = $this->redisManager->get('master_stats');
        
        if (!$stats) {
            echo "Estatísticas não disponíveis (master pode não estar rodando)" . PHP_EOL;
            return;
        }
        
        echo "=== Estatísticas Detalhadas ===" . PHP_EOL . PHP_EOL;
        
        echo "Master:" . PHP_EOL;
        echo "  PID: " . $stats['master_pid'] . PHP_EOL;
        echo "  Uptime: " . $this->formatUptime($stats['uptime']) . PHP_EOL;
        echo "  Workers ativos: " . $stats['workers_active'] . PHP_EOL;
        echo "  Memória atual: " . $this->formatBytes($stats['memory_usage']) . PHP_EOL;
        echo "  Pico de memória: " . $this->formatBytes($stats['peak_memory']) . PHP_EOL;
        echo "  Última atualização: " . $stats['last_update'] . PHP_EOL;
        echo PHP_EOL;
        
        if (isset($stats['workers_by_type'])) {
            echo "Workers por tipo:" . PHP_EOL;
            foreach ($stats['workers_by_type'] as $type => $count) {
                echo "  $type: $count" . PHP_EOL;
            }
            echo PHP_EOL;
        }
        
        if (isset($stats['queue'])) {
            $queue = $stats['queue'];
            echo "Fila de tarefas:" . PHP_EOL;
            echo "  Pendentes: " . $queue['pending'] . PHP_EOL;
            echo "  Processando: " . $queue['processing'] . PHP_EOL;
            echo "  Concluídas: " . $queue['completed'] . PHP_EOL;
            echo "  Falhadas: " . $queue['failed'] . PHP_EOL;
            echo "  Total enviadas: " . $queue['pushed'] . PHP_EOL;
        }
    }
    
    private function clearData(array $args): void {
        if (count($args) < 3) {
            echo "Uso: clear <all|logs|stats|workers>" . PHP_EOL;
            return;
        }
        
        $type = $args[2];
        
        switch ($type) {
            case 'all':
                echo "Limpando todos os dados..." . PHP_EOL;
                $this->redisManager->delete('master_heartbeat');
                $this->redisManager->delete('master_stats');
                $this->redisManager->delete('active_workers');
                $this->clearWorkerData();
                echo "Dados limpos!" . PHP_EOL;
                break;
                
            case 'logs':
                $logFile = __DIR__ . '/../logs/discador_master.log';
                if (file_exists($logFile)) {
                    file_put_contents($logFile, '');
                    echo "Log limpo!" . PHP_EOL;
                } else {
                    echo "Arquivo de log não encontrado" . PHP_EOL;
                }
                break;
                
            case 'stats':
                $this->redisManager->delete('master_stats');
                echo "Estatísticas limpas!" . PHP_EOL;
                break;
                
            case 'workers':
                $this->clearWorkerData();
                echo "Dados dos workers limpos!" . PHP_EOL;
                break;
                
            default:
                echo "Tipo desconhecido: $type" . PHP_EOL;
        }
    }
    
    private function clearWorkerData(): void {
        // Limpa dados dos workers
        $workers = $this->redisManager->hGetAll('active_workers');
        foreach (array_keys($workers) as $workerId) {
            $this->redisManager->delete("worker_heartbeat:$workerId");
        }
        $this->redisManager->delete('active_workers');
    }
    
    private function formatUptime(int $seconds): string {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m {$secs}s";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m {$secs}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        } else {
            return "{$secs}s";
        }
    }
    
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function showHelp(): void {
        echo "Sistema Discador v2.0 - Controller" . PHP_EOL . PHP_EOL;
        echo "Uso: php discador_control.php <comando> [opções]" . PHP_EOL . PHP_EOL;
        echo "Comandos disponíveis:" . PHP_EOL;
        echo "  start              - Inicia o master" . PHP_EOL;
        echo "  stop               - Para o master" . PHP_EOL;
        echo "  restart            - Reinicia o master" . PHP_EOL;
        echo "  status             - Mostra status do sistema" . PHP_EOL;
        echo "  health             - Health check completo" . PHP_EOL;
        echo "  stats              - Estatísticas detalhadas" . PHP_EOL;
        echo "  workers <ação>     - Gerencia workers" . PHP_EOL;
        echo "    add <tipo>       - Adiciona worker" . PHP_EOL;
        echo "    remove <id>      - Remove worker" . PHP_EOL;
        echo "    restart <id>     - Reinicia worker" . PHP_EOL;
        echo "  queue <ação>       - Gerencia fila" . PHP_EOL;
        echo "    clear            - Limpa fila" . PHP_EOL;
        echo "    stats            - Estatísticas da fila" . PHP_EOL;
        echo "    add              - Adiciona tarefa teste" . PHP_EOL;
        echo "  logs [linhas]      - Mostra logs (padrão: 20)" . PHP_EOL;
        echo "  clear <tipo>       - Limpa dados" . PHP_EOL;
        echo "    all              - Limpa tudo" . PHP_EOL;
        echo "    logs             - Limpa logs" . PHP_EOL;
        echo "    stats            - Limpa estatísticas" . PHP_EOL;
        echo "    workers          - Limpa dados dos workers" . PHP_EOL;
        echo PHP_EOL;
        echo "Exemplos:" . PHP_EOL;
        echo "  php discador_control.php start" . PHP_EOL;
        echo "  php discador_control.php workers add campaign" . PHP_EOL;
        echo "  php discador_control.php logs 50" . PHP_EOL;
    }
}

// Execução
if (php_sapi_name() === 'cli') {
    $controller = new DiscadorController();
    $controller->run($argv);
} else {
    echo "Este script deve ser executado via CLI" . PHP_EOL;
}
