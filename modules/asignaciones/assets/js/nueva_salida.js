// Array para almacenar productos agregados
let productosAgregados = [];
let stream = null;

// Inicializar Select2 cuando el documento esté listo
$(document).ready(function() {
    // Inicializar Select2 para Empleados
    $('#id_empleado').select2({
        placeholder: '🔍 Escribe para buscar un empleado...',
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron empleados";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });
    
    // Inicializar Select2 para Vehículos
    $('#placas').select2({
        placeholder: '🚗 Escribe para buscar un vehículo...',
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron vehículos";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });
    
    // Inicializar Select2 para Productos
    $('#producto_select').select2({
        placeholder: '📦 Escribe para buscar un producto...',
        allowClear: true,
        language: {
            noResults: function() {
                return "No se encontraron productos";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });
});

// Elementos del DOM
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const preview = document.getElementById('preview');
const fotoPreview = document.getElementById('foto-preview');
const btnIniciarCamera = document.getElementById('btnIniciarCamera');
const btnTomarFoto = document.getElementById('btnTomarFoto');
const btnNuevaFoto = document.getElementById('btnNuevaFoto');
const fotoInput = document.getElementById('foto_salida');

// Iniciar cámara con mejor manejo de errores
btnIniciarCamera.addEventListener('click', async function() {
    try {
        // Verificar si el navegador soporta getUserMedia
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Tu navegador no soporta acceso a la cámara. Por favor usa el botón "Subir Archivo" para cargar una foto.');
            return;
        }

        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment',  // Cámara trasera en móviles
                width: { ideal: 1280 },
                height: { ideal: 720 }
            } 
        });
        video.srcObject = stream;
        video.style.display = 'block';
        btnIniciarCamera.style.display = 'none';
        btnTomarFoto.style.display = 'inline-flex';
    } catch (err) {
        console.error('Error:', err);
        if (err.name === 'NotAllowedError') {
            alert('Debes permitir el acceso a la cámara. Ve a configuración del navegador.');
        } else if (err.name === 'NotFoundError') {
            alert('No se encontró ninguna cámara en este dispositivo.');
        } else {
            alert('Error al acceder a la cámara. Usa el botón "Subir Archivo" para cargar una foto desde tu galería.');
        }
    }
});

// Tomar foto
btnTomarFoto.addEventListener('click', function() {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0);
    
    // Convertir canvas a blob y crear archivo
    canvas.toBlob(function(blob) {
        const file = new File([blob], 'foto_salida.jpg', { type: 'image/jpeg' });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fotoInput.files = dataTransfer.files;
        
        // Mostrar preview
        fotoPreview.src = URL.createObjectURL(blob);
        preview.style.display = 'block';
        video.style.display = 'none';
        btnTomarFoto.style.display = 'none';
        btnNuevaFoto.style.display = 'inline-flex';
        
        // Detener stream
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    }, 'image/jpeg', 0.8);
});

// Tomar nueva foto
btnNuevaFoto.addEventListener('click', function() {
    preview.style.display = 'none';
    btnNuevaFoto.style.display = 'none';
    btnIniciarCamera.style.display = 'inline-flex';
    fotoInput.value = '';
});

// Manejar carga de archivo
fotoInput.addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(event) {
            fotoPreview.src = event.target.result;
            preview.style.display = 'block';
            video.style.display = 'none';
            btnTomarFoto.style.display = 'none';
            btnNuevaFoto.style.display = 'inline-flex';
            btnIniciarCamera.style.display = 'none';
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        };
        reader.readAsDataURL(e.target.files[0]);
    }
});

// Agregar producto a la tabla
function agregarProducto() {
    const selectElement = $('#producto_select');
    const selectedOption = selectElement.find('option:selected');
    const cantidad = parseInt(document.getElementById('cantidad_producto').value);
    
    if (!selectElement.val()) {
        alert('Selecciona un producto');
        return;
    }
    
    if (!cantidad || cantidad < 1) {
        alert('Ingresa una cantidad válida');
        return;
    }
    
    const id_producto = selectElement.val();
    const nombre = selectedOption.data('nombre');
    const stock = parseInt(selectedOption.data('stock'));
    
    // Validar stock
    if (cantidad > stock) {
        alert(`No hay suficiente stock. Disponible: ${stock}`);
        return;
    }
    
    // Verificar si ya existe
    const existe = productosAgregados.find(p => p.id_producto == id_producto);
    if (existe) {
        alert('Este producto ya está agregado. Elimínalo primero si deseas cambiar la cantidad.');
        return;
    }
    
    // Agregar al array
    productosAgregados.push({
        id_producto: id_producto,
        nombre: nombre,
        cantidad: cantidad,
        stock: stock
    });
    
    actualizarTabla();
    
    // Limpiar selección con Select2
    selectElement.val(null).trigger('change');
    document.getElementById('cantidad_producto').value = '1';
}

// Eliminar producto
function eliminarProducto(index) {
    if (confirm('¿Eliminar este producto?')) {
        productosAgregados.splice(index, 1);
        actualizarTabla();
    }
}

// Actualizar tabla de productos
function actualizarTabla() {
    const tbody = document.getElementById('productosBody');
    
    if (productosAgregados.length === 0) {
        tbody.innerHTML = `
            <tr class="empty-row">
                <td colspan="4" class="text-center text-muted">
                    No hay productos agregados
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    productosAgregados.forEach((producto, index) => {
        html += `
            <tr>
                <td>${producto.nombre}</td>
                <td><strong>${producto.cantidad}</strong></td>
                <td>${producto.stock}</td>
                <td>
                    <button type="button" class="btn-action btn-delete" onclick="eliminarProducto(${index})">
                        <i class='bx bx-trash'></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Validar formulario antes de enviar
document.getElementById('formSalida').addEventListener('submit', function(e) {
    if (productosAgregados.length === 0) {
        e.preventDefault();
        alert('Debes agregar al menos un producto');
        return false;
    }
    
    if (!fotoInput.files || fotoInput.files.length === 0) {
        e.preventDefault();
        alert('Debes tomar o subir una foto de evidencia');
        return false;
    }
    
    // Convertir productos a JSON
    document.getElementById('productos_json').value = JSON.stringify(productosAgregados);
    
    return true;
});

// Limpiar stream al salir
window.addEventListener('beforeunload', function() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});

// Detectar cuando se vuelve a cargar la página y limpiar
window.addEventListener('pageshow', function(event) {
    // Si es navegación hacia atrás o recarga
    if (event.persisted || performance.navigation.type === 2) {
        // Limpiar todo
        productosAgregados = [];
        actualizarTabla();
        document.getElementById('formSalida').reset();
        
        // Limpiar Select2
        $('#id_empleado').val(null).trigger('change');
        $('#placas').val(null).trigger('change');
        $('#producto_select').val(null).trigger('change');
        
        preview.style.display = 'none';
        video.style.display = 'none';
        btnIniciarCamera.style.display = 'inline-flex';
        btnTomarFoto.style.display = 'none';
        btnNuevaFoto.style.display = 'none';
    }
});