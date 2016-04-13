<?php

require('statement.php');

function gen_claimstatement_pdf($data, $ledger, $root_dir,$billingcompany_id,$provider_id,$patient_id,$claim_id) {


    $statement_type = $data[statement_type];
    //$statement_dir = $root_dir . '/statements/';
    //$statement_dir = $root_dir . '/' . $billingcompany_id .'/statements/';
    //$claim_dir = $root_dir . '/document/claim/' . $data[claim_id] . '/';
    //$claim_dir = $root_dir . '/' . $billingcompany_id . '/' . $provider_id . '/' . $patient_id;
    //if (!is_dir($statement_dir)) {
    //    mkdir($statement_dir);
    //}
//    if (!is_dir($claim_dir)) {
//        mkdir($claim_dir);
//    }
    
    
                $claim_dir = $root_dir . '/' . $billingcompany_id; 
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . $provider_id;
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . 'claim';
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . $claim_id;
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }   
    
    //$claim_dir_oneoff = $claim_dir . '/';
    //$dir = $root_dir . '/statements/';

    //if (!is_dir($claim_dir_oneoff)) {
    //    mkdir($claim_dir_oneoff);
    //}

    //$single_file_file = $dir . date('YmdHis') . '.pdf';

    $pdf = new PDF_Invoice('P', 'mm', 'Letter');
    $pdf->AddPage();

//           "2 Summit Road\n" .
//            "Summit, NJ 07901\n" .
//            "Phone 973-897-1234\n"
//      need to know

    $provider_phone = gen_phone_format($data[provider_phoneNumber]);
    $provider_zip = gen_zip_format($data[provider_zip]);

    $pdf->addProvider($data[provider_name], $data[prd_street_address] . "\n" .
            $data[provider_city] . ', ' . $data[provider_state] . ' ' . $provider_zip . "\n" .
            'Phone ' . $provider_phone . "\n");


    $user = Zend_Auth::getInstance()->getIdentity();
    $username = $user->user_name;
    $today = date("Y-m-d H:i:s");
    $date = explode(' ', $today);
    $time0 = explode('-',$date[0]);
    $time1 = explode(':',$date[1]);
    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
    $interactionlogs_data['claim_id'] = $claim_id;
    $interactionlogs_data['date_and_time'] = $today;
    //$interactionlogs_data['log'] = $username.": Statement I generation";
    if ($statement_type == '1') {
        $interactionlogs_data['log'] = $username.": Statement I generation";
    } elseif ($statement_type == '2') {
        $interactionlogs_data['log'] = $username.": Statement II generation";
    } elseif ($statement_type == '3') {
       $interactionlogs_data['log'] = $username.": Statement III generation";
    } else {
      $interactionlogs_data['log'] = $username.": Installment Statement generation";  
    }
    mysql_insert('interactionlog', $interactionlogs_data);
    
    if ($statement_type == '1') {
        $pdf->Notice("MEDICAL BILL");
        $pdf->WaterMark("MEDICAL BILL");
        $stmt_file = $claim_dir . '/' . $time . '-StatementI-Patient-' . $username . '.pdf';
    } elseif ($statement_type == '2') {
        $pdf->Notice("MEDICAL BILL/SECOND NOTICE");
        $pdf->WaterMark("SECOND NOTICE");
        $stmt_file = $claim_dir .  '/' . $time . '-StatementII-Patient-' . $username . '.pdf';
    } elseif ($statement_type == '3') {
        $pdf->Notice("MEDICAL BILL/FINAL NOTICE");
        $pdf->WaterMark("FINAL NOTICE");
        $stmt_file = $claim_dir . '/'.  $time . '-StatementIII-Patient-' . $username . '.pdf';
    } else {
        $pdf->Notice("MEDICAL BILL");
        $pdf->WaterMark("MEDICAL BILL");
        $stmt_file = $claim_dir . '/' . $time . '-Installment-Patient-' . $username . '.pdf';
    }


    // what is due data?
    $pdf->addDueDate("Upon Receipt");

    $pdf->addAcctNum($data[account_number]);

    // statementdata? start or generate the pdf. leger[1] or leger[2] date?
    $pdf->addStatementDate(date("m/d/Y"));


    //who is client?patient? and how to get the address
   $patient_zip = gen_zip_format($data[patient_zip]);


    $pdf->addClientAdresse($data[p_last_name] . ', ' . $data[p_first_name] . "\n"
            . $data[patient_street_address] . "\n"
            . $data[patient_city] . ", "
            . $data[patient_state] . " "
            . $patient_zip);

// send to who?


    $provider_b_phone = gen_phone_format($data[provider_b_phoneNumber]);
    $provider_b_zip = gen_zip_format($data[provider_b_zip]);

    $pdf->addSendTo("\n\n" . $data[provider_b_name] . "\n" . $data[provider_b_street_address] . "\n" . $data[provider_b_city] . ", " . $data[provider_b_state] . ' ' . $provider_b_zip . "\n" .
            "Phone " . $provider_b_phone);
    $cols = array("Date" => 22,
        "Description" => 95,
        "Amount" => 19,
        "Balance" => 20,
        "Notes" => 48);

    $pdf->addCols($cols);
    $y = 100;
    $append_page_tag = 0;
    $followup_count = 0;
    $count = count($ledger);
    $count = $count-1;
    for ($i = 0; $i < $count; $i++) {
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
            "Notes" => $ledger[$i][notes]);
        $size = $pdf->addLine($y, $line);
        $y += $size + 4;
        if (($y > 215) && (($i + 1) < $count)) {
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

            $line = array("Date" => $ledger[$i][date], //leger1 or 2 date?
                "Description" => $description,
                "Amount" => $ledger[$i][amount],
                "Balance" => $ledger[$i][balance],
                "Notes" => $ledger[$i][notes]);

            $size = $pdf->addLine($y, $line);
            $y += $size + 4;

            if (($y > 215) && (($i + 1) < $count)) {
                $append_page_tag = 1;
                $followup_count = $i + 1;
                break;
            }
        }
    }

//    $pdf->Output($single_file_file);
    $pdf->Output($stmt_file);
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



function gen_statement_pdf($datas, $ledgers, $root_dir ,$billingcompany_id,$provider_id_array,$patient_id_array,$claim_id_array) {
    $count_total = count($datas);
    $pdf = new PDF_Invoice('P', 'mm', 'Letter');
    for ($j = 0; $j < $count_total; $j++) {
        $provider_id = $provider_id_array[$j];
        $patient_id = $patient_id_array[$j];
        $claim_id = $claim_id_array[$j];
        $data = $datas[$j];
        $ledger = $ledgers[$j];
        gen_claimstatement_pdf($data,$ledger,$root_dir,$billingcompany_id,$provider_id,$patient_id,$claim_id);
        $statement_type = $data[statement_type];



        $pdf->AddPage();

//           "2 Summit Road\n" .
//            "Summit, NJ 07901\n" .
//            "Phone 973-897-1234\n"
//      need to know

        $provider_phone = gen_phone_format($data[provider_phoneNumber]);
        $provider_zip = gen_zip_format($data[provider_zip]);

        $pdf->addProvider($data[provider_name], $data[prd_street_address] . "\n" .
                $data[provider_city] . ', ' . $data[provider_state] . ' ' . $provider_zip . "\n" .
                'Phone ' . $provider_phone . "\n");



        if ($statement_type == '1') {
            $pdf->Notice("MEDICAL BILL");
            $pdf->WaterMark("MEDICAL BILL");
        } elseif ($statement_type == '2') {
            $pdf->Notice("MEDICAL BILL/SECOND NOTICE");
            $pdf->WaterMark("SECOND NOTICE");
        } elseif ($statement_type == '3') {
            $pdf->Notice("MEDICAL BILL/FINAL NOTICE");
            $pdf->WaterMark("FINAL NOTICE");
        } else {
            $pdf->Notice("MEDICAL BILL");
            $pdf->WaterMark("MEDICAL BILL");
        }


        // what is due data?
        $pdf->addDueDate("Upon Receipt");

        $pdf->addAcctNum($data[account_number]);

        // statementdata? start or generate the pdf. leger[1] or leger[2] date?
        $pdf->addStatementDate(date("m/d/Y"));


        //who is client?patient? and how to get the address
        $patient_zip = gen_zip_format($data[patient_zip]);


        $pdf->addClientAdresse($data[p_last_name] . ', ' . $data[p_first_name] . "\n"
                . $data[patient_street_address] . "\n"
                . $data[patient_city] . ", "
                . $data[patient_state] . " "
                . $patient_zip);

// send to who?


        $provider_b_phone = gen_phone_format($data[provider_b_phoneNumber]);
        $provider_b_zip  = gen_zip_format($data[provider_b_zip]);

        $pdf->addSendTo("\n\n" . $data[provider_b_name] . "\n" . $data[provider_b_street_address] . "\n" . $data[provider_b_city] . ", " . $data[provider_b_state] . ' ' . $provider_b_zip . "\n" .
                "Phone " . $provider_b_phone);
        $cols = array("Date" => 22,
            "Description" => 95,
            "Amount" => 19,
            "Balance" => 20,
            "Notes" => 48);

        $pdf->addCols($cols);
        $y = 100;
        $append_page_tag = 0;
        $followup_count = 0;
        $count = count($ledger);
        $count = $count -1;
        for ($i = 0; $i < $count; $i++) {
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
                "Notes" => $ledger[$i][notes]);
            $size = $pdf->addLine($y, $line);
            $y += $size + 4;
            if (($y > 215) && (($i + 1) < $count)) {
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

                $line = array("Date" => $ledger[$i][date], //leger1 or 2 date?
                    "Description" => $description,
                    "Amount" => $ledger[$i][amount],
                    "Balance" => $ledger[$i][balance],
                    "Notes" => $ledger[$i][notes]);

                $size = $pdf->addLine($y, $line);
                $y += $size + 4;

                if (($y > 215) && (($i + 1) < $count)) {
                    $append_page_tag = 1;
                    $followup_count = $i + 1;
                    break;
                }
            }
        }
    }
       $statement_dir = $root_dir . '/' . $billingcompany_id . '/statements/';
    if (!is_dir($statement_dir)) {
        mkdir($statement_dir);
    }

    $dir = $root_dir .  '/' . $billingcompany_id . '/statements/';
    $single_file_file = $dir . date('YmdHis') . '.pdf';
    
    
    $pdf->Output($single_file_file);
    $pdf->Close();
    return $single_file_file;
}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
