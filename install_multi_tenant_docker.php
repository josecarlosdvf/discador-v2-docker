<?php
/**
 * Instalação Multi-Tenant - Sistema Discador v2.0 (Docker)
 * 
 * Este script cria as tabelas necessárias para o sistema multi-tenant
 * usando conexão Docker.
 */

echo "=== INSTALAÇÃO MULTI-TENANT DISCADOR V2 (DOCKER) ===\n\n";

// Configuração Docker
$host = 'localhost';
$port = 3307;
$user = 'root';
$password = 'root123';
$database = 'discador';

try {
    echo "📊 Conectando ao MariaDB (Docker)...\n";
    
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexão estabelecida com sucesso!\n\n";
    
    // Selecionar/criar database
    echo "📋 Selecionando database '$database'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$database`");
    echo "✅ Database selecionado!\n\n";
    
    // SQL para criar tabelas multi-tenant
    $sql_commands = [
        // Tabela de empresas
        "CREATE TABLE IF NOT EXISTS `empresas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) NOT NULL,
            `cnpj` varchar(18) UNIQUE DEFAULT NULL,
            `email` varchar(255) NOT NULL,
            `telefone` varchar(20) DEFAULT NULL,
            `endereco` text DEFAULT NULL,
            `plano` enum('basico','intermediario','avancado') DEFAULT 'basico',
            `max_ramais` int(11) DEFAULT 10,
            `max_campanhas` int(11) DEFAULT 5,
            `max_usuarios` int(11) DEFAULT 10,
            `prefixo_ramais` varchar(10) DEFAULT NULL,
            `status` enum('pendente','ativo','suspenso','cancelado') DEFAULT 'pendente',
            `data_cadastro` timestamp DEFAULT CURRENT_TIMESTAMP,
            `data_aprovacao` timestamp NULL DEFAULT NULL,
            `observacoes` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cnpj` (`cnpj`),
            UNIQUE KEY `prefixo_ramais` (`prefixo_ramais`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de usuários multi-tenant
        "CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `empresa_id` int(11) NOT NULL,
            `nome` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            `senha` varchar(255) NOT NULL,
            `nivel` enum('master','supervisor','operador') DEFAULT 'operador',
            `ramal` varchar(20) DEFAULT NULL,
            `campanhas_permitidas` text DEFAULT NULL,
            `ativo` tinyint(1) DEFAULT 1,
            `ultimo_login` timestamp NULL DEFAULT NULL,
            `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email_empresa` (`email`, `empresa_id`),
            KEY `empresa_id` (`empresa_id`),
            CONSTRAINT `usuarios_empresa_fk` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de campanhas por empresa
        "CREATE TABLE IF NOT EXISTS `campanhas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `empresa_id` int(11) NOT NULL,
            `nome` varchar(255) NOT NULL,
            `descricao` text DEFAULT NULL,
            `fila_id` int(11) DEFAULT NULL,
            `status` enum('criada','iniciada','pausada','finalizada') DEFAULT 'criada',
            `tipo_discagem` enum('manual','automatica','preview') DEFAULT 'automatica',
            `max_tentativas` int(11) DEFAULT 3,
            `intervalo_tentativas` int(11) DEFAULT 3600,
            `horario_inicio` time DEFAULT '08:00:00',
            `horario_fim` time DEFAULT '18:00:00',
            `dias_semana` varchar(20) DEFAULT 'seg,ter,qua,qui,sex',
            `ativa` tinyint(1) DEFAULT 1,
            `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
            `data_inicio` timestamp NULL DEFAULT NULL,
            `data_fim` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `empresa_id` (`empresa_id`),
            CONSTRAINT `campanhas_empresa_fk` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de billing/faturas
        "CREATE TABLE IF NOT EXISTS `billing_faturas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `empresa_id` int(11) NOT NULL,
            `mes_referencia` date NOT NULL,
            `total_ligacoes` int(11) DEFAULT 0,
            `total_minutos` decimal(10,2) DEFAULT 0.00,
            `custo_ligacoes` decimal(10,2) DEFAULT 0.00,
            `custo_adicional` decimal(10,2) DEFAULT 0.00,
            `desconto` decimal(10,2) DEFAULT 0.00,
            `valor_total` decimal(10,2) DEFAULT 0.00,
            `status_pagamento` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
            `data_vencimento` date DEFAULT NULL,
            `data_pagamento` timestamp NULL DEFAULT NULL,
            `observacoes` text DEFAULT NULL,
            `data_geracao` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `empresa_mes` (`empresa_id`, `mes_referencia`),
            KEY `empresa_id` (`empresa_id`),
            CONSTRAINT `billing_empresa_fk` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Tabela de administradores globais
        "CREATE TABLE IF NOT EXISTS `admin_global` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) NOT NULL,
            `email` varchar(255) UNIQUE NOT NULL,
            `senha` varchar(255) NOT NULL,
            `ativo` tinyint(1) DEFAULT 1,
            `ultimo_login` timestamp NULL DEFAULT NULL,
            `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    echo "🔧 Criando/atualizando tabelas multi-tenant...\n";
    
    foreach ($sql_commands as $i => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Comando " . ($i + 1) . " executado com sucesso\n";
        } catch (PDOException $e) {
            echo "❌ Erro no comando " . ($i + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🔧 Criando admin global padrão...\n";
    
    // Criar admin global padrão se não existir
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_global WHERE email = ?");
    $stmt->execute(['admin@discador.com']);
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO admin_global (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->execute([
            'Administrador',
            'admin@discador.com', 
            password_hash('admin123', PASSWORD_DEFAULT)
        ]);
        echo "✅ Admin global criado: admin@discador.com / admin123\n";
    } else {
        echo "ℹ️ Admin global já existe\n";
    }
    
    echo "\n🔧 Criando empresa de demonstração...\n";
    
    // Criar empresa demo se não existir
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE cnpj = ?");
    $stmt->execute(['11.111.111/0001-11']);
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO empresas (nome, cnpj, email, telefone, plano, max_ramais, max_campanhas, max_usuarios, prefixo_ramais, status, data_aprovacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            'Empresa Demonstração',
            '11.111.111/0001-11',
            'demo@empresa.com',
            '(11) 99999-9999',
            'intermediario',
            50,
            10,
            20,
            '1000',
            'ativo'
        ]);
        $empresa_id = $pdo->lastInsertId();
        
        echo "✅ Empresa demo criada (ID: $empresa_id)\n";
        
        // Criar usuário master para empresa demo
        $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha, nivel) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $empresa_id,
            'Usuário Master Demo',
            'master@empresa.com',
            password_hash('master123', PASSWORD_DEFAULT),
            'master'
        ]);
        
        echo "✅ Usuário master criado: master@empresa.com / master123\n";
        
    } else {
        echo "ℹ️ Empresa demo já existe\n";
    }
    
    echo "\n📊 Verificando estrutura criada...\n";
    
    $tabelas = ['empresas', 'usuarios', 'campanhas', 'billing_faturas', 'admin_global'];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$tabela`");
        $result = $stmt->fetch();
        echo "✅ Tabela '$tabela': {$result['count']} registros\n";
    }
    
    echo "\n🎉 INSTALAÇÃO MULTI-TENANT CONCLUÍDA COM SUCESSO!\n\n";
    echo "🔐 Credenciais de acesso:\n";
    echo "   Admin Global: admin@discador.com / admin123\n";
    echo "   Empresa Demo: master@empresa.com / master123\n\n";
    echo "🌐 Acesse: http://localhost:8080/src/login.php\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
