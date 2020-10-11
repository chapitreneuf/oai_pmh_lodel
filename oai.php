<?php

#
# Functions that answer to verbs of oai-pmh protocol
#

# TODO: need $count, $deliveredRecords, $maxItems
function listSets($resumptionToken) {
    $sets = array(
        ['setSpec'=>"journals", 'setName'=>"Les super journaux de notre dépôt"]
    );
    connect_site('oai-pmh');
    $les_sets = get_sets();
    foreach($les_sets as $set) {
        $sets[] = ['setSpec'=>"journals:${set['oai_id']}", 'setName'=>$set['title']];
    }
    return $sets;
}

function ListRecords($metadataPrefix, $from, $until, $set, $count, $deliveredRecords, $maxItems) {
    if (!empty($from)) {
        $wheres[] = '`date` >= ?';
        $bind[] = $from;
    }
    if (!empty($until)) {
        $wheres[] = '`date` <= ?';
        $bind[] = $until;
    }
    if (!empty($set)) {
        list ($set_id, $oai_id) = split(':', $set);
        $wheres[] = '`set` = ? AND `oai_id` = ?';
        $bind[] = $set_id;
        $bind[] = $oai_id;
    } else {
        $wheres[] = '`set` = ?';
        $bind[] = 'journals';
    }
    $where = implode(' AND ', $wheres);

    # METS metadataPrefix: force class=publications AND type=numero

    connect_site('oai-pmh');
    if ($count) {
        $total = sql_getone('SELECT count(id) as total FROM records WHERE '.$where, $bind, 'total');
        return $total;
    }

    $bind[] = intval($deliveredRecords);
    $bind[] = intval($maxItems);

    $record_list = sql_get('SELECT `site`, `class`, `identity`, `date`, `set`, `oai_id` FROM records WHERE '.$where.' ORDER BY id LIMIT ?,?;', $bind);
//     _log_debug($record_list);

    foreach ($record_list as $record_info) {
        connect_site('oai-pmh');
        $set = get_set($record_info['site']);

        connect_site($record_info['site']);
        # TODO: do not search record if ListIdentifiers
        $record = get_record($set, $record_info['class'], $record_info['identity']);
        # TODO format according to metadataPrefix
        $record_formated = format_oai_dc($record);
        $records[] = [
            'identifier' => 'doi:' . $set['doi_prefixe'] . $record_info['identity'],
            'datestamp' => $record_info['date'],
            'set' => $record_info['set'] . ':' . $record_info['oai_id'],
            'metadata' => [
                'container_name' => 'oai_dc:dc',
                'container_attributes' => [
                    'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
                    'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
                    'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                    'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
                ],
                'fields' => $record_formated
            ],
        ];
    }

    return $records;
}

function format_oai_dc($record) {
    # TODO: each element must be an array
    # each value must be an other array [content=>string, attrs=>[name=>value]]
    $oai['dc:title'] = $record['title'];
    $oai['dc:creator'] = $record['creator'];
    $oai['dc:contibutor'] = $record['contributor'];
    $oai['dc:rights'] = [$record['rights'], $record['accessrights']];
    $oai['dc:date'][] = $record['issued'];
    if (!empty($record['embargoed'])) $oai['dc:date'][] = $record['embargoed'];
    $oai['dc:publisher'] = $record['publisher'];
    $oai['dc:identifier'] = [$record['identifier']['url'], $record['identifier']['doi']];
    $oai['dc:language'] = $record['language'];
    $oai['dc:type'] = $record['type'];
    $oai['dc:coverage'] = $record['coverage'];
    $oai['dc:subjects'] = $record['subjects'];
    # TODO: add lang as attribut
    if (!empty($record['abstract'])) {
        foreach ($record['abstract'] as $abstract) {
            $oai['dc:description'][] = $abstract[0];
        }
    } else {
        $oai['dc:description'] = $record['subjects'][0];
    }
    $oai['dc:relation'][] = $record['issn'];
    $oai['dc:relation'][] = $record['eissn'];
    return $oai;
}

function format_oai_qdc($record) {
    # TODO: each element must be an array
    # each value must be an other array [content=>string, attrs=>[name=>value]]
    $oai['dcterms:title'] = $record['title'];
    $oai['dcterms:alternative'] = $record['alternative'];
    $oai['dcterms:creator'] = $record['creator'];
    $oai['dcterms:contibutor'] = $record['contributor'];
    $oai['dcterms:issued'] = $record['issued'];
    $oai['dcterms:accessRights'] = $record['accessrights'];
    $oai['dcterms:available'] = $record['embargoed'];
    $oai['dcterms:publisher'] = $record['publisher'];
    # TODO: attrs uri and urn
    $oai['dcterms:identifier'] = $record['identifier[url, doi]'];
    # TODO: attrs issn and eissn
    $oai['dcterms:isPartOf'] = $record['issn, eissn'];
//     $oai['dcterms:hasFormat              '] = $record['?'];
    $oai['dcterms:language'] = $record['language'];
    $oai['dcterms:type'] = $record['type'];
    $oai['dcterms:rights'] = $record['rights'];
    $oai['dcterms:extent'] = $record['extend'];
    $oai['dcterms:spatial'] = $record['spatial'];
    $oai['dcterms:temporal'] = $record['temporal'];
    # TODO attrs
    $oai['dcterms:subjects'] = $record['subjects'];
    # TODO attrs
    $oai['dctems:abstract'] = $record['abstract[]'];
    $oai['dctems:description'] = $record['description'];
    # TODO
    $oai['dcterms:bibliographicalCitation'] = $record['bibliographicCitation[issue]'];

    return $oai;
};