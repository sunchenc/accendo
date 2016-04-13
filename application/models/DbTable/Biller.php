<?php
require_once 'DbTable.php';
class Application_Model_DbTable_Biller extends DbTable
{

    protected $_name = 'biller';
        protected $fields = array(////fields populated by the UI
        'biller_name',
        'notes',
    );



}

