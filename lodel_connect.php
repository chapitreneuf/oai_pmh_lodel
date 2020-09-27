<?php

global $db;
global $database_prefix;

# Connexion à lodel
function lodel_init () {
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
    $db_name = $database_prefix . ($site ? ("_" . $site) : '');
    $GLOBALS['currentdb'] = $db_name;
    _log("Connexion à $db_name");
    usecurrentdb();
}

# Role:
#   Liste des sites lodel de cette instance
function get_sites($status=0) {
    global $db;
    connect_site();
    $sites = array();
    $les_sites = $db->execute(lq("SELECT name FROM #_MTP_sites WHERE status>$status"));
    while ($site = $les_sites->FetchRow()) {
        $sites[] = $site['name'];
    }
    return $sites;
}

# Role:
#   Recevoir la liste de entitiées d'une class
#   Donne les informations essentielles
function get_entity_info($class, $type='', $site='') {
    global $db;
    _log("tentative de connexion à $site");
    if ($site) {
        connect_site($site);
    }
    $query = lq("SELECT identity, titre, datemisenligne, langue, status FROM `#_TP_$class` c, `#_TP_entities` e WHERE c.identity = e.id AND status>0");
    _log_debug($query);
    $stmt = $db->execute($query);
    _log_debug($stmt);
    return $stmt->GetAll();
}

function _log_debug($var) {
    $error = var_export($var, 1);
    _log($error);
}

function _log ($var) {
    print "<p>$var</p>";
    error_log($var);
}