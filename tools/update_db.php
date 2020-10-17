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

delete_sets();
delete_records();
update_sets();
update_records();

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

function delete_sets() {
    connect_site();
    $sites = get_sites();
    foreach ($sites as $site) {
        $site_names[] = $site['name'];
    }

    connect_site('oai-pmh');
    $sets = sql_get("SELECT name FROM `sets`;");
    foreach($sets as $set) {
        $set_names[] = $set['name'];
    }

    $to_delete = array_diff($set_names, $site_names);
    foreach ($to_delete as $set) {
        delete_set($set);
    }
}

function delete_set($name) {
    # Delete from sets and records table
    sql_query("DELETE FROM `sets` WHERE `name`=?;", [$name]);
    sql_query("DELETE FROM `records` WHERE `site`=?;", [$name]);
    _log("Deleted set and all records of site $name");
}

function delete_records() {
    $batch = 500;
    connect_site('oai-pmh');
    $sets = get_sets(0);
    foreach ($sets as $set) {
        $start = 0;
        while ($records = sql_get("SELECT `identity` FROM `records` WHERE `oai_id` = ? LIMIT ?,?;", [$set['oai_id'], $start, $batch])) {
            foreach ($records as $record) {
                $record_ids[] = $record['identity'];
            }

            connect_site($set['name']);
            $entities = sql_get(lq('SELECT id FROM #_TP_entities WHERE id IN ('.join(',',$record_ids).') AND status > 0'));
            foreach ($entities as $entity) {
                $entity_ids[] = $entity['id'];
            }

            $to_delete = array_diff($record_ids, $entity_ids);
            if ($to_delete) {
                delete_record($set['name'], $to_delete);
            }

            connect_site('oai-pmh');
            $start += $batch;
        }
    }
}

function delete_record($site, $ids) {
    connect_site('oai-pmh');
    $in = join(',', $ids);
    _log("Deleting records from $site : $in");
    sql_query("DELETE FROM `records` WHERE `site`=? AND identity IN ($in);", [$site]);
}
