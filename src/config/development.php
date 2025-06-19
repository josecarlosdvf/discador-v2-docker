<?php
/**
 * Configuração de Ambiente para Desenvolvimento/Testes
 * 
 * Este arquivo configura variáveis de ambiente para testes locais
 * quando Docker não estiver disponível.
 */

// Configurar variáveis de ambiente para desenvolvimento
if (!isset($_ENV['DB_HOST'])) {
    $_ENV['DB_HOST'] = 'localhost';
    $_ENV['DB_PORT'] = '3306';
    $_ENV['DB_NAME'] = 'discador_v2';
    $_ENV['DB_USER'] = 'root';
    $_ENV['DB_PASSWORD'] = '';
    $_ENV['APP_DEBUG'] = 'true';
    
    $_ENV['REDIS_HOST'] = 'localhost';
    $_ENV['REDIS_PORT'] = '6379';
    $_ENV['REDIS_PASSWORD'] = null;
}

// Definir constantes para compatibilidade com código legado
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST']);
    define('DB_NAME', $_ENV['DB_NAME']);
    define('DB_USER', $_ENV['DB_USER']);
    define('DB_PASS', $_ENV['DB_PASSWORD']);
    define('DB_PORT', $_ENV['DB_PORT']);
}

echo "🔧 Ambiente configurado para desenvolvimento local\n";
echo "📋 Host: " . $_ENV['DB_HOST'] . "\n";
echo "📋 Database: " . $_ENV['DB_NAME'] . "\n";
echo "📋 User: " . $_ENV['DB_USER'] . "\n";
