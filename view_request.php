<?php
session_start();
require_once "db.php";

/* ================= VALIDATE ID ================= */
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) exit("Invalid request ID");

/* ================= ROLE ACCESS ================= */
$allowed_roles = ['Admin', 'Procurement', 'Operations', 'Finance'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    exit("Unauthorized access");
}

/* ================= FETCH REQUEST ================= */
$stmt = $conn->prepare("SELECT * FROM purchase_requests WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) exit("Request not found");

$request = $result->fetch_assoc();

/* ================= PROCUREMENT LIMIT ================= */
if ($_SESSION['role'] === 'Procurement' && $request['created_by'] != $_SESSION['user_id']) {
    exit("Unauthorized access");
}

/* ================= REQUEST STATUS ================= */
$status = match ((int)$request['final_approved']) {
    1 => 'Approved',
    2 => 'Rejected',
    default => 'Pending'
};

/* ================= PAYMENT STATUS ================= */
$payment_status = $request['payment_status'] ?: 'Not Paid';

/* ================= FETCH ITEMS ================= */
$item_stmt = $conn->prepare("
    SELECT item_name, quantity, unit_cost
    FROM purchase_request_items
    WHERE request_id = ?
");

if (!$item_stmt) {
    die("Prepare failed: " . $conn->error);
}

$item_stmt->bind_param("i", $request_id);
$item_stmt->execute();
$item_res = $item_stmt->get_result();

$items = [];
while ($row = $item_res->fetch_assoc()) {
    $items[] = $row;
}

/* ================= CALCULATE TOTAL ================= */
$total_amount = 0;
foreach ($items as $i) {
    $total_amount += ((float)$i['quantity'] * (float)$i['unit_cost']);
}

/* ================= PROOF FILE ================= */
$proof_file = !empty($request['proof_file']) ? "uploads/" . htmlspecialchars($request['proof_file']) : null;
$proof_ext = $proof_file ? strtolower(pathinfo($proof_file, PATHINFO_EXTENSION)) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Purchase Request</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; margin:0; }
.box { background:#fff; padding:25px; border-radius:8px; max-width:950px; margin:auto; box-shadow:0 2px 12px rgba(0,0,0,0.15); }
.logo { text-align:center; margin-bottom:20px; }
.logo img { max-height:150px; }
h2,h3 { color:#2f8f3f; margin-top:20px; }
p { margin:6px 0; }
table { width:100%; border-collapse:collapse; margin-top:15px; }
th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
th { background:#2f8f3f; color:#fff; }
tr:nth-child(even){ background:#f9f9f9; }
.total-row td{ font-weight:bold; }
.btn { display:inline-block; padding:10px 18px; background:#2f8f3f; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; margin-bottom:10px; transition:all 0.3s ease; }
.btn:hover { background:#256d31; transform: translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,0.3); }
.status-approved { color:#2f8f3f; font-weight:bold; }
.status-pending { color:#f39c12; font-weight:bold; }
.status-rejected { color:#e74c3c; font-weight:bold; }
.proof-box { margin-top:20px; padding:20px; background:#f9f9f9; border-radius:8px; text-align:center; }
.proof-box a { display:inline-block; margin-top:15px; background:#2f8f3f; color:#fff; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:bold; transition: all 0.3s ease; }
.proof-box a:hover { background:#256d31; transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,0,0,0.3); }
.proof-box a i { margin-right:8px; color:#fff; }
.proof-image { max-width:100%; max-height:400px; border:1px solid #ddd; border-radius:6px; margin-top:15px; }
</style>
</head>
<body>

<div class="box">

    <div class="logo">
        <img src="IMG_5436.PNG" alt="Company Logo" onerror="this.style.display='none'">
    </div>

    <a href="javascript:history.back()" class="btn"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="#" onclick="window.print();" class="btn" style="margin-left:10px;"><i class="fas fa-print"></i> Print</a>

    <h2>Purchase Request Details</h2>
    <p><strong>Request Code:</strong> <?= htmlspecialchars($request['request_code']) ?></p>
    <p><strong>Title:</strong> <?= htmlspecialchars($request['request_title']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($request['request_type']) ?></p>
    <p><strong>Supplier:</strong> <?= htmlspecialchars($request['supplier_name']) ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($request['created_at']) ?></p>
    <p><strong>Status:</strong> <span class="status-<?= strtolower($status) ?>"><?= $status ?></span></p>
    <p><strong>Payment Status:</strong> <?= htmlspecialchars($payment_status) ?></p>


    <!-- ITEMS TABLE -->
    <h3>Request Items</h3>
    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Total</th>
        </tr>
        <?php
        $grand_total = 0;
        if (!empty($items)):
            foreach ($items as $item):
                $total = $item['quantity'] * $item['unit_price'];
                $grand_total += $total;
        ?>
        <tr>
            <td><?= htmlspecialchars($item['item_name']) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td>$<?= number_format($i['unit_cost'],2) ?></td>
            <td>$<?= number_format($total,2) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4">No items found</td></tr>
        <?php endif; ?>
        <tr class="total-row">
            <td colspan="3">Grand Total</td>
            <td>$<?= number_format($grand_total,2) ?></td>
        </tr>
    </table>

    <!-- PROOF FILE -->
    <?php if ($proof_file): ?>
    <div class="proof-box">
        <h3>Request Proof</h3>
        <?php if (in_array($proof_ext,['jpg','jpeg','png','gif'])): ?>
            <img src="<?= $proof_file ?>" class="proof-image">
        <?php elseif ($proof_ext === 'pdf'): ?>
            <a href="<?= $proof_file ?>" target="_blank">📄 View PDF Proof</a>
        <?php else: ?>
            <p>Unsupported file type.</p>
        <?php endif; ?>
        <br>
        <a href="<?= $proof_file ?>" target="_blank"><i class="fas fa-file-download"></i> Download Proof</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
