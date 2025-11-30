<?php
date_default_timezone_set('America/Mexico_City');
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Recibir datos del formulario
$id_asignacion_origen = isset($_POST['id_asignacion_origen']) ? intval($_POST['id_asignacion_origen']) : 0;
$id_asignacion_destino = isset($_POST['id_asignacion_destino']) ? intval($_POST['id_asignacion_destino']) : 0;
$id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
$cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 0;
$fecha_hora_traspaso = isset($_POST['fecha_hora_traspaso']) ? $_POST['fecha_hora_traspaso'] : '';
$observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

// Validaciones básicas
if ($id_asignacion_origen <= 0) {
    echo json_encode(['success' => false, 'message' => 'Asignación origen inválida']);
    exit;
}

if ($id_asignacion_destino <= 0) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un empleado destino']);
    exit;
}

if ($id_producto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Producto inválido']);
    exit;
}

if ($cantidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'La cantidad debe ser mayor a 0']);
    exit;
}

if (empty($fecha_hora_traspaso)) {
    echo json_encode(['success' => false, 'message' => 'La fecha del traspaso es obligatoria']);
    exit;
}

// Obtener información de la asignación origen
$asignacion_origen = obtenerAsignacion($conn, $id_asignacion_origen);
if (!$asignacion_origen) {
    echo json_encode(['success' => false, 'message' => 'Asignación origen no encontrada']);
    exit;
}

// Obtener información de la asignación destino
$asignacion_destino = obtenerAsignacion($conn, $id_asignacion_destino);
if (!$asignacion_destino) {
    echo json_encode(['success' => false, 'message' => 'Asignación destino no encontrada']);
    exit;
}

// Verificar que ambas asignaciones estén abiertas
if ($asignacion_origen['estado'] !== 'abierta') {
    echo json_encode(['success' => false, 'message' => 'La asignación origen ya está cerrada']);
    exit;
}

if ($asignacion_destino['estado'] !== 'abierta') {
    echo json_encode(['success' => false, 'message' => 'La asignación destino ya está cerrada']);
    exit;
}

// Preparar datos para el registro
$datos_traspaso = [
    'id_asignacion_origen' => $id_asignacion_origen,
    'id_empleado_origen' => $asignacion_origen['id_empleado'],
    'id_asignacion_destino' => $id_asignacion_destino,
    'id_empleado_destino' => $asignacion_destino['id_empleado'],
    'id_producto' => $id_producto,
    'cantidad' => $cantidad,
    'fecha_hora_traspaso' => $fecha_hora_traspaso,
    'registrado_por' => $_SESSION['usuario'],
    'observaciones' => $observaciones
];

// Registrar traspaso
$resultado = registrarTraspaso($conn, $datos_traspaso);

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode($resultado);
?>