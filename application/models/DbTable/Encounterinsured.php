<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'DbTable.php';

class Application_Model_DbTable_Encounterinsured extends DbTable {

    protected $_name = 'encounterinsured';
    protected $fields = array(////fields populated by the UI
        'id',
        'encounter_id',
        'insured_id',
        'type'
    );
}
?>
