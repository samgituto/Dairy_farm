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
   DEFAULT VALUES
============================================================ */

$categories = [
    'Lactating',
    'Pregnant',
    'Bull',
    'Calf',
    'Dry',
    'Heifer'
];

$selected_date = $_GET['feed_date']
    ?? $_POST['feed_date']
    ?? date("Y-m-d");

$selected_category = $_GET['category']
    ?? $_POST['category']
    ?? "";

$message = "";


/* ============================================================
   SAVE DAILY FEEDING RECORD
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feed_now'])) {

    $formulation_id = (int) $_POST['formulation_id'];

    $feed_date = mysqli_real_escape_string(
        $conn,
        $_POST['feed_date']
    );

    $recorded_by = $_SESSION['full_name']
        ?? $_SESSION['username']
        ?? 'System';


    /* ========================================================
       GET SELECTED FORMULATION
    ======================================================== */

    $formulationQuery = mysqli_query($conn, "
        SELECT *
        FROM feed_formulations
        WHERE formulation_id = '$formulation_id'
    ");

    if (mysqli_num_rows($formulationQuery) > 0) {

        $formulation = mysqli_fetch_assoc($formulationQuery);

        $feed_category = mysqli_real_escape_string(
            $conn,
            $formulation['feed_category']
        );


        /* ====================================================
           GET LIVE COW COUNT FROM cows.status
        ==================================================== */

        $cow_count = getSingleValue($conn, "
            SELECT COUNT(*) AS total
            FROM cows
            WHERE status = '$feed_category'
        ");


        /* ====================================================
           GET FORMULATION INGREDIENTS
        ==================================================== */

        $itemsQuery = mysqli_query($conn, "
            SELECT *
            FROM feed_formulation_items
            WHERE formulation_id = '$formulation_id'
        ");

        mysqli_begin_transaction($conn);

        try {

            while ($item = mysqli_fetch_assoc($itemsQuery)) {

                $formulation_item_id = (int) $item['formulation_item_id'];

                $ingredient_name = mysqli_real_escape_string(
                    $conn,
                    $item['ingredient_name']
                );

                $unit = mysqli_real_escape_string(
                    $conn,
                    $item['unit']
                );

                $qty_per_cow = (float) $item['qty_per_cow_per_day'];

                $total_required = $cow_count * $qty_per_cow;


                /* =============================================
                   INSERT OR UPDATE DAILY FEED USAGE

                   This prevents double feeding record for the same
                   ingredient on the same date.
                ============================================= */

                $insertUsageSql = "
                    INSERT INTO feed_daily_usage (
                        feed_date,
                        formulation_id,
                        formulation_item_id,
                        feed_category,
                        ingredient_name,
                        unit,
                        cow_count,
                        qty_per_cow_per_day,
                        total_required,
                        recorded_by
                    )
                    VALUES (
                        '$feed_date',
                        '$formulation_id',
                        '$formulation_item_id',
                        '$feed_category',
                        '$ingredient_name',
                        '$unit',
                        '$cow_count',
                        '$qty_per_cow',
                        '$total_required',
                        '$recorded_by'
                    )

                    ON DUPLICATE KEY UPDATE
                        cow_count = VALUES(cow_count),
                        qty_per_cow_per_day = VALUES(qty_per_cow_per_day),
                        total_required = VALUES(total_required),
                        recorded_by = VALUES(recorded_by),
                        updated_at = CURRENT_TIMESTAMP
                ";

                if (!mysqli_query($conn, $insertUsageSql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            header(
                "Location: feed_schedules.php?feed_date=" .
                urlencode($feed_date) .
                "&category=" .
                urlencode($selected_category) .
                "&fed=1"
            );

            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $message = "Error: " . $e->getMessage();
        }
    }
}


/* ============================================================
   CATEGORY FILTER
============================================================ */

$categoryFilter = "";

if ($selected_category !== "") {

    $category = mysqli_real_escape_string(
        $conn,
        $selected_category
    );

    $categoryFilter = "
        WHERE f.feed_category = '$category'
    ";
}


/* ============================================================
   COW COUNTS BY STATUS
============================================================ */

$cowCounts = [];

foreach ($categories as $cat) {
    $cowCounts[$cat] = 0;
}

$cowCountQuery = mysqli_query($conn, "
    SELECT
        status,
        COUNT(*) AS total
    FROM cows
    GROUP BY status
");

while ($row = mysqli_fetch_assoc($cowCountQuery)) {

    if (isset($cowCounts[$row['status']])) {
        $cowCounts[$row['status']] = (int) $row['total'];
    }
}


/* ============================================================
   FEED SCHEDULE QUERY

   Stock before feeding =
   inventory stock - previous daily feed usage

   Cow count updates automatically from cows.status.
============================================================ */

$safe_selected_date = mysqli_real_escape_string(
    $conn,
    $selected_date
);

$scheduleQuery = mysqli_query($conn, "
    SELECT
        f.formulation_id,
        f.formulation_no,
        f.formulation_name,
        f.feed_category,

        fi.formulation_item_id,
        fi.ingredient_name,
        fi.unit,
        fi.qty_per_cow_per_day,

        (
            SELECT COUNT(*)
            FROM cows c
            WHERE c.status = f.feed_category
        ) AS cow_count,

        (
            SELECT COUNT(*)
            FROM feed_daily_usage u
            WHERE u.formulation_id = f.formulation_id
            AND u.feed_date = '$safe_selected_date'
        ) AS deducted_today,

        CASE

            WHEN fi.unit = 'Kg' THEN

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN i.unit = 'Kgs' THEN i.quantity
                                WHEN i.unit = 'Grams' THEN i.quantity / 1000
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM inventory i
                    WHERE i.item_name = fi.ingredient_name
                )

                -

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN u.unit = 'Kg' THEN u.total_required
                                WHEN u.unit = 'g' THEN u.total_required / 1000
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM feed_daily_usage u
                    WHERE u.ingredient_name = fi.ingredient_name
                    AND u.feed_date < '$safe_selected_date'
                )


            WHEN fi.unit = 'g' THEN

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN i.unit = 'Grams' THEN i.quantity
                                WHEN i.unit = 'Kgs' THEN i.quantity * 1000
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM inventory i
                    WHERE i.item_name = fi.ingredient_name
                )

                -

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN u.unit = 'g' THEN u.total_required
                                WHEN u.unit = 'Kg' THEN u.total_required * 1000
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM feed_daily_usage u
                    WHERE u.ingredient_name = fi.ingredient_name
                    AND u.feed_date < '$safe_selected_date'
                )


            WHEN fi.unit = 'L' THEN

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN i.unit = 'Litres' THEN i.quantity
                                WHEN i.unit = 'Ml' THEN i.quantity / 1000
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM inventory i
                    WHERE i.item_name = fi.ingredient_name
                )

                -

                (
                    SELECT IFNULL(
                        SUM(
                            CASE
                                WHEN u.unit = 'L' THEN u.total_required
                                ELSE 0
                            END
                        ),
                        0
                    )
                    FROM feed_daily_usage u
                    WHERE u.ingredient_name = fi.ingredient_name
                    AND u.feed_date < '$safe_selected_date'
                )

            ELSE 0

        END AS stock_before_feeding

    FROM feed_formulations f

    INNER JOIN feed_formulation_items fi
        ON f.formulation_id = fi.formulation_id

    $categoryFilter

    ORDER BY
        FIELD(
            f.feed_category,
            'Lactating',
            'Pregnant',
            'Bull',
            'Calf',
            'Dry',
            'Heifer'
        ),
        f.formulation_name ASC,
        fi.ingredient_name ASC
");


/* ============================================================
   ORGANIZE DATA BY CATEGORY AND FORMULATION
============================================================ */

$schedules = [];

while ($row = mysqli_fetch_assoc($scheduleQuery)) {

    $category = $row['feed_category'];

    $formulation_id = $row['formulation_id'];

    if (!isset($schedules[$category])) {
        $schedules[$category] = [];
    }

    if (!isset($schedules[$category][$formulation_id])) {

        $schedules[$category][$formulation_id] = [
            'formulation_id' => $row['formulation_id'],
            'formulation_no' => $row['formulation_no'],
            'formulation_name' => $row['formulation_name'],
            'feed_category' => $row['feed_category'],
            'cow_count' => $row['cow_count'],
            'deducted_today' => $row['deducted_today'],
            'items' => []
        ];
    }

    $schedules[$category][$formulation_id]['items'][] = $row;
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
                <i class="fas fa-calendar-alt"></i>
                Feed Schedules
            </h1>

            <p>
                Daily feed requirements are calculated from cow status and available inventory stock.
            </p>
        </div>

        <div>
            <a href="formulations.php" class="btn btn-primary">
                <i class="fas fa-blender"></i>
                Formulations
            </a>
        </div>

    </div>


    <!-- ======================================================
         ALERT MESSAGES
    ======================================================= -->

    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <?php if (isset($_GET['fed'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Daily feeding record saved successfully.
        </div>

    <?php } ?>


    <!-- ======================================================
         CATEGORY SUMMARY CARDS
    ======================================================= -->

    <div class="feed-category-grid">

        <?php foreach ($categories as $cat) { ?>

            <div class="feed-category-card">

                <h4><?= htmlspecialchars($cat); ?></h4>

                <h2><?= number_format($cowCounts[$cat] ?? 0); ?></h2>

                <p>animals</p>

            </div>

        <?php } ?>

    </div>


    <!-- ======================================================
         FILTER FORM
    ======================================================= -->

    <div class="table-card">

        <form method="GET" action="feed_schedules.php">

            <div class="search-bar">

                <input
                    type="date"
                    name="feed_date"
                    class="form-control"
                    value="<?= htmlspecialchars($selected_date); ?>">


                <select name="category" class="form-control">

                    <option value="">All Cow Categories</option>

                    <?php foreach ($categories as $cat) { ?>

                        <option
                            value="<?= $cat; ?>"
                            <?= ($selected_category === $cat) ? 'selected' : ''; ?>>

                            <?= $cat; ?>

                        </option>

                    <?php } ?>

                </select>


                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>


                <a href="feed_schedules.php" class="btn btn-secondary">
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- ======================================================
         FEED SCHEDULES BY CATEGORY
    ======================================================= -->

    <?php if (!empty($schedules)) { ?>

        <?php foreach ($schedules as $category => $formulations) { ?>

            <div class="feed-category-section">

                <div class="feed-category-header">

                    <div>
                        <span class="feed-category-label">
                            <?= htmlspecialchars($category); ?>
                        </span>

                        <h2>
                            <?= htmlspecialchars($category); ?>
                            Feed Schedule
                        </h2>

                        <p>
                            <?= number_format($cowCounts[$category] ?? 0); ?>
                            animals currently in this category.
                        </p>
                    </div>

                </div>


                <?php foreach ($formulations as $formulation) { ?>

                    <div class="feed-formulation-card">

                        <div class="feed-formulation-header">

                            <div>
                                <h3>
                                    <?= htmlspecialchars($formulation['formulation_name']); ?>
                                </h3>

                                <p>
                                    Formulation No:
                                    <strong>
                                        <?= htmlspecialchars($formulation['formulation_no']); ?>
                                    </strong>
                                </p>
                            </div>


                            <form method="POST" action="feed_schedules.php">

                                <input
                                    type="hidden"
                                    name="feed_now"
                                    value="1">

                                <input
                                    type="hidden"
                                    name="formulation_id"
                                    value="<?= $formulation['formulation_id']; ?>">

                                <input
                                    type="hidden"
                                    name="feed_date"
                                    value="<?= htmlspecialchars($selected_date); ?>">

                                <input
                                    type="hidden"
                                    name="category"
                                    value="<?= htmlspecialchars($selected_category); ?>">


                                <div class="feed-action-box">

                                    <div>
                                        <label>Date</label>

                                        <input
                                            type="date"
                                            class="form-control"
                                            value="<?= htmlspecialchars($selected_date); ?>"
                                            readonly>
                                    </div>


                                    <div>
                                        <label>Animals</label>

                                        <input
                                            type="number"
                                            class="form-control"
                                            value="<?= (int) $formulation['cow_count']; ?>"
                                            readonly>
                                    </div>


                                    <button type="submit" class="btn btn-success">

                                        <i class="fas fa-utensils"></i>

                                        <?php if ((int) $formulation['deducted_today'] > 0) { ?>
                                            Update Feed
                                        <?php } else { ?>
                                            Feed Now
                                        <?php } ?>

                                    </button>

                                </div>

                            </form>

                        </div>


                        <div class="table-responsive">

                            <table class="custom-table">

                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Qty / Cow / Day</th>
                                        <th>Total Required / Day</th>
                                        <th>Stock Before Feeding</th>
                                        <th>Balance After Feeding</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($formulation['items'] as $item) { ?>

                                        <?php
                                        $cow_count = (int) $formulation['cow_count'];

                                        $qty_per_cow = (float) $item['qty_per_cow_per_day'];

                                        $total_required = $cow_count * $qty_per_cow;

                                        $stock_before = (float) $item['stock_before_feeding'];

                                        $balance_after = $stock_before - $total_required;

                                        $status = "Enough Stock";
                                        $badge = "badge-success";
                                        $textClass = "text-success";

                                        if ($stock_before <= 0) {

                                            $status = "No Stock";
                                            $badge = "badge-danger";
                                            $textClass = "text-danger";

                                        } elseif ($balance_after < 0) {

                                            $status = "Shortage";
                                            $badge = "badge-danger";
                                            $textClass = "text-danger";
                                        }
                                        ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?= htmlspecialchars($item['ingredient_name']); ?>
                                                </strong>
                                            </td>


                                            <td>
                                                <?= number_format($qty_per_cow, 3); ?>
                                                <?= htmlspecialchars($item['unit']); ?>
                                            </td>


                                            <td class="<?= $textClass; ?>">
                                                <strong>
                                                    <?= number_format($total_required, 3); ?>
                                                    <?= htmlspecialchars($item['unit']); ?>
                                                </strong>
                                            </td>


                                            <td>
                                                <?= number_format($stock_before, 3); ?>
                                                <?= htmlspecialchars($item['unit']); ?>
                                            </td>


                                            <td class="<?= $textClass; ?>">
                                                <strong>
                                                    <?= number_format($balance_after, 3); ?>
                                                    <?= htmlspecialchars($item['unit']); ?>
                                                </strong>
                                            </td>


                                            <td>
                                                <span class="<?= $badge; ?>">
                                                    <?= $status; ?>
                                                </span>
                                            </td>

                                        </tr>

                                    <?php } ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                <?php } ?>

            </div>

        <?php } ?>

    <?php } else { ?>

        <div class="table-card">

            <p style="text-align:center;">
                No feed schedules found. Please create feed formulations first.
            </p>

        </div>

    <?php } ?>

</div>

<?php include 'includes/footer.php'; ?>