<?php
include '../mysqlConnect.php';
session_start();

// Get UPM_ID and password from session (set during signup)
$upmId = $_SESSION['signup_upmId'] ?? '';
$password = $_SESSION['signup_password'] ?? '';
$studentName = $_SESSION['signup_fullName'] ?? '';

// Get form data
$programme = $_POST['programme'] ?? ''; // Department_ID
$courseCode = $_POST['courseCode'] ?? ''; // Course_ID
$semester = $_POST['semester'] ?? '';
$session = $_POST['session'] ?? '';
$phone = $_POST['phone'] ?? '';
$race = $_POST['race'] ?? '';
$otherRace = $_POST['otherRace'] ?? '';
$address = $_POST['address'] ?? '';
$minor = $_POST['minor'] ?? '';
$cgpa = $_POST['cgpa'] ?? '';
$lecturerId = $_POST['supervisor'] ?? ''; // This is actually Lecturer_ID from the form
$title1 = $_POST['title1'] ?? '';

// Handle race field - if "Others" is selected, use otherRace value
$finalRace = ($race === 'Others' && !empty($otherRace)) ? $otherRace : $race;

// Convert to proper types for database
$courseCodeInt = (int)$courseCode;
$semesterInt = (int)$semester;
$lecturerIdInt = (int)$lecturerId;
$programmeInt = (int)$programme;
$cgpaFloat = (float)$cgpa;

// Validate required fields
if (empty($upmId) || empty($password) || empty($studentName) || 
    empty($programme) || empty($courseCode) || empty($semester) || 
    empty($session) || empty($phone) || empty($finalRace) || 
    empty($address) || empty($cgpa) || empty($lecturerId) || empty($title1)) {
    
    $_SESSION['error'] = 'Please fill in all required fields.';
    header("Location: ../../html/login/information.php");
    exit();
}

// Check if user already exists (safety check)
$checkUser = $conn->prepare("SELECT UPM_ID FROM user WHERE UPM_ID = ?");
$checkUser->bind_param("s", $upmId);
$checkUser->execute();
$userExists = $checkUser->get_result();
$checkUser->close();

if ($userExists->num_rows > 0) {
    $_SESSION['error'] = 'This UPM ID already exists in the system.';
    header("Location: ../../html/login/information.php");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Insert into user table (UPM_ID, Password, Role)
    $stmt1 = $conn->prepare("INSERT INTO user (UPM_ID, Password, Role) VALUES (?, ?, 'Student')");
    if ($stmt1 === false) {
        throw new Exception("Prepare failed for user table: " . $conn->error);
    }
    $stmt1->bind_param("ss", $upmId, $password);
    
    if (!$stmt1->execute()) {
        throw new Exception("Failed to insert into user table. Error: " . $stmt1->error);
    }
    
    if ($stmt1->affected_rows <= 0) {
        throw new Exception("Failed to insert into user table. No rows affected. Error: " . $stmt1->error);
    }
    $stmt1->close();
    
    // 2. Look up FYP_Session_ID from fyp_session table based on session, semester, and course
    // Common column names: Session_Year, Academic_Session, Year, or Session
    // Try Session_Year first (most common naming convention)
    $stmt2 = $conn->prepare("SELECT FYP_Session_ID FROM fyp_session WHERE FYP_Session = ? AND Semester = ? AND Course_ID = ?");
    if ($stmt2 === false) {
        // If Session_Year doesn't work, the error message will show the actual column name needed
        throw new Exception("Prepare failed for fyp_session lookup. The column name for 'session' might be different. Error: " . $conn->error . ". Please check if the column is named Session_Year, Academic_Session, Year, or Session.");
    }
    $stmt2->bind_param("sii", $session, $semesterInt, $courseCodeInt);
    $stmt2->execute();
    $result = $stmt2->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception("FYP Session not found for the given session, semester, and course combination.");
    }
    
    $row = $result->fetch_assoc();
    $fypSessionId = $row['FYP_Session_ID'];
    $stmt2->close();
    
    // 2.5. Look up Supervisor_ID from supervisor table based on Lecturer_ID
    // The form sends Lecturer_ID, but student table needs Supervisor_ID
    $stmt2_5 = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
    if ($stmt2_5 === false) {
        throw new Exception("Prepare failed for supervisor lookup: " . $conn->error);
    }
    $stmt2_5->bind_param("i", $lecturerIdInt);
    $stmt2_5->execute();
    $supervisorResult = $stmt2_5->get_result();
    
    if ($supervisorResult->num_rows == 0) {
        throw new Exception("Supervisor not found for the selected lecturer. Please ensure the lecturer is registered as a supervisor.");
    }
    
    $supervisorRow = $supervisorResult->fetch_assoc();
    $supervisorId = $supervisorRow['Supervisor_ID'];
    $stmt2_5->close();
    
    // 3. Insert into student table
    // Student_ID=Varchar, Course_ID=int, Student_Name=Varchar, Phone_No=Varchar, Address=Varchar, Minor=Varchar, CGPA=decimal(4,3), Semester=int, FYP_Session_ID=int, Supervisor_ID=int, Race=Varchar, Department_ID=int
    $stmt3 = $conn->prepare("INSERT INTO student (Student_ID, Course_ID, Student_Name, Phone_No, Address, `Minor`, CGPA, Semester, FYP_Session_ID, Supervisor_ID, Race, Department_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt3 === false) {
        throw new Exception("Prepare failed for student table: " . $conn->error);
    }
    // bind_param types: Student_ID(s), Course_ID(i), Student_Name(s), Phone_No(s), Address(s), Minor(s), CGPA(d), Semester(i), FYP_Session_ID(i), Supervisor_ID(i), Race(s), Department_ID(i)
    // Pattern: s-i-s-s-s-s-d-i-i-i-s-i (12 parameters)
    $paramTypes = "s" . "i" . "s" . "s" . "s" . "s" . "d" . "i" . "i" . "i" . "s" . "i";
    $stmt3->bind_param($paramTypes, $upmId, $courseCodeInt, $studentName, $phone, $address, $minor, $cgpaFloat, $semesterInt, $fypSessionId, $supervisorId, $finalRace, $programmeInt);
    
    if (!$stmt3->execute()) {
        throw new Exception("Failed to insert into student table. Error: " . $stmt3->error);
    }
    
    if ($stmt3->affected_rows <= 0) {
        throw new Exception("Failed to insert into student table. No rows affected. Error: " . $stmt3->error);
    }
    $stmt3->close();
    
    // 4. Insert into fyp_project table (Student_ID, Project_Title)
    $stmt4 = $conn->prepare("INSERT INTO fyp_project (Student_ID, Project_Title) VALUES (?, ?)");
    if ($stmt4 === false) {
        throw new Exception("Prepare failed for fyp_project table: " . $conn->error);
    }
    $stmt4->bind_param("ss", $upmId, $title1);
    
    if (!$stmt4->execute()) {
        throw new Exception("Failed to insert into fyp_project table. Error: " . $stmt4->error);
    }
    
    if ($stmt4->affected_rows <= 0) {
        throw new Exception("Failed to insert into fyp_project table. No rows affected. Error: " . $stmt4->error);
    }
    $stmt4->close();
    
    // Commit transaction
    $conn->commit();
    
    // Clear session data
    unset($_SESSION['signup_upmId']);
    unset($_SESSION['signup_password']);
    unset($_SESSION['signup_fullName']);
    unset($_SESSION['temp_programme']);
    unset($_SESSION['temp_courseCode']);
    unset($_SESSION['temp_semester']);
    unset($_SESSION['temp_session']);
    unset($_SESSION['temp_phone']);
    unset($_SESSION['temp_race']);
    unset($_SESSION['temp_address']);
    unset($_SESSION['temp_minor']);
    unset($_SESSION['temp_cgpa']);
    unset($_SESSION['temp_supervisor']);
    unset($_SESSION['temp_title1']);
    unset($_SESSION['temp_title2']);
    unset($_SESSION['temp_title3']);
    
    // Redirect to success page or login
    header("Location: ../../html/login/Login.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Store error in session and redirect back
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['debug_info'] = [
        'upmId' => $upmId,
        'programme' => $programme,
        'courseCode' => $courseCode,
        'semester' => $semester,
        'session' => $session,
        'error' => $e->getMessage()
    ];
    header("Location: ../../html/login/information.php");
    exit();
}

$conn->close();
?>

