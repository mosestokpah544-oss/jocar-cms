<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.html");
    exit;
}

$expense_id = intval($_POST['expense_id']);
$action = $_POST['action'];

if ($action == 'approve') {
    $conn->query("UPDATE expenses SET status='Pending_Finance' WHERE id = $expense_id");
}
elseif ($action == 'reject') {
    $conn->query("UPDATE expenses SET status='Rejected' WHERE id = $expense_id");
}

header("Location: admin_expenses.php");
exit;
?>
