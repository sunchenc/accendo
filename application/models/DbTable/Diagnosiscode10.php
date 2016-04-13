<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Diagnosiscode10 extends DbTable {

    protected $_name = 'diagnosiscode_10';
    protected $fields = array(////fields populated by the UI
        'id',
        'diagnosis_code',
        'description',
    );

}