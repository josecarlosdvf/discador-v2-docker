<?php

namespace DiscadorV2\Core;

require_once __DIR__ . '/../config/pdo.php';

use PDO;
use Exception;

class CampaignManager {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Iniciar campanha
     */
    public function startCampaign($campaignId) {
        try {
            // Verificar se campanha existe e está parada
            $stmt = $this->pdo->prepare("
                SELECT id, nome, empresa_id, status 
                FROM campanhas 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campanha não encontrada'];
            }
            
            if ($campaign['status'] !== 'parada') {
                return ['success' => false, 'message' => 'Campanha não está parada'];
            }
            
            // Verificar se há contatos disponíveis
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total 
                FROM contatos_campanha 
                WHERE campanha_id = ? AND status = 'pendente'
            ");
            $stmt->execute([$campaignId]);
            $totalContatos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($totalContatos == 0) {
                return ['success' => false, 'message' => 'Não há contatos pendentes para esta campanha'];
            }
            
            // Atualizar status da campanha
            $stmt = $this->pdo->prepare("
                UPDATE campanhas 
                SET status = 'ativa', iniciado_em = NOW(), atualizado_em = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            
            // Registrar atividade
            $this->logActivity($campaign['empresa_id'], $campaignId, 'Campanha iniciada', 'start');
            
            return ['success' => true, 'message' => 'Campanha iniciada com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao iniciar campanha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Parar campanha
     */
    public function stopCampaign($campaignId) {
        try {
            // Verificar se campanha existe
            $stmt = $this->pdo->prepare("
                SELECT id, nome, empresa_id, status 
                FROM campanhas 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campanha não encontrada'];
            }
            
            if ($campaign['status'] === 'parada') {
                return ['success' => false, 'message' => 'Campanha já está parada'];
            }
            
            // Atualizar status da campanha
            $stmt = $this->pdo->prepare("
                UPDATE campanhas 
                SET status = 'parada', parado_em = NOW(), atualizado_em = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            
            // Registrar atividade
            $this->logActivity($campaign['empresa_id'], $campaignId, 'Campanha parada', 'stop');
            
            return ['success' => true, 'message' => 'Campanha parada com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao parar campanha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Pausar campanha
     */
    public function pauseCampaign($campaignId) {
        try {
            // Verificar se campanha existe e está ativa
            $stmt = $this->pdo->prepare("
                SELECT id, nome, empresa_id, status 
                FROM campanhas 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campanha não encontrada'];
            }
            
            if ($campaign['status'] !== 'ativa') {
                return ['success' => false, 'message' => 'Campanha não está ativa'];
            }
            
            // Atualizar status da campanha
            $stmt = $this->pdo->prepare("
                UPDATE campanhas 
                SET status = 'pausada', pausado_em = NOW(), atualizado_em = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            
            // Registrar atividade
            $this->logActivity($campaign['empresa_id'], $campaignId, 'Campanha pausada', 'pause');
            
            return ['success' => true, 'message' => 'Campanha pausada com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao pausar campanha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Retomar campanha pausada
     */
    public function resumeCampaign($campaignId) {
        try {
            // Verificar se campanha existe e está pausada
            $stmt = $this->pdo->prepare("
                SELECT id, nome, empresa_id, status 
                FROM campanhas 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campanha não encontrada'];
            }
            
            if ($campaign['status'] !== 'pausada') {
                return ['success' => false, 'message' => 'Campanha não está pausada'];
            }
            
            // Atualizar status da campanha
            $stmt = $this->pdo->prepare("
                UPDATE campanhas 
                SET status = 'ativa', retomado_em = NOW(), atualizado_em = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$campaignId]);
            
            // Registrar atividade
            $this->logActivity($campaign['empresa_id'], $campaignId, 'Campanha retomada', 'resume');
            
            return ['success' => true, 'message' => 'Campanha retomada com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao retomar campanha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar campanhas por empresa
     */
    public function getCampaignsByCompany($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id, c.nome, c.descricao, c.status, c.criado_em,
                    c.iniciado_em, c.pausado_em, c.parado_em,
                    (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id) as total_contatos,
                    (SELECT COUNT(*) FROM cdr cd WHERE cd.campanha_id = c.id AND DATE(cd.calldate) = CURDATE()) as ligacoes_hoje,
                    CASE 
                        WHEN (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id) > 0 
                        THEN ROUND(
                            (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id AND cc.status IN ('contatado', 'finalizado')) * 100.0 / 
                            (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id), 2
                        )
                        ELSE 0 
                    END as progresso
                FROM campanhas c
                WHERE c.empresa_id = ?
                ORDER BY 
                    CASE c.status 
                        WHEN 'ativa' THEN 1 
                        WHEN 'pausada' THEN 2 
                        WHEN 'parada' THEN 3 
                    END,
                    c.nome
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Buscar estatísticas das campanhas
     */
    public function getCampaignStats($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_campanhas,
                    SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as campanhas_ativas,
                    (SELECT COUNT(*) FROM cdr cd 
                     JOIN campanhas c ON cd.campanha_id = c.id 
                     WHERE c.empresa_id = ? AND DATE(cd.calldate) = CURDATE()) as ligacoes_hoje,
                    (SELECT COUNT(*) FROM contatos_campanha cc 
                     JOIN campanhas c ON cc.campanha_id = c.id 
                     WHERE c.empresa_id = ? AND cc.status = 'pendente') as contatos_ativos
                FROM campanhas 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresaId, $empresaId, $empresaId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [
                'total_campanhas' => 0,
                'campanhas_ativas' => 0,
                'ligacoes_hoje' => 0,
                'contatos_ativos' => 0
            ];
        }
    }
    
    /**
     * Buscar atividade recente
     */
    public function getRecentActivity($empresaId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.descricao, a.tipo, a.created_at,
                    c.nome as campanha_nome
                FROM atividades a
                JOIN campanhas c ON a.campanha_id = c.id
                WHERE a.empresa_id = ?
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$empresaId, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Criar nova campanha
     */
    public function createCampaign($empresaId, $data) {
        try {
            // Validar dados obrigatórios
            $requiredFields = ['nome', 'descricao'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
                }
            }
            
            // Verificar se nome já existe na empresa
            $stmt = $this->pdo->prepare("SELECT id FROM campanhas WHERE nome = ? AND empresa_id = ?");
            $stmt->execute([$data['nome'], $empresaId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Já existe uma campanha com este nome'];
            }
            
            // Inserir campanha
            $stmt = $this->pdo->prepare("
                INSERT INTO campanhas (
                    empresa_id, nome, descricao, status, criado_em, atualizado_em
                ) VALUES (?, ?, ?, 'parada', NOW(), NOW())
            ");
            
            $stmt->execute([
                $empresaId,
                $data['nome'],
                $data['descricao']
            ]);
            
            $campaignId = $this->pdo->lastInsertId();
            
            // Registrar atividade
            $this->logActivity($empresaId, $campaignId, 'Campanha criada', 'create');
            
            return ['success' => true, 'message' => 'Campanha criada com sucesso', 'campaign_id' => $campaignId];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao criar campanha: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar campanha por ID
     */
    public function getCampaignById($campaignId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.*,
                    e.nome as empresa_nome,
                    (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id) as total_contatos,
                    (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id AND cc.status = 'pendente') as contatos_pendentes,
                    (SELECT COUNT(*) FROM contatos_campanha cc WHERE cc.campanha_id = c.id AND cc.status = 'contatado') as contatos_contatados
                FROM campanhas c
                JOIN empresas e ON c.empresa_id = e.id
                WHERE c.id = ?
            ");
            $stmt->execute([$campaignId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Registrar atividade
     */
    private function logActivity($empresaId, $campanhaId, $descricao, $tipo) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO atividades (empresa_id, campanha_id, descricao, tipo, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$empresaId, $campanhaId, $descricao, $tipo]);
            
        } catch (Exception $e) {
            // Log silencioso - não quebrar fluxo principal
        }
    }
    
    /**
     * Buscar estatísticas em tempo real
     */
    public function getRealTimeStats($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM cdr cd 
                     JOIN campanhas c ON cd.campanha_id = c.id 
                     WHERE c.empresa_id = ? AND cd.disposition = 'ANSWERED' AND cd.duration > 0) as ligacoes_ativas,
                    (SELECT COUNT(DISTINCT cd.src) FROM cdr cd 
                     JOIN campanhas c ON cd.campanha_id = c.id 
                     WHERE c.empresa_id = ? AND DATE(cd.calldate) = CURDATE()) as operadores_online,
                    COALESCE(
                        (SELECT ROUND(AVG(cd.duration), 0) FROM cdr cd 
                         JOIN campanhas c ON cd.campanha_id = c.id 
                         WHERE c.empresa_id = ? AND DATE(cd.calldate) = CURDATE() AND cd.duration > 0), 0
                    ) as tempo_medio,
                    COALESCE(
                        (SELECT ROUND(
                            (COUNT(CASE WHEN cd.disposition = 'ANSWERED' THEN 1 END) * 100.0 / COUNT(*)), 2
                        ) FROM cdr cd 
                         JOIN campanhas c ON cd.campanha_id = c.id 
                         WHERE c.empresa_id = ? AND DATE(cd.calldate) = CURDATE()), 0
                    ) as taxa_sucesso
            ");
            $stmt->execute([$empresaId, $empresaId, $empresaId, $empresaId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [
                'ligacoes_ativas' => 0,
                'operadores_online' => 0,
                'tempo_medio' => 0,
                'taxa_sucesso' => 0
            ];
        }
    }
}
