<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function is_pwd_validate($pwd1, $pwd2) {
    if ($pwd1 == '' || ($pwd1 != $pwd2))
        return false;
    return true;
}

function create_zip($files = array(),$destination = '',$overwrite = false) {
	//if the zip file already exists and overwrite is false, return false
	if(file_exists($destination) && !$overwrite) { return false; }
	//vars
	$valid_files = array();
	//if files were passed in...
	if(is_array($files)) {
		//cycle through each file
		foreach($files as $file) {
			//make sure the file exists
			if(file_exists($file)) {
				$valid_files[] = $file;
			}
		}
	}
	//if we have good files...
	if(count($valid_files)) {
		//create the archive
		$zip = new ZipArchive();
		if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
			return false;
		}
		//add the files
		foreach($valid_files as $file) {
			$zip->addFile($file,  basename($file));//第二个参数用于指定压缩包内部的文件结构,basename 函数用于获取文件名（包含后缀）。
		}
		$zip->close();
		
		//check to make sure the file exists
		return file_exists($destination);
	}
	else
	{
		return false;
	}
}

function filter($str) {
    Zend_Loader::loadClass('Zend_Filter_StripTags');
    $filter = new Zend_Filter_StripTags();
    return $filter->filter($str);
}

function get_post_value($fields, $request) {
    $data = array();
    foreach ($fields as $key => $value) {
        $data[$value] = filter($request->getPost($value));
    }
    return $data;
}

function rowsetToArray($fields, $rowset) {
    $result = array();
    foreach ($rowset as $row) {
        $result[] = rowToArray($fields, $row);
    }
    return $result;
}

function rowToArray($fields, $row) {
    $result = array();
    foreach ($fields as $key => $value) {
        $result[$value] = $row->$value;
    }
    return $result;
}

/**
 * get submission NO
 * the NO is unique in the system
 * atuo increment
 */
function submission_counter() {
    $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
    $document_root = '../../' . $config->dir->document_root;
//get counter
    $fp = fopen($document_root . 'submission_counter.txt', "r");
    $num = fgets($fp, 9);
    fclose($fp);
//add by 1
    $num++;
    $fp = fopen($document_root . 'submission_counter.txt', "w");
    fputs($fp, $num);
    fclose($fp);
    return $num;
}

function minute_diff($time1, $time2) {
    $diff = bcdiv(strtotime($time1) - strtotime($time2), 60);
    if ($diff < 0)
        $diff = $diff + 24 * 60;
    return floor($diff);
}

function day_diff($date1, $date2) {
    if ($date1 < $date2) {
        $tmp = $date1;
        $date1 = $date2;
        $date2 = $tmp;
    }
    $second_dff = strtotime($date1) - strtotime($date2);
    $day_diff = floor($second_dff / (3600 * 24));
    return $day_diff;
}

function zip_format($zip) {
    $len = strlen($zip);
    if ($len == 5)
        return $zip;
    if ($len == 9) {
        return substr($zip, 0, 5) . '-' . substr($zip, 5, 4);
    }
}

function datecheck($input) {
    if ($input == null || $input == '0000-00-00' || $input == '0000-00-00 00:00:00' || $input == "" || $input == '' || $input == 'null')
        return false;
    else
        return true;
}

function numbercheck($input) {
    if ($input == null || $input == '' || $input == "")
        return false;
    else
        return true;
}

function zerocheck($input) {
    if ($input == null || $input == '' || $input == "" || $input == 0)
        return false;
    else
        return true;
}

function format($input, $conversion) {

    if ($conversion == 0) {
        if (datecheck($input)) {
            return date('Y-m-d', strtotime($input));
        }
        else
            return NULL;
//        else {
//            return '';
//        }
    }
    if ($conversion == 1) {
        if (datecheck($input)) {
            return date('m/d/Y', strtotime($input));
        }
        return;
//        else {
//            return '';
//        }
    }
    if ($conversion == 2) {
        if (datecheck($input)) {
            return date('m/d/Y', strtotime($input));
        } else {
            return date('m/d/Y');
        }
    }
    if ($conversion == 3) {
        if (datecheck($input)) {
            return date('H:i', strtotime($input));
        }
        return;
//        else {
//            return '';
//        }
    }
    if ($conversion == 4) {
        if (datecheck($input)) {
            return date('m/d/Y H:i', strtotime($input));
        }
        return;
//        else {
//            return '';
//        }
    }
    if ($conversion == 5) {
        if (datecheck($input)) {
            return date('Y-m-d H:i', strtotime($input));
        }
        return;
//        else {
//            return '';
//        }
    }
    if($conversion == 6) {
        if(datecheck($input)) {
            return date('Y-m-d H:i:s', strtotime($input));
        }
    }
    if ($conversion == 7) {
        if (datecheck($input)) {
            return date('m/d/Y H:i:s', strtotime($input));
        }
        return;
    }
}

function decimal($input) {
    if (numbercheck($input))
        return $input;
    else {
        return;
    }
}

function currency($input) {
    if (numbercheck($input))
        return $input;
    else {
        return 0.00;
    }
}

function percentage($input, $conversion) {
    if ($input != null && $input != "" && $input != '') {
        if ($conversion == 1) {
            $tmp = str_replace(array("%", " "), array("", ""), $input);
            return $tmp / 100;
        }
        if ($conversion == 2) {
            return $input * 100 . "%";
        }
    }
    else
        return;
}

function modifier($modifier, $defaultmodifier) {
    if ($modifier == null || $modifier == "" || $modifier == '')
        return $defaultmodifier;
    else
        return $modifier;
}

function place($place, $defaultplace) {
    if ($place == null || $place == "" || $place == '')
        return $defaultplace;
    else
        return $place;
}

function computetime($startdate, $enddate, $starttime, $endtime) {
    if (datecheck($startdate) && datecheck($enddate) && datecheck($starttime) && datecheck($endtime))
        return ceil((strtotime($enddate . ' ' . $endtime) - strtotime($startdate . ' ' . $starttime)) / 60);
    else {
        return;
    }
}

function days($startdate, $enddate) {
    return ceil((strtotime($startdate) - strtotime($enddate)) / 3600 / 24);
}

function ssn($myssn) {
    $tmp = str_replace(array("-"), array(""), $myssn);
    if ($tmp != null) {
        for ($i = 0; $i < strlen($tmp); $i++) {
            if ($i == 2 || $i == 4) {
                $SSN = $SSN . $tmp[$i];
                $SSN = $SSN . '-';
            } else {
                $SSN = $SSN . $tmp[$i];
            }
        }
        return $SSN;
    }
    else
        return;
}

function zip($myzip) {
    $tmp = str_replace(array("-"), array(""), $myzip);
    $len = strlen($tmp);
    if ($tmp != null) {
        for ($i = 0; $i < $len; $i++) {
            if ($i == 4 && $len > 5) {
                $zip = $zip . $tmp[$i];
                $zip = $zip . '-';
            } else {
                $zip = $zip . $tmp[$i];
            }
        }
        return $zip;
    }
    else
        return;
}

function phone($myphone) {
    $tmp = str_replace(array("-", "(", ")"), array("", "", ""), $myphone);
    if ($tmp != null) {
        $phone_number = $phone_number . '(';
        for ($i = 0; $i < strlen($tmp); $i++) {
            if ($i == 2) {
                $phone_number = $phone_number . $tmp[$i];
                $phone_number = $phone_number . ')';
            } else
            if ($i == 5) {
                $phone_number = $phone_number . $tmp[$i];
                $phone_number = $phone_number . '-';
            } else {
                $phone_number = $phone_number . $tmp[$i];
            }
        }
        return $phone_number;
    } else {
        return;
    }
}
 function adddatalog($provider_id,$table_name,$DBfield,$oldvalue,$newvalue)
{
            //$this->_helper->viewRenderer->setNoRender();
      if(!$newvalue){
          $newvalue= NULL;
      }
      if(!$oldvalue){
          $oldvalue= NULL;
      }
       
       $db_provider1 = new Application_Model_DbTable_Provider();
       $db1 = $db_provider1->getAdapter();
       
     
      if($newvalue!=$oldvalue){
           $user = Zend_Auth::getInstance()->getIdentity();
           $db = Zend_Registry::get('dbAdapter');
           
//           $db_user = new Application_Model_DbTable_User();
//           $dbuser= $db_user->getAdapter();
//           $where = $dbuser->quoteInto('user_name = ?', );
//           $user_data = $db_user->fetchRow($where);
           $user_name =$user->user_name;
           /*      'id',
               'data_and_time',
               'user_id',
               '',
               'oldvalue',
               'newvalue',*/
           $Now_time= date("Y-m-d H:i:s");
           
           $logs['data_name']=$table_name;
           $logs['user']=$user_name;
           $logs['data_and_time']=$Now_time;
           $logs['dbfield']=$DBfield;
           $logs['oldvalue']=$oldvalue;
           $logs['newvalue']=$newvalue;
         
           $db_datalogs=new Application_Model_DbTable_Datalog();
           $dblog= $db_datalogs->getAdapter();
           if($provider_id==0){
               $billingcompany_id = $this->billingcompany_id();
               $where=$db1->quoteInto('billingcompany_id=?',$billingcompany_id);
               $provider_data=$db_provider1->fetchAll($where)->toArray();
               for($i=0;$i<count($provider_data);$i++){
                   $provider_name=$provider_data[$i]['provider_name'];
                   $logs['provider']=$provider_name;
                  $db_datalogs->insert($logs);
               }
                //$provider_name = 'ALL';
           }else{
               $where1 = $db1->quoteInto('id = ?', $provider_id);
               // $oldprovider[0]=$db_provider->geta
               //   $where = $db->quoteInto('id = ?', $provider_id);
               $oldprovider=$db_provider1->fetchAll($where1)->toArray();
               $provider_name=$oldprovider[0]['provider_name'];
                $logs['provider']=$provider_name;
                $db_datalogs->insert($logs);
           }
          
           return 1;
    }
    else{
            return 0  ;
    }
    
    
    
}
//add '-' to phone number
function phone_format($phone) {
    return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
}

function myfilter($mystring) {
    return str_replace(array("-", "(", ")"), array("", "", ""), $mystring);
}
function getclaimList($claim){
    $patientList = array();
    $count = 0;
    foreach($claim as $row){
            $patientList[$count]['color_code'] = $row['color_code'];
            $patientList[$count]['alert'] = $row['alert'];
            $patientList[$count]['cpt_code'] = $row['CPT_code_1'];
            $patientList[$count]['anes_code'] = $row['anes_code'];
            $patientList[$count]['provider_name'] = $row['provider_name'];
            
            /********Using short_name for the facility and provider******/
            $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
            $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
            $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit'];
            /********Using short_name for the facility and provider******/
             
             /******************Add Fields*********************/
            $patientList[$count]['total_charge'] = $row['total_charge'];
            $patientList[$count]['amount_paid'] = $row['amount_paid'];
            $patientList[$count]['percentage'] = $row['percentage'];
            $patientList[$count]['due'] = $row['balance_due'];
            if($row[ 'referringprovider_last_name'] !== null && $row[ 'referringprovider_last_name'] !== '')
                $patientList[$count][ 'referringprovider_name'] = $row[ 'referringprovider_last_name'] .', '. $row['referringprovider_first_name'];
             else
                $patientList[$count][ 'referringprovider_name'] = ''; 
             /******************Add Fields*********************/
            
            $patientList[$count]['claim_status_display'] = $row['claim_status_display'];
//            $patientList[$count]['bill_status'] = $row['bill_status'];
//            $patientList[$count]['statement_status'] = $row['statement_status'];
            $patientList[$count]['bill_status_display'] = $row['bill_status_display'];
            $patientList[$count]['statement_status_display'] = $row['statement_status_display'];
            $patientList[$count]['account_number'] = $row['account_number'];

            $patientList[$count]['renderingprovider_last_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
            $patientList[$count]['encounter_id'] = $row['encounter_id'];
            $patientList[$count]['patient_id'] = $row['patient_id'];
            $patientList[$count]['name'] = $row['patient_last_name'] . ', ' . $row['patient_first_name'];
            $patientList[$count]['insurance_name'] = $row['insurance_name'];
            $patientList[$count]['insurance_display'] = $row['insurance_display'];
            $patientList[$count]['insurance_s_name'] = $row['insurance_s_name'];
            $patientList[$count]['insurance_s_display'] = $row['insurance_s_display'];
            
            $patientList[$count]['facility_name'] = $row['facility_name'];
            $patientList[$count]['DOB'] = format($row['patient_DOB'], 1);
            $patientList[$count]['last'] = $row['last'];
            $count = $count +1;
    }
    return $patientList;
}
function getpatientList($patient) {
    $patientList = array();    
    /***************************Add inquiry_last_name*****************************/
    $inquiry_last_name = $patient[0]['inquiry_last_name'];
    /***************************Add inquiry_last_name*****************************/    
    $count = 0;
    $patient_id = null;
    $patient_last_name = null;
    $patient_first_name = null;
    $insurance_name = null;
    $insurance_s_name = null;
    $insurance_display=null;
    $insurance_s_diaplay = null;
    $patient_DOB = null;
    $account_number = null;
    $percentage = null;
    $last = null;
    $due = null;
    $anes_code = null;
    $cpt_code =null;
    $color_change = false;
    foreach ($patient as $row) {
        //$encounter_id = $row['encounter_id'];        
        if ($count != 0 && $row['patient_id'] == $patient_id) {            
            /*-------------------------------------------------------------------------*/
            $patientList[$count]['color_code'] = $row['color_code'];
            $patientList[$count]['alert'] = $row['alert'];
            $patientList[$count]['cpt_code'] = $row['CPT_code_1'];
            $patientList[$count]['anes_code'] = $row['anes_code'];
            $patientList[$count]['provider_name'] = $row['provider_name'];            
            /********Using short_name for the facility and provider******/
            $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
            $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
            $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit'];
            /********Using short_name for the facility and provider******/             
             /******************Add Fields*********************/
            $patientList[$count]['total_charge'] = $row['total_charge'];
            $patientList[$count]['amount_paid'] = $row['amount_paid'];
            $patientList[$count]['percentage'] = $row['percentage'];
            $patientList[$count]['due'] = $row['balance_due'];
            if($row[ 'referringprovider_last_name'] !== null && $row[ 'referringprovider_last_name'] !== '')
                $patientList[$count][ 'referringprovider_name'] = $row[ 'referringprovider_last_name'] .', '. $row['referringprovider_first_name'];
            else
                $patientList[$count][ 'referringprovider_name'] = '';
             /******************Add Fields*********************/            
            $patientList[$count]['claim_status'] = $row['claim_status'];
            $patientList[$count]['bill_status'] = $row['bill_status'];
            $patientList[$count]['statement_status'] = $row['statement_status'];
            $patientList[$count]['account_number'] = $row['account_number'];
            $patientList[$count]['renderingprovider_last_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
            $patientList[$count]['encounter_id'] = $row['encounter_id'];
            $patientList[$count]['patient_id'] = $row['patient_id'];
            $patientList[$count]['name'] = $row['patient_last_name'] . ', ' . $row['patient_first_name'];
            $patientList[$count]['insurance_name'] = $row['insurance_name'];
            $patientList[$count]['insurance_display'] = $row['insurance_display'];
            $patientList[$count]['insurance_s_name'] = $row['insurance_s_name'];
            $patientList[$count]['insurance_s_display'] = $row['insurance_s_display'];            
            $patientList[$count]['facility_name'] = $row['facility_name'];
            $patientList[$count]['DOB'] = format($row['patient_DOB'], 1);
            $patientList[$count]['operation'] = 'View';            
            //add display_flag
            $patientList[$count]['count_flag'] = $row['count_flag'];
            $patientList[$count]['display_flag'] = 1;
            $patientList[$count]['color_flag'] = 0;
            $patientList[$count]['last'] = $row['last'];
            if($patientList[$count]['display_flag'] == 1)
            {
                if($color_change == false)
                {
                    $patientList[$count]['color_flag'] = 0;
                    $color_change = true;
                }
                else
                {
                     $patientList[$count]['color_flag'] = 1;
                     $color_change = false;
                }            
            }
            //add display_flag
            $patient_id = $row['patient_id'];
            $patient_last_name = $row['patient_last_name'];
            $patient_first_name = $row['patient_first_name'];
            //$insurance_display = $row['insurance_display'];
            //$insurance_name=$row['insurance_name'];
            $insurance_name = $patientList[$count]['insurance_name'];
            $insurance_display = $patientList[$count]['insurance_display'];
            $insurance_s_name = $patientList[$count]['insurance_s_name'];
            $insurance_s_display = $patientList[$count]['insurance_s_display'];
            $patient_DOB = format($row['patient_DOB'], 1);
            $account_number = $row['account_number'];
            $last = $row['last'];
            $cpt_code = $row['CPT_code_1'];
            $anes_code = $row['anes_code'];
            $count = $count + 1;
        } else {            
            /*-------------------------------------------------------------------------*/            
            $patientList[$count]['provider_name'] = null;            
            /********Using short_name for the facility and provider******/
            $patientList[$count]['provider_short_name'] = null;
            $patientList[$count]['facility_short_name'] =  null;
            $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit '];
            /********Using short_name for the facility and provider******/           
            /******************Add Fields*********************/
            $patientList[$count]['total_charge'] = null;
            $patientList[$count]['amount_paid'] = null;
            $patientList[$count]['percentage'] = null;
            $patientList[$count]['balance_due'] = null;
            $patientList[$count][ 'referringprovider_name'] = null;
            /******************Add Fields*********************/
            $patientList[$count]['claim_status'] = null;
            $patientList[$count]['bill_status'] = null;
            $patientList[$count]['statement_status'] = null;
            $patientList[$count]['renderingprovider_last_name'] = null;
            $patientList[$count]['start_date_1'] = null;
            $patientList[$count]['encounter_id'] = null;
            $patientList[$count]['cpt_code'] = $cpt_code;
            $patientList[$count]['anes_code'] = $anes_code;
            $patientList[$count]['patient_id'] = $patient_id;
            $patientList[$count]['name'] = $patient_last_name . ', ' . $patient_first_name;
            $patientList[$count]['insurance_name'] = $insurance_name;
            $patientList[$count]['insurance_display'] = $insurance_display;
            $patientList[$count]['insurance_s_name'] = $insurance_s_name;
            $patientList[$count]['insurance_s_display'] = $insurance_s_display;
            $patientList[$count]['account_number'] = $account_number;
            $patientList[$count]['last'] = $last;
            $patientList[$count]['operation'] = 'New Service';
            //add display_flag
            $patientList[$count]['count_flag'] = $row['count_flag'];
            $patientList[$count]['display_flag'] = 0;
            $patientList[$count]['color_flag'] = 0;
            
           if($patientList[$count]['display_flag'] == 1)
            {
                if($color_change == false)
                {
                    $patientList[$count]['color_flag'] = 0;
                    $color_change = true;
                }
                else
                {
                     $patientList[$count]['color_flag'] = 1;
                     $color_change = false;
                }
                
            }
            //add display_flag
            $patientList[$count]['DOB'] = $patient_DOB;
            $count = $count + 1;
             /*-------------------------------------------------------------------------*/
            $patientList[$count]['color_code'] = $row['color_code'];
            $patientList[$count]['alert'] = $row['alert'];
            $patientList[$count]['cpt_code'] = $row['CPT_code_1'];
            $patientList[$count]['anes_code'] = $row['anes_code'];
            $patientList[$count]['provider_name'] = $row['provider_name'];            
            /********Using short_name for the facility and provider******/
            $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
            $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
            $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit '];
            /********Using short_name for the facility and provider******/            
            /******************Add Fields*********************/
            $patientList[$count]['total_charge'] = $row['total_charge'];
            $patientList[$count]['amount_paid'] = $row['amount_paid'];
            $patientList[$count]['percentage'] = $row['percentage'];
            $patientList[$count]['due'] = $row['balance_due'];
            if($row[ 'referringprovider_last_name'] !== null && $row[ 'referringprovider_last_name'] !== '')
                $patientList[$count][ 'referringprovider_name'] = $row[ 'referringprovider_last_name'] .', '. $row['referringprovider_first_name'];
            else
                $patientList[$count][ 'referringprovider_name'] = '';
            /******************Add Fields*********************/      
            $patientList[$count]['claim_status'] = $row['claim_status'];
            $patientList[$count]['bill_status'] = $row['bill_status'];
            $patientList[$count]['statement_status'] = $row['statement_status'];
            $patientList[$count]['renderingprovider_last_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
            $patientList[$count]['encounter_id'] = $row['encounter_id'];
            $patientList[$count]['patient_id'] = $row['patient_id'];
            $patientList[$count]['name'] = $row['patient_last_name'] . ', ' . $row['patient_first_name'];
            $patientList[$count]['insurance_name'] = $row['insurance_name'];
            $patientList[$count]['insurance_display'] = $row['insurance_display'];
            $patientList[$count]['insurance_s_name'] = $row['insurance_s_name'];
            $patientList[$count]['insurance_s_display'] = $row['insurance_s_display'];
            
            $patientList[$count]['facility_name'] = $row['facility_name'];
            $patientList[$count]['account_number'] = $row['account_number'];
            $patientList[$count]['last'] = $row['last'];
            $patientList[$count]['operation'] = 'View';
            //add display_flag
            $patientList[$count]['count_flag'] = $row['count_flag'];
            $patientList[$count]['display_flag'] = 1;
            $patientList[$count]['color_flag'] = 0;
            
            if($patientList[$count]['display_flag'] == 1)
            {
                if($color_change == false)
                {
                    $patientList[$count]['color_flag'] = 0;
                    $color_change = true;
                }
                else
                {
                     $patientList[$count]['color_flag'] = 1;
                     $color_change = false;
                }                
            }
            //add display_flag
            $patientList[$count]['DOB'] = format($row['patient_DOB'], 1);
            $patient_id = $row['patient_id'];
            $patient_last_name = $row['patient_last_name'];
            $patient_first_name = $row['patient_first_name'];
            //$insurance_name = $row['insurance_name'];
            //$insurance_display = $row['insurance_display'];
            $insurance_name = $patientList[$count]['insurance_name'];
            $insurance_display = $patientList[$count]['insurance_display'];
            $insurance_s_name = $patientList[$count]['insurance_s_name'];
            $insurance_s_display = $patientList[$count]['insurance_s_display'];
            $cpt_code = $row['CPT_code_1'];
            $anes_code = $row['anes_code'];
            $account_number = $row['account_number'];
            $last = $row['last'];
            $patient_DOB = format($row['patient_DOB'], 1);
            $count = $count + 1;            
            /*-------------------------------------------------------------------------*/
        }
    }    
    /*-------------------------------------------------------------------------*/    
    $patientList[$count]['provider_name'] = null; 
    /********Using short_name for the facility and provider******/
    $patientList[$count]['provider_short_name'] = null;
    $patientList[$count]['facility_short_name'] = null;
    $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit '];   
    /********Using short_name for the facility and provider******/
    /******************Add Fields*********************/
    $patientList[$count]['total_charge'] = null;
    $patientList[$count]['amount_paid'] = null;
    $patientList[$count][ 'referringprovider_name'] = null;
    /******************Add Fields*********************/  
    $patientList[$count]['claim_status'] = null;
    $patientList[$count]['renderingprovider_last_name'] = null;
    $patientList[$count]['start_date_1'] = null;
    $patientList[$count]['operation'] = 'New Service';
    //add display_flag
    $patientList[$count]['count_flag'] = $row['count_flag'];
    $patientList[$count]['display_flag'] = 0;
    $patientList[$count]['color_flag'] = 0;
    
    if($patientList[$count]['display_flag'] == 1)
    {
        if($color_change == false)
                {
                    $patientList[$count]['color_flag'] = 0;
                    $color_change = true;
                }
                else
                {
                     $patientList[$count]['color_flag'] = 1;
                     $color_change = false;
                }
    }
    //add display_flag
    $patientList[$count]['encounter_id'] = null;
    $patientList[$count]['cpt_code'] = $cpt_code;
    $patientList[$count]['anes_code'] = $anes_code;
    $patientList[$count]['patient_id'] = $patient_id;
    $patientList[$count]['name'] = $patient_last_name . ', ' . $patient_first_name;
    $patientList[$count]['insurance_name'] = $insurance_name;
    $patientList[$count]['insurance_display'] = $insurance_display;
    $patientList[$count]['insurance_s_name'] = $insurance_s_name;
    $patientList[$count]['insurance_s_display'] = $insurance_s_display;
    $patientList[$count]['DOB'] = $patient_DOB;
    $patientList[$count]['account_number'] = $account_number;
    $count = $count + 1;    
     /*-------------------------------------------------------------------------*/
    $patientList[$count]['provider_name'] = null;
    /********Using short_name for the facility and provider******/
    $patientList[$count]['provider_short_name'] = null;
    $patientList[$count]['facility_short_name'] = null;
    $patientList[$count]['limit_number'] = $row['claim_inquiry_results_limit'];
    /********Using short_name for the facility and provider******/    
    /******************Add Fields*********************/
    $patientList[$count]['total_charge'] = null;
    $patientList[$count]['amount_paid'] = null;
    $patientList[$count][ 'referringprovider_name'] = null;
    /******************Add Fields*********************/    
    $patientList[$count]['claim_status'] = null;
    $patientList[$count]['renderingprovider_last_name'] = null;
    $patientList[$count]['start_date_1'] = null;
    $patientList[$count]['operation'] = 'New Patient';
    //add display_flag
    $patientList[$count]['count_flag'] = $row['count_flag'];
    $patientList[$count]['display_flag'] = 2;
    $patientList[$count]['color_flag'] = 0;
    //add display_flag
    $patientList[$count]['encounter_id'] = null;
    $patientList[$count]['patient_id'] = null;
    /***************************Add inquiry_last_name*****************************/
    //$patientList[$count]['name'] = null;
    if($inquiry_last_name != null)
        $patientList[$count]['name'] = $inquiry_last_name;
    if($inquiry_last_name == null)
        $patientList[$count]['name'] = "New Patient";
    /***************************Add inquiry_last_name*****************************/
    $patientList[$count]['insurance_name'] = null;
    $patientList[$count]['insurance_display'] = null;
    $patientList[$count]['insurance_s_name'] = null;
    $patientList[$count]['insurance_s_display'] = null;
    $patientList[$count]['cpt_code'] = null;
    $patientList[$count]['anes_code'] = null;
    $patientList[$count]['DOB'] = null;
    $patientList[$count]['account_number'] = null;
     /*-------------------------------------------------------------------------*/
    return $patientList;
}

function ledger($id, $number_of_days_include_in_ledger, $from) {
    $db = Zend_Registry::get('dbAdapter');
    $select = $db->select();

    $select->from('billingcompany', array('renderingprovider.last_name as r_last_name', 'encounter.id as encounter_id'));
    $select->join('provider', 'billingcompany.id=provider.billingcompany_id ');
    $select->join('options', 'options.id=provider.options_id');
    $select->join('encounter', 'provider.id = encounter.provider_id'); 
    //$select->join('mypatient', 'provider.id = mypatient.provider_id');
    $select->join('patient', 'encounter.patient_id = patient.id');
    //$select->join('encounter', 'patient.id = encounter.patient_id');
    $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
    $select->join('insured', 'insured.id = encounterinsured.insured_id');
    $select->join('insurance', 'insurance.id = insured.insurance_id');
   
    // $select->join('encounter', 'patient.id = encounter.patient_id', 'encounter.id as encounter_id');
    //$select->join('statement', 'encounter.id = statement.encounter_id', array('encounter.id as encounter_id', 'next_statement', 'statement.id as statement_id', 'statement.date as statement_date', 'statement_type', 'trigger', 'remark'));
    $select->join('facility', 'facility.id = encounter.facility_id', array('facility_name', 'facility.city as facility_city', 'facility.state as facility_state'));
    $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id');
    $select->join('claim', 'encounter.claim_id = claim.id');
    $select->join('followups', 'claim.id = followups.claim_id');
    //$select->where('encounter.patient_id=?', '79');
    $select->where('encounter.patient_id=?', $id);
    if ($number_of_days_include_in_ledger == 0)
        if (strcmp($from, "statement") === 0)
            $select->where('claim.claim_status LIKE ?', 'open%');

    if ($number_of_days_include_in_ledger > 0) {
        if (strcmp($from, "statement") === 0) {
            $startdate = date("Y-m-d", strtotime("-" . $number_of_days_include_in_ledger . " day"));
            $today = date("Y-m-d");
//        $select->where('claim.claim_status LIKE ?', 'open%');
//        $info = 'claim.claim_status LIKE "close%" AND' . ' claim.date_closed>=' . $startdate . ' AND claim.date_closed<=' . $today;
//        $select->orWhere($info);
        //$info = '(claim.claim_status LIKE "open%") OR (claim.claim_status LIKE "close%" AND' . ' claim.date_closed>=' . $startdate . ' AND claim.date_closed<=' . $today . ')';
        //$select->where($info);
            $select->where('(claim.claim_status LIKE "open%") OR (claim.claim_status LIKE "close%" AND claim.date_closed>="' . $startdate . '" AND claim.date_closed<="' . $today . '")');
        // $select->where('claim.claim_status LIKE "close%"');
//$select->where('claim.claim_status LIKE "open%" OR claim.claim_status LIKE "closed%"');
        //$select->orWhere('claim.claim_status LIKE ?', 'closed%');
        //$select->orwhere('claim.claim_status LIKE ?', 'close%');
        //$select->where('claim.date_closed>=?', $startdate);
        //$select->where('claim.date_closed<=?', $today);
        }
    }

    $claim = $db->fetchAll($select);
    $claim_test = array();
$claim_test[0]=$claim[0];
    for($i=0;$i<count($claim);$i++){
        $id_1 = $claim[$i]['encounter_id'];
        $count1= 0;
        for($j=0;$j<count($claim_test);$j++){
            $id_2 = $claim_test[$j]['encounter_id'];
            if($id_1!=$id_2){
                $count1++;
            }else{
                break;
            }
        }
        if($count1==count($claim_test)){
            $claim_test[count($claim_test)]=$claim[$i];
        }
    }
    
    
    
    $ledgerList = array();
    $index = 0;
    $balance = 0;
    //0. Statement is generated to patient
//    if ($claim[0]['statement_date'] != null) {
//        $ledgerList[$index]['date'] = $claim[0]['date_statement_I'];
//        $ledgerList[$index]['description'] = 'Patient billed statement I';
//        $ledgerList[$index]['score'] = 0;
//        $index = $index + 1;
//    }
//    if ($claim[0]['date_statement_II'] != null) {
//        $ledgerList[$index]['date'] =$claim[0]['date_statement_II'];
//        $ledgerList[$index]['description'] = 'Patient billed statement II';
//        $ledgerList[$index]['score'] = 1;
//        $index = $index + 1;
//    }
//    if ($claim[0]['date_statement_III'] != null) {
//        $ledgerList[$index]['date'] = $claim[0]['date_statement_III'];
//        $ledgerList[$index]['description'] = 'Patient billed statement III';
//        $ledgerList[$index]['score'] = 2;
//        $index = $index + 1;
//    }
     $counter = 16;
    $statement_type = array('1' => 'Statement I', '2' => 'Statement II', '3' => 'Statement III', '4' => 'Installment');
    foreach ($claim_test as $row) {
        //define varible description
        $description = ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
        //0. Statement is generated to patient
        $db_statement = new Application_Model_DbTable_Statement();
        $db = $db_statement->getAdapter();
        $where = $db->quoteInto('encounter_id = ?', $row['encounter_id']);
        $statement = $db_statement->fetchAll($where);
        if ($statement->count() > 0) {
            foreach ($statement as $statementrow) {
                if ($statementrow['date'] != null) {
                    $ledgerList[$index]['date'] = $statementrow['date'];
                    $ledgerList[$index]['description'] = 'Patient billed ' . $statement_type[$statementrow['statement_type']];
                    //$ledgerList[$index]['amount'] = $row['amount_biller_adjustment_3'];
//            $ledgerList[$index]['notes'] = $row['notes_biller_adjustment_3'];
                    $ledgerList[$index]['score'] = 0;
                    $index = $index + 1;
                }
            }
        }
        //1. claim is closed
        if ($row['date_closed'] != null && $row['date_closed'] !== "0000-00-00") {
            $ledgerList[$index]['date'] = $row['date_closed'];
            $ledgerList[$index]['description'] = 'Claim Closed ' . $description;
            $ledgerList[$index]['amount'] = $row['amount_biller_adjustment_3'];
            $ledgerList[$index]['notes'] = $row['notes_biller_adjustment_3'];
            $ledgerList[$index]['score'] = 3;
            $index = $index + 1;
        }
        //2. Biller adjustment is made
        $claim_id = $row['claim_id'];
        $description = ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
       

        $db_insuracnepayments = new Application_Model_DbTable_Insurancepayments();
        $db = $db_insuracnepayments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $insurancepayments = $db_insuracnepayments->fetchAll($where);
        if ($insurancepayments->count() > 0) {
            foreach ($insurancepayments as $insurancepaymentsrow) {
                $ledgerList[$index]['date'] = $insurancepaymentsrow['date'];
                $ledgerList[$index]['description'] = 'Insurance payment received ' . $row['insurance_display'] . $description;
                $ledgerList[$index]['amount'] = -$insurancepaymentsrow['amount'];
                $ledgerList[$index]['notes'] = $insurancepaymentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }

        $db_patientpayments = new Application_Model_DbTable_Patientpayments();
        $db = $db_patientpayments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $patientpayments = $db_patientpayments->fetchAll($where);
        if ($patientpayments->count() > 0) {
            foreach ($patientpayments as $patientpaymentsrow) {
                $ledgerList[$index]['date'] = $patientpaymentsrow['date'];
                $ledgerList[$index]['description'] = 'Patient payment received' . $description;
                $ledgerList[$index]['amount'] = -$patientpaymentsrow['amount'];
                $ledgerList[$index]['notes'] = $patientpaymentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }

        $db_billeradjustments = new Application_Model_DbTable_Billeradjustments();
        $db = $db_billeradjustments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $billeradjustments = $db_billeradjustments->fetchAll($where);
        if ($billeradjustments->count() > 0) {
            foreach ($billeradjustments as $billeradjustmentsrow) {
                $ledgerList[$index]['date'] = $billeradjustmentsrow['date'];
                $ledgerList[$index]['description'] = 'Biller adjustment' . $description;
                $ledgerList[$index]['amount'] = $billeradjustmentsrow['amount'];
                $ledgerList[$index]['notes'] = $billeradjustmentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }







        //4. Insurance payment is sent to patient
        if ($row['is_the_issued_payment_sent_to_patient'] == '1' && $row['date_insurance_payment_issued'] != null) {
            $ledgerList[$index]['date'] = $row['date_insurance_payment_issued'];
            $ledgerList[$index]['description'] = 'Insurance payment sent to patient: $' . $row['amount_insurance_payment_issued'] . $description;
            $ledgerList[$index]['notes'] = $row['notes_insurance_payment'];
            $ledgerList[$index]['score'] = 10;
            $index = $index + 1;
        }
        //5. Insurance payment is received
        //6. Bill is generated to insurance
        if ($row['date_billed'] != null) {
            if (strcmp($from, "statement") !== 0) {
                $ledgerList[$index]['date'] = $row['date_billed'];
                $ledgerList[$index]['description'] = 'Insurance billed ' . $row['insurance_display'] . ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
                $ledgerList[$index]['score'] = 14;
                $index = $index + 1;
            }
        }
//7. new claim is entered
        $ledgerList[$index]['date'] = $row['start_date_1'];
        $ledgerList[$index]['description'] = 'Anesthesia Procedure ' . $row['secondary_CPT_code_1'] . ' by Dr. ' . $row['r_last_name'] . ' at ' . $row['facility_name'] . ', ' . $row['facility_city'] . ', ' . $row['facility_state'];
        $ledgerList[$index]['amount'] = $row['total_charge'];
        $ledgerList[$index]['score'] = 15;
        $index = $index + 1;
    }



//    function my_compare($a, $b) {
//        if ((strtotime($a['date']) - strtotime($b['date'])) < 0)
//            return -1;
//        else if ($a['date'] == $b['date'])
//            return 0;
//        else
//            return 1;
//    }
//
//    function my_compare2($a, $b) {
//        if ($a['score'] > $b['score'])
//            return -1;
//        else if ($a['score'] == $b['score'])
//            return 0;
//        else
//            return 1;
//    }

    foreach ($ledgerList as $key => $row) {
        $date[$key] = format($row['date'], 1);
        $score[$key] = $row['score'];
    }
    array_multisort($date, SORT_ASC, $score, SORT_DESC, $ledgerList);
    //uasort($ledgerList, 'my_compare', 'my_compare2');
    //uasort($ledgerList, );
    $ledger = array();
    $index = 0;
    foreach ($ledgerList as $row) {
        if ($row['amount'] != null) {
            $balance = $balance + $row['amount'];
            $ledger[$index]['amount'] = number_format($row['amount'], 2);
            $ledger[$index]['balance'] = number_format($balance, 2);
        }
        $ledger[$index]['date'] = format($row['date'], 1);
        $ledger[$index]['notes'] = $row['notes'];
        $ledger[$index]['description'] = $row['description'];
        $index = $index + 1;
    }
    $ledger[$index]['balance']=  number_format($balance,2);
    return $ledger;
}

function ledger2($id, $number_of_days_include_in_ledger, $from , $cur_payments , $cur_claim_id, $cur_provider_id) {
    $db = Zend_Registry::get('dbAdapter');
    $select = $db->select();

    $select->from('billingcompany', array('renderingprovider.last_name as r_last_name', 'renderingprovider.first_name as r_first_name', 'encounter.id as encounter_id'));
    $select->join('provider', 'billingcompany.id=provider.billingcompany_id ');
    $select->join('options', 'options.id=provider.options_id');
    $select->join('encounter', 'provider.id = encounter.provider_id'); 
    //$select->join('mypatient', 'provider.id = mypatient.provider_id');
    $select->join('patient', 'encounter.patient_id = patient.id');
    //$select->join('encounter', 'patient.id = encounter.patient_id');
    $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
    $select->join('insured', 'insured.id = encounterinsured.insured_id');
    $select->join('insurance', 'insurance.id = insured.insurance_id');
   
    // $select->join('encounter', 'patient.id = encounter.patient_id', 'encounter.id as encounter_id');
    //$select->join('statement', 'encounter.id = statement.encounter_id', array('encounter.id as encounter_id', 'next_statement', 'statement.id as statement_id', 'statement.date as statement_date', 'statement_type', 'trigger', 'remark'));
    $select->join('facility', 'facility.id = encounter.facility_id', array('facility_name', 'facility.city as facility_city', 'facility.state as facility_state'));
    $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.salutation as r_salutation'));
    $select->join('claim', 'encounter.claim_id = claim.id');
    $select->join('followups', 'claim.id = followups.claim_id');
    //$select->where('encounter.patient_id=?', '79');
    //ZW Case 35
    /*
    $select->where('encounter.patient_id=?', $id);
    if ($number_of_days_include_in_ledger == 0)
        if (strcmp($from, "statement") === 0)
            $select->where('claim.claim_status LIKE ?', 'open%');

    if ($number_of_days_include_in_ledger > 0) {
        if (strcmp($from, "statement") === 0) {
            $startdate = date("Y-m-d", strtotime("-" . $number_of_days_include_in_ledger . " day"));
            $today = date("Y-m-d");
            $select->where('(claim.claim_status LIKE "open%") OR (claim.claim_status LIKE "close%" AND claim.date_closed>="' . $startdate . '" AND claim.date_closed<="' . $today . '")');
        }
    }
    */
    
    //ZW New logic for including other DOS
    if (strcmp($from, "patient") === 0) {
        $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\''));       
    } else { // For statement
        $opt_db = Zend_Registry::get('dbAdapter');
        $opt_select = $opt_db->select();
        $opt_select->from('options', array('options.tags as opt_tags'));        
        $opt_select->join('provider', 'options.id=provider.options_id');
        $opt_select->where($opt_db->quoteInto('provider.id = ?', $cur_provider_id));
        $options_data = $opt_db->fetchAll($opt_select);
        $dos_option = parse_tag($options_data[0]['opt_tags']);
        
        switch ($dos_option['stmtclaiminclude']) {
            case '1':
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('encounter.claim_id=?', $cur_claim_id));
		break;
            case '2':
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('encounter.claim_id=?', $cur_claim_id));
                $select->orWhere($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND (' . $db->quoteInto('claim.eob_co_insurance is not null OR claim.eob_deductable is not null') . ')');
    
		break;
            case '3':
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('encounter.claim_id=?', $cur_claim_id));
                $select->orWhere($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('claim.statement_status  = \'stmt_ready_selfpay\''));                
		break;    
            case '4':
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\''));
		break;
            case '2,3':
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('encounter.claim_id=?', $cur_claim_id));
                $select->orWhere($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND (' . $db->quoteInto('claim.eob_co_insurance is not null OR claim.eob_deductable is not null') . ')');
                $select->orWhere($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('claim.statement_status  = \'stmt_ready_selfpay\''));
		break;
            default:
                $select->where($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'open%\'') . ' AND ' . $db->quoteInto('encounter.claim_id=?', $cur_claim_id));
		break;		
        }
        if ($number_of_days_include_in_ledger > 0) {
            $startdate = date("Y-m-d", strtotime("-" . $number_of_days_include_in_ledger . " day"));
            $today = date("Y-m-d");                      
            $select->orWhere($db->quoteInto('encounter.patient_id=?', $id) . ' AND ' . $db->quoteInto('claim.claim_status LIKE \'close%\'') . ' AND ' . $db->quoteInto('claim.date_closed>=?', $startdate) . ' AND ' . $db->quoteInto('claim.date_closed<=?', $today));                    
        }
    }
    $claim = $db->fetchAll($select);
    $claim_test = array();
    $claim_test[0]=$claim[0];
    for($i=0;$i<count($claim);$i++){
        $id_1 = $claim[$i]['encounter_id'];
        $count1= 0;
        for($j=0;$j<count($claim_test);$j++){
            $id_2 = $claim_test[$j]['encounter_id'];
            if($id_1!=$id_2){
                $count1++;
            }else{
                break;
            }
        }
        if($count1==count($claim_test)){
            $claim_test[count($claim_test)]=$claim[$i];
        }
    }
    
    if (strcmp($from, "patient") !== 0) {
        $cur_claim_id = null;
    }
    
    $ledgerList = array();
    $index = 0;
    $balance = 0;
    //0. Statement is generated to patient
//    if ($claim[0]['statement_date'] != null) {
//        $ledgerList[$index]['date'] = $claim[0]['date_statement_I'];
//        $ledgerList[$index]['description'] = 'Patient billed statement I';
//        $ledgerList[$index]['score'] = 0;
//        $index = $index + 1;
//    }
//    if ($claim[0]['date_statement_II'] != null) {
//        $ledgerList[$index]['date'] =$claim[0]['date_statement_II'];
//        $ledgerList[$index]['description'] = 'Patient billed statement II';
//        $ledgerList[$index]['score'] = 1;
//        $index = $index + 1;
//    }
//    if ($claim[0]['date_statement_III'] != null) {
//        $ledgerList[$index]['date'] = $claim[0]['date_statement_III'];
//        $ledgerList[$index]['description'] = 'Patient billed statement III';
//        $ledgerList[$index]['score'] = 2;
//        $index = $index + 1;
//    }
     $counter = 16;
    $statement_type = array('1' => 'Statement I', '2' => 'Statement II', '3' => 'Statement III', '4' => 'Installment');
    foreach ($claim_test as $row) {
        //define varible description
        $description = ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
        //0. Statement is generated to patient
        $db_statement = new Application_Model_DbTable_Statement();
        $db = $db_statement->getAdapter();
        $where = $db->quoteInto('encounter_id = ?', $row['encounter_id']);
        $statement = $db_statement->fetchAll($where);
        if ($statement->count() > 0) {
            foreach ($statement as $statementrow) {
                if ($statementrow['date'] != null) {
                    $ledgerList[$index]['date'] = $statementrow['date'];
                    $ledgerList[$index]['description'] = 'Patient billed ' . $statement_type[$statementrow['statement_type']];
                    //$ledgerList[$index]['amount'] = $row['amount_biller_adjustment_3'];
//            $ledgerList[$index]['notes'] = $row['notes_biller_adjustment_3'];
                    $ledgerList[$index]['score'] = 0;
                    $index = $index + 1;
                }
            }
        }
        //1. claim is closed
        if ($row['date_closed'] != null && $row['date_closed'] !== "0000-00-00") {
            $ledgerList[$index]['date'] = $row['date_closed'];
            $ledgerList[$index]['description'] = 'Claim Closed ' . $description;
            $ledgerList[$index]['amount'] = $row['amount_biller_adjustment_3'];
            $ledgerList[$index]['notes'] = $row['notes_biller_adjustment_3'];
            $ledgerList[$index]['score'] = 3;
            $index = $index + 1;
        }
        //2. Biller adjustment is made
        $claim_id = $row['claim_id'];
        $description = ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
        if($claim_id != $cur_claim_id){
            $db_payments = new Application_Model_DbTable_Payments();
            $db=$db_payments->getAdapter();
            $where = $db->quoteInto("claim_id = ?", $claim_id).$db->quoteInto(" AND serviceid = 0");
            $payments_tmp = $db_payments->fetchAll($where);
            if($payments_tmp->count()>0){
                foreach ($payments_tmp as $paymentsrow) {
                    //$ledgerList[$index]['date'] = format($paymentsrow['datetime'],0);
                    $ledgerList[$index]['date'] = $paymentsrow['datetime'];
                    if($paymentsrow['from'] == "Biller Adjustment"){
                        $ledgerList[$index]['description'] = 'Biller Adjustment';
                        $ledgerList[$index]['amount'] = - abs($paymentsrow['amount']);
                    }else{                 
                        if ($paymentsrow['amount'] > 0){
                            $ledgerList[$index]['description'] = 'Payment received from ' . $paymentsrow['from']  . $description;
                            $ledgerList[$index]['amount'] = - abs($paymentsrow['amount']);
                        }else{
                            $ledgerList[$index]['description'] = 'Payment sent to ' . $paymentsrow['from']  . $description;
                            $ledgerList[$index]['amount'] = abs($paymentsrow['amount']);
                        }
                    }
                    $ledgerList[$index]['notes'] = $paymentsrow['notes'];
                    $ledgerList[$index]['score'] = $counter;
                    $counter++;
                    $index = $index + 1;
                }
            }
        }
        if($claim_id == $cur_claim_id){
            foreach($cur_payments as $paymentsrow){
                //$ledgerList[$index]['date'] = format($paymentsrow['datetime'],0);
                $ledgerList[$index]['date'] = $paymentsrow['datetime'];
                if($paymentsrow['from'] == "Biller Adjustment"){
                        $ledgerList[$index]['description'] = 'Biller Adjustment';
                        $ledgerList[$index]['amount'] = - abs($paymentsrow['amount']);
                }else{
                        if ($paymentsrow['amount'] > 0){
                            $ledgerList[$index]['description'] = 'Payment received from ' . $paymentsrow['from']  . $description;
                            $ledgerList[$index]['amount'] = - abs($paymentsrow['amount']);
                        }else{
                            $ledgerList[$index]['description'] = 'Payment sent to ' . $paymentsrow['from']  . $description;
                            $ledgerList[$index]['amount'] = abs($paymentsrow['amount']);
                        }
                }
                $ledgerList[$index]['notes'] = $paymentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }
        /*$db_insuracnepayments = new Application_Model_DbTable_Insurancepayments();
        $db = $db_insuracnepayments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $insurancepayments = $db_insuracnepayments->fetchAll($where);
        if ($insurancepayments->count() > 0) {
            foreach ($insurancepayments as $insurancepaymentsrow) {
                $ledgerList[$index]['date'] = $insurancepaymentsrow['date'];
                $ledgerList[$index]['description'] = 'Insurance payment received ' . $row['insurance_display'] . $description;
                $ledgerList[$index]['amount'] = -$insurancepaymentsrow['amount'];
                $ledgerList[$index]['notes'] = $insurancepaymentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }

        $db_patientpayments = new Application_Model_DbTable_Patientpayments();
        $db = $db_patientpayments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $patientpayments = $db_patientpayments->fetchAll($where);
        if ($patientpayments->count() > 0) {
            foreach ($patientpayments as $patientpaymentsrow) {
                $ledgerList[$index]['date'] = $patientpaymentsrow['date'];
                $ledgerList[$index]['description'] = 'Patient payment received' . $description;
                $ledgerList[$index]['amount'] = -$patientpaymentsrow['amount'];
                $ledgerList[$index]['notes'] = $patientpaymentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }

        $db_billeradjustments = new Application_Model_DbTable_Billeradjustments();
        $db = $db_billeradjustments->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $billeradjustments = $db_billeradjustments->fetchAll($where);
        if ($billeradjustments->count() > 0) {
            foreach ($billeradjustments as $billeradjustmentsrow) {
                $ledgerList[$index]['date'] = $billeradjustmentsrow['date'];
                $ledgerList[$index]['description'] = 'Biller adjustment' . $description;
                $ledgerList[$index]['amount'] = $billeradjustmentsrow['amount'];
                $ledgerList[$index]['notes'] = $billeradjustmentsrow['notes'];
                $ledgerList[$index]['score'] = $counter;
                $counter++;
                $index = $index + 1;
            }
        }*/







        //4. Insurance payment is sent to patient
        if ($row['is_the_issued_payment_sent_to_patient'] == '1' && $row['date_insurance_payment_issued'] != null) {
            $ledgerList[$index]['date'] = $row['date_insurance_payment_issued'];
            $ledgerList[$index]['description'] = 'Insurance payment sent to patient: $' . $row['amount_insurance_payment_issued'] . $description;
            $ledgerList[$index]['notes'] = $row['notes_insurance_payment'];
            $ledgerList[$index]['score'] = 10;
            $index = $index + 1;
        }
        //5. Insurance payment is received
        //6. Bill is generated to insurance
        if ($row['date_billed'] != null) {
            if (strcmp($from, "statement") !== 0) {
                $ledgerList[$index]['date'] = $row['date_billed'];
                $ledgerList[$index]['description'] = 'Insurance billed ' . $row['insurance_display'] . ' CPT: ' . $row['secondary_CPT_code_1'] . ' DOS:' . format($row['start_date_1'], 1);
                $ledgerList[$index]['score'] = 14;
                $index = $index + 1;
            }
        }
//7. new claim is entered
        $ledgerList[$index]['date'] = $row['start_date_1'];
        //$ledgerList[$index]['description'] = 'Anesthesia Procedure ' . $row['secondary_CPT_code_1'] . ' by Dr. ' . $row['r_last_name'] . ' at ' . $row['facility_name'] . ', ' . $row['facility_city'] . ', ' . $row['facility_state'];
        $ledgerList[$index]['description'] = 'Anesthesia Procedure ' . $row['secondary_CPT_code_1'] . ' by ' . $row['r_first_name'] . ' ' . $row['r_last_name'] . ', ' . $row['r_salutation'] . ' at ' . $row['facility_name'] . ', ' . $row['facility_city'] . ', ' . $row['facility_state'];
        $ledgerList[$index]['amount'] = $row['total_charge'];
        $ledgerList[$index]['score'] = 15;
        $index = $index + 1;
    }



//    function my_compare($a, $b) {
//        if ((strtotime($a['date']) - strtotime($b['date'])) < 0)
//            return -1;
//        else if ($a['date'] == $b['date'])
//            return 0;
//        else
//            return 1;
//    }
//
//    function my_compare2($a, $b) {
//        if ($a['score'] > $b['score'])
//            return -1;
//        else if ($a['score'] == $b['score'])
//            return 0;
//        else
//            return 1;
//    }

    foreach ($ledgerList as $key => $row) {
        $date[$key] = strtotime($row['date']);
        $score[$key] = $row['score'];
    }
    array_multisort($date, SORT_ASC, $score, SORT_DESC, $ledgerList);
    //uasort($ledgerList, 'my_compare', 'my_compare2');
    //uasort($ledgerList, );
    $ledger = array();
    $index = 0;
    foreach ($ledgerList as $row) {
        if ($row['amount'] != null) {
            $balance = $balance + $row['amount'];
            $ledger[$index]['amount'] = number_format($row['amount'], 2);
            $ledger[$index]['balance'] = number_format($balance, 2);
        }
        $ledger[$index]['date'] = format($row['date'], 1);
        $ledger[$index]['notes'] = $row['notes'];
        $ledger[$index]['description'] = $row['description'];
        $index = $index + 1;
    }
    $ledger[$index]['balance']=  number_format($balance,2);
    return $ledger;
}

function setdefault($default) {
    session_start();
//    if ($default['provider'] != null)
    $_SESSION['default'] = $default;
//    else
//    {
//        $_SESSION['default']['facility']='';
//        $_SESSION['default']['provider']='';
//        $_SESSION['default']['renderingprovider']='';
//        $_SESSION['default']['referringprovider']='';
//        $_SESSION['default']['place']='';
//    }
}

function getdefault() {
    session_start();
    return $_SESSION['default'];
}

//get mrn
function getmrn($billingcompany_id, $index) {
    $db_billingcompany = new Application_Model_DbTable_Billingcompany();
    $db = $db_billingcompany->getAdapter();
    $where = $db->quoteInto('id = ?', $billingcompany_id);
    $billingcompany = $db_billingcompany->fetchRow($where);
    $auto_mrn = $billingcompany['auto_mrn'];
    $last_mrn = $billingcompany['last_mrn'];
    if ($last_mrn != null) {
        if ($index == 1) {
            $billingcompany_data['last_mrn'] = $last_mrn + 1;
            $db_billingcompany->update($billingcompany_data, $where);
        }
        return substr(strval($last_mrn + 100000001), 1, 8);
    } else {
        if ($auto_mrn != 0) {
            if ($index == 1) {
                $billingcompany_data['last_mrn'] = $auto_mrn;
                $db_billingcompany->update($billingcompany_data, $where);
            }
            return substr(strval($auto_mrn + 100000000), 1, 8);
        }
    }
}

//change all folder name
function change_folder_name($path, $old, $new) {
    if (is_dir($path)) {
        $dp = dir($path);
        while ($file = $dp->read())
            if ($file != '.' && $file != '..')
                change_folder_name($path . '/' . $file, $old, $new);
        $dp->close();
    }
    if (substr_count($path, '/') && (is_dir($path))) {
        $tmp = substr($path, strpos($path, '/') + 2, strpos($path, '-') - strpos($path, '/') - 2);
        if ($tmp == $old) {
            $new_name = substr($path, 0, strpos($path, '/')) . '/' . $new . '-' . substr($path, strpos($path, '-') + 1, strlen($path) - strripos($path, '-'));
            rename($path, $new_name);
        }
    }
}

//get the dirs of one claim
function get_docfile_paths($claim_id )
{
    $user = Zend_Auth::getInstance()->getIdentity();
    $db_user = new Application_Model_DbTable_User();
    $db = $db_user->getAdapter();
    $where = $db->quoteInto('user_name = ?', $user->user_name);
    $user_data = $db_user->fetchRow($where);
    $biller_id = $user_data['reference_id'];

    $db_biller = new Application_Model_DbTable_Biller();
    $db = $db_biller->getAdapter();
    $where = $db->quoteInto('id = ?', $biller_id);
    $biller_data = $db_biller->fetchRow($where);
    $billingcompany_id = $biller_data['billingcompany_id'];
    
    $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
    $sysdoc_path = '../../' . $config->dir->document_root;
    
    $paths = array();
    if($claim_id != 0){
        $db_encounter = new Application_Model_DbTable_Encounter();
        $db = $db_encounter->getAdapter();
        $where = $db->quoteInto('claim_id = ?', $claim_id);
        $encounter = $db_encounter->fetchRow($where);
        $patient_id = $encounter['patient_id'];
        $provider_id = $encounter['provider_id'];
        $claim_doc_path = $sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
        $patient_doc_path = $sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/patient/' . $patient_id;
        $paths['claim_doc_path'] = $claim_doc_path;
        $paths['patient_doc_path'] = $patient_doc_path;
    }
    $billingcompany_doc_path = $sysdoc_path . '/' . $billingcompany_id;
    $paths['billingcompany_doc_path'] = $billingcompany_doc_path;
    return $paths;
}

function parse_tag($tag_string){
    $pairs = explode("|", $tag_string);
    $result = array();
    foreach($pairs as $pair){
        $name_value = explode("=", $pair);
        $result[$name_value[0]] = $name_value[1];
    }
    return $result;
}

function parse_tagRe($tag_string){
    $tag_string= str_replace("|Name","@Name", $tag_string);
    $doublePairs = explode("@", $tag_string);
    $res=array();
    $k=0;
    foreach($doublePairs as $result){
     $res[$k]=parse_tag($result);
     $k++;
    }
     return $res;
}

function movetotop (&$insuranceList) {
    for($i = 0; $i< count($insuranceList); $i++) {
        if($insuranceList[$i]['insurance_name'] == 'Self Pay') {
            $key = $i;
            $temp = array($key => $insuranceList[$key]);
            unset($insuranceList[$key]);                
            break;
        }
    }
    if ($temp != null)
        $insuranceList = array_merge($temp, $insuranceList);
}

function getMailMerge( &$wts, $index, $dataarray ) {
  $loop = true;
  $startfield = false;
  $setval = false;
  $counter = $index;
  $newcount = 0;
  while( $loop )
  {
    if( $wts->item( $counter )->attributes->item(0)->nodeName == 'w:fldCharType' )
    {
      $nodeName = '';
      $nodeValue = '';
      switch( $wts->item( $counter )->attributes->item(0)->nodeValue )
      {
        case 'begin':
          if( $startfield )
          {
            $counter = getMailMerge( $wts, $counter, $dataarray );
          }
          $startfield = true;
          if( $wts->item( $counter )->parentNode->nextSibling )
          {
            $nodeName = $wts->item( $counter )->parentNode->nextSibling->childNodes->item(1)->nodeName;
            $nodeValue = $wts->item( $counter )->parentNode->nextSibling->childNodes->item(1)->nodeValue;
          } else {
            // No sibling
            // check next node
            $nodeName = $wts->item( $counter + 1 )->parentNode->previousSibling->childNodes->item(1)->nodeName;
            $nodeValue = $wts->item( $counter + 1 )->parentNode->previousSibling->childNodes->item(1)->nodeValue;
          }
          if( $nodeValue == 'date \@ "MMMM d, yyyy"' )
          {
            $setval = true;
            $newval = date( "F j, Y" );
          }
          if( substr( $nodeValue, 0, 11 ) == ' MERGEFIELD' )
          {
            $setval = true;
            $newval = $dataarray[str_replace( '"', '', trim( substr( $nodeValue, 12 )))];
          }
          $counter++;
          break;
        case 'separate':
          if( $wts->item( $counter )->parentNode->nextSibling )
          {
            $nodeName = $wts->item( $counter )->parentNode->nextSibling->childNodes->item(1)->nodeName;
            $nodeValue = $wts->item( $counter )->parentNode->nextSibling->childNodes->item(1)->nodeValue;
          } else {
            // No sibling
            // check next node
            $nodeName = $wts->item( $counter + 1 )->parentNode->previousSibling->childNodes->item(1)->nodeName;
            $nodeValue = $wts->item( $counter + 1 )->parentNode->previousSibling->childNodes->item(1)->nodeValue;
          }
          if( $setval )
          {
            $wts->item( $counter )->parentNode->nextSibling->childNodes->item(1)->nodeValue = $newval;
            $setval = false;
            $newval = '';
          }
          $counter++;
          break;
        case 'end':
          if( $startfield )
          {
            $startfield = false;
          }
          $loop = false;
      }
    }
  }
  return $counter;
}

/*
 * author haoqiang
 * before 2015-10-1return false, or return true
 */
function compare_time($time){
    
        $year = 2015;
        $month = 10;
        $day = 1;
        if(!$time){
            return true;
        }
        list($thisyear,$thismonth, $thisday) = explode('-', $time);
        if ($thisyear > $year || ($thisyear == $year && $thismonth > $month) || ($thisyear == $year && $thismonth == $month && $thisday >= $day)) {
                    return true;
        }
        
        return false;
}

/*
 * author haoqiang
 * encodeSSN 
 */
//function encodeSSN($str) {
//    //加密准备，$key,$cipher,$modes,$size,$iv
//    $key = createKey(8); //随机生成一个秘钥，每个人的都不一样
//    $cipher = MCRYPT_DES;
//    $modes = MCRYPT_MODE_ECB;
//    $size = mcrypt_get_iv_size($cipher, $modes);
//    $iv = mcrypt_create_iv($size, MCRYPT_RAND);
//    $str_encrypt = mcrypt_encrypt($cipher, $key, $str, $modes, $iv);
//
//    //需要把$iv也存起来，不然无法在需要的时候解密
//    $key = base64_encode($key);
//    $iv = base64_encode($iv);
//    $str_encrypt = base64_encode($str_encrypt);
//
//    $final_str = base64_encode($key . "|" . $iv . "|" . $str_encrypt); //对最终cipher再进行一次base64
//    return $final_str;
//}

/**
 * 加密
 */
function encodeSSN($str){
    $key = getKey();
    $cipher = MCRYPT_DES;
    $modes = MCRYPT_MODE_ECB;
    $size = mcrypt_get_iv_size($cipher, $modes);
    $iv = mcrypt_create_iv($size, MCRYPT_RAND); //iv 生成需要用到size
    $str_encrypt = mcrypt_encrypt($cipher, $key, $str, $modes, $iv);

    $iv = base64_encode($iv);
    $str_encrypt = base64_encode($str_encrypt);
    //需要把$iv也存起来，不然无法再需要的时候解密
    $final_str = base64_encode($iv . "|" . $str_encrypt);
    
    return $final_str;
}

/*
 * author haoqiang
 * decodeSSN 
 */
//function decodeSSN($str) {
//    //解密准备，密文基本信息
//    $cipher = MCRYPT_DES;
//    $modes = MCRYPT_MODE_ECB;
//    $size = mcrypt_get_iv_size($cipher, $modes);
//    $str = base64_decode($str);
//    $arr = explode("|", $str);
//    $key = $arr[0];
//    $iv = $arr[1];
//    $maskstr = $arr[2];
//
//    //获取基本信息
//    $key = base64_decode($key);
//    $iv = base64_decode($iv);
//    $maskstr = base64_decode($maskstr);
//    //解密，回显到前台
//    $str_decrypt = mcrypt_decrypt($cipher, $key, $maskstr, $modes, $iv);
//
//    return $str_decrypt;
//}

/**
 * 解密
 */
function decodeSSN($str){
    //获取 key值
//    $len = strlen($str);
//    if(strlen($str)==9 || $str=="" || $str == null){
//        $key="";
//    }else{
//        $key = getKey();
//    }
    
//    $laststr = substr(trim($str),-1);
//    if($laststr != '='){
//        return FALSE;
//    }else{
//        $key = getKey();
//    }
    
    $key = getKey();
    $cipher = MCRYPT_DES;
    $modes = MCRYPT_MODE_ECB;
    
    $str = base64_decode($str);
    $arr = explode("|", $str);
    $iv = $arr[0];
    $maskstr = $arr[1];

    $iv = base64_decode($iv);
    $maskstr = base64_decode($maskstr);
    //解密，回显到前台
    $str_decrypt = mcrypt_decrypt($cipher, $key, $maskstr, $modes, $iv);
    
    return $str_decrypt;
}

/*
 * author haoqiang
 * createKey 
 */
function createKey($keyLen) {
    $randpwd = "";
    for ($i = 0; $i < $keyLen; $i++) {
        $randpwd .= chr(mt_rand(33, 126));
    }
    return $randpwd;
    
    //从配置问价读取
//    $config = new Zend_Config_Ini('../application/configs/key.ini', 'staging');
//    $key = $config->ssn->key;
//    return $key;
    
}

/*
 * author haoqiang
 * getKey 
 */
function getKey(){
    //从配置问价读取
    $config = new Zend_Config_Ini('../application/configs/key.ini', 'staging');
    $key = $config->ssn->key;
    return $key;
}

/*
 * author haoqiang
 * maskSSN 
 */
function maskSSN($str){
    $show_str = '';
    if($str){
        $last_four = substr(trim($str), -4, 4);
        $show_str = "***-**-" . $last_four;
    }
    return $show_str;
}

function checkSSN($str){
    
    $len = strlen(trim($str));
    if($len==9){
        return true;
    }else{
        return false;
    }
}
?>
