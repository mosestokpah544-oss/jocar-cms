<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

include("db.php");
include("project_summary.php");

/* ===== ADMIN NAME ===== */
$adminName = 'Admin';

if (isset($_SESSION['user_id'])) {
    $adminQuery = $conn->query("
        SELECT full_name
        FROM users
        WHERE id = {$_SESSION['user_id']}
        LIMIT 1
    ");

    if ($adminQuery && $adminQuery->num_rows > 0) {
        $admin = $adminQuery->fetch_assoc();
        $adminName = $admin['full_name'];
    }
}

/* ===== TOTAL PROJECTS (ALL TIME) ===== */
$totalProjects = $conn->query("
    SELECT COUNT(*) AS total 
    FROM projects
")->fetch_assoc()['total'] ?? 0;

/* ===== GLOBAL FINANCIAL TOTALS (ALL PROJECTS) ===== */
$totalIncome = 0;
$totalNet    = 0;

/* ⚠️ DO NOT FILTER CLOSED PROJECTS HERE */
$projects = $conn->query("
    SELECT id 
    FROM projects
");

while ($p = $projects->fetch_assoc()) {
    $s = getProjectSummary($conn, $p['id']);
    if (!$s) continue;

    $totalIncome += (float)($s['total_paid'] ?? 0);
    $totalNet    += (float)($s['gross_profit'] ?? 0);
}

/* ===== TOTAL EXPENSES (ALL TIME, PAID ONLY) ===== */

// Paid Expenses
$paidExpensesAmount = $conn->query("
    SELECT COALESCE(SUM(grand_total),0) AS total 
    FROM expenses
    WHERE payment_status = 'Paid'
")->fetch_assoc()['total'] ?? 0;

// Paid Purchase Requests
$paidRequestsAmount = $conn->query("
    SELECT COALESCE(SUM(pri.quantity * pri.unit_cost),0) AS total
    FROM purchase_requests pr
    JOIN purchase_request_items pri ON pr.id = pri.request_id
    WHERE pr.payment_status = 'Paid'
")->fetch_assoc()['total'] ?? 0;

$totalSpent = $paidExpensesAmount + $paidRequestsAmount;

/* ===== OFFICE COSTS ===== */
$officeSummary = getOfficeSummary($conn);
$officeSpent   = (float)($officeSummary['office_spent'] ?? 0);

/* ===== FINAL NET PROFIT ===== */
$totalNet = $totalNet - $officeSpent;

/* ===== PROJECT STATUS (ACTIVE ONLY – THIS IS CORRECT) ===== */
$ongoing = $conn->query("
    SELECT COUNT(*) AS c 
    FROM projects 
    WHERE status='Ongoing'
    AND is_closed = 0
")->fetch_assoc()['c'] ?? 0;

$completed = $conn->query("
    SELECT COUNT(*) AS c 
    FROM projects 
    WHERE status='Completed'
    AND is_closed = 0
")->fetch_assoc()['c'] ?? 0;

$onHold = $conn->query("
    SELECT COUNT(*) AS c 
    FROM projects 
    WHERE status='On Hol'
    AND is_closed = 0
")->fetch_assoc()['c'] ?? 0;

/* ===== RECENT PROJECTS (ACTIVE ONLY) ===== */
$recentProjects = $conn->query("
    SELECT project_name, location, status
    FROM projects
    WHERE is_closed = 0
    ORDER BY created_at DESC
    LIMIT 5
");

/* ===== RECENT EXPENSES ===== */
$recentExpenses = $conn->query("
    SELECT expense_title, grand_total
    FROM expenses
    WHERE status IN ('Approved','Pending_Finance','Pending')
    ORDER BY created_at DESC
    LIMIT 5
");

/* ===== ALERTS (ACTIVE PROJECTS ONLY) ===== */
$alerts = [];

$alertProjects = $conn->query("
    SELECT id 
    FROM projects
    WHERE is_closed = 0
");

while ($p = $alertProjects->fetch_assoc()) {
    $summary = getProjectSummary($conn, $p['id']);
    if ($summary && $summary['gross_profit'] < 0) {
        $alerts[] = "⚠ Project “{$summary['project_name']}” is operating at a loss";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Dashboard</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background-color: #f4f4f4;
}

/* Top Bar */
.topbar {
   background-color: #2f8f3f;
    color: white;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;

    position: fixed;   /* fix the topbar */
    top: 0;            /* stick to top */
    left: 0;
    width: 100%;
    z-index: 1000;     /* stays above content */
}

.topbar h2 {
    font-size: 20px;
}

.logout {
    background-color: white;
    color: #2f8f3f;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}

/* Layout */
.container {
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 200px;
    background-color: #256d31;
    min-height: 100vh;
    padding-top: 60px; /* <-- push the content down from the top */
    position: fixed;
    display: flex;
    flex-direction: column;
    justify-content: flex-start; /* keep links stacked from top, but padding pushes them down */
    align-items: center;          /* horizontally center links */
}

.sidebar a {
    display: block;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
}

.sidebar a:hover {
    background-color: #2f8f3f;
}

/* Main Content */
.main {
    flex: 1;
    padding: 30px;
      margin-left: 220px; /* match sidebar width */
    padding: 30px;
}

.main h3 {
    margin-bottom: 20px;
    color: #333;
}

/* Cards */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.card {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.card h4 {
    color: #2f8f3f;
    margin-bottom: 10px;
}

.card p {
    font-size: 22px;
    font-weight: bold;
    color: #333;
}

/* ===== NEW SECTIONS (ADDED, NOT CHANGED) ===== */

.section {
    margin-top: 40px;
}

.section h4 {
    margin-bottom: 15px;
    color: #2f8f3f;
}

/* Tables */
.table-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th, table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
}

table th {
    background-color: #f0f0f0;
    text-align: left;
}

/* Alerts */
.alert {
    background-color: #fff3cd;
    border-left: 5px solid #ff9800;
    padding: 15px;
    margin-bottom: 10px;
    font-size: 14px;
}
/* ================= MOBILE RESPONSIVE ================= */
.menu-btn {
    display: none;
    cursor: pointer;
    margin-right: 10px;
}

/* Mobile view */
@media (max-width: 768px) {

    .menu-btn {
        display: inline-block;
    }

    /* Sidebar hidden by default */
    .sidebar {
        position: fixed;
        left: -220px;
        top: 60px;
        height: 100%;
        transition: left 0.3s ease;
        z-index: 2000;
    }

    .sidebar.active {
        left: 0;
    }

    /* Main content full width */
    .main {
        margin-left: 0;
        padding: 20px;
    }

    /* Cards stack vertically */
    .cards {
        grid-template-columns: 1fr;
    }

    /* Tables scroll horizontally */
    .table-box {
        overflow-x: auto;
    }

    table {
        min-width: 600px;
    }

    /* Smaller topbar text */
    .topbar h2 {
        font-size: 16px;
    }
}
@media (max-width: 768px) {
    .card {
        text-align: center;
    }
}

</style>

<?php /* same CSS you provided */ ?>
</head>

<body>

<div class="topbar">
    <h2>
    <i class="fas fa-bars menu-btn" onclick="toggleSidebar()"></i>
    Jocar CMS — <?= htmlspecialchars($adminName) ?>
</h2>

    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_office.php"><i class="fas fa-briefcase"></i> Office</a>
    <a href="project.php"><i class="fas fa-diagram-project"></i> Projects</a>
    <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="admin_expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
    <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
    <a href="admin_settings.php"><i class="fas fa-gear"></i> Settings</a>
</div>

<div class="main">
<h3><i class="fas fa-chart-line"></i> Admin Dashboard Overview</h3>

<!-- ===== MAIN CARDS ===== -->
<div class="cards">
   <div class="card">
    <h4><i class="fas fa-arrow-down"></i> Total Income (Paid)</h4>
    <p>$<?= number_format($totalIncome,2) ?></p>
</div>

<div class="card">
    <h4><i class="fas fa-arrow-up"></i> Total Expenses (Paid)</h4>
    <p>$<?= number_format($totalSpent,2) ?></p>
</div>

<div class="card">
    <h4><i class="fas fa-wallet"></i> Net Profit</h4>
    <p>$<?= number_format($totalNet,2) ?></p>
</div>
</div>

<!-- ===== PROJECT STATUS ===== -->
<div class="section">
<h4><i class="fas fa-tasks"></i> Project Status Summary</h4>
<div class="cards">
    <div class="card"><h4><i class="fas fa-spinner"></i> Ongoing</h4><p><?= $ongoing ?></p></div>
    <div class="card"><h4><i class="fas fa-check-circle"></i> Completed</h4><p><?= $completed ?></p></div>
    <div class="card"><h4><i class="fas fa-pause-circle"></i> On Hold</h4><p><?= $onHold ?></p></div>
</div>
</div>

<!-- ===== RECENT PROJECTS ===== -->
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

<!-- ===== RECENT EXPENSES ===== -->
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

<!-- ===== ALERTS ===== -->
<div class="section">
<h4><i class="fas fa-bell"></i> Alerts & Notifications</h4>
<?php if (empty($alerts)): ?>
<div class="alert"><i class="fas fa-check-circle"></i> No financial alerts</div>
<?php else: ?>
<?php foreach ($alerts as $a): ?>
<div class="alert"><i class="fas fa-triangle-exclamation"></i> <?= $a ?></div>
<?php endforeach; ?>
<?php endif; ?>
</div>

</div>
</div>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
</script>



</body>
</html>
