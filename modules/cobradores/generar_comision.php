<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

// Prevenir caché de la página
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validarCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function obtenerMesEspanol($numero_mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    $mes = (int)$numero_mes;
    if ($mes < 1 || $mes > 12) return $numero_mes;
    return $meses[$mes];
}

$mensaje = '';
$tipo_mensaje = '';
$preview_data = null;

// GENERAR COMISIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $id_semana = $_POST['id_semana'];
        $total_gasolina = floatval($_POST['total_gasolina']);
        $observaciones = trim($_POST['observaciones']);
        $extras_montos = isset($_POST['extra_monto']) ? $_POST['extra_monto'] : [];
        $extras_observaciones = isset($_POST['extra_observacion']) ? $_POST['extra_observacion'] : [];
        $prestamo_inhabilitado = floatval($_POST['prestamo_inhabilitado'] ?? 0);
        
        // DEBUG: Log temporal para verificar el valor
        error_log("DEBUG generar_comision.php - prestamo_inhabilitado recibido: " . $prestamo_inhabilitado);
        error_log("DEBUG generar_comision.php - POST prestamo_inhabilitado raw: " . print_r($_POST['prestamo_inhabilitado'] ?? 'NO DEFINIDO', true));
        
        // Validar que prestamo_inhabilitado no sea negativo
        if ($prestamo_inhabilitado < 0) {
            $mensaje = "Error: El monto inhabilitado no puede ser negativo";
            $tipo_mensaje = "error";
        } else {
            try {
                $sql_check_semana = "SELECT fecha_inicio, fecha_fin FROM Semanas_Cobro WHERE id_semana = :id AND activa = 1";
                $stmt_check_semana = $conn->prepare($sql_check_semana);
                $stmt_check_semana->bindParam(':id', $id_semana);
                $stmt_check_semana->execute();
                $semana_info = $stmt_check_semana->fetch(PDO::FETCH_ASSOC);
            
            if (!$semana_info) {
                $mensaje = "Error: Semana no válida o inactiva";
                $tipo_mensaje = "error";
            } else {
                $hoy = date('Y-m-d');
                if ($semana_info['fecha_inicio'] > $hoy) {
                    $mensaje = "Error: No se puede generar comisión para semanas futuras.";
                    $tipo_mensaje = "error";
                } else {
                    $sql_check = "SELECT COUNT(*) FROM Comisiones_Cobradores WHERE id_empleado = :id_empleado AND id_semana = :id_semana";
                    $stmt_check = $conn->prepare($sql_check);
                    $stmt_check->bindParam(':id_empleado', $id_empleado);
                    $stmt_check->bindParam(':id_semana', $id_semana);
                    $stmt_check->execute();
                    $existe = $stmt_check->fetchColumn();
                    
                    if ($existe > 0) {
                        $mensaje = "Error: Ya existe una comisión generada para este empleado en esta semana";
                        $tipo_mensaje = "error";
                    } else {
                        $conn->beginTransaction();
                        try {
                            $sql_zona = "SELECT zona FROM Empleados WHERE id_empleado = :id";
                            $stmt_zona = $conn->prepare($sql_zona);
                            $stmt_zona->bindParam(':id', $id_empleado);
                            $stmt_zona->execute();
                            $zona = $stmt_zona->fetchColumn();
                            
                            $sql_cobros = "SELECT COALESCE(SUM(monto_cobrado), 0) as total FROM Cobros_Diarios 
                                          WHERE id_empleado = :id_empleado AND fecha BETWEEN :fecha_inicio AND :fecha_fin
                                          AND CAST(strftime('%w', fecha) AS INTEGER) BETWEEN 0 AND 5";
                            $stmt_cobros = $conn->prepare($sql_cobros);
                            $stmt_cobros->bindParam(':id_empleado', $id_empleado);
                            $stmt_cobros->bindParam(':fecha_inicio', $semana_info['fecha_inicio']);
                            $stmt_cobros->bindParam(':fecha_fin', $semana_info['fecha_fin']);
                            $stmt_cobros->execute();
                            $total_cobros = floatval($stmt_cobros->fetchColumn()) ?? 0;
                            $comision_cobro = $total_cobros * 0.10;
                            
                            $sql_prestamos = "SELECT COALESCE(SUM(monto), 0) as total_prestamos FROM Prestamos_Empleados
                                             WHERE id_empleado = :id_empleado AND id_semana = :id_semana AND estado = 'activo'";
                            $stmt_prestamos = $conn->prepare($sql_prestamos);
                            $stmt_prestamos->bindParam(':id_empleado', $id_empleado);
                            $stmt_prestamos->bindParam(':id_semana', $id_semana);
                            $stmt_prestamos->execute();
                            $prestamo_total = floatval($stmt_prestamos->fetchColumn()) ?? 0;
                            
                            // Validar que prestamo_inhabilitado no exceda el prestamo_total
                            if ($prestamo_inhabilitado > $prestamo_total) {
                                throw new Exception("El monto a inhabilitar ($" . number_format($prestamo_inhabilitado, 2) . ") no puede ser mayor al préstamo total ($" . number_format($prestamo_total, 2) . ")");
                            }
                            
                            $sql_comision_asignaciones = "SELECT (
                                COALESCE((SELECT SUM(dfv.monto_comision) FROM Detalle_Folio_Venta dfv
                                         INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
                                         INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                                         WHERE a.id_empleado = :id_empleado
                                         AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                                         AND COALESCE(fv.estado, 'activo') = 'activo'
                                         AND COALESCE(dfv.comision_cancelada, 0) = 0), 0) +
                                COALESCE((SELECT SUM(cc.monto_comision) FROM Comisiones_Cancelaciones cc
                                         INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
                                         WHERE cc.id_empleado = :id_empleado
                                         AND DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio AND :fecha_fin), 0)
                            ) as total_comision_asignaciones";
                            $stmt_comision_asig = $conn->prepare($sql_comision_asignaciones);
                            $stmt_comision_asig->bindParam(':id_empleado', $id_empleado);
                            $stmt_comision_asig->bindParam(':fecha_inicio', $semana_info['fecha_inicio']);
                            $stmt_comision_asig->bindParam(':fecha_fin', $semana_info['fecha_fin']);
                            $stmt_comision_asig->execute();
                            $comision_asignaciones = floatval($stmt_comision_asig->fetchColumn()) ?? 0;
                            
                            $total_extras = 0;
                            foreach ($extras_montos as $monto) {
                                $monto = floatval($monto);
                                if ($monto > 0) $total_extras += $monto;
                            }
                            
                            // Calcular total SIN prestamo_inhabilitado (el trigger lo agregará después)
                            $total_comision = $comision_cobro + $total_gasolina - $prestamo_total + $comision_asignaciones + $total_extras;
                            
                            $sql = "INSERT INTO Comisiones_Cobradores 
                                   (id_empleado, id_semana, zona, total_cobros, comision_cobro, comision_asignaciones, 
                                    total_gasolina, total_extras, prestamo, prestamo_inhabilitado, total_comision, observaciones, estado) 
                                   VALUES (:id_empleado, :id_semana, :zona, :total_cobros, :comision_cobro, :comision_asignaciones,
                                          :total_gasolina, :total_extras, :prestamo, 0, :total_comision, :observaciones, 'pendiente')";
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(':id_empleado', $id_empleado);
                            $stmt->bindParam(':id_semana', $id_semana);
                            $stmt->bindParam(':zona', $zona);
                            $stmt->bindParam(':total_cobros', $total_cobros);
                            $stmt->bindParam(':comision_cobro', $comision_cobro);
                            $stmt->bindParam(':comision_asignaciones', $comision_asignaciones);
                            $stmt->bindParam(':total_gasolina', $total_gasolina);
                            $stmt->bindParam(':total_extras', $total_extras);
                            $stmt->bindParam(':prestamo', $prestamo_total);
                            $stmt->bindParam(':total_comision', $total_comision);
                            $stmt->bindParam(':observaciones', $observaciones);
                            $stmt->execute();
                            
                            $id_comision = $conn->lastInsertId();
                            
                            // Registrar préstamo inhabilitado si existe
                            if ($prestamo_inhabilitado > 0) {
                                try {
                                    $obs_inhabilitado = "Préstamo inhabilitado al generar comisión";
                                    $usuario = $_SESSION['usuario'];
                                    $sql_inhabilitado = "INSERT INTO Prestamos_Inhabilitados (id_comision, monto_inhabilitado, observaciones, registrado_por) 
                                                        VALUES (:id_comision, :monto, :obs, :usuario)";
                                    $stmt_inh = $conn->prepare($sql_inhabilitado);
                                    $stmt_inh->bindParam(':id_comision', $id_comision);
                                    $stmt_inh->bindParam(':monto', $prestamo_inhabilitado);
                                    $stmt_inh->bindParam(':obs', $obs_inhabilitado);
                                    $stmt_inh->bindParam(':usuario', $usuario);
                                    $stmt_inh->execute();
                                    
                                    // FORZAR actualización manual (no depender del trigger)
                                    $sql_forzar_update = "UPDATE Comisiones_Cobradores 
                                                         SET prestamo_inhabilitado = (
                                                             SELECT COALESCE(SUM(monto_inhabilitado), 0)
                                                             FROM Prestamos_Inhabilitados
                                                             WHERE id_comision = :id_comision
                                                         ),
                                                         total_comision = comision_cobro + COALESCE(comision_asignaciones, 0) + COALESCE(total_extras, 0) 
                                                                        + total_gasolina - prestamo 
                                                                        + (SELECT COALESCE(SUM(monto_inhabilitado), 0) FROM Prestamos_Inhabilitados WHERE id_comision = :id_comision)
                                                         WHERE id_comision = :id_comision";
                                    $stmt_forzar = $conn->prepare($sql_forzar_update);
                                    $stmt_forzar->bindParam(':id_comision', $id_comision);
                                    $stmt_forzar->execute();
                                    
                                    // Verificar que se insertó
                                    $sql_verificar = "SELECT monto_inhabilitado FROM Prestamos_Inhabilitados WHERE id_comision = :id";
                                    $stmt_verificar = $conn->prepare($sql_verificar);
                                    $stmt_verificar->bindParam(':id', $id_comision);
                                    $stmt_verificar->execute();
                                    $verificacion = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
                                    
                                    if (!$verificacion) {
                                        error_log("ERROR: No se insertó el préstamo inhabilitado para comisión $id_comision");
                                    }
                                } catch (Exception $e) {
                                    error_log("ERROR al insertar préstamo inhabilitado: " . $e->getMessage());
                                    throw $e;
                                }
                            }
                            
                            if ($total_extras > 0) {
                                foreach ($extras_montos as $index => $monto) {
                                    $monto = floatval($monto);
                                    if ($monto > 0) {
                                        $obs_extra = isset($extras_observaciones[$index]) ? trim($extras_observaciones[$index]) : '';
                                        $sql_insert_extra = "INSERT INTO Extras_Comision (id_comision, monto, observaciones) 
                                                           VALUES (:id_comision, :monto, :observaciones)";
                                        $stmt_insert = $conn->prepare($sql_insert_extra);
                                        $stmt_insert->bindParam(':id_comision', $id_comision);
                                        $stmt_insert->bindParam(':monto', $monto);
                                        $stmt_insert->bindParam(':observaciones', $obs_extra);
                                        $stmt_insert->execute();
                                    }
                                }
                            }
                            
                            if ($prestamo_total > 0) {
                                $sql_pagar_prestamos = "UPDATE Prestamos_Empleados SET estado = 'pagado', monto_pendiente = 0
                                                       WHERE id_empleado = :id_empleado AND id_semana = :id_semana AND estado = 'activo'";
                                $stmt_pagar = $conn->prepare($sql_pagar_prestamos);
                                $stmt_pagar->bindParam(':id_empleado', $id_empleado);
                                $stmt_pagar->bindParam(':id_semana', $id_semana);
                                $stmt_pagar->execute();
                            }
                            
                            $conn->commit();
                            
                            // Obtener el total actualizado después del trigger
                            $sql_total_actualizado = "SELECT total_comision, prestamo_inhabilitado FROM Comisiones_Cobradores WHERE id_comision = :id";
                            $stmt_total = $conn->prepare($sql_total_actualizado);
                            $stmt_total->bindParam(':id', $id_comision);
                            $stmt_total->execute();
                            $datos_actualizados = $stmt_total->fetch(PDO::FETCH_ASSOC);
                            $total_final = $datos_actualizados['total_comision'];
                            $prestamo_inh_final = $datos_actualizados['prestamo_inhabilitado'];
                            
                            $mensaje_extra = "";
                            if ($prestamo_total > 0) {
                                $prestamo_descontado = $prestamo_total - $prestamo_inh_final;
                                $mensaje_extra = " | Préstamo: $" . number_format($prestamo_total, 2);
                                if ($prestamo_inh_final > 0) {
                                    $mensaje_extra .= " (Inhabilitado: $" . number_format($prestamo_inh_final, 2) . ", Descontado: $" . number_format($prestamo_descontado, 2) . ")";
                                }
                            }
                            
                            $mensaje = "✅ Comisión generada exitosamente. Total: $" . number_format($total_final, 2) . $mensaje_extra;
                            $tipo_mensaje = "success";
$redirigir_a_comisiones = true;
                        } catch (Exception $e) {
                            $conn->rollBack();
                            throw $e;
                        }
                    }
                }
            }
            } catch (PDOException $e) {
                $mensaje = "Error al generar comisión: " . $e->getMessage();
                $tipo_mensaje = "error";
            } catch (Exception $e) {
                $mensaje = "Error: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } // Cierre del else de validación
    }
}

// PREVIEW DE COMISIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $id_empleado = $_POST['id_empleado'];
        $id_semana = $_POST['id_semana'];
        
        try {
            $sql_check = "SELECT COUNT(*) FROM Comisiones_Cobradores WHERE id_empleado = :id_empleado AND id_semana = :id_semana";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bindParam(':id_empleado', $id_empleado);
            $stmt_check->bindParam(':id_semana', $id_semana);
            $stmt_check->execute();
            $existe = $stmt_check->fetchColumn();
            
            if ($existe > 0) {
                $mensaje = "⚠️ Ya existe una comisión generada para este empleado en esta semana. <a href='index.php' style='color: white; text-decoration: underline;'>Ver comisiones</a>";
                $tipo_mensaje = "warning";
            } else {
                $sql_empleado = "SELECT * FROM Empleados WHERE id_empleado = :id";
                $stmt_empleado = $conn->prepare($sql_empleado);
                $stmt_empleado->bindParam(':id', $id_empleado);
                $stmt_empleado->execute();
                $empleado = $stmt_empleado->fetch(PDO::FETCH_ASSOC);
                
                $sql_semana = "SELECT * FROM Semanas_Cobro WHERE id_semana = :id";
                $stmt_semana = $conn->prepare($sql_semana);
                $stmt_semana->bindParam(':id', $id_semana);
                $stmt_semana->execute();
                $semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);
                $semana['mes'] = obtenerMesEspanol($semana['mes']);
                
                $sql_cobros_diarios = "SELECT fecha,
                    CASE CAST(strftime('%w', fecha) AS INTEGER)
                        WHEN 0 THEN 'Domingo' WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes'
                        WHEN 3 THEN 'Miércoles' WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes'
                    END as dia_nombre, monto_cobrado, clientes_visitados
                    FROM Cobros_Diarios WHERE id_empleado = :id_empleado
                    AND fecha BETWEEN :fecha_inicio AND :fecha_fin
                    AND CAST(strftime('%w', fecha) AS INTEGER) BETWEEN 0 AND 5 ORDER BY fecha";
                $stmt_cobros_diarios = $conn->prepare($sql_cobros_diarios);
                $stmt_cobros_diarios->bindParam(':id_empleado', $id_empleado);
                $stmt_cobros_diarios->bindParam(':fecha_inicio', $semana['fecha_inicio']);
                $stmt_cobros_diarios->bindParam(':fecha_fin', $semana['fecha_fin']);
                $stmt_cobros_diarios->execute();
                $cobros_diarios = $stmt_cobros_diarios->fetchAll(PDO::FETCH_ASSOC);
                
                $total_cobros = 0;
                foreach ($cobros_diarios as $cobro) {
                    $total_cobros += $cobro['monto_cobrado'];
                }
                $comision_cobro = $total_cobros * 0.10;
                
                $sql_prestamos = "SELECT * FROM Prestamos_Empleados WHERE id_empleado = :id_empleado
                                 AND id_semana = :id_semana AND estado = 'activo' ORDER BY fecha_prestamo";
                $stmt_prestamos = $conn->prepare($sql_prestamos);
                $stmt_prestamos->bindParam(':id_empleado', $id_empleado);
                $stmt_prestamos->bindParam(':id_semana', $id_semana);
                $stmt_prestamos->execute();
                $prestamos = $stmt_prestamos->fetchAll(PDO::FETCH_ASSOC);
                
                $prestamo_total = 0;
                foreach ($prestamos as $prestamo) {
                    $prestamo_total += $prestamo['monto'];
                }
                
                $sql_comision_asignaciones = "SELECT (
                    COALESCE((SELECT SUM(dfv.monto_comision) FROM Detalle_Folio_Venta dfv
                             INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
                             INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                             WHERE a.id_empleado = :id_empleado
                             AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                             AND COALESCE(fv.estado, 'activo') = 'activo'
                             AND COALESCE(dfv.comision_cancelada, 0) = 0), 0) +
                    COALESCE((SELECT SUM(cc.monto_comision) FROM Comisiones_Cancelaciones cc
                             INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
                             WHERE cc.id_empleado = :id_empleado
                             AND DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio AND :fecha_fin), 0)
                ) as total_comision_asignaciones";
                $stmt_comision_asig = $conn->prepare($sql_comision_asignaciones);
                $stmt_comision_asig->bindParam(':id_empleado', $id_empleado);
                $stmt_comision_asig->bindParam(':fecha_inicio', $semana['fecha_inicio']);
                $stmt_comision_asig->bindParam(':fecha_fin', $semana['fecha_fin']);
                $stmt_comision_asig->execute();
                $comision_asignaciones = floatval($stmt_comision_asig->fetchColumn()) ?? 0;
                
                // Obtener detalle de comisiones por ventas
                $sql_detalle_ventas = "
                    SELECT 
                        fv.numero_folio,
                        fv.nombre_cliente,
                        fv.fecha_hora_venta,
                        p.nombre as producto,
                        dfv.cantidad_vendida,
                        dfv.precio_unitario,
                        dfv.porcentaje_comision,
                        dfv.monto_comision
                    FROM Detalle_Folio_Venta dfv
                    INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
                    INNER JOIN Asignaciones a ON fv.id_asignacion = a.id_asignacion
                    INNER JOIN Productos p ON dfv.id_producto = p.id_producto
                    WHERE a.id_empleado = :id_empleado
                    AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                    AND COALESCE(fv.estado, 'activo') = 'activo'
                    AND COALESCE(dfv.comision_cancelada, 0) = 0
                    ORDER BY fv.fecha_hora_venta DESC
                ";
                $stmt_detalle_ventas = $conn->prepare($sql_detalle_ventas);
                $stmt_detalle_ventas->bindParam(':id_empleado', $id_empleado);
                $stmt_detalle_ventas->bindParam(':fecha_inicio', $semana['fecha_inicio']);
                $stmt_detalle_ventas->bindParam(':fecha_fin', $semana['fecha_fin']);
                $stmt_detalle_ventas->execute();
                $comisiones_asignaciones_detalle = $stmt_detalle_ventas->fetchAll(PDO::FETCH_ASSOC);
                
                // Obtener comisiones reasignadas
                $sql_reasignadas = "
                    SELECT 
                        fv.numero_folio,
                        fv.nombre_cliente,
                        cf.fecha_cancelacion,
                        p.nombre as producto,
                        cc.monto_comision
                    FROM Comisiones_Cancelaciones cc
                    INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
                    INNER JOIN Folios_Venta fv ON cf.id_folio = fv.id_folio
                    INNER JOIN Productos p ON cc.id_producto = p.id_producto
                    WHERE cc.id_empleado = :id_empleado
                    AND DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio AND :fecha_fin
                    ORDER BY cf.fecha_cancelacion DESC
                ";
                $stmt_reasignadas = $conn->prepare($sql_reasignadas);
                $stmt_reasignadas->bindParam(':id_empleado', $id_empleado);
                $stmt_reasignadas->bindParam(':fecha_inicio', $semana['fecha_inicio']);
                $stmt_reasignadas->bindParam(':fecha_fin', $semana['fecha_fin']);
                $stmt_reasignadas->execute();
                $comisiones_reasignadas = $stmt_reasignadas->fetchAll(PDO::FETCH_ASSOC);
                
                $preview_data = [
                    'empleado' => $empleado,
                    'semana' => $semana,
                    'cobros_diarios' => $cobros_diarios,
                    'total_cobros' => $total_cobros,
                    'comision_cobro' => $comision_cobro,
                    'comision_asignaciones' => $comision_asignaciones,
                    'comisiones_asignaciones_detalle' => $comisiones_asignaciones_detalle,
                    'comisiones_reasignadas' => $comisiones_reasignadas,
                    'prestamos' => $prestamos,
                    'prestamo_total' => $prestamo_total
                ];
            }
        } catch (PDOException $e) {
            $mensaje = "Error al calcular comisión: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

$query_empleados = "SELECT id_empleado, nombre || ' ' || apellido_paterno || ' ' || apellido_materno as nombre_completo, zona, rol
                    FROM Empleados WHERE estado = 'activo' AND rol = 'cobrador' ORDER BY nombre";
$stmt_empleados = $conn->prepare($query_empleados);
$stmt_empleados->execute();
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

$query_semanas = "SELECT *, CASE 
    WHEN fecha_inicio <= date('now') AND fecha_fin >= date('now') THEN 'ACTUAL'
    WHEN fecha_inicio < date('now') THEN 'ANTERIOR'
    WHEN fecha_inicio > date('now') THEN 'SIGUIENTE'
    END as tipo,
    CASE WHEN fecha_inicio > date('now') THEN 0 ELSE 1 END as generable
    FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC LIMIT 3";
$stmt_semanas = $conn->prepare($query_semanas);
$stmt_semanas->execute();
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

foreach ($semanas as &$sem) {
    $sem['mes'] = obtenerMesEspanol($sem['mes']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Generar Comisión - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        .detail-item label {
            font-size: 12px;
            color: var(--text-muted);
            display: block;
            margin-bottom: 5px;
        }
        .detail-item .value {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .tabla-profesional {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .tabla-profesional thead {
            background: linear-gradient(135deg, #0c3c78 0%, #1e5799 100%);
            color: white;
        }
        .tabla-profesional thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .tabla-profesional tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }
        .tabla-profesional tbody tr:hover {
            background: #f8f9fa;
        }
        .tabla-profesional tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: #333;
        }
        .tabla-profesional tbody tr:last-child {
            border-bottom: none;
        }
        .tabla-profesional .fila-total {
            background: #f0f7ff;
            font-weight: 600;
            border-top: 2px solid #0c3c78;
        }
        .tabla-profesional .fila-total td {
            padding: 16px;
            font-size: 15px;
            color: #0c3c78;
        }
        .monto-positivo {
            color: #4CAF50;
            font-weight: 600;
        }

        .extras-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
        .extras-section h3 {
            color: #333;
            font-size: 18px;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .extras-section h3 i {
            color: #10B981;
            font-size: 22px;
        }
        .extra-item {
            display: grid;
            grid-template-columns: 150px 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            align-items: start;
        }
        .extra-item input[type="number"],
        .extra-item input[type="text"] {
            padding: 8px 12px;
        }
        .btn-remove-extra {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .btn-remove-extra:hover {
            background: #dc2626;
        }
        .btn-add-extra {
            background: #10B981;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-add-extra:hover {
            background: #059669;
        }
        .extras-info {
            background: #E0F2FE;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #0284C7;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0c4a6e;
        }
        .extras-info i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header no-print">
            <h1><i class='bx bx-plus-circle'></i> Generar Comisión Semanal</h1>
            <div class="header-actions">
                <a href="#" onclick="volverIndex(); return false;" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Volver
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>" id="mensaje">
            <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>

        <?php if (!$preview_data): ?>
            <div class="card">
                <h2><i class='bx bx-search'></i> Seleccionar Empleado y Semana</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="preview">

                    <?php
                    $semana_actual = null;
                    foreach ($semanas as $sem) {
                        if ($sem['tipo'] === 'ACTUAL') {
                            $semana_actual = $sem;
                            break;
                        }
                    }
                    if (!$semana_actual) {
                        foreach ($semanas as $sem) {
                            if ($sem['generable']) {
                                $semana_actual = $sem;
                                break;
                            }
                        }
                    }
                    ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="id_empleado"><i class='bx bx-user'></i> Empleado *</label>
                            <select name="id_empleado" id="id_empleado" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?php echo $emp['id_empleado']; ?>">
                                        <?php echo htmlspecialchars(trim($emp['nombre_completo'])); ?> - 
                                        <?php echo ucfirst($emp['rol']); ?> (<?php echo $emp['zona']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="semana_info"><i class='bx bx-calendar-week'></i> Semana de Pago</label>
                            <?php if ($semana_actual): ?>
                                <input type="hidden" name="id_semana" value="<?php echo $semana_actual['id_semana']; ?>">
                                <div style="padding: 15px; background: #E3F2FD; border-radius: 8px; border-left: 4px solid #1976D2;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class='bx bx-calendar-check' style="font-size: 24px; color: #1976D2;"></i>
                                        <div>
                                            <div style="font-size: 16px; font-weight: 600; color: #0d47a1;">
                                                <?php echo $semana_actual['mes'] . ' ' . $semana_actual['anio']; ?> - Semana <?php echo $semana_actual['numero_semana']; ?>
                                            </div>
                                            <div style="font-size: 14px; color: #1565c0; margin-top: 2px;">
                                                <?php echo date('d/m/Y', strtotime($semana_actual['fecha_inicio'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($semana_actual['fecha_fin'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <small class="form-text" style="margin-top: 8px; display: block;">
                                    <i class='bx bx-info-circle'></i> La comisión se generará automáticamente para la semana actual
                                </small>
                            <?php else: ?>
                                <div class="alert alert-error">
                                    <i class='bx bx-error'></i> No hay semana activa disponible. Contacta al administrador.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-search'></i> Calcular Comisión
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Información de la Semana -->
            <div class="card">
                <h2><i class='bx bx-calendar-week'></i> Información de la Semana</h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Mes y Año</label>
                        <div class="value"><?php echo $preview_data['semana']['mes'] . ' ' . $preview_data['semana']['anio']; ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Número de Semana</label>
                        <div class="value">Semana <?php echo $preview_data['semana']['numero_semana']; ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Período</label>
                        <div class="value">
                            <?php echo date('d/m/Y', strtotime($preview_data['semana']['fecha_inicio'])); ?> - 
                            <?php echo date('d/m/Y', strtotime($preview_data['semana']['fecha_fin'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Empleado -->
            <div class="card">
                <h2><i class='bx bx-user'></i> Información del Empleado</h2>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Nombre Completo</label>
                        <div class="value"><?php echo htmlspecialchars($preview_data['empleado']['nombre'] . ' ' . $preview_data['empleado']['apellido_paterno']); ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Teléfono</label>
                        <div class="value"><?php echo $preview_data['empleado']['telefono']; ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Zona Asignada</label>
                        <div class="value">
                            <span class="zona-badge"><?php echo $preview_data['empleado']['zona']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desglose de Cobros Diarios -->
            <div class="card">
                <h2><i class='bx bx-calendar-check'></i> Desglose de Cobros (Domingo a Viernes)</h2>
                
                <?php if (count($preview_data['cobros_diarios']) > 0): ?>
                    <table class="tabla-profesional">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Monto Cobrado</th>
                                <th>Clientes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data['cobros_diarios'] as $cobro): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($cobro['fecha'])); ?></td>
                                    <td><span class="badge-info"><?php echo $cobro['dia_nombre']; ?></span></td>
                                    <td class="monto-positivo">$<?php echo number_format($cobro['monto_cobrado'], 2); ?></td>
                                    <td><?php echo $cobro['clientes_visitados']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fila-total">
                                <td colspan="2" style="text-align: right;">TOTAL COBRADO:</td>
                                <td colspan="2">$<?php echo number_format($preview_data['total_cobros'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #f44336; margin-top: 15px;">⚠️ No hay cobros registrados para esta semana</p>
                <?php endif; ?>
            </div>

            <!-- Comisiones de Asignaciones (Ventas) -->
            <div class="card">
                <h2><i class='bx bx-shopping-bag'></i> Comisiones por Ventas</h2>
                
                <?php if (count($preview_data['comisiones_asignaciones_detalle']) > 0 || count($preview_data['comisiones_reasignadas']) > 0): ?>
                    <table class="tabla-profesional">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data['comisiones_asignaciones_detalle'] as $ca): ?>
                                <tr>
                                    <td><span class="badge-folio"><?php echo $ca['numero_folio']; ?></span></td>
                                    <td><?php echo htmlspecialchars($ca['nombre_cliente']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ca['fecha_hora_venta'])); ?></td>
                                    <td><?php echo htmlspecialchars($ca['producto']); ?></td>
                                    <td><?php echo $ca['cantidad_vendida']; ?></td>
                                    <td class="monto-positivo">$<?php echo number_format($ca['monto_comision'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php foreach ($preview_data['comisiones_reasignadas'] as $cr): ?>
                                <tr class="fila-reasignada">
                                    <td><span class="badge-folio"><?php echo $cr['numero_folio']; ?></span> <span class="badge-reasignada">Reasignada</span></td>
                                    <td><?php echo htmlspecialchars($cr['nombre_cliente']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cr['fecha_cancelacion'])); ?></td>
                                    <td><?php echo htmlspecialchars($cr['producto']); ?></td>
                                    <td>-</td>
                                    <td class="monto-positivo">$<?php echo number_format($cr['monto_comision'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr class="fila-total">
                                <td colspan="5" style="text-align: right;">TOTAL COMISIÓN POR VENTAS:</td>
                                <td>$<?php echo number_format($preview_data['comision_asignaciones'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666; margin-top: 15px;">
                        <i class='bx bx-info-circle'></i> No hay ventas registradas en esta semana
                    </p>
                <?php endif; ?>
            </div>

            <!-- Préstamos de la Semana -->
            <div class="card">
                <h2><i class='bx bx-wallet'></i> Préstamos de la Semana</h2>
                
                <?php if (count($preview_data['prestamos']) > 0): ?>
                    <div style="margin: 15px 0; padding: 15px; background: #FFF3E0; border-radius: 8px; border-left: 4px solid #FF9800;">
                        <p style="margin: 0; font-size: 14px;">
                            <i class='bx bx-info-circle' style="color: #FF9800;"></i>
                            <strong>Total de préstamos activos: $<?php echo number_format($preview_data['prestamo_total'], 2); ?></strong>
                        </p>
                        <p style="margin: 10px 0 0 0; font-size: 13px; color: #E65100;">
                            Estos préstamos se descontarán automáticamente y se marcarán como pagados.
                        </p>
                    </div>
                    
                    <table class="tabla-profesional">
                        <thead>
                            <tr>
                                <th>Fecha Préstamo</th>
                                <th>Monto</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data['prestamos'] as $prestamo): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                    <td class="monto-positivo">$<?php echo number_format($prestamo['monto'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($prestamo['motivo'] ?? 'Sin motivo'); ?></td>
                                    <td><span class="estado-badge <?php echo $prestamo['estado']; ?>"><?php echo ucfirst($prestamo['estado']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #4CAF50; margin-top: 15px;">✅ No hay préstamos pendientes para esta semana</p>
                <?php endif; ?>
            </div>

            <!-- INHABILITAR PRÉSTAMO -->
            <?php if (count($preview_data['prestamos']) > 0): ?>
            <div class="card">
                <h2><i class='bx bx-shield-x'></i> Inhabilitar Préstamo (Opcional)</h2>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px; border-left: 4px solid var(--primary-color);">
                    <div class="form-group" style="margin: 0;">
                        <label for="prestamo_inhabilitado" style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                            <i class='bx bx-dollar-circle' style="color: var(--primary-color);"></i>
                            <strong>Monto que absorberá la empresa</strong>
                        </label>
                        <input type="number" 
                               name="prestamo_inhabilitado" 
                               id="prestamo_inhabilitado" 
                               step="0.01" 
                               min="0"
                               max="<?php echo $preview_data['prestamo_total']; ?>"
                               value="0.00"
                               oninput="calcularTotalFinal()"
                               placeholder="0.00">
                        <small class="form-text" style="display: block; margin-top: 8px;">
                            <i class='bx bx-info-circle'></i> El monto inhabilitado NO se descuenta al empleado. Máximo: $<?php echo number_format($preview_data['prestamo_total'], 2); ?>
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="button" class="btn btn-secondary" onclick="inhabilitarTodo()" style="font-size: 14px;">
                            <i class='bx bx-shield-x'></i> Inhabilitar Todo ($<?php echo number_format($preview_data['prestamo_total'], 2); ?>)
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="limpiarInhabilitado()" style="font-size: 14px;">
                            <i class='bx bx-x'></i> Limpiar
                        </button>
                    </div>
                    
                    <div id="info_inhabilitado" style="display: none; margin-top: 15px; padding: 12px; background: #E3F2FD; border-radius: 6px; font-size: 13px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <strong>Préstamo Total:</strong> $<?php echo number_format($preview_data['prestamo_total'], 2); ?>
                            </div>
                            <div>
                                <strong style="color: var(--danger-color);">Inhabilitado:</strong> <span style="color: var(--danger-color);">$<span id="monto_inhabilitado_display">0.00</span></span>
                            </div>
                            <div>
                                <strong style="color: var(--primary-color);">A Descontar:</strong> <span style="color: var(--primary-color);">$<span id="prestamo_descontado_display"><?php echo number_format($preview_data['prestamo_total'], 2); ?></span></span>
                            </div>
                            <div>
                                <strong style="color: var(--danger-color);">Empresa Absorbe:</strong> <span style="color: var(--danger-color);">$<span id="empresa_absorbe_display">0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- FORMULARIO DE CÁLCULO FINAL -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="generar">
                <input type="hidden" name="id_empleado" value="<?php echo $preview_data['empleado']['id_empleado']; ?>">
                <input type="hidden" name="id_semana" value="<?php echo $preview_data['semana']['id_semana']; ?>">
                <!-- Campo hidden para prestamo_inhabilitado que se actualiza con JavaScript -->
                <input type="hidden" name="prestamo_inhabilitado" id="prestamo_inhabilitado_hidden" value="0">

                <!-- Gasolina de la Semana -->
                <div class="card">
                    <h2><i class='bx bxs-gas-pump'></i> Gasolina de la Semana</h2>
                    
                    <div style="margin-top: 20px; padding: 20px; background: #FFF3E0; border-radius: 8px; border-left: 4px solid #FF9800;">
                        <div class="form-group" style="margin: 0;">
                            <label for="total_gasolina" style="margin-bottom: 10px;">
                                <i class='bx bxs-gas-pump'></i> Monto en Efectivo *
                            </label>
                            <input type="number" 
                                   name="total_gasolina" 
                                   id="total_gasolina" 
                                   step="0.01" 
                                   min="0"
                                   value="0.00"
                                   required
                                   oninput="calcularTotalFinal()">
                            <small class="form-text" style="display: block; margin-top: 8px;">
                                Gasolina capturada al generar la comisión
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Montos Extras -->
                <div class="card">
                    <h2><i class='bx bx-dollar-circle'></i> Montos Extras</h2>
                    
                    <div class="extras-section">
                        <div class="extras-info">
                            <i class='bx bx-info-circle'></i>
                            <strong>Agrega cantidades extra</strong> para bonificaciones, compensaciones u otros conceptos. Las observaciones son opcionales.
                        </div>
                        
                        <div id="extras-container">
                            <!-- Los extras se agregarán dinámicamente aquí -->
                        </div>
                        
                        <button type="button" class="btn-add-extra" onclick="agregarExtra()">
                            <i class='bx bx-plus-circle'></i> Agregar Extra
                        </button>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="card">
                    <h2><i class='bx bx-note'></i> Observaciones Generales</h2>
                    <div class="form-group" style="margin-top: 15px;">
                        <textarea name="observaciones" id="observaciones" rows="3" maxlength="500" placeholder="Observaciones opcionales sobre esta comisión..."></textarea>
                    </div>
                </div>

                <!-- Cálculo de Comisión -->
                <div class="card">
                    <h2><i class='bx bx-calculator'></i> Cálculo de Comisión</h2>
                    
                    <table style="width: 100%; font-size: 16px; margin-top: 20px;">
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-dollar' style="color: var(--primary-color);"></i>
                                <strong>Total Cobrado (Dom-Vie)</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px;">
                                $<?php echo number_format($preview_data['total_cobros'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-trending-up' style="color: var(--success-color);"></i>
                                <strong>Comisión por Cobros (10%)</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<?php echo number_format($preview_data['comision_cobro'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color); background: #f8f9fa;">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-shopping-bag' style="color: var(--success-color);"></i>
                                <strong>Comisión por Ventas</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<?php echo number_format($preview_data['comision_asignaciones'], 2); ?>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bxs-gas-pump' style="color: var(--success-color);"></i>
                                <strong>Gasolina</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<span id="display_gasolina">0.00</span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-dollar-circle' style="color: var(--success-color);"></i>
                                <strong>Extras</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--success-color);">
                                +$<span id="display_extras">0.00</span>
                            </td>
                        </tr>
                        <?php if ($preview_data['prestamo_total'] > 0): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-wallet' style="color: var(--danger-color);"></i>
                                <strong>Préstamo</strong>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--danger-color);">
                                -$<?php echo number_format($preview_data['prestamo_total'], 2); ?>
                            </td>
                        </tr>
                        <tr id="fila_prestamo_inhabilitado" style="display: none; border-bottom: 1px solid var(--border-color); background: #E3F2FD;">
                            <td style="padding: 15px 0;">
                                <i class='bx bx-shield-x' style="color: var(--danger-color);"></i>
                                <strong>Préstamo Inhabilitado</strong>
                                <br><small style="color: #666; font-weight: normal;">Absorbe la empresa</small>
                            </td>
                            <td style="padding: 15px 0; text-align: right; font-size: 20px; color: var(--danger-color);">
                                +$<span id="display_prestamo_inhabilitado">0.00</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr style="border-top: 3px solid var(--text-dark); background: #f8f9fa;">
                            <td style="padding: 20px 0;">
                                <i class='bx bx-check-circle' style="color: var(--primary-color);"></i>
                                <strong style="font-size: 18px;">TOTAL A PAGAR</strong>
                            </td>
                            <td style="padding: 20px 0; text-align: right; font-size: 28px; font-weight: bold; color: var(--primary-color);">
                                $<span id="display_total"><?php 
                                    $total_inicial = $preview_data['comision_cobro'] + 
                                                    $preview_data['comision_asignaciones'] - 
                                                    $preview_data['prestamo_total'];
                                    echo number_format($total_inicial, 2); 
                                ?></span>
                            </td>
                        </tr>
                    </table>

                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 15px 30px;">
                            <i class='bx bx-check-circle'></i> Generar Comisión
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelarYRegresar()">
                            <i class='bx bx-x'></i> Cancelar
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const comision = <?php echo isset($preview_data['comision_cobro']) ? $preview_data['comision_cobro'] : 0; ?>;
        const comisionAsignaciones = <?php echo isset($preview_data['comision_asignaciones']) ? $preview_data['comision_asignaciones'] : 0; ?>;
        const prestamos = <?php echo isset($preview_data['prestamo_total']) ? $preview_data['prestamo_total'] : 0; ?>;

        function agregarExtra() {
            const container = document.getElementById('extras-container');
            const extraItem = document.createElement('div');
            extraItem.className = 'extra-item';
            extraItem.innerHTML = `
                <input type="number" 
                       name="extra_monto[]" 
                       placeholder="$0.00" 
                       step="0.01" 
                       min="0"
                       oninput="calcularTotalFinal()">
                <input type="text" 
                       name="extra_observacion[]" 
                       placeholder="Observaciones (opcional)"
                       maxlength="200">
                <button type="button" class="btn-remove-extra" onclick="eliminarExtra(this)">
                    <i class='bx bx-trash'></i>
                </button>
            `;
            container.appendChild(extraItem);
            calcularTotalFinal();
        }

        function eliminarExtra(button) {
            button.closest('.extra-item').remove();
            calcularTotalFinal();
        }

        function calcularTotalFinal() {
            const gasolinaInput = document.getElementById('total_gasolina');
            const gasolina = gasolinaInput ? parseFloat(gasolinaInput.value) || 0 : 0;
            
            let totalExtras = 0;
            const extrasInputs = document.querySelectorAll('input[name="extra_monto[]"]');
            extrasInputs.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                totalExtras += valor;
            });
            
            const prestamoInhabilitadoInput = document.getElementById('prestamo_inhabilitado');
            const prestamoInhabilitado = prestamoInhabilitadoInput ? parseFloat(prestamoInhabilitadoInput.value) || 0 : 0;
            
            // ACTUALIZAR EL CAMPO HIDDEN CON EL VALOR DEL PRÉSTAMO INHABILITADO
            const hiddenField = document.getElementById('prestamo_inhabilitado_hidden');
            if (hiddenField) {
                hiddenField.value = prestamoInhabilitado.toFixed(2);
                console.log('Campo hidden actualizado:', hiddenField.value);
            }
            
            const total = comision + comisionAsignaciones + gasolina + totalExtras - prestamos + prestamoInhabilitado;
            
            const displayGasolina = document.getElementById('display_gasolina');
            const displayExtras = document.getElementById('display_extras');
            const displayPrestamoInhabilitado = document.getElementById('display_prestamo_inhabilitado');
            const displayTotal = document.getElementById('display_total');
            const filaPrestamoInhabilitado = document.getElementById('fila_prestamo_inhabilitado');
            const infoInhabilitado = document.getElementById('info_inhabilitado');
            
            if (displayGasolina) displayGasolina.textContent = gasolina.toFixed(2);
            if (displayExtras) displayExtras.textContent = totalExtras.toFixed(2);
            if (displayTotal) displayTotal.textContent = total.toFixed(2);
            
            // Mostrar u ocultar fila de préstamo inhabilitado
            if (prestamoInhabilitado > 0) {
                if (filaPrestamoInhabilitado) filaPrestamoInhabilitado.style.display = 'table-row';
                if (displayPrestamoInhabilitado) displayPrestamoInhabilitado.textContent = prestamoInhabilitado.toFixed(2);
                
                // Mostrar info de resumen
                if (infoInhabilitado) {
                    infoInhabilitado.style.display = 'block';
                    const prestamoDescontado = prestamos - prestamoInhabilitado;
                    document.getElementById('monto_inhabilitado_display').textContent = prestamoInhabilitado.toFixed(2);
                    document.getElementById('prestamo_descontado_display').textContent = prestamoDescontado.toFixed(2);
                    document.getElementById('empresa_absorbe_display').textContent = prestamoInhabilitado.toFixed(2);
                }
            } else {
                if (filaPrestamoInhabilitado) filaPrestamoInhabilitado.style.display = 'none';
                if (infoInhabilitado) infoInhabilitado.style.display = 'none';
            }
        }
        
        function inhabilitarTodo() {
            const prestamoInhabilitadoInput = document.getElementById('prestamo_inhabilitado');
            if (prestamoInhabilitadoInput && prestamos > 0) {
                prestamoInhabilitadoInput.value = prestamos.toFixed(2);
                // Actualizar también el campo hidden
                const hiddenField = document.getElementById('prestamo_inhabilitado_hidden');
                if (hiddenField) {
                    hiddenField.value = prestamos.toFixed(2);
                }
                calcularTotalFinal();
            }
        }
        
        function limpiarInhabilitado() {
            const prestamoInhabilitadoInput = document.getElementById('prestamo_inhabilitado');
            if (prestamoInhabilitadoInput) {
                prestamoInhabilitadoInput.value = '0.00';
                // Actualizar también el campo hidden
                const hiddenField = document.getElementById('prestamo_inhabilitado_hidden');
                if (hiddenField) {
                    hiddenField.value = '0.00';
                }
                calcularTotalFinal();
            }
        }

        function volverIndex() {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'navigate', 
                    page: 'index.php',
                    fullUrl: 'index.php'
                }, '*');
            } else {
                window.location.href = 'index.php';
            }
        }

        function cancelarYRegresar() {
            window.location.href = 'generar_comision.php';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mensaje = document.getElementById('mensaje');
            if (mensaje) {
                setTimeout(() => {
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        });
    </script>
    <script src="assets/js/script_navegacion_dashboard.js"></script>
    <?php if (isset($redirigir_a_comisiones) && $redirigir_a_comisiones): ?>
<script>
setTimeout(function() {
    if (window.parent !== window) {
        window.parent.postMessage({type: 'navigate', page: 'comisiones'}, '*');
    }
    window.location.href = 'index.php';
}, 3000);
</script>
<?php endif; ?>
</body>
</html>