<?php
include 'includes/db.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <h1>Create Feed Formulation</h1>
    </div>

    <div class="formulation-card">

        <form action="save_formulation.php" method="POST">

            <div class="form-grid">

                <div class="form-group">
                    <label>Formula Code</label>
                    <input type="text" name="formula_code" required>
                </div>

                <div class="form-group">
                    <label>Formula Name</label>
                    <input type="text" name="formula_name" required>
                </div>

                <div class="form-group">
                    <label>Animal Category</label>

                    <select name="animal_category" id="animal_category" required>

                        <option value="">Select Category</option>

                        <option value="Milking Cow">
                            Milking Cow
                        </option>

                        <option value="Heifer">
                            Heifer
                        </option>

                        <option value="Bull">
                            Bull
                        </option>

                        <option value="Young Calf">
                            Young Calf
                        </option>

                    </select>

                </div>

                <div class="form-group">
                    <label>Batch Size (Kg)</label>
                    <input type="number"
                           name="batch_size"
                           value="1000"
                           required>
                </div>

            </div>

            <div class="table-card">

                <h2>Ingredients</h2>

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Percentage (%)</th>
                            <th>Weight (Kg)</th>
                        </tr>
                    </thead>

                    <tbody id="ingredientTable">

                    </tbody>

                </table>

            </div>

            <button class="btn-primary">
                Save Formulation
            </button>

        </form>

    </div>

</div>

<script>

const formulations = {

"Milking Cow":[

["Corn Silage",59.5,25],
["Alfalfa Hay",11.9,5],
["Crushed Maize",14.3,6],
["Soybean Meal",9.5,4],
["Wheat Bran",3.6,1.5],
["Limestone Powder",0.5,0.2],
["Salt",0.7,0.3]

],

"Heifer":[

["Napier Grass",54.1,10],
["Maize Straw",27.0,5],
["Crushed Maize",8.1,1.5],
["Cottonseed Meal",5.4,1],
["Rice Bran",4.3,0.8],
["Mineral Premix",1.1,0.2]

],

"Bull":[

["Alfalfa Hay",32.7,8],
["Corn Silage",40.8,10],
["Crushed Maize",16.3,4],
["Soybean Meal",6.1,1.5],
["Wheat Bran",3.3,0.8],
["Minerals",0.8,0.2]

],

"Young Calf":[

["Fine Alfalfa Hay",14.9,0.7],
["Ground Maize",46.8,2.2],
["Soybean Meal",25.5,1.2],
["Wheat Bran",10.6,0.5],
["DCP",1.1,0.05],
["Salt Premix",1.1,0.05]

]

};

document.getElementById("animal_category")
.addEventListener("change", function(){

let category = this.value;

let tbody = document.getElementById("ingredientTable");

tbody.innerHTML="";

if(formulations[category]){

formulations[category].forEach((item,index)=>{

tbody.innerHTML += `

<tr>

<td>
${item[0]}
<input type="hidden"
name="ingredient_name[]"
value="${item[0]}">
</td>

<td>
<input type="number"
step="0.01"
name="percentage[]"
value="${item[1]}"
readonly>
</td>

<td>
<input type="number"
step="0.01"
name="weight[]"
value="${item[2]}"
readonly>
</td>

</tr>

`;

});

}

});

</script>

<?php include 'includes/footer.php'; ?>