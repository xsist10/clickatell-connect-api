#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
$oClickatell->set_test(true);

file_put_contents('captcha.png', $oClickatell->get_captcha());

printf("Open captcha.png and enter the captcha code below: ");
$sCaptcha = trim(fgets(STDIN));

$aData = array(
    'user'          => uniqid('username'),
    'fname'         => 'test',
    'sname'         => 'mctest',
    'password'      => 'abc123',
    'email_address' => 'test@test.com',
    'mobile_number' => '117840000001',
    'country_id'    => 'USA',
    'captcha_code'  => $sCaptcha,
);
echo "Registration " . ($oClickatell->register($aData) ? "Successful" : "Failed") . "!\n";