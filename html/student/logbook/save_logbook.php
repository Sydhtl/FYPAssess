<?php
include '../../../php/mysqlConnect.php';
require_once __DIR__ . '/../../../php/sendEmail.php';
require_once __DIR__ . '/../../../php/emailConfig.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_POST['student_id'] ?? '';
$courseId = $_POST['course_id'] ?? null;
$logbookTitle = $_POST['logbook_title'] ?? '';
$logbookDate = $_POST['logbook_date'] ?? '';
$agendasJson = $_POST['agendas'] ?? '[]';

// Validate input
if (empty($studentId) || empty($courseId) || empty($logbookTitle) || empty($logbookDate)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Verify student matches session
if ($studentId !== $_SESSION['upmId']) {
    echo json_encode(['success' => false, 'error' => 'Student ID mismatch']);
    exit();
}

// Decode agendas early and validate at least one non-empty agenda
$agendas = json_decode($agendasJson, true);
if (!is_array($agendas)) { $agendas = []; }
$validAgendaCount = 0;
foreach ($agendas as $a) {
    $t = trim($a['name'] ?? '');
    $c = trim($a['explanation'] ?? '');
    if ($t !== '' && $c !== '') { $validAgendaCount++; }
}
if ($validAgendaCount === 0) {
    echo json_encode(['success' => false, 'error' => 'At least one agenda (title + explanation) is required']);
    exit();
}

// Resolve FYP session for this student & course (use most recent if multiple)
$fypSessionId = null;
$sessionLookup = $conn->prepare(
    "SELECT s.FYP_Session_ID
     FROM student s
     JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
     WHERE s.Student_ID = ? AND fs.Course_ID = ?
     ORDER BY fs.FYP_Session_ID DESC
     LIMIT 1"
);
if ($sessionLookup) {
    $sessionLookup->bind_param('si', $studentId, $courseId);
    if ($sessionLookup->execute()) {
        $sessionRes = $sessionLookup->get_result();
        if ($sessionRes && $sessionRes->num_rows > 0) {
            $fypSessionId = (int)$sessionRes->fetch_assoc()['FYP_Session_ID'];
        }
    }
    $sessionLookup->close();
}

if (empty($fypSessionId)) {
    echo json_encode(['success' => false, 'error' => 'Unable to resolve FYP session for this course. Please contact administrator.']);
    exit();
}

// Load email configuration
$emailConfig = require __DIR__ . '/../../../php/emailConfig.php';

// Get Supervisor Info, Student Name, and Course Code for email notification
$supQuery = "SELECT l.Lecturer_ID, l.Lecturer_Name, s.Student_Name, c.Course_Code
             FROM student s 
             JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID 
             JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
             JOIN course c ON c.Course_ID = ?
             WHERE s.Student_ID = ?";
$stmtSup = $conn->prepare($supQuery);
$supervisorData = null;
$studentName = null;
$courseCode = null;

if ($stmtSup) {
    $stmtSup->bind_param("is", $courseId, $studentId);
    $stmtSup->execute();
    $supResult = $stmtSup->get_result();
    if ($supResult->num_rows > 0) {
        $supervisorData = $supResult->fetch_assoc();
        $studentName = $supervisorData['Student_Name'] ?? $studentId;
        $courseCode = $supervisorData['Course_Code'] ?? 'N/A';
    }
    $stmtSup->close();
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO logbook (Student_ID, course_id, Fyp_Session_ID, Logbook_Name, Logbook_Date, Logbook_Status) VALUES (?, ?, ?, ?, ?, 'Waiting for approval')");
    if (!$stmt) { throw new Exception("Prepare failed: " . $conn->error); }
    $stmt->bind_param("siiss", $studentId, $courseId, $fypSessionId, $logbookTitle, $logbookDate);
    if (!$stmt->execute()) { throw new Exception("Execute failed: " . $stmt->error); }
    $logbookId = $conn->insert_id;
    $stmt->close();
    $stmtAgenda = $conn->prepare("INSERT INTO logbook_agenda (Logbook_ID, Agenda_Title, Agenda_Content) VALUES (?, ?, ?)");
    if (!$stmtAgenda) { throw new Exception("Agenda prepare failed: " . $conn->error); }
    foreach ($agendas as $agenda) {
        $agendaTitle = trim($agenda['name'] ?? '');
        $agendaContent = trim($agenda['explanation'] ?? '');
        if ($agendaTitle !== '' && $agendaContent !== '') {
            $stmtAgenda->bind_param("iss", $logbookId, $agendaTitle, $agendaContent);
            if (!$stmtAgenda->execute()) { throw new Exception("Agenda execute failed: " . $stmtAgenda->error); }
        }
    }
    $stmtAgenda->close();
    $conn->commit();
    
    // Send email notification to supervisor after successful save
    if ($supervisorData) {
        // Construct supervisor email address (UPM format: Lecturer_ID@upm.edu.my)
        $originalEmail = $supervisorData['Lecturer_ID'] . '@upm.edu.my';
        
        // Use test email if configured, otherwise use actual supervisor email
        if (!empty($emailConfig['test_email_recipient'])) {
            $lecturerEmail = $emailConfig['test_email_recipient'];
        } else {
            $lecturerEmail = $originalEmail;
        }
        
        // Create email subject and message
        $subject = "Notis Penghantaran Logbook - " . htmlspecialchars($studentName) . " (" . htmlspecialchars($studentId) . ")";
        
        // Build agenda list for email
        $agendaListHtml = '';
        if (!empty($agendas) && is_array($agendas)) {
            $validAgendas = array_filter($agendas, function($a) {
                return !empty(trim($a['name'] ?? '')) && !empty(trim($a['explanation'] ?? ''));
            });
            if (!empty($validAgendas)) {
                $agendaListHtml = '<ul style="margin: 10px 0; padding-left: 20px;">';
                foreach ($validAgendas as $index => $agenda) {
                    $agendaListHtml .= '<li style="margin-bottom: 10px;">' .
                                       '<strong>' . htmlspecialchars($agenda['name']) . ':</strong><br>' .
                                       '<span style="color: #666;">' . htmlspecialchars($agenda['explanation']) . '</span>' .
                                       '</li>';
                }
                $agendaListHtml .= '</ul>';
            }
        }
        
        if (empty($agendaListHtml)) {
            $agendaListHtml = '<p style="color: #999; font-style: italic;">Tiada agenda ditambah.</p>';
        }
        
        // HTML email message template
        $message = "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                   "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($supervisorData['Lecturer_Name']) . ",</b><br><br>" .
                   "<b>NOTIS PENGHANTARAN LOGBOOK</b><br><br>" .
                   "Sukacita dimaklumkan bahawa pelajar di bawah seliaan YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan telah menghantar logbook baharu melalui sistem FYPAssess.<br><br>" .
                   "<b>Maklumat Pelajar:</b><br>" .
                   "Nama: " . htmlspecialchars($studentName) . "<br>" .
                   "No. Matrik: " . htmlspecialchars($studentId) . "<br>" .
                   "Kod Kursus: " . htmlspecialchars($courseCode) . "<br><br>" .
                   "<b>Maklumat Logbook:</b><br>" .
                   "Tajuk Logbook: <strong>" . htmlspecialchars($logbookTitle) . "</strong><br>" .
                   "Tarikh: " . htmlspecialchars($logbookDate) . "<br><br>" .
                   "<b>Agenda Logbook:</b><br>" .
                   $agendaListHtml .
                   "<br>Sila log masuk ke sistem FYPAssess untuk menyemak dan meluluskan logbook ini.<br><br>" .
                   "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
                   "Sekian, terima kasih.<br><br>" .
                   "<b>\"MALAYSIA MADANI\"</b><br>" .
                   "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                   "Saya yang menjalankan amanah,<br><br>" .
                   "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                   "<b>Pembangun Sistem FYPAssess</b><br>" .
                   "Universiti Putra Malaysia";
        
        // Send email using sendEmail function
        $emailResult = sendEmail(
            $lecturerEmail,
            $subject,
            $message,
            'html' // Use HTML format for the email
        );
        
        // Log email result but don't fail the save if email fails
        if (!$emailResult['success']) {
            error_log("Failed to send logbook notification email: " . $emailResult['message']);
        }
    }
    
    echo json_encode(['success' => true, 'logbook_id' => $logbookId]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
