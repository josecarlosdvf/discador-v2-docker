<?php
/**
 * API - Dashboard Stats
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Para desenvolvimento, retorna dados mock
    $stats = [
        'totalCalls' => rand(50, 200),
        'ramaisOnline' => rand(5, 15),
        'filasAtivas' => rand(2, 6),
        'troncosOk' => rand(1, 4),
        'systemResources' => [
            'cpu' => rand(15, 85),
            'memory' => rand(30, 70),
            'disk' => rand(20, 60)
        ]
    ];
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to get dashboard stats',
        'totalCalls' => 0,
        'ramaisOnline' => 0,
        'filasAtivas' => 0,
        'troncosOk' => 0,
        'systemResources' => [
            'cpu' => 0,
            'memory' => 0,
            'disk' => 0
        ]
    ]);
}
?>
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cdr WHERE DATE(calldate) = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch();
    $stats['totalCalls'] = $result['count'] ?? rand(50, 200);
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
    error_log("Erro na API dashboard-stats: " . $e->getMessage());
}
?>
