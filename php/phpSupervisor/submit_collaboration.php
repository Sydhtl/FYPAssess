<?php
// submit_collaboration.php
error_reporting(0); // Suppress warnings that break JSON
ini_set('display_errors', 0);
ob_start();
session_start();

// Clear any previous output
ob_clean();

header('Content-Type: application/json');
include '../db_connect.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data)
        throw new Exception("No data received");

    // 2. Identify Supervisor
    $loginID = isset($_SESSION['upmId']) ? $_SESSION['upmId'] : 'hazura';
    $supervisorID = null;
    $stmtLookup = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
    $stmtLookup->bind_param("s", $loginID);
    $stmtLookup->execute();
    $res = $stmtLookup->get_result();
    if ($row = $res->fetch_assoc()) {
        $supervisorID = $row['Supervisor_ID'];
    } else {
        throw new Exception("Supervisor not found.");
    }

    // 3. Prepare Data Variables
    $sessionID = (!empty($data['session_id'])) ? $data['session_id'] : null;
    $status = $data['collaboration_status'] ?? 'No';

    // -- Company Details --
    $compName = $data['company_name'] ?? '';
    $compEmail = $data['company_email'] ?? '';
    $compAddress = $data['company_address'] ?? ''; // Street address
    $compPostcode = $data['company_postcode'] ?? '';
    $compCity = $data['company_city'] ?? '';
    $compState = $data['company_state'] ?? '';

    // -- Quota & Qual --
    $quota = isset($data['student_quota']) ? intval($data['student_quota']) : 0;
    // Note: Using your specific column spelling 'Acadmeic_Qualification'
    $academicQual = $data['academic_qualification'] ?? '';

    // -- Lists (converted to comma-separated format) --

    // 1. Supervisor Topics -> 'Supervisor_Title'
    $supervisorTopics = isset($data['topic']) ? implode(',', array_values(array_filter($data['topic']))) : '';

    // 2. Industry Topics -> 'Company_Title'
    $industryTopics = isset($data['ind_topic']) ? implode(',', array_values(array_filter($data['ind_topic']))) : '';

    // 3. Required Skills -> 'Required_Skills'
    $requiredSkills = isset($data['required_skill']) ? implode(',', array_values(array_filter($data['required_skill']))) : '';

    // 4. Industry Supervisors -> Store as comma-separated values in each column
    $supervisorNames = [];
    $supervisorEmails = [];
    $supervisorPhones = [];
    $supervisorRoles = [];

    // Frontend sends as 'supervisor_name', 'supervisor_email', etc.
    if (!empty($data['supervisor_name'])) {
        foreach ($data['supervisor_name'] as $i => $name) {
            if (!empty($name)) {
                $supervisorNames[] = $name;
                $supervisorEmails[] = $data['supervisor_email'][$i] ?? '';
                $supervisorPhones[] = $data['supervisor_phone'][$i] ?? '';
                $supervisorRoles[] = $data['supervisor_role'][$i] ?? '';
            }
        }
    }

    // Join arrays with commas for storage (no spaces)
    $allSupervisorNames = implode(',', $supervisorNames);
    $allSupervisorEmails = implode(',', $supervisorEmails);
    $allSupervisorPhones = implode(',', $supervisorPhones);
    $allSupervisorRoles = implode(',', $supervisorRoles);

    // 4. Check for Existing Record
    $checkSQL = "SELECT Collaboration_ID FROM collaboration WHERE FYP_Session_ID = ? AND Supervisor_ID = ?";
    $stmtCheck = $conn->prepare($checkSQL);
    $stmtCheck->bind_param("ii", $sessionID, $supervisorID);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($rowCheck = $resultCheck->fetch_assoc()) {
        // Update
        $collabID = $rowCheck['Collaboration_ID'];

        $sql = "UPDATE collaboration SET 
                Collaboration_Status = ?, 
                Company_Name = ?, 
                Company_Address = ?, 
                Postcode = ?, 
                City = ?, 
                State = ?, 
                Company_Email = ?, 
                Student_Quota = ?, 
                Academic_Qualification = ?, 
                Supervisor_Title = ?, 
                Company_Title = ?, 
                Required_Skills = ?, 
                Company_Supervisor_Name = ?,
                Company_Supervisor_Email = ?,
                Company_Supervisor_Phone = ?,
                Company_Supervisor_Role = ?
                WHERE Collaboration_ID = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssssssisssssssi",
            $status,
            $compName,
            $compAddress,
            $compPostcode,
            $compCity,
            $compState,
            $compEmail,
            $quota,
            $academicQual,
            $supervisorTopics,
            $industryTopics,
            $requiredSkills,
            $allSupervisorNames,
            $allSupervisorEmails,
            $allSupervisorPhones,
            $allSupervisorRoles,
            $collabID
        );

        // Execute the UPDATE statement
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }

    } else {
        // Insert
        $sql = "INSERT INTO collaboration (
                Collaboration_Status, Company_Name, Company_Address, Postcode, City, State,
                Company_Email, Student_Quota, Academic_Qualification, Supervisor_Title, Company_Title, 
                Required_Skills, Company_Supervisor_Name, Company_Supervisor_Email, Company_Supervisor_Phone, Company_Supervisor_Role,
                Supervisor_ID, FYP_Session_ID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Types: s=string, i=integer
        // s(Status) s(Name) s(Addr) s(Post) s(City) s(State) s(Email) i(Quota) s(Qual) 
        // s(SvTitle) s(CmpTitle) s(Skills) s(SvNames) s(SvEmails) s(SvPhones) s(SvRoles) i(SupervisorID) i(SessionID)
        $stmt->bind_param(
            "sssssssissssssssii",
            $status,
            $compName,
            $compAddress,
            $compPostcode,
            $compCity,
            $compState,
            $compEmail,
            $quota,
            $academicQual,
            $supervisorTopics,
            $industryTopics,
            $requiredSkills,
            $allSupervisorNames,
            $allSupervisorEmails,
            $allSupervisorPhones,
            $allSupervisorRoles,
            $supervisorID,
            $sessionID
        );

        // Execute INSERT and get the newly inserted ID
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $collabID = $conn->insert_id;
    }

    // Return success with collaboration ID
    ob_clean(); // Clear any accumulated output
    echo json_encode([
        'status' => 'success',
        'message' => 'Data saved successfully',
        'collaboration_id' => $collabID
    ]);
    ob_end_flush();

} catch (Exception $e) {
    ob_clean(); // Clear any accumulated output
    http_response_code(500);
    // Log the error for debugging
    error_log("Collaboration submission error: " . $e->getMessage());
    error_log("Session data: " . print_r($_SESSION, true));
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'session_id' => isset($data['session_id']) ? $data['session_id'] : 'not set',
            'login_id' => isset($loginID) ? $loginID : 'not set'
        ]
    ]);
    ob_end_flush();
}
?>