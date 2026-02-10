<?php
session_start();
$conn = mysqli_connect("localhost","root","","company_system");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Validate POST and SESSION
if (!isset($_POST['project_id'], $_POST['amount'], $_POST['payment_method'], $_POST['payment_date'])) {
    die("Missing payment data");
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    die("Session expired");
}

$project_id = intval($_POST['project_id']);
$amount = floatval($_POST['amount']);
$method = $_POST['payment_method'];
$date = $_POST['payment_date'];
$user_id = intval($_SESSION['user_id']);
$role = $_SESSION['role'];

/* ================= BLOCK CLOSED PROJECTS ================= */

// Check if project exists and is closed
$checkProjectQuery = "SELECT is_closed, budget FROM projects WHERE id = $project_id LIMIT 1";
$checkProjectResult = mysqli_query($conn, $checkProjectQuery);

if (!$checkProjectResult || mysqli_num_rows($checkProjectResult) == 0) {
    die("Invalid project selected");
}

$checkProject = mysqli_fetch_assoc($checkProjectResult);

if ((int)$checkProject['is_closed'] === 1) {
    die("This project is closed. Payments are not allowed.");
}

$budget = floatval($checkProject['budget']);

// Total already paid
$paidResult = mysqli_query($conn, "SELECT SUM(amount) AS total FROM project_payments WHERE project_id = $project_id");
$paidRow = mysqli_fetch_assoc($paidResult);
$paid = floatval($paidRow['total'] ?? 0);

// VALIDATIONS
if ($amount <= 0) {
    die("Invalid payment amount");
}

if ($amount > ($budget - $paid)) {
    die("Payment exceeds remaining balance");
}

if (strtotime($date) > time()) {
    die("Payment date cannot be in the future");
}

// Generate receipt
$receipt = 'RCPT-' . date('YmdHis');

// Save payment
$stmt = $conn->prepare("
    INSERT INTO project_payments
    (project_id, receipt_number, amount, payment_method, payment_date, entered_by, entered_by_role, created_at)
    VALUES (?,?,?,?,?,?,?,NOW())
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "isdssis",
    $project_id,
    $receipt,
    $amount,
    $method,
    $date,
    $user_id,
    $role
);

if (!$stmt->execute()) {
    die("Insert failed: " . $stmt->error);
}

$stmt->close();

ob_start();

if ($role === 'Admin') {
    header("Location: /Jocab/admin_payments.php");
} else {
    header("Location: /Jocab/finance_payments.php");
}
exit;
?>
