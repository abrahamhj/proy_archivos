<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
  header("Location: login.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: pagos.php");
  exit();
}

$id_usuario = (int)$_SESSION['id_usuario'];
$id_inscripcion = isset($_POST['id_inscripcion']) ? (int)$_POST['id_inscripcion'] : 0;

// Validar pertenencia de la inscripción y obtener estado
$stmt = $conn->prepare("SELECT i.id_inscripcion, i.estado, t.titulo FROM inscripciones i INNER JOIN talleres t ON t.id_taller = i.id_taller WHERE i.id_inscripcion = ? AND i.id_usuario = ?");
$stmt->bind_param("ii", $id_inscripcion, $id_usuario);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
  header("Location: pagos.php?error=inscripcion");
  exit();
}
$row = $res->fetch_assoc();
$titulo_taller = $row['titulo'];
$estado_actual = strtolower((string)($row['estado'] ?? ''));

// Bloquear re-subidas si ya está en revisión o pagado
if ($estado_actual === 'pendiente') {
  header("Location: pagos.php?error=ya_subido");
  exit();
}
if ($estado_actual === 'pagado') {
  header("Location: pagos.php?error=ya_pagado");
  exit();
}

if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
  header("Location: pagos.php?error=archivo");
  exit();
}

$allowedMime = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'application/pdf' => 'pdf',
];
$tmp = $_FILES['comprobante']['tmp_name'];
$size = (int)$_FILES['comprobante']['size'];
$type = mime_content_type($tmp);
$ext = $allowedMime[$type] ?? null;

if (!$ext) {
  header("Location: pagos.php?error=tipo");
  exit();
}

$maxBytes = 5 * 1024 * 1024; // 5MB
if ($size <= 0 || $size > $maxBytes) {
  header("Location: pagos.php?error=tamano");
  exit();
}

$dir = __DIR__ . "/uploads/comprobantes";
if (!is_dir($dir)) {
  @mkdir($dir, 0775, true);
}

$filename = sprintf("comprobante-%d-%s.%s", $id_inscripcion, date('YmdHis'), $ext);
$dest = $dir . "/" . $filename;

if (!move_uploaded_file($tmp, $dest)) {
  header("Location: pagos.php?error=guardar");
  exit();
}

// Registrar pago pendiente de validación
$stmtIns = $conn->prepare("INSERT INTO pagos (id_inscripcion, comprobante, validado) VALUES (?, ?, 0)");
$stmtIns->bind_param("is", $id_inscripcion, $filename);
$okPago = $stmtIns->execute();

// Asegurar estado de la inscripción como 'pendiente'
$conn->query("UPDATE inscripciones SET estado='pendiente', comprobante=NULL WHERE id_inscripcion = " . $id_inscripcion);

// Notificar al admin y al usuario
if ($okPago) {
  // Notificación para admin
  $msgAdmin = "Nuevo comprobante de pago: " . ($_SESSION['nombre'] ?? 'Usuario') . " para '" . $titulo_taller . "'";
  $stmtNA = $conn->prepare("INSERT INTO notificaciones_admin (mensaje) VALUES (?)");
  $stmtNA->bind_param("s", $msgAdmin);
  $stmtNA->execute();

  // Notificación para usuario
  $msgUser = "Tu comprobante para '" . $titulo_taller . "' fue recibido y está pendiente de validación.";
  $stmtNU = $conn->prepare("INSERT INTO notificaciones (id_usuario, mensaje) VALUES (?, ?)");
  $stmtNU->bind_param("is", $id_usuario, $msgUser);
  $stmtNU->execute();
}

header("Location: pagos.php?msg=subido");
exit();
