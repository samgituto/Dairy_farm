<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

// Total Herd
$totalHerdResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM cows"
);
$totalHerd = mysqli_fetch_assoc($totalHerdResult);

// Lactating Cows
$lactatingResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM cows
     WHERE status='Lactating'"
);
$lactating = mysqli_fetch_assoc($lactatingResult);
/* ============================================================
   DASHBOARD LOW STOCK ALERTS
============================================================ */

$dashboardLowStockItems = mysqli_query($conn, "
    SELECT
        item_name,
        category,
        unit,
        SUM(quantity) AS current_stock,
        MAX(reorder_level) AS reorder_level
    FROM inventory
    GROUP BY item_name, category, unit
    HAVING current_stock <= reorder_level
    ORDER BY current_stock ASC
    LIMIT 4
");
// Today's Milk Production
$milkTodayResult = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(litres),0) AS total
     FROM milk_records
     WHERE record_date = CURDATE()"
);
$milkToday = mysqli_fetch_assoc($milkTodayResult);

// AI Alert Logic per individual cow: flag cows whose today's production is below their 5-day average
$aiAlert = false;
$aiAlertMessage = '';

$perCowQuery = mysqli_query(
    $conn,
    "SELECT
        c.id AS cow_id,
        c.cow_name,
        COALESCE(t.today_total,0) AS today_total,
        COALESCE(a.avg_total,0) AS avg_total
     FROM cows c
     LEFT JOIN (
         SELECT cow_id, SUM(litres) AS today_total
         FROM milk_records
         WHERE record_date = CURDATE()
         GROUP BY cow_id
     ) t ON t.cow_id = c.id
     LEFT JOIN (
         SELECT cow_id, AVG(day_total) AS avg_total FROM (
             SELECT cow_id, record_date, SUM(litres) AS day_total
             FROM milk_records
             WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
               AND record_date < CURDATE()
             GROUP BY cow_id, record_date
         ) s
         GROUP BY cow_id
     ) a ON a.cow_id = c.id"
);

$alerts = [];

while ($cow = mysqli_fetch_assoc($perCowQuery)) {
    $avg = (float)$cow['avg_total'];
    $today = (float)$cow['today_total'];

    if ($avg > 0 && $today < $avg) {
        $aiAlert = true;
        $alerts[] = sprintf(
            "Today's %s production is %.1f L which is below the 5-day average %.1f L check for health or feed issues.",
            $cow['cow_name'],
            $today,
            $avg
        );
    }
}

if ($aiAlert) {
    // join alerts for display; separated by " | "
    $aiAlertMessage = implode(' | ', $alerts);
}


// Upcoming Vaccinations Count
$vaccinationCountResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM vaccinations
     WHERE next_due_date >= CURDATE()
     AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
);
$vaccinationCount = mysqli_fetch_assoc($vaccinationCountResult);

// Weekly Milk Chart Data
$chartQuery = mysqli_query(
    $conn,
    "SELECT
        record_date,
        SUM(litres) AS total
     FROM milk_records
     WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY record_date
     ORDER BY record_date ASC"
);

$chartLabels = [];
$chartValues = [];

while ($row = mysqli_fetch_assoc($chartQuery)) {

    $chartLabels[] = date(
        "D",
        strtotime($row['record_date'])
    );

    $chartValues[] = $row['total'];
}

// Upcoming Vaccinations
$upcomingVaccinations = mysqli_query(
    $conn,
    "SELECT
        cows.cow_name,
        vaccinations.vaccine_name,
        vaccinations.next_due_date

     FROM vaccinations

     INNER JOIN cows
     ON vaccinations.cow_id = cows.id

     WHERE vaccinations.next_due_date >= CURDATE()

     ORDER BY vaccinations.next_due_date ASC

     LIMIT 5"
);

// Expected Calvings
$expectedCalvings = mysqli_query(
    $conn,
    "SELECT
        cows.cow_name,
        breeding_records.expected_calving_date

     FROM breeding_records

     INNER JOIN cows
     ON breeding_records.cow_id = cows.id

     WHERE breeding_records.expected_calving_date >= CURDATE()

     ORDER BY breeding_records.expected_calving_date ASC

     LIMIT 5"
);

// Recent Treatments
$recentTreatments = mysqli_query(
    $conn,
    "SELECT
        cows.cow_name,
        treatments.disease,
        treatments.treatment_date

     FROM treatments

     INNER JOIN cows
     ON treatments.cow_id = cows.id

     ORDER BY treatments.treatment_date DESC

     LIMIT 5"
);

// Recent Milk Records
$recentMilkRecords = mysqli_query(
    $conn,
    "SELECT
        milk_records.*,
        cows.tag_number,
        cows.cow_name

     FROM milk_records

     INNER JOIN cows
     ON milk_records.cow_id = cows.id

     ORDER BY milk_records.record_date DESC

     LIMIT 5"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Dairy Farm Dashboard</title>

<link
rel="stylesheet"
href="assets/css/style.css">

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">

    <!-- Header -->

    <div class="dashboard-header">

        <div>

            <h1>
                Welcome,
                <?= $_SESSION['full_name']; ?>
            </h1>
        <div class="dashboard-role-box">
        <span class="dashboard-role-text">
            Role: <?= htmlspecialchars($currentRole); ?>
        </span>
    </div>
            <p>
                <?= date("l, d F Y"); ?> 
                
            </p> 
        </small>
        </div>

    </div>

    <!-- KPI Cards -->

    <div class="cards">

        <div class="card">

            <div class="card-icon">
                <i class="fas fa-cow"></i>
            </div>

            <div>
                <h4>Total Herd</h4>
                <h2><?= $totalHerd['total']; ?></h2>
            </div>

        </div>

        <div class="card">

            <div class="card-icon">
                <i class="fas fa-glass-water"></i>
            </div>

            <div>
                <h4>Lactating Cows</h4>
                <h2><?= $lactating['total']; ?></h2>
            </div>

        </div>

        <div class="card">

            <div class="card-icon">
                <i class="fas fa-droplet"></i>
            </div>

            <div>
                <h4>Today's Milk</h4>

                <h2>
                    <?= number_format($milkToday['total'] ?? 0, 1); ?> L
                </h2>
            </div>

        </div>

        <div class="card">

            <div class="card-icon">
                <i class="fas fa-syringe"></i>
            </div>

            <div>
                <h4>Upcoming Vaccinations</h4>
                <h2><?= $vaccinationCount['total']; ?></h2>
            </div>

        </div>

    </div>
<!-- AI Insights -->

<div class="ai-card" style="background:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06); padding:18px; margin-bottom:20px; border:1px solid #eee; max-width:100%;">

    <h3 style="display:flex; align-items:center; gap:10px; margin:0 0 10px 0; font-size:1.05rem;">
        <span style="font-size:1.2rem; line-height:1;">🤖</span>
        AI Farm Insights
    </h3>

    <?php if (!empty($aiAlert)): ?>
        <div role="alert" aria-live="polite" style="background:#fff8e1; border-left:5px solid #ffecb3; padding:12px; border-radius:6px; margin-bottom:12px; color:#5a4a00;">
            <?php $count = isset($alerts) ? count($alerts) : 0; ?>
            <?php if ($count > 1): ?>
                <strong style="display:block; margin-bottom:8px; color:#5a4a00;"><?= $count; ?> alerts found — check these cows:</strong>
                <ul style="margin:0; padding-left:18px; color:#5a4a00;">
                    <?php foreach ($alerts as $a): ?>
                        <li><?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span><?= htmlspecialchars($aiAlertMessage, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p style="margin:0; color:#555; line-height:1.45;">
        Our AI monitors milk-production trends and highlights cows whose today's yield is below their 5-day average. Use these insights to check health, feed, or milking issues early.
    </p>
</div>
<!-- ==========================================================
     LOW STOCK ALERT CARDS
========================================================== -->

<div class="dashboard-low-stock-section">

    <div class="section-header">

        <div>
            <h2>
                <i class="fas fa-exclamation-triangle"></i>
                Low Stock Alerts
            </h2>

            <p>
                Items whose quantity is below or equal to reorder level.
            </p>
        </div>

        <a href="inventory.php" class="btn btn-primary">
            <i class="fas fa-warehouse"></i>
            View Inventory
        </a>

    </div>


    <div class="dashboard-low-stock-grid">

        <?php if (mysqli_num_rows($dashboardLowStockItems) > 0) { ?>

            <?php while ($item = mysqli_fetch_assoc($dashboardLowStockItems)) { ?>

                <div class="dashboard-low-stock-card">

                    <div class="low-stock-danger-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                    <h4>
                        <?= htmlspecialchars($item['item_name']); ?>
                    </h4>

                    <p>
                        Category:
                        <strong>
                            <?= htmlspecialchars($item['category']); ?>
                        </strong>
                    </p>

                    <p>
                        Current Stock:
                        <strong>
                            <?= number_format($item['current_stock'], 2); ?>
                            <?= htmlspecialchars($item['unit']); ?>
                        </strong>
                    </p>

                    <p>
                        Reorder Level:
                        <strong>
                            <?= number_format($item['reorder_level'], 2); ?>
                            <?= htmlspecialchars($item['unit']); ?>
                        </strong>
                    </p>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="dashboard-safe-stock-card">

                <div class="safe-stock-icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <h4>No Low Stock Alerts</h4>

                <p>
                    All inventory items are above reorder level.
                </p>

            </div>

        <?php } ?>

    </div>

</div>
    <!-- Weekly Production Chart -->

    <div class="chart-card">

        <h3>Weekly Milk Production</h3>

        <canvas id="milkChart"></canvas>

    </div>

    <!-- Health & Breeding Widgets -->

    <div class="dashboard-grid">

        <div class="table-card">

            <h3>Upcoming Vaccinations</h3>

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>Cow</th>
                        <th>Vaccine</th>
                        <th>Due Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php while($row = mysqli_fetch_assoc($upcomingVaccinations)): ?>

                    <tr>
                        <td><?= $row['cow_name']; ?></td>
                        <td><?= $row['vaccine_name']; ?></td>
                        <td><?= $row['next_due_date']; ?></td>
                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

        <div class="table-card">

            <h3>Expected Calvings</h3>

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>Cow</th>
                        <th>Expected Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php while($row = mysqli_fetch_assoc($expectedCalvings)): ?>

                    <tr>
                        <td><?= $row['cow_name']; ?></td>
                        <td><?= $row['expected_calving_date']; ?></td>
                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Recent Milk Records -->

    <div class="table-card">

        <h3>Recent Milk Records</h3>

        <table class="custom-table">

            <thead>

                <tr>

                    <th>Date</th>
                    <th>Tag Number</th>
                    <th>Cow Name</th>
                    <th>Session</th>
                    <th>Litres</th>

                </tr>

            </thead>

            <tbody>

            <?php while($milk = mysqli_fetch_assoc($recentMilkRecords)): ?>

                <tr>

                    <td><?= $milk['record_date']; ?></td>

                    <td><?= $milk['tag_number']; ?></td>

                    <td><?= $milk['cow_name']; ?></td>

                    <td><?= $milk['session']; ?></td>

                    <td><?= $milk['litres']; ?> L</td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

    <!-- Recent Treatments -->

    <div class="table-card">

        <h3>Recent Treatments</h3>

        <table class="custom-table">

            <thead>

                <tr>

                    <th>Cow</th>
                    <th>Disease</th>
                    <th>Date</th>

                </tr>

            </thead>

            <tbody>

            <?php while($row = mysqli_fetch_assoc($recentTreatments)): ?>

                <tr>

                    <td><?= $row['cow_name']; ?></td>

                    <td><?= $row['disease']; ?></td>

                    <td><?= $row['treatment_date']; ?></td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

   
</div>

<script>

const ctx = document.getElementById('milkChart');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels: <?= json_encode($chartLabels); ?>,

        datasets: [{

            label: 'Milk Production (Litres)',

            data: <?= json_encode($chartValues); ?>,

            borderWidth: 1

        }]
    },

    options: {

        responsive: true,

        scales: {

            y: {

                beginAtZero: true

            }

        }

    }

});

</script>

</body>
</html>
