<?php
session_start();
include "../conexion.php";

// Solo admin puede eliminar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM talleres WHERE id_taller=$id";

    if ($conn->query($sql)) {
        header("Location: listar_talleres.php");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "ID no válido.";
}
