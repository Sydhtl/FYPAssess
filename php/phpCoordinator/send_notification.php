<?php
/**
 * Send Notification Email
 * 
 * This endpoint handles sending notification emails to lecturers
 * Used by the notify functionality in markSubmission.php
 */

include __DIR__ . '/../mysqlConnect.php';
include __DIR__ . '/../sendEmail.php';

// Load email config to check for test email
$emailConfig = require __DIR__ . '/../emailConfig.php';

session_start();
header('Content-Type: application/json');

// Ensure only Coordinators can send notifications
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

/**
 * Get email subject and message template based on page/context
 * 
 * @param string $page Page identifier (e.g., 'mark_submission', 'student_assignation', etc.)
 * @param string $lecturerName Lecturer's name
 * @param string $courseCode Course code
 * @param string $year Year/session
 * @param string $semester Semester
 * @return array ['subject' => string, 'message' => string]
 */
function getEmailTemplate($page, $lecturerName, $courseCode, $year, $semester) {
    $templates = [
        'mark_submission' => [
            'subject' => "Peringatan Untuk Segera Mengemaskini Markah Pelajar Bagi Kursus {$courseCode} ({$year}/{$semester})",
            'message' => "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                        "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan,</b><br><br>" .
                        "<b>PERINGATAN UNTUK KEMASKINI MARKAH PELAJAR</b><br><br>" .
                        "Sukacita dimaklumkan bahawa pihak kami ingin membuat peringatan berhubung dengan proses " .
                        "<b>pengemaskinian markah pelajar</b> bagi kursus yang berada di bawah seliaan " .
                        "YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan di dalam sistem yang telah ditetapkan.<br><br>" .
                        "Sehubungan dengan itu, kerjasama YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan adalah amat dihargai " .
                        "bagi memastikan markah pelajar dapat dikemaskini <b>dalam tempoh yang telah ditetapkan</b>, " .
                        "demi kelancaran proses penilaian akademik dan pengurusan pelajar.<br><br>" .
                        "Sekiranya pengemaskinian telah dibuat, pihak kami mengucapkan ribuan terima kasih atas " .
                        "kerjasama yang diberikan. Untuk sebarang pertanyaan atau bantuan lanjut, " .
                        "YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan boleh menghubungi pihak pentadbir sistem.<br><br>" .
                        "Sekian, terima kasih.<br><br>" .
                        "<b>\"MALAYSIA MADANI\"</b><br>" .
                        "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                        "Saya yang menjalankan amanah,<br><br>" .
                        "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                        "<b>Pembangun Sistem FYPAssess</b><br>" .
                        "Universiti Putra Malaysia"
        ],
        'student_assignation' => [
            'subject' => "FYPAssess Notification - Student Assignment Update for {$courseCode} ({$year}/{$semester})",
            'message' => "Dear {$lecturerName},\n\n" .
                        "There has been an update to student assignments for {$courseCode} ({$year}/{$semester}).\n\n" .
                        "Please log in to the FYPAssess system to review your assigned students.\n\n" .
                        "Thank you."
        ],
        'title_change' => [
            'subject' => "Permohonan Pertukaran Tajuk FYP - {$courseCode}",
            'message' => "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                        "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($lecturerName) . ",</b><br><br>" .
                        "<b>PERMOHONAN PERTUKARAN TAJUK FYP</b><br><br>" .
                        "Sukacita dimaklumkan bahawa pelajar di bawah seliaan YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan telah membuat permohonan untuk menukar tajuk FYP mereka.<br><br>" .
                        "Sila log masuk ke sistem FYPAssess untuk meluluskan atau menolak permohonan ini.<br><br>" .
                        "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
                        "Sekian, terima kasih.<br><br>" .
                        "<b>\"MALAYSIA MADANI\"</b><br>" .
                        "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                        "Saya yang menjalankan amanah,<br><br>" .
                         "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                        "<b>Pembangun Sistem FYPAssess</b><br>" .
                        "<b>ss System</b><br>" .
                        "Universiti Putra Malaysia"
        ],
        // Add more templates for other pages as needed
        'default' => [
            'subject' => "FYPAssess Notification - {$courseCode} ({$year}/{$semester})",
            'message' => "Dear {$lecturerName},\n\n" .
                        "This is a notification regarding {$courseCode} ({$year}/{$semester}).\n\n" .
                        "Please log in to the FYPAssess system for more details.\n\n" .
                        "Thank you."
        ]
    ];
    
    // Return template for the specified page, or default if page not found
    return $templates[$page] ?? $templates['default'];
}

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$lecturerName = $input['lecturer_name'] ?? '';
$courseCode = $input['course_code'] ?? '';
$year = $input['year'] ?? '';
$semester = $input['semester'] ?? '';
$message = $input['message'] ?? '';
$page = $input['page'] ?? 'mark_submission'; // Default to 'mark_submission' for backward compatibility

try {
    // Fetch lecturer email from database
    $lecturerQuery = "SELECT l.Lecturer_Name, l.Lecturer_ID 
                     FROM lecturer l 
                     WHERE l.Lecturer_Name = ? 
                     LIMIT 1";
    
    $stmt = $conn->prepare($lecturerQuery);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $lecturerName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Lecturer not found'
        ]);
        exit();
    }
    
    $lecturer = $result->fetch_assoc();
    $stmt->close();
    
    // Construct email address (UPM format: UPM_ID@upm.edu.my or Lecturer_ID@upm.edu.my)
    $originalEmail = $lecturer['Lecturer_ID'] . '@upm.edu.my';
    
    // Use test email if configured, otherwise use actual lecturer email
    if (!empty($emailConfig['test_email_recipient'])) {
        $lecturerEmail = $emailConfig['test_email_recipient'];
    } else {
        $lecturerEmail = $originalEmail;
    }
    
    // Get email template based on page/context
    // If custom message is provided, use it; otherwise use page-specific template
    if (empty($message)) {
        $emailTemplate = getEmailTemplate($page, $lecturerName, $courseCode, $year, $semester);
        $subject = $emailTemplate['subject'];
        $message = $emailTemplate['message'];
    } else {
        // If custom message provided but no subject, use default subject template
        $emailTemplate = getEmailTemplate($page, $lecturerName, $courseCode, $year, $semester);
        $subject = $emailTemplate['subject'];
    }
    
    // Send email - use HTML format for mark_submission since it contains HTML tags
    $emailResult = sendEmail(
        $lecturerEmail,
        $subject,
        $message,
        ($page === 'mark_submission' ? 'html' : 'text')
    );
    
    if ($emailResult['success']) {
        $response = [
            'success' => true,
            'message' => "Notification sent successfully to {$lecturerEmail}",
            'email_sent_to' => $lecturerEmail
        ];
        
        // Add test mode info if using test email
        if (!empty($emailConfig['test_email_recipient']) && $lecturerEmail === $emailConfig['test_email_recipient']) {
            $response['message'] .= " (TEST MODE - Original recipient: {$originalEmail})";
            $response['test_mode'] = true;
            $response['original_email'] = $originalEmail;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email: ' . $emailResult['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    
    error_log('Error sending notification: ' . $e->getMessage());
}

$conn->close();
?>