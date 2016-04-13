<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Providerhasrenderingprovider extends DbTable {

    protected $_name = 'providerhasrenderingprovider';
    protected $fields = array(////fields populated by the UI
        'id',
        'renderingprovider_id',
        'provider_id',
    );

}

