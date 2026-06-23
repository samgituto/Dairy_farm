<?php

include 'includes/db.php';

$formulations = mysqli_query(

$conn,

"SELECT *

FROM feed_formulations"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="form-card">

<h2>Feed Mixing</h2>

<form
action="process/save_batch.php"
method="POST">

<label>Feed Formula</label>

<select
name="formulation_id"
required>

<?php

$formulas =
mysqli_query(

$conn,

"SELECT *

FROM feed_formulations"

);

while(
$f=mysqli_fetch_assoc(
$formulas
)
):

?>

<option
value="<?= $f['id']; ?>">

<?= $f['formula_name']; ?>

</option>

<?php endwhile; ?>

</select>

<label>

Batch Weight (kg)

</label>

<input
type="number"
name="batch_weight"
required>

<button
class="btn-primary">

Produce Feed

</button>

</form>
</div>

</div>

<?php include 'includes/footer.php'; ?>