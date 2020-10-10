<?php

# MUST be connected to oai-pmh
function get_sets ($limit=10, $offset=0, $order='id') {
    $q = "SELECT `set`, `oai_id`, `name`, `title` FROM `sets` ORDER BY `$order`";
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $sets = sql_get($q.';');

    return $sets;
}

# Role:
#   Get a single set info
# MUST be connected to oai-pmh
function get_set($site) {
    return sql_getone("SELECT * FROM `sets` WHERE `name` = ?;", [$site]);
}

# Role:
#   Get a list of records from class and type
#   With information to fill `records` table
function get_records_simple($class, $type, $limit=10, $offset=0, $order='identity') {
    $q = lq("SELECT `identity`, `titre`, `datemisenligne`, `dateacceslibre`, `modificationdate` FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0 ORDER BY `$order`");
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $records = sql_get($q.';');

    return $records;
}

# Role:
#   Get a full record
# Input:
#   $set: complete set of the site
#   $class: class of the record
#   $id: id entity of the record
# MUST be connected to $set['name']
function get_record($set, $class, $id) {
    # Get lodel record for this entity
    $rec = sql_getone(lq("SELECT c.*, t.type FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE e.idtype = t.id AND c.identity = e.id AND identity=?;"), [$id]);
    if (!$rec) return false;

    # Our big array with all info about the record
    $record = array();

    #
    # TITLE
    #
    $title = $rec['titre'];
    $title = removenotes($title);
    $title = strip_tags($title);
    $record['title'] = $title;

    #
    # CREATOR
    #
    if ($class == 'textes') {
        $record['creator'] = get_persons($id, 'auteur');
    }
    # Pour les types publication, il faut les personnes de tous les enfants
    if ($class == 'publications') {
        $record['creator'] = get_persons($id, 'auteur');
        $children = get_children($id);
        foreach ($children as $child) {
            $record['creator'] = array_merge(get_persons($child, 'auteur'), $record['creator']);
        }
        $record['creator'] = array_unique($record['creator'], SORT_STRING);
    }

    #
    # CONTRIBUTOR
    #
    if ($class == 'publications') {
        $record['contributor'] = get_persons($id, 'directeurdelapublication');
    }

    #
    # RIGHTS
    #

//     TODO: OPTIONS.METADONNEESSITE.DROITSAUTEUR
//           OPTIONS.EXTRA.OPENAIRE_ACCESS_LEVEL
    $record['rights'][] = $set['droitsauteur'];
    $record['rights'][] = 'info:eu-repo/semantics/'.$set['openaire_access_level'];

    #
    # DATE
    #
    $record['date'][] = $rec['datepubli'];
    if ($set['openaire_access_level'] == 'embargoedAccess') {
        $record['date'][] = 'info:eu-repo/date/embargoEnd/' . $rec['datepubli'];
    }

    #
    # PUBLISHER
    #
    $record['publisher'][] = $set['editeur'];
    $record['publisher'][] = $set['titresite'];

    #
    # IDENTIFIER
    #
    $record['identifier'][] = $set['url'] . $id;
    $record['identifier'][] = 'urn:doi:' . $set['doi_prefixe'] . $id;

    #
    # LANGUAGE
    #
    $record['language'] = $rec['langue'] ? $rec['langue'] : $set['langueprincipale'];

    #
    # TYPE
    #
    # TODO faire la liaison avec la table type pour l'avoir dans le record
    $record['type'][] = convert_type($class, $rec['type'], 'oai');
    $record['type'][] = 'info:eu-repo/semantics/' . convert_type($class, $rec['type'], 'openaire');

    #
    # COVERAGE
    #



    _log_debug($record);
    return $record;
}

function get_persons($id, $type) {
    $pers = sql_get(
        lq("SELECT g_firstname,g_familyname FROM #_TP_relations r, #_TP_persons p, #_TP_persontypes pt WHERE r.id1=? AND r.id2=p.id AND nature='G' AND p.idtype=pt.id AND type=? ORDER BY `degree`;"),
        [$id, $type]
    );
    $persons = array();
    foreach ($pers as $p) {
        $persons[] = $p['g_familyname'] . ', ' . $p['g_firstname'];
    }

    return $persons;
}

# Role:
#   get ids of all children (recursive)
function get_children($id) {
    $ids = array();

    $children = sql_get(lq("SELECT id FROM #_TP_entities WHERE idparent=?;"), [$id]);
    foreach ($children as $i) {
        $ids[] = $i['id'];
        $ids = array_merge(get_children($i['id']), $ids);
    }

    return $ids;
}
