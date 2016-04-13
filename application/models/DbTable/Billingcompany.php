<?php
require_once 'DbTable.php';
class Application_Model_DbTable_Billingcompany extends DbTable {

    protected $_name = 'billingcompany';
    protected $fields = array(////fields populated by the UI
        'billingcompany_name',
        'street_address',
        'city',
        'state',
        'phone_number',
        'fax_number',
        'notes',
        'default_provider',
        'patientdoctypes',
        'calimdoctypes',
        'patientdocsources',
        'claimdocsources'
    );




}

