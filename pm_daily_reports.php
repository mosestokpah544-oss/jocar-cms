<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: index.php");
    exit;
}

include("db.php");

$pm_id = $_SESSION['user_id'];

// Fetch all submitted reports
$sql = "
SELECT 
    dr.id,
    dr.report_date,
    dr.status,
    u.full_name AS supervisor_name,
    p.project_name
FROM daily_reports dr
JOIN users u ON dr.site_supervisor_id = u.id
JOIN projects p ON dr.project_id = p.id
WHERE p.project_manager_id = ?
ORDER BY dr.report_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pm_id);
$stmt->execute();
$reports = $stmt->get_result();

// Fetch all material requests
$sql_requests = "
SELECT 
    mr.id,
    mr.request_date,
    u.full_name AS supervisor_name,
    p.project_name,
    mr.items
FROM material_requests mr
JOIN users u ON mr.site_supervisor_id = u.id
JOIN projects p ON mr.project_id = p.id
WHERE p.project_manager_id = ?
ORDER BY mr.request_date DESC
";
$stmt_req = $conn->prepare($sql_requests);
$stmt_req->bind_param("i", $pm_id);
$stmt_req->execute();
$requests = $stmt_req->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PM Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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

.main{flex:1;margin-left:200px;padding:30px;}
.main h2{margin-bottom:20px;color:#333;}
table{width:100%;background:#fff;border-collapse:collapse;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:30px;}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
button{padding:10px 20px;background:#2f8f3f;color:#fff;border:none;border-radius:6px;font-weight:bold;cursor:pointer;margin-top:15px;}
button:hover{background:#256d31;}
</style>
</head>
<body>

<div class="topbar">
<h2><i class="fas fa-tachometer-alt"></i> PM Dashboard</h2>
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
<h2>All Submitted Reports</h2>
<table>
<thead>
<tr>
<th>Date</th>
<th>Project</th>
<th>Supervisor</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if($reports && $reports->num_rows>0): ?>
    <?php while($row=$reports->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['report_date']) ?></td>
            <td><?= htmlspecialchars($row['project_name']) ?></td>
            <td><?= htmlspecialchars($row['supervisor_name']) ?></td>
            <td>
                <a href="pm_view_report.php?id=<?= $row['id'] ?>" style="padding:5px 10px;background:#2f8f3f;color:#fff;border-radius:5px;text-decoration:none;">View</a>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5">No reports found</td></tr>
<?php endif; ?>
</tbody>
</table>

<h2>All Material Requests</h2>
<table>
<thead>
<tr>
<th>Date</th>
<th>Project</th>
<th>Supervisor</th>
<th>Items</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if($requests && $requests->num_rows>0): ?>
    <?php while($row=$requests->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['request_date']) ?></td>
            <td><?= htmlspecialchars($row['project_name']) ?></td>
            <td><?= htmlspecialchars($row['supervisor_name']) ?></td>
            <td>
                <?php
                $items = json_decode($row['items'], true);
                $item_list = [];
                if($items){
                    foreach($items as $item){
                        $item_list[] = $item['item'] . " (" . $item['qty'] . ")";
                    }
                }
                echo htmlspecialchars(implode(", ", $item_list));
                ?>
            </td>
            <td>
                <a href="pm_view_request.php?id=<?= $row['id'] ?>" style="padding:5px 10px;background:#2f8f3f;color:#fff;border-radius:5px;text-decoration:none;">View</a>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5">No requests found</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>
</div>
</body>
</html>
