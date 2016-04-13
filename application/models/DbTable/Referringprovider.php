<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Referringprovider extends DbTable {

    protected $_name = 'referringprovider';
    protected $fields = array(////fields populated by the UI
        'id',
        'last_name',
        'first_name',
        'NPI',
    );

}

