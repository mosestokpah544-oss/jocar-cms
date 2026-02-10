<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    header("Location: login.php");
    exit;
}

require_once "project_summary.php";

/* ================= FINANCE DASHBOARD DATA ================= */

// Office Summary
$office = getOfficeSummary($conn);

// Global project totals
$projectsSummary = $conn->query("SELECT id FROM projects");
$totalPayments = 0;
$totalBudget   = 0;
$totalExpenses = 0;

while ($proj = $projectsSummary->fetch_assoc()) {
    $summary = getProjectSummary($conn, $proj['id']);
    if (!$summary) continue;

    $totalPayments += $summary['total_paid'] ?? 0;
    $totalBudget   += $summary['project_budget'] ?? 0;
    $totalExpenses += $summary['total_project_out'] ?? 0;
}

$outstandingBalance = $totalBudget - $totalPayments;

// Recent Payments
$recentPayments = $conn->query("
    SELECT 
        p.amount,
        p.payment_date,
        pr.project_code,
        pr.project_name,
        pr.project_type
    FROM project_payments p
    JOIN projects pr ON p.project_id = pr.id
    ORDER BY p.created_at DESC
    LIMIT 5
");

/* ===== PROJECT FINANCIAL SUMMARY (SAME AS ADMIN) ===== */
$selectedSummary = null;
$projectSpent = 0;

if (!empty($_GET['project_id'])) {
    $projectId = (int) $_GET['project_id'];
    $selectedSummary = getProjectSummary($conn, $projectId);

    if (!empty($selectedSummary)) {
        $projectSpent = $selectedSummary['project_spent']
            ?? $selectedSummary['total_project_out']
            ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f4f4;}
.topbar{background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;position:fixed;top:0;width:100%;z-index:1000;}
.logout{background:#fff;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}
.container{display:flex;}
.sidebar{width:200px;background:#256d31;min-height:100vh;padding-top:60px;position:fixed;}
.sidebar a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;}
.sidebar a:hover{background:#2f8f3f;}
.main{flex:1;margin-left:220px;padding:80px 30px 30px;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.card h4{color:#2f8f3f;margin-bottom:10px;}
.card p{font-size:22px;font-weight:bold;}
table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);}
th,td{padding:12px;border-bottom:1px solid #ddd;}
th{background:#2f8f3f;color:#fff;}
.section{margin-top:40px;}
.table-box{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
</style>
</head>

<body>

<div class="topbar">
    <h3><i class="fas fa-chart-line"></i> Finance Dashboard</h3>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="container">

<div class="sidebar">
    <a href="finance_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="finance_projects.php"><i class="fas fa-folder-open"></i> Projects</a>
    <a href="finance_payments.php"><i class="fas fa-hand-holding-dollar"></i> Revenue</a>
    <a href="finance_expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenditure</a>
    <a href="finance_settings.php"><i class="fas fa-cog"></i> Settings</a>
</div>

<div class="main">

<h2><i class="fas fa-wallet"></i> Financial Overview</h2>

<div class="cards">
    <div class="card">
        <h4><i class="fas fa-hand-holding-dollar"></i> Payments Received</h4>
        <p>$<?= number_format($totalPayments,2) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-balance-scale"></i> Outstanding Balance</h4>
        <p>$<?= number_format($outstandingBalance,2) ?></p>
    </div>
</div>

<!-- ===== PROJECT FINANCIAL SUMMARY (ADMIN-IDENTICAL) ===== -->
<div class="section">
<h4><i class="fas fa-chart-line"></i> Project Financial Summary</h4>

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
<option value="<?= $p['id'] ?>" <?= (!empty($_GET['project_id']) && $_GET['project_id']==$p['id'])?'selected':'' ?>>
<?= htmlspecialchars($p['project_name']) ?>
</option>
<?php endwhile; ?>
</select>
</form>

<?php if (!empty($selectedSummary)): ?>
<?php
$projectBudget = $selectedSummary['project_budget'] ?? 0;
$cashLeft = $projectBudget - $projectSpent;
?>
<div class="table-box">
<table>
<tr>
    <td>Project</td>
    <td>
        <strong><?= htmlspecialchars($selectedSummary['project_name']) ?></strong>
        <span style="color:#2f8f3f;font-weight:bold;">
            (<?= htmlspecialchars($selectedSummary['project_type'] ?? 'N/A') ?>)
        </span>
    </td>
</tr>
<tr><td>Budget Amount</td><td>$<?= number_format($projectBudget,2) ?></td></tr>
<tr><td>Total Paid</td><td>$<?= number_format($selectedSummary['total_paid'] ?? 0,2) ?></td></tr>
<tr><td>Total Spent</td><td>$<?= number_format($projectSpent,2) ?></td></tr>
<tr>
    <td>Cash Left</td>
    <td style="color:<?= $cashLeft < 0 ? '#d9534f' : '#2f8f3f' ?>">
        $<?= number_format($cashLeft,2) ?>
    </td>
</tr>
<tr><td>Client Owes</td><td>$<?= number_format($selectedSummary['client_owes'] ?? 0,2) ?></td></tr>
</table>
</div>
<?php endif; ?>
</div>

<h2><i class="fas fa-receipt"></i> Recent Payments</h2>
<table>
<tr>
    <th>ID</th>
    <th>Project</th>
    <th>Type</th>
    <th>Amount</th>
    <th>Date</th>
</tr>
<?php if ($recentPayments && $recentPayments->num_rows > 0): ?>
<?php while ($pay = $recentPayments->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($pay['project_code']) ?></td>
    <td><?= htmlspecialchars($pay['project_name']) ?></td>
    <td><?= htmlspecialchars($pay['project_type']) ?></td>
    <td>$<?= number_format($pay['amount'],2) ?></td>
    <td><?= htmlspecialchars($pay['payment_date']) ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5" style="text-align:center;">No payments found</td></tr>
<?php endif; ?>
</table>

</div>
</div>
</body>
</html>
