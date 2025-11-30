<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Prueba Reinicio Semanal - SQLite</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        .highlight {
            background: #ffd700;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .test-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            border-left: 5px solid #667eea;
        }
        
        .test-box.success { border-left-color: #10b981; }
        .test-box.info { border-left-color: #3b82f6; }
        .test-box.warning { border-left-color: #f59e0b; }
        
        .test-box h3 {
            margin: 0 0 15px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result {
            background: white;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            line-height: 1.8;
        }
        
        .result strong {
            color: #667eea;
            display: inline-block;
            min-width: 180px;
        }
        
        .big-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            display: block;
            margin: 30px auto 0;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .format-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 15px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .format-note strong {
            color: #856404;
        }
        
        hr {
            border: none;
            border-top: 2px solid #e5e7eb;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Prueba de Reinicio Semanal</h1>
        <p class="subtitle">Sistema optimizado para <span class="highlight">SQLite + CDMX</span></p>

        <?php
        // ⭐ CONFIGURAR ZONA HORARIA DE MÉXICO (CDMX)
        date_default_timezone_set('America/Mexico_City');
        
        // Función de prueba (igual a la del functions.php)
        function obtenerInicioSemanaActual() {
            date_default_timezone_set('America/Mexico_City');
            
            $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
            $hora_actual = (int)$ahora->format('G');
            $dia_semana = (int)$ahora->format('N');
            
            if ($dia_semana === 1 && $hora_actual < 7) {
                $inicio_semana = new DateTime('last monday', new DateTimeZone('America/Mexico_City'));
            } 
            elseif ($dia_semana === 1 && $hora_actual >= 7) {
                $inicio_semana = new DateTime('today', new DateTimeZone('America/Mexico_City'));
            }
            else {
                $inicio_semana = new DateTime('last monday', new DateTimeZone('America/Mexico_City'));
            }
            
            $inicio_semana->setTime(7, 0, 0);
            
            return $inicio_semana->format('Y-m-d H:i:s');
        }
        
        // Obtener información actual
        $ahora = new DateTime('now', new DateTimeZone('America/Mexico_City'));
        $inicio_semana = obtenerInicioSemanaActual();
        $inicio_obj = new DateTime($inicio_semana, new DateTimeZone('America/Mexico_City'));
        $diferencia = $ahora->diff($inicio_obj);
        
        // Nombres de días en español
        $dias_es = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        $dia_nombre = $dias_es[$ahora->format('l')];
        ?>

        <!-- Información del Sistema -->
        <div class="test-box success">
            <h3>✅ Información del Sistema</h3>
            <div class="result">
                <strong>Zona Horaria PHP:</strong> <?php echo date_default_timezone_get(); ?><br>
                <strong>Zona Horaria Actual:</strong> <?php echo $ahora->getTimezone()->getName(); ?><br>
                <hr>
                <strong>📅 Fecha Actual:</strong> <?php echo $ahora->format('d/m/Y'); ?> (<?php echo $dia_nombre; ?>)<br>
                <strong>🕐 Hora Actual:</strong> <?php echo $ahora->format('h:i A'); ?> (<?php echo $ahora->format('H:i'); ?> formato 24h)<br>
                <strong>🔢 Día de Semana:</strong> <?php echo $ahora->format('N'); ?> (1=Lunes, 7=Domingo)<br>
                <hr>
                <strong>💾 Formato SQLite:</strong> <?php echo $ahora->format('Y-m-d H:i:s'); ?>
            </div>
            
            <div class="format-note">
                <strong>📝 Nota Importante sobre SQLite:</strong><br>
                SQLite almacena fechas como <strong>texto</strong> en formato: <code>YYYY-MM-DD HH:MM:SS</code><br>
                El formato DD/MM/AAAA es solo para <strong>mostrar</strong> al usuario, no para la base de datos.
            </div>
        </div>

        <!-- Resultado del Cálculo -->
        <div class="test-box info">
            <h3>🎯 Resultado del Cálculo de Reinicio</h3>
            <div class="result">
                <strong>📌 Inicio de Semana:</strong> 
                <div class="big-number"><?php echo $inicio_semana; ?></div>
                
                <hr>
                
                <strong>📆 Fecha Legible:</strong> <?php echo $inicio_obj->format('d/m/Y'); ?> a las <?php echo $inicio_obj->format('h:i A'); ?><br>
                <strong>⏱️ Tiempo Transcurrido:</strong> 
                <?php 
                    $total_horas = ($diferencia->days * 24) + $diferencia->h;
                    echo "{$diferencia->days} días, {$diferencia->h} horas, {$diferencia->i} minutos";
                ?><br>
                <strong>🔢 Total Horas:</strong> <?php echo $total_horas; ?> horas
                
                <hr>
                
                <strong>📊 Periodo Activo:</strong><br>
                <div style="margin-left: 20px; margin-top: 10px;">
                    <strong style="color: #10b981;">DESDE:</strong> <?php echo $inicio_semana; ?><br>
                    <strong style="color: #667eea;">HASTA:</strong> <?php echo $ahora->format('Y-m-d H:i:s'); ?>
                </div>
            </div>
        </div>

        <!-- Validación Específica para Hoy -->
        <div class="test-box warning">
            <h3>🔍 Validación para Hoy (31/10/2025)</h3>
            <div class="result">
                <?php
                // Validar si el cálculo es correcto para hoy
                $dia_actual = $ahora->format('N');
                $dia_nombre_actual = $dias_es[$ahora->format('l')];
                $es_31_oct = ($ahora->format('d/m/Y') == '31/10/2025');
                $hora_correcta = ($ahora->format('H') >= 7);
                
                // Calcular cuál debería ser el inicio (el lunes más reciente)
                // Octubre 2025: 27=Lunes, 28=Martes, 29=Miércoles, 30=Jueves, 31=Viernes
                $inicio_esperado = '2025-10-27 07:00:00';
                $calculo_correcto = ($inicio_semana == $inicio_esperado);
                ?>
                
                <strong>✓ Día de la Semana:</strong> <?php echo $dia_nombre_actual; ?> (<?php echo $dia_actual; ?>)<br>
                <strong>✓ Es 31 de Octubre:</strong> <?php echo $es_31_oct ? '✅ Sí' : '❌ No'; ?><br>
                <strong>✓ Pasó las 7:00 AM:</strong> <?php echo $hora_correcta ? '✅ Sí' : '❌ No'; ?><br>
                <hr>
                <strong>✓ Inicio Esperado:</strong> 2025-10-27 07:00:00 (Lunes 27 Oct)<br>
                <strong>✓ Inicio Calculado:</strong> <?php echo $inicio_semana; ?><br>
                <strong>✓ Cálculo Correcto:</strong> 
                <?php if ($calculo_correcto): ?>
                    <span class="status-badge success">✅ CORRECTO</span>
                <?php else: ?>
                    <span class="status-badge warning">⚠️ REVISAR</span>
                <?php endif; ?>
                
                <hr>
                <div style="background: #e0f2fe; padding: 10px; border-radius: 5px; margin-top: 10px;">
                    <strong style="color: #0369a1;">📅 Calendario de Octubre 2025:</strong><br>
                    <div style="margin-left: 15px; margin-top: 5px; font-family: monospace;">
                        27 Oct = <strong>Lunes</strong> ← Inicio de semana<br>
                        28 Oct = Martes<br>
                        29 Oct = Miércoles<br>
                        30 Oct = Jueves<br>
                        31 Oct = <strong style="color: #0369a1;">Viernes (HOY)</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Casos de Prueba -->
        <div class="test-box">
            <h3>📊 Casos de Prueba - Diferentes Escenarios</h3>
            <table>
                <thead>
                    <tr>
                        <th>Escenario</th>
                        <th>Fecha Simulada</th>
                        <th>Inicio Calculado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $casos_prueba = [
                        ['Lunes 6:30 AM', '2025-10-27 06:30:00', '2025-10-20 07:00:00', 'Antes del reinicio'],
                        ['Lunes 7:00 AM', '2025-10-27 07:00:00', '2025-10-27 07:00:00', '¡REINICIO!'],
                        ['Lunes 10:00 AM', '2025-10-27 10:00:00', '2025-10-27 07:00:00', 'Después del reinicio'],
                        ['Miércoles 3:00 PM', '2025-10-29 15:00:00', '2025-10-27 07:00:00', 'Mitad de semana'],
                        ['Viernes 8:25 AM (HOY)', '2025-10-31 08:25:00', '2025-10-27 07:00:00', 'Día actual'],
                        ['Domingo 11:00 PM', '2025-11-02 23:00:00', '2025-10-27 07:00:00', 'Fin de semana'],
                    ];
                    
                    foreach ($casos_prueba as $caso) {
                        $fecha_test = new DateTime($caso[1], new DateTimeZone('America/Mexico_City'));
                        $hora_test = (int)$fecha_test->format('G');
                        $dia_test = (int)$fecha_test->format('N');
                        
                        // Calcular el inicio de semana para esta fecha de prueba
                        if ($dia_test === 1 && $hora_test < 7) {
                            // Lunes antes de las 7 AM - usar lunes anterior
                            $inicio_test = clone $fecha_test;
                            $inicio_test->modify('last monday');
                        } 
                        elseif ($dia_test === 1 && $hora_test >= 7) {
                            // Lunes después de las 7 AM - usar hoy
                            $inicio_test = clone $fecha_test;
                            $inicio_test->setTime(0, 0, 0);
                        }
                        else {
                            // Cualquier otro día - buscar lunes anterior
                            $inicio_test = clone $fecha_test;
                            $inicio_test->modify('last monday');
                        }
                        $inicio_test->setTime(7, 0, 0);
                        
                        $estado_clase = ($caso[3] === '¡REINICIO!' || strpos($caso[0], 'HOY') !== false) ? 'success' : 'warning';
                        
                        echo "<tr>";
                        echo "<td><strong>{$caso[0]}</strong></td>";
                        echo "<td>{$caso[1]}</td>";
                        echo "<td>{$inicio_test->format('Y-m-d H:i:s')}</td>";
                        echo "<td><span class='status-badge {$estado_clase}'>{$caso[3]}</span></td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Información Final -->
        <div class="test-box success">
            <h3>✨ Resumen de Estadísticas</h3>
            <div class="result">
                <strong>🎯 ¿Qué significa esto?</strong><br><br>
                
                En tu módulo de gasolina, las estadísticas del resumen mostrarán SOLO los registros que estén 
                entre estas fechas:<br><br>
                
                📍 <strong>Desde:</strong> <?php echo $inicio_obj->format('d/m/Y'); ?> a las <?php echo $inicio_obj->format('h:i A'); ?><br>
                📍 <strong>Hasta:</strong> <?php echo $ahora->format('d/m/Y'); ?> a las <?php echo $ahora->format('h:i A'); ?><br><br>
                
                <hr>
                
                📊 <strong>Total Registros:</strong> Solo los de esta semana<br>
                ⛽ <strong>Total Litros:</strong> Solo los de esta semana<br>
                💵 <strong>Total Efectivo:</strong> Solo los de esta semana<br>
                💰 <strong>Gasto Total:</strong> Solo los de esta semana<br><br>
                
                <hr>
                
                <strong style="color: #10b981;">✅ El reinicio es AUTOMÁTICO cada Lunes a las 7:00 AM</strong>
            </div>
        </div>

        <button class="btn" onclick="location.reload()">🔄 Actualizar Prueba</button>
    </div>
</body>
</html>