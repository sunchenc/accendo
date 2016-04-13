<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Followups extends DbTable {

    protected $_name = 'followups';
    protected $fields = array(////fields populated by the UI
        'id',
        'amount_paid',
        'date_and_time_1',
        'who_number_and_ref_number_1',
        'log_1',
        'date_and_time_2',
        'who_number_and_ref_number_2',
        'log_2',
        'date_and_time_3',
        'who_number_and_ref_number_3',
        'log_3',
        'date_and_time_4',
        'who_number_and_ref_number_4',
        'log_4',
        'data_and_time_5',
        'who_number_and_ref_number_5',
        'log_5',
        'data_and_time_6',
        'who_number_and_ref_number_6',
        'log_6',
        'data_and_time_7',
        'who_number_and_ref_number_7',
        'log_7',
        'data_and_time_8',
        'who_number_and_ref_number_8',
        'log_8',
        'date_of_next_followup',
        'notes',
        'claim_is_inactive',
        'date_initial_offer',
        'amount_initial_offer',
        'delivery_date',
        'negotiated_payment_amount',
        'date_negotiated_amount_reached',
        'claim_id',
        'file_path_to_negotiated_agreement',
    );

}

