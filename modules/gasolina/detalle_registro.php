<?php
session_start();
require_once '../../bd/database.php';
require_once 'functions.php';

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Obtener ID del registro
$id_registro = intval($_GET['id'] ?? 0);

if ($id_registro == 0) {
    header('Location: index.php');
    exit;
}

$registro = obtenerDetalleRegistro($id_registro);

if (!$registro) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Registro #<?php echo $id_registro; ?></title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/detalle_registro.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class='bx bx-file-blank'></i>
                Detalle Registro #<?php echo $id_registro; ?>
            </h1>
            <a href="index.php?tab=historial" class="btn btn-secondary">
                <i class='bx bx-arrow-back'></i> Volver
            </a>
        </div>

        <!-- Información General -->
        <div class="card">
            <h2><i class='bx bx-info-circle'></i> Información General</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Fecha de Registro</label>
                    <p><?php echo date('d/m/Y H:i:s', strtotime($registro['fecha_registro'])); ?></p>
                </div>
                <div class="info-item">
                    <label>Registrado Por</label>
                    <p><?php echo htmlspecialchars($registro['registrado_por']); ?></p>
                </div>
                <div class="info-item">
                    <label>Tipo de Carga</label>
                    <p>
                        <span class="badge <?php echo $registro['tipo_carga']; ?>">
                            <i class='bx <?php echo $registro['tipo_carga'] === 'litros' ? 'bx-tint' : 'bx-money'; ?>'></i>
                            <?php echo ucfirst($registro['tipo_carga']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($registro['tipo_carga'] === 'litros'): ?>
            <!-- Información del Vehículo -->
            <div class="card">
                <h2><i class='bx bxs-car'></i> Información del Vehículo</h2>
                <div class="info-grid horizontal">
                    <div class="info-item">
                        <label>Placas</label>
                        <p><?php echo htmlspecialchars($registro['placas']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Marca</label>
                        <p><?php echo htmlspecialchars($registro['marca']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Modelo</label>
                        <p><?php echo htmlspecialchars($registro['modelo']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Color</label>
                        <p><?php echo htmlspecialchars($registro['color']); ?></p>
                    </div>
                </div>
            </div>

<!-- Detalles de la Carga -->
<div class="card">
    <h2><i class='bx bxs-gas-pump'></i> Detalles de la Carga</h2>
    <div class="info-grid horizontal carga-details">
        <div class="info-item">
            <label>Litros Cargados</label>
            <p><?php echo number_format($registro['litros'], 2); ?> L</p>
        </div>
        <div class="info-item">
            <label>Precio por Litro</label>
            <p>$<?php echo number_format($registro['precio_litro'], 2); ?></p>
        </div>
    </div>
    <!-- Total Gasto FUERA del grid, directamente en el card -->
    <div class="total-box">
        <h3>Total Gasto</h3>
        <p>$<?php echo number_format($registro['total_gasto'], 2); ?></p>
    </div>
</div>
        <?php else: ?>
            <!-- Información del Empleado -->
            <div class="card">
                <h2><i class='bx bx-user'></i> Información del Empleado</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Nombre Completo</label>
                        <p><?php echo htmlspecialchars($registro['nombre_empleado']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Detalles del Efectivo -->
            <div class="card">
                <h2><i class='bx bx-money'></i> Detalles del Efectivo</h2>
                <div class="total-box">
                    <h3>Monto Entregado</h3>
                    <p>$<?php echo number_format($registro['monto_efectivo'], 2); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Observaciones -->
        <?php if (!empty($registro['observaciones'])): ?>
        <div class="card">
            <h2><i class='bx bx-note'></i> Observaciones</h2>
            <div class="info-item">
                <p><?php echo nl2br(htmlspecialchars($registro['observaciones'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones de Acción -->
        <div class="actions">
            <button class="btn btn-primary" onclick="window.print()">
                <i class='bx bx-printer'></i> Imprimir
            </button>
        </div>
    </div>
</body>
</html>