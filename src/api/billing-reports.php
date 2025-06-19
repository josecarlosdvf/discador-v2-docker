<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

require_once __DIR__ . '/../Core/MultiTenantAuth.php';
require_once __DIR__ . '/../Core/TenantManager.php';
require_once __DIR__ . '/../Core/BillingManager.php';

$auth = new \DiscadorV2\Core\MultiTenantAuth();

// Verificar se está logado
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$tenantManager = \DiscadorV2\Core\TenantManager::getInstance();
$billingManager = new \DiscadorV2\Core\BillingManager();

try {
    $empresaId = $_GET['empresa_id'] ?? null;
    $action = $_GET['action'] ?? 'stats';
    
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
    
    switch ($action) {
        case 'stats':
            // Estatísticas gerais de billing
            $stats = $billingManager->getBillingStats($empresaId);
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'monthly_report':
            // Relatório mensal
            $ano = $_GET['ano'] ?? date('Y');
            $mes = $_GET['mes'] ?? date('m');
            
            $dataInicio = "$ano-$mes-01";
            $dataFim = date('Y-m-t', strtotime($dataInicio));
            
            $report = $billingManager->getFinancialReport($empresaId, $dataInicio, $dataFim);
            echo json_encode($report);
            break;
            
        case 'process_costs':
            // Processar custos (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $dataInicio = $input['data_inicio'] ?? date('Y-m-01');
            $dataFim = $input['data_fim'] ?? date('Y-m-t');
            
            $result = $billingManager->processCallCosts($empresaId, $dataInicio, $dataFim);
            echo json_encode($result);
            break;        case 'billing_history':
            // Histórico de faturas
            $limit = $_GET['limit'] ?? 12;
            $history = $billingManager->getBillingHistory($empresaId, $limit);
            
            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;
            
        case 'cost_analysis':
            // Análise de custos por período
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-t');
            $groupBy = $_GET['group_by'] ?? 'day';
            
            $report = $billingManager->getCostReport($empresaId, $dataInicio, $dataFim, $groupBy);
            
            echo json_encode([
                'success' => true,
                'report' => $report,
                'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim],
                'group_by' => $groupBy
            ]);
            break;
            
        case 'company_stats':
            // Estatísticas específicas da empresa
            $stats = $billingManager->getCompanyBillingStats($empresaId);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'alerts':
            // Alertas de billing
            $alerts = $billingManager->getBillingAlerts($empresaId, 10);
            
            echo json_encode([
                'success' => true,
                'alerts' => $alerts
            ]);
            break;
            
        case 'tariffs':
            // Tarifas da empresa
            $tariffs = $billingManager->getCompanyTariffs($empresaId);
            
            echo json_encode([
                'success' => true,
                'tariffs' => $tariffs
            ]);
            break;
            
        case 'generate_invoice':
            // Gerar nova fatura (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $mes = $input['mes'] ?? date('n');
            $ano = $input['ano'] ?? date('Y');
            
            $result = $billingManager->generateMonthlyInvoice($empresaId, $mes, $ano);
            echo json_encode($result);
            break;
            
        case 'register_payment':
            // Registrar pagamento (POST)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $billingManager->registerPayment(
                $input['fatura_id'],
                $input['valor_pago'],
                $input['forma_pagamento'],
                $input['transacao_id'] ?? null
            );
            echo json_encode($result);
            break;
            
        case 'cost_by_campaign':
            // Custos por campanha
            $ano = $_GET['ano'] ?? date('Y');
            $mes = $_GET['mes'] ?? date('m');
            
            $dataInicio = "$ano-$mes-01";
            $dataFim = date('Y-m-t', strtotime($dataInicio));
            
            $stmt = $GLOBALS['pdo']->prepare("
                SELECT 
                    camp.nome as campanha,
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo,
                    AVG(c.custo) as custo_medio
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
                GROUP BY camp.id
                ORDER BY total_custo DESC
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'campaigns' => $campaigns,
                'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim]
            ]);
            break;
            
        case 'cost_by_destination':
            // Custos por tipo de destino
            $ano = $_GET['ano'] ?? date('Y');
            $mes = $_GET['mes'] ?? date('m');
            
            $dataInicio = "$ano-$mes-01";
            $dataFim = date('Y-m-t', strtotime($dataInicio));
            
            $stmt = $GLOBALS['pdo']->prepare("
                SELECT 
                    c.tipo_destino,
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo,
                    AVG(c.tarifa_minuto) as tarifa_media
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
                GROUP BY c.tipo_destino
                ORDER BY total_custo DESC
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'destinations' => $destinations,
                'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim]
            ]);
            break;
            
        case 'daily_costs':
            // Custos diários do mês
            $ano = $_GET['ano'] ?? date('Y');
            $mes = $_GET['mes'] ?? date('m');
            
            $dataInicio = "$ano-$mes-01";
            $dataFim = date('Y-m-t', strtotime($dataInicio));
            
            $stmt = $GLOBALS['pdo']->prepare("
                SELECT 
                    DATE(c.calldate) as data,
                    COUNT(c.id) as total_chamadas,
                    SUM(c.duration) as total_segundos,
                    SUM(c.custo) as total_custo
                FROM cdr c
                JOIN campanhas camp ON c.campanha_id = camp.id
                WHERE camp.empresa_id = ? 
                AND DATE(c.calldate) BETWEEN ? AND ?
                AND c.duration > 0
                GROUP BY DATE(c.calldate)
                ORDER BY data
            ");
            $stmt->execute([$empresaId, $dataInicio, $dataFim]);
            
            $dailyCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'daily_costs' => $dailyCosts,
                'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage()
    ]);
}
