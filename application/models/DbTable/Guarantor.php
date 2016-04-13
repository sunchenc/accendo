<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Guarantor extends DbTable {

    protected $_name = 'guarantor';
    protected $fields = array(////fields populated by the UI
        'id',
        'last_name',
        'first_name',
        'DOB',
        'street_address',
        'city',
        'state',
        'zip',
        'phone_number',
        'second_phone_number',
        'sex',
        'SSN',
        'relationship_to_patient',
        
     
    );

}