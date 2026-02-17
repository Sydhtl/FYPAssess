<?php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

$studentId = isset($_GET['student_id']) ? $_GET['student_id'] : '';

if (empty($studentId)) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required']);
    exit;
}

// Fetch approved logbook counts for both SWE4949A (Course_ID = 1) and SWE4949B (Course_ID = 2)
$counts = [
    'SWE4949A' => 0,
    'SWE4949B' => 0
];

// Get counts for SWE4949A (Course_ID = 1)
$sqlA = "SELECT COUNT(*) as count 
         FROM logbook 
         WHERE Student_ID = ? 
         AND Course_ID = 1 
         AND Logbook_Status = 'Approved'";
$stmtA = $conn->prepare($sqlA);
$stmtA->bind_param("s", $studentId);
$stmtA->execute();
$resultA = $stmtA->get_result();
if ($rowA = $resultA->fetch_assoc()) {
    $counts['SWE4949A'] = (int) $rowA['count'];
}
$stmtA->close();

// Get counts for SWE4949B (Course_ID = 2)
$sqlB = "SELECT COUNT(*) as count 
         FROM logbook 
         WHERE Student_ID = ? 
         AND Course_ID = 2 
         AND Logbook_Status = 'Approved'";
$stmtB = $conn->prepare($sqlB);
$stmtB->bind_param("s", $studentId);
$stmtB->execute();
$resultB = $stmtB->get_result();
if ($rowB = $resultB->fetch_assoc()) {
    $counts['SWE4949B'] = (int) $rowB['count'];
}
$stmtB->close();

echo json_encode([
    'status' => 'success',
    'counts' => $counts
]);

$conn->close();
?>