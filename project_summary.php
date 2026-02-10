<?php
/**
 * Project Summary
 * READ-ONLY FILE
 * Performs ALL financial calculations
 */

require_once "db.php";

function getProjectSummary(mysqli $conn, int $project_id): array
{
    /* ================= PROJECT INFO ================= */
    $project = $conn->query("
        SELECT project_name, project_type, budget, is_closed
        FROM projects
        WHERE id = $project_id
    ")->fetch_assoc();

    if (!$project) {
        return [];
    }

    $project_budget = (float)$project['budget'];
    $is_closed = (int)$project['is_closed'];

    /* ================= CLIENT PAYMENTS ================= */
    $paid = $conn->query("
        SELECT IFNULL(SUM(amount),0) AS total_paid
        FROM project_payments
        WHERE project_id = $project_id
    ")->fetch_assoc();

    $total_paid = (float)$paid['total_paid'];

    /* ================= PROJECT EXPENSES (PAID ONLY) ================= */
    $project_expenses = $conn->query("
        SELECT IFNULL(SUM(grand_total),0) AS total
        FROM expenses
        WHERE project_id = $project_id
          AND payment_status = 'Paid'
    ")->fetch_assoc();

    $project_expenses = (float)$project_expenses['total'];

    /* ================= PROJECT PURCHASE REQUESTS (PAID ONLY) ================= */
    $project_requests = $conn->query("
        SELECT IFNULL(SUM(pri.quantity * pri.unit_cost),0) AS total
        FROM purchase_requests pr
        JOIN purchase_request_items pri ON pr.id = pri.request_id
        WHERE pr.request_type = 'Project'
          AND pr.project_id = $project_id
          AND pr.payment_status = 'Paid'
    ")->fetch_assoc();

    $project_requests = (float)$project_requests['total'];

    /* ================= FREEZE MONEY IF PROJECT IS CLOSED ================= */
    if ($is_closed === 1) {
        $project_expenses = 0;
        $project_requests = 0;
    }

    /* ================= TOTAL PROJECT MONEY OUT ================= */
    $total_project_out = $project_expenses + $project_requests;

    /* ================= PROFIT ================= */
    $gross_profit = $project_budget - $total_project_out;

    return [
        'project_name'        => $project['project_name'],
        'project_type'        => $project['project_type'],
        'project_budget'      => $project_budget,

        'total_paid'          => $total_paid,
        'client_owes'         => $project_budget - $total_paid,

        'project_expenses'    => $project_expenses,
        'project_requests'    => $project_requests,
        'total_project_out'   => $total_project_out,

        'gross_profit'        => $gross_profit
    ];
}

/**
 * Office Summary (GLOBAL)
 * READ-ONLY
 * Single source of truth for office finances
 */
function getOfficeSummary(mysqli $conn): array
{
    /* ===== OFFICE BUDGET ===== */
    $budget = $conn->query("
        SELECT IFNULL(amount,0) AS amount
        FROM office_budget
        WHERE budget_month = MONTH(CURDATE())
          AND budget_year  = YEAR(CURDATE())
        LIMIT 1
    ")->fetch_assoc();

    $office_budget = (float)($budget['amount'] ?? 0);

    /* ===== OFFICE SPENT (PAID ONLY) ===== */
    $spent = $conn->query("
        SELECT IFNULL(SUM(pri.quantity * pri.unit_cost),0) AS total
        FROM purchase_requests pr
        JOIN purchase_request_items pri ON pr.id = pri.request_id
        WHERE pr.request_type = 'Office'
          AND pr.payment_status = 'Paid'
    ")->fetch_assoc();

    $office_spent = (float)($spent['total'] ?? 0);

    /* ===== BALANCE ===== */
    $office_balance = $office_budget - $office_spent;

    return [
        'office_budget'  => $office_budget,
        'office_spent'   => $office_spent,
        'office_balance' => $office_balance
    ];
}
