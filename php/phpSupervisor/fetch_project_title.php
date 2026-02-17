<?php
include '../db_connect.php';

header('Content-Type: application/json');

// 1. Receive Inputs
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$searchQuery = isset($input['search']) ? trim($input['search']) : '';
$selectedSessions = isset($input['sessions']) && is_array($input['sessions']) ? $input['sessions'] : [];

// 2. Build Query
// Joins: Project -> Student -> Enrollment -> Session -> Supervisor -> Lecturer
$sql = "SELECT DISTINCT
            p.Project_Title,
            s.Student_Name,
            l.Lecturer_Name AS Supervisor_Name,
            fs.FYP_Session
        FROM fyp_project p
        JOIN student s ON p.Student_ID = s.Student_ID
        JOIN student_enrollment se ON p.Student_ID = se.Student_ID AND s.FYP_Session_ID = se.FYP_Session_ID
        JOIN fyp_session fs ON se.FYP_Session_ID = fs.FYP_Session_ID
        LEFT JOIN supervisor sv ON se.Supervisor_ID = sv.Supervisor_ID
        LEFT JOIN lecturer l ON sv.Lecturer_ID = l.Lecturer_ID
        WHERE 1=1";

$params = [];
$types = "";

// --- LOGIC: Filter by Search Query ---
// If text exists, search Title, Student, or Supervisor
if (!empty($searchQuery)) {
    $sql .= " AND (p.Project_Title LIKE ? OR s.Student_Name LIKE ? OR l.Lecturer_Name LIKE ?)";
    $searchTerm = "%" . $searchQuery . "%";

    // Bind 3 times
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// --- LOGIC: Filter by Session (Year) ---
// If this array is empty (no boxes ticked), default to Semester 1 only
if (!empty($selectedSessions)) {
    $placeholders = implode(',', array_fill(0, count($selectedSessions), '?'));
    $sql .= " AND fs.FYP_Session IN ($placeholders)";

    foreach ($selectedSessions as $session) {
        $params[] = $session;
        $types .= "s";
    }
} else {
    // When no filter selected, show only Semester 2 projects
    $sql .= " AND fs.Semester = '2'";
}

// Order by newest session first
$sql .= " ORDER BY fs.FYP_Session DESC";

// 3. Execute Query
$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = [
        'title' => htmlspecialchars($row['Project_Title'] ?? 'Untitled'),
        'student' => htmlspecialchars($row['Student_Name'] ?? 'Unknown Student'),
        'supervisor' => htmlspecialchars($row['Supervisor_Name'] ?? 'No Supervisor'),
        'year' => htmlspecialchars($row['FYP_Session'])
    ];
}

echo json_encode($projects);

$stmt->close();
$conn->close();
?>