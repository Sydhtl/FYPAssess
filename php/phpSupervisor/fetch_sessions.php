<?php
// fetch_sessions.php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$response = ['status' => 'error', 'sessions' => []];

// Get logged-in lecturer's UPM ID
$loginID = isset($_SESSION['upmId']) ? $_SESSION['upmId'] : null;

if (!$loginID) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

try {
    // Fetch sessions filtered by lecturer's department courses
    // Group by year and semester to avoid duplicates from multiple courses
    $sql = "SELECT MIN(fs.FYP_Session_ID) as FYP_Session_ID, fs.FYP_Session, fs.Semester 
            FROM fyp_session fs
            JOIN course c ON fs.Course_ID = c.Course_ID
            JOIN lecturer l ON c.Department_ID = l.Department_ID
            WHERE l.Lecturer_ID = ?
            GROUP BY fs.FYP_Session, fs.Semester
            ORDER BY fs.FYP_Session DESC, fs.Semester DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    $result = $stmt->get_result();

    $sessions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sessions[] = [
                'FYP_Session_ID' => $row['FYP_Session_ID'],
                'FYP_Session' => $row['FYP_Session'],
                'Semester' => $row['Semester']
            ];
        }
        $response['status'] = 'success';
        $response['sessions'] = $sessions;
    } else {
        $response['message'] = "Database query failed.";
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>