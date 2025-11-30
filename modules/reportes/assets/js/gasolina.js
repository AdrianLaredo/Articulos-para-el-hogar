// modulos/reporte_semanal/assets/js/gasolina.js

document.addEventListener('DOMContentLoaded', function() {
  initCharts();
});

// Variables globales para los gráficos
let chartLitros, chartEfectivo;

/**
 * Inicializar gráficos
 */
function initCharts() {
  // Verificar si hay datos de litros
  if (!datosLitros.hayDatos || datosLitros.porTipo.length === 0 || datosLitros.porDia.length === 0) {
    mostrarMensajeSinDatos('chart-litros', 'litros');
  } else {
    renderChartLitros();
  }

  // Verificar si hay datos de efectivo
  if (!datosEfectivo.hayDatos || datosEfectivo.porEmpleado.length === 0 || datosEfectivo.porDia.length === 0) {
    mostrarMensajeSinDatos('chart-efectivo', 'efectivo');
  } else {
    renderChartEfectivo();
  }
}

/**
 * Renderizar gráfico de litros CON DINERO
 */
function renderChartLitros() {
  chartLitros = echarts.init(document.getElementById('chart-litros'), null, {
    renderer: 'canvas',
    devicePixelRatio: window.devicePixelRatio || 2
  });
  
  const optionLitros = {
    tooltip: {
      trigger: 'item',
      formatter: function(params) {
        const litros = params.value.toFixed(2);
        const dinero = params.data.dinero ? '$' + params.data.dinero.toLocaleString('es-MX', {minimumFractionDigits: 2}) : '$0.00';
        return params.name + '<br/>' + 
               litros + ' L (' + params.percent.toFixed(1) + '%)<br/>' +
               '<strong>' + dinero + '</strong>';
      }
    },
    legend: {
      orient: 'vertical',
      left: '2%',
      top: 'center',
      textStyle: {
        fontSize: 11
      }
    },
    series: [
      {
        name: 'Por Vehículo',
        type: 'pie',
        selectedMode: 'single',
        radius: [0, '28%'],
        center: ['50%', '50%'],
        label: {
          position: 'inner',
          fontSize: 11,
          formatter: function(params) {
            return params.name + '\n' + params.value.toFixed(1) + ' L';
          }
        },
        labelLine: {
          show: false
        },
        data: datosLitros.porTipo
      },
      {
        name: 'Por Periodo',
        type: 'pie',
        radius: ['38%', '58%'],
        center: ['50%', '50%'],
        labelLine: {
          length: 15,
          length2: 8
        },
        label: {
          formatter: function(params) {
            const dinero = params.data.dinero ? '$' + params.data.dinero.toFixed(0) : '';
            return params.name + '\n' + params.value.toFixed(1) + ' L\n' + dinero + '\n(' + params.percent.toFixed(1) + '%)';
          },
          fontSize: 10
        },
        data: datosLitros.porDia
      }
    ]
  };

  chartLitros.setOption(optionLitros);

  // Responsive
  window.addEventListener('resize', function() {
    if (chartLitros) chartLitros.resize();
  });

  // Ajustar para impresión
  window.addEventListener('beforeprint', function() {
    if (chartLitros) {
      chartLitros.setOption({
        legend: { left: '0%' },
        series: [
          { center: ['20%', '50%'], radius: [0, '26%'] },
          { center: ['20%', '50%'], radius: ['35%', '54%'] }
        ]
      });
      chartLitros.resize();
    }
  });

  window.addEventListener('afterprint', function() {
    if (chartLitros) {
      chartLitros.setOption({
        legend: { left: '2%' },
        series: [
          { center: ['50%', '50%'], radius: [0, '28%'] },
          { center: ['50%', '50%'], radius: ['38%', '58%'] }
        ]
      });
      chartLitros.resize();
    }
  });
}

/**
 * Renderizar gráfico de efectivo
 */
function renderChartEfectivo() {
  chartEfectivo = echarts.init(document.getElementById('chart-efectivo'), null, {
    renderer: 'canvas',
    devicePixelRatio: window.devicePixelRatio || 2
  });
  
  const optionEfectivo = {
    tooltip: {
      trigger: 'item',
      formatter: function(params) {
        return params.name + '<br/>' + 
               '$' + params.value.toLocaleString('es-MX', {minimumFractionDigits: 2}) + 
               ' (' + params.percent.toFixed(1) + '%)';
      }
    },
    legend: {
      orient: 'vertical',
      left: '2%',
      top: 'center',
      textStyle: {
        fontSize: 11
      }
    },
    series: [
      {
        name: 'Por Empleado',
        type: 'pie',
        selectedMode: 'single',
        radius: [0, '28%'],
        center: ['50%', '50%'],
        label: {
          position: 'inner',
          fontSize: 11,
          formatter: function(params) {
            return params.name + '\n$' + params.value.toFixed(0);
          }
        },
        labelLine: {
          show: false
        },
        data: datosEfectivo.porEmpleado
      },
      {
        name: 'Por Periodo',
        type: 'pie',
        radius: ['38%', '58%'],
        center: ['50%', '50%'],
        labelLine: {
          length: 15,
          length2: 8
        },
        label: {
          formatter: function(params) {
            return params.name + '\n$' + params.value.toFixed(2) + '\n(' + params.percent.toFixed(1) + '%)';
          },
          fontSize: 11
        },
        data: datosEfectivo.porDia
      }
    ]
  };

  chartEfectivo.setOption(optionEfectivo);

  // Responsive
  window.addEventListener('resize', function() {
    if (chartEfectivo) chartEfectivo.resize();
  });

  // Ajustar para impresión
  window.addEventListener('beforeprint', function() {
    if (chartEfectivo) {
      chartEfectivo.setOption({
        legend: { left: '0%' },
        series: [
          { center: ['20%', '50%'], radius: [0, '26%'] },
          { center: ['20%', '50%'], radius: ['35%', '54%'] }
        ]
      });
      chartEfectivo.resize();
    }
  });

  window.addEventListener('afterprint', function() {
    if (chartEfectivo) {
      chartEfectivo.setOption({
        legend: { left: '2%' },
        series: [
          { center: ['50%', '50%'], radius: [0, '28%'] },
          { center: ['50%', '50%'], radius: ['38%', '58%'] }
        ]
      });
      chartEfectivo.resize();
    }
  });
}

/**
 * Mostrar mensaje cuando no hay datos
 */
function mostrarMensajeSinDatos(containerId, tipo) {
  const container = document.getElementById(containerId);
  const icono = tipo === 'litros' ? 'bx-tint' : 'bx-dollar-circle';
  const mensaje = tipo === 'litros' ? 'litros' : 'efectivo';
  
  container.innerHTML = `
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; color: #9ca3af;">
      <i class='bx ${icono}' style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
      <p style="font-size: 1.2rem; font-weight: 600; color: #64748b;">No hay datos de ${mensaje} en este periodo</p>
      <p style="font-size: 0.95rem; margin-top: 0.5rem; color: #94a3b8;">Intenta seleccionar otro rango de fechas</p>
    </div>
  `;
}

/**
 * Exportar a PDF
 */
function exportarPDF() {
  alert('Funcionalidad de exportar a PDF en desarrollo.\nPor ahora, puedes usar la opción de imprimir del navegador.');
}