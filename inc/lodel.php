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
Get list of entities of a class
TODO: not used
Input:
    $class (string): class name
    $type (string): type name (optional)
    $site (string): short name of lodel site (optional)
Output:
    Array of associative arrays with those keys
    identity, titre, datemisenligne, langue, status
*/
function get_entity_info($class, $type='', $site='') {
    if ($site) {
        connect_site($site);
    }
    return sql_get(lq("SELECT identity, titre, datemisenligne, langue, status FROM `#_TP_$class` c, `#_TP_entities` e WHERE c.identity = e.id AND status>0"));
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
    identity, titre, datemisenligne, dateacceslibre, modificationdate
*/
function get_entities($class, $type, $limit=10, $offset=0, $order='identity') {
    $q = lq("SELECT `identity`, `titre`, `datemisenligne`, `dateacceslibre`, `modificationdate` FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0 ORDER BY `$order`");
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $records = sql_get($q.';');

    // Format title: delete notes and tags
    foreach ($records as &$rec) {
        $rec['titre'] = removenotes($rec['titre']);
        $rec['titre'] = strip_tags($rec['titre']);
    }

    return $records;
}
