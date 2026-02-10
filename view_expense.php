<?php
session_start();
include("db.php");
include("project_summary.php");

// Only Project Manager or any logged-in user
if (!isset($_SESSION['role'])) {
    header("Location: login.html");
    exit;
}

$id = intval($_GET['id']);

/* ================= FETCH EXPENSE ================= */
$expense = $conn->query("
    SELECT e.*, p.project_type
    FROM expenses e
    LEFT JOIN projects p ON e.project_id = p.id
    WHERE e.id = $id
")->fetch_assoc();

if (!$expense) {
    die("Expense not found");
}

/* ================= FETCH ITEMS ================= */
$items = $conn->query("SELECT * FROM expense_items WHERE expense_id = $id");

/* ================= PROJECT SUMMARY ================= */
$project_summary = [];
if (!empty($expense['project_id'])) {
    $project_summary = getProjectSummary($conn, (int)$expense['project_id']);
}
/* ================= BACK PAGE BASED ON ROLE ================= */
$backPage = "finance_expenses.php"; // default fallback

if ($_SESSION['role'] === 'ProjectManager') {
    $backPage = "pm_expenses.php";
} elseif ($_SESSION['role'] === 'Finance') {
    $backPage = "finance_expenses.php";
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>View Expense</title>

<style>
body {
    font-family: Arial;
    background: #f4f4f4;
    padding: 30px;
}

.box {
    background: white;
    padding: 25px;
    border-radius: 8px;
    max-width: 900px;
    margin: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* LOGO */
.logo-box {
    text-align: center;
    margin-bottom: 20px;
}
.logo-box img {
    max-width: 260px;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
th {
    background: #f0f0f0;
}

/* PROJECT SUMMARY */
.project-summary {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
}
.project-summary p {
    margin: 6px 0;
    font-weight: bold;
}

/* PROOF */
.proof-box {
    margin-top: 25px;
}
.proof-box img {
    max-width: 100%;
    border-radius: 6px;
    margin-top: 10px;
}

/* BUTTONS */
.btn {
    padding: 10px 15px;
    border-radius: 5px;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
}
.btn-submit {
    background: #2f8f3f;
    color: white;
    border: none;
}
.btn-back {
    background: #6c757d;
    color: white;
    margin-top: 20px;
}
.btn-back:hover {
    background: #5a6268;
}

h3, h4 {
    color: #2f8f3f;
}
.proof-box img {
    max-width: 50%;   /* reduce width to 80% of container */
    height: auto;     /* adjust height proportionally */
    border-radius: 6px;
    margin-top: 10px;
    display: block;
    margin-left: auto;
    margin-right: auto;
}
@media print {
    .btn,
    .logo-box {
        display: none;
    }
    body {
        background: white;
        padding: 0;
    }
    .box {
        box-shadow: none;
        border-radius: 0;
    }
}


</style>
</head>

<body>

<div class="box">

    <!-- LOGO -->
    <div class="logo-box">
        <img src="IMG_5436.PNG" alt="Company Logo">
    </div>

    <h3>Expense Details</h3>

    <p><strong>Title:</strong> <?= htmlspecialchars($expense['expense_title']) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($expense['expense_type']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($expense['status']) ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($expense['created_at']) ?></p>

    <?php if (!empty($expense['project_type'])): ?>
        <p><strong>Project Type:</strong> <?= htmlspecialchars($expense['project_type']) ?></p>
    <?php endif; ?>

    <!-- PROJECT SUMMARY -->
    <?php if (!empty($project_summary)): ?>
        <div class="project-summary">
            <p>Budget: $<?= number_format($project_summary['project_budget'] ?? 0, 2) ?></p>
<p>Total Paid: $<?= number_format($project_summary['total_paid'] ?? 0, 2) ?></p>
<p>Total Spent: $<?= number_format($project_summary['total_project_out'] ?? 0, 2) ?></p>
<p>Client Owes: $<?= number_format($project_summary['client_owes'] ?? 0, 2) ?></p>
<p>Gross Profit: $<?= number_format($project_summary['gross_profit'] ?? 0, 2) ?></p>
        </div>
    <?php else: ?>
        <p><strong>Project:</strong> Office / Non-Project Expense</p>
    <?php endif; ?>

    <!-- EXPENSE ITEMS -->
    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Unit Cost</th>
            <th>Total</th>
        </tr>

        <?php
        $calc_total = 0;
        while ($row = $items->fetch_assoc()):
            $calc_total += $row['total'];
        ?>
        <tr>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= $row['qty'] ?></td>
            <td>$<?= number_format($row['unit_cost'],2) ?></td>
            <td>$<?= number_format($row['total'],2) ?></td>
        </tr>
        <?php endwhile; ?>

        <tr>
            <td colspan="3"><strong>Grand Total</strong></td>
            <td><strong>$<?= number_format($calc_total,2) ?></strong></td>
        </tr>
    </table>

    <!-- EXPENSE PROOF (VIEW ONLY) -->
    <?php if (!empty($expense['proof_file'])): ?>
        <div class="proof-box">
            <h4>Expense Proof</h4>

            <?php
            $filePath = "uploads/expenses/" . $expense['proof_file'];
            $ext = strtolower(pathinfo($expense['proof_file'], PATHINFO_EXTENSION));
            ?>

            <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                <img src="<?= $filePath ?>" alt="Expense Proof">

            <?php elseif ($ext === 'pdf'): ?>
                <iframe
                    src="<?= $filePath ?>"
                    width="100%"
                    height="500"
                    style="border:1px solid #ccc; border-radius:6px;">
                </iframe>

            <?php else: ?>
                <p><em>Preview not available for this file type.</em></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- SUBMIT DRAFT -->
    <?php if ($expense['status'] === 'Draft'): ?>
        <form action="pm_save_expense.php" method="POST" style="margin-top:20px;">
            <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
            <input type="hidden" name="status" value="Pending">
            <button type="submit" class="btn btn-submit">Submit Expense</button>
        </form>
    <?php endif; ?>

    <!-- BACK -->
   <a href="<?= $backPage ?>" class="btn btn-back">← Back</a>    <button onclick="window.print()" class="btn btn-submit">🖨 Print</button>


</div>

</body>
</html>
