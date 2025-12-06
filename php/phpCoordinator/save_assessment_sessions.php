<?php
include '../mysqlConnect.php';
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

if (empty($sessions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No assessment sessions provided']);
    exit();
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
    
    // Filter sessions to only include those with date, time, and venue
    $validSessions = array_filter($sessions, function($session) {
        return !empty($session['date']) && !empty($session['time']) && !empty($session['venue']);
    });
    
    if (empty($validSessions)) {
        throw new Exception('No valid assessment sessions to save (all must have date, time, and venue)');
    }
    
    // Group sessions by date, time, venue, and course_id to create assessment_session records
    // Each unique combination gets one assessment_session record
    $sessionGroups = [];
    foreach ($validSessions as $session) {
        $key = $session['date'] . '|' . $session['time'] . '|' . $session['venue'] . '|' . $session['course_id'];
        if (!isset($sessionGroups[$key])) {
            $sessionGroups[$key] = [
                'date' => $session['date'],
                'time' => $session['time'],
                'venue' => $session['venue'],
                'course_id' => $session['course_id'],
                'students' => []
            ];
        }
        $sessionGroups[$key]['students'][] = $session['student_id'];
    }
    
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
        
        // Check if assessment_session already exists for this date, time, venue, and assessment
        $checkSessionQuery = "SELECT Session_ID FROM assessment_session 
                             WHERE Assessment_ID = ? AND Date = ? AND Time = ? AND Venue = ? 
                             LIMIT 1";
        $checkSessionStmt = $conn->prepare($checkSessionQuery);
        if (!$checkSessionStmt) {
            throw new Exception('Prepare failed (checkSession): ' . $conn->error);
        }
        
        $checkSessionStmt->bind_param("isss", $assessmentId, $date, $time, $venue);
        $checkSessionStmt->execute();
        $sessionResult = $checkSessionStmt->get_result();
        $sessionId = null;
        
        if ($sessionRow = $sessionResult->fetch_assoc()) {
            $sessionId = $sessionRow['Session_ID'];
        }
        $checkSessionStmt->close();
        
        // Create or use existing assessment_session
        if (!$sessionId) {
            $insertSessionQuery = "INSERT INTO assessment_session (Assessment_ID, Date, Time, Venue) 
                                  VALUES (?, ?, ?, ?)";
            $insertSessionStmt = $conn->prepare($insertSessionQuery);
            if (!$insertSessionStmt) {
                throw new Exception('Prepare failed (insertSession): ' . $conn->error);
            }
            
            $insertSessionStmt->bind_param("isss", $assessmentId, $date, $time, $venue);
            if (!$insertSessionStmt->execute()) {
                throw new Exception('Execute failed (insertSession): ' . $insertSessionStmt->error);
            }
            
            $sessionId = $insertSessionStmt->insert_id;
            $insertSessionStmt->close();
            $processedSessions++;
        }
        
        // Process each student in this session
        foreach ($studentIds as $studentId) {
            // Check if student_session already exists
            $checkStudentQuery = "SELECT Session_ID FROM student_session 
                                 WHERE Session_ID = ? AND Student_ID = ? 
                                 LIMIT 1";
            $checkStudentStmt = $conn->prepare($checkStudentQuery);
            if (!$checkStudentStmt) {
                throw new Exception('Prepare failed (checkStudent): ' . $conn->error);
            }
            
            $checkStudentStmt->bind_param("is", $sessionId, $studentId);
            $checkStudentStmt->execute();
            $studentResult = $checkStudentStmt->get_result();
            $checkStudentStmt->close();
            
            // Insert student_session if it doesn't exist
            if ($studentResult->num_rows == 0) {
                $insertStudentQuery = "INSERT INTO student_session (Session_ID, Student_ID) 
                                      VALUES (?, ?)";
                $insertStudentStmt = $conn->prepare($insertStudentQuery);
                if (!$insertStudentStmt) {
                    throw new Exception('Prepare failed (insertStudent): ' . $conn->error);
                }
                
                $insertStudentStmt->bind_param("is", $sessionId, $studentId);
                if (!$insertStudentStmt->execute()) {
                    throw new Exception('Execute failed (insertStudent): ' . $insertStudentStmt->error);
                }
                
                $insertStudentStmt->close();
                $processedStudents++;
            }
            
            // Get Assessor_ID_1 and Assessor_ID_2 from student_enrollment
            // We need to find the FYP_Session_ID for this student first
            $studentFypSessionId = null;
            if (!empty($fypSessionIds)) {
                // Try to find FYP_Session_ID from student table
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
            }
            
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
                                                    WHERE Session_ID = ? AND Assessor_ID = ? 
                                                    LIMIT 1";
                            $checkAssessor1Stmt = $conn->prepare($checkAssessor1Query);
                            if ($checkAssessor1Stmt) {
                                $checkAssessor1Stmt->bind_param("ii", $sessionId, $assessorId1);
                                $checkAssessor1Stmt->execute();
                                $assessor1Result = $checkAssessor1Stmt->get_result();
                                $checkAssessor1Stmt->close();
                                
                                if ($assessor1Result->num_rows == 0) {
                                    $insertAssessor1Query = "INSERT INTO assessor_session (Session_ID, Assessor_ID) 
                                                            VALUES (?, ?)";
                                    $insertAssessor1Stmt = $conn->prepare($insertAssessor1Query);
                                    if ($insertAssessor1Stmt) {
                                        $insertAssessor1Stmt->bind_param("ii", $sessionId, $assessorId1);
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
                                                    WHERE Session_ID = ? AND Assessor_ID = ? 
                                                    LIMIT 1";
                            $checkAssessor2Stmt = $conn->prepare($checkAssessor2Query);
                            if ($checkAssessor2Stmt) {
                                $checkAssessor2Stmt->bind_param("ii", $sessionId, $assessorId2);
                                $checkAssessor2Stmt->execute();
                                $assessor2Result = $checkAssessor2Stmt->get_result();
                                $checkAssessor2Stmt->close();
                                
                                if ($assessor2Result->num_rows == 0) {
                                    $insertAssessor2Query = "INSERT INTO assessor_session (Session_ID, Assessor_ID) 
                                                            VALUES (?, ?)";
                                    $insertAssessor2Stmt = $conn->prepare($insertAssessor2Query);
                                    if ($insertAssessor2Stmt) {
                                        $insertAssessor2Stmt->bind_param("ii", $sessionId, $assessorId2);
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
