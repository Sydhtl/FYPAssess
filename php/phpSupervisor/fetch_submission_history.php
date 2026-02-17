<?php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

// Get current supervisor ID
$loginID = isset($_SESSION['upmId']) ? $_SESSION['upmId'] : 'hazura';

$currentUserID = null;
$stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
$stmt->bind_param("s", $loginID);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $currentUserID = $row['Supervisor_ID'];
}
$stmt->close();

if (!$currentUserID) {
    echo json_encode(['status' => 'error', 'message' => 'Supervisor not found']);
    exit;
}

// Fetch submission history
$submissionHistory = [];
$sqlHist = "SELECT c.Collaboration_ID, fs.FYP_Session, fs.Semester
            FROM collaboration c
            LEFT JOIN fyp_session fs ON c.FYP_Session_ID = fs.FYP_Session_ID
            WHERE c.Supervisor_ID = ?
            ORDER BY fs.FYP_Session DESC, fs.Semester DESC";

if ($stmtHist = $conn->prepare($sqlHist)) {
    $stmtHist->bind_param("i", $currentUserID);
    $stmtHist->execute();
    $resHist = $stmtHist->get_result();
    while ($row = $resHist->fetch_assoc()) {
        $label = ($row['FYP_Session'] && $row['Semester'])
            ? $row['FYP_Session'] . ' - ' . $row['Semester']
            : "Form #" . $row['Collaboration_ID'];

        $submissionHistory[] = [
            'id' => $row['Collaboration_ID'],
            'label' => $label
        ];
    }
    $stmtHist->close();
}

echo json_encode([
    'status' => 'success',
    'history' => $submissionHistory
]);

$conn->close();
?>