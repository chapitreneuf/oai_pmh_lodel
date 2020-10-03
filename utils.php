<?php

function get_sets ($offset=0, $limit=10, $order='id') {
    global $db;
    global $current_site;

    # Save current site name then connect to main
    $previous_site = $current_site;
    connect_site('oai-pmh');

    $q = "SELECT `set`, `oai_id`, `name`, `title` FROM `sets` ORDER BY `$order` LIMIT $offset,$limit;";
    $stmt = $db->execute($q);
    $sets = $stmt->GetAll();

    # connect back
    connect_site($previous_site);

    return $sets;
}

