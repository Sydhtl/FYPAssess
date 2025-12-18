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
$supervisorId = $_POST['supervisor'] ?? '';
$supervisorLocked = $_POST['supervisor_locked'] ?? '';
$assessor1 = $_POST['assessor1'] ?? '';
$assessor2 = $_POST['assessor2'] ?? '';
$title1 = $_POST['title1'] ?? '';

$finalRace = ($race === 'Others' && !empty($otherRace)) ? $otherRace : $race;

$courseCodeInt = (int)$courseCode;
$semesterInt = (int)$semester;
$supervisorIdInt = (int)$supervisorId;
$supervisorLockedInt = (int)$supervisorLocked;
$programmeInt = (int)$programme;
$cgpaFloat = (float)$cgpa;
$assessor1Int = $assessor1 !== '' ? (int)$assessor1 : 0;
$assessor2Int = $assessor2 !== '' ? (int)$assessor2 : 0;

// Validate required fields
if (empty($upmId) || empty($password) || empty($studentName) || 
    empty($programme) || empty($courseCode) || empty($semester) || 
    empty($sessionName) || empty($phone) || empty($finalRace) || 
    empty($address) || empty($cgpa) || empty($title1)) {
    
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
        echo "<script>alert('Student cannot register for the same course in the same session.'); window.location.href='../../html/login/Login.php';</script>";
        exit();
    }

    $finalSupervisorId = $supervisorLockedInt ?: $supervisorIdInt;
    // Always insert a new row into student table for this session
    $stmt3 = $conn->prepare("INSERT INTO student (Student_ID, Course_ID, Student_Name, Phone_No, Address, `Minor`, CGPA, Semester, FYP_Session_ID, Supervisor_ID, Race, Department_ID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $paramTypes = "s" . "i" . "s" . "s" . "s" . "s" . "d" . "i" . "i" . "i" . "s" . "i";
    $stmt3->bind_param($paramTypes, $upmId, $courseCodeInt, $studentName, $phone, $address, $minor, $cgpaFloat, $semesterInt, $fypSessionId, $finalSupervisorId, $finalRace, $programmeInt);
    $stmt3->execute();
    $stmt3->close();

    // Insert into student_enrollment for this session
    $stmtEnroll = $conn->prepare("INSERT INTO student_enrollment (Fyp_Session_ID, Student_ID, Supervisor_ID, Assessor_ID_1, Assessor_ID_2) VALUES (?, ?, ?, ?, ?)");
    $stmtEnroll->bind_param("isiii", $fypSessionId, $upmId, $finalSupervisorId, $assessor1Int, $assessor2Int);
    $stmtEnroll->execute();
    $stmtEnroll->close();

    // For existing students, reuse existing project and do NOT create a new entry
    // For new students (or if no project exists), create the initial project entry
    $projectExistsStmt = $conn->prepare("SELECT 1 FROM fyp_project WHERE Student_ID = ? LIMIT 1");
    $projectExistsStmt->bind_param("s", $upmId);
    $projectExistsStmt->execute();
    $projectExistsRes = $projectExistsStmt->get_result();
    $projectExistsStmt->close();

    if ($projectExistsRes->num_rows === 0) {
        $stmt4 = $conn->prepare("INSERT INTO fyp_project (Student_ID, Proposed_Title, Project_Title, Title_Status) VALUES (?, ?, ?, 'Waiting For Approval')");
        $stmt4->bind_param("sss", $upmId, $title1, $title1);
        $stmt4->execute();
        $stmt4->close();
    }

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
