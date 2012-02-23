<?php

require_once 'config.php';
require_once 'lib/clickatell-connect-api.php';

$oClickatell = new ClickatellConnectApi($sToken);
//print_r($oClickatell->get_list_country());
//print_r($oClickatell->get_list_country_prefix());
//print_r($oClickatell->get_list_account());
print_r($oClickatell->get_list_terms('209.85.229.147'));