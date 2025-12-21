<?php
include '../mysqlConnect.php';
session_start();

$upmId = $_SESSION['signup_upmId'] ?? '';
$password = $_SESSION['signup_password'] ?? '';
$studentName = $_SESSION['signup_fullName'] ?? '';
$existingStudent = isset($_POST['existingStudent']) && $_POST['existingStudent'] === '1';

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


$finalRace = ($race === 'Others' && !empty($otherRace)) ? $otherRace : $race;

$courseCodeInt = (int)$courseCode;
$semesterInt = (int)$semester;
$programmeInt = (int)$programme;
$cgpaFloat = (float)$cgpa;

// Validate required fields
if (empty($upmId) || empty($password) || empty($studentName) || 
    empty($programme) || empty($courseCode) || empty($semester) || 
    empty($sessionName) || empty($phone) || empty($finalRace) || 
    empty($address) || empty($cgpa)) {
    
    $_SESSION['error'] = 'Please fill in all required fields.';
    header("Location: ../../html/login/information.php");
    exit();
}

// Check if user exists
$checkUser = $conn->prepare("SELECT UPM_ID, Password, Role FROM user WHERE UPM_ID = ?");
$checkUser->bind_param("s", $upmId);
$checkUser->execute();
$userExists = $checkUser->get_result();
$existingUserRow = ($userExists && $userExists->num_rows > 0) ? $userExists->fetch_assoc() : null;
$checkUser->close();

$conn->begin_transaction();

try {
    // Insert into user table if not exists, else update if data changed
    if ($existingUserRow === null) {
        $stmt1 = $conn->prepare("INSERT INTO user (UPM_ID, Password, Role) VALUES (?, ?, 'Student')");
        $stmt1->bind_param("ss", $upmId, $password);
        $stmt1->execute();
        $stmt1->close();
    } else {
        $currentPassword = $existingUserRow['Password'] ?? '';
        $currentRole = strtolower($existingUserRow['Role'] ?? '');
        if ($currentPassword !== $password || $currentRole !== 'student') {
            $stmtUpdate = $conn->prepare("UPDATE user SET Password = ?, Role = 'Student' WHERE UPM_ID = ?");
            $stmtUpdate->bind_param("ss", $password, $upmId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    }

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

    // Prevent duplicate enrollment in same course/semester/session for same student
    $dupCheck = $conn->prepare("SELECT 1 FROM student s JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID WHERE s.Student_ID = ? AND fs.Course_ID = ? AND fs.Semester = ? AND fs.FYP_Session = ? LIMIT 1");
    $dupCheck->bind_param("siis", $upmId, $courseCodeInt, $semesterInt, $sessionName);
    $dupCheck->execute();
    $dupResult = $dupCheck->get_result();
    $dupCheck->close();
    if ($dupResult->num_rows > 0) {
        $conn->rollback();
        $_SESSION['error'] = 'Student cannot register for the same course in the same session.';
        header("Location: ../../html/login/information.php");
        exit();
    }

    // Always insert a new row into student table for this session (without supervisor)
    $stmt3 = $conn->prepare("INSERT INTO student (Student_ID, Course_ID, Student_Name, Phone_No, Address, `Minor`, CGPA, Semester, FYP_Session_ID, Race, Department_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $paramTypes = "s" . "i" . "s" . "s" . "s" . "s" . "d" . "i" . "i" . "s" . "i";
    $stmt3->bind_param($paramTypes, $upmId, $courseCodeInt, $studentName, $phone, $address, $minor, $cgpaFloat, $semesterInt, $fypSessionId, $finalRace, $programmeInt);
    $stmt3->execute();
    $stmt3->close();

    // Skip student_enrollment and fyp_project inserts - will be handled by coordinator

    $conn->commit();

    // Set success flag and clear session data
    $_SESSION['registration_success'] = true;
    session_unset();
    $_SESSION['registration_success'] = true;
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
