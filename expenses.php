<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
   CATEGORY FILTER
============================================================ */

$category_filter = "";

if (isset($_GET['category']) && $_GET['category'] !== "") {
    $category = mysqli_real_escape_string($conn, $_GET['category']);

    $category_filter = "
        WHERE category = '$category'
    ";
}

/* ============================================================
   BASE QUERY
   Combines manual expenses and inventory purchases
============================================================ */

$base_sql = "
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
   LOAD EXPENSE RECORDS
============================================================ */

$expenses_query = mysqli_query($conn, "
    SELECT *
    FROM (
        $base_sql
    ) AS all_expenses

    $category_filter

    ORDER BY record_date DESC, created_at DESC
");

if (!$expenses_query) {
    die("Expenses query failed: " . mysqli_error($conn));
}

/* ============================================================
   TOTAL EXPENSES
============================================================ */

$total_query = mysqli_query($conn, "
    SELECT
        IFNULL(SUM(total_cost), 0) AS total_amount

    FROM (
        $base_sql
    ) AS all_expenses

    $category_filter
");

$total_expenses = mysqli_fetch_assoc($total_query)['total_amount'] ?? 0;

/* ============================================================
   TOTAL MANUAL EXPENSES
============================================================ */

$manual_query = mysqli_query($conn, "
    SELECT
        IFNULL(SUM(total_cost), 0) AS total_amount

    FROM expenses
");

$total_manual_expenses = mysqli_fetch_assoc($manual_query)['total_amount'] ?? 0;

/* ============================================================
   TOTAL INVENTORY EXPENSES
============================================================ */

$inventory_query = mysqli_query($conn, "
    SELECT
        IFNULL(SUM(total_cost), 0) AS total_amount

    FROM inventory
");

$total_inventory_expenses = mysqli_fetch_assoc($inventory_query)['total_amount'] ?? 0;

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
                <i class="fas fa-file-invoice-dollar"></i>
                Expenses
            </h1>

            <p>
                View all farm expenses including manual expenses and inventory purchases.
            </p>
        </div>

        <div>
            <a href="add_expenses.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                Add Expense
            </a>
        </div>

    </div>

    <!-- ======================================================
         SUCCESS MESSAGE
    ======================================================= -->

    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Expense saved successfully.
        </div>

    <?php } ?>

    <!-- ======================================================
         SUMMARY CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-money-bill-wave"></i>
            </div>

            <div>
                <h4>Total Expenses</h4>
                <h2>KSh <?= number_format($total_expenses, 2); ?></h2>
            </div>

        </div>

        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-receipt"></i>
            </div>

            <div>
                <h4>Manual Expenses</h4>
                <h2>KSh <?= number_format($total_manual_expenses, 2); ?></h2>
            </div>

        </div>

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-warehouse"></i>
            </div>

            <div>
                <h4>Inventory Expenses</h4>
                <h2>KSh <?= number_format($total_inventory_expenses, 2); ?></h2>
            </div>

        </div>

    </div>

    <!-- ======================================================
         CATEGORY FILTER
    ======================================================= -->

    <div class="table-card">

        <form method="GET" action="expenses.php">

            <div class="search-bar">

                <select name="category" class="form-control">

                    <option value="">All Categories</option>

                    <?php
                    $categories = [
                        'Feeds',
                        'Medicine',
                        'Equipment',
                        'Supplement',
                        'Breeding',
                        'Veterinary',
                        'Labor',
                        'Utilities',
                        'Maintenance',
                        'Other'
                    ];

                    foreach ($categories as $cat) {
                        $selected = (
                            isset($_GET['category']) &&
                            $_GET['category'] === $cat
                        ) ? 'selected' : '';
                    ?>

                        <option value="<?= $cat; ?>" <?= $selected; ?>>
                            <?= $cat; ?>
                        </option>

                    <?php } ?>

                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>

                <a href="expenses.php" class="btn btn-secondary">
                    Reset
                </a>

            </div>

        </form>

    </div>

    <!-- ======================================================
         EXPENSE RECORDS TABLE
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-list"></i>
                Expense Records
            </h3>

        </div>

        <div class="table-responsive">

            <table class="custom-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Source</th>
                        <th>Recorded By</th>
                        <th>Receipt</th>
                    </tr>

                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($expenses_query) > 0) {

                        while ($row = mysqli_fetch_assoc($expenses_query)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <?= htmlspecialchars($row['record_date']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['category']); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['description']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= number_format($row['quantity'], 2); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($row['unit_cost'], 2); ?>
                                </td>

                                <td>
                                    <strong>
                                        KSh <?= number_format($row['total_cost'], 2); ?>
                                    </strong>
                                </td>

                                <td>

                                    <?php if ($row['source'] === 'Inventory Purchase') { ?>

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
                                    <?= htmlspecialchars($row['recorded_by'] ?? 'System'); ?>
                                </td>

                                <td>

                                    <?php if (!empty($row['receipt'])) { ?>

                                        <a
                                            href="uploads/receipts/<?= htmlspecialchars($row['receipt']); ?>"
                                            target="_blank"
                                            class="btn btn-primary btn-sm">
                                            View
                                        </a>

                                    <?php } else { ?>

                                        No Receipt

                                    <?php } ?>

                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="10" style="text-align:center;">
                                No expense records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>