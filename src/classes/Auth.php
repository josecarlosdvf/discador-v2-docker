<?php
/**
 * Sistema de Autenticação - Discador v2.0
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;
    private $redis;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $redisConn = RedisConnection::getInstance();
        $this->redis = $redisConn->isConnected() ? $redisConn->getConnection() : null;
    }
    
    /**
     * Autentica um usuário
     */
    public function login($username, $password) {
        try {
            // Verificar tentativas de login
            if ($this->isLoginBlocked($username)) {
                return [
                    'success' => false,
                    'message' => 'Muitas tentativas de login. Tente novamente em 15 minutos.',
                    'code' => 'BLOCKED'
                ];
            }
            
            // Buscar usuário no banco
            $sql = "SELECT id, username, password, email, permissions, active, created_at, last_login 
                    FROM users WHERE username = :username AND deleted = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->recordFailedLogin($username);
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos.',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Verificar se usuário está ativo
            if (!$user['active']) {
                $this->recordFailedLogin($username);
                logActivity('login_attempt_inactive', ['username' => $username]);
                return [
                    'success' => false,
                    'message' => 'Usuário inativo. Contate o administrador.',
                    'code' => 'INACTIVE_USER'
                ];
            }
            
            // Verificar senha
            if (!verifyPassword($password, $user['password'])) {
                $this->recordFailedLogin($username);
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos.',
                    'code' => 'INVALID_CREDENTIALS'
                ];
            }
            
            // Login bem-sucedido
            $this->clearFailedLogins($username);
            $this->createSession($user);
            $this->updateLastLogin($user['id']);
            
            logActivity('login_success', [
                'user_id' => $user['id'],
                'username' => $username
            ], $user['id']);
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso!',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'permissions' => explode(';', $user['permissions'] ?? '')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro interno. Tente novamente.',
                'code' => 'INTERNAL_ERROR'
            ];
        }
    }
    
    /**
     * Cria sessão para o usuário
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['permissions'] = explode(';', $user['permissions'] ?? '');
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Gerar token da sessão
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        
        // Salvar no Redis se disponível
        if ($this->redis) {
            $sessionData = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'login_time' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];
            $this->redis->setex('session:' . session_id(), SESSION_TIMEOUT, json_encode($sessionData));
        }
    }
    
    /**
     * Verifica se usuário está autenticado
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * Verifica se usuário tem permissão específica
     */
    public function hasPermission($permission) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions) || in_array('admin', $permissions);
    }
    
    /**
     * Faz logout do usuário
     */
    public function logout() {
        if ($this->isAuthenticated()) {
            logActivity('logout', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ], $_SESSION['user_id']);
            
            // Remover do Redis
            if ($this->redis) {
                $this->redis->del('session:' . session_id());
            }
        }
        
        session_unset();
        session_destroy();
        
        // Iniciar nova sessão
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Verifica se login está bloqueado por muitas tentativas
     */
    private function isLoginBlocked($username) {
        if (!$this->redis) {
            return false;
        }
        
        $attempts = $this->redis->get("login_attempts:$username");
        return $attempts && $attempts >= LOGIN_MAX_ATTEMPTS;
    }
    
    /**
     * Registra tentativa de login falhada
     */
    private function recordFailedLogin($username) {
        logActivity('login_failed', ['username' => $username]);
        
        if (!$this->redis) {
            return;
        }
        
        $key = "login_attempts:$username";
        $this->redis->incr($key);
        $this->redis->expire($key, LOGIN_LOCKOUT_TIME);
    }
    
    /**
     * Limpa tentativas de login falhadas
     */
    private function clearFailedLogins($username) {
        if ($this->redis) {
            $this->redis->del("login_attempts:$username");
        }
    }
    
    /**
     * Atualiza último login do usuário
     */
    private function updateLastLogin($userId) {
        try {
            $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
        } catch (Exception $e) {
            error_log("Erro ao atualizar último login: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém dados do usuário atual
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        try {
            $sql = "SELECT id, username, email, permissions, created_at, last_login 
                    FROM users WHERE id = :id AND deleted = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $user['permissions'] = explode(';', $user['permissions'] ?? '');
                return $user;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Erro ao buscar usuário atual: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Middleware para verificar autenticação
     */
    public function requireAuth($requiredPermission = null) {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
        
        if ($requiredPermission && !$this->hasPermission($requiredPermission)) {
            header('HTTP/1.1 403 Forbidden');
            include 'templates/403.php';
            exit;
        }
    }
}

// Instância global
$auth = new Auth();
?>
