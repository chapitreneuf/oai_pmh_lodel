<?php

# Role:
#   Query and return a statement
function sql_query($q, $params=false) {
    global $db;

    # TODO make sure to setFetchMode(ADODB_FETCH_ASSOC)
    $stmt = $db->execute($q, $params);
    $err = $db->errorMsg();

    if ($err) {
        _log("Error with query $q");
        _log_debug($params, 0);
        _log_debug($err, 0);
        _log_debug($stmt, 0);
        return false;
    }

    return $stmt;
}

# Role:
#   Query and return array of results
function sql_get($q, $params=false) {
    $stmt = sql_query($q, $params);
    if (!$stmt) return false;
    # TODO use a loop and fetchRow
    # if $column is given, indexed by column
    return $stmt->GetAll();
}

# Role:
#   Query and return first row, or value of first row if given
function sql_getone($q, $params=false, $value=false) {
    $rows = sql_get($q, $params);
    if ($rows) {
        if ($value) return $rows[0][$value];
        return $rows[0];
    }
    return false;
}
