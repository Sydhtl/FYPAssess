<?php
include '../mysqlConnect.php';
session_start();

// Ensure only Coordinators can fetch assessment sessions
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get filter values from query parameters
$year = $_GET['year'] ?? '';
$semester = $_GET['semester'] ?? '';

if (empty($year) || empty($semester)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Year and semester are required']);
    exit();
}

$userId = $_SESSION['upmId'];

try {
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
        $stmt->bind_param("ssi", $userId, $year, $semester);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $fypSessionIds[] = $row['FYP_Session_ID'];
            }
        }
        $stmt->close();
    }
    
    if (empty($fypSessionIds)) {
        echo json_encode([
            'success' => true,
            'sessions' => []
        ]);
        exit();
    }
    
    // Fetch assessment session data for students
    // Ensure joins stay within the same FYP session to avoid cross-session leakage
    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
    $sessionsQuery = "SELECT 
                        s.Student_ID,
                        s.Student_Name,
                        s.Course_ID,
                        c.Course_Code,
                        ass.Date,
                        ass.Time,
                        ass.Venue
                      FROM student s
                      INNER JOIN course c ON s.Course_ID = c.Course_ID
                      LEFT JOIN student_session ss 
                        ON s.Student_ID = ss.Student_ID 
                        AND ss.FYP_Session_ID = s.FYP_Session_ID
                      LEFT JOIN assessment_session ass 
                        ON ss.Session_ID = ass.Session_ID 
                        AND ass.FYP_Session_ID = ss.FYP_Session_ID
                      WHERE s.FYP_Session_ID IN ($placeholders)
                      AND s.Department_ID = (
                          SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                      )
                      ORDER BY s.Student_Name";
    
    $sessionsData = [];
    
    if ($stmt = $conn->prepare($sessionsQuery)) {
        $types = str_repeat('i', count($fypSessionIds)) . 's';
        $params = array_merge($fypSessionIds, [$userId]);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $sessionsData[] = [
                    'student_id' => $row['Student_ID'],
                    'student_name' => $row['Student_Name'],
                    'course_id' => $row['Course_ID'],
                    'course_code' => $row['Course_Code'],
                    'date' => $row['Date'] ?? null,
                    'time' => $row['Time'] ?? null,
                    'venue' => $row['Venue'] ?? null
                ];
            }
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessionsData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching assessment sessions: ' . $e->getMessage()
    ]);
    
    error_log('Error fetching assessment sessions: ' . $e->getMessage());
}
?>

