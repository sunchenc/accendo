<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Statement extends DbTable {

    protected $_name = 'statement';
    protected $fields = array(////fields populated by the UI
        'id',
        'statement_type',
        'date',
        'trigger',
        'remark',
        'next_statement',
        'encounter_id',
    );

}

