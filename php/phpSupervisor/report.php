<?php
// Start Session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../db_connect.php';

// 1. CAPTURE ROLE & USER ID
// Check if loginID is in session, otherwise default to 'GUEST'
$loginID = isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'USER';
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. INITIALIZE SESSION VARIABLES
$courseCode = "SWE4949";
$courseSession = "2024/2025 - 1";
$latestSessionID = null;

// A. Get Login ID 
if (isset($_SESSION['upmId'])) {
    $loginID = $_SESSION['upmId'];
} else {
    $loginID = 'hazura'; // Fallback
}

// Check if user has Coordinator role
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$isCoordinator = ($userRole === 'Coordinator');

// Get lecturer full name
$lecturerName = $loginID; // Default fallback
$stmtName = $conn->prepare("SELECT Lecturer_Name FROM lecturer WHERE Lecturer_ID = ?");
$stmtName->bind_param("s", $loginID);
$stmtName->execute();
if ($rowName = $stmtName->get_result()->fetch_assoc()) {
    $lecturerName = $rowName['Lecturer_Name'];
}
$stmtName->close();

// B. Lookup Supervisor ID
$supervisorID = 0;
if ($activeRole === 'supervisor') {
    $stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $supervisorID = $row['Supervisor_ID'];
    $stmt->close();
}

// B2. Get the latest session where this supervisor has students
if ($supervisorID > 0) {
    $sqlLatestSession = "SELECT fs.FYP_Session_ID, fs.FYP_Session, fs.Semester, c.Course_Code
                         FROM student_enrollment se
                         JOIN fyp_session fs ON se.FYP_Session_ID = fs.FYP_Session_ID
                         JOIN course c ON fs.Course_ID = c.Course_ID
                         WHERE se.Supervisor_ID = ?
                         ORDER BY fs.FYP_Session DESC, fs.Semester DESC
                         LIMIT 1";
    $stmtSession = $conn->prepare($sqlLatestSession);
    $stmtSession->bind_param("i", $supervisorID);
    $stmtSession->execute();
    $resultLatestSession = $stmtSession->get_result();
    if ($rowSession = $resultLatestSession->fetch_assoc()) {
        $latestSessionID = $rowSession['FYP_Session_ID'];
        // Keep hardcoded courseCode = "SWE4949"
        $courseSession = $rowSession['FYP_Session'] . " - " . $rowSession['Semester'];
    }
    $stmtSession->close();
}

// C. Fetch students assigned to this supervisor (current session only)
$students = [];
if ($supervisorID > 0 && $latestSessionID) {
    $sqlStudents = "SELECT DISTINCT se.Student_ID, s.Student_Name, c.Course_Code
                    FROM student_enrollment se
                    JOIN student s ON se.Student_ID = s.Student_ID AND se.FYP_Session_ID = s.FYP_Session_ID
                    JOIN fyp_session fs ON se.FYP_Session_ID = fs.FYP_Session_ID
                    JOIN course c ON fs.Course_ID = c.Course_ID
                    WHERE se.Supervisor_ID = ? AND se.FYP_Session_ID = ?
                    ORDER BY s.Student_Name";
    $stmtStudents = $conn->prepare($sqlStudents);
    $stmtStudents->bind_param("ii", $supervisorID, $latestSessionID);
    $stmtStudents->execute();
    $resultStudents = $stmtStudents->get_result();
    while ($rowStudent = $resultStudents->fetch_assoc()) {
        $students[] = $rowStudent;
    }
    $stmtStudents->close();
}



// D. Get selected student (default to first student)
$selectedStudentID = isset($_GET['student_id']) ? $_GET['student_id'] : (count($students) > 0 ? $students[0]['Student_ID'] : null);
$selectedStudent = null;
$selectedStudentName = '';
$selectedCourseCode = '';
$projectTitle = '';

if ($selectedStudentID) {
    // Get student details
    foreach ($students as $s) {
        if ($s['Student_ID'] == $selectedStudentID) {
            $selectedStudent = $s;
            $selectedStudentName = $s['Student_Name'];
            $selectedCourseCode = $s['Course_Code'];
            break;
        }
    }
    
    // Get project title
    $sqlProject = "SELECT Project_Title FROM fyp_project WHERE Student_ID = ? LIMIT 1";
    $stmtProject = $conn->prepare($sqlProject);
    $stmtProject->bind_param("s", $selectedStudentID);
    $stmtProject->execute();
    if ($rowProject = $stmtProject->get_result()->fetch_assoc()) {
        $projectTitle = $rowProject['Project_Title'];
    }
    $stmtProject->close();
}

// E. Fetch all assessments with their evaluation data for selected student
$assessmentData = [];
if ($selectedStudentID && $supervisorID > 0) {
    // Get all assessments for the course
    $sqlAssessments = "SELECT a.Assessment_ID, a.Assessment_Name, a.Total_Percentage, a.Course_ID, c.Course_Code
                       FROM assessment a
                       JOIN course c ON a.Course_ID = c.Course_ID
                       ORDER BY a.Course_ID, a.Assessment_ID";
    $resultAssessments = $conn->query($sqlAssessments);
    
    while ($rowAssessment = $resultAssessments->fetch_assoc()) {
        $assessmentID = $rowAssessment['Assessment_ID'];
        $assessmentName = $rowAssessment['Assessment_Name'];
        $maxPercentage = $rowAssessment['Total_Percentage'];
        $assessmentCourseID = $rowAssessment['Course_ID'];
        $assessmentCourseCode = $rowAssessment['Course_Code'];
        
        // Determine if assessed by Supervisor or Assessor based on Assessment ID
        // Assessment 1, 4, 5 = Supervisor
        // Assessment 2, 3 = Assessor
        if (in_array($assessmentID, [1, 4, 5])) {
            $evaluatorType = 'Supervisor';
        } elseif (in_array($assessmentID, [2, 3])) {
            $evaluatorType = 'Assessor';
        } else {
            $evaluatorType = 'Supervisor'; // Default
        }
        
        // Get evaluation data for this assessment and student
        // First get criteria with marks (show all evaluations for this student)
        $sqlEval = "SELECT 
                        e.Criteria_ID,
                        ac.Criteria_Name,
                        COALESCE(SUM(e.Evaluation_Percentage), 0) as Total_Marks
                    FROM evaluation e
                    JOIN assessment_criteria ac ON e.Criteria_ID = ac.Criteria_ID
                    WHERE e.Student_ID = ? 
                    AND e.Assessment_ID = ?
                    GROUP BY e.Criteria_ID, ac.Criteria_Name
                    ORDER BY e.Criteria_ID";
        
        $stmtEval = $conn->prepare($sqlEval);
        $stmtEval->bind_param("si", $selectedStudentID, $assessmentID);
        $stmtEval->execute();
        $resultEval = $stmtEval->get_result();
        
        $criteria = [];
        $totalObtained = 0;
        while ($rowEval = $resultEval->fetch_assoc()) {
            $criteriaID = $rowEval['Criteria_ID'];
            
            // Get learning objective code for this criteria
            $sqlLO = "SELECT GROUP_CONCAT(DISTINCT LearningObjective_Code SEPARATOR ', ') as LO_Code
                      FROM learning_objective_allocation 
                      WHERE Criteria_ID = ? AND Assessment_ID = ?";
            $stmtLO = $conn->prepare($sqlLO);
            $stmtLO->bind_param("ii", $criteriaID, $assessmentID);
            $stmtLO->execute();
            $resultLO = $stmtLO->get_result();
            $loCode = 'N/A';
            if ($rowLO = $resultLO->fetch_assoc()) {
                $loCode = $rowLO['LO_Code'] ? $rowLO['LO_Code'] : 'N/A';
            }
            $stmtLO->close();
            
            $criteria[] = [
                'Criteria_ID' => $criteriaID,
                'Criteria_Name' => $rowEval['Criteria_Name'],
                'LearningObjective_Code' => $loCode,
                'Total_Marks' => $rowEval['Total_Marks']
            ];
            $totalObtained += $rowEval['Total_Marks'];
        }
        $stmtEval->close();
        
        $assessmentData[] = [
            'id' => $assessmentID,
            'name' => $assessmentName,
            'course_id' => $assessmentCourseID,
            'course_code' => $assessmentCourseCode,
            'max_percentage' => $maxPercentage,
            'total_obtained' => $totalObtained,
            'evaluator_type' => $evaluatorType,
            'criteria' => $criteria
        ];
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../css/supervisor/report.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css?v=<?php echo time(); ?>">
    <!-- <link rel="stylesheet" href="../../../css/dashboard.css"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()"><i class="bi bi-list"></i></button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">Hi, <?php echo ucwords(strtolower($lecturerName)); ?></span>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'supervisor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>
                <a href="industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
                <a href="report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>

                <a href="logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>
                <a href="signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Project Title
                </a>
            </div>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'assessor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="assessorMenu" class="menu-items <?php echo ($activeRole == 'assessor') ? 'expanded' : ''; ?>">
                <a href="../phpAssessor/dashboard.php?role=supervisor" id="Dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="../phpAssessor/notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

                    <?php if ($isCoordinator): ?>
                <a href="javascript:void(0)"
                    class="role-header <?php echo ($activeRole == 'coordinator') ? 'menu-expanded' : ''; ?>"
                    data-target="coordinatorMenu" onclick="toggleMenu('coordinatorMenu', this)">
                    <span class="role-text">Coordinator</span>
                    <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
                </a>

                <div id="coordinatorMenu"
                    class="menu-items <?php echo ($activeRole == 'coordinator') ? 'expanded' : ''; ?>">
                    <a href="../../html/coordinator/dashboard/dashboardCoordinator.php" id="CoordinatorDashboard">
                        <i class="bi bi-house-fill icon-padding"></i> Dashboard
                    </a>
                    <a href="../../html/coordinator/notification/notification.php" id="CoordinatorNotification">
                        <i class="bi bi-bell-fill icon-padding"></i> Notification
                    </a>
                    <a href="../../html/coordinator/studentAssignation/studentAssignation.php" id="StudentAssignation">
                        <i class="bi bi-people-fill icon-padding"></i> Student Assignation
                    </a>
                    <a href="../../html/coordinator/dateTimeAllocation/dateTimeAllocation.php" id="DateTimeAllocation">
                        <i class="bi bi-calendar-check-fill icon-padding"></i> Date & Time Allocation
                    </a>
                    <a href="../../html/coordinator/learningObjective/learningObjective.php" id="LearningObjective">
                        <i class="bi bi-book-fill icon-padding"></i> Learning Objective
                    </a>
                    <a href="../../html/coordinator/markSubmission/markSubmission.php" id="MarkSubmission">
                        <i class="bi bi-file-earmark-check-fill icon-padding"></i> Mark Submission
                    </a>
                    <a href="../../html/coordinator/signatureSubmission/signatureSubmission.php"
                        id="CoordinatorSignatureSubmission">
                        <i class="bi bi-pen-fill icon-padding"></i> Signature Submission
                    </a>
                </div>
            <?php endif; ?>

            <a href="../login.php" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i>
                Logout</a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">
        <a href="../dashboard/dashboard.html">
            <img src="../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>
        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule"><?php echo $moduleTitle; ?></div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo $courseCode; ?></div>
                <div id="courseSession"><?php echo $courseSession; ?></div>
            </div>
        </div>
    </div>

    </div>

    <div id="main">
        <div class="evaluation-container">
            <h1 class="page-title">Supervisees' Report</h1>

            <div class="evaluation-card">
                <!-- Student Selection Field -->
                <div class="form-field">
                    <p class="form-label"><strong>Select student</strong></p>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown" id="studentSelect" onchange="loadStudentReport(this.value)">
                            <option value="" <?php echo empty($_GET['student_id']) ? 'selected' : ''; ?>>Select a student...</option>
                            <?php if (empty($students)): ?>
                                <option value="" disabled>No students found</option>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['Student_ID']); ?>"
                                            <?php echo (isset($_GET['student_id']) && $student['Student_ID'] == $_GET['student_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['Student_ID'] . ' - ' . $student['Student_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($selectedStudent): ?>
            <!-- Student details -->
            <div class="student-details" style="<?php echo empty($_GET['student_id']) ? 'display: none;' : ''; ?>">
                <p><strong>Matric Number</strong>: <?php echo htmlspecialchars($selectedStudentID); ?></p>
                <p><strong>Name</strong>: <?php echo htmlspecialchars($selectedStudentName); ?></p>
                <p><strong>Programme Code</strong>: <?php echo htmlspecialchars($selectedCourseCode); ?></p>
                <p><strong>Supervisor</strong>: <?php echo ucwords(strtolower($lecturerName)); ?></p>
                <p><strong>Project title</strong>: <?php echo htmlspecialchars($projectTitle ? $projectTitle : 'Not assigned yet'); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($selectedStudent): ?>
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; <?php echo empty($_GET['student_id']) ? 'display: none;' : ''; ?>">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    <i class="bi bi-info-circle"></i> Expand the assessment type to see marks distribution
                </p>
                <button type="button" id="download-pdf-btn" class="btn btn-primary">
                    <i class="bi bi-download"></i> Download as PDF
                </button>
            </div>
            <div class="report-table-wrapper" style="<?php echo empty($_GET['student_id']) ? 'display: none;' : ''; ?>">
                <div class="report-grid-container">

                    <div class="grid-cell report-header-main" style="grid-column: 1 / 3;">Assessment Type</div>
                    <div class="grid-cell report-header-main" style="grid-column: 3 / 4;">Total Mark (%)</div>

                    <?php if (empty($assessmentData)): ?>
                        <div class="grid-cell" style="grid-column: 1 / 4; text-align: center; padding: 20px; color: #666;">
                            No evaluation data available for this student.
                        </div>
                    <?php else: ?>
                        <?php foreach ($assessmentData as $index => $assessment): ?>
                            <?php $groupClass = 'criteria-group-' . ($index + 1); ?>
                            
                            <!-- Assessment Header -->
                            <div class="grid-cell assessment-header collapsed" style="grid-column: 1 / 3;"
                                data-bs-toggle="collapse" data-bs-target=".<?php echo $groupClass; ?>">
                                <i class="fas fa-chevron-right toggle-icon"></i>
                                <span class="assessment-title">
                                    <?php echo htmlspecialchars($assessment['name']); ?>
                                </span>
                                <span class="evaluator-badge">
                                    <?php echo htmlspecialchars($assessment['course_code']) . ' - ' . htmlspecialchars($assessment['evaluator_type']); ?>
                                </span>
                            </div>
                            <div class="grid-cell assessment-total" style="grid-column: 3 / 4;">
                                <?php 
                                    $obtained = number_format($assessment['total_obtained'], 2);
                                    $max = number_format($assessment['max_percentage'], 2);
                                ?>
                                <span style="color: #000;"><?php echo $obtained; ?></span>
                                <span style="color: #787878;"> / <?php echo $max; ?></span>
                            </div>

                            <!-- Criteria Header (collapsible) -->
                            <?php if (!empty($assessment['criteria'])): ?>
                                <div class="criteria-row collapse <?php echo $groupClass; ?> criteria-header">
                                    <div class="grid-cell header-row">Evaluation Criteria</div>
                                    <div class="grid-cell header-row">Learning Outcome</div>
                                    <div class="grid-cell header-row">Mark (%)</div>
                                </div>

                                <!-- Criteria Rows -->
                                <?php foreach ($assessment['criteria'] as $criteria): ?>
                                    <div class="criteria-row collapse <?php echo $groupClass; ?>">
                                        <div class="grid-cell criteria-cell"><?php echo htmlspecialchars($criteria['Criteria_Name']); ?></div>
                                        <div class="grid-cell lo-cell"><?php echo htmlspecialchars($criteria['LearningObjective_Code'] ? $criteria['LearningObjective_Code'] : 'N/A'); ?></div>
                                        <div class="grid-cell mark-cell"><?php echo number_format($criteria['Total_Marks'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="criteria-row collapse <?php echo $groupClass; ?>">
                                    <div class="grid-cell" style="grid-column: 1 / 4; text-align: center; padding: 10px; color: #999;">
                                        No evaluation criteria recorded yet.
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>

            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Reload page with selected student
            function loadStudentReport(studentID) {
                if (studentID) {
                    window.location.href = 'report.php?role=supervisor&student_id=' + encodeURIComponent(studentID);
                }
            }

            // Setup download button
            document.addEventListener('DOMContentLoaded', () => {
                const downloadBtn = document.getElementById('download-pdf-btn');
                if (downloadBtn) {
                    downloadBtn.addEventListener('click', downloadReportAsPDF);
                }
            });

            // Download Report as PDF
            function downloadReportAsPDF() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');

                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 15;
                let yPosition = 20;

                // Header - UPM Logo at top center
                const logoWidth = 50;
                const logoHeight = 30;
                const logoX = (pageWidth - logoWidth) / 2;
                doc.addImage('../../assets/UPMLogo.png', 'PNG', logoX, yPosition, logoWidth, logoHeight);
                yPosition += logoHeight + 8;

                // Title
                doc.setFontSize(14);
                doc.setFont('helvetica', 'bold');
                const title = "SUPERVISEE'S REPORT";
                const titleWidth = doc.getTextWidth(title);
                doc.text(title, (pageWidth - titleWidth) / 2, yPosition);
                yPosition += 10;

                // Student Details Section
                doc.setFontSize(10);
                doc.setFont('helvetica', 'normal');
                
                const studentDetails = document.querySelector('.student-details');
                if (studentDetails) {
                    const details = Array.from(studentDetails.querySelectorAll('p')).map(p => p.textContent);
                    details.forEach(detail => {
                        if (yPosition > pageHeight - 20) {
                            doc.addPage();
                            yPosition = 20;
                        }
                        doc.text(detail, margin, yPosition);
                        yPosition += 6;
                    });
                }
                yPosition += 5;

                // Helper function to add section header
                function addSectionHeader(text) {
                    if (yPosition > pageHeight - 30) {
                        doc.addPage();
                        yPosition = 20;
                    }
                    doc.setFillColor(248, 249, 250);
                    doc.rect(margin, yPosition, pageWidth - 2 * margin, 8, 'F');
                    doc.setDrawColor(120, 0, 0);
                    doc.setLineWidth(1);
                    doc.line(margin, yPosition, margin, yPosition + 8);
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(0, 0, 0);
                    doc.text(text, margin + 3, yPosition + 5.5);
                    yPosition += 12;
                }

                // Get all assessment data from the page
                const assessmentHeaders = document.querySelectorAll('.assessment-header');
                
                assessmentHeaders.forEach((header, index) => {
                    // Get the full assessment name from the span inside the header
                    const assessmentTitleSpan = header.querySelector('.assessment-title');
                    const assessmentName = assessmentTitleSpan ? assessmentTitleSpan.textContent.trim() : 'Assessment';
                    
                    // Get total marks from the next sibling element
                    const totalMarksCell = header.nextElementSibling;
                    const totalMarksText = totalMarksCell ? totalMarksCell.textContent.trim() : '0/0';
                    
                    // Parse the total marks to remove brackets and split received/full marks
                    const cleanMarks = totalMarksText.replace(/[\[\]]/g, ''); // Remove [ and ]
                    const marksParts = cleanMarks.split('/');
                    const receivedMark = marksParts[0] ? marksParts[0].trim() : '0';
                    const fullMark = marksParts[1] ? marksParts[1].trim() : '0';
                    
                    addSectionHeader(assessmentName);
                    
                    if (yPosition > pageHeight - 20) {
                        doc.addPage();
                        yPosition = 20;
                    }
                    
                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Total Marks:', margin, yPosition);
                    
                    // Display received mark in normal black color
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(0, 0, 0);
                    doc.text(receivedMark, margin + 30, yPosition);
                    
                    // Display "/" and full mark in muted gray color
                    const receivedMarkWidth = doc.getTextWidth(receivedMark);
                    doc.setTextColor(120, 120, 120);
                    doc.text(' / ' + fullMark, margin + 30 + receivedMarkWidth, yPosition);
                    
                    // Reset text color to black for subsequent text
                    doc.setTextColor(0, 0, 0);
                    yPosition += 8;

                    // Get criteria details
                    const criteriaGroup = document.querySelectorAll('.criteria-group-' + (index + 1));
                    if (criteriaGroup.length > 0) {
                        // Add table header only once per assessment
                        doc.setFontSize(9);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Evaluation Criteria', margin, yPosition);
                        doc.text('Learning Objective', margin + 80, yPosition);
                        doc.text('Marks (%)', margin + 150, yPosition);
                        yPosition += 6;

                        doc.setFont('helvetica', 'normal');
                        criteriaGroup.forEach(row => {
                            // Skip the header row (it has 'criteria-header' class)
                            if (row.classList.contains('criteria-header')) {
                                return;
                            }
                            
                            if (yPosition > pageHeight - 15) {
                                doc.addPage();
                                yPosition = 20;
                            }

                            const cells = row.querySelectorAll('.grid-cell');
                            if (cells.length >= 3) {
                                const criteriaText = cells[0].textContent.trim();
                                const loText = cells[1].textContent.trim();
                                const marksText = cells[2].textContent.trim();

                                // Wrap long criteria text
                                const criteriaLines = doc.splitTextToSize(criteriaText, 75);
                                doc.text(criteriaLines, margin, yPosition);
                                doc.text(loText, margin + 80, yPosition);
                                doc.text(marksText, margin + 150, yPosition);
                                
                                yPosition += Math.max(criteriaLines.length * 5, 6);
                            }
                        });
                    }
                    yPosition += 5;
                });

                // Footer
                if (yPosition > pageHeight - 30) {
                    doc.addPage();
                    yPosition = 20;
                }
                yPosition = pageHeight - 15;
                doc.setFontSize(8);
                doc.setFont('helvetica', 'italic');
                doc.setTextColor(120, 120, 120);
                const now = new Date();
                const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                doc.text(`Generated by FYPAssess System on: ${dateStr}, ${timeStr}`, margin, yPosition);

                // Get student name for filename
                const studentSelect = document.getElementById('studentSelect');
                const selectedOption = studentSelect.options[studentSelect.selectedIndex];
                const studentInfo = selectedOption ? selectedOption.textContent : 'Report';
                const fileName = `Supervisee_Report_${studentInfo.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_-]/g, '')}.pdf`;
                
                doc.save(fileName);
            }
            
            // --- JAVASCRIPT LOGIC ---
            function openNav() {
                var fullWidth = "220px";
                var sidebar = document.getElementById("mySidebar");
                var header = document.getElementById("containerAtas");
                // CRITICAL FIX: Targets the main content area (now with id="main")
                var mainContent = document.getElementById("main");
                var menuIcon = document.querySelector(".menu-icon");

                // 1. Expand the Sidebar
                document.getElementById("mySidebar").style.width = fullWidth;

                // 2. Push the main content AND the header container to the right
                if (mainContent) mainContent.style.marginLeft = fullWidth;
                if (header) header.style.marginLeft = fullWidth;

                // 3. Show the links
                document.getElementById("nameSide").style.display = "block";

                var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
                for (var i = 0; i < links.length; i++) {
                    // Show role headers and other links
                    if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                        links[i].style.display = 'flex';
                    } else if (links[i].id === 'close') {
                        links[i].style.display = 'flex';
                    }
                }

                // Show currently expanded menu items
                document.querySelectorAll('.menu-items.expanded a').forEach(a => a.style.display = 'block');


                // 4. Hide the open icon
                if (menuIcon) menuIcon.style.display = "none";
            }

            function closeNav() {
                var collapsedWidth = "60px";
                var sidebar = document.getElementById("mySidebar");
                var header = document.getElementById("containerAtas");
                // CRITICAL FIX: Targets the main content area (now with id="main")
                var mainContent = document.getElementById("main");
                var menuIcon = document.querySelector(".menu-icon");

                // 1. Collapse the Sidebar
                sidebar.style.width = collapsedWidth;

                // 2. Move the main content AND the header container back
                if (mainContent) mainContent.style.marginLeft = collapsedWidth;
                if (header) header.style.marginLeft = collapsedWidth;

                // 3. Hide the name and the links (except for the open menu icon)
                document.getElementById("nameSide").style.display = "none";

                var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
                for (var i = 0; i < links.length; i++) {
                    links[i].style.display = "none";
                }

                // 4. Show the open icon
                if (menuIcon) menuIcon.style.display = "block";
            }

            // Ensure the collapsed state is set immediately on page load
            window.onload = function () {
                closeNav();
            };

            // --- Role Toggle Logic ---
            document.addEventListener('DOMContentLoaded', () => {
                const arrowContainers = document.querySelectorAll('.arrow-container');

                // Function to handle the role menu toggle
                const handleRoleToggle = (header) => {
                    const menuId = header.getAttribute('href');
                    const targetMenu = document.querySelector(menuId);
                    const arrowIcon = header.querySelector('.arrow-icon');

                    if (!targetMenu) return;

                    const isExpanded = targetMenu.classList.contains('expanded');

                    // Collapse all other menus and reset their arrows
                    document.querySelectorAll('.menu-items').forEach(menu => {
                        if (menu !== targetMenu) {
                            menu.classList.remove('expanded');
                            menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                        }
                    });
                    document.querySelectorAll('.role-header').forEach(h => {
                        if (h !== header) h.classList.remove('active-role');
                    });
                    document.querySelectorAll('.arrow-icon').forEach(icon => {
                        if (icon !== arrowIcon) {
                            icon.classList.remove('bi-chevron-down');
                            icon.classList.add('bi-chevron-right');
                        }
                    });

                    // Toggle current menu
                    targetMenu.classList.toggle('expanded', !isExpanded);
                    header.classList.toggle('active-role', !isExpanded);

                    // Show/hide child links for the current menu (only when sidebar is expanded)
                    const sidebar = document.getElementById("mySidebar");
                    const isSidebarExpanded = sidebar.style.width === "220px";

                    targetMenu.querySelectorAll('a').forEach(a => {
                        if (isSidebarExpanded) {
                            a.style.display = targetMenu.classList.contains('expanded') ? 'block' : 'none';
                        } else {
                            a.style.display = 'none';
                        }
                    });

                    // Toggle arrow direction
                    if (isExpanded) {
                        arrowIcon.classList.remove('bi-chevron-down');
                        arrowIcon.classList.add('bi-chevron-right');
                    } else {
                        arrowIcon.classList.remove('bi-chevron-right');
                        arrowIcon.classList.add('bi-chevron-down');
                    }
                }


                arrowContainers.forEach(container => {
                    // Attach event listener to the role header itself
                    const header = container.closest('.role-header');
                    header.addEventListener('click', (event) => {
                        event.preventDefault();
                        handleRoleToggle(header);
                    });
                });
                // --- Assessment Sub-Accordion Icon Logic ---
                // This script just toggles the '.collapsed' class for the icon CSS
                var assessmentHeaders = document.querySelectorAll('.assessment-header');

                assessmentHeaders.forEach(function (header) {
                    // Set the initial state of the icon on page load
                    // Bootstrap's JS runs after this, so we check the 'collapse' class
                    const targetSelector = header.getAttribute('data-bs-target');
                    const targetElement = document.querySelector(targetSelector);

                    // If the target is NOT shown by default, add 'collapsed'
                    if (targetElement && !targetElement.classList.contains('show')) {
                        header.classList.add('collapsed');
                    }

                    // Add click listener to toggle the icon class
                    header.addEventListener('click', function () {
                        // This toggles the class for the CSS rotation
                        header.classList.toggle('collapsed');
                    });
                });
            });
            // --- Assessment Toggle Logic ---
            function setupAssessmentToggle() {
                document.querySelectorAll('.assessment-toggle').forEach(header => {
                    header.addEventListener('click', () => {
                        const details = header.nextElementSibling; // The div.assessment-details
                        const icon = header.querySelector('.toggle-icon');

                        if (details.style.display === 'none' || details.style.display === '') {
                            // Expand
                            details.style.display = 'block';
                            icon.classList.add('rotated');
                        } else {
                            // Collapse
                            details.style.display = 'none';
                            icon.classList.remove('rotated');
                        }
                    });
                });

                // Set initial state for all details to collapsed
                document.querySelectorAll('.assessment-details').forEach(details => {
                    details.style.display = 'none';
                });
            }

            // Ensure the collapsed state is set immediately on page load
            window.onload = function () {
                closeNav();
            };



        </script>
</body>

</html>