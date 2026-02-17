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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            padding: 40px;
            max-width: 800px;
            margin: auto;
            background-color: #fff;
            color: #333;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo-container img {
            width: 150px;
            height: auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #ccc;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h2 {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 21px;
        }

        .header h3 {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 600;
            font-size: 18px;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 30px;
        }

        .meta-table td {
            padding: 8px;
            vertical-align: top;
            font-size: 18px;
            color: #333;
        }

        .meta-table strong {
            color: #333;
        }

        .agenda-item {
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin-bottom: 15px;
            background: #fafafa;
            border-radius: 5px;
            color: #333;
            font-size: 18px;
        }

        .agenda-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }

        .section-title {
            font-family: 'Arial', sans-serif;
            color: #333;
            font-weight: 600;
            font-size: 18px;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .divider {
            border: 0;
            height: 2px;
            background-color: #ccc;
            margin-bottom: 20px;
        }

        .btn-container {
            margin-top: 30px;
            text-align: right;
        }

        @media print {
            body {
                padding: 20px;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="logo-container">
        <img src="../../assets/UPMLogo.png" alt="UPM Logo">
    </div>

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

    <h3 class="section-title">Agenda & Progress</h3>
    <hr class="divider">

    <?php if ($agendas->num_rows > 0): ?>
        <?php while ($row = $agendas->fetch_assoc()): ?>
            <div class="agenda-item">
                <div class="agenda-title">Item: <?php echo htmlspecialchars($row['Agenda_Title']); ?></div>
                <div><?php echo nl2br(htmlspecialchars($row['Agenda_Content'])); ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No details recorded for this meeting.</p>
    <?php endif; ?>

    <div class="btn-container no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-download"></i> Download as PDF
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>