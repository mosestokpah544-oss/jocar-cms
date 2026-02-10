<?php
session_start();
include "db.php";

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: login.html");
    exit;
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

/* =========================
   HANDLE ADD PROJECT (PM)
========================= */
if (isset($_POST['add_project'])) {
    $project_name = $_POST['project_name'];
    $client_name  = $_POST['client_name'];
    $location     = $_POST['location'];
    $budget       = $_POST['budget'];
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];

    // All PM projects go for Admin approval
    $status = "Pending Approval";

    $stmt = $conn->prepare("
        INSERT INTO projects 
        (project_name, client_name, location, budget, start_date, end_date, status, project_manager_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssdsssi",
        $project_name,
        $client_name,
        $location,
        $budget,
        $start_date,
        $end_date,
        $status,
        $user_id
    );
    $stmt->execute();
    $stmt->close();

    header("Location: my_project.php");
    exit;
}

/* =========================
   HANDLE PROJECT UPDATE
========================= */
if (isset($_POST['update_project'])) {
    $id           = intval($_POST['project_id']);
    $project_name = $_POST['project_name'];
    $client_name  = $_POST['client_name'];
    $location     = $_POST['location'];
    $budget       = $_POST['budget'];
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];
    $status       = $_POST['status'];

    if(empty($start_date)) $start_date = null;
    if(empty($end_date)) $end_date = null;

    $stmt = $conn->prepare("
        UPDATE projects 
        SET project_name=?, client_name=?, location=?, budget=?, start_date=?, end_date=?, status=?
        WHERE id=? AND project_manager_id=?
    ");
    $stmt->bind_param(
        "sssdsssii",
        $project_name,
        $client_name,
        $location,
        $budget,
        $start_date,
        $end_date,
        $status,
        $id,
        $user_id
    );
    $stmt->execute();
    $stmt->close();

    header("Location: my_project.php");
    exit;
}

/* =========================
   FETCH PROJECTS (PM ONLY)
========================= */
$projects = $conn->prepare("
    SELECT * FROM projects 
    WHERE project_manager_id=?
    ORDER BY created_at DESC
");
$projects->bind_param("i", $user_id);
$projects->execute();
$result = $projects->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Projects</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
* {  margin: 0;
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

table {
    width:100%; border-collapse:collapse;
    background:white;
}
th, td {
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}
th { background:#2f8f3f; color:white; }

.edit-btn {
    background:#ffc107; color:white;
    padding:6px 10px; border-radius:5px;
    text-decoration:none;
}
.edit-btn:hover { background:#e0a800; }

input, select {
    width:100%; padding:8px;
    margin-bottom:10px;
}
button {
    background:#2f8f3f; color:white;
    padding:8px 12px; border:none;
    border-radius:5px;
    cursor:pointer;
}
button i { margin-right: 5px; }

</style>
</head>

<body>

<div class="topbar">
    <h2><i class="fas fa-folder-open"></i> My Projects</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
<div class="sidebar">
        <a href="project_manager_dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="my_project.php"><i class="fas fa-folder-open"></i> Projects</a>
        <a href="pm_expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a>
        <a href="pm_add_site_supervisor.php"><i class="fas fa-user-plus"></i> Add Supervisor</a>
        <a href="pm_daily_reports.php"><i class="fas fa-file-alt"></i> Daily Reports</a>
        <a href="pm_settings.php"><i class="fas fa-cog"></i> Settings</a>
    </div>

<div class="main">

<!-- ADD PROJECT BUTTON -->
<button onclick="toggleForm()" style="margin-bottom:20px;">
    <i class="fas fa-plus"></i> Add Project
</button>

<!-- ADD PROJECT FORM (HIDDEN) -->
<div id="addForm" style="display:none; background:#fff; padding:20px; border-radius:8px; margin-bottom:30px;">
    <h3><i class="fas fa-plus-circle"></i> Add New Project</h3>
    <form method="POST">
        <label>Project Name</label>
        <input name="project_name" required>

        <label>Client Name</label>
        <input name="client_name" required>

        <label>Location</label>
        <input name="location" required>

        <label>Budget</label>
        <input type="number" step="0.01" name="budget" required>

        <label>Start Date</label>
        <input type="date" name="start_date" required>

        <label>End Date</label>
        <input type="date" name="end_date" required>

        <button name="add_project"><i class="fas fa-paper-plane"></i> Submit for Approval</button>
    </form>
</div>

<!-- PROJECTS TABLE -->
<table>
    <th>ID</th>
    <th>Project</th>
    <th>Type</th>
    <th>Client</th>
    <th>Location</th>
    <th>Budget</th>
    <th>Start Date</th>
    <th>End Date</th>
    <th>Status</th>

<?php while($row = $result->fetch_assoc()) { 
$statusColor = match($row['status']) {
    'Pending Approval' => 'orange',
    'Ongoing'          => 'green',
    'On Hold'          => 'gray',
    'Completed'        => 'blue',
    default            => 'black'
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
</tr>

<?php } ?>
</table>

</div>
</div>

<script>
function toggleForm(){
    var f = document.getElementById("addForm");
    f.style.display = (f.style.display === "none") ? "block" : "none";
}
</script>

</body>
</html>
