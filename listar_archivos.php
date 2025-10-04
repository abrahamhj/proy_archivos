<?php
session_start();
include "conexion.php";

// Solo usuarios registrados
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] != "usuario" && $_SESSION['rol'] != "admin")) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM archivos ORDER BY fecha_subida DESC";
$result = $conn->query($sql);
?>

<h2>Archivos disponibles</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Nombre</th>
        <th>Tipo</th>
        <th>Vista previa</th>
        <th>Acción</th>
        <?php if ($_SESSION['rol'] == "admin") echo "<th>Opciones</th>"; ?>
    </tr>

<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['nombre'] . "</td>";
        echo "<td>" . $row['tipo'] . "</td>";

        // Vista previa
        echo "<td>";
        if ($row['tipo'] == "imagen") {
            echo "<img src='" . $row['ruta'] . "' width='100'>";
        } elseif ($row['tipo'] == "video") {
            echo "<video width='200' controls>
                    <source src='" . $row['ruta'] . "' type='video/mp4'>
                  </video>";
        } elseif ($row['tipo'] == "documento") {
            if (strtolower(pathinfo($row['ruta'], PATHINFO_EXTENSION)) == "pdf") {
                echo "<a href='" . $row['ruta'] . "' target='_blank'>Ver PDF</a>";
            } else {
                echo "Documento disponible";
            }
        }
        echo "</td>";

        // Descargar
        echo "<td><a href='" . $row['ruta'] . "' download>Descargar</a></td>";

        // Solo admin puede eliminar
        if ($_SESSION['rol'] == "admin") {
            echo "<td><a href='eliminar_archivo.php?id=" . $row['id_archivo'] . "' onclick='return confirm(\"¿Seguro que deseas eliminar este archivo?\")'>Eliminar</a></td>";
        }

        echo "</tr>";
        if ($_SESSION['rol'] == "admin") {
            echo "<td>
            <a href='editar_archivo.php?id=" . $row['id_archivo'] . "'>Editar</a> | 
            <a href='eliminar_archivo.php?id=" . $row['id_archivo'] . "' onclick='return confirm(\"¿Seguro que deseas eliminar este archivo?\")'>Eliminar</a>
          </td>";
}

    }
} else {
    echo "<tr><td colspan='5'>No hay archivos</td></tr>";
}
?>
</table>
<a href="panel_usuario.php">Volver al panel</a>
