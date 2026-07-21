<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";


/* ============================================================
   LOAD PREGNANT / DRY COWS WITH ACTIVE BREEDING RECORDS
============================================================ */

$pregnantCowsQuery = mysqli_query($conn, "
    SELECT
        b.id AS breeding_id,
        b.cow_id,
        b.insemination_date,
        b.expected_calving_date,
        b.status AS breeding_status,

        c.tag_number,
        c.cow_name,
        c.breed,
        c.status AS cow_status

    FROM breeding_records b

    INNER JOIN cows c
        ON c.id = b.cow_id

    WHERE b.status IN ('Pregnant', 'Pending')
    AND c.status IN ('Pregnant', 'Dry')

    ORDER BY b.expected_calving_date ASC,
             b.insemination_date ASC
");

if (!$pregnantCowsQuery) {
    die("Pregnant cows query failed: " . mysqli_error($conn));
}


/* ============================================================
   SAVE CALVING RECORD
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $breeding_id = (int) $_POST['breeding_id'];

    $calf_name = mysqli_real_escape_string(
        $conn,
        $_POST['calf_name']
    );

    $calving_date = mysqli_real_escape_string(
        $conn,
        $_POST['calving_date']
    );

    $calf_gender = mysqli_real_escape_string(
        $conn,
        $_POST['calf_gender']
    );

    $calf_weight = (float) $_POST['calf_weight'];

    $remarks = mysqli_real_escape_string(
        $conn,
        $_POST['remarks']
    );


    /* ========================================================
       VALIDATION
    ======================================================== */

    if ($breeding_id <= 0) {

        $message = "Please select the mother cow.";

    } elseif ($calf_name === "") {

        $message = "Please enter calf name.";

    } elseif ($calving_date === "") {

        $message = "Please select calving date.";

    } elseif ($calf_gender === "") {

        $message = "Please select calf gender.";

    } elseif ($calf_weight <= 0) {

        $message = "Calf weight must be greater than zero.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* =================================================
               GET MOTHER AND BREEDING RECORD DETAILS
            ================================================= */

            $motherQuery = mysqli_query($conn, "
                SELECT
                    b.id AS breeding_id,
                    b.cow_id,
                    b.status AS breeding_status,

                    c.tag_number,
                    c.cow_name,
                    c.breed,
                    c.status AS cow_status

                FROM breeding_records b

                INNER JOIN cows c
                    ON c.id = b.cow_id

                WHERE b.id = '$breeding_id'

                LIMIT 1
            ");

            if (!$motherQuery) {
                throw new Exception(mysqli_error($conn));
            }

            if (mysqli_num_rows($motherQuery) === 0) {
                throw new Exception("Selected breeding record was not found.");
            }

            $mother = mysqli_fetch_assoc($motherQuery);

            $mother_id = (int) $mother['cow_id'];

            $mother_breed = mysqli_real_escape_string(
                $conn,
                $mother['breed'] ?? ''
            );

            $mother_label = mysqli_real_escape_string(
                $conn,
                $mother['tag_number'] . " - " . ($mother['cow_name'] ?? 'Unnamed')
            );


            /* =================================================
               GENERATE CALF TAG NUMBER
            ================================================= */

            $calf_tag_number = "CALF-" . date("YmdHis") . rand(10, 99);


            /* =================================================
               ADD NEW CALF INTO COWS TABLE
            ================================================= */

            $calfNotes = mysqli_real_escape_string(
                $conn,
                "Calf born from mother: " . $mother_label
            );

            $insertCalfSql = "
                INSERT INTO cows (
                    tag_number,
                    cow_name,
                    breed,
                    gender,
                    date_of_birth,
                    weight,
                    status,
                    notes
                )
                VALUES (
                    '$calf_tag_number',
                    '$calf_name',
                    '$mother_breed',
                    '$calf_gender',
                    '$calving_date',
                    '$calf_weight',
                    'Calf',
                    '$calfNotes'
                )
            ";

            if (!mysqli_query($conn, $insertCalfSql)) {
                throw new Exception("Calf insert failed: " . mysqli_error($conn));
            }

            $calf_id = mysqli_insert_id($conn);


            /* =================================================
               SAVE CALVING RECORD
            ================================================= */

            $insertCalvingSql = "
                INSERT INTO calving_records (
                    cow_id,
                    breeding_id,
                    calf_id,
                    calf_name,
                    calving_date,
                    calf_gender,
                    calf_weight,
                    remarks
                )
                VALUES (
                    '$mother_id',
                    '$breeding_id',
                    '$calf_id',
                    '$calf_name',
                    '$calving_date',
                    '$calf_gender',
                    '$calf_weight',
                    '$remarks'
                )
            ";

            if (!mysqli_query($conn, $insertCalvingSql)) {
                throw new Exception("Calving record insert failed: " . mysqli_error($conn));
            }


            /* =================================================
               UPDATE MOTHER STATUS TO LACTATING
            ================================================= */

            $updateMotherSql = "
                UPDATE cows
                SET status = 'Lactating'
                WHERE id = '$mother_id'
            ";

            if (!mysqli_query($conn, $updateMotherSql)) {
                throw new Exception("Mother status update failed: " . mysqli_error($conn));
            }


            /* =================================================
               UPDATE BREEDING RECORD TO COMPLETED
            ================================================= */

            $updateBreedingSql = "
                UPDATE breeding_records
                SET status = 'Completed'
                WHERE id = '$breeding_id'
            ";

            if (!mysqli_query($conn, $updateBreedingSql)) {
                throw new Exception("Breeding record update failed: " . mysqli_error($conn));
            }

            mysqli_commit($conn);

            header("Location: calving.php?success=1");
            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $message = "Error: " . $e->getMessage();
        }
    }
}


/* ============================================================
   LOAD CALVING RECORDS
============================================================ */

$calvingRecordsQuery = mysqli_query($conn, "
    SELECT
        cr.*,

        mother.tag_number AS mother_tag,
        mother.cow_name AS mother_name,
        mother.status AS mother_status,

        calf.tag_number AS calf_tag,
        calf.cow_name AS calf_cow_name,
        calf.status AS calf_status,

        b.insemination_date,
        b.expected_calving_date,
        b.status AS breeding_status

    FROM calving_records cr

    INNER JOIN cows mother
        ON mother.id = cr.cow_id

    LEFT JOIN cows calf
        ON calf.id = cr.calf_id

    LEFT JOIN breeding_records b
        ON b.id = cr.breeding_id

    ORDER BY cr.calving_date DESC,
             cr.created_at DESC
");

if (!$calvingRecordsQuery) {
    die("Calving records query failed: " . mysqli_error($conn));
}


/* ============================================================
   SUMMARY COUNTS
============================================================ */

$totalCalvingsQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM calving_records
");

$totalCalvings = mysqli_fetch_assoc($totalCalvingsQuery)['total'] ?? 0;


$maleCalvesQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM calving_records
    WHERE calf_gender = 'Male'
");

$maleCalves = mysqli_fetch_assoc($maleCalvesQuery)['total'] ?? 0;


$femaleCalvesQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM calving_records
    WHERE calf_gender = 'Female'
");

$femaleCalves = mysqli_fetch_assoc($femaleCalvesQuery)['total'] ?? 0;


$pendingPregnanciesQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM breeding_records
    WHERE status IN ('Pregnant', 'Pending')
");

$pendingPregnancies = mysqli_fetch_assoc($pendingPregnanciesQuery)['total'] ?? 0;


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
                <i class="fas fa-baby-carriage"></i>
                Calving Management
            </h1>

            <p>
                Record birthing events, add calves, update mother status, and complete pregnancy records.
            </p>
        </div>

        <div>
            <a href="pregnancy.php" class="btn btn-primary">
                <i class="fas fa-calendar-alt"></i>
                Pregnancy Calendar
            </a>
        </div>

    </div>


    <!-- ======================================================
         ALERTS
    ======================================================= -->

    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Calving recorded successfully. Mother updated to lactating and calf added to herd.
        </div>

    <?php } ?>


    <!-- ======================================================
         SUMMARY CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-list"></i>
            </div>

            <div>
                <h4>Total Calvings</h4>
                <h2><?= number_format($totalCalvings); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-mars"></i>
            </div>

            <div>
                <h4>Male Calves</h4>
                <h2><?= number_format($maleCalves); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-venus"></i>
            </div>

            <div>
                <h4>Female Calves</h4>
                <h2><?= number_format($femaleCalves); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-heartbeat"></i>
            </div>

            <div>
                <h4>Pending Pregnancies</h4>
                <h2><?= number_format($pendingPregnancies); ?></h2>
            </div>

        </div>

    </div>


    <!-- ======================================================
         CALVING FORM
    ======================================================= -->

    <form method="POST" action="calving.php">

        <div class="form-card">

            <h3>
                <i class="fas fa-plus-circle"></i>
                Record Calving Event
            </h3>

            <div class="form-grid">

                <!-- MOTHER -->

                <div class="form-group">

                    <label>Mother Cow / Pregnancy Record</label>

                    <select name="breeding_id" class="form-control" required>

                        <option value="">Select Mother Cow</option>

                        <?php if (mysqli_num_rows($pregnantCowsQuery) > 0) { ?>

                            <?php while ($cow = mysqli_fetch_assoc($pregnantCowsQuery)) { ?>

                                <option value="<?= $cow['breeding_id']; ?>">

                                    <?= htmlspecialchars($cow['tag_number']); ?>
                                    -
                                    <?= htmlspecialchars($cow['cow_name'] ?? 'Unnamed'); ?>
                                    |
                                    Cow Status:
                                    <?= htmlspecialchars($cow['cow_status']); ?>
                                    |
                                    AI Date:
                                    <?= date("d M Y", strtotime($cow['insemination_date'])); ?>

                                    <?php if (!empty($cow['expected_calving_date'])) { ?>
                                        |
                                        Expected:
                                        <?= date("d M Y", strtotime($cow['expected_calving_date'])); ?>
                                    <?php } ?>

                                </option>

                            <?php } ?>

                        <?php } ?>

                    </select>

                </div>


                <!-- CALF NAME -->

                <div class="form-group">

                    <label>Calf Name</label>

                    <input
                        type="text"
                        name="calf_name"
                        class="form-control"
                        placeholder="Enter calf name"
                        required>

                </div>


                <!-- CALVING DATE -->

                <div class="form-group">

                    <label>Calving Date</label>

                    <input
                        type="date"
                        name="calving_date"
                        class="form-control"
                        value="<?= date('Y-m-d'); ?>"
                        required>

                </div>


                <!-- CALF WEIGHT -->

                <div class="form-group">

                    <label>Calf Weight</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="calf_weight"
                        class="form-control"
                        placeholder="Enter calf weight"
                        required>

                </div>


                <!-- GENDER -->

                <div class="form-group">

                    <label>Calf Gender</label>

                    <select name="calf_gender" class="form-control" required>

                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>

                    </select>

                </div>


                <!-- REMARKS -->

                <div class="form-group" style="grid-column: 1 / -1;">

                    <label>Remarks</label>

                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="3"
                        placeholder="Example: Normal birth, assisted delivery, twins, weak calf, etc."></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Save Calving Record
                </button>

                <a href="pregnancy.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </div>

    </form>


    <!-- ======================================================
         CALVING RECORDS TABLE
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-list"></i>
                Calving Records
            </h3>

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
                        <th>Mother Status</th>
                        <th>Pregnancy Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($calvingRecordsQuery) > 0) {

                        while ($record = mysqli_fetch_assoc($calvingRecordsQuery)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($record['mother_tag']); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($record['mother_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($record['calf_tag'] ?? 'N/A'); ?>
                                    </strong>
                                    <br>
                                    <?= htmlspecialchars($record['calf_name'] ?? $record['calf_cow_name'] ?? 'Unnamed Calf'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($record['calving_date'])); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['calf_gender']); ?>
                                </td>

                                <td>
                                    <?= number_format($record['calf_weight'], 2); ?>
                                </td>

                                <td>
                                    <span class="badge-success">
                                        <?= htmlspecialchars($record['mother_status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($record['breeding_status'] === 'Completed') { ?>

                                        <span class="badge-success">
                                            Completed
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge-warning">
                                            <?= htmlspecialchars($record['breeding_status'] ?? 'N/A'); ?>
                                        </span>

                                    <?php } ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['remarks'] ?? 'N/A'); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="9" style="text-align:center;">
                                No calving records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>