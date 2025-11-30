<?php
date_default_timezone_set('America/Mexico_City');

$db_path = __DIR__ . "/../sqlite/inventario.db";
$dsn = "sqlite:" . $db_path;

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES  => false,
];

try {
    $db_dir = dirname($db_path);
    if (!file_exists($db_dir)) {
        mkdir($db_dir, 0750, true);
    }

    $conn = new PDO($dsn, null, null, $options);

    $conn->exec("PRAGMA foreign_keys = ON");
    $conn->exec("PRAGMA journal_mode = WAL");
    $conn->exec("PRAGMA synchronous = NORMAL");
    $conn->exec("PRAGMA busy_timeout = 5000");

} catch (PDOException $e) {
    error_log("Error de conexión BD: " . $e->getMessage());
    die("Error al conectar con la base de datos. Por favor contacta al administrador.");
}

function getDB() {
    global $conn;
    return $conn;
}
?>