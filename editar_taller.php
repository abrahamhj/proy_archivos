<?php
session_start();
include "../conexion.php";

// Solo admin puede editar
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM talleres WHERE id_taller=$id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $taller = $result->fetch_assoc();
    } else {
        die("Taller no encontrado.");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];

    $sql = "UPDATE talleres SET titulo='$titulo', descripcion='$descripcion', fecha='$fecha', precio='$precio' WHERE id_taller=$id";

    if ($conn->query($sql)) {
        echo "Taller actualizado. <a href='listar_talleres.php'>Volver</a>";
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Editar Taller</h2>
<form method="POST" action="">
    <input type="hidden" name="id" value="<?php echo $taller['id_taller']; ?>">

    <label>Título:</label><br>
    <input type="text" name="titulo" value="<?php echo $taller['titulo']; ?>" required><br><br>

    <label>Descripción:</label><br>
    <textarea name="descripcion" required><?php echo $taller['descripcion']; ?></textarea><br><br>

    <label>Fecha:</label><br>
    <input type="date" name="fecha" value="<?php echo $taller['fecha']; ?>" required><br><br>

    <label>Precio (Bs):</label><br>
    <input type="number" name="precio" step="0.01" value="<?php echo $taller['precio']; ?>" required><br><br>

    <button type="submit">Actualizar</button>
</form>
<a href="listar_talleres.php">Cancelar</a>
