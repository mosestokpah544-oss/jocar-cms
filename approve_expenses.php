<?php
session_start();
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FinanceManager') {
    header("Location: login.html");
    exit;
}

if (isset($_POST['expense_ids'])) {

    foreach ($_POST['expense_ids'] as $id) {
        $stmt = $conn->prepare("UPDATE expenses SET status='Approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}

header("Location: finance_expenses.php");
exit;
?>
