<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/sms_config.php';

use AfricasTalking\SDK\AfricasTalking;


/* ============================================================
   SEND SMS FUNCTION
============================================================ */

function sendSMS($phoneNumber, $message)
{
    try {

        $AT = new AfricasTalking(
            AT_USERNAME,
            AT_API_KEY
        );

        $sms = $AT->sms();

        $result = $sms->send([
            'to' => [$phoneNumber],
            'message' => $message
        ]);

        return [
            'status' => true,
            'message' => 'SMS sent successfully.',
            'response' => $result
        ];

    } catch (Exception $e) {

        return [
            'status' => false,
            'message' => $e->getMessage()
        ];
    }
}