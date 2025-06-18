<?php
/**
 * Configurações da Aplicação - Sistema Discador v2.0
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'Sistema Discador v2',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['TZ'] ?? 'America/Sao_Paulo',
    
    'asterisk' => [
        'host' => $_ENV['ASTERISK_HOST'] ?? 'asterisk',
        'manager_port' => $_ENV['ASTERISK_MANAGER_PORT'] ?? 5038,
        'manager_user' => $_ENV['ASTERISK_MANAGER_USER'] ?? 'admin',
        'manager_password' => $_ENV['ASTERISK_MANAGER_PASSWORD'] ?? 'amp111',
        'sip_port' => $_ENV['ASTERISK_SIP_PORT'] ?? 5060
    ],
    
    'mail' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => $_ENV['MAIL_PORT'] ?? 25,
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? null
    ],
    
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'max_files' => $_ENV['LOG_MAX_FILES'] ?? 30,
        'path' => '/var/log/php'
    ]
];
