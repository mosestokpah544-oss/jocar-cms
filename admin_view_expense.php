<?php
session_start();
include "db.php";
include "project_summary.php"; // Include the project summary function

// Only Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

/* ================= LOAD EXPENSE ================= */
if (!isset($_GET['id'])) {
    die("Expense ID missing");
}

$expense_id = intval($_GET['id']);

// Get expense along with user and project_id
$expense = $conn->query("
    SELECT 
        e.*, 
        u.full_name, 
        u.role, 
        p.project_type
    FROM expenses e
    JOIN users u ON e.created_by = u.id
    LEFT JOIN projects p ON e.project_id = p.id
    WHERE e.id = $expense_id
")->fetch_assoc();

if (!$expense) {
    die("Expense not found");
}

// Get items
$items = $conn->query("SELECT * FROM expense_items WHERE expense_id = $expense_id");

// Get project summary if a project_id exists
$project_summary = [];
if (!empty($expense['project_id'])) {
    $project_summary = getProjectSummary($conn, (int)$expense['project_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Expense</title>
    <style>
        body { font-family: Arial; background:#f4f4f4; padding:30px; }
        .box { background:#fff; padding:20px; border-radius:8px; max-width:900px; margin:auto; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        table { width:100%; border-collapse: collapse; margin-top:15px; }
        th, td { padding:10px; border-bottom:1px solid #ddd; text-align:left; }
        th { background:#f0f0f0; }
        .btn-back {
            background-color: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            margin-top: 15px;
        }
        .btn-back:hover { background-color: #5a6268; }
        h2, h3 { color: #2f8f3f; }
        p { margin:5px 0; }
        .total-row td { font-weight:bold; }
        .project-summary { margin-top:20px; padding:15px; background:#f9f9f9; border-radius:6px; }
        .project-summary p { margin:5px 0; font-weight:bold; }
        .logo {
    text-align: center;
    margin-bottom: 20px;
}
.logo img {
    max-height: 180px;
}
.proof-box {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}
.proof-box img {
    max-width: 100%;
    max-height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.proof-box a {
    display: inline-block;
    margin-top: 10px;
    background: #2f8f3f;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
}
.proof-box a:hover {
    background: #256d31;
}

    </style>
</head>
<body>

<div class="box">
    <!-- COMPANY LOGO -->
<div class="logo">
    <img src="IMG_5436.PNG" alt="Company Logo">
</div>

    <h2><?php echo htmlspecialchars($expense['expense_title']); ?></h2>
    <p><strong>Submitted By:</strong> <?php echo htmlspecialchars($expense['full_name']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($expense['status']); ?></p>
    <p><strong>Total Cost:</strong> $<?php echo number_format($expense['grand_total'],2); ?></p>
    <p><strong>Created At:</strong> <?php echo $expense['created_at']; ?></p>

    <!-- ===== Project Summary ===== -->
    <?php if(!empty($project_summary)): ?>
        <div class="project-summary">
    <p>Project: <?php echo htmlspecialchars($project_summary['project_name']); ?></p>
    <p>Project Type: <?php echo htmlspecialchars($expense['project_type']); ?></p>
    <p>Budget: $<?php echo number_format($project_summary['budget'],2); ?></p>
    <p>Total Paid: $<?php echo number_format($project_summary['total_paid'],2); ?></p>
    <p>Total Spent: $<?php echo number_format($project_summary['total_spent'],2); ?></p>
    <p>Cash Left: $<?php echo number_format($project_summary['cash_left'],2); ?></p>
    <p>Client Owes: $<?php echo number_format($project_summary['client_owes'],2); ?></p>
</div>

    <?php else: ?>
        <p><strong>Project:</strong> Office / Non-Project Expense</p>
    <?php endif; ?>

    <!-- ===== Expense Items ===== -->
    <h3>Expense Items</h3>
    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Unit Cost</th>
            <th>Total</th>
        </tr>
        <?php 
        $calc_total = 0;
        while($row = $items->fetch_assoc()):
            $calc_total += $row['total'];
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
            <td><?php echo $row['qty']; ?></td>
            <td>$<?php echo number_format($row['unit_cost'],2); ?></td>
            <td>$<?php echo number_format($row['total'],2); ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="total-row">
            <td colspan="3">Grand Total</td>
            <td>$<?php echo number_format($calc_total,2); ?></td>
        </tr>
    </table>

    <!-- ===== EXPENSE PROOF ===== -->
<div class="proof-box">
    <h3>Expense Proof</h3>

    <?php if (!empty($expense['proof_file'])): ?>

        <?php
            $filePath = "uploads/expenses/" . $expense['proof_file'];
            $ext = strtolower(pathinfo($expense['proof_file'], PATHINFO_EXTENSION));
        ?>

        <?php if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
            <img src="<?php echo $filePath; ?>" alt="Expense Proof Image">

        <?php elseif ($ext === 'pdf'): ?>
            <a href="<?php echo $filePath; ?>" target="_blank">
                📄 View PDF Proof
            </a>

        <?php else: ?>
            <p>Unsupported file type.</p>
        <?php endif; ?>

    <?php else: ?>
        <p>No proof file attached.</p>
    <?php endif; ?>
</div>


    <a href="admin_expenses.php" class="btn-back">← Back to Expenses</a>
</div>

</body>
</html>
