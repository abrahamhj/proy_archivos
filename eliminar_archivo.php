<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
  header("Location: login.php");
  exit();
}

function redirectWith($ok) {
  $to = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'listar_archivos.php') !== false
      ? $_SERVER['HTTP_REFERER']
      : 'listar_archivos.php';
  header('Location: ' . $to . ($ok ? (strpos($to, '?')!==false?'&':'?').'msg=deleted' : (strpos($to, '?')!==false?'&':'?').'error=1'));
  exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { redirectWith(false); }

$res = $conn->prepare("SELECT ruta FROM archivos WHERE id_archivo = ?");
$res->bind_param('i', $id);
$res->execute();
$row = $res->get_result()->fetch_assoc();
if (!$row) { redirectWith(false); }

$rel = $row['ruta'];
$abs = realpath(__DIR__ . '/' . $rel);
$base = realpath(__DIR__);

// borrado físico seguro solo si está dentro del proyecto
if ($abs && strpos($abs, $base) === 0 && is_file($abs)) { @unlink($abs); }

$del = $conn->prepare("DELETE FROM archivos WHERE id_archivo = ?");
$del->bind_param('i', $id);
$ok = $del->execute();

redirectWith($ok);
