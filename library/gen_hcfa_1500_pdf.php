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
require_once 'gen_hcfa_1500_pdf_page.php';

function gen_hcfa_1500_pdf($document_feature, $options, $root_dir, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id, $encounter_claim = false, $form_flag = true) {

//variable declaration
    $ret = array();
    $re = array();

    try {
        //$file_dir = $root_dir . '/' . $billingcompany_id . '/cms1500';
        $file_dir = $root_dir . '/' . $billingcompany_id;
        if (!is_dir($file_dir)) {
            mkdir($file_dir);
        }
        $file_dir = $file_dir . '/cms1500';
        if (!is_dir($file_dir)) {
            mkdir($file_dir);
        }

        $file_name = $root_dir . '/' . $billingcompany_id . '/cms1500/' . date('YmdHis') . '.pdf';
        $pdf = & new FPDI();
        // set the sourcefile
        foreach ($options as $index => $option) {

            $pdf->setSourceFile('../library/BlankCMS1500.pdf');

            $claim = new claim($option);

            /*             * *************Juage the button****************** */
            //$sec_flag == 0 && $claim->get_claim_status() == 'open_ready_secondary_bill'
            if ($sec_flag == 0 && $claim->get_bill_status() == 'bill_ready_bill_secondary') {
                array_push($ret, 0);
                continue;
            } elseif ($sec_flag == 1 && $claim->get_bill_status() == 'bill_ready_bill_primary') {
                array_push($ret, 0);
                continue;
            }


            /*             * *************Juage the button****************** */
            /* if($form_flag){
              gen_hcfa_1500_pdf_page($claim, $pdf,$form_flag);
              }else{
              gen_hcfa_1500_pdf_page_without_form($claim, $pdf,$billingcompany_id);
              } */
            gen_hcfa_1500_pdf_page($claim, $pdf, $billingcompany_id, $form_flag);


            array_push($ret, 1);

            $single_pdf = & new FPDI();
            $single_pdf_onForm = & new FPDI();
//            $dos = date('Ymd', strtotime($claim->DOS(0)));
            //$dir = $root_dir . '/' . $billingcompany_id . '/' . $provider_id_array[$index] . '/' . $claim->claimid();
            $dir = $root_dir . '/' . $billingcompany_id;
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $dir = $dir . '/' . $provider_id_array[$index];
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $dir = $dir . '/' . 'claim';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            $dir = $dir . '/' . $claim->claimid();
            if (!is_dir($dir)) {
                mkdir($dir);
            }

            $insurace_name = $claim->payerName();
            $today = date("Y-m-d H:i:s");

            $date = explode(' ', $today);
            $time0 = explode('-', $date[0]);
            $time1 = explode(':', $date[1]);
            $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            /*             * **change the dir of the secondary insurance pdf <By Yu Lang>*** */
            if ($sec_flag == 0) {
                $single_file_file = $dir . '/' . $time . '-CMS1500-' . $insurace_name . '-' . $user_name . '.pdf';
                $single_file_file_on_form = $dir . '/' . $time . '-CMS1500_No Form-' . $insurace_name . '-' . $user_name . '.pdf';
            }
            if ($sec_flag == 1) {
                //$dir = $dir . '/one-off';
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
                if ($claim->get_bill_status() == "bill_ready_bill_other") {
                    $single_file_file = $dir . '/' . $time . '-CMS1500_Other-' . $insurace_name . '-' . $user_name . '.pdf';
                    $single_file_file_on_form = $dir . '/' . $time . '-CMS1500_Other_No Form-' . $insurace_name . '-' . $user_name . '.pdf';
                } else {
                    $single_file_file = $dir . '/' . $time . '-CMS1500_Sec-' . $insurace_name . '-' . $user_name . '.pdf';
                    $single_file_file_on_form = $dir . '/' . $time . '-CMS1500_Sec_No Form-' . $insurace_name . '-' . $user_name . '.pdf';
                }
            } else if ($claim->get_claim_status() == 'open_not_rebilled') {
                //$dir = $dir . '/one-off';
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
                $single_file_file = $dir . '/' . $time . '-CMS1500_REB-' . $insurace_name . '-' . $user_name . '.pdf';
                $single_file_file_on_form = $dir . '/' . $time . '-CMS1500_REB_No Form-' . $insurace_name . '-' . $user_name . '.pdf';
            }
            
            /*             * **change the dir of the secondary insurance pdf <By Yu Lang>*** */

            $single_pdf->setSourceFile('../library/BlankCMS1500.pdf');
            gen_hcfa_1500_pdf_page($claim, $single_pdf, $billingcompany_id, true);

            $single_pdf_onForm->setSourceFile('../library/BlankCMS1500.pdf');
            gen_hcfa_1500_pdf_page($claim, $single_pdf_onForm, $billingcompany_id, false);
            //AnesthesiaRecord
            if ($sec_flag == 0) {
                $servicecombosheet_path = $root_dir . '/' . $billingcompany_id . '/' . $provider_id_array[$index] . '/claim/' . $claim->claimid();
                $servicecombosheet_paths = array();
                if (is_dir($servicecombosheet_path)) {
                    foreach (glob($servicecombosheet_path . '/*.*') as $filename) {
                        array_push($servicecombosheet_paths, $filename);
                    }
                }
                $anesthesiaRecord_name = $claim->AnesthesiaRecord();
                foreach ($servicecombosheet_paths as $key => $path) {
                    //split($path, "/");
                    if (substr_count($path, $anesthesiaRecord_name) >= 1) {
                        $AnesthesiaRecord = $servicecombosheet_paths[$key];
                        break;
                    }
                }
                //$AnesthesiaRecord = $root_dir . '/document/encounter/' . $claim->encounterid() . '/' . $claim->AnesthesiaRecord() . '.pdf';
                if ($document_feature) {
                    if (file_exists($AnesthesiaRecord)) {
                        $pdf->setSourceFile($AnesthesiaRecord);
                        $pagenumber = $pdf->current_parser->page_count;
                        for ($j = 1; $j <= $pagenumber; $j++) {
                            $pdf->AddPage();
                            $tplIdx = $pdf->importPage($j);
                            $pdf->useTemplate($tplIdx, 0, 14, 210);
                        }
                    }
                }
            }
            /*             * *****************Generate the EOB file********************** */
            /* no longer need to add the EOB together with the CMS1500 */
            /* elseif($sec_flag == 1)
              {
              $EOBFILE = $root_dir . '/document/claim/' . $claim->claimid() . '/EOB.pdf';
              if (file_exists($EOBFILE)) {
              $pdf->setSourceFile($EOBFILE);
              $pagenumber = $pdf->current_parser->page_count;
              for ($j = 1; $j <= $pagenumber; $j++) {
              $pdf->AddPage();
              $tplIdx = $pdf->importPage($j);
              $pdf->useTemplate($tplIdx, 0, 14, 210);
              }
              }
              } */
            /*             * *****************Generate the EOB file********************** */
            $single_pdf->Output($single_file_file);
            $single_pdf->Close();
            $single_pdf_onForm->Output($single_file_file_on_form);
            $single_pdf_onForm->Close();
        }
//        header('Content-type: application/pdf');
//        header('Content-Disposition: attachment');
//        //readfile($file_name);
//        ob_end_flush();
        $pdf->Output($file_name);
        $pdf->Close();
        //  download($file_name);
    } catch (Exception $e) {
        array_push($ret, 0);
    }
    $re['ret'] = $ret;
    $re['file_name'] = $file_name;


    /*     * **************************************************************Gen cms Log************************************************** */


    /*     * *****************Claim resort**************** */

    if ($gen_log_flag >= 1) {
        /*         * ************************Generate Log file************************** */
        $data = array();
        $fields = array('num', 'date', 'time', 'name', 'mrn', 'dos', 'insurance',);
        $display_fields = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS', 'Insurance');
        /*         * ************************Generate Log file************************** */


        /*         * *****************Claim resort**************** */
        $claims_tmp = array();
        $ret_index = 0;
        $old_insurance = "";
        $insurance_count = 0;
        $ins_sort = array();
        $sdex = 0;
        /*         * *****************Claim resort**************** */

        foreach ($options as $index => $option) {
            $claim = new claim($option);
            $new_claim['ret_order'] = $ret_index;
            $new_claim['claim'] = $claim;
            array_push($claims_tmp, $new_claim);
            $ret_index = $ret_index + 1;
            array_push($ret, 0);
        }

        $billingcompany_id = $claims_tmp[0]['claim']->billingcompany_id();

        for ($i = 0; $i < $ret_index - 1; $i++) {
            for ($j = $i + 1; $j < $ret_index; $j++) {
                $a = $claims_tmp[$i]['claim']->payerName();
                $b = $claims_tmp[$j]['claim']->payerName();
                if ($a > $b) {
                    $temp = $claims_tmp[$i];
                    $claims_tmp[$i] = $claims_tmp[$j];
                    $claims_tmp[$j] = $temp;
                }
            }
        }
        $tp_index = 0;
        foreach ($claims_tmp as $index => $new_claim) {
            //$sec_flag == 0 && $new_claim['claim']->get_claim_status() == 'open_ready_secondary_bill'
            if ($sec_flag == 0 && $new_claim['claim']->get_bill_status() == 'bill_ready_bill_secondary')
                continue;
            if ($sec_flag == 1 && $new_claim['claim']->get_bill_status() == 'bill_ready_bill_primary')
                continue;
            if ($sec_flag == 0 && $new_claim['claim']->get_bill_status() == 'bill_ready_bill_other')
                continue;

            $data[$tp_index]['num'] = $tp_index + 1;
            $data[$tp_index]['date'] = date('m/d/Y');
            $data[$tp_index]['time'] = date('H:i', time());
            $data[$tp_index]['name'] = $new_claim['claim']->insuredLastName() . ' ' . $new_claim['claim']->insuredFirstName();
            $data[$index]['mrn'] = "=\"" . $new_claim['claim']->patient_account() . "\"";
            $data[$tp_index]['dos'] = $new_claim['claim']->getDOS();
            $data[$tp_index]['insurance'] = $new_claim['claim']->displayName();
            $tp_index++;
        }


//             $log_dir = $root_dir. '/billingcompany';
//                if (!is_dir($log_dir)) {
//                       mkdir($log_dir);
//                }

        $log_dir = $root_dir . '/' . $billingcompany_id;
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $log_dir = $log_dir . '/billlog';
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }


        /*         * ***********************************Write Log file******************************* */
        if ($sec_flag == 1)
            $log_file_name = $log_dir . '/BillSecond.csv';
        else if ($sec_flag == 0) {
            if($gen_log_flag == 1)
                $log_file_name = $log_dir . '/BillCMS1500.csv';
            else
                $log_file_name = $log_dir . '/BillEDI.csv';
        }    
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
    }

    /*     * **************************************************************Gen cms Log************************************************** */







    return $re;
}

?>