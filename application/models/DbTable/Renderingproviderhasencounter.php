<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Renderingproviderhasencounter extends DbTable {

    protected $_name = 'renderingproviderhasencounter';
    protected $fields = array(////fields populated by the UI
        'id',
        'renderingprovider_id',
        'encounter_id',
    );

}

