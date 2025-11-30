// ============================================
// MÓDULO DE GASOLINA - JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== VARIABLES GLOBALES =====
    let formularioModificado = false;
    
    // ===== ACTIVAR TAB SEGÚN PARÁMETRO URL =====
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam === 'historial') {
        // Cambiar a tab de historial automáticamente
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        const historialBtn = document.querySelector('[data-tab="historial"]');
        const historialContent = document.getElementById('historial');
        
        if (historialBtn && historialContent) {
            historialBtn.classList.add('active');
            historialContent.classList.add('active');
        }
    }
    
    // ===== AUTO-SCROLL A MENSAJES =====
    const mensaje = document.getElementById('mensaje');
    if (mensaje) {
        setTimeout(() => {
            mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => {
                mensaje.style.opacity = '0';
                setTimeout(() => mensaje.remove(), 300);
            }, 5000);
        }, 100);
    }

    // ===== SISTEMA DE TABS =====
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // ===== PRECIO FIJO / EDITABLE EN UN SOLO CAMPO =====
const precioInput = document.getElementById('precioLitro');
const btnEditarPrecio = document.getElementById('btnEditarPrecio');

if (precioInput && btnEditarPrecio) {
  btnEditarPrecio.addEventListener('click', () => {
    if (precioInput.hasAttribute('readonly')) {
      precioInput.removeAttribute('readonly');
      precioInput.focus();
      btnEditarPrecio.innerHTML = "<i class='bx bx-lock-open'></i>";
    } else {
      let valor = parseFloat(precioInput.value);
      if (isNaN(valor) || valor <= 0) {
        alert("Por favor ingrese un precio válido mayor que 0.");
        precioInput.focus();
        return;
      }
      precioInput.setAttribute('readonly', true);
      btnEditarPrecio.innerHTML = "<i class='bx bx-edit'></i>";
    }
  });
}

    // ===== CAMBIO DE TIPO DE CARGA =====
    const radiosTipo = document.querySelectorAll('input[name="tipoCarga"]');
    const seccionLitros = document.getElementById('seccionLitros');
    const seccionEfectivo = document.getElementById('seccionEfectivo');
    const tipoCargaInput = document.getElementById('tipoCargaInput');
    
    radiosTipo.forEach(radio => {
        radio.addEventListener('change', function() {
            const tipo = this.value;
            tipoCargaInput.value = tipo;
            
            if (tipo === 'litros') {
                seccionLitros.style.display = 'block';
                seccionEfectivo.style.display = 'none';
                
                document.getElementById('vehiculo').required = true;
                document.getElementById('litros').required = true;
                document.getElementById('precioLitro').required = true;
                document.getElementById('empleado').required = false;
                document.getElementById('montoEfectivo').required = false;
                
                document.getElementById('empleado').value = '';
                document.getElementById('montoEfectivo').value = '';
                document.getElementById('totalEfectivo').textContent = '0.00';
                
            } else {
                seccionEfectivo.style.display = 'block';
                seccionLitros.style.display = 'none';
                
                document.getElementById('empleado').required = true;
                document.getElementById('montoEfectivo').required = true;
                document.getElementById('vehiculo').required = false;
                document.getElementById('litros').required = false;
                document.getElementById('precioLitro').required = false;
                
                document.getElementById('vehiculo').value = '';
                document.getElementById('placas').value = '';
                document.getElementById('color').value = '';
                document.getElementById('modelo').value = '';
                document.getElementById('marca').value = '';
                document.getElementById('litros').value = '';
                document.getElementById('precioLitro').value = '';
                document.getElementById('totalLitros').textContent = '0.00';
            }
            
            formularioModificado = true;
        });
    });

    // ===== SELECCIÓN DE VEHÍCULO =====
    const selectVehiculo = document.getElementById('vehiculo');
    
    if (selectVehiculo) {
        selectVehiculo.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            
            if (this.value) {
                document.getElementById('placas').value = selected.dataset.placas || '';
                document.getElementById('color').value = selected.dataset.color || '';
                document.getElementById('modelo').value = selected.dataset.modelo || '';
                document.getElementById('marca').value = selected.dataset.marca || '';
            } else {
                document.getElementById('placas').value = '';
                document.getElementById('color').value = '';
                document.getElementById('modelo').value = '';
                document.getElementById('marca').value = '';
            }
            
            formularioModificado = true;
        });
    }

    // ===== CÁLCULO AUTOMÁTICO DE TOTALES =====
    const inputLitros = document.getElementById('litros');
    const inputPrecioLitro = document.getElementById('precioLitro');
    const totalLitros = document.getElementById('totalLitros');
    
    function calcularTotalLitros() {
        const litros = parseFloat(inputLitros.value) || 0;
        const precio = parseFloat(inputPrecioLitro.value) || 0;
        const total = litros * precio;
        totalLitros.textContent = total.toFixed(2);
    }
    
    if (inputLitros) {
        inputLitros.addEventListener('input', function() {
            formularioModificado = true;
            calcularTotalLitros();
        });
    }
    
    if (inputPrecioLitro) {
        inputPrecioLitro.addEventListener('input', function() {
            formularioModificado = true;
            calcularTotalLitros();
        });
    }

    // Mostrar total efectivo
    const inputMontoEfectivo = document.getElementById('montoEfectivo');
    const totalEfectivo = document.getElementById('totalEfectivo');
    
    if (inputMontoEfectivo) {
        inputMontoEfectivo.addEventListener('input', function() {
            formularioModificado = true;
            const monto = parseFloat(this.value) || 0;
            totalEfectivo.textContent = monto.toFixed(2);
        });
    }

    // ===== DETECTAR CAMBIOS EN OTROS CAMPOS =====
    ['empleado', 'observaciones'].forEach(campo => {
        const input = document.getElementById(campo);
        if (input) {
            input.addEventListener('change', function() {
                formularioModificado = true;
            });
        }
    });

    // ===== VALIDACIÓN ANTES DE ENVIAR =====
    const formGasolina = document.getElementById('formGasolina');
    if (formGasolina) {
        formGasolina.addEventListener('submit', function(e) {
            const tipo = tipoCargaInput.value;
            let esValido = true;
            
            if (tipo === 'litros') {
                const vehiculo = document.getElementById('vehiculo').value;
                const litros = parseFloat(document.getElementById('litros').value) || 0;
                const precio = parseFloat(document.getElementById('precioLitro').value) || 0;
                
                if (!vehiculo) {
                    alert('Debe seleccionar un vehículo');
                    esValido = false;
                } else if (litros <= 0) {
                    alert('La cantidad de litros debe ser mayor a 0');
                    esValido = false;
                } else if (precio <= 0) {
                    alert('El precio por litro debe ser mayor a 0');
                    esValido = false;
                }
            } else {
                const empleado = document.getElementById('empleado').value;
                const monto = parseFloat(document.getElementById('montoEfectivo').value) || 0;
                
                if (!empleado) {
                    alert('Debe seleccionar un empleado');
                    esValido = false;
                } else if (monto <= 0) {
                    alert('El monto de efectivo debe ser mayor a 0');
                    esValido = false;
                }
            }
            
            if (!esValido) {
                e.preventDefault();
            } else {
                formularioModificado = false;
            }
        });
        
        formGasolina.addEventListener('reset', function() {
            setTimeout(() => {
                document.getElementById('totalLitros').textContent = '0.00';
                document.getElementById('totalEfectivo').textContent = '0.00';
                document.getElementById('placas').value = '';
                document.getElementById('color').value = '';
                document.getElementById('modelo').value = '';
                document.getElementById('marca').value = '';
                formularioModificado = false;
            }, 10);
        });
    }

    // ===== ADVERTENCIA AL SALIR SIN GUARDAR =====
    window.addEventListener('beforeunload', function(e) {
        if (formularioModificado) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // ===== BÚSQUEDA EN TIEMPO REAL =====
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    const registrosGrid = document.getElementById('registrosGrid');
    const noResults = document.getElementById('noResults');
    
    if (searchInput && registrosGrid) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const cards = registrosGrid.getElementsByClassName('registro-card');
            let visibleCount = 0;

            if (clearSearch) {
                clearSearch.style.display = searchTerm ? 'block' : 'none';
            }

            Array.from(cards).forEach(card => {
                try {
                    const data = JSON.parse(card.getAttribute('data-registro'));
                    const descripcion = (data.descripcion || '').toLowerCase();
                    const placas = (data.placas || '').toLowerCase();
                    const tipo = (data.tipo || '').toLowerCase();

                    if (descripcion.includes(searchTerm) || 
                        placas.includes(searchTerm) || 
                        tipo.includes(searchTerm)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                } catch (e) {
                    console.error('Error parsing registro data:', e);
                }
            });

            if (noResults) {
                if (visibleCount === 0 && searchTerm) {
                    noResults.style.display = 'block';
                    registrosGrid.style.display = 'none';
                } else {
                    noResults.style.display = 'none';
                    registrosGrid.style.display = 'grid';
                }
            }
        });

        if (clearSearch) {
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }
});

// ===== FUNCIONES GLOBALES =====
function aplicarFiltros() {
    const fechaInicio = document.getElementById('filtroFechaInicio').value;
    const fechaFin = document.getElementById('filtroFechaFin').value;
    const tipo = document.getElementById('filtroTipo').value;
    
    let url = window.location.pathname + '?';
    const params = [];
    
    if (fechaInicio) params.push('fecha_inicio=' + fechaInicio);
    if (fechaFin) params.push('fecha_fin=' + fechaFin);
    if (tipo) params.push('filtro_tipo=' + tipo);

    params.push('tab=historial');

    url += params.join('&');
    window.location.href = url;
}

function verDetalle(idRegistro) {
    window.location.href = 'detalle_registro.php?id=' + idRegistro;
}

function aplicarFiltros() {
    const fechaInicio = document.getElementById('filtroFechaInicio').value;
    const fechaFin = document.getElementById('filtroFechaFin').value;
    const tipo = document.getElementById('filtroTipo').value;

    let url = '?pagina=1';
    
    if (fechaInicio) url += '&fecha_inicio=' + encodeURIComponent(fechaInicio);
    if (fechaFin) url += '&fecha_fin=' + encodeURIComponent(fechaFin);
    if (tipo) url += '&filtro_tipo=' + encodeURIComponent(tipo);

    window.location.href = url;
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);

    const tieneParametrosHistorial =
        urlParams.has('pagina') ||
        urlParams.has('filtro_tipo') ||
        urlParams.has('fecha_inicio') ||
        urlParams.has('fecha_fin');

    if (tieneParametrosHistorial) {
        const historialBtn = document.querySelector('[data-tab="historial"]');
        const historialTab = document.getElementById('historial');

        if (historialBtn && historialTab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            historialBtn.classList.add('active');
            historialTab.classList.add('active');

            setTimeout(() => {
                historialTab.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);
        }
    }

    const paginationButtons = document.querySelectorAll('.pagination-btn');
    
    if (paginationButtons.length > 0) {
        paginationButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const historialTab = document.getElementById('historial');
                if (historialTab) {
                    setTimeout(() => {
                        const cardElement = historialTab.querySelector('.card');
                        if (cardElement) {
                            cardElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }, 100);
                }
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const historialBtn = document.querySelector('[data-tab="historial"]');
    
    if (historialBtn) {
        historialBtn.addEventListener('click', function() {
            const urlParams = new URLSearchParams(window.location.search);

            if (!urlParams.has('pagina') && !urlParams.has('filtro_tipo') &&
                !urlParams.has('fecha_inicio') && !urlParams.has('fecha_fin')) {

                const newUrl = window.location.pathname + '?pagina=1';
                window.history.pushState({path: newUrl}, '', newUrl);
            }
        });
    }
});