<?php
header('Content-Type: application/json');
include '../mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];
$response = [
    'success' => false,
    'approvalDisplay' => 'NO FYP TITLE',
    'approvedCount' => 0,
    'waitingCount' => 0,
    'rejectedCount' => 0,
    'requiredTotal' => 6,
    'assessmentDateTime' => null,
];

// Basic student/session info
$studentQuery = "SELECT 
        s.Student_ID,
        s.Semester,
        fs.FYP_Session,
        fs.FYP_Session_ID,
        fp.Title_Status
    FROM student s
    LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
    LEFT JOIN fyp_project fp ON fp.Student_ID = s.Student_ID
    WHERE s.Student_ID = ?
    ORDER BY fs.FYP_Session_ID DESC
    LIMIT 1";

if ($stmt = $conn->prepare($studentQuery)) {
    $stmt->bind_param('s', $studentId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $fypSessionId = (int)($row['FYP_Session_ID'] ?? 0);
            $titleStatus = trim($row['Title_Status'] ?? '');

            if ($titleStatus === 'Approved') {
                $response['approvalDisplay'] = 'APPROVED';
            } elseif ($titleStatus === 'Rejected' || strcasecmp($titleStatus, 'Declined') === 0) {
                $response['approvalDisplay'] = 'REJECTED';
            } elseif ($titleStatus === '') {
                $response['approvalDisplay'] = 'NO FYP TITLE';
            } else {
                $response['approvalDisplay'] = 'PENDING';
            }

            // Logbook counts
            $logStmt = $conn->prepare('SELECT Logbook_Status FROM logbook WHERE Student_ID = ? AND Fyp_Session_ID = ?');
            if ($logStmt) {
                $logStmt->bind_param('si', $studentId, $fypSessionId);
                if ($logStmt->execute()) {
                    $logRes = $logStmt->get_result();
                    while ($logRow = $logRes->fetch_assoc()) {
                        $status = trim($logRow['Logbook_Status'] ?? '');
                        if ($status === 'Approved') {
                            $response['approvedCount']++;
                        } elseif (strcasecmp($status, 'Declined') === 0 || $status === 'Rejected') {
                            $response['rejectedCount']++;
                        } else {
                            $response['waitingCount']++;
                        }
                    }
                }
                $logStmt->close();
            }

            // Assessment info for countdown
            $asmtQuery = "SELECT as_session.Date, as_session.Time
                          FROM student_session ss
                          INNER JOIN assessment_session as_session ON ss.Session_ID = as_session.Session_ID
                          WHERE ss.Student_ID = ? AND ss.FYP_Session_ID = ?
                          ORDER BY as_session.Date ASC, as_session.Time ASC
                          LIMIT 1";
            if ($asmtStmt = $conn->prepare($asmtQuery)) {
                $asmtStmt->bind_param('si', $studentId, $fypSessionId);
                if ($asmtStmt->execute()) {
                    $asmtRes = $asmtStmt->get_result();
                    if ($asmtRow = $asmtRes->fetch_assoc()) {
                        $date = $asmtRow['Date'] ?? null;
                        $time = $asmtRow['Time'] ?? null;
                        if ($date) {
                            $response['assessmentDateTime'] = $time ? ($date . ' ' . $time) : ($date . ' 00:00:00');
                        }
                    }
                }
                $asmtStmt->close();
            }

            $response['success'] = true;
        }
    }
    $stmt->close();
}

echo json_encode($response);
