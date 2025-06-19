<?php
require_once 'config/config.php';

echo "<h2>Teste de Verificação de Senha</h2>";

$stored_hash = '$argon2id$v=19$m=65536,t=4,p=3$SmtOZ1ROc0M4d3pMcEczZQ$8vF3VkYGbLw3qLw9C4VDOyF3FGl2nY5jJ8h2K8j9M1E';
$password_to_test = 'admin123';

echo "<strong>Hash armazenado:</strong><br><code>" . htmlspecialchars($stored_hash) . "</code><br><br>";

// Teste com password_verify (PHP nativo)
echo "<strong>1. Teste com password_verify() [PHP nativo]:</strong><br>";
$result1 = password_verify($password_to_test, $stored_hash);
echo "Resultado: " . ($result1 ? "✅ SUCESSO" : "❌ FALHA") . "<br><br>";

// Teste com nossa função verifyPassword
echo "<strong>2. Teste com verifyPassword() [função customizada]:</strong><br>";
$result2 = verifyPassword($password_to_test, $stored_hash);
echo "Resultado: " . ($result2 ? "✅ SUCESSO" : "❌ FALHA") . "<br><br>";

// Teste gerando um novo hash com a mesma senha
echo "<strong>3. Gerando novo hash para comparação:</strong><br>";
$new_hash = hashPassword($password_to_test);
echo "Novo hash: <code>" . htmlspecialchars($new_hash) . "</code><br>";
$result3 = verifyPassword($password_to_test, $new_hash);
echo "Verificação do novo hash: " . ($result3 ? "✅ SUCESSO" : "❌ FALHA") . "<br><br>";

// Teste manual com diferentes senhas
echo "<strong>4. Testando senhas alternativas:</strong><br>";
$test_passwords = ['admin123', 'admin', '123456', 'password', 'discador'];
foreach ($test_passwords as $test_pass) {
    $test_result = verifyPassword($test_pass, $stored_hash);
    echo "Senha '{$test_pass}': " . ($test_result ? "✅ SUCESSO" : "❌ FALHA") . "<br>";
}

echo "<br><strong>5. Informações do sistema:</strong><br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Argon2 disponível: " . (defined('PASSWORD_ARGON2ID') ? "✅ SIM" : "❌ NÃO") . "<br>";
?>
