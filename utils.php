<?php

# MUST be connected to oai-pmh
function get_sets ($limit=10, $offset=0, $order='id') {
    $q = "SELECT `set`, `oai_id`, `name`, `title` FROM `sets` ORDER BY `$order`";
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $sets = sql_get($q.';');

    return $sets;
}

# Role:
#   Get a single set info
# MUST be connected to oai-pmh
function get_set($site) {
    return sql_getone("SELECT * FROM `sets` WHERE `name` = ?;", [$site]);
}

# Role:
#   Get a list of records from class and type
#   With information to fill `records` table
function get_records_simple($class, $type, $limit=10, $offset=0, $order='identity') {
    $q = lq("SELECT `identity`, `titre`, `datemisenligne`, `dateacceslibre`, `modificationdate` FROM #_TP_$class c, #_TP_entities e, #_TP_types t WHERE c.identity = e.id AND e.idtype = t.id AND t.type = '$type' AND e.status>0 ORDER BY `$order`");
    if ($limit) $q .=  " LIMIT $offset,$limit";
    $records = sql_get($q.';');

    return $records;
}

# Role:
#   Get a full record
# Input:
#   $set: complete set of the site
#   $class: class of the record
#   $id: id entity of the record
# MUST be connected to $set['name']
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

    $record['rights'][] = $set['droitsauteur'];
    $record['rights'][] = 'info:eu-repo/semantics/'.$set['openaire_access_level'];

    #
    # DATE
    #
    $record['date'][] = $rec['datepubli'];
    if ($set['openaire_access_level'] == 'embargoedAccess') {
        $record['date'][] = 'info:eu-repo/date/embargoEnd/' . $rec['datepubli'];
    }

    #
    # PUBLISHER
    #
    $record['publisher'][] = $set['editeur'];
    $record['publisher'][] = $set['titresite'];

    #
    # IDENTIFIER
    #
    $record['identifier'][] = $set['url'] . $id;
    $record['identifier'][] = 'urn:doi:' . $set['doi_prefixe'] . $id;

    #
    # LANGUAGE
    #
    $record['language'] = $rec['langue'] ? $rec['langue'] : $set['langueprincipale'];

    #
    # TYPE
    #
    # TODO faire la liaison avec la table type pour l'avoir dans le record
    $record['type'][] = convert_type($class, $rec['type'], 'oai');
    $record['type'][] = 'info:eu-repo/semantics/' . convert_type($class, $rec['type'], 'openaire');

    #
    # COVERAGE
    #
    $coverages = get_index($id, 'geographie');
    foreach ($coverages as $coverage) {
        $record['coverage'][] = $coverage['g_name'];
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
    # DESCRIPTION
    #

    # For textes use resume, split by lang
    # Ese use texte cut at 500 + … and lang of document or site
    if ($class == 'textes') {
        if ($rec['resume']) {
            # regexp from lodel/scripts/loops.php:533:function loop_mltext
            preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['resume'], $mltexts, PREG_SET_ORDER);
            foreach ($mltexts as $text) {
                $description = removenotes($text[2]);
                $description = strip_tags($description);
                $record['description'][] = [$description, $text[1]];
            }
        } else {
            $texte = removenotes($rec['texte']);
            $texte = strip_tags($texte);
            $texte = cuttext($texte, 500) . ' …';
            $record['description'][] = [$texte, $record['language']];
        }

    # For publications use introduction split by lang
    } elseif ($class == 'publications' && $rec['introduction']) {
        preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['introduction'], $mltexts, PREG_SET_ORDER);
            foreach ($mltexts as $text) {
                $description = removenotes($text[2]);
                $description = strip_tags($description);
                $record['description'][] = [$description, $text[1]];
            }
    }

    #
    # RELATION
    #
    if ($set['issn']) $record['relation'][] = 'info:eu-repo/semantics/reference/issn/' . $set['issn'];
    if ($set['issn_electronique']) $record['relation'][] = 'info:eu-repo/semantics/reference/issn/' . $set['issn_electronique'];

    #
    # ALTERNATIVE
    #
    preg_match_all("/(?:&amp;lt;|&lt;|<)r2r:ml lang\s*=(?:&amp;quot;|&quot;|\")(\w+)(?:&amp;quot;|&quot;|\")(?:&amp;gt;|&gt;|>)(.*?)(?:&amp;lt;|&lt;|<)\/r2r:ml(?:&amp;    546 gt;|&gt;|>)/s", $rec['altertitre'], $mltexts, PREG_SET_ORDER);
    foreach ($mltexts as $text) {
        $altertitre = removenotes($text[2]);
        $altertitre = strip_tags($altertitre);
        $record['alternative'][] = [$altertitre, $text[1]];
    }

    _log_debug($record);
    return $record;
}

function get_persons($id, $type) {
    $pers = sql_get(
        lq("SELECT g_firstname,g_familyname FROM #_TP_relations r, #_TP_persons p, #_TP_persontypes pt WHERE r.id1=? AND r.id2=p.id AND nature='G' AND p.idtype=pt.id AND type=? ORDER BY `degree`;"),
        [$id, $type]
    );
    $persons = array();
    foreach ($pers as $p) {
        $persons[] = $p['g_familyname'] . ', ' . $p['g_firstname'];
    }

    return $persons;
}

# Role:
#   get ids of all children (recursive)
function get_children($id) {
    $ids = array();

    $children = sql_get(lq("SELECT id FROM #_TP_entities WHERE idparent=?;"), [$id]);
    foreach ($children as $i) {
        $ids[] = $i['id'];
        $ids = array_merge(get_children($i['id']), $ids);
    }

    return $ids;
}

# Role:
#   get indexes of a type attach to an entity
#   if type contains a % will search with SQL LIKE
# Input:
#   $id: id of entity
#   $type: type of index
function get_index($id, $type) {
    $sql_type = strpos($type, '%') === false ? 't.`type` = ?' : 't.`type` LIKE ?';
    $entries = sql_get(lq("SELECT e.g_name, t.type FROM #_TP_relations r, #_TP_entries e, #_TP_entrytypes t WHERE t.class='indexes' AND r.id1=? AND r.id2=e.id AND t.id=e.idtype AND nature='E' AND $sql_type ORDER BY t.`rank`, r.`degree`;"), [$id, $type]);
    return $entries;
}

# Role:
#   return oai or openaire type of a publication
# Input:
#   $class: lodel class of the publication
#   $type: lodel type of the publication
#   $set: oai or openaire
# Output:
#   $type: string of type of publication
function convert_type($class, $type, $set='oai') {
    $types = get_publication_types();
    return get_publication_types()[$class][$type][$set];
}

# TODO: this should be a config (or at least extendable)
function get_publication_types() {
    return [
        'publications' => [
            'numero' => ['oai'=>'issue', 'openaire'=> 'other'],
            'souspartie' => ['oai'=>'part', 'openaire'=> 'other'],
        ],
        'textes' => [
            'article' => ['oai'=>'article', 'openaire'=> 'article'],
            'chronique' => ['oai'=>'article', 'openaire'=> 'other'],
            'compterendu' => ['oai'=>'review', 'openaire'=> 'review'],
            'notedelecture' => ['oai'=>'review', 'openaire'=> 'review'],
            'editorial' => ['oai'=>'introduction', 'openaire'=> 'article'],
        ],
    ];
}