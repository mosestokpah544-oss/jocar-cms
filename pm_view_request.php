<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: index.php");
    exit;
}

include("db.php");

$pm_id = $_SESSION['user_id'];
$request_id = intval($_GET['id'] ?? 0);

if (!$request_id) {
    die("Invalid request ID.");
}

/* Fetch material request */
$stmt = $conn->prepare("
    SELECT 
        mr.*,
        u.full_name AS supervisor_name,
        p.project_name,
        p.project_code,
        p.project_type
    FROM material_requests mr
    JOIN users u ON mr.site_supervisor_id = u.id
    JOIN projects p ON mr.project_id = p.id
    WHERE mr.id = ? AND p.project_manager_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $request_id, $pm_id);
$stmt->execute();
$request_data = $stmt->get_result()->fetch_assoc();

if (!$request_data) {
    die("Request not found or you don't have access to it.");
}

/* Decode items JSON */
$items = json_decode($request_data['items'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Material Request - <?= htmlspecialchars($request_data['project_name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { font-family:Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
.request-box {
    max-width:900px;
    margin:30px auto;
    background:#fff;
    padding:30px;
    border:2px solid #2f8f3f;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.logo-img { width:180px; }
.project-info { text-align:right; }
.project-info div {
    margin-bottom:5px;
    font-weight:bold;
    color:#2f8f3f;
}
h2 {
    color:#2f8f3f;
    margin-bottom:15px;
    text-align:center;
}
h3 {
    color:#2f8f3f;
    margin-top:25px;
    margin-bottom:10px;
}
table {
    width:100%;
    border-collapse:collapse;
    margin-bottom:20px;
}
th, td {
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:left;
}
th {
    background:#2f8f3f;
    color:#fff;
}
p { margin:5px 0; }
.button {
    padding:10px 20px;
    background:#2f8f3f;
    color:#fff;
    border:none;
    cursor:pointer;
    margin-right:10px;
    border-radius:5px;
}
.button:hover { background:#256d31; }
</style>
</head>
<body>

<div class="request-box">

    <div class="header">
        <img src="IMG_5436.PNG" class="logo-img" alt="Company Logo">
        <div class="project-info">
            <div>Project Type: <?= strtoupper($request_data['project_type']) ?></div>
            <div>Project Code: <?= htmlspecialchars($request_data['project_code']) ?></div>
            <div>Project Name: <?= htmlspecialchars($request_data['project_name']) ?></div>
            <div>Request ID: <?= $request_data['id'] ?></div>
            <div>Date: <?= htmlspecialchars($request_data['request_date']) ?></div>
        </div>
    </div>

    <h2>Material Request Details</h2>

    <h3>Site Supervisor</h3>
    <p><?= htmlspecialchars($request_data['supervisor_name']) ?></p>

    <h3>Requested Items</h3>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($items && is_array($items)): ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item']) ?></td>
                        <td><?= htmlspecialchars($item['qty']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2">No items found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <button class="button" onclick="window.print()">Print Request</button>
    <button class="button" onclick="window.history.back()">Back</button>

</div>

</body>
</html>
