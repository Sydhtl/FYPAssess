<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['upmId'];

try {
    // Get parameters
    $selectedYear = isset($_GET['year']) ? $_GET['year'] : null;
    $selectedSemester = isset($_GET['semester']) ? (int)$_GET['semester'] : null;

    // Get coordinator's department ID
    $deptStmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1");
    if (!$deptStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $deptStmt->bind_param('s', $userId);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    if (!$deptRow = $deptResult->fetch_assoc()) {
        throw new Exception('Coordinator not found');
    }
    $departmentId = $deptRow['Department_ID'];
    $deptStmt->close();

    // Get FYP session IDs based on year and semester filters
    $fypSessionIds = [];
    if ($selectedYear && $selectedSemester) {
        $sessionQuery = "SELECT DISTINCT FYP_Session_ID FROM fyp_session 
                        WHERE FYP_Session = ? AND Semester = ?";
        $sessionStmt = $conn->prepare($sessionQuery);
        if (!$sessionStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $sessionStmt->bind_param('si', $selectedYear, $selectedSemester);
        $sessionStmt->execute();
        $sessionResult = $sessionStmt->get_result();
        while ($row = $sessionResult->fetch_assoc()) {
            $fypSessionIds[] = $row['FYP_Session_ID'];
        }
        $sessionStmt->close();
    } else {
        // If no filters, get all sessions for the department
        $sessionQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                        FROM fyp_session fs
                        INNER JOIN student s ON fs.FYP_Session_ID = s.FYP_Session_ID
                        WHERE s.Department_ID = ?";
        $sessionStmt = $conn->prepare($sessionQuery);
        if (!$sessionStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $sessionStmt->bind_param('i', $departmentId);
        $sessionStmt->execute();
        $sessionResult = $sessionStmt->get_result();
        while ($row = $sessionResult->fetch_assoc()) {
            $fypSessionIds[] = $row['FYP_Session_ID'];
        }
        $sessionStmt->close();
    }

    $studentsData = [];
    if (!empty($fypSessionIds)) {
        $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
        
        // Query to get students with their FYP title submission status and related info
        $studentsQuery = "SELECT 
                            s.Student_ID,
                            s.Student_Name,
                            s.FYP_Session_ID,
                            s.Address,
                            s.Phone_No,
                            s.Minor,
                            s.CGPA,
                            s.Semester AS Student_Semester,
                            fs.FYP_Session,
                            c.Course_Code,
                            d.Department_Name,
                            fp.Title_Status,
                            fp.Project_Title,
                            fp.Proposed_Title,
                            lec.Lecturer_Name AS Supervisor_Name
                          FROM student s
                          LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
                          LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
                          LEFT JOIN course c ON fs.Course_ID = c.Course_ID
                          LEFT JOIN department d ON s.Department_ID = d.Department_ID
                          LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
                          LEFT JOIN lecturer lec ON sup.Lecturer_ID = lec.Lecturer_ID
                          WHERE s.FYP_Session_ID IN ($placeholders)
                          AND s.Department_ID = ?
                          ORDER BY s.Student_Name";

        $stmt = $conn->prepare($studentsQuery);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $types = str_repeat('i', count($fypSessionIds)) . 'i';
        $params = array_merge($fypSessionIds, [$departmentId]);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Determine submission status based on Title_Status field
                $titleStatus = $row['Title_Status'];
                $submissionStatus = 'Not Submitted';
                
                if ($titleStatus === 'Approved') {
                    $submissionStatus = 'Approved';
                } elseif ($titleStatus === 'Rejected') {
                    $submissionStatus = 'Rejected';
                } elseif (!empty($titleStatus) && !empty($row['Proposed_Title'])) {
                    $submissionStatus = 'Waiting for Approval';
                }
                
                $studentsData[] = [
                    'id' => $row['Student_ID'],
                    'name' => $row['Student_Name'],
                    'fypSessionId' => $row['FYP_Session_ID'],
                    'status' => $submissionStatus,
                    'projectTitle' => $row['Project_Title'],
                    'proposedTitle' => $row['Proposed_Title'],
                    'titleStatus' => $titleStatus,
                    'courseCode' => $row['Course_Code'],
                    'semester' => $row['Student_Semester'],
                    'fypSession' => $row['FYP_Session'],
                    'address' => $row['Address'],
                    'phone' => $row['Phone_No'],
                    'programme' => $row['Department_Name'],
                    'minor' => $row['Minor'],
                    'cgpa' => $row['CGPA'],
                    'supervisorName' => $row['Supervisor_Name']
                ];
            }
        }
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'students' => $studentsData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
