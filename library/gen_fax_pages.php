<?php

/*
 * gen_fax_pages
 * generate cms 1500(character)
 * author:Xinwang Qiao
 * July,2011
 */
require_once 'helper.php';
require_once 'claim.php';
require_once 'fpdf.php';
require_once 'fpdi.php';
require_once 'gen_hcfa_1500_pdf_page.php';
require_once 'send_fax.php';
require_once 'fax.php';

function gen_fax_pages($options, $root_dir, $provider_id_array ,$billingcompany_id , $encounter_claim=false) {

//variable declaration
    $ret = array();
    
    /**************************Generate Log file***************************/
     $data = array();
     $fields = array('num', 'date', 'time', 'name', 'mrn', 'dos', 'insurance', 'fax_status');
     $display_fields = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS', 'Insurance', 'Fax Status');
    /**************************Generate Log file***************************/
    
    
    /*******************Claim resort*****************/
    $claims_tmp = array();  
    $ret_index = 0;
    $old_insurance ="";
    $insurance_count = 0;
    $ins_sort = array();
    $sdex = 0;
   /*******************Claim resort*****************/
   
   /*******************Claim resort*****************/
        foreach ($options as $index => $option)
        {
            $claim = new claim($option);
            $new_claim['ret_order'] = $ret_index;
            $new_claim['claim'] = $claim;
            $new_claim['provider_id'] = $provider_id_array[$index];
            array_push($claims_tmp, $new_claim);
            $ret_index = $ret_index + 1;
            array_push($ret, 0);
        }
        
        
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
        
        
        for($i = 0; $i < $ret_index;  $i++)
        {
            if($i == 0)
            {
                array_push($ins_sort,0);
                $old_insurance = $claims_tmp[$i]['claim']->payerName();
                continue;
            }
            if( $claims_tmp[$i]['claim']->payerName() != $old_insurance)
            {
                array_push($ins_sort,$i);
                $old_insurance = $claims_tmp[$i]['claim']->payerName();
            }   
        }
        
       
        
       
        /***************************Find the total page********************/
        $index = 0;
        for($j = 0; $j < sizeof($ins_sort); $j++)
        {
           $sort_order[$index]['s'] = $ins_sort[$j];
           if( $ins_sort[$j] == $ret_index-1 || $j == sizeof($ins_sort)-1)
               $sort_order[$index]['e'] = $ret_index-1;
           else
               $sort_order[$index]['e'] = $ins_sort[$j + 1] - 1;
           $index = $index + 1;
        }
       /***************************Find the total page*******************/
         $index = 0;
         $old_insurance = "";
         
         foreach ($claims_tmp as $index => $new_claim) {          
             $data[$index]['num'] = $index + 1;
             $data[$index]['date'] = date('m/d/Y');
             $data[$index]['time'] = date('H:i',time());
             $data[$index]['name'] = $new_claim['claim']->insuredLastName().' '.$new_claim['claim']->insuredFirstName();
             $data[$index]['mrn'] = "=\"".$new_claim['claim']->patient_account()."\"";
             $data[$index]['dos'] = $new_claim['claim']->getDOS();
             $data[$index]['insurance'] = $new_claim['claim']->displayName(); 
         }
         
         for($i = 0; $i < sizeof($sort_order); $i++)
         {
             $pdf = & new FPDI();
             $totalpages = 0;
             $cover_flag = false;
             
             for($j = $sort_order[$i]['s']; $j <=  $sort_order[$i]['e']; $j++)
             {
                 
                     $log_dir = $root_dir. '/billingcompany';
                        if (!is_dir($log_dir)) {
                            mkdir($log_dir);
                     }
                     $log_dir = $log_dir. '/'.$claims_tmp[$j]['claim']->billingcompany_id();
                        if (!is_dir($log_dir)) {
                        mkdir($log_dir);
                     }
                     
                      $log_dir = $log_dir. '/billlog';
                        if (!is_dir($log_dir)) {
                        mkdir($log_dir);
                     }
    
                      //$dir = $root_dir. '/document/claim/'.$claims_tmp[$j]['claim']->claimid();  
                     $dir = $root_dir . '/' . $billingcompany_id;
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'];
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim';
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim/' . $claims_tmp[$j]['claim']->claimid();
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                $servicecombosheet_path = $root_dir . '/' . $billingcompany_id . '/' . $provider_id_array[$index] . '/claim/' . $claim->claimid() ;
                $servicecombosheet_paths = array();
                if (is_dir($servicecombosheet_path)) {
                    foreach (glob($servicecombosheet_path . '/*.*') as $filename) {
                        array_push($servicecombosheet_paths, $filename);
                    }
                }
                $anesthesiaRecord_name = $claim->AnesthesiaRecord();
                
                foreach($servicecombosheet_paths as $key => $path)
                {
                    //split($path, "/");
                    if(substr_count($path, $anesthesiaRecord_name) >= 1)
                    {
                        $AnesthesiaRecord = $servicecombosheet_paths[$key];
                        break;
                    }
                }
                      //$AnesthesiaRecord = $dir . '/' . $claims_tmp[$j]['claim']->AnesthesiaRecord() . '.pdf';
                      //$AnesthesiaRecord = $root_dir . '/document/encounter/' . $claim->encounterid() . '/' . $claim->AnesthesiaRecord() . '.pdf';
                      if (file_exists($AnesthesiaRecord)) {
                      $pdf->setSourceFile($AnesthesiaRecord);
                      $totalpages += $pdf->current_parser->page_count;
                      }
                      $totalpages+=1;
                } //Find all the $AnesthesiaRecord file number;
                $totalpages += 1; //Add the cover sheet
                
                  
             for($j = $sort_order[$i]['s']; $j <=  $sort_order[$i]['e']; $j++)
             {
                    $dir = $root_dir . '/' . $billingcompany_id;
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'];
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim';
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim/' . $claims_tmp[$j]['claim']->claimid();
                      //$dir = $root_dir. '/document/claim/'.$claims_tmp[$j]['claim']->claimid();                       
                      if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     // $AnesthesiaRecord = $dir . '/' . $claims_tmp[$j]['claim']->AnesthesiaRecord() . '.pdf';
                $servicecombosheet_path = $root_dir . '/' . $billingcompany_id . '/' . $provider_id_array[$index] . '/claim/' . $claim->claimid() ;
                $servicecombosheet_paths = array();
                if (is_dir($servicecombosheet_path)) {
                    foreach (glob($servicecombosheet_path . '/*.*') as $filename) {
                        array_push($servicecombosheet_paths, $filename);
                    }
                }
                $anesthesiaRecord_name = $claim->AnesthesiaRecord();
                foreach($servicecombosheet_paths as $key => $path)
                {
                    //split($path, "/");
                    if(substr_count($path, $anesthesiaRecord_name) >= 1)
                    {
                        $AnesthesiaRecord = $servicecombosheet_paths[$key];
                        break;
                    }
                }
                     //$AnesthesiaRecord = $root_dir . '/document/encounter/' . $claim->encounterid() . '/' . $claim->AnesthesiaRecord() . '.pdf';
                     if(!$cover_flag)
                     {
                          $pdf->setSourceFile('../library/Fax Cover Sheet.pdf');
                          gen_fax_page($claims_tmp[$j]['claim'], $pdf, $totalpages);
                          $cover_flag = true;
                     }
                     $pdf->setSourceFile('../library/BlankCMS1500.pdf');
                     gen_hcfa_1500_pdf_page($claims_tmp[$j]['claim'], $pdf);
                     
                     
                     if (file_exists($AnesthesiaRecord)) {
                             $pdf->setSourceFile($AnesthesiaRecord);
                             $pagenumber = $pdf->current_parser->page_count;
                             for ($j = 1; $j <= $pagenumber; $j++) {
                                 $pdf->AddPage();
                                 $tplIdx = $pdf->importPage($j);
                                 $pdf->useTemplate($tplIdx, 0, 14, 210);
                             }
                        }                    
                }// generate the gother pdf file
                
             for($j = $sort_order[$i]['s']; $j <=  $sort_order[$i]['e']; $j++)
             {
                  //$dir = $root_dir. '/document/claim/'.$claims_tmp[$j]['claim']->claimid();                       
                    $dir = $root_dir . '/' . $billingcompany_id;
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'];
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                     $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim';
                     if (!is_dir($dir)) {
                      mkdir($dir);
                     } 
                    $dir = $root_dir . '/' . $billingcompany_id. '/' . $claims_tmp[$j]['provider_id'] . '/claim/' . $claims_tmp[$j]['claim']->claimid(); 
                    if (!is_dir($dir)) {
                      mkdir($dir);
                     }
                   /*$fax_file_dir = $dir. '/one-off';
                     if (!is_dir($fax_file_dir)) {
                       mkdir($fax_file_dir);
                     }      */
                    $fax_file_dir = $dir;
                    $today = date("Y-m-d H:i:s");
                    $date = explode(' ', $today);
                    $time0 = explode('-',$date[0]);
                    $time1 = explode(':',$date[1]);
                    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name =  $user->user_name;
                    $insurance_name = $claims_tmp[$j]['claim']->payerName();
                    $file_name = $fax_file_dir . '/' . $time . '-' . 'Fax_CMS_1500' . '-' . $insurance_name . '-' . $user_name . '.pdf';
                    //$file_name = $fax_file_dir . '/fax.pdf';
                    $pdf->Output($file_name);
                    $pdf->Close();
             } // output the fax file in every cliam folder
             
               if (file_exists($file_name)) {
                $content['file_path'] = $file_name;

                $ddd = $claims_tmp[$sort_order[$i]['s']]['claim']->payerFax();
                $scd = "1212121212";
                //zw
                //$send_status = send_fax($file_name, $claims_tmp[$sort_order[$i]['s']]['claim']->payerFax());
                $content = array();
                $content['file_path'] = $file_name;
                $send_status = send_mail($content, $claims_tmp[$sort_order[$i]['s']]['claim']->payerFax());
                //
                $scd = "1212121212";
                //array_push($ret, 1);        
            }
             for($j = $sort_order[$i]['s']; $j <=  $sort_order[$i]['e']; $j++)
             {
                 $data[$j]['fax_status'] = $send_status;  
                 if($send_status < 0)
                     $ret[$claims_tmp[$j]['ret_order']] = 0;
                 else
                     $ret[$claims_tmp[$j]['ret_order']] = 1;
             }     
         }
         
    /*************************************Write Log file********************************/
    $log_file_name = $log_dir.'/BillFax.csv';
    $final_length = sizeof($fields);
    
    if(file_exists($log_file_name))
    {
        $rp = fopen($log_file_name, 'r');
        $tmp_log_file = $log_dir.'/tmp.csv';
        $wp = fopen($tmp_log_file, 'w');
        
        
        for ($i = 0; $i < $final_length; $i++) {
            fwrite($wp, $display_fields[$i] . ",");
        }
        fwrite($wp, "\r\n");
      
        for ($i = 0; $i < $ret_index; $i++) {
            for ($j = 0; $j < $final_length; $j++) {
            $ttt = $data[$i][$fields[$j]];
            fwrite($wp, $data[$i][$fields[$j]] . ",");
            //fputcsv($wp, $data[$i]);
            }
            fwrite($wp, "\r\n");
        }
         fwrite($wp, "\r\n");
        
         $bool_head_line = 0;
         
         $read_old_log = fgets($rp);
         while(!feof($rp))
         {
             $read_old_log = fgets($rp);
             fwrite($wp, $read_old_log);
         }
         fclose($wp);
         fclose($rp);
         
         if (!unlink($log_file_name))
            echo ("Error deleting $log_file_name");
         else
            echo ("Deleted $log_file_name");
          
        rename($tmp_log_file,$log_file_name);
    }
    else
    {
        $fp = fopen($log_file_name, 'w');
    
        for ($i = 0; $i < $final_length; $i++) {
            fwrite($fp, $display_fields[$i] . ",");
            }
             fwrite($fp, "\r\n");


        for ($i = 0; $i < $ret_index; $i++) {
            for ($j = 0; $j < $final_length; $j++) {
            $ttt = $data[$i][$fields[$j]];
            fwrite($fp, $data[$i][$fields[$j]] . ",");
            //fputcsv($fp, $data[$i]);
            }
            fwrite($fp, "\r\n");
        }
        fclose($fp);
    }
     /*************************************Write Log file********************************/
    
    return $ret;

        
        
    
    
    
   /********************************old*****************************
    try {       

        foreach ($claims_tmp as $index => $new_claim) {
            
            if($index == $ins_sort[$sdex])
            {
                $pdf = & new FPDI();
            }
            
        
            $dos = date('Ymd', strtotime($claim->DOS(0)));
            $dir = $root_dir . '/document/' . $claim->patient_account() . '-' . $dos;
          
            
            
            $log_dir = $root_dir. '/document/billingcompany';
             if (!is_dir($log_dir)) {
                mkdir($log_dir);
            }
            $log_dir = $log_dir. '/'.$new_claim['claim']->billingcompany_id();
             if (!is_dir($log_dir)) {
                mkdir($log_dir);
            }
          
            
            
            $dir = $root_dir. '/document/claim/'.$new_claim['claim']->claimid();  
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            
             $fax_file_dir = $dir. '/one-off';
             if (!is_dir($fax_file_dir)) {
                mkdir($fax_file_dir);
            }                           
            $file_name = $fax_file_dir . '/fax.pdf';
  
            
            
            $AnesthesiaRecord = $dir . '/' . $new_claim['claim']->AnesthesiaRecord() . '.pdf';
            $pagenumber = 0;
            
            if (file_exists($AnesthesiaRecord)) {
                $pdf->setSourceFile($AnesthesiaRecord);
                $pagenumber = $pdf->current_parser->page_count;
            }
            
            $pdf->setSourceFile('../library/Fax Cover Sheet.pdf');
            $total_page_count = 2 + $pagenumber;
            gen_fax_page($new_claim['claim'], $pdf, $total_page_count);

            $pdf->setSourceFile('../library/BlankCMS1500.pdf');
            gen_hcfa_1500_pdf_page($new_claim['claim'], $pdf);

            //AnesthesiaRecord

            if (file_exists($AnesthesiaRecord)) {
                $pdf->setSourceFile($AnesthesiaRecord);
                $pagenumber = $pdf->current_parser->page_count;
                for ($j = 1; $j <= $pagenumber; $j++) {
                    $pdf->AddPage();
                    $tplIdx = $pdf->importPage($j);
                    $pdf->useTemplate($tplIdx, 0, 14, 210);
                }
            }
            

            $pdf->Output($file_name);
            $pdf->Close();
            //send file
            if (file_exists($file_name)) {
                $content['file_path'] = $file_name;
                   //$send_status = send_fax($file_name,$new_claim['claim']->payerFax());
                //array_push($ret, 1);
                $ret[$new_claim['ret_order']] = 1;   
            }
            
        }
       
        
    } catch (Exception $e) {
        //array_push($ret, 0);
        $ret[$new_claim['ret_order']] = 0;
    }
     *******************************old******************************/
   
}

function gen_fax_page($claim, $pdf, $pagecount) {
// import page 1
    $pdf->AddPage();
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx, 0, 14, 210);
    $LargeFontSize = 12;
    $NormalFontSize = 10;
    $SmallFontSize = 8;
    $LineSpacing = 12;
    $left = 90;

    $Offset1 = 1;
    $Offset2 = 1;
    $startline = 82;

//if-image
    //   $pdf->Image('images/insurance.png', 84, 36, 45);
    $pdf->SetFont('Arial', "B", 26);
    $pdf->SetXY(20, 45);
    //  $pdf->Write(0, $claim->billingcompany_name());
    //$pdf->Multicell(180, 10, $claim->billingcompany_name(), '', 'C');
    $pdf->Multicell(180, 10, $claim->billing_provider_name(), '', 'C');
    $pdf->SetFont('Courier', "", $NormalFontSize);
    $tmp = $claim->billing_provider_street() . ' ' . $claim->billing_provider_city() . ' '
            . $claim->billing_provider_state() . ' ' . zip_format($claim->billing_provider_zip());
    $pdf->SetXY(70, 60);
    $pdf->Write(0, $tmp);
    //phone
    $billing_phone = '';
    $billing_fax = '';
    $billing_contact = '';
    if ($claim->billing_provider_phone()) {
        $billing_phone = phone_format($claim->billing_provider_phone());
        $billing_contact = $billing_contact.'Tel:' . $billing_phone;
    }
    if ($claim->billing_provider_fax()) {
        $billing_fax = phone_format($claim->billing_provider_fax());
        $billing_contact = $billing_contact.' Fax:' . $billing_fax;
    }
    $pdf->SetXY(70, 64);
    $pdf->Write(0, $billing_contact);
    $pdf->SetFont('Arial', "", $LargeFontSize);
    $pdf->SetXY($left, $startline + 1);
    $pdf->Write(0, $claim->payerName());
    if ($claim->payerPhoneNumber()) {
        $pdf->SetXY($left, $startline + $LineSpacing);
        $pdf->Write(0, phone_format($claim->payerPhoneNumber()));
    }
    if ($claim->payerFax()) {
        $pdf->SetXY($left, $startline + $LineSpacing * 2);
        $pdf->Write(0, phone_format($claim->payerFax()));
    }
    $pdf->SetXY($left, $startline + $LineSpacing * 3 - 0.5);
    $pdf->Write(0, $pagecount);
    $pdf->SetXY($left, $startline + $LineSpacing * 4 - 1.5);
    $pdf->Write(0, date("m/d/Y"));
    //$tmp = $claim->patientLastName() . ', ' . $claim->patientFirstName() . '/' . $claim->policyNumber();
    $pdf->SetXY($left, $startline + $LineSpacing * 5 - 2);
    //$pdf->Write(0, $tmp);
    $pdf->Write(0, "New Bill");
    $payertype = $claim->payerType();
   
    /***********Cancel the Comments*************/
    /*
    if ($payertype == 'LI') {
        $pdf->SetXY(25, $startline + $LineSpacing * 6);
        if ($claim->referrerLastName()) {
            $referringprovider = $claim->referrerFirstName() . ' ' . $claim->referrerLastName();
            $dos = date('m/d/Y', strtotime($claim->DOS(0)));
            $comment = '    This is the anesthesia bill for the procedure done by Dr.' . $referringprovider . ' on ' . $dos;
            $comment = $comment . ' at ' . $claim->facilityName().' in '.$claim->facilityCity().', '.$claim->facilityState();
            $pdf->Multicell(145, 19, $comment);
        }
    } else {
        $pdf->SetXY(40, $startline + $LineSpacing * 7 - 1);
        $pdf->Write(0, 'New Bill');
    }
     * */
     /***********Cancel the Comments*************/
    
    $pdf->SetXY(25, $startline + $LineSpacing * 11 - 1);
    $pdf->Write(0, 'If you do not receive all pages, please contact the sender at ' . $billing_phone);
}

?>
