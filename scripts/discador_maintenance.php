<?php
/**
 * Script de Backup e Manutenção do Sistema Discador v2.0
 * Realiza backup, limpeza e manutenção preventiva
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/services/RedisManager.php';

class DiscadorMaintenance {
    private $redisManager;
    private $db;
    private $backupDir;
    private $logDir;
    
    public function __construct() {
        try {
            $this->redisManager = RedisManager::getInstance();
            $this->db = Database::getInstance()->getConnection();
            
            $this->backupDir = __DIR__ . '/../backups';
            $this->logDir = __DIR__ . '/../logs';
            
            // Cria diretórios se não existirem
            if (!is_dir($this->backupDir)) {
                mkdir($this->backupDir, 0755, true);
            }
            if (!is_dir($this->logDir)) {
                mkdir($this->logDir, 0755, true);
            }
            
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
            case 'backup':
                $this->performBackup($args);
                break;
                
            case 'cleanup':
                $this->performCleanup($args);
                break;
                
            case 'optimize':
                $this->optimizeDatabase();
                break;
                
            case 'check':
                $this->performHealthCheck();
                break;
                
            case 'repair':
                $this->repairSystem($args);
                break;
                
            case 'archive':
                $this->archiveOldData($args);
                break;
                
            case 'restore':
                $this->restoreBackup($args);
                break;
                
            case 'schedule':
                $this->scheduleTask($args);
                break;
                
            default:
                echo "Comando desconhecido: $command" . PHP_EOL;
                $this->showHelp();
        }
    }
    
    /**
     * Realiza backup completo do sistema
     */
    private function performBackup(array $args): void {
        $type = $args[2] ?? 'full';
        $timestamp = date('Y-m-d_H-i-s');
        
        echo "Iniciando backup ($type) - $timestamp" . PHP_EOL;
        
        switch ($type) {
            case 'full':
                $this->backupDatabase($timestamp);
                $this->backupRedis($timestamp);
                $this->backupLogs($timestamp);
                $this->backupConfig($timestamp);
                break;
                
            case 'database':
                $this->backupDatabase($timestamp);
                break;
                
            case 'redis':
                $this->backupRedis($timestamp);
                break;
                
            case 'logs':
                $this->backupLogs($timestamp);
                break;
                
            default:
                echo "Tipo de backup desconhecido: $type" . PHP_EOL;
                return;
        }
        
        echo "Backup concluído!" . PHP_EOL;
    }
    
    private function backupDatabase(string $timestamp): void {
        echo "Realizando backup do banco de dados..." . PHP_EOL;
        
        $backupFile = $this->backupDir . "/database_$timestamp.sql";
        
        // Comando mysqldump
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > "%s"',
            DB_HOST,
            DB_PORT,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $backupFile
        );
        
        // No Windows, pode ser necessário especificar o caminho completo do mysqldump
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'mysqldump ' . sprintf(
                '-h%s -P%s -u%s -p%s %s',
                DB_HOST,
                DB_PORT,
                DB_USER,
                DB_PASS,
                DB_NAME
            ) . " > \"$backupFile\" 2>&1";
        }
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            $size = filesize($backupFile);
            echo "  ✅ Backup da base criado: " . basename($backupFile) . " (" . $this->formatBytes($size) . ")" . PHP_EOL;
            
            // Comprime o backup
            $this->compressFile($backupFile);
        } else {
            echo "  ❌ Erro no backup da base: " . implode("\n", $output) . PHP_EOL;
        }
    }
    
    private function backupRedis(string $timestamp): void {
        echo "Realizando backup do Redis..." . PHP_EOL;
        
        try {
            $redisData = [];
            
            // Backup de todas as chaves importantes
            $patterns = [
                'master_*',
                'worker_*',
                'active_workers',
                'queue_*',
                'discador_*'
            ];
            
            foreach ($patterns as $pattern) {
                $keys = $this->redisManager->getKeysMatching($pattern);
                foreach ($keys as $key) {
                    $redisData[$key] = $this->redisManager->get($key);
                }
            }
            
            $backupFile = $this->backupDir . "/redis_$timestamp.json";
            $content = json_encode($redisData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($backupFile, $content)) {
                $size = filesize($backupFile);
                echo "  ✅ Backup do Redis criado: " . basename($backupFile) . " (" . $this->formatBytes($size) . ")" . PHP_EOL;
                $this->compressFile($backupFile);
            } else {
                echo "  ❌ Erro no backup do Redis" . PHP_EOL;
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erro no backup do Redis: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function backupLogs(string $timestamp): void {
        echo "Realizando backup dos logs..." . PHP_EOL;
        
        $logFiles = glob($this->logDir . '/*.log');
        if (empty($logFiles)) {
            echo "  ℹ️ Nenhum arquivo de log encontrado" . PHP_EOL;
            return;
        }
        
        $backupFile = $this->backupDir . "/logs_$timestamp.tar.gz";
        
        if (PHP_OS_FAMILY === 'Windows') {
            // No Windows, copia os arquivos para um diretório temporário
            $tempDir = $this->backupDir . "/temp_logs_$timestamp";
            mkdir($tempDir, 0755, true);
            
            foreach ($logFiles as $logFile) {
                copy($logFile, $tempDir . '/' . basename($logFile));
            }
            
            echo "  ✅ Logs copiados para: " . basename($tempDir) . PHP_EOL;
        } else {
            // No Linux, usa tar
            $command = "tar -czf \"$backupFile\" -C \"" . dirname($this->logDir) . "\" logs/";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile)) {
                $size = filesize($backupFile);
                echo "  ✅ Backup dos logs criado: " . basename($backupFile) . " (" . $this->formatBytes($size) . ")" . PHP_EOL;
            } else {
                echo "  ❌ Erro no backup dos logs" . PHP_EOL;
            }
        }
    }
    
    private function backupConfig(string $timestamp): void {
        echo "Realizando backup das configurações..." . PHP_EOL;
        
        $configFiles = [
            __DIR__ . '/../src/config/config.php',
            __DIR__ . '/../.env',
            __DIR__ . '/../docker-compose.yml'
        ];
        
        $backupData = [];
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                $backupData[basename($file)] = file_get_contents($file);
            }
        }
        
        if (!empty($backupData)) {
            $backupFile = $this->backupDir . "/config_$timestamp.json";
            $content = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($backupFile, $content)) {
                echo "  ✅ Backup das configurações criado: " . basename($backupFile) . PHP_EOL;
            } else {
                echo "  ❌ Erro no backup das configurações" . PHP_EOL;
            }
        }
    }
    
    /**
     * Limpeza do sistema
     */
    private function performCleanup(array $args): void {
        $type = $args[2] ?? 'all';
        
        echo "Iniciando limpeza ($type)" . PHP_EOL;
        
        switch ($type) {
            case 'all':
                $this->cleanupLogs();
                $this->cleanupRedis();
                $this->cleanupDatabase();
                $this->cleanupBackups();
                break;
                
            case 'logs':
                $this->cleanupLogs();
                break;
                
            case 'redis':
                $this->cleanupRedis();
                break;
                
            case 'database':
                $this->cleanupDatabase();
                break;
                
            case 'backups':
                $this->cleanupBackups();
                break;
                
            default:
                echo "Tipo de limpeza desconhecido: $type" . PHP_EOL;
                return;
        }
        
        echo "Limpeza concluída!" . PHP_EOL;
    }
    
    private function cleanupLogs(): void {
        echo "Limpando logs antigos..." . PHP_EOL;
        
        $logFiles = glob($this->logDir . '/*.log');
        $cutoffTime = time() - (30 * 24 * 3600); // 30 dias
        $cleaned = 0;
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < $cutoffTime) {
                if (unlink($logFile)) {
                    $cleaned++;
                }
            }
        }
        
        // Rotaciona logs grandes
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10MB
                $rotatedFile = $logFile . '.' . date('Y-m-d');
                rename($logFile, $rotatedFile);
                touch($logFile);
                echo "  📄 Log rotacionado: " . basename($logFile) . PHP_EOL;
            }
        }
        
        echo "  ✅ $cleaned arquivo(s) de log removido(s)" . PHP_EOL;
    }
    
    private function cleanupRedis(): void {
        echo "Limpando dados expirados do Redis..." . PHP_EOL;
        
        try {
            // Remove chaves expiradas manualmente
            $patterns = [
                'worker_heartbeat:*',
                'queue_results:*',
                'queue_failed:*'
            ];
            
            $cleaned = 0;
            
            foreach ($patterns as $pattern) {
                $keys = $this->redisManager->getKeysMatching($pattern);
                foreach ($keys as $key) {
                    $ttl = $this->redisManager->getTTL($key);
                    if ($ttl === -1) { // Sem TTL definido
                        // Define TTL baseado no tipo da chave
                        if (strpos($key, 'heartbeat') !== false) {
                            $this->redisManager->setExpire($key, 300); // 5 minutos
                        } elseif (strpos($key, 'results') !== false) {
                            $this->redisManager->setExpire($key, 86400); // 24 horas
                        } elseif (strpos($key, 'failed') !== false) {
                            $this->redisManager->setExpire($key, 604800); // 7 dias
                        }
                        $cleaned++;
                    }
                }
            }
            
            echo "  ✅ TTL definido para $cleaned chave(s)" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "  ❌ Erro na limpeza do Redis: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function cleanupDatabase(): void {
        echo "Limpando dados antigos do banco..." . PHP_EOL;
        
        try {
            // Remove CDRs antigos (mais de 90 dias)
            $stmt = $this->db->prepare("DELETE FROM cdr WHERE calldate < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $cdrDeleted = $stmt->rowCount();
            
            // Remove hoppers de campanhas inativas antigas
            $stmt = $this->db->prepare("
                DELETE h FROM hopper h 
                INNER JOIN campanhas c ON h.campanha_id = c.id 
                WHERE c.status = 'INATIVA' 
                AND c.data_fim < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $hopperDeleted = $stmt->rowCount();
            
            // Remove logs antigos
            $stmt = $this->db->prepare("DELETE FROM logs WHERE data_criacao < DATE_SUB(NOW(), INTERVAL 60 DAY)");
            $stmt->execute();
            $logsDeleted = $stmt->rowCount();
            
            echo "  ✅ CDRs removidos: $cdrDeleted" . PHP_EOL;
            echo "  ✅ Hoppers removidos: $hopperDeleted" . PHP_EOL;
            echo "  ✅ Logs removidos: $logsDeleted" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "  ❌ Erro na limpeza do banco: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function cleanupBackups(): void {
        echo "Limpando backups antigos..." . PHP_EOL;
        
        $backupFiles = glob($this->backupDir . '/*');
        $cutoffTime = time() - (30 * 24 * 3600); // 30 dias
        $cleaned = 0;
        
        foreach ($backupFiles as $backupFile) {
            if (is_file($backupFile) && filemtime($backupFile) < $cutoffTime) {
                if (unlink($backupFile)) {
                    $cleaned++;
                }
            }
        }
        
        echo "  ✅ $cleaned backup(s) antigo(s) removido(s)" . PHP_EOL;
    }
    
    /**
     * Otimização do banco de dados
     */
    private function optimizeDatabase(): void {
        echo "Otimizando banco de dados..." . PHP_EOL;
        
        try {
            // Lista todas as tabelas
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                echo "  Otimizando tabela: $table" . PHP_EOL;
                
                // Analisa a tabela
                $this->db->exec("ANALYZE TABLE `$table`");
                
                // Otimiza a tabela
                $stmt = $this->db->query("OPTIMIZE TABLE `$table`");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['Msg_type'] === 'status' && $result['Msg_text'] === 'OK') {
                    echo "    ✅ OK" . PHP_EOL;
                } else {
                    echo "    ⚠️ " . $result['Msg_text'] . PHP_EOL;
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erro na otimização: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    /**
     * Health check completo
     */
    private function performHealthCheck(): void {
        echo "=== HEALTH CHECK COMPLETO ===" . PHP_EOL . PHP_EOL;
        
        $issues = [];
        
        // Verifica banco de dados
        echo "Verificando banco de dados..." . PHP_EOL;
        try {
            $this->db->query("SELECT 1");
            echo "  ✅ Conexão OK" . PHP_EOL;
            
            // Verifica integridade das tabelas principais
            $tables = ['campanhas', 'hopper', 'cdr', 'ramais'];
            foreach ($tables as $table) {
                $stmt = $this->db->prepare("CHECK TABLE `$table`");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['Msg_text'] === 'OK') {
                    echo "  ✅ Tabela $table: OK" . PHP_EOL;
                } else {
                    echo "  ❌ Tabela $table: " . $result['Msg_text'] . PHP_EOL;
                    $issues[] = "Problemas na tabela $table";
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erro no banco: " . $e->getMessage() . PHP_EOL;
            $issues[] = "Problemas de conexão com banco";
        }
        
        echo PHP_EOL;
        
        // Verifica Redis
        echo "Verificando Redis..." . PHP_EOL;
        $redisHealth = $this->redisManager->healthCheck();
        
        if ($redisHealth['connected'] && $redisHealth['ping']) {
            echo "  ✅ Redis OK" . PHP_EOL;
            echo "  📊 Memória: " . $this->formatBytes($redisHealth['memory_usage']) . PHP_EOL;
            echo "  🔑 Chaves: " . $redisHealth['keys_count'] . PHP_EOL;
            
            if ($redisHealth['memory_usage'] > 1024 * 1024 * 1024) { // 1GB
                $issues[] = "Alto uso de memória no Redis";
            }
        } else {
            echo "  ❌ Redis com problemas" . PHP_EOL;
            $issues[] = "Problemas no Redis";
        }
        
        echo PHP_EOL;
        
        // Verifica sistema de arquivos
        echo "Verificando sistema de arquivos..." . PHP_EOL;
        $directories = [
            $this->logDir => 'Logs',
            $this->backupDir => 'Backups'
        ];
        
        foreach ($directories as $dir => $name) {
            if (is_dir($dir) && is_writable($dir)) {
                $freeSpace = disk_free_space($dir);
                echo "  ✅ $name: OK (Espaço livre: " . $this->formatBytes($freeSpace) . ")" . PHP_EOL;
                
                if ($freeSpace < 1024 * 1024 * 1024) { // 1GB
                    $issues[] = "Pouco espaço livre em $name";
                }
            } else {
                echo "  ❌ $name: Não acessível" . PHP_EOL;
                $issues[] = "Problemas no diretório $name";
            }
        }
        
        echo PHP_EOL;
        
        // Resumo
        if (empty($issues)) {
            echo "🎉 SISTEMA SAUDÁVEL - Nenhum problema encontrado!" . PHP_EOL;
        } else {
            echo "⚠️ PROBLEMAS ENCONTRADOS:" . PHP_EOL;
            foreach ($issues as $issue) {
                echo "  • $issue" . PHP_EOL;
            }
        }
    }
    
    /**
     * Reparo automático do sistema
     */
    private function repairSystem(array $args): void {
        $type = $args[2] ?? 'auto';
        
        echo "Iniciando reparo do sistema ($type)..." . PHP_EOL;
        
        switch ($type) {
            case 'auto':
                $this->autoRepair();
                break;
                
            case 'redis':
                $this->repairRedis();
                break;
                
            case 'database':
                $this->repairDatabase();
                break;
                
            case 'workers':
                $this->repairWorkers();
                break;
                
            default:
                echo "Tipo de reparo desconhecido: $type" . PHP_EOL;
        }
    }
    
    private function autoRepair(): void {
        echo "Executando reparo automático..." . PHP_EOL;
        
        // Limpa workers órfãos
        $this->repairWorkers();
        
        // Limpa dados expirados do Redis
        $this->cleanupRedis();
        
        // Repara tabelas com problemas
        $this->repairDatabase();
        
        echo "Reparo automático concluído!" . PHP_EOL;
    }
    
    private function repairRedis(): void {
        echo "Reparando Redis..." . PHP_EOL;
        
        try {
            // Remove workers órfãos
            $workers = $this->redisManager->hGetAll('active_workers');
            $removed = 0;
            
            foreach ($workers as $workerId => $workerData) {
                $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
                if (!$heartbeat || (time() - strtotime($heartbeat)) > 600) { // 10 minutos
                    $this->redisManager->hDel('active_workers', $workerId);
                    $this->redisManager->delete("worker_heartbeat:$workerId");
                    $removed++;
                }
            }
            
            echo "  ✅ $removed worker(s) órfão(s) removido(s)" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "  ❌ Erro no reparo do Redis: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function repairDatabase(): void {
        echo "Reparando banco de dados..." . PHP_EOL;
        
        try {
            $tables = ['campanhas', 'hopper', 'cdr', 'ramais'];
            
            foreach ($tables as $table) {
                echo "  Reparando tabela: $table" . PHP_EOL;
                $stmt = $this->db->query("REPAIR TABLE `$table`");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['Msg_text'] === 'OK') {
                    echo "    ✅ OK" . PHP_EOL;
                } else {
                    echo "    ⚠️ " . $result['Msg_text'] . PHP_EOL;
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Erro no reparo do banco: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    private function repairWorkers(): void {
        echo "Reparando workers..." . PHP_EOL;
        
        // Reset de workers órfãos no Redis
        $this->repairRedis();
        
        // Comando para reiniciar workers via master
        try {
            if ($this->redisManager->set('master_command', ['action' => 'restart_all_workers'])) {
                echo "  ✅ Comando de reinicialização enviado ao master" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo "  ❌ Erro ao comunicar com master: " . $e->getMessage() . PHP_EOL;
        }
    }
    
    // Métodos auxiliares
    
    private function compressFile(string $file): void {
        if (function_exists('gzopen')) {
            $data = file_get_contents($file);
            $compressedFile = $file . '.gz';
            
            $gz = gzopen($compressedFile, 'w9');
            gzwrite($gz, $data);
            gzclose($gz);
            
            if (file_exists($compressedFile)) {
                unlink($file); // Remove arquivo original
                echo "    📦 Arquivo comprimido: " . basename($compressedFile) . PHP_EOL;
            }
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
        echo "Sistema Discador v2.0 - Manutenção" . PHP_EOL . PHP_EOL;
        echo "Uso: php discador_maintenance.php <comando> [opções]" . PHP_EOL . PHP_EOL;
        echo "Comandos:" . PHP_EOL;
        echo "  backup <tipo>         - Realiza backup (full|database|redis|logs)" . PHP_EOL;
        echo "  cleanup <tipo>        - Limpeza (all|logs|redis|database|backups)" . PHP_EOL;
        echo "  optimize              - Otimiza banco de dados" . PHP_EOL;
        echo "  check                 - Health check completo" . PHP_EOL;
        echo "  repair <tipo>         - Reparo (auto|redis|database|workers)" . PHP_EOL;
        echo "  archive <dias>        - Arquiva dados antigos" . PHP_EOL;
        echo "  restore <arquivo>     - Restaura backup" . PHP_EOL;
        echo PHP_EOL;
        echo "Exemplos:" . PHP_EOL;
        echo "  php discador_maintenance.php backup full" . PHP_EOL;
        echo "  php discador_maintenance.php cleanup logs" . PHP_EOL;
        echo "  php discador_maintenance.php repair auto" . PHP_EOL;
    }
}

// Execução
if (php_sapi_name() === 'cli') {
    $maintenance = new DiscadorMaintenance();
    $maintenance->run($argv);
} else {
    echo "Este script deve ser executado via CLI" . PHP_EOL;
}
