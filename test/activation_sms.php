#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
print_r($oClickatell->send_activation_sms(array(
    'user'          => 'test',
    'password'      => 'abc123',
)));

printf("Enter the code you received via SMS: ");
$sSmsCode = trim(fgets(STDIN));

print_r($oClickatell->validate_activation_sms(array(
    'user'                  => 'test',
    'password'              => 'abc123',
    'sms_activation_code'   => $sSmsCode,
)));
