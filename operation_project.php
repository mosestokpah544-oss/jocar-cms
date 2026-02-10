<?php
session_start();
include "db.php";

/* ===== PROTECT PAGE ===== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    header("Location: login.html");
    exit;
}




/* ===== OPERATIONS APPROVE EXPENSE ===== */
if (isset($_POST['approve_expense_id'])) {
    $expenseId = (int)$_POST['approve_expense_id'];

    $conn->query("
        UPDATE expenses
        SET 
            finance_approved = 1,
            status = 'Approved'
        WHERE id = $expenseId
    ");

    header("Location: operation_project.php");
    exit;
}



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

/* ===== FETCH ALL PROJECTS ===== */
$projects = $conn->query("
    SELECT p.*, u.full_name AS manager_name
    FROM projects p
    JOIN users u ON p.project_manager_id = u.id
    ORDER BY p.created_at DESC
");
/* ===== FETCH PENDING PROJECT MANAGER EXPENSES ===== */
$pendingExpenses = $conn->query("
    SELECT e.*, p.project_name, u.full_name AS submitted_by
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    JOIN users u ON e.created_by = u.id
    WHERE e.status = 'Pending'
      AND e.created_by_role = 'ProjectManager'
    ORDER BY e.created_at DESC
");
/* ===== FETCH ALL PROJECT EXPENSES ===== */
$allExpenses = $conn->query("
    SELECT 
        e.*, 
        p.project_name,
        u.full_name AS submitted_by
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    JOIN users u ON e.created_by = u.id
    ORDER BY e.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Projects</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* ===== SAME STYLE AS OPERATIONS DASHBOARD ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f4f4;}

.topbar{
    background:#2f8f3f;
    color:#fff;
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    position:fixed;
    top:0;
    width:100%;
    z-index:1000;
}
.logout{
    background:#fff;
    color:#2f8f3f;
    padding:8px 15px;
    border-radius:5px;
    text-decoration:none;
    font-weight:bold;
}
.logout i{margin-right:5px;}

.container{display:flex;}

.sidebar{
    width:200px;
    background:#256d31;
    min-height:100vh;
    padding-top:60px;
    position:fixed;
    display:flex;
    flex-direction:column;
    align-items:center;
}
.sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:15px 20px;
    color:#fff;
    text-decoration:none;
    font-size:15px;
}
.sidebar a:hover{background:#2f8f3f;}

.main{
    flex:1;
    margin-left:220px;
    padding:30px;
    padding-top:90px;
}

h2{margin-bottom:20px;color:#333;}

/* ===== TABLE ===== */
table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}
th,td{
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
    font-size:14px;
}
th{
    background:#f0f0f0;
}
.status{font-weight:bold;}
.status.Pending{color:orange;}
.status.Ongoing{color:green;}
.status.Completed{color:blue;}
.status.OnHold{color:gray;}

.view-btn{
    background:#007bff;
    color:white;
    padding:6px 10px;
    border-radius:5px;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="topbar">
    <h3><i class="fas fa-diagram-project"></i> Projects — <?= htmlspecialchars($opsName) ?></h3>
    <a href="logout.php" class="logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
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


<h2 style="margin-top:40px;">
    <i class="fas fa-receipt"></i> Pending Project Expenses
</h2>

<table>
<tr>
    <th>ID</th>
    <th>Project</th>
    <th>Title</th>
    <th>Amount</th>
    <th>Submitted By</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php if ($pendingExpenses->num_rows > 0): ?>
<?php while ($ex = $pendingExpenses->fetch_assoc()): ?>
<tr>
    <td>#<?= $ex['id'] ?></td>
    <td><?= htmlspecialchars($ex['project_name']) ?></td>
    <td><?= htmlspecialchars($ex['expense_title']) ?></td>
    <td>$<?= number_format($ex['grand_total'], 2) ?></td>
    <td><?= htmlspecialchars($ex['submitted_by']) ?></td>
    <td><?= date('Y-m-d', strtotime($ex['created_at'])) ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="approve_expense_id" value="<?= $ex['id'] ?>">
            <button type="submit" class="view-btn" style="background:#28a745;">
                <i class="fas fa-check"></i> Approve
            </button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="7" style="text-align:center;color:gray;">
        No pending expenses
    </td>
</tr>
<?php endif; ?>
</table>



<h2 style="margin-top:50px;">
    <i class="fas fa-file-invoice-dollar"></i> All Project Expenses
</h2>

<table>
<tr>
    <th>ID</th>
    <th>Project</th>
    <th>Title</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Submitted By</th>
    <th>Date</th>
    <th>View</th>
</tr>

<?php if ($allExpenses->num_rows > 0): ?>
<?php while ($ex = $allExpenses->fetch_assoc()): ?>
<tr>
    <td>#<?= $ex['id'] ?></td>
    <td><?= htmlspecialchars($ex['project_name']) ?></td>
    <td><?= htmlspecialchars($ex['expense_title']) ?></td>
    <td>$<?= number_format($ex['grand_total'], 2) ?></td>
    <td class="status <?= $ex['status'] ?>">
        <?= $ex['status'] ?>
    </td>
    <td><?= htmlspecialchars($ex['submitted_by']) ?></td>
    <td><?= date('Y-m-d', strtotime($ex['created_at'])) ?></td>
    <td>
        <a href="ops_view_expense.php?id=<?= $ex['id'] ?>" class="view-btn">
            <i class="fas fa-eye"></i> View
        </a>
    </td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="8" style="text-align:center;color:gray;">
        No expenses found
    </td>
</tr>
<?php endif; ?>
</table>


<h2><i class="fas fa-list"></i> All Projects</h2>

<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Client</th>
    <th>Location</th>
    <th>Budget</th>
    <th>Type</th>
    <th>Start</th>
    <th>End</th>
    <th>Status</th>
    <th>Manager</th>
</tr>

<?php if ($projects->num_rows > 0): ?>
<?php while ($row = $projects->fetch_assoc()): ?>

<?php
$year = date('Y', strtotime($row['created_at']));
$typeCode = ($row['project_type'] === 'Construction') ? 'CONT' : 'RENG';
$projectCode = str_pad($row['id'], 3, '0', STR_PAD_LEFT) . $typeCode . $year;
?>

<tr>
    <td><strong><?= $projectCode ?></strong></td>
    <td><?= htmlspecialchars($row['project_name']) ?></td>
    <td><?= htmlspecialchars($row['client_name']) ?></td>
    <td><?= htmlspecialchars($row['location']) ?></td>
    <td>$<?= number_format($row['budget'], 2) ?></td>
    <td><?= htmlspecialchars($row['project_type']) ?></td>
    <td><?= $row['start_date'] ?></td>
    <td><?= $row['end_date'] ?></td>
    <td class="status <?= str_replace(' ', '', $row['status']) ?>">
        <?= $row['status'] ?>
    </td>
    <td><?= htmlspecialchars($row['manager_name']) ?></td>
    <td>
    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="11" style="text-align:center;color:gray;">No projects found</td>
</tr>
<?php endif; ?>
</table>

</div>
</div>

</body>
</html>
