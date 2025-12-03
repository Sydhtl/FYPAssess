<?php
include '../mysqlConnect.php';
session_start();

$upmId = $_SESSION['signup_upmId'] ?? '';
$password = $_SESSION['signup_password'] ?? '';
$studentName = $_SESSION['signup_fullName'] ?? '';

$programme = $_POST['programme'] ?? '';
$courseCode = $_POST['courseCode'] ?? '';
$semester = $_POST['semester'] ?? '';
$sessionName = $_POST['session'] ?? '';
$phone = $_POST['phone'] ?? '';
$race = $_POST['race'] ?? '';
$otherRace = $_POST['otherRace'] ?? '';
$address = $_POST['address'] ?? '';
$minor = $_POST['minor'] ?? '';
$cgpa = $_POST['cgpa'] ?? '';
$supervisorId = $_POST['supervisor'] ?? '';
$title1 = $_POST['title1'] ?? '';

$finalRace = ($race === 'Others' && !empty($otherRace)) ? $otherRace : $race;

$courseCodeInt = (int)$courseCode;
$semesterInt = (int)$semester;
$supervisorIdInt = (int)$supervisorId;
$programmeInt = (int)$programme;
$cgpaFloat = (float)$cgpa;

// Validate required fields
if (empty($upmId) || empty($password) || empty($studentName) || 
    empty($programme) || empty($courseCode) || empty($semester) || 
    empty($sessionName) || empty($phone) || empty($finalRace) || 
    empty($address) || empty($cgpa) || empty($supervisorIdInt) || empty($title1)) {
    
    $_SESSION['error'] = 'Please fill in all required fields.';
    header("Location: ../../html/login/information.php");
    exit();
}

// Check if user exists
$checkUser = $conn->prepare("SELECT UPM_ID FROM user WHERE UPM_ID = ?");
$checkUser->bind_param("s", $upmId);
$checkUser->execute();
$userExists = $checkUser->get_result();
$checkUser->close();

if ($userExists->num_rows > 0) {
    $_SESSION['error'] = 'This UPM ID already exists.';
    header("Location: ../../html/login/information.php");
    exit();
}

$conn->begin_transaction();

try {
    // Insert into user table
    $stmt1 = $conn->prepare("INSERT INTO user (UPM_ID, Password, Role) VALUES (?, ?, 'Student')");
    $stmt1->bind_param("ss", $upmId, $password);
    $stmt1->execute();
    $stmt1->close();

    // Check if FYP_Session already exists with the same Course_ID, Semester, and FYP_Session
    // This ensures we don't create duplicate sessions
    $stmt2 = $conn->prepare("SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND Semester = ? AND FYP_Session = ?");
    $stmt2->bind_param("iis", $courseCodeInt, $semesterInt, $sessionName);
    $stmt2->execute();
    $result = $stmt2->get_result();

    if ($result->num_rows > 0) {
        // FYP_Session already exists → use existing FYP_Session_ID
        $row = $result->fetch_assoc();
        $fypSessionId = $row['FYP_Session_ID'];
    } else {
        // FYP_Session does not exist → create new one
        $stmtInsert = $conn->prepare("INSERT INTO fyp_session (Course_ID, Semester, FYP_Session) VALUES (?, ?, ?)");
        $stmtInsert->bind_param("iis", $courseCodeInt, $semesterInt, $sessionName);
        $stmtInsert->execute();
        $fypSessionId = $stmtInsert->insert_id;
        $stmtInsert->close();
    }
    $stmt2->close();

    // Insert into student table
    $stmt3 = $conn->prepare("INSERT INTO student (Student_ID, Course_ID, Student_Name, Phone_No, Address, `Minor`, CGPA, Semester, FYP_Session_ID, Supervisor_ID, Race, Department_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $paramTypes = "s" . "i" . "s" . "s" . "s" . "s" . "d" . "i" . "i" . "i" . "s" . "i";
    $stmt3->bind_param($paramTypes, $upmId, $courseCodeInt, $studentName, $phone, $address, $minor, $cgpaFloat, $semesterInt, $fypSessionId, $supervisorIdInt, $finalRace, $programmeInt);
    $stmt3->execute();
    $stmt3->close();

    // Insert into fyp_project: store proposed title first with Waiting status
    $stmt4 = $conn->prepare("INSERT INTO fyp_project (Student_ID, Proposed_Title, Title_Status) VALUES (?, ?, 'Waiting For Approval')");
    $stmt4->bind_param("ss", $upmId, $title1);
    $stmt4->execute();
    $stmt4->close();

    $conn->commit();

    // Clear session data
    session_unset();
    header("Location: ../../html/login/Login.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../../html/login/information.php");
    exit();
}

$conn->close();
?>
