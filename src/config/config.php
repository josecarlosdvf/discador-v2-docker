<?php
/**
 * Configuração do Sistema Discador v2.0
 * Arquivo de configuração modernizado para Docker
 */

// Carregar variáveis de ambiente para desenvolvimento local
if (!file_exists('/.dockerenv') && file_exists(__DIR__ . '/../../.env.local')) {
    $envFile = __DIR__ . '/../../.env.local';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Skip comments
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
    }
}

// Configurações do Banco de Dados
define('DB_HOST', $_ENV['DB_HOST'] ?? 'database');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'discador');
define('DB_USER', $_ENV['DB_USER'] ?? 'discador');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? 'discador123');

// Configurações do Redis
define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? 'redis');
define('REDIS_PORT', $_ENV['REDIS_PORT'] ?? '6379');
define('REDIS_PASS', $_ENV['REDIS_PASSWORD'] ?? null);
define('REDIS_DB', $_ENV['REDIS_DB'] ?? 0);

// Configurações da Aplicação
define('APP_NAME', 'Sistema Discador v2.0');
define('APP_VERSION', '2.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');

// Configurações de Sessão
define('SESSION_TIMEOUT', 3600); // 1 hora
define('SESSION_NAME', 'DISCADOR_SESSION');

// Configurações de Segurança
define('HASH_ALGO', 'sha256');
define('PASSWORD_MIN_LENGTH', 6);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configurações do Sistema
define('TIMEZONE', 'America/Sao_Paulo');
define('LOCALE', 'pt_BR.UTF-8');

// Configurar timezone
date_default_timezone_set(TIMEZONE);

/**
 * Classe de Conexão com o Banco de Dados
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erro de conexão com o banco: " . $e->getMessage());
            throw new Exception("Erro de conexão com o banco de dados");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

/**
 * Classe de Conexão com Redis
 */
class RedisConnection {
    private static $instance = null;
    private $redis;
    
    private function __construct() {
        try {
            $this->redis = new Redis();
            $this->redis->connect(REDIS_HOST, REDIS_PORT);
            
            if (REDIS_PASS) {
                $this->redis->auth(REDIS_PASS);
            }
        } catch (Exception $e) {
            error_log("Erro de conexão com Redis: " . $e->getMessage());
            $this->redis = null;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->redis;
    }
    
    public function isConnected() {
        return $this->redis !== null;
    }
}

/**
 * Funções Utilitárias
 */

/**
 * Gera hash seguro para senhas
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verifica senha com hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Gera token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitiza entrada de dados
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Log de atividades
 */
function logActivity($action, $details = [], $userId = null) {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log("ACTIVITY_LOG: " . json_encode($logData));
    
    // Salvar no Redis se disponível
    try {
        $redis = RedisConnection::getInstance()->getConnection();
        if ($redis) {
            $redis->lpush('activity_log', json_encode($logData));
            $redis->ltrim('activity_log', 0, 999); // Manter apenas os últimos 1000 logs
        }
    } catch (Exception $e) {
        error_log("Erro ao salvar log no Redis: " . $e->getMessage());
    }
}

/**
 * Inicialização da aplicação
 */
function initApp() {
    // Configurar sessão segura
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    
    session_name(SESSION_NAME);
    session_start();
    
    // Regenerar ID da sessão periodicamente
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
    
    // Verificar timeout da sessão
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
    }
    $_SESSION['last_activity'] = time();
}

// Inicializar aplicação
initApp();
?>
