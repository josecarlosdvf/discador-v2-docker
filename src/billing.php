<?php
session_start();

require_once 'Core/MultiTenantAuth.php';
require_once 'Core/TenantManager.php';
require_once 'Core/BillingManager.php';

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
    // Usuário normal só vê sua própria empresa
    $currentTenant = $tenantManager->getCurrentTenant();
    $empresaId = $currentTenant['id'];
}

if (!$currentTenant) {
    header('Location: /admin-dashboard.php');
    exit;
}

$billingManager = new \DiscadorV2\Core\BillingManager();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'process_costs':
                $result = $billingManager->processCallCosts(
                    $empresaId, 
                    $_POST['data_inicio'] ?? null, 
                    $_POST['data_fim'] ?? null
                );
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'mark_paid':
                $result = $billingManager->markInvoiceAsPaid($_POST['billing_id'], $_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Período atual
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// Buscar dados de billing
$billingData = $billingManager->getBillingByCompany($empresaId, $ano, $mes);
$stats = $billingManager->getBillingStats($empresaId);

// Relatório financeiro do mês atual
$dataInicio = "$ano-$mes-01";
$dataFim = date('Y-m-t', strtotime($dataInicio));
$relatorioMensal = $billingManager->getFinancialReport($empresaId, $dataInicio, $dataFim);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Custos - <?= htmlspecialchars($currentTenant['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .stats-card-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .stats-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #000;
        }
        
        .stats-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd5e53 100%);
            color: white;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .invoice-card {
            border-left: 5px solid;
            transition: all 0.3s ease;
        }
        
        .invoice-pendente {
            border-left-color: #ffc107;
        }
        
        .invoice-pago {
            border-left-color: #28a745;
        }
        
        .invoice-vencido {
            border-left-color: #dc3545;
        }
        
        .btn-process {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            border: none;
            color: white;
        }
        
        .cost-breakdown {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="fas fa-dollar-sign"></i> 
                Centro de Custos - <?= htmlspecialchars($currentTenant['nome']) ?>
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

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-dollar-sign"></i> Centro de Custos e Billing</h2>
                <p class="text-muted">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?>
                    - Período: <?= date('m/Y', strtotime("$ano-$mes-01")) ?>
                </p>
            </div>
            <div class="col-auto">
                <div class="btn-group me-2">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar"></i> <?= date('m/Y', strtotime("$ano-$mes-01")) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <?php for ($i = 0; $i < 12; $i++): ?>
                            <?php $data = date('Y-m', strtotime("-$i months")); ?>
                            <li><a class="dropdown-item" href="?ano=<?= substr($data, 0, 4) ?>&mes=<?= substr($data, 5, 2) ?>">
                                <?= date('m/Y', strtotime($data)) ?>
                            </a></li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <button class="btn btn-process" data-bs-toggle="modal" data-bs-target="#processModal">
                    <i class="fas fa-calculator"></i> Processar Custos
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

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-number">R$ <?= number_format($stats['receita_total'], 2, ',', '.') ?></div>
                        <div>Receita Total</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-success">
                    <div class="card-body text-center">
                        <div class="stats-number">R$ <?= number_format($stats['receita_recebida'], 2, ',', '.') ?></div>
                        <div>Receita Recebida</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-warning">
                    <div class="card-body text-center">
                        <div class="stats-number">R$ <?= number_format($stats['receita_pendente'], 2, ',', '.') ?></div>
                        <div>Receita Pendente</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card-danger">
                    <div class="card-body text-center">
                        <div class="stats-number"><?= $stats['faturas_pendentes'] ?></div>
                        <div>Faturas Pendentes</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Relatório Mensal -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Relatório Mensal - <?= date('m/Y', strtotime("$ano-$mes-01")) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($relatorioMensal['success'] && !empty($relatorioMensal['totais'])): ?>
                            <!-- Resumo Geral -->
                            <div class="cost-breakdown">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h4><?= number_format($relatorioMensal['totais']['total_chamadas']) ?></h4>
                                        <small>Total de Chamadas</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4><?= gmdate('H:i:s', $relatorioMensal['totais']['total_segundos']) ?></h4>
                                        <small>Tempo Total</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4>R$ <?= number_format($relatorioMensal['totais']['total_custo'], 2, ',', '.') ?></h4>
                                        <small>Custo Total</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4><?= number_format(($relatorioMensal['totais']['chamadas_atendidas'] / max(1, $relatorioMensal['totais']['total_chamadas'])) * 100, 1) ?>%</h4>
                                        <small>Taxa de Atendimento</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico por Tipo de Destino -->
                            <div class="mb-4">
                                <h6>Distribuição de Custos por Tipo de Destino</h6>
                                <canvas id="costChart" height="300"></canvas>
                            </div>

                            <!-- Detalhamento por Campanha -->
                            <h6>Detalhamento por Campanha</h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Campanha</th>
                                            <th>Tipo Destino</th>
                                            <th>Chamadas</th>
                                            <th>Tempo</th>
                                            <th>Custo</th>
                                            <th>Custo Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($relatorioMensal['detalhe_campanhas'] as $detalhe): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($detalhe['campanha']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $detalhe['tipo_destino'])) ?></span>
                                                </td>
                                                <td><?= number_format($detalhe['total_chamadas']) ?></td>
                                                <td><?= gmdate('H:i:s', $detalhe['total_segundos']) ?></td>
                                                <td>R$ <?= number_format($detalhe['total_custo'], 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($detalhe['custo_medio'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <h5>Nenhum dado de billing disponível</h5>
                                <p class="text-muted">Processe os custos das chamadas para gerar o relatório.</p>
                                <button class="btn btn-process" data-bs-toggle="modal" data-bs-target="#processModal">
                                    <i class="fas fa-calculator"></i> Processar Custos Agora
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Faturas -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-file-invoice-dollar"></i> Faturas</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($billingData)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-file-invoice fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Nenhuma fatura para este período</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($billingData as $billing): ?>
                                <div class="invoice-card invoice-<?= $billing['status'] ?> p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= date('d/m/Y', strtotime($billing['periodo_inicio'])) ?> - 
                                                <?= date('d/m/Y', strtotime($billing['periodo_fim'])) ?>
                                            </h6>
                                            <p class="mb-1">
                                                <strong>R$ <?= number_format($billing['total_custo'], 2, ',', '.') ?></strong>
                                            </p>
                                            <small class="text-muted">
                                                <?= number_format($billing['total_chamadas']) ?> chamadas
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?= $billing['status'] === 'pago' ? 'success' : ($billing['status'] === 'vencido' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($billing['status']) ?>
                                            </span>
                                            <?php if ($billing['status'] === 'pendente'): ?>
                                                <br>
                                                <small>
                                                    <a href="#" onclick="markAsPaid(<?= $billing['id'] ?>)" class="text-success">
                                                        <i class="fas fa-check"></i> Marcar como Pago
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <div class="row text-center small">
                                            <div class="col-4">
                                                <div class="fw-bold"><?= number_format($billing['chamadas_atendidas']) ?></div>
                                                <div class="text-muted">Atendidas</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold"><?= number_format($billing['taxa_atendimento'], 1) ?>%</div>
                                                <div class="text-muted">Taxa</div>
                                            </div>
                                            <div class="col-4">
                                                <div class="fw-bold">R$ <?= number_format($billing['custo_medio_chamada'], 2, ',', '.') ?></div>
                                                <div class="text-muted">Médio</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 d-flex gap-2">
                                        <a href="/invoice.php?id=<?= $billing['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                            <i class="fas fa-eye"></i> Ver Fatura
                                        </a>
                                        <a href="/invoice.php?id=<?= $billing['id'] ?>&download=1" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Processar Custos -->
    <div class="modal fade" id="processModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-calculator"></i> Processar Custos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="process_costs">
                        
                        <div class="mb-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= $dataInicio ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= $dataFim ?>">
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Processamento de Custos:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Será calculado o custo de todas as chamadas do período</li>
                                <li>Tarifas são aplicadas por tipo de destino</li>
                                <li>Relatórios financeiros serão atualizados</li>
                                <li>Processo pode levar alguns minutos</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> Processar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Gráfico de custos por tipo
        <?php if ($relatorioMensal['success'] && !empty($relatorioMensal['resumo_tipos'])): ?>
        const ctx = document.getElementById('costChart').getContext('2d');
        const costChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($relatorioMensal['resumo_tipos'] as $tipo): ?>
                        '<?= ucfirst(str_replace('_', ' ', $tipo['tipo_destino'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($relatorioMensal['resumo_tipos'] as $tipo): ?>
                            <?= $tipo['total_custo'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#667eea', '#764ba2', '#28a745', '#ffc107', '#dc3545', '#17a2b8'
                    ]
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
        <?php endif; ?>
        
        function markAsPaid(billingId) {
            if (confirm('Marcar esta fatura como paga?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="billing_id" value="${billingId}">
                    <input type="hidden" name="forma_pagamento" value="manual">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
