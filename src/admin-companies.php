<?php
session_start();

// Verificar se é admin geral
require_once __DIR__ . '/Core/MultiTenantAuth.php';
$auth = new \DiscadorV2\Core\MultiTenantAuth();

if (!$auth->isLoggedIn() || !$auth->isGlobalAdmin()) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/Core/CompanyRegistration.php';
$registration = new \DiscadorV2\Core\CompanyRegistration();

$message = '';
$messageType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve':
                $result = $registration->approveCompany($_POST['empresa_id'], $_SESSION['user_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'reject':
                $result = $registration->rejectCompany($_POST['empresa_id'], $_POST['motivo'] ?? 'Não informado');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Buscar empresas pendentes
$empresasPendentes = $registration->getPendingCompanies();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprovação de Empresas - Admin Geral</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            border: none;
            color: white;
        }
        
        .company-card {
            transition: transform 0.2s;
        }
        
        .company-card:hover {
            transform: translateY(-2px);
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/admin-dashboard.php">
                <i class="fas fa-crown"></i> Admin Geral - Discador V2
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/admin-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-building"></i> Aprovação de Empresas</h2>
                <p class="text-muted">Gerencie as solicitações de cadastro de novas empresas</p>
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

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="fas fa-clock"></i> Pendentes
                        </h5>
                        <h2 class="text-warning"><?= count($empresasPendentes) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="fas fa-check"></i> Hoje
                        </h5>
                        <h2 class="text-success">0</h2>
                        <small class="text-muted">Aprovadas hoje</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <i class="fas fa-building"></i> Total
                        </h5>
                        <h2 class="text-info">0</h2>
                        <small class="text-muted">Empresas ativas</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Empresas Pendentes -->
        <?php if (empty($empresasPendentes)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Nenhuma empresa pendente</h4>
                        <p>Não há solicitações de cadastro aguardando aprovação no momento.</p>
                        <a href="/register-company.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Cadastrar Empresa Manualmente
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($empresasPendentes as $empresa): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card company-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-building"></i> <?= htmlspecialchars($empresa['nome']) ?>
                                </h5>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock"></i> Pendente
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="fas fa-building"></i> Dados da Empresa</h6>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Razão Social:</span><br>
                                            <small><?= htmlspecialchars($empresa['razao_social']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">CNPJ:</span><br>
                                            <small><?= formatCNPJ($empresa['cnpj']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Email:</span><br>
                                            <small><?= htmlspecialchars($empresa['email']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Telefone:</span><br>
                                            <small><?= htmlspecialchars($empresa['telefone']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Subdomínio:</span><br>
                                            <small class="text-primary"><?= htmlspecialchars($empresa['subdomain']) ?>.discador.com</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="fas fa-user-tie"></i> Administrador</h6>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Nome:</span><br>
                                            <small><?= htmlspecialchars($empresa['admin_nome']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Email:</span><br>
                                            <small><?= htmlspecialchars($empresa['admin_email']) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Plano:</span><br>
                                            <span class="badge bg-info"><?= ucfirst($empresa['plano']) ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Solicitado em:</span><br>
                                            <small><?= date('d/m/Y H:i', strtotime($empresa['criado_em'])) ?></small>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span class="info-label">Configurações:</span><br>
                                            <small>
                                                <i class="fas fa-users"></i> <?= $empresa['max_usuarios'] ?> usuários<br>
                                                <i class="fas fa-bullhorn"></i> <?= $empresa['max_campanhas'] ?> campanhas<br>
                                                <i class="fas fa-phone"></i> <?= $empresa['max_chamadas_simultaneas'] ?> chamadas
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-approve" 
                                            onclick="approveCompany(<?= $empresa['id'] ?>, '<?= htmlspecialchars($empresa['nome']) ?>')">
                                        <i class="fas fa-check"></i> Aprovar
                                    </button>
                                    
                                    <button type="button" class="btn btn-reject" 
                                            onclick="rejectCompany(<?= $empresa['id'] ?>, '<?= htmlspecialchars($empresa['nome']) ?>')">
                                        <i class="fas fa-times"></i> Rejeitar
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-info" 
                                            onclick="viewDetails(<?= $empresa['id'] ?>)">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmação de Aprovação -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check"></i> Aprovar Empresa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja aprovar a empresa <strong id="approveCompanyName"></strong>?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Após a aprovação:</strong><br>
                        • A empresa será ativada no sistema<br>
                        • O administrador receberá um email de confirmação<br>
                        • O acesso ao sistema será liberado
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="empresa_id" id="approveEmpresaId">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Confirmar Aprovação
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Rejeição -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times"></i> Rejeitar Empresa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Tem certeza que deseja rejeitar a empresa <strong id="rejectCompanyName"></strong>?</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Atenção:</strong> Esta ação não pode ser desfeita. A empresa será removida permanentemente.
                        </div>
                        
                        <div class="form-group">
                            <label for="motivo" class="form-label">Motivo da rejeição (opcional):</label>
                            <textarea name="motivo" id="motivo" class="form-control" rows="3" 
                                      placeholder="Descreva o motivo da rejeição..."></textarea>
                        </div>
                        
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="empresa_id" id="rejectEmpresaId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirmar Rejeição
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function approveCompany(empresaId, companyName) {
            document.getElementById('approveEmpresaId').value = empresaId;
            document.getElementById('approveCompanyName').textContent = companyName;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        
        function rejectCompany(empresaId, companyName) {
            document.getElementById('rejectEmpresaId').value = empresaId;
            document.getElementById('rejectCompanyName').textContent = companyName;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
        
        function viewDetails(empresaId) {
            // Implementar visualização de detalhes
            alert('Funcionalidade em desenvolvimento');
        }
    </script>
</body>
</html>

<?php
function formatCNPJ($cnpj) {
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}
?>
