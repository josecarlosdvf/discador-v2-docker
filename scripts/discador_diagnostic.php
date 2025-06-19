<?php
/**
 * Script de Teste e Diagnóstico do Sistema Discador v2.0
 * Testa todos os componentes e valida funcionamento
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/services/RedisManager.php';
require_once __DIR__ . '/../src/services/TaskQueue.php';
require_once __DIR__ . '/../src/services/DistributedLock.php';

class DiscadorDiagnostic {
    private $redisManager;
    private $db;
    private $results = [];
    
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
            case 'all':
                $this->runAllTests();
                break;
                
            case 'database':
                $this->testDatabase();
                break;
                
            case 'redis':
                $this->testRedis();
                break;
                
            case 'queue':
                $this->testTaskQueue();
                break;
                
            case 'locks':
                $this->testDistributedLocks();
                break;
                
            case 'workers':
                $this->testWorkers();
                break;
                
            case 'performance':
                $this->testPerformance();
                break;
                
            case 'stress':
                $this->stressTest($args);
                break;
                
            case 'report':
                $this->generateReport();
                break;
                
            default:
                echo "Comando desconhecido: $command" . PHP_EOL;
                $this->showHelp();
        }
    }
    
    /**
     * Executa todos os testes
     */
    private function runAllTests(): void {
        echo "=== DIAGNÓSTICO COMPLETO DO SISTEMA ===" . PHP_EOL . PHP_EOL;
        
        $tests = [
            'database' => 'Banco de Dados',
            'redis' => 'Redis',
            'queue' => 'Sistema de Filas',
            'locks' => 'Locks Distribuídos',
            'workers' => 'Workers',
            'performance' => 'Performance'
        ];
        
        foreach ($tests as $method => $name) {
            echo "🔍 Testando: $name" . PHP_EOL;
            $this->{'test' . ucfirst($method)}();
            echo PHP_EOL;
        }
        
        $this->showSummary();
    }
    
    /**
     * Testa conexão e operações do banco
     */
    private function testDatabase(): void {
        $testName = 'Database';
        echo "Testando banco de dados..." . PHP_EOL;
        
        try {
            // Teste de conexão
            $this->db->query("SELECT 1");
            $this->addResult($testName, 'conexao', true, 'Conexão estabelecida');
            
            // Teste de tabelas essenciais
            $tables = ['campanhas', 'hopper', 'cdr', 'ramais', 'usuarios'];
            foreach ($tables as $table) {
                try {
                    $stmt = $this->db->query("SELECT COUNT(*) FROM `$table` LIMIT 1");
                    $this->addResult($testName, "tabela_$table", true, "Tabela $table acessível");
                } catch (Exception $e) {
                    $this->addResult($testName, "tabela_$table", false, "Erro na tabela $table: " . $e->getMessage());
                }
            }
            
            // Teste de escrita
            try {
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("INSERT INTO logs (nivel, mensagem, data_criacao) VALUES (?, ?, NOW())");
                $stmt->execute(['teste', 'Teste de diagnóstico']);
                $this->db->rollback(); // Desfaz a inserção
                $this->addResult($testName, 'escrita', true, 'Operação de escrita OK');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->addResult($testName, 'escrita', false, 'Erro na escrita: ' . $e->getMessage());
            }
            
            // Teste de performance
            $start = microtime(true);
            $this->db->query("SELECT COUNT(*) FROM campanhas");
            $time = (microtime(true) - $start) * 1000;
            
            $this->addResult($testName, 'performance', $time < 100, "Query simples: {$time}ms");
            
        } catch (Exception $e) {
            $this->addResult($testName, 'conexao', false, 'Erro na conexão: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa Redis e operações
     */
    private function testRedis(): void {
        $testName = 'Redis';
        echo "Testando Redis..." . PHP_EOL;
        
        try {
            // Teste de conexão e ping
            $health = $this->redisManager->healthCheck();
            $this->addResult($testName, 'conexao', $health['connected'], 'Conexão: ' . ($health['connected'] ? 'OK' : 'FALHA'));
            $this->addResult($testName, 'ping', $health['ping'], 'Ping: ' . ($health['ping'] ? 'OK' : 'FALHA'));
            
            if (!$health['connected']) {
                return;
            }
            
            // Teste de operações básicas
            $testKey = 'diagnostic_test_' . time();
            $testValue = ['test' => true, 'timestamp' => time()];
            
            // SET
            $setResult = $this->redisManager->set($testKey, $testValue, 60);
            $this->addResult($testName, 'set', $setResult, 'Operação SET: ' . ($setResult ? 'OK' : 'FALHA'));
            
            // GET
            $getValue = $this->redisManager->get($testKey);
            $getResult = $getValue !== null && $getValue['test'] === true;
            $this->addResult($testName, 'get', $getResult, 'Operação GET: ' . ($getResult ? 'OK' : 'FALHA'));
            
            // DELETE
            $delResult = $this->redisManager->delete($testKey);
            $this->addResult($testName, 'delete', $delResult, 'Operação DELETE: ' . ($delResult ? 'OK' : 'FALHA'));
            
            // Teste de Hash
            $hashKey = 'diagnostic_hash_' . time();
            $hashField = 'field1';
            $hashValue = 'value1';
            
            $hsetResult = $this->redisManager->hSet($hashKey, $hashField, $hashValue);
            $hgetResult = $this->redisManager->hGet($hashKey, $hashField) === $hashValue;
            $this->redisManager->delete($hashKey);
            
            $this->addResult($testName, 'hash', $hsetResult && $hgetResult, 'Operações HASH: ' . ($hsetResult && $hgetResult ? 'OK' : 'FALHA'));
            
            // Teste de performance
            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $this->redisManager->set("perf_test_$i", "value_$i", 60);
            }
            $setTime = (microtime(true) - $start) * 1000;
            
            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $this->redisManager->get("perf_test_$i");
            }
            $getTime = (microtime(true) - $start) * 1000;
            
            // Limpa dados de teste
            for ($i = 0; $i < 100; $i++) {
                $this->redisManager->delete("perf_test_$i");
            }
            
            $this->addResult($testName, 'performance_set', $setTime < 1000, "100 SETs: {$setTime}ms");
            $this->addResult($testName, 'performance_get', $getTime < 500, "100 GETs: {$getTime}ms");
            
        } catch (Exception $e) {
            $this->addResult($testName, 'exception', false, 'Erro no Redis: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa sistema de filas
     */
    private function testTaskQueue(): void {
        $testName = 'TaskQueue';
        echo "Testando sistema de filas..." . PHP_EOL;
        
        try {
            $taskQueue = new TaskQueue('diagnostic_test_queue');
            
            // Teste de inicialização
            $this->addResult($testName, 'init', true, 'TaskQueue inicializada');
            
            // Teste de push
            $task1 = ['type' => 'test', 'data' => 'test1', 'priority' => 'high'];
            $task2 = ['type' => 'test', 'data' => 'test2', 'priority' => 'normal'];
            
            $push1 = $taskQueue->push($task1, TaskQueue::PRIORITY_HIGH);
            $push2 = $taskQueue->push($task2, TaskQueue::PRIORITY_NORMAL);
            
            $this->addResult($testName, 'push', $push1 && $push2, 'Push de tarefas: ' . ($push1 && $push2 ? 'OK' : 'FALHA'));
            
            // Teste de pop (deve retornar a de alta prioridade primeiro)
            $poppedTask = $taskQueue->pop(1);
            $priorityTest = $poppedTask && $poppedTask['data']['data'] === 'test1';
            $this->addResult($testName, 'priority', $priorityTest, 'Prioridade de filas: ' . ($priorityTest ? 'OK' : 'FALHA'));
            
            // Completa a tarefa
            if ($poppedTask) {
                $completeResult = $taskQueue->complete($poppedTask, ['status' => 'success']);
                $this->addResult($testName, 'complete', $completeResult, 'Complete task: ' . ($completeResult ? 'OK' : 'FALHA'));
            }
            
            // Pega segunda tarefa e testa falha
            $secondTask = $taskQueue->pop(1);
            if ($secondTask) {
                $failResult = $taskQueue->fail($secondTask, 'Teste de falha');
                $this->addResult($testName, 'fail', $failResult, 'Fail task: ' . ($failResult ? 'OK' : 'FALHA'));
            }
            
            // Testa estatísticas
            $stats = $taskQueue->getStats();
            $statsOk = is_array($stats) && isset($stats['completed']) && isset($stats['failed']);
            $this->addResult($testName, 'stats', $statsOk, 'Estatísticas: ' . ($statsOk ? 'OK' : 'FALHA'));
            
            // Limpa fila de teste
            $taskQueue->clear();
            
        } catch (Exception $e) {
            $this->addResult($testName, 'exception', false, 'Erro na TaskQueue: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa locks distribuídos
     */
    private function testDistributedLocks(): void {
        $testName = 'DistributedLock';
        echo "Testando locks distribuídos..." . PHP_EOL;
        
        try {
            $lock = new DistributedLock();
            
            // Teste de aquisição de lock
            $lockKey = 'diagnostic_test_lock';
            $acquired = $lock->acquire($lockKey, 60);
            $this->addResult($testName, 'acquire', $acquired, 'Aquisição de lock: ' . ($acquired ? 'OK' : 'FALHA'));
            
            if ($acquired) {
                // Teste de lock já existente (deve falhar)
                $secondAcquire = $lock->acquire($lockKey, 60);
                $this->addResult($testName, 'duplicate', !$secondAcquire, 'Prevenção de lock duplo: ' . (!$secondAcquire ? 'OK' : 'FALHA'));
                
                // Teste de extensão de lock
                $extended = $lock->extend($lockKey, 120);
                $this->addResult($testName, 'extend', $extended, 'Extensão de lock: ' . ($extended ? 'OK' : 'FALHA'));
                
                // Teste de liberação
                $released = $lock->release($lockKey);
                $this->addResult($testName, 'release', $released, 'Liberação de lock: ' . ($released ? 'OK' : 'FALHA'));
                
                // Teste de aquisição após liberação
                $reacquired = $lock->acquire($lockKey, 60);
                $this->addResult($testName, 'reacquire', $reacquired, 'Reaquisição: ' . ($reacquired ? 'OK' : 'FALHA'));
                
                if ($reacquired) {
                    $lock->release($lockKey);
                }
            }
            
        } catch (Exception $e) {
            $this->addResult($testName, 'exception', false, 'Erro nos locks: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa workers (se estão rodando)
     */
    private function testWorkers(): void {
        $testName = 'Workers';
        echo "Testando workers..." . PHP_EOL;
        
        try {
            // Verifica master
            $masterHeartbeat = $this->redisManager->get('master_heartbeat');
            $masterRunning = $masterHeartbeat && (time() - strtotime($masterHeartbeat)) < 120;
            $this->addResult($testName, 'master', $masterRunning, 'Master ativo: ' . ($masterRunning ? 'SIM' : 'NÃO'));
            
            // Verifica workers ativos
            $workers = $this->redisManager->hGetAll('active_workers');
            $workersCount = count($workers);
            $this->addResult($testName, 'count', $workersCount > 0, "Workers ativos: $workersCount");
            
            if ($workersCount > 0) {
                $healthyWorkers = 0;
                foreach ($workers as $workerId => $workerData) {
                    $heartbeat = $this->redisManager->get("worker_heartbeat:$workerId");
                    if ($heartbeat && (time() - strtotime($heartbeat)) < 120) {
                        $healthyWorkers++;
                    }
                }
                
                $healthRatio = $healthyWorkers / $workersCount;
                $this->addResult($testName, 'health', $healthRatio > 0.8, "Workers saudáveis: $healthyWorkers/$workersCount");
            }
            
            // Testa comunicação com workers via fila
            $taskQueue = new TaskQueue('discador_tasks');
            $testTask = [
                'type' => 'diagnostic_test',
                'timestamp' => time(),
                'test_id' => uniqid()
            ];
            
            $taskSent = $taskQueue->push($testTask);
            $this->addResult($testName, 'communication', $taskSent, 'Comunicação via fila: ' . ($taskSent ? 'OK' : 'FALHA'));
            
        } catch (Exception $e) {
            $this->addResult($testName, 'exception', false, 'Erro nos workers: ' . $e->getMessage());
        }
    }
    
    /**
     * Testa performance do sistema
     */
    private function testPerformance(): void {
        $testName = 'Performance';
        echo "Testando performance..." . PHP_EOL;
        
        try {
            // Teste de memória
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');
            
            $this->addResult($testName, 'memory', $memoryUsage < 100 * 1024 * 1024, "Memória atual: " . $this->formatBytes($memoryUsage));
            $this->addResult($testName, 'memory_peak', $memoryPeak < 200 * 1024 * 1024, "Pico de memória: " . $this->formatBytes($memoryPeak));
            
            // Teste de CPU (simulação)
            $start = microtime(true);
            for ($i = 0; $i < 100000; $i++) {
                $dummy = md5($i);
            }
            $cpuTime = (microtime(true) - $start) * 1000;
            
            $this->addResult($testName, 'cpu', $cpuTime < 1000, "Processamento CPU: {$cpuTime}ms");
            
            // Teste de I/O
            $tempFile = sys_get_temp_dir() . '/discador_diagnostic_' . time() . '.tmp';
            $testData = str_repeat('A', 1024 * 1024); // 1MB
            
            $start = microtime(true);
            file_put_contents($tempFile, $testData);
            $writeTime = (microtime(true) - $start) * 1000;
            
            $start = microtime(true);
            $readData = file_get_contents($tempFile);
            $readTime = (microtime(true) - $start) * 1000;
            
            unlink($tempFile);
            
            $this->addResult($testName, 'io_write', $writeTime < 500, "Escrita I/O (1MB): {$writeTime}ms");
            $this->addResult($testName, 'io_read', $readTime < 200, "Leitura I/O (1MB): {$readTime}ms");
            
        } catch (Exception $e) {
            $this->addResult($testName, 'exception', false, 'Erro no teste de performance: ' . $e->getMessage());
        }
    }
    
    /**
     * Teste de stress do sistema
     */
    private function stressTest(array $args): void {
        $duration = (int)($args[2] ?? 30); // segundos
        $concurrent = (int)($args[3] ?? 10); // operações simultâneas
        
        echo "=== TESTE DE STRESS ===" . PHP_EOL;
        echo "Duração: {$duration}s | Concorrência: $concurrent" . PHP_EOL . PHP_EOL;
        
        $startTime = time();
        $operations = 0;
        $errors = 0;
        
        echo "Iniciando teste de stress..." . PHP_EOL;
        
        while ((time() - $startTime) < $duration) {
            $tasks = [];
            
            // Executa operações em paralelo simulado
            for ($i = 0; $i < $concurrent; $i++) {
                try {
                    // Teste Redis
                    $key = "stress_test_" . time() . "_$i";
                    $value = ['operation' => $operations, 'time' => microtime(true)];
                    
                    if ($this->redisManager->set($key, $value, 60)) {
                        $retrieved = $this->redisManager->get($key);
                        if ($retrieved && $retrieved['operation'] === $operations) {
                            $this->redisManager->delete($key);
                            $operations++;
                        } else {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                    
                    // Teste de fila
                    $taskQueue = new TaskQueue('stress_test_queue');
                    $task = ['type' => 'stress', 'data' => "operation_$operations"];
                    
                    if ($taskQueue->push($task)) {
                        $poppedTask = $taskQueue->pop(1);
                        if ($poppedTask) {
                            $taskQueue->complete($poppedTask);
                            $operations++;
                        }
                    } else {
                        $errors++;
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                }
            }
            
            // Mostra progresso
            $elapsed = time() - $startTime;
            $opsPerSec = $elapsed > 0 ? round($operations / $elapsed, 2) : 0;
            $errorRate = $operations > 0 ? round(($errors / $operations) * 100, 2) : 0;
            
            echo "\rTempo: {$elapsed}s | Ops: $operations | Ops/s: $opsPerSec | Erros: $errors ({$errorRate}%)";
            
            usleep(100000); // 0.1 segundo
        }
        
        echo PHP_EOL . PHP_EOL;
        
        // Resultados finais
        $totalTime = time() - $startTime;
        $avgOpsPerSec = round($operations / $totalTime, 2);
        $finalErrorRate = $operations > 0 ? round(($errors / $operations) * 100, 2) : 0;
        
        echo "=== RESULTADOS DO TESTE DE STRESS ===" . PHP_EOL;
        echo "Duração total: {$totalTime}s" . PHP_EOL;
        echo "Operações executadas: $operations" . PHP_EOL;
        echo "Operações por segundo: $avgOpsPerSec" . PHP_EOL;
        echo "Total de erros: $errors" . PHP_EOL;
        echo "Taxa de erro: {$finalErrorRate}%" . PHP_EOL;
        
        // Avaliação
        if ($finalErrorRate < 1.0 && $avgOpsPerSec > 10) {
            echo "✅ Sistema passou no teste de stress!" . PHP_EOL;
        } elseif ($finalErrorRate < 5.0) {
            echo "⚠️ Sistema com performance moderada no teste de stress" . PHP_EOL;
        } else {
            echo "❌ Sistema falhou no teste de stress" . PHP_EOL;
        }
        
        // Limpa dados de teste
        try {
            $taskQueue = new TaskQueue('stress_test_queue');
            $taskQueue->clear();
        } catch (Exception $e) {
            // Ignora erros de limpeza
        }
    }
    
    /**
     * Gera relatório completo
     */
    private function generateReport(): void {
        echo "Gerando relatório de diagnóstico..." . PHP_EOL;
        
        // Executa todos os testes
        $this->runAllTests();
        
        // Cria relatório
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system_info' => [
                'os' => PHP_OS_FAMILY,
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'test_results' => $this->results,
            'summary' => $this->calculateSummary()
        ];
        
        // Salva relatório
        $reportFile = __DIR__ . '/../logs/diagnostic_report_' . date('Y-m-d_H-i-s') . '.json';
        $reportContent = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($reportFile, $reportContent)) {
            echo "Relatório salvo em: $reportFile" . PHP_EOL;
        } else {
            echo "Erro ao salvar relatório" . PHP_EOL;
        }
    }
    
    // Métodos auxiliares
    
    private function addResult(string $category, string $test, bool $success, string $message): void {
        if (!isset($this->results[$category])) {
            $this->results[$category] = [];
        }
        
        $this->results[$category][$test] = [
            'success' => $success,
            'message' => $message,
            'timestamp' => microtime(true)
        ];
        
        $status = $success ? '✅' : '❌';
        echo "  $status $test: $message" . PHP_EOL;
    }
    
    private function showSummary(): void {
        echo "=== RESUMO DOS TESTES ===" . PHP_EOL;
        
        $summary = $this->calculateSummary();
        
        foreach ($summary['by_category'] as $category => $stats) {
            $percentage = round(($stats['passed'] / $stats['total']) * 100, 1);
            $status = $percentage >= 80 ? '✅' : ($percentage >= 60 ? '⚠️' : '❌');
            
            echo "$status $category: {$stats['passed']}/{$stats['total']} ({$percentage}%)" . PHP_EOL;
        }
        
        echo PHP_EOL;
        
        $overallPercentage = round(($summary['total_passed'] / $summary['total_tests']) * 100, 1);
        $overallStatus = $overallPercentage >= 80 ? '✅' : ($overallPercentage >= 60 ? '⚠️' : '❌');
        
        echo "$overallStatus GERAL: {$summary['total_passed']}/{$summary['total_tests']} ({$overallPercentage}%)" . PHP_EOL;
        
        if ($overallPercentage >= 80) {
            echo "🎉 Sistema funcionando bem!" . PHP_EOL;
        } elseif ($overallPercentage >= 60) {
            echo "⚠️ Sistema com alguns problemas que precisam de atenção" . PHP_EOL;
        } else {
            echo "🚨 Sistema com problemas críticos!" . PHP_EOL;
        }
    }
    
    private function calculateSummary(): array {
        $summary = [
            'total_tests' => 0,
            'total_passed' => 0,
            'by_category' => []
        ];
        
        foreach ($this->results as $category => $tests) {
            $categoryPassed = 0;
            $categoryTotal = count($tests);
            
            foreach ($tests as $test => $result) {
                if ($result['success']) {
                    $categoryPassed++;
                    $summary['total_passed']++;
                }
                $summary['total_tests']++;
            }
            
            $summary['by_category'][$category] = [
                'total' => $categoryTotal,
                'passed' => $categoryPassed
            ];
        }
        
        return $summary;
    }
    
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . $units[$pow];
    }
    
    private function showHelp(): void {
        echo "Sistema Discador v2.0 - Diagnóstico" . PHP_EOL . PHP_EOL;
        echo "Uso: php discador_diagnostic.php <comando> [opções]" . PHP_EOL . PHP_EOL;
        echo "Comandos:" . PHP_EOL;
        echo "  all                    - Executa todos os testes" . PHP_EOL;
        echo "  database               - Testa banco de dados" . PHP_EOL;
        echo "  redis                  - Testa Redis" . PHP_EOL;
        echo "  queue                  - Testa sistema de filas" . PHP_EOL;
        echo "  locks                  - Testa locks distribuídos" . PHP_EOL;
        echo "  workers                - Testa workers" . PHP_EOL;
        echo "  performance            - Testa performance" . PHP_EOL;
        echo "  stress <dur> <conc>    - Teste de stress (duração em segundos, concorrência)" . PHP_EOL;
        echo "  report                 - Gera relatório completo" . PHP_EOL;
        echo PHP_EOL;
        echo "Exemplos:" . PHP_EOL;
        echo "  php discador_diagnostic.php all" . PHP_EOL;
        echo "  php discador_diagnostic.php stress 60 20" . PHP_EOL;
    }
}

// Execução
if (php_sapi_name() === 'cli') {
    $diagnostic = new DiscadorDiagnostic();
    $diagnostic->run($argv);
} else {
    echo "Este script deve ser executado via CLI" . PHP_EOL;
}
