<?php

function get_sets ($offset=0, $limit=10, $order='id') {
    global $db;
    global $current_site;

    # Save current site name then connect to main
    $previous_site = $current_site;
    connect_site('oai-pmh');

    $q = "SELECT `set`, `oai_id`, `name`, `title` FROM `sets` ORDER BY `$order`";
    if ($limit) {
        $q .=  "LIMIT $offset,$limit;";
    }
    $stmt = $db->execute($q);
    $sets = $stmt->GetAll();

    # connect back
    connect_site($previous_site);

    return $sets;
}

function get_records_simple($class, $type) {
    global $db;

    $q = lq("SELECT identity, titre, datemisenligne, dateacceslibre, modificationdate FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0;");
    _log($q);
    $stmt = $db->execute($q);
    _log_debug($stmt);
    $records = $stmt->GetAll();

    return $records;
}