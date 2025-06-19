<?php
session_start();

// Se já está logado, redireciona
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/Core/CompanyRegistration.php';
    
    $registration = new \DiscadorV2\Core\CompanyRegistration();
    $result = $registration->registerCompany($_POST);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empresa - Discador V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            max-width: 600px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 30px;
            text-align: center;
        }
        
        .register-body {
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
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .text-muted {
            font-size: 0.9em;
        }
        
        .required {
            color: #dc3545;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            color: #6c757d;
            font-weight: bold;
            font-size: 14px;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            margin-top: 14px;
        }
        
        .step-line.completed {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h2><i class="fas fa-building"></i> Cadastro de Empresa</h2>
                <p class="mb-0">Registre sua empresa e comece a usar o Discador V2</p>
            </div>
            
            <div class="register-body">
                <!-- Indicador de Passos -->
                <div class="step-indicator">
                    <div class="step active">1</div>
                    <div class="step-line"></div>
                    <div class="step">2</div>
                    <div class="step-line"></div>
                    <div class="step">3</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <hr>
                        <p class="mb-0">
                            <strong>Próximos passos:</strong><br>
                            1. Aguarde a aprovação do administrador<br>
                            2. Você receberá um email com as instruções de acesso<br>
                            3. Após aprovação, poderá acessar o sistema
                        </p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="registerForm">
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-building"></i> Dados da Empresa
                                </h5>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="form-label">Nome Fantasia <span class="required">*</span></label>
                                    <input type="text" name="nome" class="form-control" required 
                                           placeholder="Ex: Minha Empresa LTDA" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">CNPJ <span class="required">*</span></label>
                                    <input type="text" name="cnpj" class="form-control" required 
                                           placeholder="00.000.000/0001-00" value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>"
                                           id="cnpj">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Razão Social <span class="required">*</span></label>
                            <input type="text" name="razao_social" class="form-control" required 
                                   placeholder="Ex: Minha Empresa Comunicações LTDA" 
                                   value="<?= htmlspecialchars($_POST['razao_social'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email da Empresa <span class="required">*</span></label>
                                    <input type="email" name="email" class="form-control" required 
                                           placeholder="contato@minhaempresa.com" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    <div class="text-muted">Este será o email principal da empresa</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone <span class="required">*</span></label>
                                    <input type="text" name="telefone" class="form-control" required 
                                           placeholder="(11) 99999-9999" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>"
                                           id="telefone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3 mt-4">
                                    <i class="fas fa-user-tie"></i> Administrador Principal
                                </h5>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Nome Completo <span class="required">*</span></label>
                                    <input type="text" name="admin_nome" class="form-control" required 
                                           placeholder="Ex: João Silva" value="<?= htmlspecialchars($_POST['admin_nome'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email do Admin <span class="required">*</span></label>
                                    <input type="email" name="admin_email" class="form-control" required 
                                           placeholder="joao@minhaempresa.com" 
                                           value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Senha <span class="required">*</span></label>
                                    <input type="password" name="admin_senha" class="form-control" required 
                                           placeholder="Mínimo 8 caracteres" minlength="8">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Confirmar Senha <span class="required">*</span></label>
                                    <input type="password" name="admin_senha_confirm" class="form-control" required 
                                           placeholder="Confirme a senha" minlength="8">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="text-primary mb-3 mt-4">
                                    <i class="fas fa-cog"></i> Configurações Iniciais
                                </h5>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Subdomínio Desejado</label>
                                    <div class="input-group">
                                        <input type="text" name="subdomain" class="form-control" 
                                               placeholder="minhaempresa" value="<?= htmlspecialchars($_POST['subdomain'] ?? '') ?>"
                                               pattern="[a-z0-9-]+" title="Apenas letras minúsculas, números e hífens">
                                        <span class="input-group-text">.discador.com</span>
                                    </div>
                                    <div class="text-muted">Deixe em branco para gerar automaticamente</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Plano Desejado</label>
                                    <select name="plano" class="form-control">
                                        <option value="basico" <?= ($_POST['plano'] ?? '') === 'basico' ? 'selected' : '' ?>>
                                            Básico - Até 10 usuários (R$ 99/mês)
                                        </option>
                                        <option value="profissional" <?= ($_POST['plano'] ?? '') === 'profissional' ? 'selected' : '' ?>>
                                            Profissional - Até 50 usuários (R$ 299/mês)
                                        </option>
                                        <option value="empresarial" <?= ($_POST['plano'] ?? '') === 'empresarial' ? 'selected' : '' ?>>
                                            Empresarial - Usuários ilimitados (R$ 599/mês)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="aceita_termos" required id="aceita_termos">
                                <label class="form-check-label" for="aceita_termos">
                                    Li e aceito os <a href="#" target="_blank">Termos de Uso</a> e a 
                                    <a href="#" target="_blank">Política de Privacidade</a> <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-register">
                                <i class="fas fa-paper-plane"></i> Solicitar Cadastro
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Já possui uma conta? <a href="/login.php">Fazer login</a>
                            </small>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Máscaras de input
            $('#cnpj').mask('00.000.000/0000-00');
            $('#telefone').mask('(00) 00000-0000');
            
            // Validação em tempo real
            $('#registerForm').on('submit', function(e) {
                const senha = $('input[name="admin_senha"]').val();
                const senhaConfirm = $('input[name="admin_senha_confirm"]').val();
                
                if (senha !== senhaConfirm) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                    return false;
                }
                
                // Validação de CNPJ básica
                const cnpj = $('#cnpj').val().replace(/\D/g, '');
                if (cnpj.length !== 14) {
                    e.preventDefault();
                    alert('CNPJ deve ter 14 dígitos!');
                    return false;
                }
            });
            
            // Auto-geração de subdomínio baseado no nome
            $('input[name="nome"]').on('blur', function() {
                const subdomain = $('input[name="subdomain"]');
                if (!subdomain.val()) {
                    const nome = $(this).val();
                    const subdomainSuggestion = nome
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '')
                        .substring(0, 20);
                    
                    subdomain.val(subdomainSuggestion);
                }
            });
        });
    </script>
</body>
</html>
