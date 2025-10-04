<?php
session_start();
include "conexion.php";

// Solo admin puede editar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Obtener datos actuales
    $sql = "SELECT * FROM archivos WHERE id_archivo=$id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $archivo = $result->fetch_assoc();
    } else {
        die("Archivo no encontrado.");
    }
}

// Procesar formulario de edición
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id     = intval($_POST['id']);
    $nombre = $_POST['nombre'];
    $tipo   = $_POST['tipo'];

    $sql = "UPDATE archivos SET nombre='$nombre', tipo='$tipo' WHERE id_archivo=$id";
    if ($conn->query($sql)) {
        echo "Archivo actualizado.<br><a href='listar_archivos.php'>Volver a la lista</a>";
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Editar archivo</h2>
<form method="POST" action="">
    <input type="hidden" name="id" value="<?php echo $archivo['id_archivo']; ?>">

    <label>Nombre:</label><br>
    <input type="text" name="nombre" value="<?php echo $archivo['nombre']; ?>" required><br><br>

    <label>Tipo:</label><br>
    <select name="tipo" required>
        <option value="documento" <?php if($archivo['tipo']=="documento") echo "selected"; ?>>Documento</option>
        <option value="imagen" <?php if($archivo['tipo']=="imagen") echo "selected"; ?>>Imagen</option>
        <option value="video" <?php if($archivo['tipo']=="video") echo "selected"; ?>>Video</option>
    </select><br><br>

    <button type="submit">Actualizar</button>
</form>

<a href="listar_archivos.php">Cancelar</a>
