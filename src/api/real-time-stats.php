<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once __DIR__ . '/../Core/MultiTenantAuth.php';
require_once __DIR__ . '/../Core/TenantManager.php';
require_once __DIR__ . '/../Core/CampaignManager.php';

$auth = new \DiscadorV2\Core\MultiTenantAuth();

// Verificar se está logado
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$tenantManager = \DiscadorV2\Core\TenantManager::getInstance();
$campaignManager = new \DiscadorV2\Core\CampaignManager();

try {
    $empresaId = $_GET['empresa_id'] ?? null;
    
    // Se for admin global, pode ver qualquer empresa
    if ($auth->isGlobalAdmin()) {
        if (!$empresaId) {
            echo json_encode(['success' => false, 'message' => 'ID da empresa é obrigatório para admin global']);
            exit;
        }
    } else {
        // Usuário normal só vê sua própria empresa
        $currentTenant = $tenantManager->getCurrentTenant();
        $empresaId = $currentTenant['id'];
    }
    
    // Buscar estatísticas em tempo real
    $stats = $campaignManager->getRealTimeStats($empresaId);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
