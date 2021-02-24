<?php

/*
Execute an SQL query and returns a statement
Input:
    $q (string): SQL query
    $params (array): bind variables
Ouput:
    $stmt: adodb statement object
*/
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

/*
Execute an SQL query and returns array of result
Input:
    $q (string): SQL query
    $params (array): bind variables
Ouput:
    $array: associative array of results
*/
function sql_get($q, $params=false) {
    $stmt = sql_query($q, $params);
    if (!$stmt) return false;
    // TODO: could use a loop and fetchRow
    // if $column is given, indexed by column
    return $stmt->GetAll();
}

/*
Execute an SQL query and returns first row or value of one field
Input:
    $q (string): SQL query
    $params (array): bind variables
    $value (string): column name of the field we want
Ouput:
    $value: array of row or value of the $value field
    false on error
*/
function sql_getone($q, $params=false, $value=false) {
    $rows = sql_get($q, $params);
    if ($rows) {
        if ($value) return $rows[0][$value];
        return $rows[0];
    }
    return false;
}
