#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
$bResult = $oClickatell->sms_activation_status(array(
    'user'          => 'test',
    'password'      => 'abc123',
));

echo "This account is " . ($bResult ? "" : "not ") . "SMS activated\n";
