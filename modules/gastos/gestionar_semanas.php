<?php
session_start();
require_once '../../bd/database.php';
date_default_timezone_set('America/Mexico_City');
require_once 'actualizar_semanas_auto.php';

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

// ========================================
// CONFIGURACIÓN DE ZONA HORARIA
// ========================================
date_default_timezone_set('America/Mexico_City');

// ========================================
// FUNCIÓN PARA GENERAR LAS 3 SEMANAS AUTOMÁTICAMENTE
// Semana anterior, actual y siguiente
// ========================================
function generarTresSemanas($conn) {
    // Obtener fecha y hora actual en Mexico City
    $hoy = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    
    // Encontrar el domingo de la semana actual
    $domingo_actual = clone $hoy;
    $dia_semana = (int)$domingo_actual->format('w'); // 0=Domingo, 6=Sábado
    
    // Si no es domingo, retroceder al domingo anterior
    if ($dia_semana > 0) {
        $domingo_actual->modify('-' . $dia_semana . ' days');
    }
    
    // Calcular domingo de semana anterior (-7 días)
    $domingo_anterior = clone $domingo_actual;
    $domingo_anterior->modify('-7 days');
    
    // Calcular domingo de semana siguiente (+7 días)
    $domingo_siguiente = clone $domingo_actual;
    $domingo_siguiente->modify('+7 days');
    
    $semanas = [];
    $domingos = [$domingo_anterior, $domingo_actual, $domingo_siguiente];
    
    foreach ($domingos as $domingo) {
        $viernes = clone $domingo;
        $viernes->modify('+5 days'); // Domingo + 5 días = Viernes
        
        // Determinar mes y número de semana
        // Usar el mes del VIERNES para determinar a qué mes pertenece
        $mes_nombre = ucfirst(strftime('%B', $viernes->getTimestamp()));
        $anio = $viernes->format('Y');
        
        // Calcular número de semana dentro del mes (basado en semanas completas)
        $numero_semana = ceil($viernes->format('d') / 7);
        
        $fecha_inicio = $domingo->format('Y-m-d');
        $fecha_fin = $viernes->format('Y-m-d');
        
        // Verificar si ya existe
        $sql_check = "SELECT id_semana FROM Semanas_Cobro 
                      WHERE fecha_inicio = :fecha_inicio AND fecha_fin = :fecha_fin";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindValue(':fecha_inicio', $fecha_inicio);
        $stmt_check->bindValue(':fecha_fin', $fecha_fin);
        $stmt_check->execute();
        $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$existe) {
            // Insertar nueva semana
            $sql_insert = "INSERT INTO Semanas_Cobro (mes, anio, numero_semana, fecha_inicio, fecha_fin, activa)
                          VALUES (:mes, :anio, :numero_semana, :fecha_inicio, :fecha_fin, 1)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindValue(':mes', $mes_nombre);
            $stmt_insert->bindValue(':anio', $anio);
            $stmt_insert->bindValue(':numero_semana', $numero_semana);
            $stmt_insert->bindValue(':fecha_inicio', $fecha_inicio);
            $stmt_insert->bindValue(':fecha_fin', $fecha_fin);
            $stmt_insert->execute();
            $id_semana = $conn->lastInsertId();
        } else {
            $id_semana = $existe['id_semana'];
            
            // Actualizar para asegurarnos que esté activa
            $sql_update = "UPDATE Semanas_Cobro SET activa = 1 WHERE id_semana = :id";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindValue(':id', $id_semana);
            $stmt_update->execute();
        }
        
        $semanas[] = [
            'id_semana' => $id_semana,
            'mes' => $mes_nombre,
            'anio' => $anio,
            'numero_semana' => $numero_semana,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ];
    }
    
    return $semanas;
}

// ========================================
// DESACTIVAR TODAS LAS SEMANAS EXCEPTO LAS 3 ACTUALES
// ========================================
function desactivarSemanasNoActuales($conn, $semanas_actuales) {
    $ids_actuales = array_column($semanas_actuales, 'id_semana');
    $placeholders = implode(',', array_fill(0, count($ids_actuales), '?'));
    
    $sql = "UPDATE Semanas_Cobro SET activa = 0 WHERE id_semana NOT IN ($placeholders) AND activa = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids_actuales);
    
    return $stmt->rowCount();
}

// ========================================
// ACTUALIZAR SEMANAS AUTOMÁTICAMENTE
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    if (!validarCSRF($_POST['csrf_token'])) {
        $mensaje = "Error: Token de seguridad inválido";
        $tipo_mensaje = "error";
    } else {
        $semanas_generadas = generarTresSemanas($conn);
        $semanas_desactivadas = desactivarSemanasNoActuales($conn, $semanas_generadas);
        
        $mensaje = "✅ Sistema actualizado. 3 semanas activas.";
        if ($semanas_desactivadas > 0) {
            $mensaje .= " Se desactivaron $semanas_desactivadas semanas antiguas.";
        }
        $tipo_mensaje = "success";
    }
}

// Generar semanas automáticamente al cargar la página
$semanas_activas = generarTresSemanas($conn);
desactivarSemanasNoActuales($conn, $semanas_activas);

$mensaje = '';
$tipo_mensaje = '';

// Obtener las 3 semanas activas
$query_semanas = "SELECT * FROM Semanas_Cobro WHERE activa = 1 ORDER BY fecha_inicio ASC";
$stmt_semanas = $conn->query($query_semanas);
$semanas = $stmt_semanas->fetchAll(PDO::FETCH_ASSOC);

// Identificar la semana actual
$hoy = date('Y-m-d');
$semana_actual_id = null;
foreach ($semanas as $sem) {
    if ($hoy >= $sem['fecha_inicio'] && $hoy <= $sem['fecha_fin']) {
        $semana_actual_id = $sem['id_semana'];
        break;
    }
}

// Si no se encontró (puede pasar si hoy es sábado), marcar la más cercana
if (!$semana_actual_id && count($semanas) > 0) {
    $semana_actual_id = $semanas[1]['id_semana'] ?? $semanas[0]['id_semana'];
}

// Configurar localización en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'spanish');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Semanas de Cobro - Zeus Hogar</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/cobradores.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class='bx bx-calendar-week'></i> Semanas de Cobro</h1>
            <div class="header-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="actualizar">
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-refresh'></i> Actualizar Sistema
                    </button>
                </form>
                <a href="index.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Volver
                </a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>" id="mensaje">
                <i class='bx <?php echo $tipo_mensaje === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Información del Sistema -->
        <div class="card" style="background: #E3F2FD; border-left: 4px solid #2196F3;">
            <h2><i class='bx bx-info-circle'></i> Sistema de Semanas Automático</h2>
            <div style="padding: 15px 0;">
                <p><strong>📅 Zona Horaria:</strong> America/Mexico_City (UTC-6)</p>
                <p><strong>🕐 Fecha y Hora Actual:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                <p><strong>✅ Semanas Activas:</strong> Siempre 3 (Anterior, Actual, Siguiente)</p>
                <p><strong>🔄 Actualización:</strong> Automática al cargar cualquier página</p>
            </div>
        </div>

        <!-- Tabla de Semanas Activas -->
        <div class="card">
            <h2><i class='bx bx-list-ul'></i> Semanas Activas (<?php echo count($semanas); ?>)</h2>

            <?php if (count($semanas) > 0): ?>
                <div class="table-container" style="margin-top: 20px;">
                    <table class="table-comisiones">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Mes</th>
                                <th>Año</th>
                                <th>Semana</th>
                                <th>Fecha Inicio (Domingo)</th>
                                <th>Fecha Fin (Viernes)</th>
                                <th>Días</th>
                                <th>Puede Generar Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tipos = ['Anterior', 'Actual', 'Siguiente'];
                            $contador = 0;
                            foreach ($semanas as $sem): 
                                $es_actual = ($semana_actual_id == $sem['id_semana']);
                                $tipo = $tipos[$contador] ?? 'Otra';
                                $puede_generar = ($tipo === 'Anterior' || $tipo === 'Actual');
                                $contador++;
                            ?>
                                <tr style="<?php echo $es_actual ? 'background: #E8F5E9;' : ''; ?>">
                                    <td>
                                        <?php if ($es_actual): ?>
                                            <span class="estado-badge badge-success">
                                                <i class='bx bx-check-circle'></i> Semana Actual
                                            </span>
                                        <?php elseif ($tipo === 'Anterior'): ?>
                                            <span class="estado-badge badge-info">
                                                <i class='bx bx-time'></i> Anterior
                                            </span>
                                        <?php else: ?>
                                            <span class="estado-badge badge-warning">
                                                <i class='bx bx-calendar-plus'></i> Siguiente
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo $sem['mes']; ?></strong></td>
                                    <td><?php echo $sem['anio']; ?></td>
                                    <td>
                                        <span class="zona-badge">Semana <?php echo $sem['numero_semana']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $fecha_i = new DateTime($sem['fecha_inicio']);
                                        echo $fecha_i->format('d/m/Y'); 
                                        ?>
                                        <br><small class="text-muted">Domingo</small>
                                    </td>
                                    <td>
                                        <?php 
                                        $fecha_f = new DateTime($sem['fecha_fin']);
                                        echo $fecha_f->format('d/m/Y'); 
                                        ?>
                                        <br><small class="text-muted">Viernes</small>
                                    </td>
                                    <td>
                                        <?php
                                        $dias = $fecha_i->diff($fecha_f)->days + 1;
                                        echo $dias . ' días';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($puede_generar): ?>
                                            <span style="color: #4CAF50; font-weight: bold;">
                                                <i class='bx bx-check-circle'></i> SÍ
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #f44336; font-weight: bold;">
                                                <i class='bx bx-block'></i> BLOQUEADA
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p style="margin: 0; color: #666; text-align: center;">
                        <i class='bx bx-time'></i>
                        <strong>Las semanas se actualizan automáticamente.</strong> No es necesario hacer nada manualmente.
                    </p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-calendar-x' style="font-size: 48px;"></i>
                    <p>No hay semanas activas</p>
                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="actualizar">
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-refresh'></i> Generar Semanas
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
</body>
</html>