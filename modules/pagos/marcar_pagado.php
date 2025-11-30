<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: historial_pagos.php");
    exit;
}

$id_pago = intval($_GET['id']);

try {
    $conn->beginTransaction();
    
    // Actualizar estado del pago
    $sql = "UPDATE Pagos_Sueldos_Fijos 
            SET estado = 'pagado',
                fecha_pago = date('now', 'localtime'),
                fecha_actualizacion = datetime('now', 'localtime')
            WHERE id_pago = :id_pago";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_pago', $id_pago);
    $stmt->execute();
    
    // Obtener id_semana del pago para actualizar fondos
    $sql_semana = "SELECT id_semana FROM Pagos_Sueldos_Fijos WHERE id_pago = :id_pago";
    $stmt_semana = $conn->prepare($sql_semana);
    $stmt_semana->bindParam(':id_pago', $id_pago);
    $stmt_semana->execute();
    $id_semana = $stmt_semana->fetchColumn();
    
    if ($id_semana) {
        // Actualizar historial de fondos
        $fondos = calcularFondosDisponibles($conn, $id_semana);
        guardarHistorialFondos($conn, $id_semana, $fondos);
    }
    
    $conn->commit();
    
    header("Location: historial_pagos.php?success=1");
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    header("Location: historial_pagos.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>