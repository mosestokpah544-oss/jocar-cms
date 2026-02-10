<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Procurement') {
    header("Location: login.html");
    exit;
}

include("db.php");
$user_id = $_SESSION['user_id'];
$message = "";

/* ============================
   HANDLE REQUEST SUBMISSION
============================ */
if (isset($_POST['submit_request'])) {

    $request_title = trim($_POST['request_title']);
    $supplier_name = trim($_POST['supplier_name']);

    /* ---- Upload proof ---- */
/* ---- Upload proof (MATCH OPS STYLE) ---- */
$proof_file = null;

if (!empty($_FILES['proof_file']['name'])) {

    $originalName = $_FILES['proof_file']['name'];
    $tmpName      = $_FILES['proof_file']['tmp_name'];

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf'];

    if (!in_array($ext, $allowed)) {
        die("Invalid file type");
    }

    // Store ONLY filename (same as Operations)
    $proof_file = time() . "_" . basename($originalName);

    if (!is_dir("uploads")) {
        mkdir("uploads", 0777, true);
    }

    if (!move_uploaded_file($tmpName, "uploads/" . $proof_file)) {
        die("File upload failed");
    }
}

    /* ---- Calculate total quantity & grand total ---- */
    $qtys  = $_POST['qty'] ?? [];
    $costs = $_POST['unit_cost'] ?? [];
    $items = $_POST['item_name'] ?? [];

    $total_qty   = 0;
    $grand_total = 0;

    for ($i = 0; $i < count($qtys); $i++) {
        if (!empty($items[$i]) && $qtys[$i] > 0 && $costs[$i] > 0) {
            $total_qty   += $qtys[$i];
            $grand_total += ($qtys[$i] * $costs[$i]);
        }
    }

    /* ---- Generate unique request code ---- */
    $year = date('Y');
    $res_count = $conn->query("SELECT COUNT(*) AS cnt FROM purchase_requests 
                               WHERE created_by = $user_id AND YEAR(created_at) = $year");
    $cnt = $res_count->fetch_assoc()['cnt'] + 1;
    $request_code = "proc-" . str_pad($cnt, 3, "0", STR_PAD_LEFT) . "-$year";

    /* ---- Insert into purchase_requests ---- */
    $stmt = $conn->prepare("
        INSERT INTO purchase_requests
        (request_code, request_title, supplier_name, quantity, unit_price, proof_file, admin_approved, ops_approved, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)
    ");

    // Cast totals properly
    $qty_cast   = (int)$total_qty;
    $grand_cast = (float)$grand_total;

    $stmt->bind_param(
    "sssidsi",
    $request_code,    // s
    $request_title,   // s
    $supplier_name,   // s
    $qty_cast,        // i
    $grand_cast,      // d
    $proof_file,      // s  ✅ FIXED
    $user_id          // i
);

    if ($stmt->execute()) {
        $request_id = $stmt->insert_id;

        /* ---- Insert each item into purchase_request_items ---- */
        $stmt_item = $conn->prepare("
            INSERT INTO purchase_request_items
            (request_id, item_name, quantity, unit_cost)
            VALUES (?, ?, ?, ?)
        ");

        for ($i = 0; $i < count($items); $i++) {
            if (!empty($items[$i]) && $qtys[$i] > 0 && $costs[$i] > 0) {
                $item_name = $items[$i];
                $qty_item  = $qtys[$i];
                $cost_item = $costs[$i];
                $stmt_item->bind_param("isid", $request_id, $item_name, $qty_item, $cost_item);
                $stmt_item->execute();
            }
        }
        $stmt_item->close();

        header("Location: proc_requests.php");
        exit;
    } else {
        $message = "❌ Failed to submit request.";
    }
    $stmt->close();
}

/* ============================
   FETCH REQUESTS
============================ */
$requests = $conn->query("
    SELECT * FROM purchase_requests
    WHERE created_by = $user_id
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Procurement | Office Requests</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{background:#f4f4f4;}

/* Top Bar */
.topbar {
    background:#2f8f3f;color:white;padding:15px 25px;
    display:flex;justify-content:space-between;align-items:center;
    position:fixed;top:0;left:0;width:100%;z-index:1000;
}
.topbar h2{font-size:20px;}
.logout{background:white;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}

/* Layout */
.container{display:flex;}

/* Sidebar */
.sidebar {
    width:200px;background:#256d31;min-height:100vh;padding-top:60px;
    position:fixed;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;
}
.sidebar a{
    display:flex;align-items:center;gap:10px;padding:15px 20px;color:white;text-decoration:none;font-size:15px;font-weight:400;
}
.sidebar a:hover{background:#2f8f3f;}

/* Main */
.main{
    margin-left:240px;
    padding:100px 30px 30px;
}

/* Button */
.add-btn, .view-btn{
    background:#2f8f3f;color:#fff;
    padding:8px 12px;border:none;
    border-radius:5px;font-size:14px;
    cursor:pointer;margin-bottom:10px;
}
.add-btn:hover, .view-btn:hover{background:#256d31;}

/* Form */
.request-form{
    display:none;
    background:#fff;padding:20px;
    border-radius:8px;margin-bottom:30px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}
input,button,textarea{padding:8px;width:100%;margin-bottom:10px;}

/* Table */
table{width:100%;border-collapse:collapse;box-shadow:0 2px 8px rgba(0,0,0,.1);background:#fff;}
th,td{padding:12px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
.status-approved{color:green;font-weight:bold;}
.status-pending{color:orange;font-weight:bold;}
.status-rejected{color:red;font-weight:bold;}
</style>
</head>
<body>

<div class="topbar">
    <h2><i class="fas fa-clipboard-list"></i> Office Requests</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="sidebar">
    <a href="procurement_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="proc_requests.php"><i class="fas fa-file-invoice"></i> Requests</a>
    <a href="proc_settings.php"><i class="fas fa-cog"></i> Settings</a>
</div>

<div class="main">

<?php if($message): ?>
<div style="color:red;font-weight:bold;margin-bottom:10px;"><?= $message ?></div>
<?php endif; ?>

<button class="add-btn" onclick="toggleForm()">
    <i class="fas fa-plus-circle"></i> New Office Request
</button>

<div class="request-form" id="requestForm">
<form method="POST" enctype="multipart/form-data">

<label><i class="fas fa-calendar-day"></i> Request Date</label>
<input type="date" value="<?= date('Y-m-d'); ?>" readonly>

<label><i class="fas fa-heading"></i> Request Title</label>
<input type="text" name="request_title" required>

<label><i class="fas fa-truck"></i> Supplier / Vendor</label>
<input type="text" name="supplier_name" required>

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
    <td><input type="text" name="item_name[]" oninput="checkRow(this)"></td>
    <td><input type="number" name="qty[]" step="0.01" min="0" oninput="calcTotals()"></td>
    <td><input type="number" name="unit_cost[]" step="0.01" min="0" oninput="calcTotals()"></td>
    <td><input type="number" name="row_total[]" readonly></td>
</tr>
</tbody>
</table>

<strong><i class="fas fa-calculator"></i> Grand Total: $<span id="grand_total">0.00</span></strong>

<label><i class="fas fa-file-upload"></i> Upload Proof</label>
<input type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png" required>

<button type="submit" name="submit_request" class="add-btn">
    <i class="fas fa-paper-plane"></i> Submit for Approval
</button>
</form>
</div>

<h3><i class="fas fa-list"></i> My Requests</h3>
<table>
<tr>
<th>ID</th><th>Title</th><th>Supplier</th><th>Total</th><th>Status</th><th>Action</th>
</tr>
<?php while($r=$requests->fetch_assoc()): 
   if ($r['admin_approved'] == 2 || $r['ops_approved'] == 2) {
    $status = 'Rejected';
} elseif ($r['admin_approved'] == 1 || $r['ops_approved'] == 1) {
    $status = 'Approved';
} else {
    $status = 'Pending';
}
?>
<tr>
    <td><?= htmlspecialchars($r['request_code']) ?></td>
    <td><?= htmlspecialchars($r['request_title']) ?></td>
    <td><?= htmlspecialchars($r['supplier_name']) ?></td>
    <td>$<?= number_format($r['unit_price'], 2) ?></td>
    <td class="status-<?= strtolower($status) ?>"><?= $status ?></td>
    <td>
        <a href="view_request.php?id=<?= $r['id'] ?>&from=<?= basename($_SERVER['PHP_SELF']) ?>" 
           class="view-btn">
            <i class="fas fa-eye"></i> View
        </a>
    </td>
</tr>
<?php endwhile; ?>
</table>

</div>

<script>
function toggleForm(){
    let f = document.getElementById("requestForm");
    f.style.display = (f.style.display==="block") ? "none" : "block";
}

function calcTotals(){
    let qty   = document.getElementsByName("qty[]");
    let cost  = document.getElementsByName("unit_cost[]");
    let rows  = document.getElementsByName("row_total[]");
    let grand = 0;

    for(let i=0;i<qty.length;i++){
        let q = parseFloat(qty[i].value)||0;
        let c = parseFloat(cost[i].value)||0;
        let t = q*c;
        rows[i].value = t.toFixed(2);
        grand += t;
    }
    document.getElementById("grand_total").innerText = grand.toFixed(2);
}

function checkRow(el){
    let row = el.closest("tr");
    let name = row.querySelector("input[name='item_name[]']").value.trim();
    let qty  = row.querySelector("input[name='qty[]']").value;
    let cost = row.querySelector("input[name='unit_cost[]']").value;
    let tbody = document.querySelector("#itemsTable tbody");

    if ((name !== "" || qty !== "" || cost !== "") && row === tbody.lastElementChild) {
        addRow();
    }
}

function addRow(){
    let tbody = document.querySelector("#itemsTable tbody");
    let tr = document.createElement("tr");
    tr.innerHTML = `
        <td><input type="text" name="item_name[]" oninput="checkRow(this)"></td>
        <td><input type="number" name="qty[]" step="0.01" min="0" oninput="calcTotals()"></td>
        <td><input type="number" name="unit_cost[]" step="0.01" min="0" oninput="calcTotals()"></td>
        <td><input type="number" name="row_total[]" readonly></td>
    `;
    tbody.appendChild(tr);
}
</script>
</body>
</html>
