<?php
session_start();
include "conexion.php";

// Solo usuarios registrados
if (!isset($_SESSION['rol']) || (($_SESSION['rol'] ?? '') !== 'usuario' && ($_SESSION['rol'] ?? '') !== 'admin')) {
  header("Location: login.php");
  exit();
}
$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'] ?? '';

// Asegurar tabla
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
// Nueva columna opcional
$conn->query("ALTER TABLE archivos ADD COLUMN IF NOT EXISTS nombre_original VARCHAR(255) NULL AFTER nombre;");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$files = $conn->query("SELECT * FROM archivos ORDER BY fecha_subida DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Archivos publicados</title>
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
        <?php if ($rol === 'admin'): ?>
          <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
          <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
          <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
          <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
          <li><a class="active" href="listar_archivos.php"><i class="fa-solid fa-file"></i> Archivos</a></li>
        <?php else: ?>
          <li><a href="panel_usuario.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
          <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
          <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Mis pagos</a></li>
          <li><a class="active" href="listar_archivos.php"><i class="fa-solid fa-file"></i> Archivos</a></li>
          <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
        <?php endif; ?>
      </ul>
      <div class="actions">
        <?php if ($rol === 'admin'): ?><a class="btn" href="archivos.php"><i class="fa-solid fa-upload"></i> Publicar</a><?php endif; ?>
        <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      </div>
    </div>
  </header>

  <main>
    <div class="wrap">
      <h2 class="greet">Archivos publicados</h2>

      <?php if (isset($_GET['msg']) && $_GET['msg']==='deleted'): ?>
        <div class="alert alert-success">Archivo eliminado.</div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-error">Ocurrió un error con la operación solicitada.</div>
      <?php endif; ?>

      <div class="files-grid">
        <?php if ($files && $files->num_rows > 0): while ($f = $files->fetch_assoc()): ?>
          <article class="file-card">
            <div class="file-media">
              <?php if ($f['tipo'] === 'imagen'): ?>
                <img src="<?php echo h($f['ruta']); ?>" alt="<?php echo h($f['nombre']); ?>" />
              <?php elseif ($f['tipo'] === 'video'): ?>
                <video controls>
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
            </div>
            <div class="file-body">
              <div class="file-title" title="<?php echo h($f['nombre']); ?>"><?php echo h($f['nombre']); ?></div>
              <?php if (!empty($f['descripcion'])): ?><div class="file-desc"><?php echo h($f['descripcion']); ?></div><?php endif; ?>
              <div style="display:flex; align-items:center; justify-content:space-between;">
                <small class="muted"><?php echo ucfirst(h($f['tipo'])); ?> · <?php echo number_format((float)($f['peso'] ?? 0)/1024, 0, '.', ''); ?> KB</small>
                <div class="row-actions">
                  <?php if ($f['tipo'] === 'imagen' || $f['tipo'] === 'video'): ?>
                    <a class="btn outline" href="<?php echo h($f['ruta']); ?>" download><i class="fa-solid fa-download"></i> Descargar</a>
                  <?php else: ?>
                    <?php if (strtolower(pathinfo($f['ruta'], PATHINFO_EXTENSION))==='pdf'): ?>
                      <a class="btn" target="_blank" href="<?php echo h($f['ruta']); ?>"><i class="fa-solid fa-up-right-from-square"></i> Abrir</a>
                    <?php endif; ?>
                    <a class="btn outline" href="<?php echo h($f['ruta']); ?>" download><i class="fa-solid fa-download"></i> Descargar</a>
                  <?php endif; ?>
                  <?php if ($rol==='admin'): ?>
                    <form action="eliminar_archivo.php" method="POST" onsubmit="return confirm('¿Eliminar archivo?');">
                      <input type="hidden" name="id" value="<?php echo (int)$f['id_archivo']; ?>" />
                      <button class="btn danger" type="submit"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endwhile; else: ?>
          <p class="muted">Aún no hay archivos publicados.</p>
        <?php endif; ?>
      </div>

      <p style="margin-top:16px;">
        <?php if ($rol === 'admin'): ?>
          <a class="btn" href="panel_admin.php">← Volver al panel</a>
        <?php else: ?>
          <a class="btn" href="panel_usuario.php">← Volver</a>
        <?php endif; ?>
      </p>
    </div>
  </main>

  <footer>
    <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
  </footer>
</body>
</html>
