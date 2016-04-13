<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Interactionlog extends DbTable {

    protected $_name = 'interactionlog';
    protected $fields = array(////fields populated by the UI
        'id',
        'date_and_time',
        'log',
        'claim_id',
    );

}

