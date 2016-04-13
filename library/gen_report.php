<?php

require_once 'mysql_table.php';

class PDF extends PDF_MySQL_Table {

    function Header() {
//        //Title
//        $this->SetFont('Arial', '', 18);
//        $this->Cell(0, 6, 'World populations', 0, 1, 'C');
//        $this->Ln(10);
        //Ensure table header is output
        parent::Header();
    }

}

class Report {

    protected $provider;
    protected $options;
    
    /****************Field of the claims******************/
    protected $claims_summary_field = array('Month', 'Claims', 'Amount_Billed', 'Amount_Collected');
    protected $claims_summary_header = array('Month' => '', 'Claims' => 'Claims', 'Amount_Billed' => 'Amount Billed', 'Amount_Collected' => 'Amount Collected');
    protected $claims_summary = array(); /*orignal Claims_summary*/
    /****************Field of the claims******************/
    
    /****************Field of the collections******************/
    protected $collections_summary_field = array('Month', 'Collection');
    protected $collections_summary_header = array('Month' => '', 'Collection' => 'Collection');
    protected $collections_summary = array();
    /****************Field of the collections******************/
    
    protected $Quarterly_Billing_Fee_field = array('quarter', 'Collections', 'Fee_Charged', 'Fee_Paid');
    protected $Quarterly_Billing_Fee_header = array('quarter' => '', 'Collections' => 'Collections', 'Fee_Charged' => 'Fee Charged', 'Fee_Paid' => 'Fee Paid');
    protected $Quarterly_Billing_Fee = array();
    
    /****************Change the header******************/
    protected $claims_details_field = array('Name', 'MRN', 'Insurance', 'DOS', 'Billed_Amount', 'Payment', 'Payment_Date', 'Rendering_Provider', 'Referring_Provider', 'Claim_Status','short_name');
    protected $claims_details_header = array('Name' => 'Name', 'MRN' => 'MRN', 'Insurance' => 'Insurance', 'DOS' => 'DOS', 'Billed_Amount' => 'Billed<br>Amount',
        'Payment' => 'Payment', 'Payment_Date' => 'Payment<br>Date', 'Rendering_Provider' => 'Rendering<br>Provider',
        'Referring_Provider' => 'Referring<br>Provider', 'Claim_Status' => 'Claim<br>Status','short_name'=>'Provider');
    /****************Change the header******************/
     
    protected $claims_details = array();
    protected $billingcompany_name = '';

    function Report($billingcompany_id,$provider_id,$year,$month,$user_id,$role) {
            $whereprovider=array();
            $providerList=array();
          
            $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
            $db = $db_userfocusonprovider->getAdapter();
            $where = $db->quoteInto('user_id = ?', $user_id);
            $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
            $provider_id_list = array();
            for ($i = 0; $i < count($userfocusonprovider); $i++) {
                  $provider_id_list[$i] = (int)$userfocusonprovider[$i]['provider_id'];
            }
               // $providerList_start[1]=2;
         
           
        $month=$month+1;
        if($provider_id!=0){
        $db_provider = new Application_Model_DbTable_Provider();
        $rowset = $db_provider->find($provider_id);
        $this->provider = $rowset->current()->toArray();
        $db_options = new Application_Model_DbTable_Options();
        $rowset = $db_options->find($this->options_id());
        $this->options = $rowset->current()->toArray();
        }

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $rowset = $db_billingcompany->find($billingcompany_id);
        $billingcompany = $rowset->current()->toArray();
        $this->billingcompany_name = $billingcompany['billingcompany_name'];
       
        //claims summary
        $db = Zend_Registry::get('dbAdapter');
        //$billingcompany=  $this->provider['billingcompany_id'];
        /********************************Nothing Changed***********************************************/
        $sql = 'SELECT
                            DATE_FORMAT( date_insurance_payment_received , \'%c/%Y\' ) AS Month,
                            SUM( amount_insurance_payment_received  )AS Collections
                         FROM claim,encounter
                        WHERE claim.id=encounter.claim_id
                        AND date_insurance_payment_received  >=? AND date_insurance_payment_received  <?
                        AND encounter.provider_id=' . $provider_id . '
                         GROUP BY DATE_FORMAT( date_insurance_payment_received , \'%c/%Y\' ) ';

        $cur_month = date('m');
        $end_month = $cur_month - ($cur_month + 2) % 3;
        $result = $db->query($sql, array((date('Y') - 1) . '-' . $end_month . '-1', date('Y') . '-' . $end_month . '-1'));
        $rows = $result->fetchAll();
        $this->Quarterly_Billing_Fee = $this->data_format_quarter($rows, $end_month);
        /********************************Nothing Changed***********************************************/
        
         /*********************Ready to operate By <Yu Lang>  2012-07-08*****************************/
         if($provider_id!=0){
        /*********************New claim summary  2012-07-011*****************************/
          $sql = 'SELECT
                            DATE_FORMAT(encounter.start_date_1, \'%c/%Y\' ) AS Month,
                            count(*) AS Claims,
                            SUM(claim.total_charge )AS Amount_Billed,
                            SUM(claim.amount_paid) AS Amount_Collected
                         FROM claim,encounter
                         WHERE claim.id=encounter.claim_id
                         AND encounter.provider_id=' . $provider_id . '
                         AND encounter.start_date_1 >=? AND encounter.start_date_1 <?
                         GROUP BY DATE_FORMAT( encounter.start_date_1, \'%c/%Y\' ) ';

         }else{
             if($role=='guest'){
                $sql = 'SELECT
                            DATE_FORMAT(encounter.start_date_1, \'%c/%Y\' ) AS Month,
                            count(*) AS Claims,
                            SUM(claim.total_charge )AS Amount_Billed,
                            SUM(claim.amount_paid) AS Amount_Collected
                         FROM claim,encounter,provider
                         WHERE claim.id=encounter.claim_id
                         AND encounter.provider_id=provider.id AND provider.billingcompany_id='.$billingcompany_id.' AND provider.id in (?)
                         AND encounter.start_date_1 >=? AND encounter.start_date_1 <? 
                         GROUP BY DATE_FORMAT( encounter.start_date_1, \'%c/%Y\' ) ';
             }
             else{
                 $sql = 'SELECT
                            DATE_FORMAT(encounter.start_date_1, \'%c/%Y\' ) AS Month,
                            count(*) AS Claims,
                            SUM(claim.total_charge )AS Amount_Billed,
                            SUM(claim.amount_paid) AS Amount_Collected
                         FROM claim,encounter,provider
                         WHERE claim.id=encounter.claim_id
                         AND encounter.provider_id=provider.id AND provider.billingcompany_id='.$billingcompany_id.'
                         AND encounter.start_date_1 >=? AND encounter.start_date_1 <? 
                         GROUP BY DATE_FORMAT( encounter.start_date_1, \'%c/%Y\' ) ';
             }
         }
        $rows=array();
        // $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '-1', date('Y-m') . '-1'));
         if($role!='guest'||$provider_id!=0){
            $result = $db->query($sql, array(($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
            $rows = $result->fetchAll();
         }else{
             if($provider_id_list!=null){
         
                 for($i=0;$i<count($provider_id_list);$i++){
                     $result = $db->query($sql, array($provider_id_list[$i],($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
                    $temp1=$result->fetchAll();
                     $rows = array_merge($rows,$temp1);
                     
                 }
                 
                 
             }
         }
        $temp = $this->data_format_month($rows, 0,$year,$month);
        $this->claims_summary = $this->data_format_month($rows, 0,$year,$month);
        
        
        /*********************New claim summary  2012-07-011*****************************/
        
       /* 
        $sql = 'SELECT
                            DATE_FORMAT(encounter.start_date_1, \'%c/%Y\' ) AS Month,
                            count(*) AS Claims,
                            SUM(claim.total_charge )AS Amount_Billed,
                            SUM(claim.amount_insurance_payment_received) AS Amount_Collected
                         FROM claim,encounter
                         WHERE claim.id=encounter.claim_id
                         AND encounter.provider_id=' . $provider_id . '
                         AND encounter.start_date_1 >=? AND encounter.start_date_1 <=?
                         GROUP BY DATE_FORMAT( encounter.start_date_1, \'%c/%Y\' ) ';


        // $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '-1', date('Y-m') . '-1'));
        $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '01', date('Y-m-d')));
        $rows = $result->fetchAll();
        $this->claims_summary = $this->data_format_month($rows, 0);
        
         */
        
        /*********************New claim collections  2012-07-011*****************************/
        if($provider_id!=0){
            /*
            $sql = 'SELECT DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' ) AS Month,
                SUM(co_amount.amount_paid) AS Collection 

                FROM (SELECT patientpayments.amount AS amount_paid , patientpayments.date AS amount_paid_date FROM patientpayments
                WHERE claim_id in
                (SELECT claim_id FROM encounter WHERE provider_id =  '. $provider_id . ')
                union all
                SELECT insurancepayments.amount AS amount_paid, insurancepayments.date AS amount_paid_date FROM insurancepayments
                WHERE claim_id in
                (SELECT claim_id FROM encounter WHERE provider_id = '. $provider_id . ')
                )AS co_amount

                WHERE co_amount.amount_paid_date  >=? AND co_amount.amount_paid_date  <?
                GROUP BY DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' ) ' ; 
             * 
             */
            $sql = 'SELECT DATE_FORMAT(datetime, \'%c/%Y\') AS Month, SUM(amount) AS Collection 
                FROM payments
                WHERE payments.serviceid=0 and payments.from <> \'Biller Adjustment\' and claim_id in
                (SELECT claim_id FROM encounter WHERE provider_id = ' . $provider_id. ') and              
                datetime  >= ? AND datetime  < ?
                GROUP BY DATE_FORMAT(datetime, \'%c/%Y\')';
        }else {
              /*  
              $sql = 'SELECT DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' ) AS Month,
                SUM(co_amount.amount_paid) AS Collection 

                FROM (SELECT patientpayments.amount AS amount_paid , patientpayments.date AS amount_paid_date FROM patientpayments
                WHERE claim_id in
                (SELECT claim_id FROM encounter,provider WHERE encounter.provider_id = provider.id AND  provider.billingcompany_id='.$billingcompany_id.' )
                union all
                SELECT insurancepayments.amount AS amount_paid, insurancepayments.date AS amount_paid_date FROM insurancepayments
                WHERE claim_id in
                (SELECT claim_id FROM encounter,provider WHERE encounter.provider_id =provider.id AND  provider.billingcompany_id='.$billingcompany_id.' )
                )AS co_amount

                WHERE co_amount.amount_paid_date  >=? AND co_amount.amount_paid_date  <?
                GROUP BY DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' ) ' ; 
               * 
               */
            if('guest'!=$role){
              $sql = 'SELECT DATE_FORMAT(datetime, \'%c/%Y\') AS Month, SUM(amount) AS Collection 
                FROM payments
                WHERE  payments.serviceid=0 and payments.from <> \'Biller Adjustment\' and claim_id in
                (SELECT claim_id FROM encounter, provider WHERE encounter.provider_id = provider.id AND  provider.billingcompany_id='.$billingcompany_id.') and              
                datetime  >= ? AND datetime  < ?
                GROUP BY DATE_FORMAT(datetime, \'%c/%Y\')';
            }else{
               $sql = 'SELECT DATE_FORMAT(datetime, \'%c/%Y\') AS Month, SUM(amount) AS Collection 
                FROM payments
                WHERE payments.serviceid=0 and payments.from <> \'Biller Adjustment\' and claim_id in
                (SELECT claim_id FROM encounter, provider WHERE encounter.provider_id = provider.id AND  provider.billingcompany_id='.$billingcompany_id.' and provider.id in (?)) and              
                datetime  >= ? AND datetime  < ? 
                GROUP BY DATE_FORMAT(datetime, \'%c/%Y\')';
            }
        }
        $rows=array();
        if('guest'!=$role||$provider_id!=0){
             $result = $db->query($sql, array(($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
             //$result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '-1' , date('Y-m-d') ));
              $rows = $result->fetchAll();
        }else{
            
            if($provider_id_list!=null){
                // $temp=null;
                 for($i=0;$i<count($provider_id_list);$i++){
                     $result = $db->query($sql, array($provider_id_list[$i],($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
                //$result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '-1' , date('Y-m-d') ));
                    $temp1=$result->fetchAll();
                    $rows = array_merge($rows,$temp1);
                 }
                
            }
        }
         $temp = $this->data_format_month($rows, 1,$year,$month);
         $this->collection_summary = $this->data_format_month($rows, 1,$year,$month);
        /*********************New claim collections  2012-07-011*****************************/
        
  /*      
        $sql = 'SELECT
                            DATE_FORMAT( date_insurance_payment_received , \'%c/%Y\' ) AS Month,
                            SUM( amount_insurance_payment_received  )AS Collection
                         FROM claim,encounter
                        WHERE claim.id=encounter.claim_id
                        AND date_insurance_payment_received  >=? AND date_insurance_payment_received  <=?
                        AND encounter.provider_id=' . $provider_id . '
                         GROUP BY DATE_FORMAT( date_insurance_payment_received , \'%c/%Y\' ) ';


        $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') . '01' , date('Y-m-d') ));
        $rows = $result->fetchAll();
        $this->collection_summary = $this->data_format_month($rows, 1);
        */
         /*********************Ready to operate By <Yu Lang>  2012-07-08*****************************/

//claims details
        
    /********************* Change DOB to MRN  2012-07-08*****************************/       
      if($provider_id==0){
          if('guest'!=$role){
                $sql = 'SELECT
              SUBSTRING(CONCAT(patient.last_name,\',\',patient.first_name),1,25) As Name,
              patient.account_number AS MRN,
              SUBSTRING(insurance.insurance_display,1,25) AS Insurance,
              DATE_FORMAT(encounter.start_date_1, \'%m/%d/%y\' ) AS DOS,
              FORMAT(claim.total_charge,2) AS Billed_Amount,

              FORMAT(co_amount.paid,2) AS Payment,
              DATE_FORMAT(co_amount.amount_paid_date,\'%m/%d/%y\') AS Payment_Date,

             SUBSTRING( CONCAT(renderingprovider.last_name,\' \',SUBSTRING(renderingprovider.first_name,1,1)) ,1,10)AS Rendering_Provider,
              SUBSTRING(CONCAT(referringprovider.last_name,\' \',SUBSTRING(referringprovider.first_name,1,1)) ,1,10)AS Referring_Provider,
              claim.claim_status AS Claim_Status,
              encounterinsured.type AS Encounterinsured_Type,
              provider.short_name

                FROM  provider,encounter,encounterinsured,patient,insured,insurance,renderingprovider,referringprovider,claim LEFT JOIN

                (SELECT payments.amount AS paid, payments.datetime AS amount_paid_date, payments.claim_id AS amount_claim_id  FROM payments
               WHERE  payments.serviceid=0 and payments.from <> \'Biller Adjustment\' and claim_id in
               (SELECT claim_id FROM encounter,provider WHERE encounter.provider_id =provider.id AND  provider.billingcompany_id='.$billingcompany_id.')
               )AS co_amount

                on claim.id = co_amount.amount_claim_id

        WHERE  encounter.provider_id =provider.id AND  provider.billingcompany_id='.$billingcompany_id.' AND
              provider.id=encounter.provider_id AND
              claim.id=encounter.claim_id AND
              encounter.patient_id=patient.id AND
              encounterinsured.encounter_id = encounter.id AND
              encounterinsured.insured_id = insured.id AND
              insured.insurance_id=insurance.id AND
              encounter.renderingprovider_id=renderingprovider.id AND
                          encounter.start_date_1>=? AND
                encounter.start_date_1 <? AND
                  encounter.referringprovider_id=referringprovider.id order by encounter.start_date_1, co_amount.amount_paid_date ';
          }else{
               $sql = 'SELECT
              SUBSTRING(CONCAT(patient.last_name,\',\',patient.first_name),1,25) As Name,
              patient.account_number AS MRN,
              SUBSTRING(insurance.insurance_display,1,25) AS Insurance,
              DATE_FORMAT(encounter.start_date_1, \'%m/%d/%y\' ) AS DOS,
              FORMAT(claim.total_charge,2) AS Billed_Amount,

              FORMAT(co_amount.paid,2) AS Payment,
              DATE_FORMAT(co_amount.amount_paid_date,\'%m/%d/%y\') AS Payment_Date,

             SUBSTRING( CONCAT(renderingprovider.last_name,\' \',SUBSTRING(renderingprovider.first_name,1,1)) ,1,10)AS Rendering_Provider,
              SUBSTRING(CONCAT(referringprovider.last_name,\' \',SUBSTRING(referringprovider.first_name,1,1)) ,1,10)AS Referring_Provider,
              claim.claim_status AS Claim_Status,
              encounterinsured.type AS Encounterinsured_Type,
              provider.short_name

                FROM  provider,encounter,encounterinsured,patient,insured,insurance,renderingprovider,referringprovider,claim LEFT JOIN

                (SELECT payments.amount AS paid, payments.datetime AS amount_paid_date, payments.claim_id AS amount_claim_id  FROM payments
               WHERE  payments.serviceid=0 and payments.from <> \'Biller Adjustment\' and claim_id in
               (SELECT claim_id FROM encounter,provider WHERE encounter.provider_id =provider.id AND  provider.billingcompany_id='.$billingcompany_id.')
               )AS co_amount

                on claim.id = co_amount.amount_claim_id

        WHERE  encounter.provider_id =provider.id AND  provider.billingcompany_id='.$billingcompany_id.' AND
              provider.id=encounter.provider_id AND provider.id in (?) and 
              claim.id=encounter.claim_id AND
              encounter.patient_id=patient.id AND
              encounterinsured.encounter_id = encounter.id AND
              encounterinsured.insured_id = insured.id AND
              insured.insurance_id=insurance.id AND
              encounter.renderingprovider_id=renderingprovider.id AND
                          encounter.start_date_1>=? AND
                encounter.start_date_1 <? AND
                  encounter.referringprovider_id=referringprovider.id order by encounter.start_date_1, co_amount.amount_paid_date ';
          }
      }
      else{
            $sql = 'SELECT
      SUBSTRING(CONCAT(patient.last_name,\',\',patient.first_name),1,25) As Name,
      patient.account_number AS MRN,
      SUBSTRING(insurance.insurance_display,1,25) AS Insurance,
      DATE_FORMAT(encounter.start_date_1, \'%m/%d/%y\' ) AS DOS,
      FORMAT(claim.total_charge,2) AS Billed_Amount,
    
      FORMAT(co_amount.paid,2) AS Payment,
      DATE_FORMAT(co_amount.amount_paid_date,\'%m/%d/%y\') AS Payment_Date,
      
     SUBSTRING( CONCAT(renderingprovider.last_name,\' \',SUBSTRING(renderingprovider.first_name,1,1)) ,1,10)AS Rendering_Provider,
      SUBSTRING(CONCAT(referringprovider.last_name,\' \',SUBSTRING(referringprovider.first_name,1,1)) ,1,10)AS Referring_Provider,
      claim.claim_status AS Claim_Status,
      encounterinsured.type AS Encounterinsured_Type,
      provider.short_name
        
        FROM  provider,encounter,encounterinsured,patient,insured,insurance,renderingprovider,referringprovider,claim LEFT JOIN
        
       (SELECT payments.amount AS paid , payments.datetime AS amount_paid_date, payments.claim_id AS amount_claim_id  FROM payments
       WHERE  payments.serviceid = 0 and payments.from <> \'Biller Adjustment\' and claim_id in
       (SELECT claim_id FROM encounter WHERE provider_id =   '. $provider_id . ')        
        )AS co_amount  
        on claim.id = co_amount.amount_claim_id
                
WHERE  encounter.provider_id=' . $provider_id .' AND
     provider.id=encounter.provider_id AND
      claim.id=encounter.claim_id AND
      encounter.patient_id=patient.id AND
      encounterinsured.encounter_id = encounter.id AND
      encounterinsured.insured_id = insured.id AND
      insured.insurance_id=insurance.id AND
      encounter.renderingprovider_id=renderingprovider.id AND
                  encounter.start_date_1>=? AND
        encounter.start_date_1 <? AND
          encounter.referringprovider_id=referringprovider.id order by encounter.start_date_1, co_amount.amount_paid_date ';
          
      }
      $rows=array();
      if('guest'!=$role||$provider_id!=0){
           $result = $db->query($sql, array(($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
      //  $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') .'-'. '1', date('Y-m-d') ));
           $rows = $result->fetchAll();
      }else{
        /*Change the date judgement*/
          if($provider_id_list!=null){
             
              for($i=0;$i<count($provider_id_list);$i++){
                     $result = $db->query($sql, array($provider_id_list[$i],($year - 1) . '-' . $month . '-1',$year . '-' . $month . '-1'));
                    $temp1=$result->fetchAll();
                    $rows = array_merge($rows,$temp1);
              }  
            
              //  $result = $db->query($sql, array((date('Y') - 1) . '-' . date('m') .'-'. '1', date('Y-m-d') ));
               // $rows = $result->fetchAll();
          }
      }
        
        $results_row = array();
       
        $index = 0;
        for($i = 0; $i < count($rows); $i++)
        {
            $tmp_row = $rows[$i];
            $tmp_status = substr($rows[$i]['Claim_Status'],0,14);

            if( ($rows[$i]['Encounterinsured_Type']== "primary") && ($tmp_status == 'open_secondary'))
            {
                continue;
            }
            else if($rows[$i]['Encounterinsured_Type']== 'secondary' && $tmp_status != 'open_secondary')
            {
                continue;
            }
            $results_row[$index]['Name'] = $rows[$i]['Name'];
            $results_row[$index]['MRN'] = $rows[$i]['MRN'];
            $results_row[$index]['Insurance'] = $rows[$i]['Insurance'];
            $results_row[$index]['DOS'] = $rows[$i]['DOS'];
            $results_row[$index]['Billed_Amount'] = $rows[$i]['Billed_Amount'];
            $results_row[$index]['Rendering_Provider'] = $rows[$i]['Rendering_Provider'];
            $results_row[$index]['Referring_Provider'] = $rows[$i]['Referring_Provider'];
            $results_row[$index]['Claim_Status'] = $rows[$i]['Claim_Status'];
            $results_row[$index]['Payment'] = $rows[$i]['Payment'];
            $results_row[$index]['Payment_Date'] = $rows[$i]['Payment_Date'];
            $results_row[$index]['short_name']=$rows[$i]['short_name'];
            $index++;           
        }
        
        
        
        $tmp_results = $this->data_format_Claim_Details($results_row);
        $this->claims_details = $tmp_results;
        /********************* Change DOB to MRN  2012-07-08*****************************/  
        
        
      /*********************Ready to operate By <Yu Lang>  2012-07-08*****************************/
    }

    public function provider_name() {
        return $this->provider['provider_name'];
    }

    public function billingcompany_name() {
        return $this->billingcompany_name;
    }

    public function options_id() {
        return $this->provider['options_id'];
    }

    public function rate() {
        return $this->options['provider_invoice_rate'];
    }

    public function claims_summary_header() {
        return $this->claims_summary_header;
    }

    public function claims_summary_field() {
        return $this->claims_summary_field;
    }

    public function claims_summary() {
        return $this->claims_summary;
    }

    public function collections_summary_field() {
        return $this->collections_summary_field;
    }

    public function collections_summary_header() {
        return $this->collections_summary_header;
    }

    public function collections_summary() {
        return $this->collection_summary;
    }

    public function Quarterly_Billing_Fee_header() {
        return $this->Quarterly_Billing_Fee_header;
    }

    public function Quarterly_Billing_Fee_field() {
        return $this->Quarterly_Billing_Fee_field;
    }

    public function Quarterly_Billing_Fee() {
        return $this->Quarterly_Billing_Fee;
    }

    public function claims_details_field() {
        return $this->claims_details_field;
    }

    public function claims_details_header() {
        $tt = $this->cl;
        return $this->claims_details_header;
    }

    public function claims_details() {
        return $this->claims_details;
    }

    protected function data_format_month($rows, $type,$year,$month) {
        $cur_year = $year;
        $cur_month = $month;
        $data = array();
        //totals:table-1
        $cliams_total = 0;
        $Amount_Billed_total = 0;
        $Amount_Collected_total = 0;
        //totals:table-2
        $Collection_total = 0;
        for ($i = 0; $i < 12; $i++) {
            $month = ($cur_month + $i);
            $year = $cur_year - 1;
            if ($month > 12) {
                $month = $month - 12;
                $year = $cur_year;
            }

            $tmp = $month . '/' . $year;
            $flag = 0;
            $r = array();

            foreach ($rows as $row) {
                if ($row['Month'] == $tmp) {
                    $flag = 1;
                    $r = $row;
                    //calculte totals and add '$'
                    if ($type == 0) {
                        //Totals
                        $cliams_total = $cliams_total + $r['Claims'];
                        $Amount_Billed_total = $Amount_Billed_total + $r['Amount_Billed'];
                        $Amount_Collected_total = $Amount_Collected_total + $r['Amount_Collected'];
                        if ($r['Amount_Billed'] == 0)
                            $r['Amount_Billed'] = '';
                        else
                            $r['Amount_Billed'] = '$' . number_format($r['Amount_Billed'], 2);
                        if ($r['Amount_Collected'] == 0)
                            $r['Amount_Collected'] = '';
                        else
                            $r['Amount_Collected'] = '$' . number_format($r['Amount_Collected'], 2);
                    }
                    if ($type == 1) {
                        $Collection_total = $Collection_total + $r['Collection'];
                        $r['Collection'] = '$' . number_format($r['Collection'], 2);
                    }
                    break;
                }
            }
            if ($flag == 1)
                array_push($data, $r);
            else {
                if ($type == 0)
                    array_push($data,
                            array(
                                'Month' => $tmp,
                                'Claims' => '',
                                'Amount_Billed' => '',
                                'Amount_Collected' => ''
                    ));
                if ($type == 1)
                    array_push($data,
                            array(
                                'Month' => $tmp,
                                'Collection' => ''
                    ));
            }
            //totals
            if ($i == 11) {
                if ($type == 0) {
                    array_push($data,
                            array(
                                'Month' => 'Totals',
                                'Claims' => $cliams_total,
                                'Amount_Billed' => '$' . number_format($Amount_Billed_total, 2),
                                'Amount_Collected' => '$' . number_format($Amount_Collected_total, 2)
                    ));
                }
                if ($type == 1) {
                    array_push($data,
                            array(
                                'Month' => 'Totals',
                                'Collection' => '$' . number_format($Collection_total, 2)
                    ));
                }
            }
        }


        return $data;
    }

    protected function data_format_quarter($rows, $end_month) {
        $cur_year = date('Y');
        $data = array();
        $quarter_collection = 0;
        for ($i = 1; $i <= 12; $i++) {
            $quarter = floor($end_month / 3) + floor(($i + 2) / 3);
            $y = $cur_year - 1;
            if ($quarter > 4) {
                $quarter = $quarter - 4;
                $y = $cur_year;
            }
            $quarter = 'Q' . $quarter . '/' . $y;

            $month = ($end_month + $i - 1);
            $year = $cur_year - 1;
            if ($month > 12) {
                $month = $month - 12;
                $year = $cur_year;
            }
            $tmp = $month . '/' . $year;
            $flag = 0;
            $r = array();
            foreach ($rows as $row) {
                if ($row['Month'] == $tmp) {
                    $quarter_collection = $quarter_collection + $row['Collections'];
                }
            }
            if ($i % 3 == 0) {
                if ($quarter_collection == 0)
                    array_push($data,
                            array(
                                'quarter' => $quarter,
                                'Collections' => '',
                                'Fee_Charged' => '',
                                'Fee_Paid' => ''
                    ));
                if ($quarter_collection != 0) {
                    array_push($data,
                            array(
                                'quarter' => $quarter,
                                'Collections' => '$' . number_format($quarter_collection, 2),
                                'Fee_Charged' => '$' . number_format(($quarter_collection * $this->rate() / 100), 2),
                                'Fee_Paid' => ''
                    ));
                    $quarter_collection = 0;
                }
            }
        }
        return $data;
    }

    protected function data_format_Claim_Details($rows) {
        $data = array();
        foreach ($rows as $row) {
            if ($row['Payment'] == 0)
                $row['Payment'] = '';
            else
                $row['Payment'] = '$' . $row['Payment'];
            if ($row['Billed_Amount'] == 0)
                $row['Billed_Amount'] = '';
            else
                $row['Billed_Amount'] = '$' . $row['Billed_Amount'];
            $pos = strpos($row['Claim_Status'], '_');
//            if ($pos > 0 && ($pos < 4))
//                $row['Claim_Status'] = 'closed';
//            $pos = strpos($row['Claim_Status'], 'pen');
//            if ($pos > 0 && ($pos < 4))
//                $row['Claim_Status'] = 'open';
            if($pos>=0)
                $row['Claim_Status'] = substr($row['Claim_Status'], 0,$pos);
            array_push($data, $row);
        }
        return $data;
    }

}

function gen_report($billingcompany_id,$provider_id,$year,$month, $dir,$user_id,$role) {
    
    $report = new Report($billingcompany_id,$provider_id,$year,$month,$user_id,$role);
    $provider_name = $report->provider_name();
    
        
         $dir_billingcompany = $dir. '/' .$billingcompany_id ;
         if(!is_dir($dir_billingcompany))
         {
                   mkdir($dir_billingcompany);
         }
         $dir_bc_reports = $dir_billingcompany. '/reports';
         if(!is_dir($dir_bc_reports))
         {
                mkdir($dir_bc_reports);
         }
            
                   
    if($provider_id!=0)
    $file_name = $dir_bc_reports.'/' . $provider_name . '-' . date('Ymd') . '.pdf';
    else
       $file_name = $dir_bc_reports.'/' . 'All Providers' . '-' . date('Ymd') . '.pdf';

    $pdf = new PDF();
    $pdf->AddPage();
    //report title
    $pdf->SetFont('Arial', 'B', 18);
    if($provider_id!=0)
    $pdf->Cell(0, 6, 'Billing Report - ' . $provider_name, 0, 1, 'C');
    else
    $pdf->Cell(0, 6, 'Billing Report - ' . 'All Providers', 0, 1, 'C');   
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, '-	By '.$report->billingcompany_name().'  '.date('m').'/'.date('d').'/'.date('Y'), 0, 1, 'C');
    $pdf->Ln(10);

    //report-1:Collections Summary
 //   $pdf->AddPage();
    $pdf->SetFont('Arial', 'UB', 14);
    $pdf->Cell(0, 6, 'Collections Summary', 0, 1, 'C');
    $pdf->Ln(5);
    $options = array(
        'fields' => $report->collections_summary_field(),
        'header' => $report->collections_summary_header(),
        'font_size' => array(
            'header' => 14,
            'body' => 11
        ),
        'width' => array('40%', '60%')
    );
    $pdf->Table($report->collections_summary(), $options);
        //report-2:claims summary
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'UB', 14);
    $pdf->Cell(0, 6, 'Claims Summary', 0, 1, 'C');
    $pdf->Ln(5);
    $options = array(
        'fields' => $report->claims_summary_field(),
        'header' => $report->claims_summary_header(),
        'font_size' => array(
            'header' => 14,
            'body' => 11
        ),
        'width' => array('20%', '20%', '30%', '30%')
    );
    $pdf->Table($report->claims_summary(), $options);
//    //report-3: Quarterly Billing Fee
//    $pdf->AddPage();
//    $pdf->SetFont('Arial', 'UB', 14);
//    $pdf->Cell(0, 6, 'Quarterly Billing Fee', 0, 1, 'C');
//    $pdf->Ln(5);
//    $options = array(
//        'fields' => $report->Quarterly_Billing_Fee_field(),
//        'header' => $report->Quarterly_Billing_Fee_header(),
//        'font_size' => array(
//            'header' => 14,
//            'body' => 11
//        ),
//        'width' => array('20%', '20%', '30%', '30%')
//    );
//    $pdf->Table($report->Quarterly_Billing_Fee(), $options);
    //report-4: Claims Details
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'UB', 14);
    $pdf->Cell(0, 6, 'Claims Details', 0, 1, 'C');
    $pdf->Ln(5);
    $options = array(
        'fields' => $report->claims_details_field(),
        'header' => $report->claims_details_header(),
        'font_size' => array(
            'header' => 8,
            'body' => 8
        ),
        'width' => array('15%', '7%', '15%', '7%', '7%', '7%', '7%', '10%', '10%', '7%','10%')
    );
    $pdf->Table($report->claims_details(), $options);
    $pdf->Output($file_name);
    $pdf->Close();
    return $file_name;
}

?>
