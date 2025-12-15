<?php
// submit_title_change.php
include '../../../php/mysqlConnect.php';
require_once __DIR__ . '/../../../php/sendEmail.php';
require_once __DIR__ . '/../../../php/emailConfig.php';
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

// Load email configuration
$emailConfig = require __DIR__ . '/../../../php/emailConfig.php';

// 1. Get Supervisor Info and Student Name
$supQuery = "SELECT l.Lecturer_ID, l.Lecturer_Name, s.Student_Name
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

$supervisorData = $supResult->fetch_assoc();
$supervisor = [
    'Lecturer_ID' => $supervisorData['Lecturer_ID'],
    'Lecturer_Name' => $supervisorData['Lecturer_Name']
];
$studentName = $supervisorData['Student_Name'] ?? $studentId;
$stmt->close();

// 2. Check if fyp_project record exists
$checkQuery = "SELECT Student_ID FROM fyp_project WHERE Student_ID = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$checkResult = $stmt->get_result();
$stmt->close();

if ($checkResult->num_rows === 0) {
    // Insert new record if it doesn't exist, set status to Waiting For Approval
    $insertQuery = "INSERT INTO fyp_project (Student_ID, Proposed_Title, Title_Status) VALUES (?, ?, 'Waiting For Approval')";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ss", $studentId, $newTitle);
    $success = $stmt->execute();
    $stmt->close();
} else {
    // Update existing record, set status to Waiting For Approval
    $updateQuery = "UPDATE fyp_project SET Proposed_Title = ?, Title_Status = 'Waiting For Approval' WHERE Student_ID = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $newTitle, $studentId);
    $success = $stmt->execute();
    $stmt->close();
}

if ($success) {
    // 3. Send email to supervisor
    // Construct supervisor email address (UPM format: Lecturer_ID@upm.edu.my)
    $originalEmail = $supervisor['Lecturer_ID'] . '@upm.edu.my';
    
    // Use test email if configured, otherwise use actual supervisor email
    if (!empty($emailConfig['test_email_recipient'])) {
        $lecturerEmail = $emailConfig['test_email_recipient'];
    } else {
        $lecturerEmail = $originalEmail;
    }
    
    // Create email subject and message
    $subject = "Permohonan Pertukaran Tajuk FYP -  (" . $studentId . ")";
    
    // HTML email message template
    $message = "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
               "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($supervisor['Lecturer_Name']) . ",</b><br><br>" .
               "<b>PERMOHONAN PERTUKARAN TAJUK FYP</b><br><br>" .
               "Sukacita dimaklumkan bahawa pelajar di bawah seliaan YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan telah membuat permohonan untuk menukar tajuk FYP mereka.<br><br>" .
               "<b>Maklumat Pelajar:</b><br>" .
               "Nama: " . htmlspecialchars($studentName) . "<br>" .
               "No. Matrik: " . htmlspecialchars($studentId) . "<br><br>" .
               "<b>Tajuk yang Dicadangkan:</b><br>" .
               "<div style='background-color: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0;'>" .
               htmlspecialchars($newTitle) . "</div><br>" .
               "Sila log masuk ke sistem FYPAssess untuk meluluskan atau menolak permohonan ini.<br><br>" .
               "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
               "Sekian, terima kasih.<br><br>" .
               "<b>\"MALAYSIA MADANI\"</b><br>" .
               "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
               "Saya yang menjalankan amanah,<br><br>" .
               "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
              "<b>Pembangun Sistem FYPAssess</b><br>" .
              "<b>PutraAssess System</b><br>" .
              "Universiti Putra Malaysia";
    
    // Send email using sendEmail function
    $emailResult = sendEmail(
        $lecturerEmail,
        $subject,
        $message,
        'html' // Use HTML format for the email
    );
    
    if ($emailResult['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Title change request submitted successfully. Email notification has been sent to your supervisor.',
            'email_sent' => true
        ]);
    } else {
        // Still return success for database update, but note email issue
        echo json_encode([
            'success' => true, 
            'message' => 'Title change request submitted successfully. However, email notification could not be sent: ' . $emailResult['message'],
            'email_sent' => false,
            'email_error' => $emailResult['message']
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$conn->close();
?>