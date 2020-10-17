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

# Get oai identifier of a ressource
# TODO: add global site name
function oai_identifier($oai_id, $id) {
    return 'oai:' . $oai_id . '/' . $id;
}

function get_record_from_identifier($identifier) {
    preg_match_all('@^oai:([^/]*)/(\d+)$@', $identifier, $matches, PREG_PATTERN_ORDER);
    if (!$matches) throw new OAI2Exception('idDoesNotExist');

    $oai_id = $matches[1][0];
    $id = $matches[2][0];

    connect_site('oai-pmh');
    $record = sql_getone("SELECT * FROM `records` WHERE `oai_id` = ? AND identity = ?;", [$oai_id, $id]);
    if (!$record) throw new OAI2Exception('idDoesNotExist');

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

# Role:
#   get indexes of a type attach to an entity
#   if type contains a % will search with SQL LIKE
# Input:
#   $id: id of entity
#   $type: type of index
function get_index($id, $type) {
    $sql_type = strpos($type, '%') === false ? 't.`type` = ?' : 't.`type` LIKE ?';
    $entries = sql_get(lq("SELECT e.g_name, t.type FROM #_TP_relations r, #_TP_entries e, #_TP_entrytypes t WHERE t.class='indexes' AND r.id1=? AND r.id2=e.id AND t.id=e.idtype AND nature='E' AND $sql_type ORDER BY t.`rank`, r.`degree`;"), [$id, $type]);
    return $entries;
}

# Role:
#   return oai or openaire type of a publication
# Input:
#   $class: lodel class of the publication
#   $type: lodel type of the publication
#   $set: oai or openaire
# Output:
#   $type: string of type of publication
function convert_type($class, $type, $set='oai') {
    $types = get_publication_types();
    return get_publication_types()[$class][$type][$set];
}

# TODO: this should be a config (or at least extendable)
function get_publication_types() {
    return [
        'publications' => [
            'numero' => ['oai'=>'issue', 'openaire'=> 'other'],
            'souspartie' => ['oai'=>'part', 'openaire'=> 'other'],
        ],
        'textes' => [
            'article' => ['oai'=>'article', 'openaire'=> 'article'],
            'chronique' => ['oai'=>'article', 'openaire'=> 'other'],
            'compterendu' => ['oai'=>'review', 'openaire'=> 'review'],
            'notedelecture' => ['oai'=>'review', 'openaire'=> 'review'],
            'editorial' => ['oai'=>'introduction', 'openaire'=> 'article'],
        ],
    ];
}

function _log_debug($var, $print=true) {
    $error = var_export($var, 1);
    _log($error, $print);
}

function _log ($var, $print=true) {
    if (php_sapi_name() == "cli") {
        $print = false;
    }
    if ($print) {
        print "<p>$var</p>";
    }
    error_log($var);
}