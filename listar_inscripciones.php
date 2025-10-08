<?php
session_start();
include "conexion.php";

// Solo admin puede ver las inscripciones
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$nombre = $_SESSION['nombre'] ?? 'Administrador';

// Notificaciones pendientes para el header
$notificaciones_pendientes = 0;
if ($resN = $conn->query("SELECT COUNT(*) AS c FROM notificaciones_admin WHERE leida = 0")) {
    if ($rn = $resN->fetch_assoc()) $notificaciones_pendientes = (int)$rn['c'];
}

// Obtener detalle de inscripciones, ordenado para agrupar por usuario
$sql = "
    SELECT 
        u.id_usuario, u.nombre, u.email,
        t.titulo, t.fecha,
        i.estado
    FROM inscripciones i
    INNER JOIN usuarios u ON u.id_usuario = i.id_usuario
    INNER JOIN talleres t ON t.id_taller = i.id_taller
    ORDER BY u.nombre ASC, t.fecha DESC
";
$res = $conn->query($sql);

// Agrupar por usuario
$usuarios = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $uid = (int)$row['id_usuario'];
        if (!isset($usuarios[$uid])) {
            $usuarios[$uid] = [
                'nombre' => $row['nombre'],
                'email' => $row['email'],
                'talleres' => []
            ];
        }
        $usuarios[$uid]['talleres'][] = [
            'titulo' => $row['titulo'],
            'fecha' => $row['fecha'],
            'estado' => $row['estado']
        ];
    }
}

function estado_badge($estado)
{
    $estado = strtolower((string)$estado);
    $styles = [
        'pendiente' => ['#b45309', '#fef3c7'], // amber-700 text on amber-100 bg
        'pagado'    => ['#065f46', '#d1fae5'], // emerald-800 on emerald-100
        'rechazado' => ['#991b1b', '#fee2e2'], // red-800 on red-100
    ];
    $pair = $styles[$estado] ?? ['#334155', '#e2e8f0']; // slate-700 on slate-200
    return '<span style="display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;color:'
        . $pair[0] . ';background:' . $pair[1] . ';text-transform:uppercase;">' . htmlspecialchars($estado) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inscripciones · Administración</title>
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="estilos/panel_usuario.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .tbl-insc{ width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl-insc th,.tbl-insc td{ padding:10px; border-bottom:1px solid #e7eef2; vertical-align:top; }
    .tbl-insc .col-user{ width:220px; }
    .tbl-insc .col-email{ width:260px; }
    .tbl-insc .col-total{ width:90px; text-align:right; font-variant-numeric:tabular-nums; }
    .tbl-insc .col-talleres{ width:auto; }
    .tbl-insc .truncate{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    @media (max-width: 900px){
      .tbl-insc .col-user{ width:180px; }
      .tbl-insc .col-email{ width:200px; }
      .tbl-insc .col-total{ width:70px; }
    }
  </style>
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
        <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
        <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
        <li><a class="active" href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
        <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
        <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones <?php if ($notificaciones_pendientes > 0) echo '(' . $notificaciones_pendientes . ')'; ?></a></li>
      </ul>
      <div class="actions">
        <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <h2 class="greet">Inscripciones de usuarios</h2>

      <div class="card">
        <div style="overflow:auto">
          <table class="table tbl-insc">
            <thead>
              <tr>
                <th class="col-user" style="text-align:left;">Usuario</th>
                <th class="col-email" style="text-align:left;">Email</th>
                <th class="col-total">Total</th>
                <th class="col-talleres" style="text-align:left;">Talleres inscritos</th>
              </tr>
            </thead>
            <tbody>
            <?php if (count($usuarios)): ?>
              <?php foreach ($usuarios as $u): ?>
                <tr>
                  <td class="col-user"><span class="truncate"><?php echo htmlspecialchars($u['nombre']); ?></span></td>
                  <td class="col-email"><span class="truncate"><?php echo htmlspecialchars($u['email']); ?></span></td>
                  <td class="col-total"><?php echo count($u['talleres']); ?></td>
                  <td class="col-talleres">
                    <?php if (count($u['talleres'])): ?>
                      <ul class="clean" style="margin:0; padding-left:18px;">
                        <?php foreach ($u['talleres'] as $t): ?>
                          <li style="margin:6px 0;">
                            <span style="font-weight:600;"><?php echo htmlspecialchars($t['titulo']); ?></span>
                            <span class="muted"> · <?php echo htmlspecialchars(date('d/m/Y', strtotime($t['fecha']))); ?></span>
                            <span> · <?php echo estado_badge($t['estado']); ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" style="padding:14px; text-align:center;">No hay inscripciones registradas</td>
              </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <p style="margin-top:16px;"><a class="btn" href="panel_admin.php">← Volver al panel</a></p>
    </div>
  </main>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia · Administración</p>
  </footer>
</body>
</html>

