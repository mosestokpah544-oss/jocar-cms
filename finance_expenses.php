<?php
session_start();
include("db.php");

// Check Finance Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    header("Location: login.php");
    exit;
}

/* ===================== DASHBOARD DATA ===================== */

// Monthly Expenses (current month)
$monthlyQuery = $conn->query("
    SELECT COALESCE(SUM(grand_total), 0) AS total 
    FROM expenses 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
      AND YEAR(created_at) = YEAR(CURDATE())
");
$monthlyTotal = ($monthlyQuery && $monthlyQuery->num_rows > 0) ? $monthlyQuery->fetch_assoc()['total'] : 0;

// Pending Expenses Count
$pendingQuery = $conn->query("SELECT COUNT(*) AS total FROM expenses WHERE status='Pending'");
$pendingCount = ($pendingQuery && $pendingQuery->num_rows > 0) ? $pendingQuery->fetch_assoc()['total'] : 0;

// Approved Expenses Count
$approvedQuery = $conn->query("SELECT COUNT(*) AS total FROM expenses WHERE status='Approved'");
$approvedCount = ($approvedQuery && $approvedQuery->num_rows > 0) ? $approvedQuery->fetch_assoc()['total'] : 0;

/* ===================== ALL EXPENSES ===================== */
$allExpensesQuery = $conn->query("
    SELECT * 
    FROM expenses 
    ORDER BY CASE WHEN status='Pending' THEN 1 ELSE 2 END, created_at DESC
");
$allExpenses = [];
if ($allExpensesQuery && $allExpensesQuery->num_rows > 0) {
    while ($row = $allExpensesQuery->fetch_assoc()) {
        $allExpenses[] = $row;
    }
}

/* ===================== ALL PURCHASE REQUESTS ===================== */
$allRequestsQuery = $conn->query("
    SELECT 
        pr.id,
        pr.request_code,
        pr.request_title,
        pr.request_type,
        COALESCE(pr.payment_status, 'Unpaid') AS payment_status,
        COALESCE(SUM(pri.quantity * pri.unit_cost), 0) AS total_amount,
        pr.final_approved
    FROM purchase_requests pr
    LEFT JOIN purchase_request_items pri ON pr.id = pri.request_id
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
");
$allRequests = [];
if ($allRequestsQuery && $allRequestsQuery->num_rows > 0) {
    while ($row = $allRequestsQuery->fetch_assoc()) {
        $allRequests[] = $row;
    }
}

/* ===== TOTAL PAID AMOUNT ===== */
$paidExpensesAmount = $conn->query("
    SELECT COALESCE(SUM(grand_total),0) AS total 
    FROM expenses 
    WHERE payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

$paidRequestsAmount = $conn->query("
    SELECT COALESCE(SUM(pri.quantity * pri.unit_cost),0) AS total
    FROM purchase_requests pr
    JOIN purchase_request_items pri ON pr.id = pri.request_id
    WHERE pr.payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

$totalPaidAmount = $paidExpensesAmount + $paidRequestsAmount;

/* ===== TOTAL PENDING TO PAY ===== */
$pendingExpensesAmount = $conn->query("
    SELECT COALESCE(SUM(grand_total),0) AS total
    FROM expenses
    WHERE status='Approved' AND payment_status!='Paid'
")->fetch_assoc()['total'] ?? 0;

$pendingRequestsAmount = $conn->query("
    SELECT COALESCE(SUM(pri.quantity * pri.unit_cost),0) AS total
    FROM purchase_requests pr
    JOIN purchase_request_items pri ON pr.id = pri.request_id
    WHERE pr.final_approved=1 AND pr.payment_status!='Paid'
")->fetch_assoc()['total'] ?? 0;

$totalPendingAmount = $pendingExpensesAmount + $pendingRequestsAmount;

/* ===== TOTAL PAID COUNT ===== */
$paidExpensesCount = $conn->query("
    SELECT COUNT(*) AS total 
    FROM expenses 
    WHERE payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

$paidRequestsCount = $conn->query("
    SELECT COUNT(*) AS total 
    FROM purchase_requests 
    WHERE payment_status='Paid'
")->fetch_assoc()['total'] ?? 0;

$totalPaidCount = $paidExpensesCount + $paidRequestsCount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Finance Expenses</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f4f4;}
.topbar{background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;position:fixed;top:0;left:0;width:100%;z-index:1000;}
.topbar h3{font-size:20px;}
.btn-logout{background:#fff;color:#2f8f3f;padding:8px 15px;border-radius:5px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;transition:all .3s;}
.btn-logout:hover{background:#2f8f3f;color:#fff;}
.btn-logout i{margin-right:5px;}
.container{display:flex;}
.sidebar{width:200px;background:#256d31;min-height:100vh;padding-top:60px;position:fixed;display:flex;flex-direction:column;}
.sidebar a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;font-size:15px;font-weight:400;}
.sidebar a i{margin-right:10px;}
.sidebar a:hover{background:#2f8f3f;}
.main{flex:1;padding:30px;margin-left:220px;}
.main h3{margin-bottom:20px;color:#333;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.card h4{color:#2f8f3f;margin-bottom:10px;}
.card p{font-size:22px;font-weight:bold;}
.table-box{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:30px;}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
.status{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:bold;display:inline-block;}
.status-pending{background:#fff3cd;color:#856404;}
.status-approved{background:#d4edda;color:#155724;}
.status-rejected{background:#f8d7da;color:#721c24;}
.btn{padding:10px 15px;border:none;cursor:pointer;border-radius:5px;font-weight:bold;}
.btn-approve{background:#2f8f3f;color:#fff;}
.btn-view{background:#e7f3ff;color:#0b5ed7;padding:5px 10px;border-radius:4px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;transition:all .3s;}
.btn-view i{margin-right:5px;}
.btn-view:hover{background:#256d31;color:#fff;}
</style>
</head>
<body>

<div class="topbar">
    <h3><i class="fas fa-file-invoice-dollar"></i> Finance Expenses</h3>
    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
        <h3><i class="fas fa-chart-pie"></i> Expense Overview</h3>
        <div class="cards">
            <div class="card">
                <h4>Total Paid</h4>
                <p>$<?= number_format($totalPaidAmount,2) ?></p>
            </div>
            <div class="card">
                <h4>Total Pending Payment</h4>
                <p>$<?= number_format($totalPendingAmount,2) ?></p>
            </div>
            <div class="card">
                <h4>Paid Count</h4>
                <p><?= $totalPaidCount ?></p>
            </div>
        </div>

        <!-- ===== ALL EXPENSES ===== -->
        <div class="table-box">
            <h3>All Expenses</h3>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>View</th>
                    <th>Pay</th>
                </tr>
                <?php foreach($allExpenses as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['expense_title']) ?></td>
                    <td><?= htmlspecialchars($row['expense_type']) ?></td>
                    <td>$<?= number_format($row['grand_total'],2) ?></td>
                    <td>
                        <?php 
                        $status = $row['status'] ?? 'Pending';
                        if($status=='Approved'): ?>
                            <span class="status status-approved"><i class="fas fa-check"></i> Approved</span>
                        <?php elseif($status=='Rejected'): ?>
                            <span class="status status-rejected"><i class="fas fa-times"></i> Rejected</span>
                        <?php else: ?>
                            <span class="status status-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="view_expense.php?id=<?= $row['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                    </td>
                    <td>
                        <?php if($status=='Approved' && ($row['payment_status'] ?? 'Unpaid')=='Unpaid'): ?>
                            <a href="pay.php?type=expense&id=<?= $row['id'] ?>" class="btn btn-approve"><i class="fas fa-credit-card"></i> Pay</a>
                        <?php elseif(($row['payment_status'] ?? '')=='Paid'): ?>
                            <span class="status status-approved"><i class="fas fa-check-circle"></i> Paid</span>
                        <?php elseif($status=='Rejected'): ?>
                            <span class="status status-rejected"><i class="fas fa-times"></i> Rejected</span>
                        <?php else: ?>
                            <span class="status status-pending">Not Ready</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ===== ALL PURCHASE REQUESTS ===== -->
        <div class="table-box">
            <h3>Purchase Requests</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>View</th>
                    <th>Pay</th>
                </tr>
                <?php foreach($allRequests as $req): ?>
<tr>
    <td><?= htmlspecialchars($req['request_code']) ?></td>
    <td><?= htmlspecialchars($req['request_title']) ?></td>
    <td><?= htmlspecialchars($req['request_type']) ?></td>
    <td>$<?= number_format($req['total_amount'],2) ?></td>

    <td>
        <?php if ($req['final_approved'] == 1): ?>
            <span class="status status-approved"><i class="fas fa-check"></i> Approved</span>
        <?php elseif ($req['final_approved'] == 2): ?>
            <span class="status status-rejected"><i class="fas fa-times"></i> Rejected</span>
        <?php else: ?>
            <span class="status status-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
        <?php endif; ?>
    </td>

    <td>
        <a href="view_request.php?id=<?= $req['id'] ?>" class="btn-view">
            <i class="fas fa-eye"></i> View
        </a>
    </td>

    <td>
        <?php if ($req['final_approved'] == 1 && $req['payment_status'] == 'Unpaid'): ?>
            <a href="pay.php?type=request&id=<?= $req['id'] ?>" class="btn btn-approve">
                <i class="fas fa-credit-card"></i> Pay
            </a>
        <?php elseif ($req['payment_status'] == 'Paid'): ?>
            <span class="status status-approved"><i class="fas fa-check-circle"></i> Paid</span>
        <?php elseif ($req['final_approved'] == 2): ?>
            <span class="status status-rejected"><i class="fas fa-times"></i> Rejected</span>
        <?php else: ?>
            <span class="status status-pending">Not Ready</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

            </table>
        </div>

    </div>
</div>
</body>
</html>
