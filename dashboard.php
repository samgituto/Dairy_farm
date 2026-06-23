<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

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

// Today's Milk Production
$milkTodayResult = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(litres),0) AS total
     FROM milk_records
     WHERE record_date = CURDATE()"
);
$milkToday = mysqli_fetch_assoc($milkTodayResult);

// AI Alert Logic: if today's production is below the average of the previous 5 days
$aiAlert = false;
$aiAlertMessage = '';

$avg5DaysResult = mysqli_query(
    $conn,
    "SELECT AVG(day_total) AS avg_total FROM (
        SELECT SUM(litres) AS day_total
        FROM milk_records
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
          AND record_date < CURDATE()
        GROUP BY record_date
    ) t"
);
$avg5DaysRow = mysqli_fetch_assoc($avg5DaysResult);
$avg5Days = (float)($avg5DaysRow['avg_total'] ?? 0);

$todayProduction = (float)($milkToday['total'] ?? 0);

if ($avg5Days > 0 && $todayProduction < $avg5Days) {
    $aiAlert = true;
    $aiAlertMessage = sprintf(
        "AI Alert: Today\'s milk production (%.1f L) is below the 5-day average (%.1f L).",
        $todayProduction,
        $avg5Days
    );
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

            <p>
                <?= date("l, d F Y"); ?>
            </p>

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

    <!-- AI Insights -->

    <div class="ai-card">

        <h3>🤖 AI Farm Insights</h3>

        <?php if (!empty($aiAlert)): ?>
            <div class="alert alert-warning" role="alert" style="margin-bottom: 15px;">
                <?= htmlspecialchars($aiAlertMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <ul>


            <li>
                Monitor cows with declining milk production.
            </li>

            <li>
                Review vaccination schedules weekly.
            </li>

            <li>
                Prepare maternity pens for expected calvings.
            </li>

            <li>
                Feed and inventory analytics will appear in Phase 5.
            </li>

        </ul>

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
