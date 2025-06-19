<?php
/**
 * Campaign Worker - Gerenciador de Campanhas do Discador v2.0
 * Responsável por executar campanhas de discagem
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../DistributedLock.php';

class CampaignWorker {
    private $workerId;
    private $db;
    private $redis;
    private $lock;
    private $isRunning = false;
    private $asteriskManager;
    private $currentCampaign = null;
    
    const HEARTBEAT_INTERVAL = 30;
    const CALL_TIMEOUT = 45;
    const MAX_DIAL_ATTEMPTS = 3;
    
    public function __construct(string $workerId) {
        $this->workerId = $workerId;
        
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->redis = new Redis();
            $this->redis->connect(REDIS_HOST, REDIS_PORT);
            if (REDIS_PASS) {
                $this->redis->auth(REDIS_PASS);
            }
            
            $this->lock = new DistributedLock();
            $this->setupAsteriskConnection();
            
            $this->logMessage("Worker {$workerId} inicializado", 'info');
            
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
                    $lastHeartbeat = $currentTime;
                }
                
                // Verificar se deve processar campanha
                if (!$this->currentCampaign) {
                    $this->acquireCampaign();
                }
                
                // Processar campanha atual
                if ($this->currentCampaign) {
                    $this->processCampaign();
                }
                
                // Aguardar próximo ciclo
                usleep(1000000); // 1 segundo
                
            } catch (Exception $e) {
                $this->logMessage("Erro no loop: " . $e->getMessage(), 'error');
                usleep(5000000); // 5 segundos antes de tentar novamente
            }
        }
        
        $this->cleanup();
    }
    
    /**
     * Adquire uma campanha para processar
     */
    private function acquireCampaign() {
        // Buscar campanhas ativas sem worker
        $stmt = $this->db->prepare("
            SELECT c.* FROM campanhas c
            WHERE c.ativo = 1 
            AND c.status IN ('running', 'starting')
            AND NOT EXISTS (
                SELECT 1 FROM campaign_workers cw 
                WHERE cw.campaign_id = c.id 
                AND cw.worker_id != ? 
                AND cw.last_heartbeat > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            )
            ORDER BY c.prioridade DESC, c.id ASC
            LIMIT 1
        ");
        
        $stmt->execute([$this->workerId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            return false;
        }
        
        // Tentar adquirir lock da campanha
        $lockKey = "campaign:{$campaign['id']}";
        $lockValue = $this->lock->acquire($lockKey, 300);
        
        if (!$lockValue) {
            return false;
        }
        
        // Registrar worker para a campanha
        $stmt = $this->db->prepare("
            INSERT INTO campaign_workers (campaign_id, worker_id, started_at, last_heartbeat)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE last_heartbeat = NOW()
        ");
        
        $stmt->execute([$campaign['id'], $this->workerId]);
        
        $this->currentCampaign = array_merge($campaign, ['lock_value' => $lockValue]);
        $this->logMessage("Adquiriu campanha {$campaign['id']}: {$campaign['nome']}", 'info');
        
        return true;
    }
    
    /**
     * Processa a campanha atual
     */
    private function processCampaign() {
        if (!$this->currentCampaign) {
            return;
        }
        
        $campaign = $this->currentCampaign;
        
        // Verificar se campanha ainda está ativa
        if (!$this->isCampaignActive($campaign['id'])) {
            $this->releaseCampaign();
            return;
        }
        
        // Obter estatísticas da campanha
        $stats = $this->getCampaignStats($campaign['id']);
        
        // Verificar limites
        if ($stats['active_calls'] >= $campaign['max_canais']) {
            $this->logMessage("Campanha {$campaign['id']} atingiu limite de canais", 'debug');
            return;
        }
        
        // Calcular quantas chamadas iniciar
        $availableChannels = $campaign['max_canais'] - $stats['active_calls'];
        $multiplier = $this->calculateDialMultiplier($campaign, $stats);
        $callsToMake = min($availableChannels * $multiplier, $campaign['max_canais']);
        
        if ($callsToMake > 0) {
            $this->makeDialCalls($campaign, $callsToMake);
        }
        
        // Processar hopper (contatos pendentes)
        $this->processHopper($campaign);
    }
    
    /**
     * Calcula multiplicador de discagem baseado em estatísticas
     */
    private function calculateDialMultiplier($campaign, $stats) {
        $baseMultiplier = $campaign['impulso'] ?? 1;
        
        // Calcular taxa de atendimento
        $totalCalls = $stats['answered_calls'] + $stats['failed_calls'];
        if ($totalCalls == 0) {
            return $baseMultiplier;
        }
        
        $answerRate = $stats['answered_calls'] / $totalCalls;
        
        // Ajustar multiplicador baseado na taxa de atendimento
        if ($answerRate < 0.3) {
            $multiplier = min($baseMultiplier * 1.5, $campaign['max_multiplicador'] ?? 3);
        } elseif ($answerRate > 0.7) {
            $multiplier = max($baseMultiplier * 0.8, 1);
        } else {
            $multiplier = $baseMultiplier;
        }
        
        $this->logMessage("Multiplicador calculado: {$multiplier} (taxa resposta: " . round($answerRate * 100, 2) . "%)", 'debug');
        
        return $multiplier;
    }
    
    /**
     * Realiza chamadas de discagem
     */
    private function makeDialCalls($campaign, $count) {
        // Buscar contatos para discar
        $contacts = $this->getContactsToCall($campaign['id'], $count);
        
        foreach ($contacts as $contact) {
            if (!$this->isRunning) break;
            
            $this->initiateCall($campaign, $contact);
            usleep(100000); // 100ms entre chamadas
        }
    }
    
    /**
     * Busca contatos para chamada
     */
    private function getContactsToCall($campaignId, $limit) {
        $stmt = $this->db->prepare("
            SELECT c.*, h.attempts, h.last_attempt
            FROM dialer_hopper h
            JOIN dialer_contato c ON h.contato_id = c.id
            WHERE h.campaign_id = ?
            AND h.status = 'WAITING'
            AND (h.next_attempt IS NULL OR h.next_attempt <= NOW())
            ORDER BY h.priority DESC, h.created_at ASC
            LIMIT ?
        ");
        
        $stmt->execute([$campaignId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Inicia uma chamada
     */
    private function initiateCall($campaign, $contact) {
        try {
            // Atualizar status no hopper
            $this->updateHopperStatus($contact['contato_id'], 'DIALING');
            
            // Preparar parâmetros da chamada
            $callParams = [
                'contact_id' => $contact['contato_id'],
                'campaign_id' => $campaign['id'],
                'phone' => $contact['telefone'],
                'worker_id' => $this->workerId,
                'attempt' => ($contact['attempts'] ?? 0) + 1
            ];
            
            // Originar chamada via Asterisk
            $callId = $this->originateCall($callParams);
            
            if ($callId) {
                $this->logMessage("Chamada iniciada para {$contact['telefone']} (ID: {$callId})", 'info');
                
                // Registrar chamada ativa
                $this->registerActiveCall($callId, $callParams);
                
            } else {
                $this->logMessage("Falha ao iniciar chamada para {$contact['telefone']}", 'warning');
                $this->updateHopperStatus($contact['contato_id'], 'FAILED');
            }
            
        } catch (Exception $e) {
            $this->logMessage("Erro ao iniciar chamada: " . $e->getMessage(), 'error');
            $this->updateHopperStatus($contact['contato_id'], 'FAILED');
        }
    }
    
    /**
     * Origina chamada via Asterisk AMI
     */
    private function originateCall($params) {
        if (!$this->asteriskManager) {
            $this->setupAsteriskConnection();
        }
        
        $actionId = uniqid('call_', true);
        $channel = "Local/{$params['phone']}@{$params['campaign_id']}";
        
        $originateAction = [
            'Action' => 'Originate',
            'Channel' => $channel,
            'Context' => 'discador-context',
            'Exten' => $params['phone'],
            'Priority' => '1',
            'CallerID' => $params['contact_id'],
            'Timeout' => self::CALL_TIMEOUT * 1000,
            'ActionID' => $actionId,
            'Async' => 'true',
            'Variable' => implode(',', [
                "CONTACT_ID={$params['contact_id']}",
                "CAMPAIGN_ID={$params['campaign_id']}",
                "WORKER_ID={$params['worker_id']}",
                "ATTEMPT={$params['attempt']}"
            ])
        ];
        
        $response = $this->sendAsteriskAction($originateAction);
        
        if ($response && $response['Response'] === 'Success') {
            return $actionId;
        }
        
        return false;
    }
    
    /**
     * Registra chamada ativa
     */
    private function registerActiveCall($callId, $params) {
        $stmt = $this->db->prepare("
            INSERT INTO active_calls (
                call_id, campaign_id, contact_id, worker_id, 
                phone, status, started_at
            ) VALUES (?, ?, ?, ?, ?, 'DIALING', NOW())
        ");
        
        $stmt->execute([
            $callId,
            $params['campaign_id'],
            $params['contact_id'],
            $params['worker_id'],
            $params['phone']
        ]);
    }
    
    /**
     * Processa hopper de retentativas
     */
    private function processHopper($campaign) {
        // Buscar contatos que precisam de retry
        $stmt = $this->db->prepare("
            SELECT h.*, c.telefone
            FROM dialer_hopper h
            JOIN dialer_contato c ON h.contato_id = c.id
            WHERE h.campaign_id = ?
            AND h.status IN ('BUSY', 'NOANSWER', 'FAILED')
            AND h.attempts < ?
            AND h.next_attempt <= NOW()
            LIMIT 100
        ");
        
        $maxAttempts = $campaign['max_tentativas'] ?? 3;
        $stmt->execute([$campaign['id'], $maxAttempts]);
        $retryContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($retryContacts as $contact) {
            // Calcular próximo agendamento
            $nextAttempt = $this->calculateNextAttempt($contact['attempts'], $campaign);
            
            $stmt = $this->db->prepare("
                UPDATE dialer_hopper 
                SET status = 'WAITING', next_attempt = ?, updated_at = NOW()
                WHERE contato_id = ?
            ");
            
            $stmt->execute([$nextAttempt, $contact['contato_id']]);
        }
    }
    
    /**
     * Calcula próxima tentativa
     */
    private function calculateNextAttempt($attempts, $campaign) {
        $baseDelay = $campaign['retry_delay'] ?? 300; // 5 minutos padrão
        $multiplier = pow(2, $attempts - 1); // Backoff exponencial
        $delay = min($baseDelay * $multiplier, 3600); // Máximo 1 hora
        
        return date('Y-m-d H:i:s', time() + $delay);
    }
    
    /**
     * Verifica se campanha está ativa
     */
    private function isCampaignActive($campaignId) {
        $stmt = $this->db->prepare("
            SELECT ativo, status FROM campanhas WHERE id = ?
        ");
        
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $campaign && $campaign['ativo'] && in_array($campaign['status'], ['running', 'starting']);
    }
    
    /**
     * Obtém estatísticas da campanha
     */
    private function getCampaignStats($campaignId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN status IN ('DIALING', 'RINGING', 'CONNECTED') THEN 1 END) as active_calls,
                COUNT(CASE WHEN status = 'ANSWERED' THEN 1 END) as answered_calls,
                COUNT(CASE WHEN status IN ('BUSY', 'NOANSWER', 'FAILED') THEN 1 END) as failed_calls
            FROM active_calls 
            WHERE campaign_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $stmt->execute([$campaignId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'active_calls' => 0,
            'answered_calls' => 0,
            'failed_calls' => 0
        ];
    }
    
    /**
     * Atualiza status no hopper
     */
    private function updateHopperStatus($contactId, $status) {
        $stmt = $this->db->prepare("
            UPDATE dialer_hopper 
            SET status = ?, last_attempt = NOW(), updated_at = NOW()
            WHERE contato_id = ?
        ");
        
        $stmt->execute([$status, $contactId]);
    }
    
    /**
     * Configura conexão com Asterisk
     */
    private function setupAsteriskConnection() {
        // Implementar conexão AMI com Asterisk
        $this->asteriskManager = null; // Placeholder
    }
    
    /**
     * Envia ação para Asterisk
     */
    private function sendAsteriskAction($action) {
        // Implementar envio de ação AMI
        return ['Response' => 'Success']; // Placeholder
    }
    
    /**
     * Libera campanha atual
     */
    private function releaseCampaign() {
        if (!$this->currentCampaign) {
            return;
        }
        
        $campaign = $this->currentCampaign;
        
        // Liberar lock
        $lockKey = "campaign:{$campaign['id']}";
        $this->lock->release($lockKey, $campaign['lock_value']);
        
        // Remover registro do worker
        $stmt = $this->db->prepare("
            DELETE FROM campaign_workers 
            WHERE campaign_id = ? AND worker_id = ?
        ");
        
        $stmt->execute([$campaign['id'], $this->workerId]);
        
        $this->logMessage("Liberou campanha {$campaign['id']}", 'info');
        $this->currentCampaign = null;
    }
    
    /**
     * Envia heartbeat
     */
    private function sendHeartbeat() {
        $heartbeatKey = "worker:heartbeat:{$this->workerId}";
        $this->redis->setex($heartbeatKey, self::HEARTBEAT_INTERVAL * 2, time());
        
        // Atualizar registro no banco se houver campanha
        if ($this->currentCampaign) {
            $stmt = $this->db->prepare("
                UPDATE campaign_workers 
                SET last_heartbeat = NOW() 
                WHERE campaign_id = ? AND worker_id = ?
            ");
            
            $stmt->execute([$this->currentCampaign['id'], $this->workerId]);
        }
    }
    
    /**
     * Cleanup ao finalizar
     */
    private function cleanup() {
        $this->logMessage("Iniciando cleanup do worker", 'info');
        
        // Liberar campanha
        $this->releaseCampaign();
        
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
        $logLine = "[{$timestamp}] [CAMPAIGN-WORKER:{$this->workerId}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents('/var/log/discador/campaign-worker.log', $logLine, FILE_APPEND | LOCK_EX);
        
        if ($level === 'error') {
            error_log($logLine);
        }
    }
}
?>
