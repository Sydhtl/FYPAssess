<?php
include '../mysqlConnect.php';
session_start();

// Ensure only Coordinators can save assignments
if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

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

// Start transaction for all assignments
$conn->begin_transaction();
$assessorMap = buildAssessorMap($conn);

try {
    $processedKeys = [];

    foreach ($assignments as $assignment) {
        $studentId      = $assignment['id'] ?? '';
        $supervisorId   = $assignment['supervisor_id'] ?? null;   // INT (Supervisor.Supervisor_ID)
        $assessor1Id    = $assignment['assessor1_id'] ?? null;
        $assessor2Id    = $assignment['assessor2_id'] ?? null;
        $supervisorName = $assignment['supervisor'] ?? null;
        $assessor1Name  = $assignment['assessor1'] ?? null;
        $assessor2Name  = $assignment['assessor2'] ?? null;

        if (empty($studentId)) {
            continue; // Skip invalid entries
        }

        // 1) Find student's FYP_Session_ID from student table
        $fypSessionId = null;
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

        // If student record not found, skip this assignment
        if (empty($fypSessionId)) {
            continue;
        }

        // 2) Update supervisor in student table (Supervisor_ID follows current selection)
        if (!is_null($supervisorId) && (int)$supervisorId > 0) {
            $updateStudent = $conn->prepare("UPDATE student SET Supervisor_ID = ? WHERE Student_ID = ?");
            if (!$updateStudent) {
                throw new Exception('Prepare failed (updateStudent): ' . $conn->error);
            }
            $supIdInt = (int)$supervisorId;
            $updateStudent->bind_param("is", $supIdInt, $studentId);
            if (!$updateStudent->execute()) {
                throw new Exception('Execute failed (updateStudent): ' . $updateStudent->error);
            }
            $updateStudent->close();
        }

        // Resolve assessor IDs using assessor table mapping (fallback to provided IDs if mapping missing)
        $assessor1Resolved = resolveAssessorId($assessor1Name, $assessorMap);
        if (is_null($assessor1Resolved)) {
            $assessor1Resolved = normalizeId($assessor1Id);
        }
        $assessor2Resolved = resolveAssessorId($assessor2Name, $assessorMap);
        if (is_null($assessor2Resolved)) {
            $assessor2Resolved = normalizeId($assessor2Id);
        }

        // 3) Insert or update student_enrollment
        //    Ensure Fyp_Session_ID follows student's FYP_Session_ID
        $enrollmentId = null;
        // Normalize IDs and upsert enrollment
        upsertStudentEnrollment(
            $conn,
            $studentId,
            $fypSessionId,
            $supervisorId,
            $assessor1Resolved,
            $assessor2Resolved
        );

        $processedKeys[$studentId . '_' . $fypSessionId] = true;
    }

    // After processing provided assignments, ensure EVERY student in DB has an enrollment record
    $allStudentsQuery = "
        SELECT Student_ID, FYP_Session_ID, Supervisor_ID
        FROM student
    ";
    if ($allStudentsResult = $conn->query($allStudentsQuery)) {
        while ($row = $allStudentsResult->fetch_assoc()) {
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

            // Use existing supervisor from student table; assessors remain null
            upsertStudentEnrollment(
                $conn,
                $studentId,
                $fypSessionId,
                $existingSup,
                null,
                null
            );
        }
        $allStudentsResult->free();
    } else {
        throw new Exception('Query failed (allStudentsQuery): ' . $conn->error);
    }

    // Commit all changes
    $conn->commit();

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


