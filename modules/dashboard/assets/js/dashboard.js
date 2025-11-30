// Estado del menú persistente (en memoria durante la sesión)
let menuState = {
    expandedCategories: [], // Categorías que están expandidas
    sidebarCollapsed: false // Estado del sidebar (expandido/colapsado)
};

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    initializeMenu();
    setupMenuListeners();
    setupSectionNavigation();
    setupSidebarToggle();
    setupIframeCommunication();
    setupMobileMenu();
});

// Inicializar el menú con el estado guardado
function initializeMenu() {
    const categories = document.querySelectorAll('.menu-category');
    categories.forEach(category => {
        const categoryName = category.getAttribute('data-category');
        const submenu = document.getElementById(`submenu-${categoryName}`);
        if (menuState.expandedCategories.includes(categoryName)) {
            category.classList.add('expanded');
            if (submenu) submenu.classList.add('expanded');
        }
    });

    const aside = document.getElementById('sidebar');
    if (menuState.sidebarCollapsed) {
        aside.classList.add('collapsed');
    }
}

// Configurar listeners del menú
function setupMenuListeners() {
    const categories = document.querySelectorAll('.menu-category');
    categories.forEach(category => {
        category.addEventListener('click', function(e) {
            e.preventDefault();
            toggleCategory(this);
        });
    });
}

// Alternar categoría (expandir/contraer)
function toggleCategory(categoryElement) {
    const categoryName = categoryElement.getAttribute('data-category');
    const submenu = document.getElementById(`submenu-${categoryName}`);
    if (!submenu) return;

    const isExpanded = categoryElement.classList.contains('expanded');

    if (isExpanded) {
        categoryElement.classList.remove('expanded');
        submenu.classList.remove('expanded');
        removeFromExpandedCategories(categoryName);
    } else {
        categoryElement.classList.add('expanded');
        submenu.classList.add('expanded');
        addToExpandedCategories(categoryName);
    }
}

// Agregar categoría a la lista de expandidas
function addToExpandedCategories(categoryName) {
    if (!menuState.expandedCategories.includes(categoryName)) {
        menuState.expandedCategories.push(categoryName);
    }
}

// Remover categoría de la lista de expandidas
function removeFromExpandedCategories(categoryName) {
    const index = menuState.expandedCategories.indexOf(categoryName);
    if (index > -1) {
        menuState.expandedCategories.splice(index, 1);
    }
}

// Configurar navegación entre secciones
function setupSectionNavigation() {
    const menuItems = document.querySelectorAll('.menu li[data-section]');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section');
            navigateToSection(sectionId);

            // Cerrar menú móvil después de seleccionar
            closeMobileMenu();

            // Colapsar sidebar automáticamente para ciertas secciones
            const sectionsToCollapse = [
                'empleados', 'vehiculos', 'inventario', 'nueva-salida', 'asignaciones-activas',
                'historial-asignaciones', 'gasolina', 'reporte-semanal', 'contratos', 'contratosCancelados',
                // SECCIONES DE COBRADORES
                'registrar-cobro', 'ver-cobros', 'registrar-gasolina', 'ver-gasolina',
                'generar-comision', 'comisiones', 'semanas-cobro', 'prestamos', 'gastos-gasolina', 'reportes-cobradores',
                // MÓDULO DE PAGOS
                'resumen-pagos', 'registrar-pago', 'historial-pagos',
                // MÓDULO DE CONTROL
                'registrarGasto',
                // MÓDULO DE USUARIOS
                'gestion-usuarios'
            ];
            
            if (sectionsToCollapse.includes(sectionId)) {
                collapseSidebar();
            }
        });
    });
}

// Navegar a una sección específica - VERSIÓN CORREGIDA
function navigateToSection(sectionId) {
    console.log('🔄 Navegando a sección:', sectionId);
    
    const sections = document.querySelectorAll('main section');
    sections.forEach(section => {
        section.classList.remove('active');
        section.style.display = 'none';
    });

    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
        targetSection.style.display = 'block';

        // Manejo inteligente de iframes - CORREGIDO PARA MÓDULO COBRADORES Y PAGOS
        const iframe = targetSection.querySelector('iframe');
        if (iframe) {
            const dataSrc = iframe.getAttribute('data-src');
            const currentSrc = iframe.getAttribute('src');
            
            // Mapa de secciones a URLs específicas
            const sectionToUrl = {
                'comisiones': '../cobradores/index.php',
                'generar-comision': '../cobradores/generar_comision.php',
                'registrar-cobro': '../cobradores/registrar_cobro.php',
                'ver-cobros': '../cobradores/ver_cobros.php',
                'semanas-cobro': '../cobradores/gestionar_semanas.php',
                'prestamos': '../cobradores/prestamos.php',
                'historial-pagos': '../pagos/historial_pagos.php',
                'registrar-pago': '../pagos/registrar_pago.php',
                'resumen-pagos': '../pagos/resumen_pagos.php'
            };
            
            if (sectionId === 'nueva-salida') {
                // Siempre recargar Nueva Salida con timestamp
                const baseSrc = dataSrc || currentSrc;
                if (baseSrc) {
                    iframe.src = baseSrc.split('?')[0] + '?t=' + new Date().getTime();
                }
            } else if (sectionToUrl[sectionId]) {
                // Para secciones específicas, usar la URL correcta
                const expectedUrl = sectionToUrl[sectionId];
                const currentBaseUrl = currentSrc ? currentSrc.split('?')[0] : '';
                
                // Solo recargar si la URL base es diferente o si está en blanco
                if (!currentSrc || currentSrc === 'about:blank' || !currentBaseUrl.includes(expectedUrl.split('/').pop().split('?')[0])) {
                    iframe.src = expectedUrl + '?t=' + new Date().getTime();
                    console.log('✅ Iframe cargado con:', expectedUrl);
                } else {
                    console.log('ℹ️ Iframe ya tiene la URL correcta');
                }
            } else if (!currentSrc || currentSrc === 'about:blank') {
                // Cargar por primera vez
                if (dataSrc) {
                    iframe.src = dataSrc;
                }
            }
        }
    }

    // Actualizar menú activo
    updateActiveMenu(sectionId);

    // Colapsar sidebar automáticamente para ciertas secciones
    const sectionsToCollapse = [
        'empleados', 'vehiculos', 'inventario', 'nueva-salida', 'asignaciones-activas',
        'historial-asignaciones', 'gasolina', 'reporte-semanal', 'contratos', 'contratosCancelados',
        // SECCIONES DE COBRADORES
        'registrar-cobro', 'ver-cobros', 'registrar-gasolina', 'ver-gasolina',
        'generar-comision', 'comisiones', 'semanas-cobro', 'prestamos', 'gastos-gasolina', 'reportes-cobradores',
        // MÓDULO DE PAGOS
        'resumen-pagos', 'registrar-pago', 'historial-pagos',
        // MÓDULO DE CONTROL
        'registrarGasto',
        // MÓDULO DE USUARIOS
        'gestion-usuarios'
    ];

    if (sectionsToCollapse.includes(sectionId)) {
        collapseSidebar();
    }
    
    console.log('✅ Sección activada:', sectionId);
}

// Función para actualizar el menú activo
function updateActiveMenu(sectionId) {
    document.querySelectorAll('.menu li[data-section]').forEach(li => {
        li.classList.remove('active');
        if (li.getAttribute('data-section') === sectionId) {
            li.classList.add('active');
        }
    });
}

// ============================================
// SISTEMA DE COMUNICACIÓN CON IFRAMES - CORREGIDO
// ============================================

// Mapeo de páginas PHP a secciones del dashboard
const PAGE_TO_SECTION_MAP = {
    'index.php': 'comisiones',
    'prestamos.php': 'prestamos',
    'generar_comision.php': 'generar-comision',
    'registrar_cobro.php': 'registrar-cobro',
    'ver_cobros.php': 'ver-cobros',
    'gestionar_semanas.php': 'semanas-cobro',
    'editar_comision.php': 'comisiones',
    'ver_comision.php': 'comisiones',
    'empleados.php': 'empleados',
    'vehiculos.php': 'vehiculos',
    'contratos.php': 'contratos',
    'folios_cancelados.php': 'contratosCancelados',
    'inventarios.php': 'inventario',
    'nueva_salida.php': 'nueva-salida',
    'asignaciones_activas.php': 'asignaciones-activas',
    'historial_asignaciones.php': 'historial-asignaciones',
    // MÓDULO DE PAGOS
    'historial_pagos.php': 'historial-pagos',
    'ver_detalle_pago.php': 'historial-pagos',
    'registrar_pago.php': 'registrar-pago',
    'marcar_pagado.php': 'historial-pagos',
    'resumen_pagos.php': 'resumen-pagos'
};

// ⭐ Mapeo de archivos a rutas completas desde dashboard
const PAGE_TO_PATH_MAP = {
    'index.php': '../cobradores/index.php',
    'prestamos.php': '../cobradores/prestamos.php',
    'generar_comision.php': '../cobradores/generar_comision.php',
    'registrar_cobro.php': '../cobradores/registrar_cobro.php',
    'ver_cobros.php': '../cobradores/ver_cobros.php',
    'gestionar_semanas.php': '../cobradores/gestionar_semanas.php',
    'editar_comision.php': '../cobradores/editar_comision.php',
    'ver_comision.php': '../cobradores/ver_comision.php',
    'empleados.php': '../empleados/empleados.php',
    'vehiculos.php': '../operaciones/vehiculos.php',
    'contratos.php': '../ventas/contratos.php',
    'folios_cancelados.php': '../cancelacion/folios_cancelados.php',
    'inventarios.php': '../operaciones/inventarios.php',
    'nueva_salida.php': '../asignaciones/nueva_salida.php',
    'asignaciones_activas.php': '../asignaciones/asignaciones_activas.php',
    'historial_asignaciones.php': '../asignaciones/historial_asignaciones.php',
    // MÓDULO DE PAGOS
    'historial_pagos.php': '../pagos/historial_pagos.php',
    'ver_detalle_pago.php': '../pagos/ver_detalle_pago.php',
    'registrar_pago.php': '../pagos/registrar_pago.php',
    'marcar_pagado.php': '../pagos/marcar_pagado.php',
    'resumen_pagos.php': '../pagos/resumen_pagos.php'
};

// Mapeo de páginas a sus categorías principales
const PAGE_TO_CATEGORY_MAP = {
    // Páginas del módulo de pagos
    'historial_pagos.php': 'pagos',
    'ver_detalle_pago.php': 'pagos',
    'registrar_pago.php': 'pagos',
    'marcar_pagado.php': 'pagos',
    'resumen_pagos.php': 'pagos',
    // Páginas del módulo de cobradores
    'index.php': 'cobradores',
    'prestamos.php': 'cobradores',
    'generar_comision.php': 'cobradores',
    'registrar_cobro.php': 'cobradores',
    'ver_cobros.php': 'cobradores',
    'gestionar_semanas.php': 'cobradores',
    'editar_comision.php': 'cobradores',
    'ver_comision.php': 'cobradores',
    // Páginas de otros módulos
    'empleados.php': 'personal',
    'vehiculos.php': 'operaciones',
    'inventarios.php': 'operaciones',
    'contratos.php': 'ventas',
    'folios_cancelados.php': 'cancelacion',
    'nueva_salida.php': 'asignaciones',
    'asignaciones_activas.php': 'asignaciones',
    'historial_asignaciones.php': 'asignaciones'
};

// ⭐ FUNCIÓN: Extraer nombre de archivo base sin parámetros
function extractPageName(url) {
    // Si la URL tiene parámetros (?id=1), extraer solo el nombre del archivo
    const pageWithParams = url.split('?')[0]; // 'ver_detalle_pago.php?id=1' → 'ver_detalle_pago.php'
    const pageName = pageWithParams.split('/').pop(); // Por si tiene rutas
    console.log(`🔍 URL original: ${url} → Archivo base: ${pageName}`);
    return pageName;
}

// Configurar sistema de comunicación con iframes
function setupIframeCommunication() {
    window.addEventListener('message', function(event) {
        console.log('📨 Mensaje recibido:', event.data);

        // TIPO 0: Navegación simple con 'navigate' (usado por script_navegacion_dashboard.js)
        if (event.data && event.data.type === 'navigate') {
            const page = event.data.page;
            console.log('🔄 Navegación solicitada a:', page);
            
            // Si page es una sección directamente (ej: 'registrar-cobro')
            if (page && !page.includes('.php')) {
                navigateToSection(page);
                return;
            }
            
            // ⭐ Extraer nombre base sin parámetros
            const basePage = extractPageName(page);
            
            // Si page es un archivo PHP, buscar su sección
            const targetSection = PAGE_TO_SECTION_MAP[basePage];
            if (targetSection) {
                console.log('🎯 Sección encontrada:', targetSection);
                
                // ⭐ IMPORTANTE: Navegar a la sección PRIMERO
                navigateToSection(targetSection);
                
                // ⭐ CRÍTICO: Si la página tiene parámetros o necesita URL específica, actualizar el iframe
                setTimeout(() => {
                    const section = document.getElementById(targetSection);
                    if (section) {
                        const iframe = section.querySelector('iframe');
                        if (iframe) {
                            const correctPath = PAGE_TO_PATH_MAP[basePage];
                            
                            if (correctPath) {
                                // Si la URL original tiene parámetros, incluirlos
                                if (page.includes('?')) {
                                    const params = page.split('?')[1];
                                    const fullUrl = `${correctPath}?${params}`;
                                    iframe.src = fullUrl;
                                    console.log('✅ Iframe actualizado con parámetros:', fullUrl);
                                } else {
                                    // Sin parámetros, solo verificar que sea la URL correcta
                                    const currentUrl = iframe.src.split('?')[0];
                                    if (!currentUrl.endsWith(correctPath)) {
                                        iframe.src = correctPath;
                                        console.log('✅ Iframe actualizado:', correctPath);
                                    }
                                }
                            }
                        }
                    }
                }, 100);
                
                // Expandir la categoría correspondiente
                const categoryName = PAGE_TO_CATEGORY_MAP[basePage];
                if (categoryName) {
                    const categoryElement = document.querySelector(`[data-category="${categoryName}"]`);
                    if (categoryElement && !categoryElement.classList.contains('expanded')) {
                        toggleCategory(categoryElement);
                    }
                }
            } else {
                console.warn('⚠️ No se encontró sección para:', basePage);
            }
            return;
        }

        // TIPO 1: Navegación con formato iframe_page (nuevo formato)
        if (event.data && event.data.type === 'IFRAME_PAGE') {
            const pageName = extractPageName(event.data.page);
            console.log('📨 Mensaje recibido desde iframe:', pageName);
            
            // Buscar la sección correspondiente a esta página
            const targetSection = PAGE_TO_SECTION_MAP[pageName];
            
            if (targetSection) {
                console.log('🎯 Sección encontrada:', targetSection);
                navigateToSection(targetSection);
                
                // Expandir la categoría correspondiente
                const categoryName = PAGE_TO_CATEGORY_MAP[pageName];
                if (categoryName) {
                    const categoryElement = document.querySelector(`[data-category="${categoryName}"]`);
                    if (categoryElement && !categoryElement.classList.contains('expanded')) {
                        toggleCategory(categoryElement);
                    }
                }
                
                // Dar tiempo para que se actualice la vista
                setTimeout(() => {
                    updateActiveMenu(targetSection);
                }, 100);
                
            } else {
                console.warn('⚠️ No se encontró sección para la página:', pageName);
            }
        }

        // TIPO 2: Navegación con formato dashboard (formato original)
        if (event.data && event.data.type === 'DASHBOARD_NAVIGATE') {
            const targetSection = event.data.section;
            const tab = event.data.tab;
            
            console.log('🔄 Navegación dashboard a sección:', targetSection);
            
            if (targetSection) {
                navigateToSection(targetSection);
                
                // Si hay una pestaña específica, enviarla al iframe
                if (tab) {
                    setTimeout(() => {
                        const section = document.getElementById(targetSection);
                        if (section) {
                            const iframe = section.querySelector('iframe');
                            if (iframe) {
                                iframe.contentWindow.postMessage({
                                    type: 'ACTIVATE_TAB',
                                    tab: tab
                                }, window.location.origin);
                            }
                        }
                    }, 500);
                }
                
                // Expandir la categoría padre de la sección seleccionada
                const menuItem = document.querySelector(`li[data-section="${targetSection}"]`);
                if (menuItem) {
                    const parentCategory = menuItem.closest('.submenu')?.previousElementSibling;
                    if (parentCategory && parentCategory.classList.contains('menu-category')) {
                        const categoryName = parentCategory.getAttribute('data-category');
                        if (!parentCategory.classList.contains('expanded')) {
                            toggleCategory(parentCategory);
                        }
                    }
                }
            }
        }
        
        // TIPO 3: Recarga de sección
        if (event.data && event.data.type === 'RELOAD_SECTION') {
            const targetSection = event.data.section;
            console.log('🔄 Recarga solicitada para sección:', targetSection);
            if (targetSection) {
                reloadSection(targetSection);
            }
        }
    });
}

// Configurar botón ☰ para colapsar/expandir sidebar
function setupSidebarToggle() {
    const toggleBtn = document.getElementById('toggle-menu');
    const aside = document.getElementById('sidebar');

    if (toggleBtn && aside) {
        toggleBtn.addEventListener('click', () => {
            // Solo funciona en desktop (no en móvil)
            if (window.innerWidth > 600) {
                aside.classList.toggle('collapsed');
                menuState.sidebarCollapsed = aside.classList.contains('collapsed');
            } else {
                // En móvil, el botón interno cierra el menú
                aside.classList.remove('mobile-open');
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    overlay.classList.remove('active');
                }
            }
        });
    }
}

// Colapsar sidebar
function collapseSidebar() {
    const aside = document.getElementById('sidebar');
    if (aside && window.innerWidth > 600) {
        aside.classList.add('collapsed');
        menuState.sidebarCollapsed = true;
    }
}

// Abrir todas las categorías
function openAllCategories() {
    const categories = document.querySelectorAll('.menu-category');
    categories.forEach(category => {
        const name = category.getAttribute('data-category');
        const submenu = document.getElementById(`submenu-${name}`);
        category.classList.add('expanded');
        if (submenu) submenu.classList.add('expanded');
        if (!menuState.expandedCategories.includes(name)) menuState.expandedCategories.push(name);
    });
}

// Cerrar todas las categorías
function closeAllCategories() {
    const categories = document.querySelectorAll('.menu-category');
    categories.forEach(category => {
        const name = category.getAttribute('data-category');
        const submenu = document.getElementById(`submenu-${name}`);
        category.classList.remove('expanded');
        if (submenu) submenu.classList.remove('expanded');
    });
    menuState.expandedCategories = [];
}

// Función para recargar un iframe específico (MEJORADA)
function reloadSection(sectionId) {
    console.log('🔄 Recargando sección:', sectionId);
    
    const section = document.getElementById(sectionId);
    if (section) {
        const iframe = section.querySelector('iframe');
        if (iframe) {
            const dataSrc = iframe.getAttribute('data-src');
            const currentSrc = iframe.getAttribute('src');
            
            // Obtener la URL base (sin parámetros)
            let baseSrc = dataSrc || currentSrc;
            if (baseSrc) {
                baseSrc = baseSrc.split('?')[0];
                // Agregar timestamp para forzar recarga y evitar caché
                iframe.src = baseSrc + '?t=' + new Date().getTime();
                console.log('✅ Sección recargada:', sectionId);
            }
        }
    } else {
        console.warn('⚠️ Sección no encontrada:', sectionId);
    }
}

// ========================================
// FUNCIONALIDAD MENÚ MÓVIL - MEJORADA
// ========================================

// Configurar menú móvil
function setupMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (mobileMenuBtn && sidebar) {
        // Abrir menú al hacer clic en hamburguesa
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.add('mobile-open');
            sidebar.classList.remove('collapsed');
            
            if (overlay) {
                overlay.classList.add('active');
            }
        });
    }

    if (overlay && sidebar) {
        // Cerrar menú al hacer clic en overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }
}

// Cerrar menú móvil al seleccionar una opción
function closeMobileMenu() {
    if (window.innerWidth <= 600) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
}

// Funciones auxiliares para debug
function getMenuState() {
    return menuState;
}

function resetMenuState() {
    menuState = { expandedCategories: [], sidebarCollapsed: false };
    closeAllCategories();
    const aside = document.getElementById('sidebar');
    if (aside) {
        aside.classList.remove('collapsed');
    }
}

// Exportar funciones globalmente para que los iframes puedan acceder
window.dashboardMenu = {
    getState: getMenuState,
    resetState: resetMenuState,
    closeAll: closeAllCategories,
    openAll: openAllCategories,
    navigateTo: navigateToSection,
    collapseSidebar: collapseSidebar,
    reloadSection: reloadSection,
    pageToSection: PAGE_TO_SECTION_MAP // Exportar el mapeo para referencia
};

// Log de inicialización
console.log('✅ Dashboard.js cargado correctamente');
console.log('📋 Mapeo de páginas:', PAGE_TO_SECTION_MAP);
console.log('📁 Mapeo de rutas:', PAGE_TO_PATH_MAP);