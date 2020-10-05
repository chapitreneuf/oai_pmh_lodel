<?php

function get_sets ($limit=10, $offset=0, $order='id') {
    global $db;
    global $current_site;

    # Save current site name then connect to main
    $previous_site = $current_site;
    connect_site('oai-pmh');

    $q = "SELECT `set`, `oai_id`, `name`, `title` FROM `sets` ORDER BY `$order`";
    if ($limit) {
        $q .=  " LIMIT $offset,$limit";
    }
    $q .= ';';
    $stmt = $db->execute($q);
    $sets = $stmt->GetAll();

    # connect back
    connect_site($previous_site);

    return $sets;
}

function get_records_simple($class, $type, $limit=10, $offset=0, $order='identity') {
    global $db;

    $q = lq("SELECT `identity`, `titre`, `datemisenligne`, `dateacceslibre`, `modificationdate` FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0 ORDER BY `$order`");
    if ($limit) {
        $q .=  " LIMIT $offset,$limit";
    }
    $q .= ';';
    _log($q);
    $stmt = $db->execute($q);
    _log_debug($stmt);
    $records = $stmt->GetAll();

    return $records;
}

# Get a full record
function get_record($site, $class, $id) {
    global $db;
    global $current_site;

    # Save current site name then connect to main
    $previous_site = $current_site;
    connect_site($site);

    # Get lodel record for this entity
    $q = lq("SELECT c.* FROM #_TP_$class c, #_TP_entities e WHERE c.identity = e.id AND identity=?;");
    $stmt = $db->execute($q, [$id]);
    $rec = $stmt->GetAll();
    if (!$rec) {
        return false;
    }
    $rec = $rec[0];

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
    $record['rights'][] = '';
    $record['rights'][] = 'info:eu-repo/semantics/';

    #
    # DATE
    #

    # connect back
    connect_site($previous_site);

    _log_debug($record);
    return $record;
}

function get_persons($id, $type) {
    global $db;
    $q = lq("SELECT g_firstname,g_familyname FROM #_TP_relations r, #_TP_persons p, #_TP_persontypes pt WHERE r.id1=? AND r.id2=p.id AND nature='G' AND p.idtype=pt.id AND type=? ORDER BY `degree`;");
    $stmt = $db->execute($q, [$id, $type]);
    $pers = $stmt->GetAll();
    $persons = array();
    foreach ($pers as $p) {
        $persons[] = $p['g_familyname'] . ', ' . $p['g_firstname'];
    }

    return $persons;
}

# Role:
#   get ids of all children (recursive)
function get_children($id) {
    global $db;
    $q = lq("SELECT id FROM #_TP_entities WHERE idparent=?;");
    $stmt = $db->execute($q, [$id]);
    $ids = $stmt->GetAll();

    $acc = array();
    foreach ($ids as $i) {
        $acc[] = $i['id'];
        $acc = array_merge(get_children($i['id']), $acc);
    }

    return $acc;
}