<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Claim extends DbTable {
 /**********Date secondary billed By<Yu Lang>**************/
    protected $_name = 'claim';
    protected $fields = array(////fields populated by the UI
        'id',
        'total_charge',
        'expected_payment',
        'date_billed',
        'date_last_billed',
        'date_secondary_insurance_billed', 
        'date_closed',
        'amount_insurance_payment_issued',
        'date_insurance_payment_issued',
        'is_the_issued_payment_ok',
        'is_the_issued_payment_sent_to_patient',
        'amount_insurance_payment_received',
        'date_insurance_payment_received',
        'EOB_co_insurance',
        'EOB_deductable',
        /*****add two field*******/
         'EOB_reduction',
         'EOB_other_reduction',
         /*****add two field*******/
        'EOB_allowed_amount',
        'EOB_not_allowed_amount',
        'EOB_adjustment_reason',
        'amount_paid',
        'balance_due',
        /*****add four custom field*******/
        'custom_1',
        'custom_2',
        'custom_3',
        'custom_4',
        /*****add four custom field*******/
        'date_of_first_patient_statement',
        'date_of_second_patient_statement',
        'date_of_third_patient_statement',
        'notes',
        'benefit_OOP',
        'benefit_OOP_remaining',
        'benefit_deductible',
        'benefit_deductible_remaining',
        'benefit_co_insurance',
        'benefit_date_taken',
        'claim_status',
        'date_creation',
        'date_statementII',
        'statement',
        'date_last_checked_with_attorney',
        'file_path_to_EOB_image',
        'file_path_to_check_image',
        
        'color_code'
    );

}

