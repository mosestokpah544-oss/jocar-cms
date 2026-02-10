<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_users.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: admin_users.php");
    exit;
}

// Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email     = $_POST['email'];
    $role      = $_POST['role'];
    $status    = $_POST['status'];

    $update = $conn->prepare("
        UPDATE users 
        SET full_name=?, email=?, role=?, status=? 
        WHERE id=?
    ");
    $update->bind_param("ssssi", $full_name, $email, $role, $status, $user_id);
    $update->execute();

    header("Location: admin_users.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit User</title>
<style>
body { font-family:Arial; background:#f4f4f4; padding:30px; }
.form-box { background:#fff; padding:20px; max-width:500px; margin:auto; border-radius:8px; }
input, select { width:100%; padding:8px; margin-bottom:10px; }
button { background:#2f8f3f; color:#fff; padding:10px; border:none; width:100%; }
</style>
</head>
<body>

<div class="form-box">
<h3>Edit User</h3>

<form method="POST">
    <label>Full Name</label>
    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

    <label>Role</label>
    <select name="role">
        <option value="Admin" <?= $user['role']=='Admin'?'selected':'' ?>>Admin</option>
        <option value="ProjectManager" <?= $user['role']=='ProjectManager'?'selected':'' ?>>Project Manager</option>
        <option value="Finance" <?= $user['role']=='Finance'?'selected':'' ?>>Finance</option>
    </select>

    <label>Status</label>
    <select name="status">
        <option value="Active" <?= $user['status']=='Active'?'selected':'' ?>>Active</option>
        <option value="Inactive" <?= $user['status']=='Inactive'?'selected':'' ?>>Inactive</option>
    </select>

    <button type="submit">Update User</button>
</form>
</div>

</body>
</html>
