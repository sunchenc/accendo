<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Patientinsured extends DbTable {

    protected $_name = 'patientinsured';
    protected $fields = array(////fields populated by the UI
        'id',
        'patient_id',
        'insured_id',       
    );
}

