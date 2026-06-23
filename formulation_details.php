<?php

include 'includes/db.php';

$id = $_GET['id'];

$formula = mysqli_fetch_assoc(

mysqli_query(

$conn,

"SELECT *

FROM feed_formulations

WHERE id='$id'"

)

);

$ingredients = mysqli_query(

$conn,

"SELECT

fi.*,
f.ingredient_name

FROM formulation_ingredients fi

INNER JOIN feed_ingredients f

ON fi.ingredient_id=f.id

WHERE formulation_id='$id'"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="table-card">

<h2>

<?= $formula['formula_name']; ?>

</h2>

<p>

Category:
<?= $formula['animal_category']; ?>

</p>

<p>

Batch Size:
<?= $formula['batch_size']; ?> kg

</p>

<table class="custom-table">

<thead>

<tr>

<th>Ingredient</th>
<th>Percentage</th>
<th>Weight</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($ingredients)): ?>

<tr>

<td><?= $row['ingredient_name']; ?></td>

<td><?= $row['percentage']; ?>%</td>

<td><?= $row['weight_kg']; ?> kg</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>