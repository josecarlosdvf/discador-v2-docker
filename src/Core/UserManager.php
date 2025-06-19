<?php

namespace DiscadorV2\Core;

require_once __DIR__ . '/../config/pdo.php';

use PDO;
use Exception;

class UserManager {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Criar novo usuário para a empresa
     */
    public function createUser($empresaId, $data) {
        try {
            // Validar dados obrigatórios
            $requiredFields = ['nome', 'email', 'senha', 'tipo'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
                }
            }
            
            // Verificar se email já existe
            $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email já está em uso'];
            }
            
            // Validar tipo de usuário
            $tiposValidos = ['master', 'supervisor', 'operador'];
            if (!in_array($data['tipo'], $tiposValidos)) {
                return ['success' => false, 'message' => 'Tipo de usuário inválido'];
            }
            
            // Inserir usuário
            $stmt = $this->pdo->prepare("
                INSERT INTO usuarios (
                    empresa_id, nome, email, senha, tipo, telefone, 
                    ativo, criado_em, campanhas_permitidas
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)
            ");
            
            $campanhasPermitidas = isset($data['campanhas']) ? json_encode($data['campanhas']) : '[]';
            
            $stmt->execute([
                $empresaId,
                $data['nome'],
                $data['email'],
                password_hash($data['senha'], PASSWORD_DEFAULT),
                $data['tipo'],
                $data['telefone'] ?? null,
                $campanhasPermitidas
            ]);
            
            return ['success' => true, 'message' => 'Usuário criado com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()];
        }
    }
    
    /**
     * Atualizar usuário existente
     */
    public function updateUser($userId, $data) {
        try {
            // Verificar se usuário existe
            $stmt = $this->pdo->prepare("SELECT id, empresa_id FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            // Validar tipo de usuário
            if (isset($data['tipo'])) {
                $tiposValidos = ['master', 'supervisor', 'operador'];
                if (!in_array($data['tipo'], $tiposValidos)) {
                    return ['success' => false, 'message' => 'Tipo de usuário inválido'];
                }
            }
            
            // Verificar se email já existe (exceto para o próprio usuário)
            if (isset($data['email'])) {
                $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Email já está em uso'];
                }
            }
            
            // Montar query de update
            $updateFields = [];
            $updateValues = [];
            
            $allowedFields = ['nome', 'email', 'tipo', 'telefone'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && !empty($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $data[$field];
                }
            }
            
            // Atualizar senha se fornecida
            if (isset($data['senha']) && !empty($data['senha'])) {
                $updateFields[] = "senha = ?";
                $updateValues[] = password_hash($data['senha'], PASSWORD_DEFAULT);
            }
            
            // Atualizar campanhas permitidas
            if (isset($data['campanhas'])) {
                $updateFields[] = "campanhas_permitidas = ?";
                $updateValues[] = json_encode($data['campanhas']);
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'Nenhum campo para atualizar'];
            }
            
            $updateFields[] = "atualizado_em = NOW()";
            $updateValues[] = $userId;
            
            $sql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($updateValues);
            
            return ['success' => true, 'message' => 'Usuário atualizado com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()];
        }
    }
    
    /**
     * Excluir usuário
     */
    public function deleteUser($userId) {
        try {
            // Verificar se usuário existe
            $stmt = $this->pdo->prepare("SELECT id, email FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            // Não permitir excluir se for o último usuário master da empresa
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_masters 
                FROM usuarios 
                WHERE empresa_id = (SELECT empresa_id FROM usuarios WHERE id = ?) 
                AND tipo = 'master' 
                AND ativo = 1
            ");
            $stmt->execute([$userId]);
            $totalMasters = $stmt->fetch(PDO::FETCH_ASSOC)['total_masters'];
            
            $stmt = $this->pdo->prepare("SELECT tipo FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $userType = $stmt->fetch(PDO::FETCH_ASSOC)['tipo'];
            
            if ($userType === 'master' && $totalMasters <= 1) {
                return ['success' => false, 'message' => 'Não é possível excluir o último usuário master da empresa'];
            }
            
            // Excluir usuário
            $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            
            return ['success' => true, 'message' => 'Usuário excluído com sucesso'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao excluir usuário: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ativar/desativar usuário
     */
    public function toggleUserStatus($userId) {
        try {
            // Verificar se usuário existe
            $stmt = $this->pdo->prepare("SELECT id, ativo, tipo, empresa_id FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuário não encontrado'];
            }
            
            // Não permitir desativar se for o último usuário master ativo da empresa
            if ($user['ativo'] == 1 && $user['tipo'] === 'master') {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as total_masters 
                    FROM usuarios 
                    WHERE empresa_id = ? 
                    AND tipo = 'master' 
                    AND ativo = 1
                ");
                $stmt->execute([$user['empresa_id']]);
                $totalMasters = $stmt->fetch(PDO::FETCH_ASSOC)['total_masters'];
                
                if ($totalMasters <= 1) {
                    return ['success' => false, 'message' => 'Não é possível desativar o último usuário master ativo da empresa'];
                }
            }
            
            $novoStatus = $user['ativo'] == 1 ? 0 : 1;
            $stmt = $this->pdo->prepare("UPDATE usuarios SET ativo = ?, atualizado_em = NOW() WHERE id = ?");
            $stmt->execute([$novoStatus, $userId]);
            
            $status = $novoStatus ? 'ativado' : 'desativado';
            return ['success' => true, 'message' => "Usuário {$status} com sucesso"];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao alterar status: ' . $e->getMessage()];
        }
    }
    
    /**
     * Buscar usuários por empresa
     */
    public function getUsersByCompany($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id, u.nome, u.email, u.tipo, u.telefone, u.ativo, 
                    u.criado_em, u.atualizado_em, u.campanhas_permitidas,
                    e.nome as empresa_nome
                FROM usuarios u
                JOIN empresas e ON u.empresa_id = e.id
                WHERE u.empresa_id = ?
                ORDER BY u.tipo, u.nome
            ");
            $stmt->execute([$empresaId]);
            
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar campanhas permitidas
            foreach ($usuarios as &$usuario) {
                $usuario['campanhas_permitidas'] = json_decode($usuario['campanhas_permitidas'] ?? '[]', true);
            }
            
            return $usuarios;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Buscar campanhas por empresa
     */
    public function getCampaignsByCompany($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nome, descricao, ativo
                FROM campanhas 
                WHERE empresa_id = ?
                ORDER BY nome
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Buscar usuário por ID
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.*, e.nome as empresa_nome
                FROM usuarios u
                JOIN empresas e ON u.empresa_id = e.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $user['campanhas_permitidas'] = json_decode($user['campanhas_permitidas'] ?? '[]', true);
            }
            
            return $user;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Verificar se usuário tem acesso a uma campanha
     */
    public function userHasAccessToCampaign($userId, $campanhaId) {
        try {
            $user = $this->getUserById($userId);
            
            if (!$user) {
                return false;
            }
            
            // Usuários master têm acesso a todas as campanhas da empresa
            if ($user['tipo'] === 'master') {
                return true;
            }
            
            // Verificar se campanha está na lista de permitidas
            $campanhasPermitidas = $user['campanhas_permitidas'] ?? [];
            return in_array($campanhaId, $campanhasPermitidas);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Buscar estatísticas de usuários por empresa
     */
    public function getUserStats($empresaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                    SUM(CASE WHEN tipo = 'master' THEN 1 ELSE 0 END) as masters,
                    SUM(CASE WHEN tipo = 'supervisor' THEN 1 ELSE 0 END) as supervisores,
                    SUM(CASE WHEN tipo = 'operador' THEN 1 ELSE 0 END) as operadores
                FROM usuarios 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresaId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [
                'total' => 0,
                'ativos' => 0,
                'masters' => 0,
                'supervisores' => 0,
                'operadores' => 0
            ];
        }
    }
}
