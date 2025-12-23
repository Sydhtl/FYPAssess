<?php
// fetchExistingMarks.php
session_start();
include '../db_connect.php';
header('Content-Type: application/json');

try {
    // Get parameters
    $studentId = $_GET['student_id'] ?? null;
    $assessmentId = $_GET['assessment_id'] ?? null;
    $role = $_GET['role'] ?? 'supervisor';

    if (!$studentId || !$assessmentId) {
        throw new Exception("Missing required parameters");
    }

    // Get current user ID
    if (isset($_SESSION['upmId'])) {
        $lecturerUPM = $_SESSION['upmId'];
    } else {
        $lecturerUPM = 'hazura'; // Fallback for testing
    }

    // Lookup the specific Numeric ID for this role
    $currentUserID = null;
    if ($role === 'supervisor') {
        $stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
        $stmt->bind_param("s", $lecturerUPM);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $currentUserID = $row['Supervisor_ID'];
        }
    } elseif ($role === 'assessor') {
        $stmt = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
        $stmt->bind_param("s", $lecturerUPM);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $currentUserID = $row['Assessor_ID'];
        }
    }

    if (!$currentUserID) {
        throw new Exception("User not found");
    }

    // Fetch existing marks
    $marks = [];
    if ($role === 'supervisor') {
        $sqlMarks = "SELECT Criteria_ID, Subcriteria_ID, Given_Marks 
                     FROM evaluation 
                     WHERE Student_ID = ? AND Assessment_ID = ? AND Supervisor_ID = ?";
        $stmtMarks = $conn->prepare($sqlMarks);
        $stmtMarks->bind_param("sii", $studentId, $assessmentId, $currentUserID);
    } else {
        $sqlMarks = "SELECT Criteria_ID, Subcriteria_ID, Given_Marks 
                     FROM evaluation 
                     WHERE Student_ID = ? AND Assessment_ID = ? AND Assessor_ID = ?";
        $stmtMarks = $conn->prepare($sqlMarks);
        $stmtMarks->bind_param("sii", $studentId, $assessmentId, $currentUserID);
    }

    $stmtMarks->execute();
    $result = $stmtMarks->get_result();

    while ($row = $result->fetch_assoc()) {
        $marks[] = [
            'criteria_id' => $row['Criteria_ID'],
            'subcriteria_id' => $row['Subcriteria_ID'],
            'given_marks' => $row['Given_Marks']
        ];
    }

    // Fetch existing comment
    $comment = '';
    $roleColumn = ($role === 'supervisor') ? 'Supervisor_ID' : 'Assessor_ID';
    $sqlComment = "SELECT Given_Comment FROM comment 
                   WHERE Student_ID = ? AND Assessment_ID = ? AND $roleColumn = ?";
    $stmtComment = $conn->prepare($sqlComment);
    $stmtComment->bind_param("sii", $studentId, $assessmentId, $currentUserID);
    $stmtComment->execute();
    $commentResult = $stmtComment->get_result();

    if ($commentRow = $commentResult->fetch_assoc()) {
        $storedComment = $commentRow['Given_Comment'];

        // Strip assessment name prefix if it exists (format: "Assessment Name: comment")
        if (strpos($storedComment, ':') !== false) {
            $parts = explode(':', $storedComment, 2);
            $comment = trim($parts[1]);
        } else {
            $comment = $storedComment;
        }
    }

    echo json_encode([
        'status' => 'success',
        'marks' => $marks,
        'comment' => $comment
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>