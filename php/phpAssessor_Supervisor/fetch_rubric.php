<?php
// fetchRubric2.php

// 1. START SESSION
session_start();

include '../db_connect.php';
header('Content-Type: application/json');

// =======================================================================
// A. SECURITY CHECK (Authentication)
// =======================================================================
// We verify the user is logged in, but we DON'T check their specific role here
// because a Lecturer can be both.
if (!isset($_SESSION['upmId'])) {
    // For testing locally without login, you can comment these 3 lines out.
    // But for production, keep them!
    //http_response_code(401); 
    //echo json_encode(["error" => "You are not logged in."]);
    //exit;
}

// =======================================================================
// B. DETERMINE CURRENT MODE (Context)
// =======================================================================
// We get the role from the URL parameter sent by the Frontend
// Example: fetchRubric2.php?role=assessor

if (isset($_GET['role']) && $_GET['role'] === 'assessor') {
    $currentRole = 'assessor';
} else {
    // Default to supervisor if missing or invalid
    $currentRole = 'supervisor';
}

// =======================================================================
// 2. DEFINE ACCESS PERMISSIONS
// =======================================================================

$allowedIDs = [];

if ($currentRole === 'supervisor') {
    $allowedIDs = [1, 4, 5];
} elseif ($currentRole === 'assessor') {
    $allowedIDs = [2, 3];
}

if (empty($allowedIDs)) {
    echo json_encode(["error" => "No assessments assigned to this role."]);
    exit;
}

$idString = implode(',', $allowedIDs);

// =======================================================================
// 3. FETCH DATA
// =======================================================================

$response = [];
$_meta = [];

// Fetch Assessments (Filtered by Role)
// =======================================================================
// NOTE: Due Date Check - Modified to show all assessments with availability status
// =======================================================================
// When ready to enable, uncomment this query to include due date info:
/*
$sqlAssessments = "SELECT a.Assessment_ID, a.Assessment_Name, 
                          dd.Start_Date, dd.End_Date, dd.Start_Time, dd.End_Time,
                          CASE 
                            WHEN CONCAT(dd.Start_Date, ' ', dd.Start_Time) <= NOW()
                             AND CONCAT(dd.End_Date, ' ', dd.End_Time) >= NOW()
                            THEN 1
                            ELSE 0
                          END as Is_Available
                   FROM assessment a
                   LEFT JOIN due_date dd ON a.Due_ID = dd.Due_ID
                   WHERE a.Assessment_ID IN ($idString) 
                   AND dd.Role = '$currentRole'";
*/

// Temporary query without due date check:
$sqlAssessments = "SELECT Assessment_ID, Assessment_Name FROM assessment WHERE Assessment_ID IN ($idString)";

$resultAssessments = $conn->query($sqlAssessments);

if ($resultAssessments) {
    while ($row = $resultAssessments->fetch_assoc()) {
        $assessmentId = $row['Assessment_ID'];
        $assessmentName = $row['Assessment_Name'];

        $slug = strtolower(str_replace(' ', '-', $assessmentName));

        // =======================================================================
        // NOTE: Due Date Metadata (Currently Commented Out - No due_date data exists yet)
        // =======================================================================
        // When ready to enable, uncomment to pass due date info to frontend:
        /*
        $dueInfo = [
            'start_date' => $row['Start_Date'] ?? null,
            'end_date' => $row['End_Date'] ?? null,
            'start_time' => $row['Start_Time'] ?? null,
            'end_time' => $row['End_Time'] ?? null,
            'is_available' => $row['Is_Available'] == 1
        ];
        */

        // ---------------------------------------------------------
        // FETCH CRITERIA
        // ---------------------------------------------------------
        $criteriaData = [];
        $sqlCriteria = "SELECT Criteria_ID, Criteria_Name, Criteria_Description, Criteria_Fullmarks, Formula_Type 
                        FROM assessment_criteria WHERE Assessment_ID = $assessmentId";
        $resCriteria = $conn->query($sqlCriteria);

        if ($resCriteria) {
            while ($crit = $resCriteria->fetch_assoc()) {
                $critId = $crit['Criteria_ID'];

                // A. Fetch Learning Objectives (Specific to Criteria)
                $specificLoList = [];
                $sqlSpecificLO = "SELECT DISTINCT LearningObjective_Code FROM learning_objective_allocation 
                                  WHERE Criteria_ID = $critId";
                $resSpecificLO = $conn->query($sqlSpecificLO);
                if ($resSpecificLO) {
                    while ($loRow = $resSpecificLO->fetch_assoc()) {
                        $specificLoList[] = $loRow['LearningObjective_Code'];
                    }
                }
                $specificLoString = implode(", ", $specificLoList);

                // B. Fetch Criteria Description (Explode into bullets)
                $pointsArray = ["Assess based on rubric requirements."];
                if (!empty($crit['Criteria_Description'])) {
                    $pointsArray = explode('|', $crit['Criteria_Description']);
                }

                // ---------------------------------------------------------
                // FETCH SUBCRITERIA & SCALES
                // ---------------------------------------------------------
                $subCriteriaData = [];
                $marksData = []; // Will hold scales if no subcriteria exist

                // Check for Subcriteria first
                $sqlSub = "SELECT Subcriteria_ID, SubCriteria_Name, Subcriteria_Description, Subscriteria_Fullmarks 
                           FROM assessment_subcriteria WHERE Criteria_ID = $critId";
                $resSub = $conn->query($sqlSub);

                // 1. Prepare "Shared" Scales (Fallback logic often used if subcriteria share scales)
                $sharedScales = [];
                // We peek at Subcriteria #1 to get a master scale if needed, or you can query by Criteria_ID only
                // This logic mirrors your original flow:
                if ($resSub && $resSub->num_rows > 0) {
                    $sqlMasterScale = "SELECT Scale_Description FROM mark_scale 
                                        WHERE Criteria_ID = $critId AND Subcriteria_ID = '1' 
                                        ORDER BY Scale_Value ASC";
                    $resMaster = $conn->query($sqlMasterScale);
                    if ($resMaster) {
                        while ($m = $resMaster->fetch_assoc()) {
                            $sharedScales[] = $m['Scale_Description'];
                        }
                    }
                    // Reset pointer so we can loop through subcriteria again
                    $resSub->data_seek(0);
                }

                // 2. Loop Subcriteria (If they exist)
                if ($resSub && $resSub->num_rows > 0) {
                    while ($sub = $resSub->fetch_assoc()) {
                        $subId = $sub['Subcriteria_ID'];

                        // Fetch Specific Scales for this Subcriteria
                        $specificScales = [];
                        $sqlSpecific = "SELECT Scale_Description FROM mark_scale 
                                        WHERE Criteria_ID = $critId AND Subcriteria_ID = '$subId' 
                                        ORDER BY Scale_Value ASC";
                        $resSpecific = $conn->query($sqlSpecific);
                        if ($resSpecific) {
                            while ($s = $resSpecific->fetch_assoc()) {
                                $specificScales[] = $s['Scale_Description'];
                            }
                        }

                        // Use specific if found, else shared
                        $finalScales = !empty($specificScales) ? $specificScales : $sharedScales;

                        // Subcriteria Description
                        $subPoints = [];
                        if (!empty($sub['Subcriteria_Description'])) {
                            $subPoints = explode('|', $sub['Subcriteria_Description']);
                        }

                        $subCriteriaData[] = [
                            "id" => $subId,
                            "name" => $sub['SubCriteria_Name'],
                            "max_marks" => $sub['Subscriteria_Fullmarks'],
                            "description" => $subPoints,
                            "marks_options" => $finalScales
                        ];
                    }
                } else {
                    // 3. No Subcriteria? Fetch Scales for the Main Criteria
                    $sqlScales = "SELECT Scale_Description FROM mark_scale 
                                  WHERE Criteria_ID = $critId 
                                  ORDER BY Scale_Value ASC";
                    $resScales = $conn->query($sqlScales);
                    if ($resScales) {
                        while ($scale = $resScales->fetch_assoc()) {
                            $marksData[] = $scale['Scale_Description'];
                        }
                    }
                }

                // ---------------------------------------------------------
                // BUILD FINAL ARRAY
                // ---------------------------------------------------------
                $criteriaData[] = [
                    "id" => $crit['Criteria_ID'],
                    "title" => $crit['Criteria_Name'],
                    "max_marks" => $crit['Criteria_Fullmarks'],
                    "formula" => $crit['Formula_Type'],
                    "outcomes" => $specificLoString,
                    "marks" => $marksData,          // Populated if no subcriteria
                    "criteria_points" => $pointsArray,
                    "sub_criteria" => $subCriteriaData // Populated if subcriteria exist
                ];
            }
        }
        $response[$slug] = $criteriaData;
        $_meta[$slug] = ['id' => $assessmentId, 'name' => $assessmentName];

        // =======================================================================
        // NOTE: Uncomment when due_date is ready:
        // $_meta[$slug]['due_date_info'] = $dueInfo;
        // =======================================================================
    }
}

$finalJson = $response;
$finalJson['_meta'] = $_meta;

echo json_encode($finalJson);
$conn->close();
?>