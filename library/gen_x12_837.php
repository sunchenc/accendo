<?php

/*
 * gen_x12_837
 * generate EDI file
 * author:Xinwang Qiao
 * May,2011
 */
require_once 'helper.php';
require_once 'claim.php';

/**
 *
 * @param <type> $options
 * @param <type> $encounter_claim:always false,unusefull for version 0.9
 * @return array
 * author: qiao
 * date:10/03/2011
 */
function gen_x12_837($options, $dir, $billingcompany_id , $encounter_claim=false) {

//variable declaration
    $ret = array();
    $re = array();
    /*the return paremter changed by<PanDazhao> */
    
    /**YuLang 2012-09-12**/
    /**************************Generate Log file***************************/
     $data = array();
     $fields = array('num', 'date', 'time', 'name', 'mrn', 'dos', 'insurance');
     $display_fields = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS', 'Insurance');
    /**************************Generate Log file***************************/
     
    /*******************Claim resort*****************/
    $claims_tmp = array();  
    $ret_index = 0;
   /*******************Claim resort*****************/
   
   /*******************Claim resort*****************/
        foreach ($options as $index => $option)
        {
            $claim = new claim($option);
            $new_claim['ret_order'] = $ret_index;
            $new_claim['claim'] = $claim;
            array_push($claims_tmp, $new_claim);
            $ret_index = $ret_index + 1;
            array_push($ret, 0);
        }
        
        
        $billingcompany_id = $claims_tmp[0]['claim']->billingcompany_id();
        
        for($i = 0; $i < $ret_index - 1; $i++)
        {
            for($j = $i+1; $j < $ret_index; $j++)
            {
                $a = $claims_tmp[$i]['claim']->payerName();
                $b = $claims_tmp[$j]['claim']->payerName();
                if($a > $b)
                {
                    $temp = $claims_tmp[$i];
                    $claims_tmp[$i] = $claims_tmp[$j];
                    $claims_tmp[$j] = $temp;
                }
            }
        }
        
        $index = 0;
        $edi_string = '';
        try{  
                $edi_dir = $dir. '/' . $billingcompany_id;
                if(!is_dir($edi_dir)){
                    mkdir($edi_dir);
                }
                $edi_dir = $dir. '/' . $billingcompany_id .  '/edi';
                if(!is_dir($edi_dir)){
                    mkdir($edi_dir);
                }
                $file_name = $edi_dir . '/' . date('YmdHis') . '.txt';
                
              
//                $log_dir = $dir. '/billingcompany';
//                if (!is_dir($log_dir)) {
//                       mkdir($log_dir);
//                }
                
                $log_dir = $log_dir. '/'.$billingcompany_id;
                if (!is_dir($log_dir)) {
                        mkdir($log_dir);
                }  
                
                 $log_dir = $log_dir. '/billlog';
                if (!is_dir($log_dir)) {
                        mkdir($log_dir);
                }
                
                
                
                foreach ($claims_tmp as $index => $new_claim) { 
             
                $edi_string = $edi_string . gen_x12_837_single($new_claim['claim'], $index, count($options), $sub_counter); 
                $ret[$new_claim['ret_order']] = 1;
             
                $data[$index]['num'] = $index + 1;
                $data[$index]['date'] = date('m/d/Y');
                $data[$index]['time'] = date('H:i',time());
                $data[$index]['name'] = $new_claim['claim']->insuredLastName().' '.$new_claim['claim']->insuredFirstName();
                $data[$index]['mrn'] = "=\"".$new_claim['claim']->patient_account()."\"";
                $data[$index]['dos'] = $new_claim['claim']->getDOS();
                $data[$index]['insurance'] = $new_claim['claim']->displayName(); 
            }
         
                $fp = fopen($file_name, "w");
                fwrite($fp, $edi_string);
                fclose($fp);        
        }catch (Exception $e) {
         $ret[$new_claim['ret_order']] = 0;
       }
       
       
       
        /*************************************Write Log file********************************/
        $log_file_name = $log_dir.'/BillEDI.csv';
        $final_length = sizeof($fields);

    if (file_exists($log_file_name)) {
        $rp = fopen($log_file_name, 'r');
        $tmp_log_file = $log_dir . '/tmp.csv';
        $wp = fopen($tmp_log_file, 'w');


        for ($i = 0; $i < $final_length; $i++) {
            fwrite($wp, $display_fields[$i] . ",");
        }
        fwrite($wp, "\r\n");

        for ($i = 0; $i < $ret_index; $i++) {
            for ($j = 0; $j < $final_length; $j++) {
                $ttt = $data[$i][$fields[$j]];
                fwrite($wp, $data[$i][$fields[$j]] . ",");
            }
            fwrite($wp, "\r\n");
        }
        fwrite($wp, "\r\n");

        $bool_head_line = 0;

        $read_old_log = fgets($rp);
        while (!feof($rp)) {
            $read_old_log = fgets($rp);
            fwrite($wp, $read_old_log);
        }
        fclose($wp);
        fclose($rp);

        if (!unlink($log_file_name))
            echo ("Error deleting $log_file_name");
        else
            echo ("Deleted $log_file_name");

        rename($tmp_log_file, $log_file_name);
    }
    else {
        $fp = fopen($log_file_name, 'w');

        for ($i = 0; $i < $final_length; $i++) {
            fwrite($fp, $display_fields[$i] . ",");
        }
        fwrite($fp, "\r\n");


        for ($i = 0; $i < $ret_index; $i++) {
            for ($j = 0; $j < $final_length; $j++) {
                $ttt = $data[$i][$fields[$j]];
                fwrite($fp, $data[$i][$fields[$j]] . ",");
            }
            fwrite($fp, "\r\n");
        }
        fclose($fp);
    }
       
        
     /*******************Claim resort*****************/
    
    /*
    $edi_string = '';
    try {
        $file_name = $dir . '/edi/' . date('YmdHis') . '.edi';
        $sub_counter = submission_counter();
        foreach ($options as $index => $option) {
            $edi_string = $edi_string . gen_x12_837_single($option, $index, count($options), $sub_counter);
            array_push($ret, 1);
        }
        $fp = fopen($file_name, "w");
        fwrite($fp, $edi_string);
        fclose($fp);
    } catch (Exception $e) {
        array_push($ret, 0);
    }
    */
    
    $re['ret']=$ret;
    $re['file_name'] = $file_name;
    return $re;
}

function gen_x12_837_single($claim, $index, $array_count, $sub_counter, $encounter_claim=false) {
    //variable declaration
    $today = time();
    $out = '';
    $edicount = 0;
    
    /***********YuLang 2012-09-12******************/
    //$claim = new claim($option);

    /* ----------------------------------settings --------------------------------- */
//    $sub_counter = submission_counter();
    /* ----------------------------------end settings ---------------------------- */
    //makeup edi content
    //section:ISA
    //ISA13 unique number,9-digits
    $x12isa13 = substr(strval($sub_counter + 1000000000), 1, 10);
    $x12gs06 = $sub_counter * 100 + 1;
    if ($index == 0) {
        $out .= "ISA" .
                "*00" .
                "*          " .
                "*00" .
                "*          " .
                "*" . $claim->x12gsisa05() .
                "*" . $claim->x12gssenderid() .
                "*" . $claim->x12gsisa07() .
                "*" . $claim->x12gsreceiverid() .
                "*" . date('ymd', $today) .
                "*" . date('Hi', $today) .
                "*U" .
                "*00401" .
                "*" . $x12isa13 .
                "*" . $claim->x12gsisa14() .
                "*" . $claim->x12gsisa15() .
                "*:" .
                "~\n";
//section:GS

        $out .= "GS" .
                "*HC" .
                "*" . $claim->x12gsgs02() .
                "*" . trim($claim->x12gsreceiverid()) .
                "*" . date('Ymd', $today) .
                "*" . date('Hi', $today) .
                "*" . $x12gs06 .
                "*X" .
                "*" . $claim->x12gsversionstring() .
                "~\n";
//section:ST
        ++$edicount;
        $out .= "ST" .
                "*837" .
                "*43701" .
                "~\n";
    }
    //section:BHT
    $bht03 = $sub_counter;
    ++$edicount;
    $out .= "BHT" .
            "*0019" .
            "*00" .
            "*" . $bht03 .
            "*" . date('Ymd', $today) .
            "*" . date('Hi', $today) .
            ($encounter_claim ? "*RP" : "*CH") .
            "~\n";
//section: REF
    ++$edicount;
    $out .= "REF" .
            "*87" .
            "*" . $claim->x12gsversionstring() .
            "~\n";
    /* ----------------------------------Start 1000A Loop --------------------------------- */
    //Loop 1000A:billing provider/sender
    //section:NM1
    ++$edicount;
    //using : QIAO
    $provider_name = substr($claim->provider_name(), 0, 35);
    $out .= "NM1" . // Loop 1000A Submitter
            "*41" .
            "*2" .
            "*" . $provider_name .
            "*" .
            "*" .
            "*" .
            "*" .
            "*46" .
            "*" . $claim->provider_EDI();
    $out .= "~\n";

//    //commented:openEMR
//    $facilityName = substr($claim->facilityName(), 0, 35);
//    $out .= "NM1" . // Loop 1000A Submitter
//            "*41" .
//            "*2" .
//            "*" . $facilityName .
//            "*" .
//            "*" .
//            "*" .
//            "*" .
//            "*46";
//    if (trim($claim->x12gsreceiverid()) == '470819582') { // if ECLAIMS EDI
//        $out .= "*" . $claim->clearingHouseETIN();
//    } else {
//        $out .= "*" . $claim->billingFacilityETIN();
//    }
//    $out .= "~\n";
//section:PER
    ++$edicount;
    $out .= "PER" .
            "*IC" .
            "*" . $claim->billing_provider_name() .
            "*TE" .
            "*" . $claim->billing_provider_phone();
    if ($claim->billing_provider_email) {
        $out .= "*EM*" . $claim->billing_provider_email;
    }
    if ($claim->billing_provider_fax()) {
        $out .= "*FX*" . $claim->billing_provider_fax();
    }
//
//    if ($claim->x12gsper06()) {
//        $out .= "*ED*" . $claim->x12gsper06();
//    }
//    if ($claim->x12gsper07()) {
//        $out .= "*EM*" . $claim->x12gsper07();
//    }
//    if ($claim->x12gsper08()) {
//        $out .= "*FX*" . $claim->x12gsper08();
//    }
    $out .= "~\n";
    /* ----------------------------------End 1000A Loop --------------------------------- */
    /* ----------------------------------Start 1000B Loop --------------------------------- */
    //Loop 1000B:receiving party
//section:NM1
    ++$edicount;
    $out .= "NM1" . // Loop 1000B Receiver
            "*40" .
            "*2" .
            "*" . $claim->clearingHouseName() .
            "*" .
            "*" .
            "*" .
            "*" .
            "*46" .
            "*" . $claim->clearingHouseETIN() .
            "~\n";
    /* ----------------------------------End 1000B Loop --------------------------------- */
    /* ----------------------------------Start 2000A Loop --------------------------------- */
    //Loop 2000A:Billing/Pay-To Provider
    //section:HL
    $HLcount = 1;
    ++$edicount;
    $out .= "HL" .
            "*$HLcount" .
            "*" .
            "*20" .
            "*1" .
            "~\n";
    $HLBillingPayToProvider = $HLcount++;

    //section:PRV
    //many samples not contain this section,may be ignored
    /* ----------------------------------End 2000A Loop --------------------------------- */
    /* ----------------------------------Start 2010AA Loop --------------------------------- */
    //Loop 2010AA:Billing Provider Name
    //section:NM1
    ++$edicount;
    //Field length is limited to 35.
    $billing_provider_name = substr($claim->billing_provider_name(), 0, 35);
    $out .= "NM1" .
            "*85" .
            "*2" .
            "*" . $billing_provider_name .
            "*" .
            "*" .
            "*" .
            "*";
    $out .= "*XX*" . $claim->get_billing_provider_NPI();
    $out .= "~\n";
//section:N3 street address
    ++$edicount;
    $out .= "N3" .
            "*" . $claim->billing_provider_street() .
            "~\n";
//section:N4 the rest of address information
    ++$edicount;
    $out .= "N4" .
            "*" . $claim->billing_provider_city() .
            "*" . $claim->billing_provider_state() .
            "*" . $claim->billing_provider_zip() .
            "~\n";

    /**
     * REF section
     * Author:Qiao
     * modify time:6/16/2011
     * summary:according to Mr Chen's email,6/15/2011
     */
    /////////////////////start REF
    ++$edicount;
    $out .= "REF" .
            "*N5*" . $claim->get_billing_provider_NPI() .
            "~\n";
    ++$edicount;
    $out .= "REF" .
            "*G2*" . $claim->get_billing_provider_NPI() .
            "~\n";
    ++$edicount;
    $out .= "REF" .
            "*EI*" . $claim->provider_Tax_id_number() .
            "~\n";
    /////////////////////end REF
    //Section:PER:billing provider contact information
    ++$edicount;
    $out .= "PER" .
            "*IC" .
            "*" . $billing_provider_name;
    if ($claim->billing_provider_phone()) {
        $out.="*TE*" . $claim->billing_provider_phone();
    }
    if ($claim->billing_provider_fax()) {
        $out.="*FX*" . $claim->billing_provider_fax();
    }
    if ($claim->billing_provider_email()) {
        $out.="*EM*" . $claim->billing_provider_email();
    }
    $out .= "~\n";
    /* ----------------------------------End 2010AA Loop --------------------------------- */
//    /* ----------------------------------Start 2010AB Loop --------------------------------- */
////Loop 2010AB:pay-to provider name
//    //Section:NM1
//    ++$edicount;
//    //Field length is limited to 35.
//    $billingFacilityName = substr($claim->billingFacilityName(), 0, 35);
//    $out .= "NM1" .
//            "*87" .
//            "*2" .
//            "*" . $billingFacilityName .
//            "*" .
//            "*" .
//            "*" .
//            "*";
//    if ($claim->billingFacilityNPI())
//        $out .= "*XX*" . $claim->billingFacilityNPI();
//    else
//        $out .= "*24*" . $claim->billingFacilityETIN();
//    $out .= "~\n";
////section:N3
//    ++$edicount;
//    $out .= "N3" .
//            "*" . $claim->billingFacilityStreet() .
//            "~\n";
////section:N4
//    ++$edicount;
//    $out .= "N4" .
//            "*" . $claim->billingFacilityCity() .
//            "*" . $claim->billingFacilityState() .
//            "*" . $claim->billingFacilityZip() .
//            "~\n";
////section:REF
//    if ($claim->billingFacilityNPI() && $claim->billingFacilityETIN()) {
//        ++$edicount;
//        $out .= "REF" .
//                "*EI" .
//                "*" . $claim->billingFacilityETIN() .
//                "~\n";
//    }
//    /* ----------------------------------End 2010AB Loop --------------------------------- */
    /* ----------------------------------Start 2000B Loop --------------------------------- */
    /**
     * Loop 2000B:information about an individual subscriber,
     * AKA,a person we provided service to,
     * and the entity we would like to bill for services rendered
     * Broken into tow sub-loops:Loop 2000BA and Loop 2000BC
     * auther:Qiao
     */
    $PatientHL = 0;
//section:HL
    ++$edicount;
    $out .= "HL" . // Loop 2000B Subscriber HL Loop
            "*$HLcount" .
            "*$HLBillingPayToProvider" .
            "*22" .
            "*$PatientHL" .
            "~\n";

    $HLSubscriber = $HLcount++;

    /////////////////////start SBR
    ++$edicount;
    $out .= "SBR" . // Subscriber Information
            "*P" .
            "*" . $claim->insuredRelationship() .
            "*" .
            "*" . $claim->payerName() .
            "*" .
            "*" .
            "*" .
            "*" .
            "*BL" .
            "~\n";

    /////////////////////end SBR

    /* ----------------------------------End 2000B Loop --------------------------------- */
    /* ----------------------------------Start 2000BA Loop --------------------------------- */
    /**
     * section:NM1
     * a row for containing most of the primary identification information about the subscriber
     */
    ++$edicount;
    $out .= "NM1" . // Loop 2010BA Subscriber
            "*IL" .
            "*1" .
            "*" . $claim->insuredLastName() .
            "*" . $claim->insuredFirstName() .
            "*" .
            "*" .
            "*" .
            "*MI" .
            "*" . $claim->policyNumber() . //SSN
            "~\n";
    /**
     * section:N3
     * a row for containing the person being identified street address
     */
    ++$edicount;
    $out .= "N3" .
            "*" . $claim->insuredStreet() .
            "~\n";
    /**
     * section:N4
     * a row for containing the rest of the subscriber's address information
     */
    ++$edicount;
    $out .= "N4" .
            "*" . $claim->insuredCity() .
            "*" . $claim->insuredState() .
            "*" . $claim->insuredZip() .
            "~\n";
    /**
     * section:DMG
     * a row for containing some of the subscriber's demographics information
     */
    ++$edicount;
    $out .= "DMG" .
            "*D8" .
            "*" . $claim->insuredDOB() .
            "*" . $claim->insuredSex() .
            "~\n";
    /* ----------------------------------End 2000BA Loop --------------------------------- */
    /* ----------------------------------Start 2000BB Loop --------------------------------- */
    /* /**
     * Loop 2000BB:payer name,repeat one
     * This is the destination payer
     */
    ++$edicount;
    //Field length is limited to 35.
    $payerName = substr($claim->payerName(), 0, 35);
    $out .= "NM1" . // Loop 2010BB Payer
            "*PR" .
            "*2" .
            "*" . $payerName .
            "*" .
            "*" .
            "*" .
            "*" .
            "*PI" .
            // Zirmed ignores this if using payer name matching:
            //"*" . ($encounter_claim ? $claim->payerAltID() : $claim->payerID()) .
            "~\n";
    /**
     * section:N3
     * payer address
     */
    ++$edicount;
    $out .= "N3" .
            "*" . $claim->payerStreet() .
            "~\n";
    /**
     * section:N4
     * payer address
     */
    ++$edicount;
    $out .= "N4" .
            "*" . $claim->payerCity() .
            "*" . $claim->payerState() .
            "*" . $claim->payerZip() .
            "~\n";
    /* ----------------------------------End 2000BB Loop --------------------------------- */
    /* ----------------------------------Start 2000C Loop --------------------------------- */
    //Loop 2000C:patient hierarchical level
    //This HL is required when the patient is a different person than the subscriber.
    //section:HL
    if (!$claim->isSelfOfInsured()) {
        ++$edicount;
        $out .= "HL" . // Loop 2000C Patient Information
                "*$HLcount" .
                "*$HLSubscriber" .
                "*23" .
                "*0" .
                "~\n";
        /**
         * section:PAT
         * patient relationship to insured
         */
        $HLcount++;

        ++$edicount;
        $out .= "PAT" .
                "*" . $claim->insuredRelationship() .
                "~\n";
        /* ----------------------------------End 2000C Loop --------------------------------- */
        /* ----------------------------------Start 2000CA Loop --------------------------------- */
        /**
         * section:NM1
         * patient name
         */
        ++$edicount;
        $out .= "NM1" .
                "*QC" .
                "*1" .
                "*" . $claim->patientLastName() .
                "*" . $claim->patientFirstName() .
                "*" .
                "~\n";
        /**
         * section:N3,patient address
         */
        ++$edicount;
        $out .= "N3" .
                "*" . $claim->patientStreet() .
                "~\n";

        ++$edicount;
        $out .= "N4" .
                "*" . $claim->patientCity() .
                "*" . $claim->patientState() .
                "*" . $claim->patientZip() .
                "~\n";
        /**
         * section:DMG
         * a row for containing some of the patient's demographics information
         */
        ++$edicount;
        $out .= "DMG" .
                "*D8" .
                "*" . $claim->patientDOB() .
                "*" . $claim->patientSex() .
                "~\n";
    }
    //// end of patient different from insured
    /* ----------------------------------End 2000CA Loop --------------------------------- */
    /* ----------------------------------Start 2300 Loop --------------------------------- */
    /**
     * Loop 2300
     * contains information about services we have provided,
     * that we expect the payer to pay for.
     * break into two sub-loops
     * Loop 2300BA: describing the person whoes plan we are trying to bill
     * Loop 2300BC: describing the entity we are trying to charge
     */
    /**
     * section:CLM
     * contained information about the claim we are making.
     */
    ++$edicount;
    $out .= "CLM" .
            "*" . $claim->patient_account() . //reference file:"Reference-SampleAnsi837PrimaryClaimfile.txt":CLM*025411-129107
            "*" . sprintf("%.2f", $claim->total_charge()) . // Zirmed computes and replaces this
            "*" .
            "*" .
            "*" . sprintf('%02d', $claim->facilityPOS()) . "::1" . // Changed to correct single digit output
            "*Y" .
            "*A" .
            "*" . ($claim->billingFacilityAssignment() ? 'Y' : 'N') .
            "*Y" .
            "*C" .
            "~\n";
    /**
     * section DTP
     * contain the date of the "Onset of Similar Symptoms or Illness"
     */
//    if($claim->onsetDate())
//    {
//    ++$edicount;
//    $out .= "DTP" . // Date of Onset
//            "*431" .
//            "*D8" .
//            "*" . $claim->onsetDate() .
//            "~\n";
//    }
  if (strcmp($claim->facilityPOS(),'21') == 0) {
    ++$edicount;
    $out .= "DTP" .     // Date of Hospitalization
      "*435" .
      "*D8" .
      "*" . $claim->onsetDate() .
      "~\n";
  }
//
//  $patientpaid = $claim->patientPaidAmount();
//  if ($patientpaid != 0) {
//    ++$edicount;
//    $out .= "AMT" .     // Patient paid amount. Page 220.
//      "*F5" .
//      "*" . $patientpaid .
//      "~\n";
//  }
//
//    if ($claim->priorAuth()) {
//        ++$edicount;
//        $out .= "REF" . // Prior Authorization Number
//                "*G1" .
//                "*" . $claim->priorAuth() .
//                "~\n";
//    }
//
//    if ($claim->cliaCode() and $claim->claimType() === 'MB') {
//        // Required by Medicare when in-house labs are done.
//        ++$edicount;
//        $out .= "REF" . // Clinical Laboratory Improvement Amendment Number
//                "*X4" .
//                "*" . $claim->cliaCode() .
//                "~\n";
//    }
    ++$edicount;
    $out .= "REF" . // Clinical Laboratory Improvement Amendment Number
            "*D9" .
            "*" .
            "~\n";
    //  // Note: This would be the place to implement the NTE segment for loop 2300.
//  if ($claim->additionalNotes()) {
//    // Claim note.
//    ++$edicount;
//    $out .= "NTE" .     // comments box 19
//      "*" .
//      "*" . $claim->additionalNotes() .
//      "~\n";
//  }
    // Diagnoses, up to 8 per HI segment.
    $da = $claim->diagArray();
    $diag_type_code = 'BK';
    $tmp = 0;
    foreach ($da as $diag) {
        if ($tmp % 8 == 0) {
            if ($tmp)
                $out .= "~\n";
            ++$edicount;
            $out .= "HI";         // Health Diagnosis Codes
        }
        $diag = str_replace('.', '', $diag);
        $out .= "*$diag_type_code:" . $diag;
        $diag_type_code = 'BF';
        ++$tmp;
    }
    if ($tmp)
        $out .= "~\n";
    /* ----------------------------------End 2300 Loop --------------------------------- */
    /* ----------------------------------Start 2310A Loop --------------------------------- */
    /**
     * Loop 2310A
     * contains information about the referring provider
     * rendering provider
     */
    if ($claim->referringproviderNPI()) {
        // Medicare requires referring provider's name and UPIN.
        ++$edicount;
        $out .= "NM1" . // Loop 2310A Referring Provider
                "*DN" .
                "*1" .
                "*" . $claim->referrerLastName() .
                "*" . $claim->referrerFirstName() .
                "*" .
                "*" .
                "*";
        if ($claim->referringproviderNPI()) {
            $out .=
                    "*XX" .
                    "*" . $claim->referringproviderNPI();
        }
        $out .= "~\n";
    }
//        if ($claim->referrerTaxonomy()) {
//            ++$edicount;
//            $out .= "PRV" .
//                    "*RF" . // ReFerring provider
//                    "*ZZ" .
//                    "*" . $claim->referrerTaxonomy() .
//                    "~\n";
//        }
//
//        if ($claim->referrerUPIN()) {
//            ++$edicount;
//            $out .= "REF" . // Referring Provider Secondary Identification
//                    "*1G" .
//                    "*" . $claim->referrerUPIN() .
//                    "~\n";
//        }
//    }
//    /* ----------------------------------End 2310A Loop --------------------------------- */

    /* ----------------------------------Start 2310B Loop --------------------------------- */
    ++$edicount;
    $out .= "NM1" . // Loop 2310B Rendering Provider
            "*82" .
            "*1" .
            "*" . $claim->renderingprovider_LastName(1) .
            "*" . $claim->renderingprovider_FirstName(1) .
            "*" .
            "*" .
            "*";
    if ($claim->renderingprovider_NPI(1)) {
        $out .=
                "*XX" .
                "*" . $claim->renderingprovider_NPI(1);
    }

    $out .= "~\n";

    ++$edicount;
    $out .= "REF" .
            "*EI*" . $claim->provider_Tax_id_number() .
            "~\n";
    ++$edicount;
    $out .= "REF" .
            "*N5*" . $claim->renderingprovider_NPI(1) .
            "~\n";
    ++$edicount;
    $out .= "REF" .
            "*G2*" . $claim->renderingprovider_NPI(1) .
            "~\n";
//
//    /**
//     * section:PRV
//     * this section is used to hold the taxonmoy code of the provider identified in the 2310B NM1 section:rendering provider
//     */
//    if ($claim->providerTaxonomy()) {
//        ++$edicount;
//        $out .= "PRV" .
//                "*PE" . // PErforming provider
//                "*ZZ" .
//                "*" . $claim->providerTaxonomy() .
//                "~\n";
//    }
    /* ----------------------------------End 2310B Loop --------------------------------- */
    /* ----------------------------------Start 2310D Loop --------------------------------- */
    /**
     * Loop 2310D
     * Service facility location
     */
    //primary comment: Loop 2310D is omitted in the case of home visits (POS=12).
    //Qiao:need to refer OpenEMR,
    /**
     * what facilityPOS!=12 for?
     * Ansi837:
     * NM101:Entity Identifier Code
     * value=77:service location,use when other codes in this element do not applay
     * value=FA:Facility
     * Qiao 05/07/2011
     */
    if ($claim->facilityPOS() != 12) {
        ++$edicount;
        $out .= "NM1" . // Loop 2310D Service Location
                "*77" .
                "*2";
        //Field length is limited to 35.
        $facilityName = substr($claim->facilityName(), 0, 35);
        if ($claim->facilityName()) {
            $out .=
                    "*" . $facilityName;
        }

        $out .= "~\n";
        if ($claim->facilityStreet()) {
            ++$edicount;
            $out .= "N3" .
                    "*" . $claim->facilityStreet() .
                    "~\n";
        }
        if ($claim->facilityState()) {
            ++$edicount;
            $out .= "N4" .
                    "*" . $claim->facilityCity() .
                    "*" . $claim->facilityState() .
                    "*" . $claim->facilityZip() .
                    "~\n";
        }
    }
    /* ----------------------------------End 2310D Loop --------------------------------- */
//  /* ----------------------------------Start 2310E Loop --------------------------------- */
//    /**
//     * Loop 2310E, Supervising Provider
//     * Required when the rendering provider is supervised by a physician
//     */
//    if ($claim->supervisorLastName()) {
//        ++$edicount;
//        $out .= "NM1" .
//                "*DQ" . // Supervising Physician
//                "*1" . // Person
//                "*" . $claim->supervisorLastName() .
//                "*" . $claim->supervisorFirstName() .
//                "*" . $claim->supervisorMiddleName() .
//                "*" . // NM106 not used
//                "*";    // Name Suffix
//        if ($claim->supervisorNPI()) {
//            $out .=
//                    "*XX" .
//                    "*" . $claim->supervisorNPI();
//        } else {
//            $out .=
//                    "*34" .
//                    "*" . $claim->supervisorSSN();
//        }
//        $out .= "~\n";
//
//        if ($claim->supervisorNumber()) {
//            ++$edicount;
//            $out .= "REF" .
//                    "*" . $claim->supervisorNumberType() .
//                    "*" . $claim->supervisorNumber() .
//                    "~\n";
//        }
//    }
//    /* ----------------------------------End 2310B Loop --------------------------------- */
    /* ----------------------------------Start 2320 and 2330* Loop --------------------------------- */
    /**
     * comment:as for most of the EDI file,I have not catch this section,so comment them
     * Qiao
     * 05/07/2011
     */
//
//    $prev_pt_resp = $clm_total_charges; // for computation below
//    // Loops 2320 and 2330*, other subscriber/payer information.
//    //
//    for ($ins = 1; $ins < $claim->payerCount(); ++$ins) {
//
//        $tmp1 = $claim->claimType($ins);
//        $tmp2 = 'C1'; // Here a kludge. See page 321.
//        if ($tmp1 === 'CI')
//            $tmp2 = 'C1';
//        if ($tmp1 === 'AM')
//            $tmp2 = 'AP';
//        if ($tmp1 === 'HM')
//            $tmp2 = 'HM';
//        if ($tmp1 === 'MB')
//            $tmp2 = 'MB';
//        if ($tmp1 === 'MC')
//            $tmp2 = 'MC';
//        if ($tmp1 === '09')
//            $tmp2 = 'PP';
//        ++$edicount;
//        $out .= "SBR" . // Loop 2320, Subscriber Information - page 318
//                "*" . $claim->payerSequence($ins) .
//                "*" . $claim->insuredRelationship($ins) .
//                "*" . $claim->groupNumber($ins) .
//                "*" . $claim->groupName($ins) .
//                "*" . $tmp2 .
//                "*" .
//                "*" .
//                "*" .
//                "*" . $claim->claimType($ins) .
//                "~\n";
//
//        // Things that apply only to previous payers, not future payers.
//        //
//        if ($claim->payerSequence($ins) < $claim->payerSequence()) {
//
//            // Generate claim-level adjustments.
//            $aarr = $claim->payerAdjustments($ins);
//            foreach ($aarr as $a) {
//                ++$edicount;
//                $out .= "CAS" . // Previous payer's claim-level adjustments. Page 323.
//                        "*" . $a[1] .
//                        "*" . $a[2] .
//                        "*" . $a[3] .
//                        "~\n";
//            }
//
//            $payerpaid = $claim->payerTotals($ins);
//            ++$edicount;
//            $out .= "AMT" . // Previous payer's paid amount. Page 332.
//                    "*D" .
//                    "*" . $payerpaid[1] .
//                    "~\n";
//
//            // Patient responsibility amount as of this previous payer.
//            $prev_pt_resp -= $payerpaid[1]; // reduce by payments
//            $prev_pt_resp -= $payerpaid[2]; // reduce by adjustments
//            ++$edicount;
//            $out .= "AMT" . // Allowed amount per previous payer. Page 334.
//                    "*B6" .
//                    "*" . sprintf('%.2f', $payerpaid[1] + $prev_pt_resp) .
//                    "~\n";
//
//            ++$edicount;
//            $out .= "AMT" . // Patient responsibility amount per previous payer. Page 335.
//                    "*F2" .
//                    "*" . sprintf('%.2f', $prev_pt_resp) .
//                    "~\n";
//        } // End of things that apply only to previous payers.
//        ++$edicount;
//        $out .= "DMG" . // Other subscriber demographic information. Page 342.
//                "*D8" .
//                "*" . $claim->insuredDOB($ins) .
//                "*" . $claim->insuredSex($ins) .
//                "~\n";
//
//        ++$edicount;
//        $out .= "OI" . // Other Insurance Coverage Information. Page 344.
//                "*" .
//                "*" .
//                "*Y" .
//                "*B" .
//                "*" .
//                "*Y" .
//                "~\n";
//
//        ++$edicount;
//        $out .= "NM1" . // Loop 2330A Subscriber info for other insco. Page 350.
//                "*IL" .
//                "*1" .
//                "*" . $claim->insuredLastName($ins) .
//                "*" . $claim->insuredFirstName($ins) .
//                "*" . $claim->insuredMiddleName($ins) .
//                "*" .
//                "*" .
//                "*MI" .
//                "*" . $claim->policyNumber($ins) .
//                "~\n";
//
//        ++$edicount;
//        $out .= "N3" .
//                "*" . $claim->insuredStreet($ins) .
//                "~\n";
//
//        ++$edicount;
//        $out .= "N4" .
//                "*" . $claim->insuredCity($ins) .
//                "*" . $claim->insuredState($ins) .
//                "*" . $claim->insuredZip($ins) .
//                "~\n";
//
//        ++$edicount;
//        //Field length is limited to 35. See nucc dataset page 81 www.nucc.org
//        $payerName = substr($claim->payerName($ins), 0, 35);
//        $out .= "NM1" . // Loop 2330B Payer info for other insco. Page 359.
//                "*PR" .
//                "*2" .
//                "*" . $payerName .
//                "*" .
//                "*" .
//                "*" .
//                "*" .
//                "*PI" .
//                "*" . $claim->payerID($ins) .
//                "~\n";
//
//        // if (!$claim->payerID($ins)) {
//        //   $log .= "*** CMS ID is missing for payer '" . $claim->payerName($ins) . "'.\n";
//        // }
//        // Payer address (N3 and N4) are added below so that Gateway EDI can
//        // auto-generate secondary claims.  These do NOT appear in my copy of
//        // the spec!  -- Rod 2008-06-12
//
//        if (trim($claim->x12gsreceiverid()) == '431420764') { // if Gateway EDI
//            ++$edicount;
//            $out .= "N3" .
//                    "*" . $claim->payerStreet($ins) .
//                    "~\n";
//            //
//            ++$edicount;
//            $out .= "N4" .
//                    "*" . $claim->payerCity($ins) .
//                    "*" . $claim->payerState($ins) .
//                    "*" . $claim->payerZip($ins) .
//                    "~\n";
//        } // end Gateway EDI
//    } // End loops 2320/2330*.
    /* ----------------------------------End 2320 and 2330* Loop --------------------------------- */
    /* ----------------------------------Start 2400  Loop ---------------------------------
     * /**
     * Loop 2400
     * this is a loop which services are described in.The rendering provider for each service is described at this level
     */

    $proccount = $claim->procCount();
    $loopcount = 0;
    // Procedure loop starts here.
    //
    for ($prockey = 0; $prockey < $proccount; ++$prockey) {
        ++$loopcount;
        //section:LX,this segment begins with 1 and is incremented by one for each additional service line of a claim
        //The LX functions as a line counter
        ++$edicount;
        $tmp = $claim->cptKey($prockey); //added by ZW
        $arr = explode(':', $tmp); //added by ZW
        $out .= "LX" . // Loop 2400 LX Service Line. Page 398.
                "*$loopcount" .
                "~\n";
        /**
         * section:SV1
         * contains information about a specific service we have provided
         * SV102:contains the code for service performed,along with any modifiers,
         *       it always starts with "HC:",indicating this is a health care claim
         *
         * sample: SV1*HC:99211:25*12.25*UN*1*11**1:2:3**N~
         */
        //maybe need modification,
        //SV105:Facility Code Value,identify the type of facility where services were performed
        //field in reference file given by Chen is filled,but neither in OpenEMR nor Ansi837.pdf
        //Qiao
        //05/07/2011
        ++$edicount;
        //$diff = minute_diff($claim->cpt_end_time($prockey), $claim->cpt_start_time($prockey));
        if ($claim->secondary_CPT_code($prockey) == null || $claim->secondary_CPT_code($prockey) == "" || $claim->secondary_CPT_code($prockey) == '') {
            $codeused = $claim->cptCode($prockey);
            $codeind = "*UN";
            $diff = "1";
        } else {
            $codeused = $claim->secondary_CPT_code($prockey);
            if($prockey == 0)
                $codeind = "*MJ";
            else
                $codeind = "*UN";
            $diff = minute_diff($claim->cpt_end_time($prockey), $claim->cpt_start_time($prockey));
        }
        $out .= "SV1" . // Professional Service. Page 400.
                //"*HC:" . $claim->secondary_CPT_code($prockey);
                "*HC:" . $codeused;
        for ($i = 1; $i < count($arr); $i++) {
            $arr_1 = explode('|', $arr[$i]); 
            for ($j = 0; $j < count($arr_1); $j++) 
                $out .= ":" . $arr_1[$j];
        }
        $out .= "*" . sprintf('%.2f', $claim->cptCharges($prockey)) .
                //"*MJ" .
                $codeind .
                "*" . $diff .
                "*" . $claim->cpt_place_of_service($prockey) .
                "*" .
                "*" . $claim->diagnosis_pointer($prockey);
        $out .= "~\n";
        //section:DTP,contains information about the date range services were provided in
        ++$edicount;
        $out .= "DTP" . // Date of Service. Page 435.
                "*472" .
                "*" . $claim->cpt_dateType($prockey) .
                "*" . $claim->cpt_serviceDate($prockey) .
                "~\n";
        //add qiao 08/18/2011 add comment according to cms
        ++$edicount;
        //if ($claim->secondary_CPT_code($prockey) != null && $claim->secondary_CPT_code($prockey) != "" && $claim->secondary_CPT_code($prockey) != '') {
        if ($prockey == 0 && $claim->billBy($prockey) == "A") {
            $out .= "NTE" . // Date of Service. Page 435.
                "*ADD" .
                "*" . $claim->cpt_start_time($prockey) . '-' . $claim->cpt_end_time($prockey) .
                "~\n";
        } 
        /*         * ***********************************************
         * comment:as for most of the EDI file,I have not catch this section,so comment them
         * Qiao
         * 05/07/2011
         * *********************************************** */
//        // AMT*AAE segment for Approved Amount from previous payer.
//        // Medicare secondaries seem to require this.
//        //
//        for ($ins = $claim->payerCount() - 1; $ins > 0; --$ins) {
//            if ($claim->payerSequence($ins) > $claim->payerSequence())
//                continue; // payer is future, not previous
// $payerpaid = $claim->payerTotals($ins, $claim->cptKey($prockey));
//            ++$edicount;
//            $out .= "AMT" . // Approved amount per previous payer. Page 485.
//                    "*AAE" .
//                    "*" . sprintf('%.2f', $claim->cptCharges($prockey) - $payerpaid[2]) .
//                    "~\n";
//            break;
//        }
//
//        // Loop 2410, Drug Information. Medicaid insurers seem to want this
//        // with HCPCS codes.
//        //
//        $ndc = $claim->cptNDCID($prockey);
//        if ($ndc) {
//            ++$edicount;
//            $out .= "LIN" . // Drug Identification. Page 500+ (Addendum pg 71).
//                    "*" . // Per addendum, LIN01 is not used.
//                    "*N4" .
//                    "*" . $ndc .
//                    "~\n";
//
//            if (!preg_match('/^\d\d\d\d\d-\d\d\d\d-\d\d$/', $ndc, $tmp)) {
//                $log .= "*** NDC code '$ndc' has invalid format!\n";
//            }
//            ++$edicount;
//            $tmpunits = $claim->cptNDCQuantity($prockey) * $claim->cptUnits($prockey);
//            if (!$tmpunits)
//                $tmpunits = 1;
//            $out .= "CTP" . // Drug Pricing. Page 500+ (Addendum pg 74).
//                    "*" .
//                    "*" .
//                    "*" . sprintf('%.2f', $claim->cptCharges($prockey) / $tmpunits) .
//                    "*" . $claim->cptNDCQuantity($prockey) .
//                    "*" . $claim->cptNDCUOM($prockey) .
//                    "~\n";
//        }
//
//        // Loop 2420A, Rendering Provider (service-specific).
//        // Used if the rendering provider for this service line is different
//        // from that in loop 2310B.
//        //
//        if ($claim->providerNPI() != $claim->providerNPI($prockey)) {
//            ++$edicount;
//            $out .= "NM1" . // Loop 2310B Rendering Provider
//                    "*82" .
//                    "*1" .
//                    "*" . $claim->providerLastName($prockey) .
//                    "*" . $claim->providerFirstName($prockey) .
//                    "*" . $claim->providerMiddleName($prockey) .
//                    "*" .
//                    "*";
//            if ($claim->providerNPI($prockey)) {
//                $out .=
//                        "*XX" .
//                        "*" . $claim->providerNPI($prockey);
//            } else {
//                $out .=
//                        "*34" .
//                        "*" . $claim->providerSSN($prockey);
//                $log .= "*** Rendering provider has no NPI.\n";
//            }
//            $out .= "~\n";
//
//            if ($claim->providerTaxonomy($prockey)) {
//                ++$edicount;
//                $out .= "PRV" .
//                        "*PE" . // PErforming provider
//                        "*ZZ" .
//                        "*" . $claim->providerTaxonomy($prockey) .
//                        "~\n";
//            }
        // REF*1C is required here for the Medicare provider number if NPI was
        // specified in NM109.  Not sure if other payers require anything here.
//        if ($claim->providerNumber($prockey)) {
//            ++$edicount;
//            $out .= "REF" .
//                    "*" . $claim->providerNumberType($prockey) .
//                    "*" . $claim->providerNumber($prockey) .
//                    "~\n";
//        }
    }
    /*     * ***********************************************
     * comment:as for most of the EDI file,I have not catch this section,so comment them
     * Qiao
     * 05/07/2011
     * *********************************************** */
//        // Loop 2430, adjudication by previous payers.
//        //
//        for ($ins = 1; $ins < $claim->payerCount(); ++$ins) {
//            if ($claim->payerSequence($ins) > $claim->payerSequence())
//                continue; // payer is future, not previous
//
//                $payerpaid = $claim->payerTotals($ins, $claim->cptKey($prockey));
//            $aarr = $claim->payerAdjustments($ins, $claim->cptKey($prockey));
//
//            if ($payerpaid[1] == 0 && !count($aarr)) {
//                $log .= "*** Procedure '" . $claim->cptKey($prockey) .
//                        "' has no payments or adjustments from previous payer!\n";
//                continue;
//            }
//            ++$edicount;
//            $out .= "SVD" . // Service line adjudication. Page 554.
//                    "*" . $claim->payerID($ins) .
//                    "*" . $payerpaid[1] .
//                    "*HC:" . $claim->cptKey($prockey) .
//                    "*" .
//                    "*" . $claim->cptUnits($prockey) .
//                    "~\n";
//
//            $tmpdate = $payerpaid[0];
//            foreach ($aarr as $a) {
//                ++$edicount;
//                $out .= "CAS" . // Previous payer's line level adjustments. Page 558.
//                        "*" . $a[1] .
//                        "*" . $a[2] .
//                        "*" . $a[3] .
//                        "~\n";
//                if (!$tmpdate)
//                    $tmpdate = $a[0];
//            }
//
//            if ($tmpdate) {
//                ++$edicount;
//                $out .= "DTP" . // Previous payer's line adjustment date. Page 566.
//                        "*573" .
//                        "*D8" .
//                        "*$tmpdate" .
//                        "~\n";
//            }
//        } // end loop 2430
//    } // end this procedure
    if ($index == ($array_count - 1)) {
        ++$edicount;
        $out .= "SE" . // SE Trailer
                "*$edicount" .
                "*43701" .
                "~\n";

        $out .= "GE" . // GE Trailer
                "*1" .
                "*" . $x12gs06 .
                "~\n";

        $out .= "IEA" . // IEA Trailer
                "*1" .
                "*" . $x12isa13 .
                "~\n";
    }
    return $out;
}

?>
