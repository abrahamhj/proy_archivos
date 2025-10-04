<?php
session_start();
include "../conexion.php";

// Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

// Consulta de inscritos pagados
$sql = "SELECT u.nombre AS usuario, u.email, t.titulo AS taller, t.fecha, t.precio
        FROM inscripciones i
        JOIN usuarios u ON i.id_usuario = u.id_usuario
        JOIN talleres t ON i.id_taller = t.id_taller
        WHERE i.estado_pago = 'pagado'
        ORDER BY t.fecha DESC";

$result = $conn->query($sql);

// Encabezados para descargar como Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=inscritos_pagados.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Encabezados de la tabla
echo "Usuario\tEmail\tTaller\tFecha\tPrecio (Bs)\n";

// Filas
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo $row['usuario'] . "\t" . $row['email'] . "\t" . $row['taller'] . "\t" . $row['fecha'] . "\t" . $row['precio'] . "\n";
    }
} else {
    echo "No hay inscripciones pagadas\n";
}
?>
