<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* ============================================================
   SELECTED COW FILTER
============================================================ */

$selected_cow_id = "";

if (isset($_GET['cow_id']) && $_GET['cow_id'] !== "") {
    $selected_cow_id = (int) $_GET['cow_id'];
}


/* ============================================================
   HELPER FUNCTION FOR DISPLAYING DATES
============================================================ */

function displayDueDate($dateValue)
{
    if ($dateValue === null || $dateValue === "") {
        return "No upcoming date";
    }

    return date("d M Y", strtotime($dateValue));
}


/* ============================================================
   LOAD COWS THAT HAVE BREEDING RECORDS
============================================================ */

$cowsQuery = mysqli_query($conn, "
    SELECT DISTINCT
        c.id,
        c.tag_number,
        c.cow_name,
        c.status
    FROM cows c

    INNER JOIN breeding_records b
        ON b.cow_id = c.id

    ORDER BY c.tag_number ASC
");

if (!$cowsQuery) {
    die("Cows query failed: " . mysqli_error($conn));
}


/* ============================================================
   BREEDING RECORD FILTER
============================================================ */

$where = "
    WHERE b.status != 'Failed'
";

if ($selected_cow_id !== "") {
    $where .= "
        AND b.cow_id = '$selected_cow_id'
    ";
}


/* ============================================================
   LOAD PREGNANCY DATA FROM BREEDING RECORDS
============================================================ */

$pregnancyQuery = mysqli_query($conn, "
    SELECT
        b.id AS breeding_id,
        b.cow_id,
        b.insemination_date,
        b.semen_batch,
        b.technician,
        b.expected_calving_date,
        b.status AS breeding_status,
        b.remarks,

        c.tag_number,
        c.cow_name,
        c.breed,
        c.status AS cow_status

    FROM breeding_records b

    INNER JOIN cows c
        ON c.id = b.cow_id

    $where

    ORDER BY b.insemination_date DESC
");

if (!$pregnancyQuery) {
    die("Pregnancy query failed: " . mysqli_error($conn));
}


/* ============================================================
   SUMMARY VARIABLES
============================================================ */

$totalPregnancyRecords = mysqli_num_rows($pregnancyQuery);

$today = new DateTime(date("Y-m-d"));

$upcomingCalvingCount = 0;
$upcomingDryOffCount = 0;
$upcomingHealthCheckCount = 0;

$urgentCalvingCount = 0;
$urgentDryOffCount = 0;
$urgentHealthCheckCount = 0;

$nextHealthCheckDate = null;
$nextDryOffDate = null;
$nextCalvingDate = null;

$pregnancyRecords = [];


/* ============================================================
   PROCESS PREGNANCY RECORDS
============================================================ */

while ($row = mysqli_fetch_assoc($pregnancyQuery)) {

    $inseminationDate = new DateTime($row['insemination_date']);


    /* ========================================================
       EXPECTED CALVING DATE
       If database has date, use it.
       Otherwise calculate 280 days after breeding date.
    ======================================================== */

    if (!empty($row['expected_calving_date'])) {

        $expectedCalvingDate = new DateTime($row['expected_calving_date']);

    } else {

        $expectedCalvingDate = clone $inseminationDate;
        $expectedCalvingDate->modify("+280 days");
    }


    /* ========================================================
       DRY-OFF DATE
       60 days before expected calving date.
    ======================================================== */

    $dryOffDate = clone $expectedCalvingDate;
    $dryOffDate->modify("-60 days");


    /* ========================================================
       HEALTH CHECK DATE
       60 days after artificial insemination.
    ======================================================== */

    $healthCheckDate = clone $inseminationDate;
    $healthCheckDate->modify("+60 days");


    /* ========================================================
       DAYS REMAINING
    ======================================================== */

    $daysToHealthCheck = (int) $today->diff($healthCheckDate)->format("%r%a");

    $daysToDryOff = (int) $today->diff($dryOffDate)->format("%r%a");

    $daysToCalving = (int) $today->diff($expectedCalvingDate)->format("%r%a");


    /* ========================================================
       COUNT ALL UPCOMING EVENTS
    ======================================================== */

    if ($daysToHealthCheck >= 0) {

        $upcomingHealthCheckCount++;

        $healthDateValue = $healthCheckDate->format("Y-m-d");

        if (
            $nextHealthCheckDate === null ||
            strtotime($healthDateValue) < strtotime($nextHealthCheckDate)
        ) {
            $nextHealthCheckDate = $healthDateValue;
        }
    }


    if ($daysToDryOff >= 0) {

        $upcomingDryOffCount++;

        $dryOffDateValue = $dryOffDate->format("Y-m-d");

        if (
            $nextDryOffDate === null ||
            strtotime($dryOffDateValue) < strtotime($nextDryOffDate)
        ) {
            $nextDryOffDate = $dryOffDateValue;
        }
    }


    if ($daysToCalving >= 0) {

        $upcomingCalvingCount++;

        $calvingDateValue = $expectedCalvingDate->format("Y-m-d");

        if (
            $nextCalvingDate === null ||
            strtotime($calvingDateValue) < strtotime($nextCalvingDate)
        ) {
            $nextCalvingDate = $calvingDateValue;
        }
    }


    /* ========================================================
       COUNT URGENT ALERTS WITHIN NEXT 30 DAYS
    ======================================================== */

    if ($daysToHealthCheck >= 0 && $daysToHealthCheck <= 30) {
        $urgentHealthCheckCount++;
    }

    if ($daysToDryOff >= 0 && $daysToDryOff <= 30) {
        $urgentDryOffCount++;
    }

    if ($daysToCalving >= 0 && $daysToCalving <= 30) {
        $urgentCalvingCount++;
    }


    /* ========================================================
       STORE CALCULATED VALUES
    ======================================================== */

    $row['calculated_expected_calving_date'] = $expectedCalvingDate->format("Y-m-d");

    $row['dry_off_date'] = $dryOffDate->format("Y-m-d");

    $row['health_check_date'] = $healthCheckDate->format("Y-m-d");

    $row['days_to_health_check'] = $daysToHealthCheck;

    $row['days_to_dry_off'] = $daysToDryOff;

    $row['days_to_calving'] = $daysToCalving;

    $pregnancyRecords[] = $row;
}


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
                <i class="fas fa-baby"></i>
                Pregnancy Management
            </h1>

            <p>
                Track pregnancy checks, dry-off dates, and upcoming calving dates.
            </p>
        </div>

        <div>
            <a href="breeding.php" class="btn btn-primary">
                <i class="fas fa-dna"></i>
                Breeding Records
            </a>
        </div>

    </div>


    <!-- ======================================================
         FILTER BY COW
    ======================================================= -->

    <div class="table-card">

        <form method="GET" action="pregnancy.php">

            <div class="search-bar">

                <select name="cow_id" class="form-control">

                    <option value="">All Cows</option>

                    <?php while ($cow = mysqli_fetch_assoc($cowsQuery)) { ?>

                        <option
                            value="<?= $cow['id']; ?>"
                            <?= ($selected_cow_id == $cow['id']) ? 'selected' : ''; ?>>

                            <?= htmlspecialchars($cow['tag_number']); ?>
                            -
                            <?= htmlspecialchars($cow['cow_name'] ?? 'Unnamed'); ?>
                            -
                            <?= htmlspecialchars($cow['status']); ?>

                        </option>

                    <?php } ?>

                </select>


                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Show Details
                </button>


                <a href="pregnancy.php" class="btn btn-secondary">
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- ======================================================
         SUMMARY CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-list"></i>
            </div>

            <div>
                <h4>Pregnancy Records</h4>

                <h2><?= number_format($totalPregnancyRecords); ?></h2>

                <p class="card-date">
                    Active pregnancy calendar records
                </p>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-stethoscope"></i>
            </div>

            <div>
                <h4>Health Checks Due</h4>

                <h2><?= number_format($upcomingHealthCheckCount); ?></h2>

                <p class="card-date">
                    Next:
                    <?= displayDueDate($nextHealthCheckDate); ?>
                </p>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-calendar-minus"></i>
            </div>

            <div>
                <h4>Dry-Off Due</h4>

                <h2><?= number_format($upcomingDryOffCount); ?></h2>

                <p class="card-date">
                    Next:
                    <?= displayDueDate($nextDryOffDate); ?>
                </p>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-baby-carriage"></i>
            </div>

            <div>
                <h4>Upcoming Calving</h4>

                <h2><?= number_format($upcomingCalvingCount); ?></h2>

                <p class="card-date">
                    Next:
                    <?= displayDueDate($nextCalvingDate); ?>
                </p>
            </div>

        </div>

    </div>


    <!-- ======================================================
         AUTOMATED ALERTS
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-bell"></i>
                Automated Alerts & Calendar
            </h3>

        </div>


        <div class="alert-list">

            <?php
            $hasAlerts = false;

            foreach ($pregnancyRecords as $record) {

                $cowLabel = $record['tag_number'] . " - " . ($record['cow_name'] ?? 'Unnamed');


                /* =================================================
                   HEALTH CHECK ALERT
                ================================================= */

                if ($record['days_to_health_check'] >= 0 && $record['days_to_health_check'] <= 30) {

                    $hasAlerts = true;
            ?>

                    <div class="alert-box alert-info">

                        <strong>Health Check Due:</strong>

                        <?= htmlspecialchars($cowLabel); ?>

                        should be checked on

                        <strong>
                            <?= date("d M Y", strtotime($record['health_check_date'])); ?>
                        </strong>.

                    </div>

            <?php
                }


                /* =================================================
                   DRY-OFF ALERT
                ================================================= */

                if ($record['days_to_dry_off'] >= 0 && $record['days_to_dry_off'] <= 30) {

                    $hasAlerts = true;
            ?>

                    <div class="alert-box alert-warning">

                        <strong>Dry-Off Alert:</strong>

                        Stop milking

                        <?= htmlspecialchars($cowLabel); ?>

                        on

                        <strong>
                            <?= date("d M Y", strtotime($record['dry_off_date'])); ?>
                        </strong>.

                    </div>

            <?php
                }


                /* =================================================
                   CALVING ALERT
                ================================================= */

                if ($record['days_to_calving'] >= 0 && $record['days_to_calving'] <= 30) {

                    $hasAlerts = true;
            ?>

                    <div class="alert-box alert-danger">

                        <strong>Upcoming Calving:</strong>

                        <?= htmlspecialchars($cowLabel); ?>

                        is expected to calve on

                        <strong>
                            <?= date("d M Y", strtotime($record['calculated_expected_calving_date'])); ?>
                        </strong>.

                    </div>

            <?php
                }
            }
            ?>


            <?php if ($hasAlerts === false) { ?>

                <div class="alert-box alert-success">
                    No urgent pregnancy alerts within the next 30 days.
                </div>

            <?php } ?>

        </div>

    </div>


    <!-- ======================================================
         PREGNANCY RECORDS TABLE
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-calendar-alt"></i>
                Pregnancy Calendar
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cow</th>
                        <th>Breed</th>
                        <th>AI Date</th>
                        <th>Health Check Date</th>
                        <th>Dry-Off Date</th>
                        <th>Expected Calving</th>
                        <th>Days to Health Check</th>
                        <th>Days to Dry-Off</th>
                        <th>Days to Calving</th>
                        <th>Status</th>
                        <th>Technician</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($pregnancyRecords)) { ?>

                        <?php
                        $count = 1;

                        foreach ($pregnancyRecords as $record) {

                            /* =====================================
                               HEALTH CHECK TEXT
                            ===================================== */

                            if ($record['days_to_health_check'] < 0) {
                                $healthCheckText = "Passed";
                                $healthCheckBadge = "badge-danger";
                            } else {
                                $healthCheckText = $record['days_to_health_check'] . " days";
                                $healthCheckBadge = "badge-success";
                            }


                            /* =====================================
                               DRY-OFF TEXT
                            ===================================== */

                            if ($record['days_to_dry_off'] < 0) {
                                $dryOffText = "Passed";
                                $dryOffBadge = "badge-danger";
                            } else {
                                $dryOffText = $record['days_to_dry_off'] . " days";
                                $dryOffBadge = "badge-success";
                            }


                            /* =====================================
                               CALVING TEXT
                            ===================================== */

                            if ($record['days_to_calving'] < 0) {
                                $calvingText = "Passed";
                                $calvingBadge = "badge-danger";
                            } else {
                                $calvingText = $record['days_to_calving'] . " days";
                                $calvingBadge = "badge-success";
                            }
                        ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($record['tag_number']); ?>
                                    </strong>

                                    <br>

                                    <?= htmlspecialchars($record['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['breed'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($record['insemination_date'])); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($record['health_check_date'])); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($record['dry_off_date'])); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= date("d M Y", strtotime($record['calculated_expected_calving_date'])); ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="<?= $healthCheckBadge; ?>">
                                        <?= htmlspecialchars($healthCheckText); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="<?= $dryOffBadge; ?>">
                                        <?= htmlspecialchars($dryOffText); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="<?= $calvingBadge; ?>">
                                        <?= htmlspecialchars($calvingText); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($record['breeding_status'] === 'Pregnant') { ?>

                                        <span class="badge-success">
                                            Pregnant
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge-warning">
                                            Pending
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($record['technician'] ?? 'N/A'); ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="12" style="text-align:center;">
                                No pregnancy records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>