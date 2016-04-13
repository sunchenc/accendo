<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Innetworkpayers extends DbTable {

    protected $_name = 'innetworkpayers';
    protected $fields = array(////fields populated by the UI
        'id',
        'insurance_id',
        'renderingprovider_id',
    );

}

