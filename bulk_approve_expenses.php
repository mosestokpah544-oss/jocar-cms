<?php
session_start();
include("db.php");

if (!isset($_POST['expense_ids'])) {
    header("Location: admin_expenses.php");
    exit;
}

$ids = $_POST['expense_ids'];

foreach ($ids as $id) {
    $id = (int)$id;
    $conn->query("
        UPDATE expenses 
        SET status='Approved', admin_approved=1 
        WHERE id=$id AND status='Pending'
    ");
}

header("Location: admin_expenses.php");
exit;
