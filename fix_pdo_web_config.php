<?php
/**
 * Correção de Configuração PDO para Ambiente Web
 */

echo "🔧 CORRIGINDO CONFIGURAÇÃO PDO PARA WEB\n\n";

// Criar arquivo de configuração PDO simplificado para web
$pdo_web_config = '<?php
/**
 * Configuração PDO Simplificada para Web
 */

// Configuração Docker para web
$host = "database";
$port = "3306"; 
$dbname = "discador";
$username = "root";
$password = "root123";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $pdo->exec("SET time_zone = \'-03:00\'");
    $GLOBALS[\'pdo\'] = $pdo;
    
} catch (PDOException $e) {
    error_log("PDO Web Error: " . $e->getMessage());
    $GLOBALS[\'pdo\'] = null;
}
?>';

// Salvar configuração web
file_put_contents('pdo_web.php', $pdo_web_config);

// Copiar para container
$cmd = "docker cp pdo_web.php discador_nginx:/var/www/html/config/pdo_web.php";
shell_exec($cmd);

echo "✅ Configuração PDO web criada\n";

// Criar versão simplificada do login.php
$login_simple = '<?php
session_start();

// Configuração PDO para web
require_once "/var/www/html/config/pdo_web.php";

if (!$GLOBALS["pdo"]) {
    die("Erro de conexão com banco de dados");
}

$error = "";

if ($_POST) {
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";
    
    if ($email && $password) {
        // Verificar admin global
        $stmt = $GLOBALS["pdo"]->prepare("SELECT * FROM admin_global WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin["senha"])) {
            $_SESSION["user_id"] = $admin["id"];
            $_SESSION["user_name"] = $admin["nome"];
            $_SESSION["is_global_admin"] = true;
            header("Location: /admin-dashboard.php");
            exit;
        }
        
        // Verificar usuário de empresa
        $stmt = $GLOBALS["pdo"]->prepare("SELECT u.*, e.nome as empresa_nome 
                                        FROM usuarios u 
                                        JOIN empresas e ON u.empresa_id = e.id 
                                        WHERE u.login = ? AND u.ativo = 1 AND e.status = \"ativo\"");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["senha"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["nome"];
            $_SESSION["empresa_id"] = $user["empresa_id"];
            $_SESSION["empresa_nome"] = $user["empresa_nome"];
            $_SESSION["is_global_admin"] = false;
            header("Location: /dashboard.php");
            exit;
        }
        
        $error = "Email ou senha incorretos";
    } else {
        $error = "Preencha todos os campos";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Discador V2</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .logo { text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { flex: 1; padding: 10px; text-align: center; background: #f8f9fa; cursor: pointer; border: 1px solid #ddd; }
        .tab.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h2>🎯 Discador V2.0</h2>
            <p>Sistema de Discagem Automática</p>
        </div>
        
        <div class="tabs">
            <div class="tab active">Login</div>
        </div>
        
        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST[\"email\"] ?? \"\") ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Entrar</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <p><strong>Credenciais de Teste:</strong></p>
            <p>Admin: admin@discador.com / admin123</p>
            <p>Empresa: master@empresa.com / master123</p>
        </div>
    </div>
</body>
</html>';

// Salvar login simplificado
file_put_contents('login_simple.php', $login_simple);

// Copiar para container
$cmd = "docker cp login_simple.php discador_nginx:/var/www/html/login_simple.php";
shell_exec($cmd);

echo "✅ Login simplificado criado: /login_simple.php\n";

// Testar login simplificado
$context = stream_context_create(["http" => ["timeout" => 10]]);
$response = @file_get_contents("http://localhost:8080/login_simple.php", false, $context);

if ($response !== false) {
    echo "✅ LOGIN SIMPLIFICADO: Funcionando (" . strlen($response) . " bytes)\n";
} else {
    echo "❌ LOGIN SIMPLIFICADO: Ainda com problema\n";
}

// Limpeza
unlink("pdo_web.php");
unlink("login_simple.php");

echo "\n🎯 CORREÇÃO CONCLUÍDA\n";
echo "🌐 Teste: http://localhost:8080/login_simple.php\n";
?>
