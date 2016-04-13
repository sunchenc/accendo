<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Insured extends DbTable {

    protected $_name = 'insured';
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
        'ID_number',
        'policy_group_or_FECA_number',
        'employer_or_school_name',
        'plan_or_program_name',
        'is_there_another_plan',
        'notes',
        'other_insured_last_name',
        'other_insured_first_name',
        'other_insured_policy_or_group_number',
        'other_insured_DOB',
        'other_insured_sex',
        'other_insured_employer_name',
        'other_insurance_name_or_program_name',
        'relationship_to_patient',
        'insurance_id',
        'other_insurance_id',
        'insured_insurance_type',
        'insured_insurance_expiration_date', 
    );

}

