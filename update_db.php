<?php

#
# CLI script to update oai-php database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('lodel_connect.php');
lodel_init();
connect_site('oai-pmh') or die("Could not connect to oai-pmh, have you launched setup.php ?");

global $db;
update_sets();

function update_sets() {
    global $db;
    $sites = get_sites();
    foreach($sites as $site) {
        _log("set up ${site['name']}");
        $q = "INSERT INTO `sets` (`set`, `name`, `oai_id`, `title`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id=id;";
        $ok = $db->execute($q, ['journal', $site['name'], $site['oai_id'], $site['title']]);
        _log($q);
        _log_debug($ok);
    }
}
