<?php
session_start();

require_once __DIR__ . '/Core/MultiTenantAuth.php';
$auth = new \DiscadorV2\Core\MultiTenantAuth();

// Verificar se é admin global
if (!$auth->isLoggedIn() || !$auth->isGlobalAdmin()) {
    header('Location: /login.php?type=admin');
    exit;
}

require_once __DIR__ . '/Core/CompanyRegistration.php';
$registration = new \DiscadorV2\Core\CompanyRegistration();

// Buscar estatísticas
$empresasPendentes = $registration->getPendingCompanies();
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Discador V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 15px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        
        .stats-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .stats-card-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .stats-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .stats-card-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .main-content {
            padding: 20px;
        }
        
        .quick-action {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            min-width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin-dashboard.php">
                <i class="fas fa-crown"></i> Admin Geral - Discador V2
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['nome']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Perfil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Configurações</a></li>
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
                        <a class="nav-link active" href="/admin-dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link position-relative" href="/admin-companies.php">
                            <i class="fas fa-building"></i> Empresas
                            <?php if (count($empresasPendentes) > 0): ?>
                                <span class="notification-badge"><?= count($empresasPendentes) ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="/admin-users.php">
                            <i class="fas fa-users"></i> Usuários
                        </a>
                        <a class="nav-link" href="/admin-billing.php">
                            <i class="fas fa-file-invoice-dollar"></i> Billing
                        </a>
                        <a class="nav-link" href="/admin-system.php">
                            <i class="fas fa-server"></i> Sistema
                        </a>
                        <a class="nav-link" href="/admin-logs.php">
                            <i class="fas fa-list-alt"></i> Logs
                        </a>
                        <a class="nav-link" href="/admin-reports.php">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                        <hr>
                        <a class="nav-link" href="/monitor-dashboard.php">
                            <i class="fas fa-eye"></i> Monitor Tempo Real
                        </a>
                        <a class="nav-link" href="/">
                            <i class="fas fa-external-link-alt"></i> Site Principal
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col">
                            <h2><i class="fas fa-tachometer-alt"></i> Dashboard Administrativo</h2>
                            <p class="text-muted">Visão geral do sistema multi-tenant</p>
                        </div>
                        <div class="col-auto">
                            <div class="btn-group">
                                <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                                    <i class="fas fa-sync"></i> Atualizar
                                </button>
                                <button class="btn btn-primary" onclick="exportReport()">
                                    <i class="fas fa-download"></i> Exportar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-building fa-2x mb-2"></i>
                                    <h4>15</h4>
                                    <p class="mb-0">Empresas Ativas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h4><?= count($empresasPendentes) ?></h4>
                                    <p class="mb-0">Pendentes Aprovação</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h4>456</h4>
                                    <p class="mb-0">Usuários Ativos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-phone fa-2x mb-2"></i>
                                    <h4>1,234</h4>
                                    <p class="mb-0">Chamadas Hoje</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-companies.php'">
                                <i class="fas fa-plus-circle text-primary"></i>
                                <div>Nova Empresa</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-companies.php'">
                                <i class="fas fa-check-circle text-success"></i>
                                <div>Aprovar Empresas</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-billing.php'">
                                <i class="fas fa-file-invoice text-warning"></i>
                                <div>Billing</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-system.php'">
                                <i class="fas fa-server text-info"></i>
                                <div>Sistema</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-logs.php'">
                                <i class="fas fa-list-alt text-secondary"></i>
                                <div>Logs</div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 mb-3">
                            <div class="quick-action" onclick="location.href='/admin-reports.php'">
                                <i class="fas fa-chart-line text-primary"></i>
                                <div>Relatórios</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Empresas Pendentes -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-clock"></i> Empresas Pendentes de Aprovação
                                    </h5>
                                    <a href="/admin-companies.php" class="btn btn-sm btn-light">
                                        Ver Todas
                                    </a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($empresasPendentes)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                                            <p>Nenhuma empresa pendente no momento!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach (array_slice($empresasPendentes, 0, 5) as $empresa): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?= htmlspecialchars($empresa['nome']) ?></strong><br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($empresa['email']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="activity-time">
                                                            <?= date('d/m/Y H:i', strtotime($empresa['criado_em'])) ?>
                                                        </div>
                                                        <span class="badge bg-warning">Pendente</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Atividade Recente -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-clock"></i> Atividade Recente
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-user-plus text-success"></i>
                                            </div>
                                            <div>
                                                <div>Novo usuário cadastrado</div>
                                                <div class="activity-time">Há 2 minutos</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-building text-primary"></i>
                                            </div>
                                            <div>
                                                <div>Empresa aprovada</div>
                                                <div class="activity-time">Há 15 minutos</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-phone text-info"></i>
                                            </div>
                                            <div>
                                                <div>Campanha iniciada</div>
                                                <div class="activity-time">Há 1 hora</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-file-invoice text-warning"></i>
                                            </div>
                                            <div>
                                                <div>Fatura gerada</div>
                                                <div class="activity-time">Há 2 horas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line"></i> Crescimento de Empresas
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="companiesChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie"></i> Distribuição por Plano
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="plansChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Função para atualizar dashboard
        function refreshDashboard() {
            location.reload();
        }
        
        // Função para exportar relatório
        function exportReport() {
            alert('Funcionalidade em desenvolvimento');
        }
        
        // Gráfico de crescimento de empresas
        const companiesCtx = document.getElementById('companiesChart').getContext('2d');
        new Chart(companiesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Empresas Cadastradas',
                    data: [2, 5, 8, 12, 15, 18],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico de distribuição por plano
        const plansCtx = document.getElementById('plansChart').getContext('2d');
        new Chart(plansCtx, {
            type: 'doughnut',
            data: {
                labels: ['Básico', 'Profissional', 'Empresarial'],
                datasets: [{
                    data: [8, 5, 2],
                    backgroundColor: ['#667eea', '#764ba2', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Auto-refresh a cada 5 minutos
        setInterval(function() {
            fetch('/api/dashboard-stats.php')
                .then(response => response.json())
                .then(data => {
                    // Atualizar estatísticas sem reload completo
                    console.log('Stats atualizadas:', data);
                })
                .catch(error => console.error('Erro ao atualizar stats:', error));
        }, 300000); // 5 minutos
    </script>
</body>
</html>
