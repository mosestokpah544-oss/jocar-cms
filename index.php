<?php
session_start();
include "db.php";

$message = '';
$success = false;

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($email === '' || $password === '') {
        $message = "❌ Email and password required";
    } else {
        $sql = "SELECT * FROM users WHERE email=? AND status='Active' LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $message = "❌ Invalid email or password";
        } else {
            $user = $result->fetch_assoc();

            // Check if password is MD5 legacy
            if (strlen($user['password']) === 32 && md5($password) === $user['password']) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $update->bind_param("si", $newHash, $user['id']);
                $update->execute();
            }

            // Check modern hashed password
            if (!password_verify($password, $user['password'])) {
                $message = "❌ Invalid email or password";
            }

            // If no error, login successful
            if ($message === '') {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                // ✅ SERVER-SIDE REDIRECT BASED ON ROLE
switch ($user['role']) {
    case 'Admin':
        header("Location: /Jocab/admin_dashboard.php");
        break;
    case 'ProjectManager':
        header("Location: /Jocab/project_manager_dashboard.php");
        break;
    case 'Finance':
    header("Location: finance_dashboard.php");
    break;

    case 'Operations':
        header("Location: /Jocab/operations_dashboard.php");
        break;
    case 'Procurement':
        header("Location: /Jocab/procurement_dashboard.php");
        break;
    case 'SiteSupervisor':
        header("Location: /Jocab/site_supervisor_dashboard.php");
        break;
    default:
        header("Location: /Jocab/login.html");
}
exit;

            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }

body {
    background: #f4f4f4;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.login-box {
    background: #fff;
    padding: 40px;
    border-radius: 14px;
    width: 380px;
    text-align: center;
    box-shadow: 0 6px 25px rgba(0,0,0,0.15);
}

.system-icon {
    background: #2f8f3f;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.system-icon i {
    color: #fff;
    font-size: 36px;
}

h2 {
    margin-bottom: 25px;
    color: #2f8f3f;
    font-size: 22px;
    font-weight: bold;
}

.input-group {
    position: relative;
    margin-bottom: 15px;
}

.input-group i {
    position: absolute;
    top: 50%;
    left: 12px;
    transform: translateY(-50%);
    color: #999;
}

.input-group input {
    width: 100%;
    padding: 12px 12px 12px 40px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 15px;
}

button {
    width: 100%;
    padding: 12px;
    background: #2f8f3f;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
}

button i {
    margin-right: 6px;
}

button:hover {
    background: #256d31;
}

.message {
    margin-bottom: 15px;
    font-weight: bold;
    font-size: 14px;
}

.message.success { color: green; }
.message.error { color: red; }

.forgot-link {
    display: block;
    margin-top: 15px;
    font-size: 14px;
    color: #2f8f3f;
    text-decoration: none;
}

.forgot-link i {
    margin-right: 4px;
}

.forgot-link:hover {
    text-decoration: underline;
}

/* Logo / Banner Image */
.system-icon img, .banner img {
    display: block;
    margin: 0 auto 20px;
    max-width: 250px;
    max-height: 150px;
    width: auto;
    height: auto;
}

.banner img {
    max-width: 300px;
    height: auto;
}

.login-container {
    position: relative;       
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    padding-bottom: 100px;
}

.watermark {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0.05;
    max-width: 250px;
    pointer-events: none;
    z-index: 1;
}

.login-box {
    position: relative;
    z-index: 2;
}
</style>
</head>
<body>

<div class="login-container">

<img src="IMG_5437.PNG" alt="Watermark" class="watermark">

<div class="login-box">

   <div class="banner">
    <img src="IMG_5436.PNG" alt="System Banner">
</div>

<h2>Internal Management System</h2>

<?php if($message): ?>
    <div class="message <?= $success ? 'success' : 'error' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<form method="POST">

    <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>" required>
    </div>

    <div class="input-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" placeholder="Password" required>
    </div>

    <button type="submit">
        <i class="fas fa-sign-in-alt"></i> Login
    </button>

    <a href="forgot_password.php" class="forgot-link">
        <i class="fas fa-key"></i> Forgot Password?
    </a>

</form>
</div>
</div>

</body>
</html>
