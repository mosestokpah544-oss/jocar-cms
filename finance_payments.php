<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    header("Location: login.php");
    exit;
}

// Include the central summary file
require_once "project_summary.php";

/* ===================== FINANCE DATA ===================== */

// Get all projects
$projectsQuery = $conn->query("SELECT id FROM projects");
$projectTotal = 0;
$paidTotal = 0;
$paymentCount = 0;

while ($proj = $projectsQuery->fetch_assoc()) {
    $summary = getProjectSummary($conn, $proj['id']);

    $projectTotal += $summary['project_budget'] ?? 0;
    $paidTotal    += $summary['total_paid'] ?? 0;

    // Count payments per project
    $paymentCountQuery = $conn->query("
        SELECT COUNT(*) AS total
        FROM project_payments
        WHERE project_id = {$proj['id']}
    ");
    $paymentCount += $paymentCountQuery->fetch_assoc()['total'] ?? 0;
}

// Outstanding Balance
$outstanding = $projectTotal - $paidTotal;

// Recent Payments (latest 5)
$recentPayments = $conn->query("
    SELECT 
        pp.*,
        p.project_name,
        p.client_name,
        p.project_code,
        u.full_name,
        CONCAT('RECT-', LPAD(pp.id, 6, '0'), '-', p.project_code) AS full_receipt_number
    FROM project_payments pp
    JOIN projects p ON pp.project_id = p.id
    JOIN users u ON pp.entered_by = u.id
    ORDER BY pp.created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FinanceManager Payments</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
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
    padding-top: 60px;
    position: fixed;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
}

.sidebar a {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    font-weight: 400;
}

.sidebar a i {
    margin-right: 10px;
}

.sidebar a:hover {
    background-color: #2f8f3f;
}

/* Main Content */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 220px;
}

.main h3 {
    margin-bottom: 20px;
    color: #333;
}

.main h3 i, .section h4 i {
    margin-right: 8px;
}

/* Cards */
.cards {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
}
.card {
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.card h4 { color:#2f8f3f; margin-bottom:10px; }
.card p { font-size:22px; font-weight:bold; }

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
</style>
</head>

<body>

<!-- TOP BAR -->
<div class="topbar">
    <h2><i class="fas fa-cash-register"></i> Construction Management System</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<!-- SIDEBAR -->
<div class="sidebar">
        <a href="finance_dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="finance_projects.php"><i class="fas fa-folder-open"></i> Projects</a>
        <a href="finance_payments.php"><i class="fas fa-hand-holding-dollar"></i> Revenue</a>
        <a href="finance_expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenditure</a>
        <a href="finance_settings.php"><i class="fas fa-cog"></i> Settings</a>
</div>

<!-- MAIN CONTENT -->
<div class="main">
<h3><i class="fas fa-money-check-dollar"></i> Payments Management (Finance)</h3>

<!-- SUMMARY CARDS -->
<div class="cards">
    <div class="card">
        <h4><i class="fas fa-chart-line"></i> Total Project Value</h4>
        <p>$<?= number_format($projectTotal) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-hand-holding-dollar"></i> Total Amount Paid</h4>
        <p>$<?= number_format($paidTotal) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-balance-scale"></i> Outstanding Balance</h4>
        <p>$<?= number_format($outstanding) ?></p>
    </div>
    <div class="card">
        <h4><i class="fas fa-receipt"></i> Total Payments</h4>
        <p><?= $paymentCount ?></p>
    </div>
</div>

<button onclick="togglePaymentForm()" style="margin-bottom:20px;">
    <i class="fas fa-plus-circle"></i> Add Payment
</button>


<!-- Add Payment Section -->
<div class="section">
<h4><i class="fas fa-plus-square"></i> Add Payment</h4>

<div class="table-box" id="paymentFormBox" style="display:none;">
<form method="POST" action="save_payment.php">

<!-- Project Selector -->
<select name="project_id" required onchange="fetchProjectDetails(this.value)">
<option value="">-- Select Project --</option>
<?php
$projects = mysqli_query($conn,"
    SELECT id, project_name, client_name
    FROM projects
    WHERE is_closed = 0
");
while ($p = mysqli_fetch_assoc($projects)) {
    echo "<option value='{$p['id']}'>" .
         htmlspecialchars($p['project_name']) .
         " (" . htmlspecialchars($p['client_name']) . ")</option>";
}
?>
</select>

<!-- Project Details Table -->
<table style="margin-top:15px;">
<tr>
    <th>Project Name</th>
    <td id="project_name">—</td>
</tr>
<tr>
    <th>Client Name</th>
    <td id="client_name">—</td>
</tr>
<tr>
    <th>Total Project Amount</th>
    <td id="total_amount">—</td>
</tr>
<tr>
    <th>Amount Paid</th>
    <td id="amount_paid">—</td>
</tr>
<tr>
    <th>Remaining Balance</th>
    <td id="remaining_balance">—</td>
</tr>
</table>

<!-- Hidden values (optional, safe to keep) -->
<input type="hidden" name="total_project_amount" id="total_amount_input">
<input type="hidden" name="amount_paid_so_far" id="amount_paid_input">
<input type="hidden" name="remaining_balance" id="remaining_balance_input">

<hr style="margin:20px 0;">

<!-- Only editable field -->
<input type="number" name="amount" placeholder="Payment Amount" required>

<select name="payment_method" required>
<option value="">-- Payment Method --</option>
<option>Cash</option>
<option>Bank Transfer</option>
<option>Mobile Money</option>
<option>Check</option>
</select>

<input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">

<button type="submit"><i class="fas fa-save"></i> Save Payment</button>

</form>
</div>
</div>

<div class="section">
<h4><i class="fas fa-history"></i> Payment History</h4>
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
$userId = $_SESSION['user_id'];

$payments = mysqli_query($conn,"
SELECT 
    pp.*,
    p.project_name,
    p.client_name,
    p.project_code,
    u.full_name,
    CONCAT(
        'RECT-',
        LPAD(pp.id, 6, '0'),
        '-',
        p.project_code
    ) AS full_receipt_number
FROM project_payments pp
JOIN projects p ON pp.project_id = p.id
JOIN users u ON pp.entered_by = u.id
WHERE pp.entered_by = $userId
ORDER BY pp.created_at DESC

");

while ($row = mysqli_fetch_assoc($payments)) {
?>
<tr>
<td><strong><?= $row['full_receipt_number'] ?></strong></td>
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
        <button type="submit"><i class="fas fa-print"></i> Print</button>
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

    fetch('get_project_details.php?project_id=' + projectId)
        .then(response => response.json())
        .then(data => {

            document.getElementById('project_name').innerText = data.project_name;
            document.getElementById('client_name').innerText = data.client_name;
            document.getElementById('total_amount').innerText = '$' + data.total_amount;
            document.getElementById('amount_paid').innerText = '$' + data.amount_paid;
            document.getElementById('remaining_balance').innerText = '$' + data.remaining_balance;

            // hidden inputs (if needed later)
            document.getElementById('total_amount_input').value = data.total_amount;
            document.getElementById('amount_paid_input').value = data.amount_paid;
            document.getElementById('remaining_balance_input').value = data.remaining_balance;
        });
}
</script>


<script>
function togglePaymentForm() {
    const form = document.getElementById('paymentFormBox');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
