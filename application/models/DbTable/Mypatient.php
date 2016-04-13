<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Mypatient extends DbTable {

    protected $_name = 'mypatient';
    protected $fields = array(////fields populated by the UI
        'id',
        'mypatient_no',
        'provider_id',
        'patient_id',
    );

}

