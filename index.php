<?php
require_once('inc/init.php');

$oai2 = new OAI2Server(get_conf('baseURL'), $_GET, identifyResponse(), get_conf('listSize'),
    array(
        'ListMetadataFormats' =>
        function($identifier = '') {
            return ListMetadataFormats($identifier);
        },

        'ListSets' => function($count, $maxItems=5, $resumptionToken='') {
            return listSets($count, $maxItems, $resumptionToken);
        },

        'ListRecords' =>
        # TODO better order, put maxitems before count
        function($metadataPrefix, $from='', $until='', $set='', $count=false, $list_records, $deliveredRecords=0, $maxItems=5) {
            return ListRecords($metadataPrefix, $from, $until, $set, $count, $list_records, $deliveredRecords, $maxItems);
        },

        'GetRecord' =>
        function($identifier, $metadataPrefix) {
            return GetRecord($identifier, $metadataPrefix);
        },
    )
);

$response = $oai2->response();
$response->formatOutput = true;
$response->preserveWhiteSpace = false;
header('Content-Type: text/xml');
echo $response->saveXML();
