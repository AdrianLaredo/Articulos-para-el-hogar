// modulos/reporte_semanal/assets/js/main.js

let selectedPeriod = null;
let selectedReport = null;

/**
 * Seleccionar periodo
 */
function selectPeriod(period) {
  selectedPeriod = period;
  
  // Remover selección anterior
  document.querySelectorAll('[data-period]').forEach(card => {
    card.classList.remove('selected');
  });
  
  // Agregar selección
  document.querySelector(`[data-period="${period}"]`).classList.add('selected');
  
  // Mostrar selector de fechas
  document.getElementById('dateRange').style.display = 'block';
  
  // Configurar fechas según el periodo
  configureDateRange(period);
  
  // Validar si se puede generar
  validateSelection();
}

/**
 * Seleccionar tipo de reporte
 */
function selectReport(report) {
  selectedReport = report;
  
  // Remover selección anterior
  document.querySelectorAll('[data-report]').forEach(card => {
    card.classList.remove('selected');
  });
  
  // Agregar selección
  document.querySelector(`[data-report="${report}"]`).classList.add('selected');
  
  // Validar si se puede generar
  validateSelection();
}

/**
 * Configurar rango de fechas según periodo
 */
function configureDateRange(period) {
  const today = new Date();
  const fechaInicio = document.getElementById('fechaInicio');
  const fechaFin = document.getElementById('fechaFin');
  
  if (period === 'semanal') {
    // Obtener lunes de esta semana
    const dayOfWeek = today.getDay();
    const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
    const monday = new Date(today.setDate(diff));
    
    // Domingo
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    
    fechaInicio.value = formatDate(monday);
    fechaFin.value = formatDate(sunday);
    
  } else if (period === 'mensual') {
    // Primer día del mes
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    // Último día del mes
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    fechaInicio.value = formatDate(firstDay);
    fechaFin.value = formatDate(lastDay);
    
  } else if (period === 'anual') {
    // Primer día del año
    const firstDay = new Date(today.getFullYear(), 0, 1);
    // Último día del año
    const lastDay = new Date(today.getFullYear(), 11, 31);
    
    fechaInicio.value = formatDate(firstDay);
    fechaFin.value = formatDate(lastDay);
  }
}

/**
 * Formatear fecha a YYYY-MM-DD
 */
function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Validar selección
 */
function validateSelection() {
  const generateBtn = document.getElementById('generateBtn');
  const alert = document.getElementById('alert');
  
  if (selectedPeriod && selectedReport) {
    generateBtn.disabled = false;
    alert.classList.remove('show');
  } else {
    generateBtn.disabled = true;
  }
}

/**
 * Generar reporte
 */
function generateReport() {
  if (!selectedPeriod || !selectedReport) {
    document.getElementById('alert').classList.add('show');
    return;
  }

  const fechaInicio = document.getElementById('fechaInicio').value;
  const fechaFin = document.getElementById('fechaFin').value;

  if (!fechaInicio || !fechaFin) {
    alert('Por favor selecciona las fechas de inicio y fin');
    return;
  }

  // Validar que fecha inicio sea menor que fecha fin
  if (new Date(fechaInicio) > new Date(fechaFin)) {
    alert('La fecha de inicio debe ser menor que la fecha de fin');
    return;
  }

  // Redirigir según el tipo de reporte
  if (selectedReport === 'gasolina') {
    window.location.href = `reporte_gasolina.php?periodo=${selectedPeriod}&inicio=${fechaInicio}&fin=${fechaFin}`;
  } else if (selectedReport === 'ventas') {
    window.location.href = `reporte_ventas.php?periodo=${selectedPeriod}&inicio=${fechaInicio}&fin=${fechaFin}`;
  }
}

// Event listeners para cambios en fechas
document.addEventListener('DOMContentLoaded', function() {
  const fechaInicio = document.getElementById('fechaInicio');
  const fechaFin = document.getElementById('fechaFin');
  
  if (fechaInicio && fechaFin) {
    fechaInicio.addEventListener('change', validateSelection);
    fechaFin.addEventListener('change', validateSelection);
  }
});