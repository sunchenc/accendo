<?php

class service {

    protected $patient = array();
    protected $statement = array();
    protected $insured = array();
    protected $insurance = array();
    protected $encounter = array();
    protected $claim = array();
    protected $followups = array();
    protected $guarantor = array();
    protected $insurancepayments = array();
    protected $patientpayments = array();
    protected $billeradjustments = array();
    protected $interactionlogs = array();
    protected $assignedclaims = array();
    
    /**************Add encoutnerinsured data*****************/
    protected $encounterinsured = array();
    protected $patientinsured = array();
    protected $patientlogs = array();
    protected $payments = array();
    /**************Add encoutnerinsured data*****************/
    
    /********Temp by Yu Lang********
    protected $provider = array();
    /********Temp by Yu Lang*********/
    
    function service($patientid, $encounterid) {
        if ($patientid != null && ($patientid != '')) {
            //patient
            $db_patient = new Application_Model_DbTable_Patient();
            $rowset = $db_patient->find($patientid);
            //below is the decode of SSN
            $rowsetArray = $rowset->current()->toArray();
            $prim_SSN = $rowsetArray['SSN'];
            
//            if(strlen($prim_SSN)!=8 && strlen($prim_SSN)>0){
//                $rowsetArray['SSN'] = decodeSSN($rowsetArray['SSN']);
//            }
            
//            $decode_SSN = decodeSSN($rowsetArray['SSN']);
//            if($prim_SSN && !$decode_SSN){
//                $rowsetArray['SSN'] = $prim_SSN;//原来有值,解密错误
//            }else if(!$prim_SSN){
//                $rowsetArray['SSN'] = '';
//            }else{
//                $rowsetArray['SSN'] = $decode_SSN;
//            }
            
            if($prim_SSN){
                if(!checkSSN($prim_SSN)){
                    $decode_SSN = decodeSSN($rowsetArray['SSN']);
                    $rowsetArray['SSN'] = $decode_SSN;
                }
            }
            
            $this->patient = $rowsetArray;
//            $this->patient = $rowset->current()->toArray();
            ///statement
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('statement', array('statement.id as statement_id', 'statement_type', 'trigger', 'remark', 'encounter_id'));
            $select->join('encounter', 'encounter.id = statement.encounter_id');
            $select->join('claim', 'claim.id = encounter.claim_id');
            $select->join('patient', 'patient.id = encounter.patient_id');
            $select->where('patient.id= ?', $patientid);
            if($encounterid!=null&&$encounterid!=''){
                 $select->where('encounter.id = ?',$encounterid);
            }
           
            $select->where('isnull(statement.date)');
            $select->where('claim.claim_status LIKE ?', 'open%');
            $select->order('statement_id DESC');
            $statement = $db->fetchAll($select);

            $mystatement['statement_id2'] = $statement[0]['statement_id'];
            $mystatement['statement_type2'] = $statement[0]['statement_type'];
            $mystatement['trigger2'] = $statement[0]['trigger'];
            $mystatement['remark2'] = $statement[0]['remark'];
            $mystatement['claim2'] = format($statement[0]['start_date_1'], 1) . ' ' . $statement[0]['CPT_code_1'];
            $mystatement['encounter_id2'] = $statement[0]['encounter_id'];

            $mystatement['statement_id1'] = null;
            $mystatement['statement_type1'] = null;
            $mystatement['trigger1'] = null;
            $mystatement['remark1'] = null;
            $mystatement['claim1'] = null;
            $mystatement['encounter_id1'] = null;
            $this->statement = $mystatement;
            
                        
            //insured
            /* new insured */          
            $db_patientinsured = new Application_Model_DbTable_Patientinsured();
            $db = $db_patientinsured->getAdapter();
            $where = $db->quoteInto('patient_id = ?', $this->patient['id']);
            $patientinsured_list = $db_patientinsured->fetchAll($where)->toArray();
            $this->patientinsured = $patientinsured_list;
            
            $db_patientlog = new Application_Model_DbTable_Patientlog();
            $db = $db_patientlog->getAdapter();
            $where = $db->quoteInto('patient_id = ?', $this->patient['id']);
            $patientlog_list = $db_patientlog->fetchAll($where)->toArray();
            $this->patientlogs = $patientlog_list;
            
            
            $tp_insured_array = array();
            $index = 0;
            for($i = 0; $i < count($patientinsured_list); $i++)
            {
                $tp_insured_id = $patientinsured_list[$i]['insured_id'];
                $db_insured = new Application_Model_DbTable_Insured();
                $rowset = $db_insured->find($tp_insured_id);
//                $tp_insured_array[$i] = $rowset->current()->toArray();
                $rowsetArray = $rowset->current()->toArray();
                $prim_SSN = $rowsetArray['SSN'];
//                $decode_SSN = decodeSSN($rowsetArray['SSN']);
//                if($prim_SSN && !$decode_SSN){
//                    $rowsetArray['SSN'] = $prim_SSN;//原来有值,解密错误
//                }else{
//                    $rowsetArray['SSN'] = $decode_SSN;
//                }
                
//                 $decode_SSN = decodeSSN($rowsetArray['SSN']);
//                if ($prim_SSN && !$decode_SSN) {
//                    $rowsetArray['SSN'] = $prim_SSN; //原来有值,解密错误
//                } else if (!$prim_SSN) {
//                    $rowsetArray['SSN'] = '';
//                } else {
//                    $rowsetArray['SSN'] = $decode_SSN;
//                }
                
                 if ($prim_SSN) {
                    if (!checkSSN($prim_SSN)) {
                        $decode_SSN = decodeSSN($rowsetArray['SSN']);
                        $rowsetArray['SSN'] = $decode_SSN;
                    }
                }

//                $prim_SSN = $rowsetArray['SSN'];
//                if(strlen($prim_SSN)!=8 && strlen($prim_SSN)>0){
//                    $rowsetArray['SSN'] = decodeSSN($rowsetArray['SSN']);
//                }
                $tp_insured_array[$i] = $rowsetArray;
                
                //if($tp_insured_array[$i]['insured_insurance_type'] == 'primary')
                    //$primary_insurance_id =$tp_insured_array[$i]['insurance_id'];
                $index = $index + 1;
            }

            
            $this->insured = $tp_insured_array;
            
            
            //Fix the bug
             if($encounterid != null && $encounterid != '')
             {               
                  $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                  $db = $db_encounterinsured->getAdapter();
                  $where = $db->quoteInto('encounter_id = ?',$encounterid);
                  $TTTT = $db_encounterinsured->fetchRow($where);
                  if($TTTT!=null){
                  $tmp_encoutnerinsured_data = $db_encounterinsured->fetchAll($where)->toArray();
                  } else{
                   $tmp_encoutnerinsured_data = null;
                  }
                
                
                if(count($tmp_encoutnerinsured_data) > 0 )
                {
                    
                    for($i =0; $i < count($tmp_encoutnerinsured_data); $i++)
                    {
                        if($tmp_encoutnerinsured_data[$i]['type'] == 'primary')
                        {
                            $tmp_insured_id = $tmp_encoutnerinsured_data[$i]['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id = ?',$tmp_insured_id);
                            $tmp_insured_data = $db_insured->fetchRow($where);
                            $primary_insurance_id = $tmp_insured_data['insurance_id']; 
                        }
                    }
                }
                else
                {
                    //$tmp_insured_datas = $this->insured;
                    //foreach($tmp_insured_datas as $row)
                    //{
                    //    if($row['insured_insurance_type'] == 'primary')
                    //       $primary_insurance_id =$tp_insured_array[$i]['insurance_id'];
                    //}
                    $primary_insurance_id = null;
                }
              }
              else
              {                 
                    $tmp_insured_datas = $this->insured;
                    $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                    $db = $db_encounterinsured->getAdapter();
                    foreach($tmp_insured_datas as $row) {   
                        $where = $db->quoteInto('insured_id = ?' , $row['id']) . ' AND ' . $db->quoteInto('type=?',  'primary');
                        //$where = $db->quoteInto('insured_id = ?',$row['id']);
                        //$where = $db->quoteInto('type = ?', 'primary');
                        $TT = $db_encounterinsured->fetchRow($where);
                        if($TT!=null){
                            $primary_insurance_id = $row['insurance_id'];
                        } else{
                            $primary_insurance_id = null;
                        }
                    }                                  
              }

            
            //  $dd = 1;          
            /* old insured 
            $insured_id = $this->patient['insured_id'];
            $db_insured = new Application_Model_DbTable_Insured();
            $rowset = $db_insured->find($insured_id);
            $this->insured = $rowset->current()->toArray();
            */
            
            //insurance
            //$insurance_id = $this->insured['insurance_id'];
            $insurance_id  = $primary_insurance_id;
            if($insurance_id != null && $insurance_id != "") {
                $db_insurance = new Application_Model_DbTable_Insurance();
                $rowset = $db_insurance->find($insurance_id);
                $this->insurance = $rowset->current()->toArray();
            }
            else
                $this->insurance = null;            
        }
        
        if ($encounterid != null && ($encounterid != '')) {
            
            ////encounter
            $db_encounter = new Application_Model_DbTable_Encounter();
            $rowset = $db_encounter->find($encounterid);
            $this->encounter = $rowset->current()->toArray();
            
            
            
            /*************************Add Encounterinsured data **************************/
            $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
            $db = $db_encounterinsured->getAdapter();
            $where = $db->quoteInto('encounter_id = ?',$encounterid);
            $TTTT = $db_encounterinsured->fetchRow($where);
            $tmp_encoutnerinsured_data = $db_encounterinsured->fetchAll($where)->toArray();
            for($i =0; $i < count($tmp_encoutnerinsured_data); $i++) {
                $tmp_insured_id = $tmp_encoutnerinsured_data[$i]['insured_id'];
                $db_insured = new Application_Model_DbTable_Insured();
                $db = $db_insured->getAdapter();
                $where = $db->quoteInto('id = ?',$tmp_insured_id);
                $tmp_insured_data = $db_insured->fetchRow($where);
                $tmp_encoutnerinsured_data[$i]['insurance_id'] = $tmp_insured_data['insurance_id']; 
            
            }
            if($TTTT!=null){
                $this->encounterinsured = $tmp_encoutnerinsured_data;
            } else{
                $this->encounterinsured = null;
            }
            /*************************Add Encounterinsured data **************************/ 
            
            
            
            
            /*************temp change By Yu Lang************
            //provider
           if ($encounterid != null && ($encounterid != ''))
           {
                $provider_id = $this->encounter['provider_id'];
                $db_provider = new Application_Model_DbTable_Provider();
                $rowset = $db_provider->find($provider_id);
                $this->provider = $rowset->current()->toArray(); 
            }
            /*************temp change By Yu Lang*************/
            //
            
            //claim
            $claim_id = $this->encounter['claim_id'];
            $db_claim = new Application_Model_DbTable_Claim();
            $rowset = $db_claim->find($claim_id);
            $this->claim = $rowset->current()->toArray();

            $db_followups = new Application_Model_DbTable_Followups();
            $db = $db_followups->getAdapter();
            $where = $db->quoteInto('claim_id = ?', $this->claim['id']);
            $this->followups = $db_followups->fetchRow($where)->toArray();
            $T = $this->claim;
            $TT = $this->followups;
            
            $db_insurancepayments = new Application_Model_DbTable_Insurancepayments();
            $db = $db_insurancepayments->getAdapter();
            $where = $db->quoteInto('claim_id = ?',$this->claim['id']);
            $TTT = $db_insurancepayments->fetchAll($where);
            if($TTT!=null){
                $this->insurancepayments = $db_insurancepayments->fetchAll($where)->toArray();
            }else{
                 $this->insurancepayments = null;
            }
            
            
            $db_patientpayments = new Application_Model_DbTable_Patientpayments();
            $db = $db_patientpayments->getAdapter();
            $where = $db->quoteInto('claim_id = ?',$this->claim['id']);
            $TTT = $db_patientpayments->fetchAll($where);
            if($TTT!=null){
                $this->patientpayments = $db_patientpayments->fetchAll($where)->toArray();
            }else{
                 $this->patientpayments = null;
            }
        
            $db_billeradjustments = new Application_Model_DbTable_Billeradjustments();
            $db =$db_billeradjustments->getAdapter();
            $where = $db->quoteInto('claim_id = ?',$this->claim['id']);
            $TTT = $db_billeradjustments->fetchAll($where);
            if($TTT!=null){
                $this->billeradjustments = $db_billeradjustments->fetchAll($where)->toArray();
            }else{
                 $this->billeradjustments = null;
            }
            
            $db_interactionlogs = new Application_Model_DbTable_Interactionlog();
            $db =$db_interactionlogs->getAdapter();
            $where = $db->quoteInto('claim_id = ?',$this->claim['id']);
            $order = 'date_and_time ASC';
            $TTT = $db_interactionlogs->fetchAll($where);
            if($TTT!=null){
                $this->interactionlogs = $db_interactionlogs->fetchAll($where,$order)->toArray();
            }else{
                 $this->interactionlogs = null;
            }
            
            $db_assignedclaims = new Application_Model_DbTable_Assignedclaims();
            $db = $db_assignedclaims->getAdapter();
            $where = $db->quoteInto('encounter = ?',$encounterid);
            $TTTT = $db_assignedclaims->fetchRow($where);
            if($TTTT!=null){
                $this->assignedclaims = $db_assignedclaims->fetchRow($where)->toArray();
            } else{
                $this->assignedclaims = null;
            }
            $db_payments = new Application_Model_DbTable_Payments();
            $db = $db_payments->getAdapter();
            $where = $db->quoteInto('claim_id=?',$claim_id) . " AND " . $db->quoteInto("serviceid = ?", 0);
            $order = 'datetime ASC';
            $TTT = $db_payments->fetchAll($where);
            if($TTT != null){
                $payments_data = $db_payments->fetchAll($where,$order)->toArray();
                for($p_i=0;$p_i<count($payments_data);$p_i++){
                    $payment_id = $payments_data[$p_i]["id"];
                    $where = $db->quoteInto('claim_id=?',$claim_id) . " AND " . $db->quoteInto("paymentid = ?", $payment_id);
                    $TTTT = $db_payments->fetchAll($where)->toArray();
                    if($TTTT!=null){
                        $payments_data[$p_i]["type"] = "EOB";
                        $payments_data[$p_i]["services"] = array();
                        for($T_i=0;$T_i<count($TTTT);$T_i++){
                            $payments_data[$p_i]["services"][$T_i]['amount'] = $TTTT[$T_i]["amount"];
                            $payments_data[$p_i]["services"][$T_i]['service_payment_id'] = $TTTT[$T_i]["id"];
                        }
                    }else{
                        $payments_data[$p_i]["type"] = "total";
                    }
                }
                $this->payments = $payments_data;
            }else{
                $this->payments = null;
            }
            if($this->claim['guarantor_id']!=""&&$this->claim['guarantor_id']!=null){
                $db_guarantor = new Application_Model_DbTable_Guarantor();
                $db= $db_guarantor->getAdapter();
                $where = $db->quoteInto('id = ?',$this->claim['guarantor_id']);
                $this->guarantor = $db_guarantor->fetchRow($where)->toArray();
            }else{
                $this->guarantor = null;
            }       
        }
    }

     /*************temp change By Yu Lang************
     public function get_provider() {
        return $this->provider;
    }
     /*************temp change By Yu Lang*************/
    
    public function get_patient() {
        return $this->patient;
    }

    public function get_statement() {
        return $this->statement;
    }

    public function get_insured() {
        return $this->insured;
    }
    
    public function get_patientinsured(){
        return $this->patientinsured;
    }

    public function get_insurance() {
        return $this->insurance;
    }

    public function get_encounter() {
        return $this->encounter;
    }

    public function get_claim() {
        return $this->claim;
    }

    public function followups() {
        return $this->followups;
    }

    public function get_insuracnepayments(){
        return $this->insurancepayments;
    }
    
     public function get_patientpayments(){
        return $this->patientpayments;
    }
    
    
    public function get_interactionlogs(){
        return $this->interactionlogs;
    }
    
    public function get_billeradjustments(){
        return $this->billeradjustments;
    }
    
    public function get_assignedclaims(){
        return $this->assignedclaims;
    }
    
    /************Add get_encounter_insured_data***************/
    public function get_encounterinsured(){
        return $this->encounterinsured;
    }
    public function get_patientlogs(){
        return $this->patientlogs;
    }
    public function get_payments(){
        return $this->payments;
    }
    public function get_guarantor(){
        return $this->guarantor;
    }
    /************Add get_encounter_insured_data***************/
}

?>
