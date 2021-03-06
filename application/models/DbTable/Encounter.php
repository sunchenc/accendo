<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Encounter extends DbTable {

    protected $_name = 'encounter';
    protected $fields = array(////fields populated by the UI
  'id',
  'date_of_current_illness_or_injury',
  'date_same_illness',
  'not_able_to_work_from_date',
  'not_able_to_work_to_date',
  'hospitalization_from_date',
  'hospitalization_to_date',
  'outside_lab',
  'charge',
  'diagnosis_code1',
  'diagnosis_code2',
  'diagnosis_code3',
  'diagnosis_code4',
  'comment_1',
  'start_date_1',
  'end_date_1',
  'start_time_1',
  'end_time_1',
  'place_of_service_1',
  'EMG_1',   
  'EPSDT_1',
  'CPT_code_1',
  'secondary_CPT_code_1',
  'modifier1_1',
  'modifier2_1',
  'modifier3_1',
  'modifier4_1',
  'diagnosis_pointer_1',
  'charges_1',
  'days_or_units_1',
  'family_1',
  'comment_2',
  'start_date_2',
  'end_date_2',
  'start_time_2',
  'end_time_2',
  'place_of_service_2',
  'EMG_2',
  'EPSDT_2',
  'CPT_code_2',
  'secondary_CPT_code_2',
  'modifier1_2',
  'modifier2_2',
  'modifier3_2',
  'modifier4_2',
  'diagnosis_pointer_2',
  'charges_2',
  'days_or_units_2',
  'family_2',
  'comment_3',
  'start_date_3',
  'end_date_3',
  'start_time_3',
  'end_time_3',
  'place_of_service_3',
  'EMG_3',
  'EPSDT_3',
  'CPT_code_3',
  'secondary_CPT_code_3',
  'modifier1_3',
  'modifier2_3',
  'modifier3_3',
  'modifier4_3',
  'diagnosis_pointer_3',
  'charges_3',
  'days_or_units_3',
  'family_3',
  'comment_4',
  'start_date_4',
  'end_date_4',
  'start_time_4',
  'end_time_4',
  'place_of_service_4',
  'EMG_4',
  'EPSDT_4',
  'CPT_code_4',
  'secondary_CPT_code_4',
  'modifier1_4',
  'modifier2_4',
  'modifier3_4',
  'modifier4_4',
  'diagnosis_pointer_4',
  'charges_4',
  'days_or_units_4',
  'family_4',
  'comment_5',
  'start_date_5',
  'end_date_5' ,
  'start_time_5',
  'end_time_5',
  'place_of_service_5',
  'EMG_5',
   'EPSDT_5',
  'CPT_code_5',
  'secondary_CPT_code_5',
  'modifier1_5',
  'modifier2_5',
  'modifier3_5',
  'modifier4_5',
  'diagnosis_pointer_5',
  'charges_5',
  'days_or_units_5',
  'family_5',
  'comment_6',
  'start_date_6',
  'end_date_6',
  'start_time_6',
  'end_time_6',
  'place_of_service_6',
  'EMG_6',
  'EPSDT_6',
  'CPT_code_6',
  'secondary_CPT_code_6',
  'modifier1_6',
  'modifier2_6',
  'modifier3_6',
  'modifier4_6',
  'diagnosis_pointer_6',
  'charges_6',
  'days_or_units_6',
  'family_6',
  'notes',
  'patient_signature',
  'patient_signature_date',
  'insured_signature',
  'rendering_provider_signature_date',
  'accept_assignment',
  'referringprovider_id',
  'patient_id',
  'facility_id',
  'provider_id',
  'renderingprovider_id',
   'renderingprovider_id_1',
   'renderingprovider_id_2',
   'renderingprovider_id_3',
    'renderingprovider_id_4',
    'renderingprovider_id_5',
    'renderingprovider_id_6',
  'claim_id',
  'file_path_service_sheet',
  'file_path_anesthesia_time_sheet_image',
  'bill_by_1',
        'bill_by_2',
        'bill_by_3',
        'bill_by_4',
        'bill_by_5',
        'bill_by_6',
    );

}

