<?php 
/*
| -------------------------------------------------------------------------
| Address Verification 
| -------------------------------------------------------------------------
| This file lets you set variable values for use when connecting to the
| WebSmart MelissaData Address Verificatin Service:
|
|	http://www.melissadata.com/tech/websmart.htm
|
*/

$config['md_rest_base_url'] = 'https://addresscheck.melissadata.net/v2/REST/Service.svc/doAddressCheck?';
$config['md_xml_base_url'] = 'https://addresscheck.melissadata.net/v2/XML/Service.svc/doAddressCheck';
$config['md_cust_id'] = '[PUT CUSTOMER ID HERE]';
$config['md_parse_address'] = TRUE;

