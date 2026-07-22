<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/twilio_config.php';

use Twilio\Rest\Client;

/* ============================================================
   SEND SMS FUNCTION (TWILIO)
============================================================ */

function sendSMS($phoneNumber, $message)
{
    try {

        $client = new Client(TWILIO_SID, TWILIO_AUTH_TOKEN);

        $result = $client->messages->create(
            $phoneNumber,
            [
                'from' => TWILIO_FROM_NUMBER,
                'body' => $message
            ]
        );

        return [
            'status' => true,
            'message' => 'SMS sent successfully.',
            'response' => $result->sid
        ];

    } catch (Exception $e) {

        return [
            'status' => false,
            'message' => $e->getMessage()
        ];
    }
}
