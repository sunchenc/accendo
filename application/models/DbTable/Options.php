<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Options extends DbTable {

    protected $_name = 'options';
    protected $fields = array(////fields populated by the UI
        'id',
        'anesthesia_unit_rounding',
        'anesthesia_billing_rate_for_non_par',
        'PIP_rate',
        'patient_statement_interval',
        'auto_populate_diagnosis_pointer',
        'signature_on_file_for_all_signatures',
        'use_DOS_for_all_dates',
        'yes_for_assingment_of_benefits',
        'default_end_date_to_start_date',
        'default_patient_relationship_to_insured',
        'default_modifier',
        'default_provider',
        'default_rendering_provider',
        'number_of_days_for_litigation_followup',
        'number_of_days_no_payment_issued',
        'default_facility',
        'provider_invoice_rate',
        'number_of_days_without_activities',
        'number_of_days_no_payment_after_agreed',
        'number_of_days_after_issued_but_not_received',
        'number_of_days_AR_outstanding',
        'number_of_days_for_delayed_bill_generation',
        'number_of_days_for_litigated_followup',
        'number_of_days_bill_has_not_been_generated',
        'close_to_claim_filing_deadline',
        'invoice_delivery_preference',
        'reports_delivery_preference',
        'Optionscol',
        'option_name',
        'default_place_of_service',
        'non_par_expected_pay',
        'anesthesia_billing_rate_for_par',
        'in_network_contract_rates',
        'number_of_days_offered_but_not_agreed',
        'default_pay_rate',
    );

}

