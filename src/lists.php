<?php
session_start();

require_once __DIR__ . '/Core/MultiTenantAuth.php';
require_once __DIR__ . '/Core/TenantManager.php';
require_once __DIR__ . '/Core/ContactListManager.php';

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

$listManager = new \DiscadorV2\Core\ContactListManager();

$message = '';
$messageType = '';

// Processar upload de arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                $result = $listManager->uploadContactList($empresaId, $_POST, $_FILES);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'delete':
                $result = $listManager->deleteContactList($_POST['list_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Buscar listas de contatos
$listas = $listManager->getContactListsByCompany($empresaId);
$campanhaId = $_GET['campaign_id'] ?? null;
$campanhas = $listManager->getCampaignsByCompany($empresaId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listas de Contatos - <?= htmlspecialchars($currentTenant['nome']) ?></title>
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
        
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .list-card {
            border-left: 5px solid #667eea;
        }
        
        .btn-upload {
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
                <i class="fas fa-list"></i> 
                Listas de Contatos - <?= htmlspecialchars($currentTenant['nome']) ?>
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
                <h2><i class="fas fa-list"></i> Listas de Contatos</h2>
                <p class="text-muted">
                    <i class="fas fa-building"></i> <?= htmlspecialchars($currentTenant['nome']) ?>
                    <?php if ($campanhaId): ?>
                        - Filtrando por campanha específica
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-auto">
                <a href="/campaigns.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Voltar às Campanhas
                </a>
                <button class="btn btn-upload" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload"></i> Upload de Lista
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

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?= count($listas) ?></h4>
                        <p class="mb-0">Total de Listas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?= array_sum(array_column($listas, 'total_contatos')) ?></h4>
                        <p class="mb-0">Total de Contatos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?= count(array_filter($listas, fn($l) => $l['status'] === 'ativa')) ?></h4>
                        <p class="mb-0">Listas Ativas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h4><?= array_sum(array_column(array_filter($listas, fn($l) => $l['campanha_id']), 'contatos_pendentes')) ?></h4>
                        <p class="mb-0">Pendentes</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Listas de Contatos -->
        <div class="row">
            <?php if (empty($listas)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-list fa-4x text-muted mb-3"></i>
                            <h4>Nenhuma lista de contatos encontrada</h4>
                            <p class="text-muted">Faça upload da sua primeira lista de contatos para começar.</p>
                            <button class="btn btn-upload" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                <i class="fas fa-upload"></i> Fazer Upload da Primeira Lista
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($listas as $lista): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card list-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($lista['nome']) ?></h5>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($lista['descricao']) ?></p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="/contacts.php?list_id=<?= $lista['id'] ?>">
                                                <i class="fas fa-eye"></i> Ver Contatos
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="downloadList(<?= $lista['id'] ?>)">
                                                <i class="fas fa-download"></i> Download
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteList(<?= $lista['id'] ?>, '<?= htmlspecialchars($lista['nome']) ?>')">
                                                <i class="fas fa-trash"></i> Excluir
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Status e Campanha -->
                                <div class="mb-3">
                                    <span class="badge bg-<?= $lista['status'] === 'ativa' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($lista['status']) ?>
                                    </span>
                                    <?php if ($lista['campanha_nome']): ?>
                                        <span class="badge bg-info ms-2">
                                            <i class="fas fa-bullhorn"></i> <?= htmlspecialchars($lista['campanha_nome']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Estatísticas -->
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary"><?= $lista['total_contatos'] ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-warning"><?= $lista['contatos_pendentes'] ?? 0 ?></div>
                                        <small class="text-muted">Pendentes</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success"><?= $lista['contatos_processados'] ?? 0 ?></div>
                                        <small class="text-muted">Processados</small>
                                    </div>
                                </div>
                                
                                <!-- Progresso -->
                                <?php if ($lista['total_contatos'] > 0): ?>
                                    <?php $progresso = ($lista['contatos_processados'] ?? 0) / $lista['total_contatos'] * 100; ?>
                                    <div class="progress mb-3" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: <?= $progresso ?>%"></div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Informações adicionais -->
                                <div class="small text-muted">
                                    <div><i class="fas fa-calendar"></i> Criada em <?= date('d/m/Y H:i', strtotime($lista['criado_em'])) ?></div>
                                    <div><i class="fas fa-file"></i> <?= strtoupper($lista['formato']) ?> - <?= number_format($lista['tamanho_mb'], 2) ?> MB</div>
                                    <?php if ($lista['processado_em']): ?>
                                        <div><i class="fas fa-check"></i> Processada em <?= date('d/m/Y H:i', strtotime($lista['processado_em'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Ações -->
                                <div class="mt-3 d-flex gap-2">
                                    <a href="/contacts.php?list_id=<?= $lista['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                        <i class="fas fa-eye"></i> Ver Contatos
                                    </a>
                                    <button class="btn btn-outline-info btn-sm" onclick="downloadList(<?= $lista['id'] ?>)">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-upload"></i> Upload de Lista de Contatos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome da Lista <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: Leads Janeiro 2025">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="2" placeholder="Descrição opcional da lista..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campanha (Opcional)</label>
                            <select name="campanha_id" class="form-control">
                                <option value="">Selecione uma campanha...</option>
                                <?php foreach ($campanhas as $campanha): ?>
                                    <option value="<?= $campanha['id'] ?>" <?= $campanhaId == $campanha['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($campanha['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Arquivo <span class="text-danger">*</span></label>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" name="arquivo" id="fileInput" accept=".csv,.txt,.xlsx" required style="display: none;">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-muted"></i>
                                <h5>Clique para selecionar ou arraste o arquivo aqui</h5>
                                <p class="text-muted">
                                    Formatos aceitos: CSV, TXT, XLSX<br>
                                    Tamanho máximo: 50 MB
                                </p>
                                <div id="fileInfo" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Formato esperado:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>CSV/TXT:</strong> nome,telefone,email (com cabeçalho)</li>
                                <li><strong>XLSX:</strong> Colunas A=Nome, B=Telefone, C=Email</li>
                                <li>Primeira linha deve conter os cabeçalhos</li>
                                <li>Telefone no formato: (11) 99999-9999 ou 11999999999</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-upload"></i> Fazer Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Upload area drag and drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFileInfo(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });
        
        function showFileInfo(file) {
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
            fileInfo.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-file"></i> 
                    <strong>${file.name}</strong> (${sizeInMB} MB)
                </div>
            `;
            fileInfo.style.display = 'block';
        }
        
        function deleteList(listId, listName) {
            if (confirm(`Tem certeza que deseja excluir a lista "${listName}"? Esta ação não pode ser desfeita.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="list_id" value="${listId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function downloadList(listId) {
            window.open(`/api/download-list.php?list_id=${listId}`, '_blank');
        }
        
        // Form submission com loading
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fazendo Upload...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
