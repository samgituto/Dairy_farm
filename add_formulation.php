<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";


/* ============================================================
   LOAD INVENTORY ITEM NAMES FOR AUTOCOMPLETE
============================================================ */

$ingredientQuery = mysqli_query($conn, "
    SELECT DISTINCT item_name
    FROM inventory
    ORDER BY item_name ASC
");


/* ============================================================
   SAVE FORMULATION
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formulation_no = "FML-" . date("YmdHis") . rand(10, 99);

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

    $created_by = $_SESSION['full_name']
        ?? $_SESSION['username']
        ?? 'System';

    $ingredient_names = $_POST['ingredient_name'] ?? [];
    $units = $_POST['unit'] ?? [];
    $quantities = $_POST['qty_per_cow_per_day'] ?? [];


    /* ========================================================
       VALIDATION
    ======================================================== */

    if ($formulation_name === "" || $feed_category === "") {

        $message = "Formulation name and feed category are required.";

    } elseif (count($ingredient_names) === 0) {

        $message = "Please add at least one ingredient.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* =================================================
               INSERT FORMULATION
            ================================================= */

            $formulationSql = "
                INSERT INTO feed_formulations (
                    formulation_no,
                    formulation_name,
                    feed_category,
                    notes,
                    created_by
                )
                VALUES (
                    '$formulation_no',
                    '$formulation_name',
                    '$feed_category',
                    '$notes',
                    '$created_by'
                )
            ";

            if (!mysqli_query($conn, $formulationSql)) {
                throw new Exception(mysqli_error($conn));
            }

            $formulation_id = mysqli_insert_id($conn);

            $validRows = 0;


            /* =================================================
               INSERT FORMULATION INGREDIENTS
            ================================================= */

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

                $itemSql = "
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

                if (!mysqli_query($conn, $itemSql)) {
                    throw new Exception(mysqli_error($conn));
                }

                $validRows++;
            }

            if ($validRows === 0) {
                throw new Exception("Please add at least one valid ingredient row.");
            }

            mysqli_commit($conn);

            header("Location: formulations.php?success=1");
            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $message = "Error: " . $e->getMessage();
        }
    }
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-blender"></i>
                Add Feed Formulation
            </h1>

            <p>
                Create feed formulations for lactating, pregnant, bull, calf, dry, and heifer categories.
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


    <form method="POST" action="add_formulation.php">

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
                        placeholder="Example: Lactating Cow Dairy Meal Mix"
                        required>

                </div>


                <div class="form-group">

                    <label>Feed Category</label>

                    <select name="feed_category" class="form-control" required>

                        <option value="">Select Category</option>
                        <option value="Lactating">Lactating</option>
                        <option value="Pregnant">Pregnant</option>
                        <option value="Bull">Bull</option>
                        <option value="Calf">Calf</option>
                        <option value="Dry">Dry</option>
                        <option value="Heifer">Heifer</option>

                    </select>

                </div>


                <div class="form-group" style="grid-column: 1 / -1;">

                    <label>Notes</label>

                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"
                        placeholder="Optional notes about the formulation"></textarea>

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

                        <tr>

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

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="form-actions">

            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i>
                Save Formulation
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