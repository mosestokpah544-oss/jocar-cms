<?php
session_start();
include("db.php");

/* ===== AUTH CHECK ===== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Finance') {
    header("Location: index.php");
    exit;
}

/* ===== VALIDATE INPUT ===== */
$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!$type || $id <= 0) {
    die("Invalid request");
}

/* ===== PROCESS PAYMENT ===== */
if ($type === 'expense') {

    // Pay expense (only if unpaid)
    $stmt = $conn->prepare("
        UPDATE expenses 
        SET payment_status = 'Paid', paid_at = NOW()
        WHERE id = ? AND payment_status != 'Paid'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

} elseif ($type === 'request') {

    // Pay purchase request (only if unpaid)
    $stmt = $conn->prepare("
        UPDATE purchase_requests 
        SET payment_status = 'Paid'
        WHERE id = ? AND payment_status != 'Paid'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

} else {
    die("Invalid payment type");
}

/* ===== REDIRECT BACK ===== */
header("Location: finance_expenses.php");
exit;
