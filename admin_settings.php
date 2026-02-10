<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

include("db.php");


$adminName = 'Admin';

if (isset($_SESSION['user_id'])) {
    $adminQuery = $conn->query("
        SELECT full_name
        FROM users
        WHERE id = {$_SESSION['user_id']}
        LIMIT 1
    ");

    if ($adminQuery && $adminQuery->num_rows > 0) {
        $admin = $adminQuery->fetch_assoc();
        $adminName = $admin['full_name'];
    }
}



$user_id = $_SESSION['user_id'];
$message = "";

/* ================= FETCH CURRENT ADMIN ================= */
$user = $conn->query("
    SELECT full_name, email, password 
    FROM users 
    WHERE id = '$user_id'
")->fetch_assoc();

/* ================= UPDATE PROFILE ================= */
if (isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email     = $conn->real_escape_string($_POST['email']);

    $conn->query("
        UPDATE users 
        SET full_name='$full_name', email='$email' 
        WHERE id='$user_id'
    ");

    $_SESSION['full_name'] = $full_name;
    $message = "✅ Profile updated successfully";
}

/* ================= CHANGE PASSWORD ================= */
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $message = "❌ Current password is incorrect";
    } elseif ($new !== $confirm) {
        $message = "❌ New passwords do not match";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE id='$user_id'");
        $message = "✅ Password changed successfully";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Admin Settings</title>

<style>
* {margin: 0;
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

    position: fixed;   /* fix the topbar */
    top: 0;            /* stick to top */
    left: 0;
    width: 100%;
    z-index: 1000;     /* stays above content */
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

/* Layout */
.container {
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 200px;
    background-color: #256d31;
    min-height: 100vh;
    padding-top: 60px; /* <-- push the content down from the top */
    position: fixed;
    display: flex;
    flex-direction: column;
    justify-content: flex-start; /* keep links stacked from top, but padding pushes them down */
    align-items: center;          /* horizontally center links */
}

.sidebar a {
    display: block;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    font-size: 15px;
}

.sidebar a:hover {
    background-color: #2f8f3f;
}

/* Main Content */
.main {
    flex: 1;
    padding: 30px;
      margin-left: 220px; /* match sidebar width */
    padding: 30px;
}

.main h3 {
    margin-bottom: 20px;
    color: #333;
}

.section {
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    margin-bottom:30px;
}
.section h4 {
    color:#2f8f3f;
    margin-bottom:15px;
}

label { font-weight:bold; font-size:14px; }
input {
    width:100%;
    padding:10px;
    margin:8px 0 15px;
}
button {
    background:#2f8f3f;
    color:#fff;
    padding:10px 20px;
    border:none;
    cursor:pointer;
    border-radius:5px;
}
button:hover { background:#256d31; }

.message {
    margin-bottom:20px;
    font-weight:bold;
}
/* ================= MOBILE RESPONSIVE ================= */
.menu-btn {
    display: none;
    cursor: pointer;
    margin-right: 10px;
}

/* Mobile view */
@media (max-width: 768px) {

    .menu-btn {
        display: inline-block;
    }

    /* Sidebar hidden by default */
    .sidebar {
        position: fixed;
        left: -220px;
        top: 60px;
        height: 100%;
        transition: left 0.3s ease;
        z-index: 2000;
    }

    .sidebar.active {
        left: 0;
    }

    /* Main content full width */
    .main {
        margin-left: 0;
        padding: 20px;
    }

    /* Cards stack vertically */
    .cards {
        grid-template-columns: 1fr;
    }

    /* Tables scroll horizontally */
    .table-box {
        overflow-x: auto;
    }

    table {
        min-width: 600px;
    }

    /* Smaller topbar text */
    .topbar h2 {
        font-size: 16px;
    }
}
@media (max-width: 768px) {
    .card {
        text-align: center;
    }
}

</style>
</head>

<body>

<div class="topbar">
   <h2>
    <i class="fas fa-bars menu-btn" onclick="toggleSidebar()"></i>
    Jocar CMS — <?= htmlspecialchars($adminName) ?>
</h2>

    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">

<div class="sidebar">
    <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
    <a href="admin_office.php"><i class="fas fa-briefcase"></i> Office</a>
    <a href="project.php"><i class="fas fa-diagram-project"></i> Projects</a>
    <a href="admin_payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="admin_expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
    <a href="admin_users.php"><i class="fas fa-users"></i> Users</a>
    <a href="admin_settings.php"><i class="fas fa-gear"></i> Settings</a>
</div>

<div class="main">
<h3><i class="fa-solid fa-user-gear"></i> Account & Profile Settings</h3>

<?php if ($message): ?>
    <div class="message"><?= $message ?></div>
<?php endif; ?>

<!-- PROFILE -->
<div class="section">
<h4><i class="fa-solid fa-user-pen"></i> Update Profile</h4>
<form method="POST">
    <label>Full Name</label>
    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

    <button name="update_profile">
        <i class="fa-solid fa-floppy-disk"></i> Save Changes
    </button>
</form>
</div>

<!-- CHANGE PASSWORD -->
<div class="section">
<h4><i class="fa-solid fa-lock"></i> Change Password</h4>
<form method="POST">
    <label>Current Password</label>
    <input type="password" name="current_password" required>

    <label>New Password</label>
    <input type="password" name="new_password" required>

    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" required>

    <button name="change_password">
        <i class="fa-solid fa-key"></i> Change Password
    </button>
</form>
</div>

</div>
</div>


<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
</script>



</body>
</html>
