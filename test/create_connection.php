#!/usr/bin/php
<?php

require_once 'config.php';
require_once '../lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
print_r($oClickatell->create_connection(array(
    'user'              => 'test',
    'password'          => 'abc123',
    'connection_id'     => 2,                   // get from get_list_connection
    'api_description'   => 'HTTP API Connection',
    'callback_url'      => 'http://www.example.com/sms.php',
    'callback_type_id'  => 0,                   // get from get_list_callback
)));
