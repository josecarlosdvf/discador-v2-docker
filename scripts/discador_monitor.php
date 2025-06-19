<?php
/**
 * Monitor do Sistema Discador v2.0
 * Dashboard em tempo real do sistema
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/services/RedisManager.php';

class DiscadorMonitor {
    private $redisManager;
    private $db;
    
    public function __construct() {
        try {
            $this->redisManager = RedisManager::getInstance();
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            echo "Erro de inicialização: " . $e->getMessage() . PHP_EOL;
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
            case 'watch':
                $this->watchSystem($args);
                break;
                
            case 'dashboard':
                $this->showDashboard();
                break;
                
            case 'campaigns':
                $this->showCampaigns();
                break;
                
            case 'calls':
                $this->showCalls();
                break;
                
            case 'performance':
                $this->showPerformance();
                break;
                
            case 'alerts':
                $this->checkAlerts();
                break;
                
            case 'export':
                $this->exportStats($args);
                break;
                
            default:
                echo "Comando desconhecido: $command" . PHP_EOL;
                $this->showHelp();
        }
    }
    
    /**
     * Monitoramento em tempo real
     */
    private function watchSystem(array $args): void {
        $interval = (int)($args[2] ?? 5);
        
        echo "Monitoramento em tempo real (intervalo: {$interval}s)" . PHP_EOL;
        echo "Pressione Ctrl+C para parar" . PHP_EOL . PHP_EOL;
        
        while (true) {
            $this->clearScreen();
            $this->showDashboard();
            sleep($interval);
        }
    }
    
    /**
     * Dashboard principal
     */
    private function showDashboard(): void {
        $timestamp = date('Y-m-d H:i:s');
        echo "=== DASHBOARD DISCADOR v2.0 - $timestamp ===" . PHP_EOL . PHP_EOL;
        
        // Status geral
        $this->showSystemStatus();
        echo PHP_EOL;
        
        // Workers
        $this->showWorkersStatus();
        echo PHP_EOL;
        
        // Campanhas ativas
        $this->showActiveCampaigns();
        echo PHP_EOL;
        
        // Métricas de performance
        $this->showQuickMetrics();
    }
    
    private function showSystemStatus(): void {
        echo "📊 SISTEMA:" . PHP_EOL;
        
        // Master
        $masterHeartbeat = $this->redisManager->get('master_heartbeat');
        $masterStatus = $masterHeartbeat ? '🟢 ATIVO' : '🔴 INATIVO';
        echo "  Master: $masterStatus";
        
        if ($masterHeartbeat) {
            $timeSince = time() - strtotime($masterHeartbeat);
            echo " (último heartbeat: {$timeSince}s atrás)";
            
            $stats = $this->redisManager->get('master_stats');
            if ($stats) {
                echo " | Uptime: " . $this->formatUptime($stats['uptime']);
                echo " | Memória: " . $this->formatBytes($stats['memory_usage']);
            }
        }
        echo PHP_EOL;
        
        // Redis
        $redisHealth = $this->redisManager->healthCheck();
        $redisStatus = $redisHealth['connected'] && $redisHealth['ping'] ? '🟢 OK' : '🔴 FALHA';
        echo "  Redis: $redisStatus";
        if ($redisHealth['connected']) {
            echo " | Memória: " . $this->formatBytes($redisHealth['memory_usage']);
            echo " | Chaves: " . $redisHealth['keys_count'];
        }
        echo PHP_EOL;
        
        // Banco
        try {
            $this->db->query("SELECT 1");
            echo "  Banco: 🟢 OK" . PHP_EOL;
        } catch (Exception $e) {
            echo "  Banco: 🔴 FALHA" . PHP_EOL;
        }
    }
    
    private function showWorkersStatus(): void {
        echo "👷 WORKERS:" . PHP_EOL;
        
        $workers = $this->redisManager->hGetAll('active_workers');
        if (empty($workers)) {
            echo "  Nenhum worker ativo" . PHP_EOL;
            return;
        }
        
        $workersByType = [];
        $healthyWorkers = 0;
        
        foreach ($workers as $workerId => $workerData) {
            $type = $workerData['type'];
            $workersByType[$type] = ($workersByType[$type] ?? 0) + 1;
            
            // Verifica health
            $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
            if ($heartbeat && (time() - strtotime($heartbeat)) < 120) {
                $healthyWorkers++;
            }
        }
        
        echo "  Total: " . count($workers) . " ativo(s) | Saudáveis: $healthyWorkers" . PHP_EOL;
        echo "  Por tipo: ";
        foreach ($workersByType as $type => $count) {
            echo "$type($count) ";
        }
        echo PHP_EOL;
        
        // Mostra workers com problemas
        foreach ($workers as $workerId => $workerData) {
            $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
            if (!$heartbeat || (time() - strtotime($heartbeat)) > 120) {
                $status = !$heartbeat ? 'SEM HEARTBEAT' : 'TIMEOUT';
                echo "    ⚠️  Worker #$workerId ({$workerData['type']}): $status" . PHP_EOL;
            }
        }
    }
    
    private function showActiveCampaigns(): void {
        echo "📞 CAMPANHAS:" . PHP_EOL;
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    id, 
                    nome, 
                    status, 
                    tentativas_por_numero,
                    DATE_FORMAT(data_inicio, '%H:%i') as inicio,
                    DATE_FORMAT(data_fim, '%H:%i') as fim,
                    (SELECT COUNT(*) FROM hopper h WHERE h.campanha_id = c.id AND h.status = 'READY') as prontos,
                    (SELECT COUNT(*) FROM hopper h WHERE h.campanha_id = c.id AND h.status = 'CALLING') as discando,
                    (SELECT COUNT(*) FROM hopper h WHERE h.campanha_id = c.id) as total_contatos
                FROM campanhas c 
                WHERE c.status = 'ATIVA' 
                ORDER BY c.id DESC 
                LIMIT 5
            ");
            
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($campaigns)) {
                echo "  Nenhuma campanha ativa" . PHP_EOL;
                return;
            }
            
            foreach ($campaigns as $campaign) {
                $progress = $campaign['total_contatos'] > 0 
                    ? round((($campaign['total_contatos'] - $campaign['prontos']) / $campaign['total_contatos']) * 100, 1)
                    : 0;
                
                echo "  📋 #{$campaign['id']} {$campaign['nome']}" . PHP_EOL;
                echo "     Horário: {$campaign['inicio']}-{$campaign['fim']} | ";
                echo "Progresso: {$progress}% | ";
                echo "Prontos: {$campaign['prontos']} | ";
                echo "Discando: {$campaign['discando']}" . PHP_EOL;
            }
            
        } catch (Exception $e) {
            echo "  Erro ao consultar campanhas: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function showQuickMetrics(): void {
        echo "📈 MÉTRICAS (últimos 60 min):" . PHP_EOL;
        
        try {
            // Chamadas por hora
            $stmt = $this->db->query("
                SELECT COUNT(*) as total_calls
                FROM cdr 
                WHERE calldate >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $callsHour = $stmt->fetch(PDO::FETCH_ASSOC)['total_calls'];
            
            // Chamadas atendidas
            $stmt = $this->db->query("
                SELECT COUNT(*) as answered_calls
                FROM cdr 
                WHERE calldate >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND disposition = 'ANSWERED'
            ");
            $answeredHour = $stmt->fetch(PDO::FETCH_ASSOC)['answered_calls'];
            
            // Taxa de conexão
            $connectionRate = $callsHour > 0 ? round(($answeredHour / $callsHour) * 100, 1) : 0;
            
            echo "  📞 Chamadas: $callsHour | ✅ Atendidas: $answeredHour | 📊 Taxa: {$connectionRate}%" . PHP_EOL;
            
            // Fila de tarefas
            require_once __DIR__ . '/../src/services/TaskQueue.php';
            $taskQueue = new TaskQueue('discador_tasks');
            $queueStats = $taskQueue->getStats();
            
            echo "  📝 Fila: {$queueStats['pending']} pendentes | ";
            echo "⚙️ Processando: {$queueStats['processing']} | ";
            echo "✅ Concluídas: {$queueStats['completed']}" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "  Erro ao calcular métricas: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * Mostra estatísticas de campanhas
     */
    private function showCampaigns(): void {
        echo "=== RELATÓRIO DE CAMPANHAS ===" . PHP_EOL . PHP_EOL;
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    c.id,
                    c.nome,
                    c.status,
                    c.data_inicio,
                    c.data_fim,
                    c.tentativas_por_numero,
                    COUNT(DISTINCT h.id) as total_contatos,
                    COUNT(DISTINCT CASE WHEN h.status = 'READY' THEN h.id END) as prontos,
                    COUNT(DISTINCT CASE WHEN h.status = 'CALLING' THEN h.id END) as discando,
                    COUNT(DISTINCT CASE WHEN h.status = 'COMPLETED' THEN h.id END) as completos,
                    COUNT(DISTINCT CASE WHEN h.status = 'FAILED' THEN h.id END) as falhados
                FROM campanhas c
                LEFT JOIN hopper h ON c.id = h.campanha_id
                WHERE c.data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY c.id
                ORDER BY c.id DESC
            ");
            
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($campaigns as $campaign) {
                $progress = $campaign['total_contatos'] > 0 
                    ? round((($campaign['completos'] + $campaign['falhados']) / $campaign['total_contatos']) * 100, 1)
                    : 0;
                
                echo "Campanha #{$campaign['id']}: {$campaign['nome']}" . PHP_EOL;
                echo "  Status: {$campaign['status']}" . PHP_EOL;
                echo "  Período: {$campaign['data_inicio']} - {$campaign['data_fim']}" . PHP_EOL;
                echo "  Progresso: {$progress}%" . PHP_EOL;
                echo "  Contatos: {$campaign['total_contatos']} total | ";
                echo "{$campaign['prontos']} prontos | ";
                echo "{$campaign['discando']} discando | ";
                echo "{$campaign['completos']} completos | ";
                echo "{$campaign['falhados']} falhados" . PHP_EOL;
                echo "  Max tentativas: {$campaign['tentativas_por_numero']}" . PHP_EOL;
                echo PHP_EOL;
            }
            
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * Estatísticas de chamadas
     */
    private function showCalls(): void {
        echo "=== ESTATÍSTICAS DE CHAMADAS ===" . PHP_EOL . PHP_EOL;
        
        try {
            // Estatísticas por hora nas últimas 24h
            $stmt = $this->db->query("
                SELECT 
                    HOUR(calldate) as hora,
                    COUNT(*) as total,
                    COUNT(CASE WHEN disposition = 'ANSWERED' THEN 1 END) as atendidas,
                    COUNT(CASE WHEN disposition = 'BUSY' THEN 1 END) as ocupadas,
                    COUNT(CASE WHEN disposition = 'NO ANSWER' THEN 1 END) as nao_atendidas,
                    AVG(CASE WHEN disposition = 'ANSWERED' THEN billsec END) as tma
                FROM cdr 
                WHERE calldate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(calldate)
                ORDER BY hora DESC
                LIMIT 12
            ");
            
            $hourlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Estatísticas por hora (últimas 12 horas):" . PHP_EOL;
            echo str_pad("Hora", 6) . str_pad("Total", 8) . str_pad("Atend.", 8) . 
                 str_pad("Ocup.", 8) . str_pad("N/Atend", 8) . str_pad("TMA", 8) . PHP_EOL;
            echo str_repeat("-", 50) . PHP_EOL;
            
            foreach ($hourlyStats as $stat) {
                $connectionRate = $stat['total'] > 0 
                    ? round(($stat['atendidas'] / $stat['total']) * 100, 1) 
                    : 0;
                
                echo str_pad($stat['hora'] . "h", 6) . 
                     str_pad($stat['total'], 8) . 
                     str_pad($stat['atendidas'] . " ({$connectionRate}%)", 8) . 
                     str_pad($stat['ocupadas'], 8) . 
                     str_pad($stat['nao_atendidas'], 8) . 
                     str_pad(round($stat['tma'] ?? 0) . "s", 8) . PHP_EOL;
            }
            
            echo PHP_EOL;
            
            // Resumo geral do dia
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_hoje,
                    COUNT(CASE WHEN disposition = 'ANSWERED' THEN 1 END) as atendidas_hoje,
                    AVG(CASE WHEN disposition = 'ANSWERED' THEN billsec END) as tma_hoje
                FROM cdr 
                WHERE DATE(calldate) = CURDATE()
            ");
            
            $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $connectionRateToday = $todayStats['total_hoje'] > 0 
                ? round(($todayStats['atendidas_hoje'] / $todayStats['total_hoje']) * 100, 1)
                : 0;
            
            echo "Resumo do dia:" . PHP_EOL;
            echo "  Total de chamadas: {$todayStats['total_hoje']}" . PHP_EOL;
            echo "  Chamadas atendidas: {$todayStats['atendidas_hoje']} ({$connectionRateToday}%)" . PHP_EOL;
            echo "  TMA médio: " . round($todayStats['tma_hoje'] ?? 0) . " segundos" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * Métricas de performance
     */
    private function showPerformance(): void {
        echo "=== MÉTRICAS DE PERFORMANCE ===" . PHP_EOL . PHP_EOL;
        
        // Performance do sistema
        $stats = $this->redisManager->get('master_stats');
        if ($stats) {
            echo "Sistema:" . PHP_EOL;
            echo "  Uptime: " . $this->formatUptime($stats['uptime']) . PHP_EOL;
            echo "  Memória PHP: " . $this->formatBytes($stats['memory_usage']) . PHP_EOL;
            echo "  Pico memória: " . $this->formatBytes($stats['peak_memory']) . PHP_EOL;
            echo "  Workers ativos: " . $stats['workers_active'] . PHP_EOL;
            echo PHP_EOL;
        }
        
        // Performance Redis
        $redisHealth = $this->redisManager->healthCheck();
        echo "Redis:" . PHP_EOL;
        echo "  Memória usada: " . $this->formatBytes($redisHealth['memory_usage']) . PHP_EOL;
        echo "  Chaves armazenadas: " . $redisHealth['keys_count'] . PHP_EOL;
        echo "  Uptime: " . $this->formatUptime($redisHealth['uptime']) . PHP_EOL;
        echo PHP_EOL;
        
        // Performance da fila
        require_once __DIR__ . '/../src/services/TaskQueue.php';
        $taskQueue = new TaskQueue('discador_tasks');
        $queueStats = $taskQueue->getStats();
        
        echo "Fila de tarefas:" . PHP_EOL;
        echo "  Pendentes: " . $queueStats['pending'] . PHP_EOL;
        echo "  Processando: " . $queueStats['processing'] . PHP_EOL;
        echo "  Concluídas: " . $queueStats['completed'] . PHP_EOL;
        echo "  Falhadas: " . $queueStats['failed'] . PHP_EOL;
        
        $successRate = ($queueStats['completed'] + $queueStats['failed']) > 0
            ? round(($queueStats['completed'] / ($queueStats['completed'] + $queueStats['failed'])) * 100, 1)
            : 0;
        echo "  Taxa de sucesso: {$successRate}%" . PHP_EOL;
        
        // Throughput estimado
        $totalProcessed = $queueStats['completed'] + $queueStats['failed'];
        if ($stats && $stats['uptime'] > 0) {
            $throughput = round($totalProcessed / ($stats['uptime'] / 3600), 2);
            echo "  Throughput: {$throughput} tarefas/hora" . PHP_EOL;
        }
    }
    
    /**
     * Verifica alertas do sistema
     */
    private function checkAlerts(): void {
        echo "=== ALERTAS DO SISTEMA ===" . PHP_EOL . PHP_EOL;
        
        $alerts = [];
        
        // Master inativo
        $masterHeartbeat = $this->redisManager->get('master_heartbeat');
        if (!$masterHeartbeat || (time() - strtotime($masterHeartbeat)) > 300) {
            $alerts[] = "🔴 CRÍTICO: Master não está respondendo";
        }
        
        // Workers com problemas
        $workers = $this->redisManager->hGetAll('active_workers');
        $unhealthyWorkers = 0;
        foreach ($workers as $workerId => $workerData) {
            $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
            if (!$heartbeat || (time() - strtotime($heartbeat)) > 180) {
                $unhealthyWorkers++;
            }
        }
        
        if ($unhealthyWorkers > 0) {
            $alerts[] = "⚠️ ATENÇÃO: $unhealthyWorkers worker(s) com problemas de heartbeat";
        }
        
        // Fila congestionada
        require_once __DIR__ . '/../src/services/TaskQueue.php';
        $taskQueue = new TaskQueue('discador_tasks');
        $queueStats = $taskQueue->getStats();
        
        if ($queueStats['pending'] > 1000) {
            $alerts[] = "⚠️ ATENÇÃO: Fila congestionada com {$queueStats['pending']} tarefas pendentes";
        }
        
        // Alta taxa de falhas
        $totalProcessed = $queueStats['completed'] + $queueStats['failed'];
        if ($totalProcessed > 100) {
            $failureRate = ($queueStats['failed'] / $totalProcessed) * 100;
            if ($failureRate > 10) {
                $alerts[] = "⚠️ ATENÇÃO: Alta taxa de falhas na fila (" . round($failureRate, 1) . "%)";
            }
        }
        
        // Memória alta
        $stats = $this->redisManager->get('master_stats');
        if ($stats && $stats['memory_usage'] > (512 * 1024 * 1024)) { // 512MB
            $alerts[] = "⚠️ ATENÇÃO: Alto uso de memória (" . $this->formatBytes($stats['memory_usage']) . ")";
        }
        
        // Redis com muitas chaves
        $redisHealth = $this->redisManager->healthCheck();
        if ($redisHealth['keys_count'] > 10000) {
            $alerts[] = "⚠️ ATENÇÃO: Muitas chaves no Redis ({$redisHealth['keys_count']})";
        }
        
        if (empty($alerts)) {
            echo "✅ Nenhum alerta ativo. Sistema funcionando normalmente." . PHP_EOL;
        } else {
            echo "Alertas ativos:" . PHP_EOL;
            foreach ($alerts as $alert) {
                echo "  $alert" . PHP_EOL;
            }
        }
    }
    
    /**
     * Exporta estatísticas
     */
    private function exportStats(array $args): void {
        $format = $args[2] ?? 'json';
        $filename = $args[3] ?? 'discador_stats_' . date('Y-m-d_H-i-s');
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'master_stats' => $this->redisManager->get('master_stats'),
            'redis_health' => $this->redisManager->healthCheck(),
            'workers' => $this->redisManager->hGetAll('active_workers'),
            'queue_stats' => null
        ];
        
        try {
            require_once __DIR__ . '/../src/services/TaskQueue.php';
            $taskQueue = new TaskQueue('discador_tasks');
            $data['queue_stats'] = $taskQueue->getStats();
        } catch (Exception $e) {
            $data['queue_error'] = $e->getMessage();
        }
        
        switch ($format) {
            case 'json':
                $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $filename .= '.json';
                break;
                
            case 'csv':
                // Simplificado para CSV
                $csv = [];
                $csv[] = ['timestamp', 'uptime', 'workers_active', 'memory_usage', 'queue_pending', 'queue_processing'];
                
                $stats = $data['master_stats'];
                $queue = $data['queue_stats'];
                
                $csv[] = [
                    $data['timestamp'],
                    $stats['uptime'] ?? 0,
                    $stats['workers_active'] ?? 0,
                    $stats['memory_usage'] ?? 0,
                    $queue['pending'] ?? 0,
                    $queue['processing'] ?? 0
                ];
                
                $content = '';
                foreach ($csv as $row) {
                    $content .= implode(',', $row) . PHP_EOL;
                }
                $filename .= '.csv';
                break;
                
            default:
                echo "Formato desconhecido: $format" . PHP_EOL;
                return;
        }
        
        $filepath = __DIR__ . '/../logs/' . $filename;
        
        if (file_put_contents($filepath, $content)) {
            echo "Estatísticas exportadas para: $filepath" . PHP_EOL;
        } else {
            echo "Erro ao exportar estatísticas" . PHP_EOL;
        }
    }
    
    // Métodos auxiliares
    
    private function clearScreen(): void {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }
    
    private function formatUptime(int $seconds): string {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
    
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 1) . $units[$pow];
    }
    
    private function showHelp(): void {
        echo "Sistema Discador v2.0 - Monitor" . PHP_EOL . PHP_EOL;
        echo "Uso: php discador_monitor.php <comando> [opções]" . PHP_EOL . PHP_EOL;
        echo "Comandos:" . PHP_EOL;
        echo "  watch [intervalo]    - Monitoramento em tempo real (padrão: 5s)" . PHP_EOL;
        echo "  dashboard           - Dashboard estático" . PHP_EOL;
        echo "  campaigns           - Relatório de campanhas" . PHP_EOL;
        echo "  calls               - Estatísticas de chamadas" . PHP_EOL;
        echo "  performance         - Métricas de performance" . PHP_EOL;
        echo "  alerts              - Verifica alertas do sistema" . PHP_EOL;
        echo "  export <fmt> [arq]  - Exporta estatísticas (json|csv)" . PHP_EOL;
        echo PHP_EOL;
        echo "Exemplos:" . PHP_EOL;
        echo "  php discador_monitor.php watch 10" . PHP_EOL;
        echo "  php discador_monitor.php export json relatorio" . PHP_EOL;
    }
}

// Execução
if (php_sapi_name() === 'cli') {
    $monitor = new DiscadorMonitor();
    $monitor->run($argv);
} else {
    echo "Este script deve ser executado via CLI" . PHP_EOL;
}
