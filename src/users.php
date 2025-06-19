<?php
session_start();

require_once __DIR__ . '/Core/MultiTenantAuth.php';
require_once __DIR__ . '/Core/TenantManager.php';

$auth = new \DiscadorV2\Core\MultiTenantAuth();
$tenantManager = \DiscadorV2\Core\TenantManager::getInstance();

// Verificar se está logado
if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$currentTenant = null;

// Se for admin global, pode gerenciar qualquer empresa
if ($auth->isGlobalAdmin()) {
    $empresaId = $_GET['empresa_id'] ?? null;
    if ($empresaId) {
        $currentTenant = $tenantManager->loadTenant($empresaId);
    }
} else {
    // Usuário normal só gerencia sua própria empresa
    $currentTenant = $tenantManager->getCurrentTenant();
    $empresaId = $currentTenant['id'];
}

if (!$currentTenant) {
    header('Location: /admin-dashboard.php');
    exit;
}

require_once __DIR__ . '/Core/UserManager.php';
$userManager = new \DiscadorV2\Core\UserManager();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $userManager->createUser($empresaId, $_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'update':
                $result = $userManager->updateUser($_POST['user_id'], $_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'delete':
                $result = $userManager->deleteUser($_POST['user_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'toggle_status':
                $result = $userManager->toggleUserStatus($_POST['user_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Buscar usuários da empresa
$usuarios = $userManager->getUsersByCompany($empresaId);
$campanhas = $userManager->getCampaignsByCompany($empresaId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - <?= htmlspecialchars($currentTenant['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .user-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .badge-nivel {
            font-size: 0.75rem;
        }
        
        .badge-master {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .badge-supervisor {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #000;
        }
        
        .badge-operador {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .quick-stats {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .btn-add-user {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            font-weight: 600;
        }
        
        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-ativo {
            background-color: #28a745;
        }
        
        .status-inativo {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= $auth->isGlobalAdmin() ? '/admin-dashboard.php' : '/dashboard.php' ?>">
                <i class="fas fa-users"></i> 
                <?= $auth->isGlobalAdmin() ? 'Admin Geral' : htmlspecialchars($currentTenant['nome']) ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($currentUser['nome']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $auth->isGlobalAdmin() ? '/admin-dashboard.php' : '/dashboard.php' ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-users"></i> Gestão de Usuários</h2>
                <p class="text-muted">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?>
                    <?php if ($auth->isGlobalAdmin()): ?>
                        <span class="badge bg-danger ms-2">Admin Global</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-auto">
                <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Novo Usuário
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="row text-center">
                <div class="col-md-3">
                    <h4><?= count($usuarios) ?></h4>
                    <p class="mb-0">Total de Usuários</p>
                </div>
                <div class="col-md-3">
                    <h4><?= count(array_filter($usuarios, fn($u) => $u['ativo'])) ?></h4>
                    <p class="mb-0">Usuários Ativos</p>
                </div>                <div class="col-md-3">
                    <h4><?= count(array_filter($usuarios, fn($u) => $u['tipo'] === 'master')) ?></h4>
                    <p class="mb-0">Administradores</p>
                </div>
                <div class="col-md-3">
                    <h4><?= count($campanhas) ?></h4>
                    <p class="mb-0">Campanhas Ativas</p>
                </div>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Lista de Usuários -->
        <div class="row">
            <?php if (empty($usuarios)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users fa-4x text-muted mb-3"></i>
                            <h4>Nenhum usuário cadastrado</h4>
                            <p class="text-muted">Comece adicionando o primeiro usuário da empresa.</p>
                            <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus"></i> Adicionar Primeiro Usuário
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card user-card" onclick="editUser(<?= $usuario['id'] ?>)">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="user-avatar me-3">
                                        <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($usuario['email']) ?>
                                        </p>
                                    </div>
                                    <div class="dropdown" onclick="event.stopPropagation()">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editUser(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="toggleUserStatus(<?= $usuario['id'] ?>, <?= $usuario['ativo'] ? 'false' : 'true' ?>)">
                                                <i class="fas fa-<?= $usuario['ativo'] ? 'ban' : 'check' ?>"></i> 
                                                <?= $usuario['ativo'] ? 'Desativar' : 'Ativar' ?>
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>')">
                                                <i class="fas fa-trash"></i> Excluir
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                  <div class="mb-3">
                                    <span class="badge badge-nivel badge-<?= $usuario['tipo'] ?>">
                                        <i class="fas fa-<?= $usuario['tipo'] === 'master' ? 'crown' : ($usuario['tipo'] === 'supervisor' ? 'user-tie' : 'user') ?>"></i>
                                        <?= ucfirst($usuario['tipo']) ?>
                                    </span>
                                    
                                    <span class="status-indicator status-<?= $usuario['ativo'] ? 'ativo' : 'inativo' ?>"></span>
                                    <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </div>
                                
                                <div class="small text-muted">
                                    <div><i class="fas fa-calendar"></i> Criado em <?= date('d/m/Y', strtotime($usuario['criado_em'] ?? 'now')) ?></div>
                                    <?php if ($usuario['ultimo_login']): ?>
                                        <div><i class="fas fa-clock"></i> Último login: <?= date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) ?></div>
                                    <?php else: ?>
                                        <div><i class="fas fa-clock"></i> Nunca fez login</div>
                                    <?php endif; ?>
                                </div>
                                  <?php if (!empty($usuario['campanhas_permitidas'])): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">Campanhas vinculadas:</small>
                                        <div class="mt-1">
                                            <?php foreach ($campanhas as $campanha): ?>
                                                <?php if (in_array($campanha['id'], $usuario['campanhas_permitidas'])): ?>
                                                    <span class="badge bg-info me-1"><?= htmlspecialchars($campanha['nome']) ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar/Editar Usuário -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> <span id="modalTitle">Novo Usuário</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="userForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="user_id" id="userId" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" id="userName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="userEmail" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Senha <span class="text-danger">*</span></label>
                                    <input type="password" name="senha" id="userPassword" class="form-control" minlength="6">
                                    <div class="form-text">Deixe em branco para manter a senha atual (ao editar)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Senha</label>
                                    <input type="password" name="senha_confirm" id="userPasswordConfirm" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nível de Acesso <span class="text-danger">*</span></label>
                                    <select name="tipo" id="userLevel" class="form-control" required>
                                        <option value="operador">Operador - Acesso básico</option>
                                        <option value="supervisor">Supervisor - Gestão de campanhas</option>
                                        <option value="master">Master - Acesso total</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="ativo" id="userStatus" class="form-control">
                                        <option value="1">Ativo</option>
                                        <option value="0">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campanhas Vinculadas</label>
                            <div id="campaignsList">
                                <?php if (!empty($campanhas)): ?>                                    <?php foreach ($campanhas as $campanha): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="campanhas[]" 
                                                   value="<?= $campanha['id'] ?>" id="campanha_<?= $campanha['id'] ?>">
                                            <label class="form-check-label" for="campanha_<?= $campanha['id'] ?>">
                                                <?= htmlspecialchars($campanha['nome']) ?>
                                                <small class="text-muted">(<?= $campanha['ativo'] ? 'Ativa' : 'Inativa' ?>)</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">Nenhuma campanha disponível</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <span id="submitText">Criar Usuário</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editUser(userId) {
            // Aqui você faria uma requisição AJAX para buscar os dados do usuário
            // Por simplicidade, vou simular
            document.getElementById('modalTitle').textContent = 'Editar Usuário';
            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = userId;
            document.getElementById('submitText').textContent = 'Salvar Alterações';
            
            // Tornar senha opcional
            document.getElementById('userPassword').removeAttribute('required');
            
            new bootstrap.Modal(document.getElementById('addUserModal')).show();
        }
        
        function toggleUserStatus(userId, newStatus) {
            if (confirm('Tem certeza que deseja ' + (newStatus === 'true' ? 'ativar' : 'desativar') + ' este usuário?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteUser(userId, userName) {
            if (confirm(`Tem certeza que deseja excluir o usuário "${userName}"? Esta ação não pode ser desfeita.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Validação de senhas iguais
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const password = document.getElementById('userPassword').value;
            const passwordConfirm = document.getElementById('userPasswordConfirm').value;
            
            if (password && password !== passwordConfirm) {
                e.preventDefault();
                alert('As senhas não coincidem!');
            }
        });
        
        // Reset form quando modal fecha
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('userForm').reset();
            document.getElementById('modalTitle').textContent = 'Novo Usuário';
            document.getElementById('formAction').value = 'create';
            document.getElementById('userId').value = '';
            document.getElementById('submitText').textContent = 'Criar Usuário';
            document.getElementById('userPassword').setAttribute('required', 'required');
        });
    </script>
</body>
</html>
