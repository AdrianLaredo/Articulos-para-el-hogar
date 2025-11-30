// Script de navegación para dashboard con iframe
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Script de navegación cargado');
});

// Función para navegar dentro del iframe o página normal
function navegarA(pagina) {
    if (window.parent && window.parent !== window) {
        console.log('🔄 Navegando a:', pagina);
        // Si está en iframe, navega usando postMessage
        window.parent.postMessage({
            type: 'navigate', 
            page: pagina,
            fullUrl: pagina
        }, '*');
    } else {
        // Si está en página normal, navega directamente
        window.location.href = pagina;
    }
}   