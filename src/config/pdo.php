<?php
/**
 * Inicialização da Conexão PDO Global
 * 
 * Este arquivo inicializa a conexão global com o banco de dados
 * que será utilizada por todas as classes do sistema.
 */

// Carregar configuração de desenvolvimento se necessário
if (!isset($_ENV['DB_HOST']) && file_exists(__DIR__ . '/development.php')) {
    require_once __DIR__ . '/development.php';
}

// Carregar configurações
$config = include __DIR__ . '/database.php';

// Configuração de ambiente (Docker/Local)
$dbConfig = $config['connections']['mysql'];

// Construir DSN
$dsn = sprintf(
    '%s:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['driver'],
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database'],
    $dbConfig['charset']
);

try {
    // Criar conexão PDO global
    $pdo = new PDO(
        $dsn,
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    // Configurar timezone para o Brasil
    $pdo->exec("SET time_zone = '-03:00'");
    
    // Definir variável global
    $GLOBALS['pdo'] = $pdo;
    
    if (php_sapi_name() === 'cli') {
        echo "✅ Conexão PDO estabelecida com sucesso\n";
    }
    
} catch (PDOException $e) {
    // Log do erro
    error_log("Falha na conexão com banco de dados: " . $e->getMessage());
    
    if (php_sapi_name() === 'cli') {
        echo "❌ Falha na conexão: " . $e->getMessage() . "\n";
        echo "💡 Sugestões:\n";
        echo "   - Verifique se o MySQL está rodando\n";
        echo "   - Configure as variáveis de ambiente corretas\n";
        echo "   - Use Docker: docker-compose up -d\n";
    }
    
    // Em desenvolvimento, mostrar erro
    if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        if (php_sapi_name() !== 'cli') {
            die("Erro na conexão com banco: " . $e->getMessage());
        }
    } else {
        // Em produção, mostrar mensagem genérica
        if (php_sapi_name() !== 'cli') {
            die("Erro interno do servidor. Tente novamente em alguns instantes.");
        }
    }
    
    // Para CLI, continuar sem interromper
    $GLOBALS['pdo'] = null;
}
