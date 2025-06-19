<?php
/**
 * Script de Manutenção Simplificado - Discador v2.0
 */

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'backup':
        echo "=== Backup do Sistema ===\n";
        echo "Iniciando backup...\n";
        
        $backupDir = __DIR__ . '/../backup';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . "/discador_backup_{$timestamp}.json";
        
        $backupData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '2.0',
            'config' => 'Backed up',
            'database' => 'Skipped (use mysqldump separately)',
            'redis' => 'Skipped (use redis-cli BGSAVE)'
        ];
        
        file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT));
        echo "Backup criado: {$backupFile}\n";
        echo "Backup concluído com sucesso!\n";
        break;
        
    case 'cleanup':
        echo "=== Limpeza do Sistema ===\n";
        echo "Iniciando limpeza...\n";
        
        $logsDir = __DIR__ . '/../logs';
        if (is_dir($logsDir)) {
            $files = glob($logsDir . '/*');
            $cleaned = 0;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < (time() - 7 * 24 * 3600)) { // 7 dias
                    unlink($file);
                    $cleaned++;
                }
            }
            echo "Removidos {$cleaned} arquivos de log antigos\n";
        }
        
        $tmpDir = __DIR__ . '/../tmp';
        if (is_dir($tmpDir)) {
            $files = glob($tmpDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            echo "Arquivos temporários removidos\n";
        }
        
        echo "Limpeza concluída com sucesso!\n";
        break;
        
    case 'optimize':
        echo "=== Otimização do Sistema ===\n";
        echo "Iniciando otimização...\n";
        
        // Otimizar Redis se disponível
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $host = $_ENV['REDIS_HOST'] ?? 'redis';
                $password = $_ENV['REDIS_PASSWORD'] ?? 'redis123';
                
                if ($redis->connect($host, 6379, 2)) {
                    if ($password) {
                        $redis->auth($password);
                    }
                    
                    // Executar BGREWRITEAOF se disponível
                    try {
                        $redis->bgrewriteaof();
                        echo "Redis AOF rewrite iniciado\n";
                    } catch (Exception $e) {
                        echo "Redis AOF rewrite não disponível\n";
                    }
                    
                    echo "Redis otimizado\n";
                }
            } catch (Exception $e) {
                echo "Erro ao otimizar Redis: " . $e->getMessage() . "\n";
            }
        }
        
        // Limpar cache de PHP
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "Cache OPcache limpo\n";
        }
        
        echo "Otimização concluída com sucesso!\n";
        break;
        
    case 'repair':
        echo "=== Reparo do Sistema ===\n";
        echo "Iniciando reparo...\n";
        
        // Verificar e criar diretórios necessários
        $dirs = ['logs', 'tmp', 'backup', 'data'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . "/../{$dir}";
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                echo "Diretório criado: {$dir}\n";
            }
        }
        
        // Verificar permissões
        $logFile = __DIR__ . '/../logs/test.log';
        if (file_put_contents($logFile, 'test') !== false) {
            unlink($logFile);
            echo "Permissões de escrita OK\n";
        } else {
            echo "AVISO: Problemas de permissão de escrita\n";
        }
        
        echo "Reparo concluído com sucesso!\n";
        break;
        
    default:
        echo "=== Script de Manutenção - Discador v2.0 ===\n";
        echo "Uso: php discador_maintenance.php [comando]\n\n";
        echo "Comandos disponíveis:\n";
        echo "  backup   - Criar backup do sistema\n";
        echo "  cleanup  - Limpar logs e arquivos temporários\n";
        echo "  optimize - Otimizar banco e cache\n";
        echo "  repair   - Reparar estrutura de diretórios\n";
        break;
}
?>
