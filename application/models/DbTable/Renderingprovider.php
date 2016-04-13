<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Renderingprovider extends DbTable {

    protected $_name = 'renderingprovider';
    protected $fields = array(////fields populated by the UI
        'id',
        'last_name',
        'first_name',
        'street_address',
        'city',
        'state',
        'zip',
        'phone_number',
        'secondary_phone_number',
        'fax_number',
        'NPI',
        'file_path_to_medical_license',
        'notes',
    );

}

