<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    header("Location: index.php");
    exit;
}

include("db.php");
include("project_summary.php"); // only include once, do not include inside itself


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


/* ===== DASHBOARD DATA ===== */

$totalIncome = 0;
$totalSpent  = 0;
$totalNet    = 0;

$projects = $conn->query("SELECT id FROM projects");
while ($p = $projects->fetch_assoc()) {
    $summary = getProjectSummary($conn, $p['id']);
    if (!$summary) continue;

    $totalIncome += $summary['total_paid'] ?? 0;       // same as Admin Dashboard
    $totalNet    += $summary['gross_profit'] ?? 0;     // same as Admin Dashboard
}



/* ===== TOTAL EXPENSES (PAID) — SAME AS FINANCE DASHBOARD ===== */

// Paid Expenses
$paidExpensesAmount = $conn->query("
    SELECT COALESCE(SUM(grand_total),0) AS total 
    FROM expenses 
    WHERE payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

// Paid Purchase Requests
$paidRequestsAmount = $conn->query("
    SELECT COALESCE(SUM(pri.quantity * pri.unit_cost),0) AS total
    FROM purchase_requests pr
    JOIN purchase_request_items pri ON pr.id = pri.request_id
    WHERE pr.payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

// FINAL TOTAL SPENT (PAID)
$totalSpent = $paidExpensesAmount + $paidRequestsAmount;





// Project status counts
$ongoing   = $conn->query("SELECT COUNT(*) AS c FROM projects WHERE status='Ongoing'")->fetch_assoc()['c'] ?? 0;
$completed = $conn->query("SELECT COUNT(*) AS c FROM projects WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
$onHold    = $conn->query("SELECT COUNT(*) AS c FROM projects WHERE status='On Hold'")->fetch_assoc()['c'] ?? 0;

// Recent projects
$recentProjects = $conn->query("SELECT project_name, location, status FROM projects ORDER BY created_at DESC LIMIT 5");

// Recent expenses
$recentExpenses = $conn->query("
    SELECT expense_title, grand_total 
    FROM expenses 
    WHERE status IN ('Approved','Pending_Finance','Pending') 
    ORDER BY created_at DESC LIMIT 5
");

// Alerts for over-budget projects
$alerts = [];
$allProjects = $conn->query("SELECT id FROM projects");
while ($p = $allProjects->fetch_assoc()) {
    $summary = getProjectSummary($conn, $p['id']);
    if ($summary && ($summary['gross_profit'] ?? 0) < 0) {
        $alerts[] = "⚠ Project “{$summary['project_name']}” is operating at a loss";
    }
}

// Selected project for summary
$selectedSummary = null;
$projectSpent = 0;
if (!empty($_GET['project_id'])) {
    $projectId = (int) $_GET['project_id'];
    $selectedSummary = getProjectSummary($conn, $projectId);
    if ($selectedSummary) {
        $projectSpent = $selectedSummary['project_spent'] ?? $selectedSummary['total_spent'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Operations Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* ==== SAME STYLES AS BEFORE ==== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f4f4;}
.topbar{background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;position:fixed;top:0;width:100%;z-index:1000;}
.topbar h2{font-size:20px;}
.logout{background:#fff;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}
.logout i{margin-right:5px;}
.container{display:flex;}
.sidebar{width:200px;background:#256d31;min-height:100vh;padding-top:60px;position:fixed;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:15px 20px;color:#fff;text-decoration:none;font-size:15px;font-weight:400;}
.sidebar a:hover{background:#2f8f3f;}
.main{flex:1;margin-left:220px;padding:30px;}
.main h3{margin-bottom:20px;color:#333;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.card h4{color:#2f8f3f;margin-bottom:10px;}
.card p{font-size:22px;font-weight:bold;color:#333;}
.section{margin-top:40px;}
.section h4{margin-bottom:15px;color:#2f8f3f;}
.table-box{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:20px;}
table{width:100%;border-collapse:collapse;}
table th,table td{padding:10px;border-bottom:1px solid #ddd;font-size:14px;}
table th{background:#f0f0f0;text-align:left;}
.alert{background:#fff3cd;border-left:5px solid #ff9800;padding:15px;margin-bottom:10px;font-size:14px;}
</style>
</head>
<body>
<div class="topbar">
    <h3><i class="fas fa-diagram-project"></i> Welcome — <?= htmlspecialchars($opsName) ?></h3>
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
<h3><i class="fas fa-chart-line"></i> Admin Dashboard Overview</h3>

<div class="cards">
<div class="card"><h4><i class="fas fa-arrow-down"></i> Total Income (Paid)</h4><p>$<?= number_format($totalIncome,2) ?></p></div>
<div class="card"><h4><i class="fas fa-arrow-up"></i> Total Expenses (Paid)</h4><p>$<?= number_format($totalSpent,2) ?></p></div>
</div>

<div class="section">
<h4><i class="fas fa-tasks"></i> Project Status Summary</h4>
<div class="cards">
<div class="card"><h4><i class="fas fa-spinner"></i> Ongoing</h4><p><?= $ongoing ?></p></div>
<div class="card"><h4><i class="fas fa-check-circle"></i> Completed</h4><p><?= $completed ?></p></div>
<div class="card"><h4><i class="fas fa-pause-circle"></i> On Hold</h4><p><?= $onHold ?></p></div>
</div>
</div>

 <div class="section">
    <h4>
        <i class="fa-solid fa-chart-line"></i>
        Project Financial Summary
    </h4>

    <form method="GET">
        <select name="project_id" onchange="this.form.submit()">
            <option value="">Select Project</option>
            <?php
            $projList = $conn->query("
    SELECT id, project_name
    FROM projects
    WHERE is_closed = 0
");
            while ($p = $projList->fetch_assoc()):
            ?>
                <option value="<?= $p['id'] ?>"
                    <?= (!empty($_GET['project_id']) && $_GET['project_id'] == $p['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['project_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if (!empty($selectedSummary)): ?>

        <?php
        $projectBudget = $selectedSummary['project_budget'] ?? 0;
        $projectSpent  = $selectedSummary['project_spent']
                      ?? $selectedSummary['total_project_out']
                      ?? 0;
        $cashLeft      = $projectBudget - $projectSpent;
        ?>

        <div class="table-box">
            <table>
                <tr>
                    <td>Project</td>
                    <td>
                        <strong><?= htmlspecialchars($selectedSummary['project_name']) ?></strong>
                        <span style="color:#2f8f3f; font-weight:bold;">
                            (<?= htmlspecialchars($selectedSummary['project_type'] ?? 'N/A') ?>)
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>Budget Amount</td>
                    <td>$<?= number_format($projectBudget, 2) ?></td>
                </tr>

                <tr>
                    <td>Total Paid</td>
                    <td>$<?= number_format($selectedSummary['total_paid'] ?? 0, 2) ?></td>
                </tr>

                <tr>
                    <td>Total Spent</td>
                    <td>$<?= number_format($projectSpent, 2) ?></td>
                </tr>

                <tr>
                    <td>Cash Left</td>
                    <td>
                        <span style="color:<?= $cashLeft < 0 ? '#d9534f' : '#2f8f3f' ?>">
                            $<?= number_format($cashLeft, 2) ?>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>Client Owes</td>
                    <td>$<?= number_format($selectedSummary['client_owes'] ?? 0, 2) ?></td>
                </tr>
            </table>
        </div>

    <?php endif; ?>
</div>

    </select>
</form>

<div class="section">
<h4><i class="fas fa-clock"></i> Recent Projects</h4>
<div class="table-box">
<table>
<tr><th>Project</th><th>Location</th><th>Status</th></tr>
<?php while($rp = $recentProjects->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($rp['project_name']) ?></td>
<td><?= htmlspecialchars($rp['location']) ?></td>
<td><?= htmlspecialchars($rp['status']) ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>

<div class="section">
<h4><i class="fas fa-receipt"></i> Recent Expenses</h4>
<div class="table-box">
<table>
<tr><th>Description</th><th>Amount</th></tr>
<?php while($re = $recentExpenses->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($re['expense_title']) ?></td>
<td>$<?= number_format($re['grand_total'],2) ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>

<div class="section">
<h4><i class="fas fa-bell"></i> Alerts & Notifications</h4>
<?php if(empty($alerts)): ?>
<div class="alert"><i class="fas fa-check-circle"></i> No financial alerts</div>
<?php else: ?>
<?php foreach($alerts as $a): ?>
<div class="alert"><i class="fas fa-triangle-exclamation"></i> <?= $a ?></div>
<?php endforeach; ?>
<?php endif; ?>
</div>

</div>
</div>

</body>
</html>
