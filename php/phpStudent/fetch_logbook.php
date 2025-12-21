<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];

// Ensure logbook table exists
$checkTableQuery = "SHOW TABLES LIKE 'logbook'";
$tableExists = $conn->query($checkTableQuery);
if (!$tableExists || $tableExists->num_rows === 0) {
    echo json_encode(['success' => true, 'entries' => []]);
    $conn->close();
    exit();
}

$logbookEntries = [];
$logbookQuery = "SELECT Logbook_ID, course_id, Logbook_Name, Logbook_Status, Logbook_Date FROM logbook WHERE Student_ID = ? ORDER BY Logbook_Date DESC, Logbook_ID DESC";
$stmtLogbook = $conn->prepare($logbookQuery);
if ($stmtLogbook) {
    $stmtLogbook->bind_param("s", $studentId);
    $stmtLogbook->execute();
    $logbookResult = $stmtLogbook->get_result();
    while ($row = $logbookResult->fetch_assoc()) {
        // Fetch agendas per logbook
        $agendas = [];
        $agendaQuery = "SELECT Agenda_Title, Agenda_Content FROM logbook_agenda WHERE Logbook_ID = ? ORDER BY Agenda_ID";
        $stmtAgenda = $conn->prepare($agendaQuery);
        if ($stmtAgenda) {
            $stmtAgenda->bind_param("i", $row['Logbook_ID']);
            $stmtAgenda->execute();
            $agendaResult = $stmtAgenda->get_result();
            while ($agendaRow = $agendaResult->fetch_assoc()) {
                $agendas[] = [
                    'name' => $agendaRow['Agenda_Title'],
                    'explanation' => $agendaRow['Agenda_Content']
                ];
            }
            $stmtAgenda->close();
        }
        $logbookEntries[] = [
            'Logbook_ID' => (int)$row['Logbook_ID'],
            'course_id' => isset($row['course_id']) ? (int)$row['course_id'] : null,
            'Logbook_Name' => $row['Logbook_Name'],
            'Logbook_Status' => $row['Logbook_Status'] ?? 'Waiting for approval',
            'Logbook_Date' => $row['Logbook_Date'],
            'agendas' => $agendas
        ];
    }
    $stmtLogbook->close();
}

echo json_encode(['success' => true, 'entries' => $logbookEntries]);
$conn->close();
?>