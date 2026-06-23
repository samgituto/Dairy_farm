<?php

include 'includes/db.php';

if(isset($_POST['save_milk'])){

$cow_id = $_POST['cow_id'];
$session = $_POST['session'];
$litres = $_POST['litres'];
$record_date = $_POST['record_date'];
$remarks = $_POST['remarks'];

mysqli_query($conn,

"INSERT INTO milk_records

(
cow_id,
session,
litres,
record_date,
remarks
)

VALUES

(
'$cow_id',
'$session',
'$litres',
'$record_date',
'$remarks'
)"

);

header("Location:milk.php");

}

$cows =
mysqli_query(
$conn,
"SELECT * FROM cows
WHERE status='Lactating'"
);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="form-card">

<h2>Record Milk Production</h2>

<form method="POST">

<div class="form-grid">

<select
name="cow_id"
required>

<option>
Select Cow
</option>

<?php while($cow=mysqli_fetch_assoc($cows)): ?>

<option
value="<?= $cow['id']; ?>">

<?= $cow['tag_number']; ?>

-

<?= $cow['cow_name']; ?>

</option>

<?php endwhile; ?>

</select>

<select
name="session">

<option>Morning</option>

<option>Evening</option>

</select>

<input
type="number"
step="0.01"
name="litres"
placeholder="Litres Produced">

<input
type="date"
name="record_date"
required>

<textarea
name="remarks"
placeholder="Remarks">
</textarea>

</div>

<button
name="save_milk"
class="btn-primary">

Save Record

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>