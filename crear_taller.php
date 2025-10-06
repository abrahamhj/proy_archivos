<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$error   = '';

/* ================= Helpers ================= */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function slugify($text)
{
    $text = trim((string)$text);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($t !== false) $text = $t;
    }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = preg_replace('~^-+|-+$~', '', $text);
    $text = strtolower($text);
    return $text ?: 'taller';
}

function ensureDirWritable($dir, &$err)
{
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            $err = "No se pudo crear la carpeta: $dir";
            return false;
        }
    }
    if (!is_writable($dir)) {
        $err = "La carpeta no es escribible: $dir";
        return false;
    }
    return true;
}

function saveImageFromUpload(string $titulo, string $tipo, ?array $file): array
{
    $slug = slugify($titulo);
    $sub  = ($tipo === 'qr') ? 'qr' : 'portada';
    $baseDir = __DIR__ . "/uploads/talleres/$sub";

    $err = null;
    if (!ensureDirWritable($baseDir, $err)) {
        return [false, '', $err];
    }

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, '', null]; 
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, '', 'Error al subir el archivo (código ' . $file['error'] . ').'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, '', 'El archivo temporal no es válido (is_uploaded_file falló).'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) finfo_close($finfo);

    // Detecta extensión
    $ext = null;
    if ($mime && isset($allowed[$mime])) {
        $ext = $allowed[$mime];
    } else {
        $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = $origExt === 'jpeg' ? 'jpg' : $origExt;
        }
    }
    if (!$ext) return [false, '', 'Formato no permitido (jpg, png, webp, gif).'];

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return [false, '', 'La imagen supera 3MB.'];
    }

    $stamp = date('Ymd-His');
    $name  = "{$slug}-{$tipo}-{$stamp}.{$ext}";
    $dest  = $baseDir . '/' . $name;

    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        $lastErr = error_get_last();
        $extra   = $lastErr ? (' · ' . $lastErr['message']) : '';
        return [false, '', "No se pudo guardar la imagen en $dest$extra"];
    }
    @chmod($dest, 0644);

    return [true, "uploads/talleres/$sub/$name", null];
}

/* ================== Crear ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if ($titulo && $fecha && is_numeric($precio)) {
        // Portada (SOLO archivo)
        [$okImg, $imgPath, $errImg] = saveImageFromUpload(
            $titulo,
            'portada',
            $_FILES['imagen'] ?? null
        );
        if ($errImg) $error = $errImg;

        // QR pago (SOLO archivo)
        [$okQr, $qrPath, $errQr] = saveImageFromUpload(
            $titulo,
            'qr',
            $_FILES['qr_imagen'] ?? null
        );
        if ($errQr && !$error) $error = $errQr;

        if (!$error) {
            $p = (float)$precio;
            $imgPath = $imgPath ?: '';
            $qrPath  = $qrPath  ?: '';

            $stmt = $conn->prepare(
                'INSERT INTO talleres (titulo, descripcion, imagen, precio, fecha, lugar, hora, duracion, categoria, estado, qr_imagen, qr_pay_ref)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            if (!$stmt) {
                $error = 'Error de preparación de consulta.';
            } else {
                $stmt->bind_param(
                    'sssdssssssss',
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
                    $qr_pay_ref
                );
                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: listar_talleres.php?msg=' . urlencode('Taller creado.'));
                    exit;
                } else {
                    $error = 'No se pudo crear el taller.';
                    $stmt->close();
                }
            }
        }
    } else {
        $error = 'Complete Título, Fecha y Precio válido.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Crear Taller</title>
    <link rel="stylesheet" href="estilos/index.css" />
    <link rel="stylesheet" href="estilos/menu.css" />
    <link rel="stylesheet" href="estilos/panel_usuario.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px
        }

        @media (max-width:800px) {
            .row {
                grid-template-columns: 1fr
            }
        }

        input[type="text"],
        input[type="url"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 8px 10px;
            font-size: 15px;
            border: 1px solid #dbe4ea;
            border-radius: 10px;
            box-sizing: border-box;
            transition: border-color .2s, box-shadow .2s;
            background: #fff
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .2)
        }

        .alert {
            border-radius: 10px;
            padding: 10px 12px
        }

        .err {
            background: #fdecea;
            border: 1px solid #f5c2c7;
            color: #a4282f
        }

        .ok {
            background: #e9f8ef;
            border: 1px solid #bcdcc2;
            color: #157347
        }

        .preview {
            display: flex;
            gap: 10px;
            align-items: center
        }

        .thumb {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f8fafc
        }
    </style>
</head>

<body>
    <header>
        <div class="navbar">
            <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo Salvemos los Archivos Bolivia"></a></div>
            <ul class="menu" id="menu">
                <li><a href="panel_admin.php"><i class="fa-solid fa-gauge"></i> Panel</a></li>
                <li><a href="listar_talleres.php" class="active"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
                <li><a href="listar_inscripciones.php"><i class="fa-solid fa-clipboard-list"></i> Inscripciones</a></li>
                <li><a href="listar_pagados.php"><i class="fa-solid fa-cash-register"></i> Pagos</a></li>
                <li><a href="notificaciones_admin.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
            </ul>
            <div class="actions">
                <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h2 class="greet">Crear Taller</h2>

            <?php if ($error): ?><div class="alert err" id="alert-err"><?php echo h($error); ?></div><?php endif; ?>

            <section class="card">
                <form method="post" enctype="multipart/form-data" class="form" onsubmit="return validarPrecio();">
                    <div class="row">
                        <div><label>Título</label><input type="text" name="titulo" required /></div>
                        <div><label>Fecha</label><input type="date" name="fecha" required /></div>
                    </div>
                    <div class="row">
                        <div><label>Hora</label><input type="time" name="hora" /></div>
                        <div><label>Precio (Bs)</label><input type="number" step="0.01" min="0" name="precio" required /></div>
                    </div>
                    <div class="row">
                        <div><label>Lugar</label><input type="text" name="lugar" /></div>
                        <div><label>Duración</label><input type="text" name="duracion" /></div>
                    </div>
                    <div class="row">
                        <div><label>Categoría</label><input type="text" name="categoria" /></div>
                        <div>
                            <label>Estado</label>
                            <select name="estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Portada (archivo)</label>
                            <input type="file" name="imagen" accept="image/*" />
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>QR de pago (archivo)</label>
                            <input type="file" name="qr_imagen" accept="image/*" />
                        </div>
                    </div>

                    <div class="row">
                        <div><label>Referencia de pago</label><input type="text" name="qr_pay_ref" /></div>
                        <div></div>
                    </div>

                    <div>
                        <label>Descripción</label>
                        <textarea name="descripcion" rows="4"></textarea>
                    </div>

                    <div class="actions" style="margin-top:8px">
                        <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Crear</button>
                        <a class="btn outline" href="listar_talleres.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
    </footer>

    <script>
        function validarPrecio() {
            const el = document.querySelector('input[name="precio"]');
            if (el && Number(el.value) < 0) el.value = 0;
            return true;
        }
    </script>
</body>

</html>