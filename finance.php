<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* ============================================================
   COMBINED EXPENSE QUERY
   Manual expenses + inventory purchases
============================================================ */

$expenseBaseSql = "
    SELECT
        expense_id AS record_id,
        category,
        description,
        quantity,
        unit_cost,
        total_cost,
        receipt,
        record_date,
        recorded_by,
        'Manual Expense' AS source,
        created_at
    FROM expenses

    UNION ALL

    SELECT
        inventory_id AS record_id,
        category,
        item_name AS description,
        quantity,
        unit_cost,
        total_cost,
        receipt,
        record_date,
        recorded_by,
        'Inventory Purchase' AS source,
        created_at
    FROM inventory
";


/* ============================================================
   FINANCIAL DASHBOARD STATISTICS
============================================================ */

/* Today's Income */

$todayIncomeQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(amount), 0) AS total
    FROM income
    WHERE DATE(transaction_date) = CURDATE()
");

$todayIncome = mysqli_fetch_assoc($todayIncomeQuery)['total'] ?? 0;


/* Today's Expenses */

$todayExpenseQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE DATE(record_date) = CURDATE()
");

$todayExpense = mysqli_fetch_assoc($todayExpenseQuery)['total'] ?? 0;


/* Monthly Income */

$monthlyIncomeQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(amount), 0) AS total
    FROM income
    WHERE MONTH(transaction_date) = MONTH(CURDATE())
    AND YEAR(transaction_date) = YEAR(CURDATE())
");

$monthlyIncome = mysqli_fetch_assoc($monthlyIncomeQuery)['total'] ?? 0;


/* Monthly Expenses */

$monthlyExpenseQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE MONTH(record_date) = MONTH(CURDATE())
    AND YEAR(record_date) = YEAR(CURDATE())
");

$monthlyExpense = mysqli_fetch_assoc($monthlyExpenseQuery)['total'] ?? 0;


/* Net Profit */

$netProfit = $monthlyIncome - $monthlyExpense;


/* Cash Available */

$cashQuery = mysqli_query($conn, "
    SELECT
    (
        SELECT IFNULL(SUM(amount), 0)
        FROM income
    )
    -
    (
        SELECT IFNULL(SUM(total_cost), 0)
        FROM (
            $expenseBaseSql
        ) AS all_expenses
    ) AS cash
");

$cash = mysqli_fetch_assoc($cashQuery)['cash'] ?? 0;


/* Largest Expense Category */

$largestExpenseQuery = mysqli_query($conn, "
    SELECT
        category,
        SUM(total_cost) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    GROUP BY category
    ORDER BY total DESC
    LIMIT 1
");

$largestExpenseRow = mysqli_fetch_assoc($largestExpenseQuery);

$largestExpense = $largestExpenseRow['category'] ?? "N/A";


/* Outstanding Payments Placeholder */

$outstanding = 0;


/* ============================================================
   RECENT INCOME
============================================================ */

$recentIncomeQuery = mysqli_query($conn, "
    SELECT
        i.*,
        fc.category_name,
        c.customer_name,
        pm.method_name
    FROM income i

    LEFT JOIN customers c
        ON c.customer_id = i.customer_id

    LEFT JOIN financial_categories fc
        ON fc.category_id = i.category_id

    LEFT JOIN payment_methods pm
        ON pm.payment_method_id = i.payment_method

    ORDER BY i.income_id DESC

    LIMIT 10
");


/* ============================================================
   RECENT EXPENSES
============================================================ */

$recentExpenseQuery = mysqli_query($conn, "
    SELECT *
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    ORDER BY record_date DESC, created_at DESC
    LIMIT 10
");


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-wallet"></i>
                Financial Dashboard
            </h1>

            <p>
                View income, expenses, profit, cash flow, and inventory-based costs.
            </p>
        </div>

    </div>


    <!-- ======================================================
         KPI CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-coins"></i>
            </div>

            <div>
                <h4>Today's Income</h4>
                <h2>KSh <?= number_format($todayIncome, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-money-bill-wave"></i>
            </div>

            <div>
                <h4>Today's Expenses</h4>
                <h2>KSh <?= number_format($todayExpense, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-chart-line"></i>
            </div>

            <div>
                <h4>Monthly Income</h4>
                <h2>KSh <?= number_format($monthlyIncome, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-chart-area"></i>
            </div>

            <div>
                <h4>Monthly Expenses</h4>
                <h2>KSh <?= number_format($monthlyExpense, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-piggy-bank"></i>
            </div>

            <div>
                <h4>Net Profit</h4>
                <h2>KSh <?= number_format($netProfit, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-university"></i>
            </div>

            <div>
                <h4>Cash Available</h4>
                <h2>KSh <?= number_format($cash, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>

            <div>
                <h4>Outstanding</h4>
                <h2>KSh <?= number_format($outstanding, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-fire"></i>
            </div>

            <div>
                <h4>Largest Expense</h4>
                <h3><?= htmlspecialchars($largestExpense); ?></h3>
            </div>

        </div>

    </div>


    <!-- ======================================================
         RECENT INCOME
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-arrow-circle-down"></i>
                Recent Income
            </h3>

            <a href="income_records.php" class="btn btn-success">
                <i class="fas fa-eye"></i>
                View All
            </a>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>

                    <tr>
                        <th>Transaction</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($recentIncomeQuery) > 0) { ?>

                        <?php while ($income = mysqli_fetch_assoc($recentIncomeQuery)) { ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($income['transaction_no'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($income['transaction_date'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($income['category_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($income['customer_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <strong>
                                        KSh <?= number_format($income['amount'], 2); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($income['method_name'] ?? 'N/A'); ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="6" style="text-align:center;">
                                No income records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         RECENT EXPENSES
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-money-check-alt"></i>
                Recent Expenses
            </h3>

            <a href="expenses.php" class="btn btn-success">
                <i class="fas fa-eye"></i>
                View All
            </a>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>

                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Source</th>
                        <th>Recorded By</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($recentExpenseQuery) > 0) { ?>

                        <?php while ($expense = mysqli_fetch_assoc($recentExpenseQuery)) { ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($expense['record_date']); ?>
                                </td>

                                <td>
                                    <span class="badge-danger">
                                        <?= htmlspecialchars($expense['category']); ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($expense['description']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format($expense['quantity'], 2); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($expense['unit_cost'], 2); ?>
                                </td>

                                <td>
                                    <strong class="text-danger">
                                        KSh <?= number_format($expense['total_cost'], 2); ?>
                                    </strong>
                                </td>

                                <td>

                                    <?php if ($expense['source'] === 'Inventory Purchase') { ?>

                                        <span class="badge-success">
                                            Inventory Purchase
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge-danger">
                                            Manual Expense
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($expense['recorded_by'] ?? 'System'); ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="8" style="text-align:center;">
                                No expense records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         QUICK ACTIONS
    ======================================================= -->

    <div class="cards">

        <a href="add_income.php" class="dashboard-action-card">
            <i class="fas fa-plus-circle"></i>
            <h4>Add Income</h4>
        </a>

        <a href="add_expenses.php" class="dashboard-action-card">
            <i class="fas fa-minus-circle"></i>
            <h4>Add Expense</h4>
        </a>

        <a href="income_records.php" class="dashboard-action-card">
            <i class="fas fa-list"></i>
            <h4>Income Records</h4>
        </a>

        <a href="expenses.php" class="dashboard-action-card">
            <i class="fas fa-file-invoice-dollar"></i>
            <h4>Expense Records</h4>
        </a>

        <a href="reports.php" class="dashboard-action-card">
            <i class="fas fa-chart-pie"></i>
            <h4>Reports</h4>
        </a>

        <a href="cash_flow.php" class="dashboard-action-card">
            <i class="fas fa-wallet"></i>
            <h4>Cash Flow</h4>
        </a>

    </div>

</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".card").forEach(function (card) {

        card.addEventListener("mouseenter", function () {
            card.style.transform = "translateY(-6px)";
            card.style.transition = ".3s";
        });

        card.addEventListener("mouseleave", function () {
            card.style.transform = "translateY(0)";
        });

    });


    document.querySelectorAll(".custom-table tbody tr").forEach(function (row) {

        row.addEventListener("mouseover", function () {
            row.style.background = "#E8F5E9";
        });

        row.addEventListener("mouseout", function () {
            row.style.background = "";
        });

    });

});
</script>

<?php include 'includes/footer.php'; ?>