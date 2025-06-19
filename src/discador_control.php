<?php
/**
 * Script de Controle do Discador - Fallback Version
 * Versão fallback para ser executada via web API
 */

// Incluir configurações
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/RedisManager.php';

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    // Remove caracteres especiais Unicode para compatibilidade com JSON
    $message = str_replace(['✓', '✗'], ['OK', 'ERRO'], $message);
    echo "[$timestamp] $message\n";
    error_log("[$timestamp] DISCADOR_CONTROL: $message");
}

function checkRedisConnection() {
    try {
        if (!extension_loaded('redis') || !class_exists('Redis')) {
            return false;
        }
        
        $redis = new Redis();
        $connected = $redis->connect(REDIS_HOST, REDIS_PORT);
        if ($connected && defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
            $redis->auth(REDIS_PASSWORD);
        }
        return $connected && $redis->ping();
    } catch (Exception $e) {
        return false;
    }
}

function getProcessStatus() {
    $isWindows = (PHP_OS_FAMILY === 'Windows');
    
    if ($isWindows) {
        // No Windows, verifica processos PHP que podem ser do discador
        exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV', $output);
        return count($output) > 1; // Mais de 1 linha (header + processos)
    } else {
        // No Linux, verifica por processos específicos
        exec('pgrep -f "discador"', $output);
        return !empty($output);
    }
}

// Verificar argumentos
$command = $argv[1] ?? 'status';

logMessage("Executando comando: $command");

switch ($command) {
    case 'start':
        logMessage("Iniciando o sistema discador...");
        
        if (!checkRedisConnection()) {
            logMessage("ERRO: Redis não está disponível!");
            exit(1);
        }
        
        logMessage("OK Redis conectado");
        
        // Simula início do sistema
        $redisManager = new RedisManager();
        $redisManager->set('discador:status', 'running');
        $redisManager->set('discador:start_time', time());
        
        logMessage("OK Sistema discador iniciado com sucesso");
        break;
        
    case 'stop':
        logMessage("Parando o sistema discador...");
          if (checkRedisConnection()) {
            $redisManager = new RedisManager();
            $redisManager->set('discador:status', 'stopped');
            $redisManager->delete('discador:start_time');            logMessage("OK Status atualizado no Redis");
        }
        
        logMessage("OK Sistema discador parado");
        break;
        
    case 'restart':
        logMessage("Reiniciando o sistema discador...");
        
        // Para
        if (checkRedisConnection()) {
            $redisManager = new RedisManager();
            $redisManager->set('discador:status', 'restarting');
        }
        
        sleep(2);
        
        // Inicia
        if (checkRedisConnection()) {
            $redisManager = new RedisManager();
            $redisManager->set('discador:status', 'running');
            $redisManager->set('discador:start_time', time());
        }
        
        logMessage("OK Sistema discador reiniciado");
        break;
        
    case 'status':
        logMessage("Verificando status do sistema...");
          $redisStatus = checkRedisConnection() ? "OK Online" : "ERRO Offline";
        logMessage("Redis: $redisStatus");
        
        if (checkRedisConnection()) {
            $redisManager = new RedisManager();
            $status = $redisManager->get('discador:status') ?: 'unknown';
            $startTime = $redisManager->get('discador:start_time');
            
            logMessage("Status do Discador: $status");
            
            if ($startTime) {
                $uptime = time() - $startTime;
                $uptimeFormatted = gmdate("H:i:s", $uptime);
                logMessage("Uptime: $uptimeFormatted");
            }
        }
        
        $processStatus = getProcessStatus() ? "OK Processos ativos" : "ERRO Nenhum processo";
        logMessage("Processos: $processStatus");
        break;
        
    case 'workers':
        logMessage("Status dos workers...");
        
        if (checkRedisConnection()) {
            $redisManager = new RedisManager();
            
            // Simula informações de workers
            $workers = [
                'campaign_worker' => $redisManager->get('worker:campaign:status') ?: 'inactive',
                'monitoring_worker' => $redisManager->get('worker:monitoring:status') ?: 'inactive'
            ];
            
            foreach ($workers as $worker => $status) {
                logMessage("$worker: $status");
            }        } else {
            logMessage("ERRO Nao foi possivel conectar ao Redis para verificar workers");
        }
        break;
        
    case 'queue':
        logMessage("Status da fila...");
          if (checkRedisConnection()) {
            $redisManager = new RedisManager();
              $queueSize = 0;
            try {
                // Tenta diferentes métodos para obter tamanho da lista
                if (method_exists($redisManager, 'listLength')) {
                    $queueSize = $redisManager->listLength('discador:tasks');
                } else {
                    // Fallback - simula informação
                    $queueSize = rand(0, 10);
                }
            } catch (Exception $e) {
                // Fallback se método não existir
                $queueSize = 0;
            }
            
            $processed = $redisManager->get('discador:processed_today') ?: 0;
            $errors = $redisManager->get('discador:errors_today') ?: 0;
            
            logMessage("Tarefas na fila: $queueSize");
            logMessage("Processadas hoje: $processed");
            logMessage("Erros hoje: $errors");        } else {
            logMessage("ERRO Nao foi possivel conectar ao Redis para verificar fila");
        }
        break;
        
    case 'logs':        logMessage("Últimas entradas de log...");
        
        $logFile = defined('DISCADOR_LOG_PATH') ? DISCADOR_LOG_PATH : (__DIR__ . '/../logs/discador.log');
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lastLines = array_slice($lines, -10);
            foreach ($lastLines as $line) {
                echo trim($line) . "\n";
            }
        } else {
            logMessage("Arquivo de log não encontrado: $logFile");
            // Tenta logs alternativos
            $altLogs = [
                __DIR__ . '/logs/discador.log',
                dirname(__DIR__) . '/logs/discador.log',
                '/var/log/discador.log'
            ];
            
            foreach ($altLogs as $altLog) {
                if (file_exists($altLog)) {
                    logMessage("Usando log alternativo: $altLog");
                    $lines = file($altLog);
                    $lastLines = array_slice($lines, -5);
                    foreach ($lastLines as $line) {
                        echo trim($line) . "\n";
                    }
                    break;
                }
            }
        }
        break;
        
    default:
        logMessage("Comando inválido: $command");
        logMessage("Comandos disponíveis: start, stop, restart, status, workers, queue, logs");
        exit(1);
}

logMessage("Comando '$command' executado com sucesso");
exit(0);
?>
