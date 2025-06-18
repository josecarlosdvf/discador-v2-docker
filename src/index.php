<?php
/**
 * Sistema Discador v2.0
 * Página inicial para teste e status do ambiente
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Discador v2.0 - Status</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .status-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #ddd;
        }
        .status-card.success {
            border-left-color: #28a745;
        }
        .status-card.warning {
            border-left-color: #ffc107;
        }
        .status-card.error {
            border-left-color: #dc3545;
        }
        .status-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .info-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .info-item strong {
            color: #495057;
        }
        .timestamp {
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sistema Discador v2.0</h1>
            <p>Ambiente Dockerizado - Status dos Serviços</p>
        </div>
        
        <div class="content">
            <div class="status-grid">
                
                <!-- Status do PHP -->
                <div class="status-card success">
                    <h3>🐘 PHP</h3>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge success"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="status-item">
                        <span>SAPI</span>
                        <span class="status-badge success"><?php echo php_sapi_name(); ?></span>
                    </div>
                    <div class="status-item">
                        <span>Timezone</span>
                        <span class="status-badge success"><?php echo date_default_timezone_get(); ?></span>
                    </div>
                </div>

                <!-- Status do Banco de Dados -->
                <div class="status-card <?php
                    try {
                        $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'database') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'discador');
                        $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'discador_user', $_ENV['DB_PASSWORD'] ?? 'discador_pass_2024!');
                        echo 'success';
                        $db_status = 'Conectado';
                        $db_version = $pdo->query('SELECT VERSION()')->fetchColumn();
                    } catch (Exception $e) {
                        echo 'error';
                        $db_status = 'Erro: ' . $e->getMessage();
                        $db_version = 'N/A';
                    }
                ?>">
                    <h3>🗄️ MariaDB</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $db_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $db_version; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $_ENV['DB_HOST'] ?? 'database'; ?></span>
                    </div>
                </div>

                <!-- Status do Redis -->
                <div class="status-card <?php
                    try {
                        $redis_host = $_ENV['REDIS_HOST'] ?? 'redis';
                        $redis_port = 6379;
                        $redis = new Redis();
                        $redis->connect($redis_host, $redis_port, 2);
                        if (!empty($_ENV['REDIS_PASSWORD'])) {
                            $redis->auth($_ENV['REDIS_PASSWORD']);
                        }
                        $redis_info = $redis->info();
                        echo 'success';
                        $redis_status = 'Conectado';
                        $redis_version = $redis_info['redis_version'] ?? 'N/A';
                    } catch (Exception $e) {
                        echo 'warning';
                        $redis_status = 'Não disponível';
                        $redis_version = 'N/A';
                    }
                ?>">
                    <h3>⚡ Redis</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo isset($redis) && $redis->ping() ? 'success' : 'warning'; ?>"><?php echo $redis_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge <?php echo isset($redis) ? 'success' : 'warning'; ?>"><?php echo $redis_version; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo isset($redis) ? 'success' : 'warning'; ?>"><?php echo $redis_host; ?></span>
                    </div>
                </div>

                <!-- Status do Asterisk -->
                <div class="status-card <?php
                    $asterisk_host = $_ENV['ASTERISK_HOST'] ?? 'asterisk';
                    $asterisk_status = 'Verificando...';
                    $asterisk_port = 5038; // Manager port
                    
                    $socket = @fsockopen($asterisk_host, $asterisk_port, $errno, $errstr, 2);
                    if ($socket) {
                        echo 'success';
                        $asterisk_status = 'Online';
                        fclose($socket);
                    } else {
                        echo 'warning';
                        $asterisk_status = 'Offline';
                    }
                ?>">
                    <h3>📞 Asterisk</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Manager Port</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_port; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_host; ?></span>
                    </div>
                </div>
            </div>

            <!-- Informações do Sistema -->
            <div class="info-section">
                <h3>ℹ️ Informações do Sistema</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Servidor Web:</strong><br>
                        Nginx + PHP-FPM
                    </div>
                    <div class="info-item">
                        <strong>Sistema Operacional:</strong><br>
                        <?php echo php_uname('s') . ' ' . php_uname('r'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Memória PHP:</strong><br>
                        <?php echo ini_get('memory_limit'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Upload Max:</strong><br>
                        <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Extensões Carregadas:</strong><br>
                        <?php echo count(get_loaded_extensions()); ?> extensões
                    </div>
                    <div class="info-item">
                        <strong>Docker Network:</strong><br>
                        discador_network
                    </div>
                </div>
            </div>

            <!-- Extensões PHP Importantes -->
            <div class="info-section">
                <h3>🔧 Extensões PHP</h3>
                <div class="info-grid">
                    <?php
                    $important_extensions = [
                        'pdo_mysql' => 'PDO MySQL',
                        'mysqli' => 'MySQLi',
                        'redis' => 'Redis',
                        'gd' => 'GD (Imagens)',
                        'curl' => 'cURL',
                        'json' => 'JSON',
                        'mbstring' => 'Multibyte String',
                        'zip' => 'ZIP',
                        'xml' => 'XML',
                        'openssl' => 'OpenSSL'
                    ];
                    
                    foreach ($important_extensions as $ext => $name) {
                        $loaded = extension_loaded($ext);
                        echo '<div class="info-item">';
                        echo '<strong>' . $name . ':</strong><br>';
                        echo '<span class="status-badge ' . ($loaded ? 'success' : 'error') . '">';
                        echo $loaded ? 'Carregada' : 'Não Carregada';
                        echo '</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div class="timestamp">
                Última verificação: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>
</body>
</html>
