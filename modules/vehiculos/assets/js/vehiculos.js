document.addEventListener('DOMContentLoaded', function() {
    // ===== VARIABLES GLOBALES =====
    let formularioModificado = false;
    let filtroActual = 'todos';
    
    // ===== AUTO-SCROLL A MENSAJES =====
    const mensaje = document.getElementById('mensaje');
    if (mensaje) {
        setTimeout(() => {
            mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }

    // ===== TOGGLE DE ALERTAS =====
    const btnAlertaToggle = document.getElementById('btnAlertaToggle');
    const alertaPanel = document.getElementById('alertaPanel');

    if (btnAlertaToggle && alertaPanel) {
        btnAlertaToggle.addEventListener('click', function() {
            if (alertaPanel.style.display === 'none') {
                alertaPanel.style.display = 'block';
                btnAlertaToggle.classList.add('active');
            } else {
                alertaPanel.style.display = 'none';
                btnAlertaToggle.classList.remove('active');
            }
        });
    }

    // ===== FILTROS DE ESTADO =====
    const filterButtons = document.querySelectorAll('.filter-btn');
    const tableBody = document.getElementById('vehiculosTableBody');
    const totalVehiculos = document.getElementById('total-vehiculos');

    function aplicarFiltros() {
        if (!tableBody) return;

        const rows = tableBody.getElementsByClassName('vehiculo-row');
        const searchTerm = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
        let visibleCount = 0;

        Array.from(rows).forEach(row => {
            const estado = row.getAttribute('data-estado');
            const data = JSON.parse(row.getAttribute('data-vehiculo'));
            
            // Verificar filtro de estado
            let cumpleFiltro = false;
            if (filtroActual === 'todos') {
                cumpleFiltro = true;
            } else if (filtroActual === estado) {
                cumpleFiltro = true;
            }

            // Verificar búsqueda
            let cumpleBusqueda = true;
            if (searchTerm) {
                const placas = data.placas.toLowerCase();
                const marca = data.marca.toLowerCase();
                const modelo = data.modelo.toLowerCase();
                const color = data.color.toLowerCase();

                cumpleBusqueda = placas.includes(searchTerm) || 
                                marca.includes(searchTerm) || 
                                modelo.includes(searchTerm) ||
                                color.includes(searchTerm);
            }

            // Mostrar u ocultar fila
            if (cumpleFiltro && cumpleBusqueda) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Actualizar contador
        if (totalVehiculos) {
            totalVehiculos.textContent = visibleCount;
        }

        // Mostrar mensaje de no resultados
        const noResults = document.getElementById('noResults');
        const tableContainer = document.querySelector('.table-container');
        
        if (visibleCount === 0) {
            if (noResults) noResults.style.display = 'block';
            if (tableContainer) tableContainer.style.display = 'none';
        } else {
            if (noResults) noResults.style.display = 'none';
            if (tableContainer) tableContainer.style.display = 'block';
        }
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover clase active de todos los botones
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Agregar clase active al botón clickeado
            this.classList.add('active');
            
            // Actualizar filtro actual
            filtroActual = this.getAttribute('data-filter');
            
            // Aplicar filtros
            aplicarFiltros();
        });
    });

    // ===== CAPITALIZACIÓN AUTOMÁTICA =====
    function capitalizarTexto(texto) {
        texto = texto.replace(/\s+/g, ' ').trim();
        return texto.toLowerCase().replace(/\b\w/g, function(letra) {
            return letra.toUpperCase();
        });
    }

    // Capitalizar marca
    const inputMarcaCap = document.getElementById('marca');
    if (inputMarcaCap) {
        inputMarcaCap.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.value = capitalizarTexto(this.value);
            }
        });
    }

    // Capitalizar modelo y color
    ['modelo', 'color'].forEach(campo => {
        const input = document.getElementById(campo);
        if (input) {
            input.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = capitalizarTexto(this.value);
                }
            });
        }
    });

    // ===== CONTADORES DE CARACTERES =====
    function actualizarContador(inputId, counterId, maxLength) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        
        if (input && counter) {
            function actualizar() {
                const length = input.value.length;
                counter.textContent = `${length}/${maxLength}`;
                
                if (length >= maxLength) {
                    counter.classList.add('error');
                    counter.classList.remove('warning');
                } else if (length >= maxLength - 2) {
                    counter.classList.add('warning');
                    counter.classList.remove('error');
                } else {
                    counter.classList.remove('error', 'warning');
                }
            }
            
            input.addEventListener('input', actualizar);
            actualizar();
        }
    }

    actualizarContador('marca', 'marca-counter', 10);
    actualizarContador('modelo', 'modelo-counter', 15);
    actualizarContador('color', 'color-counter', 10);

    // ===== VALIDACIÓN DE PLACAS =====
    const inputPlacas = document.getElementById('placas');
    const errorPlacas = document.getElementById('error-placas');
    const letraCount = document.getElementById('letra-count');
    const patronPlacas = /^[A-Z0-9]*$/;

    if (inputPlacas && letraCount) {
        function contarLetras(texto) {
            return (texto.match(/[A-Z]/g) || []).length;
        }

        function actualizarContadorLetras(texto) {
            const numLetras = contarLetras(texto);
            letraCount.textContent = `Letras: ${numLetras}/5 | Total: ${texto.length}/7`;
            
            if (numLetras > 5 || texto.length > 7) {
                letraCount.classList.add('error');
                letraCount.classList.remove('warning');
            } else if (numLetras === 5 || texto.length === 7) {
                letraCount.classList.add('warning');
                letraCount.classList.remove('error');
            } else {
                letraCount.classList.remove('error', 'warning');
            }
        }

        inputPlacas.addEventListener('input', function(e) {
            formularioModificado = true;
            let valor = e.target.value.toUpperCase().replace(/\s/g, '');
            
            if (!patronPlacas.test(valor)) {
                valor = valor.replace(/[^A-Z0-9]/g, '');
            }
            
            if (valor.length > 7) {
                valor = valor.substring(0, 7);
            }
            
            e.target.value = valor;
            actualizarContadorLetras(valor);

            if (contarLetras(valor) > 5) {
                inputPlacas.classList.add('input-error');
                errorPlacas.textContent = 'Máximo 5 letras permitidas';
                errorPlacas.classList.add('show');
            } else if (valor.length > 7) {
                inputPlacas.classList.add('input-error');
                errorPlacas.textContent = 'Máximo 7 caracteres permitidos';
                errorPlacas.classList.add('show');
            } else {
                inputPlacas.classList.remove('input-error');
                errorPlacas.classList.remove('show');
            }
        });

        if (inputPlacas.value) {
            actualizarContadorLetras(inputPlacas.value);
        }
    }

    // ===== VALIDACIÓN DE MARCA =====
    const inputMarca = document.getElementById('marca');
    const errorMarca = document.getElementById('error-marca');
    const patronMarca = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ]*$/;

    if (inputMarca && errorMarca) {
        inputMarca.addEventListener('input', function(e) {
            formularioModificado = true;
            const valor = e.target.value;
            
            if (!patronMarca.test(valor)) {
                e.target.value = valor.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ]/g, '');
                inputMarca.classList.add('input-error');
                errorMarca.classList.add('show');
            } else {
                if (e.target.value.length >= 2 || e.target.value.length === 0) {
                    inputMarca.classList.remove('input-error');
                    errorMarca.classList.remove('show');
                }
            }
        });
    }

    // ===== VALIDACIÓN DE MODELO =====
    const inputModelo = document.getElementById('modelo');
    const errorModelo = document.getElementById('error-modelo');
    const patronModelo = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]*$/;

    if (inputModelo && errorModelo) {
        inputModelo.addEventListener('input', function(e) {
            formularioModificado = true;
            const valor = e.target.value;
            
            if (!patronModelo.test(valor)) {
                e.target.value = valor.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]/g, '');
                inputModelo.classList.add('input-error');
                errorModelo.classList.add('show');
            } else {
                if (e.target.value.length >= 2 || e.target.value.length === 0) {
                    inputModelo.classList.remove('input-error');
                    errorModelo.classList.remove('show');
                }
            }
        });
    }

    // ===== VALIDACIÓN DE COLOR =====
    const inputColor = document.getElementById('color');
    const errorColor = document.getElementById('error-color');
    const patronColor = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]*$/;

    if (inputColor && errorColor) {
        inputColor.addEventListener('input', function(e) {
            formularioModificado = true;
            const valor = e.target.value;
            
            if (!patronColor.test(valor)) {
                e.target.value = valor.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
                inputColor.classList.add('input-error');
                errorColor.classList.add('show');
            } else {
                if (e.target.value.length >= 2 || e.target.value.length === 0) {
                    inputColor.classList.remove('input-error');
                    errorColor.classList.remove('show');
                }
            }
        });
    }

    // ===== BÚSQUEDA EN TIEMPO REAL =====
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (clearSearch) {
                clearSearch.style.display = searchTerm ? 'block' : 'none';
            }

            aplicarFiltros();
        });

        if (clearSearch) {
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    }

    // ===== ADVERTENCIA AL SALIR SIN GUARDAR =====
    window.addEventListener('beforeunload', function(e) {
        if (formularioModificado) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // Resetear flag cuando se cancela
    const btnCancelar = document.querySelector('.btn-secondary');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', function() {
            formularioModificado = false;
        });
    }

    // ===== VALIDACIÓN ANTES DE ENVIAR =====
    const formVehiculo = document.getElementById('formVehiculo');
    if (formVehiculo) {
        formVehiculo.addEventListener('submit', function(e) {
            formularioModificado = false;
        });
    }
});