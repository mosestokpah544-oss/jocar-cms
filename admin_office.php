<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

include("db.php");
include("project_summary.php");



// ===== SAVE OFFICE BUDGET =====
if (isset($_POST['save_budget'])) {
    $amount = (float)$_POST['amount'];
    $month  = date('n'); // current month (1–12)
    $year   = date('Y');

    // Check if budget already exists for this month
    $check = $conn->query("
        SELECT id FROM office_budget
        WHERE budget_month = $month AND budget_year = $year
        LIMIT 1
    ");

    if ($check && $check->num_rows > 0) {
        // Update existing budget
        $conn->query("
            UPDATE office_budget
            SET amount = $amount
            WHERE budget_month = $month AND budget_year = $year
        ");
    } else {
        // Insert new budget
        $conn->query("
            INSERT INTO office_budget (amount, budget_month, budget_year)
            VALUES ($amount, $month, $year)
        ");
    }

    // Prevent form resubmission
    header("Location: admin_office.php");
    exit;
}




// ===== HANDLE OFFICE REQUEST APPROVAL / REJECTION =====
if (isset($_GET['action'], $_GET['id'])) {

    $id = (int)$_GET['id'];

    if ($_GET['action'] === 'approve') {
        $conn->query("
            UPDATE purchase_requests
            SET admin_approved = 1,
                final_approved = 1
            WHERE id = $id
        ");
    }

    if ($_GET['action'] === 'reject') {
        $conn->query("
            UPDATE purchase_requests
            SET admin_approved = 2,
                final_approved = 2
            WHERE id = $id
        ");
    }

    header("Location: admin_office.php");
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


require_once "project_summary.php";

// Get office summary (monthly budget + spent + balance)
$office = getOfficeSummary($conn);

// Rename keys to match your HTML for convenience
$office['budget'] = $office['office_budget'];
$office['spent']  = $office['office_spent'];
$office['balance'] = $office['office_balance'];

// ===== OFFICE REQUEST COUNTS =====
$totalOfficeRequests   = $conn->query("SELECT COUNT(*) AS total FROM purchase_requests WHERE request_type='Office'")->fetch_assoc()['total'] ?? 0;
$pendingOfficeRequests = $conn->query("SELECT COUNT(*) AS total FROM purchase_requests WHERE request_type='Office' AND final_approved=0")->fetch_assoc()['total'] ?? 0;
$approvedOfficeRequests= $conn->query("SELECT COUNT(*) AS total FROM purchase_requests WHERE request_type='Office' AND final_approved=1")->fetch_assoc()['total'] ?? 0;

// ===== PENDING OFFICE REQUEST LIST =====
$pendingOfficeList = $conn->query("
    SELECT 
        pr.id, pr.request_code, pr.request_title, pr.created_at, pr.admin_approved, pr.ops_approved, u.role
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE pr.request_type='Office' AND pr.admin_approved=0 AND pr.ops_approved=0
    ORDER BY pr.created_at DESC
");

// ===== RECENT OFFICE REQUESTS =====
$recentOfficeRequests = $conn->query("
    SELECT 
        pr.id, pr.request_code, pr.request_title, pr.created_at, pr.final_approved, u.role
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE pr.request_type='Office'
    ORDER BY pr.created_at DESC
    LIMIT 5
");


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Admin Office</title>

<style>
/* ===== EXACT SAME STYLES (UNCHANGED) ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}
body { background-color: #f4f4f4; }
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
.topbar h2 { font-size: 20px; }
.logout {
    background-color: white;
    color: #2f8f3f;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}
.container { display: flex; }
.sidebar {
    width: 200px;
    background-color: #256d31;
    min-height: 100vh;
    padding-top: 60px;
    position: fixed;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.sidebar a {
    display: block;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
}
.sidebar a:hover { background-color: #2f8f3f; }
.main {
    flex: 1;
    margin-left: 220px;
    padding: 30px;
}
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
.card h4 { color: #2f8f3f; margin-bottom: 10px; }
.card p { font-size: 22px; font-weight: bold; }
.section { margin-top: 40px; }
.section h4 { margin-bottom: 15px; color: #2f8f3f; }
.table-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
/* ===== STATUS BADGES ===== */
.status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

/* ===== TABLE HEADER ENHANCEMENT ===== */
.table-box table th {
    background: #2f8f3f;
    color: white;
    text-transform: uppercase;
    font-size: 13px;
}

/* ===== ACTION BUTTONS ===== */
.btn {
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 13px;
    text-decoration: none;
    font-weight: bold;
    margin-right: 5px;
    display: inline-block;
}

.btn-view {
    background: #e7f3ff;
    color: #0b5ed7;
}

.btn-approve {
    background: #d4edda;
    color: #155724;
}

.btn-reject {
    background: #f8d7da;
    color: #721c24;
}

.btn:hover {
    opacity: 0.85;
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
<h3><i class="fas fa-briefcase"></i> Admin Office Overview</h3>

<!-- ===== OFFICE CARDS ===== -->
<div class="cards">
   <div class="card">
    <h4>Monthly Office Budget</h4>
    <p>$<?= number_format($office['budget'], 2) ?></p>
</div>

<div class="card">
    <h4>Amount Spent</h4>
    <p>$<?= number_format($office['spent'], 2) ?></p>
</div>

<div class="card">
    <h4>Balance</h4>
    <p>$<?= number_format($office['balance'], 2) ?></p>
</div>

    <div class="card">
        <h4>Total Requests</h4>
        <p><?= $totalOfficeRequests ?></p>
    </div>
    <div class="card">
        <h4>Pending</h4>
        <p><?= $pendingOfficeRequests ?></p>
    </div>
    <div class="card">
        <h4>Approved</h4>
        <p><?= $approvedOfficeRequests ?></p>
    </div>
</div>

<button onclick="toggleBudgetForm()" style="
    background:#2f8f3f;
    color:white;
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    margin-bottom:20px;
">
    <i class="fas fa-plus"></i> New Budget
</button>

<div id="budgetForm" style="display:none; margin-bottom:30px;">
    <div class="table-box">
        <h4>Set Monthly Office Budget</h4>

        <form method="POST">
            <label>Budget Amount</label><br><br>
            <input type="number" name="amount" required step="0.01"
                   style="padding:10px; width:250px;"><br><br>

            <button type="submit" name="save_budget" style="
                background:#2f8f3f;
                color:white;
                padding:10px 15px;
                border:none;
                border-radius:5px;
                cursor:pointer;
            ">
                Save Budget
            </button>
        </form>
    </div>
</div>

<!-- ===== PENDING OFFICE REQUESTS ===== -->
<div class="section">
<h4><i class="fas fa-hourglass-half"></i> Pending Office Requests</h4>

<div class="table-box">
<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>From</th>
    <th>Date</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php if ($pendingOfficeList->num_rows == 0): ?>
<tr>
    <td colspan="6">No pending office requests</td>
</tr>
<?php else: ?>
<?php while ($p = $pendingOfficeList->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($p['request_code']) ?></td>
    <td><?= htmlspecialchars($p['request_title']) ?></td>
    <td><?= htmlspecialchars($p['role']) ?></td>
    <td><?= $p['created_at'] ?></td>
    <td>
        <span class="status status-pending">
            <i class="fas fa-hourglass-half"></i> Pending
        </span>
    </td>
    <td>
        <a href="view_request.php?id=<?= $p['id'] ?>" class="btn btn-view">
            <i class="fas fa-eye"></i> View
        </a>

        <a href="?action=approve&id=<?= $p['id'] ?>" class="btn btn-approve">
            <i class="fas fa-check"></i>
        </a>

        <a href="?action=reject&id=<?= $p['id'] ?>" class="btn btn-reject">
            <i class="fas fa-times"></i>
        </a>
    </td>
</tr>
<?php endwhile; ?>
<?php endif; ?>

</table>

</div>
</div>

<!-- ===== RECENT OFFICE REQUESTS ===== -->
<div class="section">
<h4><i class="fas fa-clock"></i> Recent Office Requests</h4>
<div class="table-box">
<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>From</th>
    <th>Date</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php if ($recentOfficeRequests->num_rows == 0): ?>
<tr>
    <td colspan="6">No office requests found</td>
</tr>
<?php else: ?>
<?php while($r = $recentOfficeRequests->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($r['request_code']) ?></td>
    <td><?= htmlspecialchars($r['request_title']) ?></td>
    <td><?= htmlspecialchars($r['role']) ?></td>
    <td><?= $r['created_at'] ?></td>
    <td>
       <?php
$status = $r['final_approved'] ?? 0;

if ($status == 1): ?>
    <span class="status status-approved">
        <i class="fas fa-check"></i> Approved
    </span>
<?php elseif ($status == 2): ?>
    <span class="status status-rejected">
        <i class="fas fa-times"></i> Rejected
    </span>
<?php else: ?>
    <span class="status status-pending">
        <i class="fas fa-hourglass-half"></i> Pending
    </span>
<?php endif; ?>

    </td>
    <td>
        <a href="view_request.php?id=<?= $r['id'] ?>" class="btn btn-view">
            <i class="fas fa-eye"></i> View
        </a>
    </td>
</tr>
<?php endwhile; ?>
<?php endif; ?>

</table>
</div>
</div>

</div>
</div>

<script>
function toggleBudgetForm() {
    const form = document.getElementById('budgetForm');
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
