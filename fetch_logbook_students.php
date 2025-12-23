<?php
// fetch_logbook_students.php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1);

include '../db_connect.php';

// A. Get Login ID 
if (isset($_SESSION['user_id'])) {
    $loginID = $_SESSION['user_id'];
} else {
    $loginID = 'hazura'; // Fallback to hazura
}

// Get Supervisor_ID from login ID - hazura should have Supervisor_ID = 5
$supervisorID = null;
$stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("s", $loginID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $supervisorID = $row['Supervisor_ID'];
}

// If supervisor not found, use hazura's ID (5) as fallback
if (!$supervisorID) {
    $supervisorID = 5; // Fallback to hazura's Supervisor_ID
}

// Get course_id filter (optional)
$courseID = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// Fetch students supervised by this supervisor, grouped by course
// Note: FYP_Session_ID in student_enrollment is VARCHAR, we join by casting fyp_session.FYP_Session_ID to string
// Simplified query - removed course table join as it may not exist, using course_id directly
$sql = "SELECT DISTINCT
            s.Student_ID,
            s.Student_Name,
            s.Course_ID,
            se.FYP_Session_ID,
            fs.FYP_Session,
            fs.Semester,
            (SELECT COUNT(*) FROM logbook WHERE Student_ID = s.Student_ID AND (Supervisor_ID = ? OR Supervisor_ID IS NULL)) as total_submitted,
            (SELECT COUNT(*) FROM logbook WHERE Student_ID = s.Student_ID AND (Supervisor_ID = ? OR Supervisor_ID IS NULL) AND Logbook_Status = 'Approved') as total_approved
        FROM student s
        INNER JOIN student_enrollment se ON s.Student_ID = se.Student_ID
        LEFT JOIN fyp_session fs ON CAST(fs.FYP_Session_ID AS CHAR) = se.FYP_Session_ID
        WHERE se.Supervisor_ID = ?";

$params = [$supervisorID, $supervisorID, $supervisorID];
$types = "iii";

if ($courseID) {
    $sql .= " AND s.Course_ID = ?";
    $params[] = $courseID;
    $types .= "i";
}

$sql .= " ORDER BY s.Course_ID, s.Student_Name";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$courses = [];

while ($row = $result->fetch_assoc()) {
    $courseID = $row['Course_ID'];

    if (!isset($courses[$courseID])) {
        $courses[$courseID] = [
            'course_code' => 'Course ' . $courseID,
            'course_name' => 'Course ' . $courseID,
            'course_id' => $courseID,
            'students' => []
        ];
    }

    // Fetch logbooks for this student (including those with NULL Supervisor_ID)
    $logbookStmt = $conn->prepare(
        "SELECT Logbook_ID, Logbook_Name, Logbook_Status, Logbook_Date 
         FROM logbook 
         WHERE Student_ID = ? AND (Supervisor_ID = ? OR Supervisor_ID IS NULL)
         ORDER BY Logbook_Date ASC"
    );
    $logbookStmt->bind_param("si", $row['Student_ID'], $supervisorID);
    $logbookStmt->execute();
    $logbookResult = $logbookStmt->get_result();

    $logbooks = [];
    while ($logbook = $logbookResult->fetch_assoc()) {
        $logbooks[] = [
            'logbook_id' => $logbook['Logbook_ID'],
            'logbook_name' => $logbook['Logbook_Name'],
            'status' => $logbook['Logbook_Status'],
            'date' => $logbook['Logbook_Date']
        ];
    }

    $courses[$courseID]['students'][] = [
        'student_id' => $row['Student_ID'],
        'student_name' => $row['Student_Name'],
        'total_submitted' => intval($row['total_submitted']),
        'total_approved' => intval($row['total_approved']),
        'logbooks' => $logbooks
    ];
}

echo json_encode([
    'success' => true,
    'courses' => array_values($courses)
]);
