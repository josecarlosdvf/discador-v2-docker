<?php
echo "Test basic login function...\n";

try {
    session_start();
    echo "Session started\n";
    
    // Test loading core files
    require_once 'Core/MultiTenantAuth.php';
    echo "MultiTenantAuth loaded\n";
    
    $auth = new \DiscadorV2\Core\MultiTenantAuth();
    echo "Auth instance created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";  
    echo "Line: " . $e->getLine() . "\n";
}
