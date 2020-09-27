<?php
require_once('oai-pmh/oai2server.php');
require_once('lodel_connect.php');
error_log('hello oai !');

lodel_init();


print var_export(get_sites(),1);

$textes = get_entity_info('textes', '', 'dev');
print var_export($textes,1);

print "hello\n";
// test
