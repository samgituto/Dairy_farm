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
   SUMMARY COUNTS
============================================================ */

$totalVaccinations = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM vaccinations
");

$totalTreatments = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM treatments
");

$totalPregnancies = getSingleValue($conn, "
    SELECT COUNT(*) AS total
    FROM breeding_records
    WHERE status IN ('Pregnant', 'Pending')
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

/* ============================================================
   RECENT VACCINATIONS
============================================================ */

$vaccinations = mysqli_query($conn, "
    SELECT
        v.*,
        c.tag_number,
        c.cow_name
    FROM vaccinations v

    LEFT JOIN cows c
        ON c.id = v.cow_id

    ORDER BY v.vaccination_date DESC
    LIMIT 10
");

if (!$vaccinations) {
    die("Vaccinations query failed: " . mysqli_error($conn));
}


/* ============================================================
   RECENT TREATMENTS
============================================================ */

$treatments = mysqli_query($conn, "
    SELECT
        t.*,
        c.tag_number,
        c.cow_name
    FROM treatments t

    LEFT JOIN cows c
        ON c.id = t.cow_id

    ORDER BY t.treatment_date DESC
    LIMIT 10
");

if (!$treatments) {
    die("Treatments query failed: " . mysqli_error($conn));
}


/* ============================================================
   PREGNANCY RECORDS
============================================================ */

$pregnancyRecords = mysqli_query($conn, "
    SELECT
        b.id AS breeding_id,
        b.cow_id,
        b.insemination_date,
        b.expected_calving_date,
        b.status AS pregnancy_status,
        b.technician,
        b.semen_batch,

        c.tag_number,
        c.cow_name,
        c.breed,
        c.status AS cow_status,

        IFNULL(
            b.expected_calving_date,
            DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
        ) AS calculated_calving_date,

        DATE_SUB(
            IFNULL(
                b.expected_calving_date,
                DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
            ),
            INTERVAL 60 DAY
        ) AS dry_off_date,

        DATE_ADD(b.insemination_date, INTERVAL 60 DAY) AS health_check_date,

        DATEDIFF(
            IFNULL(
                b.expected_calving_date,
                DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
            ),
            CURDATE()
        ) AS days_to_calving

    FROM breeding_records b

    INNER JOIN cows c
        ON c.id = b.cow_id

    WHERE b.status IN ('Pregnant', 'Pending')

    ORDER BY calculated_calving_date ASC
    LIMIT 10
");

if (!$pregnancyRecords) {
    die("Pregnancy records query failed: " . mysqli_error($conn));
}


/* ============================================================
   UPCOMING CALVINGS
============================================================ */

$upcomingCalvings = mysqli_query($conn, "
    SELECT
        b.id AS breeding_id,
        b.cow_id,
        b.insemination_date,
        b.expected_calving_date,
        b.status AS pregnancy_status,

        c.tag_number,
        c.cow_name,
        c.breed,
        c.status AS cow_status,

        IFNULL(
            b.expected_calving_date,
            DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
        ) AS calculated_calving_date,

        DATEDIFF(
            IFNULL(
                b.expected_calving_date,
                DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
            ),
            CURDATE()
        ) AS days_to_calving

    FROM breeding_records b

    INNER JOIN cows c
        ON c.id = b.cow_id

    WHERE b.status IN ('Pregnant', 'Pending')
    AND DATE(
        IFNULL(
            b.expected_calving_date,
            DATE_ADD(b.insemination_date, INTERVAL 280 DAY)
        )
    ) >= CURDATE()

    ORDER BY calculated_calving_date ASC
    LIMIT 10
");

if (!$upcomingCalvings) {
    die("Upcoming calvings query failed: " . mysqli_error($conn));
}


/* ============================================================
   RECENT CALVING RECORDS
============================================================ */

$calving = mysqli_query($conn, "
    SELECT
        cr.*,

        mother.tag_number AS mother_tag,
        mother.cow_name AS mother_name,

        calf.tag_number AS calf_tag,
        calf.cow_name AS calf_name_from_cows

    FROM calving_records cr

    LEFT JOIN cows mother
        ON mother.id = cr.cow_id

    LEFT JOIN cows calf
        ON calf.id = cr.calf_id

    ORDER BY cr.calving_date DESC
    LIMIT 10
");

if (!$calving) {
    die("Calving query failed: " . mysqli_error($conn));
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-heartbeat"></i>
                Health Reports
            </h1>

            <p>
                View vaccinations, treatments, pregnancy records, and upcoming calving reports.
            </p>
        </div>

    </div>


    <!-- SUMMARY CARDS -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-syringe"></i>
            </div>

            <div>
                <h4>Vaccinations</h4>
                <h2><?= number_format($totalVaccinations); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-stethoscope"></i>
            </div>

            <div>
                <h4>Treatments</h4>
                <h2><?= number_format($totalTreatments); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-baby"></i>
            </div>

            <div>
                <h4>Pregnancy Records</h4>
                <h2><?= number_format($totalPregnancies); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-calendar-alt"></i>
            </div>

            <div>
                <h4>Upcoming Calvings</h4>
                <h2><?= number_format($totalUpcomingCalvings); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-baby-carriage"></i>
            </div>

            <div>
                <h4>Completed Calvings</h4>
                <h2><?= number_format($totalCalvings); ?></h2>
            </div>

        </div>

    </div>


    <!-- CHARTS -->

    <div class="cards">

        <div class="chart-card">

            <h3>
                <i class="fas fa-chart-bar"></i>
                Vaccinations vs Treatments
            </h3>

            <canvas id="healthChart"></canvas>

        </div>


        <div class="chart-card">

            <h3>
                <i class="fas fa-chart-pie"></i>
                Pregnancy & Calving Statistics
            </h3>

            <canvas id="pregnancyChart"></canvas>

        </div>

    </div>


    <!-- RECENT VACCINATIONS -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-syringe"></i>
                Recent Vaccinations
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cow</th>
                        <th>Vaccine</th>
                        <th>Date</th>
                        <th>Next Due</th>
                        <th>Administered By</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($vaccinations) > 0) {

                        while ($row = mysqli_fetch_assoc($vaccinations)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['tag_number'] ?? 'N/A'); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['vaccine_name']); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['vaccination_date'])); ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['next_due_date'])) { ?>
                                        <?= date("d M Y", strtotime($row['next_due_date'])); ?>
                                    <?php } else { ?>
                                        N/A
                                    <?php } ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['administered_by'] ?? 'N/A'); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="6" style="text-align:center;">
                                No vaccination records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- RECENT TREATMENTS -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-stethoscope"></i>
                Recent Treatments
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cow</th>
                        <th>Disease</th>
                        <th>Treatment</th>
                        <th>Date</th>
                        <th>Veterinarian</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($treatments) > 0) {

                        while ($row = mysqli_fetch_assoc($treatments)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['tag_number'] ?? 'N/A'); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['disease'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['treatment_given'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['treatment_date'])) { ?>
                                        <?= date("d M Y", strtotime($row['treatment_date'])); ?>
                                    <?php } else { ?>
                                        N/A
                                    <?php } ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['veterinarian'] ?? 'N/A'); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="6" style="text-align:center;">
                                No treatment records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- PREGNANCY RECORDS -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-baby"></i>
                Pregnancy Records
            </h3>

            <a href="pregnancy.php" class="btn btn-primary">
                <i class="fas fa-eye"></i>
                View Full Pregnancy Calendar
            </a>

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
                        <th>Days to Calving</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($pregnancyRecords) > 0) {

                        while ($row = mysqli_fetch_assoc($pregnancyRecords)) {

                            $daysToCalving = (int) $row['days_to_calving'];

                            if ($daysToCalving < 0) {
                                $calvingText = "Passed";
                                $badge = "badge-danger";
                            } elseif ($daysToCalving <= 30) {
                                $calvingText = $daysToCalving . " days";
                                $badge = "badge-warning";
                            } else {
                                $calvingText = $daysToCalving . " days";
                                $badge = "badge-success";
                            }
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['tag_number']); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['breed'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['insemination_date'])); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['health_check_date'])); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['dry_off_date'])); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= date("d M Y", strtotime($row['calculated_calving_date'])); ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="<?= $badge; ?>">
                                        <?= htmlspecialchars($calvingText); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-success">
                                        <?= htmlspecialchars($row['pregnancy_status']); ?>
                                    </span>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="9" style="text-align:center;">
                                No pregnancy records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- UPCOMING CALVINGS -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-calendar-alt"></i>
                Upcoming Calvings
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
                        <th>Expected Calving Date</th>
                        <th>Days Remaining</th>
                        <th>Cow Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($upcomingCalvings) > 0) {

                        while ($row = mysqli_fetch_assoc($upcomingCalvings)) {

                            $daysRemaining = (int) $row['days_to_calving'];

                            if ($daysRemaining <= 30) {
                                $badge = "badge-warning";
                            } else {
                                $badge = "badge-success";
                            }
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['tag_number']); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['breed'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['insemination_date'])); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= date("d M Y", strtotime($row['calculated_calving_date'])); ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="<?= $badge; ?>">
                                        <?= number_format($daysRemaining); ?> days
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['cow_status']); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="7" style="text-align:center;">
                                No upcoming calvings found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- RECENT CALVING RECORDS -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-baby-carriage"></i>
                Recent Calving Records
            </h3>

            <a href="calving.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Manage Calving
            </a>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mother Cow</th>
                        <th>Calf</th>
                        <th>Calving Date</th>
                        <th>Gender</th>
                        <th>Weight</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($calving) > 0) {

                        while ($row = mysqli_fetch_assoc($calving)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['mother_tag'] ?? 'N/A'); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['mother_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['calf_tag'] ?? 'N/A'); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($row['calf_name'] ?? $row['calf_name_from_cows'] ?? 'Unnamed Calf'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['calving_date'])); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['calf_gender'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= number_format($row['calf_weight'] ?? 0, 2); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="6" style="text-align:center;">
                                No calving records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const healthCtx = document.getElementById("healthChart");

    if (healthCtx) {

        new Chart(healthCtx, {
            type: "bar",

            data: {
                labels: [
                    "Vaccinations",
                    "Treatments"
                ],

                datasets: [
                    {
                        label: "Records",
                        data: [
                            <?= (int) $totalVaccinations; ?>,
                            <?= (int) $totalTreatments; ?>
                        ],
                        borderRadius: 8
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }


    const pregnancyCtx = document.getElementById("pregnancyChart");

    if (pregnancyCtx) {

        new Chart(pregnancyCtx, {
            type: "pie",

            data: {
                labels: [
                    "Pregnancy Records",
                    "Upcoming Calvings",
                    "Completed Calvings"
                ],

                datasets: [
                    {
                        data: [
                            <?= (int) $totalPregnancies; ?>,
                            <?= (int) $totalUpcomingCalvings; ?>,
                            <?= (int) $totalCalvings; ?>
                        ]
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

});
</script>

<?php include 'includes/footer.php'; ?>