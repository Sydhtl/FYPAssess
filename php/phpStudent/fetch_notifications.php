<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$studentId = $_SESSION['upmId'];

// Initialize session for tracking notification changes
if (!isset($_SESSION['last_notifications'])) {
    $_SESSION['last_notifications'] = [];
}

// Fetch notifications: Title submissions and Logbook submissions
$notifications = [];

// 1. Fetch Title notifications from fyp_project
$titleQuery = "
    SELECT fp.Proposed_Title, fp.Title_Status, fp.Project_ID,
           l.Lecturer_Name as Supervisor_Name
    FROM fyp_project fp
    INNER JOIN student s ON fp.Student_ID = s.Student_ID
    LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
    LEFT JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
    WHERE fp.Student_ID = ?
      AND fp.Proposed_Title IS NOT NULL 
      AND fp.Proposed_Title != ''
      AND fp.Title_Status IS NOT NULL
      AND fp.Title_Status != ''
    ORDER BY fp.Project_ID DESC
    LIMIT 1
";
$titleStmt = $conn->prepare($titleQuery);
if ($titleStmt) {
    $titleStmt->bind_param("s", $studentId);
    $titleStmt->execute();
    $titleResult = $titleStmt->get_result();
    if ($titleRow = $titleResult->fetch_assoc()) {
        $projectId = (int)($titleRow['Project_ID'] ?? 0);
        $status = $titleRow['Title_Status'] ?? '';
        $notifKey = 'title_' . $projectId;
        
        // Check if this notification has changed since last view
        $isNew = false;
        $changeTime = null;
        
        if (isset($_SESSION['last_notifications'][$notifKey])) {
            $lastData = $_SESSION['last_notifications'][$notifKey];
            $lastStatus = $lastData['status'] ?? '';
            $lastChangeTime = $lastData['change_time'] ?? null;
            
            if ($lastStatus !== $status) {
                // Status just changed - record the change time
                $isNew = true;
                $changeTime = time();
            } else {
                // Status unchanged - use existing change time if available
                $changeTime = $lastChangeTime;
                // Keep showing as "new" if changed in this session
                $isNew = ($lastChangeTime !== null);
            }
        } else {
            // First time seeing this notification
            $isNew = true;
            $changeTime = time();
        }
        
        // Use change time for sorting - new/changed notifications stay at top
        if ($changeTime !== null) {
            $sortDateStr = date('Y-m-d H:i:s', $changeTime);
        } else {
            // Old notifications use a past date
            $sortDateStr = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
        }
        
        $notifications[] = [
            'type' => 'title',
            'title' => $titleRow['Proposed_Title'],
            'status' => $status,
            'supervisor' => $titleRow['Supervisor_Name'] ?? 'N/A',
            'date' => date('Y-m-d'),
            'sort_date' => $sortDateStr,
            'project_id' => $projectId,
            'notif_key' => $notifKey,
            'is_new' => $isNew
        ];
        
        // Update session with current status and change time
        $_SESSION['last_notifications'][$notifKey] = [
            'status' => $status,
            'change_time' => $changeTime
        ];
    }
    $titleStmt->close();
}

// 2. Fetch Logbook notifications from logbook table
$logbookQuery = "
    SELECT l.Logbook_Name, l.Logbook_Status, l.Logbook_Date,
           lec.Lecturer_Name as Supervisor_Name
    FROM logbook l
    INNER JOIN student s ON l.Student_ID = s.Student_ID
    LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
    LEFT JOIN lecturer lec ON sup.Lecturer_ID = lec.Lecturer_ID
    WHERE l.Student_ID = ?
      AND l.Logbook_Status IS NOT NULL
      AND l.Logbook_Status != ''
    ORDER BY l.Logbook_Date DESC, l.Logbook_ID DESC
";
$logbookStmt = $conn->prepare($logbookQuery);
if ($logbookStmt) {
    $logbookStmt->bind_param("s", $studentId);
    $logbookStmt->execute();
    $logbookResult = $logbookStmt->get_result();
    $logbookIndex = 0;
    while ($logRow = $logbookResult->fetch_assoc()) {
        $logbookName = $logRow['Logbook_Name'];
        $logbookDate = $logRow['Logbook_Date'];
        $status = $logRow['Logbook_Status'];
        $notifKey = 'logbook_' . $logbookName . '_' . $logbookDate;
        
        // Check if this logbook notification has changed since last view
        $isNew = false;
        $changeTime = null;
        
        if (isset($_SESSION['last_notifications'][$notifKey])) {
            $lastData = $_SESSION['last_notifications'][$notifKey];
            $lastStatus = $lastData['status'] ?? '';
            $lastChangeTime = $lastData['change_time'] ?? null;
            
            if ($lastStatus !== $status) {
                // Status just changed - record the change time
                $isNew = true;
                $changeTime = time();
            } else {
                // Status unchanged - use existing change time if available
                $changeTime = $lastChangeTime;
                // Keep showing as "new" if changed in this session
                $isNew = ($lastChangeTime !== null);
            }
        } else {
            // First time seeing this notification
            $isNew = true;
            $changeTime = time();
        }
        
        // Use change time for sorting - new/changed notifications stay at top
        if ($changeTime !== null) {
            $sortDateStr = date('Y-m-d H:i:s', $changeTime - $logbookIndex); // Slightly offset to maintain order
        } else {
            // Old notifications use a past date
            $sortDateStr = date('Y-m-d', strtotime('-30 days')) . ' ' . date('H:i:s', strtotime($logbookDate));
        }
        
        $notifications[] = [
            'type' => 'logbook',
            'title' => $logbookName,
            'status' => $status,
            'supervisor' => $logRow['Supervisor_Name'] ?? 'N/A',
            'date' => $logbookDate,
            'sort_date' => $sortDateStr,
            'notif_key' => $notifKey,
            'is_new' => $isNew
        ];
        
        // Update session with current status and change time
        $_SESSION['last_notifications'][$notifKey] = [
            'status' => $status,
            'change_time' => $changeTime
        ];
        $logbookIndex++;
    }
    $logbookStmt->close();
}

// Sort notifications by date (most recent first)
// Compare dates properly - if dates are equal, don't artificially prioritize title
usort($notifications, function($a, $b) {
    // Convert dates to timestamps for proper comparison
    $dateA = strtotime($a['sort_date']);
    $dateB = strtotime($b['sort_date']);
    
    // Most recent first
    if ($dateB != $dateA) {
        return $dateB - $dateA;
    }
    
    // If dates are equal, use Project_ID or Logbook_ID for tie-breaking (higher = more recent)
    if (isset($a['project_id']) && isset($b['project_id'])) {
        return $b['project_id'] - $a['project_id'];
    }
    
    // If dates are equal and only one has project_id, they're equal priority
    return 0;
});

echo json_encode(['success' => true, 'notifications' => $notifications]);
$conn->close();
?>
