<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario'])) {
  header("Location: login.php");
  exit();
}

$rol = $_SESSION['rol'] ?? 'invitado';
if ($rol !== 'admin') {
  header("Location: listar_archivos.php");
  exit();
}

$nombre_admin = $_SESSION['nombre'] ?? 'Administrador';

// Crear/alterar tabla si no existe y agregar columna nueva
$conn->query("CREATE TABLE IF NOT EXISTS archivos (
  id_archivo INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  tipo VARCHAR(20) NOT NULL,
  ruta VARCHAR(512) NOT NULL,
  mime VARCHAR(100) NULL,
  extension VARCHAR(10) NULL,
  peso INT NULL,
  id_usuario INT NULL,
  fecha_subida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
// Nueva columna para guardar el nombre original del archivo
$conn->query("ALTER TABLE archivos ADD COLUMN IF NOT EXISTS nombre_original VARCHAR(255) NULL AFTER nombre;");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'subir') {
  $titulo = trim($_POST['titulo'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');

  if ($titulo === '') {
    $flash = 'El título es obligatorio.'; $flash_type = 'error';
  } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $flash = 'Debes seleccionar un archivo válido.'; $flash_type = 'error';
  } else {
    $tmp = $_FILES['archivo']['tmp_name'];
    $orig = $_FILES['archivo']['name'];
    $size = (int)$_FILES['archivo']['size'];
    $mime = mime_content_type($tmp);
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    // Clasificación y validación de tipos
    $tipo = '';
    $allowed = [
      'imagen' => [
        'mimes' => ['image/jpeg','image/png','image/webp','image/gif'],
        'exts'  => ['jpg','jpeg','png','webp','gif']
      ],
      'video' => [
        'mimes' => ['video/mp4','video/webm','video/ogg'],
        'exts'  => ['mp4','webm','ogg']
      ],
      'documento' => [
        'mimes' => [
          'application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
          'text/plain','application/zip','application/x-zip-compressed'
        ],
        'exts'  => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip']
      ]
    ];

    foreach ($allowed as $k => $rule) {
      if (in_array($mime, $rule['mimes'], true) || in_array($ext, $rule['exts'], true)) { $tipo = $k; break; }
    }

    if ($tipo === '') {
      $flash = 'Tipo de archivo no permitido.'; $flash_type = 'error';
    } elseif ($size <= 0 || $size > 50 * 1024 * 1024) {
      $flash = 'El archivo excede el tamaño máximo (50MB).'; $flash_type = 'error';
    } else {
      $baseDir = __DIR__ . '/uploads/archivos';
      $sub = $tipo === 'imagen' ? 'imagenes' : ($tipo === 'video' ? 'videos' : 'documentos');
      $dir = $baseDir . '/' . $sub;
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

      $safeTitle = @iconv('UTF-8','ASCII//TRANSLIT', $titulo);
      $safeTitle = preg_replace('/[^a-zA-Z0-9-_]+/','-', strtolower($safeTitle !== false ? $safeTitle : $titulo));
      if ($safeTitle === '') { $safeTitle = 'archivo'; }
      $newName = $safeTitle . '-' . date('YmdHis') . '.' . $ext;
      $destAbs = $dir . '/' . $newName;
      $destRel = 'uploads/archivos/' . $sub . '/' . $newName;

      if (!move_uploaded_file($tmp, $destAbs)) {
        $flash = 'No se pudo guardar el archivo.'; $flash_type = 'error';
      } else {
        // Insert con nombre_original
        $stmt = $conn->prepare("INSERT INTO archivos (nombre, nombre_original, descripcion, tipo, ruta, mime, extension, peso, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $uid = (int)$_SESSION['id_usuario'];
        $stmt->bind_param('sssssssii', $titulo, $orig, $descripcion, $tipo, $destRel, $mime, $ext, $size, $uid);
        if ($stmt->execute()) {
          $flash = 'Archivo publicado correctamente.'; $flash_type = 'success';
        } else {
          @unlink($destAbs);
          $flash = 'Error al registrar en base de datos.'; $flash_type = 'error';
        }
      }
    }
  }
}

// Obtener lista de archivos
$files = $conn->query("SELECT * FROM archivos ORDER BY fecha_subida DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Archivos · Administración</title>
  <link rel="stylesheet" href="estilos/index.css" />
  <link rel="stylesheet" href="estilos/menu.css" />
  <link rel="stylesheet" href="estilos/panel_usuario.css" />
  <link rel="stylesheet" href="estilos/archivos.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <header>
    <div class="navbar">
      <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo"></a></div>
      <ul class="menu" id="menu">
        <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
        <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
        <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
        <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
        <li><a class="active" href="archivos.php"><i class="fa-solid fa-file"></i> Archivos</a></li>
        <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
      </ul>
      <div class="actions"><a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <h2 class="greet">Publicar archivos</h2>

      <?php if ($flash): ?>
        <div class="alert <?php echo $flash_type==='success'?'alert-success':'alert-error'; ?>"><?php echo h($flash); ?></div>
      <?php endif; ?>

      <div class="grid-2">
        <div class="card">
          <h3>Subir nuevo</h3>
          <form method="POST" enctype="multipart/form-data" class="form">
            <input type="hidden" name="accion" value="subir"/>
            <div class="row">
              <label style="flex:1 1 280px;">
                <span>Título</span>
                <input type="text" name="titulo" required />
              </label>
              <label style="flex:1 1 280px;">
                <span>Archivo</span>
                <input type="file" name="archivo" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip" required />
              </label>
            </div>
            <label>
              <span>Descripción (opcional)</span>
              <textarea name="descripcion" rows="3" placeholder="Breve descripción"></textarea>
            </label>
            <p class="muted">Formatos permitidos: imágenes (jpg, png, webp, gif), videos (mp4, webm, ogg), documentos (pdf, docx, xlsx, pptx, txt, zip). Máx. 50MB.</p>
            <button class="btn" type="submit"><i class="fa-solid fa-upload"></i> Publicar</button>
          </form>
        </div>

        <div class="card">
          <h3>Archivos publicados</h3>
          <div class="table-scroll">
            <table class="tbl">
              <thead>
                <tr>
                  <th class="col-nombre">Nombre</th>
                  <th class="col-prev">Vista previa</th>
                  <th class="col-tipo">Tipo</th>
                  <th class="col-peso">Tamaño</th>
                  <th class="col-fecha">Fecha</th>
                  <th class="col-acc">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($files && $files->num_rows > 0): while ($f = $files->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;" title="<?php echo h($f['nombre']); ?>"><?php echo h($f['nombre']); ?></div>
                    <?php if (!empty($f['descripcion'])): ?><div class="muted truncate-2"><?php echo h($f['descripcion']); ?></div><?php endif; ?>
                  </td>
                  <td class="col-prev">
                    <?php if ($f['tipo']==='imagen'): ?>
                      <img class="preview" src="<?php echo h($f['ruta']); ?>" alt="preview" />
                    <?php elseif ($f['tipo']==='video'): ?>
                      <video class="preview" controls>
                        <source src="<?php echo h($f['ruta']); ?>" type="<?php echo h($f['mime'] ?? 'video/mp4'); ?>" />
                        Tu navegador no soporta video.
                      </video>
                    <?php else: ?>
                      <?php if (strtolower(pathinfo($f['ruta'], PATHINFO_EXTENSION))==='pdf'): ?>
                        <a class="btn outline" target="_blank" href="<?php echo h($f['ruta']); ?>"><i class="fa-solid fa-file-pdf"></i> Ver PDF</a>
                      <?php else: ?>
                        <a class="btn outline" href="<?php echo h($f['ruta']); ?>" download><i class="fa-solid fa-download"></i> Descargar</a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td style="text-transform:capitalize; "><?php echo h($f['tipo']); ?></td>
                  <td style="text-align:right; "><?php echo number_format((float)($f['peso'] ?? 0)/1024, 0, '.', ''); ?> KB</td>
                  <td><?php echo h(date('d/m/Y H:i', strtotime($f['fecha_subida']))); ?></td>
                  <td style="text-align:right; ">
                    <div class="row-actions">
                      <a class="btn outline" href="<?php echo h($f['ruta']); ?>" download><i class="fa-solid fa-download"></i></a>
                      <form action="eliminar_archivo.php" method="POST" onsubmit="return confirm('¿Eliminar archivo?');">
                        <input type="hidden" name="id" value="<?php echo (int)$f['id_archivo']; ?>" />
                        <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="6" style="text-align:center; padding:12px;">Aún no hay archivos publicados</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
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