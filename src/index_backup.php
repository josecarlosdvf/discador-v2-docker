<?php
/**
 * Página Inicial - Sistema Discador v2.0
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';

// Verificar se precisa de autenticação
$auth->requireAuth();

// Se chegou aqui, o usuário está autenticado
$currentUser = $auth->getCurrentUser();

// Template variables
$pageTitle = 'Dashboard - Sistema Discador v2.0';
$pageDescription = 'Painel de Controle do Sistema Discador v2.0';

ob_start();
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-phone-alt me-2"></i>
            Discador v2.0
        </a>
        
        <!-- Mobile toggle -->
        <button class="navbar-toggler d-lg-none" type="button" onclick="toggleSidebar()">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Right menu -->
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger badge-sm">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notificações</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-phone text-primary me-2"></i>Nova chamada perdida</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user text-success me-2"></i>Novo usuário cadastrado</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Tronco offline</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="notifications.php">Ver todas</a></li>
                    </ul>
                </li>
                
                <!-- User menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($currentUser['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Minha Conta</h6></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Meu Perfil</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Configurações</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <?php if ($auth->hasPermission('ramais')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="ramais.php">
                            <i class="fas fa-phone me-2"></i>
                            Ramais
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('filas')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="filas.php">
                            <i class="fas fa-users me-2"></i>
                            Filas
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('troncos')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="troncos.php">
                            <i class="fas fa-network-wired me-2"></i>
                            Troncos
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('discador')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="discador.php">
                            <i class="fas fa-phone-volume me-2"></i>
                            Discador
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('agenda')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="agenda.php">
                            <i class="fas fa-address-book me-2"></i>
                            Agenda
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('relatorios')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="relatorios.php">
                            <i class="fas fa-chart-line me-2"></i>
                            Relatórios
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($auth->hasPermission('monitoramento')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="monitoramento.php">
                            <i class="fas fa-desktop me-2"></i>
                            Monitoramento
                        </a>
                    </li>
                    <?php endif; ?>
                      <?php if ($auth->hasPermission('admin')): ?>
                    <li class="nav-item">
                        <hr class="my-2">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Administração</span>
                        </h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="usuarios.php">
                            <i class="fas fa-users-cog me-2"></i>
                            Usuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sistema.php">
                            <i class="fas fa-server me-2"></i>
                            Sistema
                        </a>
                    </li>
                    <li class="nav-item">
                        <hr class="my-2">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                            <span>Discador v2.0</span>
                        </h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="toggleDiscadorPanel()">
                            <i class="fas fa-cogs me-2"></i>
                            Gerenciamento
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Content Header -->
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-0">Dashboard</h1>
                        <p class="text-muted mb-0">Visão geral do sistema</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Último acesso</small>
                        <small class="text-muted">
                            <?php echo $currentUser['last_login'] ? date('d/m/Y H:i', strtotime($currentUser['last_login'])) : 'Primeiro acesso'; ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0" id="total-calls">---</h3>
                                <p class="mb-0 opacity-75">Chamadas Hoje</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0 text-success" id="ramais-online">---</h3>
                                    <p class="mb-0 text-muted">Ramais Online</p>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-phone-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0 text-info" id="filas-ativas">---</h3>
                                    <p class="mb-0 text-muted">Filas Ativas</p>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0 text-warning" id="troncos-status">---</h3>
                                    <p class="mb-0 text-muted">Troncos OK</p>
                                </div>
                                <div class="text-warning">
                                    <i class="fas fa-network-wired fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Recent Activity -->
            <div class="row">
                <!-- Call Volume Chart -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Volume de Chamadas (24h)
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="callVolumeChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-server me-2"></i>
                                Status do Sistema
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>CPU</span>
                                    <span id="cpu-usage">---%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" id="cpu-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Memória</span>
                                    <span id="memory-usage">---%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" id="memory-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>Disco</span>
                                    <span id="disk-usage">---%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" id="disk-bar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-circle text-success"></i>
                                    <br>
                                    <small>Asterisk</small>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-circle text-success"></i>
                                    <br>
                                    <small>MySQL</small>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-circle text-success"></i>
                                    <br>
                                    <small>Redis</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
              <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Atividade Recente
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Hora</th>
                                            <th>Evento</th>
                                            <th>Usuário</th>
                                            <th>Detalhes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-activity">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                Carregando...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discador v2.0 Management Panel -->
            <?php if ($auth->hasPermission('admin')): ?>
            <div class="row mt-4" id="discador-panel" style="display: none;">
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs me-2"></i>
                                Gerenciamento do Discador v2.0
                                <button type="button" class="btn btn-sm btn-outline-light float-end" onclick="toggleDiscadorPanel()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Status do Sistema Discador -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card border-info">
                                        <div class="card-body text-center">
                                            <i class="fas fa-server fa-2x text-info mb-2"></i>
                                            <h6>Master Process</h6>
                                            <span class="badge" id="master-status">Verificando...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center">
                                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                                            <h6>Workers Ativos</h6>
                                            <span class="h4" id="workers-count">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-warning">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                                            <h6>Fila de Tarefas</h6>
                                            <span class="h4" id="queue-count">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-danger">
                                        <div class="card-body text-center">
                                            <i class="fas fa-database fa-2x text-danger mb-2"></i>
                                            <h6>Redis</h6>
                                            <span class="badge" id="redis-status">Verificando...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Controles -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-play-circle me-2"></i>Controle do Sistema</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-success" onclick="executeDiscadorCommand('start')">
                                                    <i class="fas fa-play me-2"></i>Iniciar Sistema
                                                </button>
                                                <button class="btn btn-warning" onclick="executeDiscadorCommand('restart')">
                                                    <i class="fas fa-redo me-2"></i>Reiniciar Sistema
                                                </button>
                                                <button class="btn btn-danger" onclick="executeDiscadorCommand('stop')">
                                                    <i class="fas fa-stop me-2"></i>Parar Sistema
                                                </button>
                                                <button class="btn btn-info" onclick="executeDiscadorCommand('status')">
                                                    <i class="fas fa-info-circle me-2"></i>Status Detalhado
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Monitoramento</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-primary" onclick="openMonitorDashboard()">
                                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard Completo
                                                </button>
                                                <button class="btn btn-secondary" onclick="executeDiscadorCommand('workers')">
                                                    <i class="fas fa-users me-2"></i>Status Workers
                                                </button>
                                                <button class="btn btn-secondary" onclick="executeDiscadorCommand('queue')">
                                                    <i class="fas fa-list me-2"></i>Status da Fila
                                                </button>
                                                <button class="btn btn-secondary" onclick="executeDiscadorCommand('logs')">
                                                    <i class="fas fa-file-alt me-2"></i>Ver Logs
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Manutenção</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-warning" onclick="executeMaintenanceCommand('backup')">
                                                    <i class="fas fa-download me-2"></i>Fazer Backup
                                                </button>
                                                <button class="btn btn-info" onclick="executeMaintenanceCommand('cleanup')">
                                                    <i class="fas fa-broom me-2"></i>Limpeza
                                                </button>
                                                <button class="btn btn-success" onclick="executeMaintenanceCommand('optimize')">
                                                    <i class="fas fa-rocket me-2"></i>Otimizar
                                                </button>
                                                <button class="btn btn-primary" onclick="executeDiagnostic()">
                                                    <i class="fas fa-stethoscope me-2"></i>Diagnóstico
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Output Console -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="fas fa-terminal me-2"></i>Console de Saída</h6>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="clearConsole()">
                                                <i class="fas fa-trash me-1"></i>Limpar
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <pre id="discador-console" class="bg-dark text-light p-3 rounded" style="height: 300px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px;">Aguardando comandos...</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php
$content = ob_get_clean();

// Custom JS para dashboard
$customJS = "
// Load Chart.js
const script = document.createElement('script');
script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
script.onload = initCharts;
document.head.appendChild(script);

// Initialize charts
function initCharts() {
    const ctx = document.getElementById('callVolumeChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + 'h'),
            datasets: [{
                label: 'Chamadas',
                data: [12, 19, 3, 5, 2, 3, 9, 15, 22, 18, 25, 30, 28, 35, 40, 32, 28, 25, 20, 15, 10, 8, 5, 3],
                borderColor: 'rgb(37, 99, 235)',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.1,
                fill: true
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
}

// Load dashboard data
function loadDashboardData() {
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            // Update stats
            document.getElementById('total-calls').textContent = data.totalCalls || '0';
            document.getElementById('ramais-online').textContent = data.ramaisOnline || '0';
            document.getElementById('filas-ativas').textContent = data.filasAtivas || '0';
            document.getElementById('troncos-status').textContent = data.troncosOk || '0';
            
            // Update system resources
            if (data.systemResources) {
                const cpu = data.systemResources.cpu || 0;
                const memory = data.systemResources.memory || 0;
                const disk = data.systemResources.disk || 0;
                
                document.getElementById('cpu-usage').textContent = cpu + '%';
                document.getElementById('cpu-bar').style.width = cpu + '%';
                
                document.getElementById('memory-usage').textContent = memory + '%';
                document.getElementById('memory-bar').style.width = memory + '%';
                
                document.getElementById('disk-usage').textContent = disk + '%';
                document.getElementById('disk-bar').style.width = disk + '%';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados:', error);
        });
}

// Load recent activity
function loadRecentActivity() {
    fetch('api/recent-activity.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recent-activity');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan=\"4\" class=\"text-center text-muted\">Nenhuma atividade recente</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(item => 
                '<tr>' +
                    '<td>' + item.time + '</td>' +
                    '<td><i class=\"' + item.icon + ' me-2\"></i>' + item.event + '</td>' +
                    '<td>' + item.user + '</td>' +
                    '<td>' + item.details + '</td>' +
                '</tr>'
            ).join('');
        })
        .catch(error => {
            console.error('Erro ao carregar atividades:', error);
            document.getElementById('recent-activity').innerHTML = 
                '<tr><td colspan=\"4\" class=\"text-center text-danger\">Erro ao carregar atividades</td></tr>';
        });
}

// Discador v2.0 Management Functions
function toggleDiscadorPanel() {
    const panel = document.getElementById('discador-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        loadDiscadorStatus();
    } else {
        panel.style.display = 'none';
    }
}

function loadDiscadorStatus() {
    // Update status indicators
    fetch('api/discador-status.php')
        .then(response => response.json())
        .then(data => {
            // Update master status
            const masterStatus = document.getElementById('master-status');
            masterStatus.textContent = data.master.status || 'Offline';
            masterStatus.className = 'badge ' + (data.master.running ? 'bg-success' : 'bg-danger');
            
            // Update workers count
            document.getElementById('workers-count').textContent = data.workers.active || '0';
            
            // Update queue count
            document.getElementById('queue-count').textContent = data.queue.pending || '0';
            
            // Update Redis status
            const redisStatus = document.getElementById('redis-status');
            redisStatus.textContent = data.redis.status || 'Offline';
            redisStatus.className = 'badge ' + (data.redis.connected ? 'bg-success' : 'bg-danger');
        })
        .catch(error => {
            console.error('Erro ao carregar status do discador:', error);
            appendToConsole('Erro ao carregar status: ' + error.message, 'error');
        });
}

function executeDiscadorCommand(command) {
    appendToConsole('Executando comando: ' + command + '...', 'info');
    
    fetch('api/discador-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'control',
            command: command
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendToConsole(data.output || 'Comando executado com sucesso', 'success');
            // Refresh status after command
            setTimeout(loadDiscadorStatus, 2000);
        } else {
            appendToConsole('Erro: ' + (data.error || 'Comando falhou'), 'error');
        }
    })
    .catch(error => {
        appendToConsole('Erro de comunicação: ' + error.message, 'error');
    });
}

function executeMaintenanceCommand(command) {
    appendToConsole('Executando manutenção: ' + command + '...', 'info');
    
    fetch('api/discador-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'maintenance',
            command: command
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendToConsole(data.output || 'Manutenção executada com sucesso', 'success');
        } else {
            appendToConsole('Erro: ' + (data.error || 'Manutenção falhou'), 'error');
        }
    })
    .catch(error => {
        appendToConsole('Erro de comunicação: ' + error.message, 'error');
    });
}

function executeDiagnostic() {
    appendToConsole('Executando diagnóstico completo...', 'info');
    
    fetch('api/discador-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'diagnostic'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            appendToConsole(data.output || 'Diagnóstico executado com sucesso', 'success');
        } else {
            appendToConsole('Erro: ' + (data.error || 'Diagnóstico falhou'), 'error');
        }
    })
    .catch(error => {
        appendToConsole('Erro de comunicação: ' + error.message, 'error');
    });
}

function openMonitorDashboard() {
    window.open('monitor-dashboard.php', '_blank', 'width=1200,height=800');
}

function appendToConsole(message, type = 'info') {
    const console = document.getElementById('discador-console');
    const timestamp = new Date().toLocaleTimeString();
    const colors = {
        info: '#17a2b8',
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107'
    };
    
    const color = colors[type] || colors.info;
    const line = `[${timestamp}] <span style=\"color: ${color}\">${message}</span>\n`;
    
    console.innerHTML += line;
    console.scrollTop = console.scrollHeight;
}

function clearConsole() {
    document.getElementById('discador-console').innerHTML = 'Console limpo.\n';
}

// Auto-refresh data
function autoRefresh() {
    loadDashboardData();
    loadRecentActivity();
    
    // Also refresh discador status if panel is visible
    const panel = document.getElementById('discador-panel');
    if (panel && panel.style.display !== 'none') {
        loadDiscadorStatus();
    }
}

// Initial load
autoRefresh();

// Refresh every 30 seconds
setInterval(autoRefresh, 30000);

// Active menu item
document.querySelector('.nav-link[href=\"index.php\"]').classList.add('active');
";
                fill: true
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
}

// Load dashboard data
function loadDashboardData() {
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            // Update stats
            document.getElementById('total-calls').textContent = data.totalCalls || '0';
            document.getElementById('ramais-online').textContent = data.ramaisOnline || '0';
            document.getElementById('filas-ativas').textContent = data.filasAtivas || '0';
            document.getElementById('troncos-status').textContent = data.troncosOk || '0';
            
            // Update system resources
            if (data.systemResources) {
                const cpu = data.systemResources.cpu || 0;
                const memory = data.systemResources.memory || 0;
                const disk = data.systemResources.disk || 0;
                
                document.getElementById('cpu-usage').textContent = cpu + '%';
                document.getElementById('cpu-bar').style.width = cpu + '%';
                
                document.getElementById('memory-usage').textContent = memory + '%';
                document.getElementById('memory-bar').style.width = memory + '%';
                
                document.getElementById('disk-usage').textContent = disk + '%';
                document.getElementById('disk-bar').style.width = disk + '%';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados:', error);
        });
}

// Load recent activity
function loadRecentActivity() {
    fetch('api/recent-activity.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recent-activity');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan=\"4\" class=\"text-center text-muted\">Nenhuma atividade recente</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(item => 
                '<tr>' +
                    '<td>' + item.time + '</td>' +
                    '<td><i class=\"' + item.icon + ' me-2\"></i>' + item.event + '</td>' +
                    '<td>' + item.user + '</td>' +
                    '<td>' + item.details + '</td>' +
                '</tr>'
            ).join('');
        })
        .catch(error => {
            console.error('Erro ao carregar atividades:', error);
            document.getElementById('recent-activity').innerHTML = 
                '<tr><td colspan=\"4\" class=\"text-center text-danger\">Erro ao carregar atividades</td></tr>';
        });
}

// Auto-refresh data
function autoRefresh() {
    loadDashboardData();
    loadRecentActivity();
}

// Initial load
autoRefresh();

// Refresh every 30 seconds
setInterval(autoRefresh, 30000);

// Active menu item
document.querySelector('.nav-link[href=\"index.php\"]').classList.add('active');
";

// Include template
include 'templates/base.php';

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Discador v2.0 - Status</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .status-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #ddd;
        }
        .status-card.success {
            border-left-color: #28a745;
        }
        .status-card.warning {
            border-left-color: #ffc107;
        }
        .status-card.error {
            border-left-color: #dc3545;
        }
        .status-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-badge.error {
            background: #f8d7da;
            color: #721c24;
        }
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .info-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .info-item strong {
            color: #495057;
        }
        .timestamp {
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sistema Discador v2.0</h1>
            <p>Ambiente Dockerizado - Status dos Serviços</p>
        </div>
        
        <div class="content">
            <div class="status-grid">
                
                <!-- Status do PHP -->
                <div class="status-card success">
                    <h3>🐘 PHP</h3>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge success"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="status-item">
                        <span>SAPI</span>
                        <span class="status-badge success"><?php echo php_sapi_name(); ?></span>
                    </div>
                    <div class="status-item">
                        <span>Timezone</span>
                        <span class="status-badge success"><?php echo date_default_timezone_get(); ?></span>
                    </div>
                </div>

                <!-- Status do Banco de Dados -->
                <div class="status-card <?php
                    try {
                        $dsn = 'mysql:host=' . ($_ENV['DB_HOST'] ?? 'database') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'discador');
                        $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'discador_user', $_ENV['DB_PASSWORD'] ?? 'discador_pass_2024!');
                        echo 'success';
                        $db_status = 'Conectado';
                        $db_version = $pdo->query('SELECT VERSION()')->fetchColumn();
                    } catch (Exception $e) {
                        echo 'error';
                        $db_status = 'Erro: ' . $e->getMessage();
                        $db_version = 'N/A';
                    }
                ?>">
                    <h3>🗄️ MariaDB</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $db_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $db_version; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo isset($pdo) ? 'success' : 'error'; ?>"><?php echo $_ENV['DB_HOST'] ?? 'database'; ?></span>
                    </div>
                </div>                <!-- Status do Redis -->
                <div class="status-card <?php
                    try {
                        $redis_host = $_ENV['REDIS_HOST'] ?? 'redis';
                        $redis_port = 6379;
                        
                        if (class_exists('Redis')) {
                            $redis = new Redis();
                            $redis->connect($redis_host, $redis_port, 2);
                            if (!empty($_ENV['REDIS_PASSWORD'])) {
                                $redis->auth($_ENV['REDIS_PASSWORD']);
                            }
                            $redis_info = $redis->info();
                            echo 'success';
                            $redis_status = 'Conectado';
                            $redis_version = $redis_info['redis_version'] ?? 'N/A';
                        } else {
                            echo 'warning';
                            $redis_status = 'Extensão não encontrada';
                            $redis_version = 'N/A';
                        }
                    } catch (Exception $e) {
                        echo 'warning';
                        $redis_status = 'Não disponível';
                        $redis_version = 'N/A';
                    }
                ?>">
                    <h3>⚡ Redis</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo isset($redis) && $redis->ping() ? 'success' : 'warning'; ?>"><?php echo $redis_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Versão</span>
                        <span class="status-badge <?php echo isset($redis) ? 'success' : 'warning'; ?>"><?php echo $redis_version; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo isset($redis) ? 'success' : 'warning'; ?>"><?php echo $redis_host; ?></span>
                    </div>
                </div>

                <!-- Status do Asterisk -->
                <div class="status-card <?php
                    $asterisk_host = $_ENV['ASTERISK_HOST'] ?? 'asterisk';
                    $asterisk_status = 'Verificando...';
                    $asterisk_port = 5038; // Manager port
                    
                    $socket = @fsockopen($asterisk_host, $asterisk_port, $errno, $errstr, 2);
                    if ($socket) {
                        echo 'success';
                        $asterisk_status = 'Online';
                        fclose($socket);
                    } else {
                        echo 'warning';
                        $asterisk_status = 'Offline';
                    }
                ?>">
                    <h3>📞 Asterisk</h3>
                    <div class="status-item">
                        <span>Status</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_status; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Manager Port</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_port; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Host</span>
                        <span class="status-badge <?php echo $socket ? 'success' : 'warning'; ?>"><?php echo $asterisk_host; ?></span>
                    </div>
                </div>
            </div>

            <!-- Informações do Sistema -->
            <div class="info-section">
                <h3>ℹ️ Informações do Sistema</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Servidor Web:</strong><br>
                        Nginx + PHP-FPM
                    </div>
                    <div class="info-item">
                        <strong>Sistema Operacional:</strong><br>
                        <?php echo php_uname('s') . ' ' . php_uname('r'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Memória PHP:</strong><br>
                        <?php echo ini_get('memory_limit'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Upload Max:</strong><br>
                        <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                    <div class="info-item">
                        <strong>Extensões Carregadas:</strong><br>
                        <?php echo count(get_loaded_extensions()); ?> extensões
                    </div>
                    <div class="info-item">
                        <strong>Docker Network:</strong><br>
                        discador_network
                    </div>
                </div>
            </div>

            <!-- Extensões PHP Importantes -->
            <div class="info-section">
                <h3>🔧 Extensões PHP</h3>
                <div class="info-grid">
                    <?php
                    $important_extensions = [
                        'pdo_mysql' => 'PDO MySQL',
                        'mysqli' => 'MySQLi',
                        'redis' => 'Redis',
                        'gd' => 'GD (Imagens)',
                        'curl' => 'cURL',
                        'json' => 'JSON',
                        'mbstring' => 'Multibyte String',
                        'zip' => 'ZIP',
                        'xml' => 'XML',
                        'openssl' => 'OpenSSL'
                    ];
                    
                    foreach ($important_extensions as $ext => $name) {
                        $loaded = extension_loaded($ext);
                        echo '<div class="info-item">';
                        echo '<strong>' . $name . ':</strong><br>';
                        echo '<span class="status-badge ' . ($loaded ? 'success' : 'error') . '">';
                        echo $loaded ? 'Carregada' : 'Não Carregada';
                        echo '</span>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <div class="timestamp">
                Última verificação: <?php echo date('d/m/Y H:i:s'); ?>
            </div>
        </div>
    </div>
</body>
</html>
