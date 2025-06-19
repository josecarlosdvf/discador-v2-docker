<?php
session_start();

require_once __DIR__ . '/Core/MultiTenantAuth.php';
require_once __DIR__ . '/Core/TenantManager.php';
require_once __DIR__ . '/Core/CampaignManager.php';

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
    } else {
        header('Location: /admin-dashboard.php');
        exit;
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

$campaignManager = new \DiscadorV2\Core\CampaignManager();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $campaignManager->createCampaign($empresaId, $_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                
                if ($result['success']) {
                    header('Location: /campaigns.php?id=' . $result['campaign_id']);
                    exit;
                }
                break;
                
            case 'update':
                // Implementar atualização de campanha
                break;
                
            case 'delete':
                // Implementar exclusão de campanha
                break;
        }
    }
}

// Verificar se está editando uma campanha específica
$editingCampaign = null;
if (isset($_GET['id'])) {
    $editingCampaign = $campaignManager->getCampaignById($_GET['id']);
    if (!$editingCampaign || $editingCampaign['empresa_id'] != $empresaId) {
        header('Location: /campaigns.php');
        exit;
    }
}

// Buscar campanhas da empresa
$campanhas = $campaignManager->getCampaignsByCompany($empresaId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas - <?= htmlspecialchars($currentTenant['nome']) ?></title>
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
        
        .campaign-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .campaign-ativa {
            border-left-color: #28a745;
        }
        
        .campaign-pausada {
            border-left-color: #ffc107;
        }
        
        .campaign-parada {
            border-left-color: #dc3545;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .btn-add-campaign {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: white;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="fas fa-bullhorn"></i> 
                Campanhas - <?= htmlspecialchars($currentTenant['nome']) ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($currentUser['nome']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="/users.php">
                            <i class="fas fa-users"></i> Usuários
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
                <h2><i class="fas fa-bullhorn"></i> Gestão de Campanhas</h2>
                <p class="text-muted">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?>
                    <?php if ($auth->isGlobalAdmin()): ?>
                        <span class="badge bg-danger ms-2">Admin Global</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-auto">
                <a href="/dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                </a>
                <button class="btn btn-add-campaign" data-bs-toggle="modal" data-bs-target="#addCampaignModal">
                    <i class="fas fa-plus"></i> Nova Campanha
                </button>
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

        <!-- Lista de Campanhas -->
        <div class="row">
            <?php if (empty($campanhas)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                            <h4>Nenhuma campanha cadastrada</h4>
                            <p class="text-muted">Comece criando sua primeira campanha de discagem.</p>
                            <button class="btn btn-add-campaign" data-bs-toggle="modal" data-bs-target="#addCampaignModal">
                                <i class="fas fa-plus"></i> Criar Primeira Campanha
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($campanhas as $campanha): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card campaign-card campaign-<?= $campanha['status'] ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($campanha['nome']) ?></h5>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($campanha['descricao']) ?></p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="/campaigns.php?id=<?= $campanha['id'] ?>">
                                                <i class="fas fa-edit"></i> Editar
                                            </a></li>
                                            <li><a class="dropdown-item" href="/lists.php?campaign_id=<?= $campanha['id'] ?>">
                                                <i class="fas fa-list"></i> Listas
                                            </a></li>
                                            <li><a class="dropdown-item" href="/reports.php?campaign_id=<?= $campanha['id'] ?>">
                                                <i class="fas fa-chart-bar"></i> Relatórios
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteCampaign(<?= $campanha['id'] ?>, '<?= htmlspecialchars($campanha['nome']) ?>')">
                                                <i class="fas fa-trash"></i> Excluir
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div class="mb-3">
                                    <span class="badge status-badge bg-<?= $campanha['status'] === 'ativa' ? 'success' : ($campanha['status'] === 'pausada' ? 'warning' : 'danger') ?>">
                                        <i class="fas fa-<?= $campanha['status'] === 'ativa' ? 'play' : ($campanha['status'] === 'pausada' ? 'pause' : 'stop') ?>"></i>
                                        <?= ucfirst($campanha['status']) ?>
                                    </span>
                                </div>
                                
                                <!-- Estatísticas -->
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary"><?= $campanha['total_contatos'] ?? 0 ?></div>
                                        <small class="text-muted">Contatos</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success"><?= $campanha['ligacoes_hoje'] ?? 0 ?></div>
                                        <small class="text-muted">Hoje</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-info"><?= number_format($campanha['progresso'] ?? 0, 1) ?>%</div>
                                        <small class="text-muted">Progresso</small>
                                    </div>
                                </div>
                                
                                <!-- Progresso -->
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?= $campanha['progresso'] ?? 0 ?>%"></div>
                                </div>
                                
                                <!-- Datas -->
                                <div class="small text-muted">
                                    <div><i class="fas fa-calendar"></i> Criada em <?= date('d/m/Y', strtotime($campanha['criado_em'])) ?></div>
                                    <?php if ($campanha['iniciado_em']): ?>
                                        <div><i class="fas fa-play"></i> Iniciada em <?= date('d/m/Y H:i', strtotime($campanha['iniciado_em'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Ações -->
                                <div class="mt-3 d-flex gap-2">
                                    <a href="/campaigns.php?id=<?= $campanha['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-cog"></i> Configurar
                                    </a>
                                    <a href="/lists.php?campaign_id=<?= $campanha['id'] ?>" class="btn btn-info btn-sm flex-fill">
                                        <i class="fas fa-list"></i> Listas
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Nova Campanha -->
    <div class="modal fade" id="addCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-bullhorn"></i> Nova Campanha
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome da Campanha <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: Campanha Vendas Janeiro">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição <span class="text-danger">*</span></label>
                            <textarea name="descricao" class="form-control" rows="3" required placeholder="Descreva o objetivo desta campanha..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Próximos passos:</strong> Após criar a campanha, você poderá:
                            <ul class="mb-0 mt-2">
                                <li>Fazer upload de listas de contatos</li>
                                <li>Configurar parâmetros de discagem</li>
                                <li>Definir scripts e qualificações</li>
                                <li>Iniciar a campanha</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Criar Campanha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function deleteCampaign(campaignId, campaignName) {
            if (confirm(`Tem certeza que deseja excluir a campanha "${campaignName}"? Esta ação não pode ser desfeita e todos os dados relacionados serão perdidos.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="campaign_id" value="${campaignId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
