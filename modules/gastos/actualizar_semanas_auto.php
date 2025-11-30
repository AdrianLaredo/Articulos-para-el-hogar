<?php
/**
 * actualizar_semanas_auto.php
 * Helper para actualizar automáticamente las 3 semanas
 * Se incluye en todas las páginas del módulo
 */

// Configurar zona horaria y locale para español
date_default_timezone_set('America/Mexico_City');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');

// Función para obtener nombre del mes en español
function getNombreMesEspanol($timestamp) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[(int)date('n', $timestamp)];
}

// Función para generar las 3 semanas automáticamente
function actualizarSemanasAutomatico($conn) {
    try {
        // Obtener fecha actual en Mexico City
        $hoy = new DateTime('now', new DateTimeZone('America/Mexico_City'));
        
        // Encontrar el domingo de la semana actual
        $domingo_actual = clone $hoy;
        $dia_semana = (int)$domingo_actual->format('w');
        
        if ($dia_semana > 0) {
            $domingo_actual->modify('-' . $dia_semana . ' days');
        }
        
        // Calcular domingos
        $domingo_anterior = clone $domingo_actual;
        $domingo_anterior->modify('-7 days');
        
        $domingo_siguiente = clone $domingo_actual;
        $domingo_siguiente->modify('+7 days');
        
        $semanas_ids = [];
        $domingos = [$domingo_anterior, $domingo_actual, $domingo_siguiente];
        
        foreach ($domingos as $domingo) {
            $viernes = clone $domingo;
            $viernes->modify('+5 days');
            
            // Usar el mes del VIERNES con nombre en español
            $mes_nombre = getNombreMesEspanol($viernes->getTimestamp());
            $anio = $viernes->format('Y');
            $numero_semana = ceil($viernes->format('d') / 7);
            
            $fecha_inicio = $domingo->format('Y-m-d');
            $fecha_fin = $viernes->format('Y-m-d');
            
            // Verificar si ya existe
            $sql_check = "SELECT id_semana FROM Semanas_Cobro 
                          WHERE fecha_inicio = :inicio AND fecha_fin = :fin";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bindValue(':inicio', $fecha_inicio);
            $stmt_check->bindValue(':fin', $fecha_fin);
            $stmt_check->execute();
            $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$existe) {
                // Insertar
                $sql_insert = "INSERT INTO Semanas_Cobro (mes, anio, numero_semana, fecha_inicio, fecha_fin, activa)
                              VALUES (:mes, :anio, :num, :inicio, :fin, 1)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bindValue(':mes', $mes_nombre);
                $stmt_insert->bindValue(':anio', $anio);
                $stmt_insert->bindValue(':num', $numero_semana);
                $stmt_insert->bindValue(':inicio', $fecha_inicio);
                $stmt_insert->bindValue(':fin', $fecha_fin);
                $stmt_insert->execute();
                $id_semana = $conn->lastInsertId();
            } else {
                $id_semana = $existe['id_semana'];
                
                // Asegurar que esté activa
                $sql_update = "UPDATE Semanas_Cobro SET activa = 1 WHERE id_semana = :id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bindValue(':id', $id_semana);
                $stmt_update->execute();
            }
            
            $semanas_ids[] = $id_semana;
        }
        
        // Desactivar todas las demás
        $placeholders = implode(',', array_fill(0, count($semanas_ids), '?'));
        $sql_desactivar = "UPDATE Semanas_Cobro SET activa = 0 WHERE id_semana NOT IN ($placeholders) AND activa = 1";
        $stmt_desactivar = $conn->prepare($sql_desactivar);
        $stmt_desactivar->execute($semanas_ids);
        
        return true;
    } catch (Exception $e) {
        error_log("Error actualizando semanas: " . $e->getMessage());
        return false;
    }
}

// Ejecutar actualización
if (isset($conn)) {
    actualizarSemanasAutomatico($conn);
}

?>