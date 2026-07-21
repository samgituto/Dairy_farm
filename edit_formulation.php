<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: formulations.php");
    exit();
}

$formulation_id = (int) $_GET['id'];

$message = "";


/* ============================================================
   LOAD FORMULATION
============================================================ */

$formulationQuery = mysqli_query($conn, "
    SELECT *
    FROM feed_formulations
    WHERE formulation_id = '$formulation_id'
");

if (mysqli_num_rows($formulationQuery) === 0) {
    header("Location: formulations.php");
    exit();
}

$formulation = mysqli_fetch_assoc($formulationQuery);


/* ============================================================
   LOAD INGREDIENTS
============================================================ */

$itemsQuery = mysqli_query($conn, "
    SELECT *
    FROM feed_formulation_items
    WHERE formulation_id = '$formulation_id'
    ORDER BY formulation_item_id ASC
");


/* ============================================================
   LOAD INVENTORY ITEM NAMES FOR AUTOCOMPLETE
============================================================ */

$ingredientQuery = mysqli_query($conn, "
    SELECT DISTINCT item_name
    FROM inventory
    ORDER BY item_name ASC
");


/* ============================================================
   UPDATE FORMULATION
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formulation_name = mysqli_real_escape_string(
        $conn,
        $_POST['formulation_name']
    );

    $feed_category = mysqli_real_escape_string(
        $conn,
        $_POST['feed_category']
    );

    $notes = mysqli_real_escape_string(
        $conn,
        $_POST['notes']
    );

    $ingredient_names = $_POST['ingredient_name'] ?? [];
    $units = $_POST['unit'] ?? [];
    $quantities = $_POST['qty_per_cow_per_day'] ?? [];

    mysqli_begin_transaction($conn);

    try {

        $updateSql = "
            UPDATE feed_formulations
            SET
                formulation_name = '$formulation_name',
                feed_category = '$feed_category',
                notes = '$notes'
            WHERE formulation_id = '$formulation_id'
        ";

        if (!mysqli_query($conn, $updateSql)) {
            throw new Exception(mysqli_error($conn));
        }


        mysqli_query($conn, "
            DELETE FROM feed_formulation_items
            WHERE formulation_id = '$formulation_id'
        ");

        $validRows = 0;

        for ($i = 0; $i < count($ingredient_names); $i++) {

            $ingredient_name = trim($ingredient_names[$i] ?? "");
            $unit = trim($units[$i] ?? "");
            $qty = (float) ($quantities[$i] ?? 0);

            if ($ingredient_name === "" || $unit === "" || $qty <= 0) {
                continue;
            }

            $ingredient_name = mysqli_real_escape_string(
                $conn,
                $ingredient_name
            );

            $unit = mysqli_real_escape_string(
                $conn,
                $unit
            );

            $insertSql = "
                INSERT INTO feed_formulation_items (
                    formulation_id,
                    ingredient_name,
                    unit,
                    qty_per_cow_per_day
                )
                VALUES (
                    '$formulation_id',
                    '$ingredient_name',
                    '$unit',
                    '$qty'
                )
            ";

            if (!mysqli_query($conn, $insertSql)) {
                throw new Exception(mysqli_error($conn));
            }

            $validRows++;
        }

        if ($validRows === 0) {
            throw new Exception("At least one valid ingredient is required.");
        }

        mysqli_commit($conn);

        header("Location: formulations.php?updated=1");
        exit();

    } catch (Exception $e) {

        mysqli_rollback($conn);

        $message = "Error: " . $e->getMessage();
    }
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-edit"></i>
                Edit Feed Formulation
            </h1>

            <p>
                Update formulation details and ingredients.
            </p>
        </div>

        <div>
            <a href="formulations.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                View Formulations
            </a>
        </div>

    </div>


    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <form method="POST">

        <div class="form-card">

            <h3>
                <i class="fas fa-clipboard-list"></i>
                Formulation Details
            </h3>

            <div class="form-grid">

                <div class="form-group">

                    <label>Formulation Name</label>

                    <input
                        type="text"
                        name="formulation_name"
                        class="form-control"
                        value="<?= htmlspecialchars($formulation['formulation_name']); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Feed Category</label>

                    <select name="feed_category" class="form-control" required>

                        <?php
                        $categories = [
                            'Lactating',
                            'Pregnant',
                            'Bull',
                            'Calf',
                            'Dry',
                            'Heifer'
                        ];

                        foreach ($categories as $category) {
                            $selected = (
                                $formulation['feed_category'] === $category
                            ) ? 'selected' : '';
                        ?>

                            <option value="<?= $category; ?>" <?= $selected; ?>>
                                <?= $category; ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>


                <div class="form-group" style="grid-column: 1 / -1;">

                    <label>Notes</label>

                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"><?= htmlspecialchars($formulation['notes']); ?></textarea>

                </div>

            </div>

        </div>


        <div class="table-card">

            <div class="table-header">

                <h3>
                    <i class="fas fa-seedling"></i>
                    Ingredients
                </h3>

                <button
                    type="button"
                    class="btn btn-success"
                    onclick="addIngredientRow()">
                    <i class="fas fa-plus"></i>
                    Add Ingredient
                </button>

            </div>


            <datalist id="ingredientSuggestions">

                <?php while ($item = mysqli_fetch_assoc($ingredientQuery)) { ?>

                    <option value="<?= htmlspecialchars($item['item_name']); ?>">

                <?php } ?>

            </datalist>


            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>Ingredient Name</th>
                            <th>Unit</th>
                            <th>Qty per Cow per Day</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="ingredientTableBody">

                        <?php while ($item = mysqli_fetch_assoc($itemsQuery)) { ?>

                            <tr>

                                <td>
                                    <input
                                        type="text"
                                        name="ingredient_name[]"
                                        class="form-control"
                                        list="ingredientSuggestions"
                                        value="<?= htmlspecialchars($item['ingredient_name']); ?>"
                                        required>
                                </td>

                                <td>
                                    <select name="unit[]" class="form-control" required>

                                        <option value="Kg" <?= ($item['unit'] === 'Kg') ? 'selected' : ''; ?>>
                                            Kg
                                        </option>

                                        <option value="L" <?= ($item['unit'] === 'L') ? 'selected' : ''; ?>>
                                            L
                                        </option>

                                        <option value="g" <?= ($item['unit'] === 'g') ? 'selected' : ''; ?>>
                                            g
                                        </option>

                                    </select>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        name="qty_per_cow_per_day[]"
                                        class="form-control"
                                        value="<?= htmlspecialchars($item['qty_per_cow_per_day']); ?>"
                                        required>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        onclick="removeIngredientRow(this)">
                                        <i class="fas fa-trash"></i>
                                        Remove
                                    </button>
                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="form-actions">

            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i>
                Update Formulation
            </button>

            <a href="formulations.php" class="btn btn-secondary">
                Cancel
            </a>

        </div>

    </form>

</div>


<script>
function addIngredientRow() {

    const tbody = document.getElementById("ingredientTableBody");

    const row = document.createElement("tr");

    row.innerHTML = `
        <td>
            <input
                type="text"
                name="ingredient_name[]"
                class="form-control"
                list="ingredientSuggestions"
                placeholder="Type ingredient name"
                required>
        </td>

        <td>
            <select name="unit[]" class="form-control" required>
                <option value="Kg">Kg</option>
                <option value="L">L</option>
                <option value="g">g</option>
            </select>
        </td>

        <td>
            <input
                type="number"
                step="0.001"
                min="0"
                name="qty_per_cow_per_day[]"
                class="form-control"
                placeholder="Example: 2.5"
                required>
        </td>

        <td>
            <button
                type="button"
                class="btn btn-danger"
                onclick="removeIngredientRow(this)">
                <i class="fas fa-trash"></i>
                Remove
            </button>
        </td>
    `;

    tbody.appendChild(row);
}


function removeIngredientRow(button) {

    const tbody = document.getElementById("ingredientTableBody");

    if (tbody.rows.length > 1) {
        button.closest("tr").remove();
    } else {
        alert("At least one ingredient row is required.");
    }
}
</script>

<?php include 'includes/footer.php'; ?>