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

// Get filter values from URL or set defaults
$selectedYear = isset($_GET['year']) ? $_GET['year'] : '';
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Fetch distinct FYP Sessions (years) from fyp_session table
$yearOptions = [];
$yearQuery = "SELECT DISTINCT FYP_Session FROM fyp_session ORDER BY FYP_Session DESC";
if ($yearResult = $conn->query($yearQuery)) {
    while ($row = $yearResult->fetch_assoc()) {
        $yearOptions[] = $row['FYP_Session'];
    }
    $yearResult->free();
}

// Set default year if not selected
if (empty($selectedYear) && !empty($yearOptions)) {
    $selectedYear = $yearOptions[0];
}

// Fetch distinct Semesters from fyp_session table
$semesterOptions = [];
$semesterQuery = "SELECT DISTINCT Semester FROM fyp_session ORDER BY Semester";
if ($semesterResult = $conn->query($semesterQuery)) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesterOptions[] = $row['Semester'];
    }
    $semesterResult->free();
}

// Set default semester if not selected
if (empty($selectedSemester) && !empty($semesterOptions)) {
    $selectedSemester = $semesterOptions[0];
}

// Get all FYP_Session_IDs for the selected year and semester in the coordinator's department
$fypSessionIds = [];
$fypSessionQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                    FROM fyp_session fs
                    INNER JOIN course c ON fs.Course_ID = c.Course_ID
                    INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                    WHERE l.Lecturer_ID = ? 
                    AND fs.FYP_Session = ? 
                    AND fs.Semester = ?";
if ($stmt = $conn->prepare($fypSessionQuery)) {
    $stmt->bind_param("ssi", $userId, $selectedYear, $selectedSemester);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $fypSessionIds[] = $row['FYP_Session_ID'];
        }
    }
    $stmt->close();
}

// Fetch supervisors (lecturers) from the same department with their quota history
// Aggregate quotas across all courses for the selected year and semester
$lecturerData = [];
if (!empty($fypSessionIds)) {
    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
    
    $lecturerQuery = "SELECT 
                        l.Lecturer_ID,
                        l.Lecturer_Name,
                        s.Supervisor_ID,
                        SUM(COALESCE(sqh.Quota, 0)) as Quota
                      FROM lecturer l
                      INNER JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                      LEFT JOIN supervisor_quota_history sqh ON s.Supervisor_ID = sqh.Supervisor_ID 
                          AND sqh.FYP_Session_ID IN ($placeholders)
                      WHERE l.Department_ID = (
                          SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                      )
                      GROUP BY l.Lecturer_ID, l.Lecturer_Name, s.Supervisor_ID
                      ORDER BY l.Lecturer_Name";
    
    if ($stmt = $conn->prepare($lecturerQuery)) {
        // Bind FYP_Session_IDs and userId
        $types = str_repeat('i', count($fypSessionIds)) . 's';
        $params = array_merge($fypSessionIds, [$userId]);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $lecturerData[] = [
                    'id' => $row['Supervisor_ID'],
                    'lecturer_id' => $row['Lecturer_ID'],
                    'name' => $row['Lecturer_Name'],
                    'quota' => (int)$row['Quota'],
                    'remaining_quota' => (int)$row['Quota']
                ];
            }
        }
        $stmt->close();
    }
}

// Encode lecturer data as JSON for JavaScript
$lecturerDataJson = json_encode($lecturerData);
