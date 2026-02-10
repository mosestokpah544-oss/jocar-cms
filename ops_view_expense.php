<?php
session_start();
require_once "db.php";

/* ================= ROLE ACCESS ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    exit("Unauthorized access");
}

/* ================= VALIDATE ID ================= */
$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($expense_id <= 0) exit("Invalid expense ID");

/* ================= FETCH EXPENSE ================= */
$stmt = $conn->prepare("
    SELECT 
        e.*,
        u.full_name AS submitted_by,
        u.role AS user_role,
        p.project_name,
        p.project_type
    FROM expenses e
    JOIN users u ON e.created_by = u.id
    LEFT JOIN projects p ON e.project_id = p.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $expense_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) exit("Expense not found");
$expense = $res->fetch_assoc();

/* ================= STATUS LABELS ================= */
$approval_status = match (true) {
    $expense['admin_approved'] == 1 || $expense['finance_approved'] == 1 => 'Approved',
    default => 'Pending'
};

$payment_status = $expense['payment_status'] ?: 'Not Paid';

/* ================= EXPENSE ITEMS ================= */
$items = $conn->query("SELECT * FROM expense_items WHERE expense_id = $expense_id");

/* ================= PROOF FILE ================= */
$proof_file = null;
$proof_ext = null;
if (!empty($expense['proof_file'])) {
    if (str_starts_with($expense['proof_file'], 'uploads/')) {
        $proof_file = $expense['proof_file'];
    } else {
        $proof_file = "uploads/expenses/" . $expense['proof_file'];
    }
    $proof_ext = strtolower(pathinfo($proof_file, PATHINFO_EXTENSION));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Expense</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { font-family: Arial,sans-serif; background:#f4f4f4; padding:20px; margin:0; }
.box { background:#fff; padding:25px; border-radius:8px; max-width:950px; margin:auto; box-shadow:0 2px 12px rgba(0,0,0,0.15); }
.logo{text-align:center; margin-bottom:20px;} .logo img{max-height:150px;}
h2,h3{color:#2f8f3f; margin-top:20px;} p{margin:6px 0;}
table{width:100%; border-collapse:collapse; margin-top:15px;}
th, td{padding:12px; border-bottom:1px solid #ddd; text-align:left;}
th{background:#2f8f3f; color:#fff;}
tr:nth-child(even){background:#f9f9f9;}
.btn{display:inline-block; padding:10px 18px; background:#2f8f3f; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; margin-bottom:10px;}
.btn:hover{background:#256d31;}
.status-approved{color:#2f8f3f; font-weight:bold;}
.status-pending{color:#f39c12; font-weight:bold;}
.proof-box{margin-top:20px; padding:20px; background:#f9f9f9; border-radius:8px; text-align:center;}
.proof-image{max-width:100%; max-height:400px; border:1px solid #ddd; border-radius:6px; margin-top:15px;}
.total-row td{font-weight:bold;}
</style>
</head>
<body>
<div class="box">

    <!-- LOGO -->
    <div class="logo">
        <img src="IMG_5436.PNG" alt="Company Logo" onerror="this.style.display='none'">
    </div>

    <!-- BACK + PRINT -->
    <a href="javascript:history.back()" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="#" onclick="window.print();" class="btn" style="margin-left:10px;"><i class="fas fa-print"></i> Print</a>

    <!-- EXPENSE DETAILS -->
    <h2>Expense Details</h2>
    <p><strong>Expense ID:</strong> #<?= $expense['id'] ?></p>
    <p><strong>Project:</strong> <?= htmlspecialchars($expense['project_name'] ?: "Non-Project Expense") ?></p>
    <p><strong>Title:</strong> <?= htmlspecialchars($expense['expense_title']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($expense['expense_type']) ?></p>
    <p><strong>Submitted By:</strong> <?= htmlspecialchars($expense['submitted_by']) ?></p>
    <p><strong>Role:</strong> <?= htmlspecialchars($expense['user_role']) ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($expense['created_at']) ?></p>
    <p><strong>Approval Status:</strong> <span class="status-<?= strtolower($approval_status) ?>"><?= $approval_status ?></span></p>
    <p><strong>Payment Status:</strong> <?= htmlspecialchars($payment_status) ?></p>
    <?php if ($expense['paid_at']): ?>
        <p><strong>Paid At:</strong> <?= htmlspecialchars($expense['paid_at']) ?></p>
    <?php endif; ?>

    <!-- FINANCIAL SUMMARY -->
    <h3>Expense Items</h3>
    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Unit Cost</th>
            <th>Total</th>
        </tr>
        <?php $calc_total = 0; while($row = $items->fetch_assoc()): $calc_total += $row['total']; ?>
        <tr>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= $row['qty'] ?></td>
            <td>$<?= number_format($row['unit_cost'],2) ?></td>
            <td>$<?= number_format($row['total'],2) ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="total-row">
            <td colspan="3">Grand Total</td>
            <td>$<?= number_format($calc_total,2) ?></td>
        </tr>
    </table>

    <!-- PROOF FILE -->
    <?php if ($proof_file): ?>
    <div class="proof-box">
        <h3>Expense Proof</h3>
        <?php if (in_array($proof_ext,['jpg','jpeg','png','gif'])): ?>
            <img src="<?= htmlspecialchars($proof_file) ?>" class="proof-image">
        <?php elseif ($proof_ext === 'pdf'): ?>
            <a href="<?= htmlspecialchars($proof_file) ?>" target="_blank">📄 View PDF Proof</a>
        <?php else: ?>
            <p>Unsupported file type.</p>
        <?php endif; ?>
        <br>
        <a href="<?= htmlspecialchars($proof_file) ?>" target="_blank" class="btn"><i class="fas fa-file-download"></i> Download Proof</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
