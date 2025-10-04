<?php
session_start();
include "conexion.php";

// Solo usuarios normales
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "usuario") {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

$sql = "SELECT id_notificacion, mensaje, leida, fecha 
        FROM notificaciones 
        WHERE id_usuario = ? 
        ORDER BY fecha DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
?>

<h2>Mis Notificaciones</h2>
<ul>
<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $estilo = $row['leida'] ? "color:gray;" : "font-weight:bold;";
        echo "<li style='$estilo'>" . $row['mensaje'] . " (" . $row['fecha'] . ")</li>";
    }

    // Marcar todas como leídas
    $sqlUpdate = "UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("i", $id_usuario);
    $stmtUpdate->execute();
} else {
    echo "<li>No tienes notificaciones</li>";
}
?>
</ul>

<a href="panel.php">Volver al panel</a>
