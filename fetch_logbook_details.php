<?php
// fetch_logbook_details.php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Get logbook ID from request
$logbookID = isset($_GET['logbook_id']) ? intval($_GET['logbook_id']) : null;

if (!$logbookID) {
    echo json_encode(['success' => false, 'message' => 'Logbook ID required']);
    exit;
}

// Fetch logbook details with student information
// Note: FYP_Session_ID in student_enrollment is VARCHAR
$sql = "SELECT 
            l.Logbook_ID,
            l.Student_ID,
            l.Logbook_Name,
            l.Logbook_Status,
            l.Logbook_Date,
            l.Supervisor_ID,
            l.Course_ID,
            s.Student_Name,
            c.Course_Code,
            c.Course_Name,
            fs.FYP_Session,
            fs.Semester
        FROM logbook l
        INNER JOIN student s ON l.Student_ID = s.Student_ID
        INNER JOIN course c ON l.Course_ID = c.Course_ID
        LEFT JOIN student_enrollment se ON s.Student_ID = se.Student_ID
        LEFT JOIN fyp_session fs ON CAST(fs.FYP_Session_ID AS CHAR) = se.FYP_Session_ID
        WHERE l.Logbook_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $logbookID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Logbook not found']);
    exit;
}

$logbook = $result->fetch_assoc();

// Fetch agendas for this logbook
$agendaStmt = $conn->prepare(
    "SELECT Agenda_ID, Agenda_Title, Agenda_Content 
     FROM logbook_agenda 
     WHERE Logbook_ID = ? 
     ORDER BY Agenda_ID ASC"
);
$agendaStmt->bind_param("i", $logbookID);
$agendaStmt->execute();
$agendaResult = $agendaStmt->get_result();

$agendas = [];
while ($agenda = $agendaResult->fetch_assoc()) {
    $agendas[] = [
        'agenda_id' => $agenda['Agenda_ID'],
        'title' => $agenda['Agenda_Title'],
        'content' => $agenda['Agenda_Content']
    ];
}

echo json_encode([
    'success' => true,
    'logbook' => [
        'logbook_id' => $logbook['Logbook_ID'],
        'student_id' => $logbook['Student_ID'],
        'student_name' => $logbook['Student_Name'],
        'logbook_name' => $logbook['Logbook_Name'],
        'status' => $logbook['Logbook_Status'],
        'date' => $logbook['Logbook_Date'],
        'course_code' => $logbook['Course_Code'],
        'course_name' => $logbook['Course_Name'],
        'fyp_session' => $logbook['FYP_Session'] ?? 'N/A',
        'semester' => $logbook['Semester'] ?? 'N/A',
        'agendas' => $agendas
    ]
]);
