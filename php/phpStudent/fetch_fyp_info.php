<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];

// Fetch the latest student FYP info including project title, proposed title, and status
$query = "SELECT 
    s.Student_ID,
    s.Student_Name,
    s.Semester,
    d.Programme_Name,
    d.Department_ID,
    fs.FYP_Session,
    c.Course_Code,
    l.Lecturer_Name,
    fp.Project_Title,
    fp.Proposed_Title,
    fp.Title_Status
FROM student s
LEFT JOIN department d ON s.Department_ID = d.Department_ID
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
LEFT JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
WHERE s.Student_ID = ?
ORDER BY fs.FYP_Session_ID DESC
LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    $stmt->close();
    $conn->close();
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

$studentName = $student['Student_Name'] ?? 'N/A';
$matricNo = $student['Student_ID'] ?? 'N/A';
$programmeName = $student['Programme_Name'] ?? 'N/A';
$departmentId = isset($student['Department_ID']) ? (int)$student['Department_ID'] : null;
$semesterRaw = $student['Semester'] ?? 'N/A';
$fypSession = $student['FYP_Session'] ?? 'N/A';
$courseCode = $student['Course_Code'] ?? 'N/A';
$supervisorName = $student['Lecturer_Name'] ?? 'N/A';
$titleStatus = $student['Title_Status'] ?? '';
$proposedTitle = $student['Proposed_Title'] ?? '';
$projectTitle = $student['Project_Title'] ?? 'No title assigned';

// Comments for this student
$commentsData = [];
$commentStmt = $conn->prepare("SELECT Comment_ID, Given_Comment FROM `comment` WHERE Student_ID = ? ORDER BY Comment_ID ASC");
if ($commentStmt) {
    $commentStmt->bind_param("s", $studentId);
    $commentStmt->execute();
    $commentResult = $commentStmt->get_result();
    while ($cRow = $commentResult->fetch_assoc()) {
        $commentsData[] = [
            'id' => (int)$cRow['Comment_ID'],
            'text' => $cRow['Given_Comment'] ?? ''
        ];
    }
    $commentStmt->close();
}

$response = [
    'success' => true,
    'student' => [
        'studentName' => $studentName,
        'matricNo' => $matricNo,
        'programmeName' => $programmeName,
        'semester' => $semesterRaw,
        'fypSession' => $fypSession,
        'courseCode' => $courseCode,
        'supervisorName' => $supervisorName,
        'projectTitle' => $projectTitle,
        'proposedTitle' => $proposedTitle,
        'titleStatus' => $titleStatus,
        'departmentId' => $departmentId
    ],
    'comments' => $commentsData
];

echo json_encode($response);
$conn->close();
?>