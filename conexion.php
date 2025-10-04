<?php
$host = "localhost";  // o el servidor que uses
$user = "root";       // tu usuario MySQL
$pass = "";           // tu contraseña MySQL
$db   = "bd_salvemos"; // cambia por el nombre de tu BD

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error en la conexión: " . $conn->connect_error);
}
?>
