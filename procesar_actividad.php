<?php
include "conexion.php";
session_start();

// Verificamos que solo el admin acceda
if(!isset($_SESSION['rol']) || $_SESSION['rol'] != 'admin'){
  header("Location: login.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $enlace = $_POST['enlace'];

    // Subida de la imagen
    $targetDir = "uploads/";
    if(!is_dir($targetDir)){
        mkdir($targetDir, 0777, true);
    }

    $fileName = basename($_FILES["imagen"]["name"]);
    $targetFile = $targetDir . $fileName;

    if(move_uploaded_file($_FILES["imagen"]["tmp_name"], $targetFile)){
        // Guardamos en la BD
        $sql = "INSERT INTO actividades (titulo, descripcion, imagen, enlace) 
                VALUES ('$titulo', '$descripcion', '$fileName', '$enlace')";
        
        if($conn->query($sql) === TRUE){
            echo "<script>alert('Actividad guardada exitosamente'); window.location='admin_actividades.php';</script>";
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        echo "Error al subir la imagen.";
    }
}
?>
