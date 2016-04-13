<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Contractrates extends DbTable {

    protected $_name = 'contractrates';
    protected $fields = array(////fields populated by the UI
        'id',
        'insurance_id',
        'provider_id',
        'rates',
    );

}

