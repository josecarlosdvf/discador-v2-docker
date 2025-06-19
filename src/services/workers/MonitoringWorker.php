<?php
/**
 * Monitoring Worker - Monitor de Eventos Asterisk v2.0
 * Responsável por monitorar eventos do Asterisk e atualizar status
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../DistributedLock.php';

class MonitoringWorker {
    private $workerId;
    private $db;
    private $redis;
    private $asteriskSocket;
    private $isRunning = false;
    private $activeCalls = [];
    
    const HEARTBEAT_INTERVAL = 30;
    const AMI_TIMEOUT = 30;
    
    public function __construct(string $workerId) {
        $this->workerId = $workerId;
        
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->redis = new Redis();
            $this->redis->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) {
                $this->redis->auth(REDIS_PASS);
            }
            
            $this->connectToAsterisk();
            $this->logMessage("MonitoringWorker {$workerId} inicializado", 'info');
            
        } catch (Exception $e) {
            $this->logMessage("Erro na inicialização: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
    
    /**
     * Loop principal do worker
     */
    public function run() {
        $this->isRunning = true;
        $lastHeartbeat = 0;
        
        while ($this->isRunning) {
            $currentTime = time();
            
            try {
                // Heartbeat
                if ($currentTime - $lastHeartbeat >= self::HEARTBEAT_INTERVAL) {
                    $this->sendHeartbeat();
                    $this->pingAsterisk();
                    $lastHeartbeat = $currentTime;
                }
                
                // Processar eventos do Asterisk
                $this->processAsteriskEvents();
                
                // Limpar chamadas antigas
                $this->cleanupOldCalls();
                
                // Aguardar próximo ciclo
                usleep(100000); // 100ms
                
            } catch (Exception $e) {
                $this->logMessage("Erro no loop: " . $e->getMessage(), 'error');
                
                // Tentar reconectar
                $this->reconnectToAsterisk();
                usleep(5000000); // 5 segundos
            }
        }
        
        $this->cleanup();
    }
    
    /**
     * Conecta ao Asterisk AMI
     */
    private function connectToAsterisk() {
        $this->asteriskSocket = fsockopen(
            ASTERISK_AMI_HOST, 
            ASTERISK_AMI_PORT, 
            $errno, 
            $errstr, 
            10
        );
        
        if (!$this->asteriskSocket) {
            throw new Exception("Falha ao conectar AMI: {$errstr} ({$errno})");
        }
        
        // Login no AMI
        $this->sendAMIAction([
            'Action' => 'Login',
            'Username' => ASTERISK_AMI_USERNAME,
            'Secret' => ASTERISK_AMI_PASSWORD
        ]);
        
        $response = $this->readAMIResponse();
        
        if (!isset($response['Response']) || $response['Response'] !== 'Success') {
            throw new Exception("Falha no login AMI");
        }
        
        $this->logMessage("Conectado ao Asterisk AMI", 'info');
    }
    
    /**
     * Processa eventos do Asterisk
     */
    private function processAsteriskEvents() {
        if (!$this->asteriskSocket) {
            return;
        }
        
        // Verificar se há dados disponíveis
        $read = [$this->asteriskSocket];
        $write = $except = null;
        
        if (stream_select($read, $write, $except, 0, 100000) > 0) {
            $event = $this->readAMIResponse();
            
            if ($event && isset($event['Event'])) {
                $this->handleAsteriskEvent($event);
            }
        }
    }
    
    /**
     * Manipula eventos do Asterisk
     */
    private function handleAsteriskEvent($event) {
        $eventType = $event['Event'];
        
        switch ($eventType) {
            case 'Dial':
                $this->handleDialEvent($event);
                break;
                
            case 'Bridge':
                $this->handleBridgeEvent($event);
                break;
                
            case 'Hangup':
                $this->handleHangupEvent($event);
                break;
                
            case 'VarSet':
                $this->handleVarSetEvent($event);
                break;
                
            case 'OriginateResponse':
                $this->handleOriginateResponseEvent($event);
                break;
                
            case 'PeerStatus':
                $this->handlePeerStatusEvent($event);
                break;
                
            case 'QueueMemberStatus':
                $this->handleQueueMemberStatusEvent($event);
                break;
        }
        
        // Log do evento (apenas para debug)
        if (in_array($eventType, ['Dial', 'Bridge', 'Hangup', 'OriginateResponse'])) {
            $this->logMessage("Evento {$eventType}: " . json_encode($event), 'debug');
        }
    }
    
    /**
     * Manipula evento Dial
     */
    private function handleDialEvent($event) {
        if (!isset($event['SubEvent']) || $event['SubEvent'] !== 'Begin') {
            return;
        }
        
        $uniqueId = $event['Uniqueid'] ?? '';
        $channel = $event['Channel'] ?? '';
        $destination = $event['DestChannel'] ?? '';
        $callerIdNum = $event['CallerIDNum'] ?? '';
        
        if (!$uniqueId) return;
        
        // Registrar chamada ativa
        $this->activeCalls[$uniqueId] = [
            'unique_id' => $uniqueId,
            'channel' => $channel,
            'destination' => $destination,
            'caller_id' => $callerIdNum,
            'status' => 'DIALING',
            'start_time' => time(),
            'contact_id' => $this->extractContactId($event),
            'campaign_id' => $this->extractCampaignId($event)
        ];
        
        // Atualizar banco de dados
        $this->updateCallStatus($uniqueId, 'DIALING', $event);
    }
    
    /**
     * Manipula evento Bridge
     */
    private function handleBridgeEvent($event) {
        $uniqueId1 = $event['Uniqueid1'] ?? '';
        $uniqueId2 = $event['Uniqueid2'] ?? '';
        $bridgeState = $event['Bridgestate'] ?? '';
        
        if ($bridgeState === 'Link') {
            // Chamada conectada
            foreach ([$uniqueId1, $uniqueId2] as $uniqueId) {
                if (isset($this->activeCalls[$uniqueId])) {
                    $this->activeCalls[$uniqueId]['status'] = 'CONNECTED';
                    $this->activeCalls[$uniqueId]['answer_time'] = time();
                    
                    $this->updateCallStatus($uniqueId, 'CONNECTED', $event);
                    
                    // Atualizar hopper
                    $contactId = $this->activeCalls[$uniqueId]['contact_id'];
                    if ($contactId) {
                        $this->updateHopperStatus($contactId, 'ANSWERED');
                    }
                }
            }
        }
    }
    
    /**
     * Manipula evento Hangup
     */
    private function handleHangupEvent($event) {
        $uniqueId = $event['Uniqueid'] ?? '';
        $cause = $event['Cause'] ?? '';
        $causeTxt = $event['Cause-txt'] ?? '';
        
        if (!$uniqueId || !isset($this->activeCalls[$uniqueId])) {
            return;
        }
        
        $call = $this->activeCalls[$uniqueId];
        
        // Determinar status final
        $finalStatus = $this->determineFinalStatus($cause, $causeTxt, $call);
        
        // Atualizar chamada
        $call['status'] = $finalStatus;
        $call['end_time'] = time();
        $call['duration'] = $call['end_time'] - $call['start_time'];
        $call['hangup_cause'] = $cause;
        
        $this->updateCallStatus($uniqueId, $finalStatus, $event);
        
        // Atualizar hopper se necessário
        if ($call['contact_id'] && $finalStatus !== 'ANSWERED') {
            $this->updateHopperStatus($call['contact_id'], $finalStatus);
        }
        
        // Remover da lista de chamadas ativas
        unset($this->activeCalls[$uniqueId]);
        
        $this->logMessage("Chamada {$uniqueId} finalizada: {$finalStatus}", 'info');
    }
    
    /**
     * Manipula evento VarSet
     */
    private function handleVarSetEvent($event) {
        $variable = $event['Variable'] ?? '';
        $value = $event['Value'] ?? '';
        $uniqueId = $event['Uniqueid'] ?? '';
        
        // Eventos de interesse
        if ($variable === 'AMDSTATUS' || $variable === 'DIALSTATUS') {
            if (isset($this->activeCalls[$uniqueId])) {
                $this->activeCalls[$uniqueId][$variable] = $value;
            }
        }
        
        // Variáveis customizadas da campanha
        if (strpos($variable, 'CONTACT_') === 0 || strpos($variable, 'CAMPAIGN_') === 0) {
            if (isset($this->activeCalls[$uniqueId])) {
                $this->activeCalls[$uniqueId]['variables'][$variable] = $value;
            }
        }
    }
    
    /**
     * Manipula evento OriginateResponse
     */
    private function handleOriginateResponseEvent($event) {
        $response = $event['Response'] ?? '';
        $reason = $event['Reason'] ?? '';
        $uniqueId = $event['Uniqueid'] ?? '';
        $actionId = $event['ActionID'] ?? '';
        
        if ($response === 'Failure') {
            // Falha na originação
            $contactId = $this->extractContactIdFromActionId($actionId);
            
            if ($contactId) {
                $this->updateHopperStatus($contactId, 'FAILED');
                $this->logMessage("Falha na originação para contato {$contactId}: {$reason}", 'warning');
            }
        }
    }
    
    /**
     * Manipula evento PeerStatus
     */
    private function handlePeerStatusEvent($event) {
        $peer = $event['Peer'] ?? '';
        $peerStatus = $event['PeerStatus'] ?? '';
        
        // Extrair ramal do peer (ex: SIP/1001)
        if (strpos($peer, '/') !== false) {
            $extension = explode('/', $peer)[1];
            
            $registered = ($peerStatus === 'Registered') ? 1 : 0;
            
            // Atualizar status do ramal
            $stmt = $this->db->prepare("
                UPDATE ramais 
                SET registrado = ?, last_update = NOW() 
                WHERE numero = ?
            ");
            
            $stmt->execute([$registered, $extension]);
            
            $this->logMessage("Ramal {$extension}: {$peerStatus}", 'debug');
        }
    }
    
    /**
     * Manipula evento QueueMemberStatus
     */
    private function handleQueueMemberStatusEvent($event) {
        $queue = $event['Queue'] ?? '';
        $location = $event['Location'] ?? '';
        $status = $event['Status'] ?? '';
        
        // Atualizar status do membro da fila
        if ($queue && $location) {
            $stmt = $this->db->prepare("
                UPDATE queue_members 
                SET status = ?, last_update = NOW() 
                WHERE queue_name = ? AND interface = ?
            ");
            
            $stmt->execute([$status, $queue, $location]);
        }
    }
    
    /**
     * Determina status final da chamada
     */
    private function determineFinalStatus($cause, $causeTxt, $call) {
        // Verificar se foi atendida
        if (isset($call['answer_time']) && $call['status'] === 'CONNECTED') {
            return 'ANSWERED';
        }
        
        // Mapear causas de hangup
        switch ($cause) {
            case '17': // USER_BUSY
                return 'BUSY';
                
            case '18': // NO_USER_RESPONSE
            case '19': // NO_ANSWER
                return 'NOANSWER';
                
            case '21': // CALL_REJECTED
            case '22': // NUMBER_CHANGED
                return 'REJECTED';
                
            case '1':  // UNALLOCATED
            case '3':  // NO_ROUTE_DESTINATION
                return 'INVALID';
                
            default:
                return 'FAILED';
        }
    }
    
    /**
     * Extrai ID do contato do evento
     */
    private function extractContactId($event) {
        // Tentar extrair de variáveis
        if (isset($event['Variable'])) {
            $vars = explode(',', $event['Variable']);
            foreach ($vars as $var) {
                if (strpos($var, 'CONTACT_ID=') === 0) {
                    return substr($var, 11);
                }
            }
        }
        
        // Tentar extrair do CallerID
        $callerIdNum = $event['CallerIDNum'] ?? '';
        if (is_numeric($callerIdNum)) {
            return $callerIdNum;
        }
        
        return null;
    }
    
    /**
     * Extrai ID da campanha do evento
     */
    private function extractCampaignId($event) {
        if (isset($event['Variable'])) {
            $vars = explode(',', $event['Variable']);
            foreach ($vars as $var) {
                if (strpos($var, 'CAMPAIGN_ID=') === 0) {
                    return substr($var, 12);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extrai ID do contato do ActionID
     */
    private function extractContactIdFromActionId($actionId) {
        if (strpos($actionId, 'call_') === 0) {
            // ActionID formato: call_CONTACTID_timestamp
            $parts = explode('_', $actionId);
            if (count($parts) >= 2) {
                return $parts[1];
            }
        }
        
        return null;
    }
    
    /**
     * Atualiza status da chamada no banco
     */
    private function updateCallStatus($uniqueId, $status, $event) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO active_calls (
                    unique_id, call_id, status, channel, destination,
                    caller_id, event_data, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                event_data = VALUES(event_data),
                updated_at = NOW()
            ");
            
            $call = $this->activeCalls[$uniqueId] ?? [];
            
            $stmt->execute([
                $uniqueId,
                $event['ActionID'] ?? $uniqueId,
                $status,
                $call['channel'] ?? $event['Channel'] ?? '',
                $call['destination'] ?? $event['DestChannel'] ?? '',
                $call['caller_id'] ?? $event['CallerIDNum'] ?? '',
                json_encode($event)
            ]);
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao atualizar status da chamada: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Atualiza status no hopper
     */
    private function updateHopperStatus($contactId, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE dialer_hopper 
                SET status = ?, last_attempt = NOW(), updated_at = NOW()
                WHERE contato_id = ?
            ");
            
            $stmt->execute([$status, $contactId]);
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao atualizar hopper: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Limpa chamadas antigas
     */
    private function cleanupOldCalls() {
        $cutoffTime = time() - 3600; // 1 hora atrás
        
        foreach ($this->activeCalls as $uniqueId => $call) {
            if ($call['start_time'] < $cutoffTime) {
                $this->logMessage("Removendo chamada órfã: {$uniqueId}", 'warning');
                unset($this->activeCalls[$uniqueId]);
            }
        }
        
        // Limpar banco de dados
        $stmt = $this->db->prepare("
            DELETE FROM active_calls 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            AND status NOT IN ('CONNECTED')
        ");
        
        $stmt->execute();
    }
    
    /**
     * Envia ping para Asterisk
     */
    private function pingAsterisk() {
        if (!$this->asteriskSocket) {
            return;
        }
        
        $this->sendAMIAction(['Action' => 'Ping']);
    }
    
    /**
     * Reconecta ao Asterisk
     */
    private function reconnectToAsterisk() {
        $this->logMessage("Tentando reconectar ao Asterisk", 'warning');
        
        if ($this->asteriskSocket) {
            fclose($this->asteriskSocket);
            $this->asteriskSocket = null;
        }
        
        try {
            $this->connectToAsterisk();
        } catch (Exception $e) {
            $this->logMessage("Falha na reconexão: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Envia ação AMI
     */
    private function sendAMIAction($action) {
        if (!$this->asteriskSocket) {
            return false;
        }
        
        $message = '';
        foreach ($action as $key => $value) {
            $message .= "{$key}: {$value}\r\n";
        }
        $message .= "\r\n";
        
        return fwrite($this->asteriskSocket, $message);
    }
    
    /**
     * Lê resposta AMI
     */
    private function readAMIResponse() {
        if (!$this->asteriskSocket) {
            return null;
        }
        
        $response = [];
        $buffer = '';
        
        while (true) {
            $line = fgets($this->asteriskSocket, 4096);
            
            if ($line === false) {
                break;
            }
            
            $line = trim($line);
            
            if ($line === '') {
                break;
            }
            
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                $response[$key] = $value;
            }
        }
        
        return $response;
    }
    
    /**
     * Envia heartbeat
     */
    private function sendHeartbeat() {
        $heartbeatKey = "worker:heartbeat:{$this->workerId}";
        $this->redis->setex($heartbeatKey, self::HEARTBEAT_INTERVAL * 2, time());
        
        // Estatísticas do worker
        $stats = [
            'active_calls' => count($this->activeCalls),
            'asterisk_connected' => $this->asteriskSocket !== null,
            'last_update' => time()
        ];
        
        $statsKey = "worker:stats:{$this->workerId}";
        $this->redis->setex($statsKey, 300, json_encode($stats));
    }
    
    /**
     * Cleanup ao finalizar
     */
    private function cleanup() {
        $this->logMessage("Iniciando cleanup do monitoring worker", 'info');
        
        if ($this->asteriskSocket) {
            fclose($this->asteriskSocket);
        }
        
        // Remover heartbeat
        $heartbeatKey = "worker:heartbeat:{$this->workerId}";
        $this->redis->del($heartbeatKey);
        
        $this->logMessage("Cleanup concluído", 'info');
    }
    
    /**
     * Para o worker
     */
    public function stop() {
        $this->isRunning = false;
    }
    
    /**
     * Log de mensagens
     */
    private function logMessage($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [MONITORING-WORKER:{$this->workerId}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents('/var/log/discador/monitoring-worker.log', $logLine, FILE_APPEND | LOCK_EX);
        
        if ($level === 'error') {
            error_log($logLine);
        }
    }
}
?>
