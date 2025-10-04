<?php
session_start();
include "../conexion.php";

// Solo usuarios normales pueden inscribirse
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "usuario") {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_taller = intval($_GET['id']);
    $id_usuario = $_SESSION['id_usuario'];

    // Verificar si ya está inscrito
    $check = "SELECT * FROM inscripciones WHERE id_usuario=$id_usuario AND id_taller=$id_taller";
    $result = $conn->query($check);

    if ($result->num_rows > 0) {
        echo "Ya estás inscrito en este taller. <a href='listar_talleres.php'>Volver</a>";
        exit();
    }

    // Insertar inscripción
    $sql = "INSERT INTO inscripciones (id_usuario, id_taller, estado_pago) 
            VALUES ($id_usuario, $id_taller, 'pendiente')";
if ($stmt->execute()) {
    // 👇 Notificación al admin
    $sqlNotif = "INSERT INTO notificaciones_admin (mensaje) VALUES (?)";
    $stmtNotif = $conn->prepare($sqlNotif);
    $mensaje = "El usuario " . $_SESSION['nombre'] . " se inscribió al taller ID " . $id_taller;
    $stmtNotif->bind_param("s", $mensaje);
    $stmtNotif->execute();

    if ($conn->query($sql)) {
        echo "Inscripción realizada con éxito 🎉. <a href='mis_talleres.php'>Ver mis talleres</a>";
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "ID de taller no válido.";
}
// Notificación para admin
$sqlNotif = "INSERT INTO notificaciones_admin (mensaje) VALUES (?)";
$stmtNotif = $conn->prepare($sqlNotif);
$mensaje = "El usuario " . $_SESSION['nombre'] . " se inscribió al taller ID " . $id_taller;
$stmtNotif->bind_param("s", $mensaje);
$stmtNotif->execute();
