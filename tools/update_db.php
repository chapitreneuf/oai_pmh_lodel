<?php

#
# CLI script to update oai-php database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('inc/init.php');

connect_site('oai-pmh') or die("Could not connect to oai-pmh, have you launched setup.php ?");

update_sets();
update_records();
# TODO: delete records and sets that does not exists anymore

function update_sets() {
    connect_site();
    $sites = get_sites();
    # TODO: test oai_id are unique

    connect_site('oai-pmh');
    foreach($sites as $site) {
        $bind = [
            'journal', $site['name'], $site['oai_id'], $site['title'], $site['url'], $site['droitsauteur'],
            $site['editeur'], $site['titresite'], $site['issn'], $site['issn_electronique'],
            $site['langueprincipale'], $site['doi_prefixe'], $site['openaire_access_level'], $site['upd'],
        ];

        $info = sql_getone("SELECT `id`, `oai_id`, `upd` FROM `sets` WHERE `name` = ?;", [$site['name']]);
        if ($info) {

            # Always update
            # if ($site['upd'] == $info['upd']) continue;

            $q = "UPDATE `sets` SET `set`=?, `name`=?, `oai_id`=?, `title`=?, `url`=?, `droitsauteur`=?, `editeur`=?, `titresite`=?, `issn`=?, `issn_electronique`=?, `langueprincipale`=?, `doi_prefixe`=?, `openaire_access_level`=?, `upd`=? WHERE `id`=?;";
            $bind[] = $info['id'];
            _log("Updating ${site['name']} - ${site['oai_id']}");

        } else {
            $q = "INSERT INTO `sets` (`set`, `name`, `oai_id`, `title`, `url`, `droitsauteur`, `editeur`, `titresite`, `issn`, `issn_electronique`, `langueprincipale`, `doi_prefixe`, `openaire_access_level`, `upd`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
            _log("Inserting ${site['name']} - ${site['oai_id']}");
        }

        sql_query($q, $bind);
    }
}

function update_records() {
    connect_site('oai-pmh');
    $sets = get_sets(0);
    foreach ($sets as $set) {
        _log("Set up of ${set['name']} records");
        $publication_types = get_publication_types();
        foreach ($publication_types as $class => $types) {
            foreach ($types as $type => $stuff) {
                connect_site($set['name']);
                $records = get_entities($class, $type, 0);

                connect_site('oai-pmh');
                foreach ($records as $record) {
                    $bind = [$record['identity'], $record['titre'], $record['modificationdate'], 'journals', $set['oai_id'], $set['name'], $class, $type];

                    $info = sql_getone("SELECT `id`, `date` FROM `records` WHERE `oai_id` = ? AND `identity` = ?;", [$set['oai_id'], $record['identity']]);
                    if ($info) {
                        # only update if entity has changed
                        if ($info['date'] == $record['modificationdate']) {
                            _log("not updating ${set['oai_id']} - ${record['identity']}");
                            continue;
                        }

                        $q = "UPDATE `records` SET `identity`=?, `title`=?, `date`=?, `set`=?, `oai_id`=?, `site`=?, `class`=?, `type`=? WHERE id=?;";
                        $bind[] = $info['id'];
                        _log("Updating ${set['oai_id']} - ${record['identity']}");
                    } else {
                        $q = "INSERT INTO `records` (`identity`, `title`, `date`, `set`, `oai_id`, `site`, `class`, `type`) VALUES (?,?,?,?,?,?,?,?);";
                        _log("Inserting ${set['oai_id']} - ${record['identity']}");
                    }
                    sql_query($q, $bind);
                }
            }
        }
    }
}

/* TODO : delete old records
    delete site
    ask for all sites, index in hash by id
    get all of our sites
    foreach our site if not in index
        delete our site
        delete all entries in record
    delete records
    ask for our set
    get all records from this set (0,1000) order by identity
    sql from entities identity in () status > 0
    if list is 1000 next
    index in hash by identity
    loop on our record, delete if not exists
*/