// ============================================
// SISTEMA DE PAGINACIÓN Y BÚSQUEDA
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let rowsPerPage = 10;
    let allRows = [];
    let filteredRows = [];
    
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    const tableBody = document.getElementById('canceladosTableBody');
    const noResults = document.getElementById('noResults');
    const tableContainer = document.querySelector('.table-container');
    const paginationContainer = document.querySelector('.pagination-container');
    
    // Inicializar
    if (tableBody) {
        allRows = Array.from(tableBody.getElementsByClassName('cancelado-row'));
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
                const data = JSON.parse(row.getAttribute('data-cancelado'));
                const folio = data.folio.toLowerCase();
                const cliente = data.cliente.toLowerCase();
                const vendedor = data.vendedor.toLowerCase();
                const zona = data.zona.toLowerCase();
                const motivo = data.motivo.toLowerCase();
                
                return folio.includes(searchTerm) || 
                       cliente.includes(searchTerm) || 
                       vendedor.includes(searchTerm) ||
                       zona.includes(searchTerm) ||
                       motivo.includes(searchTerm);
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
    
    // Auto-scroll a mensajes
    const mensaje = document.getElementById('mensaje');
    if (mensaje) {
        setTimeout(() => {
            mensaje.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }
});

// ============================================
// FUNCIONES PARA MODAL
// ============================================

function verCancelacion(idCancelacion) {
    const modal = document.getElementById('modalCancelacion');
    const contenido = document.getElementById('contenidoModal');
    
    modal.style.display = 'flex';
    contenido.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="bx bx-loader-alt bx-spin" style="font-size: 48px; color: var(--color-primary);"></i><p>Cargando detalles...</p></div>';
    
    fetch(`ver_cancelacion.php?id=${idCancelacion}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar los detalles');
            }
            return response.text();
        })
        .then(html => {
            contenido.innerHTML = html;
        })
        .catch(error => {
            contenido.innerHTML = `
                <div style="text-align: center; padding: 40px; color: red;">
                    <i class="bx bx-error-circle" style="font-size: 48px;"></i>
                    <h3>Error</h3>
                    <p>${error.message}</p>
                    <button onclick="cerrarModal()" class="btn btn-secondary">Cerrar</button>
                </div>
            `;
        });
}

function cerrarModal() {
    const modal = document.getElementById('modalCancelacion');
    modal.style.display = 'none';
    
    setTimeout(() => {
        document.getElementById('contenidoModal').innerHTML = '';
    }, 300);
}

// Cerrar modal al hacer click fuera
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalCancelacion');
    if (e.target === modal) {
        cerrarModal();
    }
});

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
    }
});