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

update_sets();
update_records();
# TODO: delete records and sets that does not exists anymore

function update_sets() {
    $sites = get_sites();
    foreach($sites as $site) {
        _log("Set up ${site['name']}");
        # TODO: faire un vrai update
        $q = "INSERT INTO `sets` (`set`, `name`, `oai_id`, `title`, `url`, `droitsauteur`, `editeur`, `titresite`, `issn`, `issn_electronique`, `langueprincipale`, `doi_prefixe`, `openaire_access_level`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id=id;";
        sql_query($q,
            ['journal', $site['name'], $site['oai_id'], $site['title'], $site['url'], $site['droitsauteur'],
            $site['editeur'], $site['titresite'], $site['issn'], $site['issn_electronique'],
            $site['langueprincipale'], $site['doi_prefixe'], $site['openaire_access_level']]);
    }
}

function update_records() {
    connect_site('oai-pmh');
    $sets = get_sets(0);
    foreach ($sets as $set) {
        _log("Set up des records de ${set['name']}");
        $publication_types = get_publication_types();
        foreach ($publication_types as $class => $types) {
            foreach ($types as $type => $stuff) {
                connect_site($set['name']);
                $records = get_records_simple($class, $type, 0);

                connect_site('oai-pmh');
                foreach ($records as $record) {
                    # TODO: faire un vrai update
                    $q = "INSERT INTO `records` (`identity`, `title`, `date`, `set`, `oai_id`, `site`, `class`, `type`) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id;";
                    sql_query($q, [$record['identity'], $record['titre'], $record['modificationdate'], 'journals', $set['oai_id'], $set['name'], $class, $type]);
                    _log("insert de $class, $type : ${record['identity']}, ${record['titre']}, ${record['modificationdate']}, ${set['oai_id']}, ${set['name']}");
                }
            }
        }
    }
}