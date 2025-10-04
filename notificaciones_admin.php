<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

$sql = "SELECT id_notificacion, mensaje, leida, fecha 
        FROM notificaciones_admin 
        ORDER BY fecha DESC";
$result = $conn->query($sql);
?>

<h2>Notificaciones de Administrador</h2>
<ul>
<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $estilo = $row['leida'] ? "color:gray;" : "font-weight:bold;";
        echo "<li style='$estilo'>" . $row['mensaje'] . " (" . $row['fecha'] . ")</li>";
    }

    // Marcar todas como leídas
    $sqlUpdate = "UPDATE notificaciones_admin SET leida = 1 WHERE leida = 0";
    $conn->query($sqlUpdate);
} else {
    echo "<li>No hay notificaciones</li>";
}
?>
</ul>

<a href="panel_admin.php">Volver al panel</a>
