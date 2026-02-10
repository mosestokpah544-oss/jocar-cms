<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: login.html");
    exit;
}

include("db.php");

$user_id = $_SESSION['user_id'];

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $conn->query("UPDATE users SET full_name='$full_name', email='$email' WHERE id=$user_id");
        $message = "✅ Profile updated successfully!";
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        // Fetch current password
        $res = $conn->query("SELECT password FROM users WHERE id=$user_id");
        $user = $res->fetch_assoc();

        if (password_verify($current, $user['password'])) {
            if ($new === $confirm) {
                $new_hashed = password_hash($new, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$new_hashed' WHERE id=$user_id");
                $message = "✅ Password changed successfully!";
            } else {
                $message = "❌ New passwords do not match!";
            }
        } else {
            $message = "❌ Current password is incorrect!";
        }
    }
}

// Fetch current user info
$res = $conn->query("SELECT full_name, email FROM users WHERE id=$user_id");
$currentUser = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PM Settings</title>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin: 0;
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

/* Form */
.table-box{
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
    margin-bottom:20px;
    max-width:500px;
}
.table-box label{display:block;margin-top:10px;font-weight:bold}
.table-box input{padding:8px;width:100%;margin-top:5px;margin-bottom:10px;border-radius:4px;border:1px solid #ccc;}
.btn{padding:10px 15px;border:none;cursor:pointer;border-radius:5px;font-weight:bold;margin-top:10px; display:flex; align-items:center; gap:5px;}
.btn-submit{background:#2f8f3f;color:#fff;}
.message{margin-bottom:15px;font-weight:bold;color:green;}
.message.error{color:red;}
</style>
</head>
<body>

<div class="topbar">
    <h3><i class="fas fa-cog"></i> Project Manager Settings</h3>
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
        <h2><i class="fas fa-user-cog"></i> Account & Profile Settings</h2>

        <?php if($message): ?>
            <div class="message <?= strpos($message,'❌')!==false ? 'error':'' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Update Info -->
        <div class="table-box">
            <h4><i class="fas fa-user-edit"></i> Update Full Name & Email</h4>
            <form method="POST">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                <button type="submit" name="update_info" class="btn btn-submit"><i class="fas fa-check"></i> Update Info</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="table-box">
            <h4><i class="fas fa-key"></i> Change Password</h4>
            <form method="POST">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
                <label>New Password</label>
                <input type="password" name="new_password" required>
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
                <button type="submit" name="change_password" class="btn btn-submit"><i class="fas fa-lock"></i> Change Password</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
