<?php

require('statement.php');

function gen_claimstatement_pdf($data, $ledger, $root_dir) {


    $statement_type = 1;
    $statement_dir = $root_dir . '/providerbilling/';
    $claim_dir = $root_dir . '/document/billingcompany/' . $data[billingcompany_data][id] . '/';
    if (!is_dir($statement_dir)) {
        mkdir($statement_dir);
    }
    if (!is_dir($claim_dir)) {
        mkdir($claim_dir);
    }
    $claim_dir_oneoff = $claim_dir .'/';
    $dir = $root_dir . '/providerbilling/';

    if (!is_dir($claim_dir_oneoff)) {
        mkdir($claim_dir_oneoff);
    }

    $single_file_file = $dir . date('YmdHis') . '.pdf';

    $pdf = new PDF_Invoice('P', 'mm', 'Letter');
    $pdf->AddPage();

//           "2 Summit Road\n" .
//            "Summit, NJ 07901\n" .
//            "Phone 973-897-1234\n"
//      need to know

    $billingcompany_phone = gen_phone_format($data[billingcompany_data][phone_number]);
    $billingcompany_zip = gen_zip_format($data[billingcompany_data][zip]);

    $pdf->addProvider($data[billingcompany_data][billingcompany_name], $data[billingcompany_data][street_address] . "\n" .
            $data[billingcompany_data][city] . ', ' . $data[billingcompany_data][state] . ' ' . $billingcompany_zip . "\n" .
            'Phone ' . $billingcompany_phone . "\n");



    if ($statement_type == '1') {
        $pdf->Notice("Provider Invoice");
        $pdf->WaterMark("Provider Invoice");
        $claim_oneoff = $claim_dir_oneoff . 'providerbilling' . '.pdf';
    } elseif ($statement_type == '2') {
        $pdf->Notice("MEDICAL BILL/SECOND NOTICE");
        $pdf->WaterMark("SECOND NOTICE");
        $claim_oneoff = $claim_dir_oneoff . 'S' . 'II' . '.pdf';
    } elseif ($statement_type == '3') { 
        $pdf->Notice("MEDICAL BILL/FINAL NOTICE");
        $pdf->WaterMark("FINAL NOTICE");
        $claim_oneoff = $claim_dir_oneoff . 'S' . 'III' . '.pdf';
    } else {
        $pdf->Notice("Provider Invoice");
        $pdf->WaterMark("Provider Invoice");
        $claim_oneoff = $claim_dir_oneoff . 'providerbilling' . '.pdf';
    }
    


    // what is due data?
    $pdf->addDueDate("Upon Receipt");

    $pdf->addAcctNum($data[short_name]);

    // statementdata? start or generate the pdf. leger[1] or leger[2] date?
    $pdf->addStatementDate(date("m/d/Y"));


    //who is client?patient? and how to get the address
   $provider_zip = gen_zip_format($data[zip]);


    $pdf->addClientAdresse($data[provider_name] ."\n"
            . $data[street_address] . "\n"
            . $data[city] . ", "
            . $data[state] . " "
            . $provider_zip);

// send to who?


    $provider_b_phone = gen_phone_format($data[provider_b_phoneNumber]);
    $provider_b_zip = gen_zip_format($data[provider_b_zip]);

    $pdf->addSendTo("\n\n" . $data[billingcompany_data][billingcompany_name] . "\n" . $data[billingcompany_data][street_address] . "\n" . $data[billingcompany_data][city] . ", " . $data[billingcompany_data][state] . ' ' . $billingcompany_zip . "\n" .
            "Phone " . $billingcompany_phone);
    $cols = array("Bill Period" => 22,
        "Bill Date" => 24,
        "Collection" => 18,
        "Billed Amount" => 27,
        "Received Amount" => 29,
        "Received Date" => 27,
        "Notes" => 30);

    $pdf->addCols($cols);
    $y = 100;
    $append_page_tag = 0;
    $followup_count = 0;
    $count = count($ledger);
    $count = $count;
    for ($i = 0; $i < $count; $i++) {
        $temp = $ledger[$i][notes];
        $piece = explode(" at ", $temp);
        $piece_count = count($piece);
        $description = "";
        if ($piece_count > 1) {
            $description = $piece[0] . " at\n" . $piece[1];
        } else {
            $description = $piece[0];
        }

        $line = array("Bill Period" => $ledger[$i][bill_period], //leger1 or 2 date?
            "Bill Date" => $ledger[$i][date_billed],
            "Collection" => $ledger[$i][amount_collected],
            "Billed Amount" => number_format($ledger[$i][amount_billed],2),
            "Received Amount" => number_format($ledger[$i][amount_paid],2),
            "Received Date" => $ledger[$i][date_paid],
            "Notes" => $ledger[$i][notes]);
        $size = $pdf->addLine($y, $line);
        $y += $size + 4;
        if (($y > 216) && (($i + 1) < $count)) {
            $append_page_tag = 1;
            $followup_count = $i + 1;
            break;
        }
    }

// deal with the description format;

    $pdf->addRemark($data[remark]);
    $pdf->addAmountDue($ledger[$count][balance] . "\n");




    while ($append_page_tag == 1) {

        $pdf->AddPage();

        if ($statement_type == '1') {
            $pdf->WaterMark("MEDICAL BILL");
        } elseif ($statement_type == '2') {
            $pdf->WaterMark("SECOND NOTICE");
        } elseif ($statement_type == '3') {
            $pdf->WaterMark("FINAL NOTICE");
        } else {
            $pdf->WaterMark("MEDICAL BILL");
        }

        $cols = array("Date" => 22,
            "Description" => 95,
            "Amount" => 19,
            "Balance" => 20,
            "Notes" => 48);

        $pdf->addFollowPageCols($cols);
        $y = 30;
        $append_page_tag = 0;

        for ($i = $followup_count; $i < $count; $i++) {
            $temp = $ledger[$i][description];
            $piece = explode(" at ", $temp);
            $piece_count = count($piece);
            $description = "";
            if ($piece_count > 1) {
                $description = $piece[0] . " at\n" . $piece[1];
            } else {
                $description = $piece[0];
            }

            $line = array("Date" => format($ledger[$i][date],1), //leger1 or 2 date?
                "Description" => $description,
                "Amount" => $ledger[$i][amount],
                "Balance" => $ledger[$i][balance],
                "Notes" => "");

            $size = $pdf->addLine($y, $line);
            $y += $size + 4;

            if (($y > 216) && (($i + 1) < $count)) {
                $append_page_tag = 1;
                $followup_count = $i + 1;
                break;
            }
        }
    }

//    $pdf->Output($single_file_file);
    $pdf->Output($claim_oneoff);
    $pdf->Close();
}

function gen_phone_format($phone) {
    $fist_four = substr($phone, 0, 3);
    $middle_phone = substr($phone, 3, 3);
    $last_phone = substr($phone, 6);
    return '(' . $fist_four . ')' . $middle_phone . '-' . $last_phone;
}

function gen_zip_format($zip){
    if(strlen($zip) > 5){
        $head_zip = substr($zip,0,5);
        $hail_zip = substr($zip,5);
        return $head_zip.'-'.$hail_zip;
    }else{
        return $zip;
    }
}



function gen_providerbilling_pdf($datas, $ledgers, $root_dir,$billingcompany_id) {
    $count_total = count($datas);
    $pdf = new PDF_Invoice('P', 'mm', 'Letter');
    for ($j = 0; $j < $count_total; $j++) {
        
        $data = $datas[$j];
        $ledger = $ledgers[$j];
        gen_claimstatement_pdf($data,$ledger,$root_dir);
        $statement_type = $data[statement_type];
         $statement_type = 1;


        $pdf->AddPage();

//           "2 Summit Road\n" .
//            "Summit, NJ 07901\n" .
//            "Phone 973-897-1234\n"
//      need to know

       $billingcompany_phone = gen_phone_format($data[billingcompany_data][phone_number]);
    $billingcompany_zip = gen_zip_format($data[billingcompany_data][zip]);

    $pdf->addProvider($data[billingcompany_data][billingcompany_name], $data[billingcompany_data][street_address] . "\n" .
            $data[billingcompany_data][city] . ', ' . $data[billingcompany_data][state] . ' ' . $billingcompany_zip . "\n" .
            'Phone ' . $billingcompany_phone . "\n");



    if ($statement_type == '1') {
        $pdf->Notice("Provider Invoice");
        $pdf->WaterMark("Provider Invoice");
        $claim_oneoff = $claim_dir_oneoff . 'providerbilling' . '.pdf';
    } elseif ($statement_type == '2') {
        $pdf->Notice("MEDICAL BILL/SECOND NOTICE");
        $pdf->WaterMark("SECOND NOTICE");
        $claim_oneoff = $claim_dir_oneoff . 'S' . 'II' . '.pdf';
    } elseif ($statement_type == '3') {
        $pdf->Notice("MEDICAL BILL/FINAL NOTICE");
        $pdf->WaterMark("FINAL NOTICE");
        $claim_oneoff = $claim_dir_oneoff . 'S' . 'III' . '.pdf';
    } else {
        $pdf->Notice("Provider Invoice");
        $pdf->WaterMark("Provider Invoice");
        $claim_oneoff = $claim_dir_oneoff . 'providerbilling' . '.pdf';
    }


    // what is due data?
    $pdf->addDueDate("Upon Receipt");

    $pdf->addAcctNum($data[short_name]);

    // statementdata? start or generate the pdf. leger[1] or leger[2] date?
    $pdf->addStatementDate(date("m/d/Y"));


    //who is client?patient? and how to get the address
   $provider_zip = gen_zip_format($data[zip]);


    $pdf->addClientAdresse($data[provider_name] ."\n"
            . $data[street_address] . "\n"
            . $data[city] . ", "
            . $data[state] . " "
            . $provider_zip);

// send to who?


    $provider_b_phone = gen_phone_format($data[provider_b_phoneNumber]);
    $provider_b_zip = gen_zip_format($data[provider_b_zip]);

    $pdf->addSendTo("\n\n" . $data[billingcompany_data][billingcompany_name] . "\n" . $data[billingcompany_data][street_address] . "\n" . $data[billingcompany_data][city] . ", " . $data[billingcompany_data][state] . ' ' . $billingcompany_zip . "\n" .
            "Phone " . $billingcompany_phone);
    $cols = array("Bill Period" => 22,
        "Bill Date" => 24,
        "Collection" => 28,
        "Billed Amount" => 27,
        "Received Amount" => 29,
        "Received Date" => 27,
        "Notes" => 30);

    $pdf->addCols($cols);
    $y = 100;
    $append_page_tag = 0;
    $followup_count = 0;
    $count = count($ledger);
   
    for ($i = 0; $i < ($count-1); $i++) {
        $temp = $ledger[$i][notes];
        $piece = explode(" at ", $temp);
        $piece_count = count($piece);
        $description = "";
        if ($piece_count > 1) {
            $description = $piece[0] . " at\n" . $piece[1];
        } else {
            $description = $piece[0];
        }
        $amount_paid =$ledger[$i][amount_paid];
        if($amount_paid=='0.00'){
            $amount_paid = "";
        }
        $date_paid = $ledger[$i][date_paid];
        if($date_paid=="0000-00-00"){
            $date_paid="";
        }
        $line = array("Bill Period" => $ledger[$i][bill_period], //leger1 or 2 date?
            "Bill Date" => format($ledger[$i][date_billed],1),
            "Collection" => number_format($ledger[$i][amount_collected],2),
            "Billed Amount" => number_format($ledger[$i][amount_billed],2),
            "Received Amount" =>number_format($amount_paid,2),
            "Received Date" => format($date_paid,1),
            "Notes" => $ledger[$i][notes]);
        $size = $pdf->addLine($y, $line);
        $y += $size + 4;
        if (($y > 216) && (($i + 1) < $count)) {
            $append_page_tag = 1;
            $followup_count = $i + 1;
            break;
        }
    }

// deal with the description format;

    $pdf->addRemark($data[remark]);
    $pdf->addAmountDue(number_format($ledger[($count-1)][balance],2) . "\n");




    while ($append_page_tag == 1) {

        $pdf->AddPage();


            $pdf->WaterMark("Provider Invoice");
       

        $cols = array("Date" => 22,
            "Description" => 95,
            "Amount" => 19,
            "Balance" => 20,
            "Notes" => 48);

        $pdf->addFollowPageCols($cols);
        $y = 30;
        $append_page_tag = 0;

        for ($i = $followup_count; $i < $count; $i++) {
            $temp = $ledger[$i][description];
            $piece = explode(" at ", $temp);
            $piece_count = count($piece);
            $description = "";
            if ($piece_count > 1) {
                $description = $piece[0] . " at\n" . $piece[1];
            } else {
                $description = $piece[0];
            }

            $line = array("Date" => $ledger[$i][date], //leger1 or 2 date?
                "Description" => $description,
                "Amount" => $ledger[$i][amount],
                "Balance" => $ledger[$i][balance],
                "Notes" => "");

            $size = $pdf->addLine($y, $line);
            $y += $size + 4;

            if (($y > 216) && (($i + 1) < $count)) {
                $append_page_tag = 1;
                $followup_count = $i + 1;
                break;
            }
        }
    }
    }
       $statement_dir = $root_dir . '/'.$billingcompany_id;
    if (!is_dir($statement_dir)) {
        mkdir($statement_dir);
    }

    $dir = $statement_dir . '/providerbilling';
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    $single_file_file = $dir .'/'. date('YmdHis') . '.pdf';
    
    
    $pdf->Output($single_file_file);
    $pdf->Close();
    return $single_file_file;
}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
