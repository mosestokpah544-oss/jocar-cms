<?php
session_start();
include "db.php";

// Only Project Manager can submit
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ProjectManager') {
    header("Location: login.html");
    exit;
}

// ================= COLLECT VALUES =================
$expense_title = $_POST['expense_title'] ?? '';
$expense_type  = $_POST['expense_type'] ?? '';
$project_id    = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$status        = $_POST['status'];
$created_by    = $_SESSION['user_id'];
$expense_id    = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

// ================= FILE UPLOAD (GLOBAL) =================
$proofFile = null;

if (isset($_FILES['expense_proof']) && $_FILES['expense_proof']['error'] === 0) {

    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($_FILES['expense_proof']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Invalid file type. Only PDF, JPG, PNG allowed.");
    }

    if (!is_dir("uploads/expenses")) {
        mkdir("uploads/expenses", 0777, true);
    }

    $newName = time() . "_" . uniqid() . "." . $ext;
    $target  = "uploads/expenses/" . $newName;

    if (!move_uploaded_file($_FILES['expense_proof']['tmp_name'], $target)) {
        die("File upload failed.");
    }

    $proofFile = $newName;
}

// =================================================
// 1️⃣ SUBMIT EXISTING DRAFT (STATUS ONLY)
// =================================================
if ($expense_id > 0 && $status === 'Pending' && empty($_POST['item_name'])) {

    if ($proofFile) {
        $stmt = $conn->prepare("
            UPDATE expenses 
            SET status='Pending', proof_file=? 
            WHERE id=? AND created_by=?
        ");
        $stmt->bind_param("sii", $proofFile, $expense_id, $created_by);
    } else {
        $stmt = $conn->prepare("
            UPDATE expenses 
            SET status='Pending' 
            WHERE id=? AND created_by=?
        ");
        $stmt->bind_param("ii", $expense_id, $created_by);
    }

    $stmt->execute();
    header("Location: pm_expenses.php");
    exit;
}

// =================================================
// 2️⃣ CALCULATE GRAND TOTAL
// =================================================
$grand_total = 0;
if (!empty($_POST['qty']) && !empty($_POST['unit_cost'])) {
    for ($i = 0; $i < count($_POST['qty']); $i++) {
        $grand_total += floatval($_POST['qty'][$i]) * floatval($_POST['unit_cost'][$i]);
    }
}

// =================================================
// 3️⃣ INSERT OR UPDATE EXPENSE
// =================================================
if ($expense_id > 0) {

    if ($proofFile) {
        $stmt = $conn->prepare("
            UPDATE expenses 
            SET expense_title=?, expense_type=?, project_id=?, grand_total=?, status=?, proof_file=?
            WHERE id=? AND created_by=?
        ");
        $stmt->bind_param(
            "ssidsisi",
            $expense_title,
            $expense_type,
            $project_id,
            $grand_total,
            $status,
            $proofFile,
            $expense_id,
            $created_by
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE expenses 
            SET expense_title=?, expense_type=?, project_id=?, grand_total=?, status=?
            WHERE id=? AND created_by=?
        ");
        $stmt->bind_param(
            "ssidsii",
            $expense_title,
            $expense_type,
            $project_id,
            $grand_total,
            $status,
            $expense_id,
            $created_by
        );
    }

    $stmt->execute();
    $conn->query("DELETE FROM expense_items WHERE expense_id=$expense_id");

} else {

    $created_by_role = 'ProjectManager';

    $stmt = $conn->prepare("
        INSERT INTO expenses (
            expense_title, expense_type, project_id, grand_total, status,
            created_by, created_by_role,
            admin_approved, finance_approved, finance_approve,
            proof_file, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, NOW())
    ");

    $stmt->bind_param(
        "ssidsiss",
        $expense_title,
        $expense_type,
        $project_id,
        $grand_total,
        $status,
        $created_by,
        $created_by_role,
        $proofFile
    );

    $stmt->execute();
    $expense_id = $stmt->insert_id;
}

// =================================================
// 4️⃣ INSERT ITEMS
// =================================================
if (!empty($_POST['item_name'])) {
    for ($i = 0; $i < count($_POST['item_name']); $i++) {
        if ($_POST['qty'][$i] > 0 && $_POST['unit_cost'][$i] > 0) {
            $stmtItem = $conn->prepare("
                INSERT INTO expense_items (expense_id, item_name, qty, unit_cost, total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $total = $_POST['qty'][$i] * $_POST['unit_cost'][$i];
            $stmtItem->bind_param(
                "isidd",
                $expense_id,
                $_POST['item_name'][$i],
                $_POST['qty'][$i],
                $_POST['unit_cost'][$i],
                $total
            );
            $stmtItem->execute();
        }
    }
}

// =================================================
// 5️⃣ REDIRECT
// =================================================
header("Location: pm_expenses.php");
exit;
