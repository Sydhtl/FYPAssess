<?php
include '../../../php/mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];

if (!isset($_FILES['signature_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['signature_file'];
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

// Detect actual column names present
$idCol = 'Signature_StudentID';
$fileCol = 'Signature_StudentFile';
$schemaCheck = $conn->query("SHOW COLUMNS FROM signature_student");
if ($schemaCheck) {
    $cols = [];
    while ($c = $schemaCheck->fetch_assoc()) { $cols[] = $c['Field']; }
    // Detect ID column
    if (!in_array($idCol, $cols) && in_array('Signature_ID', $cols)) {
        $idCol = 'Signature_ID';
    }
    // Detect file/blob column (handle common misspelling 'Siganture_StudentFile')
    if (!in_array($fileCol, $cols)) {
        if (in_array('Signature_File', $cols)) {
            $fileCol = 'Signature_File';
        } else if (in_array('Siganture_StudentFile', $cols)) {
            $fileCol = 'Siganture_StudentFile';
        }
    }
    // If still missing critical columns, report what's available
    if (!in_array($idCol, $cols) || !in_array('Student_ID', $cols) || !in_array($fileCol, $cols)) {
        echo json_encode(['success'=>false,'error'=>'signature_student columns mismatch','columns_found'=>$cols,'expected_id'=>$idCol,'expected_file'=>$fileCol]);
        exit();
    }
}

$existsSql = "SELECT $idCol FROM signature_student WHERE Student_ID = ? LIMIT 1";
$existsStmt = $conn->prepare($existsSql);
if (!$existsStmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare exists failed: ' . $conn->error]);
    exit();
}
$existsStmt->bind_param('s', $studentId);
$existsStmt->execute();
$existsResult = $existsStmt->get_result();
$recordExists = $existsResult && $existsResult->num_rows > 0;
$existsStmt->close();

if ($recordExists) {
    $updateSql = "UPDATE signature_student SET $fileCol = ? WHERE Student_ID = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare update failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $updateStmt->bind_param('bs', $null, $studentId);
    $updateStmt->send_long_data(0, $blobData);
    $ok = $updateStmt->execute();
    $updateStmt->close();
} else {
    $insertSql = "INSERT INTO signature_student ($fileCol, Student_ID) VALUES (?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare insert failed: ' . $conn->error]);
        exit();
    }
    $null = NULL;
    $insertStmt->bind_param('bs', $null, $studentId);
    $insertStmt->send_long_data(0, $blobData);
    $ok = $insertStmt->execute();
    $insertStmt->close();
}

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit();
}

echo json_encode(['success' => true]);
$conn->close();
?>