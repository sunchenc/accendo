<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Cptcode extends DbTable {

    protected $_name = 'cptcode';
    protected $fields = array(////fields populated by the UI
        'id',
        'CPT_code',
        'description',
        'anesthesiacode_id',
    );

}

