<?php
include __DIR__ . '/mysqlConnect.php';
session_start();

// Prevent caching to avoid back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    header("Location: ../../login/Login.php");
    exit();
}

$userId = $_SESSION['upmId'];
$coordinatorName = 'Coordinator';

// Fetch coordinator info including department
$coordinatorDepartmentId = null;
$departmentName = '';
if ($stmt = $conn->prepare("SELECT l.Lecturer_Name, l.Department_ID, d.Department_Name 
                            FROM lecturer l 
                            LEFT JOIN department d ON l.Department_ID = d.Department_ID 
                            WHERE l.Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['Lecturer_Name'])) {
                $coordinatorName = $row['Lecturer_Name'];
            }
            $coordinatorDepartmentId = $row['Department_ID'];
            $departmentName = $row['Department_Name'];
        }
    }
    $stmt->close();
}

// Fetch course codes for the coordinator's department
$departmentCourseCodes = [];
if ($coordinatorDepartmentId) {
    $courseQuery = "SELECT DISTINCT Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code";
    if ($stmt = $conn->prepare($courseQuery)) {
        $stmt->bind_param("i", $coordinatorDepartmentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $departmentCourseCodes[] = $row['Course_Code'];
            }
        }
        $stmt->close();
    }
}

// Set display course code (first course or default)
$displayCourseCode = !empty($departmentCourseCodes) ? $departmentCourseCodes[0] : 'N/A';

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

// Fetch students for the selected sessions and same department as coordinator with submission status
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
                        lec.Lecturer_Name AS Supervisor_Name,
                        GROUP_CONCAT(DISTINCT lec_assessor.Lecturer_Name SEPARATOR ', ') AS Assessor_Names
                      FROM student s
                      LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
                      LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
                      LEFT JOIN course c ON fs.Course_ID = c.Course_ID
                      LEFT JOIN department d ON s.Department_ID = d.Department_ID
                      LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
                      LEFT JOIN lecturer lec ON sup.Lecturer_ID = lec.Lecturer_ID
                      LEFT JOIN student_enrollment se ON s.Student_ID = se.Student_ID AND s.FYP_Session_ID = se.FYP_Session_ID
                      LEFT JOIN assessor a ON (se.Assessor_ID_1 = a.Assessor_ID OR se.Assessor_ID_2 = a.Assessor_ID)
                      LEFT JOIN lecturer lec_assessor ON a.Lecturer_ID = lec_assessor.Lecturer_ID
                      WHERE s.FYP_Session_ID IN ($placeholders)
                      AND s.Department_ID = (
                          SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                      )
                      GROUP BY s.Student_ID, s.Student_Name, s.FYP_Session_ID, s.Address, s.Phone_No, s.Minor, s.CGPA, 
                               s.Semester, fs.FYP_Session, c.Course_Code, d.Department_Name, fp.Title_Status, 
                               fp.Project_Title, fp.Proposed_Title, lec.Lecturer_Name
                      ORDER BY s.Student_Name";

    if ($stmt = $conn->prepare($studentsQuery)) {
        $types = str_repeat('i', count($fypSessionIds)) . 's';
        $params = array_merge($fypSessionIds, [$userId]);
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
                    'supervisorName' => $row['Supervisor_Name'],
                    'assessorNames' => $row['Assessor_Names']
                ];
            }
        }
        $stmt->close();
    }
}

$studentsDataJson = json_encode($studentsData);
