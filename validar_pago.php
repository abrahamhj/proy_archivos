<?php
session_start();
include "conexion.php";

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
  header("Location: login.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: pagos.php");
  exit();
}

$id_pago = isset($_POST['id_pago']) ? (int)$_POST['id_pago'] : 0;
$accion = $_POST['accion'] ?? '';

// Obtener datos del pago e inscripción
$sql = "SELECT p.id_pago, p.validado, p.comprobante,
               i.id_inscripcion, i.id_usuario, i.estado,
               t.titulo
        FROM pagos p
        INNER JOIN inscripciones i ON i.id_inscripcion = p.id_inscripcion
        INNER JOIN talleres t ON t.id_taller = i.id_taller
        WHERE p.id_pago = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pago);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
  header("Location: pagos.php?error=pago");
  exit();
}
$row = $res->fetch_assoc();
$id_inscripcion = (int)$row['id_inscripcion'];
$id_usuario = (int)$row['id_usuario'];
$titulo = $row['titulo'];

if ($accion === 'aprobar') {
  // Marcar pago validado y estado pagado
  $conn->begin_transaction();
  try {
    $upP = $conn->prepare("UPDATE pagos SET validado = 1 WHERE id_pago = ?");
    $upP->bind_param("i", $id_pago);
    $upP->execute();

    $upI = $conn->prepare("UPDATE inscripciones SET estado = 'pagado' WHERE id_inscripcion = ?");
    $upI->bind_param("i", $id_inscripcion);
    $upI->execute();

    // Notificación al usuario
    $msgUser = "Tu pago para '" . $titulo . "' fue APROBADO.";
    $nu = $conn->prepare("INSERT INTO notificaciones (id_usuario, mensaje) VALUES (?, ?)");
    $nu->bind_param("is", $id_usuario, $msgUser);
    $nu->execute();

    // Notificación admin
    $msgAdmin = "Pago aprobado para '" . $titulo . "' (pago #$id_pago).";
    $na = $conn->prepare("INSERT INTO notificaciones_admin (mensaje) VALUES (?)");
    $na->bind_param("s", $msgAdmin);
    $na->execute();

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    header("Location: pagos.php?error=aprobar");
    exit();
  }
  header("Location: pagos.php?msg=aprobado");
  exit();
}

if ($accion === 'rechazar') {
  // Marcar pago como rechazado (validado=2) y estado de inscripción 'rechazado'
  $conn->begin_transaction();
  try {
    $upP = $conn->prepare("UPDATE pagos SET validado = 2 WHERE id_pago = ?");
    $upP->bind_param("i", $id_pago);
    $upP->execute();

    // Extra: invalidar cualquier otro pago pendiente de esta inscripción
    $upPOtros = $conn->prepare("UPDATE pagos SET validado = 2 WHERE id_inscripcion = ? AND validado = 0");
    $upPOtros->bind_param("i", $id_inscripcion);
    $upPOtros->execute();

    $upI = $conn->prepare("UPDATE inscripciones SET estado = 'rechazado' WHERE id_inscripcion = ?");
    $upI->bind_param("i", $id_inscripcion);
    $upI->execute();

    // Notificación al usuario
    $msgUser = "Tu pago para '" . $titulo . "' fue RECHAZADO. Vuelve a subir un comprobante válido.";
    $nu = $conn->prepare("INSERT INTO notificaciones (id_usuario, mensaje) VALUES (?, ?)");
    $nu->bind_param("is", $id_usuario, $msgUser);
    $nu->execute();

    // Notificación admin
    $msgAdmin = "Pago rechazado para '" . $titulo . "' (pago #$id_pago).";
    $na = $conn->prepare("INSERT INTO notificaciones_admin (mensaje) VALUES (?)");
    $na->bind_param("s", $msgAdmin);
    $na->execute();

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    header("Location: pagos.php?error=rechazar");
    exit();
  }
  header("Location: pagos.php?msg=rechazado");
  exit();
}

header("Location: pagos.php");
exit();
