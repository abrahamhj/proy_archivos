<?php
session_start();
include "../conexion.php";

// Solo admin puede actualizar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_inscripcion = $_POST['id_inscripcion'];
    $estado_pago = $_POST['estado_pago'];

    $sql = "UPDATE inscripciones SET estado_pago = ? WHERE id_inscripcion = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $estado_pago, $id_inscripcion);

    if ($stmt->execute()) {

        // Crear notificación para el usuario
        $sqlNotif = "INSERT INTO notificaciones (id_usuario, mensaje) 
                 SELECT i.id_usuario, CONCAT('Tu pago para el taller ', t.titulo, ' fue actualizado a: ', ?) 
                 FROM inscripciones i
                 JOIN talleres t ON i.id_taller = t.id_taller
                 WHERE i.id_inscripcion = ?";
        $stmtNotif = $conn->prepare($sqlNotif);
        $stmtNotif->bind_param("si", $estado_pago, $id_inscripcion);
        $stmtNotif->execute();
        header("Location: listar_inscripciones.php?msg=ok");
    } else {
        echo "Error al actualizar el estado de pago.";
    }

    $stmt->close();
    $conn->close();
}
?>
