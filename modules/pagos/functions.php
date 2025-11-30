<?php
// ============================================
// FUNCIONES DEL MÓDULO DE PAGOS
// Zeus Hogar - Sistema de Gestión
// ACTUALIZACIÓN: Comisión 10%, Extras, Préstamos Inhabilitados y Comisión Asignaciones DESPUÉS del Total Disponible
// ============================================

function calcularFondosDisponibles($conn, $id_semana) {
    try {
        $sql_semana = "SELECT * FROM Semanas_Cobro WHERE id_semana = :id_semana";
        $stmt_semana = $conn->prepare($sql_semana);
        $stmt_semana->bindParam(':id_semana', $id_semana);
        $stmt_semana->execute();
        $semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);
        
        if (!$semana) {
            return [
                'error' => 'Semana no encontrada',
                'total_disponible' => 0,
                'total_despues_comision' => 0
            ];
        }
        
        $sql_cobradores = "
            SELECT COALESCE(SUM(monto_cobrado), 0) as total
            FROM Cobros_Diarios 
            WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
            AND CAST(strftime('%w', fecha) AS INTEGER) BETWEEN 0 AND 5
        ";
        $stmt_cobradores = $conn->prepare($sql_cobradores);
        $stmt_cobradores->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_cobradores->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_cobradores->execute();
        $total_ventas_zonas = floatval($stmt_cobradores->fetchColumn());
        
        $sql_enganches = "
            SELECT COALESCE(SUM(enganche), 0) as total
            FROM Folios_Venta 
            WHERE date(fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
            AND estado = 'activo'
        ";
        $stmt_enganches = $conn->prepare($sql_enganches);
        $stmt_enganches->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_enganches->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_enganches->execute();
        $total_enganches = floatval($stmt_enganches->fetchColumn());
        
        $total_ingresos = $total_ventas_zonas + $total_enganches;
        
        $comision_10_porciento = $total_ventas_zonas * 0.10;
        
        $sql_comision_asignaciones = "
            SELECT COALESCE(SUM(comision), 0) as total
            FROM (
                SELECT dfv.monto_comision as comision
                FROM Detalle_Folio_Venta dfv
                INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
                WHERE COALESCE(dfv.comision_cancelada, 0) = 0
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                AND DATE(fv.fecha_hora_venta) BETWEEN :fecha_inicio AND :fecha_fin
                
                UNION ALL
                
                SELECT cc.monto_comision as comision
                FROM Comisiones_Cancelaciones cc
                INNER JOIN Cancelaciones_Folios cf ON cc.id_cancelacion = cf.id_cancelacion
                WHERE DATE(cf.fecha_cancelacion) BETWEEN :fecha_inicio2 AND :fecha_fin2
            )
        ";
        $stmt_asignaciones = $conn->prepare($sql_comision_asignaciones);
        $stmt_asignaciones->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_asignaciones->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_asignaciones->bindParam(':fecha_inicio2', $semana['fecha_inicio']);
        $stmt_asignaciones->bindParam(':fecha_fin2', $semana['fecha_fin']);
        $stmt_asignaciones->execute();
        $comision_asignaciones = floatval($stmt_asignaciones->fetchColumn());
        
        $sql_gasolina_cobradores = "
            SELECT COALESCE(SUM(total_gasolina), 0) as total
            FROM Comisiones_Cobradores
            WHERE id_semana = :id_semana
        ";
        $stmt_gasolina_cob = $conn->prepare($sql_gasolina_cobradores);
        $stmt_gasolina_cob->bindParam(':id_semana', $id_semana);
        $stmt_gasolina_cob->execute();
        $gasolina_cobradores = floatval($stmt_gasolina_cob->fetchColumn());
        
        $sql_gasolina_litros = "
            SELECT COALESCE(SUM(total_gasto), 0) as total
            FROM Registro_Gasolina
            WHERE date(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
            AND tipo_carga = 'litros'
        ";
        $stmt_gasolina_litros = $conn->prepare($sql_gasolina_litros);
        $stmt_gasolina_litros->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_gasolina_litros->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_gasolina_litros->execute();
        $gasolina_litros = floatval($stmt_gasolina_litros->fetchColumn());
        
        $sql_gasolina_efectivo = "
            SELECT COALESCE(SUM(monto_efectivo), 0) as total
            FROM Registro_Gasolina
            WHERE date(fecha_registro) BETWEEN :fecha_inicio AND :fecha_fin
            AND tipo_carga = 'efectivo'
        ";
        $stmt_gasolina_efectivo = $conn->prepare($sql_gasolina_efectivo);
        $stmt_gasolina_efectivo->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_gasolina_efectivo->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_gasolina_efectivo->execute();
        $gasolina_efectivo = floatval($stmt_gasolina_efectivo->fetchColumn());
        
        $gasolina_modulo = $gasolina_litros + $gasolina_efectivo;
        
        $sql_prestamos = "
            SELECT COALESCE(SUM(monto), 0) as total
            FROM Prestamos_Empleados
            WHERE id_semana = :id_semana
              AND estado = 'activo'
        ";
        $stmt_prestamos = $conn->prepare($sql_prestamos);
        $stmt_prestamos->bindParam(':id_semana', $id_semana);
        $stmt_prestamos->execute();
        $prestamos_cobradores = floatval($stmt_prestamos->fetchColumn());

        $sql_extras = "
            SELECT COALESCE(SUM(ec.monto), 0) as total
            FROM Extras_Comision ec
            INNER JOIN Comisiones_Cobradores cc ON ec.id_comision = cc.id_comision
            WHERE cc.id_semana = :id_semana
        ";
        $stmt_extras = $conn->prepare($sql_extras);
        $stmt_extras->bindParam(':id_semana', $id_semana);
        $stmt_extras->execute();
        $total_extras = floatval($stmt_extras->fetchColumn());

        $sql_pagos = "
            SELECT COALESCE(SUM(total_pagar), 0) as total
            FROM Pagos_Sueldos_Fijos
            WHERE id_semana = :id_semana
        ";
        $stmt_pagos = $conn->prepare($sql_pagos);
        $stmt_pagos->bindParam(':id_semana', $id_semana);
        $stmt_pagos->execute();
        $total_pagos_realizados = floatval($stmt_pagos->fetchColumn());
        
        $sql_gastos = "
            SELECT COALESCE(SUM(total_gasto), 0) as total
            FROM Gastos
            WHERE fecha_gasto BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $stmt_gastos = $conn->prepare($sql_gastos);
        $stmt_gastos->bindParam(':fecha_inicio', $semana['fecha_inicio']);
        $stmt_gastos->bindParam(':fecha_fin', $semana['fecha_fin']);
        $stmt_gastos->execute();
        $total_gastos_semana = floatval($stmt_gastos->fetchColumn());
        
        $sql_prestamos_inhabilitados = "
            SELECT COALESCE(SUM(prestamo_inhabilitado), 0) as total
            FROM Comisiones_Cobradores
            WHERE id_semana = :id_semana
        ";
        $stmt_prestamos_inh = $conn->prepare($sql_prestamos_inhabilitados);
        $stmt_prestamos_inh->bindParam(':id_semana', $id_semana);
        $stmt_prestamos_inh->execute();
        $prestamos_inhabilitados = floatval($stmt_prestamos_inh->fetchColumn());
        
        $total_egresos = $gasolina_cobradores + $gasolina_modulo + $prestamos_cobradores + $total_pagos_realizados + $total_gastos_semana;
        
        $total_disponible = $total_ingresos - $total_egresos;
        
        $total_despues_comision = $total_disponible - $comision_10_porciento - $total_extras - $prestamos_inhabilitados - $comision_asignaciones;
        
        return [
            'semana' => $semana,
            'total_ventas_zonas' => $total_ventas_zonas,
            'total_enganches' => $total_enganches,
            'total_ingresos' => $total_ingresos,
            'gasolina_cobradores' => $gasolina_cobradores,
            'gasolina_litros' => $gasolina_litros,
            'gasolina_efectivo' => $gasolina_efectivo,
            'gasolina_modulo' => $gasolina_modulo,
            'prestamos_cobradores' => $prestamos_cobradores,
            'total_pagos_realizados' => $total_pagos_realizados,
            'total_gastos_semana' => $total_gastos_semana,
            'total_extras' => $total_extras,
            'prestamos_inhabilitados' => $prestamos_inhabilitados,
            'total_egresos' => $total_egresos,
            'total_disponible' => $total_disponible,
            'comision_10_porciento' => $comision_10_porciento,
            'comision_asignaciones' => $comision_asignaciones,
            'total_despues_comision' => $total_despues_comision,
            'saldo_restante' => $total_despues_comision,
            'hay_fondos' => $total_despues_comision > 0
        ];
        
    } catch (PDOException $e) {
        return [
            'error' => 'Error al calcular fondos: ' . $e->getMessage(),
            'total_disponible' => 0,
            'total_despues_comision' => 0,
            'saldo_restante' => 0
        ];
    }
}

function guardarHistorialFondos($conn, $id_semana, $fondos) {
    try {
        $sql_check = "SELECT id_fondo FROM Fondos_Disponibles_Historial WHERE id_semana = :id_semana";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':id_semana', $id_semana);
        $stmt_check->execute();
        $existe = $stmt_check->fetch();
        
        if ($existe) {
            $sql = "UPDATE Fondos_Disponibles_Historial 
                    SET total_antes_comision = :total_antes_comision,
                        comision_cobradores = :comision_cobradores,
                        total_cobradores = :total_cobradores,
                        total_enganches = :total_enganches,
                        total_disponible = :total_disponible,
                        total_pagos_realizados = :total_pagos_realizados,
                        saldo_restante = :saldo_restante,
                        fecha_calculo = datetime('now', 'localtime')
                    WHERE id_semana = :id_semana";
        } else {
            $sql = "INSERT INTO Fondos_Disponibles_Historial 
                    (id_semana, total_antes_comision, comision_cobradores, total_cobradores, 
                     total_enganches, total_disponible, total_pagos_realizados, saldo_restante) 
                    VALUES 
                    (:id_semana, :total_antes_comision, :comision_cobradores, :total_cobradores,
                     :total_enganches, :total_disponible, :total_pagos_realizados, :saldo_restante)";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_semana', $id_semana);
        $stmt->bindParam(':total_antes_comision', $fondos['total_ventas_zonas']);
        $stmt->bindParam(':comision_cobradores', $fondos['comision_10_porciento']);
        $stmt->bindParam(':total_cobradores', $fondos['total_ingresos']);
        $stmt->bindParam(':total_enganches', $fondos['total_enganches']);
        $stmt->bindParam(':total_disponible', $fondos['total_disponible']);
        $stmt->bindParam(':total_pagos_realizados', $fondos['total_pagos_realizados']);
        $saldo_restante = $fondos['total_despues_comision'];
        $stmt->bindParam(':saldo_restante', $saldo_restante);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
}

function obtenerPagosPendientes($conn, $id_semana) {
    try {
        $sql = "SELECT * FROM Vista_Pagos_Sueldos_Completo 
                WHERE id_semana = :id_semana AND estado = 'pendiente'
                ORDER BY nombre_empleado";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_semana', $id_semana);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function obtenerPagosRealizados($conn, $id_semana) {
    try {
        $sql = "SELECT * FROM Vista_Pagos_Sueldos_Completo 
                WHERE id_semana = :id_semana AND estado = 'pagado'
                ORDER BY fecha_pago DESC, nombre_empleado";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id_semana', $id_semana);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function verificarFondosSuficientes($conn, $id_semana, $monto_pago) {
    $fondos = calcularFondosDisponibles($conn, $id_semana);
    
    if (isset($fondos['error'])) {
        return ['suficiente' => false, 'mensaje' => $fondos['error']];
    }
    
    if ($fondos['total_despues_comision'] >= $monto_pago) {
        return [
            'suficiente' => true,
            'total_disponible' => $fondos['total_despues_comision'],
            'mensaje' => 'Fondos suficientes'
        ];
    } else {
        return [
            'suficiente' => false,
            'total_disponible' => $fondos['total_despues_comision'],
            'faltante' => $monto_pago - $fondos['total_despues_comision'],
            'mensaje' => 'Fondos insuficientes. Faltan $' . number_format($monto_pago - $fondos['total_despues_comision'], 2)
        ];
    }
}

function obtenerSemanasActivas($conn) {
    try {
        $sql = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio DESC";
        return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function calcularTotalesHistoricos($conn) {
    try {
        $sql_ventas = "SELECT COALESCE(SUM(monto_cobrado), 0) FROM Cobros_Diarios WHERE CAST(strftime('%w', fecha) AS INTEGER) BETWEEN 0 AND 5";
        $total_ventas_historico = floatval($conn->query($sql_ventas)->fetchColumn());
        
        $sql_enganches = "SELECT COALESCE(SUM(enganche), 0) FROM Folios_Venta WHERE estado = 'activo'";
        $total_enganches_historico = floatval($conn->query($sql_enganches)->fetchColumn());
        
        $total_ingresos_historico = $total_ventas_historico + $total_enganches_historico;
        
        $comision_historica = $total_ventas_historico * 0.10;
        
        $sql_asignaciones = "
            SELECT COALESCE(SUM(comision), 0) FROM (
                SELECT dfv.monto_comision as comision
                FROM Detalle_Folio_Venta dfv
                INNER JOIN Folios_Venta fv ON dfv.id_folio = fv.id_folio
                WHERE COALESCE(dfv.comision_cancelada, 0) = 0
                AND (fv.estado = 'activo' OR fv.estado IS NULL)
                
                UNION ALL
                
                SELECT cc.monto_comision as comision
                FROM Comisiones_Cancelaciones cc
            )
        ";
        $comision_asignaciones_historica = floatval($conn->query($sql_asignaciones)->fetchColumn());
        
        $sql_gas_cob = "SELECT COALESCE(SUM(total_gasolina), 0) FROM Comisiones_Cobradores";
        $gasolina_cobradores_historica = floatval($conn->query($sql_gas_cob)->fetchColumn());
        
        $sql_gas_litros = "SELECT COALESCE(SUM(total_gasto), 0) FROM Registro_Gasolina WHERE tipo_carga = 'litros'";
        $gasolina_litros_hist = floatval($conn->query($sql_gas_litros)->fetchColumn());
        
        $sql_gas_efectivo = "SELECT COALESCE(SUM(monto_efectivo), 0) FROM Registro_Gasolina WHERE tipo_carga = 'efectivo'";
        $gasolina_efectivo_hist = floatval($conn->query($sql_gas_efectivo)->fetchColumn());
        
        $gasolina_modulo_historica = $gasolina_litros_hist + $gasolina_efectivo_hist;
        
        $sql_prestamos = "
            SELECT COALESCE(SUM(monto), 0)
            FROM Prestamos_Empleados
            WHERE estado = 'activo'
        ";
        $prestamos_historicos = floatval($conn->query($sql_prestamos)->fetchColumn());
        
        $sql_pagos = "SELECT COALESCE(SUM(total_pagar), 0) FROM Pagos_Sueldos_Fijos";
        $total_pagos_historico = floatval($conn->query($sql_pagos)->fetchColumn());
        
        $sql_gastos = "SELECT COALESCE(SUM(total_gasto), 0) FROM Gastos";
        $total_gastos_historico = floatval($conn->query($sql_gastos)->fetchColumn());
        
        $sql_extras = "SELECT COALESCE(SUM(monto), 0) FROM Extras_Comision";
        $total_extras_historico = floatval($conn->query($sql_extras)->fetchColumn());
        
        $sql_prestamos_inh = "SELECT COALESCE(SUM(prestamo_inhabilitado), 0) FROM Comisiones_Cobradores";
        $prestamos_inhabilitados_historico = floatval($conn->query($sql_prestamos_inh)->fetchColumn());
        
        $total_egresos_historico = $gasolina_cobradores_historica + $gasolina_modulo_historica + $prestamos_historicos + $total_pagos_historico + $total_gastos_historico;
        
        $total_disponible_historico = $total_ingresos_historico - $total_egresos_historico;
        
        $total_despues_comision_historico = $total_disponible_historico - $comision_historica - $total_extras_historico - $prestamos_inhabilitados_historico - $comision_asignaciones_historica;
        
        $total_pagos_count = $conn->query("SELECT COUNT(*) FROM Pagos_Sueldos_Fijos")->fetchColumn();
        $pagos_pendientes_count = $conn->query("SELECT COUNT(*) FROM Pagos_Sueldos_Fijos WHERE estado = 'pendiente'")->fetchColumn();
        $pagos_pagados_count = $conn->query("SELECT COUNT(*) FROM Pagos_Sueldos_Fijos WHERE estado = 'pagado'")->fetchColumn();
        
        return [
            'total_ventas_historico' => $total_ventas_historico,
            'total_enganches_historico' => $total_enganches_historico,
            'total_ingresos_historico' => $total_ingresos_historico,
            'comision_historica' => $comision_historica,
            'comision_asignaciones_historica' => $comision_asignaciones_historica,
            'gasolina_cobradores_historica' => $gasolina_cobradores_historica,
            'gasolina_modulo_historica' => $gasolina_modulo_historica,
            'prestamos_historicos' => $prestamos_historicos,
            'total_pagos_historico' => $total_pagos_historico,
            'total_gastos_historico' => $total_gastos_historico,
            'total_extras_historico' => $total_extras_historico,
            'prestamos_inhabilitados_historico' => $prestamos_inhabilitados_historico,
            'total_egresos_historico' => $total_egresos_historico,
            'total_disponible_historico' => $total_disponible_historico,
            'total_despues_comision_historico' => $total_despues_comision_historico,
            'total_pagos_count' => $total_pagos_count,
            'pagos_pendientes_count' => $pagos_pendientes_count,
            'pagos_pagados_count' => $pagos_pagados_count
        ];
    } catch (PDOException $e) {
        return [
            'error' => 'Error al calcular totales históricos',
            'total_disponible_historico' => 0,
            'total_despues_comision_historico' => 0
        ];
    }
}

function obtenerMesEspanol($numero_mes) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)$numero_mes] ?? '';
}

function obtenerAniosDisponibles($conn) {
    try {
        $sql = "SELECT DISTINCT CAST(strftime('%Y', fecha_inicio) AS INTEGER) as anio 
                FROM Semanas_Cobro ORDER BY anio DESC";
        return $conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function obtenerSemanasPorMesAnio($conn, $mes, $anio) {
    try {
        $sql = "SELECT * FROM Semanas_Cobro 
                WHERE CAST(strftime('%m', fecha_inicio) AS INTEGER) = :mes
                AND CAST(strftime('%Y', fecha_inicio) AS INTEGER) = :anio
                ORDER BY fecha_inicio ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>