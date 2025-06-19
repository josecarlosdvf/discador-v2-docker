<?php
/**
 * Logout - Sistema Discador v2.0
 */

require_once 'config/config.php';
require_once 'classes/Auth.php';

// Fazer logout
$auth->logout();

// Redirecionar para login com mensagem
header('Location: login.php?logout=1');
exit;
?>
