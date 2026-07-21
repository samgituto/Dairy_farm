<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* ============================================================
   FILTER SETTINGS
============================================================ */

$filter_type = $_GET['filter_type'] ?? 'month';

$selected_date = $_GET['sale_date'] ?? date("Y-m-d");

$selected_month = $_GET['month'] ?? date("m");

$selected_year = $_GET['year'] ?? date("Y");

$selected_category = $_GET['sale_category'] ?? "";

$selected_cow = $_GET['cow_id'] ?? "";


/* ============================================================
   DATE RANGE
============================================================ */

if ($filter_type === "day") {

    $start_date = $selected_date;

    $end_date = $selected_date;

} elseif ($filter_type === "week") {

    $date = new DateTime($selected_date);

    $start = clone $date;
    $start->modify("monday this week");

    $end = clone $date;
    $end->modify("sunday this week");

    $start_date = $start->format("Y-m-d");

    $end_date = $end->format("Y-m-d");

} else {

    $start_date = $selected_year . "-" . $selected_month . "-01";

    $end_date = date(
        "Y-m-t",
        strtotime($start_date)
    );
}


/* ============================================================
   WHERE CONDITIONS
============================================================ */

$where = "
    WHERE DATE(s.sale_date) BETWEEN '$start_date' AND '$end_date'
";

if ($selected_category !== "") {

    $category = mysqli_real_escape_string(
        $conn,
        $selected_category
    );

    $where .= "
        AND s.sale_category = '$category'
    ";
}

if ($selected_cow !== "") {

    $cow_id = (int) $selected_cow;

    $where .= "
        AND s.cow_id = '$cow_id'
    ";
}


/* ============================================================
   LOAD COWS FOR FILTER
============================================================ */

$cowsQuery = mysqli_query($conn, "
    SELECT
        id,
        tag_number,
        cow_name,
        status,
        is_active
    FROM cows
    ORDER BY tag_number ASC
");


/* ============================================================
   SALES QUERY
============================================================ */

$salesQuery = mysqli_query($conn, "
    SELECT
        s.*,
        c.tag_number,
        c.cow_name,
        c.status,
        c.is_active
    FROM sales s

    LEFT JOIN cows c
        ON c.id = s.cow_id

    $where

    ORDER BY s.sale_date DESC,
             s.created_at DESC
");


/* ============================================================
   SUMMARY TOTALS
============================================================ */

$totalSalesQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.total_cost), 0) AS total
    FROM sales s
    $where
");

$total_sales_amount = mysqli_fetch_assoc($totalSalesQuery)['total'] ?? 0;


$totalMilkLitresQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.quantity), 0) AS total
    FROM sales s
    $where
    AND s.sale_category = 'Milk'
    AND s.unit = 'Litres'
");

$total_milk_litres = mysqli_fetch_assoc($totalMilkLitresQuery)['total'] ?? 0;


$totalManureBagsQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.quantity), 0) AS total
    FROM sales s
    $where
    AND s.sale_category = 'Manure'
    AND s.unit = 'Bags'
");

$total_manure_bags = mysqli_fetch_assoc($totalManureBagsQuery)['total'] ?? 0;


$totalCowQuantityQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.quantity), 0) AS total
    FROM sales s
    $where
    AND s.sale_category = 'Cows'
    AND s.unit = 'Cows'
");

$total_cows_sold = mysqli_fetch_assoc($totalCowQuantityQuery)['total'] ?? 0;


$milkSalesQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.total_cost), 0) AS total
    FROM sales s
    $where
    AND s.sale_category = 'Milk'
");

$total_milk_sales = mysqli_fetch_assoc($milkSalesQuery)['total'] ?? 0;


$cowSalesQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(s.total_cost), 0) AS total
    FROM sales s
    $where
    AND s.sale_category = 'Cows'
");

$total_cow_sales = mysqli_fetch_assoc($cowSalesQuery)['total'] ?? 0;


/* ============================================================
   HISTORICAL MILK SALES PER COW
============================================================ */

$milkHistoryQuery = mysqli_query($conn, "
    SELECT
        c.id,
        c.tag_number,
        c.cow_name,
        IFNULL(SUM(s.quantity), 0) AS total_litres_sold,
        IFNULL(SUM(s.total_cost), 0) AS total_amount
    FROM sales s

    LEFT JOIN cows c
        ON c.id = s.cow_id

    WHERE s.sale_category = 'Milk'
    AND s.unit = 'Litres'
    AND s.cow_id IS NOT NULL

    GROUP BY s.cow_id

    ORDER BY total_litres_sold DESC
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
                <i class="fas fa-shopping-cart"></i>
                Sales Records
            </h1>

            <p>
                View milk, manure, and cow sales records.
            </p>
        </div>

        <div>
            <a href="record_sales.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                Record Sale
            </a>
        </div>

    </div>


    <!-- ======================================================
         SUCCESS MESSAGE
    ======================================================= -->

    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Sale recorded successfully and transferred to income.
        </div>

    <?php } ?>


    <!-- ======================================================
         SUMMARY CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-money-bill-wave"></i>
            </div>

            <div>
                <h4>Total Sales</h4>
                <h2>KSh <?= number_format($total_sales_amount, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-glass-whiskey"></i>
            </div>

            <div>
                <h4>Milk Sold</h4>
                <h2><?= number_format($total_milk_litres, 2); ?> L</h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-box"></i>
            </div>

            <div>
                <h4>Manure Sold</h4>
                <h2><?= number_format($total_manure_bags, 2); ?> Bags</h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-cow"></i>
            </div>

            <div>
                <h4>Cows Sold</h4>
                <h2><?= number_format($total_cows_sold, 0); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-coins"></i>
            </div>

            <div>
                <h4>Milk Sales</h4>
                <h2>KSh <?= number_format($total_milk_sales, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-money-check-alt"></i>
            </div>

            <div>
                <h4>Cow Sales</h4>
                <h2>KSh <?= number_format($total_cow_sales, 2); ?></h2>
            </div>

        </div>

    </div>


    <!-- ======================================================
         FILTER FORM
    ======================================================= -->

    <div class="table-card">

        <form method="GET" action="sales.php">

            <div class="search-bar">

                <select name="filter_type" class="form-control">

                    <option value="day" <?= ($filter_type === 'day') ? 'selected' : ''; ?>>
                        Day
                    </option>

                    <option value="week" <?= ($filter_type === 'week') ? 'selected' : ''; ?>>
                        Week
                    </option>

                    <option value="month" <?= ($filter_type === 'month') ? 'selected' : ''; ?>>
                        Month
                    </option>

                </select>


                <input
                    type="date"
                    name="sale_date"
                    class="form-control"
                    value="<?= htmlspecialchars($selected_date); ?>">


                <select name="month" class="form-control">

                    <?php for ($m = 1; $m <= 12; $m++) { ?>

                        <option
                            value="<?= str_pad($m, 2, '0', STR_PAD_LEFT); ?>"
                            <?= ($selected_month == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>

                            <?= date("F", mktime(0, 0, 0, $m, 1)); ?>

                        </option>

                    <?php } ?>

                </select>


                <select name="year" class="form-control">

                    <?php
                    $currentYear = date("Y");

                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                    ?>

                        <option value="<?= $y; ?>" <?= ($selected_year == $y) ? 'selected' : ''; ?>>
                            <?= $y; ?>
                        </option>

                    <?php } ?>

                </select>


                <select name="sale_category" class="form-control">

                    <option value="">All Sales Categories</option>

                    <option value="Milk" <?= ($selected_category === 'Milk') ? 'selected' : ''; ?>>
                        Milk
                    </option>

                    <option value="Manure" <?= ($selected_category === 'Manure') ? 'selected' : ''; ?>>
                        Manure
                    </option>

                    <option value="Cows" <?= ($selected_category === 'Cows') ? 'selected' : ''; ?>>
                        Cows
                    </option>

                </select>


                <select name="cow_id" class="form-control">

                    <option value="">All Cows</option>

                    <?php while ($cow = mysqli_fetch_assoc($cowsQuery)) { ?>

                        <option
                            value="<?= $cow['id']; ?>"
                            <?= ($selected_cow == $cow['id']) ? 'selected' : ''; ?>>

                            <?= htmlspecialchars($cow['tag_number']); ?>
                            -
                            <?= htmlspecialchars($cow['cow_name'] ?? 'Unnamed'); ?>

                            <?php if ((int) $cow['is_active'] === 0) { ?>
                                - Sold
                            <?php } ?>

                        </option>

                    <?php } ?>

                </select>


                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>


                <a href="sales.php" class="btn btn-secondary">
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- ======================================================
         SALES TABLE
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-list"></i>
                Sales Records
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sale No</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Cow</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($salesQuery) > 0) {

                        while ($sale = mysqli_fetch_assoc($salesQuery)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($sale['sale_no']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['sale_date']); ?>
                                </td>

                                <td>
                                    <span class="badge-success">
                                        <?= htmlspecialchars($sale['sale_category']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (!empty($sale['cow_id'])) { ?>

                                        <?= htmlspecialchars($sale['tag_number']); ?>
                                        -
                                        <?= htmlspecialchars($sale['cow_name'] ?? 'Unnamed'); ?>

                                        <?php if ((int) ($sale['is_active'] ?? 1) === 0) { ?>
                                            <span class="badge-danger">
                                                Sold
                                            </span>
                                        <?php } ?>

                                    <?php } else { ?>

                                        General Sale

                                    <?php } ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['description']); ?>
                                </td>

                                <td>
                                    <?= number_format($sale['quantity'], 2); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['unit']); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($sale['unit_cost'], 2); ?>
                                </td>

                                <td>
                                    <strong>
                                        KSh <?= number_format($sale['total_cost'], 2); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['customer_name']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['payment_method']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['recorded_by'] ?? 'System'); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="13" style="text-align:center;">
                                No sales records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         HISTORICAL MILK SALES PER COW
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-history"></i>
                Historical Milk Sales Per Cow
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cow Tag</th>
                        <th>Cow Name</th>
                        <th>Total Litres Sold</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $h = 1;

                    if (mysqli_num_rows($milkHistoryQuery) > 0) {

                        while ($history = mysqli_fetch_assoc($milkHistoryQuery)) {
                    ?>

                            <tr>

                                <td><?= $h++; ?></td>

                                <td>
                                    <?= htmlspecialchars($history['tag_number'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($history['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= number_format($history['total_litres_sold'], 2); ?>
                                    Litres
                                </td>

                                <td>
                                    <strong>
                                        KSh <?= number_format($history['total_amount'], 2); ?>
                                    </strong>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="5" style="text-align:center;">
                                No historical milk sales found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>