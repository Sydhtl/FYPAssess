<?php
include '../mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

/**
 * Assign supervisors automatically to students without supervisors
 * Respects quota limits and skips manual assignments
 */
function assignSupervisorsAutomatically($conn, $fypSessionIds) {
    $assigned = 0;
    $errors = [];
    
    // Get lecturers with their available quota
    $lecturerQuery = "SELECT l.Lecturer_ID, l.Lecturer_Name, lq.Quota, 
                      COALESCE((SELECT COUNT(*) FROM student s 
                               WHERE s.Supervisor_ID = l.Lecturer_ID 
                               AND s.FYP_Session_ID IN (" . implode(',', array_fill(0, count($fypSessionIds), '?')) . ")), 0) as assigned_count
                      FROM lecturer l
                      LEFT JOIN lecturer_quota lq ON l.Lecturer_ID = lq.Lecturer_ID
                      WHERE lq.Quota > 0
                      ORDER BY assigned_count ASC, l.Lecturer_Name ASC";
    
    $stmt = $conn->prepare($lecturerQuery);
    $stmt->bind_param(str_repeat('i', count($fypSessionIds)), ...$fypSessionIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lecturers = [];
    while ($row = $result->fetch_assoc()) {
        $available = $row['Quota'] - $row['assigned_count'];
        if ($available > 0) {
            $lecturers[] = [
                'id' => $row['Lecturer_ID'],
                'name' => $row['Lecturer_Name'],
                'available' => $available
            ];
        }
    }
    $stmt->close();
    
    if (empty($lecturers)) {
        return ['assigned' => 0, 'errors' => ['No lecturers with available quota']];
    }
    
    // Get students without supervisors (not manually assigned)
    $studentQuery = "SELECT Student_ID FROM student 
                     WHERE FYP_Session_ID IN (" . implode(',', array_fill(0, count($fypSessionIds), '?')) . ")
                     AND (Supervisor_ID IS NULL OR Supervisor_ID = '')
                     AND (Supervisor_Manual IS NULL OR Supervisor_Manual = 0)
                     ORDER BY Student_ID ASC";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param(str_repeat('i', count($fypSessionIds)), ...$fypSessionIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row['Student_ID'];
    }
    $stmt->close();
    
    // Assign supervisors in round-robin fashion
    $lecturerIndex = 0;
    foreach ($students as $studentId) {
        if (empty($lecturers)) {
            $errors[] = "No more lecturers available for student $studentId";
            break;
        }
        
        $lecturer = &$lecturers[$lecturerIndex];
        
        // Update student with supervisor
        $updateStmt = $conn->prepare("UPDATE student SET Supervisor_ID = ?, Supervisor_Manual = 0 WHERE Student_ID = ?");
        $updateStmt->bind_param("ss", $lecturer['id'], $studentId);
        
        if ($updateStmt->execute()) {
            $assigned++;
            $lecturer['available']--;
            
            // Remove lecturer if no more quota
            if ($lecturer['available'] <= 0) {
                array_splice($lecturers, $lecturerIndex, 1);
                if ($lecturerIndex >= count($lecturers) && count($lecturers) > 0) {
                    $lecturerIndex = 0;
                }
            } else {
                $lecturerIndex = ($lecturerIndex + 1) % count($lecturers);
            }
        } else {
            $errors[] = "Failed to assign supervisor to student $studentId";
        }
        $updateStmt->close();
    }
    
    return ['assigned' => $assigned, 'errors' => $errors];
}

/**
 * Assign assessors automatically to students without assessors
 * Excludes supervisors and respects manual assignments
 */
function assignAssessorsAutomatically($conn, $fypSessionIds) {
    $assigned = 0;
    $errors = [];
    
    // Get all lecturers
    $lecturerQuery = "SELECT Lecturer_ID, Lecturer_Name FROM lecturer ORDER BY Lecturer_Name ASC";
    $result = $conn->query($lecturerQuery);
    
    $lecturers = [];
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = ['id' => $row['Lecturer_ID'], 'name' => $row['Lecturer_Name']];
    }
    
    if (count($lecturers) < 2) {
        return ['assigned' => 0, 'errors' => ['Not enough lecturers for assessor assignment']];
    }
    
    // Get students needing assessors
    $studentQuery = "SELECT Student_ID, Supervisor_ID FROM student 
                     WHERE FYP_Session_ID IN (" . implode(',', array_fill(0, count($fypSessionIds), '?')) . ")
                     AND Supervisor_ID IS NOT NULL
                     AND (
                         (Assessor1_ID IS NULL AND (Assessor1_Manual IS NULL OR Assessor1_Manual = 0))
                         OR (Assessor2_ID IS NULL AND (Assessor2_Manual IS NULL OR Assessor2_Manual = 0))
                     )
                     ORDER BY Student_ID ASC";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param(str_repeat('i', count($fypSessionIds)), ...$fypSessionIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $studentId = $row['Student_ID'];
        $supervisorId = $row['Supervisor_ID'];
        
        // Get current assessors
        $checkStmt = $conn->prepare("SELECT Assessor1_ID, Assessor2_ID, Assessor1_Manual, Assessor2_Manual 
                                      FROM student WHERE Student_ID = ?");
        $checkStmt->bind_param("s", $studentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $current = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        // Find available assessors (not supervisor, not already assigned)
        $availableAssessors = array_filter($lecturers, function($lec) use ($supervisorId, $current) {
            return $lec['id'] !== $supervisorId 
                   && $lec['id'] !== $current['Assessor1_ID'] 
                   && $lec['id'] !== $current['Assessor2_ID'];
        });
        
        $availableAssessors = array_values($availableAssessors);
        
        if (count($availableAssessors) < 1) {
            $errors[] = "Not enough available assessors for student $studentId";
            continue;
        }
        
        // Assign Assessor1 if needed and not manual
        if (empty($current['Assessor1_ID']) && empty($current['Assessor1_Manual'])) {
            $assessor1 = $availableAssessors[0];
            $updateStmt = $conn->prepare("UPDATE student SET Assessor1_ID = ?, Assessor1_Manual = 0 WHERE Student_ID = ?");
            $updateStmt->bind_param("ss", $assessor1['id'], $studentId);
            if ($updateStmt->execute()) {
                $assigned++;
                // Remove from available for next assignment
                $availableAssessors = array_filter($availableAssessors, function($a) use ($assessor1) {
                    return $a['id'] !== $assessor1['id'];
                });
                $availableAssessors = array_values($availableAssessors);
            }
            $updateStmt->close();
        }
        
        // Assign Assessor2 if needed and not manual
        if (empty($current['Assessor2_ID']) && empty($current['Assessor2_Manual'])) {
            if (count($availableAssessors) < 1) {
                $errors[] = "Not enough available assessors for second assessor of student $studentId";
                continue;
            }
            
            $assessor2 = $availableAssessors[0];
            $updateStmt = $conn->prepare("UPDATE student SET Assessor2_ID = ?, Assessor2_Manual = 0 WHERE Student_ID = ?");
            $updateStmt->bind_param("ss", $assessor2['id'], $studentId);
            if ($updateStmt->execute()) {
                $assigned++;
            }
            $updateStmt->close();
        }
    }
    $stmt->close();
    
    return ['assigned' => $assigned, 'errors' => $errors];
}

/**
 * Group-based assessor assignment
 * Groups students by supervisors (3 supervisors form a group)
 * Each student is assessed by the other 2 supervisors in their group
 * ENSURES: All students under the same supervisor get the SAME assessors
 */
function assignAssessorsGroupBased($conn, $fypSessionIds) {
    $assigned = 0;
    $errors = [];
    
    // Get all students with supervisors, grouped by supervisor
    $studentQuery = "SELECT Student_ID, Supervisor_ID, Assessor1_Manual, Assessor2_Manual 
                     FROM student 
                     WHERE FYP_Session_ID IN (" . implode(',', array_fill(0, count($fypSessionIds), '?')) . ")
                     AND Supervisor_ID IS NOT NULL
                     ORDER BY Supervisor_ID, Student_ID";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param(str_repeat('i', count($fypSessionIds)), ...$fypSessionIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $studentsBySupervisor = [];
    while ($row = $result->fetch_assoc()) {
        $supervisorId = $row['Supervisor_ID'];
        if (!isset($studentsBySupervisor[$supervisorId])) {
            $studentsBySupervisor[$supervisorId] = [];
        }
        $studentsBySupervisor[$supervisorId][] = $row;
    }
    $stmt->close();
    
    // Get all available lecturers
    $lecturerQuery = "SELECT Lecturer_ID FROM lecturer ORDER BY Lecturer_ID";
    $allLecturers = [];
    $result = $conn->query($lecturerQuery);
    while ($row = $result->fetch_assoc()) {
        $allLecturers[] = $row['Lecturer_ID'];
    }
    
    $supervisorIds = array_keys($studentsBySupervisor);
    
    // Create groups of 3 supervisors
    for ($i = 0; $i < count($supervisorIds); $i += 3) {
        $group = array_slice($supervisorIds, $i, 3);
        
        if (count($group) < 3) {
            // Incomplete group - still assign consistent assessors to all students under same supervisor
            foreach ($group as $supervisorId) {
                // Find 2 assessors that are not this supervisor
                $availableAssessors = array_values(array_filter($allLecturers, function($lec) use ($supervisorId) {
                    return $lec !== $supervisorId;
                }));
                
                if (count($availableAssessors) < 2) {
                    foreach ($studentsBySupervisor[$supervisorId] as $student) {
                        $errors[] = "Not enough assessors available for students of supervisor $supervisorId";
                    }
                    continue;
                }
                
                // Use first 2 available assessors for ALL students of this supervisor
                $assessor1 = $availableAssessors[0];
                $assessor2 = $availableAssessors[1];
                
                // Assign same assessors to ALL students under this supervisor
                foreach ($studentsBySupervisor[$supervisorId] as $student) {
                    $studentId = $student['Student_ID'];
                    
                    // Assign Assessor1 if not manual
                    if (empty($student['Assessor1_Manual']) || $student['Assessor1_Manual'] == 0) {
                        $updateStmt = $conn->prepare("UPDATE student SET Assessor1_ID = ?, Assessor1_Manual = 0 WHERE Student_ID = ?");
                        $updateStmt->bind_param("ss", $assessor1, $studentId);
                        if ($updateStmt->execute()) {
                            $assigned++;
                        } else {
                            $errors[] = "Failed to assign Assessor1 to student $studentId";
                        }
                        $updateStmt->close();
                    }
                    
                    // Assign Assessor2 if not manual
                    if (empty($student['Assessor2_Manual']) || $student['Assessor2_Manual'] == 0) {
                        $updateStmt = $conn->prepare("UPDATE student SET Assessor2_ID = ?, Assessor2_Manual = 0 WHERE Student_ID = ?");
                        $updateStmt->bind_param("ss", $assessor2, $studentId);
                        if ($updateStmt->execute()) {
                            $assigned++;
                        } else {
                            $errors[] = "Failed to assign Assessor2 to student $studentId";
                        }
                        $updateStmt->close();
                    }
                }
            }
            continue;
        }
        
        // Complete group of 3 - use standard group-based assignment
        // For each supervisor in the group
        foreach ($group as $index => $supervisorId) {
            // Get the other 2 supervisors in the group (these will be assessors)
            $assessors = array_values(array_diff($group, [$supervisorId]));
            
            if (count($assessors) != 2) {
                $errors[] = "Invalid group structure for supervisor $supervisorId";
                continue;
            }
            
            // Assign SAME assessors to ALL students of this supervisor
            foreach ($studentsBySupervisor[$supervisorId] as $student) {
                $studentId = $student['Student_ID'];
                
                // Assign Assessor1 if not manual
                if (empty($student['Assessor1_Manual']) || $student['Assessor1_Manual'] == 0) {
                    $updateStmt = $conn->prepare("UPDATE student SET Assessor1_ID = ?, Assessor1_Manual = 0 WHERE Student_ID = ?");
                    $updateStmt->bind_param("ss", $assessors[0], $studentId);
                    if ($updateStmt->execute()) {
                        $assigned++;
                    } else {
                        $errors[] = "Failed to assign Assessor1 to student $studentId";
                    }
                    $updateStmt->close();
                }
                
                // Assign Assessor2 if not manual
                if (empty($student['Assessor2_Manual']) || $student['Assessor2_Manual'] == 0) {
                    $updateStmt = $conn->prepare("UPDATE student SET Assessor2_ID = ?, Assessor2_Manual = 0 WHERE Student_ID = ?");
                    $updateStmt->bind_param("ss", $assessors[1], $studentId);
                    if ($updateStmt->execute()) {
                        $assigned++;
                    } else {
                        $errors[] = "Failed to assign Assessor2 to student $studentId";
                    }
                    $updateStmt->close();
                }
            }
        }
    }
    
    return ['assigned' => $assigned, 'errors' => $errors];
}

/**
 * Assign both supervisors and assessors automatically
 * Uses group-based assessor assignment (recommended for consistency)
 */
function assignBothAutomatically($conn, $fypSessionIds) {
    $supervisorResult = assignSupervisorsAutomatically($conn, $fypSessionIds);
    
    // Always use group-based mode for consistent assessment panels
    $assessorResult = assignAssessorsGroupBased($conn, $fypSessionIds);
    
    return [
        'supervisors_assigned' => $supervisorResult['assigned'],
        'assessors_assigned' => $assessorResult['assigned'],
        'errors' => array_merge($supervisorResult['errors'], $assessorResult['errors'])
    ];
}

/**
 * Assign assessment sessions automatically
 * Spreads students evenly across days and rooms
 * Keeps same assessor groups together but distributes students evenly
 */
function assignAssessmentSessionsAutomatically($conn, $fypSessionIds, $availableDays, $availableRooms, $maxStudentsPerRoom = 10) {
    $assigned = 0;
    $errors = [];
    
    if (empty($availableDays) || empty($availableRooms)) {
        return ['assigned' => 0, 'errors' => ['No available days or rooms']];
    }
    
    // Get students with their assessor assignments
    $studentQuery = "SELECT Student_ID, Supervisor_ID, Assessor1_ID, Assessor2_ID 
                     FROM student 
                     WHERE FYP_Session_ID IN (" . implode(',', array_fill(0, count($fypSessionIds), '?')) . ")
                     AND Assessor1_ID IS NOT NULL AND Assessor2_ID IS NOT NULL
                     ORDER BY Assessor1_ID, Assessor2_ID, Student_ID";
    
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param(str_repeat('i', count($fypSessionIds)), ...$fypSessionIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Group students by assessor combination
    $assessmentGroups = [];
    while ($row = $result->fetch_assoc()) {
        $groupKey = $row['Assessor1_ID'] . '_' . $row['Assessor2_ID'];
        if (!isset($assessmentGroups[$groupKey])) {
            $assessmentGroups[$groupKey] = [];
        }
        $assessmentGroups[$groupKey][] = $row['Student_ID'];
    }
    $stmt->close();
    
    // Initialize room capacity tracking for each day, seeded from existing DB assignments
    $dayRoomCapacity = [];
    $dayTotals = [];
    foreach ($availableDays as $day) {
        $dayRoomCapacity[$day] = [];
        $dayTotals[$day] = 0;
        foreach ($availableRooms as $room) {
            $dayRoomCapacity[$day][$room] = 0;
        }
    }

    // Seed capacity with existing assignments (manual or automatic) to avoid exceeding limits
    if (!empty($availableDays) && !empty($availableRooms)) {
        $dayPlaceholders = implode(',', array_fill(0, count($availableDays), '?'));
        $roomPlaceholders = implode(',', array_fill(0, count($availableRooms), '?'));
        $capacityQuery = "SELECT Day, Room, COUNT(*) AS cnt 
                          FROM assessment_session 
                          WHERE Day IN ($dayPlaceholders) AND Room IN ($roomPlaceholders)
                          GROUP BY Day, Room";
        $stmtCap = $conn->prepare($capacityQuery);
        $typesCap = str_repeat('s', count($availableDays) + count($availableRooms));
        $paramsCap = array_merge($availableDays, $availableRooms);
        $stmtCap->bind_param($typesCap, ...$paramsCap);
        if ($stmtCap->execute()) {
            $resCap = $stmtCap->get_result();
            while ($rowCap = $resCap->fetch_assoc()) {
                $d = $rowCap['Day'];
                $r = $rowCap['Room'];
                if (isset($dayRoomCapacity[$d]) && isset($dayRoomCapacity[$d][$r])) {
                    $dayRoomCapacity[$d][$r] = intval($rowCap['cnt']);
                    $dayTotals[$d] += intval($rowCap['cnt']);
                }
            }
        }
        $stmtCap->close();
    }
    
    $totalDays = count($availableDays);
    $totalRooms = count($availableRooms);
    $groupCounter = 0;

    // Distribute each assessor group across days, blending to max 10 per day
    foreach ($assessmentGroups as $groupKey => $students) {
        $studentsToAssign = $students;
        // Rotate starting room and day for fair distribution
        $assignedRoom = $availableRooms[$groupCounter % $totalRooms];
        $dayCursor = $groupCounter % $totalDays;
        $triesAllDays = 0;

        while (!empty($studentsToAssign)) {
            $currentDay = $availableDays[$dayCursor % $totalDays];
            
            // Check if day is at capacity (10 globally, not per room)
            $dayCapacity = intval($dayTotals[$currentDay] ?? 0);
            if ($dayCapacity >= 10) {
                // Day is full; move to next day
                $dayCursor++;
                $triesAllDays++;
                if ($triesAllDays >= $totalDays) {
                    // All days are full; stop assigning from this group
                    break;
                }
                continue;
            }

            // Real-time DB check to catch any race conditions
            $capStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM assessment_session WHERE Day = ? AND Room = ?");
            $capStmt->bind_param("ss", $currentDay, $assignedRoom);
            $capStmt->execute();
            $capRes = $capStmt->get_result();
            $capRow = $capRes->fetch_assoc();
            $capStmt->close();
            $currentCapacity = isset($dayRoomCapacity[$currentDay][$assignedRoom]) ? max($dayRoomCapacity[$currentDay][$assignedRoom], intval($capRow['cnt'])) : intval($capRow['cnt']);

            // Check how many students we can add to day without exceeding 10
            $availableInDay = 10 - intval($dayTotals[$currentDay] ?? 0);
            if ($availableInDay <= 0) {
                // Day is full; move to next day
                $dayCursor++;
                $triesAllDays++;
                if ($triesAllDays >= $totalDays) {
                    // All days are full; stop
                    break;
                }
                continue;
            }

            // Take only as many students as the day can hold
            $batchSize = min($availableInDay, count($studentsToAssign));
            $batch = array_splice($studentsToAssign, 0, $batchSize);

            foreach ($batch as $studentId) {
                $checkStmt = $conn->prepare("SELECT Session_Manual FROM assessment_session WHERE Student_ID = ?");
                $checkStmt->bind_param("s", $studentId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $current = $checkResult->fetch_assoc();
                $checkStmt->close();

                if (empty($current) || empty($current['Session_Manual']) || $current['Session_Manual'] == 0) {
                    $upsertStmt = $conn->prepare("INSERT INTO assessment_session (Student_ID, Day, Room, Session_Manual) 
                                                  VALUES (?, ?, ?, 0)
                                                  ON DUPLICATE KEY UPDATE Day = ?, Room = ?, Session_Manual = 0");
                    $upsertStmt->bind_param("sssss", $studentId, $currentDay, $assignedRoom, $currentDay, $assignedRoom);
                    if ($upsertStmt->execute()) {
                        $assigned++;
                        // Update capacities
                        $dayRoomCapacity[$currentDay][$assignedRoom] = ($dayRoomCapacity[$currentDay][$assignedRoom] ?? 0) + 1;
                        $dayTotals[$currentDay] = ($dayTotals[$currentDay] ?? 0) + 1;
                    } else {
                        $errors[] = "Failed to assign session to student $studentId";
                    }
                    $upsertStmt->close();
                }
            }

            // If day is now full or students remain, move to next day
            if (!empty($studentsToAssign)) {
                $dayCursor++;
            }
        }

        $groupCounter++;
    }
    
    return ['assigned' => $assigned, 'errors' => $errors];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $year = $_POST['year'] ?? '';
    $semester = $_POST['semester'] ?? '';
    
    // Get FYP Session IDs
    $fypSessionIds = [];
    $sessionQuery = "SELECT FYP_Session_ID FROM fyp_session WHERE FYP_Session = ? AND Semester = ?";
    $stmt = $conn->prepare($sessionQuery);
    $stmt->bind_param("ss", $year, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $fypSessionIds[] = $row['FYP_Session_ID'];
    }
    $stmt->close();
    
    if (empty($fypSessionIds)) {
        echo json_encode(['success' => false, 'message' => 'No FYP sessions found']);
        exit();
    }
    
    $response = ['success' => false];
    
    switch ($action) {
        case 'assign_supervisors':
            $result = assignSupervisorsAutomatically($conn, $fypSessionIds);
            $response = [
                'success' => true,
                'assigned' => $result['assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        case 'assign_assessors':
            // Group-based mode is recommended for consistency
            $result = assignAssessorsGroupBased($conn, $fypSessionIds);
            $response = [
                'success' => true,
                'assigned' => $result['assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        case 'assign_assessors_individual':
            // Individual mode (not recommended - use only for special cases)
            $result = assignAssessorsAutomatically($conn, $fypSessionIds);
            $response = [
                'success' => true,
                'assigned' => $result['assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        case 'assign_assessors_group':
            // Alias for assign_assessors (group-based is default)
            $result = assignAssessorsGroupBased($conn, $fypSessionIds);
            $response = [
                'success' => true,
                'assigned' => $result['assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        case 'assign_both':
            // Always uses group-based mode for consistent assessment panels
            $result = assignBothAutomatically($conn, $fypSessionIds);
            $response = [
                'success' => true,
                'supervisors_assigned' => $result['supervisors_assigned'],
                'assessors_assigned' => $result['assessors_assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        case 'assign_sessions':
            $days = json_decode($_POST['days'] ?? '[]', true);
            $rooms = json_decode($_POST['rooms'] ?? '[]', true);
            $maxStudentsPerRoom = isset($_POST['max_students_per_room']) ? intval($_POST['max_students_per_room']) : 10;
            $result = assignAssessmentSessionsAutomatically($conn, $fypSessionIds, $days, $rooms, $maxStudentsPerRoom);
            $response = [
                'success' => true,
                'assigned' => $result['assigned'],
                'errors' => $result['errors']
            ];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    echo json_encode($response);
    exit();
}

$conn->close();
?>
