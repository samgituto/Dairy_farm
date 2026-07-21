<?php
session_start();

include 'includes/db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';
include 'includes/sidebar.php';

/*=====================================================
    GENERATE TRANSACTION NUMBER
=====================================================*/

$transactionNo = "INC-" . date("Ymd") . "-" . rand(1000,9999);

/*=====================================================
    LOAD INCOME CATEGORIES
=====================================================*/

$categoryQuery = mysqli_query($conn,"
SELECT *
FROM financial_categories
WHERE category_type='Income'
ORDER BY category_name ASC
");

/*=====================================================
    LOAD CUSTOMERS
=====================================================*/

$customerQuery = mysqli_query($conn,"
SELECT *
FROM customers
ORDER BY customer_name ASC
");

/*=====================================================
    LOAD PAYMENT METHODS
=====================================================*/

$paymentQuery = mysqli_query($conn,"
SELECT *
FROM payment_methods
ORDER BY method_name ASC
");

/*=====================================================
    TODAY'S DATE
=====================================================*/

$today = date("Y-m-d");

?>

<div class="main-content">

<div class="page-header">

    <div>

        <h1>

            <i class="fas fa-coins"></i>

            Add Income

        </h1>

        <p>
            Record a new income transaction.
        </p>

    </div>

    <div>

        <a href="income_records.php" class="btn btn-success">

            <i class="fas fa-list"></i>

            Income Records

        </a>

    </div>

</div>

<!--=========================================
    SUCCESS MESSAGE
==========================================-->

<?php

if(isset($_GET['success']))
{

?>

<div class="alert alert-success">

Income successfully recorded.

</div>

<?php

}

?>

<!--=========================================
    ERROR MESSAGE
==========================================-->

<?php

if(isset($_GET['error']))
{

?>

<div class="alert alert-danger">

Unable to save income record.

</div>

<?php

}

?>

<!--=========================================
    ADD INCOME FORM
==========================================-->

<div class="form-card">

<form
id="incomeForm"
action="save_income.php"
method="POST"
enctype="multipart/form-data">

<div class="form-grid">

<!-- Transaction Number -->

<div class="form-group">

<label>

Transaction Number

</label>

<input
type="text"
name="transaction_no"
class="form-control"
value="<?= $transactionNo ?>"
readonly>

</div>

<!-- Date -->

<div class="form-group">

<label>

Transaction Date

</label>

<input
type="date"
name="transaction_date"
class="form-control"
value="<?= $today ?>"
required>

</div>

<!-- Income Category -->

<div class="form-group">

<label>

Income Source

</label>

<select
name="category_id"
class="form-control"
required>

<option value="">

Select Income Source

</option>

<?php

while($category=mysqli_fetch_assoc($categoryQuery))
{

?>

<option
value="<?= $category['category_id']; ?>">

<?= $category['category_name']; ?>

</option>

<?php

}

?>

</select>

</div>

<!-- Customer -->

<div class="form-group">

<label>

Customer

</label>

<select
name="customer_id"
class="form-control">

<option value="">

Select Customer

</option>

<?php

while($customer=mysqli_fetch_assoc($customerQuery))
{

?>

<option
value="<?= $customer['customer_id']; ?>">

<?= $customer['customer_name']; ?>

</option>

<?php

}

?>

</select>

</div>

<!-- Description -->

<div class="form-group full-width">

<label>

Description

</label>

<textarea
name="description"
class="form-control"
rows="4"
placeholder="Describe this income transaction..."></textarea>

</div>
<!-- =========================================
        AMOUNT
========================================= -->

<div class="form-group">

    <label>

        Amount (KSh)

    </label>

    <input
        type="number"
        name="amount"
        id="amount"
        class="form-control"
        placeholder="0.00"
        step="0.01"
        min="0"
        required>

</div>

<!-- =========================================
        PAYMENT METHOD
========================================= -->

<div class="form-group">

    <label>

        Payment Method

    </label>

    <select
        name="payment_method"
        class="form-control"
        required>

        <option value="">
            Select Payment Method
        </option>

        <?php
        while($payment=mysqli_fetch_assoc($paymentQuery))
        {
        ?>

        <option
            value="<?= $payment['payment_method_id']; ?>">

            <?= $payment['method_name']; ?>

        </option>

        <?php } ?>

    </select>

</div>

<!-- =========================================
        REFERENCE NUMBER
========================================= -->

<div class="form-group">

    <label>

        Reference Number

    </label>

    <input
        type="text"
        name="reference_no"
        class="form-control"
        placeholder="Mpesa Code / Receipt No">

</div>

<!-- =========================================
        RECORDED BY
========================================= -->

<div class="form-group">

    <label>

        Recorded By

    </label>

    <input
        type="text"
        name="recorded_by"
        class="form-control"
        value="<?= $_SESSION['fullname'] ?? $_SESSION['username']; ?>"
        readonly>

</div>

<!-- =========================================
        ATTACHMENT
========================================= -->

<div class="form-group full-width">

    <label>

        Attachment (Optional)

    </label>

    <input
        type="file"
        id="attachment"
        name="attachment"
        class="form-control"
        accept=".jpg,.jpeg,.png,.pdf">

    <small>

        Upload receipt, invoice or payment confirmation.

    </small>

</div>

<!-- =========================================
        FILE PREVIEW
========================================= -->

<div class="form-group full-width">

    <div id="previewBox"
         style="display:none;
                margin-top:15px;">

        <strong>Selected File:</strong>

        <p id="fileName"></p>

    </div>

</div>

<!-- =========================================
        NOTES
========================================= -->

<div class="form-group full-width">

    <label>

        Additional Notes

    </label>

    <textarea
        name="notes"
        rows="4"
        class="form-control"
        placeholder="Optional notes..."></textarea>

</div>

</div>

<!-- =========================================
        FORM BUTTONS
========================================= -->

<div class="form-actions">

    <button
        type="submit"
        class="btn btn-success">

        <i class="fas fa-save"></i>

        Save Income

    </button>

    <button
        type="reset"
        class="btn btn-secondary">

        <i class="fas fa-undo"></i>

        Reset

    </button>

    <a
        href="finance.php"
        class="btn btn-danger">

        <i class="fas fa-times"></i>

        Cancel

    </a>

</div>

</form>

</div>

<!-- =========================================
        SUCCESS MODAL
========================================= -->

<div id="successModal"
     class="modal">

    <div class="modal-content">

        <i class="fas fa-check-circle success-icon"></i>

        <h2>

            Income Saved Successfully

        </h2>

        <p>

            The financial record has been added.

        </p>

        <button
            class="btn btn-success"
            onclick="closeModal()">

            Continue

        </button>

    </div>

</div>
<!-- =====================================================
        LOADING OVERLAY
===================================================== -->

<div id="loadingOverlay" class="loading-overlay">

    <div class="loader"></div>

    <h3>Saving Income Record...</h3>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("incomeForm");
    const amount = document.getElementById("amount");
    const attachment = document.getElementById("attachment");
    const previewBox = document.getElementById("previewBox");
    const fileName = document.getElementById("fileName");
    const loading = document.getElementById("loadingOverlay");

    /*=========================================
        FILE PREVIEW
    =========================================*/

    attachment.addEventListener("change", function(){

        if(this.files.length>0){

            previewBox.style.display="block";

            fileName.innerHTML=this.files[0].name;

        }else{

            previewBox.style.display="none";

        }

    });


    /*=========================================
        AMOUNT VALIDATION
    =========================================*/

    amount.addEventListener("input", function(){

        if(parseFloat(this.value)<0){

            this.value=0;

        }

    });


    /*=========================================
        FORM VALIDATION
    =========================================*/

    form.addEventListener("submit", function(e){

        e.preventDefault();

        let valid=true;

        const required=form.querySelectorAll("[required]");

        required.forEach(function(field){

            if(field.value.trim()==""){

                field.style.border="2px solid red";

                valid=false;

            }else{

                field.style.border="1px solid #ddd";

            }

        });

        if(!valid){

            alert("Please complete all required fields.");

            return;

        }

        loading.style.display="flex";

        let formData=new FormData(form);

        fetch("save_income.php",{

            method:"POST",

            body:formData

        })

        .then(response=>response.text())

        .then(data=>{

            loading.style.display="none";

            if(data.trim()=="success"){

                document.getElementById("successModal").style.display="flex";

                form.reset();

                previewBox.style.display="none";

            }

            else{

                alert(data);

            }

        })

        .catch(error=>{

            loading.style.display="none";

            alert("An unexpected error occurred.");

            console.log(error);

        });

    });

});


/*=========================================
    CLOSE MODAL
=========================================*/

function closeModal(){

    document.getElementById("successModal").style.display="none";

    window.location.href="income_records.php";

}


/*=========================================
    NUMBER FORMAT
=========================================*/

document.getElementById("amount").addEventListener("blur",function(){

    let value=parseFloat(this.value);

    if(!isNaN(value)){

        this.value=value.toFixed(2);

    }

});


/*=========================================
    AUTO HIDE ALERTS
=========================================*/

setTimeout(function(){

    const alerts=document.querySelectorAll(".alert");

    alerts.forEach(function(alert){

        alert.style.transition="0.5s";

        alert.style.opacity="0";

        setTimeout(function(){

            alert.remove();

        },500);

    });

},4000);


/*=========================================
    BUTTON LOADING EFFECT
=========================================*/

document.querySelectorAll(".btn-success").forEach(btn=>{

    btn.addEventListener("click",function(){

        this.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';

    });

});

</script>

<?php include 'includes/footer.php'; ?>