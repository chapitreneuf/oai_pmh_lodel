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
        if ($metadataPrefix == 'oai_dc') {
            $record_formated = format_oai_dc($record);
            $container_name = 'oai_dc:dc';
            $container_attributes = [
                'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
                'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
                'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
            ];
        } else if ($metadataPrefix == 'qdc') {
            $record_formated = format_oai_qdc($record);
            $container_name = 'qdc:qualifieddc';
            $container_attributes = [
                'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                'xsi:schemaLocation' => 'http://www.bl.uk/namespaces/oai_dcq/ http://www.bl.uk/schemas/qualifieddc/oai_dcq.xsd'
            ];
        }
        $records[] = [
            # TODO: create a function for that
            'identifier' => 'doi:' . $set['doi_prefixe'] . $record_info['identity'],
            'datestamp' => $record_info['date'],
            # TODO: create a function for that
            'set' => $record_info['set'] . ':' . $record_info['oai_id'],
            'metadata' => [
                'container_name' => $container_name,
                'container_attributes' => $container_attributes,
                'fields' => $record_formated
            ],
        ];
    }

    return $records;
}

function format_oai_dc($record) {
    $convert = [
        'title' => 'dc:title',
        'identifier_url' => 'dc:identifier',
        'identifier_doi' => 'dc:identifier',
        'creator' => 'dc:creator',
        'contributor' => 'dc:contibutor',
        'rights' => 'dc:rights',
        'accessrights' => 'dc:rights',
        'issued' => 'dc:date',
        'embargoed' => 'dc:date',
        'publisher' => 'dc:publisher',
        'language' => 'dc:language',
        'type' => 'dc:type',
        'coverage' => 'dc:coverage',
        'issn' => 'dc:relation',
        'eissn' => 'dc:relation',
    ];
    foreach ($convert as $from => $to) {
        if (!empty($record[$from])) {
            if (is_array($record[$from])) {
                $oai[$to] = $record[$from];
            } else {
                $oai[$to][] = $record[$from];
            }
        }
    }

    $convert_lang = [
        ['subjects', 'dc:subjects'],
        ['abstract', 'dc:description'],
        ['description', 'dc:description'],
    ];
    foreach ($convert_lang as $conv) {
        list($from, $to) = $conv;
        if (!empty($record[$from])) {
            foreach ($record[$from] as $fields) {
                $oai[$to][] = [$fields[0], ['xml:lang'=>$fields[1]]];
            }
        }
    }

    return $oai;
}

function format_oai_qdc($record) {
    # TODO some value in record must be prefixed

    # [ [from, to, [attrs], prefix ]
    $convert = [
        ['title', 'dcterms:title'],
        ['identifier_url', 'dcterms:identifier', ['scheme'=>'URI']],
        ['identifier_doi', 'dcterms:identifier', ['scheme'=>'URN']],
        ['issn', 'dcterms:isPartOf', ['scheme'=>'URN']],
        ['eissn', 'dcterms:isPartOf', ['scheme'=>'URN']],
        ['creator', 'dcterms:creator'],
        ['contributor', 'dcterms:contibutor'],
        ['accessrights', 'dcterms:accessRights'],
        ['rights', 'dcterms:rights'],
        ['issued', 'dcterms:issued', ['xsi:type'=>'dcterms:W3CDTF']],
        ['embargoed', 'dcterms:available', ['xsi:type'=>'dcterms:W3CDTF']],
        ['publisher', 'dcterms:publisher'],
        ['language', 'dcterms:language', ['xsi:type'=>'dcterms:RFC1766']],
        ['type', 'dcterms:type'],
        ['extent', 'dcterms:extent'],
        ['spatial', 'dcterms:spatial'],
        ['temporal', 'dcterms:temporal'],
        ['bibliographicalCitation.issue', 'dcterms:bibliographicalCitation.issue'],
    ];
    foreach ($convert as $conv) {
        list ($from, $to, $attrs, $prefix) = $conv;
        if (!empty($record[$from])) {
            if (is_array($record[$from])) {
                $oai[$to] = $record[$from];
            } else {
                if ($attrs) {
                    $oai[$to][] = [$record[$from], $attrs];
                } else {
                    $oai[$to][] = $record[$from];
                }
            }
        }
    }

//     $oai['dcterms:hasFormat              '] = $record['?'];

    $convert_lang = [
        ['alternative', 'dcterms:alternative'],
        ['subjects', 'dcterms:subjects'],
        ['abstract', 'dcterms:abstract'],
        ['description', 'dcterms:description'],
    ];
    foreach ($convert_lang as $conv) {
        list($from, $to) = $conv;
        if (!empty($record[$from])) {
            foreach ($record[$from] as $fields) {
                $oai[$to][] = [$fields[0], ['xml:lang'=>$fields[1]]];
            }
        }
    }

    return $oai;
};