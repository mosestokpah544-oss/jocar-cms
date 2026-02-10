<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    die("Access denied");
}

$conn = mysqli_connect("localhost","root","","company_system");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Admin Invoices</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ===== SAME GLOBAL STYLES ===== */
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
.action-btn {
    display: inline-block;
    padding: 6px 14px;
    background-color: #2f8f3f;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: bold;
    transition: background 0.3s ease;
}

.action-btn:hover {
    background-color: #256d31;
}
</style>
</head>

<body>

<div class="topbar">
    <h2><i class="fas fa-building"></i> Construction Management System</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_office.php"><i class="fas fa-briefcase"></i> Office</a>
    <a href="project.php"><i class="fas fa-diagram-project"></i> Projects</a>
    <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="admin_expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
    <a href="admin_invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a>
    <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
    <a href="admin_settings.php"><i class="fas fa-gear"></i> Settings</a>
</div>

<div class="main">
<h3>Approved Expense Invoices</h3>

<div class="table-box">
<table>
<tr>
<th>Invoice #</th>
<th>Expense</th>
<th>Amount</th>
<th>Created By</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php
$sql = "
SELECT e.*, u.full_name
FROM expenses e
JOIN users u ON e.created_by = u.id
WHERE 
(
    e.admin_approved = 1 OR e.admin_approved = 'Approved'
)
AND
(
    e.finance_approved = 1 OR e.finance_approved = 'Approved'
)
ORDER BY e.created_at DESC
";
$res = mysqli_query($conn,$sql);

while ($row = mysqli_fetch_assoc($res)) {
?>
<tr>
<td>INV-<?= $row['id'] ?></td>
<td><?= htmlspecialchars($row['expense_title']) ?></td>
<td>$<?= number_format($row['grand_total'],2) ?></td>
<td><?= htmlspecialchars($row['full_name']) ?></td>
<td><?= $row['created_at'] ?></td>
<td>
<a href="view_invoice.php?id=<?= $row['id'] ?>" target="_blank" class="action-btn">
    <i class="fa-solid fa-print"></i> Print
</a>

</td>
</tr>
<?php } ?>
</table>
</div>

</div>
</div>
</body>
</html>
