<?php
// submit_title_change.php
include '../../../php/mysqlConnect.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['upmId']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];
$newTitle = isset($_POST['title']) ? trim($_POST['title']) : '';

if (empty($newTitle)) {
    echo json_encode(['success' => false, 'message' => 'Title cannot be empty']);
    exit();
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// 1. Get Supervisor Info
$supQuery = "SELECT l.Lecturer_ID, l.Lecturer_Name 
             FROM student s 
             JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID 
             JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID 
             WHERE s.Student_ID = ?";
$stmt = $conn->prepare($supQuery);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
    exit();
}

$stmt->bind_param("s", $studentId);
$stmt->execute();
$supResult = $stmt->get_result();

if ($supResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No supervisor assigned']);
    exit();
}

$supervisor = $supResult->fetch_assoc();
$stmt->close();

// 2. Check if fyp_project record exists
$checkQuery = "SELECT Student_ID FROM fyp_project WHERE Student_ID = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$checkResult = $stmt->get_result();
$stmt->close();

if ($checkResult->num_rows === 0) {
    // Insert new record if it doesn't exist
    $insertQuery = "INSERT INTO fyp_project (Student_ID, Proposed_Title) VALUES (?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ss", $studentId, $newTitle);
    $success = $stmt->execute();
    $stmt->close();
} else {
    // Update existing record
    $updateQuery = "UPDATE fyp_project SET Proposed_Title = ? WHERE Student_ID = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $newTitle, $studentId);
    $success = $stmt->execute();
    $stmt->close();
}

if ($success) {
    // 3. Prepare email details
    $testUpmId = "214673"; 
    $lecturerEmail = $testUpmId . "@student.upm.edu.my";
    
    $subject = "FYP Title Change Request: " . $studentId;
    $message = "Dear " . $supervisor['Lecturer_Name'] . ",\n\n";
    $message .= "Student " . $studentId . " has requested to change their FYP title.\n\n";
    $message .= "Proposed Title:\n" . $newTitle . "\n\n";
    $message .= "Please log in to the system to Approve or Reject this request.\n";
    $headers = "From: no-reply@fypassess.upm.edu.my";

    // For localhost testing - just save the request successfully without sending email
    // In production with a proper mail server, uncomment the mail() function below
    // mail($lecturerEmail, $subject, $message, $headers);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Title change request submitted successfully',
        'email_to' => $lecturerEmail,
        'email_subject' => $subject,
        'note' => 'Email would be sent in production environment'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$conn->close();
?>