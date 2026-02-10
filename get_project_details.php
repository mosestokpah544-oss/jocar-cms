<?php
session_start();
if (!isset($_SESSION['role'])) {
    die(json_encode(['error'=>'Access denied']));
}

$conn = mysqli_connect("localhost","root","","company_system");

$project_id = intval($_GET['project_id']);

// Get project details
$project = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT project_name, client_name, budget FROM projects WHERE id = $project_id
"));

// Get total paid for this project
$paid = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(amount) AS total_paid FROM project_payments WHERE project_id = $project_id
"))['total_paid'] ?? 0;

$data['remaining_balance'] = $projectBudget - $paidAmount - $approvedExpenses;


$remaining = ($project['budget'] ?? 0) - $paid;

echo json_encode([
    'project_name' => $project['project_name'],
    'client_name' => $project['client_name'],
    'total_amount' => $project['budget'],
    'amount_paid' => $paid,
    'remaining_balance' => $remaining
]);
