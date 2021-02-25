<?php

/*
Get a full record from a lodel entity
MUST be connected to $set['name']
TODO: ideally should not query DB
Input:
    $set (array): all information of the set of the site for this record (from `sets` table)
    $class (string): class of the record
    $id (int): id entity of the record
Output:
    Array of array
    [
        [name, value, optional lang value],
        [name, value, optional lang value]
        , …
    ]
*/
function get_record($set, $class, $id) {
    // Get lodel entity for this record
    $rec = sql_getone(lq("SELECT c.*, t.type FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE e.idtype = t.id AND c.identity = e.id AND identity=?;"), [$id]);
    if (!$rec) return false;

    // Our big array with all info about the record
    $record = array();

    #
    # TITLE
    #
    $title = $rec['titre'];
    $title = removenotes($title);
    $title = strip_tags($title);
    $record[] = ['title', $title];

    #
    # CREATOR
    #
    if ($class == 'textes') {
        foreach (get_persons($id, 'auteur') as $creator) {
            $record[] = ['creator', $creator];
        }
    }
    // For publication type, we need all persons associated to all children
    if ($class == 'publications') {
        $creators = get_persons($id, 'auteur');
        $children = get_children($id);
        foreach ($children as $child) {
            $creators = array_merge(get_persons($child, 'auteur'), $creators);
        }
        $creators = array_unique($creators, SORT_STRING);
        foreach ($creators as $creator) {
            $record[] = ['creator', $creator];
        }
    }

    #
    # CONTRIBUTOR
    #
    if ($class == 'publications') {
        foreach (get_persons($id, 'directeurdelapublication') as $contributor) {
            $record[] = ['contributor', $contributor];
        }
    }

    #
    # RIGHTS
    #
    $record[] = ['rights', $set['droitsauteur']];
    $record[] = ['accessrights', 'info:eu-repo/semantics/'.$set['openaire_access_level']];

    #
    # DATE
    #
    $record[] = ['issued', $rec['datepubli']];
    if ($set['openaire_access_level'] == 'embargoedAccess') {
        $record[] = ['embargoed', $rec['datepubli']];
    }

    #
    # PUBLISHER
    #
    $record[] = ['publisher', $set['editeur']];
    $record[] = ['publisher', $set['titresite']];

    #
    # IDENTIFIER
    #
    $record[] = ['identifier_url', $set['url'] . '/' . $id];
    $record[] = ['identifier_doi', 'urn:doi:' . $set['doi_prefixe'] . $id];

    #
    # LANGUAGE
    #
    $record[] = [
        'language',
        !empty($rec['langue']) ? $rec['langue'] : $set['langueprincipale']
    ];

    #
    # TYPE
    #
    $record[] = ['type', convert_type($class, $rec['type'], 'oai')];
    $record[] = ['type', 'info:eu-repo/semantics/' . convert_type($class, $rec['type'], 'openaire')]; # TODO not for qdc ?

    #
    # COVERAGE
    #
    $coverages = get_index($id, 'geographie');
    foreach ($coverages as $coverage) {
        $record[] = ['coverage', $coverage['g_name']];
    }

    #
    # TEMPORAL
    #
    $temporals = get_index($id, 'chrono');
    foreach ($temporals as $temporal) {
        $record[] = ['temporal', $temporal['g_name']];
    }

    #
    # SUBJECTS
    #
    $subjects = get_index($id, 'motscles%');
    foreach ($subjects as $subject) {
        $lang = str_replace('motscles','',$subject['type']);
        $record[] = ['subject', $subject['g_name'], $lang];
    }


    #
    # DESCRIPTION / ABSTRACT
    #

    // For textes use resume, split by lang
    // Else use texte cut at 500 + … and lang of document or site
    if ($class == 'textes') {
        if (!empty($rec['resume'])) {
            // Split resume by lang
            // regexp from lodel/scripts/loops.php:533:function loop_mltext
            preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['resume'], $mltexts, PREG_SET_ORDER);
            foreach ($mltexts as $text) {
                $description = removenotes($text[2]);
                $description = strip_tags($description);
                $description = html_entity_decode($description);
                $description = htmlspecialchars($description);
                $record[] = ['abstract', $description, $text[1]];
            }
        } elseif (!empty($rec['texte'])) {
            // Name this 'description' so formater can know it's not abstract (qdc)
            $texte = removenotes($rec['texte']);
            $texte = strip_tags($texte);
            $texte = html_entity_decode($texte);
            $texte = cuttext($texte, 500) . ' …';
            $texte = htmlspecialchars($texte);
            $record[] = ['description', $texte, $record['language']];
        }

    // For publications use introduction split by lang
    } elseif ($class == 'publications' && !empty($rec['introduction'])) {
        preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['introduction'], $mltexts, PREG_SET_ORDER);
        foreach ($mltexts as $text) {
            $description = removenotes($text[2]);
            $description = strip_tags($description);
            $description = html_entity_decode($description);
            $description = htmlspecialchars($description);
            $record[] = ['abstract', $description, $text[1]];
        }
    }

    #
    # RELATION
    #
    if (!empty($set['issn'])) $record[] = ['issn', $set['issn']];
    if (!empty($set['issn_electronique'])) $record[] = ['eissn', $set['issn_electronique']];

    #
    # ALTERNATIVE
    #
    preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['altertitre'], $mltexts, PREG_SET_ORDER);
    foreach ($mltexts as $text) {
        $altertitre = removenotes($text[2]);
        $altertitre = strip_tags($altertitre);
        $record[] = ['alternative', $altertitre, $text[1]];
    }

    #
    # EXTEND
    #
    if ($class == 'textes' && !empty($rec['pagination'])) {
        $record[] = ['extend', $rec['pagination']];
    }

    #
    # bibliographicalCitation
    #
    if ($class == 'publications' && !empty($rec['numerometas'])) {
        $record[] = ['bibliographicCitation.issue', $rec['numerometas']];
        // TODO bibliographicCitation.volume qdc ?
    }

//     _log_debug($record);
    return $record;
}

/*
Create a record formatted to fit OAI-PMH library function input
Input:
    $record_info (array): "all" information of the record (from `records` table)
    $metadataPrefix (string): how to format the record: oai_dc, qdc or mets
    $full (bool): export all information or only basics
        True for verb getRecord and ListRecords, False for ListIdentifiers)
Output: Associative array
[
    identifier => oai:$oai_id/$id,
    datestamp => '2017-01-17 10:30:02',
    set => [name, name],
    metadata => [
        format_tag_name,
        '',
        [format_attr_name => attr_value, … ],
        [
            [ child_node_name, child_node_value, [attrs… ], [children, …] ],
            …
        ],
    ]
]
*/
function create_record($record_info, $metadataPrefix, $full) {
    $record = [
        'identifier' => oai_identifier($record_info['oai_id'], $record_info['identity']),
        'datestamp' => $record_info['date'],
        'set' => [
            $record_info['set'],
            $record_info['set'] . ':' . $record_info['oai_id']
        ],
    ];
    // add openaire set if relevant
    if ($record_info['openaire'] == 'openAccess') {
        $record['set'][] = 'openaire';
    }

    // return only basic information if asked to
    if (!$full) return $record;

    // Search for all informations about the record (ListRecords and getRecord verbs)
    connect_site('oai-pmh');
    $set = get_set($record_info['site']);
    connect_site($record_info['site']);
    $children = get_record($set, $record_info['class'], $record_info['identity']);

    // Format according to metadataPrefix
    if ($metadataPrefix == 'oai_dc') {
        format_oai_dc($children);
        $container_name = 'oai_dc:dc';
        $container_attributes = [
            'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
            'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
        ];
    } else if ($metadataPrefix == 'qdc') {
        format_oai_qdc($children);
        $container_name = 'qdc:qualifieddc';
        $container_attributes = [
            'xmlns:qdc' => "http://www.bl.uk/namespaces/oai_dcq/",
            'xmlns:dcterms' => 'http://purl.org/dc/terms/',
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:schemaLocation' => 'http://www.bl.uk/namespaces/oai_dcq/ http://www.bl.uk/schemas/qualifieddc/oai_dcq.xsd',
        ];
    }

    $record['metadata'] = [
        $container_name,
        '',
        $container_attributes,
        $children
    ];

    return $record;
}

/*
Format a record using a map
Input:
    $record (array): from get_record() function: [[name, value, lang], …]
    $map (associative array): ['from' => [to, prefix, [attr_name=>attr_value,…]], …]
Output:
    none: $record changed in place with names converted, values prefixed and attributes added
*/
function format_record(&$record, &$map) {
    foreach ($record as &$field) {
        if (!empty($map[$field[0]])) {
            @list($to, $prefix, $attrs) = $map[$field[0]];

            // rename name
            $field[0] = $to;
            // add prefix to value
            if ($prefix) $field[1] = $prefix . $field[1];
            // Set attributes if any
            // If attribute is defined in field, it is a lang attribute
            if (!empty($field[2])) {
                $field[2] = ['xml:lang' => $field[2]];
            } elseif ($attrs) {
                $field[2] = $attrs;
            }
        }
    }
}

/*
Format a record to oai_dc
Input:
    $record (array): from get_record() function
Output:
    none: $record is formatted in place
*/
function format_oai_dc(&$record) {
    # from => [to, prefix]
    $oai_dc_map = [
        'title' => ['dc:title'],
        'identifier_url' => ['dc:identifier'],
        'identifier_doi' => ['dc:identifier'],
        'creator' => ['dc:creator'],
        'contributor' => ['dc:contibutor'],
        'rights' => ['dc:rights'],
        'accessrights' => ['dc:rights'],
        'issued' => ['dc:date'],
        'embargoed' => ['dc:date', 'info:eu-repo/date/embargoEnd/'],
        'publisher' => ['dc:publisher'],
        'language' => ['dc:language'],
        'type' => ['dc:type'],
        'coverage' => ['dc:coverage'],
        'issn' => ['dc:relation', 'info:eu-repo/semantics/reference/issn/'],
        'eissn' => ['dc:relation', 'info:eu-repo/semantics/reference/issn/'],
        'subject' => ['dc:subject'],
        'abstract' => ['dc:description'],
        'description' => ['dc:description'],
    ];

    format_record($record, $oai_dc_map);
}

/*
Format a record to oai_qdc
Input:
    $record (array): from get_record() function
Output:
    none: $record is formatted in place
*/
function format_oai_qdc(&$record) {
    // from => [to, prefix, [attrs]]
    $oai_qdc_map = [
        'title' => ['dcterms:title'],
        'identifier_url' => ['dcterms:identifier', '', ['scheme'=>'URI']],
        'identifier_doi' => ['dcterms:identifier', '', ['scheme'=>'URN']],
        'issn' => ['dcterms:isPartOf', 'urn:issn:', ['scheme'=>'URN']],
        'eissn' => ['dcterms:isPartOf', 'urn:eissn:', ['scheme'=>'URN']],
        'creator' => ['dcterms:creator'],
        'contributor' => ['dcterms:contibutor'],
        'accessrights' => ['dcterms:accessRights'],
        'rights' => ['dcterms:rights'],
        'issued' => ['dcterms:issued', '', ['xsi:type'=>'dcterms:W3CDTF']],
        'embargoed' => ['dcterms:available', '', ['xsi:type'=>'dcterms:W3CDTF']],
        'publisher' => ['dcterms:publisher'],
        'language' => ['dcterms:language', '', ['xsi:type'=>'dcterms:RFC1766']],
        'type' => ['dcterms:type'],
        'extent' => ['dcterms:extent'],
        'spatial' => ['dcterms:spatial'],
        'temporal' => ['dcterms:temporal'],
        'bibliographicalCitation.issue' => ['dcterms:bibliographicalCitation.issue'],
        'alternative' => ['dcterms:alternative'],
        'subject' => ['dcterms:subject'],
        'abstract' => ['dcterms:abstract'],
        'description' => ['dcterms:description'],
    ];
    // TODO ? $oai['dcterms:hasFormat'] = $record['?'];

    format_record($record, $oai_qdc_map);
};
