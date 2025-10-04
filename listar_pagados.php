<?php
session_start();
include "../conexion.php";

// Solo admin puede ver este listado
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT u.nombre AS usuario, u.email, t.titulo AS taller, t.fecha, t.precio
        FROM inscripciones i
        JOIN usuarios u ON i.id_usuario = u.id_usuario
        JOIN talleres t ON i.id_taller = t.id_taller
        WHERE i.estado_pago = 'pagado'
        ORDER BY t.fecha DESC";

$result = $conn->query($sql);
?>

<h2>Listado de Participantes Pagados</h2>
<a href="exportar_pagados_excel.php">📥 Exportar a Excel</a>
<br><br>
<table border="1" cellpadding="5">
    <tr>
        <th>Usuario</th>
        <th>Email</th>
        <th>Taller</th>
        <th>Fecha</th>
        <th>Precio (Bs)</th>
    </tr>

<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['usuario'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['taller'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . $row['precio'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No hay inscripciones pagadas</td></tr>";
}
?>
</table>

<a href="../panel.php">Volver al panel</a>
