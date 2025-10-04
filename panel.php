<?php
session_start();

// Verificamos si el usuario está logueado
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Navegación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        header {
            background: #333;
            color: white;
            padding: 15px;
            text-align: center;
        }
        nav {
            background: #444;
            padding: 10px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 5px;
        }
        nav a:hover {
            background: #666;
        }
        main {
            padding: 20px;
            text-align: center;
        }
        .rol {
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <header>
        <h1>Bienvenido, <?php echo $nombre; ?> 👋</h1>
        <p class="rol">Rol: <?php echo ucfirst($rol); ?></p>
    </header>

    <nav>
        <a href="panel.php">Inicio</a>
        <?php if ($rol == "admin") { ?>
            <a href="subir_archivo.php">Subir archivos</a>
            <a href="listar_archivos.php">Gestionar archivos</a>
            <a href="talleres.php">Gestionar talleres</a>
            <a href="talleres/crear_taller.php">Crear taller</a>
            <a href="talleres/listar_inscripciones.php">Ver inscripciones</a>
            <a href="talleres/listar_pagados.php">Ver inscritos pagados</a>

        <?php } elseif ($rol == "usuario") { ?>
            <a href="listar_archivos.php">Ver archivos</a>
            <a href="talleres.php">Talleres disponibles</a>
            <a href="talleres/mis_talleres.php">Mis talleres</a>
            <a href="notificaciones.php">🔔 Mis notificaciones</a>

        <?php } elseif ($rol == "invitado") { ?>
            <a href="info.php">Información del colectivo</a>
            <a href="registro.php">Registrarse</a>
        <?php } ?>
        <a href="logout.php">Cerrar sesión</a>
    </nav>

    <main>
        <h2>Panel principal</h2>
        <p>Selecciona una opción del menú según tu rol.</p>
    </main>
</body>
</html>
