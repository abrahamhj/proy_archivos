<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

$admin_id = (int)$_SESSION['id_usuario'];
$nombre_admin = $_SESSION['nombre'] ?? 'Administrador';

$errors = [];
$success = null;

function v($arr, $key, $default = '')
{
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = v($_POST, 'action');

    // --- CREAR USUARIO ---
    if ($action === 'create') {
        $nombre = v($_POST, 'nombre');
        $email  = v($_POST, 'email');
        $rol    = v($_POST, 'rol', 'usuario');
        $pass   = (string)($_POST['password'] ?? '');
        $pass2  = (string)($_POST['password2'] ?? '');

        if ($nombre === '') $errors[] = 'Nombre requerido.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if ($pass === '') $errors[] = 'Contraseña requerida.';
        if ($pass !== $pass2) $errors[] = 'Las contraseñas no coinciden.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT 1 FROM usuarios WHERE email=? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = 'El email ya está registrado.';
            $stmt->close();
        }

        if (!$errors) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)');
            $stmt->bind_param('ssss', $nombre, $email, $hash, $rol);
            if ($stmt->execute()) {
                $success = 'Usuario creado correctamente.';
                $msg = sprintf('Usuario creado por admin: %s (%s) · Rol: %s', $nombre, $email, $rol);
                $conn->query("INSERT INTO notificaciones_admin (mensaje) VALUES ('" . $conn->real_escape_string($msg) . "')");
                header("Location: usuarios.php?msg=" . urlencode($success));
                exit;
            } else {
                $errors[] = 'No se pudo crear el usuario.';
            }
            $stmt->close();
        }
    }

    // --- EDITAR USUARIO ---
    if ($action === 'update') {
        $id     = (int)($_POST['id_usuario'] ?? 0);
        $nombre = v($_POST, 'nombre');
        $email  = v($_POST, 'email');
        $rol    = v($_POST, 'rol', 'usuario');
        $pass   = (string)($_POST['password'] ?? '');
        $pass2  = (string)($_POST['password2'] ?? '');

        if ($id <= 0) $errors[] = 'ID inválido.';
        if ($nombre === '') $errors[] = 'Nombre requerido.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if ($pass !== '' && $pass !== $pass2) $errors[] = 'Las contraseñas no coinciden.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT 1 FROM usuarios WHERE email=? AND id_usuario<>? LIMIT 1');
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = 'El email ya está registrado por otro usuario.';
            $stmt->close();
        }

        if (!$errors) {
            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $conn->prepare('UPDATE usuarios SET nombre=?, email=?, rol=?, password=? WHERE id_usuario=?');
                $stmt->bind_param('ssssi', $nombre, $email, $rol, $hash, $id);
            } else {
                $stmt = $conn->prepare('UPDATE usuarios SET nombre=?, email=?, rol=? WHERE id_usuario=?');
                $stmt->bind_param('sssi', $nombre, $email, $rol, $id);
            }
            if ($stmt->execute()) {
                $success = 'Usuario actualizado correctamente.';
                header("Location: usuarios.php?msg=" . urlencode($success));
                exit;
            } else {
                $errors[] = 'No se pudo actualizar el usuario.';
            }
            $stmt->close();
        }
    }

    // --- ELIMINAR USUARIO ---
    if ($action === 'delete') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'ID inválido.';
        } else {
            $stmt = $conn->prepare('DELETE FROM usuarios WHERE id_usuario=?');
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $success = 'Usuario eliminado.';
                header("Location: usuarios.php?msg=" . urlencode($success));
                exit;
            } else {
                $errors[] = 'No se pudo eliminar el usuario.';
            }
            $stmt->close();
        }
    }
}

// Mostrar mensaje si vino por GET
if (isset($_GET['msg'])) {
    $success = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
}

// Cargar todos los usuarios
$usuarios = [];
$res = $conn->query("SELECT id_usuario, nombre, email, rol, fecha_registro FROM usuarios WHERE rol != 'admin' ORDER BY fecha_registro");
if ($res) {
    while ($row = $res->fetch_assoc()) $usuarios[] = $row;
}

// Cargar usuario para edición
$editUser = null;
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    $stmt = $conn->prepare('SELECT id_usuario, nombre, email, rol FROM usuarios WHERE id_usuario=?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $resE = $stmt->get_result();
    $editUser = $resE ? $resE->fetch_assoc() : null;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar usuarios</title>
    <link rel="stylesheet" href="estilos/index.css" />
    <link rel="stylesheet" href="estilos/menu.css" />
    <link rel="stylesheet" href="estilos/panel_usuario.css" />
    <link rel="stylesheet" href="estilos/usuarios.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <header>
        <div class="navbar">
            <div class="logo">
                <a href="index.php"><img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia"></a>
            </div>
            <ul class="menu" id="menu">
                <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
                <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
                <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
                <li><a href="listar_pagados.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
                <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
            </ul>
            <div class="actions">
                <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h2 class="greet">Gestión de usuarios</h2>

            <?php if (!empty($success)): ?>
                <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid-2">
                <section class="card">
                    <h3 style="margin-top:0;">Crear nuevo usuario</h3>
                    <form method="post" class="form">
                        <input type="hidden" name="action" value="create">
                        <div class="form-row">
                            <div>
                                <label>Nombre</label>
                                <input type="text" name="nombre" required />
                            </div>
                            <div>
                                <label>Email</label>
                                <input type="email" name="email" required />
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Rol</label>
                                <select name="rol" required>
                                    <option value="usuario">Usuario</option>
                                    <option value="invitado">Invitado</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <div></div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Contraseña</label>
                                <input type="password" name="password" required />
                            </div>
                            <div>
                                <label>Confirmar contraseña</label>
                                <input type="password" name="password2" required />
                            </div>
                        </div>
                        <button class="btn" type="submit"><i class="fa-solid fa-user-plus"></i> Crear usuario</button>
                    </form>
                </section>

                <section class="card">
                    <?php if ($editUser): ?>
                        <h3>Editar usuario #<?php echo (int)$editUser['id_usuario']; ?></h3>
                        <form method="post" class="form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id_usuario" value="<?php echo (int)$editUser['id_usuario']; ?>">
                            <div class="form-row">
                                <div>
                                    <label>Nombre</label>
                                    <input type="text" name="nombre" required value="<?php echo htmlspecialchars($editUser['nombre']); ?>" />
                                </div>
                                <div>
                                    <label>Email</label>
                                    <input type="email" name="email" required value="<?php echo htmlspecialchars($editUser['email']); ?>" />
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label>Rol</label>
                                    <select name="rol" required>
                                        <option value="usuario" <?php echo $editUser['rol'] === 'usuario' ? 'selected' : ''; ?>>Usuario</option>
                                        <option value="invitado" <?php echo $editUser['rol'] === 'invitado' ? 'selected' : ''; ?>>Invitado</option>
                                        <option value="admin" <?php echo $editUser['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label>Nueva contraseña (opcional)</label>
                                    <input type="password" name="password" />
                                </div>
                                <div>
                                    <label>Confirmar contraseña</label>
                                    <input type="password" name="password2" />
                                </div>
                            </div>
                            <div class="actions-row">
                                <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
                                <a class="btn outline" href="usuarios.php"><i class="fa-solid fa-xmark"></i> Cancelar</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h3>Editar usuario</h3>
                        <p class="muted">Selecciona “Editar” en la tabla para modificar un usuario.</p>
                    <?php endif; ?>
                </section>
            </div>

            <section class="card" style="margin-top:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                    <h3 style="margin:0;">Usuarios (<?php echo count($usuarios); ?>)</h3>
                    <a class="btn outline" href="panel_admin.php"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>
                </div>
                <div style="overflow:auto; margin-top:8px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$usuarios): ?>
                                <tr>
                                    <td colspan="6" class="muted">Sin usuarios.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td><?php echo (int)$u['id_usuario']; ?></td>
                                        <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['rol']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($u['fecha_registro'])); ?></td>
                                        <td>
                                            <div class="actions-row">
                                                <a class="btn" href="usuarios.php?edit=<?php echo (int)$u['id_usuario']; ?>"><i class="fa-solid fa-pen"></i> Editar</a>
                                                <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id_usuario']; ?>">
                                                    <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia · Administración</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const success = document.querySelector('.alert-success');
            const error = document.querySelector('.alert-error');

            if (success) {
                setTimeout(() => {
                    setTimeout(() => success.remove(), 600);
                }, 3000);
            }

            if (error) {
                setTimeout(() => {
                    setTimeout(() => error.remove(), 600);
                }, 3000);
            }
        });
    </script>

</body>

</html>