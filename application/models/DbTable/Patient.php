<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Patient extends DbTable {

    protected $_name = 'patient';
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
        'relationship_to_insured',
        'status',
        'condition_related_to',
        'insurance_card_image',
        'notes',
        'account_number',
        /*Add the alert column*/
        'alert',
        
    );

}

