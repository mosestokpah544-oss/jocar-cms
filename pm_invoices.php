<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    die("Access denied");
}

$conn = mysqli_connect("localhost","root","","company_system");
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Invoices</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* SAME STYLES AS ADMIN */
* { margin: 0;
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
.logout i { margin-right: 5px; }

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
    align-items: center;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    font-weight: 400;
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
.table-box { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border-bottom:1px solid #ddd; }
th { background:#f0f0f0; }
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
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
    <h2><i class="fas fa-wallet"></i> Construction Management System</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<div class="sidebar">
        <a href="project_manager_dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="my_project.php"><i class="fas fa-folder-open"></i> Projects</a>
        <a href="pm_expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a>
        <a href="pm_invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a>
        <a href="pm_settings.php"><i class="fas fa-cog"></i> Settings</a>
</div>

<div class="main">
<h3><i class="fas fa-file-invoice"></i> My Approved Invoices</h3>

<div class="table-box">
<table>
<tr>
<th>Invoice #</th>
<th>Expense</th>
<th>Amount</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php
$sql = "
SELECT e.*
FROM expenses e
WHERE e.created_by = $user_id
AND
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
<td><?= $row['created_at'] ?></td>
<td>
<a href="view_invoice.php?id=<?= $row['id'] ?>" target="_blank" class="action-btn">
    <i class="fas fa-print"></i> Print
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
