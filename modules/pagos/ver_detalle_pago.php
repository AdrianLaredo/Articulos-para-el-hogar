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

if (!isset($_GET['id'])) {
    header("Location: historial_pagos.php");
    exit;
}

$id_pago = intval($_GET['id']);

// Obtener datos del pago
$sql = "SELECT * FROM Vista_Pagos_Sueldos_Completo WHERE id_pago = :id_pago";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id_pago', $id_pago);
$stmt->execute();
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    header("Location: historial_pagos.php?error=not_found");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pago - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/pagos.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class='bx bx-file-blank'></i> Detalle del Pago #<?php echo $pago['id_pago']; ?></h1>
            <div class="header-actions">
                <a href="#" onclick="navegarA('historial_pagos.php'); return false;" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Volver al Historial
                </a>
            </div>
        </div>

        <!-- Estado del Pago -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class='bx bx-info-circle'></i> Estado del Pago</h2>
                <?php if ($pago['estado'] == 'pagado'): ?>
                    <span class="estado-badge badge-success" style="font-size: 1.2rem; padding: 10px 20px;">
                        <i class='bx bx-check'></i> PAGADO
                    </span>
                <?php else: ?>
                    <span class="estado-badge badge-warning" style="font-size: 1.2rem; padding: 10px 20px;">
                        <i class='bx bx-time'></i> PENDIENTE
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información del Beneficiario -->
        <div class="card">
            <h2><i class='bx bx-user-circle'></i> Información del Beneficiario</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-user'></i> Nombre Completo:
                    </label>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">
                        <?php echo htmlspecialchars($pago['nombre_empleado']); ?>
                    </div>
                </div>
                
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-briefcase'></i> Rol:
                    </label>
                    <span class="rol-badge <?php echo $pago['rol_empleado']; ?>">
                        <?php echo ucfirst($pago['rol_empleado']); ?>
                    </span>
                </div>
                
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-map'></i> Zona:
                    </label>
                    <span class="zona-badge"><?php echo $pago['zona']; ?></span>
                </div>
                
<?php if (isset($pago['telefono']) && !empty($pago['telefono']) && $pago['telefono'] !== 'N/A'): ?>
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-phone'></i> Teléfono:
                    </label>
                    <div style="font-size: 1.1rem; color: #333;">
                        <?php echo htmlspecialchars($pago['telefono']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información de la Semana -->
        <div class="card">
            <h2><i class='bx bx-calendar-week'></i> Semana de Pago</h2>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong>Mes/Año:</strong><br>
                        <?php echo $pago['mes'] . ' ' . $pago['anio']; ?>
                    </div>
                    <div>
                        <strong>Número de Semana:</strong><br>
                        Semana <?php echo $pago['numero_semana']; ?>
                    </div>
                    <div>
                        <strong>Periodo:</strong><br>
                        <?php echo date('d/m/Y', strtotime($pago['fecha_inicio'])); ?> al 
                        <?php echo date('d/m/Y', strtotime($pago['fecha_fin'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desglose de Montos -->
        <div class="card">
            <h2><i class='bx bx-calculator'></i> Desglose de Montos</h2>
            <div style="margin-top: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 15px 10px; font-size: 1.1rem;">
                            <i class='bx bx-money' style="color: var(--color-primary); margin-right: 10px;"></i>
                            Sueldo Fijo
                        </td>
                        <td style="padding: 15px 10px; text-align: right; font-size: 1.1rem; font-weight: 600;">
                            $<?php echo number_format($pago['sueldo_fijo'], 2); ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e0e0e0;">
                        <td style="padding: 15px 10px; font-size: 1.1rem;">
                            <i class='bx bxs-gas-pump' style="color: var(--color-primary); margin-right: 10px;"></i>
                            Gasolina
                        </td>
                        <td style="padding: 15px 10px; text-align: right; font-size: 1.1rem; font-weight: 600;">
                            $<?php echo number_format($pago['gasolina'], 2); ?>
                        </td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td style="padding: 20px 10px; font-size: 1.3rem; font-weight: 700; color: var(--color-primary);">
                            <i class='bx bx-calculator' style="margin-right: 10px;"></i>
                            TOTAL A PAGAR
                        </td>
                        <td style="padding: 20px 10px; text-align: right; font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">
                            $<?php echo number_format($pago['total_pagar'], 2); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Fechas y Registro -->
        <div class="card">
            <h2><i class='bx bx-time'></i> Fechas y Registro</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-calendar-plus'></i> Fecha de Registro:
                    </label>
                    <div style="font-size: 1rem; color: #333;">
                        <?php echo date('d/m/Y H:i:s', strtotime($pago['fecha_registro'])); ?>
                    </div>
                </div>
                
                <?php if ($pago['fecha_pago']): ?>
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-calendar-check'></i> Fecha de Pago:
                    </label>
                    <div style="font-size: 1rem; color: var(--color-success); font-weight: 600;">
                        <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div>
                    <label style="color: #666; font-size: 0.9rem; display: block; margin-bottom: 5px;">
                        <i class='bx bx-user-circle'></i> Registrado por:
                    </label>
                    <div style="font-size: 1rem; color: #333;">
                        <?php echo htmlspecialchars($pago['registrado_por']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
<?php if (isset($pago['observaciones']) && !empty($pago['observaciones'])): ?>
        <div class="card">
            <h2><i class='bx bx-note'></i> Observaciones</h2>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($pago['observaciones'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Acciones -->
        <?php if ($pago['estado'] == 'pendiente'): ?>
        <div class="card">
            <h2><i class='bx bx-cog'></i> Acciones Disponibles</h2>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="#" onclick="marcarPagado(<?php echo $pago['id_pago']; ?>); return false;" 
                   class="btn btn-primary">
                    <i class='bx bx-check-circle'></i> Marcar como Pagado
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function navegarA(pagina) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({type: 'navigate', page: pagina}, '*');
            } else {
                window.location.href = pagina;
            }
        }

        function marcarPagado(id) {
            if (confirm('¿Está seguro de marcar este pago como pagado?')) {
                window.location.href = 'marcar_pagado.php?id=' + id;
            }
        }
    </script>
</body>
</html>