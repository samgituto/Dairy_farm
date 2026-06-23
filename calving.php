<?php

include 'includes/db.php';

if(isset($_POST['save_calving'])){

mysqli_query($conn,

"INSERT INTO calving_records
(
cow_id,
calving_date,
calf_gender,
calf_weight,
remarks
)

VALUES
(
'{$_POST['cow_id']}',
'{$_POST['calving_date']}',
'{$_POST['calf_gender']}',
'{$_POST['calf_weight']}',
'{$_POST['remarks']}'
)"

);

header("Location:calving.php");

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

<h2>Calving Records</h2>

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
type="date"
name="calving_date">

<select name="calf_gender">

<option>Male</option>

<option>Female</option>

</select>

<input
type="number"
step="0.01"
name="calf_weight"
placeholder="Calf Weight">

<textarea
name="remarks">
</textarea>

</div>

<button
name="save_calving"
class="btn-primary">

Save Calving Record

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>