<?php

include_once __DIR__ . '/send_sms.php';


/* ============================================================
   LOW STOCK SMS CHECKER
============================================================ */

function checkLowStockAndSendSMS($conn)
{
    $today = date("Y-m-d");

    $lowStockQuery = mysqli_query($conn, "
        SELECT
            item_name,
            category,
            unit,
            SUM(quantity) AS current_stock,
            MAX(reorder_level) AS reorder_level
        FROM inventory
        GROUP BY item_name, category, unit
        HAVING current_stock <= reorder_level
        ORDER BY current_stock ASC
    ");

    if (!$lowStockQuery) {
        return [
            'status' => false,
            'message' => mysqli_error($conn)
        ];
    }

    $sentCount = 0;

    while ($row = mysqli_fetch_assoc($lowStockQuery)) {

        $item_name = mysqli_real_escape_string($conn, $row['item_name']);

        $category = mysqli_real_escape_string($conn, $row['category']);

        $unit = mysqli_real_escape_string($conn, $row['unit']);

        $current_stock = (float) $row['current_stock'];

        $reorder_level = (float) $row['reorder_level'];

        $phone = LOW_STOCK_PHONE;


        /* ====================================================
           PREVENT DUPLICATE SMS FOR SAME ITEM ON SAME DAY
        ==================================================== */

        $existingAlertQuery = mysqli_query($conn, "
            SELECT alert_id
            FROM sms_alerts
            WHERE item_name = '$item_name'
            AND category = '$category'
            AND alert_type = 'Low Stock'
            AND alert_date = '$today'
            LIMIT 1
        ");

        if (mysqli_num_rows($existingAlertQuery) > 0) {
            continue;
        }


        /* ====================================================
           SMS MESSAGE
        ==================================================== */

        $message = "LOW STOCK ALERT: " .
            $row['item_name'] .
            " is below reorder level. Current stock: " .
            number_format($current_stock, 2) .
            " " .
            $row['unit'] .
            ". Reorder level: " .
            number_format($reorder_level, 2) .
            " " .
            $row['unit'] .
            ".";


        /* ====================================================
           SEND SMS
        ==================================================== */

        $smsResponse = sendSMS($phone, $message);


        /* ====================================================
           SAVE ALERT LOG
        ==================================================== */

        if ($smsResponse['status'] === true) {

            $safeMessage = mysqli_real_escape_string($conn, $message);

            mysqli_query($conn, "
                INSERT INTO sms_alerts (
                    item_name,
                    category,
                    current_stock,
                    reorder_level,
                    unit,
                    alert_type,
                    phone_number,
                    message,
                    alert_date
                )
                VALUES (
                    '$item_name',
                    '$category',
                    '$current_stock',
                    '$reorder_level',
                    '$unit',
                    'Low Stock',
                    '$phone',
                    '$safeMessage',
                    '$today'
                )
            ");

            $sentCount++;
        }
    }

    return [
        'status' => true,
        'message' => $sentCount . ' low stock SMS alert(s) sent.'
    ];
}