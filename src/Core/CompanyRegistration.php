<?php
namespace DiscadorV2\Core;

/**
 * Classe para gerenciar o registro de novas empresas
 */
class CompanyRegistration {
    private $db;
      public function __construct() {
        require_once __DIR__ . '/../config/pdo.php';
        $this->db = $GLOBALS['pdo'];
    }
    
    /**
     * Registra uma nova empresa
     */
    public function registerCompany(array $data): array {
        try {
            // Validação dos dados
            $validation = $this->validateCompanyData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Verificar se empresa já existe
            if ($this->companyExists($data['cnpj'], $data['email'])) {
                return [
                    'success' => false,
                    'message' => 'Empresa já cadastrada com este CNPJ ou email'
                ];
            }
            
            // Verificar se subdomínio já existe
            if (!empty($data['subdomain']) && $this->subdomainExists($data['subdomain'])) {
                return [
                    'success' => false,
                    'message' => 'Subdomínio já está em uso'
                ];
            }
            
            // Gerar subdomínio se não informado
            if (empty($data['subdomain'])) {
                $data['subdomain'] = $this->generateSubdomain($data['nome']);
            }
            
            // Iniciar transação
            $this->db->beginTransaction();
            
            try {
                // Inserir empresa
                $empresaId = $this->insertCompany($data);
                
                // Inserir usuário administrador
                $usuarioId = $this->insertAdminUser($empresaId, $data);
                
                // Criar configurações iniciais
                $this->createInitialSettings($empresaId);
                
                // Enviar email de notificação para admin geral
                $this->notifyAdminNewCompany($empresaId, $data);
                
                // Enviar email de confirmação para empresa
                $this->sendWelcomeEmail($data);
                
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => 'Empresa cadastrada com sucesso! Aguarde a aprovação do administrador.',
                    'empresa_id' => $empresaId,
                    'subdomain' => $data['subdomain']
                ];
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            error_log("Erro ao registrar empresa: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno. Tente novamente ou entre em contato com o suporte.'
            ];
        }
    }
    
    /**
     * Valida os dados da empresa
     */
    private function validateCompanyData(array $data): array {
        $required = [
            'nome' => 'Nome da empresa',
            'razao_social' => 'Razão social',
            'cnpj' => 'CNPJ',
            'email' => 'Email da empresa',
            'telefone' => 'Telefone',
            'admin_nome' => 'Nome do administrador',
            'admin_email' => 'Email do administrador',
            'admin_senha' => 'Senha',
            'admin_senha_confirm' => 'Confirmação de senha'
        ];
        
        // Verificar campos obrigatórios
        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Campo obrigatório: {$label}"
                ];
            }
        }
        
        // Validar CNPJ
        if (!$this->isValidCNPJ($data['cnpj'])) {
            return [
                'valid' => false,
                'message' => 'CNPJ inválido'
            ];
        }
        
        // Validar emails
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Email da empresa inválido'
            ];
        }
        
        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Email do administrador inválido'
            ];
        }
        
        // Validar senha
        if (strlen($data['admin_senha']) < 8) {
            return [
                'valid' => false,
                'message' => 'A senha deve ter pelo menos 8 caracteres'
            ];
        }
        
        if ($data['admin_senha'] !== $data['admin_senha_confirm']) {
            return [
                'valid' => false,
                'message' => 'As senhas não coincidem'
            ];
        }
        
        // Validar subdomínio se informado
        if (!empty($data['subdomain'])) {
            if (!preg_match('/^[a-z0-9-]+$/', $data['subdomain'])) {
                return [
                    'valid' => false,
                    'message' => 'Subdomínio deve conter apenas letras minúsculas, números e hífens'
                ];
            }
            
            if (strlen($data['subdomain']) < 3 || strlen($data['subdomain']) > 20) {
                return [
                    'valid' => false,
                    'message' => 'Subdomínio deve ter entre 3 e 20 caracteres'
                ];
            }
        }
        
        // Verificar aceite dos termos
        if (empty($data['aceita_termos'])) {
            return [
                'valid' => false,
                'message' => 'É necessário aceitar os termos de uso'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Verifica se empresa já existe
     */
    private function companyExists(string $cnpj, string $email): bool {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        $sql = "SELECT id FROM empresas WHERE cnpj = ? OR email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cnpj, $email]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Verifica se subdomínio já existe
     */
    private function subdomainExists(string $subdomain): bool {
        $sql = "SELECT id FROM empresas WHERE subdomain = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$subdomain]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Gera subdomínio automaticamente
     */
    private function generateSubdomain(string $nome): string {
        $base = strtolower($nome);
        $base = preg_replace('/[^a-z0-9\s-]/', '', $base);
        $base = preg_replace('/\s+/', '-', trim($base));
        $base = preg_replace('/-+/', '-', $base);
        $base = trim($base, '-');
        $base = substr($base, 0, 15);
        
        $subdomain = $base;
        $counter = 1;
        
        while ($this->subdomainExists($subdomain)) {
            $subdomain = $base . '-' . $counter;
            $counter++;
        }
        
        return $subdomain;
    }
    
    /**
     * Insere a empresa no banco
     */
    private function insertCompany(array $data): int {
        $sql = "
            INSERT INTO empresas (
                nome, razao_social, cnpj, email, telefone,
                subdomain, plano, status, max_usuarios, max_campanhas,
                max_chamadas_simultaneas, prefixo_ramal, contexto_asterisk
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, 'pendente_aprovacao', ?, ?,
                ?, ?, ?
            )
        ";
        
        $cnpj = preg_replace('/\D/', '', $data['cnpj']);
        $plano = $data['plano'] ?? 'basico';
        
        // Configurações por plano
        $planoConfig = [
            'basico' => ['max_usuarios' => 10, 'max_campanhas' => 5, 'max_chamadas' => 50],
            'profissional' => ['max_usuarios' => 50, 'max_campanhas' => 20, 'max_chamadas' => 200],
            'empresarial' => ['max_usuarios' => 999, 'max_campanhas' => 100, 'max_chamadas' => 500]
        ];
        
        $config = $planoConfig[$plano];
        
        // Gerar prefixo de ramal único
        $prefixoRamal = $this->generateRamalPrefix();
        $contextoAsterisk = 'empresa_' . $data['subdomain'];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['nome'],
            $data['razao_social'],
            $cnpj,
            $data['email'],
            $data['telefone'],
            $data['subdomain'],
            $plano,
            $config['max_usuarios'],
            $config['max_campanhas'],
            $config['max_chamadas'],
            $prefixoRamal,
            $contextoAsterisk
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Insere o usuário administrador
     */
    private function insertAdminUser(int $empresaId, array $data): int {
        $sql = "
            INSERT INTO usuarios (
                empresa_id, nome, email, senha, nivel, ativo
            ) VALUES (
                ?, ?, ?, ?, 'master', 1
            )
        ";
        
        $senhaHash = password_hash($data['admin_senha'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $empresaId,
            $data['admin_nome'],
            $data['admin_email'],
            $senhaHash
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Cria configurações iniciais da empresa
     */
    private function createInitialSettings(int $empresaId): void {
        // Aqui podem ser criadas configurações padrão, filas, etc.
        // Por exemplo, uma fila padrão
        
        $sql = "
            INSERT INTO filas (
                empresa_id, nome, numero, strategy, timeout, 
                maxlen, announce_frequency, periodic_announce
            ) VALUES (
                ?, 'Atendimento Geral', '1000', 'rrmemory', 30,
                0, 0, ''
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$empresaId]);
    }
    
    /**
     * Gera prefixo único para ramais da empresa
     */
    private function generateRamalPrefix(): string {
        $sql = "SELECT MAX(CAST(prefixo_ramal AS UNSIGNED)) as max_prefix FROM empresas";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();
        
        $maxPrefix = $result['max_prefix'] ?? 1000;
        return (string)($maxPrefix + 1000);
    }
    
    /**
     * Notifica admin geral sobre nova empresa
     */
    private function notifyAdminNewCompany(int $empresaId, array $data): void {
        // Implementar notificação por email para admin geral
        // Por enquanto, apenas log
        error_log("Nova empresa cadastrada: {$data['nome']} (ID: {$empresaId})");
    }
    
    /**
     * Envia email de boas-vindas
     */
    private function sendWelcomeEmail(array $data): void {
        // Implementar envio de email de boas-vindas
        // Por enquanto, apenas log
        error_log("Email de boas-vindas enviado para: {$data['admin_email']}");
    }
    
    /**
     * Valida CNPJ (algoritmo básico)
     */
    private function isValidCNPJ(string $cnpj): bool {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        // Verificar se não são todos iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Validação dos dígitos verificadores
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        $sum1 = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum1 += $cnpj[$i] * $weights1[$i];
        }
        $digit1 = $sum1 % 11 < 2 ? 0 : 11 - ($sum1 % 11);
        
        $sum2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum2 += $cnpj[$i] * $weights2[$i];
        }
        $digit2 = $sum2 % 11 < 2 ? 0 : 11 - ($sum2 % 11);
        
        return $cnpj[12] == $digit1 && $cnpj[13] == $digit2;
    }
    
    /**
     * Lista empresas pendentes de aprovação
     */
    public function getPendingCompanies(): array {
        $sql = "
            SELECT e.*, u.nome as admin_nome, u.email as admin_email
            FROM empresas e
            INNER JOIN usuarios u ON e.id = u.empresa_id AND u.nivel = 'master'
            WHERE e.status = 'pendente_aprovacao'
            ORDER BY e.criado_em DESC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Aprova uma empresa
     */
    public function approveCompany(int $empresaId, int $adminId): array {
        try {
            $this->db->beginTransaction();
            
            // Atualizar status da empresa
            $sql = "
                UPDATE empresas 
                SET status = 'ativa', aprovado_por = ?, aprovado_em = NOW()
                WHERE id = ? AND status = 'pendente_aprovacao'
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $empresaId]);
            
            if ($stmt->rowCount() === 0) {
                throw new \Exception('Empresa não encontrada ou já processada');
            }
            
            // Buscar dados da empresa
            $sql = "
                SELECT e.*, u.email as admin_email, u.nome as admin_nome
                FROM empresas e
                INNER JOIN usuarios u ON e.id = u.empresa_id AND u.nivel = 'master'
                WHERE e.id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$empresaId]);
            $empresa = $stmt->fetch();
            
            // Enviar email de aprovação
            $this->sendApprovalEmail($empresa);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Empresa aprovada com sucesso!'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao aprovar empresa: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erro ao aprovar empresa: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Rejeita uma empresa
     */
    public function rejectCompany(int $empresaId, string $motivo): array {
        try {
            $this->db->beginTransaction();
            
            // Buscar dados da empresa antes de rejeitar
            $sql = "
                SELECT e.*, u.email as admin_email, u.nome as admin_nome
                FROM empresas e
                INNER JOIN usuarios u ON e.id = u.empresa_id AND u.nivel = 'master'
                WHERE e.id = ? AND e.status = 'pendente_aprovacao'
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$empresaId]);
            $empresa = $stmt->fetch();
            
            if (!$empresa) {
                throw new \Exception('Empresa não encontrada ou já processada');
            }
            
            // Deletar empresa e usuários relacionados
            $this->db->prepare("DELETE FROM usuarios WHERE empresa_id = ?")->execute([$empresaId]);
            $this->db->prepare("DELETE FROM empresas WHERE id = ?")->execute([$empresaId]);
            
            // Enviar email de rejeição
            $this->sendRejectionEmail($empresa, $motivo);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Empresa rejeitada com sucesso!'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao rejeitar empresa: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erro ao rejeitar empresa: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia email de aprovação
     */
    private function sendApprovalEmail(array $empresa): void {
        // Implementar envio de email
        error_log("Email de aprovação enviado para: {$empresa['admin_email']}");
    }
    
    /**
     * Envia email de rejeição
     */
    private function sendRejectionEmail(array $empresa, string $motivo): void {
        // Implementar envio de email
        error_log("Email de rejeição enviado para: {$empresa['admin_email']} - Motivo: {$motivo}");
    }
}
