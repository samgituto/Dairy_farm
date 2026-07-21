<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


/* ============================================================
   LOAD FORMULATIONS
============================================================ */

$formulationsQuery = mysqli_query($conn, "
    SELECT
        f.formulation_id,
        f.formulation_no,
        f.formulation_name,
        f.feed_category,
        f.notes,
        f.created_by,
        f.created_at,

        COUNT(fi.formulation_item_id) AS ingredient_count,

        IFNULL(SUM(fi.qty_per_cow_per_day), 0) AS total_qty_per_cow,

        GROUP_CONCAT(
            CONCAT(
                fi.ingredient_name,
                ' - ',
                fi.qty_per_cow_per_day,
                ' ',
                fi.unit
            )
            SEPARATOR '<br>'
        ) AS ingredients

    FROM feed_formulations f

    LEFT JOIN feed_formulation_items fi
        ON f.formulation_id = fi.formulation_id

    GROUP BY f.formulation_id

    ORDER BY f.created_at DESC
");


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-blender"></i>
                Feed Formulations
            </h1>

            <p>
                Manage feed formulations for different cow categories.
            </p>
        </div>

    </div>


    <div class="formulation-action-row">

        <a href="feed_schedules.php" class="btn btn-primary">
            <i class="fas fa-calendar-alt"></i>
            Feeds Schedule
        </a>

        <a href="add_formulation.php" class="btn btn-success">
            <i class="fas fa-plus"></i>
            Add Formulation
        </a>

    </div>


    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Formulation saved successfully.
        </div>

    <?php } ?>


    <?php if (isset($_GET['updated'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Formulation updated successfully.
        </div>

    <?php } ?>


    <?php if (isset($_GET['deleted'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Formulation deleted successfully.
        </div>

    <?php } ?>


    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-list"></i>
                Saved Formulations
            </h3>

        </div>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Formulation No</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Ingredients</th>
                        <th>Total Qty / Cow / Day</th>
                        <th>Created By</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($formulationsQuery) > 0) {

                        while ($row = mysqli_fetch_assoc($formulationsQuery)) {
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['formulation_no']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['formulation_name']); ?>
                                </td>

                                <td>
                                    <span class="badge-success">
                                        <?= htmlspecialchars($row['feed_category']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $row['ingredients'] ?? 'No ingredients'; ?>
                                </td>

                                <td>
                                    <?= number_format($row['total_qty_per_cow'], 3); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['created_by'] ?? 'System'); ?>
                                </td>

                                <td>
                                    <?= date("d M Y", strtotime($row['created_at'])); ?>
                                </td>

                                <td>

                                    <a
                                        href="edit_formulation.php?id=<?= $row['formulation_id']; ?>"
                                        class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>

                                    <a
                                        href="delete_formulation.php?id=<?= $row['formulation_id']; ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete this formulation?');">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </a>

                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="9" style="text-align:center;">
                                No feed formulations found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>