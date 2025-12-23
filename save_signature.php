<?php
session_start();
include 'php/db_connect.php';
header('Content-Type: application/json');

// Get login ID
$loginID = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'hazura');

if (!isset($_FILES['signature_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['signature_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
    exit();
}

// Validate file type (images only)
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload JPG, PNG, or GIF']);
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

// Check if signature exists for this lecturer
$checkSql = "SELECT Signature_ID FROM signature_lecturer WHERE Lecturer_ID = ?";
$stmtCheck = $conn->prepare($checkSql);
if (!$stmtCheck) {
    echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
    exit();
}
$stmtCheck->bind_param("s", $loginID);
$stmtCheck->execute();
$result = $stmtCheck->get_result();
$recordExists = $result->num_rows > 0;
$stmtCheck->close();

if ($recordExists) {
    // Update existing signature
    $updateSql = "UPDATE signature_lecturer SET Signature_File = ? WHERE Lecturer_ID = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare update failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $updateStmt->bind_param('bs', $null, $loginID);
    $updateStmt->send_long_data(0, $blobData);
    $ok = $updateStmt->execute();
    $updateStmt->close();
} else {
    // Insert new signature
    $insertSql = "INSERT INTO signature_lecturer (Lecturer_ID, Signature_File) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare insert failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $insertStmt->bind_param('sb', $loginID, $null);
    $insertStmt->send_long_data(1, $blobData);
    $ok = $insertStmt->execute();
    $insertStmt->close();
}

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Database operation failed: ' . $conn->error]);
    exit();
}

echo json_encode(['success' => true]);
$conn->close();
?>