<?php

include 'includes/send_sms.php';

$response = sendSMS(
    LOW_STOCK_PHONE,
    'Test SMS from Dairy Farm Management System.'
);

echo "<pre>";
print_r($response);
echo "</pre>";