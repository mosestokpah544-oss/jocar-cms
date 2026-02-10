<?php
session_start();
include("db.php");
include("project_summary.php");

// Protect API: only logged-in Project Managers
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate project_id
$project_id = (int)($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    echo json_encode([]);
    exit;
}

// Fetch project summary using the function
$data = getProjectSummary($conn, $project_id);

// Return empty if invalid
if (!$data) {
    echo json_encode([]);
    exit;
}

// Map to JS-friendly format
$response = [
    'project_name' => $data['project_name'],
    'budget'       => $data['project_budget'],
    'total_paid'   => $data['total_paid'],
    'total_spent'  => $data['total_project_out'],
    'cash_left'    => $data['gross_profit'],
    'client_owes'  => $data['client_owes']
];

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
