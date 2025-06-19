<?php
/**
 * API para Status do Discador v2.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Função para sempre retornar JSON válido
function returnJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Status padrão
    $status = [
        'master' => [
            'status' => 'Verificando...',
            'running' => false,
            'pid' => null,
            'uptime' => null
        ],
        'workers' => [
            'active' => 0,
            'total' => 0,
            'details' => []
        ],
        'queue' => [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0
        ],
        'redis' => [
            'status' => 'Offline',
            'connected' => false,
            'info' => null
        ]
    ];
    
    // Tentar conectar ao Redis
    $redis = null;
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            $password = $_ENV['REDIS_PASSWORD'] ?? 'redis123';
            
            if ($redis->connect($host, $port, 2)) {
                if ($password) {
                    $redis->auth($password);
                }
                $redis->select($_ENV['REDIS_DB'] ?? 0);
                
                $status['redis']['status'] = 'Online';
                $status['redis']['connected'] = true;
                
                // Tentar obter dados do Redis
                try {
                    // Check master process
                    $masterInfo = $redis->hGetAll('discador:master:info');
                    if ($masterInfo && !empty($masterInfo)) {
                        $status['master']['status'] = $masterInfo['status'] ?? 'Unknown';
                        $status['master']['running'] = ($masterInfo['status'] ?? '') === 'running';
                        $status['master']['pid'] = $masterInfo['pid'] ?? null;
                        $status['master']['uptime'] = $masterInfo['started_at'] ?? null;
                    }
                    
                    // Check workers
                    $workerKeys = $redis->keys('discador:worker:*:heartbeat');
                    $activeWorkers = 0;
                    $workerDetails = [];
                    
                    foreach ($workerKeys as $key) {
                        $lastHeartbeat = $redis->get($key);
                        if ($lastHeartbeat && (time() - $lastHeartbeat) < 60) {
                            $activeWorkers++;
                            $workerId = str_replace(['discador:worker:', ':heartbeat'], '', $key);
                            $workerInfo = $redis->hGetAll("discador:worker:{$workerId}:info");
                            $workerDetails[] = [
                                'id' => $workerId,
                                'type' => $workerInfo['type'] ?? 'unknown',
                                'status' => $workerInfo['status'] ?? 'unknown',
                                'last_heartbeat' => $lastHeartbeat
                            ];
                        }
                    }
                    
                    $status['workers']['active'] = $activeWorkers;
                    $status['workers']['total'] = count($workerKeys);
                    $status['workers']['details'] = $workerDetails;
                    
                    // Check queue
                    $queueStats = $redis->hGetAll('discador:queue:stats');
                    if ($queueStats && !empty($queueStats)) {
                        $status['queue']['pending'] = (int)($queueStats['pending'] ?? 0);
                        $status['queue']['processing'] = (int)($queueStats['processing'] ?? 0);
                        $status['queue']['failed'] = (int)($queueStats['failed'] ?? 0);
                    }
                    
                    // Get Redis info
                    $redisInfo = $redis->info();
                    if ($redisInfo) {
                        $status['redis']['info'] = [
                            'version' => $redisInfo['redis_version'] ?? 'unknown',
                            'used_memory' => $redisInfo['used_memory_human'] ?? 'unknown',
                            'connected_clients' => $redisInfo['connected_clients'] ?? 0
                        ];
                    }
                    
                } catch (Exception $e) {
                    // Redis conectado mas erro ao obter dados
                    error_log("Redis data retrieval error: " . $e->getMessage());
                }
            } else {
                $status['redis']['status'] = 'Conexão falhou';
            }
        } catch (Exception $e) {
            $status['redis']['status'] = 'Erro: ' . $e->getMessage();
        }
    } else {
        $status['redis']['status'] = 'Extensão Redis não encontrada';
    }
    
    returnJson($status);
    
} catch (Exception $e) {
    // Em caso de qualquer erro, retorna status de erro mas sempre JSON válido
    $errorStatus = [
        'error' => 'Failed to get status: ' . $e->getMessage(),
        'master' => ['status' => 'Error', 'running' => false, 'pid' => null, 'uptime' => null],
        'workers' => ['active' => 0, 'total' => 0, 'details' => []],
        'queue' => ['pending' => 0, 'processing' => 0, 'failed' => 0],
        'redis' => ['status' => 'Error', 'connected' => false, 'info' => null]
    ];
    
    returnJson($errorStatus);
}
?>
