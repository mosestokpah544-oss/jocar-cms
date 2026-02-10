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



// Handle new user submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email     = $conn->real_escape_string($_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role      = $conn->real_escape_string($_POST['role']);
    $status    = 'Active';

    $conn->query("
        INSERT INTO users (full_name, email, password, role, status, created_at)
        VALUES ('$full_name','$email','$password','$role','$status', NOW())
    ");
}

// Fetch all users
$users = $conn->query("SELECT id, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<title>Admin - Users</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial,sans-serif; }
body { margin: 0;
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
.cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
.card { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.card h4 { color:#2f8f3f; margin-bottom:10px; }
.card p { font-size:22px; font-weight:bold; color:#333; }
.section { margin-top:30px; }
.table-box { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
table th, table td { padding:10px; border-bottom:1px solid #ddd; font-size:14px; }
table th { background:#f0f0f0; text-align:left; }
input, select { padding:8px; width:100%; margin-top:5px; margin-bottom:10px; }
.btn { padding:10px 15px; border:none; cursor:pointer; border-radius:5px; font-weight:bold; }
.btn-submit { background:#2f8f3f; color:#fff; }
.status-active { color:green; font-weight:bold; }
.status-inactive { color:red; font-weight:bold; }
.action-btn {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    text-decoration: none;
    font-weight: bold;
    margin-right: 5px;
    display: inline-block;
}

.btn-edit {
    background-color: #2f8f3f;
    color: #fff;
}

.btn-edit:hover {
    background-color: #256d31;
}

.btn-delete {
    background-color: #e53935;
    color: #fff;
}

.btn-delete:hover {
    background-color: #c62828;
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
<h3><i class="fa-solid fa-users"></i> Manage Users</h3>

<!-- Add New User Form -->
<div class="section">
    <h4><i class="fa-solid fa-user-plus"></i> Add New User</h4>
    <div class="table-box">
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="full_name" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Role</label>
<select name="role" required>
    <option value="">Select Role</option>
    <option value="ProjectManager">Project Manager</option>
    <option value="Finance">Finance</option>
    <option value="Operations">Operations</option>
    <option value="Procurement">Procurement</option>
</select>

           <button type="submit" class="btn btn-submit">
    <i class="fa-solid fa-user-plus"></i> Add User
</button>

        </form>
    </div>
</div>

<!-- User List -->
<div class="section">
    <h4><i class="fa-solid fa-list"></i> All Users</h4>
    <div class="table-box">
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>

            <?php while($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td class="<?= $u['status']=='Active' ? 'status-active' : 'status-inactive' ?>">
                    <?= htmlspecialchars($u['status']) ?>
                </td>
                <td><?= htmlspecialchars($u['created_at']) ?></td>
                <td>
                   <a href="admin_edit_user.php?id=<?= $u['id'] ?>" 
   class="action-btn btn-edit">
   <i class="fa-solid fa-pen-to-square"></i> Edit
</a>


<?php if ($u['id'] != $_SESSION['user_id']): ?>
<a href="admin_delete_user.php?id=<?= $u['id'] ?>" 
   class="action-btn btn-delete"
   onclick="return confirm('Are you sure you want to delete this user?');">
   <i class="fa-solid fa-trash"></i> Delete
</a>

<?php endif; ?>

                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
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
