<?php
// submit_collaboration.php
ob_start();
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

try {
    // 1. Receive Input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data)
        throw new Exception("No data received");

    // 2. Identify Supervisor
    $loginID = $_SESSION['user_id'] ?? 'hazura';
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
        // --- UPDATE ---
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
        // Types: s=string, i=integer. 
        // s(Status) s(Name) s(Addr) s(Post) s(City) s(State) s(Email) s(Quota) s(Qual) 
        // s(SvTitle) s(CmpTitle) s(Skills) s(SvNames) s(SvEmails) s(SvPhones) s(SvRoles) i(ID)
        $stmt->bind_param(
            "ssssssssssssssssi",
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

    } else {
        // --- INSERT ---
        $sql = "INSERT INTO collaboration (
                Collaboration_Status, Company_Name, Company_Address, Postcode, City, State,
                Company_Email, Student_Quota, Academic_Qualification, Supervisor_Title, Company_Title, 
                Required_Skills, Company_Supervisor_Name, Company_Supervisor_Email, Company_Supervisor_Phone, Company_Supervisor_Role,
                Supervisor_ID, FYP_Session_ID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssssssii",
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
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
    } else {
        throw new Exception("Database Error: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>