<?php

#
# Functions that answer to verbs of oai-pmh protocol
#

# TODO: need $count, $deliveredRecords, $maxItems
function listSets($count, $maxItems, $cursor=0) {
    if ($cursor == 0) {
        $maxItems -= 2;
        $sets = array(
            ['setSpec'=>"journals", 'setName'=>"Les super journaux de notre dépôt"],
            ['setSpec'=>"openaire", 'setName'=>"openaire"]
        );
    } else {
        $cursor -= 2;
    }
    connect_site('oai-pmh');

    if ($count) {
        $les_sets = get_sets();
        return count($les_sets)+1;
    }
    $les_sets = get_sets($maxItems, $cursor);

    foreach($les_sets as $set) {
        $this_set = ['setSpec'=>"journals:${set['oai_id']}", 'setName'=>$set['title']];
        if (!empty($set['description']) || !empty($set['issn']) || !empty($set['issn_electronique']) || !empty($set['subject']) ) {
            $this_set['setDescription'] = [
                'container_name' => 'oai_dc:dc',
                'container_attributes' => [
                    'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
                    'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
                    'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
                    'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
                ],
            ];
            if (!empty($set['description']))
                $this_set['setDescription']['fields']['dc:description'][] = [$set['description'], ['xml:lang'=>$set['langueprincipale']]];
            if (!empty($set['url']))
                $this_set['setDescription']['fields']['dc:identifier'][] = 'uri:' . $set['url'];
            if (!empty($set['issn']))
                $this_set['setDescription']['fields']['dc:identifier'][] = 'urn:' . $set['issn'];
            if (!empty($set['issn_electronique']))
               $this_set['setDescription']['fields']['dc:identifier'][] = 'urn:' . $set['issn_electronique'];
            if (!empty($set['subject'])) {
                foreach (explode(',', $set['subject']) as $subject) {
                    $this_set['setDescription']['fields']['dc:subject'][] = trim($subject);
                }
            }
        }
        $sets[] = $this_set;
    }
    return $sets;
}

function GetRecord($identifier, $metadataPrefix) {
    $record_info = get_record_from_identifier($identifier);
    if (!$record_info) return false;

    # METS metadataPrefix: force class=publications AND type=numero

    return create_record($record_info, $metadataPrefix, True);
}

function ListRecords($metadataPrefix, $from, $until, $set, $count, $list_records, $deliveredRecords, $maxItems) {
    if (!empty($from)) {
        $wheres[] = '`date` >= ?';
        $bind[] = $from;
    }
    if (!empty($until)) {
        $wheres[] = '`date` <= ?';
        $bind[] = $until;
    }
    if (!empty($set)) {
        $sets = explode(':', $set);
        if (empty($sets[1])) {
            if ($sets[0] == 'openaire') {
                $wheres[] = '`openaire` = ?';
                $bind[] = 'openAccess';
            } else {
                $wheres[] = '`set` = ?';
                $bind[] = $sets[0];
            }
        } else {
            $wheres[] = '`set` = ? AND `oai_id` = ?';
            $bind[] = $sets[0];
            $bind[] = $sets[1];
        }
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

    $record_list = sql_get('SELECT `site`, `class`, `identity`, `date`, `set`, `oai_id`, `openaire` FROM records WHERE '.$where.' ORDER BY id LIMIT ?,?;', $bind);
//     _log_debug($record_list);

    foreach ($record_list as $record_info) {
        $record = create_record($record_info, $metadataPrefix, $list_records);
        $records[] = $record;
    }

    return $records;
}

function ListMetadataFormats($identifier) {
    $formats = [
        'qdc' => [
            'metadataPrefix'=>'qdc',
            'schema'=>'http://dublincore.org/schemas/xmls/qdc/2008/02/11/qualifieddc.xsd',
            'metadataNamespace'=>'http://purl.org/dc/terms/'
        ],
        'oai_dc' => [
            'metadataPrefix'=>'oai_dc',
            'schema'=>'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'metadataNamespace'=>'http://www.openarchives.org/OAI/2.0/oai_dc/',
            'record_prefix'=>'dc',
            'record_namespace' => 'http://purl.org/dc/elements/1.1/',
        ]
    ];
    if (!empty($identifier)) {
        $record = get_record_from_identifier($identifier);
        # TODO add mets for publication - numero when implemented
    //                 if ($record['class'] == 'publications' && $record['type'] == 'numero') {
    //                     $formats['mets'] = [
    //                         'metadataPrefix'=>'mets',
    //                         'schema'=>'http://www.loc.gov/standards/mets/mets.xsd',
    //                         'metadataNamespace'=>'http://www.loc.gov/METS/',
    //                     ]
    //                 }
    }
    return $formats;
}
