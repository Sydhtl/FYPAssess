<?php
// upload_signature.php - Alternative endpoint (same as save_signature.php in root)
session_start();
include '../db_connect.php';
header('Content-Type: application/json');

// Get login ID
$loginID = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'hazura');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if file is uploaded
    if (!isset($_FILES['signature_file']) && !isset($_FILES['signatureFile'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    // Support both field names
    $file = isset($_FILES['signature_file']) ? $_FILES['signature_file'] : $_FILES['signatureFile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
        exit;
    }

    // Validate File Type (Images only)
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload JPG, PNG, or GIF']);
        exit;
    }

    // Read File Content
    $fileContent = file_get_contents($file['tmp_name']);

    if ($fileContent === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to read file']);
        exit;
    }

    if (strlen($fileContent) > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large (>10MB)']);
        exit;
    }

    // Check if signature exists for this Lecturer
    $checkSql = "SELECT Signature_ID FROM signature_lecturer WHERE Lecturer_ID = ?";
    $stmtCheck = $conn->prepare($checkSql);
    if (!$stmtCheck) {
        echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }

    $stmtCheck->bind_param("s", $loginID);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    $recordExists = $result->num_rows > 0;
    $stmtCheck->close();

    if ($recordExists) {
        // UPDATE existing
        $sql = "UPDATE signature_lecturer SET Signature_File = ? WHERE Lecturer_ID = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare update failed: ' . $conn->error]);
            exit;
        }
        $null = NULL;
        $stmt->bind_param("bs", $null, $loginID);
        $stmt->send_long_data(0, $fileContent);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Signature updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        // INSERT new
        $sql = "INSERT INTO signature_lecturer (Lecturer_ID, Signature_File) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Prepare insert failed: ' . $conn->error]);
            exit;
        }
        $null = NULL;
        $stmt->bind_param("sb", $loginID, $null);
        $stmt->send_long_data(1, $fileContent);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Signature uploaded successfully.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database insert failed: ' . $stmt->error]);
        }
        $stmt->close();
    }

    $conn->close();

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>