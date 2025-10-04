<?php
session_start();
include "conexion.php";
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== "admin") {
  header("Location: login.php");
  exit();
}
$nombre = $_SESSION['nombre'] ?? 'Administrador';

// Métricas
function get_count($conn, $sql)
{
  $res = $conn->query($sql);
  if ($res && ($row = $res->fetch_assoc())) return (int)array_values($row)[0];
  return 0;
}
$cnt_usuarios = get_count($conn, "SELECT COUNT(*) FROM usuarios WHERE rol='usuario'");
$cnt_talleres = get_count($conn, "SELECT COUNT(*) FROM talleres");
$cnt_archivos = get_count($conn, "SELECT COUNT(*) FROM archivos");
$cnt_insc_pend = get_count($conn, "SELECT COUNT(*) FROM inscripciones WHERE estado='pendiente'");
$cnt_pagos_pend = get_count($conn, "SELECT COUNT(*) FROM pagos WHERE validado=0");
$notificaciones_pendientes = get_count($conn, "SELECT COUNT(*) FROM notificaciones_admin WHERE leida = 0");

// Últimas notificaciones admin
$ultimas_notis = [];
$res = $conn->query("SELECT mensaje, fecha, leida FROM notificaciones_admin ORDER BY fecha DESC LIMIT 3");
if ($res) {
  while ($r = $res->fetch_assoc()) $ultimas_notis[] = $r;
}

function txt_trunc($text, $width = 80, $ellipsis = '…')
{
  if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $width, $ellipsis, 'UTF-8');
  $text = (string)$text;
  if (strlen($text) <= $width) return $text;
  return substr($text, 0, max(0, $width - strlen($ellipsis))) . $ellipsis;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Panel de Administración</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
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
        <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
        <li><a href="listar_pagados.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
        <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones <?php if ($notificaciones_pendientes > 0) echo '(' . $notificaciones_pendientes . ')'; ?></a></li>
      </ul>
      <div class="actions">
        <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <h2 class="greet">Hola, <?php echo htmlspecialchars($nombre); ?> 👑</h2>
      <div class="grid">
        <section class="card span-4">
          <div class="stat">
            <h3>Usuarios</h3>
            <div class="num"><?php echo $cnt_usuarios; ?></div>
          </div>
          <p class="muted">Totales registrados</p>
          <a class="btn" href="usuarios.php">Gestionar</a>
        </section>

        <section class="card span-4">
          <div class="stat">
            <h3>Talleres</h3>
            <div class="num"><?php echo $cnt_talleres; ?></div>
          </div>
          <p class="muted">Publicados</p>
          <a class="btn" href="listar_talleres.php">Ver talleres</a>
          <a class="btn outline" href="crear_taller.php">Crear taller</a>
        </section>

        <section class="card span-4">
          <div class="stat">
            <h3>Archivos</h3>
            <div class="num"><?php echo $cnt_archivos; ?></div>
          </div>
          <p class="muted">Archivos subidos</p>
          <a class="btn" href="Ver_archivos.php">Ver archivos</a>
        </section>

        <section class="card span-6">
          <h3 style="margin-top:0">Operaciones pendientes</h3>
          <div class="info" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
            <div class="info-card" style="border:1px solid #e7eef2;border-radius:12px;padding:12px;">
              <div class="stat">
                <h3 style="margin:0">Inscripciones</h3>
                <div class="num"><?php echo $cnt_insc_pend; ?></div>
              </div>
              <p class="muted" style="margin:.25rem 0 0;">Pendientes</p>
              <a class="btn" href="listar_inscripciones.php" style="margin-top:8px;">Revisar</a>
            </div>
            <div class="info-card" style="border:1px solid #e7eef2;border-radius:12px;padding:12px;">
              <div class="stat">
                <h3 style="margin:0">Pagos</h3>
                <div class="num"><?php echo $cnt_pagos_pend; ?></div>
              </div>
              <p class="muted" style="margin:.25rem 0 0;">Por validar</p>
              <a class="btn" href="listar_pagados.php" style="margin-top:8px;">Validar</a>
            </div>
          </div>
        </section>

        <section class="card span-6">
          <h3 style="margin-top:0">Últimas notificaciones</h3>
          <?php if (count($ultimas_notis)): ?>
            <ul class="clean">
              <?php foreach ($ultimas_notis as $n): ?>
                <li class="notif-item">
                  <div><?php echo htmlspecialchars(txt_trunc($n['mensaje'], 100)); ?></div>
                  <div class="muted"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($n['fecha']))); ?> · <?php echo $n['leida'] ? 'Leída' : 'No leída'; ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
            <a class="btn" href="notificaciones_admin.php">Ver todas</a>
          <?php else: ?>
            <p class="muted">Sin notificaciones aún.</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia · Administración</p>
  </footer>
</body>

</html>