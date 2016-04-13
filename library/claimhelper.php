<?php

function get_fields($table) {
    $fields = array();
    $tmp = array();
    switch ($table) {
        case 'patient':
            $tmp = get_ui2patient();
            break;
        case 'statement':
            $tmp = get_ui2statement();
            break;
        case 'insured':
            $tmp = get_ui2insured();
            break;
        case 'insurance':
            $tmp = get_ui2insurance();
            break;
        case 'claim':
            $tmp = get_ui2claim();
            break;
        case 'followups':
            $tmp = get_ui2followup();
            break;
        case 'encounter':
            $tmp = get_ui2encounter();
            break;
        default:
            break;
    }
    foreach ($tmp as $key => $val) {
        array_push($fields, $key);
    }
    return $fields;
}

function session2DB($data, $table) {
    $tmp = array();
    switch ($table) {
        case 'patient':
            $tmp = session2DB_patient($data);
            break;
        case 'statement':
            $tmp = session2DB_statement($data);
            break;
        case 'insured':
            $tmp = session2DB_insured($data);
            break;
        case 'encounter':
            $tmp = session2DB_encounter($data);
            break;
        case 'claim':
            $tmp = session2DB_claim($data);
            break;
        case 'followups':
            $tmp = session2DB_followups($data);
            break;
        case 'insurance':
            $tmp = $data;
            break;
        case 'insurancepayments':
            $tmp = session2DB_payments($data);
            break;
        case 'patientpayments':
            $tmp = session2DB_payments($data);
            break;
        case 'billeradjustments':
            $tmp = session2DB_payments($data);
            break;
        case 'interactionlogs':
            $tmp = session2DB_interactionlogs($data);
            break;
        case 'patientlogs':
            $tmp = session2DB_patientlogs($data);
            break;
        case 'newpayments':
            $tmp = session2DB_newpayments($data);
            break;
        default:
            break;
    }
    return $tmp;
}

function get_ui2patient() {
    $tmp = array(
        'id' => 'id', 'last_name' => 'last_name', 'first_name' => 'first_name', 'sex' => 'sex', 'DOB' => 'DOB',
        'SSN' => 'SSN', 'street_address' => 'street_address', 'zip' => 'zip', 'state' => 'state', 'city' => 'city',
        'phone_number' => 'phone_number', 'second_phone_number' => 'second_phone_number', 'account_number' => 'account_number',
        'status' => 'status', 'relationship_to_insured' => 'relationship_to_insured', 'insurance_card_image' => 'insurance_card_image',
        'notes' => 'notes', 'condition_related_to' => 'condition_related_to','alert' => 'alert'
    );
    return $tmp;
}

function get_ui2statement() {
    $tmp = array();
    for ($i = 1; $i <= 2; $i++) {
        $tmp['statement_type' . $i] = 'statement_type' . $i;
        $tmp['date' . $i] = 'date' . $i;
        $tmp['trigger' . $i] = 'trigger' . $i;
        $tmp['claim' . $i] = 'claim' . $i;
        $tmp['remark' . $i] = 'remark' . $i;
        $tmp['encounter_id' . $i] = 'encounter_id' . $i;
        $tmp['statement_id' . $i] = 'statement_id' . $i;
    }
    return $tmp;
}

function get_ui2insurancepayments(){
    return array('amount'=>'amount_insurance_payment_received_',  'date'=>'date_insurance_payment_received_', 'notes' =>'notes_insurance_payment_');
}

function get_ui2patientpayments(){
    return array('amount'=>'amount_patient_payment_received_',  'date'=>'date_patient_payment_received_', 'notes' =>'notes_patient_payment_');
}

function get_ui2billeradjustments(){
    return array('amount'=>'amount_biller_adjustment_',  'date'=>'date_biller_adjustment_', 'notes' =>'notes_biller_adjustment_');
}

function get_ui2guarantor(){
    return array('id'=>'guarantor','last_name'=>'g_last_name','first_name'=>'g_first_name','DOB'=>'g_dob','sex'=>'g_sex','street_address'=>'g_street_address','zip'=>'g_zip','city'=>'g_city','state'=>'g_state','phone_number'=>'g_phone_number','second_phone_number'=>'g_second_phone_number','SSN'=>'g_ssn','relationship_to_patient'=>'g_relationship_to_patient',);
}
function get_ui2interactionlogs(){
   return array('date_and_time'=>'date_and_time_real_', 'log' =>'log_');
}
function get_ui2payments(){
    return array('amount'=>'payment_amount_','datetime'=>'payment_date_and_time_','from'=>'payment_from_','notes'=>'payment_notes_','internal_notes'=>'payment_internal_notes_');
}
function get_ui2patientlogs(){
    return array("date_and_time"=>'date_and_time_real_','log'=>'log_');
}

function get_common_patient_and_insured() {
    return array('last_name', 'first_name', 'sex', 'DOB', 'SSN', 'street_address', 'zip', 'state', 'city', 'phone_number');
}

function get_ui2insured() {
    return array(
        'id' => 'id', 'last_name' => 'last_name', 'first_name' => 'first_name', 'sex' => 'sex', 'DOB' => 'DOB',
        'SSN' => 'SSN', 'street_address' => 'street_address', 'zip' => 'zip', 'state' => 'state', 'city' => 'city',
        'phone_number' => 'phone_number', 'second_phone_number' => 'second_phone_number', 'ID_number' => 'ID_number',
        'policy_group_or_FECA_number' => 'policy_group_or_FECA_number', 'notes' => 'notes',       
        'insured_insurance_type' => 'insured_insurance_type',  'insured_insurance_expiration_date'=> 'insured_insurance_expiration_date', 'employer_or_school_name' => 'employer_or_school_name',
        'is_there_another_plan' => 'is_there_another_plan', 'other_insured_last_name' => 'other_insured_last_name',
        'other_insured_first_name' => 'other_insured_first_name', 'other_insured_DOB' => 'other_insured_DOB',
        'other_insured_sex' => 'other_insured_sex', 'other_insured_policy_or_group_number' => 'other_insured_policy_or_group_number',
        'other_insured_employer_name' => 'other_insured_employer_name', 'relationship_to_patient' => 'relationship_to_patient', 'insurance_id' => 'insurance_id', 'other_insurance_id' => 'other_insurance_id',
        'notes'=>'insurance_notes','insurance_name'=>'insurance_name'
    );
}
function get_ui2insured_2_1(){
    return array(
         'last_name' => 'last_name', 'first_name' => 'first_name', 'sex' => 'sex', 'DOB' => 'DOB',
        'SSN' => 'SSN', 'street_address' => 'street_address', 'zip' => 'zip', 'state' => 'state', 'city' => 'city',
        'phone_number' => 'phone_number', 'second_phone_number' => 'second_phone_number',
        'relationship_to_patient' => 'relationship_to_patient'
    );
}
function get_ui2insured_2_2(){
    return array(
        'ID_number' => 'ID_number','policy_group_or_FECA_number' => 'policy_group_or_FECA_number',      
         'employer_or_school_name' => 'employer_or_school_name', 'insured_insurance_expiration_date'=> 'insured_insurance_expiration_date',
         'insurance_id' => 'insurance_id'
    );
}
function get_ui2insured_2_3(){
    return array(
        'notes'=>'insurance_notes'
    );
}
function get_ui2insured_2() {
    return array(
        'id' => 'id', 'last_name' => 'last_name', 'first_name' => 'first_name', 'sex' => 'sex', 'DOB' => 'DOB',
        'SSN' => 'SSN', 'street_address' => 'street_address', 'zip' => 'zip', 'state' => 'state', 'city' => 'city',
        'phone_number' => 'phone_number', 'second_phone_number' => 'second_phone_number', 'ID_number' => 'ID_number',
        'policy_group_or_FECA_number' => 'policy_group_or_FECA_number',      
        'insured_insurance_type' => 'insured_insurance_type',  'insured_insurance_expiration_date'=> 'insured_insurance_expiration_date', 'employer_or_school_name' => 'employer_or_school_name',
        'relationship_to_patient' => 'relationship_to_patient', 'insurance_id' => 'insurance_id',
        'notes'=>'insurance_notes','insurance_name'=>'insurance_name'
    );
}
function get_ui2insurance() {
    return array(
        'id' => 'insurance_id', 'insurance_name' => 'insurance_name', 'fax_number' => 'fax_number', 'street_address' => 'insurance_street_address',
        'zip' => 'insurance_zip', 'state' => 'insurance_state', 'city' => 'insurance_city',
        'payer_type' => 'payer_type', 'anesthesia_bill_rate' => 'anesthesia_bill_rate', 'phone_number' => 'insurance_phone_number',
        'EDI_number' => 'EDI_number', 'anesthesia_crosswalk_overwrite' => 'anesthesia_crosswalk_overwrite',
        'anesthesia_bill_rate' => 'anesthesia_bill_rate', 'benefit_lookup' => 'benefit_lookup',
        'claim_status_lookup' => 'claim_status_lookup', 'EFT' => 'EFT',
        'claim_filing_deadline' => 'claim_filing_deadline', 'reconsideration' => 'reconsideration',
        'navinet_web_support_number' => 'navinet_web_support_number', 'appeal' => 'appeal', 'PID_interpretation' => 'PID_interpretation',
        'notes' => 'insurance_notes', 'claim_submission_preference' => 'claim_submission_preference'
    );
}

function get_ui2claim() {
    return array(
        'id' => 'id', 'amount_paid' => 'amount_paid', 'balance_due' => 'balance_due',
        'total_charge' => 'total_charge', 'claim_status' => 'claim_status', 'statement_status' => 'statement_status','bill_status'=>'bill_status', 'expected_payment' => 'expected_payment',
        'date_billed' => 'date_billed', 'date_last_billed' => 'date_last_billed','date_secondary_insurance_billed' => 'date_secondary_insurance_billed', 'date_closed' => 'date_closed',
        'color_code' =>'colorselector_1',
        'patientcorrespondence'=>'patientcorrespondence',
        'amount_insurance_payment_issued' => 'amount_insurance_payment_issued',
        'amount_insurance_payment_received' => 'amount_insurance_payment_received',
        'date_insurance_payment_issued' => 'date_insurance_payment_issued',
        'date_insurance_payment_received' => 'date_insurance_payment_received',
        'date_last_checked_with_attorney' => 'date_last_checked_with_attorney',
        'benefit_OOP' => 'benefit_OOP',
        'benefit_OOP_remaining' => 'benefit_OOP_remaining', 'benefit_deductible' => 'benefit_deductible',
        'benefit_co_insurance' => 'benefit_co_insurance', 'benefit_date_taken' => 'benefit_date_taken',
        'date_creation' => 'date_creation',
        'EOB_not_allowed_amount' => 'EOB_not_allowed_amount',
        'EOB_deductable' => 'EOB_deductable',
        
        /**********Add two field********/
        'EOB_reduction' => 'EOB_reduction',
        'EOB_other_reduction' => 'EOB_other_reduction',   
        /**********Add two field********/
        
        /**********Add four custom field********/
        'custom_1' => 'custom_1',
        'custom_2' => 'custom_2',
        'custom_3' => 'custom_3',
        'custom_4' => 'custom_4',
        /**********Add four custom field********/     
        
        'EOB_allowed_amount' => 'EOB_allowed_amount',
        'EOB_adjustment_reason' => 'EOB_adjustment_reason',
        'EOB_co_insurance' => 'EOB_co_insurance',
        'selfpay' => 'selfpay',
        'is_the_issued_payment_sent_to_patient' => 'is_the_issued_payment_sent_to_patient',
        'notes_insurance_payment' => 'notes_insurance_payment',
        'amount_patient_payment_received' => 'amount_patient_payment_received',
        'date_patient_payment_received' => 'date_patient_payment_received',
        'notes_patient_payment' => 'notes_patient_payment',
        'amount_biller_adjustment' => 'amount_biller_adjustment',
        'date_biller_adjustment' => 'date_biller_adjustment',
        'notes_biller_adjustment' => 'notes_biller_adjustment',
        'amount_biller_adjustment_2' => 'amount_biller_adjustment_2',
        'date_biller_adjustment_2' => 'date_biller_adjustment_2',
        'notes_biller_adjustment_2' => 'notes_biller_adjustment_2',
        'amount_biller_adjustment_3' => 'amount_biller_adjustment_3',
        'date_biller_adjustment_3' => 'date_biller_adjustment_3',
        'notes_biller_adjustment_3' => 'notes_biller_adjustment_3',
        //'guarantor_id'  => 'guarantor'
    );
}

function get_ui2followup() {
    $tmp = array('id' => 'followup_id', 'date_of_next_followup' => 'date_of_next_followup', 'delivery_date' => 'delivery_date',
        'notes' => 'notes', 'negotiated_payment_amount' => 'negotiated_payment_amount',
        'date_negotiated_amount_reached' => 'date_negotiated_amount_reached',
        'amount_initial_offer' => 'amount_initial_offer', 'date_initial_offer' => 'date_initial_offer',);
    for ($i = 1; $i <= 8; $i++) {
        $tmp['date_and_time_' . $i] = 'hidden_date_and_time_' . $i;
        $tmp['log_' . $i] = 'log_' . $i;
        /*delete the who_number_and_ref_number by PanDazhao 2012/7/24 */
//        $tmp['who_number_and_ref_number_' . $i] = 'who_number_and_ref_number_' . $i;
    }
    return $tmp;
}

function get_ui2encounter() {
    $tmp = array('id' => 'id', 'date_of_current_illness_or_injury' => 'date_of_current_illness_or_injury',
        'date_same_illness' => 'date_same_illness',
        'not_able_to_work_from_date' => 'not_able_to_work_from_date',
        'not_able_to_work_to_date' => 'not_able_to_work_to_date',
        'hospitalization_from_date' => 'hospitalization_from_date',
        'hospitalization_to_date' => 'hospitalization_to_date',
        'charge' => 'charge', 'file_path_service_sheet' => 'file_path_service_sheet',
        'file_path_anesthesia_time_sheet_image' => 'file_path_anesthesia_time_sheet_image',
        'notes' => 'notes', 'facility_id' => 'facility_id',
        'renderingprovider_id' => 'renderingprovider_id','renderingprovider_name'=>'renderingprovider_name','referringprovider_name'=>'referringprovider_name','provider_id' => 'provider_id',
        'resubmission_code'=>'resubmission_code','ref_number'=>'ref_number','authorization_number'=>'authorization_number',
        'referringprovider_id' => 'referringprovider_id',
        'accept_assignment' => 'accept_assignment', 'outside_lab' => 'outside_lab','secondary_CPT_code_1_text'=>'secondary_CPT_code_1_text','secondary_CPT_code_2_text'=>'secondary_CPT_code_2_text',
        'secondary_CPT_code_3_text'=>'secondary_CPT_code_3_text','secondary_CPT_code_4_text'=>'secondary_CPT_code_4_text','secondary_CPT_code_5_text'=>'secondary_CPT_code_5_text','secondary_CPT_code_6_text'=>'secondary_CPT_code_6_text',
        'CPT_code_1_text'=>'CPT_code_1_text','CPT_code_2_text'=>'CPT_code_2_text',
//        'start_date_1_old'=>'start_date_1_old','start_date_2_old'=>'start_date_2_old','start_date_3_old'=>'start_date_3_old','start_date_4_old'=>'start_date_4_old','start_date_5_old'=>'start_date_5_old','start_date_6_old'=>'start_date_6_old',
        'CPT_code_3_text'=>'CPT_code_3_text','CPT_code_4_text'=>'CPT_code_4_text','CPT_code_5_text'=>'CPT_code_5_text','CPT_code_6_text'=>'CPT_code_6_text',
        'diagnosis_code1_text'=>'diagnosis_code1_text','diagnosis_code2_text'=>'diagnosis_code2_text','diagnosis_code3_text'=>'diagnosis_code3_text','diagnosis_code4_text'=>'diagnosis_code4_text', 'selfpay'=>'selfpay'
        
    );
    for ($i = 1; $i <= 4; $i++)
        $tmp['diagnosis_code' . $i] = 'diagnosis_code' . $i;
    for ($i = 1; $i <= 6; $i++) {
        $tmp['bill_by_'.$i] = "bill_by_" . $i;
        $tmp['secondary_CPT_code_' . $i] = 'secondary_CPT_code_' . $i;
        $tmp['start_date_' . $i] = 'start_date_' . $i;
        $tmp['start_time_' . $i] = 'start_time_' . $i;
        $tmp['CPT_code_' . $i] = 'CPT_code_' . $i;
        $tmp['end_date_' . $i] = 'end_date_' . $i;
        $tmp['end_time_' . $i] = 'end_time_' . $i;
        $tmp['modifier1_' . $i] = 'modifier1_' . $i;
        //$tmp['modifier_unit_' . $i] = 'modifier_unit_' . $i;
        $tmp['days_or_units_' . $i] = 'days_or_units_' . $i;
        $tmp['place_of_service_' . $i] = 'place_of_service_' . $i;
        $tmp['charges_' . $i] = 'charges_' . $i;
        $tmp['expected_payment_'.$i]='expected_payment_'.$i;
        $tmp['diagnosis_pointer_' . $i] = 'diagnosis_pointer_' . $i;
    //    $tmp['EMG_' . $i] = 'EMG_' . $i;
        $tmp['renderingprovider_id_'.$i]='renderingprovider_id_'.$i;
        $tmp['EMG_'.$i]='EMG_'.$i;
        $tmp['EPSDT_'.$i]='EPSDT_'.$i;
    }
    return $tmp;
}

function session2DB_patient($data) {
    $field_list_filter = array('SSN', 'zip', 'phone_number', 'second_phone_number');
    foreach ($field_list_filter as $field) {
        if ($data[$field] != null)
            $data[$field] = myfilter($data[$field]);
    }
    if ($data['DOB'] != null)
        $data['DOB'] = format($data['DOB'], 0);
    return $data;
}

function session2DB_statement($data) {
    $field_list_format = array();
    for ($i = 1; $i <= 2; $i++) {
        array_push($field_list_format, 'date' . $i);
    }
    foreach ($field_list_format as $field) {
        if ($data[$field] != null)
            $data[$field] = format($data[$field], 0);
    }
    return $data;
}

function session2DB_insured($data) {
    $field_list_filter = array('SSN', 'zip', 'phone_number', 'second_phone_number');
    foreach ($field_list_filter as $field) {
        if ($data[$field] != null)
            $data[$field] = myfilter($data[$field]);
    }
    if ($data['DOB'] != null)
        $data['DOB'] = format($data['DOB'], 0);
    if ($data['other_insured_DOB'] != null)
        $data['other_insured_DOB'] = format($data['other_insured_DOB'], 0);
    return $data;
}

function session2DB_encounter($data) {
    $field_list_format = array('date_same_illness', 'not_able_to_work_from_date', 'not_able_to_work_to_date',
        'hospitalization_from_date', 'hospitalization_to_date', 'date_of_current_illness_or_injury',
    );
    $field_list_decimal = array('charge');
    for ($i = 1; $i <= 6; $i++) {
        array_push($field_list_format, 'start_date_' . $i);
        array_push($field_list_format, 'end_date_' . $i);
        array_push($field_list_decimal, 'days_or_units_' . $i);
        array_push($field_list_decimal, 'charges_' . $i);
    }
    foreach ($field_list_format as $field) {
        if ($data[$field] != null)
            $data[$field] = format($data[$field], 0);
    }
    foreach ($field_list_decimal as $field) {
        if ($data[$field] != null)
            $data[$field] = decimal($data[$field]);
    }
    return $data;
}
/*********add two EOB_field By <Yu Lang>*******/
function session2DB_claim($data) {
    $field_list_format = array('date_last_checked_with_attorney', 'date_insurance_payment_received', 'date_insurance_payment_issued',
        'benefit_date_taken', 'date_creation', 'date_closed', 'date_billed', 'date_last_billed', 'date_secondary_insurance_billed','date_insurance_payment_received_2', 'date_insurance_payment_received_3',
        'date_patient_payment_received', 'date_patient_payment_received_2', 'date_patient_payment_received_3', 'date_insurance_payment_received_2', 'date_insurance_payment_received_3', 'date_biller_adjustment', 'date_biller_adjustment_2', 'date_biller_adjustment_3');
    $field_list_decimal = array('benefit_OOP_remaining', 'benefit_deductible', 'benefit_OOP', 'EOB_not_allowed_amount',
        'EOB_allowed_amount', 'EOB_deductable','EOB_reduction','EOB_other_reduction','EOB_co_insurance', 'amount_insurance_payment_received',
        'amount_insurance_payment_issued', 'expected_payment', 'balance_due', 'amount_paid', 'total_charge', 'amount_insurance_payment_received_2', 'amount_insurance_payment_received_3',
        'amount_patient_payment_received', 'amount_patient_payment_received_2', 'amount_patient_payment_received_3', 'amount_biller_adjustment',
        'amount_biller_adjustment_2', 'amount_biller_adjustment_3');
    foreach ($field_list_format as $field) {
        if ($data[$field] != null)
            $data[$field] = format($data[$field], 0);
    }
    foreach ($field_list_decimal as $field) {
        if ($data[$field] != null)
            $data[$field] = decimal($data[$field]);
    }
    if ($data['benefit_co_insurance'])
        $data['benefit_co_insurance'] = percentage($data['benefit_co_insurance'], 1);
    return $data;
}

function session2DB_followups($data) {
    for ($i = 1; $i <= 8; $i++)
        if ($data['date_and_time_' . $i] != null)
            $data['date_and_time_' . $i] = format($data['date_and_time_' . $i], 6);
    if ($data['date_of_next_followup'] != null)
        $data['date_of_next_followup'] = format($data['date_of_next_followup'], 0);
    if ($data['date_negotiated_amount_reached'] != null)
        $data['date_negotiated_amount_reached'] = format($data['date_negotiated_amount_reached'], 0);
    if ($data['date_initial_offer'] != null)
        $data['date_initial_offer'] = format($data['date_initial_offer'], 0);
    if ($data['delivery_date'] != null)
        $data['delivery_date'] = format($data['delivery_date'], 0);
    if ($data['negotiated_payment_amount'] != null)
        $data['negotiated_payment_amount'] = decimal($data['negotiated_payment_amount']);
    if ($data['amount_initial_offer'] != null)
        $data['amount_initial_offer'] = decimal($data['amount_initial_offer']);
    return $data;
}

function session2DB_payments($data){
    $count = count($data);
    if($count>0){
        for($i = 0;$i<$count;$i++){
            $data[$i]['date'] = format($data[$i]['date'],0);
        }
    }
    return $data;
}

function session2DB_interactionlogs($data){
    $count = count($data);
    if($count>0){
        for($i = 0;$i<$count;$i++){
            $data[$i]['date_and_time'] = format($data[$i]['date_and_time'],6);
        }
    }
    return $data;
}
function session2DB_newpayments($data){
    $count = count($data);
    if($count>0){
        for($i = 0;$i<$count;$i++){
            $data[$i]['datetime'] = format($data[$i]['datetime'],6);
        }
    }
    return $data;
}
function session2DB_patientlogs($data){
    $count = count($data);
    if($count>0){
        for($i = 0;$i<$count;$i++){
            $data[$i]['date_and_time'] = format($data[$i]['date_and_time'],6);
        }
    }
    return $data;
}
/* * ********************document******************************* */

function init_sysdoc($sysdoc) {
    if (!is_dir($sysdoc))
        mkdir($sysdoc);
    if (!is_dir($sysdoc . '/cms1500'))
        mkdir($sysdoc . '/cms1500');
    if (!is_dir($sysdoc . '/edi'))
        mkdir($sysdoc . '/edi');
    if (!is_dir($sysdoc . '/reports'))
        mkdir($sysdoc . '/reports');
    $document = $sysdoc . '/document';
    if (!is_dir($document))
        mkdir($document);
    $subfolders = get_subfolders();
    foreach ($subfolders as $val) {
        if (!is_dir($document . '/' . $val))
            mkdir($document . '/' . $val);
    }
}

function init_document($sysdoc, $ids) {
    $subfolders = get_subfolders();
    for ($i = 0; $i < count($subfolders); $i++) {
        $tmp_folder = $sysdoc . '/document' . '/' . $subfolders[$i] . '/' . $ids[$i];
        if (($ids[$i] != NULL) && ($ids[$i] != '') && (!is_dir($tmp_folder))) {
            mkdir($tmp_folder);
            mkdir($tmp_folder . '/one-off');
        }
    }
}

function get_subfolders() {
    return array('patient', 'insured', 'encounter', 'claim');
}

function get_session_name() {
    return array('patient', 'encounterinsured', 'patientinsured', 'insured', 'statement', 'insurance', 'encounter', 'claim', 'followups','insurancepayments','patientpayments','billeradjustments', 'interactionlogs');
}

function get_file_list($pageno, $combo='') {
    $file_type_list = array();
    switch ($pageno) {
        case '2':
            $file_type_list = array('Insurance' => 'Insurance');
            break;
        case '3':
            $file_type_list = array('Combo Sheets' => 'ComboSheets',);
            break;
        case '4':
            $file_type_list = array('Combo Sheets' => 'ComboSheets', 'Agreement' => 'Agreement', 'EOB' => 'EOB', 'Payment' => 'Payment', 'one-off' => 'one-off');
            break;
        default:
            break;
    }
    if (count($combo) > 0)
        foreach ($combo as $val)
            $file_type_list[$val] = $val;
    $file_type_list['one-off'] = 'one-off';
    return $file_type_list;
}

function is_docbtn_avaiable($id/* , $sysdoc, $type */) {
    return!((is_null($id)) || ($id == ''));
}

///**********************document******************************/
?>
