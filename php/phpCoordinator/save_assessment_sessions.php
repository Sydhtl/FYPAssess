<?php
include '../mysqlConnect.php';
require_once __DIR__ . '/../sendEmail.php';
require_once __DIR__ . '/../emailConfig.php';
session_start();

// Ensure only Coordinators can save assessment sessions
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['sessions']) || !is_array($input['sessions'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input payload']);
    exit();
}

$sessions = $input['sessions'];
$year = $input['year'] ?? '';
$semester = $input['semester'] ?? '';

// Log received data
error_log("=== SAVE ASSESSMENT SESSIONS START ===");
error_log("Received " . count($sessions) . " session(s)");
error_log("Year: $year, Semester: $semester");

if (empty($sessions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No assessment sessions provided']);
    exit();
}

// Before transaction: Capture OLD state for comparison
// This will be used to detect changes and determine if emails should be sent
$oldAssessorAssignments = [];

// Get all student IDs from input to check old state
$allStudentIds = array_map(function($session) {
    return $session['student_id'];
}, $sessions);

// Get OLD assessment session assignments before saving
if (!empty($allStudentIds)) {
    $placeholders = implode(',', array_fill(0, count($allStudentIds), '?'));
    $oldSessionQuery = "
        SELECT ss.Student_ID, ass.Date, ass.Time, ass.Venue,
               se.Assessor_ID_1, se.Assessor_ID_2,
               l1.Lecturer_ID as Assessor1_Lecturer_ID,
               l1.Lecturer_Name as Assessor1_Name,
               l2.Lecturer_ID as Assessor2_Lecturer_ID,
               l2.Lecturer_Name as Assessor2_Name
        FROM student_session ss
        INNER JOIN assessment_session ass ON ss.Session_ID = ass.Session_ID
        INNER JOIN student s ON ss.Student_ID = s.Student_ID
        LEFT JOIN student_enrollment se ON ss.Student_ID = se.Student_ID AND se.Fyp_Session_ID = s.FYP_Session_ID
        LEFT JOIN assessor a1 ON se.Assessor_ID_1 = a1.Assessor_ID
        LEFT JOIN lecturer l1 ON a1.Lecturer_ID = l1.Lecturer_ID
        LEFT JOIN assessor a2 ON se.Assessor_ID_2 = a2.Assessor_ID
        LEFT JOIN lecturer l2 ON a2.Lecturer_ID = l2.Lecturer_ID
        WHERE ss.Student_ID IN ($placeholders)
    ";
    $stmtOldSession = $conn->prepare($oldSessionQuery);
    if ($stmtOldSession) {
        $types = str_repeat('s', count($allStudentIds));
        $stmtOldSession->bind_param($types, ...$allStudentIds);
        $stmtOldSession->execute();
        $oldSessionResult = $stmtOldSession->get_result();
        
        while ($oldRow = $oldSessionResult->fetch_assoc()) {
            // Build old assignments structure
            if (!empty($oldRow['Assessor1_Lecturer_ID'])) {
                $lecId = $oldRow['Assessor1_Lecturer_ID'];
                if (!isset($oldAssessorAssignments[$lecId])) {
                    $oldAssessorAssignments[$lecId] = [];
                }
                $oldAssessorAssignments[$lecId][] = [
                    'student_id' => $oldRow['Student_ID'],
                    'date' => $oldRow['Date'],
                    'time' => $oldRow['Time'],
                    'venue' => $oldRow['Venue']
                ];
            }
            if (!empty($oldRow['Assessor2_Lecturer_ID'])) {
                $lecId = $oldRow['Assessor2_Lecturer_ID'];
                if (!isset($oldAssessorAssignments[$lecId])) {
                    $oldAssessorAssignments[$lecId] = [];
                }
                $oldAssessorAssignments[$lecId][] = [
                    'student_id' => $oldRow['Student_ID'],
                    'date' => $oldRow['Date'],
                    'time' => $oldRow['Time'],
                    'venue' => $oldRow['Venue']
                ];
            }
        }
        $stmtOldSession->close();
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Get FYP_Session_IDs for the selected year and semester
    $fypSessionIds = [];
    if (!empty($year) && !empty($semester)) {
        $fypSessionQuery = "SELECT DISTINCT FYP_Session_ID FROM fyp_session WHERE FYP_Session = ? AND Semester = ?";
        $fypStmt = $conn->prepare($fypSessionQuery);
        if ($fypStmt) {
            $fypStmt->bind_param("si", $year, $semester);
            $fypStmt->execute();
            $fypResult = $fypStmt->get_result();
            while ($row = $fypResult->fetch_assoc()) {
                $fypSessionIds[] = $row['FYP_Session_ID'];
            }
            $fypStmt->close();
        }
    }
    
    // Delete existing student_session rows ONLY for the (Student_ID, FYP_Session_ID) pairs we will rewrite.
    // This prevents wiping other sessions the student has in different FYP_Session_IDs.
    $targetStudentSessions = [];
    foreach ($sessions as $session) {
        if (!empty($session['student_id']) && !empty($session['fyp_session_id'])) {
            $key = $session['student_id'] . '|' . $session['fyp_session_id'];
            $targetStudentSessions[$key] = [
                'student_id' => $session['student_id'],
                'fyp_session_id' => $session['fyp_session_id']
            ];
        }
    }

    if (!empty($targetStudentSessions)) {
        $deleteStmt = $conn->prepare("DELETE FROM student_session WHERE Student_ID = ? AND FYP_Session_ID = ?");
        if ($deleteStmt) {
            foreach ($targetStudentSessions as $pair) {
                $deleteStmt->bind_param("si", $pair['student_id'], $pair['fyp_session_id']);
                $deleteStmt->execute();
            }
            $deleteStmt->close();
        }
    }
    
    // Filter sessions to only include those with date, time, and venue
    // Sessions without date/time/venue are treated as deletions (already handled above)
    $validSessions = array_filter($sessions, function($session) {
        return !empty($session['date']) && !empty($session['time']) && !empty($session['venue']);
    });
    
    error_log("Valid sessions (with date/time/venue): " . count($validSessions));
    
    // If no valid sessions, just commit the deletions
    if (empty($validSessions)) {
        $conn->commit();
        error_log("No valid sessions to process. Committing deletions only.");
        echo json_encode([
            'success' => true,
            'message' => "Assessment sessions saved successfully. Removed all student session assignments."
        ]);
        $conn->close();
        exit();
    }
    
    // Group sessions by date, time, venue, course_id, and FYP_Session_ID to create assessment_session records
    // Each unique combination gets one assessment_session record scoped to the FYP session
    $sessionGroups = [];
    foreach ($validSessions as $session) {
        $key = $session['date'] . '|' . $session['time'] . '|' . $session['venue'] . '|' . $session['course_id'] . '|' . $session['fyp_session_id'];
        if (!isset($sessionGroups[$key])) {
            $sessionGroups[$key] = [
                'date' => $session['date'],
                'time' => $session['time'],
                'venue' => $session['venue'],
                'course_id' => $session['course_id'],
                'fyp_session_id' => $session['fyp_session_id'],
                'students' => []
            ];
        }
        $sessionGroups[$key]['students'][] = $session['student_id'];
    }
    
    error_log("Created " . count($sessionGroups) . " session group(s) from valid sessions");
    
    $processedSessions = 0;
    $processedStudents = 0;
    $processedAssessors = 0;
    
    // Process each session group
    foreach ($sessionGroups as $groupKey => $group) {
        $date = $group['date'];
        $time = $group['time'];
        $venue = $group['venue'];
        $courseId = $group['course_id'];
        $studentIds = $group['students'];
        
        // Get Assessment_ID for this course (get the first assessment for the course)
        $assessmentId = null;
        $assessmentQuery = "SELECT Assessment_ID FROM assessment WHERE Course_ID = ? LIMIT 1";
        $assessmentStmt = $conn->prepare($assessmentQuery);
        if ($assessmentStmt) {
            $assessmentStmt->bind_param("i", $courseId);
            $assessmentStmt->execute();
            $assessmentResult = $assessmentStmt->get_result();
            if ($assessmentRow = $assessmentResult->fetch_assoc()) {
                $assessmentId = $assessmentRow['Assessment_ID'];
            }
            $assessmentStmt->close();
        }
        
        if (!$assessmentId) {
            // Skip if no assessment found for this course
            continue;
        }
        
        // Use the group's FYP_Session_ID (already grouped by fyp_session_id above)
        $groupFypSessionId = $group['fyp_session_id'] ?? null;
        if (!$groupFypSessionId) {
            // Skip if no FYP_Session_ID found
            continue;
        }
        
        // Check if assessment_session already exists for this date, time, venue, assessment AND FYP_Session_ID
        $checkSessionQuery = "SELECT Session_ID FROM assessment_session 
                     WHERE Assessment_ID = ? AND Date = ? AND Time = ? AND Venue = ? AND FYP_Session_ID = ?
                     LIMIT 1";
        $checkSessionStmt = $conn->prepare($checkSessionQuery);
        if (!$checkSessionStmt) {
            throw new Exception('Prepare failed (checkSession): ' . $conn->error);
        }
        
        $checkSessionStmt->bind_param("isssi", $assessmentId, $date, $time, $venue, $groupFypSessionId);
        $checkSessionStmt->execute();
        $sessionResult = $checkSessionStmt->get_result();
        $sessionId = null;
        
        if ($sessionRow = $sessionResult->fetch_assoc()) {
            $sessionId = $sessionRow['Session_ID'];
        }
        $checkSessionStmt->close();
        
        // Create or use existing assessment_session
        if (!$sessionId) {
            // Log the attempt to insert
            error_log("Attempting to insert assessment_session: Assessment_ID=$assessmentId, Date=$date, Time=$time, Venue=$venue, FYP_Session_ID=$groupFypSessionId");
            
            $insertSessionQuery = "INSERT INTO assessment_session (Assessment_ID, Date, Time, Venue, FYP_Session_ID) 
                                  VALUES (?, ?, ?, ?, ?)";
            $insertSessionStmt = $conn->prepare($insertSessionQuery);
            if (!$insertSessionStmt) {
                $error = 'Prepare failed (insertSession): ' . $conn->error;
                error_log($error);
                throw new Exception($error);
            }
            
            $insertSessionStmt->bind_param("isssi", $assessmentId, $date, $time, $venue, $groupFypSessionId);
            if (!$insertSessionStmt->execute()) {
                $error = 'Execute failed (insertSession): ' . $insertSessionStmt->error;
                error_log($error);
                throw new Exception($error);
            }
            
            $sessionId = $insertSessionStmt->insert_id;
            error_log("Successfully inserted assessment_session with Session_ID=$sessionId");
            $insertSessionStmt->close();
            $processedSessions++;
        } else {
            error_log("Reusing existing assessment_session with Session_ID=$sessionId");
        }
        
        // Process each student in this session
        // Since we already deleted all existing student_session records for these students,
        // we can now safely insert new ones
        foreach ($studentIds as $studentId) {
            // Use the student's specific FYP_Session_ID from payload to avoid cross-session mixing
            $studentFypSessionId = null;
            foreach ($validSessions as $s) {
                if ($s['student_id'] === $studentId && $s['course_id'] == $courseId) {
                    $studentFypSessionId = $s['fyp_session_id'] ?? null;
                    break;
                }
            }
            
            if (!$studentFypSessionId) {
                // Skip this student if no FYP_Session_ID found for this course
                continue;
            }
            
            // Insert new student_session record with FYP_Session_ID
            $insertStudentQuery = "INSERT INTO student_session (Session_ID, Student_ID, FYP_Session_ID) 
                                  VALUES (?, ?, ?)";
            $insertStudentStmt = $conn->prepare($insertStudentQuery);
            if (!$insertStudentStmt) {
                throw new Exception('Prepare failed (insertStudent): ' . $conn->error);
            }
            
            $insertStudentStmt->bind_param("isi", $sessionId, $studentId, $studentFypSessionId);
            if (!$insertStudentStmt->execute()) {
                throw new Exception('Execute failed (insertStudent): ' . $insertStudentStmt->error);
            }
            
            $insertStudentStmt->close();
            $processedStudents++;
            
            // Get Assessor_ID_1 and Assessor_ID_2 from student_enrollment
            // We already have the FYP_Session_ID for this student
            
            if ($studentFypSessionId) {
                // Get assessor IDs from student_enrollment
                $enrollmentQuery = "SELECT Assessor_ID_1, Assessor_ID_2 FROM student_enrollment 
                                   WHERE Student_ID = ? AND Fyp_Session_ID = ? 
                                   LIMIT 1";
                $enrollmentStmt = $conn->prepare($enrollmentQuery);
                if ($enrollmentStmt) {
                    $enrollmentStmt->bind_param("si", $studentId, $studentFypSessionId);
                    $enrollmentStmt->execute();
                    $enrollmentResult = $enrollmentStmt->get_result();
                    
                    if ($enrollmentRow = $enrollmentResult->fetch_assoc()) {
                        $assessorId1 = $enrollmentRow['Assessor_ID_1'];
                        $assessorId2 = $enrollmentRow['Assessor_ID_2'];
                        
                        // Insert assessor_session for Assessor_ID_1 if exists
                        if ($assessorId1) {
                            $checkAssessor1Query = "SELECT Session_ID FROM assessor_session 
                                                    WHERE Session_ID = ? AND Assessor_ID = ? AND FYP_Session_ID = ?
                                                    LIMIT 1";
                            $checkAssessor1Stmt = $conn->prepare($checkAssessor1Query);
                            if ($checkAssessor1Stmt) {
                                $checkAssessor1Stmt->bind_param("iii", $sessionId, $assessorId1, $studentFypSessionId);
                                $checkAssessor1Stmt->execute();
                                $assessor1Result = $checkAssessor1Stmt->get_result();
                                $checkAssessor1Stmt->close();
                                
                                if ($assessor1Result->num_rows == 0) {
                                    $insertAssessor1Query = "INSERT INTO assessor_session (Session_ID, Assessor_ID, FYP_Session_ID) 
                                                            VALUES (?, ?, ?)";
                                    $insertAssessor1Stmt = $conn->prepare($insertAssessor1Query);
                                    if ($insertAssessor1Stmt) {
                                        $insertAssessor1Stmt->bind_param("iii", $sessionId, $assessorId1, $studentFypSessionId);
                                        if ($insertAssessor1Stmt->execute()) {
                                            $processedAssessors++;
                                        }
                                        $insertAssessor1Stmt->close();
                                    }
                                }
                            }
                        }
                        
                        // Insert assessor_session for Assessor_ID_2 if exists
                        if ($assessorId2) {
                            $checkAssessor2Query = "SELECT Session_ID FROM assessor_session 
                                                    WHERE Session_ID = ? AND Assessor_ID = ? AND FYP_Session_ID = ?
                                                    LIMIT 1";
                            $checkAssessor2Stmt = $conn->prepare($checkAssessor2Query);
                            if ($checkAssessor2Stmt) {
                                $checkAssessor2Stmt->bind_param("iii", $sessionId, $assessorId2, $studentFypSessionId);
                                $checkAssessor2Stmt->execute();
                                $assessor2Result = $checkAssessor2Stmt->get_result();
                                $checkAssessor2Stmt->close();
                                
                                if ($assessor2Result->num_rows == 0) {
                                    $insertAssessor2Query = "INSERT INTO assessor_session (Session_ID, Assessor_ID, FYP_Session_ID) 
                                                            VALUES (?, ?, ?)";
                                    $insertAssessor2Stmt = $conn->prepare($insertAssessor2Query);
                                    if ($insertAssessor2Stmt) {
                                        $insertAssessor2Stmt->bind_param("iii", $sessionId, $assessorId2, $studentFypSessionId);
                                        if ($insertAssessor2Stmt->execute()) {
                                            $processedAssessors++;
                                        }
                                        $insertAssessor2Stmt->close();
                                    }
                                }
                            }
                        }
                    }
                    $enrollmentStmt->close();
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    error_log("Transaction committed successfully");
    error_log("Processed: $processedSessions session(s), $processedStudents student(s), $processedAssessors assessor(s)");
    
    // After successful save, send email notifications to assessors
    try {
        // Load email config
        $emailConfig = require __DIR__ . '/../emailConfig.php';
        
        // Collect assessor assignments: map assessor_id -> list of students with session details
        $assessorAssignments = [];
        
        // Get all valid sessions with assessor information
        foreach ($validSessions as $session) {
            $studentId = $session['student_id'];
            $studentName = $session['student_name'] ?? '';
            $date = $session['date'];
            $time = $session['time'];
            $venue = $session['venue'];
            $courseId = $session['course_id'] ?? null;
            
            // Get FYP_Session_ID for this student directly from student table
            // Don't rely on $fypSessionIds array - query directly
            $studentFypSessionId = null;
            $studentQuery = "SELECT FYP_Session_ID FROM student WHERE Student_ID = ? LIMIT 1";
            $studentStmt = $conn->prepare($studentQuery);
            if ($studentStmt) {
                $studentStmt->bind_param("s", $studentId);
                $studentStmt->execute();
                $studentFypResult = $studentStmt->get_result();
                if ($studentFypRow = $studentFypResult->fetch_assoc()) {
                    $studentFypSessionId = $studentFypRow['FYP_Session_ID'];
                }
                $studentStmt->close();
            }
            
            if ($studentFypSessionId) {
                // Get assessor IDs and lecturer information from student_enrollment
                $enrollmentQuery = "
                    SELECT se.Assessor_ID_1, se.Assessor_ID_2,
                           l1.Lecturer_ID as Assessor1_Lecturer_ID,
                           l1.Lecturer_Name as Assessor1_Name,
                           l2.Lecturer_ID as Assessor2_Lecturer_ID,
                           l2.Lecturer_Name as Assessor2_Name
                    FROM student_enrollment se
                    LEFT JOIN assessor a1 ON se.Assessor_ID_1 = a1.Assessor_ID
                    LEFT JOIN lecturer l1 ON a1.Lecturer_ID = l1.Lecturer_ID
                    LEFT JOIN assessor a2 ON se.Assessor_ID_2 = a2.Assessor_ID
                    LEFT JOIN lecturer l2 ON a2.Lecturer_ID = l2.Lecturer_ID
                    WHERE se.Student_ID = ? AND se.Fyp_Session_ID = ?
                    LIMIT 1
                ";
                $enrollmentStmt = $conn->prepare($enrollmentQuery);
                if ($enrollmentStmt) {
                    $enrollmentStmt->bind_param("si", $studentId, $studentFypSessionId);
                    $enrollmentStmt->execute();
                    $enrollmentResult = $enrollmentStmt->get_result();
                    
                    if ($enrollmentRow = $enrollmentResult->fetch_assoc()) {
                        // Process Assessor 1
                        if (!empty($enrollmentRow['Assessor1_Lecturer_ID'])) {
                            $lecId = $enrollmentRow['Assessor1_Lecturer_ID'];
                            $lecName = $enrollmentRow['Assessor1_Name'];
                            
                            if (!isset($assessorAssignments[$lecId])) {
                                $assessorAssignments[$lecId] = [
                                    'lecturer_id' => $lecId,
                                    'lecturer_name' => $lecName,
                                    'students' => []
                                ];
                            }
                            
                            $assessorAssignments[$lecId]['students'][] = [
                                'student_id' => $studentId,
                                'student_name' => $studentName,
                                'date' => $date,
                                'time' => $time,
                                'venue' => $venue
                            ];
                        }
                        
                        // Process Assessor 2
                        if (!empty($enrollmentRow['Assessor2_Lecturer_ID'])) {
                            $lecId = $enrollmentRow['Assessor2_Lecturer_ID'];
                            $lecName = $enrollmentRow['Assessor2_Name'];
                            
                            if (!isset($assessorAssignments[$lecId])) {
                                $assessorAssignments[$lecId] = [
                                    'lecturer_id' => $lecId,
                                    'lecturer_name' => $lecName,
                                    'students' => []
                                ];
                            }
                            
                            $assessorAssignments[$lecId]['students'][] = [
                                'student_id' => $studentId,
                                'student_name' => $studentName,
                                'date' => $date,
                                'time' => $time,
                                'venue' => $venue
                            ];
                        }
                    } else {
                        error_log("Assessment session emails: No enrollment found for student {$studentId} with FYP_Session_ID {$studentFypSessionId}");
                    }
                    $enrollmentStmt->close();
                } else {
                    error_log("Assessment session emails: Failed to prepare enrollment query for student {$studentId}");
                }
            } else {
                error_log("Assessment session emails: No FYP_Session_ID found for student {$studentId}");
            }
        }
        
        // Determine if this is first distribution
        // First distribution: no assessors had student assessment sessions before (oldAssessorAssignments is empty)
        $isFirstDistribution = empty($oldAssessorAssignments);
        
        // Find assessors with changes (students added/removed OR date/time/venue changed)
        $assessorsWithChanges = [];
        foreach ($assessorAssignments as $lecId => $lecData) {
            if (empty($lecData['lecturer_name']) || empty($lecData['students'])) {
                continue;
            }
            
            $hasChanges = false;
            
            // Get old assignments for this lecturer
            $oldStudents = isset($oldAssessorAssignments[$lecId]) ? $oldAssessorAssignments[$lecId] : [];
            
            // Build normalized comparison structures
            // Old: student_id -> {date, time, venue}
            $oldStudentMap = [];
            foreach ($oldStudents as $oldStudent) {
                $oldStudentMap[$oldStudent['student_id']] = [
                    'date' => $oldStudent['date'] ?? '',
                    'time' => $oldStudent['time'] ?? '',
                    'venue' => $oldStudent['venue'] ?? ''
                ];
            }
            
            // New: student_id -> {date, time, venue}
            $newStudentMap = [];
            foreach ($lecData['students'] as $newStudent) {
                $newStudentMap[$newStudent['student_id']] = [
                    'date' => $newStudent['date'] ?? '',
                    'time' => $newStudent['time'] ?? '',
                    'venue' => $newStudent['venue'] ?? ''
                ];
            }
            
            // Check if any students were added or removed
            $oldStudentIds = array_keys($oldStudentMap);
            $newStudentIds = array_keys($newStudentMap);
            sort($oldStudentIds);
            sort($newStudentIds);
            if ($oldStudentIds != $newStudentIds) {
                $hasChanges = true;
            }
            
            // Check if any session details changed for existing students
            foreach ($newStudentMap as $studentId => $newDetails) {
                if (isset($oldStudentMap[$studentId])) {
                    $oldDetails = $oldStudentMap[$studentId];
                    if ($oldDetails['date'] != $newDetails['date'] ||
                        $oldDetails['time'] != $newDetails['time'] ||
                        $oldDetails['venue'] != $newDetails['venue']) {
                        $hasChanges = true;
                        break;
                    }
                }
            }
            
            // For first distribution, include all assessors with assignments
            if ($isFirstDistribution && !empty($lecData['students'])) {
                $hasChanges = true;
            }
            
            if ($hasChanges) {
                $assessorsWithChanges[$lecId] = $lecData;
            }
        }
        
        // Debug: Log how many assessors we found
        error_log("Assessment session emails: Found " . count($assessorAssignments) . " assessors with assignments");
        error_log("Assessment session emails: " . ($isFirstDistribution ? "First distribution" : "Update") . " - " . count($assessorsWithChanges) . " assessors with changes");
        
        // Send emails only to assessors with changes (or all if first distribution)
        $emailResults = [];
        foreach ($assessorsWithChanges as $lecId => $lecData) {
            if (empty($lecData['lecturer_name']) || empty($lecData['students'])) {
                error_log("Assessment session emails: Skipping lecturer {$lecId} - name or students empty");
                continue; // Skip if no name or no students
            }
            
            error_log("Assessment session emails: Sending to {$lecData['lecturer_name']} ({$lecId}) - " . count($lecData['students']) . " students");
            
            // Construct lecturer email address
            $originalEmail = $lecData['lecturer_id'] . '@upm.edu.my';
            
            // Use test email if configured
            if (!empty($emailConfig['test_email_recipient'])) {
                $lecturerEmail = $emailConfig['test_email_recipient'];
            } else {
                $lecturerEmail = $originalEmail;
            }
            
            // Build student list HTML
            $studentListHtml = '<ul style="margin: 5px 0; padding-left: 20px;">';
            foreach ($lecData['students'] as $student) {
                // Format date for display (e.g., "2024-01-15" -> "15 January 2024")
                $dateObj = new DateTime($student['date']);
                $formattedDate = $dateObj->format('d F Y');
                
                // Format time for display (e.g., "14:00" -> "2:00 PM")
                $timeObj = DateTime::createFromFormat('H:i', $student['time']);
                $formattedTime = $timeObj ? $timeObj->format('g:i A') : $student['time'];
                
                $studentListHtml .= '<li style="margin-bottom: 10px;">';
                $studentListHtml .= '<strong>' . htmlspecialchars($student['student_name']) . '</strong> (' . htmlspecialchars($student['student_id']) . ')<br>';
                $studentListHtml .= 'Tarikh: <strong>' . htmlspecialchars($formattedDate) . '</strong><br>';
                $studentListHtml .= 'Masa: <strong>' . htmlspecialchars($formattedTime) . '</strong><br>';
                $studentListHtml .= 'Tempat: <strong>' . htmlspecialchars($student['venue']) . '</strong>';
                $studentListHtml .= '</li>';
            }
            $studentListHtml .= '</ul>';
            
            // Create email subject and message
            $subject = "Maklumat Sesi Penilaian - " . htmlspecialchars($year) . " / Semester " . htmlspecialchars($semester);
            
            // HTML email message
            $message = "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                       "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($lecData['lecturer_name']) . ",</b><br><br>" .
                       "<b>MAKLUMAT SESI PENILAIAN</b><br><br>" .
                       "Sukacita dimaklumkan bahawa sesi penilaian untuk pelajar-pelajar yang akan dinilai oleh YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan telah ditetapkan dalam sistem FYPAssess.<br><br>" .
                       "<b>Senarai Pelajar yang Akan Dinilai:</b><br>" .
                       $studentListHtml . "<br>" .
                       "Sila log masuk ke sistem FYPAssess untuk melihat maklumat lanjut mengenai sesi penilaian.<br><br>" .
                       "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
                       "Sekian, terima kasih.<br><br>" .
                       "<b>\"MALAYSIA MADANI\"</b><br>" .
                       "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                       "Saya yang menjalankan amanah,<br><br>" .
                       "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                       "<b>Pembangun Sistem FYPAssess</b><br>" .
                      
                       "Universiti Putra Malaysia";
            
            // Send email
            $emailResult = sendEmail(
                $lecturerEmail,
                $subject,
                $message,
                'html'
            );
            
            $emailResults[] = [
                'lecturer' => $lecData['lecturer_name'],
                'success' => $emailResult['success'],
                'message' => $emailResult['message'] ?? ''
            ];
            
            // Log failures but don't stop the process
            if (!$emailResult['success']) {
                error_log("Failed to send assessment session notification to {$lecData['lecturer_name']}: " . ($emailResult['message'] ?? 'Unknown error'));
            } else {
                error_log("Successfully sent assessment session notification to {$lecData['lecturer_name']}");
            }
        }
        
        error_log("Assessment session emails: Processed " . count($emailResults) . " email(s) total");
    } catch (Exception $emailEx) {
        // Log email errors but don't fail the save operation
        error_log("Error sending assessment session notification emails: " . $emailEx->getMessage());
        error_log("Error stack trace: " . $emailEx->getTraceAsString());
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Assessment sessions saved successfully. Created/Updated {$processedSessions} session(s), linked {$processedStudents} student(s), and {$processedAssessors} assessor(s)."
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving assessment sessions: ' . $e->getMessage()
    ]);
    
    error_log('Error saving assessment sessions: ' . $e->getMessage());
}
?>
