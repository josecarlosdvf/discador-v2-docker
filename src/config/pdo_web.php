<?php
/**
 * Configuração PDO Simplificada para Web
 */

// Configuração Docker para web
$host = "database";
$port = "3306"; 
$dbname = "discador";
$username = "root";
$password = "root123";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $pdo->exec("SET time_zone = '-03:00'");
    $GLOBALS['pdo'] = $pdo;
    
} catch (PDOException $e) {
    error_log("PDO Web Error: " . $e->getMessage());
    $GLOBALS['pdo'] = null;
}
?>