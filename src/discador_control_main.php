<?php
/**
 * Script de Controle do Discador v2.0 - Versão Principal
 * Com suporte completo ao Redis para uso no Docker
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/RedisManager.php';

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("[$timestamp] DISCADOR_CONTROL: $message");
}

function getRedisConnection() {
    try {
        $redis = new Redis();
        $connected = $redis->connect(REDIS_HOST, REDIS_PORT, 5);
        if (!$connected) {
            return false;
        }
        
        if (REDIS_PASS) {
            $auth = $redis->auth(REDIS_PASS);
            if (!$auth) {
                return false;
            }
        }
        
        return $redis;
    } catch (Exception $e) {
        error_log("Redis connection error: " . $e->getMessage());
        return false;
    }
}

function getProcessStatus() {
    // No Docker, verifica processos PHP do discador
    $output = shell_exec('ps aux | grep discador | grep -v grep');
    return !empty(trim($output));
}

// Verificar argumentos
$command = $argv[1] ?? 'status';

logMessage("Executando comando: $command (versao principal com Redis)");

$redis = getRedisConnection();
if (!$redis) {
    logMessage("AVISO: Redis nao conectado, funcionalidade limitada");
}

switch ($command) {
    case 'start':
        logMessage("Iniciando o sistema discador...");
        
        if ($redis) {
            $redis->set('discador:status', 'running');
            $redis->set('discador:start_time', time());
            $redis->set('discador:master_pid', getmypid());
            logMessage("Status salvo no Redis");
        }
        
        logMessage("Sistema discador iniciado com sucesso");
        break;
        
    case 'stop':
        logMessage("Parando o sistema discador...");
        
        if ($redis) {
            $redis->set('discador:status', 'stopped');
            $redis->del('discador:start_time');
            $redis->del('discador:master_pid');
            logMessage("Status atualizado no Redis");
        }
        
        logMessage("Sistema discador parado");
        break;
        
    case 'restart':
        logMessage("Reiniciando o sistema discador...");
        
        if ($redis) {
            $redis->set('discador:status', 'restarting');
            sleep(2);
            $redis->set('discador:status', 'running');
            $redis->set('discador:start_time', time());
            $redis->set('discador:master_pid', getmypid());
            logMessage("Status atualizado no Redis");
        }
        
        logMessage("Sistema discador reiniciado");
        break;
        
    case 'status':
        logMessage("Verificando status do sistema...");
        
        if ($redis) {
            logMessage("Redis: Conectado");
            
            $status = $redis->get('discador:status') ?: 'stopped';
            $startTime = $redis->get('discador:start_time');
            $masterPid = $redis->get('discador:master_pid');
            
            logMessage("Status do Discador: $status");
            
            if ($startTime) {
                $uptime = time() - $startTime;
                $uptimeFormatted = gmdate("H:i:s", $uptime);
                logMessage("Uptime: $uptimeFormatted");
            }
            
            if ($masterPid) {
                logMessage("Master PID: $masterPid");
            }
        } else {
            logMessage("Redis: Desconectado");
        }
        
        $processStatus = getProcessStatus();
        logMessage("Processos: " . ($processStatus ? "Ativos" : "Nenhum processo"));
        break;
        
    case 'workers':
        logMessage("Status dos workers...");
        
        if ($redis) {
            $workers = [
                'campaign_worker' => $redis->get('worker:campaign:status') ?: 'inactive',
                'monitoring_worker' => $redis->get('worker:monitoring:status') ?: 'inactive',
                'queue_worker' => $redis->get('worker:queue:status') ?: 'inactive'
            ];
            
            foreach ($workers as $worker => $status) {
                logMessage("$worker: $status");
            }
            
            // Informações das filas
            $queueSize = $redis->llen('discador:tasks') ?: 0;
            logMessage("Fila principal: $queueSize tarefas");
            
        } else {
            logMessage("Impossivel verificar workers sem Redis");
        }
        break;
        
    case 'queue':
        logMessage("Status da fila...");
        
        if ($redis) {
            $queueSize = $redis->llen('discador:tasks') ?: 0;
            $processed = $redis->get('discador:processed_today') ?: 0;
            $errors = $redis->get('discador:errors_today') ?: 0;
            $lastProcessed = $redis->get('discador:last_processed');
            
            logMessage("Tarefas na fila: $queueSize");
            logMessage("Processadas hoje: $processed");
            logMessage("Erros hoje: $errors");
            
            if ($lastProcessed) {
                logMessage("Ultima tarefa: " . date('H:i:s', $lastProcessed));
            }
        } else {
            logMessage("Impossivel verificar fila sem Redis");
        }
        break;
        
    case 'logs':
        logMessage("Ultimas entradas de log...");
        
        $logFile = '/var/www/html/logs/discador.log';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lastLines = array_slice($lines, -10);
            foreach ($lastLines as $line) {
                echo trim($line) . "\n";
            }
        } else {
            logMessage("Arquivo de log nao encontrado");
            
            // Tenta logs do Redis
            if ($redis) {
                $recentLogs = $redis->lrange('discador:logs', -10, -1);
                foreach ($recentLogs as $log) {
                    echo $log . "\n";
                }
            }
        }
        break;
        
    default:
        logMessage("Comando invalido: $command");
        logMessage("Comandos disponiveis: start, stop, restart, status, workers, queue, logs");
        exit(1);
}

logMessage("Comando '$command' executado com sucesso");

if ($redis) {
    // Registrar execução do comando
    $redis->lpush('discador:command_history', json_encode([
        'command' => $command,
        'timestamp' => time(),
        'user' => 'system'
    ]));
    $redis->ltrim('discador:command_history', 0, 99); // Manter últimos 100
}

exit(0);
?>
