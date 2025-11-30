/**
 * Script de navegación para integración con Dashboard
 * Incluir al final de cada página del módulo de cobradores
 * Compatible con sistema de mensajería del dashboard
 */

// Función para navegar al dashboard (compatible con iframe y ventana normal)
function volverADashboard(pagina) {
    console.log('🔄 Navegación solicitada:', pagina);
    
    // Verificar si estamos dentro de un iframe
    if (window.parent && window.parent !== window) {
        try {
            // Enviar mensaje al dashboard parent
            window.parent.postMessage({
                type: 'navigate',
                page: pagina
            }, '*'); // Usar '*' para origen o window.location.origin
            
            console.log('✅ Mensaje enviado al dashboard:', pagina);
            return false; // Prevenir navegación por defecto
        } catch (e) {
            console.error('❌ Error al enviar mensaje:', e);
        }
    }
    
    // Si no está en iframe, navegar normalmente
    console.log('➡️ Navegación normal a:', pagina);
    window.location.href = pagina;
    return true;
}

// Configurar automáticamente todos los enlaces de volver
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 Configurando navegación de página');
    
    // Buscar todos los enlaces a páginas del módulo
    const enlaces = document.querySelectorAll('a[href*=".php"]');
    
    enlaces.forEach(enlace => {
        const href = enlace.getAttribute('href');
        
        // Si el enlace tiene onclick, no modificarlo
        if (enlace.getAttribute('onclick')) {
            return;
        }
        
        // Mapeo de archivos PHP a secciones del dashboard
        const mapaNavegacion = {
            'index.php': 'comisiones',
            'prestamos.php': 'prestamos',
            'generar_comision.php': 'generar-comision',
            'registrar_cobro.php': 'registrar-cobro',
            'ver_cobros.php': 'ver-cobros',
            'gestionar_semanas.php': 'semanas-cobro',
            'editar_comision.php': 'comisiones',
            'ver_comision.php': 'comisiones'
        };
        
        // Si el href contiene alguna de estas páginas
        for (const [archivo, seccion] of Object.entries(mapaNavegacion)) {
            if (href && href.includes(archivo)) {
                // Configurar el evento click
                enlace.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Si estamos en iframe, enviar mensaje al dashboard
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({
                            type: 'navigate',
                            page: seccion
                        }, '*');
                        console.log('🔗 Navegando a sección:', seccion);
                    } else {
                        // Si no estamos en iframe, navegar normalmente
                        window.location.href = href;
                    }
                });
                
                console.log('🔗 Enlace configurado:', archivo, '→', seccion);
                break;
            }
        }
    });
    
    console.log('✅ Navegación configurada');
});