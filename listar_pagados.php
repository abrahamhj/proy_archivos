<?php
session_start();
include "conexion.php";

// Solo admin puede ver este listado
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

$sql = "SELECT u.nombre AS usuario, u.email, t.titulo AS taller, t.fecha, t.precio
        FROM inscripciones i
        JOIN usuarios u ON i.id_usuario = u.id_usuario
        JOIN talleres t ON i.id_taller = t.id_taller
        WHERE i.estado = 'pagado'
        ORDER BY t.fecha DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Listado de Pagados</title>
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="estilos/panel_usuario.css" />
</head>
<body>
<header>
  <div class="navbar">
    <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo"></a></div>
    <ul class="menu" id="menu">
      <li><a href="panel_admin.php">Panel</a></li>
      <li><a href="listar_talleres.php">Talleres</a></li>
      <li><a href="listar_inscripciones.php">Inscripciones</a></li>
      <li><a class="active" href="listar_pagados.php">Pagos</a></li>
    </ul>
    <div class="actions">
      <a class="btn danger" href="logout.php">Cerrar sesión</a>
    </div>
  </div>
</header>

<main>
  <div class="wrap">
    <h2>Listado de Participantes Pagados</h2>
    <a class="btn" href="exportar_pagados_excel.php">Exportar a Excel</a>
    <div class="card" style="margin-top:10px;">
      <div style="overflow:auto">
        <table class="table" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:8px; border-bottom:1px solid #e7eef2;">Usuario</th>
              <th style="text-align:left; padding:8px; border-bottom:1px solid #e7eef2;">Email</th>
              <th style="text-align:left; padding:8px; border-bottom:1px solid #e7eef2;">Taller</th>
              <th style="text-align:left; padding:8px; border-bottom:1px solid #e7eef2;">Fecha</th>
              <th style="text-align:right; padding:8px; border-bottom:1px solid #e7eef2;">Precio (Bs)</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($row['usuario']); ?></td>
                <td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($row['email']); ?></td>
                <td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($row['taller']); ?></td>
                <td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($row['fecha']); ?></td>
                <td style="padding:8px; border-bottom:1px solid #f1f5f9; text-align:right;"><?php echo htmlspecialchars($row['precio']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="padding:12px; text-align:center;">No hay inscripciones pagadas</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p style="margin-top:16px;"><a class="btn" href="panel_admin.php">← Volver al panel</a></p>
  </div>
</main>
</body>
</html>
