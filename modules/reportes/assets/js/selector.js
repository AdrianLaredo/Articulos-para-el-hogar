// modulos/reporte_semanal/assets/js/selector.js

let reporteSeleccionado = null;
let periodoSeleccionado = null;
let fechaInicio = null;
let fechaFin = null;

document.addEventListener('DOMContentLoaded', function() {
  initializeSelectors();
  initializeDateInputs();
  generatePeriodOptions();
});

/**
 * Inicializar selectores de reporte y periodo
 */
function initializeSelectors() {
  // Selector de tipo de reporte
  const reportCards = document.querySelectorAll('[data-report]');
  reportCards.forEach(card => {
    card.addEventListener('click', function() {
      reportCards.forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      reporteSeleccionado = this.dataset.report;
      checkFormValidity();
    });
  });

  // Selector de periodo
  const periodoCards = document.querySelectorAll('[data-periodo]');
  periodoCards.forEach(card => {
    card.addEventListener('click', function() {
      if (this.classList.contains('disabled')) {
        return;
      }
      
      periodoCards.forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      periodoSeleccionado = this.dataset.periodo;

      // Ocultar todos los selectores
      document.getElementById('semanalSelector').style.display = 'none';
      document.getElementById('semanalDomVieSelector').style.display = 'none';
      document.getElementById('mensualSelector').style.display = 'none';
      document.getElementById('anualSelector').style.display = 'none';
      document.getElementById('personalizadoSelector').style.display = 'none';

      // Resetear fechas
      fechaInicio = null;
      fechaFin = null;
      document.getElementById('fechaInicio').value = '';
      document.getElementById('fechaFin').value = '';

      // Mostrar el selector correspondiente
      if (periodoSeleccionado === 'semanal') {
        document.getElementById('semanalSelector').style.display = 'block';
      } else if (periodoSeleccionado === 'semanal_dom_vie') {
        document.getElementById('semanalDomVieSelector').style.display = 'block';
      } else if (periodoSeleccionado === 'mensual') {
        document.getElementById('mensualSelector').style.display = 'block';
      } else if (periodoSeleccionado === 'anual') {
        document.getElementById('anualSelector').style.display = 'block';
        // Auto-seleccionar el año si hay opciones
        const anioSelect = document.getElementById('anioSelect');
        if (anioSelect.options.length > 0 && anioSelect.value) {
          const [inicio, fin] = anioSelect.value.split('|');
          fechaInicio = inicio;
          fechaFin = fin;
        }
      } else if (periodoSeleccionado === 'personalizado') {
        document.getElementById('personalizadoSelector').style.display = 'block';
      }

      checkFormValidity();
    });
  });

  // Event listeners para los selectores
  document.getElementById('semanaSelect').addEventListener('change', function() {
    if (this.value) {
      const [inicio, fin] = this.value.split('|');
      fechaInicio = inicio;
      fechaFin = fin;
      checkFormValidity();
    }
  });

  document.getElementById('semanaDomVieSelect').addEventListener('change', function() {
    if (this.value) {
      const [inicio, fin] = this.value.split('|');
      fechaInicio = inicio;
      fechaFin = fin;
      checkFormValidity();
    }
  });

  document.getElementById('mesSelect').addEventListener('change', function() {
    if (this.value) {
      const [inicio, fin] = this.value.split('|');
      fechaInicio = inicio;
      fechaFin = fin;
      checkFormValidity();
    }
  });

  document.getElementById('anioSelect').addEventListener('change', function() {
    if (this.value) {
      const [inicio, fin] = this.value.split('|');
      fechaInicio = inicio;
      fechaFin = fin;
      checkFormValidity();
    }
  });

  document.getElementById('generateBtn').addEventListener('click', generarReporte);
}

/**
 * Inicializar inputs de fecha personalizados
 */
function initializeDateInputs() {
  const fechaInicioInput = document.getElementById('fechaInicio');
  const fechaFinInput = document.getElementById('fechaFin');

  // Solo establecer fecha máxima (hoy), sin fecha mínima
  const hoy = new Date().toISOString().split('T')[0];
  
  // SIN fecha mínima - puede seleccionar cualquier fecha del pasado
  fechaInicioInput.max = hoy;
  fechaFinInput.max = hoy;

  fechaInicioInput.addEventListener('change', function() {
    fechaInicio = this.value;
    fechaFinInput.min = this.value; // La fecha fin no puede ser antes que la de inicio
    checkFormValidity();
  });

  fechaFinInput.addEventListener('change', function() {
    fechaFin = this.value;
    checkFormValidity();
  });
}

/**
 * Generar opciones de periodos disponibles
 */
function generatePeriodOptions() {
  generateWeekOptions();
  generateWeekDomVieOptions();
  generateMonthOptions();
  generateYearOptions();
}

/**
 * Generar últimas 5 semanas completas (Lun-Dom)
 */
function generateWeekOptions() {
  const select = document.getElementById('semanaSelect');
  select.innerHTML = '<option value="">Selecciona una semana...</option>';
  
  const hoy = new Date();
  const diaSemana = hoy.getDay();
  
  let domingoAnterior = new Date(hoy);
  
  if (diaSemana === 0) {
    domingoAnterior.setDate(hoy.getDate() - 7);
  } else {
    domingoAnterior.setDate(hoy.getDate() - diaSemana);
  }
  
  for (let i = 0; i < 5; i++) {
    const domingo = new Date(domingoAnterior);
    domingo.setDate(domingoAnterior.getDate() - (i * 7));
    
    const lunes = new Date(domingo);
    lunes.setDate(domingo.getDate() - 6);
    
    const inicioStr = formatDate(lunes);
    const finStr = formatDate(domingo);
    
    const option = document.createElement('option');
    option.value = `${inicioStr}|${finStr}`;
    option.textContent = `${formatDateDisplay(lunes)} - ${formatDateDisplay(domingo)}`;
    select.appendChild(option);
  }
}

/**
 * Generar últimas 5 semanas laborales (Dom-Vie)
 */
function generateWeekDomVieOptions() {
  const select = document.getElementById('semanaDomVieSelect');
  select.innerHTML = '<option value="">Selecciona una semana...</option>';
  
  const hoy = new Date();
  const diaSemana = hoy.getDay(); // 0=Domingo, 1=Lunes, ..., 6=Sábado
  
  // Encontrar el viernes más reciente COMPLETADO (pasado)
  let viernesAnterior = new Date(hoy);
  
  if (diaSemana === 6) {
    // Hoy es sábado, el viernes fue ayer
    viernesAnterior.setDate(hoy.getDate() - 1);
  } else if (diaSemana === 0) {
    // Hoy es domingo, el viernes fue hace 2 días
    viernesAnterior.setDate(hoy.getDate() - 2);
  } else {
    // Es lunes (1) a viernes (5)
    // Retroceder al viernes anterior
    const diasDesdeViernes = (diaSemana + 2) % 7;
    viernesAnterior.setDate(hoy.getDate() - diasDesdeViernes);
  }
  
  // Generar las últimas 5 semanas Dom-Vie
  for (let i = 0; i < 5; i++) {
    const viernes = new Date(viernesAnterior);
    viernes.setDate(viernesAnterior.getDate() - (i * 7));
    
    // El domingo es 5 días antes del viernes
    const domingo = new Date(viernes);
    domingo.setDate(viernes.getDate() - 5);
    
    const inicioStr = formatDate(domingo);
    const finStr = formatDate(viernes);
    
    const option = document.createElement('option');
    option.value = `${inicioStr}|${finStr}`;
    option.textContent = `${formatDateDisplay(domingo)} - ${formatDateDisplay(viernes)}`;
    select.appendChild(option);
  }
}

/**
 * Generar meses completos disponibles (últimos 12 meses)
 */
function generateMonthOptions() {
  const select = document.getElementById('mesSelect');
  select.innerHTML = '<option value="">Selecciona un mes...</option>';
  
  const hoy = new Date();
  const mesActualNum = hoy.getMonth(); // 0-11
  const anioActualNum = hoy.getFullYear();
  
  // Array para almacenar los meses
  let meses = [];
  
  // Generar los últimos 12 meses completos
  for (let i = 1; i <= 12; i++) {
    let fecha = new Date(hoy);
    fecha.setMonth(fecha.getMonth() - i);
    
    // Verificar que no sea el mes actual
    if (fecha.getMonth() === mesActualNum && fecha.getFullYear() === anioActualNum) {
      continue;
    }
    
    const mes = fecha.getMonth();
    const anio = fecha.getFullYear();
    
    const primerDia = new Date(anio, mes, 1);
    const ultimoDia = new Date(anio, mes + 1, 0);
    
    const inicioStr = formatDate(primerDia);
    const finStr = formatDate(ultimoDia);
    
    meses.push({
      value: `${inicioStr}|${finStr}`,
      text: `${getMesNombre(mes)} ${anio}`
    });
  }
  
  // Agregar los meses al select (están en orden inverso, así que los invertimos)
  meses.reverse().forEach(mes => {
    const option = document.createElement('option');
    option.value = mes.value;
    option.textContent = mes.text;
    select.appendChild(option);
  });
  
  if (select.options.length === 1) {
    select.innerHTML = '<option value="">No hay meses completos disponibles aún</option>';
  }
}

/**
 * Generar años disponibles (últimos 5 años)
 */
function generateYearOptions() {
  const select = document.getElementById('anioSelect');
  const label = document.getElementById('anualLabel');
  select.innerHTML = '';
  
  const hoy = new Date();
  const anioActualNum = hoy.getFullYear();
  const anioInicio = typeof ANIO_INICIO !== 'undefined' ? ANIO_INICIO : 2025;
  const enAnioInicio = typeof EN_ANIO_INICIO !== 'undefined' ? EN_ANIO_INICIO : (anioActualNum === anioInicio);
  
  // Calcular cuántos años han pasado desde el inicio
  const aniosPasados = anioActualNum - anioInicio;
  
  // Si estamos en el primer año (2025)
  if (enAnioInicio) {
    label.textContent = `Año ${anioInicio} (hasta hoy)`;
    const inicioStr = `${anioInicio}-01-01`;
    const finStr = formatDate(hoy);
    
    const option = document.createElement('option');
    option.value = `${inicioStr}|${finStr}`;
    option.textContent = `Año ${anioInicio} (01/01/${anioInicio} - ${formatDateDisplay(hoy)})`;
    option.selected = true;
    select.appendChild(option);
    
    fechaInicio = inicioStr;
    fechaFin = finStr;
  } else {
    label.textContent = 'Selecciona el año';
    
    // Determinar cuántos años mostrar (máximo 5, o menos si no han pasado 5 años)
    const aniosAMostrar = Math.min(5, aniosPasados + 1);
    const anioDesde = anioActualNum - aniosAMostrar + 1;
    
    // Generar años desde el más antiguo al más reciente
    for (let anio = Math.max(anioInicio, anioDesde); anio < anioActualNum; anio++) {
      const inicioStr = `${anio}-01-01`;
      const finStr = `${anio}-12-31`;
      
      const option = document.createElement('option');
      option.value = `${inicioStr}|${finStr}`;
      option.textContent = `Año ${anio} (Completo)`;
      select.appendChild(option);
    }
    
    // Agregar año actual (hasta hoy)
    const inicioStrActual = `${anioActualNum}-01-01`;
    const finStrActual = formatDate(hoy);
    
    const optionActual = document.createElement('option');
    optionActual.value = `${inicioStrActual}|${finStrActual}`;
    optionActual.textContent = `Año ${anioActualNum} (hasta hoy)`;
    select.appendChild(optionActual);
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
 * Formatear fecha para mostrar (dd/mm/yyyy)
 */
function formatDateDisplay(date) {
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
}

/**
 * Obtener nombre del mes
 */
function getMesNombre(mes) {
  const meses = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];
  return meses[mes];
}

/**
 * Validar formulario
 */
function checkFormValidity() {
  const generateBtn = document.getElementById('generateBtn');
  const alertMessage = document.getElementById('alertMessage');
  const alertText = document.getElementById('alertText');

  let isValid = false;
  let mensaje = '';

  if (!reporteSeleccionado) {
    mensaje = 'Por favor selecciona un tipo de reporte';
  } else if (!periodoSeleccionado) {
    mensaje = 'Por favor selecciona un periodo';
  } else if (!fechaInicio || !fechaFin) {
    mensaje = 'Por favor selecciona un periodo específico';
  } else {
    isValid = true;
    mensaje = '✓ Todo listo para generar el reporte';
  }

  generateBtn.disabled = !isValid;
  alertText.textContent = mensaje;
  
  if (isValid) {
    alertMessage.classList.remove('show');
  } else {
    alertMessage.classList.add('show');
  }
}
/**
 * Generar reporte
 */
function generarReporte() {
  if (!reporteSeleccionado || !periodoSeleccionado || !fechaInicio || !fechaFin) {
    alert('Por favor completa todos los campos');
    return;
  }

  // Determinar el nombre del archivo según el tipo de reporte
  let nombreArchivo = '';
  
  switch(reporteSeleccionado) {
    case 'gasolina':
      nombreArchivo = 'reporte_gasolina.php';
      break;
    case 'ventas':
      nombreArchivo = 'reporte_ventas.php';
      break;
    case 'comisiones':
      nombreArchivo = 'reportes_comisiones.php';
      break;
    case 'cobradores':
      nombreArchivo = 'reporte_cobradores.php';
      break;
    case 'nomina':  // ⭐ AGREGAR ESTA LÍNEA
      nombreArchivo = 'reporte_nomina.php';  // ⭐ AGREGAR ESTA LÍNEA
      break;  // ⭐ AGREGAR ESTA LÍNEA
    default:
      alert('Tipo de reporte no válido');
      return;
  }

  // Ajustar el nombre del periodo para la URL
  const periodoUrl = periodoSeleccionado === 'semanal_dom_vie' ? 'semanal' : periodoSeleccionado;

  const url = `${nombreArchivo}?periodo=${periodoUrl}&inicio=${fechaInicio}&fin=${fechaFin}`;
  window.location.href = url;
}