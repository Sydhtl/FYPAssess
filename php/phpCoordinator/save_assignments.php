
<?php
include '../mysqlConnect.php';
require_once __DIR__ . '/../sendEmail.php';
require_once __DIR__ . '/../emailConfig.php';
session_start();

// Ensure only Coordinators can save assignments
if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Load email configuration
$emailConfig = require __DIR__ . '/../emailConfig.php';

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['assignments']) || !is_array($input['assignments'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input payload']);
    exit();
}

$assignments = $input['assignments'];

if (empty($assignments)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No assignments provided']);
    exit();
}

/**
 * Normalize an incoming ID (string/int) to either a positive integer or null.
 */
function normalizeId($value) {
    if (is_null($value)) {
        return null;
    }
    $intVal = (int)$value;
    return ($intVal > 0) ? $intVal : null;
}

/**
 * Build a map of lecturer identifiers (name / lecturer_id) to Assessor_ID.
 */
function buildAssessorMap($conn) {
    $map = [];
    $assessorQuery = "
        SELECT a.Assessor_ID, a.Lecturer_ID, l.Lecturer_Name
        FROM assessor a
        LEFT JOIN lecturer l ON a.Lecturer_ID = l.Lecturer_ID
    ";
    if ($result = $conn->query($assessorQuery)) {
        while ($row = $result->fetch_assoc()) {
            $assessorId  = (int)$row['Assessor_ID'];
            $lecturerId  = $row['Lecturer_ID'] ?? '';
            $lecturerName = $row['Lecturer_Name'] ?? '';
            $sanitizedName = '';

            if (!empty($lecturerId)) {
                $map[strtolower(trim($lecturerId))] = $assessorId;
            }
            if (!empty($lecturerName)) {
                $key = strtolower(trim($lecturerName));
                $map[$key] = $assessorId;
                // Additional sanitized key without spaces/punctuation
                $sanitizedName = preg_replace('/\s+/', '', $key);
                if (!empty($sanitizedName)) {
                    $map[$sanitizedName] = $assessorId;
                }
            }
        }
        $result->free();
    } else {
        // Log but do not fail the whole request if assessor table lookup fails
        error_log('buildAssessorMap query failed: ' . $conn->error);
    }
    return $map;
}

/**
 * Resolve assessor ID by lecturer name/id using the assessor map.
 */
function resolveAssessorId($value, $assessorMap) {
    if (empty($value)) {
        return null;
    }
    $key = strtolower(trim($value));
    if (isset($assessorMap[$key])) {
        return normalizeId($assessorMap[$key]);
    }
    return null;
}

/**
 * Insert or update a row in student_enrollment for the given student/session.
 */
function upsertStudentEnrollment($conn, $studentId, $fypSessionId, $supervisorId = null, $assessor1Id = null, $assessor2Id = null) {
    if (empty($studentId) || empty($fypSessionId)) {
        return;
    }

    $supervisorId = normalizeId($supervisorId);
    $assessor1Id  = normalizeId($assessor1Id);
    $assessor2Id  = normalizeId($assessor2Id);

    $checkEnrollment = $conn->prepare("
        SELECT Student_Enrollment_ID 
        FROM student_enrollment 
        WHERE Student_ID = ? AND Fyp_Session_ID = ? 
        LIMIT 1
    ");
    if (!$checkEnrollment) {
        throw new Exception('Prepare failed (checkEnrollment): ' . $conn->error);
    }
    $checkEnrollment->bind_param("si", $studentId, $fypSessionId);
    if (!$checkEnrollment->execute()) {
        throw new Exception('Execute failed (checkEnrollment): ' . $checkEnrollment->error);
    }
    $enrollResult = $checkEnrollment->get_result();
    $enrollmentId = null;
    if ($enrollRow = $enrollResult->fetch_assoc()) {
        $enrollmentId = (int)$enrollRow['Student_Enrollment_ID'];
    }
    $checkEnrollment->close();

    if ($enrollmentId) {
        $updateEnrollment = $conn->prepare("
            UPDATE student_enrollment 
            SET Supervisor_ID = ?, Assessor_ID_1 = ?, Assessor_ID_2 = ?
            WHERE Student_Enrollment_ID = ?
        ");
        if (!$updateEnrollment) {
            throw new Exception('Prepare failed (updateEnrollment): ' . $conn->error);
        }
        $updateEnrollment->bind_param(
            "iiii",
            $supervisorId,
            $assessor1Id,
            $assessor2Id,
            $enrollmentId
        );
        if (!$updateEnrollment->execute()) {
            throw new Exception('Execute failed (updateEnrollment): ' . $updateEnrollment->error);
        }
        $updateEnrollment->close();
    } else {
        $insertEnrollment = $conn->prepare("
            INSERT INTO student_enrollment 
                (Fyp_Session_ID, Student_ID, Supervisor_ID, Assessor_ID_1, Assessor_ID_2)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$insertEnrollment) {
            throw new Exception('Prepare failed (insertEnrollment): ' . $conn->error);
        }
        $insertEnrollment->bind_param(
            "isiii",
            $fypSessionId,
            $studentId,
            $supervisorId,
            $assessor1Id,
            $assessor2Id
        );
        if (!$insertEnrollment->execute()) {
            throw new Exception('Execute failed (insertEnrollment): ' . $insertEnrollment->error);
        }
        $insertEnrollment->close();
    }
}

// Before transaction: Capture OLD state for comparison
// This will be used to detect changes and determine if emails should be sent
$oldLecturerAssignments = [];
$oldQuotas = [];

// Get FYP_Session_IDs from assignments to check old state
$fypSessionIdsForOld = [];
if (!empty($assignments)) {
    $firstStudentId = $assignments[0]['id'] ?? '';
    if (!empty($firstStudentId)) {
        // Get all FYP_Session_IDs for students in assignments
        $studentIdsForOld = array_map(function($a) { return $a['id'] ?? ''; }, $assignments);
        $studentIdsForOld = array_filter($studentIdsForOld);
        
        if (!empty($studentIdsForOld)) {
            $placeholders = implode(',', array_fill(0, count($studentIdsForOld), '?'));
            $fypSessionQuery = "SELECT DISTINCT FYP_Session_ID FROM student WHERE Student_ID IN ($placeholders)";
            $stmtFypSessions = $conn->prepare($fypSessionQuery);
            if ($stmtFypSessions) {
                $types = str_repeat('s', count($studentIdsForOld));
                $stmtFypSessions->bind_param($types, ...array_values($studentIdsForOld));
                $stmtFypSessions->execute();
                $fypSessionResult = $stmtFypSessions->get_result();
                while ($fypRow = $fypSessionResult->fetch_assoc()) {
                    $fypSessionIdsForOld[] = (int)$fypRow['FYP_Session_ID'];
                }
                $stmtFypSessions->close();
            }
        }
    }
}

// Get OLD assignments before saving
if (!empty($fypSessionIdsForOld)) {
    $sessionPlaceholders = implode(',', array_fill(0, count($fypSessionIdsForOld), '?'));
    $oldEnrollmentQuery = "
        SELECT se.Student_ID, se.Supervisor_ID, se.Assessor_ID_1, se.Assessor_ID_2,
               s.Student_Name,
               sup.Lecturer_ID as Supervisor_Lecturer_ID,
               a1.Lecturer_ID as Assessor1_Lecturer_ID,
               a2.Lecturer_ID as Assessor2_Lecturer_ID,
               l1.Lecturer_Name as Supervisor_Name,
               l2.Lecturer_Name as Assessor1_Name,
               l3.Lecturer_Name as Assessor2_Name
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        LEFT JOIN supervisor sup ON se.Supervisor_ID = sup.Supervisor_ID
        LEFT JOIN lecturer l1 ON sup.Lecturer_ID = l1.Lecturer_ID
        LEFT JOIN assessor a1 ON se.Assessor_ID_1 = a1.Assessor_ID
        LEFT JOIN lecturer l2 ON a1.Lecturer_ID = l2.Lecturer_ID
        LEFT JOIN assessor a2 ON se.Assessor_ID_2 = a2.Assessor_ID
        LEFT JOIN lecturer l3 ON a2.Lecturer_ID = l3.Lecturer_ID
        WHERE se.Fyp_Session_ID IN ($sessionPlaceholders)
    ";
    $stmtOldEnroll = $conn->prepare($oldEnrollmentQuery);
    if ($stmtOldEnroll) {
        $types = str_repeat('i', count($fypSessionIdsForOld));
        $stmtOldEnroll->bind_param($types, ...$fypSessionIdsForOld);
        $stmtOldEnroll->execute();
        $oldEnrollResult = $stmtOldEnroll->get_result();
        
        while ($oldRow = $oldEnrollResult->fetch_assoc()) {
            // Build old assignments structure
            if (!empty($oldRow['Supervisor_Lecturer_ID'])) {
                $lecId = $oldRow['Supervisor_Lecturer_ID'];
                if (!isset($oldLecturerAssignments[$lecId])) {
                    $oldLecturerAssignments[$lecId] = [
                        'supervisor_students' => [],
                        'assessor_students' => []
                    ];
                }
                $oldLecturerAssignments[$lecId]['supervisor_students'][] = $oldRow['Student_ID'];
            }
            if (!empty($oldRow['Assessor1_Lecturer_ID'])) {
                $lecId = $oldRow['Assessor1_Lecturer_ID'];
                if (!isset($oldLecturerAssignments[$lecId])) {
                    $oldLecturerAssignments[$lecId] = [
                        'supervisor_students' => [],
                        'assessor_students' => []
                    ];
                }
                if (!in_array($oldRow['Student_ID'], $oldLecturerAssignments[$lecId]['assessor_students'])) {
                    $oldLecturerAssignments[$lecId]['assessor_students'][] = $oldRow['Student_ID'];
                }
            }
            if (!empty($oldRow['Assessor2_Lecturer_ID'])) {
                $lecId = $oldRow['Assessor2_Lecturer_ID'];
                if (!isset($oldLecturerAssignments[$lecId])) {
                    $oldLecturerAssignments[$lecId] = [
                        'supervisor_students' => [],
                        'assessor_students' => []
                    ];
                }
                if (!in_array($oldRow['Student_ID'], $oldLecturerAssignments[$lecId]['assessor_students'])) {
                    $oldLecturerAssignments[$lecId]['assessor_students'][] = $oldRow['Student_ID'];
                }
            }
        }
        $stmtOldEnroll->close();
        
        // Get OLD quotas
        if (!empty($oldLecturerAssignments) && !empty($fypSessionIdsForOld)) {
            $oldLecturerIds = array_keys($oldLecturerAssignments);
            $oldPlaceholders = implode(',', array_fill(0, count($oldLecturerIds), '?'));
            $oldFypSessionId = $fypSessionIdsForOld[0];
            $oldQuotaQuery = "
                SELECT l.Lecturer_ID, COALESCE(sqh.Quota, 0) as Quota
                FROM lecturer l
                JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                LEFT JOIN supervisor_quota_history sqh ON s.Supervisor_ID = sqh.Supervisor_ID 
                    AND sqh.FYP_Session_ID = ?
                WHERE l.Lecturer_ID IN ($oldPlaceholders)
            ";
            $stmtOldQuota = $conn->prepare($oldQuotaQuery);
            if ($stmtOldQuota) {
                $types = 'i' . str_repeat('s', count($oldLecturerIds));
                $params = array_merge([$oldFypSessionId], $oldLecturerIds);
                $stmtOldQuota->bind_param($types, ...$params);
                $stmtOldQuota->execute();
                $oldQuotaResult = $stmtOldQuota->get_result();
                while ($oldQuotaRow = $oldQuotaResult->fetch_assoc()) {
                    $oldQuotas[$oldQuotaRow['Lecturer_ID']] = (int)$oldQuotaRow['Quota'];
                }
                $stmtOldQuota->close();
            }
        }
    }
}

// Start transaction for all assignments
$conn->begin_transaction();
$assessorMap = buildAssessorMap($conn);

try {
    $processedKeys = [];

    foreach ($assignments as $assignment) {
        // Use student_id and fyp_session_id from frontend (new structure)
        $studentId      = $assignment['student_id'] ?? ($assignment['id'] ?? '');
        $fypSessionIdFromFrontend = $assignment['fyp_session_id'] ?? null;
        $supervisorId   = $assignment['supervisor_id'] ?? null;   // INT (Supervisor.Supervisor_ID)
        $assessor1Id    = $assignment['assessor1_id'] ?? null;
        $assessor2Id    = $assignment['assessor2_id'] ?? null;
        $supervisorName = $assignment['supervisor'] ?? null;
        $assessor1Name  = $assignment['assessor1'] ?? null;
        $assessor2Name  = $assignment['assessor2'] ?? null;

        // Debug logging - log all assignments including nulls
        error_log("DEBUG: Processing Student $studentId");
        error_log("  Supervisor: " . ($supervisorName ?: 'NULL') . ", Supervisor_ID: " . var_export($supervisorId, true));
        error_log("  Assessor1: " . ($assessor1Name ?: 'NULL') . ", Assessor1_ID: " . var_export($assessor1Id, true));
        error_log("  Assessor2: " . ($assessor2Name ?: 'NULL') . ", Assessor2_ID: " . var_export($assessor2Id, true));

        if (empty($studentId)) {
            error_log("WARNING: Skipping assignment with empty student_id");
            continue; // Skip invalid entries
        }

        // 1) Find student's FYP_Session_ID from student table (or use provided one)
        $fypSessionId = $fypSessionIdFromFrontend;
        if (empty($fypSessionId)) {
            $lookupStudent = $conn->prepare("SELECT FYP_Session_ID FROM student WHERE Student_ID = ? LIMIT 1");
            if (!$lookupStudent) {
                throw new Exception('Prepare failed (lookupStudent): ' . $conn->error);
            }
            $lookupStudent->bind_param("s", $studentId);
            if (!$lookupStudent->execute()) {
                throw new Exception('Execute failed (lookupStudent): ' . $lookupStudent->error);
            }
            $result = $lookupStudent->get_result();

            if ($row = $result->fetch_assoc()) {
                $fypSessionId = (int)$row['FYP_Session_ID'];
            }
            $lookupStudent->close();
        }

        // If student record not found, skip this assignment
        if (empty($fypSessionId)) {
            error_log("WARNING: No FYP_Session_ID found for student $studentId");
            continue;
        }

        // 2) Update supervisor in student table - handle NULL values for clearing
        // Supervisor follows the session and year - update for current and future sessions
        $updateStudent = $conn->prepare("UPDATE student SET Supervisor_ID = ? WHERE Student_ID = ?");
        if (!$updateStudent) {
            throw new Exception('Prepare failed (updateStudent): ' . $conn->error);
        }
        $supIdNormalized = normalizeId($supervisorId); // Will be NULL if cleared
        $updateStudent->bind_param("is", $supIdNormalized, $studentId);
        if (!$updateStudent->execute()) {
            throw new Exception('Execute failed (updateStudent): ' . $updateStudent->error);
        }
        $updateStudent->close();
        error_log("  Updated student table: Supervisor_ID = " . var_export($supIdNormalized, true));

        // Resolve assessor IDs using assessor table mapping (fallback to provided IDs if mapping missing)
        $assessor1Resolved = resolveAssessorId($assessor1Name, $assessorMap);
        if (is_null($assessor1Resolved)) {
            $assessor1Resolved = normalizeId($assessor1Id);
        }
        $assessor2Resolved = resolveAssessorId($assessor2Name, $assessorMap);
        if (is_null($assessor2Resolved)) {
            $assessor2Resolved = normalizeId($assessor2Id);
        }
        
        error_log("  Resolved Assessor1_ID: " . var_export($assessor1Resolved, true));
        error_log("  Resolved Assessor2_ID: " . var_export($assessor2Resolved, true));

        // 3) Insert or update student_enrollment - this handles NULL values properly
        //    Ensure Fyp_Session_ID follows student's FYP_Session_ID
        upsertStudentEnrollment(
            $conn,
            $studentId,
            $fypSessionId,
            $supIdNormalized,
            $assessor1Resolved,
            $assessor2Resolved
        );
        
        error_log("  student_enrollment updated for Student $studentId");

        $processedKeys[$studentId . '_' . $fypSessionId] = true;
    }

    // After processing provided assignments, ensure students from CURRENT SESSION only have enrollment records
    // Get FYP_Session_IDs from the assignments (only process students from current session/semester)
    $currentSessionIds = [];
    foreach ($assignments as $assignment) {
        $studentId = $assignment['id'] ?? '';
        if (!empty($studentId)) {
            $lookupSession = $conn->prepare("SELECT FYP_Session_ID FROM student WHERE Student_ID = ? LIMIT 1");
            if ($lookupSession) {
                $lookupSession->bind_param("s", $studentId);
                $lookupSession->execute();
                $sessionResult = $lookupSession->get_result();
                if ($sessionRow = $sessionResult->fetch_assoc()) {
                    $fypSessionId = (int)$sessionRow['FYP_Session_ID'];
                    if (!in_array($fypSessionId, $currentSessionIds)) {
                        $currentSessionIds[] = $fypSessionId;
                    }
                }
                $lookupSession->close();
            }
        }
    }
    
    // Only process students from the current session(s) - do NOT touch students from other sessions
    if (!empty($currentSessionIds)) {
        $sessionPlaceholders = implode(',', array_fill(0, count($currentSessionIds), '?'));
        $currentStudentsQuery = "
            SELECT Student_ID, FYP_Session_ID, Supervisor_ID
            FROM student
            WHERE FYP_Session_ID IN ($sessionPlaceholders)
        ";
        $currentStmt = $conn->prepare($currentStudentsQuery);
        if ($currentStmt) {
            $types = str_repeat('i', count($currentSessionIds));
            $currentStmt->bind_param($types, ...$currentSessionIds);
            $currentStmt->execute();
            $currentStudentsResult = $currentStmt->get_result();
            
            while ($row = $currentStudentsResult->fetch_assoc()) {
                $studentId    = $row['Student_ID'];
                $fypSessionId = (int)$row['FYP_Session_ID'];
                $existingSup  = $row['Supervisor_ID'];

                if (empty($studentId) || empty($fypSessionId)) {
                    continue;
                }

                $key = $studentId . '_' . $fypSessionId;
                if (isset($processedKeys[$key])) {
                    continue; // Already upserted above with fresh data
                }

                // For students not in the assignment list, preserve existing enrollment data
                // Check if enrollment exists and preserve assessors
                $checkExisting = $conn->prepare("
                    SELECT Supervisor_ID, Assessor_ID_1, Assessor_ID_2
                    FROM student_enrollment
                    WHERE Student_ID = ? AND Fyp_Session_ID = ?
                    LIMIT 1
                ");
                if ($checkExisting) {
                    $checkExisting->bind_param("si", $studentId, $fypSessionId);
                    $checkExisting->execute();
                    $existingResult = $checkExisting->get_result();
                    if ($existingRow = $existingResult->fetch_assoc()) {
                        // Use existing enrollment data (preserve assessors)
                        upsertStudentEnrollment(
                            $conn,
                            $studentId,
                            $fypSessionId,
                            $existingRow['Supervisor_ID'] ?? $existingSup,
                            $existingRow['Assessor_ID_1'] ?? null,
                            $existingRow['Assessor_ID_2'] ?? null
                        );
                    } else {
                        // No existing enrollment - create with supervisor only
                        upsertStudentEnrollment(
                            $conn,
                            $studentId,
                            $fypSessionId,
                            $existingSup,
                            null,
                            null
                        );
                    }
                    $checkExisting->close();
                }
            }
            $currentStmt->close();
        }
    }

    // Commit all changes
    $conn->commit();
    
    // After successful save, send email notifications to lecturers
    try {
        // Get coordinator's department ID
        $coordinatorDepartmentId = null;
        $coordinatorId = $_SESSION['upmId'] ?? null;
        if (!empty($coordinatorId)) {
            $deptQuery = "SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1";
            $stmtDept = $conn->prepare($deptQuery);
            if ($stmtDept) {
                $stmtDept->bind_param("s", $coordinatorId);
                $stmtDept->execute();
                $deptResult = $stmtDept->get_result();
                if ($deptRow = $deptResult->fetch_assoc()) {
                    $coordinatorDepartmentId = $deptRow['Department_ID'];
                }
                $stmtDept->close();
            }
        }
        
        // Get year and semester from the first assignment's FYP_Session_ID
        $year = '';
        $semester = '';
        $courseCode = ''; // This will be the first course code encountered
        $firstAssignmentSessionId = null;
        
        foreach ($assignments as $assignment) {
            $sessionId = $assignment['fyp_session_id'] ?? null;
            if (!empty($sessionId)) {
                $firstAssignmentSessionId = (int)$sessionId;
                break;
            }
        }
        
        if (!empty($firstAssignmentSessionId)) {
            $sessionQuery = "SELECT fs.FYP_Session, fs.Semester, c.Course_Code 
                             FROM fyp_session fs
                             JOIN course c ON fs.Course_ID = c.Course_ID
                             WHERE fs.FYP_Session_ID = ? LIMIT 1";
            $stmtSession = $conn->prepare($sessionQuery);
            if ($stmtSession) {
                $stmtSession->bind_param("i", $firstAssignmentSessionId);
                $stmtSession->execute();
                $sessionResult = $stmtSession->get_result();
                if ($sessionRow = $sessionResult->fetch_assoc()) {
                    $year = $sessionRow['FYP_Session'] ?? '';
                    $semester = $sessionRow['Semester'] ?? '';
                    $courseCode = $sessionRow['Course_Code'] ?? '';
                }
                $stmtSession->close();
            }
        }
        
        // Now get ALL FYP_Session_IDs that match this year and semester
        // This ensures we fetch all course codes for the same year/semester
        $fypSessionIds = [];
        if (!empty($year) && !empty($semester)) {
            $allSessionsQuery = "SELECT DISTINCT FYP_Session_ID 
                                 FROM fyp_session 
                                 WHERE FYP_Session = ? AND Semester = ?";
            $stmtAllSessions = $conn->prepare($allSessionsQuery);
            if ($stmtAllSessions) {
                $stmtAllSessions->bind_param("si", $year, $semester);
                $stmtAllSessions->execute();
                $allSessionsResult = $stmtAllSessions->get_result();
                while ($sessionRow = $allSessionsResult->fetch_assoc()) {
                    $fypSessionIds[] = (int)$sessionRow['FYP_Session_ID'];
                }
                $stmtAllSessions->close();
            }
        }
        
        // Collect lecturer assignments: supervisors and assessors with their students
        $lecturerAssignments = []; // Key: Lecturer_ID, Value: {name, lecturer_id, supervisor_students: [], assessor_students: [], course_codes: [], quota: 0, remaining_quota: 0}
        
        // Get all student enrollments for THIS specific session only
        if (!empty($fypSessionIds)) {
            $sessionPlaceholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
            
            // Use the year and semester we already retrieved above
            $currentYear = $year;
            $currentSemester = $semester;
            
            $enrollmentQuery = "
                SELECT DISTINCT se.Student_ID, se.Supervisor_ID, se.Assessor_ID_1, se.Assessor_ID_2,
                       s.Student_Name, s.Course_ID,
                       c.Course_Code,
                       sup.Lecturer_ID as Supervisor_Lecturer_ID,
                       a1.Lecturer_ID as Assessor1_Lecturer_ID,
                       a2.Lecturer_ID as Assessor2_Lecturer_ID,
                       l1.Lecturer_Name as Supervisor_Name,
                       l2.Lecturer_Name as Assessor1_Name,
                       l3.Lecturer_Name as Assessor2_Name
                FROM student_enrollment se
                JOIN student s ON se.Student_ID = s.Student_ID AND se.Fyp_Session_ID = s.FYP_Session_ID
                JOIN fyp_session fs ON se.Fyp_Session_ID = fs.FYP_Session_ID
                JOIN course c ON fs.Course_ID = c.Course_ID
                LEFT JOIN supervisor sup ON se.Supervisor_ID = sup.Supervisor_ID
                LEFT JOIN lecturer l1 ON sup.Lecturer_ID = l1.Lecturer_ID
                LEFT JOIN assessor a1 ON se.Assessor_ID_1 = a1.Assessor_ID
                LEFT JOIN lecturer l2 ON a1.Lecturer_ID = l2.Lecturer_ID
                LEFT JOIN assessor a2 ON se.Assessor_ID_2 = a2.Assessor_ID
                LEFT JOIN lecturer l3 ON a2.Lecturer_ID = l3.Lecturer_ID
                WHERE se.Fyp_Session_ID IN ($sessionPlaceholders)";
            
            // Add filter for current year and semester if available
            if (!empty($currentYear) && !empty($currentSemester)) {
                $enrollmentQuery .= " AND fs.FYP_Session = ? AND fs.Semester = ?";
            }
            
            // Add department filter - only include lecturers from the same department
            if (!empty($coordinatorDepartmentId)) {
                $enrollmentQuery .= " AND (l1.Department_ID = ? OR l2.Department_ID = ? OR l3.Department_ID = ?)";
            }
            
            $stmtEnroll = $conn->prepare($enrollmentQuery);
            if ($stmtEnroll) {
                $params = $fypSessionIds;
                $types = str_repeat('i', count($fypSessionIds));
                
                if (!empty($currentYear) && !empty($currentSemester)) {
                    $types .= 'si';
                    $params[] = $currentYear;
                    $params[] = $currentSemester;
                }
                
                if (!empty($coordinatorDepartmentId)) {
                    $types .= 'iii';
                    $params[] = $coordinatorDepartmentId;
                    $params[] = $coordinatorDepartmentId;
                    $params[] = $coordinatorDepartmentId;
                }
                
                $stmtEnroll->bind_param($types, ...$params);
                $stmtEnroll->execute();
                $enrollResult = $stmtEnroll->get_result();
                
                while ($enrollRow = $enrollResult->fetch_assoc()) {
                    $studentId = $enrollRow['Student_ID'];
                    $studentName = $enrollRow['Student_Name'];
                    
                    // Process supervisor
                    if (!empty($enrollRow['Supervisor_Lecturer_ID'])) {
                        $lecId = $enrollRow['Supervisor_Lecturer_ID'];
                        if (!isset($lecturerAssignments[$lecId])) {
                            $lecturerAssignments[$lecId] = [
                                'lecturer_id' => $lecId,
                                'lecturer_name' => $enrollRow['Supervisor_Name'] ?? '',
                                'supervisor_students' => [], // Now array of {id, name, course_code}
                                'assessor_students' => [],   // Now array of {id, name, course_code}
                                'course_codes' => [],        // Array of unique course codes
                                'quota' => 0,
                                'remaining_quota' => 0
                            ];
                        }
                        $studentKey = $studentId . '_' . $enrollRow['Course_Code'];
                        // Add student if not already added (avoid duplicates across course codes)
                        $exists = false;
                        foreach ($lecturerAssignments[$lecId]['supervisor_students'] as $student) {
                            if ($student['id'] === $studentId) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $lecturerAssignments[$lecId]['supervisor_students'][] = [
                                'id' => $studentId,
                                'name' => $studentName,
                                'course_code' => $enrollRow['Course_Code']
                            ];
                            // Add course code if not already in list
                            if (!in_array($enrollRow['Course_Code'], $lecturerAssignments[$lecId]['course_codes'])) {
                                $lecturerAssignments[$lecId]['course_codes'][] = $enrollRow['Course_Code'];
                            }
                        }
                    }
                    
                    // Process assessor 1
                    if (!empty($enrollRow['Assessor1_Lecturer_ID'])) {
                        $lecId = $enrollRow['Assessor1_Lecturer_ID'];
                        if (!isset($lecturerAssignments[$lecId])) {
                            $lecturerAssignments[$lecId] = [
                                'lecturer_id' => $lecId,
                                'lecturer_name' => $enrollRow['Assessor1_Name'] ?? '',
                                'supervisor_students' => [],
                                'assessor_students' => [],
                                'course_codes' => [],
                                'quota' => 0,
                                'remaining_quota' => 0
                            ];
                        }
                        // Add student if not already added (avoid duplicates)
                        $exists = false;
                        foreach ($lecturerAssignments[$lecId]['assessor_students'] as $student) {
                            if ($student['id'] === $studentId) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $lecturerAssignments[$lecId]['assessor_students'][] = [
                                'id' => $studentId,
                                'name' => $studentName,
                                'course_code' => $enrollRow['Course_Code']
                            ];
                            // Add course code if not already in list
                            if (!in_array($enrollRow['Course_Code'], $lecturerAssignments[$lecId]['course_codes'])) {
                                $lecturerAssignments[$lecId]['course_codes'][] = $enrollRow['Course_Code'];
                            }
                        }
                    }
                    
                    // Process assessor 2
                    if (!empty($enrollRow['Assessor2_Lecturer_ID'])) {
                        $lecId = $enrollRow['Assessor2_Lecturer_ID'];
                        if (!isset($lecturerAssignments[$lecId])) {
                            $lecturerAssignments[$lecId] = [
                                'lecturer_id' => $lecId,
                                'lecturer_name' => $enrollRow['Assessor2_Name'] ?? '',
                                'supervisor_students' => [],
                                'assessor_students' => [],
                                'course_codes' => [],
                                'quota' => 0,
                                'remaining_quota' => 0
                            ];
                        }
                        // Only add if not already added as assessor (avoid duplicates)
                        $exists = false;
                        foreach ($lecturerAssignments[$lecId]['assessor_students'] as $assessStudent) {
                            if ($assessStudent['id'] === $studentId) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $lecturerAssignments[$lecId]['assessor_students'][] = [
                                'id' => $studentId,
                                'name' => $studentName,
                                'course_code' => $enrollRow['Course_Code']
                            ];
                            // Add course code if not already in list
                            if (!in_array($enrollRow['Course_Code'], $lecturerAssignments[$lecId]['course_codes'])) {
                                $lecturerAssignments[$lecId]['course_codes'][] = $enrollRow['Course_Code'];
                            }
                        }
                    }
                }
                $stmtEnroll->close();
                
                // Get quota information for supervisors
                if (!empty($lecturerAssignments)) {
                    $lecturerIds = array_keys($lecturerAssignments);
                    $placeholders = implode(',', array_fill(0, count($lecturerIds), '?'));
                    
                    // Get quota for each lecturer (only supervisors have quotas)
                    // Use the first FYP_Session_ID (quota is the same across all courses for a session)
                    if (!empty($fypSessionIds)) {
                        $fypSessionId = $fypSessionIds[0]; // Use first session ID for quota lookup
                        $quotaQuery = "
                            SELECT l.Lecturer_ID, COALESCE(sqh.Quota, 0) as Quota
                            FROM lecturer l
                            JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                            LEFT JOIN supervisor_quota_history sqh ON s.Supervisor_ID = sqh.Supervisor_ID 
                                AND sqh.FYP_Session_ID = ?
                            WHERE l.Lecturer_ID IN ($placeholders)
                        ";
                        $stmtQuota = $conn->prepare($quotaQuery);
                        if ($stmtQuota) {
                            $types = 'i' . str_repeat('s', count($lecturerIds));
                            $params = array_merge([$fypSessionId], $lecturerIds);
                            $stmtQuota->bind_param($types, ...$params);
                            $stmtQuota->execute();
                            $quotaResult = $stmtQuota->get_result();
                            while ($quotaRow = $quotaResult->fetch_assoc()) {
                                $lecId = $quotaRow['Lecturer_ID'];
                                if (isset($lecturerAssignments[$lecId])) {
                                    $quota = (int)$quotaRow['Quota'];
                                    $lecturerAssignments[$lecId]['quota'] = $quota;
                                    $supervisorCount = count($lecturerAssignments[$lecId]['supervisor_students']);
                                    $lecturerAssignments[$lecId]['remaining_quota'] = max(0, $quota - $supervisorCount);
                                }
                            }
                            $stmtQuota->close();
                        }
                    }
                }
                
                // Determine if this is first distribution
                // First distribution: no lecturers had student assignments before (oldLecturerAssignments is empty)
                // OR we're assigning all students for the first time in this session
                $isFirstDistribution = empty($oldLecturerAssignments);
                
                // Also need to get OLD quotas for ALL supervisors in the session to detect quota changes
                // (not just those who had assignments before)
                $allSupervisorLecturerIds = [];
                foreach ($lecturerAssignments as $lecId => $lecData) {
                    // Check if this lecturer is a supervisor (has supervisor_students OR has quota > 0)
                    if (!empty($lecData['supervisor_students']) || $lecData['quota'] > 0) {
                        if (!isset($oldQuotas[$lecId]) && !empty($fypSessionIds)) {
                            // Need to check old quota even if they weren't in oldLecturerAssignments
                            $allSupervisorLecturerIds[] = $lecId;
                        }
                    }
                }
                
                // Get old quotas for supervisors not in oldLecturerAssignments
                if (!empty($allSupervisorLecturerIds) && !empty($fypSessionIds)) {
                    $newSupervisorIds = array_diff($allSupervisorLecturerIds, array_keys($oldQuotas));
                    if (!empty($newSupervisorIds)) {
                        $newPlaceholders = implode(',', array_fill(0, count($newSupervisorIds), '?'));
                        $oldQuotaQueryNew = "
                            SELECT l.Lecturer_ID, COALESCE(sqh.Quota, 0) as Quota
                            FROM lecturer l
                            JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                            LEFT JOIN supervisor_quota_history sqh ON s.Supervisor_ID = sqh.Supervisor_ID 
                                AND sqh.FYP_Session_ID = ?
                            WHERE l.Lecturer_ID IN ($newPlaceholders)
                        ";
                        $stmtOldQuotaNew = $conn->prepare($oldQuotaQueryNew);
                        if ($stmtOldQuotaNew) {
                            $fypSessionId = $fypSessionIds[0];
                            $types = 'i' . str_repeat('s', count($newSupervisorIds));
                            $stmtOldQuotaNew->bind_param($types, $fypSessionId, ...array_values($newSupervisorIds));
                            $stmtOldQuotaNew->execute();
                            $oldQuotaResultNew = $stmtOldQuotaNew->get_result();
                            while ($oldQuotaRowNew = $oldQuotaResultNew->fetch_assoc()) {
                                $oldQuotas[$oldQuotaRowNew['Lecturer_ID']] = (int)$oldQuotaRowNew['Quota'];
                            }
                            $stmtOldQuotaNew->close();
                        }
                    }
                }
                
                // Find lecturers with changes (quota changed OR student assignments changed)
                $lecturersWithChanges = [];
                foreach ($lecturerAssignments as $lecId => $lecData) {
                    if (empty($lecData['lecturer_name'])) {
                        continue;
                    }
                    
                    $hasChanges = false;
                    
                    // Check if quota changed
                    $oldQuota = $oldQuotas[$lecId] ?? 0;
                    $newQuota = $lecData['quota'];
                    if ($oldQuota != $newQuota) {
                        $hasChanges = true;
                    }
                    
                    // Check if supervisor assignments changed
                    $oldSupervisorStudents = isset($oldLecturerAssignments[$lecId]) ? 
                        $oldLecturerAssignments[$lecId]['supervisor_students'] : [];
                    $newSupervisorStudents = array_map(function($s) { return $s['id']; }, $lecData['supervisor_students']);
                    sort($oldSupervisorStudents);
                    sort($newSupervisorStudents);
                    if ($oldSupervisorStudents != $newSupervisorStudents) {
                        $hasChanges = true;
                    }
                    
                    // Check if assessor assignments changed
                    $oldAssessorStudents = isset($oldLecturerAssignments[$lecId]) ? 
                        $oldLecturerAssignments[$lecId]['assessor_students'] : [];
                    $newAssessorStudents = array_map(function($s) { return $s['id']; }, $lecData['assessor_students']);
                    sort($oldAssessorStudents);
                    sort($newAssessorStudents);
                    if ($oldAssessorStudents != $newAssessorStudents) {
                        $hasChanges = true;
                    }
                    
                    // For first distribution, include all lecturers with assignments
                    if ($isFirstDistribution && (!empty($lecData['supervisor_students']) || !empty($lecData['assessor_students']))) {
                        $hasChanges = true;
                    }
                    
                    if ($hasChanges) {
                        $lecturersWithChanges[$lecId] = $lecData;
                    }
                }

                // Even if no differences were detected (e.g., user clicked Save without
                // changing assignments), still notify all lecturers involved in the
                // current session. This keeps the email behaviour consistent with the
                // Save action and surfaces issues like SMTP failures instead of silently
                // exiting early.
                if (empty($lecturersWithChanges)) {
                    $lecturersWithChanges = $lecturerAssignments;
                }
                
                // Send emails only to lecturers with changes (or all if first distribution)
                $emailResults = [];
                foreach ($lecturersWithChanges as $lecId => $lecData) {
                    // FOR TESTING: Always send to test email
                    $lecturerEmail = '214673@student.upm.edu.my';
                    $originalEmail = $lecData['lecturer_id'] . '@upm.edu.my';
                    
                    // Log which lecturer this email is intended for
                    error_log("Sending email for lecturer: " . $lecData['lecturer_name'] . " (Original: $originalEmail, Sent to: $lecturerEmail)");
                    
                    // Get course codes for this lecturer (only if supervising/assessing multiple courses)
                    $allCourseCodesForLecturer = !empty($lecData['course_codes']) ? $lecData['course_codes'] : [$courseCode];
                    $courseCodeDisplay = count($allCourseCodesForLecturer) > 1 ? 
                        implode(', ', $allCourseCodesForLecturer) : $courseCode;
                    
                    // Build student lists grouped by course code (only show course if lecturer has multiple)
                    $supervisorListHtml = '';
                    if (!empty($lecData['supervisor_students'])) {
                        if (count($allCourseCodesForLecturer) > 1) {
                            // Group students by course code
                            $supervisorsByCode = [];
                            foreach ($lecData['supervisor_students'] as $student) {
                                $code = $student['course_code'] ?? 'Unknown';
                                if (!isset($supervisorsByCode[$code])) {
                                    $supervisorsByCode[$code] = [];
                                }
                                $supervisorsByCode[$code][] = $student;
                            }
                            
                            $supervisorListHtml = '';
                            foreach ($supervisorsByCode as $code => $students) {
                                $supervisorListHtml .= '<p style="font-weight: bold; margin: 8px 0 4px 0;">Kursus: ' . htmlspecialchars($code) . '</p>';
                                $supervisorListHtml .= '<ul style="margin: 5px 0; padding-left: 20px;">';
                                foreach ($students as $student) {
                                    $supervisorListHtml .= '<li>' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['id']) . ')</li>';
                                }
                                $supervisorListHtml .= '</ul>';
                            }
                        } else {
                            // Single course code - list without course header
                            $supervisorListHtml = '<ul style="margin: 5px 0; padding-left: 20px;">';
                            foreach ($lecData['supervisor_students'] as $student) {
                                $supervisorListHtml .= '<li>' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['id']) . ')</li>';
                            }
                            $supervisorListHtml .= '</ul>';
                        }
                    } else {
                        $supervisorListHtml = '<p style="color: #999; font-style: italic;">Tiada pelajar yang diselia.</p>';
                    }
                    
                    $assessorListHtml = '';
                    if (!empty($lecData['assessor_students'])) {
                        if (count($allCourseCodesForLecturer) > 1) {
                            // Group students by course code
                            $assessorsByCode = [];
                            foreach ($lecData['assessor_students'] as $student) {
                                $code = $student['course_code'] ?? 'Unknown';
                                if (!isset($assessorsByCode[$code])) {
                                    $assessorsByCode[$code] = [];
                                }
                                $assessorsByCode[$code][] = $student;
                            }
                            
                            $assessorListHtml = '';
                            foreach ($assessorsByCode as $code => $students) {
                                $assessorListHtml .= '<p style="font-weight: bold; margin: 8px 0 4px 0;">Kursus: ' . htmlspecialchars($code) . '</p>';
                                $assessorListHtml .= '<ul style="margin: 5px 0; padding-left: 20px;">';
                                foreach ($students as $student) {
                                    $assessorListHtml .= '<li>' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['id']) . ')</li>';
                                }
                                $assessorListHtml .= '</ul>';
                            }
                        } else {
                            // Single course code - list without course header
                            $assessorListHtml = '<ul style="margin: 5px 0; padding-left: 20px;">';
                            foreach ($lecData['assessor_students'] as $student) {
                                $assessorListHtml .= '<li>' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($student['id']) . ')</li>';
                            }
                            $assessorListHtml .= '</ul>';
                        }
                    } else {
                        $assessorListHtml = '<p style="color: #999; font-style: italic;">Tiada pelajar untuk dinilai.</p>';
                    }
                    
                    // Create email subject and message
                    $subject = "Maklumat Pengagihan Pelajar - " . $courseCodeDisplay . " (" . $year . "/" . $semester . ")";
                    
                    // Add original recipient info in subject for testing
                    $subject .= " [FOR: " . $lecData['lecturer_name'] . "]";
                    
                    // HTML email message
                    $message = "<b>Assalamualaikum Warahmatullahi Wabarakatuh dan Salam Sejahtera,</b><br><br>" .
                               "<b>YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan " . htmlspecialchars($lecData['lecturer_name']) . ",</b><br><br>" .
                               "<b>MAKLUMAT PENGAGIHAN PELAJAR</b><br><br>" .
                               "Sukacita dimaklumkan bahawa pengagihan pelajar untuk kursus <b>" . htmlspecialchars($courseCodeDisplay) . "</b> bagi sesi <b>" . htmlspecialchars($year) . " / Semester " . htmlspecialchars($semester) . "</b> telah dikemaskini dalam sistem FYPAssess.<br><br>" .
                               "<b>Maklumat Kuota:</b><br>" .
                               "Kuota yang diperuntukkan: <strong>" . $lecData['quota'] . "</strong><br>" .
                               "Kuota yang tinggal: <strong>" . $lecData['remaining_quota'] . "</strong><br><br>" .
                               "<b>Senarai Pelajar yang Diselia (Supervisor):</b><br>" .
                               $supervisorListHtml . "<br>" .
                               "<b>Senarai Pelajar untuk Dinilai (Assessor):</b><br>" .
                               $assessorListHtml . "<br>" .
                               "Sila log masuk ke sistem FYPAssess untuk melihat maklumat lanjut mengenai pelajar yang diagihkan kepada YBhg. Dato'/Datin/Prof./Dr./Tuan/Puan.<br><br>" .
                               "Untuk sebarang pertanyaan, sila hubungi pihak pentadbir sistem.<br><br>" .
                               "Sekian, terima kasih.<br><br>" .
                               "<b>\"MALAYSIA MADANI\"</b><br>" .
                               "<b>\"BERILMU BERBAKTI\"</b><br><br>" .
                               "Saya yang menjalankan amanah,<br><br>" .
                               "<b>Nurul Saidahtul Fatiha binti Shaharudin</b><br>" .
                               "<b>Pembangun Sistem FYPAssess</b><br>" .
                               
                               "Universiti Putra Malaysia<br><br>" .
                               "<hr style='border: 1px solid #ccc; margin: 20px 0;'>" .
                               "<p style='color: #666; font-size: 12px;'><b>TESTING MODE:</b> This email was originally intended for: " . htmlspecialchars($originalEmail) . "</p>";
                    
                    // Send email
                    $emailResult = sendEmail(
                        $lecturerEmail,
                        $subject,
                        $message,
                        'html'
                    );
                    
                    $emailResults[] = [
                        'lecturer' => $lecData['lecturer_name'],
                        'original_email' => $originalEmail,
                        'sent_to' => $lecturerEmail,
                        'success' => $emailResult['success'],
                        'message' => $emailResult['message'] ?? ''
                    ];
                    
                    // Log email sending result
                    if ($emailResult['success']) {
                        error_log("Successfully sent assignment notification for {$lecData['lecturer_name']} to $lecturerEmail");
                    } else {
                        error_log("Failed to send assignment notification for {$lecData['lecturer_name']}: " . ($emailResult['message'] ?? 'Unknown error'));
                    }
                }
            }
        }
    } catch (Exception $emailEx) {
        // Log email errors but don't fail the save operation
        error_log("Error sending assignment notification emails: " . $emailEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Student assignments saved successfully.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving assignments: ' . $e->getMessage()
    ]);
}

$conn->close();
?>


