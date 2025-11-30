<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Validar token CSRF
function validarCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    
    case 'guardar_registro':
        // Validar CSRF
        if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
            exit;
        }
        
        $datos = [
            'tipo_carga' => $_POST['tipo_carga'],
            'registrado_por' => $_SESSION['usuario'],
            'observaciones' => trim($_POST['observaciones'] ?? '')
        ];
        
        if ($_POST['tipo_carga'] === 'litros') {
            $datos['id_vehiculo'] = intval($_POST['id_vehiculo']);
            $datos['placas'] = trim($_POST['placas']);
            $datos['litros'] = floatval($_POST['litros']);
            $datos['precio_litro'] = floatval($_POST['precio_litro']);
            
            // Validaciones
            if ($datos['id_vehiculo'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar un vehículo válido']);
                exit;
            }
            if ($datos['litros'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'La cantidad de litros debe ser mayor a 0']);
                exit;
            }
            if ($datos['precio_litro'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'El precio por litro debe ser mayor a 0']);
                exit;
            }
        } else {
            $datos['id_empleado'] = intval($_POST['id_empleado']);
            $datos['monto_efectivo'] = floatval($_POST['monto_efectivo']);
            
            // Validaciones
            if ($datos['id_empleado'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Debe seleccionar un empleado válido']);
                exit;
            }
            if ($datos['monto_efectivo'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'El monto de efectivo debe ser mayor a 0']);
                exit;
            }
        }
        
        $resultado = registrarGasolina($datos);
        echo json_encode($resultado);
        break;
    
    case 'obtener_historial':
        $filtros = [
            'fecha_inicio' => $_GET['fecha_inicio'] ?? '',
            'fecha_fin' => $_GET['fecha_fin'] ?? '',
            'tipo' => $_GET['tipo'] ?? ''
        ];
        
        $historial = obtenerHistorial($filtros);
        echo json_encode(['success' => true, 'data' => $historial]);
        break;
    
    case 'obtener_detalle':
        $id_registro = intval($_GET['id_registro'] ?? 0);
        $detalle = obtenerDetalleRegistro($id_registro);
        
        if ($detalle) {
            echo json_encode(['success' => true, 'data' => $detalle]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        }
        break;
    
    case 'eliminar_registro':
        // Validar CSRF
        if (!isset($_POST['csrf_token']) || !validarCSRF($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
            exit;
        }
        
        $id_registro = intval($_POST['id_registro'] ?? 0);
        $resultado = eliminarRegistro($id_registro);
        echo json_encode($resultado);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>