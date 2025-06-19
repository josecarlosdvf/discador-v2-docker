<?php
/**
 * Monitor Dashboard - Discador v2.0
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';

// Verificar se precisa de autenticação
$auth->requireAuth();

// Se chegou aqui, o usuário está autenticado
$currentUser = $auth->getCurrentUser();

if (!$auth->hasPermission('admin')) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Dashboard - Discador v2.0</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: 300;
            margin: 10px 0;
        }
        .metric-label {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .log-console {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 5px;
            height: 300px;
            overflow-y: auto;
        }
        .refresh-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Refresh Button -->
        <button class="btn btn-primary refresh-btn" onclick="refreshAll()">
            <i class="fas fa-sync-alt me-2"></i>Atualizar
        </button>
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h2 mb-3">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    Monitor Dashboard - Discador v2.0
                </h1>
                <p class="text-muted">
                    Monitoramento em tempo real do sistema de discagem automática
                </p>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="metric-label">Master Process</div>
                            <div class="metric-value" id="master-status-text">Checking...</div>
                            <div class="metric-label">
                                <span class="status-indicator" id="master-indicator"></span>
                                <span id="master-uptime">-</span>
                            </div>
                        </div>
                        <i class="fas fa-server fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="metric-label">Workers Ativos</div>
                            <div class="metric-value" id="workers-active">-</div>
                            <div class="metric-label">
                                <span id="workers-total">-</span> total
                            </div>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="metric-label">Fila de Tarefas</div>
                            <div class="metric-value" id="queue-pending">-</div>
                            <div class="metric-label">
                                <span id="queue-processing">-</span> processando
                            </div>
                        </div>
                        <i class="fas fa-tasks fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="metric-label">Redis Cache</div>
                            <div class="metric-value" id="redis-status-text">Checking...</div>
                            <div class="metric-label">
                                <span class="status-indicator" id="redis-indicator"></span>
                                <span id="redis-memory">-</span>
                            </div>
                        </div>
                        <i class="fas fa-database fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Details -->
        <div class="row">
            <!-- Workers Details -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>
                        Status dos Workers
                    </h5>
                    <div id="workers-list">
                        <div class="text-center text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Carregando...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Queue Details -->
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Estatísticas da Fila
                    </h5>
                    <canvas id="queueChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Performance do Sistema (Últimas 24h)
                    </h5>
                    <canvas id="performanceChart" width="800" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- System Logs -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>
                            Logs do Sistema
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearLogs()">
                            <i class="fas fa-trash me-1"></i>Limpar
                        </button>
                    </div>
                    <div class="log-console" id="system-logs">
                        Aguardando logs...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let queueChart, performanceChart;
        
        // Initialize charts
        function initCharts() {
            // Queue Chart
            const queueCtx = document.getElementById('queueChart').getContext('2d');
            queueChart = new Chart(queueCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pendentes', 'Processando', 'Falhadas'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: ['#ffc107', '#28a745', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Performance Chart
            const perfCtx = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(perfCtx, {
                type: 'line',
                data: {
                    labels: Array.from({length: 24}, (_, i) => i + 'h'),
                    datasets: [{
                        label: 'Chamadas Processadas',
                        data: generateRandomData(24),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Workers Ativos',
                        data: generateRandomData(24, 0, 10),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function generateRandomData(length, min = 0, max = 100) {
            return Array.from({length}, () => Math.floor(Math.random() * (max - min + 1)) + min);
        }
        
        // Load system status
        function loadSystemStatus() {
            fetch('api/discador-status.php')
                .then(response => response.json())
                .then(data => {
                    updateMasterStatus(data.master);
                    updateWorkersStatus(data.workers);
                    updateQueueStatus(data.queue);
                    updateRedisStatus(data.redis);
                })
                .catch(error => {
                    console.error('Error loading status:', error);
                    appendLog('Erro ao carregar status: ' + error.message, 'error');
                });
        }
        
        function updateMasterStatus(master) {
            document.getElementById('master-status-text').textContent = master.status || 'Unknown';
            const indicator = document.getElementById('master-indicator');
            indicator.className = 'status-indicator ' + (master.running ? 'status-online' : 'status-offline');
            
            const uptime = master.uptime ? new Date(master.uptime).toLocaleString() : 'N/A';
            document.getElementById('master-uptime').textContent = uptime;
        }
        
        function updateWorkersStatus(workers) {
            document.getElementById('workers-active').textContent = workers.active || '0';
            document.getElementById('workers-total').textContent = workers.total || '0';
            
            const workersList = document.getElementById('workers-list');
            if (workers.details && workers.details.length > 0) {
                workersList.innerHTML = workers.details.map(worker => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong>${worker.id}</strong>
                            <br>
                            <small class="text-muted">${worker.type}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge ${worker.status === 'active' ? 'bg-success' : 'bg-secondary'}">${worker.status}</span>
                            <br>
                            <small class="text-muted">Último: ${new Date(worker.last_heartbeat * 1000).toLocaleTimeString()}</small>
                        </div>
                    </div>
                `).join('');
            } else {
                workersList.innerHTML = '<div class="text-center text-muted">Nenhum worker ativo</div>';
            }
        }
        
        function updateQueueStatus(queue) {
            document.getElementById('queue-pending').textContent = queue.pending || '0';
            document.getElementById('queue-processing').textContent = queue.processing || '0';
            
            // Update queue chart
            if (queueChart) {
                queueChart.data.datasets[0].data = [
                    queue.pending || 0,
                    queue.processing || 0,
                    queue.failed || 0
                ];
                queueChart.update();
            }
        }
        
        function updateRedisStatus(redis) {
            document.getElementById('redis-status-text').textContent = redis.status || 'Unknown';
            const indicator = document.getElementById('redis-indicator');
            indicator.className = 'status-indicator ' + (redis.connected ? 'status-online' : 'status-offline');
            
            const memory = redis.info && redis.info.used_memory ? redis.info.used_memory : 'N/A';
            document.getElementById('redis-memory').textContent = memory;
        }
        
        function appendLog(message, type = 'info') {
            const logs = document.getElementById('system-logs');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                info: '#17a2b8',
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };
            
            const color = colors[type] || colors.info;
            const line = `<div style="color: ${color}">[${timestamp}] ${message}</div>`;
            
            logs.innerHTML += line;
            logs.scrollTop = logs.scrollHeight;
        }
        
        function clearLogs() {
            document.getElementById('system-logs').innerHTML = 'Logs limpos.\n';
        }
        
        function refreshAll() {
            loadSystemStatus();
            appendLog('Dashboard atualizado', 'success');
        }
        
        // Auto-refresh every 30 seconds
        function autoRefresh() {
            loadSystemStatus();
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            loadSystemStatus();
            appendLog('Monitor dashboard iniciado', 'success');
            
            // Auto-refresh every 30 seconds
            setInterval(autoRefresh, 30000);
        });
    </script>
</body>
</html>
