<?php

# Role:
#   Get a full record
# Input:
#   $set: complete set of the site
#   $class: class of the record
#   $id: id entity of the record
# MUST be connected to $set['name']
# TODO: ideally should not query DB
function get_record($set, $class, $id) {
    # Get lodel record for this entity
    $rec = sql_getone(lq("SELECT c.*, t.type FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE e.idtype = t.id AND c.identity = e.id AND identity=?;"), [$id]);
    if (!$rec) return false;

    # Our big array with all info about the record
    $record = array();

    #
    # TITLE
    #
    $title = $rec['titre'];
    $title = removenotes($title);
    $title = strip_tags($title);
    $record['title'] = $title;

    #
    # CREATOR
    #
    if ($class == 'textes') {
        $record['creator'] = get_persons($id, 'auteur');
    }
    # Pour les types publication, il faut les personnes de tous les enfants
    if ($class == 'publications') {
        $record['creator'] = get_persons($id, 'auteur');
        $children = get_children($id);
        foreach ($children as $child) {
            $record['creator'] = array_merge(get_persons($child, 'auteur'), $record['creator']);
        }
        $record['creator'] = array_unique($record['creator'], SORT_STRING);
    }

    #
    # CONTRIBUTOR
    #
    if ($class == 'publications') {
        $record['contributor'] = get_persons($id, 'directeurdelapublication');
    }

    #
    # RIGHTS
    #
    $record['rights'] = $set['droitsauteur'];
    $record['accessrights'] = 'info:eu-repo/semantics/'.$set['openaire_access_level'];

    #
    # DATE
    #
    $record['issued'] = $rec['datepubli'];
    if ($set['openaire_access_level'] == 'embargoedAccess') {
        $record['embargoed'] = $rec['datepubli'];
    }

    #
    # PUBLISHER
    #
    $record['publisher'][] = $set['editeur'];
    $record['publisher'][] = $set['titresite'];

    #
    # IDENTIFIER
    #
    $record['identifier_url'] = $set['url'] . $id;
    $record['identifier_doi'] = 'urn:doi:' . $set['doi_prefixe'] . $id;

    #
    # LANGUAGE
    #
    $record['language'] = !empty($rec['langue']) ? $rec['langue'] : $set['langueprincipale'];

    #
    # TYPE
    #
    $record['type'][] = convert_type($class, $rec['type'], 'oai');
    $record['type'][] = 'info:eu-repo/semantics/' . convert_type($class, $rec['type'], 'openaire'); # TODO not for qdc ?

    #
    # COVERAGE
    #
    $coverages = get_index($id, 'geographie');
    foreach ($coverages as $coverage) {
        $record['coverage'][] = $coverage['g_name'];
    }

    #
    # TEMPORAL
    #
    $temporals = get_index($id, 'chrono');
    foreach ($temporals as $temporal) {
        $record['temporal'][] = $temporal['g_name'];
    }

    #
    # SUBJECTS
    #
    $subjects = get_index($id, 'motscles%');
    foreach ($subjects as $subject) {
        $lang = str_replace('motscles','',$subject['type']);
        $record['subjects'][] = [$subject['g_name'], $lang];
    }


    #
    # DESCRIPTION / ABSTRACT
    #

    # For textes use resume, split by lang
    # Else use texte cut at 500 + … and lang of document or site
    if ($class == 'textes') {
        if (!empty($rec['resume'])) {
            # regexp from lodel/scripts/loops.php:533:function loop_mltext
            preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['resume'], $mltexts, PREG_SET_ORDER);
            foreach ($mltexts as $text) {
                $description = removenotes($text[2]);
                $description = strip_tags($description);
                $description = html_entity_decode($description);
                $record['abstract'][] = [$description, $text[1]];
            }
        } elseif (!empty($rec['texte'])) {
            # Name it description so formater can know it's not abstract (qdc)
            $texte = removenotes($rec['texte']);
            $texte = strip_tags($texte);
            $texte = html_entity_decode($texte);
            $texte = cuttext($texte, 500) . ' …';
            $record['description'][] = [$texte, $record['language']];
        }

    # For publications use introduction split by lang
    } elseif ($class == 'publications' && !empty($rec['introduction'])) {
        preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['introduction'], $mltexts, PREG_SET_ORDER);
            foreach ($mltexts as $text) {
                $description = removenotes($text[2]);
                $description = strip_tags($description);
                $description = html_entity_decode($description);
                $record['abstract'][] = [$description, $text[1]];
            }
    }

    #
    # RELATION
    #
    if (!empty($set['issn'])) $record['issn'] = $set['issn'];
    if (!empty($set['issn_electronique'])) $record['eissn'] = $set['issn_electronique'];

    #
    # ALTERNATIVE
    #
    preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['altertitre'], $mltexts, PREG_SET_ORDER);
    foreach ($mltexts as $text) {
        $altertitre = removenotes($text[2]);
        $altertitre = strip_tags($altertitre);
        $record['alternative'][] = [$altertitre, $text[1]];
    }

    #
    # EXTEND
    #
    if ($class == 'textes' && !empty($rec['pagination'])) {
        $record['extend'] = $rec['pagination'];
    }

    #
    # bibliographicalCitation
    #
    if ($class == 'publications' && !empty($rec['numerometas'])) {
        $record['bibliographicCitation.issue'] = $rec['numerometas'];
        # TODO bibliographicCitation.volume qdc ?
    }

//     _log_debug($record);
    return $record;
}

# Create an OAI record
# TODO: ideally should not query DB
function create_record($record_info, $metadataPrefix, $full) {
    $record = [
        'identifier' => oai_identifier($record_info['oai_id'], $record_info['identity']),
        'datestamp' => $record_info['date'],
        'set' => [
            $record_info['set'],
            $record_info['set'] . ':' . $record_info['oai_id']
        ],
    ];
    # add openaire set
    if ($record_info['openaire'] == 'openAccess') {
        $record['set'][] = 'openaire';
    }

    if (!$full) return $record;

    # Only search for all informations if ListRecords
    connect_site('oai-pmh');
    $set = get_set($record_info['site']);

    connect_site($record_info['site']);
    $record_raw = get_record($set, $record_info['class'], $record_info['identity']);

    if ($metadataPrefix == 'oai_dc') {
        $record_formated = format_oai_dc($record_raw);
        $container_name = 'oai_dc:dc';
        $container_attributes = [
            'xmlns:oai_dc' => "http://www.openarchives.org/OAI/2.0/oai_dc/",
            'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd'
        ];
    } else if ($metadataPrefix == 'qdc') {
        $record_formated = format_oai_qdc($record_raw);
        $container_name = 'qdc:qualifieddc';
        $container_attributes = [
            'xmlns:qdc' => "http://www.bl.uk/namespaces/oai_dcq/",
            'xmlns:dcterms' => 'http://purl.org/dc/terms/',
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:schemaLocation' => 'http://www.bl.uk/namespaces/oai_dcq/ http://www.bl.uk/schemas/qualifieddc/oai_dcq.xsd',
        ];
    }

    $record['metadata'] = [
        'container_name' => $container_name,
        'container_attributes' => $container_attributes,
        'fields' => $record_formated
    ];

    return $record;
}

function format_oai_dc($record) {
    # [ [from, to, prefix ]
    $convert = [
        ['title', 'dc:title', ''],
        ['identifier_url', 'dc:identifier', ''],
        ['identifier_doi', 'dc:identifier', ''],
        ['creator', 'dc:creator', ''],
        ['contributor', 'dc:contibutor', ''],
        ['rights', 'dc:rights', ''],
        ['accessrights', 'dc:rights', ''],
        ['issued', 'dc:date', ''],
        ['embargoed', 'dc:date', 'info:eu-repo/date/embargoEnd/'],
        ['publisher', 'dc:publisher', ''],
        ['language', 'dc:language', ''],
        ['type', 'dc:type', ''],
        ['coverage', 'dc:coverage', ''],
        ['issn', 'dc:relation', 'info:eu-repo/semantics/reference/issn/'],
        ['eissn', 'dc:relation', 'info:eu-repo/semantics/reference/issn/'],
    ];
    foreach ($convert as $conv) {
        list ($from, $to, $prefix) = $conv;
        if (!empty($record[$from])) {
            if (is_array($record[$from])) {
                $oai[$to] = $record[$from];
            } else {
                $oai[$to][] = $prefix . $record[$from];
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
        ['title', 'dcterms:title', False, ''],
        ['identifier_url', 'dcterms:identifier', ['scheme'=>'URI'], ''],
        ['identifier_doi', 'dcterms:identifier', ['scheme'=>'URN'], ''],
        ['issn', 'dcterms:isPartOf', ['scheme'=>'URN'], 'urn:issn:'],
        ['eissn', 'dcterms:isPartOf', ['scheme'=>'URN'], 'urn:eissn:'],
        ['creator', 'dcterms:creator', False, ''],
        ['contributor', 'dcterms:contibutor', False, ''],
        ['accessrights', 'dcterms:accessRights', False, ''],
        ['rights', 'dcterms:rights', False, ''],
        ['issued', 'dcterms:issued', ['xsi:type'=>'dcterms:W3CDTF'], ''],
        ['embargoed', 'dcterms:available', ['xsi:type'=>'dcterms:W3CDTF'], ''],
        ['publisher', 'dcterms:publisher', False, ''],
        ['language', 'dcterms:language', ['xsi:type'=>'dcterms:RFC1766'], ''],
        ['type', 'dcterms:type', False, ''],
        ['extent', 'dcterms:extent', False, ''],
        ['spatial', 'dcterms:spatial', False, ''],
        ['temporal', 'dcterms:temporal', False, ''],
        ['bibliographicalCitation.issue', 'dcterms:bibliographicalCitation.issue', False, ''],
    ];
    foreach ($convert as $conv) {
        list ($from, $to, $attrs, $prefix) = $conv;
        if (!empty($record[$from])) {
            if (is_array($record[$from])) {
                $oai[$to] = $record[$from];
            } else {
                if ($attrs) {
                    $oai[$to][] = [$prefix . $record[$from], $attrs];
                } else {
                    $oai[$to][] = $prefix . $record[$from];
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
