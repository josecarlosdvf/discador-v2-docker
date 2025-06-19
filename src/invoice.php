<?php
session_start();

require_once __DIR__ . '/Core/MultiTenantAuth.php';
require_once __DIR__ . '/Core/TenantManager.php';
require_once __DIR__ . '/Core/BillingManager.php';

$auth = new \DiscadorV2\Core\MultiTenantAuth();
$tenantManager = \DiscadorV2\Core\TenantManager::getInstance();

// Verificar se está logado
if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$billingManager = new \DiscadorV2\Core\BillingManager();

// Buscar fatura
$billingId = $_GET['id'] ?? null;
if (!$billingId) {
    header('Location: /billing.php');
    exit;
}

$invoice = $billingManager->getInvoiceById($billingId);
if (!$invoice) {
    header('Location: /billing.php');
    exit;
}

// Verificar permissões
$currentUser = $auth->getCurrentUser();
if (!$auth->isGlobalAdmin()) {
    $currentTenant = $tenantManager->getCurrentTenant();
    if ($invoice['empresa_id'] != $currentTenant['id']) {
        header('Location: /billing.php');
        exit;
    }
}

// Verificar se é download
if (isset($_GET['download'])) {
    // Headers para download PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="fatura_' . $invoice['id'] . '.pdf"');
    
    // Aqui seria gerado o PDF da fatura
    // Por simplicidade, vou redirecionar para página de impressão
    header('Location: /invoice.php?id=' . $billingId . '&print=1');
    exit;
}

$isPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura #<?= $invoice['id'] ?> - <?= htmlspecialchars($invoice['empresa_nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        
        .invoice-body {
            padding: 30px;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .invoice-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <?php if (!$isPrint): ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="/billing.php">
                <i class="fas fa-file-invoice-dollar"></i> 
                Fatura #<?= $invoice['id'] ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a href="/billing.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <a href="?id=<?= $billingId ?>&print=1" class="btn btn-light me-2" target="_blank">
                    <i class="fas fa-print"></i> Imprimir
                </a>
                <a href="?id=<?= $billingId ?>&download=1" class="btn btn-success">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container mt-4">
        <div class="invoice-container">
            <!-- Header da Fatura -->
            <div class="invoice-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="company-logo">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <h2>Discador VoIP</h2>
                        <p class="mb-0">Sistema de Discagem Automatizada</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h1>FATURA</h1>
                        <h3>#<?= str_pad($invoice['id'], 6, '0', STR_PAD_LEFT) ?></h3>
                        <p class="mb-0">Data: <?= date('d/m/Y') ?></p>
                    </div>
                </div>
            </div>

            <!-- Corpo da Fatura -->
            <div class="invoice-body">
                <!-- Informações da Empresa -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5><i class="fas fa-building"></i> Dados da Empresa</h5>
                        <div class="invoice-details">
                            <strong><?= htmlspecialchars($invoice['empresa_nome']) ?></strong><br>
                            CNPJ: <?= htmlspecialchars($invoice['empresa_cnpj']) ?><br>
                            Email: <?= htmlspecialchars($invoice['empresa_email']) ?><br>
                            <?php if ($invoice['empresa_endereco']): ?>
                                Endereço: <?= htmlspecialchars($invoice['empresa_endereco']) ?><br>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-calendar"></i> Período de Cobrança</h5>
                        <div class="invoice-details">
                            <strong>Período:</strong> 
                            <?= date('d/m/Y', strtotime($invoice['periodo_inicio'])) ?> até 
                            <?= date('d/m/Y', strtotime($invoice['periodo_fim'])) ?><br>
                            
                            <strong>Status:</strong> 
                            <span class="badge bg-<?= $invoice['status'] === 'pago' ? 'success' : ($invoice['status'] === 'vencido' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($invoice['status']) ?>
                            </span><br>
                            
                            <?php if ($invoice['data_pagamento']): ?>
                                <strong>Data Pagamento:</strong> <?= date('d/m/Y', strtotime($invoice['data_pagamento'])) ?><br>
                            <?php endif; ?>
                            
                            <?php if ($invoice['forma_pagamento']): ?>
                                <strong>Forma Pagamento:</strong> <?= ucfirst($invoice['forma_pagamento']) ?><br>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Resumo dos Serviços -->
                <h5><i class="fas fa-list"></i> Resumo dos Serviços</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Descrição</th>
                                <th class="text-center">Quantidade</th>
                                <th class="text-end">Valor Unitário</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Serviço de Discagem VoIP</strong><br>
                                    <small class="text-muted">
                                        Período: <?= date('d/m/Y', strtotime($invoice['periodo_inicio'])) ?> a <?= date('d/m/Y', strtotime($invoice['periodo_fim'])) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <?= number_format($invoice['total_chamadas']) ?> chamadas<br>
                                    <small class="text-muted"><?= gmdate('H:i:s', $invoice['total_segundos']) ?> de duração</small>
                                </td>
                                <td class="text-end">
                                    R$ <?= number_format($invoice['custo_medio_chamada'], 4, ',', '.') ?><br>
                                    <small class="text-muted">por chamada</small>
                                </td>
                                <td class="text-end">
                                    <strong>R$ <?= number_format($invoice['total_custo'], 2, ',', '.') ?></strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Detalhamento Técnico -->
                <h5><i class="fas fa-chart-bar"></i> Detalhamento Técnico</h5>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-primary"><?= number_format($invoice['total_chamadas']) ?></h4>
                            <small>Total de Chamadas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-success"><?= number_format($invoice['chamadas_atendidas']) ?></h4>
                            <small>Chamadas Atendidas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-info"><?= number_format($invoice['taxa_atendimento'], 1) ?>%</h4>
                            <small>Taxa de Atendimento</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <h4 class="text-warning"><?= gmdate('H:i:s', $invoice['total_segundos']) ?></h4>
                            <small>Tempo Total</small>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Informações de Pagamento</h6>
                            <p class="mb-0">
                                Vencimento: <?= date('d/m/Y', strtotime($invoice['periodo_fim'] . ' +30 days')) ?><br>
                                Forma de Pagamento: Boleto Bancário, PIX ou Cartão<br>
                                Em caso de dúvidas, entre em contato: suporte@discador.com
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <h6>TOTAL GERAL</h6>
                            <h2>R$ <?= number_format($invoice['total_custo'], 2, ',', '.') ?></h2>
                            <p class="mb-0">
                                <?php if ($invoice['status'] === 'pago'): ?>
                                    <i class="fas fa-check-circle"></i> PAGO
                                <?php else: ?>
                                    <i class="fas fa-clock"></i> PENDENTE
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4 pt-4 border-top">
                    <p class="text-muted mb-0">
                        <small>
                            Fatura gerada automaticamente em <?= date('d/m/Y H:i') ?> - 
                            Sistema Discador VoIP v2.0
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isPrint): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    <?php endif; ?>

    <?php if (!$isPrint): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>
