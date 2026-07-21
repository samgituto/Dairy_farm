<?php
session_start();

include 'includes/db.php';

/*====================================================
    AUTHENTICATION
====================================================*/

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized Access");
}

/*====================================================
    ALLOW ONLY POST REQUEST
====================================================*/

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die("Invalid Request");
}

/*====================================================
    START DATABASE TRANSACTION
====================================================*/

mysqli_begin_transaction($conn);

try {

    /*====================================================
        SANITIZE INPUTS
    ====================================================*/

    $transaction_no = mysqli_real_escape_string(
        $conn,
        trim($_POST['transaction_no'])
    );

    $transaction_date = mysqli_real_escape_string(
        $conn,
        trim($_POST['transaction_date'])
    );

    $category_id = (int)$_POST['category_id'];

    $customer_id = !empty($_POST['customer_id'])
        ? (int)$_POST['customer_id']
        : NULL;

    $description = mysqli_real_escape_string(
        $conn,
        trim($_POST['description'])
    );

    $amount = floatval($_POST['amount']);

    $payment_method = (int)$_POST['payment_method'];

    $reference_no = mysqli_real_escape_string(
        $conn,
        trim($_POST['reference_no'])
    );

    $recorded_by = mysqli_real_escape_string(
        $conn,
        trim($_POST['recorded_by'])
    );

    $notes = mysqli_real_escape_string(
        $conn,
        trim($_POST['notes'])
    );

    $created_by = $_SESSION['user_id'];

    /*====================================================
        VALIDATION
    ====================================================*/

    if (
        empty($transaction_no) ||
        empty($transaction_date) ||
        empty($category_id) ||
        empty($amount) ||
        empty($payment_method)
    ) {

        throw new Exception("Please complete all required fields.");

    }

    if ($amount <= 0) {

        throw new Exception("Income amount must be greater than zero.");

    }

    /*====================================================
        CHECK DUPLICATE TRANSACTION NUMBER
    ====================================================*/

    $duplicate = mysqli_query(
        $conn,
        "SELECT income_id
         FROM income
         WHERE transaction_no='$transaction_no'"
    );

    if (mysqli_num_rows($duplicate) > 0) {

        throw new Exception("Transaction number already exists.");

    }

    /*====================================================
        FILE UPLOAD
    ====================================================*/

    $attachment = "";

    if (
        isset($_FILES['attachment']) &&
        $_FILES['attachment']['error'] == 0
    ) {

        $allowed = [
            "jpg",
            "jpeg",
            "png",
            "pdf"
        ];

        $extension = strtolower(
            pathinfo(
                $_FILES['attachment']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (!in_array($extension, $allowed)) {

            throw new Exception(
                "Only JPG, PNG and PDF files are allowed."
            );

        }

        if ($_FILES['attachment']['size'] > (5 * 1024 * 1024)) {

            throw new Exception(
                "Attachment must not exceed 5MB."
            );

        }

        if (!is_dir("uploads/income")) {

            mkdir(
                "uploads/income",
                0777,
                true
            );

        }

        $attachment =
            "income_" .
            time() .
            "_" .
            rand(1000,9999) .
            "." .
            $extension;

        $destination =
            "uploads/income/" .
            $attachment;

        if (
            !move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $destination
            )
        ) {

            throw new Exception(
                "Failed to upload attachment."
            );

        }

    }

        /*====================================================
        INSERT INTO INCOME TABLE
    ====================================================*/

    $stmt = mysqli_prepare($conn,"
        INSERT INTO income
        (
            transaction_no,
            transaction_date,
            category_id,
            customer_id,
            description,
            amount,
            payment_method,
            reference_no,
            attachment,
            notes,
            recorded_by,
            created_by,
            created_at
        )

        VALUES

        (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    mysqli_stmt_bind_param(

        $stmt,

        "ssiisdissssi",

        $transaction_no,
        $transaction_date,
        $category_id,
        $customer_id,
        $description,
        $amount,
        $payment_method,
        $reference_no,
        $attachment,
        $notes,
        $recorded_by,
        $created_by

    );

    if(!mysqli_stmt_execute($stmt))
    {

        throw new Exception(mysqli_error($conn));

    }

    $income_id = mysqli_insert_id($conn);


    /*====================================================
        CREATE FINANCIAL TRANSACTION
    ====================================================*/

    $transaction_type="Income";

    $stmt=mysqli_prepare($conn,"

        INSERT INTO financial_transactions
        (

            transaction_type,

            reference_id,

            reference_number,

            transaction_date,

            category_id,

            amount,

            payment_method,

            description,

            created_by,

            created_at

        )

        VALUES

        (?,?,?,?,?,?,?,?,?,NOW())

    ");

    mysqli_stmt_bind_param(

        $stmt,

        "sissidisi",

        $transaction_type,

        $income_id,

        $transaction_no,

        $transaction_date,

        $category_id,

        $amount,

        $payment_method,

        $description,

        $created_by

    );

    if(!mysqli_stmt_execute($stmt))
    {

        throw new Exception(mysqli_error($conn));

    }


    /*====================================================
        UPDATE CASH FLOW TABLE
    ====================================================*/

    $cash_type="Income";

    $stmt=mysqli_prepare($conn,"

        INSERT INTO cash_flow
        (

            flow_date,

            flow_type,

            transaction_id,

            amount,

            description,

            created_by,

            created_at

        )

        VALUES

        (?,?,?,?,?,?,NOW())

    ");

    mysqli_stmt_bind_param(

        $stmt,

        "ssidsi",

        $transaction_date,

        $cash_type,

        $income_id,

        $amount,

        $description,

        $created_by

    );

    if(!mysqli_stmt_execute($stmt))
    {

        throw new Exception(mysqli_error($conn));

    }


    /*====================================================
        UPDATE CUSTOMER TOTAL PURCHASES
        (IF CUSTOMER EXISTS)
    ====================================================*/

    if(!empty($customer_id))
    {

        mysqli_query($conn,"

            UPDATE customers

            SET

            total_purchases=

            IFNULL(total_purchases,0)+$amount

            WHERE customer_id=$customer_id

        ");

    }


    /*====================================================
        AUTOMATIC MILK SALES LINK
    ====================================================*/

    if($category_id==1)
    {

        mysqli_query($conn,"

            UPDATE milk_sales

            SET

            finance_status='Recorded'

            WHERE reference_no='$reference_no'

        ");

    }


    /*====================================================
        UPDATE FINANCIAL REPORT TOTALS
        (OPTIONAL SUMMARY TABLE)
    ====================================================*/

    mysqli_query($conn,"

        INSERT INTO financial_reports

        (

            report_date,

            total_income,

            total_expenses,

            net_profit

        )

        VALUES

        (

            CURDATE(),

            $amount,

            0,

            $amount

        )

        ON DUPLICATE KEY UPDATE

        total_income=total_income+$amount,

        net_profit=total_income-total_expenses

    ");
    /*====================================================
        AUDIT LOG
    ====================================================*/

    $action = "Created Income Record";

    $details =
        "Transaction No: " . $transaction_no .
        " | Amount: " . number_format($amount,2) .
        " | Category ID: " . $category_id;

    $audit = mysqli_prepare($conn,"
        INSERT INTO audit_logs
        (
            user_id,
            action,
            module,
            reference_id,
            details,
            ip_address,
            created_at
        )
        VALUES
        (?,?,?,?,?,?,NOW())
    ");

    $module = "Financial";

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    mysqli_stmt_bind_param(

        $audit,

        "ississ",

        $created_by,
        $action,
        $module,
        $income_id,
        $details,
        $ipAddress

    );

    mysqli_stmt_execute($audit);
    /*====================================================
        COMMIT DATABASE TRANSACTION
    ====================================================*/

    mysqli_commit($conn);

    if(
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    )
    {

        echo "success";
        exit();

    }



    /*====================================================
        NORMAL FORM SUBMISSION
    ====================================================*/

    header("Location: add_income.php?success=1");
    exit();

}

/*====================================================
    ERROR HANDLING
====================================================*/

catch(Exception $e)
{

    mysqli_rollback($conn);

    error_log(
        "[Income Module] ".
        $e->getMessage()
    );

    if(
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    )
    {

        echo $e->getMessage();
        exit();

    }

    header(
        "Location:add_income.php?error=" .
        urlencode($e->getMessage())
    );

    exit();

}

mysqli_close($conn);

?>