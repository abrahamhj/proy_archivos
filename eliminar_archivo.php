<?php
session_start();
include "conexion.php";

// Solo admin puede borrar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Buscar el archivo en BD
    $sql = "SELECT ruta FROM archivos WHERE id_archivo=$id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ruta = $row['ruta'];

        // Eliminar archivo físico
        if (file_exists($ruta)) {
            unlink($ruta);
        }

        // Eliminar registro en BD
        $conn->query("DELETE FROM archivos WHERE id_archivo=$id");

        echo "Archivo eliminado.<br><a href='listar_archivos.php'>Volver</a>";
    } else {
        echo "Archivo no encontrado.";
    }
} else {
    echo "ID no válido.";
}
