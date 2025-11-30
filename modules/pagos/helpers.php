<?php
/**
 * Funciones auxiliares para el módulo de pagos
 * Zeus Hogar - Sistema de Gestión
 */

/**
 * Convierte el número de mes a nombre en español
 */
function obtenerMesEspanol($numero_mes) {
    $meses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];
    
    return $meses[$numero_mes] ?? '';
}

/**
 * Nota: Asegúrate de tener también las siguientes funciones en tu archivo functions.php:
 * - obtenerSemanasActivas($conn)
 * - calcularTotalesHistoricos($conn)
 * - calcularFondosDisponibles($conn, $id_semana)
 * - guardarHistorialFondos($conn, $id_semana, $fondos)
 * - obtenerPagosPendientes($conn, $id_semana)
 * - obtenerPagosRealizados($conn, $id_semana)
 */
?>