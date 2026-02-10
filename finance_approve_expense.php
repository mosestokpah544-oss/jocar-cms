<?php
session_start();
include "db.php";

// Only Finance allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    die("Access denied.");
}

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$expense_id = intval($_GET['id']);

// Mark finance approval
$conn->query("
    UPDATE expenses 
    SET finance_approve = 1
    WHERE id = $expense_id
");

// If Admin already approved, mark fully approved
$conn->query("
    UPDATE expenses 
    SET status = 'Approved'
    WHERE id = $expense_id AND admin_approve = 1
");

// Redirect back
header("Location: finance_expenses.php");
exit;
?>
