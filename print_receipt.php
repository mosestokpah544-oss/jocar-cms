<?php
session_start();
if (!isset($_SESSION['role'])) {
    die("Access denied");
}

/* ================= DATABASE ================= */
$conn = mysqli_connect("localhost","root","","company_system");
if (!$conn) {
    die("Database connection failed");
}

$payment_id = intval($_GET['payment_id']);

/* ================= PAYMENT DETAILS ================= */
$payment = mysqli_fetch_assoc(mysqli_query($conn,"
   SELECT pp.*, 
       p.project_name, 
       p.project_code,
       p.project_type,
       p.client_name, 
       p.budget, 
       u.full_name
    FROM project_payments pp
    JOIN projects p ON pp.project_id = p.id
    JOIN users u ON pp.entered_by = u.id
    WHERE pp.id = $payment_id
"));

if (!$payment) {
    die("Payment not found");
}

/* ================= TOTAL PAID FOR PROJECT ================= */
$totalPaid = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(amount) AS total_paid
    FROM project_payments
    WHERE project_id = {$payment['project_id']}
"))['total_paid'] ?? 0;

/* ================= CALCULATIONS ================= */
$totalProjectAmount = (float)$payment['budget'];
$remainingBalance   = $totalProjectAmount - $totalPaid;

/* ================= RECEIPT NUMBER ================= */
$receiptNumber = "RECT-" . str_pad($payment['id'], 5, "0", STR_PAD_LEFT) . "-" . $payment['project_code'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Receipt</title>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 30px;
    background: #f4f4f4;
}

.receipt-box {
    max-width: 750px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* LOGO */
.logo-box {
    text-align: center;
    margin-bottom: 20px;
}
.company-logo {
    max-width: 300px;
    width: 100%;
    height: auto;
}

/* TITLE */
h2 {
    text-align: center;
    color: #2f8f3f;
}

/* TABLE */
table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}
table td {
    padding: 12px;
    border: 1px solid #ddd;
}
.total-row {
    font-weight: bold;
    background: #f9f9f9;
}

/* BUTTONS */
.action-buttons {
    margin-top: 25px;
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-buttons button {
    padding: 10px 22px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.btn-print {
    background: #2f8f3f;
    color: #fff;
}
.btn-print:hover {
    background: #256d31;
}

.btn-share {
    background: #0d6efd;
    color: #fff;
}
.btn-share:hover {
    background: #084298;
}

.btn-back {
    background: #6c757d;
    color: #fff;
}
.btn-back:hover {
    background: #5a6268;
}

/* FOOTER */
.footer {
    margin-top: 30px;
    text-align: center;
    font-size: 13px;
    color: #666;
}

/* PRINT */
@media print {
    .action-buttons {
        display: none;
    }
    body {
        background: #fff;
    }
}
</style>
</head>

<body>

<div class="receipt-box">

    <!-- LOGO -->
    <div class="logo-box">
        <img src="IMG_5436.PNG" alt="Company Logo" class="company-logo">
    </div>

    <h2>Payment Receipt</h2>

    <table>
        <tr>
            <td>Receipt Number</td>
            <td><?= htmlspecialchars($receiptNumber) ?></td>
        </tr>

        <tr>
            <td>Project</td>
            <td>
                <?= htmlspecialchars($payment['project_name']) ?><br>
                <small style="color:#555;">(<?= htmlspecialchars($payment['project_type']) ?>)</small>
            </td>
        </tr>

        <tr><td>Client</td><td><?= htmlspecialchars($payment['client_name']) ?></td></tr>
        <tr><td>Payment Amount</td><td>$<?= number_format($payment['amount'], 2) ?></td></tr>
        <tr><td>Payment Method</td><td><?= htmlspecialchars($payment['payment_method']) ?></td></tr>
        <tr><td>Payment Date</td><td><?= htmlspecialchars($payment['payment_date']) ?></td></tr>
        <tr><td>Entered By</td><td><?= htmlspecialchars($payment['full_name']) ?></td></tr>
        <tr><td>Timestamp</td><td><?= htmlspecialchars($payment['created_at']) ?></td></tr>

        <tr class="total-row">
            <td>Total Project Amount</td>
            <td>$<?= number_format($totalProjectAmount, 2) ?></td>
        </tr>

        <tr class="total-row">
            <td>Total Paid So Far</td>
            <td>$<?= number_format($totalPaid, 2) ?></td>
        </tr>

        <tr class="total-row">
            <td>Outstanding Balance</td>
            <td>$<?= number_format($remainingBalance, 2) ?></td>
        </tr>
    </table>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons">
        <button class="btn-print" onclick="window.print()">🖨 Print</button>
        <button class="btn-share" onclick="shareReceipt()">📤 Share</button>
       <button class="btn-back" onclick="window.location.href='finance_payments.php'">
    ← Back
</button>

    </div>

    <div class="footer">
        This receipt was generated automatically by the JOCAR GROUP OF COMPANIES.
    </div>

</div>

<script>
function shareReceipt() {
    if (navigator.share) {
        navigator.share({
            title: 'Payment Receipt',
            text: 'Payment receipt for <?= addslashes($payment['project_name']) ?>',
            url: window.location.href
        });
    } else {
        alert("Sharing not supported on this browser. Please copy the link manually.");
    }
}
</script>

</body>
</html>
