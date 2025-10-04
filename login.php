<?php
session_start();
include "conexion.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = isset($_POST['email']) ? trim($_POST['email']) : "";
  $pass  = isset($_POST['password']) ? $_POST['password'] : "";

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Por favor ingresa un correo válido.";
  } elseif ($pass === "") {
    $error = "Ingresa tu contraseña.";
  } else {
    $stmt = $conn->prepare("SELECT id_usuario, nombre, password, rol FROM usuarios WHERE email = ?");
    if ($stmt) {
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
        if (password_verify($pass, $row['password'])) {
          session_regenerate_id(true);

          $_SESSION['id_usuario'] = (int)$row['id_usuario'];
          $_SESSION['nombre']     = $row['nombre'];
          $_SESSION['rol']        = $row['rol'];

          if ($row['rol'] === 'admin') {
            header("Location: panel_admin.php");
            exit;
          } else {
            header("Location: panel_usuario.php");
            exit;
          }
        } else {
          $error = "Contraseña incorrecta.";
        }
      } else {
        $error = "No encontramos una cuenta con ese correo.";
      }
      $stmt->close();
    } else {
      $error = "Error interno. Inténtalo más tarde.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Iniciar sesión - Salvemos los archivos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="estilos/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <main class="login-card">
    <h2 class="login-title">Iniciar sesión</h2>

    <?php if (!empty($error)): ?>
      <div class="alert error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" required autocomplete="username">
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn-primary">Iniciar Sesión</button>

      <p class="small-note">
        ¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a>
      </p>
    </form>
  </main>

  <script>
    (function() {
      const form = document.querySelector("form");
      const email = form.email;
      const pass = form.password;

      form.addEventListener("submit", function(e) {
        form.querySelectorAll(".field-error").forEach(el => el.remove());
        let ok = true;

        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(email.value.trim())) {
          showError(email, "Por favor ingresa un correo válido.");
          ok = false;
        }
        if (pass.value.trim() === "") {
          showError(pass, "Ingresa tu contraseña.");
          ok = false;
        }
        if (!ok) e.preventDefault();
      });

      function showError(inputEl, message) {
        const div = document.createElement("div");
        div.className = "field-error";
        div.textContent = message;
        inputEl.parentNode.appendChild(div);
      }
    })();
  </script>
</body>

</html>