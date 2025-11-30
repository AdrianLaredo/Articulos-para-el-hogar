<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';
date_default_timezone_set('America/Mexico_City');

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

$anios_disponibles = obtenerAniosDisponibles($conn);
$anio_actual = date('Y');
$mes_actual = date('n');

$anio_seleccionado = $_GET['anio'] ?? $anio_actual;
$mes_seleccionado = $_GET['mes'] ?? $mes_actual;

$semanas_filtradas = obtenerSemanasPorMesAnio($conn, $mes_seleccionado, $anio_seleccionado);
$semanas_activas = obtenerSemanasActivas($conn);
$totales_historicos = calcularTotalesHistoricos($conn);

$id_semana_seleccionada = $_GET['semana'] ?? null;

if (!$id_semana_seleccionada && count($semanas_filtradas) > 0) {
    $id_semana_seleccionada = $semanas_filtradas[0]['id_semana'];
}

$fondos = null;
$pagos_pendientes = [];
$pagos_realizados = [];

if ($id_semana_seleccionada) {
    $fondos = calcularFondosDisponibles($conn, $id_semana_seleccionada);
    
    if (!isset($fondos['error'])) {
        guardarHistorialFondos($conn, $id_semana_seleccionada, $fondos);
        $pagos_pendientes = obtenerPagosPendientes($conn, $id_semana_seleccionada);
        $pagos_realizados = obtenerPagosRealizados($conn, $id_semana_seleccionada);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Resumen de Pagos - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/pagos.css">
</head>
<body>
    <div class="container">
        <h1>
            <i class='bx bx-credit-card'></i>
            Resumen de Pagos
        </h1>

        <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
            <a href="#" onclick="navegarA('registrar_pago.php'); return false;" class="btn btn-primary">
                <i class='bx bx-money-withdraw'></i> Registrar Pago
            </a>
            <a href="#" onclick="navegarA('historial_pagos.php'); return false;" class="btn btn-secondary">
                <i class='bx bx-history'></i> Historial
            </a>
        </div>

        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="resumen-general">
                    <i class='bx bx-line-chart'></i>
                    Resumen General
                </button>
                <button class="tab-btn" data-tab="por-semana">
                    <i class='bx bx-calendar-week'></i>
                    Por Semana
                </button>
            </div>

            <!-- TAB 1: RESUMEN GENERAL -->
            <div id="resumen-general" class="tab-content active">
                <?php if (!isset($totales_historicos['error'])): ?>
                <div class="card">
                    <h2>
                        <i class='bx bx-bar-chart-alt-2'></i> 
                        Resumen General (Todo el Tiempo)
                    </h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class='bx bx-trending-up'></i>
                            </div>
                            <div class="stat-info">
                                <h3>$<?php echo number_format($totales_historicos['total_ingresos_historico'], 2); ?></h3>
                                <p>Total Ingresos</p>
                                <small>Ventas + Enganches</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class='bx bx-trending-down'></i>
                            </div>
                            <div class="stat-info">
                                <h3>$<?php echo number_format($totales_historicos['total_egresos_historico'], 2); ?></h3>
                                <p>Total Egresos</p>
                                <small>Sin Comisiones</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class='bx bx-wallet'></i>
                            </div>
                            <div class="stat-info">
                                <h3 style="color: <?php echo $totales_historicos['total_disponible_historico'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>">
                                    $<?php echo number_format($totales_historicos['total_disponible_historico'], 2); ?>
                                </h3>
                                <p>Total Disponible</p>
                                <small>Antes de Comisiones</small>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? 'success' : 'warning'; ?>">
                                <i class='bx bx-check-circle'></i>
                            </div>
                            <div class="stat-info">
                                <h3 style="color: <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>">
                                    $<?php echo number_format($totales_historicos['total_despues_comision_historico'], 2); ?>
                                </h3>
                                <p>Después de Comisiones</p>
                                <small>Total Final Real</small>
                            </div>
                        </div>
                    </div>

                    <div class="desglose-rapido">
                        <h3>
                            <i class='bx bx-calculator'></i> Desglose General
                        </h3>
                        <div class="desglose-grid">
                            <div class="desglose-item" style="background: #e8f5e9;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #2e7d32;">
                                    <i class='bx bx-plus-circle'></i> INGRESOS
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600;">
                                    $<?php echo number_format($totales_historicos['total_ingresos_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ Total Ventas 8 Zonas</div>
                                <div class="desglose-item-valor">
                                    $<?php echo number_format($totales_historicos['total_ventas_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (+) Total Enganches</div>
                                <div class="desglose-item-valor">
                                    +$<?php echo number_format($totales_historicos['total_enganches_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: #ffebee;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #c62828;">
                                    <i class='bx bx-minus-circle'></i> EGRESOS (Sin Comisiones)
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600;">
                                    -$<?php echo number_format($totales_historicos['total_egresos_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (-) Gasolina Cobradores</div>
                                <div class="desglose-item-valor opaco">
                                    -$<?php echo number_format($totales_historicos['gasolina_cobradores_historica'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (-) Gasolina Módulo</div>
                                <div class="desglose-item-valor opaco">
                                    -$<?php echo number_format($totales_historicos['gasolina_modulo_historica'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (-) Préstamos Cobradores</div>
                                <div class="desglose-item-valor opaco">
                                    -$<?php echo number_format($totales_historicos['prestamos_historicos'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (-) Salarios Pagados</div>
                                <div class="desglose-item-valor opaco">
                                    -$<?php echo number_format($totales_historicos['total_pagos_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item">
                                <div class="desglose-item-label">→ (-) Gastos Operativos</div>
                                <div class="desglose-item-valor opaco">
                                    -$<?php echo number_format($totales_historicos['total_gastos_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: <?php echo $totales_historicos['total_disponible_historico'] >= 0 ? '#e3f2fd' : '#ffebee'; ?>; border-top: 2px solid <?php echo $totales_historicos['total_disponible_historico'] >= 0 ? '#2196f3' : '#f44336'; ?>;">
                                <div class="desglose-item-label" style="font-weight: 700; font-size: 1.05rem; color: <?php echo $totales_historicos['total_disponible_historico'] >= 0 ? '#1976d2' : '#c62828'; ?>;">
                                    = TOTAL DISPONIBLE
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 700; font-size: 1.2rem; color: <?php echo $totales_historicos['total_disponible_historico'] >= 0 ? '#1976d2' : '#c62828'; ?>;">
                                    $<?php echo number_format($totales_historicos['total_disponible_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: #fff3e0;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #e65100;">
                                    <i class='bx bx-minus-circle'></i> (-) Comisión 10%
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600; color: #e65100;">
                                    -$<?php echo number_format($totales_historicos['comision_historica'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: #fff3e0;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #e65100;">
                                    <i class='bx bx-minus-circle'></i> (-) Extras Comisiones
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600; color: #e65100;">
                                    -$<?php echo number_format($totales_historicos['total_extras_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: #fff3e0;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #e65100;">
                                    <i class='bx bx-minus-circle'></i> (-) Préstamos Inhabilitados
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600; color: #e65100;">
                                    -$<?php echo number_format($totales_historicos['prestamos_inhabilitados_historico'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: #fff3e0;">
                                <div class="desglose-item-label" style="font-weight: 600; color: #e65100;">
                                    <i class='bx bx-minus-circle'></i> (-) Comisión Asignaciones
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 600; color: #e65100;">
                                    -$<?php echo number_format($totales_historicos['comision_asignaciones_historica'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="desglose-item" style="background: <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? '#e8f5e9' : '#ffebee'; ?>; border-top: 3px solid <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? '#4caf50' : '#f44336'; ?>;">
                                <div class="desglose-item-label" style="font-weight: 700; font-size: 1.1rem; color: <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? '#2e7d32' : '#c62828'; ?>;">
                                    = TOTAL FINAL
                                </div>
                                <div class="desglose-item-valor" style="font-weight: 700; font-size: 1.3rem; color: <?php echo $totales_historicos['total_despues_comision_historico'] >= 0 ? '#2e7d32' : '#c62828'; ?>;">
                                    $<?php echo number_format($totales_historicos['total_despues_comision_historico'], 2); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="estadisticas-pagos">
                        <h3>
                            <i class='bx bx-pie-chart-alt-2'></i> Estadísticas de Pagos
                        </h3>
                        <div class="estadisticas-grid">
                            <div class="stat-item">
                                <div class="stat-numero"><?php echo $totales_historicos['total_pagos_count']; ?></div>
                                <div class="stat-label">Total de Pagos Registrados</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-numero text-warning"><?php echo $totales_historicos['pagos_pendientes_count']; ?></div>
                                <div class="stat-label">Pagos Pendientes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-numero text-success"><?php echo $totales_historicos['pagos_pagados_count']; ?></div>
                                <div class="stat-label">Pagos Completados</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i>
                    <?php echo $totales_historicos['error']; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: POR SEMANA -->
            <div id="por-semana" class="tab-content">
                <div class="card">
                    <h2>
                        <i class='bx bx-calendar-check'></i> 
                        Seleccionar Semana de Cobro
                    </h2>
                    
                    <form method="GET" id="formFiltroSemana" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>
                                <i class='bx bx-calendar'></i> Año
                            </label>
                            <select name="anio" id="selectAnio" class="form-control" onchange="recargarSemanas()" required>
                                <option value="">-- Seleccionar Año --</option>
                                <?php foreach ($anios_disponibles as $anio): ?>
                                    <option value="<?php echo $anio; ?>" <?php echo $anio_seleccionado == $anio ? 'selected' : ''; ?>>
                                        <?php echo $anio; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>
                                <i class='bx bx-calendar-event'></i> Mes
                            </label>
                            <select name="mes" id="selectMes" class="form-control" onchange="recargarSemanas()" required>
                                <option value="">-- Seleccionar Mes --</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $mes_seleccionado == $m ? 'selected' : ''; ?>>
                                        <?php echo obtenerMesEspanol($m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>
                                <i class='bx bx-list-ul'></i> Semana
                            </label>
                            <select name="semana" id="selectSemana" class="form-control" onchange="this.form.submit()" required>
                                <option value="">-- Seleccionar Semana --</option>
                                <?php 
                                if (count($semanas_filtradas) > 0) {
                                    foreach ($semanas_filtradas as $semana):
                                        $fecha_inicio = date('d/m', strtotime($semana['fecha_inicio']));
                                        $fecha_fin = date('d/m', strtotime($semana['fecha_fin']));
                                        $es_activa = $semana['activa'] == 1;
                                ?>
                                    <option value="<?php echo $semana['id_semana']; ?>" 
                                            <?php echo ($id_semana_seleccionada == $semana['id_semana']) ? 'selected' : ''; ?>>
                                        <?php echo $es_activa ? '🟢 ' : ''; ?>Semana <?php echo $semana['numero_semana']; ?> 
                                        (<?php echo $fecha_inicio; ?> al <?php echo $fecha_fin; ?>)
                                    </option>
                                <?php 
                                    endforeach;
                                } else {
                                    echo '<option value="">No hay semanas en este mes</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </form>
                    
                    <?php if (count($semanas_filtradas) == 0): ?>
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <i class='bx bx-info-circle'></i>
                        No hay semanas registradas para <strong><?php echo obtenerMesEspanol($mes_seleccionado) . ' ' . $anio_seleccionado; ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($fondos && !isset($fondos['error'])): ?>
                    <div class="card">
                        <h2>
                            <i class='bx bx-calendar-week'></i> 
                            Semana <?php echo $fondos['semana']['numero_semana']; ?> - 
                            <?php echo obtenerMesEspanol(date('n', strtotime($fondos['semana']['fecha_inicio']))); ?> <?php echo date('Y', strtotime($fondos['semana']['fecha_inicio'])); ?>
                            <span style="color: var(--color-muted); font-size: 1rem; font-weight: 500;">
                                (<?php echo date('d/m', strtotime($fondos['semana']['fecha_inicio'])); ?> al 
                                <?php echo date('d/m', strtotime($fondos['semana']['fecha_fin'])); ?>)
                            </span>
                        </h2>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class='bx bx-trending-up'></i>
                                </div>
                                <div class="stat-info">
                                    <h3>$<?php echo number_format($fondos['total_ingresos'], 2); ?></h3>
                                    <p>Total Ingresos</p>
                                    <small>Ventas + Enganches</small>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class='bx bx-trending-down'></i>
                                </div>
                                <div class="stat-info">
                                    <h3>$<?php echo number_format($fondos['total_egresos'], 2); ?></h3>
                                    <p>Total Egresos</p>
                                    <small>Sin Comisiones</small>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <i class='bx bx-wallet'></i>
                                </div>
                                <div class="stat-info">
                                    <h3 style="color: <?php echo $fondos['total_disponible'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
                                        $<?php echo number_format($fondos['total_disponible'], 2); ?>
                                    </h3>
                                    <p>Total Disponible</p>
                                    <small>Antes de Comisiones</small>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon <?php echo $fondos['total_despues_comision'] >= 0 ? 'success' : 'warning'; ?>">
                                    <i class='bx bx-check-circle'></i>
                                </div>
                                <div class="stat-info">
                                    <h3 style="color: <?php echo $fondos['total_despues_comision'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
                                        $<?php echo number_format($fondos['total_despues_comision'], 2); ?>
                                    </h3>
                                    <p>Después de Comisiones</p>
                                    <small>Total Final Real</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h2>
                            <i class='bx bx-calculator'></i> 
                            Desglose del Cálculo
                        </h2>
                        <div class="calculo-desglose">
                            <table>
                                <tr style="background: #e8f5e9;">
                                    <td colspan="2" style="font-weight: 700; color: #2e7d32; padding: 12px;">
                                        <i class='bx bx-plus-circle' style="margin-right: 8px;"></i>
                                        INGRESOS
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bx-coin-stack' style="color: var(--color-primary); margin-right: 8px;"></i>
                                        Total Ventas 8 Zonas
                                    </td>
                                    <td class="text-right">$<?php echo number_format($fondos['total_ventas_zonas'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bx-dollar-circle' style="color: var(--color-success); margin-right: 8px;"></i>
                                        (+) Enganches
                                    </td>
                                    <td class="text-right" style="color: var(--color-success);">+$<?php echo number_format($fondos['total_enganches'], 2); ?></td>
                                </tr>
                                <tr style="background: #f5f5f5; font-weight: 600;">
                                    <td>
                                        <i class='bx bx-calculator' style="color: var(--color-primary); margin-right: 8px;"></i>
                                        = Total Ingresos
                                    </td>
                                    <td class="text-right" style="color: var(--color-primary);">$<?php echo number_format($fondos['total_ingresos'], 2); ?></td>
                                </tr>
                                
                                <tr style="background: #ffebee;">
                                    <td colspan="2" style="font-weight: 700; color: #c62828; padding: 12px;">
                                        <i class='bx bx-minus-circle' style="margin-right: 8px;"></i>
                                        EGRESOS (Sin Comisiones)
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bxs-gas-pump' style="color: var(--color-danger); margin-right: 8px;"></i>
                                        (-) Gasolina Cobradores
                                    </td>
                                    <td class="text-right" style="color: var(--color-danger);">-$<?php echo number_format($fondos['gasolina_cobradores'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bxs-gas-pump' style="color: var(--color-danger); margin-right: 8px;"></i>
                                        (-) Gasolina Módulo
                                    </td>
                                    <td class="text-right" style="color: var(--color-danger);">-$<?php echo number_format($fondos['gasolina_modulo'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bx-money-withdraw' style="color: var(--color-danger); margin-right: 8px;"></i>
                                        (-) Préstamos Cobradores
                                    </td>
                                    <td class="text-right" style="color: var(--color-danger);">-$<?php echo number_format($fondos['prestamos_cobradores'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bx-user-check' style="color: var(--color-danger); margin-right: 8px;"></i>
                                        (-) Salarios Pagados
                                    </td>
                                    <td class="text-right" style="color: var(--color-danger);">-$<?php echo number_format($fondos['total_pagos_realizados'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class='bx bx-wallet-alt' style="color: var(--color-danger); margin-right: 8px;"></i>
                                        (-) Gastos Operativos
                                    </td>
                                    <td class="text-right" style="color: var(--color-danger);">-$<?php echo number_format($fondos['total_gastos_semana'], 2); ?></td>
                                </tr>
                                <tr style="background: <?php echo $fondos['total_disponible'] >= 0 ? '#e3f2fd' : '#ffebee'; ?>; font-weight: 700;">
                                    <td style="padding: 14px;">
                                        <i class='bx bx-wallet' style="color: <?php echo $fondos['total_disponible'] >= 0 ? '#1976d2' : '#c62828'; ?>; margin-right: 8px;"></i>
                                        = TOTAL DISPONIBLE
                                    </td>
                                    <td class="text-right" style="padding: 14px;">
                                        <span style="color: <?php echo $fondos['total_disponible'] >= 0 ? '#1976d2' : '#c62828'; ?>; font-size: 1.2rem;">
                                            $<?php echo number_format($fondos['total_disponible'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                                
                                <tr style="background: #fff3e0;">
                                    <td style="padding: 12px;">
                                        <i class='bx bx-minus-circle' style="color: #e65100; margin-right: 8px;"></i>
                                        <strong>(-) Comisión 10%</strong>
                                    </td>
                                    <td class="text-right" style="padding: 12px;">
                                        <strong style="color: #e65100;">
                                            -$<?php echo number_format($fondos['comision_10_porciento'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                                
                                <tr style="background: #fff3e0;">
                                    <td style="padding: 12px;">
                                        <i class='bx bx-dollar-circle' style="color: #e65100; margin-right: 8px;"></i>
                                        <strong>(-) Extras Comisiones</strong>
                                    </td>
                                    <td class="text-right" style="padding: 12px;">
                                        <strong style="color: #e65100;">
                                            -$<?php echo number_format($fondos['total_extras'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                                
                                <tr style="background: #fff3e0;">
                                    <td style="padding: 12px;">
                                        <i class='bx bx-error-circle' style="color: #e65100; margin-right: 8px;"></i>
                                        <strong>(-) Préstamos Inhabilitados (Empresa Absorbe)</strong>
                                    </td>
                                    <td class="text-right" style="padding: 12px;">
                                        <strong style="color: #e65100;">
                                            -$<?php echo number_format($fondos['prestamos_inhabilitados'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                                
                                <tr style="background: #fff3e0;">
                                    <td style="padding: 12px;">
                                        <i class='bx bx-minus-circle' style="color: #e65100; margin-right: 8px;"></i>
                                        <strong>(-) Comisión Asignaciones</strong>
                                    </td>
                                    <td class="text-right" style="padding: 12px;">
                                        <strong style="color: #e65100;">
                                            -$<?php echo number_format($fondos['comision_asignaciones'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                                
                                <tr style="background: <?php echo $fondos['total_despues_comision'] >= 0 ? '#e8f5e9' : '#ffebee'; ?>; border-top: 3px solid <?php echo $fondos['total_despues_comision'] >= 0 ? '#4caf50' : '#f44336'; ?>;">
                                    <td style="padding: 16px;">
                                        <strong style="font-size: 1.1rem; color: <?php echo $fondos['total_despues_comision'] >= 0 ? '#2e7d32' : '#c62828'; ?>;">
                                            <i class='bx bx-check-circle' style="margin-right: 8px;"></i>
                                            = TOTAL FINAL
                                        </strong>
                                    </td>
                                    <td class="text-right" style="padding: 16px;">
                                        <strong style="color: <?php echo $fondos['total_despues_comision'] >= 0 ? '#2e7d32' : '#c62828'; ?>; font-size: 1.3rem;">
                                            $<?php echo number_format($fondos['total_despues_comision'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (count($pagos_pendientes) > 0): ?>
                    <div class="card">
                        <h2>
                            <i class='bx bx-time-five'></i> 
                            Pagos Pendientes (<?php echo count($pagos_pendientes); ?>)
                        </h2>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Empleado</th>
                                        <th>Rol</th>
                                        <th>Zona</th>
                                        <th>Sueldo Fijo</th>
                                        <th>Gasolina</th>
                                        <th class="text-right">Total a Pagar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos_pendientes as $index => $pago): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($pago['nombre_empleado']); ?></td>
                                        <td>
                                            <span class="rol-badge <?php echo $pago['rol_empleado']; ?>">
                                                <?php echo ucfirst($pago['rol_empleado']); ?>
                                            </span>
                                        </td>
                                        <td><span class="zona-badge"><?php echo $pago['zona']; ?></span></td>
                                        <td>$<?php echo number_format($pago['sueldo_fijo'], 2); ?></td>
                                        <td>$<?php echo number_format($pago['gasolina'], 2); ?></td>
                                        <td class="text-right text-bold">$<?php echo number_format($pago['total_pagar'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="6" class="text-right"><strong>Total Pendiente:</strong></td>
                                        <td class="text-right"><strong>$<?php echo number_format(array_sum(array_column($pagos_pendientes, 'total_pagar')), 2); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($pagos_realizados) > 0): ?>
                    <div class="card">
                        <h2>
                            <i class='bx bx-check-circle'></i> 
                            Pagos Realizados (<?php echo count($pagos_realizados); ?>)
                        </h2>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Empleado</th>
                                        <th>Rol</th>
                                        <th>Total Pagado</th>
                                        <th>Fecha de Pago</th>
                                        <th>Registrado por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos_realizados as $index => $pago): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($pago['nombre_empleado']); ?></td>
                                        <td>
                                            <span class="rol-badge <?php echo $pago['rol_empleado']; ?>">
                                                <?php echo ucfirst($pago['rol_empleado']); ?>
                                            </span>
                                        </td>
                                        <td class="text-bold" style="color: var(--color-success);">
                                            $<?php echo number_format($pago['total_pagar'], 2); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></td>
                                        <td><?php echo htmlspecialchars($pago['registrado_por']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row">
                                        <td colspan="3" class="text-right"><strong>Total Pagado:</strong></td>
                                        <td class="text-bold" style="color: var(--color-success);">
                                            <strong>$<?php echo number_format(array_sum(array_column($pagos_realizados, 'total_pagar')), 2); ?></strong>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif (isset($fondos['error'])): ?>
                    <div class="alert alert-error">
                        <i class='bx bx-error-circle'></i>
                        <?php echo $fondos['error']; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-info-circle'></i>
                        <p>Selecciona un año, mes y semana para ver el resumen de fondos disponibles</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');

                    localStorage.setItem('activeTab', targetTab);
                });
            });

            const savedTab = localStorage.getItem('activeTab');
            if (savedTab) {
                const savedButton = document.querySelector(`[data-tab="${savedTab}"]`);
                if (savedButton) {
                    savedButton.click();
                }
            }
        });

        function recargarSemanas() {
            const anio = document.getElementById('selectAnio').value;
            const mes = document.getElementById('selectMes').value;
            
            if (anio && mes) {
                window.location.href = `?anio=${anio}&mes=${mes}`;
            }
        }

        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'navigate', 
                    page: pagina,
                    fullUrl: pagina
                }, '*');
            } else {
                window.location.href = pagina;
            }
        }

        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>