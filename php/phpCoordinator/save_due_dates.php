<?php
include __DIR__ . '/../mysqlConnect.php';
require_once __DIR__ . '/../sendEmail.php';
require_once __DIR__ . '/../emailConfig.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Allow empty allocations array if only deletions are being sent
if (!isset($input['allocations']) || !is_array($input['allocations'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data format']);
    exit();
}

if (!isset($input['fyp_session_id']) || $input['fyp_session_id'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'FYP_Session_ID is required']);
    exit();
}

$fypSessionId = intval($input['fyp_session_id']);
    $deletions = isset($input['deletions']) && is_array($input['deletions']) ? $input['deletions'] : [];

try {
    $conn->begin_transaction();
    
    // First, handle deletions
    if (!empty($deletions)) {
        // Validate that all deletion IDs are integers
        $deletionIds = array_filter(array_map('intval', $deletions), function($id) {
            return $id > 0;
        });
        
        if (!empty($deletionIds)) {
            $placeholders = implode(',', array_fill(0, count($deletionIds), '?'));
            $deleteSql = "DELETE FROM due_date WHERE Due_ID IN ($placeholders)";
            $deleteStmt = $conn->prepare($deleteSql);
            if (!$deleteStmt) {
                throw new Exception('Prepare delete failed: ' . $conn->error);
            }
            
            $types = str_repeat('i', count($deletionIds));
            $deleteStmt->bind_param($types, ...$deletionIds);
            
            if (!$deleteStmt->execute()) {
                $deleteStmt->close();
                throw new Exception('Delete failed: ' . $conn->error);
            }
            $deleteStmt->close();
        }
    }
    
    // Then, handle insertions and updates
    foreach ($input['allocations'] as $allocation) {
        if (!isset($allocation['assessment_id'])) {
            continue;
        }
        
        $assessmentId = intval($allocation['assessment_id']);
        
        if (isset($allocation['due_dates']) && is_array($allocation['due_dates'])) {
            foreach ($allocation['due_dates'] as $dueDate) {
                if (empty($dueDate['start_date']) || empty($dueDate['end_date']) || 
                    empty($dueDate['start_time']) || empty($dueDate['end_time']) || 
                    empty($dueDate['role'])) {
                    continue;
                }
                
                $startDate = $dueDate['start_date'];
                $endDate = $dueDate['end_date'];
                $startTime = $dueDate['start_time'];
                $endTime = $dueDate['end_time'];
                $role = $dueDate['role'];
                $dueId = isset($dueDate['due_id']) ? intval($dueDate['due_id']) : 0;
                
                // Check if due_date already exists (if due_id is provided)
                if ($dueId > 0) {
                    // Update existing due_date
                    $updateSql = "UPDATE due_date SET Start_Date = ?, End_Date = ?, Start_Time = ?, End_Time = ?, Role = ? WHERE Due_ID = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    if (!$updateStmt) {
                        throw new Exception('Prepare update failed: ' . $conn->error);
                    }
                    $updateStmt->bind_param('sssssi', $startDate, $endDate, $startTime, $endTime, $role, $dueId);
                    if (!$updateStmt->execute()) {
                        $updateStmt->close();
                        throw new Exception('Update failed: ' . $conn->error);
                    }
                    $updateStmt->close();
                } else {
                    // Insert new due_date with Assessment_ID and FYP_Session_ID
                    $insertSql = "INSERT INTO due_date (Assessment_ID, FYP_Session_ID, Start_Date, End_Date, Start_Time, End_Time, Role) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if (!$insertStmt) {
                        throw new Exception('Prepare insert failed: ' . $conn->error);
                    }
                    $insertStmt->bind_param('iisssss', $assessmentId, $fypSessionId, $startDate, $endDate, $startTime, $endTime, $role);
                    
                    if (!$insertStmt->execute()) {
                        $insertStmt->close();
                        throw new Exception('Insert failed: ' . $conn->error);
                    }
                    $insertStmt->close();
                }
            }
        }
    }
    
    $conn->commit();
    
    // After successful save, send email notifications to all lecturers
    try {
        // Load email config
        $emailConfig = require __DIR__ . '/../emailConfig.php';
        
        // Get year and semester from FYP_Session
        $year = '';
        $semester = '';
        $yearSemesterQuery = "SELECT fs.FYP_Session, fs.Semester 
                             FROM fyp_session fs 
                             WHERE fs.FYP_Session_ID = ? 
                             LIMIT 1";
        $yearSemesterStmt = $conn->prepare($yearSemesterQuery);
        if ($yearSemesterStmt) {
            $yearSemesterStmt->bind_param('i', $fypSessionId);
            $yearSemesterStmt->execute();
            $yearSemesterResult = $yearSemesterStmt->get_result();
            if ($yearSemRow = $yearSemesterResult->fetch_assoc()) {
                $year = $yearSemRow['FYP_Session'];
                $semester = $yearSemRow['Semester'];
            }
            $yearSemesterStmt->close();
        }
        
        // Get coordinator's department ID
        $userId = $_SESSION['upmId'];
        $departmentId = null;
        $deptQuery = "SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1";
        $deptStmt = $conn->prepare($deptQuery);
        if ($deptStmt) {
            $deptStmt->bind_param('s', $userId);
            $deptStmt->execute();
            $deptResult = $deptStmt->get_result();
            if ($deptRow = $deptResult->fetch_assoc()) {
                $departmentId = $deptRow['Department_ID'];
            }
            $deptStmt->close();
        }
        
        if (!$departmentId) {
            error_log("Date time allocation emails: Could not determine department ID");
        } else {
            // Get all lecturers in the department
            $lecturers = [];
            $lecturerQuery = "SELECT Lecturer_ID, Lecturer_Name FROM lecturer WHERE Department_ID = ? ORDER BY Lecturer_Name";
            $lecturerStmt = $conn->prepare($lecturerQuery);
            if ($lecturerStmt) {
                $lecturerStmt->bind_param('i', $departmentId);
                $lecturerStmt->execute();
                $lecturerResult = $lecturerStmt->get_result();
                while ($lecRow = $lecturerResult->fetch_assoc()) {
                    $lecturers[] = [
                        'lecturer_id' => $lecRow['Lecturer_ID'],
                        'lecturer_name' => $lecRow['Lecturer_Name']
                    ];
                }
                $lecturerStmt->close();
            }
            
            // Get all due dates with assessment and course information
            $dueDatesDetails = [];
            $detailsQuery = "
                SELECT dd.Due_ID, dd.Start_Date, dd.End_Date, dd.Start_Time, dd.End_Time, dd.Role,
                       a.Assessment_Name, c.Course_Code
                FROM due_date dd
                INNER JOIN assessment a ON dd.Assessment_ID = a.Assessment_ID
                INNER JOIN course c ON a.Course_ID = c.Course_ID
                WHERE dd.FYP_Session_ID = ?
                ORDER BY c.Course_Code, a.Assessment_Name, dd.Role
            ";
            $detailsStmt = $conn->prepare($detailsQuery);
            if ($detailsStmt) {
                $detailsStmt->bind_param('i', $fypSessionId);
                $detailsStmt->execute();
                $detailsResult = $detailsStmt->get_result();
                while ($detailRow = $detailsResult->fetch_assoc()) {
                    $dueDatesDetails[] = $detailRow;
                }
                $detailsStmt->close();
            }
            
            // Build due dates list HTML
            $dueDatesListHtml = '';
            if (!empty($dueDatesDetails)) {
                $currentCourse = '';
                foreach ($dueDatesDetails as $detail) {
                    // Group by course
                    if ($currentCourse !== $detail['Course_Code']) {
                        if ($currentCourse !== '') {
                            $dueDatesListHtml .= '</ul></li>';
                        }
                        $currentCourse = $detail['Course_Code'];
                        $dueDatesListHtml .= '<li style="margin-bottom: 15px;"><strong>' . htmlspecialchars($currentCourse) . '</strong><ul style="margin: 5px 0; padding-left: 20px;">';
                    }
                    
                    // Format dates for display
                    $startDateObj = new DateTime($detail['Start_Date']);
                    $formattedStartDate = $startDateObj->format('d F Y');
                    $endDateObj = new DateTime($detail['End_Date']);
                    $formattedEndDate = $endDateObj->format('d F Y');
                    
                    // Format times for display
                    $startTimeObj = DateTime::createFromFormat('H:i:s', $detail['Start_Time']);
                    $formattedStartTime = $startTimeObj ? $startTimeObj->format('g:i A') : $detail['Start_Time'];
                    $endTimeObj = DateTime::createFromFormat('H:i:s', $detail['End_Time']);
                    $formattedEndTime = $endTimeObj ? $endTimeObj->format('g:i A') : $detail['End_Time'];
                    
                    $roleText = $detail['Role'] === 'Assessor' ? 'Assessor' : 'Supervisor';
                    
                    $dueDatesListHtml .= '<li style="margin-bottom: 10px;">';
                    $dueDatesListHtml .= '<strong>' . htmlspecialchars($detail['Assessment_Name']) . '</strong> (' . htmlspecialchars($roleText) . ')<br>';
                    $dueDatesListHtml .= 'Tarikh Mula: <strong>' . htmlspecialchars($formattedStartDate) . ' ' . htmlspecialchars($formattedStartTime) . '</strong><br>';
                    $dueDatesListHtml .= 'Tarikh Tamat: <strong>' . htmlspecialchars($formattedEndDate) . ' ' . htmlspecialchars($formattedEndTime) . '</strong>';
                    $dueDatesListHtml .= '</li>';
                }
                if ($currentCourse !== '') {
                    $dueDatesListHtml .= '</ul></li>';
                }
            } else {
                $dueDatesListHtml = '<p style="color: #999; font-style: italic;">Tiada tarikh dan masa yang ditetapkan.</p>';
            }
            
            // Send emails to all lecturers
            $emailResults = [];
            foreach ($lecturers as $lecturer) {
                // Construct lecturer email address
                $originalEmail = $lecturer['lecturer_id'] . '@upm.edu.my';
                
                // Use test email if configured
                if (!empty($emailConfig['test_email_recipient'])) {
                    $lecturerEmail = $emailConfig['test_email_recipient'];
                } else {
                    $lecturerEmail = $originalEmail;
                }
                
                // Create email subject and message
                $subject = "Maklumat Peruntukan Tarikh dan Masa - " . htmlspecialchars($year) . " / Semester " . htmlspecialchars($semester);
                
                // HTML email message
                $message = "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                           "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($lecturer['lecturer_name']) . ",</b><br><br>" .
                           "<b>MAKLUMAT PERUNTUKAN TARIKH DAN MASA</b><br><br>" .
                           "Sukacita dimaklumkan bahawa peruntukan tarikh dan masa untuk penilaian telah dikemaskini dalam sistem FYPAssess untuk sesi <b>" . htmlspecialchars($year) . " / Semester " . htmlspecialchars($semester) . "</b>.<br><br>" .
                           "<b>Senarai Tarikh dan Masa Penilaian:</b><br>" .
                           "<ul style=\"margin: 5px 0; padding-left: 20px;\">" .
                           $dueDatesListHtml .
                           "</ul><br>" .
                           "Sila log masuk ke sistem FYPAssess untuk melihat maklumat lanjut mengenai peruntukan tarikh dan masa.<br><br>" .
                           "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
                           "Sekian, terima kasih.<br><br>" .
                           "<b>\"MALAYSIA MADANI\"</b><br>" .
                           "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                           "Saya yang menjalankan amanah,<br><br>" .
                           "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                           "<b>Pembangun Sistem FYPAssess</b><br>" .
                           "<b>PutraAssess System</b><br>" .
                           "Universiti Putra Malaysia";
                
                // Send email
                $emailResult = sendEmail(
                    $lecturerEmail,
                    $subject,
                    $message,
                    'html'
                );
                
                $emailResults[] = [
                    'lecturer' => $lecturer['lecturer_name'],
                    'success' => $emailResult['success'],
                    'message' => $emailResult['message'] ?? ''
                ];
                
                // Log failures but don't stop the process
                if (!$emailResult['success']) {
                    error_log("Failed to send date time allocation notification to {$lecturer['lecturer_name']}: " . ($emailResult['message'] ?? 'Unknown error'));
                }
            }
            
            error_log("Date time allocation emails: Sent " . count($emailResults) . " email(s) to lecturers");
        }
    } catch (Exception $emailEx) {
        // Log email errors but don't fail the save operation
        error_log("Error sending date time allocation notification emails: " . $emailEx->getMessage());
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
?>
