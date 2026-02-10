<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Procurement') {
    header("Location: login.html");
    exit;
}

include("db.php");
$user_id = (int)$_SESSION['user_id'];

/* ===========================
   FETCH PROCUREMENT REQUESTS
=========================== */
$sql = "
    SELECT
        id,
        request_title,
        supplier_name,
        quantity,
        unit_price,
        proof_file,
        admin_approved,
        ops_approved,
        created_at
    FROM purchase_requests
    WHERE created_by = $user_id
    ORDER BY created_at DESC
";

$res = $conn->query($sql);

$requests = [];

$total_requests    = 0;
$pending_requests  = 0;
$approved_requests = 0;
$rejected_requests = 0;

while ($row = $res->fetch_assoc()) {

    // ---- STATUS LOGIC (MATCH REQUESTS PAGE) ----
    if ($row['admin_approved'] == 2 || $row['ops_approved'] == 2) {
        $row['status'] = "Rejected";
        $rejected_requests++;
    } elseif ($row['admin_approved'] == 1 || $row['ops_approved'] == 1) {
        $row['status'] = "Approved";
        $approved_requests++;
    } else {
        $row['status'] = "Pending";
        $pending_requests++;
    }

    $requests[] = $row;
    $total_requests++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Procurement Dashboard</title>

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

/* ================= MAIN ================= */
.main{
    margin-left:240px;
    padding:30px;
    width:100%;
}

.main h2{
    margin-bottom:20px;
    color:#333;
}

.main h3{
    margin:25px 0 15px;
    color:#333;
}

/* ================= CARDS ================= */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:30px;
}

.card{
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    display:flex;
    align-items:center;
    gap:15px;
}

.card i{
    font-size:34px;
    color:#2f8f3f;
}

.card h4{
    color:#2f8f3f;
    font-size:15px;
    margin-bottom:5px;
}

.card p{
    font-size:24px;
    font-weight:bold;
    color:#333;
}

/* ================= TABLE ================= */
table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:8px;
    overflow:hidden;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}

th{
    background:#2f8f3f;
    color:#fff;
    text-align:left;
    padding:14px;
    font-size:14px;
}

td{
    padding:14px;
    border-bottom:1px solid #eee;
    font-size:14px;
    color:#333;
}

tr:last-child td{
    border-bottom:none;
}

tr:hover{
    background:#f8fdf9;
}

/* ================= STATUS COLORS ================= */
.status-approved{
    color:#1e8e3e;
    font-weight:bold;
}

.status-pending{
    color:#e67e22;
    font-weight:bold;
}

.status-rejected{
    color:#c0392b;
    font-weight:bold;
}

/* ================= RESPONSIVE ================= */
@media(max-width:900px){
    .sidebar{
        width:200px;
    }
    .main{
        margin-left:210px;
    }
}

@media(max-width:700px){
    .sidebar{
        display:none;
    }
    .main{
        margin-left:0;
    }
}
</style>

</head>

<body>

<div class="topbar">
    <h2><i class="fas fa-hand-holding-usd"></i> Procurement Dashboard</h2>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

    <div class="sidebar">
        <a href="procurement_dashboard.php"><i class="fas fa-home"></i> Home</a>
        <a href="proc_requests.php"><i class="fas fa-file-invoice"></i> Requests</a>
        <a href="proc_settings.php"><i class="fas fa-cog"></i> Settings</a>
    </div>

    <div class="main">
        <h2>Overview</h2>

        <div class="cards">
            <div class="card">
                <i class="fas fa-clipboard-list"></i>
                <div>
                    <h4>Total Requests</h4>
                    <p><?= $total_requests ?></p>
                </div>
            </div>

            <div class="card">
                <i class="fas fa-spinner"></i>
                <div>
                    <h4>Pending</h4>
                    <p><?= $pending_requests ?></p>
                </div>
            </div>

            <div class="card">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Approved</h4>
                    <p><?= $approved_requests ?></p>
                </div>
            </div>

            <div class="card">
                <i class="fas fa-times-circle"></i>
                <div>
                    <h4>Rejected</h4>
                    <p><?= $rejected_requests ?></p>
                </div>
            </div>
        </div>

        <h3><i class="fas fa-file-invoice"></i> Recent Requests</h3>

        <table>
            <tr>
                <th>Request Title</th>
                <th>Supplier</th>
                <th>Total Qty</th>
                <th>Unit Price</th>
                <th>Status</th>
                <th>Created</th>
            </tr>

            <?php foreach ($requests as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['request_title']) ?></td>
                <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                <td><?= (int)$r['quantity'] ?></td>
                <td>$<?= number_format($r['unit_price'], 2) ?></td>
                <td class="status-<?= strtolower($r['status']) ?>">
                    <?= $r['status'] ?>
                </td>
                <td><?= date("Y-m-d H:i", strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

    </div>
</div>

</body>
</html>
