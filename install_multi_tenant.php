<?php
/**
 * Script de instalação do schema multi-tenant via PHP
 * Alternativa para quando há problemas de autenticação MySQL via linha de comando
 */

require_once 'config/database.php';

echo "=== INSTALAÇÃO MULTI-TENANT DISCADOR V2 ===\n";
echo "Tentando conectar ao MySQL...\n";

// Parâmetros de conexão alternativos
$configs = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => 'database', 'user' => 'discador_user', 'pass' => ''],
];

$pdo = null;
$dbName = 'discador_v2';

foreach ($configs as $i => $config) {
    echo "Tentativa " . ($i + 1) . ": " . $config['host'] . " / " . $config['user'] . "\n";
    
    try {
        // Conectar sem especificar database primeiro
        $dsn = "mysql:host={$config['host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "Conexão estabelecida com sucesso!\n";
        
        // Criar banco se não existir
        echo "Criando banco de dados '$dbName'...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Usar o banco
        $pdo->exec("USE `$dbName`");
        echo "Banco '$dbName' selecionado.\n";
        
        break;
        
    } catch (Exception $e) {
        echo "Falhou: " . $e->getMessage() . "\n";
        $pdo = null;
    }
}

if (!$pdo) {
    echo "\nERRO: Não foi possível conectar ao MySQL.\n";
    echo "Verifique se:\n";
    echo "1. O MySQL está rodando\n";
    echo "2. As credenciais estão corretas\n";
    echo "3. Não há problemas de autenticação\n";
    exit(1);
}

echo "\n=== EXECUTANDO SCRIPT SQL ===\n";

// Ler e executar o script SQL
$sqlFile = 'scripts/sql/02_multi_tenant_schema.sql';

if (!file_exists($sqlFile)) {
    echo "ERRO: Arquivo SQL não encontrado: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);

// Dividir em statements (separados por ;)
$statements = explode(';', $sql);
$executados = 0;
$erros = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue; // Pular comentários e linhas vazias
    }
    
    try {
        $pdo->exec($statement);
        $executados++;
        
        // Mostrar apenas os statements importantes
        if (strpos($statement, 'CREATE TABLE') !== false) {
            preg_match('/CREATE TABLE\s+(\w+)/', $statement, $matches);
            $tableName = $matches[1] ?? 'desconhecida';
            echo "✓ Tabela '$tableName' criada\n";
        } elseif (strpos($statement, 'INSERT INTO') !== false) {
            preg_match('/INSERT INTO\s+(\w+)/', $statement, $matches);
            $tableName = $matches[1] ?? 'desconhecida';
            echo "✓ Dados inseridos em '$tableName'\n";
        }
        
    } catch (Exception $e) {
        $erros++;
        
        // Ignorar erros de "tabela já existe"
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "✗ Erro: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== RESULTADO ===\n";
echo "Statements executados: $executados\n";
echo "Erros encontrados: $erros\n";

// Verificar se as tabelas foram criadas
echo "\n=== VERIFICANDO INSTALAÇÃO ===\n";

$tabelas = ['admin_global', 'empresas', 'usuarios', 'campanhas', 'contatos', 'ramais', 'filas', 'chamadas', 'billing'];

foreach ($tabelas as $tabela) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tabela]);
        
        if ($stmt->rowCount() > 0) {
            echo "✓ Tabela '$tabela' OK\n";
        } else {
            echo "✗ Tabela '$tabela' NÃO ENCONTRADA\n";
        }
    } catch (Exception $e) {
        echo "✗ Erro ao verificar '$tabela': " . $e->getMessage() . "\n";
    }
}

// Verificar admin global
echo "\n=== VERIFICANDO ADMIN GLOBAL ===\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_global WHERE email = 'admin@discador.com'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Admin global criado: admin@discador.com\n";
        echo "✓ Senha padrão: password\n";
    } else {
        echo "✗ Admin global não encontrado\n";
    }
} catch (Exception $e) {
    echo "✗ Erro ao verificar admin: " . $e->getMessage() . "\n";
}

echo "\n============================================\n";
echo "INSTALAÇÃO MULTI-TENANT CONCLUÍDA!\n";
echo "============================================\n";
echo "\nPRÓXIMOS PASSOS:\n";
echo "1. Acesse: http://localhost/discador_v2/src/login.php?type=admin\n";
echo "2. Login Admin Global:\n";
echo "   Email: admin@discador.com\n";
echo "   Senha: password\n";
echo "3. Gerencie empresas em: /admin-companies.php\n";
echo "4. Cadastre nova empresa em: /register-company.php\n";
echo "\n";
?>
