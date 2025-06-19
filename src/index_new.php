<?php
/**
 * Página Inicial - Sistema Discador v2.0
 */

require_once 'config/config.php';

// Verificar se a classe Auth existe
if (!class_exists('Auth')) {
    // Se não existe, criar uma versão básica para desenvolvimento
    class Auth {
        public function requireAuth() {
            session_start();
            // Para desenvolvimento, sempre autenticado
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['permissions'] = ['admin'];
            }
        }
        
        public function getCurrentUser() {
            return [
                'username' => $_SESSION['username'] ?? 'admin',
                'last_login' => date('Y-m-d H:i:s')
            ];
        }
        
        public function hasPermission($permission) {
            return true; // Para desenvolvimento
        }
    }
}

$auth = new Auth();
$auth->requireAuth();
$currentUser = $auth->getCurrentUser();

// Template variables
$pageTitle = 'Dashboard - Sistema Discador v2.0';
$pageDescription = 'Painel de Controle do Sistema Discador v2.0';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 8px;
            margin: 2px 0;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .discador-panel {
            border: 2px solid #007bff;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-phone-alt me-2"></i>
                Discador v2.0
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($currentUser['username']); ?>
                </a>
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
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleDiscadorPanel()">
                                <i class="fas fa-cogs me-2"></i>
                                Gerenciamento Discador
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="total-calls">0</h3>
                                    <p class="mb-0 opacity-75">Chamadas Hoje</p>
                                </div>
                                <div class="opacity-50">
                                    <i class="fas fa-phone fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="workers-count">0</h3>
                                    <p class="mb-0 opacity-75">Workers Ativos</p>
                                </div>
                                <div class="opacity-50">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="queue-count">0</h3>
                                    <p class="mb-0 opacity-75">Fila de Tarefas</p>
                                </div>
                                <div class="opacity-50">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0" id="redis-status">Offline</h3>
                                    <p class="mb-0 opacity-75">Redis</p>
                                </div>
                                <div class="opacity-50">
                                    <i class="fas fa-database fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discador v2.0 Management Panel -->
                <div class="discador-panel" id="discador-panel" style="display: none;">
                    <div class="card">
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
                                            <span class="badge bg-secondary" id="master-status">Verificando...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center">
                                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                                            <h6>Workers Ativos</h6>
                                            <span class="h4" id="workers-active">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-warning">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                                            <h6>Fila de Tarefas</h6>
                                            <span class="h4" id="queue-pending">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-danger">
                                        <div class="card-body text-center">
                                            <i class="fas fa-database fa-2x text-danger mb-2"></i>
                                            <h6>Redis</h6>
                                            <span class="badge bg-secondary" id="redis-connection">Verificando...</span>
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

                <!-- System Status -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Status do Sistema</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-circle text-success"></i>
                                        <br>
                                        <small>Docker</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-circle text-success"></i>
                                        <br>
                                        <small>PHP</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-circle text-success"></i>
                                        <br>
                                        <small>MariaDB</small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-circle" id="redis-indicator"></i>
                                        <br>
                                        <small>Redis</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
            fetch('api/discador-status.php')
                .then(response => response.json())
                .then(data => {
                    // Update master status
                    const masterStatus = document.getElementById('master-status');
                    if (masterStatus) {
                        masterStatus.textContent = data.master?.status || 'Offline';
                        masterStatus.className = 'badge ' + (data.master?.running ? 'bg-success' : 'bg-danger');
                    }
                    
                    // Update workers count
                    const workersActive = document.getElementById('workers-active');
                    if (workersActive) {
                        workersActive.textContent = data.workers?.active || '0';
                    }
                    
                    // Update queue count
                    const queuePending = document.getElementById('queue-pending');
                    if (queuePending) {
                        queuePending.textContent = data.queue?.pending || '0';
                    }
                    
                    // Update Redis status
                    const redisConnection = document.getElementById('redis-connection');
                    if (redisConnection) {
                        redisConnection.textContent = data.redis?.status || 'Offline';
                        redisConnection.className = 'badge ' + (data.redis?.connected ? 'bg-success' : 'bg-danger');
                    }
                    
                    // Update main dashboard stats
                    document.getElementById('workers-count').textContent = data.workers?.active || '0';
                    document.getElementById('queue-count').textContent = data.queue?.pending || '0';
                    document.getElementById('redis-status').textContent = data.redis?.connected ? 'Online' : 'Offline';
                    
                    const redisIndicator = document.getElementById('redis-indicator');
                    if (redisIndicator) {
                        redisIndicator.className = 'fas fa-circle ' + (data.redis?.connected ? 'text-success' : 'text-danger');
                    }
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
            if (!console) return;
            
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                info: '#17a2b8',
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };
            
            const color = colors[type] || colors.info;
            const line = `[${timestamp}] <span style="color: ${color}">${message}</span>\n`;
            
            console.innerHTML += line;
            console.scrollTop = console.scrollHeight;
        }

        function clearConsole() {
            const console = document.getElementById('discador-console');
            if (console) {
                console.innerHTML = 'Console limpo.\n';
            }
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            loadDiscadorStatus();
            
            // Auto-refresh every 30 seconds
            setInterval(loadDiscadorStatus, 30000);
        });
    </script>
</body>
</html>
