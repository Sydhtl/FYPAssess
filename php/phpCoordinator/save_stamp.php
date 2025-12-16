<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$lecturerId = $_SESSION['upmId'];

if (!isset($_FILES['stamp_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['stamp_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error']);
    exit();
}

$blobData = file_get_contents($file['tmp_name']);
if ($blobData === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to read file']);
    exit();
}

if (strlen($blobData) > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large (>10MB)']);
    exit();
}

// Check if stamp already exists for this lecturer
$existsSql = "SELECT Stamp_ID FROM stamp WHERE Lecturer_ID = ? LIMIT 1";
$existsStmt = $conn->prepare($existsSql);
if (!$existsStmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare exists failed: ' . $conn->error]);
    exit();
}
$existsStmt->bind_param('s', $lecturerId);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
$recordExists = $existsResult && $existsResult->num_rows > 0;
$existsStmt->close();

if ($recordExists) {
    // Update existing stamp
    $updateSql = "UPDATE stamp SET Stamp_File = ? WHERE Lecturer_ID = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare update failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $updateStmt->bind_param('bs', $null, $lecturerId);
    $updateStmt->send_long_data(0, $blobData);
    $ok = $updateStmt->execute();
    $updateStmt->close();
} else {
    // Insert new stamp
    $insertSql = "INSERT INTO stamp (Stamp_File, Lecturer_ID) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare insert failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $insertStmt->bind_param('bs', $null, $lecturerId);
    $insertStmt->send_long_data(0, $blobData);
    $ok = $insertStmt->execute();
    $insertStmt->close();
}

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
    exit();
}

echo json_encode(['success' => true]);
$conn->close();
?>
