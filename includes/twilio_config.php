<?php

/* ============================================================
   TWILIO SMS CONFIGURATION
============================================================ */

/*
    Do NOT hardcode your real Account SID / Auth Token in this
    file if this project is committed to git or shared anywhere.

    Instead, set these as environment variables on your server,
    e.g. in a .env file loaded by your framework, an Apache
    "SetEnv" directive, an Nginx/PHP-FPM "env[...]" entry, or
    your hosting provider's environment variable settings.

    TWILIO_SID          - Account SID   (twilio.com/console)
    TWILIO_AUTH_TOKEN   - Auth Token    (twilio.com/console)
    TWILIO_FROM_NUMBER  - Your Twilio phone number, e.g. +15551234567
    LOW_STOCK_PHONE      - Phone number that should receive the alert
*/

define('TWILIO_SID', getenv('TWILIO_SID') ?: 'YOUR_TWILIO_ACCOUNT_SID');

define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: 'YOUR_TWILIO_AUTH_TOKEN');

define('TWILIO_FROM_NUMBER', getenv('TWILIO_FROM_NUMBER') ?: '+15551234567');

// Number that receives low-stock alerts (E.164 format, e.g. +2547XXXXXXXX)
define('LOW_STOCK_PHONE', getenv('LOW_STOCK_PHONE') ?: '+254797968772');
