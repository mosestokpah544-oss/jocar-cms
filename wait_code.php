<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

$admin_id = $_SESSION['user_id'];

/* ------------------ FETCH DATA ------------------ */
$projects = $conn->query("SELECT id, project_name FROM projects WHERE status != 'Completed'");
$categories = $conn->query("SELECT id, category_name FROM expenses_categories");

/* ------------------ CREATE SESSION ------------------ */
if (isset($_POST['start_expense'])) {
    $stmt = $conn->prepare("
        INSERT INTO expenses_sessions (project_id, title, category_id, created_by)
        VALUES (?,?,?,?)
    ");
    $stmt->bind_param("isii",
        $_POST['project_id'],
        $_POST['title'],
        $_POST['category_id'],
        $admin_id
    );
    $stmt->execute();
    header("Location: admin_expenses.php?session=".$stmt->insert_id);
    exit;
}

/* ------------------ ADD ITEM ------------------ */
if (isset($_POST['add_item'])) {
    $total = $_POST['quantity'] * $_POST['unit_amount'];
    $stmt = $conn->prepare("
        INSERT INTO expense_items 
        (session_id, subcategory_id, quantity, unit_amount, total_amount, vendor, expense_date)
        VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "iiiddss",
        $_POST['session_id'],
        $_POST['subcategory_id'],
        $_POST['quantity'],
        $_POST['unit_amount'],
        $total,
        $_POST['vendor'],
        $_POST['expense_date']
    );
    $stmt->execute();
}

/* ------------------ SUBMIT ------------------ */
if (isset($_POST['submit_expense'])) {
    $conn->query("
        UPDATE expenses_sessions 
        SET status='Pending', submitted_at=NOW()
        WHERE id=".$_POST['session_id']
    );
    header("Location: admin_expenses.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Expenses</title>
<style>
body{background:#f4f4f4;font-family:Arial}
.topbar{background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between}
.sidebar{width:220px;background:#256d31;min-height:100vh}
.sidebar a{display:block;padding:15px;color:white;text-decoration:none}
.sidebar a:hover{background:#2f8f3f}
.container{display:flex}
.main{flex:1;padding:30px}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border-bottom:1px solid #ddd}
th{background:#2f8f3f;color:white}
button{background:#2f8f3f;color:#fff;padding:8px 12px;border:none;border-radius:5px}
.danger{background:#dc3545}
input,select{padding:8px;width:100%}
</style>

<script>
function calcTotal(){
 let sum=0;
 document.querySelectorAll('.rowTotal').forEach(r=>sum+=parseFloat(r.innerText));
 document.getElementById('grand').innerText=sum.toFixed(2);
}
</script>
</head>

<body>

<div class="topbar">
<h2>Admin Expenses</h2>
<a href="logout.php" style="background:#fff;color:#2f8f3f;padding:8px 12px;border-radius:5px;text-decoration:none">Logout</a>
</div>

<div class="container">
<div class="sidebar">
<a href="admin_dashboard.php">Dashboard</a>
<a href="project.php">Projects</a>
<a href="admin_expenses.php">Expenses</a>
</div>

<div class="main">

<?php if(!isset($_GET['session'])){ ?>
<h3>Add Expense</h3>
<form method="POST">
<select name="project_id" required>
<option value="">Select Project</option>
<?php while($p=$projects->fetch_assoc()){ ?>
<option value="<?= $p['id'] ?>"><?= $p['project_name'] ?></option>
<?php } ?>
</select><br><br>

<input name="title" placeholder="Expense Title" required><br><br>

<select name="category_id" required>
<option value="">Select Category</option>
<?php while($c=$categories->fetch_assoc()){ ?>
<option value="<?= $c['id'] ?>"><?= $c['category_name'] ?></option>
<?php } ?>
</select><br><br>

<button name="start_expense">Continue</button>
</form>
<?php } ?>

<?php if(isset($_GET['session'])){ 
$sid=$_GET['session'];
$items=$conn->query("
SELECT i.*, s.subcategory_name 
FROM expense_items i 
JOIN expenses_subcategories s ON i.subcategory_id=s.id
WHERE session_id=$sid
");
?>

<h3>Expense Items</h3>
<form method="POST">
<input type="hidden" name="session_id" value="<?= $sid ?>">

<select name="subcategory_id" required>
<?php
$subs=$conn->query("
SELECT sc.id, sc.subcategory_name 
FROM expenses_sessions es
JOIN expenses_subcategories sc ON es.category_id=sc.category_id
WHERE es.id=$sid
");
while($s=$subs->fetch_assoc()){
echo "<option value='{$s['id']}'>{$s['subcategory_name']}</option>";
}
?>
</select><br><br>

<input name="quantity" type="number" placeholder="Quantity" required><br><br>
<input name="unit_amount" type="number" step="0.01" placeholder="Amount per Unit" required><br><br>
<input name="vendor" placeholder="Vendor" required><br><br>
<input type="date" name="expense_date" required><br><br>

<button name="add_item">Add Item</button>
</form>

<table onload="calcTotal()">
<tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr>
<?php while($i=$items->fetch_assoc()){ ?>
<tr>
<td><?= $i['subcategory_name'] ?></td>
<td><?= $i['quantity'] ?></td>
<td><?= $i['unit_amount'] ?></td>
<td class="rowTotal"><?= $i['total_amount'] ?></td>
</tr>
<?php } ?>
</table>

<h3>Grand Total: $<span id="grand">0.00</span></h3>
<script>calcTotal()</script>

<form method="POST">
<input type="hidden" name="session_id" value="<?= $sid ?>">
<button name="submit_expense">Submit for Approval</button>
</form>

<?php } ?>

</div>
</div>
</body>
</html>