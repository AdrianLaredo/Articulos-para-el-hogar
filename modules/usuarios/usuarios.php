<?php
session_start();
require_once '../../bd/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login/login.php");
    exit;
}

// Solo admin puede acceder a este módulo
if ($_SESSION['rol'] !== 'admin') {
    echo "<script>alert('No tienes permisos para acceder a este módulo'); window.location.href='../dashboard/dashboard.php';</script>";
    exit;
}

$usuario_actual = $_SESSION['usuario'];
$id_usuario_actual = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;

// Si no hay ID en sesión, intentar obtenerlo de la BD
if ($id_usuario_actual === 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM Usuarios WHERE usuario = ?");
        $stmt->execute([$usuario_actual]);
        $row = $stmt->fetch();
        if ($row) {
            $id_usuario_actual = (int)$row['id'];
            $_SESSION['id_usuario'] = $id_usuario_actual;
        }
    } catch (Exception $e) {
        // Continuar sin ID
    }
}

// ============================================
// MANEJO DE PETICIONES AJAX
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = getDB();
        $action = $_POST['action'];
        
        // ==================== LISTAR USUARIOS ====================
        if ($action === 'listar') {
            $query = "SELECT 
                        id,
                        usuario,
                        nombre,
                        apellido_paterno,
                        apellido_materno,
                        rol,
                        estado,
                        fecha_registro
                      FROM Usuarios
                      ORDER BY fecha_registro DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios
            ]);
            exit;
        }
        
        // ==================== CREAR USUARIO ====================
        elseif ($action === 'crear') {
            $campos_requeridos = ['usuario', 'nombre', 'apellido_paterno', 'apellido_materno', 'rol', 'estado', 'password', 'password_confirmar'];
            
            foreach ($campos_requeridos as $campo) {
                if (!isset($_POST[$campo]) || empty(trim($_POST[$campo]))) {
                    echo json_encode(['success' => false, 'message' => "El campo '$campo' es requerido"]);
                    exit;
                }
            }
            
            $usuario = trim($_POST['usuario']);
            $nombre = trim($_POST['nombre']);
            $apellido_paterno = trim($_POST['apellido_paterno']);
            $apellido_materno = trim($_POST['apellido_materno']);
            $rol = $_POST['rol'];
            $estado = $_POST['estado'];
            $password = $_POST['password'];
            $password_confirmar = $_POST['password_confirmar'];
            
            // Validar que las contraseñas coincidan
            if ($password !== $password_confirmar) {
                echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
                exit;
            }
            
            if (!in_array($rol, ['admin', 'vendedor'])) {
                echo json_encode(['success' => false, 'message' => 'Rol inválido']);
                exit;
            }
            
            if (!in_array($estado, ['activo', 'inactivo'])) {
                echo json_encode(['success' => false, 'message' => 'Estado inválido']);
                exit;
            }
            
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'La contraseña debe tener mínimo 6 caracteres']);
                exit;
            }
            
            // Verificar si el usuario ya existe
            $check = $db->prepare("SELECT COUNT(*) FROM Usuarios WHERE usuario = ?");
            $check->execute([$usuario]);
            
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'El usuario ya existe']);
                exit;
            }
            
            // Hash MD5 de la contraseña
            $password_hash = md5($password);
            
            // Insertar usuario
            $query = "INSERT INTO Usuarios 
                      (usuario, contraseña, nombre, apellido_paterno, apellido_materno, rol, estado) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $usuario,
                $password_hash,
                $nombre,
                $apellido_paterno,
                $apellido_materno,
                $rol,
                $estado
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al crear el usuario']);
            }
            exit;
        }
        
        // ==================== ELIMINAR USUARIO ====================
        elseif ($action === 'eliminar') {
            if (!isset($_POST['id']) || empty($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
                exit;
            }
            
            $id_eliminar = (int)$_POST['id'];
            
            // VALIDACIÓN CRÍTICA: No puede eliminar su propio usuario
            if ($id_eliminar === $id_usuario_actual && $id_usuario_actual > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No puedes eliminar tu propio usuario'
                ]);
                exit;
            }
            
            // Verificar que el usuario existe
            $check = $db->prepare("SELECT usuario FROM Usuarios WHERE id = ?");
            $check->execute([$id_eliminar]);
            $usuario = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                exit;
            }
            
            // Eliminar usuario
            $query = "DELETE FROM Usuarios WHERE id = ?";
            $stmt = $db->prepare($query);
            $result = $stmt->execute([$id_eliminar]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario "' . $usuario['usuario'] . '" eliminado exitosamente'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
            }
            exit;
        }
        
        // ==================== CAMBIAR CONTRASEÑA ====================
        elseif ($action === 'cambiar_password') {
            if (!isset($_POST['password_actual']) || !isset($_POST['password_nueva'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit;
            }
            
            $password_actual = $_POST['password_actual'];
            $password_nueva = $_POST['password_nueva'];
            
            if (strlen($password_nueva) < 6) {
                echo json_encode(['success' => false, 'message' => 'La contraseña nueva debe tener mínimo 6 caracteres']);
                exit;
            }
            
            // Verificar contraseña actual
            $query = "SELECT contraseña FROM Usuarios WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id_usuario_actual]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                exit;
            }
            
            // Verificar que la contraseña actual sea correcta (MD5)
            $password_actual_hash = md5($password_actual);
            
            if ($password_actual_hash !== $usuario['contraseña']) {
                echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
                exit;
            }
            
            // Hash MD5 de la nueva contraseña
            $password_nueva_hash = md5($password_nueva);
            
            // Actualizar contraseña
            $update = "UPDATE Usuarios SET contraseña = ? WHERE id = ?";
            $stmt = $db->prepare($update);
            $result = $stmt->execute([$password_nueva_hash, $id_usuario_actual]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al cambiar la contraseña']);
            }
            exit;
        }
        
        else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/usuarios.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>
                <i class='bx bx-user-circle'></i>
                Gestión de Usuarios
            </h1>
            <div class="header-actions">
                <button class="btn btn-primary" id="btnCambiarPassword">
                    <i class='bx bx-key'></i>
                    Cambiar mi Contraseña
                </button>
                <button class="btn btn-success" id="btnNuevoUsuario">
                    <i class='bx bx-plus'></i>
                    Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="card filtros-card">
            <div class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre, usuario o apellido...">
                    </div>
                    <div class="form-group">
                        <select id="filterRol" class="form-control">
                            <option value="">Todos los roles</option>
                            <option value="admin">Admin</option>
                            <option value="vendedor">Vendedor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="filterEstado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-container">
                <table class="table-comisiones">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyUsuarios">
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: #2196F3;">
                            <i class='bx bx-loader-alt bx-spin' style="font-size: 40px;"></i><br>Cargando usuarios...
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL NUEVO USUARIO -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nuevo Usuario</h2>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <form id="formUsuario">
                <div class="form-group">
                    <label for="usuario">
                        <i class='bx bx-user'></i>
                        Usuario *
                    </label>
                    <input type="text" id="usuario" name="usuario" class="form-control" required placeholder="Nombre de usuario único">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">
                            <i class='bx bx-id-card'></i>
                            Nombre *
                        </label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="apellidoPaterno">Apellido Paterno *</label>
                        <input type="text" id="apellidoPaterno" name="apellido_paterno" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="apellidoMaterno">Apellido Materno *</label>
                    <input type="text" id="apellidoMaterno" name="apellido_materno" class="form-control" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="rol">
                            <i class='bx bx-shield'></i>
                            Rol *
                        </label>
                        <select id="rol" name="rol" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <option value="admin">Administrador</option>
                            <option value="vendedor">Vendedor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="estado">
                            <i class='bx bx-check-circle'></i>
                            Estado *
                        </label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class='bx bx-lock'></i>
                        Contraseña * (mínimo 6 caracteres)
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres" minlength="6">
                        <span onclick="togglePassword('password')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 20px;">
                            <i class='bx bx-show'></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="passwordConfirmar">
                        <i class='bx bx-check-shield'></i>
                        Confirmar Contraseña *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="passwordConfirmar" name="password_confirmar" class="form-control" required placeholder="Repite la contraseña" minlength="6">
                        <span onclick="togglePassword('passwordConfirmar')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 20px;">
                            <i class='bx bx-show'></i>
                        </span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancelar">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i>
                        Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CAMBIAR CONTRASEÑA -->
    <div id="modalPassword" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Cambiar mi Contraseña</h2>
                <span class="close" id="closeModalPassword">&times;</span>
            </div>
            <form id="formCambiarPassword">
                <div class="form-group">
                    <label for="passwordActual">
                        <i class='bx bx-lock'></i>
                        Contraseña Actual *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="passwordActual" name="password_actual" class="form-control" required>
                        <span onclick="togglePassword('passwordActual')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 20px;">
                            <i class='bx bx-show'></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="passwordNueva">
                        <i class='bx bx-lock-open'></i>
                        Contraseña Nueva * (mínimo 6 caracteres)
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="passwordNueva" name="password_nueva" class="form-control" required minlength="6">
                        <span onclick="togglePassword('passwordNueva')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 20px;">
                            <i class='bx bx-show'></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="passwordConfirmarCambio">
                        <i class='bx bx-check-shield'></i>
                        Confirmar Contraseña Nueva *
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="passwordConfirmarCambio" name="password_confirmar" class="form-control" required minlength="6">
                        <span onclick="togglePassword('passwordConfirmarCambio')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 20px;">
                            <i class='bx bx-show'></i>
                        </span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancelarPassword">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i>
                        Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const ID_USUARIO_ACTUAL = <?php echo $id_usuario_actual; ?>;
        const USUARIO_ACTUAL = '<?php echo htmlspecialchars($usuario_actual); ?>';
        let usuariosData = [];

        console.log('ID Usuario Actual:', ID_USUARIO_ACTUAL);
        console.log('Usuario Actual:', USUARIO_ACTUAL);

        const btnNuevoUsuario = document.getElementById('btnNuevoUsuario');
        const btnCambiarPassword = document.getElementById('btnCambiarPassword');
        const modalUsuario = document.getElementById('modalUsuario');
        const modalPassword = document.getElementById('modalPassword');
        const closeModal = document.getElementById('closeModal');
        const closeModalPassword = document.getElementById('closeModalPassword');
        const btnCancelar = document.getElementById('btnCancelar');
        const btnCancelarPassword = document.getElementById('btnCancelarPassword');
        const formUsuario = document.getElementById('formUsuario');
        const formCambiarPassword = document.getElementById('formCambiarPassword');
        const searchInput = document.getElementById('searchInput');
        const filterRol = document.getElementById('filterRol');
        const filterEstado = document.getElementById('filterEstado');

        document.addEventListener('DOMContentLoaded', () => {
            cargarUsuarios();
            
            btnNuevoUsuario.addEventListener('click', () => {
                formUsuario.reset();
                modalUsuario.style.display = 'block';
            });
            
            btnCambiarPassword.addEventListener('click', () => {
                formCambiarPassword.reset();
                modalPassword.style.display = 'block';
            });
            
            closeModal.addEventListener('click', () => modalUsuario.style.display = 'none');
            closeModalPassword.addEventListener('click', () => modalPassword.style.display = 'none');
            btnCancelar.addEventListener('click', () => modalUsuario.style.display = 'none');
            btnCancelarPassword.addEventListener('click', () => modalPassword.style.display = 'none');
            
            formUsuario.addEventListener('submit', guardarUsuario);
            formCambiarPassword.addEventListener('submit', cambiarPassword);
            searchInput.addEventListener('input', filtrarUsuarios);
            filterRol.addEventListener('change', filtrarUsuarios);
            filterEstado.addEventListener('change', filtrarUsuarios);
            
            window.addEventListener('click', (e) => {
                if (e.target === modalUsuario) modalUsuario.style.display = 'none';
                if (e.target === modalPassword) modalPassword.style.display = 'none';
            });
        });

        async function cargarUsuarios() {
            try {
                const formData = new FormData();
                formData.append('action', 'listar');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', text);
                    mostrarError('Error al procesar la respuesta del servidor');
                    return;
                }
                
                if (data.success) {
                    usuariosData = data.usuarios;
                    mostrarUsuarios(usuariosData);
                } else {
                    mostrarError('Error al cargar usuarios: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error al cargar los usuarios');
            }
        }

        function mostrarUsuarios(usuarios) {
            const tbody = document.getElementById('tbodyUsuarios');
            
            if (usuarios.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class='bx bx-user-x'></i>
                            <p>No se encontraron usuarios</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = usuarios.map(usuario => {
                const esUsuarioActual = (usuario.id === ID_USUARIO_ACTUAL) || (usuario.usuario === USUARIO_ACTUAL);
                const rolBadge = usuario.rol === 'admin' ? 'badge-warning' : 'badge-info';
                const estadoBadge = usuario.estado === 'activo' ? 'badge-success' : 'estado-badge';
                const rolTexto = usuario.rol === 'admin' ? 'Administrador' : 'Vendedor';
                
                console.log('Usuario:', usuario.usuario, 'ID:', usuario.id, 'Es actual:', esUsuarioActual);
                
                return `
                    <tr>
                        <td>
                            <strong>${usuario.usuario}</strong>
                            ${esUsuarioActual ? '<span style="color: #2196F3; font-weight: bold;"> (Tú)</span>' : ''}
                        </td>
                        <td>${usuario.nombre} ${usuario.apellido_paterno} ${usuario.apellido_materno}</td>
                        <td><span class="estado-badge ${rolBadge}">${rolTexto}</span></td>
                        <td><span class="estado-badge ${estadoBadge}">${usuario.estado}</span></td>
                        <td>${formatearFecha(usuario.fecha_registro)}</td>
                        <td>
                            ${!esUsuarioActual ? `
                                <button class="btn btn-danger btn-sm" onclick="eliminarUsuario(${usuario.id}, '${usuario.usuario}')">
                                    <i class='bx bx-trash'></i>
                                    Eliminar
                                </button>
                            ` : `
                                <span class="text-muted">No puedes eliminarte</span>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filtrarUsuarios() {
            const searchTerm = searchInput.value.toLowerCase();
            const rolFilter = filterRol.value;
            const estadoFilter = filterEstado.value;
            
            const usuariosFiltrados = usuariosData.filter(usuario => {
                const matchSearch = 
                    usuario.usuario.toLowerCase().includes(searchTerm) ||
                    usuario.nombre.toLowerCase().includes(searchTerm) ||
                    usuario.apellido_paterno.toLowerCase().includes(searchTerm) ||
                    usuario.apellido_materno.toLowerCase().includes(searchTerm);
                
                const matchRol = !rolFilter || usuario.rol === rolFilter;
                const matchEstado = !estadoFilter || usuario.estado === estadoFilter;
                
                return matchSearch && matchRol && matchEstado;
            });
            
            mostrarUsuarios(usuariosFiltrados);
        }

        async function guardarUsuario(e) {
            e.preventDefault();
            
            // Validar que las contraseñas coincidan en el frontend también
            const password = document.getElementById('password').value;
            const passwordConfirmar = document.getElementById('passwordConfirmar').value;
            
            if (password !== passwordConfirmar) {
                mostrarError('Las contraseñas no coinciden');
                return;
            }
            
            const formData = new FormData(formUsuario);
            formData.append('action', 'crear');
            
            const submitBtn = formUsuario.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Guardando...';
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarExito(data.message);
                    modalUsuario.style.display = 'none';
                    cargarUsuarios();
                } else {
                    mostrarError(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error al guardar el usuario');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }
        }

        async function eliminarUsuario(id, usuario) {
            const esUsuarioActual = (id === ID_USUARIO_ACTUAL) || (usuario === USUARIO_ACTUAL);
            
            if (esUsuarioActual) {
                mostrarError('No puedes eliminar tu propio usuario');
                return;
            }
            
            if (!confirm(`¿Estás seguro de eliminar al usuario "${usuario}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'eliminar');
                formData.append('id', id);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarExito(data.message);
                    cargarUsuarios();
                } else {
                    mostrarError(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error al eliminar el usuario');
            }
        }

        async function cambiarPassword(e) {
            e.preventDefault();
            
            const passwordNueva = document.getElementById('passwordNueva').value;
            const passwordConfirmar = document.getElementById('passwordConfirmarCambio').value;
            
            if (passwordNueva !== passwordConfirmar) {
                mostrarError('Las contraseñas no coinciden');
                return;
            }
            
            const formData = new FormData(formCambiarPassword);
            formData.append('action', 'cambiar_password');
            
            const submitBtn = formCambiarPassword.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Cambiando...';
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarExito(data.message);
                    modalPassword.style.display = 'none';
                } else {
                    mostrarError(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                mostrarError('Error al cambiar la contraseña');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            } else {
                input.type = 'password';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            }
        }

        function formatearFecha(fechaString) {
            const fecha = new Date(fechaString);
            const opciones = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return fecha.toLocaleDateString('es-MX', opciones);
        }

        function mostrarExito(mensaje) {
            const alert = document.createElement('div');
            alert.className = 'mensaje success';
            alert.innerHTML = `
                <i class='bx bx-check-circle'></i>
                <span>${mensaje}</span>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alert, container.firstChild);
            
            setTimeout(() => alert.remove(), 4000);
        }

        function mostrarError(mensaje) {
            const alert = document.createElement('div');
            alert.className = 'mensaje error';
            alert.innerHTML = `
                <i class='bx bx-error-circle'></i>
                <span>${mensaje}</span>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alert, container.firstChild);
            
            setTimeout(() => alert.remove(), 5000);
        }
    </script>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(3px);
        overflow-y: auto; /* Permite scroll en el modal */
        padding: 20px 0; /* Espacio arriba y abajo */
    }

    .modal-content {
        background: white;
        margin: 20px auto;
        padding: 0;
        width: 90%;
        max-width: 700px;
        max-height: calc(100vh - 40px); /* No más alto que la pantalla menos padding */
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 25px 30px;
        background: linear-gradient(135deg, #2196F3, #1976D2);
        color: white;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0; /* No se encoge */
    }

    .modal-header h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 600;
    }

    .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        line-height: 1;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .modal form {
        padding: 30px;
        overflow-y: auto; /* Permite scroll dentro del formulario */
        flex: 1; /* Toma el espacio disponible */
    }

    .bx-spin {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* RESPONSIVE PARA MÓVILES */
    @media screen and (max-width: 768px) {
        .modal {
            padding: 10px 0;
        }

        .modal-content {
            width: 95%;
            max-height: calc(100vh - 20px);
            margin: 10px auto;
        }

        .modal-header {
            padding: 20px;
        }

        .modal-header h2 {
            font-size: 18px;
        }

        .modal form {
            padding: 20px;
        }
    }

    /* Para pantallas muy pequeñas */
    @media screen and (max-height: 600px) {
        .modal-content {
            margin: 10px auto;
        }

        .modal-header {
            padding: 15px 20px;
        }

        .modal form {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px !important;
        }
    }
</style>
</body>
</html>