<?php

include 'includes/db.php';

if(isset($_POST['save_treatment'])){

mysqli_query($conn,

"INSERT INTO treatments
(
cow_id,
disease,
treatment_given,
treatment_date,
veterinarian,
cost,
remarks
)

VALUES
(
'{$_POST['cow_id']}',
'{$_POST['disease']}',
'{$_POST['treatment_given']}',
'{$_POST['treatment_date']}',
'{$_POST['veterinarian']}',
'{$_POST['cost']}',
'{$_POST['remarks']}'
)"

);

header("Location:treatments.php");

}

$cows =
mysqli_query(
$conn,
"SELECT * FROM cows"
);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="form-card">

<h2>Treatment Records</h2>

<form method="POST">

<div class="form-grid">

<select name="cow_id">

<?php while($cow=mysqli_fetch_assoc($cows)): ?>

<option value="<?= $cow['id']; ?>">

<?= $cow['tag_number']; ?>

-

<?= $cow['cow_name']; ?>

</option>

<?php endwhile; ?>

</select>

<input
type="text"
name="disease"
placeholder="Disease">

<input
type="text"
name="treatment_given"
placeholder="Treatment Given">

<input
type="date"
name="treatment_date">

<input
type="text"
name="veterinarian"
placeholder="Veterinarian">

<input
type="number"
step="0.01"
name="cost"
placeholder="Cost">

<textarea
name="remarks">
</textarea>

</div>

<button
name="save_treatment"
class="btn-primary">

Save Treatment

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>