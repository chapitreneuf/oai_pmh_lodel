<?php

/*
Returns an array of the `sets` table
MUST be connected to oai-pmh site
Input:
    $limit (int): limit !
    $offset (int): offset !!
    $order (string): optional order by clause (default id)
Output:
    Array of associative array with those keys
    id, set, oai_id, name, title, description, subject, url, droitsauteur, editeur, titresite, issn
    issn_electronique, langueprincipale, doi_prefixe, openaire_access_level, upd
*/
function get_sets ($limit=10, $offset=0, $order='id') {
    $q = "SELECT * FROM `sets` ORDER BY `$order`";
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $sets = sql_get($q.';');

    return $sets;
}

/*
Returns one line of the `sets` table
MUST be connected to oai-pmh site
Input:
    $site (string): lodel site short name
Output:
    Associative array with those keys
    id, set, oai_id, name, title, description, subject, url, droitsauteur, editeur, titresite, issn
    issn_electronique, langueprincipale, doi_prefixe, openaire_access_level, upd
*/
function get_set($site) {
    return sql_getone("SELECT * FROM `sets` WHERE `name` = ?;", [$site]);
}

/*
Formats an oai identifier to identify a ressources
TODO: add global site name
Input:
    $oai_id (string): from lodel option of the site
    $id (int): identity of lodel entity
Output:
    formated string oai identifier
*/
function oai_identifier($oai_id, $id) {
    return 'oai:' . $oai_id . '/' . $id;
}

/*
Returns a line of `records table` from an oai identifier
OAI identifier is made by oai_identifier() function
Input:
    $identifier (string): oai:$oai_id/$lodel_identifier
Output:
    Associative array with those keys :
    id, identity, title, date, set, oai_id, openaire, site, class, type
*/
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

/*
Returns array of person name associated to an entity
Input:
    $id (int): identifier of lodel entity
    $type (string): persontype of lodel Editorial Model
Output:
    ['lastname, firstname', …]
*/
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

/*
Get array of identity of all children (recursive)
Input:
    $id (int): identifier of parent lodel entity
Output:
    [id, id, …]
*/
function get_children($id) {
    $ids = array();

    $children = sql_get(lq("SELECT id FROM #_TP_entities WHERE idparent=?;"), [$id]);
    foreach ($children as $i) {
        $ids[] = $i['id'];
        $ids = array_merge(get_children($i['id']), $ids);
    }

    return $ids;
}

/*
Get indexes of a type attach to an entity
If type contains a % will search with SQL LIKE
Input:
    $id: id of entity
    $type: type of index
Output:
    Array of associative array with found indexes
    [ [name=>, type=>], … ]
*/
function get_index($id, $type) {
    $sql_type = strpos($type, '%') === false ? 't.`type` = ?' : 't.`type` LIKE ?';
    $entries = sql_get(lq("SELECT e.g_name, t.type FROM #_TP_relations r, #_TP_entries e, #_TP_entrytypes t WHERE t.class='indexes' AND r.id1=? AND r.id2=e.id AND t.id=e.idtype AND nature='E' AND $sql_type ORDER BY t.`rank`, r.`degree`;"), [$id, $type]);
    return $entries;
}

/*
Returns oai, openaire or mets type of a publication
Input:
  $class (string): lodel class of the publication
  $type (string): lodel type of the publication
  $set (string): oai or openaire (TODO mets)
Output:
  $type (string): type of publication for this kind of set
*/
function convert_type($class, $type, $set='oai') {
    $types = get_publication_types();
    return get_publication_types()[$class][$type][$set];
}

/*
List of classes and types that are exposed to OAI
Also list name of type to oai, openaire and mets (TODO) types
TODO: this could be a config (or at least extendable)
*/
function get_publication_types() {
    return [
        'publications' => [
            'numero' => ['oai'=>'issue', 'openaire'=> 'other', 'mets'=>'issue'],
            'souspartie' => ['oai'=>'part', 'openaire'=> 'other', 'mets'=>'part'],
        ],
        'textes' => [
            'article' => ['oai'=>'article', 'openaire'=> 'article', 'mets'=>'article'],
            'chronique' => ['oai'=>'article', 'openaire'=> 'other', 'mets'=>'article'],
            'compterendu' => ['oai'=>'review', 'openaire'=> 'review', 'mets'=>'review'],
            'notedelecture' => ['oai'=>'review', 'openaire'=> 'review', 'mets'=>'review'],
            'editorial' => ['oai'=>'introduction', 'openaire'=> 'article', 'mets'=>'introduction'],
        ],
    ];
}

// Log an object
function _log_debug($var, $print=true) {
    if (!get_conf('log')) return;

    $error = var_export($var, 1);
    _log($error, $print);
}

// log to screen and/or error.log
function _log ($var, $print=true) {
    if (!get_conf('log')) return;

    if (php_sapi_name() == "cli") {
        $print = false;
    }
    if ($print) {
        print "<p>$var</p>";
    }
    error_log($var);
}
