<?php

global $database_prefix;
global $current_site;

# Connexion à lodel
function lodel_connect () {
    global $database_prefix;
    define('backoffice-lodeladmin', true);
    require_once '../lodelconfig.php';

    define("SITEROOT","../");
    $cfg['home'] = "lodel/scripts/";
    $cfg['sharedir'] = SITEROOT . $cfg['sharedir'];
    ini_set('include_path', SITEROOT. $cfg['home'] . PATH_SEPARATOR . ini_get('include_path'));
    error_log(ini_get('include_path'));
    error_log($cfg['sharedir']);
    error_log(SITEROOT);
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

# Role:
#   Connexion à la base d'un site lodel
# Input:
#   $site: nom du site, ne rien mettre pour le site lodeladmin
# Output:
#   none
function connect_site($site='') {
    global $database_prefix;
    global $current_site;

    if ($site == $current_site) return true;

    $current_site = $site;
    $db_name = $database_prefix . ($site ? ("_" . $site) : '');
    $GLOBALS['currentdb'] = $db_name;
    _log("Connexion à $db_name", false);

    # Do it ourself, lodel usecurrentdb() always return true…
    # We want to return false if error
    return $GLOBALS['db']->SelectDB($db_name);
}

# Role:
#   Liste des sites lodel de cette instance qui ont OAI d'activé
function get_sites($status=0) {
    global $current_site;

    $sites = array();
    $les_sites = sql_get(lq("SELECT title, name, url FROM #_MTP_sites WHERE status>?"), [$status]);

    foreach ($les_sites as $site) {
        connect_site($site['name']);
        $oai_id = get_option('extra', 'oai_id');
        # Seulement les sites avec oai_id de renseigné
        # TODO if status == 0 get site anyway
        if ($oai_id) {
            $this_site = ['name' => $site['name'], 'title' => $site['title'], 'url' => $site['url'], 'oai_id' => $oai_id];
            $this_site['droitsauteur'] = get_option('metadonneessite', 'droitsauteur');
            $this_site['editeur'] = get_option('metadonneessite', 'editeur');
            $this_site['titresite'] = get_option('metadonneessite', 'titresite');
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

# Role:
#   Recevoir la valeur d'une option
function get_option($group, $name) {
    $q = lq("SELECT value FROM #_TP_options o, #_TP_optiongroups og WHERE o.idgroup = og.`id` AND og.`name` = ? AND  o.`name`=?");
    $value = sql_getone($q, [$group, $name], 'value');
    return $value ? $value : '';
}


# Role:
#   Recevoir la liste de entités d'une class
#   Donne les informations essentielles
function get_entity_info($class, $type='', $site='') {
    if ($site) {
        connect_site($site);
    }
    return sql_get(lq("SELECT identity, titre, datemisenligne, langue, status FROM `#_TP_$class` c, `#_TP_entities` e WHERE c.identity = e.id AND status>0"));
}
