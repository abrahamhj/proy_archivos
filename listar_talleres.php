<?php
session_start();
include 'conexion.php';

$rol    = $_SESSION['rol']    ?? 'invitado';
$nombre = $_SESSION['nombre'] ?? '';

$mensaje = '';
$error   = '';

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function trunc($s, $l = 120)
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($s) > $l ? mb_substr($s, 0, $l) . '…' : $s;
    $s = (string)$s;
    return strlen($s) > $l ? substr($s, 0, $l) . '…' : $s;
}
function slugify($text)
{
    $text = trim((string)$text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = preg_replace('~^-+|-+$~', '', $text);
    $text = strtolower($text);
    return $text ?: 'taller';
}
function ensureDir($dir)
{
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

function saveImageFromUploadOrUrl(string $titulo, string $tipo, ?array $file, ?string $url, ?string $oldPath = null): array
{
    $slug = slugify($titulo);
    $baseDir = __DIR__ . '/uploads/talleres/' . ($tipo === 'qr' ? 'qr' : 'portada');
    ensureDir($baseDir);

    // Solo archivos subidos (sin URL)
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return [false, null, 'Error al subir el archivo.'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
        if ($finfo) finfo_close($finfo);
        if (!$mime || !isset($allowed[$mime])) return [false, null, 'Formato no permitido (jpg, png, webp, gif).'];
        if (($file['size'] ?? 0) > 3 * 1024 * 1024) return [false, null, 'La imagen supera 3MB.'];

        $ext   = $allowed[$mime];
        $stamp = date('Ymd-His');
        $name  = "{$slug}-{$tipo}-{$stamp}.{$ext}";
        $dest  = $baseDir . '/' . $name;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) return [false, null, 'No se pudo guardar la imagen.'];

        if ($oldPath && str_starts_with($oldPath, 'uploads/talleres/') && file_exists(__DIR__ . '/' . $oldPath)) {
            @unlink(__DIR__ . '/' . $oldPath);
        }
        return [true, 'uploads/talleres/' . ($tipo === 'qr' ? 'qr' : 'portada') . '/' . $name, null];
    }

    // Sin archivo: se mantiene el path anterior
    return [false, $oldPath, null];
}

if (isset($_SESSION['id_usuario']) && $rol === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $titulo      = trim($_POST['titulo'] ?? '');
        $fecha       = trim($_POST['fecha'] ?? '');
        $precio      = $_POST['precio'] ?? '';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $lugar       = trim($_POST['lugar'] ?? '');
        $hora        = trim($_POST['hora'] ?? '');
        $duracion    = trim($_POST['duracion'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? '');
        $estado      = in_array(($_POST['estado'] ?? 'activo'), ['activo', 'inactivo'], true) ? $_POST['estado'] : 'activo';
        $qr_pay_ref  = trim($_POST['qr_pay_ref'] ?? '');

        $imgActual   = trim($_POST['imagen_actual'] ?? '');
        $qrActual    = trim($_POST['qr_imagen_actual'] ?? '');

        if ($id > 0 && $titulo && $fecha && is_numeric($precio)) {
            $p = (float)$precio;

            // Portada
            [$okImg, $imgPath, $errImg] = saveImageFromUploadOrUrl(
                $titulo,
                'portada',
                $_FILES['imagen'] ?? null,
                null,
                $imgActual ?: null
            );
            if ($errImg) $error = $errImg;

            // QR pago
            [$okQr, $qrPath, $errQr] = saveImageFromUploadOrUrl(
                $titulo,
                'qr',
                $_FILES['qr_imagen'] ?? null,
                null,
                $qrActual ?: null
            );
            if ($errQr) $error = $errQr;

            if (!$error) {
                $stmt = $conn->prepare('UPDATE talleres SET
            titulo=?, descripcion=?, imagen=?, precio=?, fecha=?, lugar=?, hora=?, duracion=?, categoria=?, estado=?, qr_imagen=?, qr_pay_ref=?
          WHERE id_taller=?');
                $stmt->bind_param(
                    'sssdssssssssi',
                    $titulo,
                    $descripcion,
                    $imgPath,
                    $p,
                    $fecha,
                    $lugar,
                    $hora,
                    $duracion,
                    $categoria,
                    $estado,
                    $qrPath,
                    $qr_pay_ref,
                    $id
                );

                if ($stmt->execute()) {
                    $mensaje = 'Taller actualizado.';
                    $stmt->close();
                    header('Location: listar_talleres.php?msg=' . urlencode($mensaje));
                    exit;
                } else {
                    $error = 'No se pudo actualizar.';
                    $stmt->close();
                }
            }
        } else {
            $error = 'Datos inválidos.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Borrar imágenes locales si existen
            $q = $conn->prepare('SELECT imagen, qr_imagen FROM talleres WHERE id_taller=?');
            $q->bind_param('i', $id);
            $q->execute();
            $rs = $q->get_result();
            $row = $rs ? $rs->fetch_assoc() : null;
            $q->close();
            foreach (['imagen', 'qr_imagen'] as $col) {
                if ($row && $row[$col] && str_starts_with($row[$col], 'uploads/talleres/') && file_exists(__DIR__ . '/' . $row[$col])) {
                    @unlink(__DIR__ . '/' . $row[$col]);
                }
            }

            $stmt = $conn->prepare('DELETE FROM talleres WHERE id_taller=?');
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $mensaje = 'Taller eliminado.';
                $stmt->close();
                header('Location: listar_talleres.php?msg=' . urlencode($mensaje));
                exit;
            } else {
                $error = 'No se pudo eliminar.';
                $stmt->close();
            }
        }
    }
}

if (isset($_GET['msg'])) $mensaje = h($_GET['msg']);

/* ================== Búsqueda + orden ================== */
$q = trim((string)($_GET['q'] ?? ''));
$sort = $_GET['sort'] ?? 'fecha_desc';
$validSort = [
    'fecha_desc' => 'fecha DESC',
    'fecha_asc'  => 'fecha ASC',
    'precio_desc' => 'precio DESC',
    'precio_asc' => 'precio ASC',
    'titulo_asc' => 'titulo ASC'
];
$orderBy = $validSort[$sort] ?? $validSort['fecha_desc'];

$talleres = [];
$total = 0;

// Filtrado: solo usuarios ven activos; admin ve todos
$onlyActive = ($rol === 'usuario');

if ($q !== '') {
    $like = "%$q%";
    $sqlC = 'SELECT COUNT(*) AS c FROM talleres WHERE (titulo LIKE ? OR descripcion LIKE ?)' . ($onlyActive ? " AND estado='activo'" : '');
    $stmtC = $conn->prepare($sqlC);
    $stmtC->bind_param('ss', $like, $like);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($resC && ($rowC = $resC->fetch_assoc())) $total = (int)$rowC['c'];
    $stmtC->close();

    $sql = "SELECT id_taller, titulo, descripcion, fecha, precio, lugar, hora, duracion, categoria, estado, imagen, qr_imagen, qr_pay_ref
          FROM talleres WHERE (titulo LIKE ? OR descripcion LIKE ?)" . ($onlyActive ? " AND estado='activo'" : '') . " ORDER BY $orderBy";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $sqlCount = 'SELECT COUNT(*) AS c FROM talleres' . ($onlyActive ? " WHERE estado='activo'" : '');
    $resC = $conn->query($sqlCount);
    if ($resC && ($rowC = $resC->fetch_assoc())) $total = (int)$rowC['c'];
    $sql = "SELECT id_taller, titulo, descripcion, fecha, precio, lugar, hora, duracion, categoria, estado, imagen, qr_imagen, qr_pay_ref
          FROM talleres " . ($onlyActive ? "WHERE estado='activo' " : '') . "ORDER BY $orderBy";
    $res = $conn->query($sql);
}
if ($res) {
    while ($row = $res->fetch_assoc()) $talleres[] = $row;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Talleres</title>
    <link rel="stylesheet" href="estilos/index.css" />
    <link rel="stylesheet" href="estilos/menu.css" />
    <link rel="stylesheet" href="estilos/panel_usuario.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="estilos/usuarios.css" />
    <link rel="stylesheet" href="estilos/talleres.css" />
    <style>
        .table.talleres {
            table-layout: fixed;
        }

        .table.talleres .col-img {
            width: 110px;
        }

        .table.talleres .col-date {
            width: 120px;
        }

        .table.talleres .col-price {
            width: 130px;
        }

        .table.talleres .col-qr {
            width: 160px;
        }

        .table.talleres .col-actions {
            width: 220px;
        }

        .td-details {
            overflow: hidden;
            word-break: break-word;
        }

        .td-details .desc {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }

        @supports not (-webkit-line-clamp: 1) {
            .td-details .desc {
                display: block;
                line-height: 1.4;
                max-height: calc(1.4em * 3);
                overflow: hidden;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="navbar">
            <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia"></a></div>

            <?php if ($rol === 'admin'): ?>
                <ul class="menu" id="menu">
                    <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
                    <li><a href="listar_talleres.php" class="active"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
                    <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
                    <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
                    <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
                </ul>
            <?php else: ?>
                <ul class="menu" id="menu">
                    <li><a href="index.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
                    <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
                    <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Mis pagos</a></li>
                    <li><a class="active" href="mis_talleres.php"><i class="fa-solid fa-list-check"></i> Mis talleres</a></li>
                    <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
                </ul>
            <?php endif; ?>

            <div class="actions">
                <?php if (isset($_SESSION['id_usuario'])): ?>
                    <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
                <?php else: ?>
                    <a class="btn" href="login.php">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h2 class="greet">Lista de Talleres</h2>

            <?php if ($mensaje): ?><div class="alert ok" id="alert-ok"><?php echo $mensaje; ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert err" id="alert-err"><?php echo h($error); ?></div><?php endif; ?>

            <!-- Toolbar -->
            <section class="card">
                <div class="toolbar">
                    <form method="get" style="display:flex; gap:8px; flex:1;">
                        <input type="text" name="q" placeholder="Buscar por título o descripción" value="<?php echo h($q); ?>" style="flex:1;" />
                        <button class="btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                        <?php if ($q !== '' || ($sort && $sort !== 'fecha_desc')): ?><a class="btn outline" href="listar_talleres.php">Limpiar</a><?php endif; ?>
                    </form>
                    <?php if ($rol === 'admin'): ?>
                        <a class="btn" href="crear_taller.php"><i class="fa-solid fa-plus"></i> Crear Taller</a>
                    <?php endif; ?>
                </div>
                <div class="muted" style="margin-top:6px;">Total: <?php echo (int)$total; ?> talleres</div>
            </section>

            <div class="grid-main">
                <section class="card">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <h3 style="margin:0;">Talleres</h3>
                        <a class="btn outline" href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>
                    </div>
                    <div style="overflow:auto;">
                        <table class="table talleres">
                            <thead>
                                <tr>
                                    <th class="col-img">Portada</th>
                                    <th>Título y detalles</th>
                                    <th class="col-date">Fecha / Hora</th>
                                    <th class="col-price">Precio (Bs)</th>
                                    <th class="col-qr">Pago por QR</th>
                                    <th class="col-actions">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$talleres): ?>
                                    <tr>
                                        <td colspan="6" class="muted">No hay talleres registrados.</td>
                                    </tr>
                                    <?php else: foreach ($talleres as $t): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($t['imagen'])): ?>
                                                    <img class="thumb-lg" src="<?php echo h($t['imagen']); ?>" alt="Portada" onerror="this.src='img/placeholder.png'">
                                                <?php else: ?>
                                                    <img class="thumb-lg" src="img/placeholder.png" alt="Sin imagen">
                                                <?php endif; ?>
                                            </td>
                                            <td class="td-details">
                                                <strong><?php echo h($t['titulo']); ?></strong><br>
                                                <small class="muted"><?php
                                                                        $p = [];
                                                                        if (!empty($t['categoria'])) $p[] = 'Categoría: ' . h($t['categoria']);
                                                                        if (!empty($t['duracion']))  $p[] = 'Duración: ' . h($t['duracion']);
                                                                        echo implode(' · ', $p);
                                                                        ?></small>
                                                <div class="muted desc"><?php echo h(trunc($t['descripcion'] ?? '', 120)); ?></div>
                                                <?php if (!empty($t['lugar'])): ?><div><small><i class="fa-solid fa-location-dot"></i> <?php echo h($t['lugar']); ?></small></div><?php endif; ?>
                                                <?php
                                                $estado = strtolower($t['estado'] ?? '');
                                                if ($estado) {
                                                    $cls = $estado === 'activo' ? 'ok' : 'bad';
                                                    echo '<div style="margin-top:6px;"><span class="pill ' . $cls . '">' . h(ucfirst($estado)) . '</span></div>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo h(date('d/m/Y', strtotime($t['fecha']))); ?>
                                                <?php if (!empty($t['hora'])): ?><br><small><?php echo h(substr($t['hora'], 0, 5)); ?></small><?php endif; ?>
                                            </td>
                                            <td><?php echo number_format((float)$t['precio'], 2, '.', ','); ?></td>
                                            <td>
                                                <?php if (!empty($t['qr_imagen'])): ?>
                                                    <img class="thumb" src="<?php echo h($t['qr_imagen']); ?>" alt="QR" onerror="this.src='img/placeholder.png'"><br>
                                                <?php endif; ?>
                                                <?php if (!empty($t['qr_pay_ref'])): ?><small class="muted"><?php echo h($t['qr_pay_ref']); ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rol === 'admin'): ?>
                                                    <button type="button" class="btn edit-btn"
                                                        data-id="<?php echo (int)$t['id_taller']; ?>"
                                                        data-titulo="<?php echo h($t['titulo']); ?>"
                                                        data-descripcion="<?php echo h($t['descripcion'] ?? ''); ?>"
                                                        data-fecha="<?php echo h($t['fecha']); ?>"
                                                        data-precio="<?php echo (float)$t['precio']; ?>"
                                                        data-lugar="<?php echo h($t['lugar'] ?? ''); ?>"
                                                        data-hora="<?php echo h($t['hora'] ?? ''); ?>"
                                                        data-duracion="<?php echo h($t['duracion'] ?? ''); ?>"
                                                        data-categoria="<?php echo h($t['categoria'] ?? ''); ?>"
                                                        data-estado="<?php echo h($t['estado'] ?? 'activo'); ?>"
                                                        data-img="<?php echo h($t['imagen'] ?? ''); ?>"
                                                        data-qrimg="<?php echo h($t['qr_imagen'] ?? ''); ?>"
                                                        data-qrref="<?php echo h($t['qr_pay_ref'] ?? ''); ?>">
                                                        <i class="fa-solid fa-pen"></i> Editar
                                                    </button>
                                                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este taller?');">
                                                        <input type="hidden" name="action" value="delete" />
                                                        <input type="hidden" name="id" value="<?php echo (int)$t['id_taller']; ?>" />
                                                        <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                                    </form>
                                                <?php elseif ($rol === 'usuario'): ?>
                                                    <a class="btn" href="ver_taller.php?id=<?php echo (int)$t['id_taller']; ?>"><i class="fa-solid fa-user-check"></i> Inscribirse</a>
                                                <?php else: ?>
                                                    <a class="btn outline" href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Inicia sesión</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Editar (modal) -->
            <?php if ($rol === 'admin'): ?>
                <div id="modalEdit" class="modal-backdrop" aria-hidden="true">
                    <div class="modal-card">
                        <div class="modal-header">
                            <h3 style="margin:0">Editar taller</h3>
                            <button id="closeModal" class="btn outline" type="button"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <form id="formEdit" method="post" class="form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update" />
                            <input type="hidden" name="id" id="edit_id" />
                            <input type="hidden" name="imagen_actual" id="edit_img_actual" />
                            <input type="hidden" name="qr_imagen_actual" id="edit_qr_actual" />

                            <div class="row">
                                <div><label>Título</label><input type="text" name="titulo" id="edit_titulo" required /></div>
                                <div><label>Fecha</label><input type="date" name="fecha" id="edit_fecha" required /></div>
                            </div>
                            <div class="row">
                                <div><label>Hora</label><input type="time" name="hora" id="edit_hora" /></div>
                                <div><label>Precio (Bs)</label><input type="number" step="0.01" min="0" name="precio" id="edit_precio" required /></div>
                            </div>
                            <div class="row">
                                <div><label>Lugar</label><input type="text" name="lugar" id="edit_lugar" /></div>
                                <div><label>Duración</label><input type="text" name="duracion" id="edit_duracion" /></div>
                            </div>
                            <div class="row">
                                <div><label>Categoría</label><input type="text" name="categoria" id="edit_categoria" /></div>
                                <div>
                                    <label>Estado</label>
                                    <select name="estado" id="edit_estado">
                                        <option value="activo">Activo</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div>
                                    <div class="flex">
                                        <div>
                                            <label>Portada </label><br>
                                            <input type="file" name="imagen" accept="image/*" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div>
                                    <div class="flex">
                                        <div>
                                            <label>QR de pago </label><br>
                                            <input type="file" name="qr_imagen" accept="image/*" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div><label>Referencia de pago</label><input type="text" name="qr_pay_ref" id="edit_qr_ref" /></div>
                                <div></div>
                            </div>

                            <div>
                                <label>Descripción</label>
                                <textarea name="descripcion" id="edit_descripcion" rows="4"></textarea>
                            </div>

                            <div class="actions" style="margin-top:8px">
                                <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
                                <button class="btn outline" type="button" id="cancelModal"><i class="fa-solid fa-xmark"></i> Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
    </footer>

    <?php if ($rol === 'admin'): ?>
        <script>
            (function() {
                const ok = document.getElementById('alert-ok');
                const err = document.getElementById('alert-err');
                if (ok) setTimeout(() => {
                    setTimeout(() => ok.remove(), 600);
                }, 3000);
                if (err) setTimeout(() => {
                    setTimeout(() => err.remove(), 600);
                }, 4000);
                if (location.search.indexOf('msg=') > -1) {
                    history.replaceState(null, '', location.pathname + location.hash);
                }
            })();

            // Modal
            const modal = document.getElementById('modalEdit');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelModal');
            const imgPrev = document.getElementById('edit_img_preview');
            const qrPrev = document.getElementById('edit_qr_preview');

            function openModal() {
                modal.classList.add('show');
            }

            function closeModal() {
                modal.classList.remove('show');
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (modal) modal.addEventListener('click', e => {
                if (e.target === modal) closeModal();
            });

            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const get = a => btn.dataset[a] || '';
                    document.getElementById('edit_id').value = get('id');
                    document.getElementById('edit_titulo').value = get('titulo');
                    document.getElementById('edit_descripcion').value = get('descripcion');
                    document.getElementById('edit_fecha').value = get('fecha');
                    document.getElementById('edit_precio').value = get('precio');
                    document.getElementById('edit_lugar').value = get('lugar');
                    document.getElementById('edit_hora').value = get('hora');
                    document.getElementById('edit_duracion').value = get('duracion');
                    document.getElementById('edit_categoria').value = get('categoria');
                    document.getElementById('edit_estado').value = get('estado') || 'activo';
                    document.getElementById('edit_img_actual').value = get('img');
                    document.getElementById('edit_qr_actual').value = get('qrimg');
                    document.getElementById('edit_qr_ref').value = get('qrref');
                    openModal();
                });
            });

            document.querySelectorAll('input[type="number"][name="precio"]').forEach(el => {
                el.addEventListener('input', () => {
                    if (el.value < 0) el.value = 0;
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>