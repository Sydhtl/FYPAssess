<?php
// fetch_collaboration.php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();
ob_clean();
header('Content-Type: application/json');
include '../db_connect.php';

$sessionID = isset($_GET['session_id']) ? $_GET['session_id'] : null;
$loginID = $_SESSION['upmId'] ?? 'hazura';

if (!$sessionID) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Session ID']);
    exit;
}

// Get Supervisor ID
$supervisorID = null;
$stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
$stmt->bind_param("s", $loginID);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $supervisorID = $row['Supervisor_ID'];
} else {
    echo json_encode(['status' => 'error', 'message' => 'Supervisor not found']);
    exit;
}

// Fetch Data using YOUR specific columns
$sql = "SELECT * FROM collaboration WHERE FYP_Session_ID = ? AND Supervisor_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sessionID, $supervisorID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // Parse comma-separated values into arrays
    $supervisorTopics = !empty($row['Supervisor_Title']) ? explode(',', $row['Supervisor_Title']) : [];
    $industryTopics = !empty($row['Company_Title']) ? explode(',', $row['Company_Title']) : [];
    $requiredSkills = !empty($row['Required_Skills']) ? explode(',', $row['Required_Skills']) : [];

    // Parse comma-separated supervisor data into array of objects
    $supervisorNames = !empty($row['Company_Supervisor_Name']) ? explode(',', $row['Company_Supervisor_Name']) : [];
    $supervisorEmails = !empty($row['Company_Supervisor_Email']) ? explode(',', $row['Company_Supervisor_Email']) : [];
    $supervisorPhones = !empty($row['Company_Supervisor_Phone']) ? explode(',', $row['Company_Supervisor_Phone']) : [];
    $supervisorRoles = !empty($row['Company_Supervisor_Role']) ? explode(',', $row['Company_Supervisor_Role']) : [];

    $industrySupervisors = [];
    for ($i = 0; $i < count($supervisorNames); $i++) {
        $industrySupervisors[] = [
            'name' => $supervisorNames[$i] ?? '',
            'email' => $supervisorEmails[$i] ?? '',
            'phone' => $supervisorPhones[$i] ?? '',
            'role' => $supervisorRoles[$i] ?? ''
        ];
    }

    // Map DB columns to Frontend keys
    $data = [
        'Collaboration_ID' => $row['Collaboration_ID'],
        'Collaboration_Status' => $row['Collaboration_Status'],

        // Address Breakdown
        'Company_Name' => $row['Company_Name'],
        'Company_Email' => $row['Company_Email'],
        'Company_Address' => $row['Company_Address'],
        'Company_Postcode' => $row['Postcode'],      // Mapped from DB 'Postcode'
        'Company_City' => $row['City'],          // Mapped from DB 'City'
        'Company_State' => $row['State'],         // Mapped from DB 'State'


        // Quota & Qual
        'Student_Quota' => $row['Student_Quota'],
        'Academic_Qualification' => $row['Academic_Qualification'],

        // Arrays for dynamic lists
        'Topic_List' => $supervisorTopics,
        'Ind_Topic_List' => $industryTopics,
        'Skill_List' => $requiredSkills,
        'Supervisor_List' => $industrySupervisors
    ];

    echo json_encode(['status' => 'success', 'data' => $data]);

} else {
    echo json_encode(['status' => 'success', 'data' => null]);
}
?>