<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['upmId'];

try {
    // Get coordinator's department
    $deptQuery = "SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1";
    $deptStmt = $conn->prepare($deptQuery);
    if (!$deptStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $deptStmt->bind_param('s', $userId);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    if (!$deptRow = $deptResult->fetch_assoc()) {
        throw new Exception('Coordinator not found');
    }
    $departmentId = $deptRow['Department_ID'];
    $deptStmt->close();

    // Get courses for this department
    $coursesQuery = "SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code";
    $coursesStmt = $conn->prepare($coursesQuery);
    if (!$coursesStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $coursesStmt->bind_param('i', $departmentId);
    $coursesStmt->execute();
    $coursesResult = $coursesStmt->get_result();
    
    $courses = [];
    while ($row = $coursesResult->fetch_assoc()) {
        $courses[] = [
            'course_id' => $row['Course_ID'],
            'course_code' => $row['Course_Code']
        ];
    }
    $coursesStmt->close();

    echo json_encode([
        'success' => true,
        'courses' => $courses
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
