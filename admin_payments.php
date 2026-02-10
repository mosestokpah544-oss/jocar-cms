<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

/* ===================== DATABASE ===================== */
$conn = mysqli_connect("localhost","root","","company_system");
if (!$conn) {
    die("Database connection failed");
}

/* ===================== SHARED SUMMARY ENGINE ===================== */
include("project_summary.php");



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


/* ===================== TOP CARD TOTALS (FIXED) ===================== */
$totalBudget   = 0;
$totalPaid     = 0;
$totalSpent    = 0;
$totalOwed     = 0;

$projects = mysqli_query($conn, "SELECT id FROM projects");

while ($p = mysqli_fetch_assoc($projects)) {
    $summary = getProjectSummary($conn, (int)$p['id']);
    if (!$summary) continue;

   $totalBudget += $summary['project_budget'];
   $totalPaid   += $summary['total_paid'];
   $totalSpent  += $summary['total_project_out'];
   $totalOwed   += $summary['client_owes'];

}

$paymentCount = mysqli_num_rows(
    mysqli_query($conn,"SELECT id FROM project_payments")
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Admin Payments</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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

/* Sections */
.section { margin-top:40px; }
.section h4 { margin-bottom:15px; color:#2f8f3f; }

/* Forms */
input, select, button {
    width:100%;
    padding:10px;
    margin-bottom:15px;
}
button {
    background:#2f8f3f;
    color:#fff;
    border:none;
    cursor:pointer;
}
button:hover { background:#256d31; }

/* Tables */
.table-box {
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
table { width:100%; border-collapse:collapse; }
th, td {
    padding:10px;
    border-bottom:1px solid #ddd;
    font-size:14px;
}
th { background:#f0f0f0; text-align:left; }
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
</head>

<body>

<!-- TOP BAR -->
<div class="topbar">
   <h2>
    <i class="fas fa-bars menu-btn" onclick="toggleSidebar()"></i>
    Jocar CMS — <?= htmlspecialchars($adminName) ?>
</h2>

    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_office.php"><i class="fas fa-briefcase"></i> Office</a>
    <a href="project.php"><i class="fas fa-diagram-project"></i> Projects</a>
    <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="admin_expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
    <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
    <a href="admin_settings.php"><i class="fas fa-gear"></i> Settings</a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
<h3><i class="fas fa-credit-card"></i> Payments Management</h3>

<!-- SUMMARY CARDS -->
<div class="cards">
    <div class="card">
        <h4><i class="fas fa-chart-line"></i> Project Value</h4>
        <p>$<?= number_format($totalBudget,2) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-money-bill-wave"></i> Amount Paid</h4>
        <p>$<?= number_format($totalPaid,2) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-wallet"></i> Outstanding Balabce</h4>
        <p>$<?= number_format($totalOwed,2) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-receipt"></i> Total Payments</h4>
        <p><?= $paymentCount ?></p>
    </div>
</div>

<div class="section">
<h4><i class="fas fa-clock-rotate-left"></i> Payment History</h4>
<div class="table-box">
<table>
<tr>
<th>Receipt</th>
<th>Project</th>
<th>Client</th>
<th>Amount</th>
<th>Method</th>
<th>Date</th>
<th>Entered By</th>
<th>Timestamp</th>
<th>Print</th>
</tr>

<?php
$payments = mysqli_query($conn,"
SELECT pp.*, p.project_name, p.client_name, u.full_name
FROM project_payments pp
JOIN projects p ON pp.project_id = p.id
JOIN users u ON pp.entered_by = u.id
ORDER BY pp.created_at DESC
");

while ($row = mysqli_fetch_assoc($payments)) {
?>
<tr>
<td><?= $row['receipt_number'] ?></td>
<td><?= $row['project_name'] ?></td>
<td><?= $row['client_name'] ?></td>
<td>$<?= number_format($row['amount']) ?></td>
<td><?= $row['payment_method'] ?></td>
<td><?= $row['payment_date'] ?></td>
<td><?= $row['full_name'] ?></td>
<td><?= $row['created_at'] ?></td>
<td>
    <form method="GET" action="print_receipt.php" target="_blank">
        <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
        <button type="submit">
    <i class="fas fa-print"></i> Print
</button>

    </form>
</td>
</tr>
<?php } ?>
</table>
</div>
</div>

</div>
</div>

<script>
function fetchProjectDetails(projectId) {
    if (!projectId) return;

    fetch('project_summary_api.php?project_id=' + projectId)
        .then(response => response.json())
        .then(data => {
            if (!data.project_name) return;

            document.getElementById('project_name').value      = data.project_name;
            document.getElementById('total_amount').value     = data.budget;
            document.getElementById('amount_paid').value      = data.total_paid;
            document.getElementById('remaining_balance').value= data.client_owes;
        });
}
</script>

<script>
function togglePaymentForm() {
    const form = document.getElementById('paymentFormBox');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>



<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
</script>



</body>
</html>
