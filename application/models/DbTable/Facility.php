<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Facility extends DbTable {

    protected $_name = 'facility';
    protected $fields = array(////fields populated by the UI
        'id',
        'facility_name',
        'street_address',
        'city',
        'state',
        'zip',
        'phone_number',
        'fax_number',
        'notes',
    );

}

