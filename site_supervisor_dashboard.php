<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SiteSupervisor') {
    header("Location: index.php");
    exit;
}
include("db.php");

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

/* Assigned Project */
$proj_res = $conn->query("
    SELECT p.id, p.project_name, p.location, p.status
    FROM projects p
    JOIN users u ON u.assigned_project_id = p.id
    WHERE u.id = $user_id
    LIMIT 1
");
$project = $proj_res->fetch_assoc() ?? null;

/* Reports */
$reports_submitted = $conn->query("
    SELECT COUNT(*) AS t FROM daily_reports WHERE site_supervisor_id=$user_id
")->fetch_assoc()['t'] ?? 0;

/* Issues */
$issues_reported = $conn->query("
    SELECT COUNT(*) AS t FROM site_issues WHERE site_supervisor_id=$user_id
")->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Site Supervisor Dashboard</title>
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
.menu-btn{
    font-size:22px;
    cursor:pointer;
}
.logout{
    background:#fff;
    color:#2f8f3f;
    padding:6px 12px;
    border-radius:5px;
    text-decoration:none;
    font-weight:bold;
}

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
    .sidebar a{width:100%;}
}


h2{margin-bottom:15px}

/* Cards */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
    margin-bottom:25px;
}
.card{
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
    display:flex;
    gap:15px;
    align-items:center;
}
.card i{font-size:28px;color:#2f8f3f}
.card h4{color:#2f8f3f}
.card p{font-size:20px;font-weight:bold}

/* Table */
table{
    width:100%;
    background:#fff;
    border-collapse:collapse;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}
th,td{padding:12px;border-bottom:1px solid #ddd}
th{background:#2f8f3f;color:#fff}




/* Desktop */
@media(min-width:768px){
    .menu-btn{display:none}
    .sidebar{left:0}
    .main{margin-left:220px}
    .overlay{display:none!important}
}
.sidebar-logo img{
    width:150px;
    max-width:80%;
    height:auto;
}


</style>
</head>

<body>

<div class="topbar">
    <i class="fas fa-bars menu-btn" onclick="toggleMenu()"></i>
    <h3>Site Supervisor Dashboard</h3>
    <a href="logout.php" class="logout">Logout</a>
</div>

<div class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <img src="IMG_5436.PNG" alt="Company Logo">
    </div>

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

<div class="main">
<h2>Overview</h2>

<div class="cards">
    <div class="card">
        <i class="fas fa-clipboard-list"></i>
        <div>
            <h4>Assigned Project</h4>
            <p><?= $project ? htmlspecialchars($project['project_name']) : 'None' ?></p>
        </div>
    </div>

    <div class="card">
        <i class="fas fa-file-alt"></i>
        <div>
            <h4>Reports Submitted</h4>
            <p><?= $reports_submitted ?></p>
        </div>
    </div>

    <div class="card">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <h4>Issues Reported</h4>
            <p><?= $issues_reported ?></p>
        </div>
    </div>
</div>

<h3>Project Details</h3>
<?php if($project): ?>
<table>
<tr><th>Project</th><th>Location</th><th>Status</th></tr>
<tr>
<td><?= htmlspecialchars($project['project_name']) ?></td>
<td><?= htmlspecialchars($project['location']) ?></td>
<td><?= htmlspecialchars($project['status']) ?></td>
</tr>
</table>
<?php else: ?>
<p>No project assigned.</p>
<?php endif; ?>
</div>

<script>
function toggleMenu(){
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}
</script>

</body>
</html>