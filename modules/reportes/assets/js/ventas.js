// modulos/reporte_semanal/assets/js/ventas.js

let chartPeriodo = null;
let chartEmpleados = null;

document.addEventListener('DOMContentLoaded', function() {
  if (datosVentas.porEmpleado.length > 0 || datosVentas.porPeriodo.length > 0) {
    initCharts();
  } else {
    mostrarMensajeSinDatos();
  }
});

/**
 * Inicializar gráficos
 */
function initCharts() {
  // ============================================
  // GRÁFICO 1: Folios por Periodo (Barras)
  // ============================================
  if (datosVentas.porPeriodo.length > 0) {
    chartPeriodo = echarts.init(document.getElementById('chart-periodo'), null, {
      renderer: 'canvas',
      useDirtyRect: false
    });
    
    const optionPeriodo = {
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        },
        formatter: function(params) {
          return params[0].name + '<br/>' + 
                 params[0].marker + ' ' + params[0].seriesName + ': <strong>' + params[0].value + ' folios</strong>';
        }
      },
      grid: {
        left: '8%',
        right: '4%',
        bottom: '12%',
        top: '8%',
        containLabel: true
      },
      xAxis: [
        {
          type: 'category',
          data: datosVentas.porPeriodo.map(item => item.name),
          axisTick: {
            alignWithLabel: true
          },
          axisLabel: {
            rotate: periodo === 'anual' ? 45 : 0,
            fontSize: 11,
            color: '#64748b',
            interval: 0
          }
        }
      ],
      yAxis: [
        {
          type: 'value',
          name: 'Folios',
          nameTextStyle: {
            color: '#64748b',
            fontSize: 12
          },
          minInterval: 1,
          axisLabel: {
            color: '#64748b'
          }
        }
      ],
      series: [
        {
          name: 'Folios Generados',
          type: 'bar',
          barWidth: '60%',
          data: datosVentas.porPeriodo.map(item => item.value),
          itemStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: '#0c3c78' },
              { offset: 1, color: '#1e5799' }
            ]),
            borderRadius: [4, 4, 0, 0]
          },
          label: {
            show: true,
            position: 'top',
            fontSize: 12,
            fontWeight: 'bold',
            color: '#0c3c78'
          },
          emphasis: {
            itemStyle: {
              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: '#10b981' },
                { offset: 1, color: '#34d399' }
              ])
            }
          }
        }
      ]
    };

    chartPeriodo.setOption(optionPeriodo);
  }

  // ============================================
  // GRÁFICO 2: Top Empleados (Barras Horizontales)
  // ============================================
  if (datosVentas.porEmpleado.length > 0) {
    chartEmpleados = echarts.init(document.getElementById('chart-empleados'), null, {
      renderer: 'canvas',
      useDirtyRect: false
    });
    
    const empleadosOrdenados = datosVentas.porEmpleado;
    
    const optionEmpleados = {
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        },
        formatter: function(params) {
          return params[0].name + '<br/>' + 
                 params[0].marker + ' Folios: <strong>' + params[0].value + '</strong>';
        }
      },
      grid: {
        left: '5%',
        right: '10%',
        bottom: '5%',
        top: '5%',
        containLabel: true
      },
      xAxis: [
        {
          type: 'value',
          name: 'Folios',
          nameTextStyle: {
            color: '#64748b',
            fontSize: 12
          },
          minInterval: 1,
          axisLabel: {
            color: '#64748b',
            fontSize: 11
          }
        }
      ],
      yAxis: [
        {
          type: 'category',
          data: empleadosOrdenados.map(item => item.name).reverse(),
          axisTick: {
            alignWithLabel: true
          },
          axisLabel: {
            fontSize: 11,
            color: '#64748b'
          }
        }
      ],
      series: [
        {
          name: 'Folios',
          type: 'bar',
          barWidth: '60%',
          data: empleadosOrdenados.map((item, index) => {
            const posicionReal = empleadosOrdenados.length - index - 1;
            const porcentaje = posicionReal / (empleadosOrdenados.length - 1);
            
            let color;
            if (porcentaje >= 0.7) {
              color = new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                { offset: 0, color: '#10b981' },
                { offset: 1, color: '#34d399' }
              ]);
            } else if (porcentaje >= 0.4) {
              color = new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                { offset: 0, color: '#3b82f6' },
                { offset: 1, color: '#60a5fa' }
              ]);
            } else {
              color = new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                { offset: 0, color: '#64748b' },
                { offset: 1, color: '#94a3b8' }
              ]);
            }
            
            return {
              value: item.value,
              itemStyle: {
                color: color,
                borderRadius: [0, 4, 4, 0]
              }
            };
          }).reverse(),
          label: {
            show: true,
            position: 'right',
            fontSize: 13,
            fontWeight: 'bold',
            color: '#0c3c78',
            formatter: '{c}'
          },
          emphasis: {
            itemStyle: {
              shadowBlur: 10,
              shadowOffsetX: 0,
              shadowColor: 'rgba(0, 0, 0, 0.3)'
            }
          }
        }
      ]
    };

    chartEmpleados.setOption(optionEmpleados);
  }

  // Responsive
  window.addEventListener('resize', function() {
    if (chartPeriodo) chartPeriodo.resize();
    if (chartEmpleados) chartEmpleados.resize();
  });

  // Evento ANTES de imprimir - Redimensionar gráficas
  window.addEventListener('beforeprint', function() {
    prepararImpresion();
  });

  // Evento DESPUÉS de imprimir - Restaurar gráficas
  window.addEventListener('afterprint', function() {
    restaurarDespuesImpresion();
  });
}

/**
 * Preparar gráficas para impresión
 */
function prepararImpresion() {
  // Esperar un momento para que el navegador ajuste el layout
  setTimeout(function() {
    if (chartPeriodo) {
      chartPeriodo.resize({
        width: 700,
        height: 280
      });
    }
    if (chartEmpleados) {
      chartEmpleados.resize({
        width: 700,
        height: 280
      });
    }
  }, 100);
}

/**
 * Restaurar gráficas después de imprimir
 */
function restaurarDespuesImpresion() {
  setTimeout(function() {
    if (chartPeriodo) chartPeriodo.resize();
    if (chartEmpleados) chartEmpleados.resize();
  }, 100);
}

/**
 * Mostrar mensaje cuando no hay datos
 */
function mostrarMensajeSinDatos() {
  const containers = document.querySelectorAll('.chart-container');
  containers.forEach(container => {
    container.innerHTML = `
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #9ca3af;">
        <i class='bx bx-data' style="font-size: 4rem; margin-bottom: 1rem;"></i>
        <p style="font-size: 1.1rem;">No hay datos de ventas en este periodo</p>
        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Intenta seleccionar otro periodo</p>
      </div>
    `;
  });
}

/**
 * Exportar a PDF
 */
function exportarPDF() {
  alert('Funcionalidad de exportar a PDF en desarrollo. Puedes usar la función de imprimir del navegador.');
}