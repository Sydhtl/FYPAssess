<?php
// view_collaboration_pdf.php
session_start();
include '../db_connect.php';

// 1. Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Request. No ID provided.");
}

$collabID = intval($_GET['id']);

// 2. Fetch Data
// Fetch new columns: Collaboration_Topics, Collaboration_Requirements
$sql = "SELECT c.*, fs.FYP_Session 
        FROM collaboration c 
        LEFT JOIN fyp_session fs ON c.FYP_Session_ID = fs.FYP_Session_ID 
        WHERE c.Collaboration_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $collabID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Form not found.");
}

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Collaboration Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body { background-color: #525659; font-family: 'Times New Roman', serif; }
        
        /* A4 Paper Container */
        #content-to-print {
            width: 210mm; 
            min-height: 297mm; 
            background: white;
            margin: 30px auto;
            padding: 40px 50px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }
        
        .header-logo { width: 150px; display: block; margin-bottom: 20px; }
        .doc-title { text-align: center; font-weight: bold; text-transform: uppercase; margin-bottom: 40px; text-decoration: underline; }
        .field-label { font-weight: bold; width: 35%; vertical-align: top; }
        .field-value { width: 65%; }
        .info-table td { padding: 10px 5px; border-bottom: 1px solid #eee; }
        
        .section-header { 
            background-color: #f8f9fa; 
            padding: 8px; 
            font-weight: bold; 
            border-left: 4px solid #780000; /* UPM Red */
            margin-top: 20px; margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        /* Hide the 'Download' button in the actual print/PDF */
        @media print {
            body { background: white; margin: 0; }
            #content-to-print { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="text-center mt-3 mb-3 no-print">
        <button onclick="downloadPDF()" class="btn btn-danger btn-lg shadow">
            Download PDF
        </button>
    </div>

    <div id="content-to-print">
        <img src="../../assets/UPMLogo.png" alt="UPM Logo" class="header-logo">
        
        <h4 class="doc-title">Industry Collaboration Form<br><?php echo $data['FYP_Session'] ?? 'Session Unknown'; ?></h4>

        <table class="table table-borderless info-table">
            <tr><td colspan="2"><div class="section-header">1. Collaboration Status</div></td></tr>
            <tr>
                <td class="field-label">Is there a collaboration?</td>
                <td class="field-value"><?php echo htmlspecialchars($data['Collaboration_Status']); ?></td>
            </tr>

            <?php if ($data['Collaboration_Status'] === 'Yes'): ?>
            
            <tr><td colspan="2"><div class="section-header">2. Company Information</div></td></tr>
            <tr>
                <td class="field-label">Company Name:</td>
                <td><?php echo htmlspecialchars($data['Company_Name']); ?></td>
            </tr>
            <tr>
                <td class="field-label">Address:</td>
                <td><?php echo nl2br(htmlspecialchars($data['Company_Address'])); ?></td>
            </tr>
            <tr>
                <td class="field-label">Email:</td>
                <td><?php echo htmlspecialchars($data['Company_Email']); ?></td>
            </tr>

            <tr><td colspan="2"><div class="section-header">3. Suggested Bachelor Topics</div></td></tr>
            <tr>
                <td colspan="2">
                    <?php 
                        // Use nl2br to preserve line breaks from the database text
                        echo nl2br(htmlspecialchars($data['Collaboration_Topics'])); 
                    ?>
                </td>
            </tr>

            <tr><td colspan="2"><div class="section-header">4. Industry Requirements</div></td></tr>
            <tr>
                <td colspan="2">
                    <?php 
                        echo nl2br(htmlspecialchars($data['Collaboration_Requirements'])); 
                    ?>
                </td>
            </tr>

            <tr><td colspan="2"><div class="section-header">5. Project Details</div></td></tr>
            <tr>
                <td class="field-label">Project Title:</td>
                <td><?php echo htmlspecialchars($data['Company_Title']); ?></td>
            </tr>
            <tr>
                <td class="field-label">Student Quota:</td>
                <td><?php echo htmlspecialchars($data['Student_Quota']); ?> Students</td>
            </tr>

            <tr><td colspan="2"><div class="section-header">6. Industry Supervisor(s)</div></td></tr>
            <tr>
                <td class="field-label">Name(s):</td>
                <td><?php echo htmlspecialchars($data['Company_Supervisor_Name']); ?></td>
            </tr>
            <tr>
                <td class="field-label">Email(s):</td>
                <td><?php echo htmlspecialchars($data['Company_Supervisor_Email']); ?></td>
            </tr>
            <tr>
                <td class="field-label">Phone(s):</td>
                <td><?php echo htmlspecialchars($data['Company_Supervisor_Phone']); ?></td>
            </tr>
            <tr>
                <td class="field-label">Role(s):</td>
                <td><?php echo htmlspecialchars($data['Company_Supervisor_Role']); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div style="margin-top: 50px; font-size: 0.9rem; color: #777; border-top: 1px solid #ccc; padding-top: 10px;">
            <p>Generated by FYPAssess System on: <?php echo date("d M Y, h:i A"); ?></p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const content = document.getElementById('content-to-print');
            const btn = document.querySelector('button');

            // Feedback to user
            btn.innerText = "Generating PDF...";
            btn.disabled = true;

            html2canvas(content, {
                scale: 2, // High resolution
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                const pdfWidth = 210; // A4 Width mm
                const pdfHeight = 297; // A4 Height mm
                
                // Calculate height to keep aspect ratio
                const imgProps = pdf.getImageProperties(imgData);
                const pdfImageHeight = (imgProps.height * pdfWidth) / imgProps.width;

                const pdf = new jsPDF('p', 'mm', 'a4');
                pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfImageHeight);
                pdf.save('Industry_Collaboration_Form.pdf');

                // Reset button
                btn.innerText = "Download PDF";
                btn.disabled = false;
            });
        }
    </script>

</body>
</html>