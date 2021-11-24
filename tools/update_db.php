<?php

#
# CLI script to update oai-php database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('inc/init.php');
connect_site(get_conf('lodelOAIsite')) or die("Could not connect to ".get_conf('lodelOAIsite').", have you launched setup.php ?");

// First delete non existing sets and records
delete_sets();
delete_records();
// Then update values of sets and records
update_sets();
update_records();

/*
Update or insert all lodel sites to `sets` table
Will always update informations about the set
Input:
    none
Output:
    none
*/
function update_sets() {
    connect_site();
    $sites = get_sites();
    // TODO: test oai_id are unique

    connect_site(get_conf('lodelOAIsite'));
    $global_set = get_conf('setsName');
    foreach($sites as $site) {
        $bind = [
            $global_set, $site['name'], $site['oai_id'], $site['title'], $site['description'], $site['subject'], $site['url'], $site['droitsauteur'],
            $site['editeur'], $site['titresite'], $site['issn'], $site['issn_electronique'],
            $site['langueprincipale'], $site['doi_prefixe'], $site['openaire_access_level'], $site['upd'],
        ];

        // Test if set exists
        $info = sql_getone("SELECT `id`, `oai_id`, `upd` FROM `sets` WHERE `name` = ?;", [$site['name']]);
        if ($info) {
            // Always update
            // if ($site['upd'] == $info['upd']) continue;
            $q = "UPDATE `sets` SET `set`=?, `name`=?, `oai_id`=?, `title`=?, `description`=?, `subject`=?, `url`=?, `droitsauteur`=?, `editeur`=?, `titresite`=?, `issn`=?, `issn_electronique`=?, `langueprincipale`=?, `doi_prefixe`=?, `openaire_access_level`=?, `upd`=? WHERE `id`=?;";
            $bind[] = $info['id'];
            _log("Updating ${site['name']} - ${site['oai_id']}");

        } else {
            $q = "INSERT INTO `sets` (`set`, `name`, `oai_id`, `title`, `description`, `subject`, `url`, `droitsauteur`, `editeur`, `titresite`, `issn`, `issn_electronique`, `langueprincipale`, `doi_prefixe`, `openaire_access_level`, `upd`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
            _log("Inserting ${site['name']} - ${site['oai_id']}");
        }

        sql_query($q, $bind);
    }
}

/*
Update or insert all records of all sets to `records` table
Will only update informations about the record if upd of entity has changed
Input:
    none
Output:
    none
*/
function update_records() {
    connect_site(get_conf('lodelOAIsite'));
    $sets = get_sets(0);
    $global_set = get_conf('setsName');
    foreach ($sets as $set) {
        _log("Set up of ${set['name']} records");
        // loop on classes and types that we expose to OAI
        $publication_types = get_publication_types();
        // for each class
        foreach ($publication_types as $class => $types) {
            // for each type of that class
            foreach ($types as $type => $stuff) {
                connect_site($set['name']);
                // get all published entities of that class-type for this set (lodel site)
                $records = get_entities($class, $type, 0); // from lodel

                connect_site(get_conf('lodelOAIsite'));
                foreach ($records as $record) {
                    // openaire information: can be openAccess, embargoedAccess, restrictedAccess
                    $openaire = !empty($set['openaire_access_level']) ? $set['openaire_access_level'] : '';

                    // prepare our fields
                    $bind = [
                        $record['identity'], $record['titre'], $record['modificationdate'],
                        $global_set, $set['oai_id'], $openaire, $set['name'], $class, $type, $record['idparent'], $record['rank'], $record['upd']
                    ];

                    // Test if record exists
                    $info = sql_getone("SELECT `id`, `upd` FROM `records` WHERE `oai_id` = ? AND `identity` = ?;", [$set['oai_id'], $record['identity']]);
                    if ($info) {
                        // only update if entity has changed
                        if ($info['upd'] == $record['upd']) {
                            _log("not updating ${set['oai_id']} - ${record['identity']}");
                            continue;
                        }

                        $q = "UPDATE `records` SET `identity`=?, `title`=?, `date`=?, `set`=?, `oai_id`=?, `openaire`=?, `site`=?, `class`=?, `type`=?, `idparent`=?, `rank`=?, `upd`=? WHERE id=?;";
                        $bind[] = $info['id'];
                        _log("Updating ${set['oai_id']} - ${record['identity']}");
                    } else {
                        $q = "INSERT INTO `records` (`identity`, `title`, `date`, `set`, `oai_id`, `openaire`, `site`, `class`, `type`, `idparent`, `rank`, `upd`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?);";
                        _log("Inserting ${set['oai_id']} - ${record['identity']}");
                    }

                    sql_query($q, $bind);
                }
            }
        }
    }
}

/*
Delete sites from `sets` table that no longer exists
Input:
    none
Output:
    none
*/
function delete_sets() {
    connect_site();
    $site_names = [];
    // get sites that have oai option on
    $sites = get_sites(); // from lodel
    foreach ($sites as $site) {
        $site_names[] = $site['name'];
    }

    connect_site(get_conf('lodelOAIsite'));
    $set_names = [];
    $sets = sql_get("SELECT name FROM `sets`;");
    foreach($sets as $set) {
        $set_names[] = $set['name'];
    }

    $to_delete = array_diff($set_names, $site_names);
    foreach ($to_delete as $set) {
        delete_set($set);
    }
}

/*
Delete one site from `sets`
Input:
    $name (string): short name of lodel site
Output:
    none
*/
function delete_set($name) {
    # Delete from sets and records table
    sql_query("DELETE FROM `sets` WHERE `name`=?;", [$name]);
    sql_query("DELETE FROM `records` WHERE `site`=?;", [$name]);
    _log("Deleted set and all records of site $name");
}

/*
Delete all records from `records` table that no longer exists
MUST have deleted sets before
Input:
    none
Output:
    none
*/
function delete_records() {
    $batch = 500;
    connect_site(get_conf('lodelOAIsite'));
    $sets = get_sets(0);
    foreach ($sets as $set) {
        $start = 0;
        // Get all of our records for this set (lodel site)
        while ($records = sql_get("SELECT `identity` FROM `records` WHERE `oai_id` = ? LIMIT ?,?;", [$set['oai_id'], $start, $batch])) {
            $record_ids = [];
            foreach ($records as $record) {
                $record_ids[] = $record['identity'];
            }

            // Look if found records still exists in the lodel site
            connect_site($set['name']);
            $entities = sql_get(lq('SELECT id FROM #_TP_entities WHERE id IN ('.join(',',$record_ids).') AND status > 0'));
            $entity_ids = [];
            foreach ($entities as $entity) {
                $entity_ids[] = $entity['id'];
            }

            $to_delete = array_diff($record_ids, $entity_ids);
            if ($to_delete) {
                delete_record($set['name'], $to_delete);
            }

            connect_site(get_conf('lodelOAIsite'));
            $start += $batch;
        }
    }
}

/*
Delete records of a site from `records` table that no longer exists
Input:
    $site (string): short name of lodel site
    $ids (array of int): ids of records to delete
Output:
    none
*/
function delete_record($site, $ids) {
    connect_site(get_conf('lodelOAIsite'));
    $in = join(',', $ids);
    _log("Deleting records from $site : $in");
    sql_query("DELETE FROM `records` WHERE `site`=? AND identity IN ($in);", [$site]);
}
