<?php
session_start();
include("db.php");

if (!isset($_SESSION['role'])) {
    header("Location: login.html");
    exit;
}

$role = $_SESSION['role'];
$id = intval($_GET['id']);

// Get expense info
$result = $conn->query("SELECT created_by_role FROM expenses WHERE id=$id");
if ($result->num_rows == 0) {
    die("Expense not found");
}

$row = $result->fetch_assoc();
$creatorRole = $row['created_by_role'];

// ===== ROLE RULES =====
if ($role === 'Admin' && $creatorRole !== 'Manager') {
    die("You can only reject Project Manager expenses.");
}

if ($role === 'Finance' && $creatorRole !== 'Admin') {
    die("You can only reject Admin expenses.");
}

// ===== REJECT =====
$conn->query("UPDATE expenses SET status='Rejected' WHERE id=$id");

header("Location: admin_expenses.php");
exit;
?>
