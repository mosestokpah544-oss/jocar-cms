<?php
session_start();
include "db.php";

// Protect page by role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}


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



$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

/* ===================== HANDLE DELETE ===================== */
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM projects WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: project.php");
    exit;
}



/* ===================== HANDLE CLOSE PROJECT ===================== */
if (isset($_GET['close_project'])) {
    $id = (int)$_GET['close_project'];

    $stmt = $conn->prepare("
        UPDATE projects
        SET is_closed = 1,
            closed_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Project has been closed successfully.";
    header("Location: project.php");
    exit;
}



/* ===================== HANDLE APPROVAL ===================== */
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE projects SET status='Ongoing' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: project.php");
    exit;
}

/* ===================== HANDLE REJECT ===================== */
if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE projects SET status='Rejected' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: project.php");
    exit;
}

/* ===================== HANDLE ADD PROJECT ===================== */
if (isset($_POST['add_project'])) {
    $project_type = $_POST['project_type'];
    $project_name = $_POST['project_name'];
    $client_name  = $_POST['client_name'];
    $location     = $_POST['location'];
    $budget       = $_POST['budget'];
    $start_date   = $_POST['start_date'];
    $end_date     = $_POST['end_date'];
    $status       = $_POST['status'];
    $manager_id   = $_POST['project_manager'];

    $stmt = $conn->prepare("
    INSERT INTO projects 
    (project_type, project_name, client_name, location, budget, start_date, end_date, status, project_manager_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssdsssi",
    $project_type,
    $project_name,
    $client_name,
    $location,
    $budget,
    $start_date,
    $end_date,
    $status,
    $manager_id
);

$stmt->execute();

// ✅ Get auto ID
$project_id = $stmt->insert_id;
$stmt->close();

// ✅ Generate permanent project code
$year = date('Y');
$typeCode = ($project_type === 'Construction') ? 'CONT' : 'RENG';
$project_code = str_pad($project_id, 3, '0', STR_PAD_LEFT) . $typeCode . $year;

// ✅ Save it
$update = $conn->prepare("UPDATE projects SET project_code=? WHERE id=?");
$update->bind_param("si", $project_code, $project_id);
$update->execute();
$update->close();

header("Location: project.php");
exit;
}

/* ===================== HANDLE OPS PROJECT REQUEST APPROVAL ===================== */

if (isset($_GET['approve_request'])) {
    $id = (int)$_GET['approve_request'];

    // 1. Set admin approval
    $conn->query("
        UPDATE purchase_requests
        SET admin_approved = 1
        WHERE id = $id
    ");

    // 2. Recalculate final approval
    if (isset($_GET['approve_request'])) {
    $id = (int)$_GET['approve_request'];

    // 1. Admin approves
    $conn->query("
        UPDATE purchase_requests
        SET admin_approved = 1,
            final_approved = 1,
            status = 'Approved'
        WHERE id = $id
    ");

    $_SESSION['message'] = "Request approved by Admin";
    header("Location: project.php");
    exit;
}


    $status = match ($final) {
    1 => 'Approved',
    2 => 'Rejected',
    default => 'Pending'
};

$conn->query("
    UPDATE purchase_requests
    SET final_approved = $final,
        status = '$status'
    WHERE id = $id
");

    $_SESSION['message'] = "Request approved by Admin";
    header("Location: project.php");
    exit;
}

/* ===================== HANDLE OPS PROJECT REQUEST REJECTION ===================== */

if (isset($_GET['reject_request'])) {
    $id = (int)$_GET['reject_request'];

    $conn->query("
        UPDATE purchase_requests
        SET admin_approved = 2
        WHERE id = $id
    ");

    $conn->query("
    UPDATE purchase_requests
    SET admin_approved = 2,
        final_approved = 2,
        status = 'Rejected'
    WHERE id = $id
");

    $_SESSION['message'] = "Request rejected by Admin";
    header("Location: project.php");
    exit;
}

/* ===================== FETCH ALL PROJECTS ===================== */
$projects = $conn->query("
    SELECT p.*, u.full_name as manager_name 
    FROM projects p 
    JOIN users u ON p.project_manager_id = u.id 
    ORDER BY 
        CASE 
            WHEN p.status IN ('Pending Approval','Pending') THEN 0 
            ELSE 1 
        END,
        p.created_at DESC
");

/* ===================== OPS PENDING PROJECT REQUESTS ===================== */

$pendingOpsProjects = $conn->query("
    SELECT pr.*, u.full_name AS ops_name
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE pr.request_type = 'Project'
      AND pr.admin_approved = 0
      AND pr.final_approved = 0
    ORDER BY pr.created_at DESC
");

/* ===================== ALL OPS PROJECT REQUESTS ===================== */
$allOpsProjects = $conn->query("
    SELECT 
        pr.*,
        u.full_name AS ops_name,
        COALESCE(SUM(pri.quantity),0) AS total_qty
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    LEFT JOIN purchase_request_items pri 
        ON pr.id = pri.request_id
    WHERE pr.request_type = 'Project'
      AND u.role = 'Operations'
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
");

/* ===================== FETCH MANAGERS ===================== */
$managers = $conn->query("SELECT id, full_name FROM users WHERE role='ProjectManager'");

/* ===================== DISPLAY SUCCESS MESSAGE ===================== */
if (!empty($_SESSION['message'])) {
    echo "<div style='background:#28a745;color:white;padding:10px 20px;margin:20px 0;border-radius:5px;'>"
        .htmlspecialchars($_SESSION['message'])."</div>";
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Projects</title>
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
    font-weight: 400;  /* normal weight, thinner than bold */
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

table {
    width:100%; border-collapse:collapse;
    background:white;
}
th, td {
    padding:12px;
    border-bottom:1px solid #ddd;
}
th { background:#2f8f3f; color:white; }

button, a {
    padding:6px 10px; border-radius:5px;
    text-decoration:none; font-weight:bold;
}
.add-btn { background:#2f8f3f; color:white; margin-bottom:15px; }
.edit { background:#ffc107; color:white; }
.delete { background:#dc3545; color:white; }
.approve { background:#28a745; color:white; }
.reject { background:#6c757d; color:white; }

form input, form select {
    width:100%; padding:8px;
    margin-bottom:10px;
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
</head>

<body>

<div class="topbar">
    <h2>
    <h2>
    <i class="fas fa-bars menu-btn" onclick="toggleSidebar()"></i>
    Jocar CMS — <?= htmlspecialchars($adminName) ?>
</h2>


    <a href="logout.php" class="logout">
<i class="fas fa-right-from-bracket"></i> Logout
</a>
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
<h3><i class="fas fa-diagram-project"></i> Projects</h3>

<!-- ADD PROJECT BUTTON -->
<button class="add-btn" onclick="toggleForm()">
<i class="fas fa-plus"></i> Add Project
</button>

<!-- ADD PROJECT FORM (HIDDEN) -->
<div id="addForm" style="display:none;">
<form method="POST">
    <label>Project Type</label>
<select name="project_type" required>
    <option value="">Select Project Type</option>
    <option value="Construction">Construction Project</option>
    <option value="Renewable Energy">Renewable Energy Project</option>
</select>
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

    <label>Status</label>
    <select name="status">
        <option value="Pending">Pending</option>
        <option value="Ongoing">Ongoing</option>
        <option value="On Hold">On Hold</option>
        <option value="Completed">Completed</option>
    </select>

    <label>Project Manager</label>
    <select name="project_manager">
        <?php while($m = $managers->fetch_assoc()) { ?>
            <option value="<?php echo $m['id']; ?>"><?php echo $m['full_name']; ?></option>
        <?php } ?>
    </select>

    <button name="add_project" class="add-btn">
<i class="fas fa-save"></i> Save Project
</button>
</form>
</div>

<br>

<h3 style="margin-top:30px;">
    <i class="fas fa-hourglass-half"></i> Pending Project Requests (From OPS)
</h3>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Supplier</th>
    <th>Qty</th>
    <th>Type</th>
    <th>OPS Name</th>
    <th>Action</th>
</tr>

<?php if ($pendingOpsProjects->num_rows == 0): ?>
<tr>
    <td colspan="7" style="text-align:center;color:gray;">
        No pending project requests from Operations
    </td>
</tr>
<?php else: ?>
<?php while ($p = $pendingOpsProjects->fetch_assoc()): ?>
<tr>
<td><strong><?= htmlspecialchars($p['request_code']) ?></strong></td>
<td><?= htmlspecialchars($p['request_title']) ?></td>
<td><?= htmlspecialchars($p['supplier_name']) ?></td>
<td><?= number_format($p['quantity'],2) ?></td>
<td><?= htmlspecialchars($p['request_type']) ?></td>
<td><?= htmlspecialchars($p['ops_name']) ?></td>
<td>
     <a href="view_request.php?id=<?= $p['id'] ?>" class="edit">
        <i class="fas fa-eye"></i> View
    </a>

    <!-- Approve button -->
    <a href="?approve_request=<?= $p['id'] ?>" class="approve"
   onclick="return confirm('Approve this project request?')">
    <i class="fas fa-check"></i> Approve
</a>

<a href="?reject_request=<?= $p['id'] ?>" class="reject"
   onclick="return confirm('Reject this project request?')">
    <i class="fas fa-times"></i> Reject
</a>

    <!-- Optional approve/reject buttons if you want to handle approval from admin -->
</td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</table>

<h3 style="margin-top:40px;">
    <i class="fas fa-list"></i> All Project Requests (From OPS)
</h3>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Supplier</th>
    <th>Qty</th>
    <th>Type</th>
    <th>Status</th>
    <th>OPS Name</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php if ($allOpsProjects->num_rows == 0): ?>
<tr>
    <td colspan="9" style="text-align:center;color:gray;">
        No project requests found
    </td>
</tr>
<?php else: ?>
<?php while ($r = $allOpsProjects->fetch_assoc()): ?>

<?php
// Set a color for status
$statusColor = match ($r['status']) {
    'Pending' => 'orange',
    'Approved' => 'green',
    'Rejected' => 'red',
    default => 'gray'
};
?>

<tr>
<td><strong><?= htmlspecialchars($r['request_code']) ?></strong></td>
<td><?= htmlspecialchars($r['request_title']) ?></td>
<td><?= htmlspecialchars($r['supplier_name']) ?></td>
<td><?= number_format($r['total_qty'],2) ?></td>
<td><?= htmlspecialchars($r['request_type']) ?></td>
<td style="color:<?= $statusColor ?>; font-weight:bold;"><?= htmlspecialchars($r['status']) ?></td>
<td><?= htmlspecialchars($r['ops_name']) ?></td>
<td><?= $r['created_at'] ?></td>
<td>
    <a href="view_request.php?id=<?= $r['id'] ?>" class="edit">
        <i class="fas fa-eye"></i> View
    </a>
</td>
</tr>

<?php endwhile; ?>
<?php endif; ?>
</table>


<!-- ================= ALL PROJECTS ================= -->
<h3>All Projects</h3>
<table>
<tr>
<th>ID</th>
<th>Project</th>
<th>Client</th>
<th>Location</th>
<th>Budget</th>
<th>Type</th>
<th>Start</th>
<th>End</th>
<th>Status</th>
<th>Manager</th>
<th>Actions</th>
</tr>

<?php if ($projects->num_rows > 0) { ?>
<?php while($row = $projects->fetch_assoc()) { ?>

<?php
$statusColor = 'black';

if ($row['status'] === 'Pending' || $row['status'] === 'Pending Approval') {
    $statusColor = 'orange';
} elseif ($row['status'] === 'Ongoing') {
    $statusColor = 'green';
} elseif ($row['status'] === 'On Hold') {
    $statusColor = 'gray';
} elseif ($row['status'] === 'Completed') {
    $statusColor = 'blue';
}


// ===== CUSTOM PROJECT ID (DISPLAY ONLY) =====
$year = date('Y', strtotime($row['created_at']));
$typeCode = ($row['project_type'] === 'Construction') ? 'CONT' : 'RENG';
$project_code = str_pad($row['id'], 3, '0', STR_PAD_LEFT) . $typeCode . $year;
?>

<tr>
<td><strong><?php echo $project_code; ?></strong></td>

<td>
    <a href="view_project.php?id=<?php echo $row['id']; ?>" style="color:#2f8f3f; font-weight:bold;">
        <?php echo $row['project_name']; ?>
    </a>
</td>

<td><?php echo $row['client_name']; ?></td>
<td><?php echo $row['location']; ?></td>
<td>$<?php echo number_format($row['budget'],2); ?></td>

<!-- ✅ FIXED TYPE COLUMN -->
<td><?php echo $row['project_type']; ?></td>

<td><?php echo $row['start_date']; ?></td>
<td><?php echo $row['end_date']; ?></td>

<td style="color:<?php echo $statusColor; ?>; font-weight:bold;">
    <?php echo $row['status']; ?>
</td>

<td><?php echo $row['manager_name']; ?></td>

<td>

<!-- APPROVE PROJECT -->
<?php if (in_array($row['status'], ['Pending','Pending Approval'])) { ?>
    <a class="approve" href="?approve=<?php echo $row['id']; ?>">
        <i class="fas fa-thumbs-up"></i> Approve
    </a>
<?php } ?>

<!-- CLOSE PROJECT (ADMIN ONLY, WHEN COMPLETED) -->
<?php if ($row['status'] === 'Completed' && !$row['is_closed']) { ?>
    <a class="approve"
       href="?close_project=<?= $row['id'] ?>"
       onclick="return confirm('This will permanently close this project. Continue?')">
       <i class="fas fa-lock"></i> Close Project
    </a>
<?php } ?>

<!-- EDIT PROJECT (ONLY IF NOT CLOSED) -->
<?php if (!$row['is_closed']) { ?>
    <a class="edit" href="edit_project.php?id=<?php echo $row['id']; ?>">
        <i class="fa-solid fa-pen-to-square"></i> Edit
    </a>
<?php } ?>

<!-- DELETE PROJECT -->
<a class="delete"
   href="?delete=<?php echo $row['id']; ?>"
   onclick="return confirm('Delete project?')">
    <i class="fa-solid fa-trash"></i> Delete
</a>

</td>

</tr>

<?php } ?>
<?php } else { ?>
<tr>
<td colspan="11" style="text-align:center; color:gray;">No projects found.</td>
</tr>
<?php } ?>


</table>

</div>
</div>

<script>
function toggleForm() {
    let form = document.getElementById("addForm");
    form.style.display = (form.style.display === "none") ? "block" : "none";
}
</script>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
</script>



</body>
</html>
