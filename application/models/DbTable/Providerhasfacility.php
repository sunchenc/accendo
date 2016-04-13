<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Providerhasfacility extends DbTable {

    protected $_name = 'providerhasfacility';
    protected $fields = array(////fields populated by the UI
        'id',
        'provider_id',
        'facility_id',
        'status',
    );

}

