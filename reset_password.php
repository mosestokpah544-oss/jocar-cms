<?php
session_start();
include "db.php";

if (!isset($_SESSION['reset_user'], $_SESSION['code_verified'])) {
    header("Location: forgot_password.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($new !== $confirm) {
        $message = "❌ Passwords do not match";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users 
            SET password=?, reset_code=NULL, reset_expires=NULL 
            WHERE id=?
        ");
        $stmt->bind_param("si", $hash, $_SESSION['reset_user']);
        $stmt->execute();

        session_destroy();
        $message = "✅ Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
<style>
body{font-family:Arial;background:#f4f4f4;height:100vh;display:flex;justify-content:center;align-items:center}
.box{background:#fff;padding:40px;border-radius:10px;width:350px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,.2)}
input{width:100%;padding:12px;margin:10px 0;border-radius:6px;border:1px solid #ccc}
button{width:100%;padding:12px;background:#2f8f3f;color:#fff;border:none;border-radius:6px}
.message{font-weight:bold;margin-bottom:10px;color:green}
</style>
</head>
<body>

<div class="box">
<h2>Reset Password</h2>

<?php if($message): ?><div class="message"><?= $message ?></div><?php endif; ?>

<form method="POST">
    <input type="password" name="password" placeholder="New Password" required>
    <input type="password" name="confirm" placeholder="Confirm Password" required>
    <button type="submit">Update Password</button>
</form>
</div>

</body>
</html>
