<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Providerbilling extends DbTable {

    protected $_name = 'providerbilling';
    protected $fields = array(////fields populated by the UI
        'id',
        'provider_id',
        'date_billed',
    );

}

