<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Billingcompanyclaimstatus extends DbTable {

    protected $_name = 'billingcompanyclaimstatus';
    protected $fields = array(////fields populated by the UI
        'id',
        'billingcompany_id',
        'claimstatus_id',
    );

}
?>
