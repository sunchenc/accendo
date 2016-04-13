<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Diagnosiscode extends DbTable {

    protected $_name = 'diagnosiscode';
    protected $fields = array(////fields populated by the UI
        'id',
        'diagnosis_code',
        'description',
    );

}