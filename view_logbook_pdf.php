<?php
// view_logbook_pdf.php
session_start();
include '../db_connect.php';

// Get logbook ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Request. No logbook ID provided.");
}

$logbookID = intval($_GET['id']);

// Fetch logbook details with student and course information
// Note: FYP_Session_ID in student_enrollment is VARCHAR
$sql = "SELECT 
            l.Logbook_ID,
            l.Student_ID,
            l.Logbook_Name,
            l.Logbook_Status,
            l.Logbook_Date,
            s.Student_Name,
            c.Course_Code,
            c.Course_Name,
            fs.FYP_Session,
            fs.Semester
        FROM logbook l
        INNER JOIN student s ON l.Student_ID = s.Student_ID
        INNER JOIN course c ON l.Course_ID = c.Course_ID
        LEFT JOIN student_enrollment se ON s.Student_ID = se.Student_ID
        LEFT JOIN fyp_session fs ON CAST(fs.FYP_Session_ID AS CHAR) = se.FYP_Session_ID
        WHERE l.Logbook_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $logbookID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Logbook not found.");
}

$data = $result->fetch_assoc();

// Fetch agendas for this logbook
$agendaStmt = $conn->prepare(
    "SELECT Agenda_ID, Agenda_Title, Agenda_Content 
     FROM logbook_agenda 
     WHERE Logbook_ID = ? 
     ORDER BY Agenda_ID ASC"
);
$agendaStmt->bind_param("i", $logbookID);
$agendaStmt->execute();
$agendaResult = $agendaStmt->get_result();

$agendas = [];
while ($agenda = $agendaResult->fetch_assoc()) {
    $agendas[] = $agenda;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Logbook Entry - PDF View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            background-color: #525659;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
        }

        h2 {
            color: #780000;
            margin-bottom: 20px;
        }

        .btn-generate {
            background-color: #780000;
            color: white;
            padding: 15px 40px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-generate:hover {
            background-color: #5a0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-generate:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .info-text {
            color: #666;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Logbook Entry</h2>
        <p class="info-text"><strong>Student:</strong> <?php echo htmlspecialchars($data['Student_Name']); ?></p>
        <p class="info-text"><strong>Course:</strong> <?php echo htmlspecialchars($data['Course_Code']); ?></p>
        <p class="info-text"><strong>Entry:</strong> <?php echo htmlspecialchars($data['Logbook_Name']); ?></p>
        <p class="info-text"><strong>Status:</strong> <span class="badge bg-<?php
        echo $data['Logbook_Status'] === 'Approved' ? 'success' :
            ($data['Logbook_Status'] === 'Declined' ? 'danger' : 'warning');
        ?>"><?php echo htmlspecialchars($data['Logbook_Status']); ?></span></p>
        <button onclick="generateLogbookPdf()" class="btn-generate" id="generateBtn">
            <i class="bi bi-file-pdf"></i> Generate PDF
        </button>
        <p class="info-text">Click the button above to generate and view your PDF document.</p>
    </div>

    <script>
        // Inject server-derived logbook data
        var studentName = <?php echo json_encode($data['Student_Name']); ?>;
        var courseCode = <?php echo json_encode($data['Course_Code']); ?>;
        var sessionDB = <?php echo json_encode(($data['FYP_Session'] ?? 'N/A') . ' - ' . ($data['Semester'] ?? '')); ?>;
        var logbookName = <?php echo json_encode($data['Logbook_Name']); ?>;
        var logbookStatus = <?php echo json_encode($data['Logbook_Status']); ?>;
        var logbookDate = <?php echo json_encode($data['Logbook_Date']); ?>;
        var agendas = <?php echo json_encode($agendas); ?>;

        function generateLogbookPdf() {
            // Get jsPDF from the UMD bundle
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            const btn = document.getElementById('generateBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
            btn.disabled = true;

            var session = sessionDB || 'N/A';

            // Load and add UPM logo
            var img = new Image();
            img.src = '../../assets/UPMLogo.png';
            img.onload = function () {
                // Add logo centered at top
                var logoWidth = 30;
                var logoHeight = 20;
                var pageWidth = 210;
                var xPos = (pageWidth - logoWidth) / 2;
                doc.addImage(img, 'PNG', xPos, 10, logoWidth, logoHeight);

                // Title
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('Logbook Entry', 105, 35, { align: 'center' });

                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, 40, 190, 40);

                // Content
                doc.setFontSize(12);
                doc.setFont(undefined, 'normal');

                var yPos = 50;
                var lineHeight = 10;

                // Student info
                doc.setFont(undefined, 'bold');
                doc.text('Student Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(studentName, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Course Code:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(courseCode, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Session:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(session, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Logbook Entry:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookName, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Date:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookDate, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Status:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookStatus, 70, yPos);
                yPos += lineHeight + 5;

                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;

                // Logbook content section
                doc.setFont(undefined, 'bold');
                doc.text('Logbook Content:', 20, yPos);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;

                // Display agendas
                if (agendas.length > 0) {
                    agendas.forEach(function (agenda, index) {
                        // Check if we need a new page
                        if (yPos > 260) {
                            doc.addPage();
                            yPos = 20;
                        }

                        doc.setFont(undefined, 'bold');
                        var agendaTitle = (index + 1) + '. ' + agenda.Agenda_Title;
                        var titleLines = doc.splitTextToSize(agendaTitle, 170);
                        doc.text(titleLines, 20, yPos);
                        yPos += lineHeight * titleLines.length;

                        doc.setFont(undefined, 'normal');
                        var explanationLines = doc.splitTextToSize(agenda.Agenda_Content, 170);
                        doc.text(explanationLines, 20, yPos);
                        yPos += lineHeight * explanationLines.length + 5;
                    });
                } else {
                    var contentText = 'No agenda items added.';
                    doc.text(contentText, 20, yPos);
                    yPos += lineHeight;
                }

                // Footer
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });

                // Reset button and open PDF
                btn.innerHTML = originalText;
                btn.disabled = false;

                // Open PDF in new window for viewing
                window.open(doc.output('bloburl'), '_blank');
            };

            // Fallback without logo
            img.onerror = function () {
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('Logbook Entry', 105, 20, { align: 'center' });

                doc.setLineWidth(0.5);
                doc.line(20, 25, 190, 25);

                doc.setFontSize(12);
                doc.setFont(undefined, 'normal');

                var yPos = 35;
                var lineHeight = 10;

                doc.setFont(undefined, 'bold');
                doc.text('Student Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(studentName, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Course Code:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(courseCode, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Session:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(session, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Logbook Entry:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookName, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Date:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookDate, 70, yPos);
                yPos += lineHeight;

                doc.setFont(undefined, 'bold');
                doc.text('Status:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(logbookStatus, 70, yPos);
                yPos += lineHeight + 5;

                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;

                doc.setFont(undefined, 'bold');
                doc.text('Logbook Content:', 20, yPos);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;

                // Display agendas
                if (agendas.length > 0) {
                    agendas.forEach(function (agenda, index) {
                        if (yPos > 260) {
                            doc.addPage();
                            yPos = 20;
                        }

                        doc.setFont(undefined, 'bold');
                        var agendaTitle = (index + 1) + '. ' + agenda.Agenda_Title;
                        var titleLines = doc.splitTextToSize(agendaTitle, 170);
                        doc.text(titleLines, 20, yPos);
                        yPos += lineHeight * titleLines.length;

                        doc.setFont(undefined, 'normal');
                        var explanationLines = doc.splitTextToSize(agenda.Agenda_Content, 170);
                        doc.text(explanationLines, 20, yPos);
                        yPos += lineHeight * explanationLines.length + 5;
                    });
                } else {
                    var contentText = 'No agenda items added.';
                    doc.text(contentText, 20, yPos);
                    yPos += lineHeight;
                }

                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });

                // Reset button and open PDF
                btn.innerHTML = originalText;
                btn.disabled = false;

                window.open(doc.output('bloburl'), '_blank');
            };
        }
    </script>

</body>

</html>