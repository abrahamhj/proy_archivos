<?php
session_start();
include "conexion.php";

// Solo usuarios normales pueden inscribirse
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
    header("Location: login.php");
    exit();
}

$id_usuario = (int)$_SESSION['id_usuario'];
// Aceptar id por POST o GET
$id_taller = isset($_POST['id_taller']) ? (int)$_POST['id_taller'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id_taller <= 0) {
    header("Location: listar_talleres.php?err=taller");
    exit();
}

// Verificar que el taller exista y (por seguridad) que esté activo
$stmtT = $conn->prepare("SELECT titulo, estado FROM talleres WHERE id_taller = ?");
$stmtT->bind_param("i", $id_taller);
$stmtT->execute();
$resT = $stmtT->get_result();
if (!$resT || $resT->num_rows === 0) {
    header("Location: listar_talleres.php?err=noexiste");
    exit();
}
$taller = $resT->fetch_assoc();
if (strtolower($taller['estado']) !== 'activo') {
    header("Location: listar_talleres.php?err=inactivo");
    exit();
}

// Verificar si ya está inscrito
$stmtC = $conn->prepare("SELECT 1 FROM inscripciones WHERE id_usuario = ? AND id_taller = ?");
$stmtC->bind_param("ii", $id_usuario, $id_taller);
$stmtC->execute();
$resC = $stmtC->get_result();
if ($resC && $resC->num_rows > 0) {
    header("Location: mis_talleres.php?msg=ya_inscrito");
    exit();
}

// Insertar inscripción con estado 'pendiente'
$stmtI = $conn->prepare("INSERT INTO inscripciones (id_usuario, id_taller, estado) VALUES (?, ?, 'pendiente')");
$stmtI->bind_param("ii", $id_usuario, $id_taller);
if ($stmtI->execute()) {
    // Notificación al admin
    $mensajeAdmin = "El usuario " . ($_SESSION['nombre'] ?? ('ID ' . $id_usuario)) . " se inscribió al taller '" . $taller['titulo'] . "'.";
    $stmtNA = $conn->prepare("INSERT INTO notificaciones_admin (mensaje) VALUES (?)");
    $stmtNA->bind_param("s", $mensajeAdmin);
    $stmtNA->execute();

    // Notificación al usuario
    $mensajeUser = "Te inscribiste en '" . $taller['titulo'] . "'. Tu estado es PENDIENTE. Sube tu comprobante en Mis pagos.";
    $stmtNU = $conn->prepare("INSERT INTO notificaciones (id_usuario, mensaje) VALUES (?, ?)");
    $stmtNU->bind_param("is", $id_usuario, $mensajeUser);
    $stmtNU->execute();

    header("Location: mis_talleres.php?msg=inscrito");
    exit();
}

// Si falla el insert
header("Location: listar_talleres.php?err=inscribir");
exit();
