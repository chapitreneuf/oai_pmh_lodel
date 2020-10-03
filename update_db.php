<?php

#
# CLI script to update oai-php database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('lodel_connect.php');
require_once('utils.php');
lodel_init();
connect_site('oai-pmh') or die("Could not connect to oai-pmh, have you launched setup.php ?");

global $db;
update_sets();
update_records();

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

function update_records() {
    global $db;
    $sets = get_sets(0, 0);
    foreach ($sets as $set) {
        _log_debug($set);
        $all_types = array (
            ['publications', 'numero', 'issue', 'other'],
            ['publications', 'souspartie', 'part', 'other'],
            ['textes', 'article', 'article', 'article'],
            ['textes', 'chronique', 'article', 'other'],
            ['textes', 'compterendu', 'review', 'review'],
            ['textes', 'notedelecture', 'review', 'review'],
            ['textes', 'editorial', 'introduction', 'article'],
        );
        foreach ($all_types as $types) {
            list($class, $type, $type_dc, $type_oa) = $types;

            connect_site($set['name']);
            $records = get_records_simple($class, $type);

            connect_site('oai-pmh');
            _log_debug($records);
            foreach ($records as $record) {
                $q = "INSERT INTO `records` (`identity`, `title`, `date`, `set`, `class`, `type`) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id;";
                $ok = $db->execute($q, [$record['identity'], $record['titre'], $record['modificationdate'], $set['oai_id'], $class, $type]);
                _log($q);
                _log_debug($ok);
                _log_debug([$record['identity'], $record['titre'], $record['modificationdate'], $set['oai_id'], $class, $type]);
            }
        }
    }
}