<?php

function mysql_insert($table, $value) {
    unset($value['diff']);
    $db = Zend_Registry::get('dbAdapter');
    $rows_affected = $db->insert($table, $value);
    $last_insert_id = $db->lastInsertId();
    return $last_insert_id;
}

function mysql_update_by_id($table, $set, $id) {
    unset($set['diff']);
    $db = Zend_Registry::get('dbAdapter');
    $where = $db->quoteInto('id = ?', $id);
    $rows_affected = $db->update($table, $set, $where);
    return $rows_affected;
}
function statement_insert($table,$value) {
    $db = Zend_Registry::get('dbAdapter');
    $db->insert($table, $value);
}
function get_service_doc_pages($facility_id) {
    $pages = array();
    $db_facility = new Application_Model_DbTable_Facility();
    $facility_row = $db_facility->fetchRow('id=' . $facility_id);
    if ($facility_row->service_doc_first_page)
        array_push($pages, $facility_row->service_doc_first_page);
    if ($facility_row->service_doc_second_page)
        array_push($pages, $facility_row->service_doc_second_page);
    if ($facility_row->service_doc_third_page)
        array_push($pages, $facility_row->service_doc_third_page);
    if ($facility_row->service_doc_forth_page)
        array_push($pages, $facility_row->service_doc_forth_page);
    return $pages;
}

?>
