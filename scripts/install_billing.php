#!/usr/bin/env php
<?php
/**
 * Instalador do Schema de Billing e Centro de Custos
 * 
 * Este script instala todas as tabelas, views, procedures e triggers
 * necessários para o sistema de billing multi-tenant.
 */

require_once __DIR__ . '/../src/config/database.php';

class BillingSchemaInstaller {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function install() {
        try {
            echo "🚀 Iniciando instalação do Schema de Billing...\n\n";
            
            // Ler arquivo SQL
            $sqlFile = __DIR__ . '/sql/03_billing_schema.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("Arquivo SQL não encontrado: $sqlFile");
            }
            
            $sql = file_get_contents($sqlFile);
            
            // Dividir em comandos separados
            $commands = $this->splitSqlCommands($sql);
            
            $this->pdo->beginTransaction();
            
            $totalCommands = count($commands);
            $success = 0;
            $errors = 0;
            
            foreach ($commands as $i => $command) {
                $command = trim($command);
                if (empty($command) || $command === 'COMMIT;') continue;
                
                try {
                    $this->pdo->exec($command);
                    $success++;
                    echo "✅ Comando " . ($i + 1) . "/$totalCommands executado com sucesso\n";
                } catch (PDOException $e) {
                    $errors++;
                    echo "❌ Erro no comando " . ($i + 1) . ": " . $e->getMessage() . "\n";
                    
                    // Continuar mesmo com erros menores
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
            
            $this->pdo->commit();
            
            echo "\n🎉 Instalação concluída!\n";
            echo "✅ Comandos executados com sucesso: $success\n";
            echo "⚠️  Comandos com erro (podem ser normais): $errors\n\n";
            
            // Verificar se as tabelas foram criadas
            $this->verifyInstallation();
            
            // Inserir dados de exemplo se solicitado
            if ($this->askForSampleData()) {
                $this->installSampleData();
            }
            
            echo "\n🎯 Schema de Billing instalado com sucesso!\n";
            echo "📋 Próximos passos:\n";
            echo "   1. Configure as tarifas padrão por empresa\n";
            echo "   2. Execute o processamento inicial de custos\n";
            echo "   3. Configure alertas de billing\n";
            echo "   4. Teste as APIs de billing\n\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "💥 Erro durante a instalação: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    private function splitSqlCommands($sql) {
        // Remove comentários e divide por delimiters
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Separar por DELIMITER
        $parts = preg_split('/DELIMITER\s+/i', $sql);
        $commands = [];
        
        $currentDelimiter = ';';
        
        foreach ($parts as $part) {
            $lines = explode("\n", $part);
            $newDelimiter = trim($lines[0]);
            
            if (!empty($newDelimiter) && $newDelimiter !== $currentDelimiter) {
                $currentDelimiter = $newDelimiter;
                array_shift($lines);
                $part = implode("\n", $lines);
            }
            
            if ($currentDelimiter === ';') {
                $cmds = explode(';', $part);
            } else {
                $cmds = explode($currentDelimiter, $part);
            }
            
            foreach ($cmds as $cmd) {
                $cmd = trim($cmd);
                if (!empty($cmd)) {
                    $commands[] = $cmd;
                }
            }
        }
        
        return $commands;
    }
    
    private function verifyInstallation() {
        echo "🔍 Verificando instalação...\n";
        
        $tables = [
            'tarifas_empresa',
            'billing_chamadas', 
            'billing_faturas',
            'billing_pagamentos',
            'billing_alertas',
            'billing_configuracoes',
            'billing_relatorios_cache'
        ];
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT 1 FROM $table LIMIT 1");
                echo "✅ Tabela $table: OK\n";
            } catch (PDOException $e) {
                echo "❌ Tabela $table: ERRO - " . $e->getMessage() . "\n";
            }
        }
        
        // Verificar view
        try {
            $stmt = $this->pdo->query("SELECT 1 FROM v_billing_estatisticas LIMIT 1");
            echo "✅ View v_billing_estatisticas: OK\n";
        } catch (PDOException $e) {
            echo "❌ View v_billing_estatisticas: ERRO - " . $e->getMessage() . "\n";
        }
        
        // Verificar procedures
        $procedures = ['sp_gerar_fatura_mensal', 'sp_processar_custo_chamada', 'sp_classificar_destino'];
        
        foreach ($procedures as $proc) {
            try {
                $stmt = $this->pdo->query("SHOW PROCEDURE STATUS WHERE Name = '$proc'");
                if ($stmt->rowCount() > 0) {
                    echo "✅ Procedure $proc: OK\n";
                } else {
                    echo "❌ Procedure $proc: NÃO ENCONTRADA\n";
                }
            } catch (PDOException $e) {
                echo "❌ Procedure $proc: ERRO - " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function askForSampleData() {
        echo "\n❓ Deseja instalar dados de exemplo? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        return strtolower(trim($line)) === 'y';
    }
    
    private function installSampleData() {
        echo "\n📝 Instalando dados de exemplo...\n";
        
        try {
            // Inserir tarifas padrão para empresas existentes
            $stmt = $this->pdo->query("SELECT id FROM empresas WHERE status = 'ativa'");
            $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $tarifasPadrao = [
                'fixo_local' => 0.08,
                'fixo_ddd' => 0.12,
                'celular_local' => 0.35,
                'celular_ddd' => 0.45,
                'internacional' => 2.50,
                'especial' => 1.20
            ];
            
            foreach ($empresas as $empresa) {
                foreach ($tarifasPadrao as $tipo => $valor) {
                    $stmt = $this->pdo->prepare("
                        INSERT IGNORE INTO tarifas_empresa (empresa_id, tipo_destino, tarifa_por_minuto)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$empresa['id'], $tipo, $valor]);
                }
                
                // Inserir configurações padrão
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO billing_configuracoes (
                        empresa_id, dia_vencimento, limite_credito, 
                        envio_automatico_fatura, dias_aviso_vencimento
                    ) VALUES (?, 10, 100.00, 1, 3)
                ");
                $stmt->execute([$empresa['id']]);
                
                echo "✅ Dados de exemplo criados para empresa ID: {$empresa['id']}\n";
            }
            
            echo "🎉 Dados de exemplo instalados com sucesso!\n";
            
        } catch (Exception $e) {
            echo "❌ Erro ao instalar dados de exemplo: " . $e->getMessage() . "\n";
        }
    }
}

// Executar instalação
if (php_sapi_name() === 'cli') {
    $installer = new BillingSchemaInstaller();
    $installer->install();
} else {
    echo "Este script deve ser executado via linha de comando.\n";
}
