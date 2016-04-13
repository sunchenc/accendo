<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Provider extends DbTable {

    protected $_name = 'provider';
    protected $fields = array(////fields populated by the UI
        'id',
        'provider_name',
        'street_address',
        'city',
        'state',
        'zip',
        'phone_number',
        'secondary_phone_number',
        'fax_number',
        'email_address',
        'tax_ID_number',
        'notes',
        'file_path_to_W9',
        'billing_street_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_phone_number',
        'billingcompany_id',
        'billing_fax',
        'options_id',
    );

}

