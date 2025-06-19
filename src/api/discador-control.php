<?php
/**
 * API para Controle do Discador v2.0
 */

// Iniciar output buffering para evitar saídas indevidas
ob_start();

// Limpar qualquer output anterior
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$command = $input['command'] ?? $_GET['command'] ?? '';

try {    // Encontrar o caminho correto dos scripts
    $possible_paths = [
        realpath(__DIR__ . '/../../scripts'),           // Para estrutura normal: src/api/ -> scripts/
        realpath(__DIR__ . '/../../../scripts'),        // Para Docker ou outras estruturas
        realpath(__DIR__ . '/../../../../scripts'),     // Para estruturas mais profundas
        '/var/www/html/scripts',                        // Para Docker padrão
        '/var/www/scripts',                             // Para Docker alternativo
        realpath(dirname(dirname(__DIR__)) . '/scripts'), // Caminho absoluto calculado
        realpath(__DIR__ . '/..'),                      // Scripts em src/ (mesmo nível da API)
        '/var/www/html'                                 // Scripts no diretório raiz do contêiner
    ];
    
    $scriptsPath = null;
    foreach ($possible_paths as $path) {
        if ($path && is_dir($path)) {
            $scriptsPath = $path;
            break;
        }
    }
    
    // Se ainda não encontrou, tenta buscar a partir do diretório raiz do projeto
    if (!$scriptsPath) {
        $rootDir = dirname(dirname(__DIR__)); // De src/api para raiz do projeto
        $testPath = $rootDir . DIRECTORY_SEPARATOR . 'scripts';
        if (is_dir($testPath)) {
            $scriptsPath = $testPath;
        }
    }
    
    // Para Docker: verificar se está no diretório html
    if (!$scriptsPath && file_exists('/.dockerenv')) {
        if (is_dir('/var/www/html')) {
            $scriptsPath = '/var/www/html';
        }
    }
    
    if (!$scriptsPath) {
        // Log para debug
        error_log('Scripts directory not found. Tried paths: ' . print_r($possible_paths, true));
        error_log('Current directory: ' . __DIR__);
        error_log('Project root should be: ' . dirname(dirname(__DIR__)));
        error_log('Docker env: ' . (file_exists('/.dockerenv') ? 'yes' : 'no'));
        throw new Exception('Scripts directory not found. Current dir: ' . __DIR__);
    }
    
    $output = '';
    $success = false;
    
    // Check if running in Docker container
    $isDocker = file_exists('/.dockerenv') || (getenv('CONTAINER') !== false);
    
    switch ($action) {        case 'control':
            $validCommands = ['start', 'stop', 'restart', 'status', 'workers', 'queue', 'logs'];
            if (!in_array($command, $validCommands)) {
                throw new Exception('Invalid command: ' . $command);
            }
            
            $phpPath = 'php';
            $scriptPath = $scriptsPath . DIRECTORY_SEPARATOR . 'discador_control.php';
            
            // Verifica se há extensão Redis disponível
            $hasRedis = extension_loaded('redis') && class_exists('Redis');
              // Se no Docker, usar sempre o script principal se Redis disponível
            if ($isDocker && $hasRedis) {
                // No Docker os scripts estão em /var/www/html/
                if (file_exists('/var/www/html/discador_control_main.php')) {
                    $scriptPath = '/var/www/html/discador_control_main.php';
                } elseif (file_exists('/var/www/html/discador_control.php')) {
                    $scriptPath = '/var/www/html/discador_control.php';
                }
            } elseif (!file_exists($scriptPath) || !$hasRedis) {
                // Usa o script fallback em src/ se não há Redis ou script principal
                $fallbackPath = __DIR__ . '/../discador_control.php';
                if (file_exists($fallbackPath)) {
                    $scriptPath = $fallbackPath;
                } elseif ($isDocker && file_exists('/var/www/html/discador_control.php')) {
                    $scriptPath = '/var/www/html/discador_control.php';
                } else {
                    throw new Exception('Control script not found in: ' . $scriptPath . ' or ' . $fallbackPath);
                }
            }
            
            // Detecta Windows e ajusta o comando
            $isWindows = (PHP_OS_FAMILY === 'Windows');
            
            if ($isDocker) {
                $cmd = sprintf('%s "%s" %s 2>&1', $phpPath, $scriptPath, escapeshellarg($command));
            } elseif ($isWindows) {
                $cmd = sprintf('powershell -Command "& {%s \"%s\" %s 2>$null}"', $phpPath, $scriptPath, escapeshellarg($command));
            } else {
                $cmd = sprintf('"%s" "%s" %s 2>&1', $phpPath, $scriptPath, escapeshellarg($command));
            }
            
            exec($cmd, $outputArray, $returnCode);
            $output = implode("\n", $outputArray);
            $success = ($returnCode === 0) || !empty($output);
            break;case 'maintenance':
            $validCommands = ['backup', 'cleanup', 'optimize', 'repair'];
            if (!in_array($command, $validCommands)) {
                throw new Exception('Invalid maintenance command: ' . $command);
            }
            
            $phpPath = 'php';
            $scriptPath = __DIR__ . '/../discador_maintenance.php';
            
            // Também verifica no diretório scripts
            if (!file_exists($scriptPath)) {
                $altPath = $scriptsPath . DIRECTORY_SEPARATOR . 'discador_maintenance.php';
                if (file_exists($altPath)) {
                    $scriptPath = $altPath;
                } else {
                    throw new Exception('Maintenance script not found in: ' . $scriptPath . ' or ' . $altPath);
                }
            }
            
            // Detecta Windows e ajusta o comando
            $isWindows = (PHP_OS_FAMILY === 'Windows');
            
            if ($isDocker) {
                $cmd = sprintf('%s "%s" %s 2>&1', $phpPath, $scriptPath, escapeshellarg($command));
            } elseif ($isWindows) {
                $cmd = sprintf('powershell -Command "& {%s \"%s\" %s}"', $phpPath, $scriptPath, escapeshellarg($command));
            } else {
                $cmd = sprintf('"%s" "%s" %s 2>&1', $phpPath, $scriptPath, escapeshellarg($command));
            }
            
            exec($cmd, $outputArray, $returnCode);
            $output = implode("\n", $outputArray);
            $success = ($returnCode === 0) || !empty($output);
            break;
              case 'diagnostic':
            $phpPath = 'php';
            $scriptPath = __DIR__ . '/../discador_diagnostic.php';
            
            // Também verifica no diretório scripts
            if (!file_exists($scriptPath)) {
                $altPath = $scriptsPath . DIRECTORY_SEPARATOR . 'discador_diagnostic.php';
                if (file_exists($altPath)) {
                    $scriptPath = $altPath;
                } else {
                    throw new Exception('Diagnostic script not found in: ' . $scriptPath . ' or ' . $altPath);
                }
            }
            
            // Detecta Windows e ajusta o comando
            $isWindows = (PHP_OS_FAMILY === 'Windows');
            
            if ($isDocker) {
                $cmd = sprintf('%s "%s" 2>&1', $phpPath, $scriptPath);
            } elseif ($isWindows) {
                $cmd = sprintf('powershell -Command "& {%s \"%s\"}"', $phpPath, $scriptPath);
            } else {
                $cmd = sprintf('"%s" "%s" 2>&1', $phpPath, $scriptPath);
            }
            
            exec($cmd, $outputArray, $returnCode);
            $output = implode("\n", $outputArray);
            $success = ($returnCode === 0) || !empty($output);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
      echo json_encode([
        'success' => $success,
        'output' => $output ?: 'Command executed successfully',
        'action' => $action,
        'command' => $command,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Limpar qualquer output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action,
        'command' => $command,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} finally {
    // Certificar que o output buffer está limpo
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>
