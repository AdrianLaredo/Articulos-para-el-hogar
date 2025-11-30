<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode([
        'error' => 'No autorizado',
        'saldo_restante' => 0,
        'total_disponible' => 0
    ]);
    exit;
}

if (!isset($_GET['semana'])) {
    echo json_encode([
        'error' => 'Parámetro semana requerido',
        'saldo_restante' => 0,
        'total_disponible' => 0
    ]);
    exit;
}

$id_semana = intval($_GET['semana']);

try {
    $fondos = calcularFondosDisponibles($conn, $id_semana);
    
    if (isset($fondos['error'])) {
        echo json_encode([
            'error' => $fondos['error'],
            'saldo_restante' => floatval($fondos['saldo_restante'] ?? 0),
            'total_disponible' => floatval($fondos['total_disponible'] ?? 0)
        ]);
    } else {
        // Asegurar que todos los valores sean números válidos
        echo json_encode([
            'saldo_restante' => floatval($fondos['saldo_restante'] ?? $fondos['total_disponible'] ?? 0),
            'total_disponible' => floatval($fondos['total_disponible'] ?? 0),
            'total_ingresos' => floatval($fondos['total_ingresos'] ?? 0),
            'total_egresos' => floatval($fondos['total_egresos'] ?? 0),
            'total_pagos_realizados' => floatval($fondos['total_pagos_realizados'] ?? 0),
            'hay_fondos' => ($fondos['total_disponible'] ?? 0) > 0
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error al calcular fondos: ' . $e->getMessage(),
        'saldo_restante' => 0,
        'total_disponible' => 0
    ]);
}
?>