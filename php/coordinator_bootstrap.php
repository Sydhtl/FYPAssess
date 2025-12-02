<?php
include __DIR__ . '/mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    header("Location: ../html/login/Login.php");
    exit();
}

$userId = $_SESSION['upmId'];
$coordinatorName = 'Coordinator';

// Attempt to resolve coordinator name from lecturer table
if ($stmt = $conn->prepare("SELECT Lecturer_Name FROM lecturer WHERE Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['Lecturer_Name'])) {
                $coordinatorName = $row['Lecturer_Name'];
            }
        }
    }
    $stmt->close();
}
