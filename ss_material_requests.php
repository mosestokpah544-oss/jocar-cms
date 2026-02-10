<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SiteSupervisor') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch assigned project
$proj_res = $conn->query("
    SELECT p.id, p.project_name
    FROM projects p
    JOIN users u ON u.assigned_project_id = p.id
    WHERE u.id = $user_id
    LIMIT 1
");
$project = $proj_res->fetch_assoc() ?? null;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_date = $_POST['request_date'] ?? date('Y-m-d');

    $items_array = [];
    $item_names = $_POST['item_name'] ?? [];
    $item_qtys  = $_POST['item_qty'] ?? [];

    foreach ($item_names as $index => $name) {
        if (!empty(trim($name)) && !empty(trim($item_qtys[$index]))) {
            $items_array[] = [
                'item' => trim($name),
                'qty'  => intval($item_qtys[$index])
            ];
        }
    }

    if (!empty($items_array)) {
        $items_json = json_encode($items_array);

        $stmt = $conn->prepare("
            INSERT INTO material_requests (site_supervisor_id, project_id, request_date, items)
            VALUES (?,?,?,?)
        ");
        $stmt->bind_param("iiss", $user_id, $project['id'], $request_date, $items_json);
        if ($stmt->execute()) {
            $message = "✅ Material request submitted successfully!";
        } else {
            $message = "❌ Failed to submit request.";
        }
    } else {
        $message = "⚠️ Please enter at least one item and quantity.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Material Request</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body {background:#f4f4f4;}

.topbar{
    background:#2f8f3f;color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;
    position:fixed;top:0;left:0;width:100%;z-index:1000;
}
.topbar h2{font-size:20px;}
.logout{background:#fff;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}
.logout i{margin-right:5px;}

.container{display:flex;}
.sidebar{
    width:200px;background:#256d31;min-height:100vh;padding-top:60px;
    position:fixed;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;
}
.sidebar a{display:flex;align-items:center;gap:10px;padding:15px 20px;color:white;text-decoration:none;font-size:15px;font-weight:400;}
.sidebar a:hover{background:#2f8f3f;}

.main{flex:1;margin-left:220px;padding:30px;}
.main h2,h3{margin-bottom:20px;color:#333;}

.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;}
.card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);display:flex;align-items:center;gap:15px;}
.card i{font-size:30px;color:#2f8f3f;}
.card h4{color:#2f8f3f;margin-bottom:5px}
.card p{font-size:22px;font-weight:bold;margin:0}

/* Tables */
table{width:100%;background:#fff;border-collapse:collapse;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:30px;}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
input, select, textarea{width:100%;padding:6px;border-radius:4px;border:1px solid #ccc;font-size:14px;}
button{padding:10px 20px;background:#2f8f3f;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;margin-top:15px;}
button:hover{background:#256d31;}
.message{margin-bottom:15px;font-weight:bold;font-size:14px;}
.message.success{color:green;}
.message.error{color:red;}
/* Hamburger */
.menu-btn{
    font-size:22px;
    cursor:pointer;
    display:none;
}

/* Sidebar mobile behavior */
.sidebar{
    width:220px;
    background:#256d31;
    height:100vh;
    position:fixed;
    top:0;
    left:-220px;
    padding-top:70px;
    transition:0.3s;
    z-index:1100;
}
.sidebar.active{
    left:0;
}

/* Overlay */
.overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.4);
    display:none;
    z-index:1050;
}
.overlay.active{
    display:block;
}

/* Main mobile */
.main{
    padding:90px 15px 30px;
    margin-left:0;
}

/* Desktop */
@media(min-width:768px){
    .menu-btn{display:none;}
    .sidebar{left:0;}
    .main{margin-left:220px;}
    .overlay{display:none!important;}
}

/* Mobile */
@media(max-width:767px){
    .menu-btn{display:block;}
    .sidebar a{width:100%;}yy
}
.sidebar-logo img{
    width:150px;
    max-width:80%;
    height:auto;
}
/* Sidebar Logo */
.sidebar-logo{
    width:60%;
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <i class="fas fa-bars menu-btn" onclick="toggleMenu()"></i>
    <h2><i class="fas fa-file-alt"></i> Material Request</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>


<div class="container">
    <!-- Sidebar -->
   <div class="sidebar" id="sidebar">

    <img src="IMG_5436.PNG" class="sidebar-logo" alt="Company Logo">

    <a href="site_supervisor_dashboard.php">
        <i class="fas fa-home"></i> Home
    </a>
    <a href="ss_daily_reports.php">
        <i class="fas fa-file-alt"></i> Reports
    </a>
    <a href="ss_material_requests.php">
        <i class="fas fa-box"></i> Requests
    </a>

</div>


<div class="overlay" id="overlay" onclick="toggleMenu()"></div>


    <!-- Main Content -->
    <div class="main">
        <h2>Submit Material Request</h2>

        <?php if($message): ?>
            <div class="message <?= strpos($message,'successfully')!==false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Project:</label>
            <input type="text" value="<?= htmlspecialchars($project['project_name'] ?? '') ?>" readonly>

            <label>Date:</label>
            <input type="date" name="request_date" value="<?= date('Y-m-d') ?>">

            <h3>Items</h3>
            <table id="itemsTable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="item_name[]"></td>
                        <td><input type="number" name="item_qty[]" min="1"></td>
                    </tr>
                </tbody>
            </table>

            <button type="submit">Submit Request</button>
        </form>
    </div>
</div>

<script>
// Automatic row addition for Items
const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
itemsTable.addEventListener('input', function() {
    const lastRow = itemsTable.rows[itemsTable.rows.length-1];
    let filled = Array.from(lastRow.cells).some(cell => {
        const input = cell.querySelector('input');
        return input && input.value.trim() !== '';
    });
    if(filled){
        const newRow = lastRow.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => input.value='');
        itemsTable.appendChild(newRow);
    }
});
</script>


<script>
function toggleMenu(){
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
</script>

</body>
</html>
