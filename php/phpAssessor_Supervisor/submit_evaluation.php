<?php

ob_start();
session_start(); 
header('Content-Type: application/json');
include '../db_connect.php';

try {
    // =======================================================================
    // 1. READ INPUT DATA
    // =======================================================================
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) throw new Exception("No data received");

    // =======================================================================
    // 2. DETERMINE IDENTITY (Who is logged in?)
    // =======================================================================
    // A. Get the String ID from Session (e.g., "hazura")
    if (isset($_SESSION['user_id'])) {
        $lecturerUPM = $_SESSION['user_id'];
    } else {
        // Fallback for testing if not logged in (REMOVE THIS IN PRODUCTION)
        $lecturerUPM = 'hazura'; 
    }

    // B. Get the Context Role from Frontend Payload
    $currentRole = isset($data['role']) ? $data['role'] : 'supervisor';

    // C. Lookup the specific Numeric ID for this role
    $currentUserID = null; // This will hold the Integer (e.g., 21)

    if ($currentRole === 'supervisor') {
        // Find the Supervisor_ID associated with this Lecturer
        $stmtLookup = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
        $stmtLookup->bind_param("s", $lecturerUPM);
        $stmtLookup->execute();
        $res = $stmtLookup->get_result();
        if ($row = $res->fetch_assoc()) {
            $currentUserID = $row['Supervisor_ID'];
        }
    } 
    elseif ($currentRole === 'assessor') {
        // Find the Assessor_ID associated with this Lecturer
        $stmtLookup = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
        $stmtLookup->bind_param("s", $lecturerUPM);
        $stmtLookup->execute();
        $res = $stmtLookup->get_result();
        if ($row = $res->fetch_assoc()) {
            $currentUserID = $row['Assessor_ID'];
        }
    }

    // Safety Check: If we couldn't find an ID, stop.
    if (!$currentUserID) {
        throw new Exception("Error: No $currentRole profile found for User: $lecturerUPM");
    }

    // =======================================================================
    // 3. SETUP VARIABLES & TRANSACTION
    // =======================================================================
    
    $studentId = $data['student_id'];
    $assessmentId = $data['assessment_id'];
    $marksList = $data['marks'] ?? [];
    $commentText = $data['comment'] ?? '';

    if (empty($marksList)) throw new Exception("No marks provided.");

    // Determine which column to use based on Role
    $assessorCol = ($currentRole === 'assessor') ? $currentUserID : null;
    $supervisorCol = ($currentRole === 'supervisor') ? $currentUserID : null;
    
    // Determine which column to use for Comment ownership
    $roleColumnForComment = ($currentRole === 'supervisor') ? 'Supervisor_ID' : 'Assessor_ID';

    $conn->begin_transaction();

    // ---------------------------------------------------
    // 4. SAVE COMMENT
    // ---------------------------------------------------
    $stmtChk = $conn->prepare("SELECT Comment_ID FROM comment WHERE Student_ID = ? AND Assessment_ID = ?");
    $stmtChk->bind_param("si", $studentId, $assessmentId);
    $stmtChk->execute();
    
    if ($stmtChk->get_result()->num_rows > 0) {
        // Update existing comment
        $sqlC = "UPDATE comment SET Given_Comment = ?, $roleColumnForComment = ? WHERE Student_ID = ? AND Assessment_ID = ?";
        $stmtC = $conn->prepare($sqlC);
        $stmtC->bind_param("siis", $commentText, $currentUserID, $studentId, $assessmentId);
    } else {
        // Insert new comment
        $sqlC = "INSERT INTO comment (Student_ID, Assessment_ID, Given_Comment, $roleColumnForComment) VALUES (?, ?, ?, ?)";
        $stmtC = $conn->prepare($sqlC);
        $stmtC->bind_param("sssi", $studentId, $assessmentId, $commentText, $currentUserID);
    }
    $stmtC->execute();

    // ---------------------------------------------------
    // 5. SAVE MARKS (Update or Insert Logic)
    // ---------------------------------------------------
    
    foreach ($marksList as $mark) {
        $c_id = intval($mark['criteria_id']);
        $raw_score = intval($mark['score']);
        $sub_id = (isset($mark['type']) && $mark['type'] === 'subcriteria') ? strval($mark['element_id']) : null;
        
        // --- CALCULATE WEIGHT ---
        $calculated_weight = 0;

        // ======================================================
        // CRITERIA 10: REPORT ASSESSMENT
        // ======================================================
        if ($c_id == 10) {
            
            // FORMULA A: Subcriteria 1, 2, 7
            // Logic: ((Sub1 + Sub2 + Sub7) / 15) * 5
            // Per Item: (Score / 15) * 5
            if (in_array($sub_id, ['1', '2', '7'])) {
                $calculated_weight = ($raw_score / 15) * 5;
            }
            
            // FORMULA B: Subcriteria 3, 4, 5, 6
            // Logic: ((Sub3 + Sub4 + Sub5 + Sub6) / 20) * 15
            // Per Item: (Score / 20) * 15
            elseif (in_array($sub_id, ['3', '4', '5', '6'])) {
                 $calculated_weight = ($raw_score / 20) * 15;
            }
            
            // FORMULA C: Subcriteria 8 (Thesis Output - inferred based on remainder)
            // Logic: Direct 5 marks
            elseif ($sub_id == '8') {
                $calculated_weight = $raw_score; 
            }
            
            // Fallback for any other ID in Criteria 10
            else {
                $calculated_weight = $raw_score; 
            }
        } 
        
        // ======================================================
        // CRITERIA 12: SENSE OF RESPONSIBILITY
        // ======================================================
        // Formula E: (Sub9 + Sub10) / 2
        // Per Item: Score / 2
        elseif ($c_id == 12) {
             $calculated_weight = $raw_score / 2;
        } 
        
        // ======================================================
        // CRITERIA 5, 6, 9: DOUBLE WEIGHT
        // ======================================================
        elseif (in_array($c_id, [5, 6, 9])) {
             $calculated_weight = $raw_score * 2; 
        }
        
        // ======================================================
        // STANDARD / DIRECT (Criteria 11, 13, 14, etc.)
        // ======================================================
        else {
             $calculated_weight = $raw_score; 
        }

        // Check if evaluation record already exists
        if ($currentRole === 'supervisor') {
            $stmtChkEval = $conn->prepare("SELECT Evaluation_ID FROM evaluation WHERE Student_ID = ? AND Assessment_ID = ? AND Criteria_ID = ? AND Supervisor_ID = ? AND (Subcriteria_ID = ? OR (Subcriteria_ID IS NULL AND ? IS NULL))");
            $stmtChkEval->bind_param("siiiss", $studentId, $assessmentId, $c_id, $currentUserID, $sub_id, $sub_id);
        } else {
            $stmtChkEval = $conn->prepare("SELECT Evaluation_ID FROM evaluation WHERE Student_ID = ? AND Assessment_ID = ? AND Criteria_ID = ? AND Assessor_ID = ? AND (Subcriteria_ID = ? OR (Subcriteria_ID IS NULL AND ? IS NULL))");
            $stmtChkEval->bind_param("siiiss", $studentId, $assessmentId, $c_id, $currentUserID, $sub_id, $sub_id);
        }
        $stmtChkEval->execute();
        $resultEval = $stmtChkEval->get_result();
        
        if ($resultEval->num_rows > 0) {
            // Update existing evaluation
            if ($currentRole === 'supervisor') {
                $stmtUpd = $conn->prepare("UPDATE evaluation SET Given_Marks = ?, Evaluation_Percentage = ? WHERE Student_ID = ? AND Assessment_ID = ? AND Criteria_ID = ? AND Supervisor_ID = ? AND (Subcriteria_ID = ? OR (Subcriteria_ID IS NULL AND ? IS NULL))");
                $stmtUpd->bind_param("idsiisss", $raw_score, $calculated_weight, $studentId, $assessmentId, $c_id, $currentUserID, $sub_id, $sub_id);
            } else {
                $stmtUpd = $conn->prepare("UPDATE evaluation SET Given_Marks = ?, Evaluation_Percentage = ? WHERE Student_ID = ? AND Assessment_ID = ? AND Criteria_ID = ? AND Assessor_ID = ? AND (Subcriteria_ID = ? OR (Subcriteria_ID IS NULL AND ? IS NULL))");
                $stmtUpd->bind_param("idsiisss", $raw_score, $calculated_weight, $studentId, $assessmentId, $c_id, $currentUserID, $sub_id, $sub_id);
            }
            $stmtUpd->execute();
        } else {
            // Insert new evaluation
            $stmtIns = $conn->prepare("INSERT INTO evaluation (Assessor_ID, Supervisor_ID, Student_ID, Assessment_ID, Criteria_ID, Subcriteria_ID, Given_Marks, Evaluation_Percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->bind_param("iisiisid", $assessorCol, $supervisorCol, $studentId, $assessmentId, $c_id, $sub_id, $raw_score, $calculated_weight);
            $stmtIns->execute();
        }
    }

    // ---------------------------------------------------
    // 6. UPDATE REPORT (Average Calculation)
    // ---------------------------------------------------
    $sqlCalc = "
        SELECT AVG(GraderTotal) as FinalScore 
        FROM (
            SELECT SUM(Evaluation_Percentage) as GraderTotal 
            FROM evaluation 
            WHERE Student_ID = ? AND Assessment_ID = ?
            GROUP BY IFNULL(Assessor_ID, Supervisor_ID)
        ) as SubQuery
    ";
    
    $stmtCalc = $conn->prepare($sqlCalc);
    $stmtCalc->bind_param("si", $studentId, $assessmentId);
    $stmtCalc->execute();
    $finalScore = round($stmtCalc->get_result()->fetch_assoc()['FinalScore'] ?? 0);

    // Save to Report Table
    $stmtRepChk = $conn->prepare("SELECT Report_ID FROM report WHERE Student_ID = ?");
    $stmtRepChk->bind_param("s", $studentId);
    $stmtRepChk->execute();
    
    if ($stmtRepChk->get_result()->num_rows > 0) {
        $stmtRep = $conn->prepare("UPDATE report SET Student_Marks = ? WHERE Student_ID = ?");
        $stmtRep->bind_param("is", $finalScore, $studentId);
    } else {
        $stmtRep = $conn->prepare("INSERT INTO report (Student_ID, Student_Marks) VALUES (?, ?)");
        $stmtRep->bind_param("si", $studentId, $finalScore);
    }
    $stmtRep->execute();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "Saved successfully as $currentRole", 'total_marks' => $finalScore]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>