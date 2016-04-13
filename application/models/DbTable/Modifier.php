<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Modifier extends DbTable {

    protected $_name = 'modifier';
    protected $fields = array(////fields populated by the UI
        'id',
        'modifier',
        'description',
    );

}

