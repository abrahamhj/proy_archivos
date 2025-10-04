<?php
session_start();
include "conexion.php";

$errores = [];
$nombre = "";
$email  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : "";
    $email  = isset($_POST['email']) ? trim($_POST['email']) : "";
    $pass   = isset($_POST['password']) ? (string)$_POST['password'] : "";
    $pass2  = isset($_POST['password2']) ? (string)$_POST['password2'] : "";

    if ($nombre === "") {
        $errores[] = "Ingresa tu nombre.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Ingresa un correo válido.";
    }
    if (strlen($pass) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    }
    if ($pass !== $pass2) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    // Verificar si el correo ya existe
    if (!$errores) {
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errores[] = "Ya existe una cuenta con ese correo.";
            }
            $stmt->close();
        } else {
            $errores[] = "Error interno al validar el correo.";
        }
    }

    // Insertar usuario
    if (!$errores) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'usuario')");
        if ($stmt) {
            $stmt->bind_param("sss", $nombre, $email, $hash);
            if ($stmt->execute()) {
                $nuevoId = $stmt->insert_id;
                $stmt->close();

                // Registrar notificación para administradores (no crítico)
                $msg = "Nuevo usuario registrado: {$nombre} ({$email})";
                if ($ins = $conn->prepare("INSERT INTO notificaciones_admin (mensaje) VALUES (?)")) {
                    $ins->bind_param("s", $msg);
                    $ins->execute();
                    $ins->close();
                }

                // Iniciar sesión automáticamente
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = (int)$nuevoId;
                $_SESSION['nombre']     = $nombre;
                $_SESSION['rol']        = 'usuario';

                header("Location: panel_usuario.php");
                exit;
            } else {
                $errores[] = "No se pudo registrar. Inténtalo nuevamente.";
                $stmt->close();
            }
        } else {
            $errores[] = "Error interno al registrar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registro</title>
    <link rel="stylesheet" href="estilos/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <main class="login-card">
        <h2 class="login-title">Crear cuenta</h2>

        <?php if (!empty($errores)): ?>
            <div class="alert error">
                <?php foreach ($errores as $e): ?>
                    <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input id="nombre" name="nombre" type="text" required value="<?= $nombre ?>" />
            </div>

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" required autocomplete="username" value="<?= $email ?>" />
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" />
            </div>

            <div class="form-group">
                <label for="password2">Confirmar contraseña</label>
                <input id="password2" name="password2" type="password" required autocomplete="new-password" />
            </div>

            <button type="submit" class="btn-primary">Registrarme</button>
            <p class="small-note">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </form>
    </main>

    <script>
    (function(){
        const form = document.querySelector('form');
        const nombre = form.nombre;
        const email = form.email;
        const p1 = form.password;
        const p2 = form.password2;

        form.addEventListener('submit', function(e){
            form.querySelectorAll('.field-error').forEach(el => el.remove());
            let ok = true;
            if (nombre.value.trim() === '') { showError(nombre, 'Ingresa tu nombre.'); ok = false; }
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!re.test(email.value.trim())) { showError(email, 'Ingresa un correo válido.'); ok = false; }
            if (p1.value.length < 6) { showError(p1, 'La contraseña debe tener al menos 6 caracteres.'); ok = false; }
            if (p1.value !== p2.value) { showError(p2, 'Las contraseñas no coinciden.'); ok = false; }
            if (!ok) e.preventDefault();
        });
        function showError(inputEl, message){
            const div = document.createElement('div');
            div.className = 'field-error';
            div.textContent = message;
            inputEl.parentNode.appendChild(div);
        }
    })();
    </script>
</body>
</html>