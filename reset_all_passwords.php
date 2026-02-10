<?php
include "db.php";

$newPassword = password_hash("123456", PASSWORD_DEFAULT);

$conn->query("UPDATE users SET password = '$newPassword'");

echo "All passwords reset to: 123456";
