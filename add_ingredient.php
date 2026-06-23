<?php

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="form-card">

<h2>Add Feed Ingredient</h2>

<form action="process/save_ingredient.php"
      method="POST">

<div class="form-grid">

<input
type="text"
name="ingredient_code"
placeholder="Ingredient Code"
required>

<input
type="text"
name="ingredient_name"
placeholder="Ingredient Name"
required>

<input
type="text"
name="category"
placeholder="Category"
required>

<input
type="number"
step="0.01"
name="current_stock"
placeholder="Current Stock">

<input
type="number"
step="0.01"
name="unit_cost"
placeholder="Unit Cost">

<input
type="number"
step="0.01"
name="reorder_level"
placeholder="Reorder Level">

</div>

<button
type="submit"
class="btn-primary">

Save Ingredient

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>