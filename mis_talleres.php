<?php
session_start();
include "conexion.php";

// Solo usuarios normales
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
    header("Location: login.php");
    exit();
}

$id_usuario = (int)$_SESSION['id_usuario'];

// Obtener inscripciones del usuario
$sql = "SELECT i.id_inscripcion, i.estado, i.comprobante, t.id_taller, t.titulo, t.fecha, t.precio
        FROM inscripciones i
        INNER JOIN talleres t ON t.id_taller = i.id_taller
        WHERE i.id_usuario = ?
        ORDER BY t.fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

function estado_badge($estado)
{
    $estado = strtolower((string)$estado);
    $styles = [
        'pendiente' => ['#b45309', '#fef3c7'],
        'pagado'    => ['#065f46', '#d1fae5'],
        'rechazado' => ['#991b1b', '#fee2e2'],
    ];
    $pair = $styles[$estado] ?? ['#334155', '#e2e8f0'];
    return '<span style="display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.75rem;font-weight:600;color:'
        . $pair[0] . ';background:' . $pair[1] . ';text-transform:uppercase;">' . htmlspecialchars($estado) . '</span>';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mis talleres</title>
    <link rel="stylesheet" href="estilos/index.css" />
    <link rel="stylesheet" href="estilos/menu.css" />
    <link rel="stylesheet" href="estilos/panel_usuario.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        .table-mis {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table-mis th,
        .table-mis td {
            border-bottom: 1px solid #e7eef2;
            padding: 10px;
            vertical-align: top;
            text-align: left;
        }

        .table-mis .col-title {
            width: 42%;
        }

        .table-mis .col-fecha {
            width: 120px;
            white-space: nowrap;
        }

        .table-mis .col-precio {
            width: 120px;
            white-space: nowrap;
        }

        .table-mis .col-estado {
            width: 140px;
        }

        .table-mis .col-accion {
            width: 160px;
        }

        .table-mis .title {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }

        .table-mis .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
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
                <li><a href="notificaciones.php"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
            </ul>
            <div class="actions">
                <a class="btn danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main>
        <div class="wrap">
            <h2 class="greet">Mis talleres</h2>

            <?php
            $msg = $_GET['msg'] ?? '';
            if ($msg === 'inscrito') {
                echo '<div class="alert ok">Inscripción realizada con éxito. Ahora puedes subir tu comprobante en Mis pagos.</div>';
            } elseif ($msg === 'ya_inscrito') {
                echo '<div class="alert ok">Ya estás inscrito en ese taller.</div>';
            }
            ?>

            <div class="card">
                <div style="overflow:auto">
                    <table class="table table-mis">
                        <thead>
                            <tr>
                                <th class="col-title">Taller</th>
                                <th class="col-fecha">Fecha</th>
                                <th class="col-precio">Precio (Bs)</th>
                                <th class="col-estado">Estado</th>
                                <th class="col-accion">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="col-title">
                                            <div class="title"><?php echo htmlspecialchars($row['titulo']); ?></div>
                                        </td>
                                        <td class="col-fecha">
                                            <?php echo htmlspecialchars(date('d/m/Y', strtotime($row['fecha']))); ?>
                                        </td>
                                        <td class="col-precio">
                                            <?php echo number_format((float)$row['precio'], 2, '.', ''); ?>
                                        </td>
                                        <td class="col-estado">
                                            <?php echo estado_badge($row['estado']); ?>
                                        </td>
                                        <td class="col-accion">
                                            <span class="actions">
                                                <a class="btn" href="ver_taller.php?id=<?php echo (int)$row['id_taller']; ?>">
                                                    <i class="fa-solid fa-eye"></i> Ver taller
                                                </a>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="padding:14px;text-align:center;">No estás inscrito en ningún taller</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p style="margin-top:16px;">
                <a class="btn" href="panel_usuario.php">← Volver</a>
                <a class="btn" href="pagos.php">Ir a Mis pagos →</a>
            </p>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Salvemos los Archivos - Bolivia</p>
    </footer>
</body>

</html>