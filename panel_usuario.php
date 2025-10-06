<?php
session_start();
include "conexion.php";
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== "usuario") {
  header("Location: index.php");
  exit();
}

$id_usuario = (int)$_SESSION['id_usuario'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';

// Contar notificaciones no leídas
$notificaciones_pendientes = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notificaciones WHERE id_usuario = ? AND leida = 0")) {
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $res = $stmt->get_result();
  $notificaciones_pendientes = (int)($res->fetch_assoc()['total'] ?? 0);
  $stmt->close();
}

// Contar archivos del usuario
$mis_archivos = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS total FROM archivos WHERE id_usuario = ?")) {
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $res = $stmt->get_result();
  $mis_archivos = (int)($res->fetch_assoc()['total'] ?? 0);
  $stmt->close();
}

// Contar talleres inscritos del usuario (inscripciones)
$mis_talleres = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS total FROM inscripciones WHERE id_usuario = ?")) {
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $res = $stmt->get_result();
  $mis_talleres = (int)($res->fetch_assoc()['total'] ?? 0);
  $stmt->close();
}

// Próximo taller (por fecha futura más cercana)
$proximo_taller = null;
if ($stmt = $conn->prepare("SELECT t.titulo, t.fecha FROM inscripciones i JOIN talleres t ON i.id_taller = t.id_taller WHERE i.id_usuario = ? AND t.fecha >= CURDATE() ORDER BY t.fecha ASC LIMIT 1")) {
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows) {
    $proximo_taller = $res->fetch_assoc();
  }
  $stmt->close();
}

// Últimas notificaciones
$ultimas_notis = [];
if ($stmt = $conn->prepare("SELECT mensaje, fecha, leida FROM notificaciones WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 3")) {
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $ultimas_notis[] = $row;
  }
  $stmt->close();
}

// Helper para truncar texto de forma segura sin requerir mbstring
function txt_trunc($text, $width = 80, $ellipsis = '…')
{
  if (function_exists('mb_strimwidth')) {
    return mb_strimwidth($text, 0, $width, $ellipsis, 'UTF-8');
  }
  $text = (string)$text;
  if (strlen($text) <= $width) return $text;
  return substr($text, 0, max(0, $width - strlen($ellipsis))) . $ellipsis;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Panel de Usuario</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="estilos/panel_usuario.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <header>
    <div class="navbar">
      <div class="logo">
        <a href="index.php">
          <img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia">
        </a>
      </div>

      <ul class="menu" id="menu">
        <li><a href="index.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
        <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
        <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Mis pagos</a></li>
        <li><a class="active" href="mis_talleres.php"><i class="fa-solid fa-list-check"></i> Mis talleres</a></li>
        <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
      </ul>

      <div class="actions">
        <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <h2 class="greet">Bienvenido, <?php echo $nombre; ?> 🎉</h2>

      <div class="grid">
        <section class="card span-4">
          <div class="stat">
            <h3>Mis archivos</h3>
            <div class="num"><?php echo $mis_archivos; ?></div>
          </div>
          <div>
            <a class="btn" href="listar_archivos.php">Ver archivos</a>
          </div>
        </section>

        <section class="card span-4">
          <div class="stat">
            <h3>Mis talleres</h3>
            <div class="num"><?php echo $mis_talleres; ?></div>
          </div>
          <div>
            <a class="btn" href="mis_talleres.php">Ver Talleres</a>
          </div>
        </section>

        <section class="card span-4">
          <div class="stat">
            <h3>Notificaciones</h3>
            <div class="num"><?php echo $notificaciones_pendientes; ?></div>
          </div>
          <div>
            <a class="btn" href="notificaciones.php">Ver todas</a>
          </div>
        </section>

        <section class="card span-8">
          <h3 style="margin-top:0">Próximo taller</h3>
          <?php if ($proximo_taller): ?>
            <p><strong><?php echo $proximo_taller['titulo']; ?></strong></p>
            <p class="muted">Fecha: <?php echo date('d/m/Y', strtotime($proximo_taller['fecha'])); ?></p>
            <a class="btn" href="mis_talleres.php">Ver detalles</a>
          <?php else: ?>
            <p class="muted">Aún no tienes talleres próximos. Explora e inscríbete en uno.</p>
            <a class="btn outline" href="listar_talleres.php">Explorar talleres</a>
          <?php endif; ?>
        </section>

        <section class="card span-4">
          <h3 style="margin-top:0">Últimas notificaciones</h3>
          <?php if (count($ultimas_notis)): ?>
            <ul class="clean">
              <?php foreach ($ultimas_notis as $n): ?>
                <li class="notif-item">
                  <div><?php echo txt_trunc($n['mensaje'], 80); ?></div>
                  <div class="muted"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?> <?php echo $n['leida'] ? '• Leída' : '• No leída'; ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">No tienes notificaciones aún.</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia · Todos los derechos reservados</p>
  </footer>
</body>

</html>