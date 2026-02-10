<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

if (!isset($_POST['expense_id'])) {
    die("Invalid request");
}

$expense_id = intval($_POST['expense_id']);

// Send to Finance
$conn->query("
    UPDATE expenses 
    SET status = 'Pending_Finance' 
    WHERE id = $expense_id
");

header("Location: admin_expenses.php");
exit;
?>
