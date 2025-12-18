<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid course ID']);
    exit();
}

try {
    // Get assessments for this course
    $assessmentsQuery = "SELECT Assessment_ID, Assessment_Name FROM assessment WHERE Course_ID = ? ORDER BY Assessment_Name";
    $assessmentsStmt = $conn->prepare($assessmentsQuery);
    if (!$assessmentsStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $assessmentsStmt->bind_param('i', $courseId);
    $assessmentsStmt->execute();
    $assessmentsResult = $assessmentsStmt->get_result();
    
    $assessments = [];
    while ($row = $assessmentsResult->fetch_assoc()) {
        $assessments[] = [
            'assessment_id' => $row['Assessment_ID'],
            'assessment_name' => $row['Assessment_Name']
        ];
    }
    $assessmentsStmt->close();

    echo json_encode([
        'success' => true,
        'assessments' => $assessments
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
