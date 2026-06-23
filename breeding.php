<?php

include 'includes/db.php';

if(isset($_POST['save_ai'])){

$insemination_date =
$_POST['insemination_date'];

$expected_calving =
date(
'Y-m-d',
strtotime(
$insemination_date . ' +283 days'
)
);

mysqli_query($conn,

"INSERT INTO breeding_records
(
cow_id,
insemination_date,
semen_batch,
technician,
expected_calving_date,
remarks
)

VALUES
(
'{$_POST['cow_id']}',
'$insemination_date',
'{$_POST['semen_batch']}',
'{$_POST['technician']}',
'$expected_calving',
'{$_POST['remarks']}'
)"

);

header("Location:breeding.php");

}

$cows =
mysqli_query(
$conn,
"SELECT * FROM cows
WHERE gender='Female'"
);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="form-card">

<h2>Artificial Insemination</h2>

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
name="insemination_date">

<input
type="text"
name="semen_batch"
placeholder="Semen Batch">

<input
type="text"
name="technician"
placeholder="AI Technician">

<textarea
name="remarks">
</textarea>

</div>

<button
name="save_ai"
class="btn-primary">

Save AI Record

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>