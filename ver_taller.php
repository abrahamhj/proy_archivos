<?php
session_start();
include "conexion.php";

$rol = $_SESSION['rol'] ?? 'invitado';
$id_usuario = $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) {
    header("Location: login.php");
    exit();
}

$id_taller = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_taller <= 0) {
    header("Location: listar_talleres.php?err=taller");
    exit();
}

$stmt = $conn->prepare("SELECT id_taller, titulo, descripcion, imagen, precio, fecha, lugar, hora, duracion, categoria, estado, qr_imagen, qr_pay_ref FROM talleres WHERE id_taller = ?");
$stmt->bind_param("i", $id_taller);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header("Location: listar_talleres.php?err=noexiste");
    exit();
}
$t = $res->fetch_assoc();

$ya = false;
$stmt2 = $conn->prepare("SELECT 1 FROM inscripciones WHERE id_usuario=? AND id_taller=?");
$stmt2->bind_param("ii", $id_usuario, $id_taller);
$stmt2->execute();
$r2 = $stmt2->get_result();
$ya = $r2 && $r2->num_rows > 0;

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function badge_estado($e)
{
    $e = strtolower($e ?? '');
    $map = [
        'activo'   => ['#065f46', '#d1fae5'],
        'inactivo' => ['#991b1b', '#fee2e2'],
    ];
    $c = $map[$e] ?? ['#334155', '#e2e8f0'];
    return '<span class="badge" style="color:' . $c[0] . ';background:' . $c[1] . '">' . h(ucfirst($e)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($t['titulo']); ?> · Vista previa</title>
    <link rel="stylesheet" href="estilos/index.css" />
    <link rel="stylesheet" href="estilos/menu.css" />
    <link rel="stylesheet" href="estilos/panel_usuario.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .preview-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 16px
        }

        @media (max-width: 900px) {
            .preview-grid {
                grid-template-columns: 1fr
            }
        }

        .poster {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e7eef2;
            background: #f8fafc;
            cursor: zoom-in
        }

        .qr {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e7eef2;
            background: #f8fafc;
            cursor: zoom-in
        }

        .muted {
            color: #6b7280
        }

        .hr {
            height: 1px;
            background: #e7eef2;
            margin: 12px 0
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px
        }

        @media (max-width:600px) {
            .meta {
                grid-template-columns: 1fr
            }
        }

        .meta .item {
            border: 1px dashed #e7eef2;
            border-radius: 10px;
            padding: 10px
        }

        .badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700
        }

        .price {
            font-weight: 800
        }

        .copy {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #e7eef2;
            background: #fff;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer
        }

        .copy:hover {
            background: #f6f8fb
        }

        .lb {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000
        }

        .lb.show {
            display: flex
        }

        .lb img {
            max-width: 92vw;
            max-height: 92vh;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .4)
        }
    </style>
</head>

<body>
    <header>
        <div class="navbar">
            <div class="logo"><a href="index.php"><img src="img/logo.jpg" alt="Logo"></a></div>
            <ul class="menu" id="menu">
                <li><a href="panel_usuario.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
                <li><a href="listar_talleres.php"><i class="fa-solid fa-chalkboard"></i> Talleres</a></li>
                <li><a href="pagos.php"><i class="fa-solid fa-cash-register"></i> Mis pagos</a></li>
                <li><a class="active" href="mis_talleres.php"><i class="fa-solid fa-list-check"></i> Mis talleres</a></li>
                <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones <?php if ($notificaciones_pendientes > 0) echo '(' . $notificaciones_pendientes . ')'; ?></a></li>
            </ul>
            <div class="actions">
                <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h2 class="greet">Vista previa de inscripción</h2>

            <?php if ($ya): ?>
                <div class="alert ok">Ya estás inscrito en este taller. <a class="btn" href="mis_talleres.php">Ver mis talleres</a></div>
            <?php elseif (strtolower($t['estado']) !== 'activo' && $rol !== 'admin'): ?>
                <div class="alert err">Este taller está inactivo actualmente.</div>
            <?php endif; ?>

            <section class="card">
                <div class="preview-grid">
                    <aside>
                        <?php if (!empty($t['imagen'])): ?>
                            <img id="poster" class="poster" src="<?php echo h($t['imagen']); ?>" alt="Portada del taller" onerror="this.src='img/placeholder.png'">
                        <?php else: ?>
                            <img id="poster" class="poster" src="img/placeholder.png" alt="Portada no disponible">
                        <?php endif; ?>

                        <?php if (!empty($t['qr_imagen'])): ?>
                            <div style="margin-top:12px">
                                <div class="muted" style="margin-bottom:6px"><i class="fa-solid fa-qrcode"></i> Pago por QR</div>
                                <img id="qrimg" class="qr" src="<?php echo h($t['qr_imagen']); ?>" alt="QR de pago" onerror="this.src='img/placeholder.png'">
                                <?php if (!empty($t['qr_pay_ref'])): ?>
                                    <div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                        <small class="muted" id="reftext"><?php echo h($t['qr_pay_ref']); ?></small>
                                        <button class="copy" type="button" id="copyBtn"><i class="fa-regular fa-copy"></i> Copiar</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </aside>

                    <section>
                        <h3 style="margin:0 0 6px;display:flex;align-items:center;gap:8px;">
                            <?php echo h($t['titulo']); ?>
                        </h3>
                        <div class="muted">ID #<?php echo (int)$t['id_taller']; ?> · <?php echo h(ucfirst($t['categoria'] ?: 'General')); ?> · <?php echo badge_estado($t['estado']); ?></div>

                        <?php if (!empty($t['descripcion'])): ?>
                            <div class="hr"></div>
                            <div style="white-space:pre-wrap"><?php echo h($t['descripcion']); ?></div>
                        <?php endif; ?>

                        <div class="hr"></div>
                        <div class="meta">
                            <div class="item"><i class="fa-regular fa-calendar"></i> <strong>Fecha:</strong> <?php echo h(date('d/m/Y', strtotime($t['fecha']))); ?></div>
                            <?php if (!empty($t['hora'])): ?><div class="item"><i class="fa-regular fa-clock"></i> <strong>Hora:</strong> <?php echo h(substr($t['hora'], 0, 5)); ?></div><?php endif; ?>
                            <?php if (!empty($t['lugar'])): ?><div class="item"><i class="fa-solid fa-location-dot"></i> <strong>Lugar:</strong> <?php echo h($t['lugar']); ?></div><?php endif; ?>
                            <?php if (!empty($t['duracion'])): ?><div class="item"><i class="fa-regular fa-hourglass-half"></i> <strong>Duración:</strong> <?php echo h($t['duracion']); ?></div><?php endif; ?>
                            <div class="item"><i class="fa-solid fa-tag"></i> <strong>Precio:</strong> <span class="price">Bs <?php echo number_format((float)$t['precio'], 2, '.', ','); ?></span></div>
                        </div>

                        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                            <?php if (!$ya && strtolower($t['estado']) === 'activo' && $rol === 'usuario'): ?>
                                <a class="btn outline" href="listar_talleres.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
                                <form method="post" action="inscribirse.php"
                                    onsubmit="return confirm('¿Confirmar inscripción al taller:\n<?php echo h(addslashes($t['titulo'])); ?>?');">
                                    <input type="hidden" name="id_taller" value="<?php echo (int)$t['id_taller']; ?>" />
                                    <button class="btn" type="submit"><i class="fa-solid fa-user-check"></i> Confirmar inscripción</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
    </footer>

    <div id="lb" class="lb" role="dialog" aria-modal="true" aria-label="Imagen ampliada">
        <img id="lbimg" src="" alt="Imagen ampliada">
    </div>

    <script>
        (function() {
            const btn = document.getElementById('copyBtn');
            const txt = document.getElementById('reftext');
            if (btn && txt) {
                btn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(txt.textContent.trim());
                        btn.textContent = '¡Copiado!';
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar';
                        }, 1400);
                    } catch (e) {
                        alert('No se pudo copiar.');
                    }
                });
            }
        })();

        (function() {
            const lb = document.getElementById('lb');
            const img = document.getElementById('lbimg');
            const poster = document.getElementById('poster');
            const qrimg = document.getElementById('qrimg');

            function openLB(src) {
                if (!src) return;
                img.src = src;
                lb.classList.add('show');
            }

            function closeLB() {
                lb.classList.remove('show');
                img.src = '';
            }

            if (poster) poster.addEventListener('click', () => openLB(poster.src));
            if (qrimg) qrimg.addEventListener('click', () => openLB(qrimg.src));

            lb.addEventListener('click', (e) => {
                if (e.target === lb) closeLB();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeLB();
            });
        })();
    </script>
</body>

</html>