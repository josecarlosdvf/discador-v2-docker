<?php
namespace DiscadorV2\Core;

require_once __DIR__ . '/TenantManager.php';

/**
 * Sistema de Autenticação Multi-Tenant
 * Gerencia login, sessões e permissões por empresa
 */
class MultiTenantAuth {
    private $tenantManager;
    private $db;    public function __construct() {
        $this->tenantManager = TenantManager::getInstance();
        require_once __DIR__ . '/../config/pdo.php';
        $this->db = $GLOBALS['pdo'];
    }
    
    /**
     * Realiza login do usuário
     */
    public function login(string $email, string $password, ?int $tenantId = null): array {
        // Se tenant não especificado, tenta detectar
        if (!$tenantId) {
            $tenant = $this->tenantManager->detectTenant();
            $tenantId = $tenant['id'] ?? null;
        }
        
        // Busca usuário
        $sql = "
            SELECT u.*, e.nome as empresa_nome, e.status as empresa_status
            FROM usuarios u
            INNER JOIN empresas e ON u.empresa_id = e.id
            WHERE u.email = ? AND u.ativo = 1
        ";
        
        $params = [$email];
        
        // Se tenant especificado, filtra por ele
        if ($tenantId) {
            $sql .= " AND u.empresa_id = ?";
            $params[] = $tenantId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new \Exception("Usuário não encontrado ou inativo");
        }
        
        // Verifica senha
        if (!password_verify($password, $user['senha'])) {
            throw new \Exception("Senha incorreta");
        }
        
        // Verifica se empresa está ativa
        if ($user['empresa_status'] !== 'ativa') {
            throw new \Exception("Empresa inativa ou suspensa");
        }
        
        // Carrega dados da empresa
        $tenant = $this->tenantManager->loadTenant($user['empresa_id']);
        if (!$tenant) {
            throw new \Exception("Empresa não encontrada");
        }
        
        // Inicia sessão
        $this->startSession($user, $tenant);
        
        // Atualiza último login
        $this->updateLastLogin($user['id']);
        
        return [
            'success' => true,
            'user' => $this->sanitizeUser($user),
            'tenant' => $tenant,
            'permissions' => $this->getUserPermissions($user)
        ];
    }
    
    /**
     * Verifica se usuário está autenticado
     */
    public function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['tenant_id']);
    }
    
    /**
     * Alias para isAuthenticated
     */
    public function isLoggedIn(): bool {
        return $this->isAuthenticated();
    }
    
    /**
     * Verifica se é administrador geral (global admin)
     */
    public function isGlobalAdmin(): bool {
        return isset($_SESSION['is_global_admin']) && $_SESSION['is_global_admin'] === true;
    }
    
    /**
     * Login do administrador geral (multi-tenant)
     */
    public function loginGlobalAdmin(string $email, string $password): array {
        try {
            // Busca admin geral na tabela especial
            $sql = "
                SELECT id, nome, email, senha, ativo
                FROM admin_global
                WHERE email = ? AND ativo = 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                throw new \Exception("Administrador não encontrado ou inativo");
            }
            
            // Verifica senha
            if (!password_verify($password, $admin['senha'])) {
                throw new \Exception("Senha incorreta");
            }
            
            // Inicia sessão de admin global
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['nome'];
            $_SESSION['user_email'] = $admin['email'];
            $_SESSION['is_global_admin'] = true;
            $_SESSION['tenant_id'] = null; // Admin global não tem tenant específico
            
            // Atualiza último login
            $this->updateGlobalAdminLastLogin($admin['id']);
            
            return [
                'success' => true,
                'user' => $this->sanitizeUser($admin),
                'is_global_admin' => true
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica se usuário tem permissão específica
     */
    public function hasPermission(string $permission): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $permissions = $this->getUserPermissions($user);
        return in_array($permission, $permissions);
    }
    
    /**
     * Verifica se usuário pode acessar recurso de empresa específica
     */
    public function canAccessTenant(int $tenantId): bool {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Admin geral pode acessar qualquer tenant
        if ($user['perfil'] === 'admin_geral') {
            return true;
        }
        
        // Usuários normais só podem acessar sua própria empresa
        return $user['empresa_id'] == $tenantId;
    }
    
    /**
     * Middleware para proteger rotas
     */
    public function requireAuth(): void {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }
    }
    
    /**
     * Middleware para proteger rotas com permissão específica
     */
    public function requirePermission(string $permission): void {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            http_response_code(403);
            $this->renderError("Acesso negado. Permissão necessária: {$permission}");
            exit;
        }
    }
    
    /**
     * Middleware para admin geral
     */
    public function requireGlobalAdmin(): void {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        if (!$user || $user['perfil'] !== 'admin_geral') {
            http_response_code(403);
            $this->renderError("Acesso restrito a administradores gerais");
            exit;
        }
    }
    
    /**
     * Logout
     */
    public function logout(): void {
        session_destroy();
        $this->tenantManager->clearTenantCache();
    }
    
    /**
     * Inicia sessão do usuário
     */
    private function startSession(array $user, array $tenant): void {
        session_start();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_data'] = $this->sanitizeUser($user);
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['tenant_data'] = $tenant;
        $_SESSION['login_time'] = time();
        
        // Define tenant no manager
        $this->tenantManager->setCurrentTenant($tenant);
    }
    
    /**
     * Remove dados sensíveis do usuário
     */
    private function sanitizeUser(array $user): array {
        unset($user['senha'], $user['token_acesso']);
        return $user;
    }
    
    /**
     * Retorna permissões do usuário baseado no perfil
     */
    private function getUserPermissions(array $user): array {
        $permissions = [];
        
        switch ($user['perfil']) {
            case 'admin_geral':
                $permissions = [
                    'admin_global',
                    'gerenciar_empresas',
                    'gerenciar_usuarios_global',
                    'ver_financeiro_global',
                    'criar_campanhas',
                    'gerenciar_usuarios',
                    'ver_relatorios',
                    'ver_financeiro'
                ];
                break;
                
            case 'master_empresa':
                $permissions = [
                    'gerenciar_empresa',
                    'criar_campanhas',
                    'gerenciar_usuarios',
                    'ver_relatorios',
                    'ver_financeiro',
                    'configurar_sistema'
                ];
                break;
                
            case 'supervisor':
                $permissions = [
                    'criar_campanhas',
                    'gerenciar_usuarios_limitado',
                    'ver_relatorios',
                    'pausar_campanhas'
                ];
                break;
                
            case 'operador':
                $permissions = [
                    'ver_relatorios',
                    'atender_chamadas'
                ];
                break;
        }
        
        // Adiciona permissões específicas do banco
        if ($user['pode_criar_campanhas']) {
            $permissions[] = 'criar_campanhas';
        }
        if ($user['pode_gerenciar_usuarios']) {
            $permissions[] = 'gerenciar_usuarios';
        }
        if ($user['pode_ver_relatorios']) {
            $permissions[] = 'ver_relatorios';
        }
        if ($user['pode_ver_financeiro']) {
            $permissions[] = 'ver_financeiro';
        }
        
        return array_unique($permissions);
    }
    
    /**
     * Atualiza último login do usuário
     */
    private function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET ultimo_login = NOW(), ultimo_ip = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $userId
        ]);
    }
    
    /**
     * Redireciona para página de login
     */
    private function redirectToLogin(): void {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $loginUrl = '/login.php?redirect=' . urlencode($currentUrl);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Request AJAX
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Não autenticado',
                'redirect' => $loginUrl
            ]);
        } else {
            // Request normal
            header("Location: {$loginUrl}");
        }
        exit;
    }
    
    /**
     * Renderiza erro
     */
    private function renderError(string $message): void {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Request AJAX
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            // Request normal
            echo "<div style='padding: 20px; color: red; font-family: Arial;'>";
            echo "<h3>Erro de Acesso</h3>";
            echo "<p>{$message}</p>";
            echo "<a href='/'>Voltar ao início</a>";
            echo "</div>";
        }
    }
    
    /**
     * Cria novo usuário
     */
    public function createUser(array $userData): int {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        
        // Valida dados obrigatórios
        $required = ['nome', 'email', 'senha', 'perfil'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new \Exception("Campo obrigatório: {$field}");
            }
        }
        
        // Verifica se email já existe na empresa
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM usuarios 
            WHERE email = ? AND empresa_id = ?
        ");
        $stmt->execute([$userData['email'], $tenantId]);
        if ($stmt->fetch()['count'] > 0) {
            throw new \Exception("Email já está em uso nesta empresa");
        }
        
        // Hash da senha
        $userData['senha'] = password_hash($userData['senha'], PASSWORD_DEFAULT);
        
        // Insere usuário
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (
                empresa_id, nome, email, senha, telefone, perfil,
                pode_criar_campanhas, pode_gerenciar_usuarios, 
                pode_ver_relatorios, pode_ver_financeiro,
                horario_inicio, horario_fim, dias_trabalho,
                criado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId,
            $userData['nome'],
            $userData['email'],
            $userData['senha'],
            $userData['telefone'] ?? null,
            $userData['perfil'],
            $userData['pode_criar_campanhas'] ?? false,
            $userData['pode_gerenciar_usuarios'] ?? false,
            $userData['pode_ver_relatorios'] ?? true,
            $userData['pode_ver_financeiro'] ?? false,
            $userData['horario_inicio'] ?? '08:00:00',
            $userData['horario_fim'] ?? '18:00:00',
            $userData['dias_trabalho'] ?? 'seg,ter,qua,qui,sex',
            $_SESSION['user_id'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Valida token de API (para futuro)
     */
    public function validateApiToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, e.nome as empresa_nome
            FROM usuarios u
            INNER JOIN empresas e ON u.empresa_id = e.id
            WHERE u.token_acesso = ? 
            AND u.token_expira > NOW() 
            AND u.ativo = 1 
            AND e.status = 'ativa'
        ");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Obtém usuário atual da sessão
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        if ($this->isGlobalAdmin()) {
            // Admin global
            $sql = "SELECT id, nome, email FROM admin_global WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $user['is_global_admin'] = true;
                $user['nivel'] = 'global_admin';
            }
            
            return $user;
        } else {
            // Usuário normal
            $sql = "
                SELECT u.*, e.nome as empresa_nome
                FROM usuarios u
                INNER JOIN empresas e ON u.empresa_id = e.id
                WHERE u.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        }
    }
    
    /**
     * Atualiza último login do admin global
     */
    private function updateGlobalAdminLastLogin(int $adminId): void {
        $sql = "UPDATE admin_global SET ultimo_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$adminId]);
    }
}
