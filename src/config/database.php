<?php
/**
 * Configurações de Banco de Dados - Sistema Discador v2.0
 * 
 * Este arquivo contém as configurações de conexão com o banco de dados
 * usando variáveis de ambiente do Docker.
 */

return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'database',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_NAME'] ?? 'discador',
            'username' => $_ENV['DB_USER'] ?? 'discador_user',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
            ]
        ]
    ],
    
    'redis' => [
        'host' => $_ENV['REDIS_HOST'] ?? 'redis',
        'port' => $_ENV['REDIS_PORT'] ?? 6379,
        'password' => $_ENV['REDIS_PASSWORD'] ?? null,
        'database' => 0,
        'timeout' => 2.0
    ]
];
