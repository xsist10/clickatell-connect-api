#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);

file_put_contents('captcha.png', $oClickatell->get_captcha());

printf("Open captcha.png and enter the captcha code below: ");
$sCaptcha = trim(fgets(STDIN));

print_r($oClickatell->forgot_password(array(
    'user'          => 'test',
    'email_address' => 'test@test.com',
    'captcha_code'  => $sCaptcha,
)));