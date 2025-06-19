<?php
session_start();

require_once 'Core/MultiTenantAuth.php';
require_once 'Core/TenantManager.php';
require_once 'Core/CampaignManager.php';

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
        // Redirecionar para admin dashboard se não especificou empresa
        header('Location: /admin-dashboard.php');
        exit;
    }
} else {
    // Usuário normal só vê sua própria empresa
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

// Processar ações do discador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_campaign':
                $result = $campaignManager->startCampaign($_POST['campaign_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'stop_campaign':
                $result = $campaignManager->stopCampaign($_POST['campaign_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'pause_campaign':
                $result = $campaignManager->pauseCampaign($_POST['campaign_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'resume_campaign':
                $result = $campaignManager->resumeCampaign($_POST['campaign_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Buscar dados para o dashboard
$campanhas = $campaignManager->getCampaignsByCompany($empresaId);
$stats = $campaignManager->getCampaignStats($empresaId);
$recentActivity = $campaignManager->getRecentActivity($empresaId, 10);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discador - <?= htmlspecialchars($currentTenant['nome']) ?></title>
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
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 0;
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #667eea;
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
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
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
        }
        
        .campaign-pausada {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
        }
        
        .campaign-parada {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .btn-control {
            border-radius: 20px;
            padding: 8px 20px;
            font-weight: 600;
            margin: 2px;
        }
        
        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
        }
        
        .btn-stop {
            background: linear-gradient(135deg, #dc3545 0%, #fd5e53 100%);
            border: none;
            color: white;
        }
        
        .btn-pause {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: #000;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        .status-ativa {
            background-color: #28a745;
        }
        
        .status-pausada {
            background-color: #ffc107;
        }
        
        .status-parada {
            background-color: #dc3545;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .activity-item {
            border-left: 3px solid #667eea;
            margin-bottom: 15px;
            padding-left: 15px;
        }
        
        .real-time-stats {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="fas fa-phone-alt"></i> 
                Discador - <?= htmlspecialchars($currentTenant['nome']) ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($currentUser['nome']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/users.php">
                            <i class="fas fa-users"></i> Usuários
                        </a></li>
                        <li><a class="dropdown-item" href="/campaigns.php">
                            <i class="fas fa-bullhorn"></i> Campanhas
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="/dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="/campaigns.php">
                            <i class="fas fa-bullhorn"></i> Campanhas
                        </a>
                        <a class="nav-link" href="/lists.php">
                            <i class="fas fa-list"></i> Listas de Contatos
                        </a>
                        <a class="nav-link" href="/users.php">
                            <i class="fas fa-users"></i> Usuários
                        </a>
                        <a class="nav-link" href="/reports.php">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                        <a class="nav-link" href="/cdr.php">
                            <i class="fas fa-phone"></i> CDR
                        </a>
                        <a class="nav-link" href="/settings.php">
                            <i class="fas fa-cog"></i> Configurações
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col">
                            <h2><i class="fas fa-phone-alt"></i> Controle do Discador</h2>
                            <p class="text-muted">
                                <i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?>
                                <?php if ($auth->isGlobalAdmin()): ?>
                                    <span class="badge bg-danger ms-2">Admin Global</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-auto">
                            <a href="/campaigns.php?action=new" class="btn btn-start">
                                <i class="fas fa-plus"></i> Nova Campanha
                            </a>
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

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-number"><?= $stats['total_campanhas'] ?? 0 ?></div>
                                    <div>Total de Campanhas</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-number"><?= $stats['campanhas_ativas'] ?? 0 ?></div>
                                    <div>Campanhas Ativas</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-number"><?= $stats['ligacoes_hoje'] ?? 0 ?></div>
                                    <div>Ligações Hoje</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-number"><?= $stats['contatos_ativos'] ?? 0 ?></div>
                                    <div>Contatos Ativos</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Campanhas -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-bullhorn"></i> Campanhas</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($campanhas)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                            <h5>Nenhuma campanha cadastrada</h5>
                                            <p class="text-muted">Comece criando sua primeira campanha de discagem.</p>
                                            <a href="/campaigns.php?action=new" class="btn btn-start">
                                                <i class="fas fa-plus"></i> Criar Primeira Campanha
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($campanhas as $campanha): ?>
                                            <div class="campaign-card campaign-<?= $campanha['status'] ?> p-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="status-indicator status-<?= $campanha['status'] ?>"></span>
                                                            <?= htmlspecialchars($campanha['nome']) ?>
                                                        </h6>
                                                        <p class="text-muted mb-1"><?= htmlspecialchars($campanha['descricao']) ?></p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-list"></i> <?= $campanha['total_contatos'] ?? 0 ?> contatos
                                                            <i class="fas fa-phone ms-3"></i> <?= $campanha['ligacoes_hoje'] ?? 0 ?> ligações hoje
                                                            <i class="fas fa-clock ms-3"></i> <?= ucfirst($campanha['status']) ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <?php if ($campanha['status'] === 'parada'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="start_campaign">
                                                                <input type="hidden" name="campaign_id" value="<?= $campanha['id'] ?>">
                                                                <button type="submit" class="btn btn-start btn-sm">
                                                                    <i class="fas fa-play"></i> Iniciar
                                                                </button>
                                                            </form>
                                                        <?php elseif ($campanha['status'] === 'ativa'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="pause_campaign">
                                                                <input type="hidden" name="campaign_id" value="<?= $campanha['id'] ?>">
                                                                <button type="submit" class="btn btn-pause btn-sm">
                                                                    <i class="fas fa-pause"></i> Pausar
                                                                </button>
                                                            </form>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="stop_campaign">
                                                                <input type="hidden" name="campaign_id" value="<?= $campanha['id'] ?>">
                                                                <button type="submit" class="btn btn-stop btn-sm">
                                                                    <i class="fas fa-stop"></i> Parar
                                                                </button>
                                                            </form>
                                                        <?php elseif ($campanha['status'] === 'pausada'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="resume_campaign">
                                                                <input type="hidden" name="campaign_id" value="<?= $campanha['id'] ?>">
                                                                <button type="submit" class="btn btn-start btn-sm">
                                                                    <i class="fas fa-play"></i> Retomar
                                                                </button>
                                                            </form>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="stop_campaign">
                                                                <input type="hidden" name="campaign_id" value="<?= $campanha['id'] ?>">
                                                                <button type="submit" class="btn btn-stop btn-sm">
                                                                    <i class="fas fa-stop"></i> Parar
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <a href="/campaigns.php?id=<?= $campanha['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-cog"></i> Configurar
                                                        </a>
                                                    </div>
                                                </div>
                                                
                                                <!-- Progresso da campanha -->
                                                <?php if (isset($campanha['progresso'])): ?>
                                                    <div class="mt-2">
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-success" style="width: <?= $campanha['progresso'] ?>%"></div>
                                                        </div>
                                                        <small class="text-muted"><?= $campanha['progresso'] ?>% concluído</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Stats em Tempo Real e Atividade Recente -->
                        <div class="col-lg-4">
                            <!-- Stats em Tempo Real -->
                            <div class="real-time-stats mb-4">
                                <h6><i class="fas fa-chart-line"></i> Estatísticas em Tempo Real</h6>
                                <div class="row text-center mt-3">
                                    <div class="col-6">
                                        <div class="h4" id="ligacoes-ativas">0</div>
                                        <small>Ligações Ativas</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4" id="operadores-online">0</div>
                                        <small>Operadores Online</small>
                                    </div>
                                </div>
                                <div class="row text-center mt-2">
                                    <div class="col-6">
                                        <div class="h4" id="taxa-sucesso">0%</div>
                                        <small>Taxa de Sucesso</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4" id="tempo-medio">0s</div>
                                        <small>Tempo Médio</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Atividade Recente -->
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="fas fa-clock"></i> Atividade Recente</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentActivity)): ?>
                                        <p class="text-muted text-center">Nenhuma atividade recente</p>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong><?= htmlspecialchars($activity['descricao']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($activity['campanha_nome']) ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?= date('H:i', strtotime($activity['created_at'])) ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Atualizar estatísticas em tempo real
        function updateRealTimeStats() {
            fetch('/api/real-time-stats.php?empresa_id=<?= $empresaId ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('ligacoes-ativas').textContent = data.stats.ligacoes_ativas || 0;
                        document.getElementById('operadores-online').textContent = data.stats.operadores_online || 0;
                        document.getElementById('taxa-sucesso').textContent = (data.stats.taxa_sucesso || 0) + '%';
                        document.getElementById('tempo-medio').textContent = (data.stats.tempo_medio || 0) + 's';
                    }
                })
                .catch(error => console.error('Erro ao atualizar stats:', error));
        }

        // Atualizar a cada 5 segundos
        setInterval(updateRealTimeStats, 5000);
        
        // Primeira atualização
        updateRealTimeStats();
        
        // Confirmação para ações críticas
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                
                if (action === 'stop_campaign') {
                    if (!confirm('Tem certeza que deseja parar esta campanha? As ligações em andamento serão finalizadas.')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>
