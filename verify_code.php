<?php
session_start();
include "db.php";

if (!isset($_SESSION['reset_user'])) {
    header("Location: forgot_password.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $uid  = $_SESSION['reset_user'];

    $stmt = $conn->prepare("
        SELECT reset_code, reset_expires 
        FROM users WHERE id=? LIMIT 1
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $code !== $user['reset_code']) {
        $message = "❌ Invalid verification code";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $message = "❌ Code expired";
    } else {
        $_SESSION['code_verified'] = true;
        header("Location: reset_password.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Verify Code</title>
<style>
body{font-family:Arial;background:#f4f4f4;height:100vh;display:flex;justify-content:center;align-items:center}
.box{background:#fff;padding:40px;border-radius:10px;width:350px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,.2)}
input{width:100%;padding:12px;margin:10px 0;border-radius:6px;border:1px solid #ccc}
button{width:100%;padding:12px;background:#2f8f3f;color:#fff;border:none;border-radius:6px}
.message{color:red;font-weight:bold;margin-bottom:10px}
</style>
</head>
<body>

<div class="box">
<h2>Enter Verification Code</h2>

<?php if($message): ?><div class="message"><?= $message ?></div><?php endif; ?>

<form method="POST">
    <input type="text" name="code" placeholder="6-digit code" required>
    <button type="submit">Verify</button>
</form>
</div>

</body>
</html>
