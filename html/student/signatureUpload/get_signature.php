<?php
include '../../../php/mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$studentId = $_SESSION['upmId'];

// Detect columns
$idCol = 'Signature_StudentID';
$fileCol = 'Signature_StudentFile';
$schemaCheck = $conn->query("SHOW COLUMNS FROM signature_student");
if ($schemaCheck) {
    $cols = [];
    while ($c = $schemaCheck->fetch_assoc()) { $cols[] = $c['Field']; }
    if (!in_array($idCol, $cols) && in_array('Signature_ID', $cols)) { $idCol = 'Signature_ID'; }
    if (!in_array($fileCol, $cols)) {
        if (in_array('Signature_File', $cols)) { $fileCol = 'Signature_File'; }
        elseif (in_array('Siganture_StudentFile', $cols)) { $fileCol = 'Siganture_StudentFile'; }
    }
    if (!in_array($fileCol, $cols) || !in_array('Student_ID', $cols)) {
        http_response_code(404);
        echo 'Signature not found';
        exit();
    }
}

$stmt = $conn->prepare("SELECT $fileCol FROM signature_student WHERE Student_ID = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Prepare failed';
    exit();
}
$stmt->bind_param('s', $studentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo 'Signature not found';
    exit();
}
$stmt->bind_result($blob);
$stmt->fetch();
$stmt->close();

// Try to detect mime type from binary
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $blob ? finfo_buffer($finfo, $blob) : 'application/octet-stream';
finfo_close($finfo);
if (!$mime) { $mime = 'application/octet-stream'; }

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="signature_' . $studentId . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $blob;
$conn->close();
?>