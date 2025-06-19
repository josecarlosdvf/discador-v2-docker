<?php
// Test login page access
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing login.php access...\n";

try {
    echo "Starting session...\n";
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "Session started successfully\n";
    
    echo "Loading required files...\n";
    require_once __DIR__ . '/src/Core/MultiTenantAuth.php';
    echo "MultiTenantAuth loaded\n";
    
    require_once __DIR__ . '/src/Core/TenantManager.php';
    echo "TenantManager loaded\n";
    
    $auth = new \DiscadorV2\Core\MultiTenantAuth();
    echo "Auth instance created\n";
    
    $tenantManager = \DiscadorV2\Core\TenantManager::getInstance();
    echo "TenantManager instance created\n";
    
    echo "All components loaded successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
