<?php
include '../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$coordinatorId = $_SESSION['upmId'];
$currentYear   = $input['year'] ?? '';
$currentSemStr = $input['semester'] ?? '';
$lecturerIds    = isset($input['lecturer_ids']) && is_array($input['lecturer_ids']) ? $input['lecturer_ids'] : [];

if (empty($currentYear) || empty($currentSemStr)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing year or semester']);
    exit();
}

$currentSemester = (int)$currentSemStr;

// Compute past year/semester
$pastYear = $currentYear;
$pastSemester = $currentSemester - 1;

if ($currentSemester <= 1) {
    // Need previous academic year in coordinator's department
    $prevYearQuery = "SELECT DISTINCT fs.FYP_Session AS Year
                      FROM fyp_session fs
                      INNER JOIN course c ON fs.Course_ID = c.Course_ID
                      INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                      WHERE l.Lecturer_ID = ? AND fs.FYP_Session < ?
                      ORDER BY fs.FYP_Session DESC
                      LIMIT 1";
    if ($stmt = $conn->prepare($prevYearQuery)) {
        $stmt->bind_param('ss', $coordinatorId, $currentYear);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $pastYear = $row['Year'];
        }
        $stmt->close();
    }
    // Default past semester to 2 for previous year
    $pastSemester = 2;
}

// Resolve all FYP_Session_IDs for the past year+semester in coordinator's department
$fypIds = [];
$fypQuery = "SELECT DISTINCT fs.FYP_Session_ID
             FROM fyp_session fs
             INNER JOIN course c ON fs.Course_ID = c.Course_ID
             INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
             WHERE l.Lecturer_ID = ? AND fs.FYP_Session = ? AND fs.Semester = ?";
if ($stmt = $conn->prepare($fypQuery)) {
    $stmt->bind_param('ssi', $coordinatorId, $pastYear, $pastSemester);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fypIds[] = (int)$row['FYP_Session_ID'];
    }
    $stmt->close();
}

if (empty($fypIds)) {
    echo json_encode([
        'success' => true,
        'message' => 'No past FYP sessions found for coordinator department',
        'pastYear' => $pastYear,
        'pastSemester' => $pastSemester,
        'quotas' => []
    ]);
    $conn->close();
    exit();
}

// Build response quotas per supervisor
$quotas = [];

// Prepare statements for quota history and fallback
$placeholders = implode(',', array_fill(0, count($fypIds), '?'));
$types = str_repeat('i', count($fypIds));

$historySql = "SELECT Quota FROM supervisor_quota_history WHERE Supervisor_ID = ? AND FYP_Session_ID IN ($placeholders) ORDER BY FYP_Session_ID DESC LIMIT 1";
$historyStmt = $conn->prepare($historySql);

$fallbackSql = "SELECT Supervisor_Quota AS Quota FROM supervisor WHERE Supervisor_ID = ?";
$fallbackStmt = $conn->prepare($fallbackSql);

foreach ($lecturerIds as $supIdRaw) {
    $supId = (int)$supIdRaw;
    if ($supId <= 0) { continue; }
    $quotaVal = null;

    if ($historyStmt) {
        // bind dynamic params: supervisor_id + fypIds
        $bindTypes = 'i' . $types;
        $bindParams = array_merge([$supId], $fypIds);
        $historyStmt->bind_param($bindTypes, ...$bindParams);
        if ($historyStmt->execute()) {
            $r = $historyStmt->get_result();
            if ($hrow = $r->fetch_assoc()) {
                $quotaVal = (int)$hrow['Quota'];
            }
        }
        // reset statement
        $historyStmt->reset();
    }

    if ($quotaVal === null && $fallbackStmt) {
        $fallbackStmt->bind_param('i', $supId);
        if ($fallbackStmt->execute()) {
            $r2 = $fallbackStmt->get_result();
            if ($frow = $r2->fetch_assoc()) {
                $quotaVal = (int)$frow['Quota'];
            }
        }
        $fallbackStmt->reset();
    }

    if ($quotaVal !== null) {
        $quotas[] = ['supervisor_id' => $supId, 'quota' => $quotaVal];
    }
}

// Close statements
if ($historyStmt) { $historyStmt->close(); }
if ($fallbackStmt) { $fallbackStmt->close(); }

echo json_encode([
    'success' => true,
    'pastYear' => $pastYear,
    'pastSemester' => $pastSemester,
    'quotas' => $quotas
]);

$conn->close();
?>