<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Placeofservice extends DbTable {

    protected $_name = 'placeofservice';
    protected $fields = array(////fields populated by the UI
        'id',
        'pos',
        'description',
    );

}

