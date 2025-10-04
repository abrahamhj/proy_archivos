<?php
session_start();
include "../conexion.php";

$sql = "SELECT * FROM talleres ORDER BY fecha DESC";
$result = $conn->query($sql);
?>

<h2>Lista de Talleres</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Título</th>
        <th>Descripción</th>
        <th>Fecha</th>
        <th>Precio (Bs)</th>
        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] == "admin") { ?>
            <th>Acciones</th>
        <?php } ?>
    </tr>

<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['titulo'] . "</td>";
        echo "<td>" . $row['descripcion'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . $row['precio'] . "</td>";

        if (isset($_SESSION['rol']) && $_SESSION['rol'] == "admin") {
            echo "<td>
                    <a href='editar_taller.php?id=" . $row['id_taller'] . "'>Editar</a> | 
                    <a href='eliminar_taller.php?id=" . $row['id_taller'] . "' onclick='return confirm(\"¿Seguro que deseas eliminar este taller?\")'>Eliminar</a>
                  </td>";
        }
        echo "</tr>";
        if (isset($_SESSION['rol']) && $_SESSION['rol'] == "usuario") {
            echo "<td><a href='inscribirse.php?id=" . $row['id_taller'] . "'>Inscribirse</a></td>";
        }

    }
} else {
    echo "<tr><td colspan='5'>No hay talleres registrados</td></tr>";
}
?>
</table>
<a href="../panel.php">Volver al panel</a>
