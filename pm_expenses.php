<?php
session_start();
include "db.php";

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: login.html");
    exit;
}

include("db.php");
include("project_summary.php");

$user_id = $_SESSION['user_id'];

/* ===================== DASHBOARD COUNTS ===================== */

// Total Expenses (ONLY fully approved by Admin & Finance)
$approvedQuery = $conn->query("
    SELECT COUNT(*) AS approved
    FROM expenses
    WHERE created_by='$user_id'
    AND status = 'Approved'
");
$approvedCount = $approvedQuery->fetch_assoc()['approved'] ?? 0;

// Draft Expenses (Only PM sees)
$draftQuery = $conn->query("
    SELECT COUNT(*) AS draft
    FROM expenses
    WHERE created_by = '$user_id'
    AND status = 'Draft'
");
$draftCount = $draftQuery->fetch_assoc()['draft'] ?? 0;

// Pending Expenses (Waiting for both Admin & Finance)
$pendingQuery = $conn->query("
    SELECT COUNT(*) AS pending
    FROM expenses
    WHERE created_by='$user_id'
    AND status = 'Pending'
");
$pendingCount = $pendingQuery->fetch_assoc()['pending'] ?? 0;

// Approved Expenses (Only if both approved)
// Approved Expenses (Admin + Finance approved)
$totalExpensesQuery = $conn->query("
    SELECT COALESCE(SUM(grand_total), 0) AS total
    FROM expenses
    WHERE created_by = '$user_id'
");
$totalExpenses = $totalExpensesQuery->fetch_assoc()['total'];

// Load ONLY Projects Assigned To This PM
$projects = $conn->query("
    SELECT id, project_name 
    FROM projects 
    WHERE project_manager_id = '$user_id'
      AND is_closed = 0
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PM Expenses</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
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
.logout i { margin-right: 5px; }

/* Layout */
.container {
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 200px;
    background-color: #256d31;
    min-height: 100vh;
    padding-top: 60px;
    position: fixed;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: center;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
    font-weight: 400;
}

.sidebar a:hover {
    background-color: #2f8f3f;
}

/* Main Content */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 220px;
}

.main h3 {
    margin-bottom: 20px;
    color: #333;
}

.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom:30px; }
.card { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h4 { color: #2f8f3f; margin-bottom: 10px; }
.card p { font-size: 22px; font-weight: bold; color: #333; }

.section { margin-top: 30px; }
.table-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; }

table { width: 100%; border-collapse: collapse; }
table th, table td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 14px; }
table th { background-color: #f0f0f0; text-align: left; }

input, select { padding: 8px; width: 100%; margin-top: 5px; margin-bottom: 10px; }

.btn { padding: 10px 15px; border: none; cursor: pointer; border-radius: 5px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
.btn-draft { background: #ccc; }
.btn-submit { background: #2f8f3f; color: white; }
.btn-add { background: #2f8f3f; color: white; margin-bottom: 15px; }

.status-draft { color: gray; font-weight: bold; }
.status-pending { color: orange; font-weight: bold; }
.status-approved { color: green; font-weight: bold; }

/* View Button for PM Expenses Table */
.table-box a.view-btn {
    background-color: #0275d8; /* blue */
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.table-box a.view-btn:hover {
    background-color: #025aa5;
}
</style>
</head>

<body>

<div class="topbar">
    <h2><i class="fas fa-wallet"></i> Construction Management System</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<div class="sidebar">
        <a href="project_manager_dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="my_project.php"><i class="fas fa-folder-open"></i> Projects</a>
        <a href="pm_expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a>
        <a href="pm_add_site_supervisor.php"><i class="fas fa-user-plus"></i> Add Supervisor</a>
        <a href="pm_daily_reports.php"><i class="fas fa-file-alt"></i> Daily Reports</a>
        <a href="pm_settings.php"><i class="fas fa-cog"></i> Settings</a>
    </div>

<div class="main">
    <h3><i class="fas fa-file-invoice-dollar"></i> My Expenses</h3>

    <!-- DASHBOARD CARDS -->
    <div class="cards">
        <div class="card"><h4><i class="fas fa-check-circle"></i> Total Approved Expenses</h4><p>$<?php echo number_format($totalExpenses,2); ?></p></div>
        <div class="card"><h4><i class="fas fa-pencil-alt"></i> Draft</h4><p><?php echo $draftCount; ?></p></div>
        <div class="card"><h4><i class="fas fa-hourglass-half"></i> Pending Approval</h4><p><?php echo $pendingCount; ?></p></div>
        <div class="card"><h4><i class="fas fa-thumbs-up"></i> Approved</h4><p><?php echo $approvedCount; ?></p></div>
    </div>

    <button class="btn btn-add" onclick="toggleForm()"><i class="fas fa-plus"></i> Add Expense</button>

    <!-- ADD EXPENSE FORM -->
    <div class="section" id="expenseForm" style="display:none;">
        <h4><i class="fas fa-plus-circle"></i> Add New Expense</h4>
        <div class="table-box">
            <form action="pm_save_expense.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="created_by" value="<?php echo $user_id; ?>">

                <label>Expense Type</label>
                <select name="expense_type" required>
                    <option value="Project">Project Expense</option>
                </select>

                <label>Select Project</label>
                <select name="project_id" required>
                    <option value="">Select Project</option>
                    <?php while($row = $projects->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo $row['project_name']; ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Expense Title</label>
                <input type="text" name="expense_title" required>

                <label>Date</label>
                <input type="text" value="<?php echo date('Y-m-d'); ?>" readonly>

                <h4><i class="fas fa-list"></i> Expense Items</h4>
                <table id="items_table">
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Total</th>
                    </tr>
                    <tr>
    <td><input type="text" name="item_name[]" required></td>
    <td><input type="number" name="qty[]" value="0" step="1" min="0" oninput="calculateTotals()" required></td>
    <td><input type="number" name="unit_cost[]" value="0.00" step="0.01" min="0" oninput="calculateTotals()" required></td>
    <td><input type="number" name="total[]" value="0.00" readonly></td>
</tr>

                </table>

                <br>
                <strong>Grand Total: $<span id="grand_total">0.00</span></strong>
                 <label>Upload Proof (PDF or Image)</label>
                 <input type="file" name="expense_proof" 
                 accept=".pdf,.jpg,.jpeg,.png" 
                 required>
                 <small>Accepted: PDF, JPG, PNG</small>

                <br><br>
                <button type="submit" name="status" value="Draft" class="btn btn-draft"><i class="fas fa-save"></i> Save as Draft</button>
                <button type="submit" name="status" value="Pending" class="btn btn-submit"><i class="fas fa-paper-plane"></i> Submit for Approval</button>
            </form>
        </div>
    </div>

    <div class="section">
    <h4><i class="fas fa-chart-pie"></i> Project Financial Summary</h4>

    <select id="projectSelect">
        <option value="">Select Project</option>
        <?php
        $projList = $conn->query("
    SELECT id, project_name 
    FROM projects 
    WHERE project_manager_id = '$user_id'
      AND is_closed = 0
");

        while ($p = $projList->fetch_assoc()):
        ?>
            <option value="<?= $p['id'] ?>">
                <?= htmlspecialchars($p['project_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <div class="table-box" id="summaryBox" style="display:none;">
        <table>
            <tr><td>Project</td><td id="s_name"></td></tr>
            <tr><td>Budget Amount</td><td id="s_budget"></td></tr>
            <tr><td>Total Paid</td><td id="s_paid"></td></tr>
            <tr><td>Total Spent</td><td id="s_spent"></td></tr>
            <tr><td>Cash Left</td><td id="s_cash"></td></tr>
            <tr><td>Client Owes</td><td id="s_owes"></td></tr>
        </table>
    </div>
</div>

<!-- EXPENSE LIST -->
<div class="section">
    <h4><i class="fas fa-list-alt"></i> My Expense Records</h4>
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
            $list = $conn->query("
                SELECT * FROM expenses 
                WHERE created_by='$user_id' 
                ORDER BY created_at DESC
            ");
            while($row = $list->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo $row['expense_title']; ?></td>
                <td><?php echo $row['expense_type']; ?></td>
                <td class="<?php 
                    echo ($row['status']=='Draft') ? 'status-draft' : 
                         (($row['status']=='Pending') ? 'status-pending' : 'status-approved'); ?>">
                    <?php echo $row['status']; ?>
                </td>
                <td>$<?php echo number_format($row['grand_total'],2); ?></td>
                <td>
                    <a href="view_expense.php?id=<?php echo $row['id']; ?>" class="view-btn">
                        <i class="fas fa-eye"></i> View
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
function toggleForm() {
    let form = document.getElementById("expenseForm");
    form.style.display = (form.style.display === "none") ? "block" : "none";
}

function calculateTotals() {
    let rows = document.querySelectorAll("#items_table tr");
    let grandTotal = 0;

    rows.forEach((row, index) => {
        if (index === 0) return;

        let qty = row.querySelector("input[name='qty[]']").value || 0;
        let unit = row.querySelector("input[name='unit_cost[]']").value || 0;
        let totalField = row.querySelector("input[name='total[]']");

        let total = qty * unit;
        totalField.value = total.toFixed(2);
        grandTotal += total;
    });

    document.getElementById("grand_total").innerText = grandTotal.toFixed(2);
    addNewRowIfNeeded();
}

function addNewRowIfNeeded() {
    let table = document.getElementById("items_table");
    let lastRow = table.rows[table.rows.length - 1];

    let item = lastRow.querySelector("input[name='item_name[]']").value;
    let qty = lastRow.querySelector("input[name='qty[]']").value;
    let unit = lastRow.querySelector("input[name='unit_cost[]']").value;

    if (item !== "" && qty !== "" && unit !== "") {
        let newRow = table.insertRow();
        newRow.innerHTML = `
    <td><input type="text" name="item_name[]"></td>
    <td><input type="number" name="qty[]" value="0" step="1" min="0" oninput="calculateTotals()"></td>
    <td><input type="number" name="unit_cost[]" value="0.00" step="0.01" min="0" oninput="calculateTotals()"></td>
    <td><input type="number" name="total[]" value="0.00" readonly></td>
`;
    }
}

document.getElementById('projectSelect').addEventListener('change', function () {
    const projectId = this.value;
    const box = document.getElementById('summaryBox');

    if (!projectId) {
        box.style.display = 'none';
        return;
    }

    fetch('project_summary_api.php?project_id=' + projectId)
        .then(res => res.json())
        .then(data => {
            if (!data.project_name) return;

            document.getElementById('s_name').innerText   = data.project_name;
            document.getElementById('s_budget').innerText = '$' + data.budget.toLocaleString();
            document.getElementById('s_paid').innerText   = '$' + data.total_paid.toLocaleString();
            document.getElementById('s_spent').innerText  = '$' + data.total_spent.toLocaleString();
            document.getElementById('s_cash').innerText   = '$' + data.cash_left.toLocaleString();
            document.getElementById('s_owes').innerText   = '$' + data.client_owes.toLocaleString();

            box.style.display = 'block';
        });
});
</script>

</body>
</html>
