<?php
namespace DiscadorV2\Core;

/**
 * Gerenciador Multi-Tenant
 * Responsável por detectar e gerenciar o contexto da empresa atual
 */
class TenantManager {
    private static $instance = null;
    private $currentTenant = null;
    private $db;
      private function __construct() {
        require_once __DIR__ . '/../config/pdo.php';
        $this->db = $GLOBALS['pdo'];
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detecta a empresa atual baseado na URL ou sessão
     */
    public function detectTenant(): ?array {
        // Prioridade 1: Tenant já definido na sessão
        if (isset($_SESSION['tenant_id'])) {
            return $this->loadTenant($_SESSION['tenant_id']);
        }
        
        // Prioridade 2: Subdomain (empresa1.discador.com)
        $subdomain = $this->extractSubdomain();
        if ($subdomain && $subdomain !== 'www') {
            $tenant = $this->findTenantBySubdomain($subdomain);
            if ($tenant) {
                $_SESSION['tenant_id'] = $tenant['id'];
                return $tenant;
            }
        }
        
        // Prioridade 3: Path prefix (/empresa1)
        $pathPrefix = $this->extractPathPrefix();
        if ($pathPrefix) {
            $tenant = $this->findTenantByPath($pathPrefix);
            if ($tenant) {
                $_SESSION['tenant_id'] = $tenant['id'];
                return $tenant;
            }
        }
        
        // Prioridade 4: Parâmetro GET tenant
        if (isset($_GET['tenant'])) {
            $tenant = $this->findTenantBySubdomain($_GET['tenant']);
            if ($tenant) {
                $_SESSION['tenant_id'] = $tenant['id'];
                return $tenant;
            }
        }
        
        // Default: Empresa principal (para compatibilidade)
        return $this->getDefaultTenant();
    }
    
    /**
     * Define o tenant atual
     */
    public function setCurrentTenant(array $tenant): void {
        $this->currentTenant = $tenant;
        $_SESSION['tenant_id'] = $tenant['id'];
        $_SESSION['tenant_data'] = $tenant;
    }
    
    /**
     * Retorna o tenant atual
     */
    public function getCurrentTenant(): ?array {
        if ($this->currentTenant === null) {
            $this->currentTenant = $this->detectTenant();
        }
        return $this->currentTenant;
    }
    
    /**
     * Retorna o ID da empresa atual
     */
    public function getCurrentTenantId(): ?int {
        $tenant = $this->getCurrentTenant();
        return $tenant ? $tenant['id'] : null;
    }
    
    /**
     * Verifica se o usuário tem acesso ao tenant
     */
    public function userHasAccessToTenant(int $userId, int $tenantId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM usuarios 
            WHERE id = ? AND empresa_id = ? AND ativo = 1
        ");
        $stmt->execute([$userId, $tenantId]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Lista todas as empresas que um usuário tem acesso
     */
    public function getUserTenants(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, u.perfil 
            FROM empresas e
            INNER JOIN usuarios u ON e.id = u.empresa_id
            WHERE u.id = ? AND u.ativo = 1 AND e.status = 'ativa'
            ORDER BY e.nome
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Cria WHERE clause automático para queries multi-tenant
     */
    public function getTenantWhere(string $tableAlias = ''): string {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            throw new \Exception("Tenant não definido");
        }
        
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        return "{$prefix}empresa_id = {$tenantId}";
    }
    
    /**
     * Adiciona condição de tenant automaticamente em queries
     */
    public function addTenantCondition(string $sql, array $params = [], string $tableAlias = ''): array {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            throw new \Exception("Tenant não definido");
        }
        
        $prefix = $tableAlias ? $tableAlias . '.' : '';
        
        // Verifica se já tem WHERE
        if (stripos($sql, 'WHERE') !== false) {
            $sql .= " AND {$prefix}empresa_id = ?";
        } else {
            $sql .= " WHERE {$prefix}empresa_id = ?";
        }
        
        $params[] = $tenantId;
        
        return [$sql, $params];
    }
    
    /**
     * Busca empresa por subdomain
     */
    private function findTenantBySubdomain(string $subdomain): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM empresas 
            WHERE subdomain = ? AND status = 'ativa'
        ");
        $stmt->execute([$subdomain]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Busca empresa por path prefix
     */
    private function findTenantByPath(string $path): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM empresas 
            WHERE path_prefix = ? AND status = 'ativa'
        ");
        $stmt->execute([$path]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Carrega tenant por ID
     */
    public function loadTenant(int $tenantId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM empresas 
            WHERE id = ? AND status = 'ativa'
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Retorna empresa padrão (para compatibilidade)
     */
    private function getDefaultTenant(): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM empresas 
            WHERE subdomain = 'principal' OR id = 1
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Extrai subdomain da URL
     */
    private function extractSubdomain(): ?string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);
        
        if (count($parts) >= 3) {
            return $parts[0];
        }
        
        return null;
    }
    
    /**
     * Extrai path prefix da URL
     */
    private function extractPathPrefix(): ?string {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $pathParts = explode('/', trim($path, '/'));
        
        if (!empty($pathParts[0]) && !in_array($pathParts[0], ['api', 'assets', 'css', 'js'])) {
            return $pathParts[0];
        }
        
        return null;
    }
    
    /**
     * Gera contexto único para o Asterisk baseado na empresa
     */
    public function getAsteriskContext(): string {
        $tenant = $this->getCurrentTenant();
        return $tenant['contexto_asterisk'] ?? 'default';
    }
    
    /**
     * Gera prefixo de ramal baseado na empresa
     */
    public function getRamalPrefix(): string {
        $tenant = $this->getCurrentTenant();
        return $tenant['prefixo_ramal'] ?? '1000';
    }
    
    /**
     * Verifica se usuário é admin geral (acesso a todos os tenants)
     */
    public function isGlobalAdmin(int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT perfil FROM usuarios 
            WHERE id = ? AND ativo = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        return $user && $user['perfil'] === 'admin_geral';
    }
    
    /**
     * Lista todas as empresas (só para admin geral)
     */
    public function getAllTenants(): array {
        $stmt = $this->db->prepare("
            SELECT e.*, 
                   COUNT(u.id) as total_usuarios,
                   COUNT(c.id) as total_campanhas
            FROM empresas e
            LEFT JOIN usuarios u ON e.id = u.empresa_id AND u.ativo = 1
            LEFT JOIN campanhas c ON e.id = c.empresa_id
            GROUP BY e.id
            ORDER BY e.nome
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Limpa cache do tenant (útil para testes)
     */
    public function clearTenantCache(): void {
        $this->currentTenant = null;
        unset($_SESSION['tenant_id'], $_SESSION['tenant_data']);
    }
}
