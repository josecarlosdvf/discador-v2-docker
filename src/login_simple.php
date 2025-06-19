<?php
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
</html>