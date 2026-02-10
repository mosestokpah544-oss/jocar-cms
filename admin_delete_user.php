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

// Prevent admin deleting self
if ($user_id === $_SESSION['user_id']) {
    header("Location: admin_users.php");
    exit;
}

$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

header("Location: admin_users.php");
exit;
