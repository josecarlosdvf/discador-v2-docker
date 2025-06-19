<?php
// Teste rápido do login.php após correção
$context = stream_context_create(['http' => ['timeout' => 10]]);
$response = @file_get_contents('http://localhost:8080/login.php', false, $context);

if ($response !== false) {
    echo "✅ LOGIN.PHP: Resposta recebida (" . strlen($response) . " bytes)\n";
    
    if (strpos($response, 'Fatal error') !== false || 
        strpos($response, 'Parse error') !== false) {
        echo "❌ Ainda há erro PHP\n";
    } else {
        echo "✅ Sem erros PHP detectados\n";
    }
} else {
    echo "❌ LOGIN.PHP: Ainda retorna erro 500\n";
}
?>
