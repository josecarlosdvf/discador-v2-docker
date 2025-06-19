<?php
// Simple test login page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Don't start session or redirect - just test basic functionality
echo "<!DOCTYPE html><html><head><title>Test Login</title></head><body>";
echo "<h1>Testing Login Components</h1>";

try {
    echo "<p>Loading Core classes...</p>";
    require_once 'Core/MultiTenantAuth.php';
    echo "<p>✅ MultiTenantAuth loaded</p>";
    
    require_once 'Core/TenantManager.php';
    echo "<p>✅ TenantManager loaded</p>";
    
    $auth = new \DiscadorV2\Core\MultiTenantAuth();
    echo "<p>✅ Auth instance created</p>";
    
    $tenantManager = \DiscadorV2\Core\TenantManager::getInstance();
    echo "<p>✅ TenantManager instance created</p>";
    
    echo "<p><strong>All components loaded successfully!</strong></p>";
    
    // Show simple login form
    echo "<h2>Login Form</h2>";
    echo "<form method='POST'>";
    echo "<input type='email' name='email' placeholder='Email' required><br><br>";
    echo "<input type='password' name='password' placeholder='Password' required><br><br>";
    echo "<button type='submit'>Login</button>";
    echo "</form>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        echo "<h3>Attempting login for: $email</h3>";
        
        $result = $auth->login($email, $password, 1);
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "</body></html>";
?>
