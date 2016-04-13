<?php

/*
 * gen_hcfa_1500
 * generate cms 1500(character)
 * author:Xinwang Qiao
 * July,2011
 */
require_once 'helper.php';
require_once 'claim.php';
require_once 'fpdf.php';
require_once 'fpdi.php';

//function put_hcfa($line, $col, $maxlen, $data) {
//    if ($data == NULL || ($data == ''))
//        return;
//    $len = min(strlen($data), $maxlen);
//    $hcfa_data = substr($data, 0, $len);
//    $pdf->SetXY($line, $col);
//    $pdf->Write(0, $hcfa_data); 
//}

function gen_hcfa_1500_pdf_page($claim, $pdf,$billingcompany_id,$form_flag=true) {
    //get service(encounter)
    $service = $claim->get_servce();
// import source file
    $pdf->AddPage();
    if($form_flag){
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, -16, -4, 0, 0, TRUE);
    }
    //$pdf->useTemplate($tplIdx, 0, 14, 210);
    $LargeFontSize = 11;
    $NormalFontSize = 10;
    $SmallFontSize = 8;
    $Offset1 = 1;
    $Offset2 = 1;
    if(!$form_flag){
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto("id=?",$billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        if(isset($billingcompany_data["cms1500_offset_p1"])){
            $offsets = explode("|",$billingcompany_data["cms1500_offset_p1"]);
            $offset_x = floatval($offsets[0])/10;
            $offset_y = floatval($offsets[1])/10;
        }else{
            $offset_x = 0;
            $offset_y = 0;
        }
    } else{
         $offset_x = 0;
         $offset_y = 0;
}     
    $pdf->SetFont('Arial', "", $NormalFontSize);

//header payer:name,city,state,zip,etc.
//    put_hcfa(99, 34,30,$claim->payerName());

    $pdf->SetXY(105+$offset_x, 30+$offset_y);

    $pdf->Write(0, $claim->payerName());
    $pdf->SetXY(105+$offset_x, 34+$offset_y);
    $pdf->Write(0, $claim->payerStreet());
    $tmp = $claim->payerCity() ? ($claim->payerCity() . ', ') : '';
    $pdf->SetXY(105+$offset_x, 38+$offset_y);
    $pdf->Write(0, $tmp . $claim->payerState() . ' ' . zip_format($claim->payerZip()));
    $Line1 = 49 + $offset_y;
    $LineSpacing = 8.46;
    $Line2 = $Line1 + $LineSpacing;
    $Line3 = $Line1 + 2 * $LineSpacing;
    $Line4 = $Line1 + 3 * $LineSpacing;
    $Line5 = $Line1 + 4 * $LineSpacing;
    $Line6 = $Line1 + 5 * $LineSpacing;
    $Line7 = $Line1 + 6 * $LineSpacing;
    $Line8 = $Line1 + 7 * $LineSpacing;
    $Line9 = $Line1 + 8 * $LineSpacing;
    $Line10 = $Line1 + 9 * $LineSpacing;
    $Line11 = $Line1 + 11 * $LineSpacing - $Offset1;
    $LineSP1 = 151;
    $LineSP2 = $LineSP1 + $LineSpacing;
    $LineSP3 = $LineSP1 + 2 * $LineSpacing;
    $LineSP4 = $LineSP1 + 3 * $LineSpacing;
    $LineSP5 = $LineSP1 + 4 * $LineSpacing;
    $LineSP6 = $LineSP1 + 5 * $LineSpacing;
    $LineSP7 = $LineSP1 + 6 * $LineSpacing;
    $LineSP8 = $LineSP1 + 7 * $LineSpacing;
    $LineSP9 = $LineSP1 + 8 * $LineSpacing;
    $LineSP10 = $LineSP1 + 9 * $LineSpacing;
    $LineSP11 = $LineSP1 + 10 * $LineSpacing;
    $LineSP12 = $LineSP1 + 11 * $LineSpacing;
    $LineSP13 = $LineSP1 + 12 * $LineSpacing;
    $LineSP14 = $LineSP1 + 13 * $LineSpacing;
    $column1 = 5 + $offset_x;
    $column9 = 129 + $offset_x;
//
//Line1
//
    // Box 1. Not yet implemented

    switch ($claim->insurancetype()) {
        case "MEDICARE":
                $pdf->SetXY($column1, $Line1);
                $pdf->Write(0,"X");
                break;
        case "MEDICAID":
                $pdf->SetXY($column1 + 17, $Line1);
                $pdf->Write(0,"X");
                break;   
         case "TRICARE":
                $pdf->SetXY($column1 + 35, $Line1);
                $pdf->Write(0,"X");
                break;
        case "CHAMPVA":
                $pdf->SetXY($column1 + 58, $Line1);
                $pdf->Write(0,"X");
                break;
        case "GROUP":
                $pdf->SetXY($column1 + 76, $Line1);
                $pdf->Write(0,"X");
                break;
        case "FECA":
                $pdf->SetXY($column1 + 96, $Line1);
                $pdf->Write(0,"X");
                break;
         case "OTHER":
         case NULL:
                $pdf->SetXY($column1 + 112, $Line1);
                $pdf->Write(0,"X");
                break;          
    }
    // Box 1a. Insured's ID Number
    if ($claim->policyNumber()) {
        $pdf->SetXY($column9, $Line1);
        $pdf->Write(0, $claim->policyNumber());
    }
//
//Line2
//
    // Box 2. Patient's Name
    $tmp = $claim->patientLastName() . ', ' . $claim->patientFirstName();
    $pdf->SetXY($column1, $Line2);
    $pdf->Write(0, $tmp);
    
    // Box 3. Patient's Birth Date and Sex
    $tmp = $claim->patientDOB();
    $pdf->SetXY($column1 + 76, $Line2);
    $pdf->Write(0, substr($tmp, 4, 2));
    $pdf->SetXY($column1 + 85, $Line2);
    $pdf->Write(0, substr($tmp, 6, 2));
    $pdf->SetXY($column1 + 92, $Line2);
    $pdf->Write(0, substr($tmp, 0, 4));
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($claim->patientSex() == 'M' ? 109 : 121, $Line2);
    $pdf->Write(0, "X");
    $pdf->SetFont('Arial', "", $NormalFontSize);
    
    //box 4
    $tmp = $claim->insuredLastName() . ', ' . $claim->insuredFirstName();
    $pdf->SetXY($column9, $Line2);
    $pdf->Write(0, $tmp);
//
//Line3
// 
    // Box 5. Patient's Address
    $pdf->SetXY($column1, $Line3);
    $pdf->Write(0, $claim->patientStreet());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    
    // Box 6. Patient Relationship to Insured
    $tmp = $claim->insuredRelationship();
    $PRTI_Start_Column= $column1 + 81;
    $tmpcol = $PRTI_Start_Column + 36;                  // Other
    if ($tmp === 'self')
        $tmpcol = $PRTI_Start_Column; // self
    else if ($tmp === 'spouse')
        $tmpcol = $PRTI_Start_Column+ 12; // spouse
    else if ($tmp === 'child')
        $tmpcol = $PRTI_Start_Column + 22; // child
    $pdf->SetXY($tmpcol, $Line3);
    $pdf->Write(0, "X");
    
    //box 7 insured address
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column9, $Line3);
    $pdf->Write(0, $claim->insuredStreet());
//    
//Line4
//
    // box 5 patient city  state
    $pdf->SetXY($column1, $Line4);
    $pdf->Write(0, $claim->patientCity());
    $pdf->SetXY($column1 + 65, $Line4);
    $pdf->Write(0, $claim->patientState());
    
    //box 7 insured city state
    $pdf->SetXY($column9, $Line4);
    $pdf->Write(0, $claim->insuredCity());
    $pdf->SetXY($column9 + 61, $Line4);
    $pdf->Write(0, $claim->insuredState());
//
// Line5
// 
    // box 5 patient zip telephone
    $pdf->SetXY($column1, $Line5);
    $pdf->Write(0, zip_format($claim->patientZip()));
    $tmp = $claim->patientPhone();
    $pdf->SetXY($column1 + 37, $Line5);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column1 + 45, $Line5);
    $pdf->Write(0, substr($tmp, 3));
    
    // box 7 insured zip telephone
    $pdf->SetXY($column9, $Line5);
    $pdf->Write(0, zip_format($claim->insuredZip()));
    $tmp = $claim->insuredPhone();
    $pdf->SetXY($column9 + 40, $Line5);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column9 + 49, $Line5);
    $pdf->Write(0, substr($tmp, 3));
//
//Line6
//    
    //box 9 other insured name
    $tmp = $claim->otherinsuredLastName() . ', ' . $claim->otherinsuredFirstName();
    if ($claim->otherinsuredLastName()) {
        $pdf->SetXY($column1, $Line6);
        $pdf->Write(0, $tmp);
    }
    
    // box 11 insured's policy group or feca number
    $pdf->SetXY($column9, $Line6);
    $pdf->Write(0, $claim->policy_group_or_FECA_number());
//
//Line7
//    
    // box 9a other_insured_policy_or_group_number
    if ($other_insured) {
        $pdf->SetXY($column1, $Line7);
        $pdf->Write(0, $claim->other_insured_policy_or_group_number());
    }
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 0, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = $column1 + 86;
        if ($tmp == 0)
            $tmpcol = $column1 + 101;
        if ($tmpcol > 0) {
            $pdf->SetFont('Arial', "B", $NormalFontSize);
            $pdf->SetXY($tmpcol, $Line7);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // Box 11a. insured's Birth Date and Sex
    $tmp = $claim->insuredDOB();
    $pdf->SetXY($column9 + 8, $Line7);
    $pdf->Write(0, substr($tmp, 4, 2));
    $pdf->SetXY($column9 + 16, $Line7);
    $pdf->Write(0, substr($tmp, 6, 2));
    $pdf->SetXY($column9 + 22, $Line7);
    $pdf->Write(0, substr($tmp, 0, 4));
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($claim->insuredSex() == 'M' ? $column9 + 45 : $column9 + 63, $Line7);
    $pdf->Write(0, "X");
//
//Line8
//
    // Box 9b. other insured's Birth Date and Sex
    if ($claim->otherinsuredLastName()) {
        $pdf->SetFont('Arial', "", $NormalFontSize);
        $tmp = $claim->otherinsuredDOB();
        $pdf->SetXY(12, $Line8);
        $pdf->Write(0, substr($tmp, 4, 2));
        $pdf->SetXY(20, $Line8);
        $pdf->Write(0, substr($tmp, 6, 2));
        $pdf->SetXY(28, $Line8);
        $pdf->Write(0, substr($tmp, 0, 4));
        $pdf->SetFont('Arial', "B", $NormalFontSize);
        $pdf->SetXY($claim->otherinsuredSex() == 'M' ? 51 : 65, $Line8);
        $pdf->Write(0, "X");
        $pdf->SetFont('Arial', "", $NormalFontSize);
    }
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 1, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = $column1 + 86;
        if ($tmp == 0)
            $tmpcol = $column1 + 101;
        if ($tmpcol > 0) {
            $pdf->SetXY($tmpcol, $Line8);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // box 11.b
    $pdf->SetXY($column9, $Line8);
    $pdf->Write(0, $claim->other_insured_employer_name());
//
//Line9
//
    // box 9.c
    $pdf->SetXY($column1, $Line9);
    $pdf->Write(0, $claim->employer_or_school_name());
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 2, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = $column1 + 86;
        if ($tmp == 0)
            $tmpcol = $column1 + 101;
        if ($tmpcol > 0) {
            $pdf->SetFont('Arial', "B", $NormalFontSize);
            $pdf->SetXY($tmpcol, $Line9);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // box 11.c
    $pdf->SetXY($column9, $Line9);
    $pdf->Write(0, $claim->other_insurance_name_or_program_name());
//
//Line10
//
    // box 9.d
    if ($other_insured) {
        $pdf->SetXY($column1, $Line10);
        $pdf->Write(0, $claim->plan_or_program_name());
    }
    
    // box 11.d is_there_another_plan
    //$pdf->SetFont('Arial', "B", $NormalFontSize);
    //$pdf->SetXY($claim->is_there_another_plan() == '0' ? 146 : 134, $Line10);
    //$pdf->Write(0, "X");
//    
//Line11
//     
    // box 12 signatrue and date
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column1 +21, $Line11);
    $pdf->Write(0, "Signature on File");
    if ($claim->procCount() > 0) {
        $tmp = $claim->cpt_serviceDate(0);
        $date = substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2);
        $pdf->SetXY($column1 + 91, $Line11);
        $pdf->Write(0, $date);
    }
    
    // box 13 insured signature
    $pdf->SetXY($column9 + 21, $Line11);
    $pdf->Write(0, $claim->insured_signature());
//  
//LineSP1
//
    // box 14 date of current illness
    $tmp = $service['date_of_current_illness_or_injury'];
    $pdf->SetXY($column1 + 4, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($column1 + 11, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($column1 + 18, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    
    // box 15 date of the same illness
    $tmp = $service['date_same_illness'];
    $tmpx = $column1 + 92;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 14, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    
    // box 16 not_able_to_work_from_date not_able_to_work_to_date
    $tmp = $service['not_able_to_work_from_date'];
    $tmpx = $column9 + 11;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    $tmp = $service['not_able_to_work_to_date'];
    $tmpx = $column9 + 46;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
//
//LineSP2
//
    // box 17 referringprovider and its NPI
    if ($claim->referringproviderNPI()) {
        $pdf->SetXY($column1, $LineSP2);
        $pdf->Write(0, "DN");
        $referringprovider = $claim->referrerFirstName() . ' ' . $claim->referrerLastName() . ','.$claim->referringprovidersalutation();
        $pdf->SetXY($column1 + 8, $LineSP2);
        $pdf->Write(0, $referringprovider);

        $pdf->SetXY($column1 + 80, $LineSP2);
        $pdf->Write(0, $claim->referringproviderNPI());
    }
    
    // box 18 hospitalization_from_date	hospitalization_to_date
    $tmp = $service['hospitalization_from_date'];
    $tmpx = $column9 + 11;
    $pdf->SetXY($tmpx, $LineSP2);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP2);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP2);
    $pdf->Write(0, substr($tmp, 0, 4));
    $tmp = $service['hospitalization_to_date'];
    $tmpx = 175;
    $pdf->SetXY($tmpx, $LineSP2);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP2);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 14, $LineSP2);
    $pdf->Write(0, substr($tmp, 0, 4));
//    
//LineSP3
//
    // box 19 reserved for local use
    // box 20 outside lab? charges
    if ($service['outside_lab'] != "0") {
        $pdf->SetFont('Arial', "B", $NormalFontSize);
        $pdf->SetXY($service['outside_lab'] == 0 ? $column9 + 15 : $column9 + 5, $LineSP3);
        $pdf->Write(0, "X");
        $pdf->SetFont('Arial', "", $NormalFontSize);

        $charge = $service['charge'];
        $charge_int = floor($charge);
        $charge_dec = sprintf('%01.2f', $charge);
        $pdf->SetXY($column9 + 40, $LineSP3);
        $pdf->Write(0, '$' . $charge_int);
        $pdf->SetXY($column9 + 55, $LineSP3);
        $pdf->Write(0, substr($charge_dec, -2));
    }
    $tag_taxonomy = $claim->get_tag("insurance","include_taxonomy");
    if($tag_taxonomy == "yes"){
        $pdf->SetXY($column1, $LineSP3);
        $taxonomy_code = $claim->taxonomy_code();
        $pdf->write(0,$taxonomy_code);
    }
//
//LineSP4
//  
    // Box 21. Diagnoses
    $tmp = $claim->diagArray();
    $diags = array();
    $dot = '.';
    $space = ' ';
    foreach ($tmp as $diag)
        $diags[] = $diag;
    
    if (!empty($diags[0])) {
        $pdf->SetXY($column1 + 5, $LineSP4);
        if (strpos($diags[0], $dot) === false) {
            $pdf->Write(0, $diags[0]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[0]));
        }                
        //$pdf->SetXY($column1 + 13, $LineSP4);
        //$pdf->Write(0, substr($diags[0], 4));
    }
    if (!empty($diags[1])) {
        $pdf->SetXY($column1 + 38, $LineSP4);
        if (strpos($diags[1], $dot) === false) {
            $pdf->Write(0, $diags[1]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[1]));
        }        
        //$pdf->SetXY($column1 + 46, $LineSP4);
        //$pdf->Write(0, substr($diags[1], 4));
    }
    if (!empty($diags[2])) {
        $pdf->SetXY($column1 + 71, $LineSP4);
        if (strpos($diags[2], $dot) === false) {
            $pdf->Write(0, $diags[2]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[2]));
        }        
        //$pdf->SetXY($column1 + 79, $LineSP4);
        //$pdf->Write(0, substr($diags[2], 4));
    }
    if (!empty($diags[3])) {
        $pdf->SetXY($column1 + 104, $LineSP4);
        if (strpos($diags[3], $dot) === false) {
            $pdf->Write(0, $diags[3]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[3]));
        }        
        //$pdf->SetXY($column1 + 112, $LineSP4);
        //$pdf->Write(0, substr($diags[3], 4));
    }
    // box 21 E-L to be implemented
    // box 23
    // box 24
//
//LineSP6
//    
    //$pdf->SetFont('Arial', "", $SmallFontSize);
    //$pdf->SetXY($column1, $LineSP6 + 1);
    //$diff = minute_diff($claim->cpt_end_time(0), $claim->cpt_start_time(0));
    //$comment = 'Anesthesia start time ' . $claim->cpt_start_time(0) . ', end time ' . $claim->cpt_end_time(0) . '. Anesthesia minutes: ' . $diff;
    // zw
    // if (!($claim->secondary_CPT_code($prockey) == '')) {
    //    $pdf->Write($column1, $comment);
    // }
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $proccount = $claim->procCount(); // number of procedures
    for ($prockey = 0; $prockey < 6 && ($prockey < $proccount); ++$prockey) {
    //zw        
        if ($claim->billBy($prockey) == 'A') {
            $pdf->SetFont('Arial', "", $SmallFontSize);
            $pdf->SetXY($column1, $LineSP1 + ($prockey + 5)*$LineSpacing + 1);
            $diff = minute_diff($claim->cpt_end_time($prockey), $claim->cpt_start_time($prockey));
            $comment = 'Anesthesia start time ' . $claim->cpt_start_time($prockey) . ', end time ' . $claim->cpt_end_time($prockey) . '. Anesthesia minutes: ' . $diff;
            $pdf->Write($column1, $comment);
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }    
    
        $linepos = $LineSP1 + (6 + $prockey) * $LineSpacing;

    // 24a. Date of Service
        $tmp = $claim->cpt_serviceDate($prockey);
        $pdf->SetXY($column1, $linepos);
        $pdf->Write(0, substr($tmp, 4, 2));
        $pdf->SetXY($column1 + 8, $linepos);
        $pdf->Write(0, substr($tmp, 6, 2));
        $pdf->SetXY($column1 + 15, $linepos);
        $pdf->Write(0, substr($tmp, 2, 2));
        if (strpos($tmp, '-') > 0) {
            $pdf->SetXY($column1 + 24, $linepos);
            $pdf->Write(0, substr($tmp, 13, 2));
            $pdf->SetXY($column1 + 31, $linepos);
            $pdf->Write(0, substr($tmp, 15, 2));
            $pdf->SetXY($column1 + 39, $linepos);
            $pdf->Write(0, substr($tmp, 11, 2));
        }
        
    // 24b. Place of Service
        $pdf->SetXY($column1 + 46, $linepos);
        $pdf->Write(0, $claim->cpt_place_of_service($prockey));
        
    // 24c. EMG
    // Not currently supported.
        $tmp = $claim->cptEMG($prockey);
        if ($tmp != null) {
            $pdf->SetXY($column1 + 53, $linepos);
            $pdf->Write(0, $tmp);
        }

    // 24d. Procedures, Services or Supplies
        $tmp = $claim->cptKey($prockey);
        $arr = explode(':', $tmp);
        if (count($arr) > 0) {
            $pdf->SetXY($column1 + 63, $linepos);
            /*             * use secondary_CPT_code if it  exists,
             * modifier: Qiao
             * Time:09/01/2011
             */
            $cpt_code = $arr[0];
            if (!($claim->secondary_CPT_code($prockey) == '')) {
                $cpt_code = $claim->secondary_CPT_code($prockey);
            }

            $pdf->Write(0, $cpt_code);
            $arr = explode('|', $arr[1]);
            for ($i = 0; $i < count($arr); $i++) {
                $pdf->SetXY(86 + 8 * $i, $linepos);
                $pdf->Write(0, $arr[$i]);
            }
        }

    // 24e. Diagnosis Pointer
        $tmp = str_replace(':', ',', $claim->diagnosis_pointer($prockey));
        $pdf->SetXY($column1 + 112, $linepos);
        $pdf->Write(0, "A");
        
    // 24f. Charges
        $charge = $claim->cptCharges($prockey);
        $charge_int = floor($charge);
        $charge_dec = sprintf('%01.2f', $charge);
        $pdf->SetXY($column9 + 1, $linepos);
        $pdf->Write(0, $charge_int);
        $pdf->SetXY($column9 + 16, $linepos);
        $pdf->Write(0, substr($charge_dec, -2));
    
    // 24g. Days or Units
        $pdf->SetXY($column9 + 23, $linepos);
        $pdf->Write(0, $claim->cpt_days_or_units($prockey));
    
    // 24h. EPSDT Family Plan
    // Not currently supported.
        $pdf->SetXY($column9 + 33, $linepos);
        $pdf->Write(0, $claim->EPSDT($prockey));
    
    // 24j. Rendering Provider NPI
        $pdf->SetXY($column9 + 45, $linepos);
        $pdf->Write(0, $claim->renderingprovider_NPI($prockey+1));
    //
        $include1 = $claim->get_tag("insurance","include_id1");
        if($include1=="yes"){
            $pdf->SetXY($column9 + 45, $LineSP6+4+$prockey*$LineSpacing);
            $pdf->Write(0, $claim->renderprovider_id1($prockey+1));
        }
        $include2 = $claim->get_tag("insurance","include_id2");
        if($include2=="yes"){
            $pdf->SetXY($column9 + 45, $LineSP6+4+$prockey*$LineSpacing);
            $pdf->Write(0, $claim->renderprovider_id2($prockey+1));
        }
    }
//
//LineSP13
//
    // 25. Federal Tax ID Number
    $pdf->SetXY($column1, $LineSP13);
    $pdf->Write(0, $claim->provider_Tax_id_number());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($column1 + 46, $LineSP13);
    $pdf->Write(0, "X");
    
    // 26. Patient's Account No.
    // Instructions say hyphens are not allowed, but freeb used them.
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column1 + 57, $LineSP13);
    $pdf->Write(0, $claim->patient_account());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($column1 + 93, $LineSP13);
    $pdf->Write(0, "X");
    
    // 28. Total Charge
    $charge = $claim->total_charge();
    $charge_int = floor($charge);
    $charge_dec = sprintf('%01.2f', $charge);
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column9 + 3, $LineSP13);
    $pdf->Write(0, $charge_int);
    $pdf->SetXY($column9 + 21, $LineSP13);
    $pdf->Write(0, substr($charge_dec, -2));
    
    //zw
    $amount_paid_dec = substr(sprintf('%01.2f', $claim->amount_paid()), -2);
    $balance_due_dec = substr(sprintf('%01.2f', $claim->balance_due()), -2);
    $pdf->SetXY($column9 + 32, $LineSP13);
    $pdf->Write(0, floor($claim->amount_paid()));
    $pdf->SetXY($column9 + 47, $LineSP13);
    $pdf->Write(0, $amount_paid_dec);
//
//LineSP14
//
    $BottomBoxLine1 = 257 + offset_y;
    $BottomBoxLineSpacing = 3.5;
//
//LineSP14.1
//      
    // 33. Billing Provider: Phone Number
    $tmp = $claim->billing_provider_phone();
    $pdf->SetFont('Arial', "", $SmallFontSize);
    $pdf->SetXY($column9 + 42, $BottomBoxLine1);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column9 + 50, $BottomBoxLine1);
    $pdf->Write(0, substr($tmp, 3));
//
//LinSP14.2
//        
    // 32. Service Facility Location Information: Name
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->facilityName());
    
    // 33. Billing Provider: Name
    $pdf->SetXY($column9, $BottomBoxLine1 + $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->billing_provider_name());
//
//LineSP14.3
//        
    // 32. Service Facility Location Information: Street
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + 2 * $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->facilityStreet());

    // 33. Billing Provider: Name
    $pdf->SetXY($column9, $BottomBoxLine1 + 2 * $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->billing_provider_street());
//
//LinSP14.4
//
    // 32. Service Facility Location Information: City State Zip
    $tmp = $claim->facilityCity() ? ($claim->facilityCity() . ' ') : '';
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + 3 * $BottomBoxLineSpacing);
    $pdf->Write(0, $tmp . $claim->facilityState() . ' ' .
            zip_format($claim->facilityZip()));

    // 33. Billing Provider: City State Zip
    $tmp = $claim->billing_provider_city() ? ($claim->billing_provider_city() . ' ') : '';
    $pdf->SetXY($column9, $BottomBoxLine1 + 3 * $BottomBoxLineSpacing);
    $pdf->Write(0, $tmp . $claim->billing_provider_state() . ' ' .
            zip_format($claim->billing_provider_zip()));
//
//LineSP14.5
//
    //box 31    
    $tmp = $claim->renderingprovider_FirstName(1) . ' ' . $claim->renderingprovider_LastName(1) . ','.$claim->renderingprovider_salutation(1);
    $pdf->SetXY($column1 + 10, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    $tmp = $claim->cpt_serviceDate(0);
    $pdf->SetXY($column1 + 44, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2));
    
    //Box 32a
    $tmp = $claim->facilityNPI();
    $pdf->SetXY($column + 64, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    
    //Box 33a
    $tmp = $claim->get_billing_provider_NPI();
    $pdf->SetXY($column9 + 4, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    $include_id1 = $claim->get_tag("insurance","include_id1");
    if($include_id1=="yes"){
        $pdf->SetXY($column9 + 33, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
        $pdf->Write(0, $claim->provider_id1());
    }
    $include_id2 = $claim->get_tag("insurance","include_id2");
    if($include_id2=="yes"){
        $pdf->SetXY($column9 + 33, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
        $pdf->Write(0, $claim->provider_id2());
    }
    /**************Add billing_provider_NPI for the CMS1500 Form*******************/
    
    
    //if ($claim->procCount() > 0) {
      //$tmp = $claim->cpt_serviceDate(0);
      //$pdf->SetXY(46, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing);
      //$pdf->Write(0, substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2));
    //}
}
function gen_hcfa_1500_pdf_page_without_form($claim, $pdf,$billingcompany_id) {
    //get service(encounter)
    $service = $claim->get_servce();
// import source file
    $pdf->AddPage();
    /*if($form_flag){
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, -16, -4, 0, 0, TRUE);
    }*/
    //$pdf->useTemplate($tplIdx, 0, 14, 210);
    $LargeFontSize = 11;
    $NormalFontSize = 10;
    $SmallFontSize = 8;
    $Offset1 = 1;
    $Offset2 = 1;

    $pdf->SetFont('Arial', "", $NormalFontSize);

//header payer:name,city,state,zip,etc.
//    put_hcfa(99, 34,30,$claim->payerName());
    $db_billingcompany = new Application_Model_DbTable_Billingcompany();
    $db = $db_billingcompany->getAdapter();
    $where = $db->quoteInto("id=?",$billingcompany_id);
    $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
    if(isset($billingcompany_data["cms1500_offset_p1"])){
        $offsets = explode("|",$billingcompany_data["cms1500_offset_p1"]);
        $offset_x = floatval($offsets[0])/10;
        $offset_y = floatval($offsets[1])/10;
    }else{
        $offset_x = 0;
        $offset_y = 0;
    }
    
    $pdf->SetXY(105+$offset_x, 30+$offset_y);
    $pdf->Write(0, $claim->payerName());
    $pdf->SetXY(105, 34);
    $pdf->Write(0, $claim->payerStreet());
    $tmp = $claim->payerCity() ? ($claim->payerCity() . ', ') : '';
    $pdf->SetXY(105, 38);
    $pdf->Write(0, $tmp . $claim->payerState() . ' ' . zip_format($claim->payerZip()));
    $Line1 = 49;
    $LineSpacing = 8.46;
    $Line2 = $Line1 + $LineSpacing;
    $Line3 = $Line1 + 2 * $LineSpacing;
    $Line4 = $Line1 + 3 * $LineSpacing;
    $Line5 = $Line1 + 4 * $LineSpacing;
    $Line6 = $Line1 + 5 * $LineSpacing;
    $Line7 = $Line1 + 6 * $LineSpacing;
    $Line8 = $Line1 + 7 * $LineSpacing;
    $Line9 = $Line1 + 8 * $LineSpacing;
    $Line10 = $Line1 + 9 * $LineSpacing;
    $Line11 = $Line1 + 11 * $LineSpacing - $Offset1;
    $LineSP1 = 151;
    $LineSP2 = $LineSP1 + $LineSpacing;
    $LineSP3 = $LineSP1 + 2 * $LineSpacing;
    $LineSP4 = $LineSP1 + 3 * $LineSpacing;
    $LineSP5 = $LineSP1 + 4 * $LineSpacing;
    $LineSP6 = $LineSP1 + 5 * $LineSpacing;
    $LineSP7 = $LineSP1 + 6 * $LineSpacing;
    $LineSP8 = $LineSP1 + 7 * $LineSpacing;
    $LineSP9 = $LineSP1 + 8 * $LineSpacing;
    $LineSP10 = $LineSP1 + 9 * $LineSpacing;
    $LineSP11 = $LineSP1 + 10 * $LineSpacing;
    $LineSP12 = $LineSP1 + 11 * $LineSpacing;
    $LineSP13 = $LineSP1 + 12 * $LineSpacing;
    $LineSP14 = $LineSP1 + 13 * $LineSpacing;
    $column1 = 5;
    $column9 = 129;
//
//Line1
//
    // Box 1. Not yet implemented
    
    // Box 1a. Insured's ID Number
    if ($claim->policyNumber()) {
        $pdf->SetXY($column9, $Line1);
        $pdf->Write(0, $claim->policyNumber());
    }
//
//Line2
//
    // Box 2. Patient's Name
    $tmp = $claim->patientLastName() . ', ' . $claim->patientFirstName();
    $pdf->SetXY($column1, $Line2);
    $pdf->Write(0, $tmp);
    
    // Box 3. Patient's Birth Date and Sex
    $tmp = $claim->patientDOB();
    $pdf->SetXY($column1 + 76, $Line2);
    $pdf->Write(0, substr($tmp, 4, 2));
    $pdf->SetXY($column1 + 85, $Line2);
    $pdf->Write(0, substr($tmp, 6, 2));
    $pdf->SetXY($column1 + 92, $Line2);
    $pdf->Write(0, substr($tmp, 0, 4));
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($claim->patientSex() == 'M' ? 109 : 121, $Line2);
    $pdf->Write(0, "X");
    $pdf->SetFont('Arial', "", $NormalFontSize);
    
    //box 4
    $tmp = $claim->insuredLastName() . ', ' . $claim->insuredFirstName();
    $pdf->SetXY($column9, $Line2);
    $pdf->Write(0, $tmp);
//
//Line3
// 
    // Box 5. Patient's Address
    $pdf->SetXY($column1, $Line3);
    $pdf->Write(0, $claim->patientStreet());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    
    // Box 6. Patient Relationship to Insured
    $tmp = $claim->insuredRelationship();
    $tmpcol = 122;                   // Other
    if ($tmp === 'self')
        $tmpcol = 86; // self
    else if ($tmp === 'spouse')
        $tmpcol = 98; // spouse
    else if ($tmp === 'child')
        $tmpcol = 108; // child
    $pdf->SetXY($tmpcol, $Line3);
    $pdf->Write(0, "X");
    
    //box 7 insured address
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column9, $Line3);
    $pdf->Write(0, $claim->insuredStreet());
//    
//Line4
//
    // box 5 patient city  state
    $pdf->SetXY($column1, $Line4);
    $pdf->Write(0, $claim->patientCity());
    $pdf->SetXY($column1 + 65, $Line4);
    $pdf->Write(0, $claim->patientState());
    
    //box 7 insured city state
    $pdf->SetXY($column9, $Line4);
    $pdf->Write(0, $claim->insuredCity());
    $pdf->SetXY($column9 + 61, $Line4);
    $pdf->Write(0, $claim->insuredState());
//
// Line5
// 
    // box 5 patient zip telephone
    $pdf->SetXY($column1, $Line5);
    $pdf->Write(0, zip_format($claim->patientZip()));
    $tmp = $claim->patientPhone();
    $pdf->SetXY($column1 + 37, $Line5);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column1 + 45, $Line5);
    $pdf->Write(0, substr($tmp, 3));
    
    // box 7 insured zip telephone
    $pdf->SetXY($column9, $Line5);
    $pdf->Write(0, zip_format($claim->insuredZip()));
    $tmp = $claim->insuredPhone();
    $pdf->SetXY($column9 + 40, $Line5);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column9 + 49, $Line5);
    $pdf->Write(0, substr($tmp, 3));
//
//Line6
//    
    //box 9 other insured name
    $tmp = $claim->otherinsuredLastName() . ', ' . $claim->otherinsuredFirstName();
    if ($claim->otherinsuredLastName()) {
        $pdf->SetXY($column1, $Line6);
        $pdf->Write(0, $tmp);
    }
    
    // box 11 insured's policy group or feca number
    $pdf->SetXY($column9, $Line6);
    $pdf->Write(0, $claim->policy_group_or_FECA_number());
//
//Line7
//    
    // box 9a other_insured_policy_or_group_number
    if ($other_insured) {
        $pdf->SetXY($column1, $Line7);
        $pdf->Write(0, $claim->other_insured_policy_or_group_number());
    }
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 0, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = 91;
        if ($tmp == 0)
            $tmpcol = 108;
        if ($tmpcol > 0) {
            $pdf->SetFont('Arial', "B", $NormalFontSize);
            $pdf->SetXY($tmpcol, $Line7);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // Box 11a. insured's Birth Date and Sex
    $tmp = $claim->insuredDOB();
    $pdf->SetXY($column9 + 8, $Line7);
    $pdf->Write(0, substr($tmp, 4, 2));
    $pdf->SetXY($column9 + 16, $Line7);
    $pdf->Write(0, substr($tmp, 6, 2));
    $pdf->SetXY($column9 + 22, $Line7);
    $pdf->Write(0, substr($tmp, 0, 4));
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($claim->insuredSex() == 'M' ? $column9 + 45 : $column9 + 63, $Line7);
    $pdf->Write(0, "X");
//
//Line8
//
    // Box 9b. other insured's Birth Date and Sex
    if ($claim->otherinsuredLastName()) {
        $pdf->SetFont('Arial', "", $NormalFontSize);
        $tmp = $claim->otherinsuredDOB();
        $pdf->SetXY(12, $Line8);
        $pdf->Write(0, substr($tmp, 4, 2));
        $pdf->SetXY(20, $Line8);
        $pdf->Write(0, substr($tmp, 6, 2));
        $pdf->SetXY(28, $Line8);
        $pdf->Write(0, substr($tmp, 0, 4));
        $pdf->SetFont('Arial', "B", $NormalFontSize);
        $pdf->SetXY($claim->otherinsuredSex() == 'M' ? 51 : 65, $Line8);
        $pdf->Write(0, "X");
        $pdf->SetFont('Arial', "", $NormalFontSize);
    }
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 1, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = 91;
        if ($tmp == 0)
            $tmpcol = 106;
        if ($tmpcol > 0) {
            $pdf->SetXY($tmpcol, $Line8);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // box 11.b
    $pdf->SetXY($column9, $Line8);
    $pdf->Write(0, $claim->other_insured_employer_name());
//
//Line9
//
    // box 9.c
    $pdf->SetXY($column1, $Line9);
    $pdf->Write(0, $claim->employer_or_school_name());
    
    // box 10 patient condition_related_to
    $tmp = substr($claim->patient_condition_related_to(), 2, 1);
    if ($tmp) {
        $tmpcol = 0;
        if ($tmp == 1)
            $tmpcol = 91;
        if ($tmp == 0)
            $tmpcol = 106;
        if ($tmpcol > 0) {
            $pdf->SetFont('Arial', "B", $NormalFontSize);
            $pdf->SetXY($tmpcol, $Line9);
            $pdf->Write(0, 'X');
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
    }
    
    // box 11.c
    $pdf->SetXY($column9, $Line9);
    $pdf->Write(0, $claim->other_insurance_name_or_program_name());
//
//Line10
//
    // box 9.d
    if ($other_insured) {
        $pdf->SetXY($column1, $Line10);
        $pdf->Write(0, $claim->plan_or_program_name());
    }
    
    // box 11.d is_there_another_plan
    //$pdf->SetFont('Arial', "B", $NormalFontSize);
    //$pdf->SetXY($claim->is_there_another_plan() == '0' ? 146 : 134, $Line10);
    //$pdf->Write(0, "X");
//    
//Line11
//     
    // box 12 signatrue and date
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY(25, $Line11);
    $pdf->Write(0, "Signature on File");
    if ($claim->procCount() > 0) {
        $tmp = $claim->cpt_serviceDate(0);
        $date = substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2);
        $pdf->SetXY(96, $Line11);
        $pdf->Write(0, $date);
    }
    
    // box 13 insured signature
    $pdf->SetXY(150, $Line11);
    $pdf->Write(0, $claim->insured_signature());
//  
//LineSP1
//
    // box 14 date of current illness
    $tmp = $service['date_of_current_illness_or_injury'];
    $pdf->SetXY($column1 + 4, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($column1 + 11, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($column1 + 18, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    
    // box 15 date of the same illness
    $tmp = $service['date_same_illness'];
    $tmpx = 97;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 14, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    
    // box 16 not_able_to_work_from_date not_able_to_work_to_date
    $tmp = $service['not_able_to_work_from_date'];
    $tmpx = 140;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
    $tmp = $service['not_able_to_work_to_date'];
    $tmpx = 175;
    $pdf->SetXY($tmpx, $LineSP1);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP1);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP1);
    $pdf->Write(0, substr($tmp, 0, 4));
//
//LineSP2
//
    // box 17 referringprovider and its NPI
    if ($claim->referringproviderNPI()) {
        $pdf->SetXY($column1, $LineSP2);
        $pdf->Write(0, "DN");
        $referringprovider = $claim->referrerFirstName() . ' ' . $claim->referrerLastName() . ','.$claim->referringprovidersalutation();
        $pdf->SetXY($column1 + 8, $LineSP2);
        $pdf->Write(0, $referringprovider);

        $pdf->SetXY(85, $LineSP2);
        $pdf->Write(0, $claim->referringproviderNPI());
    }
    
    // box 18 hospitalization_from_date	hospitalization_to_date
    $tmp = $service['hospitalization_from_date'];
    $tmpx = 140;
    $pdf->SetXY($tmpx, $LineSP2);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP2);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 15, $LineSP2);
    $pdf->Write(0, substr($tmp, 0, 4));
    $tmp = $service['hospitalization_to_date'];
    $tmpx = 175;
    $pdf->SetXY($tmpx, $LineSP2);
    $pdf->Write(0, substr($tmp, 5, 2));
    $pdf->SetXY($tmpx + 7, $LineSP2);
    $pdf->Write(0, substr($tmp, 8, 2));
    $pdf->SetXY($tmpx + 14, $LineSP2);
    $pdf->Write(0, substr($tmp, 0, 4));
//    
//LineSP3
//
    // box 19 reserved for local use
    // box 20 outside lab? charges
    if ($service['outside_lab'] != "0") {
        $pdf->SetFont('Arial', "B", $NormalFontSize);
        $pdf->SetXY($service['outside_lab'] == 0 ? 144 : 132, $LineSP3);
        $pdf->Write(0, "X");
        $pdf->SetFont('Arial', "", $NormalFontSize);

        $charge = $service['charge'];
        $charge_int = floor($charge);
        $charge_dec = sprintf('%01.2f', $charge);
        $pdf->SetXY(160, $LineSP3);
        $pdf->Write(0, '$' . $charge_int);
        $pdf->SetXY(178, $LineSP3);
        $pdf->Write(0, substr($charge_dec, -2));
    }
//
//LineSP4
//
    // Box 21. Diagnoses
    $tmp = $claim->diagArray();
    $diags = array();
    $dot = '.';
    $space = ' ';
    
    foreach ($tmp as $diag)
        $diags[] = $diag;
    
    if (!empty($diags[0])) {
        $pdf->SetXY($column1 + 5, $LineSP4);
        if (strpos($diags[0], $dot) === false) {
            $pdf->Write(0, $diags[0]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[0]));
        }
        //$pdf->SetXY($column1 + 13, $LineSP4);
        //$pdf->Write(0, substr($diags[0], 4));
    }
    if (!empty($diags[1])) {
        $pdf->SetXY($column1 + 38, $LineSP4);
        if (strpos($diags[1], $dot) === false) {
            $pdf->Write(0, $diags[1]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[1]));
        }
        //$pdf->SetXY($column1 + 46, $LineSP4);
        //$pdf->Write(0, substr($diags[1], 4));
    }
    if (!empty($diags[2])) {
        $pdf->SetXY($column1 + 71, $LineSP4);
        if (strpos($diags[2], $dot) === false) {
            $pdf->Write(0, $diags[2]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[2]));
        }
        //$pdf->SetXY($column1 + 79, $LineSP4);
        //$pdf->Write(0, substr($diags[1], 4));
    }
    if (!empty($diags[3])) {
        $pdf->SetXY($column1 + 104, $LineSP4);
        if (strpos($diags[3], $dot) === false) {
            $pdf->Write(0, $diags[3]);
        } else {
            $pdf->Write(0, str_replace($dot, $space, $diags[3]));
        }
        //$pdf->SetXY($column1 + 112, $LineSP4);
        //$pdf->Write(0, substr($diags[3], 4));
    }
    // box 21 E-L to be implemented
    // box 23
    // box 24
//
//LineSP6
//    
    //$pdf->SetFont('Arial', "", $SmallFontSize);
    //$pdf->SetXY($column1, $LineSP6 + 1);
    //$diff = minute_diff($claim->cpt_end_time(0), $claim->cpt_start_time(0));
    //$comment = 'Anesthesia start time ' . $claim->cpt_start_time(0) . ', end time ' . $claim->cpt_end_time(0) . '. Anesthesia minutes: ' . $diff;
    // zw
    // if (!($claim->secondary_CPT_code($prockey) == '')) {
    //    $pdf->Write($column1, $comment);
    // }
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $proccount = $claim->procCount(); // number of procedures
    for ($prockey = 0; $prockey < 6 && ($prockey < $proccount); ++$prockey) {
    //zw
        if ($claim->billBy($prockey) == 'A') {
            $pdf->SetFont('Arial', "", $SmallFontSize);
            $pdf->SetXY($column1, $LineSP1 + ($prockey + 5)*$LineSpacing + 1);
            $diff = minute_diff($claim->cpt_end_time($prockey), $claim->cpt_start_time($prockey));
            $comment = 'Anesthesia start time ' . $claim->cpt_start_time($prockey) . ', end time ' . $claim->cpt_end_time($prockey) . '. Anesthesia minutes: ' . $diff;
            $pdf->Write($column1, $comment);
            $pdf->SetFont('Arial', "", $NormalFontSize);
        }
        $linepos = $LineSP1 + (6 + $prockey) * $LineSpacing;

    // 24a. Date of Service
        $tmp = $claim->cpt_serviceDate($prockey);
        $pdf->SetXY($column1, $linepos);
        $pdf->Write(0, substr($tmp, 4, 2));
        $pdf->SetXY($column1 + 8, $linepos);
        $pdf->Write(0, substr($tmp, 6, 2));
        $pdf->SetXY($column1 + 15, $linepos);
        $pdf->Write(0, substr($tmp, 2, 2));
        if (strpos($tmp, '-') > 0) {
            $pdf->SetXY($column1 + 24, $linepos);
            $pdf->Write(0, substr($tmp, 13, 2));
            $pdf->SetXY($column1 + 31, $linepos);
            $pdf->Write(0, substr($tmp, 15, 2));
            $pdf->SetXY($column1 + 39, $linepos);
            $pdf->Write(0, substr($tmp, 11, 2));
        }
        
    // 24b. Place of Service
        $pdf->SetXY($column1 + 46, $linepos);
        $pdf->Write(0, $claim->cpt_place_of_service($prockey));
        
    // 24c. EMG
    // Not currently supported.
        $tmp = $claim->cptEMG($prockey);
        if ($tmp != null) {
            $pdf->SetXY($column1 + 53, $linepos);
            $pdf->Write(0, $tmp);
        }

    // 24d. Procedures, Services or Supplies
        $tmp = $claim->cptKey($prockey);
        $arr = explode(':', $tmp);
        if (count($arr) > 0) {
            $pdf->SetXY($column1 + 63, $linepos);
            /*             * use secondary_CPT_code if it  exists,
             * modifier: Qiao
             * Time:09/01/2011
             */
            $cpt_code = $arr[0];
            if (!($claim->secondary_CPT_code($prockey) == '')) {
                $cpt_code = $claim->secondary_CPT_code($prockey);
            }

            $pdf->Write(0, $cpt_code);
            for ($i = 1; $i < count($arr); $i++) {
                $pdf->SetXY(86 + 7 * ($i - 1), $linepos);
                $pdf->Write(0, $arr[$i]);
            }
        }

    // 24e. Diagnosis Pointer
        $tmp = str_replace(':', ',', $claim->diagnosis_pointer($prockey));
        $pdf->SetXY($column1 + 112, $linepos);
        $pdf->Write(0, "A");
        
    // 24f. Charges
        $charge = $claim->cptCharges($prockey);
        $charge_int = floor($charge);
        $charge_dec = sprintf('%01.2f', $charge);
        $pdf->SetXY($column9 + 1, $linepos);
        $pdf->Write(0, $charge_int);
        $pdf->SetXY($column9 + 16, $linepos);
        $pdf->Write(0, substr($charge_dec, -2));
    
    // 24g. Days or Units
        $pdf->SetXY($column9 + 23, $linepos);
        $pdf->Write(0, $claim->cpt_days_or_units($prockey));
    
    // 24h. EPSDT Family Plan
    // Not currently supported.
        $tmp = $claim->EPSDT($prockey);
        if ($tmp != null) {
            $pdf->SetXY($column1 + 53, $linepos);
            $pdf->Write(0, $tmp);
        }
    
    // 24j. Rendering Provider NPI
        $pdf->SetXY($column9 + 45, $linepos);
        $pdf->Write(0, $claim->renderingprovider_NPI($prockey+1));
        //if($prockey==0){
            $include1 = $this->get_tag("insurance","include_id1");
            if($include1=="yes"){
                $pdf->SetXY($column9 + 45, $linepos-$LineSpacing);
                $pdf->Write(0, $claim->renderprovider_id1($prockey+1));
            }
            $include2 = $this->get_tag("insurance","include_id2");
            if($include2=="yes"){
                $pdf->SetXY($column9 + 45, $linepos-$LineSpacing);
                $pdf->Write(0, $claim->renderprovider_id2($prockey+1));
            }
        //}
    }
//
//LineSP13
//
    // 25. Federal Tax ID Number
    $pdf->SetXY($column1, $LineSP13);
    $pdf->Write(0, $claim->provider_Tax_id_number());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($column1 + 46, $LineSP13);
    $pdf->Write(0, "X");
    
    // 26. Patient's Account No.
    // Instructions say hyphens are not allowed, but freeb used them.
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column1 + 57, $LineSP13);
    $pdf->Write(0, $claim->patient_account());
    $pdf->SetFont('Arial', "B", $NormalFontSize);
    $pdf->SetXY($column1 + 93, $LineSP13);
    $pdf->Write(0, "X");
    
    // 28. Total Charge
    $charge = $claim->total_charge();
    $charge_int = floor($charge);
    $charge_dec = sprintf('%01.2f', $charge);
    $pdf->SetFont('Arial', "", $NormalFontSize);
    $pdf->SetXY($column9 + 3, $LineSP13);
    $pdf->Write(0, $charge_int);
    $pdf->SetXY($column9 + 21, $LineSP13);
    $pdf->Write(0, substr($charge_dec, -2));
    
    //zw
    $amount_paid_dec = substr(sprintf('%01.2f', $claim->amount_paid()), -2);
    $balance_due_dec = substr(sprintf('%01.2f', $claim->balance_due()), -2);
    $pdf->SetXY($column9 + 32, $LineSP13);
    $pdf->Write(0, floor($claim->amount_paid()));
    $pdf->SetXY($column9 + 47, $LineSP13);
    $pdf->Write(0, $amount_paid_dec);
//
//LineSP14
//
    $BottomBoxLine1 = 257;
    $BottomBoxLineSpacing = 3.5;
//
//LineSP14.1
//      
    // 33. Billing Provider: Phone Number
    $tmp = $claim->billing_provider_phone();
    $pdf->SetFont('Arial', "", $SmallFontSize);
    $pdf->SetXY($column9 + 42, $BottomBoxLine1);
    $pdf->Write(0, substr($tmp, 0, 3));
    $pdf->SetXY($column9 + 50, $BottomBoxLine1);
    $pdf->Write(0, substr($tmp, 3));
//
//LinSP14.2
//        
    // 32. Service Facility Location Information: Name
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->facilityName());
    
    // 33. Billing Provider: Name
    $pdf->SetXY($column9, $BottomBoxLine1 + $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->billing_provider_name());
//
//LineSP14.3
//        
    // 32. Service Facility Location Information: Street
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + 2 * $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->facilityStreet());

    // 33. Billing Provider: Name
    $pdf->SetXY($column9, $BottomBoxLine1 + 2 * $BottomBoxLineSpacing);
    $pdf->Write(0, $claim->billing_provider_street());
//
//LinSP14.4
//
    // 32. Service Facility Location Information: City State Zip
    $tmp = $claim->facilityCity() ? ($claim->facilityCity() . ' ') : '';
    $pdf->SetXY($column1 + 60, $BottomBoxLine1 + 3 * $BottomBoxLineSpacing);
    $pdf->Write(0, $tmp . $claim->facilityState() . ' ' .
            zip_format($claim->facilityZip()));

    // 33. Billing Provider: City State Zip
    $tmp = $claim->billing_provider_city() ? ($claim->billing_provider_city() . ' ') : '';
    $pdf->SetXY($column9, $BottomBoxLine1 + 3 * $BottomBoxLineSpacing);
    $pdf->Write(0, $tmp . $claim->billing_provider_state() . ' ' .
            zip_format($claim->billing_provider_zip()));
//
//LineSP14.5
//
    //box 31    
    $tmp = $claim->renderingprovider_FirstName(1) . ' ' . $claim->renderingprovider_LastName(1) . ','.$claim->renderingprovider_salutation(1);
    $pdf->SetXY($column1 + 10, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    $tmp = $claim->cpt_serviceDate(0);
    $pdf->SetXY($column1 + 44, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2));
    
    //Box 32a
    $tmp = $claim->facilityNPI();
    $pdf->SetXY($column + 64, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    
    //Box 33a
    $tmp = $claim->get_billing_provider_NPI();
    $pdf->SetXY($column9 + 4, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
    $pdf->Write(0, $tmp);
    //Box 33b
    $include_id1 = $claim->get_tag("insurance","include_id1");
    if($include_id1=="yes"){
        $pdf->SetXY($column9 + 30, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
        $pdf->Write(0, $this->provider_id1());
    }
    $include_id2 = $claim->get_tag("insurance","include_id2");
    if($include_id2=="yes"){
        $pdf->SetXY($column9 + 30, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing + 2);
        $pdf->Write(0, $this->provider_id2());
    }
    /**************Add billing_provider_NPI for the CMS1500 Form*******************/
    
    
    //if ($claim->procCount() > 0) {
      //$tmp = $claim->cpt_serviceDate(0);
      //$pdf->SetXY(46, $BottomBoxLine1 + 4 * $BottomBoxLineSpacing);
      //$pdf->Write(0, substr($tmp, 4, 2) . substr($tmp, 6, 2) . substr($tmp, 2, 2));
    //}
}

?>
