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
    $rec = get_entity($class, $id);
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
    $language = !empty($rec['langue']) ? $rec['langue'] : $set['langueprincipale'];
    $record[] = ['language', $language];

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
		    preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;gt;|&gt;|>)/s", $rec['resume'], $mltexts, PREG_SET_ORDER);
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
            $record[] = ['description', $texte, $language];
        }

    // For publications use introduction split by lang
    } elseif ($class == 'publications' && !empty($rec['introduction'])) {
        preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;gt;|&gt;|>)/s", $rec['introduction'], $mltexts, PREG_SET_ORDER);
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
    preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;gt;|&gt;|>)/s", $rec['altertitre'], $mltexts, PREG_SET_ORDER);
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

    if ($metadataPrefix == 'mets') {
        $children = mets_record($set, $record_info);
        if (!$children) return false;

        $container_name = 'mets:mets';
        $container_attributes = [
            'xmlns:mets' => "http://www.loc.gov/METS/",
            'xmlns:dcterms' => "http://purl.org/dc/terms/",
            'xmlns:xlink' => "http://www.w3.org/1999/xlink",
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:schemaLocation' => "http://www.loc.gov/METS/ http://www.loc.gov/standards/mets/mets.xsd http://www.w3.org/1999/xlink http://www.loc.gov/standards/mets/xlink.xsd http://purl.org/dc/terms/ https://dublincore.org/schemas/xmls/qdc/2006/01/06/dcterms.xsd",
        ];
    } else {
        connect_site($record_info['site']);
        $children = get_record($set, $record_info['class'], $record_info['identity']);
        if (!$children) return false;

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
Create a structure for mets format (recursive)
Input:
    $set (assoc array): row from the `sets` table
    $record_info (assoc array): row from the `records` table
    $mets (array): mets structure (xml tree)
    $map (array): map structure (xml tree)
    $files (array): array of files (xml tree)
    $no_return (bool): control recursion
Output:
    $mets (assoc array): structure of mets record that can be fed to OAI-PMH library
*/
function mets_record($set, $record_info, &$mets=[], &$map=false, &$files=[], $no_return=0) {
    // First call, create root of the map
    if ($map === false) {
        $map = ['mets:structMap', '', [], []];
    }

    # Get the record
    connect_site($record_info['site']);
    $record = get_record($set, $record_info['class'], $record_info['identity']);
    if (!$record) return false;

    # Create dmdSec for this record
    format_oai_qdc($record);
    $dmdid = $record_info['oai_id'] . ':' . $record_info['identity'];
    $mets[] = ['mets:dmdSec', '', ['ID' => $dmdid],
        [
            [
                'mets:mdWrap', '',
                ['MDTYPE'=>'DC', 'LABEL'=>'Dublin Core Descriptive Metadata', 'MIMETYPE'=>'text/xml'],
                [ ['mets:xmlData', '', [], $record] ],
            ]
        ]
    ];

    # Create files
    $files_of_record = get_record_files($set, $record_info);
    $my_file = ['mets:fileGrp', '', ['ID' => 'FG:' . $dmdid], []];
    foreach ($files_of_record as $file) {
        $my_file[3][] = mets_file_structure($file);
    }
    $files[] = &$my_file;

    # Create map
    $label = htmlspecialchars($record_info['title'], ENT_QUOTES, "UTF-8");
    $div_attrs = [
        'LABEL' => $label,
        'DMDID' => $dmdid,
        'ID' => 'M:'.$dmdid,
        'TYPE' => convert_type($record_info['class'], $record_info['type'], 'mets'),
    ];
    if (isset($record_info['order'])) {
        $div_attrs['ORDER'] = $record_info['order'];
    }
    $my_map = ['mets:div', '', $div_attrs, []];
    // add files in the map
    foreach ($files_of_record as $file) {
        $my_map[3][] = ['mets:fptr', '', ['FILEID'=>$file['id']]];
    }

    // Look for children of this record
    if ($record_info['class'] == 'publications') {
        connect_site('oai-pmh');
        $children =get_record_children($record_info['oai_id'], $record_info['identity']);

        // Loop on children, add order information, and construct their maps
        $order = 1;
        foreach ($children as $child_info) {
            $child_info['order'] = $order++;
            mets_record($set, $child_info, $mets, $my_map, $files, 1);
        }
    }

    // Push our map to the map of our parent
    $map[3][] = &$my_map;

    // Only return something at first call
    if ($no_return) return 1;

    array_push(
        $mets,
        ['mets:fileSec', '', [], $files],
        $map
    );

    return $mets;
}

/*
Returns an array of files associated to a record
Input:
    $record_info (assoc array): line from `records` table
    $set (assoc array): line from `set` table
Output:
    $files (array of assoc array):
    [
        [type, url, mimetype, id]
    ]
TODO:
    Use this for create_record
    add user defined function to add some files
*/
function get_record_files($set, $record_info) {
    $files = [];
    // All types have an HTML version of the record
    $files['xhtml'] = [
        'type' => 'xhtml',
        'url' => $set['url'] . '/' . $record_info['identity'],
        'mimetype' => 'text/html',
        'id' => $set['oai_id'] . ':xhtml:' . $record_info['identity'],
    ];

    // Only if not embargoed
    if ($set['openaire_access_level'] != 'embargoedAccess') {

        // Files of class TEXTES
        if ($record_info['class'] == 'textes') {
            // PDF of textes is in alterfichier
            $class = $record_info['class'];
            $pdf = sql_getone(lq("select alterfichier from #_TP_$class where identity=?"),[$record_info['identity']], 'alterfichier');
            if ($pdf) {
                $files['pdf'] = [
                    'type' => 'pdf',
                    'url' => $set['url'] . '/' . $record_info['identity'] . '?file=1',
                    'mimetype' => 'application/pdf',
                    'id' => $set['oai_id'] . ':pdf:' . $record_info['identity'],
                ];
            }

            // TEI is a child entity of type fichierannexe
            $tei = sql_getone(lq("SELECT e.id from #_TP_entities as e, types as t WHERE e.idtype=t.id AND class='fichiers' AND type='fichierannexe' AND identifier='tei' AND idparent=?"), [$record_info['identity']], 'id');
            if ($tei) {
                $files['tei'] = [
                    'type' => 'tei',
                    'url' => $set['url'] . '/' . $tei . '?file=1',
                    'mimetype' => 'text/xml',
                    'id' => $set['oai_id'] . ':tei:' . $record_info['identity'],
                ];
            }

        // Files of class PUBLICATIONS
        } elseif ($record_info['class'] == 'publications') {
            // PDF of publications is a child entity of type facsimile
            $class = $record_info['class'];
            $pdf = sql_getone(lq("SELECT e.id from #_TP_entities as e, types as t WHERE e.idtype=t.id AND class='fichiers' AND type='facsimile' AND idparent=?"), [$record_info['identity']], 'id');
            if ($pdf) {
                $files['pdf'] = [
                    'type' => 'pdf',
                    'url' => $set['url'] . '/' . $pdf . '?file=1',
                    'mimetype' => 'application/pdf',
                    'id' => $set['oai_id'] . ':pdf:' . $record_info['identity'],
                ];
            }
        }
    }

    return $files;
}

/*
Create a structured array describing a file for mets record
Input:
    $file (assoc array): with type, url, mimetype and id
Output:
    $struc (assoc array): xml tree structure of a file for mets record
TODO:
    can add DMDID attribute to mets:file
*/
function mets_file_structure($file) {
    return [
        'mets:file', '', ['ID' => $file['id'], 'MIMETYPE' => $file['mimetype']],
        [
            ['mets:FLocat', '', ['LOCTYPE'=>'URL', 'xlink:href' => $file['url']]],
        ],
    ];
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
            }
            // Add attributes
            if ($attrs) {
                foreach ($attrs as $k => $v) {
                    $field[2][$k] = $v;
                }
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
        'subject' => ['dcterms:subject', '', ['scheme'=>'keywords']],
        'abstract' => ['dcterms:abstract'],
        'description' => ['dcterms:description'],
    ];
    // TODO ? $oai['dcterms:hasFormat'] = $record['?'];

    format_record($record, $oai_qdc_map);
};
