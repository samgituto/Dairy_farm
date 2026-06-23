<?php
include 'includes/db.php';
include 'includes/header.php';
include 'includes/sidebar.php';

/* ===========================
   DASHBOARD STATISTICS
=========================== */

$totalVaccinations = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) total FROM vaccinations")
)['total'] ?? 0;

$totalTreatments = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) total FROM treatments")
)['total'] ?? 0;

$totalAI = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) total FROM breeding_records")
)['total'] ?? 0;

$totalCalvings = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) total FROM calving_records")
)['total'] ?? 0;
?>

<div class="main-content">

    <div class="page-header">
        <h1>Health & Breeding Reports</h1>
    </div>

    <!-- SUMMARY CARDS -->

    <div class="cards">

        <div class="card">
            <div class="card-icon">
                <i class="fas fa-syringe"></i>
            </div>
            <div>
                <h4>Vaccinations</h4>
                <h2><?= $totalVaccinations ?></h2>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div>
                <h4>Treatments</h4>
                <h2><?= $totalTreatments ?></h2>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">
                <i class="fas fa-dna"></i>
            </div>
            <div>
                <h4>AI Services</h4>
                <h2><?= $totalAI ?></h2>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">
                <i class="fas fa-baby"></i>
            </div>
            <div>
                <h4>Calving Records</h4>
                <h2><?= $totalCalvings ?></h2>
            </div>
        </div>

    </div>

    <!-- CHARTS -->

    <div class="cards">

        <div class="chart-card">
            <h3>Vaccinations vs Treatments</h3>
            <canvas id="healthChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>Breeding & Calving Statistics</h3>
            <canvas id="breedingChart"></canvas>
        </div>

    </div>

    <!-- RECENT VACCINATIONS -->

    <div class="table-card">

        <h3>
            <i class="fas fa-syringe"></i>
            Recent Vaccinations
        </h3>

        <table class="custom-table">

            <thead>
            <tr>
                <th>Cow ID</th>
                <th>Vaccine</th>
                <th>Date</th>
                <th>Next Due</th>
            </tr>
            </thead>

            <tbody>

            <?php

            $vaccinations = mysqli_query($conn,"
                SELECT *
                FROM vaccinations
                ORDER BY vaccination_date DESC
                LIMIT 10
            ");

            while($row=mysqli_fetch_assoc($vaccinations))
            {
            ?>

            <tr>
                <td><?= $row['cow_id'] ?></td>
                <td><?= $row['vaccine_name'] ?></td>
                <td><?= $row['vaccination_date'] ?></td>
                <td><?= $row['next_due_date'] ?></td>
            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

    <!-- RECENT TREATMENTS -->

    <div class="table-card">

        <h3>
            <i class="fas fa-stethoscope"></i>
            Recent Treatments
        </h3>

        <table class="custom-table">

            <thead>
            <tr>
                <th>Cow ID</th>
                <th>Disease</th>
                <th>Treatment</th>
                <th>Date</th>
            </tr>
            </thead>

            <tbody>

            <?php

            $treatments = mysqli_query($conn,"
                SELECT *
                FROM treatments
                ORDER BY treatment_date DESC
                LIMIT 10
            ");

            while($row=mysqli_fetch_assoc($treatments))
            {
            ?>

            <tr>
                <td><?= $row['cow_id'] ?></td>
                <td><?= $row['disease'] ?></td>
                <td><?= $row['treatment_given'] ?></td>
                <td><?= $row['treatment_date'] ?></td>
            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

    <!-- BREEDING REPORT -->

    <div class="table-card">

        <h3>
            <i class="fas fa-dna"></i>
            Recent Breeding Records
        </h3>

        <table class="custom-table">

            <thead>
            <tr>
                <th>Cow ID</th>
                <th>Semen Code</th>
                <th>AI Date</th>
                <th>Status</th>
            </tr>
            </thead>

            <tbody>

            <?php

            $breeding = mysqli_query($conn,"
                SELECT *
                FROM breeding_records
                ORDER BY insemination_date DESC
                LIMIT 10
            ");

            while($row=mysqli_fetch_assoc($breeding))
            {
            ?>

            <tr>
                <td><?= $row['cow_id'] ?></td>
                <td><?= $row['semen_code'] ?></td>
                <td><?= $row['ai_date'] ?></td>
                <td>
                    <span class="badge-success">
                        <?= $row['status'] ?>
                    </span>
                </td>
            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

    <!-- CALVING REPORT -->

    <div class="table-card">

        <h3>
            <i class="fas fa-baby"></i>
            Recent Calving Records
        </h3>

        <table class="custom-table">

            <thead>
            <tr>
                <th>Cow ID</th>
                <th>Calf ID</th>
                <th>Calving Date</th>
                <th>Gender</th>
            </tr>
            </thead>

            <tbody>

            <?php

            $calving = mysqli_query($conn,"
                SELECT *
                FROM calving_records
                ORDER BY calving_date DESC
                LIMIT 10
            ");

            while($row=mysqli_fetch_assoc($calving))
            {
            ?>

            <tr>
                <td><?= $row['cow_id'] ?></td>
                <td><?= $row['calf_id'] ?></td>
                <td><?= $row['calving_date'] ?></td>
                <td><?= $row['gender'] ?></td>
            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function(){

    const healthCtx =
    document.getElementById('healthChart');

    new Chart(healthCtx,{

        type:'bar',

        data:{
            labels:[
                'Vaccinations',
                'Treatments'
            ],

            datasets:[{
                label:'Records',

                data:[
                    <?= $totalVaccinations ?>,
                    <?= $totalTreatments ?>
                ]
            }]
        },

        options:{
            responsive:true,
            maintainAspectRatio:false
        }

    });

    const breedingCtx =
    document.getElementById('breedingChart');

    new Chart(breedingCtx,{

        type:'pie',

        data:{

            labels:[
                'AI Records',
                'Calving Records'
            ],

            datasets:[{

                data:[
                    <?= $totalAI ?>,
                    <?= $totalCalvings ?>
                ]

            }]
        },

        options:{
            responsive:true,
            maintainAspectRatio:false
        }

    });

});

</script>

<?php include 'includes/footer.php'; ?>