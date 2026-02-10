<?php
session_start();
include "db.php";

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    header("Location: login.php");
    exit;
}

// Fetch all projects (Finance = read-only)
$projects = $conn->query("
    SELECT p.*, u.full_name AS manager_name
    FROM projects p
    JOIN users u ON p.project_manager_id = u.id
    ORDER BY p.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Finance Project Overview</title>

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

.main h3 i {
    margin-right: 8px;
}

/* Table */
table {
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:6px;
    overflow:hidden;
}
th, td {
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}
th {
    background-color:#2f8f3f;
    color:white;
}
tr:hover {
    background:#f1f1f1;
}
</style>
</head>

<body>

<div class="topbar">
    <h2><i class="fas fa-folder-open"></i> Finance Project Overview</h2>
    <a href="/Jocab/logout.php" class="logout">Logout</a>
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
<h3><i class="fas fa-project-diagram"></i> All Projects (Financial View)</h3>

<table>
<tr>
    
    <th>ID</th>
    <th>Project</th>
    <th>Type</th>
    <th>Client</th>
    <th>Location</th>
    <th>Budget</th>
    <th>Start</th>
    <th>End</th>
    <th>Status</th>
    <th>Manager</th>
</tr>

<?php while($row = $projects->fetch_assoc()) {

$statusColor = match($row['status']) {
    'Pending'   => 'orange',
    'Ongoing'   => 'green',
    'On Hold'   => 'gray',
    'Completed' => 'blue',
    default     => 'black'
};
?>
<tr>
   
    <td><?php echo htmlspecialchars($row['project_code']); ?></td>
    <td><?php echo htmlspecialchars($row['project_name']); ?></td>
    <td><?php echo htmlspecialchars($row['project_type']); ?></td>
    <td><?php echo htmlspecialchars($row['client_name']); ?></td>
    <td><?php echo htmlspecialchars($row['location']); ?></td>
    <td>$<?php echo number_format($row['budget'],2); ?></td>
    <td><?php echo $row['start_date']; ?></td>
    <td><?php echo $row['end_date']; ?></td>
    <td style="color:<?php echo $statusColor; ?>; font-weight:bold;">
        <?php echo $row['status']; ?>
    </td>
    <td><?php echo htmlspecialchars($row['manager_name']); ?></td>
</tr>

<?php } ?>

</table>
</div>

</div>

</body>
</html>
