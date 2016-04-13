<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Anesthesiacode extends DbTable {

    protected $_name = 'anesthesiacode';
    protected $fields = array(////fields populated by the UI
        'id',
        'anesthesia_code',
        'description',
        
     
    );

}

