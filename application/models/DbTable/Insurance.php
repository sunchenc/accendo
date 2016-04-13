<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Insurance extends DbTable {

    protected $_name = 'insurance';
    protected $fields = array(////fields populated by the UI
        'id',
        'insurance_name',
        'insurance_display',
        'street_address',
        'city',
        'state',
        'zip',
        'phone_number',
        'phone_extension_for_claims',
        'phone_extension_for_benefits',
        'second_phone_number',
        'fax_number',
        'EDI_number',
        'claim_submission_preference',
        'payer_type',
        /*************Add insurance_type By Yu Lang********/
        'insurance_type',
        /*************Add insurance_type By Yu Lang********/
        'notes',
        'anesthesia_bill_rate',
        'anesthesia_crosswalk_overwrite',
        'claim_filing_deadline',
        'EFT',
        'reconsideration',
        'appeal',
        'benefit_lookup',
        'claim_status_lookup',
        'navinet_web_support_number',
        'PID_interpretation',
    );

}

