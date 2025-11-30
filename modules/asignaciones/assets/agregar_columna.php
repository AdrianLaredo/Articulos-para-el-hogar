<?php
/**
 * ========================================
 * SCRIPT TEMPORAL - AGREGAR COLUMNA precio_cargado
 * Zeus Hogar - Sistema de Precios Personalizados
 * ========================================
 * 
 * INSTRUCCIONES:
 * 1. Guardar este archivo como: agregar_columna.php
 * 2. Colocarlo en: C:\xampp\htdocs\GestorInventario\modules\asignaciones\
 * 3. Abrir en navegador: http://localhost/GestorInventario/modules/asignaciones/agregar_columna.php
 * 4. Ver resultados
 * 5. ELIMINAR este archivo después de usar
 */

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Intentar cargar la conexión a la base de datos
$posibles_rutas = [
    '../../bd/database.php',
    '../bd/database.php',
    'bd/database.php',
    '../../config/database.php',
    '../config/database.php'
];

$conn = null;
foreach ($posibles_rutas as $ruta) {
    if (file_exists($ruta)) {
        require_once $ruta;
        break;
    }
}

if (!$conn) {
    die("❌ ERROR: No se pudo conectar a la base de datos. Verifica la ruta del archivo database.php");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Columna precio_cargado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #0c3c78;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .step {
            background: #f8f9fa;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .step-title {
            font-weight: 700;
            color: #0c3c78;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .success {
            background: #d1fae5;
            border-left-color: #10b981;
        }
        
        .error {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        
        .warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        
        .code {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .columns-list {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .column-item {
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .column-item:last-child {
            border-bottom: none;
        }
        
        .column-highlight {
            background: #d1fae5;
            font-weight: 700;
            color: #059669;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-danger {
            background: #ef4444;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🔧</span>Instalación de Precios Personalizados</h1>
        <p class="subtitle">Zeus Hogar - Agregando columna precio_cargado a la base de datos</p>

        <?php
        $pasos_completados = 0;
        $total_pasos = 3;
        $exito_total = false;
        
        try {
            // PASO 1: Verificar si la columna ya existe
            echo '<div class="step">';
            echo '<div class="step-title"><span class="icon">1️⃣</span>Verificando estado actual de la base de datos...</div>';
            
            $stmt = $conn->query("PRAGMA table_info(Detalle_Asignacion)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $columna_existe = false;
            foreach ($columns as $col) {
                if ($col['name'] === 'precio_cargado') {
                    $columna_existe = true;
                    break;
                }
            }
            
            if ($columna_existe) {
                echo '<p style="color: #10b981; font-weight: 600;">✅ La columna precio_cargado YA EXISTE en la tabla</p>';
                echo '<p style="color: #6b7280; margin-top: 10px;">No es necesario agregarla nuevamente.</p>';
                $pasos_completados = 3; // Saltar todos los pasos
                $exito_total = true;
            } else {
                echo '<p style="color: #f59e0b; font-weight: 600;">⚠️ La columna precio_cargado NO EXISTE</p>';
                echo '<p style="color: #6b7280; margin-top: 10px;">Se procederá a agregarla...</p>';
            }
            echo '</div>';
            
            if (!$columna_existe) {
                // PASO 2: Agregar la columna
                echo '<div class="step">';
                echo '<div class="step-title"><span class="icon">2️⃣</span>Agregando columna precio_cargado...</div>';
                
                try {
                    $sql_add = "ALTER TABLE Detalle_Asignacion ADD COLUMN precio_cargado REAL DEFAULT NULL";
                    $conn->exec($sql_add);
                    echo '<p style="color: #10b981; font-weight: 600;">✅ Columna agregada exitosamente</p>';
                    echo '<div class="code">ALTER TABLE Detalle_Asignacion ADD COLUMN precio_cargado REAL DEFAULT NULL;</div>';
                    $pasos_completados++;
                } catch (PDOException $e) {
                    echo '<p style="color: #ef4444; font-weight: 600;">❌ Error al agregar columna:</p>';
                    echo '<div class="code" style="color: #ef4444;">' . htmlspecialchars($e->getMessage()) . '</div>';
                    throw $e;
                }
                echo '</div>';
                
                // PASO 3: Actualizar registros existentes
                echo '<div class="step">';
                echo '<div class="step-title"><span class="icon">3️⃣</span>Actualizando registros existentes...</div>';
                
                try {
                    $sql_update = "UPDATE Detalle_Asignacion 
                                   SET precio_cargado = (
                                       SELECT p.precio_venta 
                                       FROM Productos p 
                                       WHERE p.id_producto = Detalle_Asignacion.id_producto
                                   )
                                   WHERE precio_cargado IS NULL";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->execute();
                    $registros_actualizados = $stmt_update->rowCount();
                    
                    echo '<p style="color: #10b981; font-weight: 600;">✅ Registros actualizados: ' . $registros_actualizados . '</p>';
                    echo '<p style="color: #6b7280; margin-top: 10px;">Se asignó el precio del inventario a las asignaciones existentes.</p>';
                    $pasos_completados++;
                } catch (PDOException $e) {
                    echo '<p style="color: #ef4444; font-weight: 600;">❌ Error al actualizar registros:</p>';
                    echo '<div class="code" style="color: #ef4444;">' . htmlspecialchars($e->getMessage()) . '</div>';
                    throw $e;
                }
                echo '</div>';
            }
            
            // PASO 4: Verificación final
            echo '<div class="step success">';
            echo '<div class="step-title"><span class="icon">4️⃣</span>Verificación final</div>';
            
            $stmt = $conn->query("PRAGMA table_info(Detalle_Asignacion)");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<p style="margin-bottom: 10px;"><strong>Columnas de la tabla Detalle_Asignacion:</strong></p>';
            echo '<div class="columns-list">';
            
            $precio_cargado_encontrado = false;
            foreach ($columns as $col) {
                $class = ($col['name'] === 'precio_cargado') ? 'column-item column-highlight' : 'column-item';
                echo '<div class="' . $class . '">';
                echo htmlspecialchars($col['name']) . ' (' . htmlspecialchars($col['type']) . ')';
                if ($col['name'] === 'precio_cargado') {
                    echo ' ← ✨ NUEVA COLUMNA';
                    $precio_cargado_encontrado = true;
                }
                echo '</div>';
            }
            echo '</div>';
            
            if ($precio_cargado_encontrado) {
                $pasos_completados++;
                $exito_total = true;
            }
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="step error">';
            echo '<div class="step-title"><span class="icon">❌</span>Error Crítico</div>';
            echo '<p style="color: #dc2626; font-weight: 600;">No se pudo completar la instalación:</p>';
            echo '<div class="code" style="color: #ef4444;">' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '</div>';
        }
        
        // Resultado final
        if ($exito_total) {
            echo '<div class="step success" style="margin-top: 30px; text-align: center;">';
            echo '<h2 style="color: #059669; font-size: 32px; margin-bottom: 15px;">🎉 ¡INSTALACIÓN EXITOSA!</h2>';
            echo '<p style="font-size: 16px; color: #047857;">La columna precio_cargado ha sido agregada correctamente.</p>';
            echo '<p style="margin-top: 20px; color: #6b7280;">Ahora puedes:</p>';
            echo '<ul style="text-align: left; margin: 15px auto; max-width: 500px; color: #374151;">';
            echo '<li>✅ Modificar precios en "Nueva Salida"</li>';
            echo '<li>✅ Registrar folios con precios personalizados</li>';
            echo '<li>✅ Mantener el inventario protegido</li>';
            echo '</ul>';
            echo '<div style="margin-top: 30px;">';
            echo '<a href="nueva_salida.php" class="btn">Ir a Nueva Salida</a>';
            echo '<a href="asignaciones_activas.php" class="btn" style="background: #10b981; margin-left: 10px;">Ver Asignaciones</a>';
            echo '</div>';
            echo '<div style="margin-top: 20px;">';
            echo '<p style="color: #dc2626; font-size: 13px; font-weight: 600;">⚠️ IMPORTANTE: Elimina este archivo (agregar_columna.php) ahora</p>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="step error" style="margin-top: 30px;">';
            echo '<h2 style="color: #dc2626;">❌ Instalación Incompleta</h2>';
            echo '<p style="margin-top: 10px;">Pasos completados: ' . $pasos_completados . ' de ' . $total_pasos . '</p>';
            echo '<p style="margin-top: 10px; color: #6b7280;">Por favor, contacta a soporte técnico.</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>