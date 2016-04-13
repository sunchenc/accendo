<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Patientcorrespondence extends DbTable {

    protected $_name = 'patientcorrespondence';
    protected $fields = array(////fields populated by the UI
        'id',
        'template',
        'variables',
        'billingcompany_id',

    );

}

