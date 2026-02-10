<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    header("Location: index.php");
    exit;
}

include("db.php");
include("project_summary.php");


$opsName = 'Operations';

if (isset($_SESSION['user_id'])) {
    $q = $conn->query("
        SELECT full_name 
        FROM users 
        WHERE id = {$_SESSION['user_id']} 
        LIMIT 1
    ");
    if ($q && $q->num_rows > 0) {
        $opsName = $q->fetch_assoc()['full_name'];
    }
}



$officeSummary  = getOfficeSummary($conn);

$officeBudget   = $officeSummary['office_budget'];
$officeSpent    = $officeSummary['office_spent'];
$officeBalance  = $officeSummary['office_balance'];

/* ===== TOTAL APPROVED OFFICE REQUESTS ===== */
$totalOfficeRequests = $conn->query("
    SELECT COUNT(*) AS total
    FROM purchase_requests
    WHERE request_type='Office'
    AND final_approved = 1
")->fetch_assoc()['total'] ?? 0;

/* ===== OFFICE SPENDING ===== */
$month = date('m'); // Ensure $month is defined
$officeSpent = $conn->query("
    SELECT IFNULL(SUM(pri.quantity * pri.unit_cost),0) AS total
    FROM purchase_requests pr
    JOIN purchase_request_items pri ON pr.id = pri.request_id
    WHERE pr.request_type = 'Office'
    AND pr.payment_status = 'Paid'
    AND MONTH(pr.created_at) = '$month'
")->fetch_assoc()['total'];


/* ===== APPROVED OFFICE REQUESTS LIST ===== */
$approvedOfficeRequestsList = $conn->query("
    SELECT 
        pr.id,
        pr.request_code,
        pr.request_title,
        pr.created_at,
        u.role
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE pr.request_type='Office'
    AND pr.final_approved = 1
    ORDER BY pr.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OPS Office</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}
body { background: #f4f4f4; }

/* Top Bar */
.topbar {
    background: #2f8f3f;
    color: #fff;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
}
.topbar h2 { font-size: 20px; }
.logout {
    background: #fff;
    color: #2f8f3f;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}
.logout i { margin-right: 5px; }

/* Layout */
.container { display: flex; }

/* Sidebar */
.sidebar {
    width: 200px;
    background: #256d31;
    min-height: 100vh;
    padding-top: 60px;
    position: fixed;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
}
.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    color: #fff;
    text-decoration: none;
    font-size: 15px;
    font-weight: 400;
}
.sidebar a:hover { background: #2f8f3f; }

/* Main Content */
.main {
    flex: 1;
    margin-left: 220px;
    padding: 90px 30px 30px 30px;
}

/* Cards */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.card h4 { color: #2f8f3f; margin-bottom: 10px; }
.card p { font-size: 22px; font-weight: bold; color: #333; }

/* Sections */
.section { margin-top: 40px; }
.table-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
}
th {
    background: #2f8f3f;
    color: white;
    font-size: 13px;
    text-transform: uppercase;
}

/* Status */
.status-approved {
    background: #d4edda;
    color: #155724;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

/* Buttons */
.btn-view {
    background: #e7f3ff;
    color: #0b5ed7;
    padding: 6px 10px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 13px;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="topbar">
    <h3><i class="fas fa-briefcase"></i> Office — <?= htmlspecialchars($opsName) ?></h3>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
       <div class="sidebar">
<a href="operations_dashboard.php"><i class="fas fa-home"></i> Home</a>
<a href="ops_office.php"><i class="fas fa-briefcase"></i> Office</a>
<a href="operation_project.php"><i class="fas fa-diagram-project"></i> Projects</a>
<a href="ops_projects.php"><i class="fas fa-folder-open"></i> Request</a>
<a href="ops_settings.php"><i class="fas fa-cog"></i> Settings</a>
</div>

    <div class="main">

        <h3>Office Overview (Read Only)</h3>

        <div class="cards">
            <div class="card">
                <h4>Monthly Budget</h4>
                <p>$<?= number_format($officeBudget,2) ?></p>
            </div>
            <div class="card">
                <h4>Amount Spent</h4>
                <p>$<?= number_format($officeSpent,2) ?></p>
            </div>
            <div class="card">
                <h4>Balance</h4>
                <p>$<?= number_format($officeBalance,2) ?></p>
            </div>
            <div class="card">
                <h4>Approved Requests</h4>
                <p><?= $totalOfficeRequests ?></p>
            </div>
        </div>

        <div class="section">
            <h4><i class="fas fa-check-circle"></i> Approved Office Requests</h4>

            <div class="table-box">
                <table>
                    <tr>
                        <th>Request Code</th>
                        <th>Title</th>
                        <th>From</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($approvedOfficeRequestsList->num_rows == 0): ?>
                    <tr>
                        <td colspan="6">No approved office requests</td>
                    </tr>
                    <?php else: ?>
                    <?php while($r = $approvedOfficeRequestsList->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['request_code']) ?></td>
                        <td><?= htmlspecialchars($r['request_title']) ?></td>
                        <td><?= htmlspecialchars($r['role']) ?></td>
                        <td><?= $r['created_at'] ?></td>
                        <td><span class="status-approved"><i class="fas fa-check"></i> Approved</span></td>
                        <td><a href="view_request.php?id=<?= $r['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>

                </table>
            </div>
        </div>

    </div>
</div>

</body>
</html>
