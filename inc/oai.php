<?php

/*
    Functions that answer to verbs of oai-pmh protocol
*/

/*
Returns a list of set
TODO: need $count, $deliveredRecords, $maxItems
Input:
    $count (bool): only return total count of sets
    $maxItems (int): number of set to return
    $cursor (int): offset
Output:
[
    [
        setSpec => journals:$oai_id,
        setName => name,
        setDescription => (optional) [
            container_name => name,
            container_attributes => [name => value, ],
            fields => [
                tagname => [value, value], OR
                tagname => [[value,[attr_name=>value]], [value,[attr_name=>value]]],
            ],
        ],
    ],
    [another set], …
]
*/
function listSets($count, $maxItems, $cursor=0) {
    // artificially add our two top level sets
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

    // If asked only returns count of sets
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
                $this_set['setDescription']['fields']['dc:identifier'][] = 'urn:issn:' . $set['issn'];
            if (!empty($set['issn_electronique']))
               $this_set['setDescription']['fields']['dc:identifier'][] = 'urn:eissn:' . $set['issn_electronique'];
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

/*
Returns a record formatted by $metadataPrefix
Input:
    $identifier (string): oai:$oai_id/$id
    $metadataPrefix (string): output format of record (oai_dc, qdc or mets)
Output:
[
    identifier => oai:$oai_id/$id,
    datestamp => '2017-01-17 10:30:02',
    set => [name, name],
    metadata => [
        node_name,
        node_value,
        [attr_name => attr_value, … ],
        [
            [ child_node_name, child_node_value, [attrs… ], [children, …] ],
            …
        ],
    ]
]
*/
function GetRecord($identifier, $metadataPrefix) {
    $record_info = get_record_from_identifier($identifier); // from utils
    if (!$record_info) return false;

    // if METS metadataPrefix refuse if not class=publications AND type=numero

    // use True because we want to get the full record
    return create_record($record_info, $metadataPrefix, True); // from record
}

/*
Returns a list of records formatted by $metadataPrefix
Input:
    $metadataPrefix (string): output format of records (oai_dc, qdc or mets)
    $from (date string): from when (can be empty)
    $until (date string): until when (can be empty)
    $set (string): name of the set (can be empty)
    $count (int): number of record to return
    $list_records (bool): return full information about the record (False for ListIdentifiers verb)
    $deliveredRecords (int): offset of the list
    $maxItems (int): number of records to return
Output:
    $records: array of records (like output of GetRecord())
*/
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

    // If METS metadataPrefix, MUST force class=publications AND type=numero

    connect_site('oai-pmh');
    if ($count) {
        $total = sql_getone('SELECT count(id) as total FROM records WHERE '.$where, $bind, 'total');
        return $total;
    }

    $bind[] = intval($deliveredRecords);
    $bind[] = intval($maxItems);

    $record_list = sql_get('SELECT `site`, `class`, `identity`, `date`, `set`, `oai_id`, `openaire` FROM records WHERE '.$where.' ORDER BY id LIMIT ?,?;', $bind);
    // _log_debug($record_list);

    foreach ($record_list as $record_info) {
        $record = create_record($record_info, $metadataPrefix, $list_records);
        $records[] = $record;
    }

    return $records;
}

/*
List all formats this OAI server can serve
Input:
    $identifier (string): oai identifier
Output:
    $formats: associative array (read the function)
*/
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
        ],
        // [
        //     'metadataPrefix'=>'mets',
        //     'schema'=>'http://www.loc.gov/standards/mets/mets.xsd',
        //     'metadataNamespace'=>'http://www.loc.gov/METS/',
        // ],
    ];

    // If we have an identifier test if it can be exported as mets
    // if (!empty($identifier)) {
    //     $record = get_record_from_identifier($identifier);
    //     if (!($record['class'] == 'publications' && $record['type'] == 'numero')) {
    //         delete($formats['mets']);
    //     }
    // }

    return $formats;
}
