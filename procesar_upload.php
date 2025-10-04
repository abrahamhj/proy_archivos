<?php
session_start();
include __DIR__ . "/../conexion.php";

// Solo admins pueden subir
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != "admin") {
    header("Location: login.php");
    exit();
}

// Verificar que el archivo llegó
if (!isset($_FILES["archivo"]) || $_FILES["archivo"]["error"] != 0) {
    header("Location: subir_archivo.php?status=error");
    exit();
}

$tipo = $_POST['tipo']; // documentos | imagenes | videos

// Directorios base
$directorioBase = __DIR__ . "/uploads";
$subcarpeta = $directorioBase . "/" . $tipo;

if (!file_exists($subcarpeta)) {
    mkdir($subcarpeta, 0777, true);
}

$archivoTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);
$rutaDestino = $subcarpeta . "/" . $nombreArchivo;

if (move_uploaded_file($archivoTmp, $rutaDestino)) {
    // Redirige con éxito
    header("Location: subir_archivo.php?status=ok&tipo=" . urlencode($tipo));
    exit();
} else {
    header("Location: subir_archivo.php?status=error");
    exit();
}


// Carpeta donde se guardarán los archivos
$targetDir = "uploads/";

// Crear carpeta si no existe
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$fileName = basename($_FILES["archivo"]["name"]);
$fileTmp  = $_FILES["archivo"]["tmp_name"];
$fileSize = $_FILES["archivo"]["size"];

// Extensiones permitidas
$allowedExtensions = ["pdf","doc","docx","xls","xlsx","jpg","jpeg","png","gif","mp4","avi","mkv"];
$allowedMimeTypes  = [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "image/jpeg",
    "image/png",
    "image/gif",
    "video/mp4",
    "video/x-msvideo",
    "video/x-matroska"
];

// Extraer extensión
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Validar extensión y MIME
$fileMime = mime_content_type($fileTmp);
if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileMime, $allowedMimeTypes)) {
    header("Location: subir_archivo.php?status=tipo_no_valido");
    exit();
}

// Validar tamaño (máx 50 MB)
if ($fileSize > 50 * 1024 * 1024) {
    header("Location: subir_archivo.php?status=archivo_grande");
    exit();
}

// Crear nombre único para evitar colisiones
$newName = uniqid("file_", true) . "." . $fileExtension;
$targetFilePath = $targetDir . $newName;

// Mover archivo
if (move_uploaded_file($fileTmp, $targetFilePath)) {
    // 🚀 OPCIONAL: Guardar en la base de datos
    // $stmt = $conn->prepare("INSERT INTO archivos (nombre_original, nombre_guardado, tipo, tamaño, fecha) VALUES (?, ?, ?, ?, NOW())");
    // $stmt->bind_param("sssi", $fileName, $newName, $fileMime, $fileSize);
    // $stmt->execute();
    // $stmt->close();

    header("Location: subir_archivo.php?status=ok");
    exit();
} else {
    header("Location: subir_archivo.php?status=error");
    exit();
}
?>
