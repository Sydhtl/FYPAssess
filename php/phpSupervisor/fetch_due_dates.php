<?php
include __DIR__ . '/../db_connect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['upmId'];

try {
    // Get supervisor ID
    $supervisorID = null;
    $supStmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ? LIMIT 1");
    if (!$supStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $supStmt->bind_param('s', $userId);
    $supStmt->execute();
    $supResult = $supStmt->get_result();
    if ($supRow = $supResult->fetch_assoc()) {
        $supervisorID = $supRow['Supervisor_ID'];
    }
    $supStmt->close();

    if (!$supervisorID) {
        throw new Exception('Supervisor not found');
    }

    // Get latest FYP_Session_ID
    $latestSessionID = null;
    $sessionStmt = $conn->prepare("
        SELECT fs.FYP_Session_ID, fs.FYP_Session, fs.Semester, c.Course_Code 
        FROM fyp_session fs
        JOIN course c ON fs.Course_ID = c.Course_ID
        ORDER BY fs.FYP_Session_ID DESC
        LIMIT 1
    ");
    if ($sessionStmt) {
        $sessionStmt->execute();
        $sessionResult = $sessionStmt->get_result();
        if ($sessionRow = $sessionResult->fetch_assoc()) {
            $latestSessionID = $sessionRow['FYP_Session_ID'];
        }
        $sessionStmt->close();
    }

    if (!$latestSessionID) {
        throw new Exception('No active session found');
    }

    // Fetch due dates for supervisor assessments
    $dueDates = [];
    $dueDateStmt = $conn->prepare("
        SELECT 
            dd.Due_ID,
            dd.Start_Date,
            dd.End_Date,
            dd.Start_Time,
            dd.End_Time,
            dd.Role,
            a.Assessment_ID,
            a.Assessment_Name,
            c.Course_Code
        FROM due_date dd
        JOIN assessment a ON dd.Assessment_ID = a.Assessment_ID
        JOIN course c ON a.Course_ID = c.Course_ID
        WHERE dd.Role = 'Supervisor'
        AND dd.FYP_Session_ID = ?
        AND dd.End_Date >= CURDATE()
        ORDER BY dd.Start_Date ASC, dd.Start_Time ASC
    ");

    if ($dueDateStmt) {
        $dueDateStmt->bind_param('i', $latestSessionID);
        $dueDateStmt->execute();
        $dueDateResult = $dueDateStmt->get_result();

        while ($row = $dueDateResult->fetch_assoc()) {
            $dueDates[] = [
                'due_id' => $row['Due_ID'],
                'start_date' => $row['Start_Date'],
                'end_date' => $row['End_Date'],
                'start_time' => $row['Start_Time'],
                'end_time' => $row['End_Time'],
                'role' => $row['Role'],
                'assessment_id' => $row['Assessment_ID'],
                'assessment_name' => $row['Assessment_Name'],
                'course_code' => $row['Course_Code']
            ];
        }
        $dueDateStmt->close();
    }

    echo json_encode([
        'success' => true,
        'due_dates' => $dueDates,
        'session_id' => $latestSessionID
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>