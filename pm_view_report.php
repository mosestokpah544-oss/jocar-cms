<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: index.php");
    exit;
}

include("db.php");

$pm_id = $_SESSION['user_id'];
$report_id = intval($_GET['id'] ?? 0);

if (!$report_id) {
    die("Invalid report ID.");
}

// Fetch report
$stmt = $conn->prepare("
    SELECT dr.*, u.full_name AS supervisor_name, p.project_name, p.project_code, p.project_type
    FROM daily_reports dr
    JOIN users u ON dr.site_supervisor_id = u.id
    JOIN projects p ON dr.project_id = p.id
    WHERE dr.id=? AND p.project_manager_id=?
    LIMIT 1
");
$stmt->bind_param("ii", $report_id, $pm_id);
$stmt->execute();
$report_data = $stmt->get_result()->fetch_assoc();

if (!$report_data) {
    die("Report not found or you don't have access to it.");
}

/* Decode attachments */
$attachments = [];
if (!empty($report_data['attachments'])) {
    $attachments = json_decode($report_data['attachments'], true);
}

// Function to render JSON arrays in tables
function renderJsonTable($json, $cols) {
    $data = json_decode($json, true);
    if (!$data || !is_array($data)) {
        echo "<tr><td colspan='".count($cols)."'>No data</td></tr>";
        return;
    }

    $rows = array_map(null, ...array_values($data));
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>".htmlspecialchars($value)."</td>";
        }
        echo "</tr>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Report - <?= $report_data['project_name'] ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { font-family:Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
.report-box { max-width:900px; margin:30px auto; background:#fff; padding:30px; border:2px solid #2f8f3f; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.logo-img { width:180px; }
.project-info { text-align:right; }
.project-info div { margin-bottom:5px; font-weight:bold; color:#2f8f3f; }
h2 { color:#2f8f3f; margin-bottom:15px; text-align:center; }
h3 { color:#2f8f3f; margin-top:25px; margin-bottom:10px; }
table { width:100%; border-collapse:collapse; margin-bottom:20px; }
th, td { padding:10px; border-bottom:1px solid #ddd; text-align:left; }
th { background:#2f8f3f; color:#fff; }
p { margin:5px 0; }
button { padding:10px 20px; background:#2f8f3f; color:#fff; border:none; cursor:pointer; margin-top:20px; border-radius:5px; }
.share-btn { background:#007bff; margin-left:10px; }
.share-btn:hover { background:#0056b3; }
.button {
    padding:10px 20px;
    background:#2f8f3f;
    color:#fff;
    border:none;
    cursor:pointer;
    margin-right:10px;
}
.button:hover { background:#256d31; }
/* ===== ATTACHMENTS SECTION ===== */
.attachment-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

/* Images */
.attachment-box img {
    width: 100%;
    max-height: 350px;
    object-fit: contain; /* keeps full image without cropping */
    border: 1px solid #ccc;
    padding: 8px;
    background: #fafafa;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* PDFs */
.attachment-box iframe {
    width: 100%;
    height: 420px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Print-friendly */
@media print {
    .attachment-box {
        grid-template-columns: 1fr;
    }

    .attachment-box img {
        max-height: none;
        page-break-inside: avoid;
    }

    iframe {
        display: none; /* PDFs don't print well */
    }
}

</style>
</head>
<body>

<div class="report-box">

    <div class="header">
        <img src="IMG_5436.PNG" class="logo-img" alt="Company Logo">
        <div class="project-info">
            <div>Project Type: <?= strtoupper($report_data['project_type']) ?></div>
            <div>Project Code: <?= $report_data['project_code'] ?></div>
            <div>Project Name: <?= htmlspecialchars($report_data['project_name']) ?></div>
            <div>Report ID: <?= $report_data['id'] ?></div>
            <div>Date: <?= htmlspecialchars($report_data['report_date']) ?></div>
        </div>
    </div>

    <h2>Daily Report Details</h2>

    <h3>Supervisor</h3>
    <p><?= htmlspecialchars($report_data['supervisor_name']) ?></p>

    <h3>Weather</h3>
    <p><?= htmlspecialchars($report_data['weather']) ?></p>

    <h3>Crew</h3>
    <table>
        <thead><tr><th>Crew Type</th><th>Number</th><th>Hours Worked</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['crew'], ['type','number','hours']); ?></tbody>
    </table>

    <h3>Work Accomplished</h3>
    <table>
        <thead><tr><th>Task</th><th>Status</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['work'], ['task','status']); ?></tbody>
    </table>

    <h3>Equipment</h3>
    <table>
        <thead><tr><th>Type</th><th>Number</th><th>Status</th><th>Hours Used</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['equipment'], ['type','number','status','hours']); ?></tbody>
    </table>

    <h3>Material Quantities</h3>
    <table>
        <thead><tr><th>Type</th><th>Total</th><th>Used</th><th>Remaining</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['material_quantities'], ['type','total','used','remaining']); ?></tbody>
    </table>

    <h3>Material Deliveries</h3>
    <table>
        <thead><tr><th>Type</th><th>Name</th><th>Quantity</th><th>Scheduled</th><th>Actual</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['material_deliveries'], ['type','name','qty','scheduled','actual']); ?></tbody>
    </table>

    <h3>Potential Delays</h3>
    <table>
        <thead><tr><th>Type</th><th>Name</th><th>Description</th><th>Duration</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['potential_delays'], ['type','name','desc','duration']); ?></tbody>
    </table>

    <h3>Significant Events</h3>
    <table>
        <thead><tr><th>Type</th><th>Name</th><th>Description</th><th>Potential Delay</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['significant_events'], ['type','name','desc','delay']); ?></tbody>
    </table>

    <h3>Meetings</h3>
    <table>
        <thead><tr><th>Type</th><th>Time</th><th>Attendees</th><th>Summary</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['meetings'], ['type','time','attendees','summary']); ?></tbody>
    </table>

    <h3>Decisions</h3>
    <table>
        <thead><tr><th>Description</th><th>Action</th><th>Responsible Party</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['decisions'], ['description','action','party']); ?></tbody>
    </table>

    <h3>Directions</h3>
    <table>
        <thead><tr><th>Type</th><th>Given By</th><th>Details</th><th>Way to Fix</th></tr></thead>
        <tbody><?= renderJsonTable($report_data['directions'], ['type','given_by','detail','fix']); ?></tbody>
    </table>

    <h3>Safety</h3>
    <p><?= htmlspecialchars($report_data['safety']) ?></p>

    <!-- ✅ ATTACHMENTS SECTION -->
    <h3>Attachments</h3>

    <?php if (!empty($attachments) && is_array($attachments)): ?>
        <div class="attachment-box">
            <?php foreach ($attachments as $file):
                $filePath = "uploads/daily_reports/" . $file;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            ?>

                <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
                    <img src="<?= htmlspecialchars($filePath) ?>" alt="Attachment Image">

                <?php elseif ($ext === 'pdf'): ?>
                    <iframe 
                        src="<?= htmlspecialchars($filePath) ?>" 
                        width="100%" 
                        height="500px"
                        style="border:1px solid #ccc; margin-bottom:20px;"
                    ></iframe>
                <?php endif; ?>

            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No attachments submitted.</p>
    <?php endif; ?>

    <button onclick="window.print()">Print Report</button>
<button onclick="window.history.back()">Back</button>


</body>
</html>
