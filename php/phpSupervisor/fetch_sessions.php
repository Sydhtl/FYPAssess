<?php
// fetch_sessions.php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

$response = ['status' => 'error', 'sessions' => []];

try {
    // Fetch sessions where Semester = 1, ordered by latest session first
    $sql = "SELECT FYP_Session_ID, FYP_Session 
            FROM fyp_session 
            WHERE Semester = 1 
            ORDER BY FYP_Session DESC";
            
    $result = $conn->query($sql);
    
    $sessions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sessions[] = [
                'FYP_Session_ID' => $row['FYP_Session_ID'],
                'FYP_Session' => $row['FYP_Session']
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