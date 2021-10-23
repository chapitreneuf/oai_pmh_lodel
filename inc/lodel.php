<?php

global $database_prefix;
global $current_site;

/*
Connect to lodel config and functions
*/
function lodel_connect() {
    global $database_prefix;
    define('backoffice-lodeladmin', true);
    require_once '../lodelconfig.php';

    define("SITEROOT","../");
    $cfg['home'] = "lodel/scripts/";
    $cfg['sharedir'] = SITEROOT . $cfg['sharedir'];
    ini_set('include_path', SITEROOT. $cfg['home'] . PATH_SEPARATOR . ini_get('include_path'));
    require 'context.php';
    require 'textfunc.php';
    require 'func.php';
    C::setCfg($cfg);

    require_once 'auth.php';
    $lodeluser = array('rights'=>LEVEL_ADMINLODEL, 'adminlodel'=>1, 'id'=>1, 'groups'=>'');
    C::set('lodeluser', $lodeluser);
    C::set('login', 'admin');
    C::setUser($lodeluser);
    unset($lodeluser);

    $GLOBALS['nodesk'] = true;
    C::set('nocache', true);
    require_once 'connect.php';

    $database_prefix = c::Get('database','cfg');
};

/*
Connect to a lodel site database
Input:
    $site (string): short name of the lodel site, empty to connect to lodeladmin
Output:
    none
*/
function connect_site($site='') {
    global $database_prefix;
    global $current_site;

    if ($site == $current_site) return true;

    $current_site = $site;
    $db_name = $database_prefix . ($site ? ("_" . $site) : '');
    $GLOBALS['currentdb'] = $db_name;
//     _log("Connexion à $db_name", false);

    // Do it ourself, lodel usecurrentdb() always return true…
    // We want to return false if error
    return $GLOBALS['db']->SelectDB($db_name);
}

/*
List all lodel site which have OAI activated
Input:
    $status (int): minimum status of the site
Output:
    Array of associative arrays with those keys
    name, title, url, oai_id, upd, description, subject, droitsauteur, editeur, titresite, issn
    issn_electronique, langueprincipale, doi_prefixe, openaire_access_level
*/
function get_sites($status=0) {
    global $current_site;

    $sites = array();
    $les_sites = sql_get(lq("SELECT title, name, url, upd FROM #_MTP_sites WHERE status>?"), [$status]);

    foreach ($les_sites as $site) {
        connect_site($site['name']);
        $oai_id = get_option('extra', 'oai_id');
        # Seulement les sites avec oai_id de renseigné
        # TODO if status == 0 get site anyway
        if ($oai_id) {
            $this_site = ['name'=>$site['name'], 'title'=>$site['title'], 'url'=>$site['url'], 'oai_id'=>$oai_id, 'upd'=>$site['upd']];
            $description = get_option('metadonneessite', 'descriptionsite');
            $this_site['description'] = htmlspecialchars(html_entity_decode(strip_tags($description)));
            $this_site['subject'] = htmlspecialchars(get_option('metadonneessite', 'motsclesdusite'));
            $this_site['droitsauteur'] = get_option('metadonneessite', 'droitsauteur');
            $this_site['editeur'] = htmlspecialchars(get_option('metadonneessite', 'editeur'));
            $this_site['titresite'] = htmlspecialchars(get_option('metadonneessite', 'titresite'));
            $this_site['issn'] = get_option('metadonneessite', 'issn');
            $this_site['issn_electronique'] = get_option('metadonneessite', 'issn_electronique');
            $this_site['langueprincipale'] = get_option('metadonneessite', 'langueprincipale');
            $this_site['doi_prefixe'] = get_option('extra', 'doi_prefixe');
            $this_site['openaire_access_level'] = get_option('extra', 'openaire_access_level');

            $sites[] = $this_site;
        }
    }

    return $sites;
}

/*
Retrieve value of a lodel site option
Input:
    $group (string): group name of the option
    $name (string): name of option
Output:
    $value (string): value of the option, '' if empty or non existing
*/
function get_option($group, $name) {
    $q = lq("SELECT value FROM #_TP_options o, #_TP_optiongroups og WHERE o.idgroup = og.`id` AND og.`name` = ? AND  o.`name`=?");
    $value = sql_getone($q, [$group, $name], 'value');
    return $value ? $value : '';
}

/*
Get list of entities of a class and type
MUST be connected to a site
Input:
    $class (string): class name
    $type (string): type name (optional)
    $limit (int): limit !
    $offset (int): offset !!
    $order (string): order by clause
Output:
    Array of associative arrays with those keys
    identity, titre, datemisenligne, dateacceslibre, modificationdate, idparent, rank, upd
*/
function get_entities($class, $type, $limit=10, $offset=0, $order='identity') {
    $q = lq("SELECT c.`identity`, c.`titre`, c.`datemisenligne`, c.`dateacceslibre`, e.`modificationdate`, e.`idparent`, e.`rank`, e.`upd` FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0 ORDER BY `$order`");
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $records = sql_get($q.';');

    // Format title: delete notes and tags
    foreach ($records as &$rec) {
        $rec['titre'] = removenotes($rec['titre']);
        $rec['titre'] = strip_tags($rec['titre']);
    }

    return $records;
}

/*
Get all fields of an entity
Input:
    $class (string): class name of the entity
    $id (int): identity
Output:
    $entity (assoc array): row of the entity class table, and type
*/
function get_entity($class, $id) {
    return sql_getone(lq("SELECT c.*, t.type FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE e.idtype = t.id AND c.identity = e.id AND identity=?;"), [$id]);
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
