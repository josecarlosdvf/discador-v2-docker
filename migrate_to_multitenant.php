<?php
/**
 * Migração Multi-Tenant - Sistema Discador v2.0
 * 
 * Este script migra o sistema single-tenant existente para multi-tenant
 * preservando dados e criando estrutura compatível.
 */

echo "=== MIGRAÇÃO PARA MULTI-TENANT ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3307;dbname=discador;charset=utf8mb4',
        'root',
        'root123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Conectado ao banco 'discador'\n\n";
    
    // 1. Criar tabela de empresas
    echo "🏢 Criando tabela de empresas...\n";
    $sql_empresas = "CREATE TABLE IF NOT EXISTS `empresas` (
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
        `status` enum('pendente','ativo','suspenso','cancelado') DEFAULT 'ativo',
        `data_cadastro` timestamp DEFAULT CURRENT_TIMESTAMP,
        `data_aprovacao` timestamp NULL DEFAULT NULL,
        `observacoes` text DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_empresas);
    echo "✅ Tabela empresas criada\n";
    
    // 2. Criar empresa principal (para dados existentes)
    echo "🏢 Criando empresa principal...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO empresas (id, nome, cnpj, email, telefone, plano, max_ramais, max_campanhas, max_usuarios, prefixo_ramais, status, data_aprovacao) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            'Empresa Principal',
            '00.000.000/0001-00',
            'contato@empresa.com',
            '(11) 0000-0000',
            'avancado',
            100,
            50,
            50,
            '2000',
            'ativo'
        ]);
        echo "✅ Empresa principal criada (ID: 1)\n";
    } else {
        echo "ℹ️ Empresa principal já existe\n";
    }
    
    // 3. Renomear tabela usuarios existente e criar nova com empresa_id
    echo "👥 Migrando estrutura de usuários...\n";
    
    // Verificar se já foi migrada
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'empresa_id'");
    if ($stmt->rowCount() == 0) {
        // Backup da tabela original
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios_backup AS SELECT * FROM usuarios");
        echo "✅ Backup da tabela usuarios criado\n";
        
        // Adicionar coluna empresa_id
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN empresa_id int(11) NOT NULL DEFAULT 1 FIRST");
        $pdo->exec("ALTER TABLE usuarios ADD CONSTRAINT usuarios_empresa_fk FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE");
        echo "✅ Coluna empresa_id adicionada à tabela usuarios\n";
        
        // Atualizar usuários existentes para empresa_id = 1
        $pdo->exec("UPDATE usuarios SET empresa_id = 1 WHERE empresa_id = 0");
        echo "✅ Usuários existentes vinculados à empresa principal\n";
    } else {
        echo "ℹ️ Tabela usuarios já foi migrada\n";
    }
    
    // 4. Criar tabela de campanhas
    echo "📞 Criando tabela de campanhas...\n";
    $sql_campanhas = "CREATE TABLE IF NOT EXISTS `campanhas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_campanhas);
    echo "✅ Tabela campanhas criada\n";
    
    // 5. Criar campanha demo para empresa principal
    echo "📞 Criando campanha demo...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campanhas WHERE empresa_id = 1");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO campanhas (empresa_id, nome, descricao, status, tipo_discagem) VALUES (1, ?, ?, ?, ?)");
        $stmt->execute([
            'Campanha Demonstração',
            'Campanha de demonstração criada automaticamente durante a migração',
            'criada',
            'automatica'
        ]);
        echo "✅ Campanha demo criada\n";
    } else {
        echo "ℹ️ Campanhas já existem para empresa principal\n";
    }
    
    // 6. Criar tabela de billing
    echo "💰 Criando tabela de billing...\n";
    $sql_billing = "CREATE TABLE IF NOT EXISTS `billing_faturas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_billing);
    echo "✅ Tabela billing criada\n";
    
    // 7. Adicionar empresa_id às tabelas existentes se necessário
    echo "🔧 Atualizando tabelas existentes...\n";
    
    $tabelas_para_migrar = ['ramais', 'filas'];
    foreach ($tabelas_para_migrar as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$tabela` LIKE 'empresa_id'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `$tabela` ADD COLUMN empresa_id int(11) NOT NULL DEFAULT 1");
                $pdo->exec("ALTER TABLE `$tabela` ADD CONSTRAINT {$tabela}_empresa_fk FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE");
                echo "✅ Tabela $tabela migrada para multi-tenant\n";
            } else {
                echo "ℹ️ Tabela $tabela já é multi-tenant\n";
            }
        }
    }
    
    // 8. Criar empresa de demo para testes
    echo "🏢 Criando empresa de demonstração...\n";
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
        $empresa_demo_id = $pdo->lastInsertId();
        
        echo "✅ Empresa demo criada (ID: $empresa_demo_id)\n";
        
        // Criar usuário master para empresa demo
        $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, login, senha, email, perfil, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $empresa_demo_id,
            'Usuário Master Demo',
            'master@empresa.com',
            password_hash('master123', PASSWORD_DEFAULT),
            'master@empresa.com',
            'admin',
            1
        ]);
        
        echo "✅ Usuário master demo criado: master@empresa.com / master123\n";
        
    } else {
        echo "ℹ️ Empresa demo já existe\n";
    }
    
    echo "\n📊 Verificando estrutura final...\n";
    
    $tabelas = ['empresas', 'usuarios', 'campanhas', 'billing_faturas', 'admin_global'];
    foreach ($tabelas as $tabela) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$tabela`");
        $result = $stmt->fetch();
        echo "✅ Tabela '$tabela': {$result['count']} registros\n";
    }
    
    echo "\n🎉 MIGRAÇÃO MULTI-TENANT CONCLUÍDA COM SUCESSO!\n\n";
    echo "🔐 Credenciais de acesso:\n";
    echo "   Admin Global: admin@discador.com / admin123\n";
    echo "   Empresa Principal: [usuário existente]\n";
    echo "   Empresa Demo: master@empresa.com / master123\n\n";
    echo "🌐 Acesse: http://localhost:8080/src/login.php\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
