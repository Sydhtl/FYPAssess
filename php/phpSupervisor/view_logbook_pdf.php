<?php
// view_logbook_pdf.php
include '../db_connect.php';

$logbookID = $_GET['id'] ?? 0;

// Fetch Logbook Details
$sql = "SELECT l.*, s.Student_Name 
        FROM logbook l 
        JOIN student s ON l.Student_ID = s.Student_ID 
        WHERE Logbook_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $logbookID);
$stmt->execute();
$logbook = $stmt->get_result()->fetch_assoc();

if (!$logbook) {
    die("Logbook not found.");
}

// Fetch Agenda Items
$sqlAgenda = "SELECT * FROM logbook_agenda WHERE Logbook_ID = ?";
$stmtAgenda = $conn->prepare($sqlAgenda);
$stmtAgenda->bind_param("i", $logbookID);
$stmtAgenda->execute();
$agendas = $stmtAgenda->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Logbook PDF View</title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 40px; max-width: 800px; margin: auto; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .meta-table { width: 100%; margin-bottom: 30px; }
        .meta-table td { padding: 5px; vertical-align: top; }
        .agenda-item { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; background: #f9f9f9; }
        .agenda-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .status-badge { float: right; padding: 5px 10px; background: #eee; border-radius: 4px; font-weight: bold; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="no-print" style="margin-bottom: 20px; padding: 10px;">Print / Save as PDF</button>

    <div class="header">
        <h2>FINAL YEAR PROJECT LOGBOOK</h2>
        <h3><?php echo htmlspecialchars($logbook['Logbook_Name']); ?></h3>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Student Name:</strong> <?php echo htmlspecialchars($logbook['Student_Name']); ?></td>
            <td><strong>Student ID:</strong> <?php echo htmlspecialchars($logbook['Student_ID']); ?></td>
        </tr>
        <tr>
            <td><strong>Date:</strong> <?php echo htmlspecialchars($logbook['Logbook_Date']); ?></td>
            <td><strong>Status:</strong> <?php echo htmlspecialchars($logbook['Logbook_Status']); ?></td>
        </tr>
    </table>

    <h3>Agenda & Progress</h3>
    <hr>

    <?php if ($agendas->num_rows > 0): ?>
        <?php while($row = $agendas->fetch_assoc()): ?>
            <div class="agenda-item">
                <div class="agenda-title">Item: <?php echo htmlspecialchars($row['Agenda_Title']); ?></div>
                <div><?php echo nl2br(htmlspecialchars($row['Agenda_Content'])); ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No details recorded for this meeting.</p>
    <?php endif; ?>

</body>
</html>