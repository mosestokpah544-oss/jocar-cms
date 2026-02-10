<?php
include("db.php");
$project_id = intval($_GET['project_id']);
include("project_financials.php"); // the function we just made

$data = getProjectFinancials($conn, $project_id);
echo json_encode($data);
?>
