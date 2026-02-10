<?php
session_start();

/* ===== ROLE CHECK (FIXED FOR MULTIPLE ROLES) ===== */
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin','Finance','Manager'])) {
    header("Location: login.html");
    exit;
}

include("db.php"); // Database connection


/* ===================== DASHBOARD COUNTS ===================== */

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



$selectedSummary = null;
$projectSpent = 0;   // ✅ DEFAULT VALUE (IMPORTANT)

if (!empty($_GET['project_id'])) {
    $projectId = (int) $_GET['project_id'];
    $selectedSummary = getProjectSummary($conn, $projectId);

    if (!empty($selectedSummary)) {
        $projectSpent = $selectedSummary['project_spent']
            ?? $selectedSummary['total_spent']
            ?? 0;
    }
}


$totalExpenses = 0;
$activeProjects = 0;

$projects = $conn->query("SELECT id, status FROM projects");

while ($p = $projects->fetch_assoc()) {
    if ($p['status'] === 'Ongoing') {
        $activeProjects++;
    }

    $summary = getProjectSummary($conn, (int)$p['id']);
    if (!$summary) continue;

    $totalExpenses += $summary['total_project_out'];
}

// Pending Expenses
$pendingQuery = $conn->query("SELECT COUNT(*) AS pending FROM expenses WHERE status='Pending'");
$pendingCount = $pendingQuery->fetch_assoc()['pending'] ?? 0;

// Approved Expenses
$approvedQuery = $conn->query("SELECT COUNT(*) AS approved FROM expenses WHERE status='Approved'");
$approvedCount = $approvedQuery->fetch_assoc()['approved'] ?? 0;

// Load Projects
$projects = $conn->query("SELECT id, project_name FROM projects");

// ===================== PM PENDING EXPENSES (FOR ADMIN) =====================
$pmPending = $conn->query("
    SELECT 
        e.*, 
        u.full_name,
        p.project_name
    FROM expenses e
    JOIN users u ON e.created_by = u.id
    LEFT JOIN projects p ON e.project_id = p.id
    WHERE e.status = 'Pending'
    AND u.role = 'ProjectManager'
    ORDER BY e.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Expenses</title>

<style>
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
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
.card { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h4 { color: #2f8f3f; margin-bottom: 10px; }
.card p { font-size: 22px; font-weight: bold; color: #333; }

.section { margin-top: 40px; }
.section h4 { margin-bottom: 15px; color: #2f8f3f; }

.table-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; }
table th, table td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 14px; }
table th { background-color: #f0f0f0; text-align: left; }

input, select { padding: 8px; width: 100%; margin-top: 5px; margin-bottom: 10px; }
.btn { padding: 10px 15px; border: none; cursor: pointer; border-radius: 5px; font-weight: bold; }
.btn-draft { background: #ccc; }
.btn-submit { background: #2f8f3f; color: white; }
.btn-add { background: #2f8f3f; color: white; margin-bottom: 15px; } 
.status-draft { color:#6c757d; font-weight:bold; }      /* Gray */
.status-pending { color:#f0ad4e; font-weight:bold; }    /* Orange */
.status-finance { color:#0275d8; font-weight:bold; }    /* Blue */
.status-approved { color:#2f8f3f; font-weight:bold; }   /* Green */
.status-rejected { color:#d9534f; font-weight:bold; }   /* Red */
.btn-review {
    background-color: #0275d8; /* blue */
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
}

.btn-review:hover {
    background-color: #025aa5;
}

/* Review Button for Recent Expenses Table */
.table-box a.review-btn {
    background-color: #0275d8; /* blue */
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
}

.table-box a.review-btn:hover {
    background-color: #025aa5;
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

<!-- Top Bar -->
<div class="topbar">
     <h2>
    <i class="fas fa-bars menu-btn" onclick="toggleSidebar()"></i>
    Jocar CMS — <?= htmlspecialchars($adminName) ?>
</h2>

    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_office.php"><i class="fas fa-briefcase"></i> Office</a>
    <a href="project.php"><i class="fas fa-diagram-project"></i> Projects</a>
    <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="admin_expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
    <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
    <a href="admin_settings.php"><i class="fas fa-gear"></i> Settings</a>
</div>

    <!-- Main Content -->
    <div class="main">
        <h3><i class="fas fa-receipt"></i> Expense Management</h3>
        <!-- ===== CARDS ===== -->
        <div class="cards">
            <div class="card"><h4><i class="fas fa-money-bill-wave"></i> Total Expenses</h4><p>$<?php echo number_format($totalExpenses,2); ?></p></div>
            <div class="card"><h4><i class="fas fa-clock"></i> Pending</h4><p><?php echo $pendingCount; ?></p></div>
            <div class="card"><h4><i class="fas fa-circle-check"></i> Approved</h4><p><?php echo $approvedCount; ?></p></div>
            <div class="card"><h4><i class="fas fa-helmet-safety"></i> Active Projects</h4><p><?php echo $activeProjects; ?></p></div>
        </div>

        <!-- ===== PM PENDING APPROVAL ===== -->
<div class="section">
    <h4><i class="fas fa-clock"></i> Project Manager Pending Expenses</h4>
    <div class="table-box">
        <form method="POST" action="bulk_approve_expenses.php">
<table>
    <tr>
        <th>Select</th>
        <th>Title</th>
        <th>Project</th>
        <th>Submitted By</th>
        <th>Status</th>
        <th>Total</th>
        <th>Action</th>
    </tr>


            <?php while($row = $pmPending->fetch_assoc()): ?>
            <tr>
    <td>
        <input type="checkbox" name="expense_ids[]" value="<?php echo $row['id']; ?>">
    </td>
    <td><?php echo $row['expense_title']; ?></td>
    <td>
    <?php echo $row['project_name'] ?? 'Office Expense'; ?>
    </td>
    <td><?php echo $row['full_name']; ?></td>
    <td><?php echo $row['status']; ?></td>
    <td>$<?php echo number_format($row['grand_total'],2); ?></td>
    <td>
        <a href="admin_view_expense.php?id=<?php echo $row['id']; ?>" class="review-btn">
    <i class="fa-solid fa-eye"></i> Review
</a>
    </td>
</tr>
            <?php endwhile; ?>
        </table>
        <br>
<button type="submit" class="btn btn-submit">
    <i class="fa-solid fa-circle-check"></i> Approve Selected Expenses
</button>
</form>

    </div>
</div>

   <div class="section">
    <h4>
        <i class="fa-solid fa-chart-line"></i>
        Project Financial Summary
    </h4>

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
                <option value="<?= $p['id'] ?>"
                    <?= (!empty($_GET['project_id']) && $_GET['project_id'] == $p['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['project_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if (!empty($selectedSummary)): ?>

        <?php
        $projectBudget = $selectedSummary['project_budget'] ?? 0;
        $projectSpent  = $selectedSummary['project_spent']
                      ?? $selectedSummary['total_project_out']
                      ?? 0;
        $cashLeft      = $projectBudget - $projectSpent;
        ?>

        <div class="table-box">
            <table>
                <tr>
                    <td>Project</td>
                    <td>
                        <strong><?= htmlspecialchars($selectedSummary['project_name']) ?></strong>
                        <span style="color:#2f8f3f; font-weight:bold;">
                            (<?= htmlspecialchars($selectedSummary['project_type'] ?? 'N/A') ?>)
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>Budget Amount</td>
                    <td>$<?= number_format($projectBudget, 2) ?></td>
                </tr>

                <tr>
                    <td>Total Paid</td>
                    <td>$<?= number_format($selectedSummary['total_paid'] ?? 0, 2) ?></td>
                </tr>

                <tr>
                    <td>Total Spent</td>
                    <td>$<?= number_format($projectSpent, 2) ?></td>
                </tr>

                <tr>
                    <td>Cash Left</td>
                    <td>
                        <span style="color:<?= $cashLeft < 0 ? '#d9534f' : '#2f8f3f' ?>">
                            $<?= number_format($cashLeft, 2) ?>
                        </span>
                    </td>
                </tr>

                <tr>
                    <td>Client Owes</td>
                    <td>$<?= number_format($selectedSummary['client_owes'] ?? 0, 2) ?></td>
                </tr>
            </table>
        </div>

    <?php endif; ?>
</div>

    </select>
</form>

        <!-- ===== RECENT EXPENSES ===== -->
        <div class="section">
            <h4>
    <i class="fa-solid fa-receipt"></i>
    Recent Expenses
</h4>

            <div class="table-box">
                <table>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $list = $conn->query("SELECT * FROM expenses ORDER BY created_at DESC");
                    while($row = $list->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $row['expense_title']; ?></td>
                        <td><?php echo $row['expense_type']; ?></td>
                        <td class="
<?php
if ($row['status']=='Draft') echo 'status-draft';
elseif ($row['status']=='Pending') echo 'status-pending';
elseif ($row['status']=='Pending_Finance') echo 'status-finance';
elseif ($row['status']=='Approved') echo 'status-approved';
elseif ($row['status']=='Rejected') echo 'status-rejected';
?>
">
<?php echo $row['status']; ?>
</td>
                        <td>$<?php echo number_format($row['grand_total'],2); ?></td>
                        <td>
    <a href="admin_view_expense.php?id=<?php echo $row['id']; ?>" class="review-btn">
    <i class="fa-solid fa-eye"></i> Review
</a>

</td>

                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
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