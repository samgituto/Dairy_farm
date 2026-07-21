<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ============================================================
   HELPER FUNCTION
============================================================ */

function getSingleValue($conn, $sql, $column = "total")
{
    $query = mysqli_query($conn, $sql);

    if (!$query) {
        die("Query failed: " . mysqli_error($conn));
    }

    $row = mysqli_fetch_assoc($query);

    return $row[$column] ?? 0;
}


/* ============================================================
   FILTER SETTINGS
============================================================ */

$report_module = $_GET['report_module'] ?? 'finance';

$report_type = $_GET['report_type'] ?? 'monthly';

$selected_year = $_GET['year'] ?? date("Y");

$selected_month = $_GET['month'] ?? date("m");

$selected_week_date = $_GET['week_date'] ?? date("Y-m-d");


/* ============================================================
   DATE RANGE SETUP
============================================================ */

if ($report_type === "weekly") {

    $date = new DateTime($selected_week_date);

    $start_date = clone $date;
    $start_date->modify("monday this week");

    $end_date = clone $date;
    $end_date->modify("sunday this week");

    $period_title = "Weekly";

} elseif ($report_type === "yearly") {

    $start_date = new DateTime($selected_year . "-01-01");

    $end_date = new DateTime($selected_year . "-12-31");

    $period_title = "Yearly";

} else {

    $start_date = new DateTime($selected_year . "-" . $selected_month . "-01");

    $end_date = clone $start_date;
    $end_date->modify("last day of this month");

    $period_title = "Monthly";
}

$start = $start_date->format("Y-m-d");

$end = $end_date->format("Y-m-d");


/* ============================================================
   REPORT MODULE TITLE
============================================================ */

$module_titles = [
    'finance' => 'Finance Report',
    'herd' => 'Herd Report',
    'production' => 'Production Report',
    'inventory' => 'Inventory Report',
    'health_breeding' => 'Health & Breeding Report'
];

$report_title = $period_title . " " . ($module_titles[$report_module] ?? 'System Report');


/* ============================================================
   PERIOD LABELS FOR CHARTS
============================================================ */

$periods = [];

if ($report_type === "weekly") {

    $period = new DatePeriod(
        new DateTime($start),
        new DateInterval("P1D"),
        (new DateTime($end))->modify("+1 day")
    );

    foreach ($period as $day) {
        $periods[] = [
            'label' => $day->format("D"),
            'start' => $day->format("Y-m-d"),
            'end' => $day->format("Y-m-d")
        ];
    }

} elseif ($report_type === "yearly") {

    for ($m = 1; $m <= 12; $m++) {

        $month_start = new DateTime($selected_year . "-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-01");

        $month_end = clone $month_start;
        $month_end->modify("last day of this month");

        $periods[] = [
            'label' => $month_start->format("M"),
            'start' => $month_start->format("Y-m-d"),
            'end' => $month_end->format("Y-m-d")
        ];
    }

} else {

    $days_in_month = cal_days_in_month(
        CAL_GREGORIAN,
        (int) $selected_month,
        (int) $selected_year
    );

    for ($d = 1; $d <= $days_in_month; $d++) {

        $date_value = $selected_year . "-" . $selected_month . "-" . str_pad($d, 2, "0", STR_PAD_LEFT);

        $periods[] = [
            'label' => $d,
            'start' => $date_value,
            'end' => $date_value
        ];
    }
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
   FINANCE REPORT DATA
============================================================ */

$total_income = getSingleValue($conn, "
    SELECT IFNULL(SUM(amount), 0) AS total
    FROM income
    WHERE DATE(transaction_date) BETWEEN '$start' AND '$end'
");

$total_expenses = getSingleValue($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$total_manual_expenses = getSingleValue($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM expenses
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$total_inventory_expenses = getSingleValue($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM inventory
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$net_profit = $total_income - $total_expenses;

$largestExpenseQuery = mysqli_query($conn, "
    SELECT
        category,
        SUM(total_cost) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    GROUP BY category
    ORDER BY total DESC
    LIMIT 1
");

$largestExpenseRow = mysqli_fetch_assoc($largestExpenseQuery);

$largest_expense_category = $largestExpenseRow['category'] ?? "N/A";

$finance_labels = [];
$finance_income_data = [];
$finance_expense_data = [];
$finance_profit_data = [];

foreach ($periods as $p) {

    $p_start = $p['start'];
    $p_end = $p['end'];

    $income = getSingleValue($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM income
        WHERE DATE(transaction_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $expense = getSingleValue($conn, "
        SELECT IFNULL(SUM(total_cost), 0) AS total
        FROM (
            $expenseBaseSql
        ) AS all_expenses
        WHERE DATE(record_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $finance_labels[] = $p['label'];
    $finance_income_data[] = (float) $income;
    $finance_expense_data[] = (float) $expense;
    $finance_profit_data[] = (float) ($income - $expense);
}

$expense_category_labels = [];
$expense_category_totals = [];

$expenseCategoryQuery = mysqli_query($conn, "
    SELECT
        category,
        SUM(total_cost) AS total
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    GROUP BY category
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($expenseCategoryQuery)) {
    $expense_category_labels[] = $row['category'];
    $expense_category_totals[] = (float) $row['total'];
}


/* ============================================================
   HERD REPORT DATA
============================================================ */

$total_cows = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM cows
");

$new_cows = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM cows
    WHERE DATE(created_at) BETWEEN '$start' AND '$end'
");

$lactating_cows = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM cows
    WHERE status = 'Lactating'
");

$pregnant_cows = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM cows
    WHERE status = 'Pregnant'
");

$herd_status_labels = [];
$herd_status_totals = [];

$herdStatusQuery = mysqli_query($conn, "
    SELECT
        status,
        COUNT(*) AS total
    FROM cows
    GROUP BY status
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($herdStatusQuery)) {
    $herd_status_labels[] = $row['status'] ?? 'Unknown';
    $herd_status_totals[] = (int) $row['total'];
}

$herd_breed_labels = [];
$herd_breed_totals = [];

$herdBreedQuery = mysqli_query($conn, "
    SELECT
        breed,
        COUNT(*) AS total
    FROM cows
    GROUP BY breed
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($herdBreedQuery)) {
    $herd_breed_labels[] = $row['breed'] ?? 'Unknown';
    $herd_breed_totals[] = (int) $row['total'];
}

$herd_gender_labels = [];
$herd_gender_totals = [];

$herdGenderQuery = mysqli_query($conn, "
    SELECT
        gender,
        COUNT(*) AS total
    FROM cows
    GROUP BY gender
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($herdGenderQuery)) {
    $herd_gender_labels[] = $row['gender'] ?? 'Unknown';
    $herd_gender_totals[] = (int) $row['total'];
}

$herd_addition_labels = [];
$herd_addition_totals = [];

foreach ($periods as $p) {

    $p_start = $p['start'];
    $p_end = $p['end'];

    $added = getSingleValue($conn, "
        SELECT COUNT(*) AS total
        FROM cows
        WHERE DATE(created_at) BETWEEN '$p_start' AND '$p_end'
    ");

    $herd_addition_labels[] = $p['label'];
    $herd_addition_totals[] = (int) $added;
}


/* ============================================================
   PRODUCTION REPORT DATA
============================================================ */

$total_milk = getSingleValue($conn, "
    SELECT IFNULL(SUM(litres), 0) AS total
    FROM milk_records
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$milk_records_count = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM milk_records
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$highest_daily_milk = getSingleValue($conn, "
    SELECT IFNULL(MAX(day_total), 0) AS total
    FROM (
        SELECT
            record_date,
            SUM(litres) AS day_total
        FROM milk_records
        WHERE DATE(record_date) BETWEEN '$start' AND '$end'
        GROUP BY record_date
    ) AS daily_milk
");

$days_count = max(1, count($periods));

$average_daily_milk = $total_milk / $days_count;

$milk_trend_labels = [];
$milk_trend_totals = [];

foreach ($periods as $p) {

    $p_start = $p['start'];
    $p_end = $p['end'];

    $milk = getSingleValue($conn, "
        SELECT IFNULL(SUM(litres), 0) AS total
        FROM milk_records
        WHERE DATE(record_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $milk_trend_labels[] = $p['label'];
    $milk_trend_totals[] = (float) $milk;
}

$milk_session_labels = [];
$milk_session_totals = [];

$milkSessionQuery = mysqli_query($conn, "
    SELECT
        session,
        SUM(litres) AS total
    FROM milk_records
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    GROUP BY session
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($milkSessionQuery)) {
    $milk_session_labels[] = $row['session'];
    $milk_session_totals[] = (float) $row['total'];
}

$top_cow_labels = [];
$top_cow_totals = [];

$topCowQuery = mysqli_query($conn, "
    SELECT
        COALESCE(c.cow_name, c.tag_number) AS cow_label,
        SUM(m.litres) AS total
    FROM milk_records m
    LEFT JOIN cows c
        ON c.id = m.cow_id
    WHERE DATE(m.record_date) BETWEEN '$start' AND '$end'
    GROUP BY m.cow_id
    ORDER BY total DESC
    LIMIT 10
");

while ($row = mysqli_fetch_assoc($topCowQuery)) {
    $top_cow_labels[] = $row['cow_label'] ?? 'Unknown Cow';
    $top_cow_totals[] = (float) $row['total'];
}


/* ============================================================
   INVENTORY REPORT DATA
============================================================ */

$total_inventory_value = getSingleValue($conn, "
    SELECT IFNULL(SUM(total_cost), 0) AS total
    FROM inventory
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$total_inventory_records = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM inventory
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
");

$total_inventory_items = getSingleValue($conn, "
    SELECT COUNT(DISTINCT item_name) AS total
    FROM inventory
");

$low_stock_items = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM (
        SELECT
            item_name,
            category,
            unit,
            SUM(quantity) AS current_stock,
            MAX(reorder_level) AS reorder_level
        FROM inventory
        GROUP BY item_name, category, unit
        HAVING current_stock <= reorder_level
    ) AS low_stock
");

$inventory_category_labels = [];
$inventory_category_totals = [];

$inventoryCategoryQuery = mysqli_query($conn, "
    SELECT
        category,
        SUM(total_cost) AS total
    FROM inventory
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    GROUP BY category
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($inventoryCategoryQuery)) {
    $inventory_category_labels[] = $row['category'];
    $inventory_category_totals[] = (float) $row['total'];
}

$inventory_trend_labels = [];
$inventory_trend_totals = [];

foreach ($periods as $p) {

    $p_start = $p['start'];
    $p_end = $p['end'];

    $stock_cost = getSingleValue($conn, "
        SELECT IFNULL(SUM(total_cost), 0) AS total
        FROM inventory
        WHERE DATE(record_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $inventory_trend_labels[] = $p['label'];
    $inventory_trend_totals[] = (float) $stock_cost;
}

$inventory_stock_labels = [];
$inventory_stock_totals = [];

$inventoryStockQuery = mysqli_query($conn, "
    SELECT
        item_name,
        SUM(quantity) AS current_stock
    FROM inventory
    GROUP BY item_name
    ORDER BY current_stock DESC
    LIMIT 10
");

while ($row = mysqli_fetch_assoc($inventoryStockQuery)) {
    $inventory_stock_labels[] = $row['item_name'];
    $inventory_stock_totals[] = (float) $row['current_stock'];
}


/* ============================================================
   HEALTH & BREEDING REPORT DATA
============================================================ */

$total_vaccinations = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM vaccinations
    WHERE DATE(vaccination_date) BETWEEN '$start' AND '$end'
");

$total_treatments = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM treatments
    WHERE DATE(treatment_date) BETWEEN '$start' AND '$end'
");

$total_breeding = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM breeding_records
    WHERE DATE(insemination_date) BETWEEN '$start' AND '$end'
");
$totalUpcomingCalvings = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM breeding_records
    WHERE status IN ('Pregnant', 'Pending')
    AND DATE(
        IFNULL(
            expected_calving_date,
            DATE_ADD(insemination_date, INTERVAL 280 DAY)
        )
    ) >= CURDATE()
");
$total_calving = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM calving_records
    WHERE DATE(calving_date) BETWEEN '$start' AND '$end'
");

$health_event_labels = [
    'Vaccinations',
    'Treatments',
    'Breeding',
    'Calving'
];

$health_event_totals = [
    (int) $total_vaccinations,
    (int) $total_treatments,
    (int) $total_breeding,
    (int) $total_calving
];

$health_trend_labels = [];
$health_vaccination_trend = [];
$health_treatment_trend = [];
$health_breeding_trend = [];
$health_calving_trend = [];

foreach ($periods as $p) {

    $p_start = $p['start'];
    $p_end = $p['end'];

    $vaccinations = getSingleValue($conn, "
        SELECT COUNT(*) AS total
        FROM vaccinations
        WHERE DATE(vaccination_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $treatments = getSingleValue($conn, "
        SELECT COUNT(*) AS total
        FROM treatments
        WHERE DATE(treatment_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $breeding = getSingleValue($conn, "
        SELECT COUNT(*) AS total
        FROM breeding_records
        WHERE DATE(insemination_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $calving = getSingleValue($conn, "
        SELECT COUNT(*) AS total
        FROM calving_records
        WHERE DATE(calving_date) BETWEEN '$p_start' AND '$p_end'
    ");

    $health_trend_labels[] = $p['label'];
    $health_vaccination_trend[] = (int) $vaccinations;
    $health_treatment_trend[] = (int) $treatments;
    $health_breeding_trend[] = (int) $breeding;
    $health_calving_trend[] = (int) $calving;
}

$breeding_status_labels = [];
$breeding_status_totals = [];

$breedingStatusQuery = mysqli_query($conn, "
    SELECT
        status,
        COUNT(*) AS total
    FROM breeding_records
    GROUP BY status
    ORDER BY total DESC
");

while ($row = mysqli_fetch_assoc($breedingStatusQuery)) {
    $breeding_status_labels[] = $row['status'] ?? 'Unknown';
    $breeding_status_totals[] = (int) $row['total'];
}


/* ============================================================
   RECENT RECORDS
============================================================ */

$recentFinanceIncomeQuery = mysqli_query($conn, "
    SELECT
        transaction_no,
        transaction_date,
        amount,
        description,
        recorded_by
    FROM income
    WHERE DATE(transaction_date) BETWEEN '$start' AND '$end'
    ORDER BY transaction_date DESC
    LIMIT 5
");

$recentFinanceExpenseQuery = mysqli_query($conn, "
    SELECT *
    FROM (
        $expenseBaseSql
    ) AS all_expenses
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    ORDER BY record_date DESC, created_at DESC
    LIMIT 5
");

$recentMilkQuery = mysqli_query($conn, "
    SELECT
        m.*,
        c.tag_number,
        c.cow_name
    FROM milk_records m
    LEFT JOIN cows c
        ON c.id = m.cow_id
    WHERE DATE(m.record_date) BETWEEN '$start' AND '$end'
    ORDER BY m.record_date DESC
    LIMIT 5
");

$recentInventoryQuery = mysqli_query($conn, "
    SELECT *
    FROM inventory
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    ORDER BY record_date DESC, created_at DESC
    LIMIT 5
");

$recentHealthQuery = mysqli_query($conn, "
    SELECT *
    FROM (
        SELECT
            vaccination_date AS record_date,
            cow_id,
            'Vaccination' AS event_type,
            vaccine_name AS details
        FROM vaccinations

        UNION ALL

        SELECT
            treatment_date AS record_date,
            cow_id,
            'Treatment' AS event_type,
            disease AS details
        FROM treatments

        UNION ALL

        SELECT
            insemination_date AS record_date,
            cow_id,
            'Breeding' AS event_type,
            semen_batch AS details
        FROM breeding_records

        UNION ALL

        SELECT
            calving_date AS record_date,
            cow_id,
            'Calving' AS event_type,
            calf_gender AS details
        FROM calving_records
    ) AS health_events
    WHERE DATE(record_date) BETWEEN '$start' AND '$end'
    ORDER BY record_date DESC
    LIMIT 8
");


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-chart-pie"></i>
                Reports & Analytics
            </h1>

            <p>
                View finance, herd, production, inventory, health, and breeding reports.
            </p>
        </div>

        <div>
            <button onclick="window.print()" class="btn btn-success">
                <i class="fas fa-print"></i>
                Print Report
            </button>
        </div>

    </div>


    <!-- FILTER FORM -->

    <div class="table-card">

        <form method="GET" action="reports.php">

            <div class="search-bar">

                <select name="report_module" class="form-control">

                    <option value="finance" <?= ($report_module === 'finance') ? 'selected' : ''; ?>>
                        Finance Report
                    </option>

                    <option value="herd" <?= ($report_module === 'herd') ? 'selected' : ''; ?>>
                        Herd Report
                    </option>

                    <option value="production" <?= ($report_module === 'production') ? 'selected' : ''; ?>>
                        Production Report
                    </option>

                    <option value="inventory" <?= ($report_module === 'inventory') ? 'selected' : ''; ?>>
                        Inventory Report
                    </option>

                    <option value="health_breeding" <?= ($report_module === 'health_breeding') ? 'selected' : ''; ?>>
                        Health & Breeding Report
                    </option>

                </select>


                <select name="report_type" class="form-control" id="reportType">

                    <option value="weekly" <?= ($report_type === 'weekly') ? 'selected' : ''; ?>>
                        Weekly
                    </option>

                    <option value="monthly" <?= ($report_type === 'monthly') ? 'selected' : ''; ?>>
                        Monthly
                    </option>

                    <option value="yearly" <?= ($report_type === 'yearly') ? 'selected' : ''; ?>>
                        Yearly
                    </option>

                </select>


                <input
                    type="date"
                    name="week_date"
                    id="weekDate"
                    class="form-control"
                    value="<?= htmlspecialchars($selected_week_date); ?>">


                <select name="month" id="monthFilter" class="form-control">

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


                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Generate Report
                </button>

            </div>

        </form>

    </div>


    <!-- REPORT TITLE -->

    <div class="report-title-box">

        <h2><?= htmlspecialchars($report_title); ?></h2>

        <p>
            Period:
            <strong><?= date("d M Y", strtotime($start)); ?></strong>
            to
            <strong><?= date("d M Y", strtotime($end)); ?></strong>
        </p>

    </div>


    <!-- ======================================================
         FINANCE REPORT
    ======================================================= -->

    <?php if ($report_module === 'finance') { ?>

        <div class="cards">

            <div class="card">
                <div class="card-icon bg-green">
                    <i class="fas fa-coins"></i>
                </div>
                <div>
                    <h4>Total Income</h4>
                    <h2>KSh <?= number_format($total_income, 2); ?></h2>
                </div>
            </div>

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
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h4>Net Profit</h4>
                    <h2>KSh <?= number_format($net_profit, 2); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-warning">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div>
                    <h4>Inventory Expenses</h4>
                    <h2>KSh <?= number_format($total_inventory_expenses, 2); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <h4>Manual Expenses</h4>
                    <h2>KSh <?= number_format($total_manual_expenses, 2); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-fire"></i>
                </div>
                <div>
                    <h4>Largest Expense</h4>
                    <h3><?= htmlspecialchars($largest_expense_category); ?></h3>
                </div>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Income vs Expenses</h3>
                <canvas id="financeIncomeExpenseChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Net Profit Trend</h3>
                <canvas id="financeProfitChart"></canvas>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Expense Breakdown</h3>
                <canvas id="financeExpenseCategoryChart"></canvas>
            </div>

        </div>

    <?php } ?>


    <!-- ======================================================
         HERD REPORT
    ======================================================= -->

    <?php if ($report_module === 'herd') { ?>

        <div class="cards">

            <div class="card">
                <div class="card-icon bg-green">
                    <i class="fas fa-cow"></i>
                </div>
                <div>
                    <h4>Total Cows</h4>
                    <h2><?= number_format($total_cows); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-blue">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h4>New Cows</h4>
                    <h2><?= number_format($new_cows); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-warning">
                    <i class="fas fa-glass-whiskey"></i>
                </div>
                <div>
                    <h4>Lactating</h4>
                    <h2><?= number_format($lactating_cows); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h4>Pregnant</h4>
                    <h2><?= number_format($pregnant_cows); ?></h2>
                </div>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Herd by Status</h3>
                <canvas id="herdStatusChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Herd by Breed</h3>
                <canvas id="herdBreedChart"></canvas>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Gender Distribution</h3>
                <canvas id="herdGenderChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>New Herd Additions</h3>
                <canvas id="herdAdditionChart"></canvas>
            </div>

        </div>

    <?php } ?>


    <!-- ======================================================
         PRODUCTION REPORT
    ======================================================= -->

    <?php if ($report_module === 'production') { ?>

        <div class="cards">

            <div class="card">
                <div class="card-icon bg-green">
                    <i class="fas fa-fill-drip"></i>
                </div>
                <div>
                    <h4>Total Milk</h4>
                    <h2><?= number_format($total_milk, 2); ?> L</h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-blue">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <h4>Average Daily Milk</h4>
                    <h2><?= number_format($average_daily_milk, 2); ?> L</h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-warning">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h4>Highest Daily Milk</h4>
                    <h2><?= number_format($highest_daily_milk, 2); ?> L</h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-list"></i>
                </div>
                <div>
                    <h4>Milk Records</h4>
                    <h2><?= number_format($milk_records_count); ?></h2>
                </div>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Milk Production Trend</h3>
                <canvas id="milkTrendChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Milk by Session</h3>
                <canvas id="milkSessionChart"></canvas>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Top Producing Cows</h3>
                <canvas id="topCowChart"></canvas>
            </div>

        </div>

    <?php } ?>


    <!-- ======================================================
         INVENTORY REPORT
    ======================================================= -->

    <?php if ($report_module === 'inventory') { ?>

        <div class="cards">

            <div class="card">
                <div class="card-icon bg-green">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div>
                    <h4>Inventory Value</h4>
                    <h2>KSh <?= number_format($total_inventory_value, 2); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-blue">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <h4>Inventory Records</h4>
                    <h2><?= number_format($total_inventory_records); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-warning">
                    <i class="fas fa-seedling"></i>
                </div>
                <div>
                    <h4>Total Items</h4>
                    <h2><?= number_format($total_inventory_items); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h4>Low Stock Items</h4>
                    <h2><?= number_format($low_stock_items); ?></h2>
                </div>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Inventory Cost Trend</h3>
                <canvas id="inventoryTrendChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Inventory Value by Category</h3>
                <canvas id="inventoryCategoryChart"></canvas>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Top Current Stock Items</h3>
                <canvas id="inventoryStockChart"></canvas>
            </div>

        </div>

    <?php } ?>


    <!-- ======================================================
         HEALTH & BREEDING REPORT
    ======================================================= -->

    <?php if ($report_module === 'health_breeding') { ?>

        <div class="cards">

            <div class="card">
                <div class="card-icon bg-green">
                    <i class="fas fa-syringe"></i>
                </div>
                <div>
                    <h4>Vaccinations</h4>
                    <h2><?= number_format($total_vaccinations); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-danger">
                    <i class="fas fa-notes-medical"></i>
                </div>
                <div>
                    <h4>Treatments</h4>
                    <h2><?= number_format($total_treatments); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-blue">
                    <i class="fas fa-dna"></i>
                </div>
                <div>
                    <h4>Breeding Records</h4>
                    <h2><?= number_format($total_breeding); ?></h2>
                </div>
            </div>

            <div class="card">
                <div class="card-icon bg-warning">
                    <i class="fas fa-baby"></i>
                </div>
                <div>
                    <h4>Calving Records</h4>
                    <h2><?= number_format($total_calving); ?></h2>
                </div>
            </div>

        </div>

        <div class="cards">

            <div class="chart-card">
                <h3>Health & Breeding Events</h3>
                <canvas id="healthEventChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Event Trend</h3>
                <canvas id="healthTrendChart"></canvas>
            </div>

        </div>


        <div class="cards">

            <div class="chart-card">
                <h3>Breeding Status</h3>
                <canvas id="breedingStatusChart"></canvas>
            </div>

        </div>

    <?php } ?>

</div>


<!-- ==========================================================
     JAVASCRIPT DATA
========================================================== -->

<script>
    const reportModule = <?= json_encode($report_module); ?>;

    const financeLabels = <?= json_encode($finance_labels); ?>;
    const financeIncomeData = <?= json_encode($finance_income_data); ?>;
    const financeExpenseData = <?= json_encode($finance_expense_data); ?>;
    const financeProfitData = <?= json_encode($finance_profit_data); ?>;
    const expenseCategoryLabels = <?= json_encode($expense_category_labels); ?>;
    const expenseCategoryTotals = <?= json_encode($expense_category_totals); ?>;

    const herdStatusLabels = <?= json_encode($herd_status_labels); ?>;
    const herdStatusTotals = <?= json_encode($herd_status_totals); ?>;
    const herdBreedLabels = <?= json_encode($herd_breed_labels); ?>;
    const herdBreedTotals = <?= json_encode($herd_breed_totals); ?>;
    const herdGenderLabels = <?= json_encode($herd_gender_labels); ?>;
    const herdGenderTotals = <?= json_encode($herd_gender_totals); ?>;
    const herdAdditionLabels = <?= json_encode($herd_addition_labels); ?>;
    const herdAdditionTotals = <?= json_encode($herd_addition_totals); ?>;

    const milkTrendLabels = <?= json_encode($milk_trend_labels); ?>;
    const milkTrendTotals = <?= json_encode($milk_trend_totals); ?>;
    const milkSessionLabels = <?= json_encode($milk_session_labels); ?>;
    const milkSessionTotals = <?= json_encode($milk_session_totals); ?>;
    const topCowLabels = <?= json_encode($top_cow_labels); ?>;
    const topCowTotals = <?= json_encode($top_cow_totals); ?>;

    const inventoryTrendLabels = <?= json_encode($inventory_trend_labels); ?>;
    const inventoryTrendTotals = <?= json_encode($inventory_trend_totals); ?>;
    const inventoryCategoryLabels = <?= json_encode($inventory_category_labels); ?>;
    const inventoryCategoryTotals = <?= json_encode($inventory_category_totals); ?>;
    const inventoryStockLabels = <?= json_encode($inventory_stock_labels); ?>;
    const inventoryStockTotals = <?= json_encode($inventory_stock_totals); ?>;

    const healthEventLabels = <?= json_encode($health_event_labels); ?>;
    const healthEventTotals = <?= json_encode($health_event_totals); ?>;
    const healthTrendLabels = <?= json_encode($health_trend_labels); ?>;
    const healthVaccinationTrend = <?= json_encode($health_vaccination_trend); ?>;
    const healthTreatmentTrend = <?= json_encode($health_treatment_trend); ?>;
    const healthBreedingTrend = <?= json_encode($health_breeding_trend); ?>;
    const healthCalvingTrend = <?= json_encode($health_calving_trend); ?>;
    const breedingStatusLabels = <?= json_encode($breeding_status_labels); ?>;
    const breedingStatusTotals = <?= json_encode($breeding_status_totals); ?>;
</script>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const green = "#2E7D32";
    const red = "#EF5350";
    const blue = "#42A5F5";
    const orange = "#FFA726";
    const purple = "#7E57C2";
    const grey = "#BDBDBD";

    const chartColors = [
        green,
        red,
        blue,
        orange,
        purple,
        grey,
        "#8BC34A",
        "#009688",
        "#607D8B",
        "#FFC107"
    ];

    function createBarChart(id, labels, label, data) {

        const canvas = document.getElementById(id);

        if (!canvas) return;

        new Chart(canvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: data,
                        backgroundColor: green,
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function createLineChart(id, labels, datasets) {

        const canvas = document.getElementById(id);

        if (!canvas) return;

        new Chart(canvas, {
            type: "line",
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function createPieChart(id, labels, data) {

        const canvas = document.getElementById(id);

        if (!canvas) return;

        new Chart(canvas, {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [
                    {
                        data: data,
                        backgroundColor: chartColors
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "60%"
            }
        });
    }

    function createGroupedBarChart(id, labels, datasets) {

        const canvas = document.getElementById(id);

        if (!canvas) return;

        new Chart(canvas, {
            type: "bar",
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }


    /* ======================================================
       FINANCE CHARTS
    ====================================================== */

    createGroupedBarChart(
        "financeIncomeExpenseChart",
        financeLabels,
        [
            {
                label: "Income",
                data: financeIncomeData,
                backgroundColor: green,
                borderRadius: 8
            },
            {
                label: "Expenses",
                data: financeExpenseData,
                backgroundColor: red,
                borderRadius: 8
            }
        ]
    );

    createLineChart(
        "financeProfitChart",
        financeLabels,
        [
            {
                label: "Net Profit",
                data: financeProfitData,
                borderColor: blue,
                backgroundColor: "rgba(66, 165, 245, 0.15)",
                fill: true,
                tension: 0.4
            }
        ]
    );

    createPieChart(
        "financeExpenseCategoryChart",
        expenseCategoryLabels,
        expenseCategoryTotals
    );


    /* ======================================================
       HERD CHARTS
    ====================================================== */

    createPieChart(
        "herdStatusChart",
        herdStatusLabels,
        herdStatusTotals
    );

    createBarChart(
        "herdBreedChart",
        herdBreedLabels,
        "Cows",
        herdBreedTotals
    );

    createPieChart(
        "herdGenderChart",
        herdGenderLabels,
        herdGenderTotals
    );

    createLineChart(
        "herdAdditionChart",
        herdAdditionLabels,
        [
            {
                label: "New cows",
                data: herdAdditionTotals,
                borderColor: green,
                backgroundColor: "rgba(46, 125, 50, 0.15)",
                fill: true,
                tension: 0.4
            }
        ]
    );


    /* ======================================================
       PRODUCTION CHARTS
    ====================================================== */

    createLineChart(
        "milkTrendChart",
        milkTrendLabels,
        [
            {
                label: "Milk litres",
                data: milkTrendTotals,
                borderColor: blue,
                backgroundColor: "rgba(66, 165, 245, 0.15)",
                fill: true,
                tension: 0.4
            }
        ]
    );

    createPieChart(
        "milkSessionChart",
        milkSessionLabels,
        milkSessionTotals
    );

    createBarChart(
        "topCowChart",
        topCowLabels,
        "Milk litres",
        topCowTotals
    );


    /* ======================================================
       INVENTORY CHARTS
    ====================================================== */

    createLineChart(
        "inventoryTrendChart",
        inventoryTrendLabels,
        [
            {
                label: "Inventory cost",
                data: inventoryTrendTotals,
                borderColor: green,
                backgroundColor: "rgba(46, 125, 50, 0.15)",
                fill: true,
                tension: 0.4
            }
        ]
    );

    createPieChart(
        "inventoryCategoryChart",
        inventoryCategoryLabels,
        inventoryCategoryTotals
    );

    createBarChart(
        "inventoryStockChart",
        inventoryStockLabels,
        "Current stock",
        inventoryStockTotals
    );


    /* ======================================================
       HEALTH & BREEDING CHARTS
    ====================================================== */

    createPieChart(
        "healthEventChart",
        healthEventLabels,
        healthEventTotals
    );

    createGroupedBarChart(
        "healthTrendChart",
        healthTrendLabels,
        [
            {
                label: "Vaccinations",
                data: healthVaccinationTrend,
                backgroundColor: green,
                borderRadius: 8
            },
            {
                label: "Treatments",
                data: healthTreatmentTrend,
                backgroundColor: red,
                borderRadius: 8
            },
            {
                label: "Breeding",
                data: healthBreedingTrend,
                backgroundColor: blue,
                borderRadius: 8
            },
            {
                label: "Calving",
                data: healthCalvingTrend,
                backgroundColor: orange,
                borderRadius: 8
            }
        ]
    );

    createPieChart(
        "breedingStatusChart",
        breedingStatusLabels,
        breedingStatusTotals
    );


    /* ======================================================
       FILTER VISIBILITY
    ====================================================== */

    const reportType = document.getElementById("reportType");

    const weekDate = document.getElementById("weekDate");

    const monthFilter = document.getElementById("monthFilter");

    function updateFilterVisibility() {

        if (reportType.value === "weekly") {

            weekDate.style.display = "block";
            monthFilter.style.display = "none";

        } else if (reportType.value === "monthly") {

            weekDate.style.display = "none";
            monthFilter.style.display = "block";

        } else {

            weekDate.style.display = "none";
            monthFilter.style.display = "none";

        }
    }

    reportType.addEventListener("change", updateFilterVisibility);

    updateFilterVisibility();

});
</script>

<?php include 'includes/footer.php'; ?>