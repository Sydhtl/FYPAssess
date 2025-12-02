<?php
include '../../../php/mysqlConnect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_POST['student_id'] ?? '';
$courseId = $_POST['course_id'] ?? null;
$logbookTitle = $_POST['logbook_title'] ?? '';
$logbookDate = $_POST['logbook_date'] ?? '';
$agendasJson = $_POST['agendas'] ?? '[]';

// Validate input
if (empty($studentId) || empty($courseId) || empty($logbookTitle) || empty($logbookDate)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Verify student matches session
if ($studentId !== $_SESSION['upmId']) {
    echo json_encode(['success' => false, 'error' => 'Student ID mismatch']);
    exit();
}

// Decode agendas early and validate at least one non-empty agenda
$agendas = json_decode($agendasJson, true);
if (!is_array($agendas)) { $agendas = []; }
$validAgendaCount = 0;
foreach ($agendas as $a) {
    $t = trim($a['name'] ?? '');
    $c = trim($a['explanation'] ?? '');
    if ($t !== '' && $c !== '') { $validAgendaCount++; }
}
if ($validAgendaCount === 0) {
    echo json_encode(['success' => false, 'error' => 'At least one agenda (title + explanation) is required']);
    exit();
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO logbook (Student_ID, course_id, Logbook_Name, Logbook_Date, Logbook_Status) VALUES (?, ?, ?, ?, 'Waiting for approval')");
    if (!$stmt) { throw new Exception("Prepare failed: " . $conn->error); }
    $stmt->bind_param("siss", $studentId, $courseId, $logbookTitle, $logbookDate);
    if (!$stmt->execute()) { throw new Exception("Execute failed: " . $stmt->error); }
    $logbookId = $conn->insert_id;
    $stmt->close();
    $stmtAgenda = $conn->prepare("INSERT INTO logbook_agenda (Logbook_ID, Agenda_Title, Agenda_Content) VALUES (?, ?, ?)");
    if (!$stmtAgenda) { throw new Exception("Agenda prepare failed: " . $conn->error); }
    foreach ($agendas as $agenda) {
        $agendaTitle = trim($agenda['name'] ?? '');
        $agendaContent = trim($agenda['explanation'] ?? '');
        if ($agendaTitle !== '' && $agendaContent !== '') {
            $stmtAgenda->bind_param("iss", $logbookId, $agendaTitle, $agendaContent);
            if (!$stmtAgenda->execute()) { throw new Exception("Agenda execute failed: " . $stmtAgenda->error); }
        }
    }
    $stmtAgenda->close();
    $conn->commit();
    echo json_encode(['success' => true, 'logbook_id' => $logbookId]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
