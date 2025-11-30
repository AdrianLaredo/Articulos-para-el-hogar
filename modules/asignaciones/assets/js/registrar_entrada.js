let foliosCreados = [];
let contadorFolios = 0;
let productosDisponibles = {};

document.addEventListener('DOMContentLoaded', function() {
    inicializarProductosDisponibles();
    configurarFormulario();
});

function inicializarProductosDisponibles() {
    const filas = document.querySelectorAll('#tablaProductosCargados tbody tr');
    filas.forEach(fila => {
        const id = fila.dataset.id;
        const nombre = fila.dataset.nombre;
        const cargado = parseInt(fila.dataset.cargado);
        const precioVenta = parseFloat(fila.dataset.precioVenta);
        
        productosDisponibles[id] = {
            id: id,
            nombre: nombre,
            cargado: cargado,
            vendido: 0,
            disponible: cargado,
            precioVenta: precioVenta
        };
    });
}

function agregarFolioMejorado() {
    contadorFolios++;
    const folioId = 'folio_' + contadorFolios;
    
    const folioHtml = `
        <div class="folio-card" id="${folioId}" style="background: white; padding: 25px; margin-bottom: 20px; border-radius: 8px; border: 2px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #0c3c78;">
                    <i class='bx bx-file'></i> Folio de Venta
                </h3>
                <button type="button" class="btn btn-danger" onclick="eliminarFolio('${folioId}')" style="padding: 5px 10px;">
                    <i class='bx bx-trash'></i> Eliminar
                </button>
            </div>
            
            <!-- NÚMERO DE FOLIO MANUAL -->
            <div class="folio-manual">
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <i class='bx bx-hash'></i> Número de Folio (Manual) *
                        </label>
                        <input type="text" 
                               id="${folioId}_numero" 
                               placeholder="Ej: FV-001-2024, VENTA-123, etc." 
                               class="form-control"
                               required
                               onblur="validarNumeroFolio('${folioId}')"
                               style="background: #fffbea; font-weight: bold;">
                        <small style="color: #6b7280;">Ingrese el número de folio manualmente</small>
                    </div>
                </div>
            </div>
            
            <!-- DATOS DEL CLIENTE -->
            <div class="form-grid" style="margin-top: 20px;">
                <div class="form-group">
                    <label><i class='bx bx-user'></i> Nombre del Cliente *</label>
                    <input type="text" id="${folioId}_cliente" placeholder="Nombre completo" required>
                </div>
                <div class="form-group">
                    <label><i class='bx bx-map'></i> Zona *</label>
                    <select id="${folioId}_zona" required style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Seleccionar zona...</option>
                        <option value="XZ">XZ</option>
                        <option value="WZ">WZ</option>
                        <option value="VZ">VZ</option>
                        <option value="KZ">KZ</option>
                        <option value="AKZ">AKZ </option>
                        <option value="TZ">TZ</option>
                        <option value="RZ">RZ</option>
                        <option value="YZ">YZ</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><i class='bx bx-home'></i> Dirección</label>
                    <input type="text" id="${folioId}_direccion" placeholder="Dirección completa">
                </div>
            </div>
            
            <!-- PRODUCTOS CON PRECIO Y COMISIÓN EDITABLES -->
            <div style="margin: 20px 0;">
                <h4 style="color: #0c3c78; margin-bottom: 15px;">
                    <i class='bx bx-cart'></i> Productos, Precios y Comisiones
                </h4>
                
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    <div style="display: grid; grid-template-columns: 2fr 0.8fr 1fr 1fr auto; gap: 10px; align-items: flex-end;">
                        <div style="display: flex; flex-direction: column;">
                            <label>Producto</label>
                            <select id="${folioId}_producto_select" style="width: 100%;" onchange="actualizarPrecioProducto('${folioId}')">
                                <option value="">Seleccionar producto...</option>
                                ${obtenerOpcionesProductos()}
                            </select>
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label>Cantidad</label>
                            <input type="number" id="${folioId}_cantidad" min="1" value="1" style="width: 70px;">
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label>Precio Unit.</label>
                            <input type="number" id="${folioId}_precio" min="0" step="0.01" value="0" style="width: 100px;" placeholder="0.00">
                        </div>
                        <div style="display: flex; flex-direction: column;">
                            <label>Comisión $</label>
                            <input type="number" id="${folioId}_comision" min="0" step="0.01" value="0" style="width: 100px;" placeholder="0.00">
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="agregarProductoFolio('${folioId}')">
                                <i class='bx bx-plus'></i> Agregar
                            </button>
                        </div>
                    </div>
                </div>
                
                <table style="width: 100%;">
                    <thead style="background: #e5e7eb;">
                        <tr>
                            <th style="padding: 10px;">Producto</th>
                            <th style="padding: 10px; text-align: center;">Cantidad</th>
                            <th style="padding: 10px; text-align: center;">Precio Unit.</th>
                            <th style="padding: 10px; text-align: center;">Comisión $</th>
                            <th style="padding: 10px; text-align: right;">Subtotal</th>
                            <th style="padding: 10px; text-align: right;">Total Comisión</th>
                            <th style="padding: 10px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="${folioId}_productos">
                        <tr class="empty-row">
                            <td colspan="7" style="text-align: center; padding: 20px; color: #9ca3af;">
                                No hay productos agregados
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f3f4f6; font-weight: bold;">
                            <td colspan="4" style="padding: 10px; text-align: right;">TOTAL VENTA:</td>
                            <td style="padding: 10px; text-align: right; color: #0c3c78;" id="${folioId}_total_venta">$0.00</td>
                            <td style="padding: 10px; text-align: right; color: #10b981;" id="${folioId}_total_comision">$0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- SECCIÓN DE ENGANCHE -->
            <div class="enganche-section">
                <h4 style="color: #0c3c78; margin-bottom: 15px;">
                    <i class='bx bx-dollar-circle'></i> Enganche y Forma de Pago
                </h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Monto de Enganche</label>
                        <input type="number" 
                               id="${folioId}_enganche" 
                               min="0" 
                               step="0.01" 
                               value="0" 
                               placeholder="0.00"
                               onchange="calcularSaldoPendiente('${folioId}')"
                               oninput="calcularSaldoPendiente('${folioId}')">
                    </div>
                    <div class="form-group">
                        <label>Método de Pago</label>
                        <select id="${folioId}_metodo_pago">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saldo Pendiente</label>
                        <input type="text" 
                               id="${folioId}_saldo_pendiente" 
                               value="$0.00" 
                               readonly 
                               style="background: #e5e7eb; font-weight: bold; color: #dc2626;">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Venta</label>
                        <input type="text" 
                               id="${folioId}_tipo_venta" 
                               value="Contado" 
                               readonly 
                               style="background: #e5e7eb; font-weight: bold; color: #10b981;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>Observaciones</label>
                    <textarea id="${folioId}_observaciones" 
                              rows="2" 
                              placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('foliosContainer').insertAdjacentHTML('beforeend', folioHtml);
    actualizarResumen();
}

function actualizarPrecioProducto(folioId) {
    const select = document.getElementById(folioId + '_producto_select');
    const precioInput = document.getElementById(folioId + '_precio');
    
    if (select.value) {
        const producto = productosDisponibles[select.value];
        precioInput.value = producto.precioVenta.toFixed(2);
    } else {
        precioInput.value = '0.00';
    }
}

function obtenerOpcionesProductos() {
    let opciones = '';
    for (let id in productosDisponibles) {
        const producto = productosDisponibles[id];
        if (producto.disponible > 0) {
            opciones += `<option value="${id}" data-precio="${producto.precioVenta}">
                ${producto.nombre} (Disponible: ${producto.disponible}) - $${producto.precioVenta.toFixed(2)}
            </option>`;
        }
    }
    return opciones;
}

function agregarProductoFolio(folioId) {
    const select = document.getElementById(folioId + '_producto_select');
    const cantidadInput = document.getElementById(folioId + '_cantidad');
    const precioInput = document.getElementById(folioId + '_precio');
    const comisionInput = document.getElementById(folioId + '_comision');
    
    const productoId = select.value;
    const cantidad = parseInt(cantidadInput.value);
    const precioUnitario = parseFloat(precioInput.value);
    const comision = parseFloat(comisionInput.value);
    
    // Validaciones
    if (!productoId) {
        alert('Seleccione un producto');
        return;
    }
    
    if (cantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        return;
    }
    
    if (precioUnitario < 0) {
        alert('El precio no puede ser negativo');
        return;
    }
    
    if (comision < 0) {
        alert('La comisión no puede ser negativa');
        return;
    }
    
    const producto = productosDisponibles[productoId];
    
    if (cantidad > producto.disponible) {
        alert(`Solo hay ${producto.disponible} unidades disponibles de ${producto.nombre}`);
        return;
    }
    
    // Actualizar disponibilidad
    producto.disponible -= cantidad;
    producto.vendido += cantidad;
    
    // Calcular subtotal y total de comisión
    const subtotal = precioUnitario * cantidad;
    const totalComision = comision * cantidad;
    
    // Agregar fila a la tabla
    const tbody = document.getElementById(folioId + '_productos');
    
    // Eliminar fila vacía si existe
    const emptyRow = tbody.querySelector('.empty-row');
    if (emptyRow) {
        emptyRow.remove();
    }
    
    const productoRow = `
        <tr data-producto-id="${productoId}" 
            data-cantidad="${cantidad}" 
            data-precio="${precioUnitario}"
            data-comision="${comision}">
            <td style="padding: 10px;">${producto.nombre}</td>
            <td style="padding: 10px; text-align: center;">
                <input type="number" 
                       value="${cantidad}" 
                       min="1" 
                       max="${cantidad + producto.disponible}"
                       onchange="actualizarCantidadProducto(this, '${folioId}', '${productoId}')"
                       style="width: 60px; text-align: center; padding: 5px; border: 1px solid #d1d5db; border-radius: 4px;">
            </td>
            <td style="padding: 10px; text-align: center;">
                <input type="number" 
                       value="${precioUnitario.toFixed(2)}" 
                       min="0" 
                       step="0.01"
                       onchange="actualizarPrecioProductoTabla(this, '${folioId}', '${productoId}')"
                       style="width: 100px; text-align: center; padding: 5px; border: 1px solid #d1d5db; border-radius: 4px; font-weight: 600; background: #fffbeb;">
            </td>
            <td style="padding: 10px; text-align: center;">
                <input type="number" 
                       value="${comision.toFixed(2)}" 
                       min="0" 
                       step="0.01"
                       onchange="actualizarComisionProducto(this, '${folioId}', '${productoId}')"
                       style="width: 100px; text-align: center; padding: 5px; border: 1px solid #d1d5db; border-radius: 4px; font-weight: 600; background: #f0fdf4;">
            </td>
            <td style="padding: 10px; text-align: right; font-weight: 700;">$${subtotal.toFixed(2)}</td>
            <td style="padding: 10px; text-align: right; font-weight: 700; color: #10b981;">$${totalComision.toFixed(2)}</td>
            <td style="padding: 10px; text-align: center;">
                <button type="button" 
                        onclick="eliminarProductoFolio(this, '${folioId}', '${productoId}')" 
                        style="padding: 5px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    <i class='bx bx-trash'></i>
                </button>
            </td>
        </tr>
    `;
    
    tbody.insertAdjacentHTML('beforeend', productoRow);
    
    // Limpiar inputs
    select.value = '';
    cantidadInput.value = '1';
    precioInput.value = '0.00';
    comisionInput.value = '0.00';
    
    // Actualizar totales
    calcularTotalesFolio(folioId);
    actualizarTablaProductosCargados();
    actualizarOpcionesProductos();
    actualizarResumen();
}

function actualizarPrecioProductoTabla(input, folioId, productoId) {
    const nuevoPrecio = parseFloat(input.value);
    
    if (nuevoPrecio < 0) {
        alert('El precio no puede ser negativo');
        input.value = input.defaultValue;
        return;
    }
    
    const fila = input.closest('tr');
    fila.dataset.precio = nuevoPrecio;
    
    calcularTotalesFolio(folioId);
    actualizarResumen();
}

function actualizarCantidadProducto(input, folioId, productoId) {
    const fila = input.closest('tr');
    const cantidadAnterior = parseInt(fila.dataset.cantidad);
    const nuevaCantidad = parseInt(input.value);
    
    if (nuevaCantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        input.value = cantidadAnterior;
        return;
    }
    
    const producto = productosDisponibles[productoId];
    const diferencia = nuevaCantidad - cantidadAnterior;
    
    if (diferencia > producto.disponible) {
        alert(`Solo hay ${producto.disponible} unidades adicionales disponibles`);
        input.value = cantidadAnterior;
        return;
    }
    
    // Actualizar disponibilidad
    producto.disponible -= diferencia;
    producto.vendido += diferencia;
    
    // Actualizar datos de la fila
    fila.dataset.cantidad = nuevaCantidad;
    
    // Recalcular totales
    calcularTotalesFolio(folioId);
    actualizarTablaProductosCargados();
    actualizarOpcionesProductos();
    actualizarResumen();
}

function actualizarComisionProducto(input, folioId, productoId) {
    const nuevaComision = parseFloat(input.value);
    
    if (nuevaComision < 0) {
        alert('La comisión no puede ser negativa');
        input.value = input.defaultValue;
        return;
    }
    
    const fila = input.closest('tr');
    fila.dataset.comision = nuevaComision;
    
    calcularTotalesFolio(folioId);
    actualizarResumen();
}

function eliminarProductoFolio(btn, folioId, productoId) {
    const fila = btn.closest('tr');
    const cantidad = parseInt(fila.dataset.cantidad);
    
    // Devolver producto al inventario
    productosDisponibles[productoId].disponible += cantidad;
    productosDisponibles[productoId].vendido -= cantidad;
    
    // Eliminar fila
    fila.remove();
    
    // Si no quedan productos, mostrar mensaje
    const tbody = document.getElementById(folioId + '_productos');
    if (tbody.querySelectorAll('tr').length === 0) {
        tbody.innerHTML = `
            <tr class="empty-row">
                <td colspan="7" style="text-align: center; padding: 20px; color: #9ca3af;">
                    No hay productos agregados
                </td>
            </tr>
        `;
    }
    
    calcularTotalesFolio(folioId);
    actualizarTablaProductosCargados();
    actualizarOpcionesProductos();
    actualizarResumen();
}

function calcularTotalesFolio(folioId) {
    const tbody = document.getElementById(folioId + '_productos');
    const filas = tbody.querySelectorAll('tr:not(.empty-row)');
    
    let totalVenta = 0;
    let totalComision = 0;
    
    filas.forEach(fila => {
        const cantidad = parseInt(fila.dataset.cantidad);
        const precio = parseFloat(fila.dataset.precio);
        const comision = parseFloat(fila.dataset.comision);
        
        const subtotal = cantidad * precio;
        const comisionTotal = cantidad * comision;
        
        totalVenta += subtotal;
        totalComision += comisionTotal;
        
        // Actualizar valores en la fila
        const celdas = fila.querySelectorAll('td');
        celdas[4].textContent = '$' + subtotal.toFixed(2);
        celdas[5].textContent = '$' + comisionTotal.toFixed(2);
    });
    
    document.getElementById(folioId + '_total_venta').textContent = '$' + totalVenta.toFixed(2);
    document.getElementById(folioId + '_total_comision').textContent = '$' + totalComision.toFixed(2);
    
    calcularSaldoPendiente(folioId);
}

function calcularSaldoPendiente(folioId) {
    const totalVentaText = document.getElementById(folioId + '_total_venta').textContent;
    const totalVenta = parseFloat(totalVentaText.replace('$', '').replace(',', ''));
    
    const enganche = parseFloat(document.getElementById(folioId + '_enganche').value) || 0;
    const saldoPendiente = totalVenta - enganche;
    
    document.getElementById(folioId + '_saldo_pendiente').value = '$' + saldoPendiente.toFixed(2);
    
    // Actualizar tipo de venta
    const tipoVenta = saldoPendiente <= 0 ? 'Contado' : 'Crédito';
    const tipoVentaInput = document.getElementById(folioId + '_tipo_venta');
    tipoVentaInput.value = tipoVenta;
    tipoVentaInput.style.color = tipoVenta === 'Contado' ? '#10b981' : '#f59e0b';
}

function validarNumeroFolio(folioId) {
    const numeroInput = document.getElementById(folioId + '_numero');
    const numero = numeroInput.value.trim();
    
    if (!numero) return;
    
    // Verificar que no esté duplicado en otros folios
    const folios = document.querySelectorAll('.folio-card');
    let duplicado = false;
    
    folios.forEach(folio => {
        if (folio.id !== folioId) {
            const otroNumero = document.getElementById(folio.id + '_numero').value.trim();
            if (otroNumero === numero) {
                duplicado = true;
            }
        }
    });
    
    if (duplicado) {
        alert(`El número de folio "${numero}" ya existe en otro folio. Por favor use un número diferente.`);
        numeroInput.value = '';
        numeroInput.focus();
    }
}

function actualizarTablaProductosCargados() {
    const filas = document.querySelectorAll('#tablaProductosCargados tbody tr');
    filas.forEach(fila => {
        const id = fila.dataset.id;
        const producto = productosDisponibles[id];
        
        fila.querySelector('.cantidad-vendida').textContent = producto.vendido;
        fila.querySelector('.disponible-venta').textContent = producto.disponible;
    });
}

function actualizarOpcionesProductos() {
    const selects = document.querySelectorAll('[id$="_producto_select"]');
    const opciones = obtenerOpcionesProductos();
    
    selects.forEach(select => {
        const valorActual = select.value;
        select.innerHTML = '<option value="">Seleccionar producto...</option>' + opciones;
        select.value = valorActual;
    });
}

function eliminarFolio(folioId) {
    if (!confirm('¿Está seguro de eliminar este folio? Se devolverán los productos al inventario disponible.')) {
        return;
    }
    
    // Devolver productos al inventario
    const tbody = document.getElementById(folioId + '_productos');
    const filas = tbody.querySelectorAll('tr:not(.empty-row)');
    
    filas.forEach(fila => {
        const productoId = fila.dataset.productoId;
        const cantidad = parseInt(fila.dataset.cantidad);
        
        productosDisponibles[productoId].disponible += cantidad;
        productosDisponibles[productoId].vendido -= cantidad;
    });
    
    // Eliminar folio del DOM
    document.getElementById(folioId).remove();
    
    // Actualizar todo
    actualizarTablaProductosCargados();
    actualizarOpcionesProductos();
    actualizarResumen();
}

function actualizarResumen() {
    let totalVendidos = 0;
    let totalComisiones = 0;
    let totalFolios = 0;
    
    const folios = document.querySelectorAll('.folio-card');
    totalFolios = folios.length;
    
    // Calcular totales
    for (let id in productosDisponibles) {
        totalVendidos += productosDisponibles[id].vendido;
    }
    
    // Calcular comisiones totales
    folios.forEach(folio => {
        const comisionText = folio.querySelector('[id$="_total_comision"]').textContent;
        totalComisiones += parseFloat(comisionText.replace('$', '').replace(',', ''));
    });
    
    const totalCargados = Object.values(productosDisponibles).reduce((sum, p) => sum + p.cargado, 0);
    const totalDevueltos = totalCargados - totalVendidos;
    
    document.getElementById('totalVendidos').textContent = totalVendidos;
    document.getElementById('totalComisiones').textContent = `$${totalComisiones.toFixed(2)}`;
    document.getElementById('totalFolios').textContent = totalFolios;
    
    // Actualizar devueltos si existe el elemento
    if (document.getElementById('totalDevueltos')) {
        document.getElementById('totalDevueltos').textContent = totalDevueltos;
    }
}

function configurarFormulario() {
    const form = document.getElementById('formCerrar');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Permitir cerrar aunque no haya folios
        const folios = document.querySelectorAll('.folio-card');
        if (folios.length === 0) {
            if (!confirm('No hay folios creados. ¿Desea cerrar la asignación de todos modos?')) {
                return;
            }
        }
        
        // Recopilar datos de todos los folios
        const foliosData = [];
        let valid = true;
        
        folios.forEach(folio => {
            const folioId = folio.id;
            
            // Validar campos obligatorios
            const numeroFolio = document.getElementById(folioId + '_numero').value.trim();
            const cliente = document.getElementById(folioId + '_cliente').value.trim();
            
            if (!numeroFolio) {
                alert('Todos los folios deben tener un número de folio');
                valid = false;
                return;
            }
            
            if (!cliente) {
                alert('Todos los folios deben tener un nombre de cliente');
                valid = false;
                return;
            }
            
            // Validar que tiene productos
            const tbody = document.getElementById(folioId + '_productos');
            const productos = tbody.querySelectorAll('tr:not(.empty-row)');
            
            if (productos.length === 0) {
                alert(`El folio ${numeroFolio} debe tener al menos un producto`);
                valid = false;
                return;
            }
            
            // Recopilar datos del folio
            const productosData = [];
            productos.forEach(prod => {
                productosData.push({
                    id_producto: prod.dataset.productoId,
                    cantidad: parseInt(prod.dataset.cantidad),
                    precio_unitario: parseFloat(prod.dataset.precio),
                    comision: parseFloat(prod.dataset.comision)
                });
            });
            
            foliosData.push({
                numero_folio: numeroFolio,
                nombre_cliente: cliente,
                zona: document.getElementById(folioId + '_zona').value,
                direccion: document.getElementById(folioId + '_direccion').value,
                enganche: parseFloat(document.getElementById(folioId + '_enganche').value) || 0,
                metodo_pago: document.getElementById(folioId + '_metodo_pago').value,
                observaciones: document.getElementById(folioId + '_observaciones').value,
                productos: productosData
            });
        });
        
        if (!valid) return;
        
        // Confirmar cierre
        const confirmMsg = `¿Está seguro de cerrar la asignación con ${foliosData.length} folio(s)?\n\n` +
                        `Total vendido: ${document.getElementById('totalVendidos').textContent} productos\n` +
                        `Total comisiones: ${document.getElementById('totalComisiones').textContent}`;
        
        if (!confirm(confirmMsg)) return;
        
        // Asignar datos al campo hidden y enviar
        document.getElementById('folios_json').value = JSON.stringify(foliosData);
        form.submit();
    });
}

function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(valor);
}