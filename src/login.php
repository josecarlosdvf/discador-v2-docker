<?php
session_start();

// Se já está logado, redireciona
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_global_admin']) && $_SESSION['is_global_admin']) {
        header('Location: /admin-dashboard.php');
    } else {
        header('Location: /');
    }
    exit;
}

require_once __DIR__ . '/Core/MultiTenantAuth.php';
require_once __DIR__ . '/Core/TenantManager.php';

$auth = new \DiscadorV2\Core\MultiTenantAuth();
$tenantManager = \DiscadorV2\Core\TenantManager::getInstance();

$error = '';
$loginType = $_GET['type'] ?? 'company'; // company ou admin

// Detectar tenant atual (se aplicável)
$currentTenant = null;
if ($loginType === 'company') {
    $currentTenant = $tenantManager->detectTenant();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($email) || empty($password)) {
        $error = 'Email e senha são obrigatórios';
    } else {
        try {
            if ($loginType === 'admin') {
                // Login admin global
                $result = $auth->loginGlobalAdmin($email, $password);
            } else {
                // Login empresa
                $tenantId = $currentTenant['id'] ?? null;
                $result = $auth->login($email, $password, $tenantId);
            }
            
            if ($result['success']) {
                // Login realizado com sucesso
                if ($rememberMe) {
                    // Implementar "lembrar-me" se necessário
                }
                
                // Redirecionar baseado no tipo de usuário
                if (isset($result['is_global_admin']) && $result['is_global_admin']) {
                    header('Location: /admin-dashboard.php');
                } else {
                    header('Location: /dashboard.php');
                }
                exit;
            } else {
                $error = $result['message'] ?? 'Erro no login';
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $loginType === 'admin' ? 'Admin Login' : 'Login' ?> - Discador V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .admin-focus:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-admin-login {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-admin-login:hover {
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .tenant-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .login-tabs {
            display: flex;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .login-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            background: #e9ecef;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .login-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .login-tab.admin.active {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header <?= $loginType === 'admin' ? 'admin-header' : '' ?>">
                <?php if ($loginType === 'admin'): ?>
                    <h2><i class="fas fa-crown"></i> Admin Login</h2>
                    <p class="mb-0">Acesso de Administrador Geral</p>
                <?php else: ?>
                    <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                    <p class="mb-0">Acesse o Discador V2</p>
                <?php endif; ?>
            </div>
            
            <div class="login-body">
                <!-- Abas de Login -->
                <div class="login-tabs">
                    <a href="/login.php?type=company" class="login-tab <?= $loginType === 'company' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i> Empresa
                    </a>
                    <a href="/login.php?type=admin" class="login-tab admin <?= $loginType === 'admin' ? 'active' : '' ?>">
                        <i class="fas fa-crown"></i> Admin Geral
                    </a>
                </div>
                
                <!-- Informações do Tenant (apenas para login de empresa) -->
                <?php if ($loginType === 'company' && $currentTenant): ?>
                    <div class="tenant-info">
                        <strong><i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?></strong><br>
                        <small class="text-muted">
                            <?= htmlspecialchars($currentTenant['subdomain']) ?>.discador.com
                        </small>
                    </div>
                <?php elseif ($loginType === 'company' && !$currentTenant): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Empresa não identificada</strong><br>
                        Certifique-se de acessar através do subdomínio correto da sua empresa.
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" name="email" class="form-control <?= $loginType === 'admin' ? 'admin-focus' : '' ?>" 
                               required placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Senha
                        </label>
                        <div class="password-toggle">
                            <input type="password" name="password" id="password" 
                                   class="form-control <?= $loginType === 'admin' ? 'admin-focus' : '' ?>" 
                                   required placeholder="Sua senha">
                            <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Lembrar-me
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-login <?= $loginType === 'admin' ? 'btn-admin-login' : '' ?>">
                            <i class="fas fa-sign-in-alt"></i> 
                            <?= $loginType === 'admin' ? 'Entrar como Admin' : 'Entrar' ?>
                        </button>
                    </div>
                </form>
                
                <?php if ($loginType === 'company'): ?>
                    <div class="text-center mt-4">
                        <div class="small text-muted mb-2">
                            <a href="#" class="text-decoration-none">Esqueceu sua senha?</a>
                        </div>
                        <div class="small text-muted">
                            Empresa nova? <a href="/register-company.php" class="text-decoration-none">Cadastre-se aqui</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <div class="small text-muted">
                            <i class="fas fa-shield-alt"></i> Acesso restrito para administradores
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Informações de Demo (Remover em produção) -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="text-primary">
                        <i class="fas fa-info-circle"></i> Dados de Teste
                    </h6>
                    <?php if ($loginType === 'admin'): ?>
                        <small>
                            <strong>Admin Global:</strong><br>
                            Email: admin@discador.com<br>
                            Senha: password
                        </small>
                    <?php else: ?>
                        <small>
                            <strong>Empresa Demo:</strong><br>
                            Acesse: demo.discador.com<br>
                            Email: admin@demo.com<br>
                            Senha: demo123
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('password-eye');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        }
        
        // Auto-focus no campo email
        document.querySelector('input[name="email"]').focus();
    </script>
</body>
</html>
