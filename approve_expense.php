<?php
session_start();
include "db.php";

// Only Admin allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    die("Access denied.");
}

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$expense_id = intval($_GET['id']);

// Approve expense
$conn->query("
    UPDATE expenses 
    SET admin_approve = 1, status = 'Approved'
    WHERE id = $expense_id
");

// Redirect back
header("Location: admin_expenses.php");
exit;
?>
