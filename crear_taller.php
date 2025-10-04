<?php
session_start();
include "../conexion.php";

// Solo admin puede crear talleres
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];

    $sql = "INSERT INTO talleres (titulo, descripcion, fecha, precio) 
            VALUES ('$titulo', '$descripcion', '$fecha', '$precio')";

    if ($conn->query($sql)) {
        echo "Taller creado con éxito. <a href='listar_talleres.php'>Ver talleres</a>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Crear Taller</h2>
<form method="POST" action="">
    <label>Título:</label><br>
    <input type="text" name="titulo" required><br><br>

    <label>Descripción:</label><br>
    <textarea name="descripcion" required></textarea><br><br>

    <label>Fecha:</label><br>
    <input type="date" name="fecha" required><br><br>

    <label>Precio (Bs):</label><br>
    <input type="number" name="precio" step="0.01" required><br><br>

    <button type="submit">Crear</button>
</form>
<a href="listar_talleres.php">Volver a la lista</a>
