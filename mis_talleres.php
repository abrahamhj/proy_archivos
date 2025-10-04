<?php
session_start();
include "../conexion.php";

// Solo usuarios normales
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "usuario") {
    header("Location: ../login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

$sql = "SELECT t.titulo, t.fecha, t.precio, i.estado_pago
        FROM inscripciones i
        JOIN talleres t ON i.id_taller = t.id_taller
        WHERE i.id_usuario = ?
        ORDER BY t.fecha DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $conn->query($sql);
?>

<h2>Mis Talleres</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Título</th>
        <th>Fecha</th>
        <th>Precio (Bs)</th>
        <th>Estado de pago</th>
        <th>Comprobante</th>
    </tr>

<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['titulo'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . $row['precio'] . "</td>";
        echo "<td>" . $row['estado_pago'] . "</td>";
        
        // Mostrar comprobante o formulario para subirlo
        echo "<td>";
        if (!empty($row['comprobante'])) {
            echo "<a href='../comprobantes/" . $row['comprobante'] . "' target='_blank'>Ver comprobante</a>";
        } else {
            echo "<form action='subir_comprobante.php' method='POST' enctype='multipart/form-data'>
                    <input type='hidden' name='id_taller' value='" . $row['titulo'] . "'>
                    <input type='file' name='comprobante' accept='image/*' required>
                    <button type='submit'>Subir</button>
                  </form>";
        }
        echo "</td>";

        
        echo "</tr>";

    }

} else {
    echo "<tr><td colspan='5'>No estás inscrito en ningún taller</td></tr>";
}
?>
</table>
<a href="../panel.php">Volver al panel</a>