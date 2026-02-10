<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    header("Location: login.html");
    exit;
}

include("db.php");
include("project_summary.php");



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


$user_id = $_SESSION['user_id'];
$message = "";

$summary = null;

if (!empty($_GET['project_id'])) {
    $project_id = (int) $_GET['project_id'];
    $summary = getProjectSummary($conn, $project_id);
}


$projects = $conn->query("
    SELECT id, project_name
    FROM projects
    WHERE status != 'Closed'
    ORDER BY project_name
");


/* ===============================
   HANDLE OPS APPROVAL
================================ */
if (isset($_POST['ops_action'], $_POST['request_id'])) {

    $req_id = (int) $_POST['request_id'];
    $action = ($_POST['ops_action'] === 'approve') ? 1 : 2;

    // Update OPS approval
    $conn->query("
        UPDATE purchase_requests 
        SET ops_approved = $action 
        WHERE id = $req_id
    ");

    // Recalculate final approval
    $req = $conn->query("
        SELECT ops_approved, admin_approved 
        FROM purchase_requests 
        WHERE id = $req_id
    ")->fetch_assoc();

    if ($req['ops_approved'] == 1 || $req['admin_approved'] == 1) {
        $final = 1;
    } elseif ($req['ops_approved'] == 2 || $req['admin_approved'] == 2) {
        $final = 2;
    } else {
        $final = 0;
    }

    $conn->query("
        UPDATE purchase_requests 
        SET final_approved = $final 
        WHERE id = $req_id
    ");

    $message = "✅ Request updated successfully";
}

/* ===============================
   HANDLE NEW REQUEST
================================ */
if (isset($_POST['submit_request'])) {

    $request_type = $_POST['request_type'];
    $project_id   = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $title        = trim($_POST['request_title']);
    $supplier     = trim($_POST['supplier_name']);
    $year         = date('Y');

    /* ---- File Upload ---- */
    $proof = null;
    if (!empty($_FILES['proof_file']['name'])) {
        $proof = time() . "_" . basename($_FILES['proof_file']['name']);
        move_uploaded_file($_FILES['proof_file']['tmp_name'], "uploads/" . $proof);
    }

    /* ---- Insert Request (NO request_code yet) ---- */
    $stmt = $conn->prepare("
        INSERT INTO purchase_requests
        (request_title, supplier_name, proof_file, created_by, request_type, project_id, ops_approved, admin_approved, final_approved)
        VALUES (?,?,?,?,?,?,0,0,0)
    ");

    $stmt->bind_param(
        "sssssi",
        $title,
        $supplier,
        $proof,
        $user_id,
        $request_type,
        $project_id
    );

    $stmt->execute();
    $request_id = $stmt->insert_id;
    $stmt->close();

    /* ---- Generate request_code ---- */
    if ($request_type === 'Office') {

        $request_code = "ops-" . str_pad($request_id, 3, '0', STR_PAD_LEFT) . "-$year";

    } elseif ($request_type === 'Project') {

        if (!$project_id) {
            die("Project is required for project requests.");
        }

        $projRes = $conn->query("
            SELECT project_code 
            FROM projects 
            WHERE id = $project_id 
            LIMIT 1
        ");

        if ($projRes->num_rows === 0) {
            die("Invalid project selected.");
        }

        $proj = $projRes->fetch_assoc();

        if (empty($proj['project_code'])) {
            die("Project code missing. Contact admin.");
        }

        $request_code = "ops-" . $proj['project_code'] . "-" .
                        str_pad($request_id, 3, '0', STR_PAD_LEFT) . "-$year";
    }

    /* ---- Save request_code ---- */
    $upd = $conn->prepare("
        UPDATE purchase_requests 
        SET request_code = ? 
        WHERE id = ?
    ");
    $upd->bind_param("si", $request_code, $request_id);
    $upd->execute();
    $upd->close();

    /* ---- Insert Items ---- */
    if (!empty($_POST['item_name'])) {
        foreach ($_POST['item_name'] as $i => $name) {

            if (trim($name) === '') continue;

            $qty  = (float) $_POST['item_qty'][$i];
            $cost = (float) $_POST['item_cost'][$i];

            $stmt = $conn->prepare("
                INSERT INTO purchase_request_items
                (request_id, item_name, quantity, unit_cost)
                VALUES (?,?,?,?)
            ");
            $stmt->bind_param("isdd", $request_id, $name, $qty, $cost);
            $stmt->execute();
            $stmt->close();
        }
    }

    $message = "✅ Request submitted successfully";
}

/* ===============================
   FETCH DATA
================================ */
$requests = $conn->query("
    SELECT pr.*, u.role AS creator_role
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE pr.final_approved = 0
      AND u.role = 'Procurement'
    ORDER BY pr.created_at DESC
");

$projects = $conn->query("
    SELECT id, project_name
    FROM projects
    WHERE is_closed = 0
    ORDER BY project_name
");

$all_requests = $conn->query("
    SELECT pr.*, u.role AS creator_role
    FROM purchase_requests pr
    JOIN users u ON pr.created_by = u.id
    WHERE u.role IN ('Procurement','Operations')
    ORDER BY pr.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Operations Projects</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{ margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif;}
body { background-color: #f4f4f4; }

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
.topbar h2 { font-size: 20px; }
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
.container { display: flex; }

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
.sidebar a:hover { background-color: #2f8f3f; }

.main{
    flex:1;
    margin-left:220px;
    padding:90px 30px 30px;
}

.section{
    background:#fff;
    padding:20px;
    margin-bottom:25px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}

table{width:100%;border-collapse:collapse;}
th,td{padding:12px;border-bottom:1px solid #ddd;}
th{background:#2f8f3f;color:#fff;}

.btn{
    padding:6px 12px;
    border:none;
    border-radius:4px;
    cursor:pointer;
    font-size:14px;
}

.approve{background:#2f8f3f;color:#fff;}
.reject{background:#c0392b;color:#fff;}

.view{
    background:#3498db;
    color:#fff;
    text-decoration:none;
    margin-right:6px;
}
.view:hover{background:#2980b9;}

input,select{width:100%;padding:8px;margin-bottom:10px;}
/* Existing CSS... */
*{ margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif;}
body { background-color: #f4f4f4; }
/* ... other styles ... */

/* Status colors */
.status-approved {
    color: #2f8f3f; /* green */
    font-weight: bold;
}
.status-pending {
    color: #f39c12; /* orange */
    font-weight: bold;
}
.status-rejected {
    color: #e74c3c; /* red */
    font-weight: bold;
}
td strong {
    color: #256d31;
}

</style>

<script>
function toggleProject(sel){
    document.getElementById('projectBox').style.display =
        sel.value === 'Project' ? 'block' : 'none';
}
function toggleRequestForm(){
    const form = document.getElementById('requestForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

</head>

<body>

<div class="topbar">
    <h3><i class="fas fa-folder-open"></i> Request — <?= htmlspecialchars($opsName) ?></h3>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

<h2><i class="fas fa-tasks"></i> Operations Project Page</h2>

<?php if($message): ?>
<p style="color:green;font-weight:bold"><?= $message ?></p>
<?php endif; ?>

<div class="section">
<h3><i class="fas fa-check-circle"></i> Approve Procurement Requests</h3>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Type</th>
    <th>Supplier</th>
    <th>Action</th>
</tr>


<?php while($r = $requests->fetch_assoc()): ?>
<tr>
<td><strong><?= htmlspecialchars($r['request_code']) ?></strong></td>
<td><?= htmlspecialchars($r['request_title']) ?></td>
<td><?= htmlspecialchars($r['request_type']) ?></td>
<td><?= htmlspecialchars($r['supplier_name']) ?></td>
<td>

    <a href="view_request.php?id=<?= $r['id'] ?>&from=ops_projects.php" class="btn view">
        <i class="fas fa-eye"></i> View
    </a>

    <form method="POST" style="display:inline">
        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
        <button name="ops_action" value="approve" class="btn approve">Approve</button>
        <button name="ops_action" value="reject" class="btn reject">Reject</button>
    </form>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<?php if ($summary): ?>

<?php
$budget     = $summary['project_budget'];
$spent      = $summary['total_project_out']; // 🔑 CORRECT
$profit     = $summary['gross_profit'];
$totalPaid = $summary['total_paid'];
$clientOwes = $summary['client_owes'];
?>

<table>
<tr>
    <td>Project</td>
    <td>
        <strong><?= htmlspecialchars($summary['project_name']) ?></strong>
        (<?= htmlspecialchars($summary['project_type']) ?>)
    </td>
</tr>

<tr>
    <td>Budget</td>
    <td>$<?= number_format($budget, 2) ?></td>
</tr>

<tr>
    <td>Total Paid</td>
    <td>$<?= number_format($totalPaid, 2) ?></td>
</tr>

<tr>
    <td>Total Spent</td>
    <td>$<?= number_format($spent, 2) ?></td>
</tr>

<tr>
    <td>Client Owes</td>
    <td>$<?= number_format($clientOwes, 2) ?></td>
</tr>

<tr>
    <td>Profit / Loss</td>
    <td style="color:<?= $profit < 0 ? '#e74c3c' : '#2f8f3f' ?>">
        $<?= number_format($profit, 2) ?>
    </td>
</tr>
</table>

<?php endif; ?>


<div class="section">
<h3><i class="fas fa-file-invoice"></i> Make a Request</h3>

<button class="btn approve" onclick="toggleRequestForm()">
    <i class="fas fa-plus"></i> Make New Request
</button>

<form id="requestForm" method="POST" enctype="multipart/form-data" style="display:none;margin-top:15px">

<label>Request Type</label>
<select name="request_type" onchange="toggleProject(this)" required>
<option value="">-- Select --</option>
<option value="Project">Project</option>
<option value="Office">Office</option>
</select>

<div id="projectBox" style="display:none">
<label>Select Project</label>
<select name="project_id">
<?php while($p = $projects->fetch_assoc()): ?>
<option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
<?php endwhile; ?>
</select>
</div>

<label>Request Title</label>
<input type="text" name="request_title" required>

<label>Supplier</label>
<input type="text" name="supplier_name">

<h4><i class="fas fa-boxes"></i> Request Items</h4>

<table id="itemsTable">
<thead>
<tr>
    <th>Item</th>
    <th>Qty</th>
    <th>Unit Cost</th>
    <th>Total</th>
</tr>
</thead>

<tbody>
<tr>
    <td>
        <input type="text" name="item_name[]" oninput="checkLastRow(this)">
    </td>
    <td>
        <input type="number" name="item_qty[]" step="0.01" min="0"
               oninput="calcTotals(); checkLastRow(this)">
    </td>
    <td>
        <input type="number" name="item_cost[]" step="0.01" min="0"
               oninput="calcTotals(); checkLastRow(this)">
    </td>
    <td>
        <input type="number" class="row_total" readonly>
    </td>
</tr>
</tbody>
</table>

<strong style="display:block;margin-top:10px">
    <i class="fas fa-calculator"></i>
    Grand Total: $<span id="grand_total">0.00</span>
</strong>


<label>Proof</label>
<input type="file" name="proof_file">

<button class="btn approve" name="submit_request">
    <i class="fas fa-paper-plane"></i> Submit Request
</button>

</form>

<div class="section">
<h3><i class="fas fa-list"></i> All Requests</h3>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Type</th>
    <th>Supplier</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while ($r = $all_requests->fetch_assoc()): ?>

<?php
    // Determine final status
    if ($r['final_approved'] == 1) {
        $status_text  = 'Approved';
        $status_class = 'status-approved';
        $status_icon  = '<i class="fas fa-check-circle"></i>';
    } elseif ($r['final_approved'] == 2) {
        $status_text  = 'Rejected';
        $status_class = 'status-rejected';
        $status_icon  = '<i class="fas fa-times-circle"></i>';
    } else {
        $status_text  = 'Pending';
        $status_class = 'status-pending';
        $status_icon  = '<i class="fas fa-clock"></i>';
    }
?>

<tr>
    <td><strong><?= htmlspecialchars($r['request_code']) ?></strong></td>
    <td><?= htmlspecialchars($r['request_title']) ?></td>
    <td><?= htmlspecialchars($r['request_type']) ?></td>
    <td><?= htmlspecialchars($r['supplier_name']) ?></td>

    <td class="<?= $status_class ?>">
        <?= $status_icon ?> <?= $status_text ?>
    </td>

    <td><?= htmlspecialchars($r['created_at']) ?></td>

    <td>
        <a href="view_request.php?id=<?= $r['id'] ?>&from=ops_projects.php" class="btn view">
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
function calcTotals() {
    let grandTotal = 0;
    document.querySelectorAll("#itemsTable tbody tr").forEach(row => {
        const qty  = parseFloat(row.querySelector('input[name="item_qty[]"]')?.value) || 0;
        const cost = parseFloat(row.querySelector('input[name="item_cost[]"]')?.value) || 0;
        const total = qty * cost;

        row.querySelector('.row_total').value = total.toFixed(2);
        grandTotal += total;
    });

    document.getElementById("grand_total").innerText = grandTotal.toFixed(2);
}

function checkLastRow(input) {
    const row = input.closest('tr');
    const rows = document.querySelectorAll("#itemsTable tbody tr");
    const lastRow = rows[rows.length - 1];

    // If typing in last row → auto add new row
    if (row === lastRow) {
        const hasValue = [...lastRow.querySelectorAll("input")]
            .some(i => i.value !== "");

        if (hasValue) addNewRow();
    }
}

function addNewRow() {
    const tbody = document.querySelector("#itemsTable tbody");

    const newRow = document.createElement("tr");
    newRow.innerHTML = `
        <td><input type="text" name="item_name[]" oninput="checkLastRow(this)"></td>
        <td><input type="number" name="item_qty[]" step="0.01" min="0"
                   oninput="calcTotals(); checkLastRow(this)"></td>
        <td><input type="number" name="item_cost[]" step="0.01" min="0"
                   oninput="calcTotals(); checkLastRow(this)"></td>
        <td><input type="number" class="row_total" readonly></td>
    `;

    tbody.appendChild(newRow);
}
</script>

</body>
</html>
