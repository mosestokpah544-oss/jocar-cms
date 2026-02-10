<?php
session_start();
include "db.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);

    if ($username === '' || $email === '') {
        $message = "❌ All fields are required";
    } else {
        $stmt = $conn->prepare("
            SELECT id FROM users 
            WHERE full_name = ? AND email = ? AND status='Active' 
            LIMIT 1
        ");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows !== 1) {
            $message = "❌ User not found";
        } else {
            $user = $res->fetch_assoc();

            // Generate 6-digit code
            $code = rand(100000, 999999);
            $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            $update = $conn->prepare("
                UPDATE users 
                SET reset_code=?, reset_expires=? 
                WHERE id=?
            ");
            $update->bind_param("ssi", $code, $expires, $user['id']);
            $update->execute();

            // SAVE USER ID FOR NEXT STEP
            $_SESSION['reset_user'] = $user['id'];

            // 🔔 EMAIL PLACEHOLDER (replace later)
            // mail($email, "Password Reset Code", "Your code is: $code");

            header("Location: verify_code.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
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
<h2>Forgot Password</h2>

<?php if($message): ?><div class="message"><?= $message ?></div><?php endif; ?>

<form method="POST">
    <input type="text" name="username" placeholder="Username (Full Name)" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <button type="submit">Send Verification Code</button>
</form>
</div>

</body>
</html>
