<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

$id = $_POST['id'];

$conn->query("UPDATE expenses SET status='Pending' WHERE id=$id");

header("Location: admin_expenses.php");
exit;
?>
