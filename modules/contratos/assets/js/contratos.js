// ============================================
// SISTEMA DE PAGINACIÓN Y BÚSQUEDA
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Variables globales de paginación
    let currentPage = 1;
    let rowsPerPage = 10;
    let allRows = [];
    let filteredRows = [];
    
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    const tableBody = document.getElementById('foliosTableBody');
    const noResults = document.getElementById('noResults');
    const tableContainer = document.querySelector('.table-container');
    const paginationContainer = document.querySelector('.pagination-container');
    
    // Inicializar
    if (tableBody) {
        allRows = Array.from(tableBody.getElementsByClassName('contrato-row'));
        filteredRows = [...allRows];
        initPagination();
        displayPage(currentPage);
    }
    
    // ===== BÚSQUEDA EN TIEMPO REAL =====
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            clearSearch.style.display = searchTerm ? 'block' : 'none';
            
            filteredRows = allRows.filter(row => {
                const data = JSON.parse(row.getAttribute('data-contrato'));
                const folio = data.folio.toLowerCase();
                const cliente = data.cliente.toLowerCase();
                const vendedor = data.vendedor.toLowerCase();
                const zona = data.zona.toLowerCase();
                
                return folio.includes(searchTerm) || 
                       cliente.includes(searchTerm) || 
                       vendedor.includes(searchTerm) ||
                       zona.includes(searchTerm);
            });
            
            currentPage = 1;
            displayPage(currentPage);
            updatePaginationInfo();
            
            if (filteredRows.length === 0 && searchTerm) {
                noResults.style.display = 'block';
                tableContainer.style.display = 'none';
                paginationContainer.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                tableContainer.style.display = 'block';
                paginationContainer.style.display = filteredRows.length > 0 ? 'flex' : 'none';
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
    
    // ===== PAGINACIÓN =====
    function initPagination() {
        const btnFirst = document.getElementById('btnFirst');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const btnLast = document.getElementById('btnLast');
        
        if (btnFirst) {
            btnFirst.addEventListener('click', () => {
                currentPage = 1;
                displayPage(currentPage);
                updatePaginationInfo();
            });
        }
        
        if (btnPrev) {
            btnPrev.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                    updatePaginationInfo();
                }
            });
        }
        
        if (btnNext) {
            btnNext.addEventListener('click', () => {
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    displayPage(currentPage);
                    updatePaginationInfo();
                }
            });
        }
        
        if (btnLast) {
            btnLast.addEventListener('click', () => {
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                currentPage = totalPages;
                displayPage(currentPage);
                updatePaginationInfo();
            });
        }
    }
    
    function displayPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        
        allRows.forEach(row => row.style.display = 'none');
        filteredRows.slice(start, end).forEach(row => row.style.display = '');
        
        updatePaginationInfo();
    }
    
    function updatePaginationInfo() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        const start = ((currentPage - 1) * rowsPerPage) + 1;
        const end = Math.min(currentPage * rowsPerPage, filteredRows.length);
        
        document.getElementById('showingStart').textContent = filteredRows.length > 0 ? start : 0;
        document.getElementById('showingEnd').textContent = end;
        document.getElementById('totalRecords').textContent = filteredRows.length;
        
        const btnFirst = document.getElementById('btnFirst');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const btnLast = document.getElementById('btnLast');
        
        if (btnFirst) btnFirst.disabled = currentPage === 1;
        if (btnPrev) btnPrev.disabled = currentPage === 1;
        if (btnNext) btnNext.disabled = currentPage === totalPages || totalPages === 0;
        if (btnLast) btnLast.disabled = currentPage === totalPages || totalPages === 0;
        
        generatePageNumbers(totalPages);
    }
    
    function generatePageNumbers(totalPages) {
        const pageNumbers = document.getElementById('pageNumbers');
        if (!pageNumbers) return;
        
        pageNumbers.innerHTML = '';
        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        
        if (currentPage <= 3) {
            endPage = Math.min(5, totalPages);
        }
        if (currentPage >= totalPages - 2) {
            startPage = Math.max(1, totalPages - 4);
        }
        
        if (startPage > 1) {
            const btn = createPageButton(1);
            pageNumbers.appendChild(btn);
            
            if (startPage > 2) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '8px';
                dots.style.color = 'var(--color-muted)';
                pageNumbers.appendChild(dots);
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const btn = createPageButton(i);
            pageNumbers.appendChild(btn);
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '8px';
                dots.style.color = 'var(--color-muted)';
                pageNumbers.appendChild(dots);
            }
            
            const btn = createPageButton(totalPages);
            pageNumbers.appendChild(btn);
        }
    }
    
    function createPageButton(pageNum) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-page';
        btn.textContent = pageNum;
        
        if (pageNum === currentPage) {
            btn.classList.add('active');
        }
        
        btn.addEventListener('click', () => {
            currentPage = pageNum;
            displayPage(currentPage);
            updatePaginationInfo();
            
            document.querySelector('.table-container').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        });
        
        return btn;
    }
    
    const mensaje = document.getElementById('mensaje');
    if (mensaje) {
        setTimeout(() => {
            mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
    
    const folioHighlighted = document.querySelector('.folio-highlight');
    if (folioHighlighted) {
        setTimeout(() => {
            folioHighlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
    }
});

// ============================================
// FUNCIONES PARA MODAL DE FOLIO
// ============================================

function verFolio(idFolio) {
    document.getElementById('modalFolio').style.display = 'flex';
    document.getElementById('contenidoModal').innerHTML = '<p style="text-align: center; padding: 40px;">Cargando folio...</p>';
    
    fetch(`ver_contrato.php?id=${idFolio}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoModal').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('contenidoModal').innerHTML = '<p style="color: red;">Error al cargar el folio</p>';
        });
}

function cerrarModal() {
    document.getElementById('modalFolio').style.display = 'none';
}

// ============================================
// FUNCIONES PARA MODAL DE CANCELACIÓN
// ============================================

function abrirModalCancelacion(idFolio) {
    const modal = document.getElementById('modalCancelacion');
    const contenido = document.getElementById('contenidoCancelacion');
    
    modal.style.display = 'flex';
    contenido.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="bx bx-loader-alt bx-spin" style="font-size: 48px; color: var(--color-primary);"></i><p>Cargando formulario de cancelación...</p></div>';
    
    fetch(`cancelar_folio.php?id=${idFolio}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(html => {
            contenido.innerHTML = html;
            console.log('🔥 Contenido cargado, inicializando scripts de cancelación...');
            initCancelacionScripts();
        })
        .catch(error => {
            contenido.innerHTML = `
                <div style="text-align: center; padding: 40px; color: red;">
                    <i class="bx bx-error-circle" style="font-size: 48px;"></i>
                    <h3>Error al cargar el formulario</h3>
                    <p>${error.message}</p>
                    <button onclick="cerrarModalCancelacion()" class="btn btn-secondary">Cerrar</button>
                </div>
            `;
        });
}

function cerrarModalCancelacion() {
    const modal = document.getElementById('modalCancelacion');
    modal.style.display = 'none';
    
    setTimeout(() => {
        document.getElementById('contenidoCancelacion').innerHTML = '';
    }, 300);
}

// ============================================
// INICIALIZACIÓN DE SCRIPTS DE CANCELACIÓN
// ============================================

function initCancelacionScripts() {
    console.log('🚀 Iniciando scripts de cancelación REDISEÑADOS...');
    
    // ===== 1. CHECKBOXES DE UNIDADES INDIVIDUALES =====
    const checkboxesUnidades = document.querySelectorAll('.unidad-checkbox');
    console.log('✅ Checkboxes de unidades encontrados:', checkboxesUnidades.length);
    
    checkboxesUnidades.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const productoId = this.getAttribute('data-producto-id');
            const unidad = this.getAttribute('data-unidad');
            
            // Obtener el select de estado correspondiente
            const selectEstado = document.querySelector(
                `select[name="estado_${productoId}_${unidad}"]`
            );
            
            if (this.checked) {
                console.log(`✅ Unidad ${unidad} del producto ${productoId} marcada`);
                selectEstado.disabled = false;
                selectEstado.value = 'bueno'; // Por defecto buen estado
            } else {
                console.log(`❌ Unidad ${unidad} del producto ${productoId} desmarcada`);
                selectEstado.disabled = true;
                selectEstado.value = '';
            }
            
            // Actualizar contador de este producto
            actualizarContador(productoId);
            
            // Verificar condición de enganche
            verificarCondicionEnganche();
        });
    });
    
    // ===== 2. SELECTS DE ESTADO =====
    const selectsEstado = document.querySelectorAll('.estado-select');
    console.log('✅ Selects de estado encontrados:', selectsEstado.length);
    
    selectsEstado.forEach(function(select) {
        select.addEventListener('change', function() {
            console.log('🔄 Estado cambiado:', this.value);
            // Verificar condición de enganche cuando cambia un estado
            verificarCondicionEnganche();
        });
    });
    
    // ===== 3. INPUTS DE COMISIÓN =====
    const inputsComision = document.querySelectorAll('.comision-input');
    console.log('✅ Inputs de comisión encontrados:', inputsComision.length);
    
    inputsComision.forEach(function(input) {
        input.addEventListener('input', function() {
            console.log('💰 Comisión cambiada manualmente');
            calcularComisionTotal();
        });
    });
    
    // ===== 4. CAMBIO DE MOTIVO =====
    const selectMotivo = document.getElementById('motivoCancelacion');
    console.log('🔍 Select de motivo:', selectMotivo ? 'ENCONTRADO ✅' : 'NO ENCONTRADO ❌');
    
    if (selectMotivo) {
        selectMotivo.addEventListener('change', function() {
            console.log('🔄 Motivo cambiado a:', this.value);
            
            const engancheDiv = document.getElementById('engancheDevolucion');
            const alertaEnganche = document.getElementById('alertaEnganche');
            
            if (this.value === 'morosidad_inmediata') {
                console.log('💰 Morosidad inmediata - Comisión = $0.00');
                
                // Establecer todas las comisiones en 0
                inputsComision.forEach(function(input) {
                    input.value = '0.00';
                });
                
                calcularComisionTotal();
                
                if (engancheDiv) engancheDiv.style.display = 'grid';
                if (alertaEnganche) alertaEnganche.style.display = 'none';
                
            } else {
                console.log('📊 Otro motivo - Restaurando comisiones sugeridas');
                
                // Restaurar comisiones sugeridas
                inputsComision.forEach(function(input) {
                    const sugerida = input.getAttribute('data-comision-sugerida');
                    if (sugerida) {
                        input.value = sugerida;
                    }
                });
                
                calcularComisionTotal();
                
                if (engancheDiv) engancheDiv.style.display = 'none';
                if (alertaEnganche) alertaEnganche.style.display = 'flex';
                
                verificarCondicionEnganche();
            }
        });
    }
    
    // ===== 5. VALIDACIÓN DEL FORMULARIO =====
    const form = document.getElementById('formCancelacion');
    console.log('🔍 Formulario:', form ? 'ENCONTRADO ✅' : 'NO ENCONTRADO ❌');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('📤 Formulario enviado');
            
            const motivo = selectMotivo ? selectMotivo.value : '';
            const confirmarCheck = document.getElementById('confirmarCheck');
            const confirmar = confirmarCheck ? confirmarCheck.checked : false;
            
            if (!motivo) {
                alert('⚠️ Seleccione un motivo de cancelación');
                return;
            }
            
            if (!confirmar) {
                alert('⚠️ Debe confirmar la cancelación');
                return;
            }
            
            const folioElement = document.querySelector('.highlight-text');
            const folio = folioElement ? folioElement.textContent : 'este folio';
            
            if (confirm('⚠️ ¿Confirma cancelar el folio ' + folio + '?\n\nEsta acción NO se puede deshacer.')) {
                const btn = document.getElementById('btnSubmit');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Procesando...';
                }
                console.log('✅ Enviando formulario...');
                this.submit();
            } else {
                console.log('❌ Usuario canceló');
            }
        });
    }
    
    console.log('✅✅✅ Todos los scripts inicializados correctamente');
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

// Actualizar contador de productos recuperados
function actualizarContador(productoId) {
    const checkboxes = document.querySelectorAll(
        `.unidad-checkbox[data-producto-id="${productoId}"]`
    );
    
    let recuperados = 0;
    checkboxes.forEach(function(cb) {
        if (cb.checked) recuperados++;
    });
    
    const contador = document.querySelector(`#contador_${productoId} .num-recuperados`);
    if (contador) {
        contador.textContent = recuperados;
        console.log(`📦 Producto ${productoId}: ${recuperados} de ${checkboxes.length} recuperados`);
    }
}

// Verificar si se puede devolver el enganche
function verificarCondicionEnganche() {
    const selectMotivo = document.getElementById('motivoCancelacion');
    
    // Solo verificar si NO es morosidad inmediata
    if (selectMotivo && selectMotivo.value === 'morosidad_inmediata') {
        return;
    }
    
    const checkboxes = document.querySelectorAll('.unidad-checkbox');
    const alertaEnganche = document.getElementById('alertaEnganche');
    const estadoEnganche = document.getElementById('estadoEnganche');
    const inputPuedeEnganche = document.getElementById('inputPuedeEnganche');
    
    let algunoMarcado = false;
    let todosRecuperadosEnBuenEstado = true;
    let totalMarcados = 0;
    
    checkboxes.forEach(function(checkbox) {
        if (checkbox.checked) {
            algunoMarcado = true;
            totalMarcados++;
            
            const productoId = checkbox.getAttribute('data-producto-id');
            const unidad = checkbox.getAttribute('data-unidad');
            const selectEstado = document.querySelector(
                `select[name="estado_${productoId}_${unidad}"]`
            );
            
            if (selectEstado && selectEstado.value !== 'bueno') {
                todosRecuperadosEnBuenEstado = false;
            }
        }
    });
    
    if (!algunoMarcado) {
        if (alertaEnganche) alertaEnganche.style.display = 'none';
        if (inputPuedeEnganche) inputPuedeEnganche.value = '0';
        return;
    }
    
    if (alertaEnganche && estadoEnganche) {
        alertaEnganche.style.display = 'flex';
        
        if (todosRecuperadosEnBuenEstado) {
            estadoEnganche.innerHTML = `
                <span style="color: #065f46; font-weight: 700;">
                    ✅ Todos los productos recuperados están en "Buen estado".<br>
                    El enganche SÍ se puede devolver al cliente.
                </span>
            `;
            if (inputPuedeEnganche) inputPuedeEnganche.value = '1';
        } else {
            estadoEnganche.innerHTML = `
                <span style="color: #991b1b; font-weight: 700;">
                    ❌ Hay productos que NO están en "Buen estado".<br>
                    El enganche NO se devolverá al cliente.
                </span>
            `;
            if (inputPuedeEnganche) inputPuedeEnganche.value = '0';
        }
    }
    
    console.log(`📊 Enganche: ${todosRecuperadosEnBuenEstado ? 'SÍ devolver' : 'NO devolver'}`);
}

// Calcular comisión total
function calcularComisionTotal() {
    const inputsComision = document.querySelectorAll('.comision-input');
    let totalComision = 0;
    
    inputsComision.forEach(function(input) {
        const valor = parseFloat(input.value) || 0;
        totalComision += valor;
    });
    
    // Actualizar display
    const valorComisionFinal = document.getElementById('valorComisionFinal');
    const valorComisionCancelar = document.getElementById('valorComisionCancelar');
    const inputComisionFinal = document.getElementById('inputComisionFinal');
    
    if (valorComisionFinal) {
        valorComisionFinal.textContent = '$' + totalComision.toFixed(2);
    }
    
    if (inputComisionFinal) {
        inputComisionFinal.value = totalComision.toFixed(2);
    }
    
    // La comisión a cancelar es la diferencia con el total original
    const comisionOriginal = document.querySelector('.calculo-item:first-child .calculo-value');
    if (comisionOriginal && valorComisionCancelar) {
        const originalValue = parseFloat(comisionOriginal.textContent.replace('$', '').replace(',', ''));
        const aCancelar = originalValue - totalComision;
        valorComisionCancelar.textContent = '$' + aCancelar.toFixed(2);
    }
    
    console.log('💰 Comisión total calculada:', totalComision.toFixed(2));
}