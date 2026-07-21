<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';
include 'includes/sidebar.php';

$search          = $_GET['search'] ?? '';
$category        = $_GET['category'] ?? '';
$customer        = $_GET['customer'] ?? '';
$payment_method  = $_GET['payment_method'] ?? '';
$from            = $_GET['from'] ?? '';
$to              = $_GET['to'] ?? '';

$totalIncome = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT IFNULL(SUM(amount),0) total
FROM income
"))['total'];

$todayIncome = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT IFNULL(SUM(amount),0) total
FROM income
WHERE transaction_date=CURDATE()
"))['total'];

$monthIncome = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT IFNULL(SUM(amount),0) total
FROM income
WHERE MONTH(transaction_date)=MONTH(CURDATE())
AND YEAR(transaction_date)=YEAR(CURDATE())
"))['total'];

$totalTransactions = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM income
"))['total'];

$categories=mysqli_query($conn,"
SELECT *
FROM financial_categories
WHERE category_type='Income'
ORDER BY category_name
");

$customers=mysqli_query($conn,"
SELECT *
FROM customers
ORDER BY customer_name
");

$payments=mysqli_query($conn,"
SELECT *
FROM payment_methods
ORDER BY method_name
");

/*========================================================
    BUILD QUERY
=========================================================*/

$where=" WHERE 1=1 ";

if($search!=""){
$where.=" AND (
transaction_no LIKE '%$search%'
OR description LIKE '%$search%'
)";
}

if($category!=""){
$where.=" AND category_id='$category'";
}

if($customer!=""){
$where.=" AND customer_id='$customer'";
}

if($payment_method!=""){
$where.=" AND payment_method='$payment_method'";
}

if($from!=""){
$where.=" AND transaction_date>='$from'";
}

if($to!=""){
$where.=" AND transaction_date<='$to'";
}

?>

<div class="main-content">

<div class="page-header">

<div>

<h1>

<i class="fas fa-money-bill-wave"></i>

Income Records

</h1>

<p>

Manage all farm income transactions.

</p>

</div>

<div>

<a href="add_income.php" class="btn btn-success">

<i class="fas fa-plus-circle"></i>

Add Income

</a>

</div>

</div>

<!--====================================================
    KPI CARDS
=====================================================-->

<div class="cards">

<div class="card">

<div class="card-icon">

<i class="fas fa-wallet"></i>

</div>

<div>

<h4>Total Income</h4>

<h2>KSh <?=number_format($totalIncome,2)?></h2>

</div>

</div>

<div class="card">

<div class="card-icon">

<i class="fas fa-calendar-day"></i>

</div>

<div>

<h4>Today's Income</h4>

<h2>KSh <?=number_format($todayIncome,2)?></h2>

</div>

</div>

<div class="card">

<div class="card-icon">

<i class="fas fa-calendar-alt"></i>

</div>

<div>

<h4>This Month</h4>

<h2>KSh <?=number_format($monthIncome,2)?></h2>

</div>

</div>

<div class="card">

<div class="card-icon">

<i class="fas fa-file-invoice-dollar"></i>

</div>

<div>

<h4>Transactions</h4>

<h2><?=$totalTransactions?></h2>

</div>

</div>

</div>

<div class="form-card">

<form method="GET">

<div class="form-grid">

<div class="form-group">

<label>Search</label>

<input
type="text"
name="search"
class="form-control"
placeholder="Transaction No / Description"
value="<?=htmlspecialchars($search)?>">

</div>

<div class="form-group">

<label>Income Source</label>

<select
name="category"
class="form-control">

<option value="">All Sources</option>

<?php while($cat=mysqli_fetch_assoc($categories)){ ?>

<option
value="<?=$cat['category_id']?>"
<?=$category==$cat['category_id']?'selected':''?>>

<?=$cat['category_name']?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Customer</label>

<select
name="customer"
class="form-control">

<option value="">All Customers</option>

<?php while($cust=mysqli_fetch_assoc($customers)){ ?>

<option
value="<?=$cust['customer_id']?>"
<?=$customer==$cust['customer_id']?'selected':''?>>

<?=$cust['customer_name']?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Payment Method</label>

<select
name="payment_method"
class="form-control">

<option value="">All Methods</option>

<?php while($pay=mysqli_fetch_assoc($payments)){ ?>

<option
value="<?=$pay['payment_method_id']?>"
<?=$payment_method==$pay['payment_method_id']?'selected':''?>>

<?=$pay['method_name']?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>From</label>

<input
type="date"
name="from"
class="form-control"
value="<?=$from?>">

</div>

<div class="form-group">

<label>To</label>

<input
type="date"
name="to"
class="form-control"
value="<?=$to?>">

</div>

</div>

<div class="form-actions">

<button
type="submit"
class="btn btn-success">

<i class="fas fa-search"></i>

Search

</button>

<a
href="income_records.php"
class="btn btn-secondary">

<i class="fas fa-sync"></i>

Reset

</a>

<a
href="add_income.php"
class="btn btn-primary">

<i class="fas fa-plus"></i>

New Income

</a>

</div>

</form>

</div>

<?php

/*========================================================
    QUERY READY FOR TABLE
=========================================================*/

$sql = "

SELECT

i.*,

fc.category_name,

c.customer_name,

pm.method_name

FROM income i

LEFT JOIN financial_categories fc
ON i.category_id=fc.category_id

LEFT JOIN customers c
ON i.customer_id=c.customer_id

LEFT JOIN payment_methods pm
ON i.payment_method=pm.payment_method_id

$where

ORDER BY i.transaction_date DESC,
i.income_id DESC

";

$result = mysqli_query($conn,$sql);

?>

<div class="table-card">

    <div class="table-header">

        <h3>
            <i class="fas fa-list"></i>
            Income Transactions
        </h3>

        <div class="table-actions">

            <a href="export_income_excel.php" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Excel
            </a>

            <a href="export_income_pdf.php" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i>
                PDF
            </a>

            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
                Print
            </button>

        </div>

    </div>

    <div class="table-responsive">

        <table class="custom-table">

            <thead>

                <tr>

                    <th>#</th>

                    <th>Transaction No</th>

                    <th>Date</th>

                    <th>Income Source</th>

                    <th>Customer</th>

                    <th>Description</th>

                    <th>Payment</th>

                    <th>Amount</th>

                    <th>Recorded By</th>

                    <th>Attachment</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

<?php

if(mysqli_num_rows($result)>0)
{

    $no=1;

    while($row=mysqli_fetch_assoc($result))
    {

?>

<tr>

<td><?= $no++ ?></td>

<td>

<strong>

<?= htmlspecialchars($row['transaction_no']) ?>

</strong>

</td>

<td>

<?= date("d M Y",strtotime($row['transaction_date'])) ?>

</td>

<td>

<span class="badge badge-success">

<?= htmlspecialchars($row['category_name']) ?>

</span>

</td>

<td>

<?= !empty($row['customer_name'])
? htmlspecialchars($row['customer_name'])
: "Walk-in Customer"; ?>

</td>

<td>

<?= htmlspecialchars($row['description']) ?>

</td>

<td>

<?= htmlspecialchars($row['method_name']) ?>

</td>

<td>

<strong>

KSh <?= number_format($row['amount'],2) ?>

</strong>

</td>

<td>

<?= htmlspecialchars($row['recorded_by']) ?>

</td>

<td>

<?php

if(!empty($row['attachment']))
{

?>

<a

href="uploads/income/<?= $row['attachment'] ?>"

target="_blank"

class="btn btn-secondary btn-sm">

<i class="fas fa-paperclip"></i>

View

</a>

<?php

}
else
{

echo "-";

}

?>

</td>

<td>

<div class="action-buttons">

<a

href="income_details.php?id=<?= $row['income_id'] ?>"

class="btn btn-info btn-sm"

title="View">

<i class="fas fa-eye"></i>

</a>

<a

href="edit_income.php?id=<?= $row['income_id'] ?>"

class="btn btn-warning btn-sm"

title="Edit">

<i class="fas fa-edit"></i>

</a>

<a

href="delete_income.php?id=<?= $row['income_id'] ?>"

class="btn btn-danger btn-sm delete-btn"

onclick="return confirm('Delete this income record?')"

title="Delete">

<i class="fas fa-trash"></i>

</a>

</div>

</td>

</tr>

<?php

    }

}
else
{

?>

<tr>

<td colspan="11" style="text-align:center;padding:40px;">

<i class="fas fa-folder-open fa-3x"></i>

<br><br>

No income records found.

</td>

</tr>

<?php

}

?>

            </tbody>

        </table>

    </div>

</div>

<div class="pagination-container">

    <div class="pagination-info">

        Showing

        <strong>

<?= mysqli_num_rows($result) ?>

        </strong>

record(s)

    </div>

    <div class="pagination">

        <!--
            Future pagination links go here.

            Example:

            Previous 1 2 3 Next

        -->

    </div>

</div>

<div id="deleteModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <i class="fas fa-trash-alt fa-3x text-danger"></i>

            <h2>Delete Income Record</h2>

        </div>

        <div class="modal-body">

            <p>

                Are you sure you want to permanently delete this income record?

            </p>

            <p class="text-danger">

                This action cannot be undone.

            </p>

        </div>

        <div class="modal-footer">

            <button
                id="cancelDelete"
                class="btn btn-secondary">

                Cancel

            </button>

            <a
                href="#"
                id="confirmDelete"
                class="btn btn-danger">

                Delete

            </a>

        </div>

    </div>

</div>

<!-- ===========================================================
        LOADING OVERLAY
=========================================================== -->

<div id="loadingOverlay" class="loading-overlay">

    <div class="loader"></div>

    <h3>Loading...</h3>

</div>

<script>

document.addEventListener("DOMContentLoaded",function(){


const searchInput=document.querySelector("input[name='search']");

if(searchInput){

searchInput.addEventListener("keyup",function(){

const keyword=this.value.toLowerCase();

document.querySelectorAll(".custom-table tbody tr")

.forEach(function(row){

const rowText=row.innerText.toLowerCase();

row.style.display=rowText.includes(keyword)?"":"none";

});

});

}


/*==========================================================
    DELETE MODAL
===========================================================*/

const modal=document.getElementById("deleteModal");

const confirmDelete=document.getElementById("confirmDelete");

const cancelDelete=document.getElementById("cancelDelete");

document.querySelectorAll(".delete-btn")

.forEach(function(button){

button.addEventListener("click",function(e){

e.preventDefault();

confirmDelete.href=this.href;

modal.style.display="flex";

});

});

cancelDelete.addEventListener("click",function(){

modal.style.display="none";

});

window.addEventListener("click",function(e){

if(e.target===modal){

modal.style.display="none";

}

});

document.querySelectorAll(".custom-table tbody tr")

.forEach(function(row){

row.addEventListener("mouseenter",function(){

this.style.background="#F1F8E9";

});

row.addEventListener("mouseleave",function(){

this.style.background="";

});

});

document.querySelectorAll(".card")

.forEach(function(card){

card.addEventListener("mouseenter",function(){

this.style.transform="translateY(-8px)";

this.style.transition=".3s";

this.style.boxShadow="0 12px 25px rgba(0,0,0,.15)";

});

card.addEventListener("mouseleave",function(){

this.style.transform="translateY(0px)";

this.style.boxShadow="";

});

});

document.querySelectorAll(".card h2")

.forEach(function(counter){

let text=counter.innerText.replace(/,/g,"");

text=text.replace("KSh","");

let target=parseFloat(text);

if(isNaN(target)) return;

let current=0;

let increment=target/80;

const timer=setInterval(function(){

current+=increment;

if(current>=target){

current=target;

clearInterval(timer);

}

if(counter.innerText.includes("KSh")){

counter.innerHTML="KSh "+current.toLocaleString(undefined,{

minimumFractionDigits:2,

maximumFractionDigits:2

});

}else{

counter.innerHTML=Math.floor(current);

}

},20);

});


/*==========================================================
    TABLE SORTING
===========================================================*/

document.querySelectorAll(".custom-table th")

.forEach(function(header,index){

header.style.cursor="pointer";

header.addEventListener("click",function(){

sortTable(index);

});

});

function sortTable(column){

const table=document.querySelector(".custom-table");

const rows=Array.from(table.rows).slice(1);

const ascending=table.dataset.sort!="asc";

rows.sort(function(a,b){

const x=a.cells[column].innerText.toLowerCase();

const y=b.cells[column].innerText.toLowerCase();

return ascending?

x.localeCompare(y):

y.localeCompare(x);

});

rows.forEach(row=>table.tBodies[0].appendChild(row));

table.dataset.sort=ascending?"asc":"desc";

}


/*==========================================================
    PRINT FUNCTION
===========================================================*/

window.printIncome=function(){

document.getElementById("loadingOverlay").style.display="flex";

setTimeout(function(){

document.getElementById("loadingOverlay").style.display="none";

window.print();

},800);

};

});

</script>

<div id="toast" class="toast-notification">

    <i class="fas fa-check-circle"></i>

    <span id="toastMessage">

        Operation completed successfully.

    </span>

</div>

<script>


function refreshIncomeRecords(){

    fetch("income_records.php?ajax=1")

    .then(response=>response.text())

    .then(data=>{

        console.log("Income records refreshed.");

    })

    .catch(error=>{

        console.log(error);

    });

}

/* Refresh every 60 seconds */

setInterval(refreshIncomeRecords,60000);


function showToast(message,type="success"){

    const toast=document.getElementById("toast");

    const text=document.getElementById("toastMessage");

    text.innerHTML=message;

    toast.className="toast-notification";

    if(type==="error"){

        toast.classList.add("toast-error");

    }

    toast.classList.add("show");

    setTimeout(function(){

        toast.classList.remove("show");

    },3500);

}

document.querySelectorAll(".btn-export")

.forEach(function(button){

button.addEventListener("click",function(){

showToast("Preparing export...");

});

});


const resetBtn=document.querySelector(".btn-secondary");

if(resetBtn){

resetBtn.addEventListener("click",function(){

showToast("Filters cleared.");

});

}

document.querySelectorAll("form")

.forEach(function(form){

form.addEventListener("submit",function(){

const loader=document.getElementById("loadingOverlay");

if(loader){

loader.style.display="flex";

}

});

});

setTimeout(function(){

document.querySelectorAll(".alert")

.forEach(function(alert){

alert.style.transition=".5s";

alert.style.opacity="0";

setTimeout(function(){

alert.remove();

},500);

});

},4000);


document.addEventListener("keydown",function(e){

if(e.ctrlKey && e.key==="n"){

e.preventDefault();

window.location="add_income.php";

}

if(e.ctrlKey && e.key==="p"){

e.preventDefault();

window.print();

}

});


window.addEventListener("load",function(){

const loader=document.getElementById("loadingOverlay");

if(loader){

loader.style.display="none";

}

showToast("Income Records Loaded Successfully");

});

</script>

<?php include 'includes/footer.php'; ?>

?>