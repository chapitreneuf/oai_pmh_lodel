<?php
global $config;

# Default configuration
$base_config = array(
    'listSize' => 5,
    'setsName' => 'journals',
    'setDescription' => 'Our beautiful journals collection',
    'deletedRecord' => 'no',
    'repositoryName' => 'Not configured name',
    'baseURL' => 'Not configured URL',
    'protocolVersion' => '2.0',
    'adminEmail' => 'not@configur.ed',
    'earliestDatestamp' => '0000-12-24T00:00:00Z',
    'granularity' => 'YYYY-MM-DDThh:mm:ssZ',
    'log' => false,
//     'metadatas' => '',
);

# Merge install configuration with default one
$config = array_merge($base_config, $config);

# Get a config
function get_conf($name) {
    global $config;
    return $config[$name];
}

# Config for the oai server
function identifyResponse() {
    global $config;
    return array(
        'repositoryName' => $config['repositoryName'],
        'baseURL' => $config['baseURL'],
        'protocolVersion' => $config['protocolVersion'],
        'adminEmail' => $config['adminEmail'],
        'earliestDatestamp' => $config['earliestDatestamp'],
        'deletedRecord' => $config['deletedRecord'],
        'granularity' => $config['granularity'],
    );
}
