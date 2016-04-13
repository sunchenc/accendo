<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Providerhasreferringprovider extends DbTable {

    protected $_name = 'providerhasreferringprovider';
    protected $fields = array(////fields populated by the UI
        'id',
        'referringprovider_id',
        'provider_id',
        'status',
    );

}

