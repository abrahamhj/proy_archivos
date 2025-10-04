<?php
session_start();
include "conexion.php";

// Solo admin puede ver las inscripciones
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

$sql = "SELECT i.id_inscripcion, u.nombre AS usuario, u.email, 
               t.titulo AS taller, t.fecha, t.precio, i.estado_pago
        FROM inscripciones i
        JOIN usuarios u ON i.id_usuario = u.id_usuario
        JOIN talleres t ON i.id_taller = t.id_taller
        ORDER BY t.fecha DESC";

$result = $conn->query($sql);
?>

<h2>Listado de Inscripciones</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Usuario</th>
        <th>Email</th>
        <th>Taller</th>
        <th>Fecha</th>
        <th>Precio (Bs)</th>
        <th>Estado de pago</th>
        <th>Comprobante</th>
        <th>Acción</th>
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
        echo "<td>" . $row['estado_pago'] . "</td>";

        // Mostrar comprobante si existe
        echo "<td>";
        if (!empty($row['comprobante'])) {
            echo "<a href='../comprobantes/" . $row['comprobante'] . "' target='_blank'>Ver comprobante</a>
            <form method='POST' action='rechazar_comprobante.php' style='margin-top:5px;'>
                      <input type='hidden' name='id_inscripcion' value='" . $row['id_inscripcion'] . "'>
                      <input type='hidden' name='archivo' value='" . $row['comprobante'] . "'>
                      <button type='submit'>Rechazar</button>
                  </form>";
        } else {
            echo "Sin comprobante";
        }
        echo "</td>";

        // Formulario para actualizar estado

        echo "<td>
                <form method='POST' action='marcar_pago.php'>
                    <input type='hidden' name='id_inscripcion' value='" . $row['id_inscripcion'] . "'>
                    <select name='estado_pago'>
                        <option value='pendiente' " . ($row['estado_pago'] == 'pendiente' ? 'selected' : '') . ">Pendiente</option>
                        <option value='pagado' " . ($row['estado_pago'] == 'pagado' ? 'selected' : '') . ">Pagado</option>
                    </select>
                    <button type='submit'>Actualizar</button>
                </form>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8'>No hay inscripciones registradas</td></tr>";
}
?>
</table>

<a href="../panel.php">Volver al panel</a>
