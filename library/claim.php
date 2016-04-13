<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of claim
 *
 * @author lenvovo
 */
function x12clean($str) {
    return preg_replace('/[^A-Z0-9!"\\&\'()+,\\-.\\/;?= ]/', '', strtoupper($str));
}

class claim {

//put your code here
    protected $x12_partner;       // row from x12_partners table
    protected $x12_partner_id;
    protected $facility;
    protected $provider;
    protected $renderingprovider = array();
    protected $patient;
    protected $insured;
   /*******Add for sec insured*******/
    protected $sec_insured;
    /*******Add for sec insured*******/
    protected $insurance_company;
    /*Other insurance <By Yu Lang>*/
    protected $sec_insurance_company;
    /*Other insurance <By Yu Lang>*/     
    protected $billing_claim;
    protected $encounter;
    protected $option;
    protected $procs = array();
    protected $referringprovider;
    protected $billingcompany;

    function claim($options) {
        //x12_partner
        if (!is_null($options['x12_partner_id'])) {
            $this->x12_partner_id = $options['x12_partner_id'];
            $db_x12_partners = new Application_Model_DbTable_X12partners();
            $rowset = $db_x12_partners->find($this->x12_partner_id);
            $this->x12_partner = $rowset->current()->toArray();
        }

        ////encounter
        $encounter_id = $options['encounter_id'];
        $db_encounter = new Application_Model_DbTable_Encounter();
        $rowset = $db_encounter->find($encounter_id);
        $this->encounter = $rowset->current()->toArray();

        //patient
        $patient_id = $this->encounter['patient_id'];
        $db_patient = new Application_Model_DbTable_Patient();
        $rowset = $db_patient->find($patient_id);
        $this->patient = $rowset->current()->toArray();
        
        
          //claim
        $claim_id = $this->encounter['claim_id'];
        $db_claim = new Application_Model_DbTable_Claim();
        $rowset = $db_claim->find($claim_id);      
        $this->billing_claim = $rowset->current()->toArray();
        $tmp_claim_data = $rowset->current()->toArray();
        
        //For change of the pdf generate 2013-03-17//
        //insured
        $tmp_claim_status = $tmp_claim_data['claim_status'];
        
        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
        $db = $db_encounterinsured->getAdapter();
        $where = $db->quoteInto('encounter_id = ?', $encounter_id);
        $tmp_encounterinsured_data = $db_encounterinsured->fetchAll($where)->toArray();
        
        for($i = 0; $i < count($tmp_encounterinsured_data); $i++)
        {
            if($tmp_encounterinsured_data[$i]['type'] ==  'primary')
                $tmp_primary_insured_id = $tmp_encounterinsured_data[$i]['insured_id'];
            else if($tmp_encounterinsured_data[$i]['type'] ==  'secondary')
                $tmp_secondary_insured_id = $tmp_encounterinsured_data[$i]['insured_id'];
        }
        
        $insured_id = $tmp_primary_insured_id;
        $db_insured = new Application_Model_DbTable_Insured();
        $rowset = $db_insured->find($insured_id);
        $this->insured = $rowset->current()->toArray();
        
        
        $sec_insured_id = $tmp_secondary_insured_id;
        if($sec_insured_id != null)
        {
            $db_insured = new Application_Model_DbTable_Insured();
            $rowset = $db_insured->find($sec_insured_id);
            $this->sec_insured = $rowset->current()->toArray();
        }
        else
            $this->sec_insured = null;
               
        //For change of the pdf generate 2013-03-17//
       
        
         /*Other insurance <By Yu Lang>*/
        //another insurance
        //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
        {
            $sec_insurance_id = $this->sec_insured['insurance_id'];
            $db_sec_insurance = new Application_Model_DbTable_Insurance();
            $rowset = $db_sec_insurance->find($sec_insurance_id);
            $tmp_sec_insurance = $rowset->current()->toArray();
            $this->sec_insurance_company = $this->set_tag($tmp_sec_insurance);
        }
        /*Other insurance <By Yu Lang>*/
        
        //insurance
        $insurance_id = $this->insured['insurance_id'];
        $db_insurance = new Application_Model_DbTable_Insurance();
        $rowset = $db_insurance->find($insurance_id);
        $tmp_insurance_company = $rowset->current()->toArray();
        $this->insurance_company = $this->set_tag($tmp_insurance_company);

        //provider
        $provider_id = $this->encounter['provider_id'];
        $db_provider = new Application_Model_DbTable_Provider();
        $rowset = $db_provider->find($provider_id);
        $this->provider = $rowset->current()->toArray();

        //billingcompany
        $billingcompany_id = $this->provider['billingcompany_id'];
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $rowset = $db_billingcompany->find($billingcompany_id);
        $this->billingcompany = $rowset->current()->toArray();
        //rendering provider
        $db_rendering_provider = new Application_Model_DbTable_Renderingprovider();
        for($k = 1; $k < 7; $k++) {
            if($this->encounter['renderingprovider_id_' . $k] !=null && $this->encounter['renderingprovider_id_' . $k] !='') {
                $renderingprovider_id = $this->encounter['renderingprovider_id_' . $k];
                $rowset = $db_rendering_provider->find($renderingprovider_id);
                $this->renderingprovider[$k] = $rowset->current()->toArray();
            }
        }
        //facility
        $facility_id = $this->encounter['facility_id'];
        $db_facility = new Application_Model_DbTable_Facility();
        $rowset = $db_facility->find($facility_id);
        $this->facility = $rowset->current()->toArray();


        //referringprovider
        $referringprovider_id = $this->encounter['referringprovider_id'];
        if ($referringprovider_id > 0) {
            $db_referringprovider = new Application_Model_DbTable_Referringprovider();
            $rowset = $db_referringprovider->find($referringprovider_id);
            $this->referringprovider = $rowset->current()->toArray();
        }
        //procs
        for ($k = 1; $k <= 6; $k++) {
            if ($this->encounter['CPT_code_' . $k] != ""||$this->encounter['secondary_CPT_code_' . $k] != "") {
                $modifer = '';
                for ($i = 1; $i < 5; $i++) {
                    $modifer_tmp = $this->encounter['modifier' . $i . '_' . $k];
                    if ($modifer_tmp != "") {
                        $modifer = $modifer . ':' . $modifer_tmp;
                    }
                }

                $serviceDate = '';
                $dateType = '';
                $DOS = $this->encounter['start_date_1'];
                if ($this->encounter['end_date_' . $k] == '') {
                    $serviceDate = str_replace('-', '', $this->encounter['start_date_' . $k]);
                    $dateType = 'D8';
                } else {
                    $serviceDate = str_replace('-', '', $this->encounter['start_date_' . $k]) .
                            '-' .
                            str_replace('-', '', $this->encounter['end_date_' . $k]);
                    $dateType = 'RD8';
                }
                $proc = array(
                    'serviceDate' => $serviceDate,
                    'place_of_service' => $this->encounter['place_of_service_' . $k],
                    'EMG' => $this->encounter['EMG_' . $k],
                    'cpt_code' => $this->encounter['CPT_code_' . $k],
                    'secondary_CPT_code' => $this->encounter['secondary_CPT_code_' . $k],
                    'modifier' => $modifer,
                    'diagnosis_pointer' => $this->encounter['diagnosis_pointer_' . $k],
                    'charge' => $this->encounter['charges_' . $k],
                    'days_or_units' => $this->encounter['days_or_units_' . $k],
                    'EPSDT' => $this->encounter['EPSDT_' . $k],
                    'dateType' => $dateType,
                    'DOS' => $DOS,
                    'start_date_' => $this->encounter['start_date_' . $k],
                    'end_date_' => $this->encounter['end_date_' . $k],
                    'start_time' => $this->encounter['start_time_' . $k],
                    'end_time' => $this->encounter['end_time_' . $k],
                    'bill_by' => $this->encounter['bill_by_' . $k],
                );
                array_push($this->procs, $proc);
            }
        }
    }

    public function x12gsisa05() {
        return $this->x12_partner['x12_isa05'];
    }

    public function x12gssenderid() {
        return $this->x12_partner['sender_id'];
    }

    public function x12gsisa07() {
        return $this->x12_partner['x12_isa07'];
    }

    public function x12gsreceiverid() {
        return $this->x12_partner['receiver_id'];
    }

    public function x12gsisa14() {
        return $this->x12_partner['x12_isa14'];
    }

    public function x12gsisa15() {
        return $this->x12_partner['x12_isa15'];
    }

    public function x12gsgs02() {
        return $this->x12_partner['x12_gs02'];
    }

    public function x12gsversionstring() {
        return $this->x12_partner['x12_version'];
    }

    public function onsetDate() {

        return str_replace('-', '', $this->encounter['hospitalization_from_date']);
    }
    
    public function provider_name() {

        return $this->provider['provider_name'];
    }

    public function provider_EDI() {
        return $this->provider['tax_ID_number'];
    }

    public function provider_Tax_id_number() {
        return $this->provider['tax_ID_number'];
    }
    
    /*********************Find the Provider_NPI*******************/
    
    public function get_billing_provider_NPI(){
        return $this->provider['billing_provider_NPI'];
    }
    /*********************Find the Provider_NPI*******************/
    
   /**********************Add billingcompany_id***********************/
    public function billingcompany_id(){
        return $this->provider['billingcompany_id'];
    }
    
    public function getDOS(){
        return $this->encounter['start_date_1'];
    }
   /**********************Add billingcompany_id***********************/
    
    public function billingcompany_name() {
        return $this->billingcompany['billingcompany_name'];
    }

    //////////////////////////////// //NM1*85*2*
    public function billing_provider_name() {
        return $this->provider['billing_provider_name'];
    }

    public function billing_provider_NPI() {
        return $this->provider['billing_provider_NPI'];
    }

    public function billing_provider_street() {
        return $this->provider['billing_street_address'];
    }

    public function billing_provider_zip() {
        return $this->provider['billing_zip'];
    }

    public function billing_provider_state() {
        return $this->provider['billing_state'];
    }

    public function billing_provider_city() {
        return $this->provider['billing_city'];
    }

    public function billing_provider_phone() {
        return $this->provider['billing_phone_number'];
    }

    public function billing_provider_fax() {
        return $this->provider['billing_fax'];
    }

    public function billing_provider_email() {
        return $this->provider['billing_email'];
    }

    ////////////////////////////////////////////////////////////
    //renderingprovider
    public function renderingprovider_NPI($prockey) {
        return $this->renderingprovider[$prockey]['NPI'];
    }
    public function renderingprovider_salutation($prockey) {
        return $this->renderingprovider[$prockey]['salutation'];
    }

    public function renderingprovider_LastName($prockey) {
        return $this->renderingprovider[$prockey]['last_name'];
    }

    public function renderingprovider_FirstName($prockey) {
        return $this->renderingprovider[$prockey]['first_name'];
    }

    //////////////////////////insured info
    function insuredLastName() {
        return $this->insured['last_name'];
    }

    function insuredFirstName() {
        return $this->insured['first_name'];
    }

    
    function insuredStreet() {
        return $this->insured['street_address'];
    }

    function insuredCity() {
        return $this->insured['city'];
    }

    function insuredState() {
        return $this->insured['state'];
    }

    function insuredZip() {
        return $this->insured['zip'];
    }

    function insuredPhone() {
        return $this->insured['phone_number'];
    }

    function insuredDOB() {

        return str_replace('-', '', $this->insured['DOB']);
    }

    function insuredSex() {
        $sex = $this->insured['sex'];
        if ($sex == 'f' || ($sex == 'F'))
            return 'F';
        return 'M';
    }

    function plan_or_program_name() {
        return $this->insured['plan_or_program_name'];
    }

    function insured_signature() {
        return $this->insured['signature'];
    }

    function is_there_another_plan() {
        return $this->insured['is_there_another_plan'];
    }

    function otherinsuredFirstName() {
        return $this->insured['other_insured_first_name'];
    }

    function otherinsuredLastName() {
        return $this->insured['other_insured_last_name'];
    }

    function otherinsuredDOB() {
        return str_replace('-', '', $this->insured['other_insured_DOB']);
    }

    function employer_or_school_name() {
        return $this->insured['employer_or_school_name'];
    }

    function other_insured_employer_name() {
        return $this->insured['other_insured_employer_name'];
    }

    function other_insurance_name_or_program_name() {
        return $this->insured['other_insurance_name_or_program_name'];
    }

    function otherinsuredSex() {
        $sex = $this->insured['other_insured_sex'];
        if ($sex == 'f' || ($sex == 'F'))
            return 'F';
        return 'M';
    }

    function policy_group_or_FECA_number() {
        return $this->insured['policy_group_or_FECA_number'];
    }

    function other_insured_policy_or_group_number() {
        return $this->insured['other_insured_policy_or_group_number'];
    }

    ///////////////////////////////////////////////////////////
    public function clearingHouseName() {
        //testing value
        return "Anvicare";
    }

    public function clearingHouseETIN() {
        //testing value
        return "32145";
    }

    public function facilityName() {
        return (trim($this->facility['facility_name']));
    }

    function facilityNPI() {
// mofify it after discussing with Chen
// using default renderprovider NPI instead.
// return x12clean(trim($this->facility['facility_npi']));
        //return (trim($this->renderingprovider['NPI']));
        return (trim($this->facility['NPI']));
    }

    function facilityStreet() {
        return (trim($this->facility['street_address']));
    }

    function facilityCity() {
        return (trim($this->facility['city']));
    }

    function facilityState() {
        return (trim($this->facility['state']));
    }

    function facilityZip() {
        return (trim($this->facility['zip']));
    }

    function AnesthesiaRecord() {
        if ($this->facility['service_doc_forth_page'])
            return (trim($this->facility['service_doc_forth_page']));
        else if ($this->facility['service_doc_third_page'])
            return (trim($this->facility['service_doc_third_page']));
        else if ($this->facility['service_doc_second_page'])
            return (trim($this->facility['service_doc_second_page']));
        else
            return (trim($this->facility['service_doc_first_page']));
    }

//  function facilityETIN() {
//    return x12clean(trim(str_replace('-', '', $this->facility['federal_ein'])));
//  }
//
//
//  function facilityPOS() {
//    return sprintf('%02d', trim($this->facility['pos_code']));
//  }
//    function clearingHouseName() {
//        return x12clean(trim($this->x12_partner['name']));
//    }
//
//    function clearingHouseETIN() {
//        return x12clean(trim(str_replace('-', '', $this->x12_partner['id_number'])));
//    }

    function providerPhone() {
        return $this->provider['phone_number'];
    }

    function providerFax() {
        return $this->provider['fax_number'];
    }

        /*Other insurance <By Yu Lang>*/
    
    function policyNumber() {
           //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
             return $this->sec_insured['ID_number'];
        return $this->insured['ID_number'];
    }
        /*Other insurance <By Yu Lang>*/
    /*Other insurance <By Yu Lang>*/
    function payerName() {
        //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['insurance_name'];
        return $this->insurance_company['insurance_name'];
    }
    function displayName(){
           //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['insurance_display'];
        return $this->insurance_company['insurance_display'];
    }

    function insuranceType(){          
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['insurance_type'];
        return $this->insurance_company['insurance_type'];
    }
    
    function payerID() {
        //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['EDI_number'];
        return $this->insurance_company['EDI_number'];
    }

    function payerStreet() {
        //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['street_address'];
        return $this->insurance_company['street_address'];
    }

    function payerCity() {
           //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['city'];
        return $this->insurance_company['city'];
    }

    function payerState() {
         //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['state'];
        return $this->insurance_company['state'];
    }

    function payerZip() {
          //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['zip'];
        return $this->insurance_company['zip'];
    }

    function payerPhoneNumber() {
         //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['phone_number'];
        return $this->insurance_company['phone_number'];
    }
   
    
    function payerFax() {
         //$this->billing_claim['claim_status'] == 'open_ready_secondary_bill'
        if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary')
            return $this->sec_insurance_company['fax_number'];
        return $this->insurance_company['fax_number'];
    }
 /*Other insurance <By Yu Lang>*/
    
    function payerType() {
        return $this->insurance_company['payer_type'];
    }

    
    function isSelfOfInsured() {
        if ($this->insuredRelationship() == 'self')
            return true;
        return false;
    }

    function insuredRelationship() {
        return strtolower($this->patient['relationship_to_insured']);
    }

    function patientLastName() {
        return $this->patient['last_name'];
    }

    function patientFirstName() {
        return $this->patient['first_name'];
    }

    function patientStreet() {
        return $this->patient['street_address'];
    }

    function patientCity() {
        return $this->patient['city'];
    }

    function patientState() {
        return $this->patient['state'];
    }

    function patientZip() {
        return $this->patient['zip'];
    }

    function patientDOB() {
        return str_replace('-', '', $this->patient['DOB']);
    }

    function patientSex() {
        return strtoupper($this->patient['sex']);
    }

    function patientPhone() {
        return $this->patient['phone_number'];
    }

    function patientStatus() {
        return strtolower($this->patient['status']);
    }

    function patient_condition_related_to() {
        return $this->patient['condition_related_to'];
    }

    function patient_account() {
        return $this->patient['account_number'];
    }

    function total_charge() {
        return $this->billing_claim['total_charge'];
    }

    function expected_payment() {
        return intval($this->billing_claim['expected_payment']);
    }
    
    function amount_paid() {
        return $this->billing_claim['amount_paid'];
    }
    
    function balance_due() {
        return $this->billing_claim['balance_due'];
    }

    function facilityPOS() {
        return $this->encounter['place_of_service_1'];
    }

    function billingFacilityAssignment() {
        $accept_assign = $this->option['yes_for_assingment_of_benefits'];
        if ($accept_assign == '1')
            return TRUE;
        return FALSE;
    }

   
    function claimid() {
        return $this->encounter['claim_id'];
    }
    /***************Get the claim status*******************/
    function get_claim_status()
    {
        return $this->billing_claim['claim_status'];
    }
    function get_bill_status(){
        return $this->billing_claim['bill_status'];
    }
    /***************Get the claim status*******************/
    function encounterid() {
        return $this->encounter['id'];
    }

    function get_servce() {
        return $this->encounter;
    }

    function diagArray() {
        $digarray = array();

        if ($this->encounter['diagnosis_code1']) {
            $diag = $this->encounter['diagnosis_code1'];
            $digarray [$diag] = $diag;
        }
        if ($this->encounter['diagnosis_code2']) {
            $diag = $this->encounter['diagnosis_code2'];
            $digarray [$diag] = $diag;
        }
        if ($this->encounter['diagnosis_code3']) {
            $diag = $this->encounter['diagnosis_code3'];
            $digarray [$diag] = $diag;
        }
        if ($this->encounter['diagnosis_code4']) {
            $diag = $this->encounter['diagnosis_code4'];
            $digarray [$diag] = $diag;
        }
        return $digarray;
    }

    function date_of_current() {
        return $this->encounter['date_of_current_illness_or_injury'];
    }

    function referrerLastName() {
        return $this->referringprovider['last_name'];
    }

    function referrerFirstName() {
        return $this->referringprovider['first_name'];
    }

    function referringproviderNPI() {
        return $this->referringprovider['NPI'];
    }
     function referringprovidersalutation() {
        return $this->referringprovider['salutation'];
    }

    function procCount() {
        return count($this->procs);
    }

    function cptCode($prockey) {
        return (trim($this->procs[$prockey]['cpt_code']));
    }

    function secondary_CPT_code($prockey) {
        return (trim($this->procs[$prockey]['secondary_CPT_code']));
    }

    function cptModifier($prockey) {
        $aa = $this->procs[$prockey];
        return trim($this->procs[$prockey]['modifier']);
    }
    function cptEMG($prockey) {
        return trim($this->procs[$prockey]['EMG']);
    }
    
    function EPSDT($prockey) {
        return trim($this->procs[$prockey]['EPSDT']);
    }
    
    function CPSDT($prockey) {
        return trim($this->procs[$prockey]['CPSDT']);
    }
    
    // Returns the procedure code, followed by ":modifier" if there is one.
    function cptKey($prockey) {
        $tmp = $this->cptModifier($prockey);
        return $this->cptCode($prockey) . ($tmp ? "$tmp" : "");
    }

    function cptCharges($prockey) {
        // return intval(x12clean(trim($this->procs[$prockey]['charge'])));
        return floatval((trim($this->procs[$prockey]['charge'])));
    }

    function cpt_days_or_units($prockey) {
        if (empty($this->procs[$prockey]['days_or_units']))
            return '1';
        return (trim($this->procs[$prockey]['days_or_units']));
    }

    function cpt_place_of_service($prockey) {
        return (trim($this->procs[$prockey]['place_of_service']));
    }

    function cpt_dateType($prockey) {
        return (trim($this->procs[$prockey]['dateType']));
    }

    function cpt_serviceDate($prockey) {
        return (trim($this->procs[$prockey]['serviceDate']));
    }

    function DOS($prockey) {
        return (trim($this->procs[$prockey]['DOS']));
    }

    function cpt_start_time($prockey) {
        $start_time = (trim($this->procs[$prockey]['start_time']));
        return substr($start_time, 0, 2) . ':' . substr($start_time, 3, 2);
    }

    function cpt_end_time($prockey) {
        $end_time = (trim($this->procs[$prockey]['end_time']));
        return substr($end_time, 0, 2) . ':' . substr($end_time, 3, 2);
    }
    
    function billBy($prockey) {
        return (trim($this->procs[$prockey]['bill_by']));
    }

    function diagnosis_pointer($prockey) {
        $diagonsis_pointer_tmp = (trim($this->procs[$prockey]['diagnosis_pointer']));
//        $diag_pointer = '';
//        for ($i = 1; $i <= 4; $i++) {
//            $tmparray = explode($i, $diagonsis_pointer_tmp);
//            if (count($tmparray) > 1) {
//                $diag_pointer = $diag_pointer . $i;
//            }
//        }
        return str_replace(',', ':', $diagonsis_pointer_tmp);
    }
    function provider_id1(){
        return $this->provider["id1"];
    }
    function provider_id2(){
        return $this->provider["id2"];
    }
    function taxonomy_code(){
        return $this->provider["taxonomy_code"];
    }
    function renderprovider_id1($prockey){
        return $this->renderingprovider[$prockey]["id1"];
    }
    function renderprovider_id2($prockey){
        return $this->renderingprovider[$prockey]["id2"];
    }
    function set_tag($data_array){
        $result = $data_array;
        if($result["tags"]!=""&&$result["tags"]!=null){
            $result["tags"] = parse_tag($result["tags"]);
        }
        return $result;
    }
    function get_tag($table_name,$tag_name){
        $data_array = array();
        if($table_name=="insurance"){
            if($this->billing_claim['bill_status'] == 'bill_ready_bill_secondary'){
                $data_array =  $this->sec_insurance_company;
            }else{
                $data_array =  $this->insurance_company;
            }
        }
        return $data_array["tags"][$tag_name];
    }

}

?>
