<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $expense_type  = $_POST['expense_type'];
    $project_id    = !empty($_POST['project_id']) ? $_POST['project_id'] : NULL;
    $expense_title = $_POST['expense_title'];
    $status        = $_POST['status'];   // Draft or Pending

    $created_by = $_SESSION['user_id'];
    $created_at = date("Y-m-d H:i:s");

    // Insert main expense
    $stmt = $conn->prepare("
        INSERT INTO expenses (expense_type, project_id, expense_title, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sissis", $expense_type, $project_id, $expense_title, $status, $created_by, $created_at);
    $stmt->execute();

    $expense_id = $stmt->insert_id;

    // Insert items
    $grand_total = 0;

    for ($i = 0; $i < count($_POST['item_name']); $i++) {

        $item = $_POST['item_name'][$i];
        $qty = isset($_POST['qty'][$i]) ? floatval($_POST['qty'][$i]) : 0;
        $unit_cost = isset($_POST['unit_cost'][$i]) ? floatval($_POST['unit_cost'][$i]) : 0;

        if ($item == "" || $qty <= 0 || $unit_cost <= 0) continue;

        $total = $qty * $unit_cost;
        $grand_total += $total;

        $stmtItem = $conn->prepare("
            INSERT INTO expense_items (expense_id, item_name, qty, unit_cost, total)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtItem->bind_param("isddd", $expense_id, $item, $qty, $unit_cost, $total);
        $stmtItem->execute();
    }

    // Update grand total
    $stmtUpdate = $conn->prepare("UPDATE expenses SET grand_total=? WHERE id=?");
    $stmtUpdate->bind_param("di", $grand_total, $expense_id);
    $stmtUpdate->execute();

    header("Location: admin_expenses.php?success=1");
    exit;
}
?>
