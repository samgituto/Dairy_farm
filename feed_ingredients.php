<?php

session_start();

include 'includes/db.php';

$ingredients = mysqli_query(

$conn,

"SELECT *

FROM feed_ingredients

ORDER BY ingredient_name ASC"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

    <div class="page-header">

        <h2>Feed Ingredients</h2>

        <a href="add_ingredient.php"
           class="btn-primary">

            Add Ingredient

        </a>

    </div>

    <div class="table-card">

        <table class="custom-table">

            <thead>

                <tr>

                    <th>Code</th>
                    <th>Ingredient</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Unit Cost</th>
                    <th>Reorder Level</th>
                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php while($row=mysqli_fetch_assoc($ingredients)): ?>

                <tr>

                    <td><?= $row['ingredient_code']; ?></td>

                    <td><?= $row['ingredient_name']; ?></td>

                    <td><?= $row['category']; ?></td>

                    <td><?= $row['current_stock']; ?> kg</td>

                    <td>KES <?= number_format($row['unit_cost']); ?></td>

                    <td><?= $row['reorder_level']; ?> kg</td>

                    <td>

                        <a href="edit_ingredient.php?id=<?= $row['id']; ?>">
                            Edit
                        </a>

                    </td>

                </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include 'includes/footer.php'; ?>