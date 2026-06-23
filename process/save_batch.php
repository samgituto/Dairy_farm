<?php

include '../includes/db.php';

$formulation_id =
$_POST['formulation_id'];

$requested_batch_weight =
$_POST['batch_weight'];

$formula =
mysqli_fetch_assoc(

mysqli_query(

$conn,

"SELECT *

FROM feed_formulations

WHERE id='$formulation_id'"

)

);

$formula_batch_weight =
$formula['batch_size'];

$scaling_factor =
$requested_batch_weight
/
$formula_batch_weight;

$total_cost = 0;
<?php

$ingredients =
mysqli_query(

$conn,

"SELECT

fi.*,
f.unit_cost,
f.current_stock

FROM formulation_ingredients fi

INNER JOIN feed_ingredients f

ON fi.ingredient_id=f.id

WHERE formulation_id='$formulation_id'"

);

while(
$row=mysqli_fetch_assoc(
$ingredients
)
){

$used_quantity =
$row['weight_kg']
*
$scaling_factor;

$ingredient_cost =
$used_quantity
*
$row['unit_cost'];

$total_cost +=
$ingredient_cost;

mysqli_query(

$conn,

"UPDATE feed_ingredients

SET current_stock =
current_stock -
$used_quantity

WHERE id =
{$row['ingredient_id']}"

);

mysqli_query(

$conn,

"INSERT INTO inventory_transactions(

ingredient_id,
transaction_type,
quantity,
transaction_date,
remarks

)

VALUES(

{$row['ingredient_id']},
'OUT',
$used_quantity,
NOW(),
'Feed Batch Production'

)"

);

}
<?php

mysqli_query(

$conn,

"INSERT INTO feed_batches(

formulation_id,
batch_date,
batch_weight,
total_cost,
status

)

VALUES(

'$formulation_id',
CURDATE(),
'$requested_batch_weight',
'$total_cost',
'Completed'

)"

);

header(
"Location: ../feed_batches.php"
);