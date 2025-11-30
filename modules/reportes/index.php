<?php
// modulos/reporte_semanal/index.php
session_start();
require_once '../../bd/database.php';

// Validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Calcular año de inicio del sistema
$anioInicio = 2025;
$anioActual = date('Y');
$mesActual = date('n'); // 1-12

// Determinar si estamos en el año de inicio
$enAnioInicio = ($anioActual == $anioInicio);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generador de Reportes</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <h1><i class='bx bx-bar-chart-alt-2'></i> Generador de Reportes</h1>
      <p>Selecciona el tipo de reporte y el periodo que deseas consultar</p>
    </div>

    <!-- Selector de Reportes -->
    <div class="report-selector">
      <!-- Sección 1: Tipo de Reporte -->
      <div class="section">
        <div class="section-title">
          <span>📊</span> Tipo de Reporte
        </div>
        <div class="options-grid">
          <div class="option-card" data-report="gasolina">
            <i class='bx bxs-gas-pump option-icon'></i>
            <div class="option-title">Gasolina</div>
            <div class="option-description">Consumo de litros y efectivo</div>
          </div>
          <div class="option-card" data-report="ventas">
            <i class='bx bx-shopping-bag option-icon'></i>
            <div class="option-title">Ventas</div>
            <div class="option-description">Folios y productos vendidos</div>
          </div>
          <div class="option-card" data-report="comisiones">
            <i class='bx bx-money option-icon'></i>
            <div class="option-title">Comisiones</div>
            <div class="option-description">Comisiones por empleado</div>
          </div>
          <div class="option-card" data-report="cobradores">
            <i class='bx bx-wallet option-icon'></i>
            <div class="option-title">Cobradores</div>
            <div class="option-description">Cobros por empleado y zona</div>
          </div>
          <div class="option-card" data-report="nomina">
            <i class='bx bx-receipt option-icon'></i>
            <div class="option-title">Nómina</div>
            <div class="option-description">Nómina completa consolidada</div>
          </div>
        </div>
      </div>

      <!-- Sección 2: Periodo -->
      <div class="section">
        <div class="section-title">
          <span>📅</span> Periodo de Tiempo
        </div>
        <div class="options-grid">
          <div class="option-card" data-periodo="semanal">
            <i class='bx bx-calendar-week option-icon'></i>
            <div class="option-title">Semanal (Lun-Dom)</div>
            <div class="option-description">Últimas 5 semanas completas</div>
          </div>
          <div class="option-card" data-periodo="semanal_dom_vie">
            <i class='bx bx-calendar-week option-icon'></i>
            <div class="option-title">Semanal (Dom-Vie)</div>
            <div class="option-description">Últimas 5 semanas laborales</div>
          </div>
          <div class="option-card" data-periodo="mensual">
            <i class='bx bx-calendar option-icon'></i>
            <div class="option-title">Mensual</div>
            <div class="option-description" id="mensualDesc">Meses completos disponibles</div>
          </div>
          <div class="option-card" data-periodo="anual">
            <i class='bx bx-calendar-event option-icon'></i>
            <div class="option-title">Anual</div>
            <div class="option-description" id="anualDesc">
              <?php 
              if ($enAnioInicio) {
                echo "Del 01/01/$anioInicio a hoy";
              } else {
                echo "Años completos disponibles";
              }
              ?>
            </div>
          </div>
          <div class="option-card" data-periodo="personalizado">
            <i class='bx bx-customize option-icon'></i>
            <div class="option-title">Personalizado</div>
            <div class="option-description">Selecciona fechas</div>
          </div>
        </div>

        <!-- Selector de Semanas Lun-Dom -->
        <div class="date-range" id="semanalSelector" style="display: none;">
          <label>
            <i class='bx bx-calendar-week'></i> Selecciona la semana (Lunes a Domingo)
          </label>
          <select id="semanaSelect" class="periodo-select">
            <option value="">Cargando semanas disponibles...</option>
          </select>
        </div>

        <!-- Selector de Semanas Dom-Vie -->
        <div class="date-range" id="semanalDomVieSelector" style="display: none;">
          <label>
            <i class='bx bx-calendar-week'></i> Selecciona la semana (Domingo a Viernes)
          </label>
          <select id="semanaDomVieSelect" class="periodo-select">
            <option value="">Cargando semanas disponibles...</option>
          </select>
        </div>

        <!-- Selector de Meses -->
        <div class="date-range" id="mensualSelector" style="display: none;">
          <label>
            <i class='bx bx-calendar'></i> Selecciona el mes completo
          </label>
          <select id="mesSelect" class="periodo-select">
            <option value="">Cargando meses disponibles...</option>
          </select>
        </div>

        <!-- Selector de Años -->
        <div class="date-range" id="anualSelector" style="display: none;">
          <label>
            <i class='bx bx-calendar-event'></i> <span id="anualLabel">Selecciona el año</span>
          </label>
          <select id="anioSelect" class="periodo-select">
            <option value="">Cargando años disponibles...</option>
          </select>
        </div>

        <!-- Selector de Rango Personalizado -->
        <div class="date-range" id="personalizadoSelector" style="display: none;">
          <label>
            <i class='bx bx-calendar'></i> Selecciona el rango de fechas
          </label>
          <div class="date-inputs">
            <div>
              <label>Fecha de Inicio</label>
              <input type="date" id="fechaInicio">
            </div>
            <div>
              <label>Fecha de Fin</label>
              <input type="date" id="fechaFin">
            </div>
          </div>
        </div>
      </div>

      <!-- Alerta -->
      <div class="alert" id="alertMessage">
        <i class='bx bx-info-circle'></i>
        <span id="alertText">Por favor selecciona un tipo de reporte y un periodo</span>
      </div>

      <!-- Botón Generar -->
      <button class="generate-btn" id="generateBtn" disabled>
        <i class='bx bx-line-chart'></i> Generar Reporte
      </button>
    </div>
  </div>

  <script>
    // Pasar variables PHP a JavaScript
    const ANIO_INICIO = <?php echo $anioInicio; ?>;
    const ANIO_ACTUAL = <?php echo $anioActual; ?>;
    const MES_ACTUAL = <?php echo $mesActual; ?>;
    const EN_ANIO_INICIO = <?php echo $enAnioInicio ? 'true' : 'false'; ?>;
  </script>
  <script src="assets/js/selector.js"></script>
</body>
</html>