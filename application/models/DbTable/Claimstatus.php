<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Claimstatus extends DbTable {

    protected $_name = 'claimstatus';
    protected $fields = array(////fields populated by the UI
        'id',
        'claim_status_display',
        'claim_status',
        'required',
    );

}
?>
