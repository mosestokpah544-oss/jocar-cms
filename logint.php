<?php
session_start();
include "db.php";

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    die("❌ Email and password required");
}

/* ================= FETCH USER ================= */
$sql = "SELECT * FROM users WHERE email = ? AND status = 'Active' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("❌ Invalid email or password");
}

$user = $result->fetch_assoc();

/* ================= PASSWORD CHECK ================= */

// Case 1: OLD MD5 PASSWORD
if (strlen($user['password']) === 32 && md5($password) === $user['password']) {

    // ✅ Auto-upgrade MD5 to password_hash
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $update->bind_param("si", $newHash, $user['id']);
    $update->execute();

}
// Case 2: NEW password_hash PASSWORD
elseif (!password_verify($password, $user['password'])) {
    die("❌ Invalid email or password");
}

/* ================= LOGIN SUCCESS ================= */
$_SESSION['user_id']   = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

/* ================= REDIRECT BY ROLE ================= */
switch ($user['role']) {
    case 'Admin':
        header("Location: admin_dashboard.php");
        break;

    case 'ProjectManager':
        header("Location: project_manager_dashboard.php");
        break;

    case 'FinanceManager':
        header("Location: finance_dashboard.php");
        break;

    default:
        header("Location: login.html");
        break;
}

exit;
