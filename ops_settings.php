<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Operations') {
    header("Location: login.html");
    exit;
}

include("db.php");

$user_id = $_SESSION['user_id'];
$message = '';



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



// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update Full Name & Email
    if (isset($_POST['update_info'])) {
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email     = $conn->real_escape_string($_POST['email']);

        if ($full_name && $email) {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
            $stmt->bind_param("ssi", $full_name, $email, $user_id);
            if ($stmt->execute()) {
                $message = "✅ Profile updated successfully!";
            } else {
                $message = "❌ Unable to update profile!";
            }
            $stmt->close();
        } else {
            $message = "❌ Full name and email are required!";
        }

    // Change Password
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $res = $conn->query("SELECT password FROM users WHERE id=$user_id");
        $user = $res->fetch_assoc();

        if (password_verify($current, $user['password'])) {
            if ($new === $confirm) {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $hashed, $user_id);
                if ($stmt->execute()) {
                    $message = "✅ Password changed successfully!";
                } else {
                    $message = "❌ Unable to change password!";
                }
                $stmt->close();
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
<title>Operations Settings</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family: Arial, sans-serif;}
body { background:#f4f4f4; }

/* Top Bar */
.topbar {
    background:#2f8f3f;color:white;padding:15px 25px;
    display:flex;justify-content:space-between;align-items:center;
    position:fixed;top:0;left:0;width:100%;z-index:1000;
}
.topbar h2{font-size:20px;}
.logout{background:white;color:#2f8f3f;padding:8px 15px;border-radius:5px;text-decoration:none;font-weight:bold;}
.logout i{margin-right:5px;}

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

/* Main Content */
.main{flex:1;margin-left:220px;padding:30px;}
.main h2{margin-bottom:20px;color:#333;}

/* Form Boxes */
.table-box{
    background:#fff;padding:20px;border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
    margin-bottom:20px;max-width:500px;
}
.table-box h4{margin-bottom:15px;}
.table-box label{display:block;margin-top:10px;font-weight:bold;}
.table-box input{padding:8px;width:100%;margin-top:5px;margin-bottom:10px;border-radius:4px;border:1px solid #ccc;}

/* Buttons */
.btn{padding:10px 15px;border:none;cursor:pointer;border-radius:5px;font-weight:bold;margin-top:10px; display:flex;align-items:center;gap:5px;}
.btn-submit{background:#2f8f3f;color:#fff;}
.btn-submit:hover{background:#256d31;}

/* Messages */
.message{margin-bottom:15px;font-weight:bold;color:green;}
.message.error{color:red;}
</style>
</head>
<body>

<div class="topbar">
    <h3><i class="fas fa-cog"></i></i> Setting — <?= htmlspecialchars($opsName) ?></h3>
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
        <h2><i class="fas fa-user-cog"></i> Account & Profile Settings</h2>

        <?php if($message): ?>
            <div class="message <?= strpos($message,'❌')!==false ? 'error':'' ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Update Full Name & Email -->
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
