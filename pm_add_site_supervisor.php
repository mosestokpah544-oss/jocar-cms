<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: index.php");
    exit;
}

include("db.php");

$user_id = $_SESSION['user_id'];
$message = '';

// ---------- Fetch PM Projects ----------
$projects_res = $conn->query("
    SELECT id, project_name
    FROM projects
    WHERE project_manager_id = $user_id
");
$projects = [];
while ($row = $projects_res->fetch_assoc()) {
    $projects[] = $row;
}

// ---------- Handle Form Submission ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $assigned_project_id = $_POST['assigned_project_id'] ?? '';

    if ($full_name === '' || $email === '' || $password === '' || $assigned_project_id === '') {
        $message = "❌ All fields are required!";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users table
        // Insert into users table
$stmt = $conn->prepare("
    INSERT INTO users 
    (full_name, email, password, role, supervisor_manager_id, assigned_project_id, status, created_at) 
    VALUES (?,?,?,?,?,?, 'Active', NOW())
");
$role = 'SiteSupervisor';
$stmt->bind_param("ssssii", $full_name, $email, $hashed_password, $role, $user_id, $assigned_project_id);

        if ($stmt->execute()) {
            $message = "✅ Site Supervisor added successfully!";
        } else {
            $message = "❌ Failed to add Site Supervisor. Email might already exist.";
        }
    }
}

// ---------- Fetch Site Supervisors under this PM ----------
$ss_res = $conn->query("
    SELECT u.id, u.full_name, u.email, p.project_name, u.status 
    FROM users u
    LEFT JOIN projects p ON u.assigned_project_id = p.id
    WHERE u.role='SiteSupervisor' AND u.supervisor_manager_id = $user_id
");
$site_supervisors = [];
while ($row = $ss_res->fetch_assoc()) {
    $site_supervisors[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Site Supervisor</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
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

/* Main */
.main{flex:1;margin-left:220px;padding:30px;}
.main h2,h3{margin-bottom:20px;color:#333;}
input, select{width:100%;padding:8px;margin-bottom:10px;border-radius:5px;border:1px solid #ccc;font-size:14px;}
button{padding:10px 20px;background:#2f8f3f;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:bold;cursor:pointer;margin-top:10px;}
button:hover{background:#256d31;}
.message{margin-bottom:15px;font-weight:bold;font-size:14px;}
.message.success{color:green;}
.message.error{color:red;}

/* Table */
table{width:100%;border-collapse:collapse;margin-top:20px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.1);}
th, td{padding:12px;border-bottom:1px solid #ddd;text-align:left;}
th{background:#2f8f3f;color:#fff;}
</style>
</head>
<body>

<div class="topbar">
    <h2><i class="fas fa-user-plus"></i> Add Site Supervisor</h2>
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
        <?php if($message): ?>
            <div class="message <?= strpos($message,'successfully')!==false?'success':'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <h2>Add New Site Supervisor</h2>
        <form method="POST">
            <label>Full Name:</label>
            <input type="text" name="full_name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Password:</label>
            <input type="text" name="password" value="123456" required>

            <label>Assign Project:</label>
            <select name="assigned_project_id" required>
                <option value="">-- Select Project --</option>
                <?php foreach($projects as $proj): ?>
                    <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit"><i class="fas fa-plus"></i> Add Site Supervisor</button>
        </form>

        <h3>My Site Supervisors</h3>
        <table>
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Assigned Project</th>
                <th>Status</th>
            </tr>
            <?php foreach($site_supervisors as $ss): ?>
                <tr>
                    <td><?= htmlspecialchars($ss['full_name']) ?></td>
                    <td><?= htmlspecialchars($ss['email']) ?></td>
                    <td><?= htmlspecialchars($ss['project_name']) ?></td>
                    <td><?= htmlspecialchars($ss['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

</body>
</html>
