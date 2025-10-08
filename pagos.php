<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario'])) {
  header("Location: login.php");
  exit();
}

$rol     = $_SESSION['rol'] ?? 'invitado';
$nombre  = $_SESSION['nombre'] ?? '';
$id_user = (int)$_SESSION['id_usuario'];

/* ====== Notificaciones (header) ====== */
$notificaciones_pendientes = 0;
if ($rol === 'admin') {
  if ($resN = $conn->query("SELECT COUNT(*) AS c FROM notificaciones_admin WHERE leida = 0")) {
    if ($rn = $resN->fetch_assoc()) $notificaciones_pendientes = (int)$rn['c'];
  }
}

/* ====== Utils ====== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function estado_badge($estado){
  $estado = strtolower((string)$estado);
  $styles = [
    'pendiente' => ['#b45309', '#fef3c7'],
    'pagado'    => ['#065f46', '#d1fae5'],
    'rechazado' => ['#991b1b', '#fee2e2'],
  ];
  $pair = $styles[$estado] ?? ['#334155', '#e2e8f0'];
  return '<span style="display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;color:' .
    $pair[0] . ';background:' . $pair[1] . ';text-transform:uppercase;">' . h($estado) . '</span>';
}

/* ====== Flash messages ====== */
$flash_msg = '';
$flash_type = '';
if (isset($_GET['msg'])) {
  if ($_GET['msg'] === 'subido')   { $flash_msg = "Comprobante subido correctamente. Está pendiente de validación."; $flash_type = 'success'; }
  elseif ($_GET['msg'] === 'aprobado') { $flash_msg = "Pago aprobado correctamente."; $flash_type = 'success'; }
  elseif ($_GET['msg'] === 'rechazado'){ $flash_msg = "Pago rechazado."; $flash_type = 'error'; }
}
if (isset($_GET['error'])) {
  $flash_type = 'error';
  switch ($_GET['error']) {
    case 'inscripcion': $flash_msg = 'Inscripción no válida.'; break;
    case 'archivo':     $flash_msg = 'Error al recibir el archivo.'; break;
    case 'tipo':        $flash_msg = 'Tipo de archivo no permitido. Sube imagen o PDF.'; break;
    case 'tamano':      $flash_msg = 'El archivo excede el tamaño máximo (5MB).'; break;
    case 'guardar':     $flash_msg = 'No se pudo guardar el archivo. Intenta nuevamente.'; break;
    case 'ya_subido':   $flash_msg = 'Ya enviaste un comprobante para este taller. Espera la validación.'; break;
    case 'ya_pagado':   $flash_msg = 'Esta inscripción ya está pagada.'; break;
    default:            $flash_msg = 'Ocurrió un error.'; break;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo ($rol === 'admin' ? 'Pagos · Admin' : 'Mis pagos'); ?></title>
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="estilos/panel_usuario.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    /* Tabla base */
    .tbl{ width:100%; border-collapse:collapse; table-layout:fixed; }
    .tbl th,.tbl td{ border-bottom:1px solid #e7eef2; padding:10px; vertical-align:middle; text-align:left; }
    .tbl th{ white-space:nowrap; }
    .tbl .col-act{ white-space:nowrap; }

    /* Mantener columnas clave con ancho mínimo */
    .tbl .col-state{ width:140px; }
    .tbl .col-date{  width:110px; }
    .tbl .col-price{ width:120px; }
    .tbl .col-act{   width:420px; }

    .truncate-2{
      min-width:0;
      display:-webkit-box;
      -webkit-box-orient:vertical;
      -webkit-line-clamp:2;
      line-clamp:2;
      overflow:hidden;
      text-overflow:ellipsis;
      word-break:break-word;
    }

    /* Miniatura QR */
    .qr-thumb-btn{
      width:40px; height:40px; padding:0; border:0; background:transparent; cursor:zoom-in;
      display:inline-flex; align-items:center; justify-content:center;
    }
    .qr-thumb{
      width:100%; height:100%; object-fit:contain;
      border:1px solid #e7eef2; border-radius:6px; background:#fff;
      display:block;
    }
    .qr-group{ display:flex; align-items:center; gap:10px; }

    /* Pila vertical dentro de la celda "Acción" */
    .actions-stack{ display:flex; flex-direction:column; align-items:flex-end; gap:12px; }

    /* Línea de formulario "Subir" */
    .row-actions{
      display:flex; gap:12px; align-items:center; justify-content:flex-end;
      flex-wrap:wrap;
    }
    /* Input de archivo controlado */
    .file-narrow{
      width:100%; max-width:240px; height:40px; align-self:center;
    }
    .row-actions .btn{ white-space:nowrap; margin:0; line-height:1; display:inline-flex; align-items:center; height:40px; }
    .row-actions form{ display:flex; align-items:center; gap:12px; margin:0; }
    .row-actions input[type="file"]{ font-size:.95rem; }

    .table-scroll{ overflow-x:auto; }

    /* Modal imagen */
    #img-modal{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; align-items:center; justify-content:center; }
    #img-modal img{ max-width:90vw; max-height:90vh; box-shadow:0 10px 30px rgba(0,0,0,.5); border-radius:8px; }

    @media (max-width: 1100px){
      .row-actions .btn{ padding:8px 12px; font-size:.95rem; }
      .tbl .col-act{ width:360px; }
      .file-narrow{ max-width:200px; }
    }
    @media (max-width: 820px){
      .tbl .col-act{ width:100%; }
    }

    .note-rechazo{ color:#991b1b; font-size:.9rem; text-align:right; margin-bottom:4px; }
  </style>
</head>

<body>
  <header>
    <div class="navbar">
      <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia"></a></div>
      <ul class="menu" id="menu">
        <?php if ($rol === 'admin'): ?>
          <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
          <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
          <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
          <li><a class="active" href="pagos.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
          <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones <?php if ($notificaciones_pendientes > 0) echo '(' . $notificaciones_pendientes . ')'; ?></a></li>
        <?php else: ?>
          <li><a href="panel_usuario.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
          <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
          <li><a class="active" href="pagos.php"><i class="fa-solid fa-cash-register"></i> Mis pagos</a></li>
          <li><a href="mis_talleres.php"><i class="fa-solid fa-list-check"></i> Mis talleres</a></li>
          <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
        <?php endif; ?>
      </ul>
      <div class="actions">
        <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <?php if ($flash_msg): ?>
        <div class="alert <?php echo $flash_type === 'success' ? 'alert-success' : 'alert-error'; ?>"><?php echo h($flash_msg); ?></div>
      <?php endif; ?>

      <?php if ($rol === 'admin'): ?>
        <!-- =================== ADMIN: Validación de pagos =================== -->
        <h2 class="greet">Validación de pagos</h2>
        <div class="card">
          <div style="overflow:auto">
            <?php
            $sqlPend = "SELECT p.id_pago, p.comprobante, p.fecha_pago, p.validado,
                               i.id_inscripcion, i.estado, u.nombre AS usuario, u.email,
                               t.titulo AS taller
                        FROM pagos p
                        INNER JOIN inscripciones i ON i.id_inscripcion = p.id_inscripcion
                        INNER JOIN usuarios u ON u.id_usuario = i.id_usuario
                        INNER JOIN talleres t ON t.id_taller = i.id_taller
                        WHERE p.validado = 0
                        ORDER BY p.fecha_pago DESC";
            $pend = $conn->query($sqlPend);
            ?>
            <table class="table tbl tbl-pend">
              <thead>
                <tr>
                  <th class="col-user">Usuario</th>
                  <th class="col-email">Email</th>
                  <th class="col-title">Taller</th>
                  <th class="col-date">Enviado</th>
                  <th class="col-comp">Comprobante</th>
                  <th class="col-state">Estado</th>
                  <th class="col-act" style="text-align:right;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($pend && $pend->num_rows > 0): while ($p = $pend->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo h($p['usuario']); ?></td>
                    <td><?php echo h($p['email']); ?></td>
                    <td><div class="title-2l"><?php echo h($p['taller']); ?></div></td>
                    <td><?php echo h(date('d/m/Y H:i', strtotime($p['fecha_pago']))); ?></td>
                    <td>
                      <?php if (!empty($p['comprobante'])): ?>
                        <a class="btn outline" href="<?php echo 'uploads/comprobantes/' . rawurlencode($p['comprobante']); ?>" target="_blank">
                          <i class="fa-solid fa-file"></i> Ver
                        </a>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo estado_badge($p['estado']); ?></td>
                    <td style="text-align:right;">
                      <div class="row-actions">
                        <form action="validar_pago.php" method="POST">
                          <input type="hidden" name="id_pago" value="<?php echo (int)$p['id_pago']; ?>" />
                          <button class="btn" name="accion" value="aprobar" onclick="return confirm('¿Aprobar pago?');">
                            <i class="fa-solid fa-check"></i> Aprobar
                          </button>
                        </form>
                        <form action="validar_pago.php" method="POST">
                          <input type="hidden" name="id_pago" value="<?php echo (int)$p['id_pago']; ?>" />
                          <button class="btn danger" name="accion" value="rechazar" onclick="return confirm('¿Rechazar pago?');">
                            <i class="fa-solid fa-xmark"></i> Rechazar
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="7" style="padding:14px;text-align:center;">No hay pagos pendientes de validación</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php else: ?>
        <!-- =================== USUARIO: Subir comprobantes =================== -->
        <h2 class="greet">Subir comprobantes de pago</h2>
        <div class="card">
          <p class="muted" style="margin-top:0">Selecciona el taller y sube la imagen o PDF del comprobante. El equipo lo validará y te notificaremos.</p>
          <div style="overflow:auto">
            <?php
            $sqlMis = "SELECT i.id_inscripcion, i.estado, t.titulo, t.fecha, t.precio, t.qr_imagen,
                              (SELECT COUNT(*) FROM pagos p WHERE p.id_inscripcion = i.id_inscripcion AND p.validado = 0) AS pagos_pendientes
                       FROM inscripciones i
                       INNER JOIN talleres t ON t.id_taller = i.id_taller
                       WHERE i.id_usuario = ?
                       ORDER BY t.fecha DESC";
            $stmtMis = $conn->prepare($sqlMis);
            $stmtMis->bind_param("i", $id_user);
            $stmtMis->execute();
            $mis = $stmtMis->get_result();
            ?>
            <table class="table tbl tbl-mis">
              <thead>
                <tr>
                  <th class="col-title">Taller</th>
                  <th class="col-date">Fecha</th>
                  <th class="col-price">Precio (Bs)</th>
                  <th class="col-state">Estado</th>
                  <th class="col-act" style="text-align:right;">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($mis && $mis->num_rows > 0): while ($r = $mis->fetch_assoc()): $estado_row = strtolower((string)$r['estado']); ?>
                  <tr>
                    <td><div class="title-2l" title="<?php echo h($r['titulo']); ?>"><?php echo h($r['titulo']); ?></div></td>
                    <td><?php echo h(date('d/m/Y', strtotime($r['fecha']))); ?></td>
                    <td class="col-price"><?php echo number_format((float)$r['precio'], 2, '.', ''); ?></td>
                    <td><?php echo estado_badge($r['estado']); ?></td>
                    <td class="col-act" style="text-align:right;">
                      <?php $pend_count = isset($r['pagos_pendientes']) ? (int)$r['pagos_pendientes'] : 0; ?>
                      <?php if ($estado_row === 'pagado'): ?>
                        <span class="muted">Pago aprobado</span>
                      <?php elseif ($estado_row === 'pendiente' && $pend_count > 0): ?>
                        <span class="muted">Comprobante enviado — en revisión</span>
                      <?php else: ?>
                        <div class="actions-stack">

                          <?php $qr = trim((string)($r['qr_imagen'] ?? '')); if ($qr): ?>
                            <div class="qr-group">
                              <button type="button" class="qr-thumb-btn" data-src="<?php echo h($qr); ?>">
                                <img src="<?php echo h($qr); ?>" alt="QR de pago" class="qr-thumb"
                                     onerror="this.closest('.qr-thumb-btn').style.display='none'">
                              </button>
                              <button type="button" class="btn outline qr-open-btn" data-src="<?php echo h($qr); ?>">
                                <i class="fa-solid fa-magnifying-glass"></i> Ver QR
                              </button>
                            </div>
                          <?php endif; ?>

                          <div class="row-actions">
                            <form action="subir_comprobante.php" method="POST" enctype="multipart/form-data">
                              <input type="hidden" name="id_inscripcion" value="<?php echo (int)$r['id_inscripcion']; ?>" />
                              <input class="file-narrow" type="file" name="comprobante" accept="image/*,application/pdf" required />
                              <button class="btn" type="submit"><i class="fa-solid fa-upload"></i> Subir</button>
                            </form>
                          </div>

                          <?php if ($estado_row === 'rechazado'): ?>
                            <div class="note-rechazo">Pago rechazado, vuelve a subir un comprobante.</div>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="5" style="padding:14px;text-align:center;">No tienes inscripciones registradas</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <?php if (isset($stmtMis)) $stmtMis->close(); ?>
          </div>
        </div>
      <?php endif; ?>

      <p style="margin-top:16px;">
        <?php if ($rol === 'admin'): ?>
          <a class="btn" href="panel_admin.php">← Volver al panel</a>
        <?php else: ?>
          <a class="btn" href="panel_usuario.php">← Volver</a>
        <?php endif; ?>
      </p>
    </div>
  </main>

  <div id="img-modal"><img id="img-modal-image" src="" alt="Imagen ampliada"></div>
  <script>
    function openImgModal(src) {
      const m = document.getElementById('img-modal');
      const i = document.getElementById('img-modal-image');
      if (m && i) {
        i.src = src;
        m.style.display = 'flex';
      }
    }
    function closeImgModal() {
      const m = document.getElementById('img-modal');
      if (m) m.style.display = 'none';
    }
    (function() {
      const m = document.getElementById('img-modal');
      if (!m) return;
      m.addEventListener('click', e => { if (e.target === m) closeImgModal(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeImgModal(); });
    })();

    // Delegación: abre el modal si clickean la miniatura o el botón "Ver QR"
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.qr-thumb-btn, .qr-open-btn');
      if (!btn) return;
      const src = btn.getAttribute('data-src');
      if (src) openImgModal(src);
    });

    // Autocierre de alertas
    (function autoHideAlert() {
      const el = document.querySelector('.alert');
      if (!el) return;
      setTimeout(() => {
        el.classList.add('alert-hide');
        setTimeout(() => { try { el.remove(); } catch(_){} }, 400);
      }, 3000);
    })();
  </script>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
  </footer>
</body>
</html>