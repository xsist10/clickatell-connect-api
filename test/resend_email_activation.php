#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
print_r($oClickatell->resend_email_activation(array(
    'user'          => 'test',
    'password'      => 'abc123',
    'email_address' => 'test@test.com',
)));