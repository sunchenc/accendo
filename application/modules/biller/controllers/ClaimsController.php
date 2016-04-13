<?php

require_once 'helper.php';
require_once 'makestatement.php';
require_once 'pdfhelper.php';
require_once 'gen_x12_837.php';
require_once 'gen_hcfa_1500_pdf.php';
require_once 'gen_fax_pages.php';
require_once 'service.php';
require_once 'Zend/Mail.php';
require_once 'mysqlhelper.php';
require_once 'claimhelper.php';
require_once 'makecorrespondence.php';

/* * ****************new library file for upload********************* */
require_once 'upload.php';
require_once 'get_absoluted_path.php';

//require_once  'parsexml.php';
/* * ****************new library file for upload********************* */


class Biller_ClaimsController extends Zend_Controller_Action {

    /**
     * @var string $billingcompany_id
     *
     * @author Qiao Xinwang
     * @version 12/12/2011
     */
    protected $billingcompany_id = '';
    protected $user_role = '';
    protected $inactive;
    protected $user_id;

    /**
     * a function returning the billingcompany_id.
     * @author Qiao Xinwang
     * @see Biller_ClaimController::billingcompany_id
     * @return The billingcompany id
     */
    public function get_billingcompany_id() {
        return $this->billingcompany_id;
    }

    public function get_user_role() {
        return $this->user_role;
    }

    public function get_inactive() {
        return $this->inactive;
    }

    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * a function geting the POS.
     * @author Wei
     * @param $facility_id a string argument.
     */
    public function get_pos($facility_id) {
        $db = Zend_Registry::get('dbAdapter');
        $db_facility = new Application_Model_DbTable_Facility();
        $db = $db_facility->getAdapter();
        $where = $db->quoteInto('id = ?', $facility_id);
        $facility_data = $db_facility->fetchRow($where);
        return $facility_data['POS'];
    }

    /**
     * a function geting the Color alerts.
     * @author Wei
     * @param none
     */
    public function get_coloralerts() {
        $colorTags = parse_tag($_SESSION['billingcompany_data']['tags']);
        $colorAlerts = array();
        $k = 0;
        foreach ($colorTags as $key => $val) {
            $colorAlerts[$k]['RGB'] = strtoupper($key);
            $colorAlerts[$k]['alert'] = $val;
            if (isset($_SESSION['claim_data']['color_code']) && $_SESSION['claim_data']['color_code'] == $colorAlerts[$k]['RGB'])
                $colorAlerts[$k]['select'] = 1;
            else
                $colorAlerts[$k]['select'] = 0;
            $k++;
        }
        //print_r($colorAlerts);
        return $colorAlerts;
    }

    /**
     * a function seting the billingcompany id.
     * @author Qiao Xinwang
     * @param $billingcompany_id a string argument.
     * @see Biller_ClaimController::billingcompany_id
     */
    public function set_billingcompany_id($billingcompany_id) {
        $this->billingcompany_id = $billingcompany_id;
    }

    public function set_user_role($user_role) {
        $this->user_role = $user_role;
    }

    public function set_inactive($time_out) {
        $this->inactive = $time_out;
    }

    public function set_user_id($user_id) {
        $this->user_id = $user_id;
    }

    /*     * * 
     * member function 
     * get_modifier_units
     * argument concatenated modifiers of one procedure
     * return total units
     */

    public function get_modifier_units($modifiers) {
        $temparray = explode("|", $modifiers);
        $modifierstoquery = "(";
        for ($i = 0; $i < sizeof($temparray); $i++)
            $modifierstoquery = $modifierstoquery . "'" . $temparray[$i] . "', ";
        $modifierstoquery = substr($modifierstoquery, 0, strlen($modifierstoquery) - 2) . ")";

        $db_innetworkpayers = new Application_Model_DbTable_Modifier();
        $db = $db_innetworkpayers->getAdapter();
        $search = 'modifier IN ' . $modifierstoquery;
        $search = 'modifier IN ' . $modifierstoquery . ' AND ' . 'billingcompany_id=' . $this->get_billingcompany_id();
        $where = $db->quoteInto($search);
        $unitsArray = $db_innetworkpayers->fetchAll($where)->toArray();
        $units = 0;
        for ($i = 0; $i < sizeof($unitsArray); $i++)
            $units = $units + $unitsArray[$i]['unit'];
        return $units;
    }

    public function movetobot(&$insuranceList, $outkey, $newstring) {
        for ($i = 0; $i < count($insuranceList); $i++) {
            //if($insuranceList[$i]['insurance_name'] == 'Need New Insurance') {
            if ($insuranceList[$i][$outkey] == $newstring) {
                $key = $i;
                $temp = array($key => $insuranceList[$key]);
                unset($insuranceList[$key]);
                break;
            }
        }
        if ($temp != null)
            $insuranceList = array_merge($insuranceList, $temp);
    }

    public function validation_fail($ret, $encounter, $encounterinsured, $insurance_data) {
        session_start();
        $_SESSION['invalid_services_data']['services']['invalid_flag'] = 1;
        $_SESSION['invalid_services_data']['encounter_data'] = $encounter;
        $_SESSION['invalid_services_data']['encounterinsured_data'] = $encounterinsured;
        $_SESSION['invalid_services_data']['insurance_data'] = $insurance_data;
        $this->_redirect('/biller/claims/services/nullcheck/' . $ret);
    }

    public function mailmerge($templateFileName, $fieldValues, $index) {
//   $mergeResult = false;
        $app_path = getcwd();
        $billingcompany_id = $this->get_billingcompany_id();
        //$templateFile = $this->sysdoc_path . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR. "Test.docx" ;
        //$templateFile = $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "sysdoc" . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR. "Test.docx" ;
        $templateFile = $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . $this->document_root . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $templateFileName . ".docx";
//        $newFile =      $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "sysdoc" . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR. "Temp.docx" ;        
        $newFile = $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . $this->document_root . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . $templateFileName . '_' . $index . ".docx";

        copy($templateFile, $newFile); //复制模板到临时文件中。
        //下面应该是在操作word
        $zip = new ZipArchive();
        if ($zip->open($newFile, ZIPARCHIVE::CHECKCONS) !== TRUE) {
            echo 'failed to open template';
            exit;
        }//把word解yasu
        $file = 'word/document.xml';
        $data = $zip->getFromName($file);

        $doc = new DOMDocument();
        $doc->loadXML($data);
        $wts = $doc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'fldChar');

        for ($x = 0; $x < $wts->length; $x++) {
            if ($wts->item($x)->attributes->item(0)->nodeName == 'w:fldCharType' && $wts->item($x)->attributes->item(0)->nodeValue == 'begin') {
                $newcount = getMailMerge($wts, $x, $fieldValues);
                $x = $newcount;
            }
        }

        $zip->deleteName($file);
        $zip->addFromString($file, $doc->saveXML());
        $zip->close();

        //return sucess or failure
//        return $mergeResult;
        return $newFile;
    }

//---QXW 23.02.2012
    public function get_cur_service_info($type) {
        session_start();
        //add provider,insurance ID and total_charge
        //Qiao 2012-05-20
        $provider_id = '';
        $insurance_id = '';
        $total_charge = '';
        $gender = '';
        $amount_paid = '';
        $percentage = '';
        $balance_due = '';
        $last = '';
        $encounterinsured_data = $_SESSION['encounterinsured_data'];


        $tp_insured_data = $_SESSION['insured_data'];


        $tp_count = 0;
        foreach ($tp_insured_data as $row) {
            if ($row['insured_insurance_type'] == 'expired')
                $tp_count++;
        }

        //$tp_count += 5;

        /*         * **************By <Yu Lang>************** */
        $en_data = $_SESSION['encounter_data'];
        $provider_id = $_SESSION['encounter_data']['provider_id'];

        $secondary_cpt_code_1 = $_SESSION['encounter_data']['secondary_CPT_code_1'];
        /*         * **************By <Yu Lang>************** */

        if ($_SESSION['claim_data']['id']) {
            $total_charge = $_SESSION['claim_data']['total_charge'];
            $amount_paid = $_SESSION['claim_data']['amount_paid'];
            $balance_due = $_SESSION['claim_data']['balance_due'];
            $percentage = $amount_paid / $total_charge;
            $percentage = round($percentage, 2) * 100;
            if ($percentage == 0)
                $percentage = "";
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('interactionlog');
            $select->where('interactionlog.claim_id=?', $_SESSION['claim_data']['id']);
            $select->order('interactionlog.date_and_time DESC');
            $tmp_interactionlogs = $db->fetchAll($select);
            $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
            $start_date = date("Y-m-d");
            $noactiondays = 99;
            if ($end_date != null && $end_date != "") {
                $noactiondays = days($start_date, $end_date);
            } else {
                $temp_days = 99;
                if ($_SESSION['claim_data']['date_last_billed'] != null && $_SESSION['claim_data'] != null && $_SESSION['claim_data']['date_rebilled'] != null) {
                    $temp_end_date = max($_SESSION['claim_data']['date_last_billed'], $_SESSION['claim_data']['date_billed'], $_SESSION['claim_data']['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] != null && $_SESSION['claim_data']['date_billed'] == null && $_SESSION['claim_data']['date_rebilled'] != null) {
                    $temp_end_date = max($_SESSION['claim_data']['date_last_billed'], $_SESSION['claim_data']['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] == null && $_SESSION['claim_data']['date_billed'] != null && $_SESSION['claim_data']['date_rebilled'] != null) {
                    $temp_end_date = max($_SESSION['claim_data']['date_billed'], $_SESSION['claim_data']['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] != null && $_SESSION['claim_data']['date_billed'] != null && $_SESSION['claim_data']['date_rebilled'] == null) {
                    $temp_end_date = max($_SESSION['claim_data']['date_billed'], $_SESSION['claim_data']['date_last_billed']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] == null && $_SESSION['claim_data']['date_billed'] == null && $_SESSION['claim_data']['date_rebilled'] != null) {
                    $temp_end_date = $_SESSION['claim_data']['date_rebilled'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] != null && $_SESSION['claim_data']['date_billed'] == null && $_SESSION['claim_data']['date_rebilled'] == null) {
                    $temp_end_date = $_SESSION['claim_data']['date_last_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($_SESSION['claim_data']['date_last_billed'] == null && $_SESSION['claim_data']['date_billed'] != null && $_SESSION['claim_data']['date_rebilled'] == null) {
                    $temp_end_date = $_SESSION['claim_data']['date_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                }
                $noactiondays = $temp_days;
            }
            if ($noactiondays <= 99)
                $last = $noactiondays;
            else
                $last = 99;
        }

        $dd = 0;

        /*
          if ($_SESSION['insurance_data']['id']) {
          $insurance_id = $_SESSION['insurance_data']['id'];


          $db_insurance = new Application_Model_DbTable_Insurance();
          $db = $db_insurance->getAdapter();
          $where = $db->quoteInto('id = ?', $insurance_id);
          $insurance_data = $db_insurance->fetchRow($where);
          $insurance_name = $insurance_data['insurance_name'];

          }
         * 
         */
        /*         * ***************insurance for midsection*********************** */
        if (count($encounterinsured_data) > 0) {
            foreach ($encounterinsured_data as $row) {
                if ($row['type'] == 'primary') {
                    $tmp_insured_id = $row['insured_id'];
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('insured');
                    $select->join('insurance', 'insured.insurance_id = insurance.id', 'insurance_display');
                    $select->where('insured.id = ?', $tmp_insured_id);
                    $tmpList = $db->fetchAll($select);
                    //$insurance_name = $tmpList[0]['insurance_name']; 
                    $insurance_id = $tmpList[0]['insurance_id'];
                    $insurance_display = $tmpList[0]['insurance_display'];
                    $insured_ID_number = $tmpList[0]['ID_number'];
                }
            }
        } else {
            foreach ($tp_insured_data as $row) {
                if ($row['insured_insurance_type'] == 'primary') {
                    $tmp_insurance_id = $row['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $tmp_insurance_id);
                    $insurance_data = $db_insurance->fetchRow($where);
                    $insurance_display = $insurance_data['insurance_display'];
                    $insured_ID_number = $row['ID_number'];
                }
            }
        }


        /*         * ***************insurance for midsection*********************** */


        if ($_SESSION['patient_data']['id']) {
            /*             * **************By <Yu Lang>************** */
            $gender = $_SESSION['patient_data']['sex'];
            $gender = strtoupper($gender);
            $db_insured = new Application_Model_DbTable_Insured();
            $db = $db_insured->getAdapter();
            $where = $db->quoteInto('id = ?', $_SESSION['patient_data']['insured_id']);
            $insured_data = $db_insured->fetchRow($where);
//            $insured_ID_number = $insured_data['ID_number'];
            /*             * **************By <Yu Lang>************** */
        }

        $dd = 0;
        /*         * **************By <Yu Lang>************** */
        if ($_SESSION['encounter_data']['provider_id']) {
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('id = ?', $provider_id);
            $provider_data = $db_provider->fetchRow($where);
            $provider_name = $provider_data['provider_name'];
            $provider_short_name = $provider_data['short_name'];
            //$provider_tax_ID = $provider_data['tax_ID_number'];
        }
        /*         * **************By <Yu Lang>************** */


        /*         * *************Get render provider id for mid section************** */

        if ($_SESSION['encounter_data']['renderingprovider_id']) {
            $renderingprovider_id = $_SESSION['encounter_data']['renderingprovider_id'];
            $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
            $db = $db_renderingprovider->getAdapter();
            $where = $db->quoteInto('id = ?', $renderingprovider_id);
            $renderingprovider_data = $db_renderingprovider->fetchRow($where);
            $renderingprovider_name = $renderingprovider_data['last_name'] . ', ' . $renderingprovider_data['first_name'];
        }

        /**         * *************Get render provider id for mid section************** */
        /*         * *************Get refering provider id for mid section************** */

        if ($_SESSION['encounter_data']['referringprovider_id']) {
            $referringprovider_id = $_SESSION['encounter_data']['referringprovider_id'];
            $db_referringprovider = new Application_Model_DbTable_Referringprovider();
            $db = $db_referringprovider->getAdapter();
            $where = $db->quoteInto('id = ?', $referringprovider_id);
            $referringprovider_data = $db_referringprovider->fetchRow($where);
            $referringprovider_name = $referringprovider_data['last_name'] . ', ' . $referringprovider_data['first_name'];
        }

        /**         * *************Get render provider id for mid section************** */
        $cur_service_info = array(
            'gender' => $gender,
            'percentage' => $percentage,
            'patient_id' => $_SESSION['patient_data']['id'],
            'patient_name' => $_SESSION['patient_data']['last_name'] . ', ' . $_SESSION['patient_data']['first_name'],
            'DOS' => format($_SESSION['encounter_data']['start_date_1'], 1),
            /*             * ***********Add DOB for the mid section************** */
            'DOB' => format($_SESSION['patient_data']['DOB'], 1),
            /*             * ***********Add DOB for the mid section************** */
            'insurance' => $_SESSION['insurance_data']['insurance_display'],
            'MRN' => $_SESSION['patient_data']['account_number'],
            'balance_due' => $balance_due,
            'amount_paid' => $amount_paid,
            'docbtn_vavaiable' => is_docbtn_avaiable($_SESSION[$type . '_data']['id']),
            'provider_id' => $provider_id,
            'total_charge' => $total_charge,
            'insurance_id' => $insurance_id,
            'last' => $last,
            /*             * **************By <Yu Lang>************** */
            'provider_name' => $provider_name,
            /*             * **************By <Yu Lang>************** */

            /*             * **************By <Yu Lang>************** */
            'provider_short_name' => $provider_short_name,
            /*             * **************By <Yu Lang>************** */
            /*             * **************By <Yu Lang>************** */
            // 'insurance_name' => $insurance_name,
            'insurance_display' => $insurance_display,
            /*             * **************By <Yu Lang>************** */

            /*             * *****************Add claim_id 2012-11-12 *********************** */
            'claim_id' => $_SESSION['claim_data']['id'],
            /*             * *****************Add claim_id *********************** */
            'claim_status' => $_SESSION['claim_data']['claim_status'],
            'statement_status' => $_SESSION['claim_data']['statement_status'],
            'bill_status' => $_SESSION['claim_data']['bill_status'],
            /*             * *****************Add content for mid section 2012-11-12 *********************** */
            'secondary_cpt_code_1' => $secondary_cpt_code_1,
            'renderingprovider_name' => $renderingprovider_name,
            'referingprovider_name' => $referringprovider_name,
            /*             * *****************Add content for mid section 2012-11-12 *********************** */


            /*             * **************By <Yu Lang>************** */
            'insured_ID_number' => $insured_ID_number,
            /*             * **************By <Yu Lang>************** */
            'insured_expired_array_count' => $tp_count
        );
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('statementstatus');
        //  $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
        $select->where('statementstatus.statement_status = ?', $cur_service_info['statement_status']);

        try {
            $statementstatus = $db->fetchAll($select); //throw an exception,because the first time the value of $cur_service_info['statement_status'] if null
            $cur_service_info['statement_status'] = $statementstatus[0]['statement_status_display'];
        } catch (Exception $e) {
            //echo "errormessage:" . $e->getMessage(); there will be an exception at the first time, the page load
        }
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('billstatus');
        //  $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
        $select->where('billstatus.bill_status = ?', $cur_service_info['bill_status']);


        try {
            $billstatus = $db->fetchAll($select);
            $cur_service_info['bill_status'] = $billstatus[0]['bill_status_display'];
        } catch (Exception $e) {
            //echo "errormessage:" + $e->getMessage();
            //the same reason as statement_status_display. you should connect string by '.' instead of '+'
        }
        /*         * ***********Get information for dropwindow*************** */
        $tmp_user_mrn = $cur_service_info['MRN'];

        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('patient', array('account_number as mrn', 'id AS patient_id'));
        $select->join('encounter', 'encounter.patient_id = patient.id', array('start_date_1 AS dos', 'claim_id', 'id AS encounter_id'));
        $select->join('claim', 'claim.id = encounter.claim_id');
        $select->join('renderingprovider', 'encounter.renderingprovider_id= renderingprovider.id', array('renderingprovider.last_name as ren_last_name', 'renderingprovider.first_name as ren_first_name'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
        //zw $select->join('patientinsured','patientinsured.patient_id = patient.id');
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        //zw $select->join('insured', 'insured.id = patientinsured.insured_id');
        $select->join('insured', 'insured.id = encounterinsured.insured_id');
        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        $select->joinLeft('referringprovider', 'encounter.referringprovider_id= referringprovider.id', array('referringprovider.last_name as ref_last_name', 'referringprovider.first_name as ref_first_name'));
        $select->where('patient.account_number = ?', $tmp_user_mrn);
        //zw
        $select->where('encounterinsured.type = ?', 'primary');
        //
        $select->order('start_date_1 DESC');
        $tmpList = $db->fetchAll($select);

        for ($i = 0; $i < count($tmpList); $i++) {
            $tmpList[$i]['dos'] = format($tmpList[$i]['dos'], 1);
            $amount_paid = $tmpList[$i]['amount_paid'];
            $total_charge = $tmpList[$i]['total_charge'];
            $per = $amount_paid / $total_charge;
            $per = round($per, 2) * 100;
            if ($per == 0)
                $tmpList[$i]['percentage'] = "";
            else
                $tmpList[$i]['percentage'] = $per;
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('interactionlog');
            $select->where('interactionlog.claim_id=?', $tmpList[$i]['claim_id']);
            $select->order('interactionlog.date_and_time DESC');
            $tmp_interactionlogs = $db->fetchAll($select);
            $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
            $start_date = date("Y-m-d");
            $noactiondays = 99;
            if ($end_date != null && $end_date != "") {
                $noactiondays = days($start_date, $end_date);
            } else {
                $temp_days = 99;
                if ($tmpList[$i]['date_last_billed'] != null && $tmpList[$i]['date_billed'] != null && $tmpList[$i]['date_rebilled'] != null) {
                    $temp_end_date = max($tmpList[$i]['date_last_billed'], $tmpList[$i]['date_billed'], $tmpList[$i]['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] != null && $tmpList[$i]['date_billed'] == null && $tmpList[$i]['date_rebilled'] != null) {
                    $temp_end_date = max($tmpList[$i]['date_last_billed'], $tmpList[$i]['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] == null && $tmpList[$i]['date_billed'] != null && $tmpList[$i]['date_rebilled'] != null) {
                    $temp_end_date = max($tmpList[$i]['date_billed'], $tmpList[$i]['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] != null && $tmpList[$i]['date_billed'] != null && $tmpList[$i]['date_rebilled'] == null) {
                    $temp_end_date = max($tmpList[$i]['date_billed'], $tmpList[$i]['date_last_billed']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] == null && $tmpList[$i]['date_billed'] == null && $tmpList[$i]['date_rebilled'] != null) {
                    $temp_end_date = $tmpList[$i]['date_rebilled'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] != null && $tmpList[$i]['date_billed'] == null && $tmpList[$i]['date_rebilled'] == null) {
                    $temp_end_date = $tmpList[$i]['date_last_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($tmpList[$i]['date_last_billed'] == null && $tmpList[$i]['date_billed'] != null && $tmpList[$i]['date_rebilled'] == null) {
                    $temp_end_date = $tmpList[$i]['date_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                }
                $noactiondays = $temp_days;
            }
            if ($noactiondays <= 99)
                $tmpList[$i]['last'] = $noactiondays;
            else
                $tmpList[$i]['last'] = 99;
        }


        /*         * **************Add claimstatus for ***************** */
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('claimstatus');
        $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
        $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
        $select->group('claimstatus.id');
        $select->order('claimstatus.claim_status');
        try {
            $claimstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }


        /*         * *********************translate the claim_status**************** */

        foreach ($claimstatuslist as $row) {
            $translate_claim_status[$row['claim_status']] = $row['claim_status_display'];
        }

        for ($i = 0; $i < count($tmpList); $i++) {
            $tmp_claim_status = $tmpList[$i]['claim_status'];
            $tmpList[$i]['claim_status'] = $translate_claim_status[$tmp_claim_status];
        }
        /*         * **************Add billstatus for ***************** */
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('billstatus');
        $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
        $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('billstatus.requried = ?', 1);
        $select->group('billstatus.id');
        $select->order('billstatus.bill_status');
        try {
            $billstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }


        /*         * *********************translate the bill_status**************** */

        foreach ($billstatuslist as $row) {
            $translate_bill_status[$row['bill_status']] = $row['bill_status_display'];
        }

        for ($i = 0; $i < count($tmpList); $i++) {
            $tmp_bill_status = $tmpList[$i]['bill_status'];
            $tmpList[$i]['bill_status'] = $translate_bill_status[$tmp_bill_status];
        }
        /*         * **************Add claimstatus for ***************** */
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('statementstatus');
        $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
        $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('statementstatus.requried = ?', 1);
        $select->group('statementstatus.id');
        $select->order('statementstatus.statement_status');
        try {
            $statementstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }


        /*         * *********************translate the claim_status**************** */

        foreach ($statementstatuslist as $row) {
            $translate_statement_status[$row['statement_status']] = $row['statement_status_display'];
        }

        for ($i = 0; $i < count($tmpList); $i++) {
            $tmp_statement_status = $tmpList[$i]['statement_status'];
            $tmpList[$i]['statement_status'] = $translate_statement_status[$tmp_statement_status];
        }
        /*         * *********************translate the bill_status**************** */
        $this->view->tmpList = $tmpList;

        /*         * ***********Get information for dropwindow*************** */

        $dd = 0;
        //echo "hello1";//??????????????????????????????????????????????????????????????????????????????????????????????????
        return $cur_service_info;
    }

    public function init() {
        $testtemp = $this->getRequest()->getBaseUrl();
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());

        $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
        $this->sysdoc_path = '../../' . $config->dir->document_root;
        $this->document_root = $config->dir->document_root;
//---QXW 23.02.2012
//        $this->sysdoc = $config->host;
        init_sysdoc($this->sysdoc_path);
        /**
         * set value of billingcompany_id
         * Qiao Xinwang
         * 12/12/2011
         */
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $this->set_user_role($user_data['role']);
        $this->view->assign('role', $user_data['role']);

        $this->set_user_id($user_id);
//        $this->view->assign('timeout','no');
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $where = $db->quoteInto('id = ?', $biller_id);
        $biller_data = $db_biller->fetchRow($where);
        $billingcompany_id = $biller_data['billingcompany_id'];

        $dd = 0;

        $this->set_billingcompany_id($billingcompany_id);

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $billingcompany_id);
        $company_data = $db_billingcompany->fetchRow($where);

        $_SESSION['billingcompany_data'] = $company_data;

        if ($company_data['session_timeout'] == null || $company_data['session_timeout'] == 0) {
            $session_timeout = -1;
        } else {
            $session_timeout = $company_data['session_timeout'];
        }
        if (!isset($_SESSION['time_out']) || $_SESSION['time_out'] === null) {
            $_SESSION['time_out'] = time();
        }

//        $session_timeout=($company_data['session_timeout']==null?60:$company_data['session_timeout']);
        $this->set_inactive($session_timeout * 60);
    }

    public function indexAction() {
// action body
        $this->clearsession();
    }

    public function initsession($patientid, $encounterid) {
        $this->clearsession();
        if ($patientid == null) {
            session_start();
            $_SESSION['claim_data']['manual_flag'] = "no";
            return;
        }

        $service = new service($patientid, $encounterid);
        session_start();
        /*         * *Add check for new claim** */
        $_SESSION['new_upload_file'] = null;
        $_SESSION['new_claim_flag'] = 0;
        /*         * *Add check for new claim** */
        $_SESSION['time_out'] = time();
        $_SESSION['user_role'] = $this->get_user_role();
        $_SESSION['patient_data'] = $service->get_patient();


        /*         * *****************Have been changed in service.php**************** */
        $_SESSION['insured_data'] = $service->get_insured();
        $_SESSION['insurance_data'] = $service->get_insurance();

        $_SESSION['patientinsured_data'] = $service->get_patientinsured();
        /*         * *****************Have been changed**************** */
        $_SESSION['encounter_data'] = $service->get_encounter();
        $_SESSION['claim_data'] = $service->get_claim();
        $_SESSION['statement_data'] = $service->get_statement();
        $_SESSION['followups_data'] = $service->followups();
        //$_SESSION['insurancepayments_data'] = $service->get_insuracnepayments();
        //$_SESSION['patientpayments_data'] = $service->get_patientpayments();
        //$_SESSION['billeradjustments_data'] = $service->get_billeradjustments();
        $_SESSION['interactionlogs_data'] = $service->get_interactionlogs();
        $_SESSION['assignedclaims_data'] = $service->get_assignedclaims();
        $_SESSION['patientlogs'] = $service->get_patientlogs();
        $_SESSION['payments_data'] = $service->get_payments();
        $_SESSION['guarantor_data'] = $service->get_guarantor();
        /*         * ******************Add encounter insured************************ */
        $encounterinsured_data = $service->get_encounterinsured();
        for ($i = 0; $i < count($encounterinsured_data); $i++) {
            $encounterinsured_data[$i]['change_flag'] = 0;
        }
        $_SESSION['encounterinsured_data'] = $encounterinsured_data;
        /*         * ******************Add encounter insured************************ */


        $dd = 0;

        /*         * ******Temp by Yu Lang********
          $_SESSION['provider_data'] = $service->get_provider();
          /********Temp by Yu Lang******** */

        $TT = $service->get_assignedclaims();
        $datas = array('patient', 'insured', 'insurance', 'encounter', 'claim', 'followups', 'insurancepayments');
//        foreach ($datas as $val)
//            $_SESSION[$val . '_data']['diff'] = array();
//init document folders ---QXW 23.02.2012
        //$ids = array($_SESSION['patient_data']['id'], $_SESSION['insurance_data']['id'],
        //    $_SESSION['encounter_data']['id'], $_SESSION['claim_data']['id']);
        //init_document($this->sysdoc_path, $ids);
//session backup
//xinwang.qiao 04.19.2012
        session_start();
        $tmp_patient_data = $_SESSION['patient_data'];

        $_SESSION['patient_data_BK'] = $_SESSION['patient_data'];
        $_SESSION['insured_data_BK'] = $_SESSION['insured_data'];
        $_SESSION['insurance_data_BK'] = $_SESSION['insurance_data'];
        $_SESSION['encounter_data_BK'] = $_SESSION['encounter_data'];
        $_SESSION['claim_data_BK'] = $_SESSION['claim_data'];
        $_SESSION['statement_data_BK'] = $_SESSION['statement_data'];
        $_SESSION['followups_data_BK'] = $_SESSION['followups_data'];
        //$_SESSION['insurancepayments_data_BK'] = $_SESSION['insurancepayments_data'];
        //$_SESSION['billeradjustments_data_BK'] = $_SESSION['billeradjustments_data'];
        $_SESSION['interactionlogs_data_BK'] = $_SESSION['interactionlogs_data'];
        $_SESSION['patientlogs_BK'] = $service->get_patientlogs();
        //$_SESSION['patientpayments_data_BK'] = $_SESSION['patientpayments_data'];
        $_SESSION['assignedclaims_data_BK'] = $_SESSION['assignedclaims_data'];

        /*         * *******************Add encounter insured data ***************** */
        $_SESSION['encounterinsured_data_BK'] = $_SESSION['encounterinsured_data'];
        $_SESSION['patientinsured_data_BK'] = $_SESSION['patientinsured_data'];
        /*         * *******************Add encounter insured data ***************** */
        $_SESSION['payments_data_BK'] = $_SESSION['payments_data'];
        $_SESSION['guarantor_data_BK'] = $service->get_guarantor();

        $test = $_SESSION['assignedclaims_data_BK'];
        /*         * ******Temp by Yu Lang********
          $_SESSION['provider_data_BK'] =  $_SESSION['provider_data'];
          /********Temp by Yu Lang******** */

        $insurance = $_SESSION['insurance_data'];

        /* if ($$encounterid == null || $encounterid == "") {
          $claim_status = 'inactive_future_service';
          } */
        /* else if ($_SESSION['claim_data']['claim_status'] == null) {
          $claim_status = '';
          if (strtoupper($insurance['payer_type']) == 'MM')
          $claim_status = 'open_ready_delayed_primary_bill';
          else {
          if (strtoupper($insurance['payer_type']) == 'SP')
          $claim_status = 'inactive_selfpay';
          else
          $claim_status = 'open_ready_primary_bill';
          }
          //$this->write_session('claim', array('claim_status' => $claim_status));
          $_SESSION['claim_data']['claim_status'] = $claim_status;
          } */
        /* else if($_SESSION['claim_data']['claim_status'] == null || $_SESSION['claim_data']['bill_status'] == null){
          if($_SESSION['claim_data']['bill_status'] == null){
          $bill_status = '';
          if (strtoupper($insurance['payer_type']) == 'MM')
          $bill_status = 'bill_ready_bill_delayed_primary';
          else {
          if (!strtoupper($insurance['payer_type']) == 'SP')
          $bill_status = 'bill_ready_bill_primary';
          }
          $_SESSION['claim_data']['bill_status'] = $bill_status;
          }else{
          if (strtoupper($insurance['payer_type']) == 'SP')
          $_SESSION['claim_data']['claim_status'] = "inactive_selfpay";
          }
          } */
        if ($encounterid == null || $encounterid == "") {
            $_SESSION['claim_data']['mannual_flag'] = "no";
        }
        $benefit_co_insurance = $_SESSION['claim_data']['benefit_co_insurance'];
        $_SESSION['claim_data']['benefit_co_insurance'] = percentage($benefit_co_insurance, 2);
    }

    public function clearsession() {
//unset all session
        session_start();
        foreach (get_session_name() as $val) {
            unset($_SESSION[$val . '_data']);
            unset($_SESSION[$val . '_data_BK']);
        }
        unset($_SESSION['patientlogs']);
        unset($_SESSION['patientlogs_BK']);
        unset($_SESSION['payments_data']);
        unset($_SESSION['payments_data_BK']);
        unset($_SESSION['new_patient_flag']);
        unset($_SESSION['from_top_ten']);
        unset($_SESSION['insured_data_change_flag']);
        unset($_SESSION['first_severe_flag']);
        unset($_SESSION['first_claim_flag']);
        unset($_SESSION['claim_data']['mannual_flag']);
        unset($_SESSION['invalid_data']);
        unset($_SESSION['invalid_data']['claim']['invalid_flag']);
        unset($_SESSION['invalid_services_data']);
        /*         * *Added SESSION BY YuLang*** */
        //unset($_SESSION['new_claim_flag']);
        /*         * *Added SESSION BY YuLang*** */
    }

    /**
     * set_options_session
     *
     * set options session
     *
     * @author Qiao Xinwang
     * @version 12/11/2011
     *
     * @param string $providerid provider.id
     * @param string $providername provider.provider_name
     * @param string $billingcompanyid billingcompany.id
     */
    public function set_options_session($providerid, $providername, $billingcompanyid) {
        $options_data = array();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('options');
        $select->join('provider', 'provider.options_id=options.id');
        if ($providerid) {
            $select->where('provider.id IN(?)', $providerid);
        } else if ($providername) {
            $select->where('provider.provider_name=?', $providername);
        } else {
            $select->join('billingcompany', 'billingcompany.default_provider=provider.provider_name');
            $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        }
        $options_data = $db->fetchRow($select);
        session_start();
        $_SESSION['options'] = $options_data;
    }

    /**
     * unset_options_session
     *
     * clear session: options
     *
     * @author Qiao Xinwang
     * @version 12/11/2011
     */
    public function unset_options_session() {
        session_start();
        unset($_SESSION['options']);
    }

    /**
     * Get TAI list
     */
    public function getTAIList($insurance_id, &$issue, &$second_order) {
        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        $providerList = array();
        for ($i = 0; $i < count($userfocusonprovider); $i++) {
            $providerList[$i] = $userfocusonprovider[$i]['provider_id'];
        }

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('billingcompany', array('patient.id AS patient_id', 'encounter.id AS encounter_id', 'claim.id AS claim_id', 'billingcompany_name', 'claim.claim_status', 'provider.provider_name', 'patient.account_number', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name', 'patient.DOB as p_DOB',
            'renderingprovider.last_name as r_last_name', 'renderingprovider.first_name as r_first_name', 'referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name', 'insurance.insurance_name', 'insurance.number_of_days_without_activities_i', 'insurance.number_of_days_no_payment_issued_i', 'encounter.start_date_1', 'encounter.id as encounter_id'));
        $select->join('provider', 'billingcompany.id=provider.billingcompany_id', array('provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('options', 'options.id=provider.options_id');
        $select->join('encounter', 'provider.id=encounter.provider_id');
        /*         * ********************Add fields***************** */
        $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id');
        /*         * ********************Add fields***************** */
        //$select->join('mypatient', 'provider.id = mypatient.provider_id');
        $select->join('patient', 'encounter.patient_id = patient.id');
        //zw $select->join('insured', 'insured.id = patient.insured_id');
        $select->join('encounterinsured', 'encounter.id = encounterinsured.encounter_id');
        $select->join('insured', 'insured.id = encounterinsured.insured_id');
        $select->join('insurance', 'insurance.id = insured.insurance_id');
        $select->join('claim', 'encounter.claim_id = claim.id');
        //$select->join('encounter', 'patient.id = encounter.patient_id');
        $select->join('facility', 'facility.id = encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id');
        //$select->join('claim', 'encounter.claim_id = claim.id');
        $select->join('followups', 'claim.id = followups.claim_id');

        /*         * *******************************Add the issued payment**************************************** */
        /* $select->joinLeft('insurancepayments', 'insurancepayments.claim_id = claim.id',array('insurancepayments.amount AS insurancepayments_amount', 'insurancepayments.date AS insurancepayments_date' ));
          /*********************************Add the issued payment**************************************** */
        //zw
        $select->where('encounterinsured.type LIKE ?', 'primary%');
        //
        $select->where('claim.claim_status LIKE ?', 'open%');
        $select->where('claim.claim_status!=?', 'open_inactive');
        //$select->where('claim.claim_status!=?', 'open_ready_primary_bill');
        //$select->where('claim.claim_status!=?', 'open_ready_delayed_primary_bill');
        //$select->where('claim.bill_status!=?', 'bill_inactive');
        $select->where('claim.bill_status is null OR claim.bill_status!=\'bill_ready_bill_primary\'');
        $select->where('claim.bill_status is null OR claim.bill_status!=\'bill_ready_bill_delayed_primary\'');
        if ($providerList != null) {
            $select->where('provider.id IN(?)', $providerList);
        }
        if ($insurance_id != null) {
            $select->where('insurance.id =?', $insurance_id);
        }
        /*         * ***************************Change TAI  By Yu Lang 2012-08-09*************************************** */
        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
//        $select->order('patient.last_name ASC');

        $List = $db->fetchAll($select);

        /*         * ******************************Check the recived date for the TAI list*************************** */
        /*         * **********************for test************************* */
        $test_list = array();
        /*         * **********************for test************************* */

        $size_list = sizeof($List);
        for ($j = 0; $j < $size_list; $j++) {
            $tmp_claim_id = $List[$j]['claim_id'];
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('insurancepayments');
            $select->where('insurancepayments.claim_id=?', $tmp_claim_id);
            $tmp_insurancepayments = $db->fetchRow($select);
            $ee = 'ddd';
            $List[$j]['insurancepayments_amount'] = $tmp_insurancepayments['amount'];
            $List[$j]['insurancepayments_date'] = $tmp_insurancepayments['date'];

            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('interactionlog');
            $select->where('interactionlog.claim_id=?', $tmp_claim_id);
            $select->order('interactionlog.date_and_time DESC');
            $tmp_interactionlogs = $db->fetchAll($select);
            $List[$j]['date_and_time'] = $tmp_interactionlogs[0]['date_and_time'];
            $amount_paid = $List[$j]['amount_paid'];
            $total_charge = $List[$j]['total_charge'];
            $per = $amount_paid / $total_charge;
            $per = round($per, 2) * 100;
            if ($per == 0)
                $List[$j]['percentage'] = "";
            else
                $List[$j]['percentage'] = $per;
            /*             * **********************for test************************* */
            if ($List[$j]['claim_id'] == '840')
                array_push($test_list, $List[$j]);
            /*             * **********************for test************************* */
        }

        $searchList = array();
        $index = 0;
        $today = date("Y-m-d");
        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $start_date = date("Y-m-d");

        foreach ($List as $row) {

            $number_of_days_without_activities = $List[0]['number_of_days_without_activities'];
            $number_of_days_no_payment_issued = $List[0]['number_of_days_no_payment_issued'];

            $searchList[$index]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
            $searchList[$index]['MRN'] = $row['account_number'];
            $searchList[$index]['DOB'] = $row['p_DOB'];
            $searchList[$index]['provider_name'] = $row['provider_name'];
            $searchList[$index]['renderingprovider'] = $row['r_last_name'] . ', ' . $row['r_first_name'];
            $searchList[$index]['DOS'] = $row['start_date_1'];
            $searchList[$index]['encounter_id'] = $row['encounter_id'];
            $searchList[$index]['payer'] = $row['insurance_display'];
            $searchList[$index]['facility_name'] = $row['facility_name'];

            /*             * ****************Add fields********************* */
            $searchList[$index]['facility_short_name'] = $row['facility_short_name'];
            $searchList[$index]['provider_short_name'] = $row['provider_short_name'];
            if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                $searchList[$index]['referringprovider'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
            else
                $searchList[$index]['referringprovider'] = '';

            $searchList[$index]['total_charge'] = $row['total_charge'];
            $searchList[$index]['percentage'] = $row['percentage'];
//             if($row['balance_due']==null||$row['balance_due']=="")
//                 $row['balance_due']="0.00";
            $searchList[$index]['due'] = $row['balance_due'];
            $searchList[$index]['amount_paid'] = $row['amount_paid'];
            /*             * ****************Add fields********************* */

            /*             * ****************for test********************* */
            $searchList[$index]['company_name'] = $row['billingcompany_name'];
            $searchList[$index]['claim_id'] = $row['claim_id'];
            $searchList[$index]['encounter_id'] = $row['encounter_id'];
            $searchList[$index]['patient_id'] = $row['patient_id'];
            /*             * ****************for test********************* */
            $searchList[$index]['score'] = -1;

// Set no action days            
            if ($row['date_and_time'] != null && $row['date_and_time'] != "") {
                $end_date = format($row['date_and_time'], 0);
                $noactiondays = days($start_date, $end_date);
                $searchList[$index]['last'] = $noactiondays;
            }
//zw. Check next followup date            
            if ($row['date_of_next_followup'] != null && $row['date_of_next_followup'] != '0000-00-00' && $row['date_of_next_followup'] != '') {
                $end_date = format($row['date_of_next_followup'], 0);
                if ($end_date != null) {
                    $days = days($start_date, $end_date);
                }
                if ($days >= 0) {
                    $searchList[$index]['score'] = 10;
                    $searchList[$index]['display'] = "Follow-up date " . format($row['date_of_next_followup'], 1);
                    //$searchList[$index]['followup_date'] = format($row['date_of_next_followup'], 0);
                    $dateArray = explode("-", format($row['date_of_next_followup'], 0));
                    $searchList[$index]['second_order'] = intval($dateArray[0]) * 10000 + intval($dateArray[1]) * 100 + intval($dateArray[2]);
                    $index = $index + 1;
                }
                continue;
            }
//zw. Check no interaction days   
            //if (!strpos($row['claim_status'], 'pen_billed') > 0 && !strpos($row['claim_status'], 'pen_rebilled') > 0) 
            //if (!strpos($row['claim_status'], 'pen_billed') > 0)        
            if (strpos($row['bill_status'], 'bill_billed') === false || strpos($row['claim_status'], 'pen_new_claim') === false)
                continue;
            if ($searchList[$index]['score'] < 0) {
                if ($row['number_of_days_without_activities_i'] != null && $row['number_of_days_without_activities_i'] != "")
                    $number_of_days_without_activities = $row['number_of_days_without_activities_i'];
                if ($row['date_and_time'] != null)
                    $end_date = format($row['date_and_time'], 0);
                if ($end_date != null && $end_date != "") {
                    $noactiondays = days($start_date, $end_date);
                    $searchList[$index]['last'] = $noactiondays;
                    $days = days($start_date, $end_date);
                    if ($days >= (float) $number_of_days_without_activities) {
                        $searchList[$index]['display'] = "No interaction for " . $days . " days";
                        $searchList[$index]['second_order'] = intval($days) * (-1);
                        $searchList[$index]['score'] = 8;
                        $index = $index + 1;
                        continue;
                    }
                }
            }
// zw. Check billed, but not closed
            //if($row['p_last_name'] == "Stewart" && $row['p_first_name'] == "Damon")
            //     $dd = 0;
            if ($searchList[$index]['score'] < 0) {
                if ($row['number_of_days_no_payment_issued_i'] != null && $row['number_of_days_no_payment_issued_i'] != "")
                    $number_of_days_no_payment_issued = $row['number_of_days_no_payment_issued_i'];
                $date_last_billed = max($row['date_last_billed'] != null ? $row['date_last_billed'] : '0000-00-00', $row['date_billed'] != null ? $row['date_billed'] : '0000-00-00', $row['date_secondary_insurance_billed'] != null ? $row['date_secondary_insurance_billed'] : '0000-00-00');
                if ($date_last_billed != '0000-00-00')
                    $end_date = format($date_last_billed, 0);
                if ($end_date != null && $end_date != "") {
                    $nopaymentdays = days($start_date, $end_date);
//                    $searchList[$index]['last'] = $nopaymentdays;
                    $days = days($start_date, $end_date);
                    if ($days >= (float) $number_of_days_no_payment_issued) {
                        $searchList[$index]['display'] = "Not closed after " . $days . " days";
                        $searchList[$index]['second_order'] = intval($days) * (-1);
                        $searchList[$index]['score'] = 6;
                        $index = $index + 1;
                        continue;
                    }
                }
            }
            //$index = $index + 1;    
        }

        /*         * ****************for test*************** */
        $tmp_list = array();
        /*         * ****************for test*************** */

        $index = 0;
        $taiList = array();
        foreach ($searchList as $row) {
            if ($row['score'] > 0) {

                /*                 * ****************for test*************** */
//                if ($row['MRN'] == '0000743') {
//                    array_push($tmp_list, $row);
//                }

                /*                 * ****************for test*************** */
                $taiList[$index]['name'] = $row['name'];
                $taiList[$index]['MRN'] = $row['MRN'];
                $taiList[$index]['DOB'] = $row['DOB'];
                /*                 * ****************Add fields*************** */
                $taiList[$index]['facility_short_name'] = $row['facility_short_name'];
                $taiList[$index]['provider_short_name'] = $row['provider_short_name'];
                $taiList[$index]['referringprovider'] = $row['referringprovider'];
                $taiList[$index]['total_charge'] = $row['total_charge'];
                $taiList[$index]['patient_id'] = $row['patient_id'];
                $taiList[$index]['percentage'] = $row['percentage'];
                $taiList[$index]['due'] = $row['due'];
                $taiList[$index]['amount_paid'] = $row['amount_paid'];

                /*                 * ****************Add fields*************** */
                $taiList[$index]['provider_name'] = $row['provider_name'];
                $taiList[$index]['renderingprovider'] = $row['renderingprovider'];
                $taiList[$index]['DOS'] = $row['DOS'];
                $taiList[$index]['encounter_id'] = $row['encounter_id'];
                $taiList[$index]['payer'] = $row['payer'];
                $taiList[$index]['claim_id'] = $row['claim_id'];
                $taiList[$index]['facility_name'] = $row['facility_name'];
                $taiList[$index]['score'] = $row['score'];
                if ($row['last'] <= 99)
                    $taiList[$index]['last'] = $row['last'];
                else
                    $taiList[$index]['last'] = 99;
                $taiList[$index]['display'] = $row['display'];
                $taiList[$index]['second_order'] = $row['second_order'];
                $issue[$index] = $row['score'];
                $second_order[$index] = $row['second_order'];
                $index = $index + 1;
            }
        }
        return $taiList;
    }

    /**
     * actionlistAction
     * a function returning an array of TAI list and write it to $_SESSION['tmp']['actionList'] for sorting.
     * @author Haowei.
     * @return The TAI list
     * @version 05/15/2012
     */
    public function actionlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $issue = array();
        $second_order = array();
        $taiList = $this->getTAIList($insurance_id, $issue, $second_order);
        /*
          $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
          $db = $db_userfocusonprovider->getAdapter();
          $where = $db->quoteInto('user_id = ?', $this->user_id);
          $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
          $providerList = array();
          for ($i = 0; $i < count($userfocusonprovider); $i++) {
          $providerList[$i] = $userfocusonprovider[$i]['provider_id'];
          }



          $db = Zend_Registry::get('dbAdapter');
          $select = $db->select();




          $select->from('billingcompany', array('patient.id AS patient_id', 'encounter.id AS encounter_id', 'claim.id AS claim_id', 'billingcompany_name', 'claim.claim_status', 'provider.provider_name', 'patient.account_number', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name', 'patient.DOB as p_DOB',
          'renderingprovider.last_name as r_last_name', 'renderingprovider.first_name as r_first_name', 'referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name', 'insurance.insurance_name', 'insurance.number_of_days_without_activities_i', 'insurance.number_of_days_no_payment_issued_i', 'encounter.start_date_1', 'encounter.id as encounter_id'));
          $select->join('provider', 'billingcompany.id=provider.billingcompany_id', array('provider_name', 'provider.short_name AS provider_short_name'));
          $select->join('options', 'options.id=provider.options_id');
          $select->join('encounter', 'provider.id=encounter.provider_id');

          $select->join('referringprovider', 'referringprovider.id = encounter.referringprovider_id');

          //$select->join('mypatient', 'provider.id = mypatient.provider_id');
          $select->join('patient', 'encounter.patient_id = patient.id');
          //zw $select->join('insured', 'insured.id = patient.insured_id');
          $select->join('encounterinsured', 'encounter.id = encounterinsured.encounter_id');
          $select->join('insured', 'insured.id = encounterinsured.insured_id');
          $select->join('insurance', 'insurance.id = insured.insurance_id');
          $select->join('claim', 'encounter.claim_id = claim.id');
          //$select->join('encounter', 'patient.id = encounter.patient_id');
          $select->join('facility', 'facility.id = encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
          $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id');
          //$select->join('claim', 'encounter.claim_id = claim.id');
          $select->join('followups', 'claim.id = followups.claim_id');


          //zw
          $select->where('encounterinsured.type LIKE ?', 'primary%');
          //
          $select->where('claim.claim_status LIKE ?', 'open%');
          $select->where('claim.claim_status!=?', 'open_inactive');
          //$select->where('claim.claim_status!=?', 'open_ready_primary_bill');
          //$select->where('claim.claim_status!=?', 'open_ready_delayed_primary_bill');
          //$select->where('claim.bill_status!=?', 'bill_inactive');
          $select->where('claim.bill_status is null OR claim.bill_status!=\'bill_ready_bill_primary\'');
          $select->where('claim.bill_status is null OR claim.bill_status!=\'bill_ready_bill_delayed_primary\'');
          if ($providerList != null) {
          $select->where('provider.id IN(?)', $providerList);
          }

          $select->where('billingcompany.id=?', $this->get_billingcompany_id());
          //        $select->order('patient.last_name ASC');

          $List = $db->fetchAll($select);


          $test_list = array();


          $size_list = sizeof($List);
          for ($j = 0; $j < $size_list; $j++) {
          $tmp_claim_id = $List[$j]['claim_id'];
          $db = Zend_Registry::get('dbAdapter');
          $select = $db->select();
          $select->from('insurancepayments');
          $select->where('insurancepayments.claim_id=?', $tmp_claim_id);
          $tmp_insurancepayments = $db->fetchRow($select);
          $ee = 'ddd';
          $List[$j]['insurancepayments_amount'] = $tmp_insurancepayments['amount'];
          $List[$j]['insurancepayments_date'] = $tmp_insurancepayments['date'];

          $db = Zend_Registry::get('dbAdapter');
          $select = $db->select();
          $select->from('interactionlog');
          $select->where('interactionlog.claim_id=?', $tmp_claim_id);
          $select->order('interactionlog.date_and_time DESC');
          $tmp_interactionlogs = $db->fetchAll($select);
          $List[$j]['date_and_time'] = $tmp_interactionlogs[0]['date_and_time'];
          $amount_paid = $List[$j]['amount_paid'];
          $total_charge = $List[$j]['total_charge'];
          $per =  $amount_paid/$total_charge;
          $per=round($per, 2)*100;
          if($per==0)
          $List[$j]['percentage'] ="";
          else
          $List[$j]['percentage'] = $per;

          if ($List[$j]['claim_id'] == '840')
          array_push($test_list, $List[$j]);

          }



          //$number_of_days_without_activities = $List[0]['number_of_days_without_activities'];
          //$number_of_days_no_payment_issued = $List[0]['number_of_days_no_payment_issued'];

          $searchList = array();
          $index = 0;
          $today = date("Y-m-d");
          $yesterday = date("Y-m-d", strtotime("-1 day"));
          $start_date = date("Y-m-d");

          foreach ($List as $row) {
          //if($row['p_last_name'] === "Francesco" && $row['p_first_name'] === "Joseph") {
          //    $days = 0;
          //    $enddate = "0000-00-00";
          //}
          $number_of_days_without_activities = $List[0]['number_of_days_without_activities'];
          $number_of_days_no_payment_issued = $List[0]['number_of_days_no_payment_issued'];

          $searchList[$index]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
          $searchList[$index]['MRN'] = $row['account_number'];
          $searchList[$index]['DOB'] = $row['p_DOB'];
          $searchList[$index]['provider_name'] = $row['provider_name'];
          $searchList[$index]['renderingprovider'] = $row['r_last_name'] . ', ' . $row['r_first_name'];
          $searchList[$index]['DOS'] = $row['start_date_1'];
          $searchList[$index]['encounter_id'] = $row['encounter_id'];
          $searchList[$index]['payer'] = $row['insurance_display'];
          $searchList[$index]['facility_name'] = $row['facility_name'];


          $searchList[$index]['facility_short_name'] = $row['facility_short_name'];
          $searchList[$index]['provider_short_name'] = $row['provider_short_name'];
          $searchList[$index]['referringprovider'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
          //$searchList[$index]['referringprovider_last_name'] =  $row['referringprovider_last_name'];
          //$searchList[$index]['referringprovider_first_name'] = $row['referringprovider_last_name'];
          $searchList[$index]['total_charge'] = $row['total_charge'];
          $searchList[$index]['percentage'] = $row['percentage'];
          //             if($row['balance_due']==null||$row['balance_due']=="")
          //                 $row['balance_due']="0.00";
          $searchList[$index]['due'] = $row['balance_due'];
          $searchList[$index]['amount_paid'] = $row['amount_paid'];

          $searchList[$index]['company_name'] = $row['billingcompany_name'];
          $searchList[$index]['claim_id'] = $row['claim_id'];
          $searchList[$index]['encounter_id'] = $row['encounter_id'];
          $searchList[$index]['patient_id'] = $row['patient_id'];

          $searchList[$index]['score'] = -1;

          // Set no action days
          if ($row['date_and_time'] != null && $row['date_and_time'] != "") {
          $end_date = format($row['date_and_time'], 0);
          $noactiondays = days($start_date, $end_date);
          $searchList[$index]['last'] = $noactiondays;
          }
          //zw. Check next followup date
          if ($row['date_of_next_followup'] != null && $row['date_of_next_followup'] != '0000-00-00' && $row['date_of_next_followup'] != '') {
          $end_date = format($row['date_of_next_followup'], 0);
          if ($end_date != null) {
          $days = days($start_date, $end_date);
          }
          if ($days >= 0) {
          $searchList[$index]['score'] = 10;
          $searchList[$index]['display'] = "Follow-up date " . format($row['date_of_next_followup'], 1);
          //$searchList[$index]['followup_date'] = format($row['date_of_next_followup'], 0);
          $dateArray = explode("-", format($row['date_of_next_followup'], 0));
          $searchList[$index]['second_order'] = intval($dateArray[0])*10000 + intval($dateArray[1])*100 + intval($dateArray[2]);
          $index = $index + 1;
          }
          continue;
          }
          //zw. Check no interaction days
          //if (!strpos($row['claim_status'], 'pen_billed') > 0 && !strpos($row['claim_status'], 'pen_rebilled') > 0)
          //if (!strpos($row['claim_status'], 'pen_billed') > 0)
          if (strpos($row['bill_status'], 'bill_billed') === false || strpos($row['claim_status'], 'pen_new_claim') === false)
          continue;
          if ($searchList[$index]['score'] < 0) {
          if($row['number_of_days_without_activities_i'] != null && $row['number_of_days_without_activities_i'] != "")
          $number_of_days_without_activities = $row['number_of_days_without_activities_i'];
          if ($row['date_and_time'] != null)
          $end_date = format($row['date_and_time'], 0);
          if ($end_date != null && $end_date!="") {
          $noactiondays = days($start_date, $end_date);
          $searchList[$index]['last'] = $noactiondays;
          $days = days($start_date, $end_date);
          if ($days >= (float)$number_of_days_without_activities) {
          $searchList[$index]['display'] = "No interaction for " . $days . " days";
          $searchList[$index]['second_order'] = intval($days) * (-1);
          $searchList[$index]['score'] = 8;
          $index = $index + 1;
          continue;
          }
          }
          }
          // zw. Check billed, but not closed
          //if($row['p_last_name'] == "Stewart" && $row['p_first_name'] == "Damon")
          //     $dd = 0;
          if ($searchList[$index]['score'] < 0) {
          if($row['number_of_days_no_payment_issued_i'] != null && $row['number_of_days_no_payment_issued_i'] != "")
          $number_of_days_no_payment_issued = $row['number_of_days_no_payment_issued_i'];
          $date_last_billed = max($row['date_last_billed']!=null?$row['date_last_billed']:'0000-00-00', $row['date_billed']!=null?$row['date_billed']:'0000-00-00', $row['date_secondary_insurance_billed']!=null?$row['date_secondary_insurance_billed']:'0000-00-00');
          if ($date_last_billed != '0000-00-00')
          $end_date = format($date_last_billed, 0);
          if ($end_date != null && $end_date!="") {
          $nopaymentdays = days($start_date, $end_date);
          //                    $searchList[$index]['last'] = $nopaymentdays;
          $days = days($start_date, $end_date);
          if ($days >= (float)$number_of_days_no_payment_issued) {
          $searchList[$index]['display'] = "Not closed after " . $days . " days";
          $searchList[$index]['second_order'] = intval($days) * (-1);
          $searchList[$index]['score'] = 6;
          $index = $index + 1;
          continue;
          }
          }
          }
          //$index = $index + 1;
          }


          $tmp_list = array();


          $index = 0;
          $taiList = array();
          foreach ($searchList as $row) {
          if ($row['score'] > 0) {


          $taiList[$index]['name'] = $row['name'];
          $taiList[$index]['MRN'] = $row['MRN'];
          $taiList[$index]['DOB'] = $row['DOB'];

          $taiList[$index]['facility_short_name'] = $row['facility_short_name'];
          $taiList[$index]['provider_short_name'] = $row['provider_short_name'];
          $taiList[$index]['referringprovider'] = $row['referringprovider'];
          $taiList[$index]['total_charge'] = $row['total_charge'];
          $taiList[$index]['percentage'] = $row['percentage'];
          $taiList[$index]['due'] = $row['due'];
          $taiList[$index]['amount_paid'] = $row['amount_paid'];
          //$taiList[$index]['referringprovider_last_name'] = $row['referringprovider_last_name'];
          //$taiList[$index]['referringprovider_first_name'] = $row['referringprovider_first_name'];

          $taiList[$index]['provider_name'] = $row['provider_name'];
          $taiList[$index]['renderingprovider'] = $row['renderingprovider'];
          $taiList[$index]['DOS'] = $row['DOS'];
          $taiList[$index]['encounter_id'] = $row['encounter_id'];
          $taiList[$index]['payer'] = $row['payer'];
          $taiList[$index]['facility_name'] = $row['facility_name'];
          $taiList[$index]['score'] = $row['score'];
          if($row['last']<=99)
          $taiList[$index]['last'] = $row['last'];
          else
          $taiList[$index]['last'] = 99;
          $taiList[$index]['display'] = $row['display'];
          $taiList[$index]['second_order'] = $row['second_order'];
          $issue[$index] = $row['score'];
          $second_order[$index] = $row['second_order'];
          $index = $index + 1;
          }
          }
         */
        /*         * ****************for test*************** */

        //var_dump($tmp_list[0]);
        //var_dump($test_list);
        //return;
        /*         * ****************for test*************** */

        //function my_compare($a, $b) {
        //    if ($a['score'] < $b['score'])
        //        return 1;
        //    else if ($a['score'] == $b['score'])
        //        return 0;
        //    else
        //        return -1;
        //}
        //uasort($taiList, 'my_compare');
        array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $taiList);
        session_start();
        unset($_SESSION['tmp']);
        $_SESSION['tmp']['actionList'] = $taiList;

        $this->_redirect('/biller/claims/action');
    }

    /**
     * actionAction
     * the controller of action.html
     * it will accept the order and give the view the sorted TAI list.
     * @author Haowei
     * @version 05/15/2012.
     */
    public function actionAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $taiList = $_SESSION['tmp']['actionList'];
            $this->view->searchList = $taiList;
        }
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            $postType = $this->getRequest()->getParam('post');
            session_start();
            $taiList = $_SESSION['tmp']['actionList'];
            foreach ($taiList as $key => $row) {
                $score[$key] = $row['score'];
                $patient[$key] = $row['name'];

                /*                 * **********Add Fields********** */
                // $dob[$key] = $row['DOB'];
                $dob[$key] = format($row['DOB'], 0);
                $total_charge[$key] = $row['total_charge'];
                $amount_paid[$key] = $row['amount_paid'];
                $percentage[$key] = $row['percentage'];
                $due[$key] = $row['due'];
                $referringprovider[$key] = $row['referringprovider'];
                /*                 * **********Add Fields********** */
                $insurance[$key] = $row['payer'];
                $provider[$key] = $row['provider_name'];
                $facility[$key] = $row['facility_name'];
                $mrn[$key] = $row['MRN'];
                $dos[$key] = format($row['DOS'], 0);
                $issue[$key] = $row['score'];
                $second_order[$key] = $row['second_order'];
                $last[$key] = $row['last'];
                $renderingprovider[$key] = $row['renderingprovider'];
            }

            /*             * **********Add Fields********** */

            if ($postType == "DOB") {
                if ($taiList[0]['DOB'] >= $taiList[sizeof($taiList) - 1]['DOB']) {

                    array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "Charge") {
                if ($total_charge[0] <= $total_charge[sizeof($total_charge) - 1]) {

                    array_multisort($total_charge, SORT_DESC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($total_charge, SORT_ASC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "Paid") {
                if ($taiList[0]['amount_paid'] >= $taiList[sizeof($taiList) - 1]['amount_paid']) {

                    array_multisort($amount_paid, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($amount_paid, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "%") {
                if ($percentage[0] <= $percentage[sizeof($percentage) - 1]) {

                    array_multisort($percentage, SORT_DESC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($percentage, SORT_ASC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "Due") {
                if ($taiList[0]['due'] <= $taiList[sizeof($taiList) - 1]['due']) {

                    array_multisort($due, SORT_DESC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($due, SORT_ASC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "Referring Provider") {
                if ($taiList[0]['referringprovider'] >= $taiList[sizeof($taiList) - 1]['referringprovider']) {

                    array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            /*             * **********Add Fields********** */

            if ($postType == "Name") {

                /*                 * *****************sort the insurance****************** */
                $patient_slowercase = array_map('strtolower', $patient);
                if ($patient_slowercase[0] >= $patient_slowercase[sizeof($patient_slowercase) - 1]) {

                    array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($patient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $taiList);
                }

                //array_multisort($patient, SORT_ASC, $taiList);
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "Facility") {
                if ($taiList[0]['facility_short_name'] >= $taiList[sizeof($taiList) - 1]['facility_short_name']) {

                    array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "MRN") {
                if ($taiList[0]['MRN'] >= $taiList[sizeof($taiList) - 1]['MRN']) {

                    array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "DOS") {
                if ($taiList[0]['DOS'] >= $taiList[sizeof($taiList) - 1]['DOS']) {

                    array_multisort($dos, SORT_ASC, $taiList);
                } else {

                    array_multisort($dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "Last") {
                if ($taiList[0]['last'] <= $taiList[sizeof($taiList) - 1]['last']) {

                    array_multisort($last, SORT_DESC, $dos, SORT_DESC, $taiList);
                } else {

                    array_multisort($last, SORT_ASC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "Issue") {
                //array_multisort($issue, SORT_ASC, $fu_date, SORT_ASC, $p_i_n_r_days, SORT_ASC, SORT_NUMERIC, $taiList);
                //array_multisort($issue, SORT_ASC, $p_i_n_r_days, SORT_ASC, SORT_NUMERIC, $taiList);
                if ($taiList[0]['score'] <= $taiList[sizeof($taiList) - 1]['score']) {

                    array_multisort($issue, SORT_DESC, $dos, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $taiList);
                } else {

                    array_multisort($issue, SORT_ASC, $dos, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "Insurance") {

                /*                 * *****************sort the insurance****************** */

                $insurance_slowercase = array_map('strtolower', $insurance);
                if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {
                    $_SESSION['cliam_recent_sort_Insurance_reverse_acction'] = 1;
                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $taiList);
                } else {
                    $_SESSION['cliam_recent_sort_Insurance_reverse_acction'] = 0;
                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            if ($postType == "Provider") {
                if ($provider[0] >= $provider[sizeof($provider) - 1]) {
                    $_SESSION['cliam_recent_sort_Provider_reverse_acction'] = 1;
                    array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {
                    $_SESSION['cliam_recent_sort_Provider_reverse_acction'] = 0;
                    array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }

            if ($postType == "Rendering Provider") {
                if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {
                    $_SESSION['cliam_recent_sort_Rendering_Provider_reverse_acction'] = 1;
                    array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $taiList);
                } else {
                    $_SESSION['cliam_recent_sort_Rendering_Provider_reverse_acction'] = 0;
                    array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $taiList);
                }
                $_SESSION['tmp']['actionList'] = $taiList;
                $this->_redirect('/biller/claims/action');
            }
            $encounter_id = $this->getRequest()->getPost('id');
            if ($encounter_id != null) {
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                $where = $db->quoteInto('id = ?', $encounter_id);
                $encounter_data = $db_encounter->fetchRow($where);
                $patient_id = $encounter_data['patient_id'];
                $this->initsession($patient_id, $encounter_id);

                $this->set_options_session($encounter_data['provider_id'], '', '');
            }
            $this->_redirect('/biller/claims/claim');
        }
    }

    public function assignedclaimslistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('renderingprovider', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('encounter.start_date_1', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id'));
        /*         * ********************Add fields***************** */
        $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id', array('referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name'));
        /*         * ********************Add fields***************** */
        $select->join('claim', 'claim.id=encounter.claim_id');
        $select->joinLeft('claimstatus', 'claimstatus.claim_status=claim.claim_status', array('CONCAT(\'\',claim_status_display,\'\') AS claim_status_display'));
        $select->joinLeft('billstatus', 'billstatus.bill_status=claim.bill_status', array('CONCAT(\'\',bill_status_display,\'\') AS bill_status_display'));
        $select->joinLeft('statementstatus', 'statementstatus.statement_status=claim.statement_status', array('CONCAT(\'\',statement_status_display,\'\') AS statement_status_display'));
        /*         * ******Using short_name for the facility and provider***** */
        $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        /*         * ******Using short_name for the facility and provider***** */

        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'patient.account_number'));


        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        //$select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        $select->join('assignedclaims', 'assignedclaims.encounter=encounter.id');
        $select->join('user', 'user.id=assignedclaims.assignor', 'user_name');
        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        $temp = $db->fetchAll($select);
        $test_temp = $this->get_user_id();
        $select->where('assignedclaims.assignee=?', $this->get_user_id());
        /*         * *New insurance change** */
        $select->where('encounterinsured.type = ?', 'primary');
        /*         * *New insurance change** */

        $assignedclaims = $db->fetchAll($select);
        session_start();
        unset($_SESSION['tmp']);

        $this->view->claimstatusList = $_SESSION['claimstatusList'];

        if ($assignedclaims != null) {
            $_SESSION['tmp']['assignedclaims_data'] = $assignedclaims;
            $this->_redirect('/biller/claims/assignedclaims');
        } else {
            $this->_redirect('/biller/claims/assignedclaims');
            //    $this->_redirect('/biller/claims/actionlist');
        }
    }

    /**
     * assigned work action
     * a function to view 
     */
    public function assignedclaimsAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $assignedclaims = $_SESSION['tmp']['assignedclaims_data'];

            /*             * **************************test for patient_data************************ */
            //$_SESSION['patient_data'] = $tmp_patient_data;
//            $list_size = sizeof($patient);
//            for($i = 0; $i < $list_size; $i++)
//                $assignedclaims[$i]['inquiry_last_name'] = $inquiry_last_name;  
            /*             * **************************test for patient_data************************ */


//            $assignedclaimsList = getpatientList($assignedclaims);
//            for($i = 0;$i<sizeof($assignedclaims);$i++){
//                $assignedclaimsList[$i]['user_name'] = $assignedclaims['$i']['user_name'];
//            }        
            $count_len = sizeof($assignedclaims);
            for ($i = 0; $i < $count_len; $i++) {
                $assignedclaims[$i]['name'] = $assignedclaims[$i]['last_name'] . ', ' . $assignedclaims[$i]['first_name'];
                $assignedclaims[$i]['DOB'] = format($assignedclaims[$i]['DOB'], 1);
                $assignedclaims[$i]['start_date_1'] = format($assignedclaims[$i]['start_date_1'], 1);
                if ($assignedclaims[$i]['referringprovider_last_name'] !== null && $assignedclaims[$i]['referringprovider_last_name'] !== '')
                    $assignedclaims[$i]['referringprovider_name'] = $assignedclaims[$i]['referringprovider_last_name'] . ', ' . $assignedclaims[$i]['referringprovider_first_name'];
                else
                    $assignedclaims[$i]['referringprovider_name'] = '';
                $assignedclaims[$i]['renderingprovider_name'] = $assignedclaims[$i]['renderingprovider_last_name'] . ', ' . $assignedclaims[$i]['renderingprovider_first_name'];
                $total_charge = $assignedclaims[$i]['total_charge'];
                $amount_paid = $assignedclaims[$i]['amount_paid'];
                $per = $amount_paid / $total_charge;
                $per = round($per, 2) * 100;
                if ($per == 0)
                    $assignedclaims[$i]['percentage'] = "";
                else
                    $assignedclaims[$i]['percentage'] = $per;
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('interactionlog');
                $select->where('interactionlog.claim_id=?', $assignedclaims[$i]['claim_id']);
                $select->order('interactionlog.date_and_time DESC');
                $tmp_interactionlogs = $db->fetchAll($select);
                $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                $start_date = date("Y-m-d");
                $noactiondays = 99;
                if ($end_date != null && $end_date != "") {
                    $noactiondays = days($start_date, $end_date);
                } else {
                    $temp_days = 99;
                    if ($assignedclaims[$i]['date_last_billed'] != null && $assignedclaims[$i]['date_billed'] != null && $assignedclaims[$i]['date_rebilled'] != null) {
                        $temp_end_date = max($assignedclaims[$i]['date_last_billed'], $assignedclaims[$i]['date_billed'], $assignedclaims[$i]['date_rebilled']);
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] != null && $assignedclaims[$i]['date_billed'] == null && $assignedclaims[$i]['date_rebilled'] != null) {
                        $temp_end_date = max($assignedclaims[$i]['date_last_billed'], $assignedclaims[$i]['date_rebilled']);
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] == null && $assignedclaims[$i]['date_billed'] != null && $assignedclaims[$i]['date_rebilled'] != null) {
                        $temp_end_date = max($assignedclaims[$i]['date_billed'], $assignedclaims[$i]['date_rebilled']);
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] != null && $assignedclaims[$i]['date_billed'] != null && $assignedclaims[$i]['date_rebilled'] == null) {
                        $temp_end_date = max($assignedclaims[$i]['date_billed'], $assignedclaims[$i]['date_last_billed']);
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] == null && $assignedclaims[$i]['date_billed'] == null && $assignedclaims[$i]['date_rebilled'] != null) {
                        $temp_end_date = $assignedclaims[$i]['date_rebilled'];
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] != null && $assignedclaims[$i]['date_billed'] == null && $assignedclaims[$i]['date_rebilled'] == null) {
                        $temp_end_date = $assignedclaims[$i]['date_last_billed'];
                        $temp_days = days($start_date, $temp_end_date);
                    } else if ($assignedclaims[$i]['date_last_billed'] == null && $assignedclaims[$i]['date_billed'] != null && $assignedclaims[$i]['date_rebilled'] == null) {
                        $temp_end_date = $assignedclaims[$i]['date_billed'];
                        $temp_days = days($start_date, $temp_end_date);
                    }
                    $noactiondays = $temp_days;
                }
                if ($noactiondays <= 99)
                    $assignedclaims[$i]['last'] = $noactiondays;
                else
                    $assignedclaims[$i]['last'] = 99;
            }
            $_SESSION['tmp']['assignedclaims_data'] = $assignedclaims;
            /*             * **************Add claimstatus for ***************** */
            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }

            /*             * **************Add claimstatus for ***************** */


            /*             * *********************translate the claim_status**************** */

            foreach ($claimstatuslist as $row) {
                $translate_claim_status[$row['claim_status']] = $row['claim_status_display'];
            }
            for ($i = 0; $i < count($assignedclaims); $i++) {
                $tmp_claim_status = $assignedclaims[$i]['claim_status'];
                $assignedclaims[$i]['claim_status'] = $translate_claim_status[$tmp_claim_status];
            }

            // dump_all($assignedclaims);
            //$this->view->assignedclaimsList = $assignedclaims;
            //return;
            /*             * *********************translate the claim_status**************** */
            $this->view->assignedclaimsList = $assignedclaims;
        }

        if ($this->getRequest()->isPost()) {
            $postType = $this->getRequest()->getParam('post');
            session_start();
            $assignedclaimsList = $_SESSION['tmp']['assignedclaims_data'];
            foreach ($assignedclaimsList as $key => $row) {

                $frombiller[$key] = $row['user_name'];
                $mypatient[$key] = $row['patient_last_name'] . $row['patient_first_name'];

                //Add reange of the added fields

                $dob[$key] = $row['patient_DOB'];
                $totalcharge[$key] = $row['total_charge'];
                $amtpaid[$key] = $row['amount_paid'];
                $percentage[$key] = $row['percentage'];
                $last[$key] = $row['last'];
                $due[$key] = $row['balance_due'];
                $referringprovider[$key] = $row['referringprovider_last_name'] . $row['referringprovider_first_name'];

                //Add reange of the added fields

                $insurance[$key] = $row['insurance_display'];
                $provider[$key] = $row['provider_name'];
                $facility[$key] = $row['facility_name'];
                $dos[$key] = format($row['start_date_1'], 0);
                $mrn[$key] = $row['account_number'];
                $status[$key] = $row['claim_status_display'];
                $bill_status_diplay[$key] = $row['bill_status_display'];
                $statement_status_display[$key] = $row['statement_status_display'];
                $renderingprovider[$key] = $row['renderingprovider_last_name'] . $row['renderingprovider_first_name'];
            }

            //Add reange of the added fields
            if ($postType == "DOB") {
                if ($dob[0] >= $dob[sizeof($dob) - 1]) {

                    array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Charge") {
                if ($totalcharge[0] <= $totalcharge[sizeof($totalcharge) - 1]) {

                    array_multisort($totalcharge, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($totalcharge, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Paid") {
                if ($amtpaid[0] >= $amtpaid[sizeof($amtpaid) - 1]) {

                    array_multisort($amtpaid, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($amtpaid, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "%") {
                if ($percentage[0] <= $percentage[sizeof($percentage) - 1]) {

                    array_multisort($percentage, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($percentage, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Last") {
                if ($last[0] <= $last[sizeof($last) - 1]) {

                    array_multisort($last, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($last, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Due") {
                if ($due[0] <= $due[sizeof($due) - 1]) {

                    array_multisort($due, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($due, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Referring Provider") {
                if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1]) {

                    array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            //Add reange of the added fields
            if ($postType == "From") {
                $frombiller_slowercase = array_map('strtolower', $frombiller);
                if ($frombiller_slowercase[0] >= $frombiller_slowercase[sizeof($frombiller_slowercase) - 1]) {

                    array_multisort($frombiller_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($frombiller_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                }

                //array_multisort($mypatient, SORT_ASC, $assignedclaimsList);
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }

            if ($postType == "Name") {

                /*                 * *****************sort the insurance****************** */
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($mypatient_slowercase[0] >= $mypatient_slowercase[sizeof($mypatient_slowercase) - 1]) {

                    array_multisort($mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($mypatient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                }

                //array_multisort($mypatient, SORT_ASC, $assignedclaimsList);
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Facility") {
                if ($facility[0] >= $facility[sizeof($facility) - 1]) {

                    array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "DOS") {
                if ($dos[0] >= $dos[sizeof($dos) - 1]) {

                    array_multisort($dos, SORT_ASC, $assignedclaimsList);
                } else {

                    array_multisort($dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "MRN") {
                if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {

                    array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Claim Status") {
                if ($status[0] >= $status[sizeof($status) - 1]) {

                    array_multisort($status, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($status, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }

                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Bill Status") {
                if ($bill_status_diplay[0] >= $bill_status_diplay[sizeof($bill_status_diplay) - 1]) {

                    array_multisort($bill_status_diplay, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($bill_status_diplay, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }

                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Statement Status") {
                if ($statement_status_display[0] >= $statement_status_display[sizeof($statement_status_display) - 1]) {

                    array_multisort($statement_status_display, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($statement_status_display, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }

                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Insurance") {

                /*                 * *****************sort the insurance****************** */
                $insurance_slowercase = array_map('strtolower', $insurance);
                if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $assignedclaimsList);
                }


                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($postType == "Provider") {
                if ($provider[0] >= $provider[sizeof($provider) - 1]) {
                    array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {
                    array_multisort($provider, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }

            if ($postType == "Rendering Provider") {
                if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {

                    array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $assignedclaimsList);
                } else {

                    array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $assignedclaimsList);
                }
                $_SESSION['tmp']['assignedclaims_data'] = $assignedclaimsList;
                $this->_redirect('/biller/claims/assignedclaims');
            }
            $encounter_id = $this->getRequest()->getPost('encounter_id');
            if ($encounter_id != null) {
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                $where = $db->quoteInto('id = ?', $encounter_id);
                $encounter_data = $db_encounter->fetchRow($where);
                $patient_id = $encounter_data['patient_id'];
                $this->initsession($patient_id, $encounter_id);

                $this->set_options_session($encounter_data['provider_id'], '', '');
            }
            $this->_redirect('/biller/claims/claim');
        }


//        $this->_redirect('/biller/claims/assignedclaims');
    }

    /**
     * cptcodeAction
     * a function taking CPT code from the view and inquiry the crosswalk code from the insurance's
     * anesthesia_crosswalk_overwrite first, if not exist, inquiry the crosswalk code from the database
     * it will return the description, base unit and crosswalk code.
     * @author Haowei
     * @version 05/15/2012
     * @param $CPT_code a string get from view.
     * @param $insurance an array of insurance data.
     * @return An array inlcluding description, base unit, and crosswalk code.
     */
    public function cptcodeAction() {
        $this->_helper->viewRenderer->setNoRender();
        $CPT_code = $_POST['CPT_code'];
        //zw
        $A_code = $_POST['A_code'];

        $provider_id = $_POST['provider_id'];
        Zend_Session::start();
        $insurance = $_SESSION['insurance_data'];
        $tmp = $insurance['anesthesia_crosswalk_overwrite'];
        $anesthesia_crosswalk_overwrite = explode(",", $tmp);
        $acount = count($anesthesia_crosswalk_overwrite);
        $crosswalk_code = null;
        $base_unit = null;
        for ($i = 0; $i < $acount; $i++) {
            $code = explode("|", $anesthesia_crosswalk_overwrite[$i]);
            if ($CPT_code == $code[0]) {
                $crosswalk_code = $code[1];
                $base_unit = $code[2];
                $description = '';
                break;
            }
        }
        if ($crosswalk_code == null) {
            $db_cptcode = new Application_Model_DbTable_Cptcode();
            $db = $db_cptcode->getAdapter();
            $where = $db->quoteInto('CPT_code = ?', $CPT_code) . $db->quoteInto('AND provider_id=?', $provider_id);
            $cptcode_data = $db_cptcode->fetchRow($where);
            //zw
            $description = $cptcode_data['description'];
            $charge_amount = $cptcode_data['charge_amount'];
            $payment_expected = $cptcode_data['payment_expected'];
            $anesthesiacode_id = $cptcode_data['anesthesiacode_id'];
            if ($A_code !== null && $A_code !== '') {
                $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
                $db = $db_crosswalk->getAdapter();
//            $search = 'surgery_code ='.$CPT_code.'AND'.' provider_id='.$provider_id;
                $where = $db->quoteInto('id = ?', $anesthesiacode_id) . $db->quoteInto('AND provider_id=?', $provider_id);
                $crosswalk_data = $db_crosswalk->fetchRow($where);
                $crosswalk_code = $crosswalk_data['anesthesia_code'];
                $base_unit = $crosswalk_data['base_unit'];

                if ($crosswalk_code == null) {
                    $default_modifier_1 = $cptcode_data['default_modifier_1'];
                    $default_modifier_2 = $cptcode_data['default_modifier_2'];
                } else {
                    $default_modifier_1 = $crosswalk_data['default_modifier_1'];
                    $default_modifier_2 = $crosswalk_data['default_modifier_2'];
                }
            } else { //zw
                $default_modifier_1 = $cptcode_data['default_modifier_1'];
                $default_modifier_2 = $cptcode_data['default_modifier_2'];
            }
        }
        /* if($A_code == null || $A_code == ''){
          $default_modifier_1 = $cptcode_data['default_modifier_1'];
          $default_modifier_2 = $cptcode_data['default_modifier_2'];
          } */
        $data = array();
        $data = array('description' => $description, 'anesthesia_code' => $crosswalk_code, 'base_unit' => $base_unit, 'charge_amount' => $charge_amount,
            'payment_expected' => $payment_expected, 'modifier' => array($default_modifier_1, $default_modifier_2));
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * crosswalkcodeAction
     * a function taking crosswalk code from the view and inquiry the unit from database
     * this is needed if the biller change the crosswalk code manually.
     * @author Haowei
     * @version 05/15/2012
     * @param $crosswalkcode a string get from view.
     * @return An array inlcluding base unit.
     */
    public function crosswalkcodeAction() {
        $this->_helper->viewRenderer->setNoRender();
        $crosswalkcode = $_POST['crosswalkcode'];
        $provider_id = $_POST['provider_id'];
        $cpt = $_POST['cpt_code'];
        $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_crosswalk->getAdapter();
        //zw
        //if ($cpt != null) {
        $where = $db->quoteInto('anesthesia_code = ?', $crosswalkcode) . $db->quoteInto('AND provider_id=?', $provider_id);
        //} else {
        //    $where = $db->quoteInto('anesthesia_code = ?', $crosswalkcode) . $db->quoteInto('AND provider_id=?', $provider_id) . $db->quoteInto('AND surgery_code=?', '');
        //}
        $crosswalk_data = $db_crosswalk->fetchRow($where);
        $default_modifier_1 = $crosswalk_data['default_modifier_1'];
        $default_modifier_2 = $crosswalk_data['default_modifier_2'];
        If ($_SESSION['options']['anesthesia_unit_rounding'] == 'always round up')
            $roundup = 1;
        else
            $roundup = 0;
        $data = array();
        //$data = array('base_unit' => $crosswalk_data['base_unit'], 'ifcptcodeexist' => $crosswalk_data['surgery_code'], 'roundup' =>$roundup);
        $data = array('base_unit' => $crosswalk_data['base_unit'], 'roundup' => $roundup, 'modifier' => array($default_modifier_1, $default_modifier_2));
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * billrateAction
     * a function that get the anesthesia billing rate
     * @author Haowei
     * @version 05/15/2012
     * @return An array inlcluding the antsthesia billing rate.
     */
    public function billrateAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $renderingprovider_id = $_POST['renderingprovider_id'];
        //zw
        $units = $_POST['units'];
        $cp_insurance = $_POST['cp_insurance'];
        //

        session_start();

        //Fix the bug
        $encounterinsured_data = $_SESSION['encounterinsured_data'];
        $insured_data = $_SESSION['insured_data'];
        $insurance_data = $_SESSION['insurance_data'];

        if ($insurance_data == null) {
            //zw
            //if(count($encounterinsured_data) == 0)
            //{
            foreach ($insured_data as $row) {
                if ($row['id'] == $cp_insurance) {
                    $insurance_id = $row['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $insurance_id);
                    $insurance_data = $db_insurance->fetchRow($where);
                }
            }
            //} else {
            //    foreach($encounterinsured_data as $row) {
            //        if($row['type']== 'primary') {
            //            $tmp_insured_id = $row['insured_id'];
            //            $db_insured = new Application_Model_DbTable_Insured();
            //            $db = $db_insured->getAdapter();
            //            $where = $db->quoteInto('id=?', $tmp_insured_id);
            //            $tmp_insured = $db_insured->fetchRow($where);
            //            $tmp_insurance_id = $tmp_insured['insurance_id'];
            //            $db_insurance = new Application_Model_DbTable_Insurance();
            //            $db = $db_insurance->getAdapter();
            //            $where = $db->quoteInto('id=?', $tmp_insurance_id);
            //            $insurance_data = $db_insurance->fetchRow($where);
            //        }                 
            //    }
            //}            
        } //else
        $insurance_id = $insurance_data['id'];

        $dd = 0;
        //Old version


        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id=?', $provider_id);
        $provider = $db_provider->fetchRow($where);

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('id=?', $provider['options_id']);
        $options = $db_options->fetchRow($where);

        $db_innetworkpayers = new Application_Model_DbTable_Innetworkpayers();
        $db = $db_innetworkpayers->getAdapter();
        $search = 'insurance_id=' . $insurance_id . ' AND ' . 'renderingprovider_id=' . $renderingprovider_id;
        $where = $db->quoteInto($search);
        $innetworkpayers = $db_innetworkpayers->fetchAll($where);
        $data = array();

        $db_contractrates = new Application_Model_DbTable_Contractrates();
        $db = $db_contractrates->getAdapter();
        $search = 'insurance_id=' . $insurance_id . ' And ' . 'provider_id=' . $provider_id;
        $where = $db->quoteInto($search);
        $contractrates = $db_contractrates->fetchRow($where);

        //zw
        //if ($insurance_data['payer_type'] == 'MM') {
        if ($innetworkpayers->count() > 0) {
            //$data['anesthesia_billing_rate'] = $options['anesthesia_billing_rate_for_par'];
            //$data['contractrates'] = $contractrates['rates'];
            $data['anesthesia_charge'] = $options['anesthesia_billing_rate_for_par'] * $units;
        } else {
            //$data['non_par_expected_pay'] = $options['non_par_expected_pay'];
            //$data['anesthesia_billing_rate'] = $options['anesthesia_billing_rate_for_non_par'];
            $data['anesthesia_charge'] = $options['anesthesia_billing_rate_for_non_par'] * $units;
        }
        $data['anesthesia_expected_payment'] = $contractrates['rates'] * $units;
        //} else {
        //    $data['anesthesia_billing_rate'] = $options['anesthesia_billing_rate_for_non_par'];
        //    $data['PIP_rate'] = $options['PIP_rate'];
        //}
        //If ($_SESSION['options']['anesthesia_unit_rounding'] == 'always round up')
        //    $roundup = 1;
        //else
        //    $roundup = 0;
        //$data['roundup'] = $roundup;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /*     * *
     * modifierunitAction
     * a function that find the units of the modifiers
     */

    public function modifierunitAction() {
        $this->_helper->viewRenderer->setNoRender();
        $modifiers = $_POST['modifiers'];
        $modifiers = str_replace(' ', '|', $modifiers);
        $units = $this->get_modifier_units($modifiers);
        /*
          $temparray = explode("|", $modifiers);
          $modifierstoquery = "(";
          for($i = 0; $i < sizeof($temparray); $i++)
          $modifierstoquery = $modifierstoquery . "'" . $temparray[$i] . "', ";
          $modifierstoquery = substr($modifierstoquery, 0, strlen($modifierstoquery)-2) . ")";

          $db_innetworkpayers = new Application_Model_DbTable_Modifier();
          $db = $db_innetworkpayers->getAdapter();
          $search = 'modifier IN ' . $modifierstoquery;
          $where = $db->quoteInto($search);
          $unitsArray = $db_innetworkpayers->fetchAll($where)->toArray();
          $units = 0;
          for($i = 0; $i < sizeof($unitsArray); $i++)
          $units = $units + $unitsArray[$i]['unit'];
         */
        $data = array();
        $data['modifier_units'] = $units;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * innetworkAction
     * a function telling the biller if the ralationship betweent insuance and rendering provider is innetwork
     * @author Haowei
     * @version 05/15/2012
     * @return An integer indicating if the ralationship is innetwork.
     */
    public function innetworkAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $insurance_data = $_SESSION['insurance_data'];
        $encouter_data = $_SESSION['encounter_data'];


        //Fix the bug
        /*
          $insured_data  = $_SESSION['insured_data'];

          foreach($insured_data as $row)
          {
          if($row['insured_insurance_type'] == 'primary')
          {
          $insurance_id = $row['insurance_id'];
          }
          }
         */

        // Try to fix bug
        $insurance_id = $insurance_data['id'];

        $provider_id = $encouter_data['provider_id'];
        $renderingprovider_id = $encouter_data['renderingprovider_id'];

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id=?', $provider_id);
        $provider = $db_provider->fetchRow($where);

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('id=?', $provider['options_id']);
        $options = $db_options->fetchRow($where);

        $db_innetworkpayers = new Application_Model_DbTable_Innetworkpayers();
        $db = $db_innetworkpayers->getAdapter();
        $search = 'insurance_id=' . $insurance_id . ' And ' . 'renderingprovider_id=' . $renderingprovider_id;
        $where = $db->quoteInto($search);
        $innetworkpayers = $db_innetworkpayers->fetchAll($where);
        $data = array();
        if ($insurance_data['payer_type'] == 'MM') {
            if ($innetworkpayers->count() > 0) {
                $data['innetwork'] = '1';
            } else {
                $data['innetwork'] = '0';
            }
        } else
            $data['innetwork'] = '0';
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function changeicdAction() {
        session_start();
        $this->_helper->viewRenderer->setNoRender();
        $time = $this->_request->getPost('input_time');
        $provider_id = $this->_request->getPost('providerid');
//        $arr = explode('/', $time);
//        $time = join("-", array_reverse($arr));
//        $changeToNull = $this->_request->getPost('changeToNull');
//        $flag = $this->_request->getPost('flag');

        /*         * **********************选择2015-10-1之后，强制刷新前diagnosiscode******************************** */
//        if ($_SESSION['invalid_services_data']['services']['invalid_flag'] == 1)
//            $encounter_data = $_SESSION['invalid_services_data']['encounter_data'];
//        else
//            $encounter_data = $_SESSION['encounter_data'];
//        $provider_id = $encounter_data['provider_id'];
        $dbdiag = Zend_Registry::get('dbAdapter');

        $sqlold = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode join diagnosiscode
on providerhasdiagnosiscode.diagnosiscode_id = diagnosiscode.id
where provider_id = ?
order by diagnosis_code
SQL;

        $sqlnew = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode_10 join diagnosiscode_10 
on providerhasdiagnosiscode_10.diagnosiscode_10_id = diagnosiscode_10.id
where provider_id = ?
order by diagnosis_code
SQL;


        if ($time == 'false') {
            $sql = $sqlold;
        } else {
            $sql = $sqlnew;
        }


        $paras = array($provider_id);
        $result = $dbdiag->query($sql, $paras);
        //获取所有行
        $diagnosiscode_data = $result->fetchAll();
        $this->movetobot($diagnosiscode_data, 'diagnosis_code', 'Need New');

        $diagnosiscodeList = array();
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['diagnosis_code'] . " " . $row['description'];
            $idx++;
        }
        $data['diagnosiscodeList'] = $diagnosiscodeList;


        $data['flag'] = $flag;
        $data['changeToNull'] = $changeToNull;

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * servicesAction
     * a function taking CPT code from the view and inquiry the crosswalk code from the insurance's
     * anesthesia_crosswalk_overwrite first, if not exist, inquiry the crosswalk code from the database
     * it will return the description, base unit and crosswalk code.
     * @author Haowei
     * @version 05/15/2012
     * @param $CPT_code a string get from view.
     * @param $insurance an array of insurance data.
     * @return An array inlcluding description, base unit, and crosswalk code.
     */
    public function servicesAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $options = $_SESSION['options'];
            $insurance = $_SESSION['insurance_data'];
            $new_claim_flag = $_SESSION['new_claim_flag'];

            if (!$_SESSION['first_severe_flag'])
                $_SESSION['first_severe_flag'] = 1;
            $this->view->new_claim_flag = $new_claim_flag;
            //   $this->view->cur_service_info = $this->get_cur_service_info('encounter');
            $cur_service_info = $this->get_cur_service_info('encounter');
            $this->view->cur_service_info = $cur_service_info;
            $this->view->nullcheck = $this->getRequest()->getParam('nullcheck');
            $insurance_id = $cur_service_info['insurance_id'];
            $insurance_display = $cur_service_info['insurance_display'];
            if ('Self Pay' == $insurance_display) {
                $ctaiList = array();
            } else {
                $issue = array();
                $second_order = array();
                $ctaiList = $this->getTAIList($insurance_id, $issue, $second_order);
                array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $ctaiList);
            }
            $this->view->taiList = $ctaiList;

//session_start();
            if ($_SESSION['invalid_services_data']['services']['invalid_flag'] == 1)
                $encounter_data = $_SESSION['invalid_services_data']['encounter_data'];
            else
                $encounter_data = $_SESSION['encounter_data'];

            $provider_id = $encounter_data['provider_id'];

            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id());
            $provider = $db_provider->fetchAll($where, 'provider_name ASC');
//get renderingprovider list
            if ($provider_id != null && $provider_id != '') {
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
                $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
                $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
                $select->where('provider.id = ?', $provider_id);
                $select->group('renderingprovider.id');
                $select->order('last_name ASC');
                $renderingproviderList = $db->fetchAll($select);
                $this->movetobot($renderingproviderList, 'last_name', 'Need New');
                $this->view->renderingproviderList = $renderingproviderList;


                /* cici */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
                $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
                $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');
                $select->where('provider.id = ?', $provider_id);
                $select->group('diagnosiscode_10.diagnosis_code');
                $select->order('diagnosis_code ASC');
                $diagnosis_codeList = $db->fetchAll($select);
                $this->view->diagnosis_codeList = $diagnosis_codeList;

                $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
                $select = $db->select();
                $select->from('facility', array('facility.id as fid', 'facility_display'));
                $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
                $select->join('provider', 'providerhasfacility.provider_id = provider.id');
                $select->where('provider.id = ?', $provider_id);
                $select->group('facility.id');
                $select->order('facility_display ASC');
                $facilityList = $db->fetchAll($select);

                $this->view->facilityList = $facilityList;
//referringprovider
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
                $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
                $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
                $select->where('provider.id = ?', $provider_id);
                $select->group('referringprovider.id');
                $select->order('last_name ASC');
                $referringproviderList = $db->fetchAll($select);
                $this->movetobot($referringproviderList, 'last_name', 'Need New');
                $this->view->referringproviderList = $referringproviderList;
            }

            /// zw <
            $insured_data = $_SESSION['insured_data'];
            $i = 0;

            foreach ($insured_data as $row) {
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db = $db_insurance->getAdapter();
                $where = $db->quoteInto('id = ?', $row['insurance_id']);
                $tmp_insurance_data = $db_insurance->fetchRow($where);
                $insured_data[$i]['insurance_name'] = $tmp_insurance_data['insurance_name'];
                $insuredinsuranceList[$i]['insured_id'] = $row['id'];
                $insuredinsuranceList[$i]['insurance_id'] = $row['insurance_id'];
                $insuredinsuranceList[$i]['ID_number'] = $row['ID_number'];
                $insuredinsuranceList[$i]['insured_insurance_type'] = $row['insured_insurance_type'];
                $insuredinsuranceList[$i]['last_name'] = $row['last_name'];
                $insuredinsuranceList[$i]['first_name'] = $row['first_name'];
                $insuredinsuranceList[$i]['insured_insurance_type'] = $row['insured_insurance_type'];
                $insuredinsuranceList[$i]['insurance_display'] = $tmp_insurance_data['insurance_display'];
                $i++;
            }
            $_SESSION['insured_data'] = $insured_data;
            $this->view->insuredinsuranceList = $insuredinsuranceList;
            /// > zw      

            /*          $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
              $db = $db_diagnosiscode->getAdapter();
              $where = $db->quoteInto('provider_id=?', $provider_id);
              $diagnosiscode_data = $db_diagnosiscode->fetchAll($where, 'diagnosis_code ASC');
              $this->view->diagnosiscodeList = $diagnosiscode_data; */

            $dbdiag = Zend_Registry::get('dbAdapter');
            $sqlold = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode join diagnosiscode
on providerhasdiagnosiscode.diagnosiscode_id = diagnosiscode.id
where provider_id = ?
order by diagnosis_code
SQL;

            $sqlnew = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode_10 join diagnosiscode_10 
on providerhasdiagnosiscode_10.diagnosiscode_10_id = diagnosiscode_10.id
where provider_id = ?
order by diagnosis_code
SQL;
            $dos = $encounter_data['start_date_1'];
//            if($encounter_id == null || $encounter_id == ""){
            if (compare_time($dos)) {
                $sql = $sqlnew;
            } else {
                $sql = $sqlold;
            }
//            }
//            else{
//                //从老的claim 里面取数据
//                if(!compare_time($dos)){
//                    $sql = $sqlnew;
//                }else{
//                    $sql = $sqlold;
//                }
//            }

            /*             * ******************************************************************************************** */
            /*
              $mysql = $_SESSION['sql'];
              if($mysql){
              $sql = $mysql;
              }else{
              if ($encounter_id == null || $encounter_id == "") {
              $sql = $sqlnew;
              $_SESSION['mytime'] = 1;
              } else {
              //$dos一共有6个，获取最新的$dos
              //                    $dos_default_string = 'start_date_';
              //                    $dos = null;
              //                    for ($i = 1; $i <= 6; $i++) {
              //                        $dos_string = $dos_default_string . $i;
              //                        $dos_new = $encounter_data[$dos_string];
              //                        if (!$dos_new)
              //                            break;
              //                        $dos = $dos_new;
              //                    }
              if ($dos == null) {
              $sql = $sqlnew;
              $_SESSION['mytime'] = 1;
              } else {
              if (compare_time($dos)) {
              $sql = $sqlnew;
              $_SESSION['mytime'] = 1;
              } else {
              $sql = $sqlold;
              $_SESSION['mytime'] = 0;
              }
              }
              }
              }


              $_SESSION['sql'] = $sql;
             */
            /*             * ******************************************************************************************** */

            $paras = array($provider_id);
            $result = $dbdiag->query($sql, $paras);
            //获取所有行
            $diagnosiscode_data = $result->fetchAll();
            $this->movetobot($diagnosiscode_data, 'diagnosis_code', 'Need New');
            $diagnosiscodeList = array();
            $idx = 0;
            foreach ($diagnosiscode_data as $row) {
                $diagnosiscodeList[$idx] = $row['diagnosis_code'] . " " . $row['description'];
                $idx++;
            }
            //将获取值赋值给providerlist
            $this->view->diagnosiscodeList = $diagnosiscodeList;


            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $where = $db->quoteInto('id = ?', $this->get_billingcompany_id()); //
            $billingcompany_data = $db_billingcompany->fetchRow($where);
//
//            $count = 0;
//            $index = 0;
//            foreach ($provider as $row) {
//                if ($row['provider_name'] != $billingcompany_data['default_provider']) {
//                    $count = $count + 1;
//                } else {
//                    $index = $count;
//                }
//            }
//            $tmp = array();
//            $tmp['id'] = $provider[$index]['id'];
//            $tmp['provider_name'] = $provider[$index]['provider_name'];
//
//            $provider[$index]['id'] = $provider[0]['id'];
//            $provider[$index]['provider_name'] = $provider[0]['provider_name'];
//
//            $provider[0]['id'] = $tmp['id'];
//            $provider[0]['provider_name'] = $tmp['provider_name'];
            $this->view->providerList = $provider;

//            $db = Zend_Registry::get('dbAdapter');
//            $select = $db->select();
//            $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
//            $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
//            $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
//            $select->group('renderingprovider.id');
//            $select->order('last_name ASC');
//            //$where = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id());
//            $select->where('billingcompany_id = ?', $this->get_billingcompany_id());
//            $renderingprovider = $db->fetchAll($select);
//$facility = $db_facility->fetchAll(null, 'facility_name ASC');
//            $count = 0;
//            $index = 0;
//            foreach ($renderingprovider as $row) {
//                if (($row['first_name'] . ' ' . $row['last_name']) != $options['default_rendering_provider']) {
//                    $count = $count + 1;
//                } else {
//                    $index = $count;
//                }
//            }
//            $tmp = array();
//            $tmp['rid'] = $renderingprovider[$index]['rid'];
//            $tmp['first_name'] = $renderingprovider[$index]['first_name'];
//            $tmp['last_name'] = $renderingprovider[$index]['last_name'];
//
//            $renderingprovider[$index]['rid'] = $renderingprovider[0]['rid'];
//            $renderingprovider[$index]['first_name'] = $renderingprovider[0]['first_name'];
//            $renderingprovider[$index]['last_name'] = $renderingprovider[0]['last_name'];
//
//            $renderingprovider[0]['rid'] = $tmp['rid'];
//            $renderingprovider[0]['first_name'] = $tmp['first_name'];
//            $renderingprovider[0]['last_name'] = $tmp['last_name'];
//            $this->view->renderingproviderList = $renderingprovider;
//        $db_facility = new Application_Model_DbTable_Facility();
//        $db = $db_facility->getAdapter();
//        $facility = $db_facility->fetchAll(null, 'facility_name ASC');
//            $db = Zend_Registry::get('dbAdapter');
//            $select = $db->select();
//            $select->from('facility', array('facility.id as fid', 'facility_name'));
//            $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
//            $select->join('provider', 'providerhasfacility.provider_id = provider.id');
//            $select->group('facility.id');
//            $select->order('facility_name ASC');
//            $select->where('billingcompany_id = ?', $this->get_billingcompany_id());
//            $facility = $db->fetchAll($select);
//
//            $count = 0;
//            $index = 0;
//            $options['default_facility'];
//            foreach ($facility as $row) {
//                if ($row['facility_name'] != $options['default_facility']) {
//                    $count = $count + 1;
//                } else {
//                    $index = $count;
//                }
//            }
//            $tmp = array();
//            $tmp['fid'] = $facility[$index]['fid'];
//            $tmp['facility_name'] = $facility[$index]['facility_name'];
//$tmp['description'] = $Modifier[$index]['description'];
//            $facility[$index]['fid'] = $facility[0]['fid'];
//            $facility[$index]['facility_name'] = $facility[0]['facility_name'];
//$Modifier[$index]['description'] = $Modifier[0]['description'];
//            $facility[0]['fid'] = $tmp['fid'];
//            $facility[0]['facility_name'] = $tmp['facility_name'];
//$Modifier[0]['description'] = $tmp['description'];
//            $facility;
//            $this->view->facilityList = $facility;
////
//            $db = Zend_Registry::get('dbAdapter');
//            $select = $db->select();
//            $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
//            $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
//            $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
//            $select->group('referringprovider.id');
//            $select->order('last_name ASC');
//            $select->where('billingcompany_id = ?', $this->get_billingcompany_id());
//            $referringprovider = $db->fetchAll($select);
//        $db_referringprovider = new Application_Model_DbTable_Referringprovider();
//        $db = $db_referringprovider->getAdapter();
//
//        $referringproviderList = $db_referringprovider->fetchAll('id>0', 'last_name ASC');
//            $this->view->referringproviderList = $referringprovider;
            if ($provider_id) {
                $db_cptcode = new Application_Model_DbTable_Cptcode();
                $db = $db_cptcode->getAdapter();
                $where = $db->quoteInto('provider_id=?', $provider_id);
                $CptcodeList = $db_cptcode->fetchAll($where, 'CPT_code ASC')->toArray();
                //$this->movetobot($CptcodeList, 'CPT_code','Need New');
                $cpt_a = array();
                $i = 0;
                foreach ($CptcodeList as $cpt_code) {
                    if ($cpt_code["anesthesiacode_id"] != null) {
                        $cpt_a[$i] = $cpt_code;
                        $i++;
                    }
                }
                foreach ($CptcodeList as $key => $cpt_code) {
                    if ($cpt_code["CPT_code"] == "Need New") {
                        $cpt_a[$i] = $cpt_code;
                        unset($CptcodeList[$key]);
                    }
                }
                $this->view->Cptcodeadd = $cpt_a;
                $this->view->CptcodeList = $CptcodeList;

                /*
                  $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
                  $db = $db_anesthesia->getAdapter();Anesthesiacode
                  $where = $db->quoteInto('provider_id=?', $provider_id); //. $where = $db->quoteInto('AND surgery_code=?', null);
                  $AnesthesiaList = $db_anesthesia->fetchAll($where, 'anesthesia_code ASC');
                 * 
                 */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('anesthesiacode');
                $select->where('provider_id = ?', $provider_id);
                $select->group('anesthesia_code'); //to obtain distinct anesthesia_code
                $select->order('anesthesia_code ASC');
                $AnesthesiaList = $db->fetchAll($select);
                $this->movetobot($AnesthesiaList, 'anesthesia_code', 'Need New');
                $this->view->AnesthesiaList = $AnesthesiaList;
            }
            $db_modifier = new Application_Model_DbTable_Modifier();
            $db = $db_modifier->getAdapter();
            $where = $db->quoteInto('billingcompany_id=?', $this->get_billingcompany_id());
            $ModifierList = $db_modifier->fetchAll($where, 'modifier ASC');
//            $count = 1;
////$index = 0;
//            $ModifierList[0]['modifier'] = $options['default_modifier'];
//            foreach ($Modifier as $row) {
//                if ($row['modifier'] != $options['default_modifier']) {
//                    $ModifierList[$count]['id'] = $row['id'];
//                    $ModifierList[$count]['modifier'] = $row['modifier'];
//                    $ModifierList[$count]['description'] = $row['description'];
//                    $count = $count + 1;
//                } else {
//                    $ModifierList[0]['id'] = $row['id'];
////$ModifierList[0]['modifier'] = $row['modifier'];
//                    $ModifierList[0]['description'] = $row['description'];
//                }
//            }
//            $ModifierList;
            $this->view->modifierList = $ModifierList;

            $db_placeofservice = new Application_Model_DbTable_Placeofservice();
            $db = $db_placeofservice->getAdapter();
            $Placeofservice = $db_placeofservice->fetchAll(null, 'pos ASC');
//            $count = 1;
//            //$index = 0;
//            $PlaceofserviceList[0]['pos'] = $options['default_place_of_service'];
//            foreach ($Placeofservice as $row) {
//                if ($row['pos'] != $options['default_place_of_service']) {
//                    $PlaceofserviceList[$count]['id'] = $row['id'];
//                    $PlaceofserviceList[$count]['pos'] = $row['pos'];
//                    $PlaceofserviceList[$count]['description'] = $row['description'];
//                    $count = $count + 1;
//                } else {
//                    $PlaceofserviceList[0]['id'] = $row['id'];
//                    $PlaceofserviceList[0]['description'] = $row['description'];
//                }
//            }
            $this->view->PlaceofserviceList = $Placeofservice;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $this->view->user_name = $user_name;
        }
        if ($this->getRequest()->isPost()) {
            session_start();
            $invalid_services_data = $_SESSION['invalid_services_data']['services']['invalid_flag'];
            if ($invalid_services_data != 1) {
                $claim = $_SESSION['claim_data'];
            } else {
                $claim = $_SESSION['invalid_services_data']['claim_data'];
            }
            $claim_id = $claim['id'];
            $claimlogs = $_SESSION['interactionlogs_data'];
            $count_claim_logs = count($claimlogs);
            $index_claim_logs = $count_claim_logs;
            $new_patient_flag = $_SESSION['new_patient_flag'];
            $count5 = intval($this->getRequest()->getParam('count5'));
            if ($count5 > 0) {
                for ($i = 0; $i < $count5; $i++) {
                    $v = $this->getRequest()->getPost('date_and_time_' . ($i + 1));
                    if ($v != null && $v != "") {
                        $claimlogs[$index_claim_logs]['claim_id'] = $claim_id;
//                        $insurancepayments[$j]['id'] = '';
                        foreach (get_ui2patientlogs() as $key => $val) {
                            $v = $this->getRequest()->getPost($val . ($i + 1));
                            $claimlogs[$index_claim_logs][$key] = $v;
                        }
                        $index_claim_logs++;
                    }
                }
            }
            array_splice($claimlogs, $index_claim_logs);
            $_SESSION['interactionlogs_data'] = $claimlogs;
            if ($invalid_services_data != 1)
                $encounter = $_SESSION['encounter_data'];
            else
                $encounter = $_SESSION['invalid_services_data']['encounter_data'];
            $oldencounter = $_SESSION['encounter_data'];
            $encounter_id = $encounter['id'];
// get post QXW 18.02.2012
            $checkfields = array('start_date_', 'end_date_', 'start_time_', 'end_time_', 'days_or_units_', 'charges_');



            $needvalidate_array = array();
            for ($e = 1; $e <= 6; $e++) {
                $needvalidate_array[$e] = false;
            }
            
            foreach (get_ui2encounter() as $key => $val) {
                    $v = $this->getRequest()->getPost($val);
                if ($v != null && $v != "") {
                    if (substr($key, 0, 10) == 'modifier1_') {
                        $v = str_replace(' ', '|', $v);
                    }
                    if (in_array(substr($key, 0, strlen($key) - 1), $checkfields)) {
                        $old_value = $oldencounter[$key];
                        if (strpos($key, "date"))
                            $v = format($v, 0);
                        if (strpos($key, "time")) {
                            $v = format($v, 3);
                            $old_value = format($old_value, 3);
                        }
                        if ($oldencounter[$key] == null || $v != $old_value) {
                            //$save_key = $key;
                            //$save_new_value = $v;
                            //$save_old_value = $old_value;

                            if (strpos($key, "1"))
                                $needvalidate_array[1] = true;
                            if (strpos($key, "2"))
                                $needvalidate_array[2] = true;
                            if (strpos($key, "3"))
                                $needvalidate_array[3] = true;
                            if (strpos($key, "4"))
                                $needvalidate_array[4] = true;
                            if (strpos($key, "5"))
                                $needvalidate_array[5] = true;
                            if (strpos($key, "6"))
                                $needvalidate_array[6] = true;
                        }
                    }
                    if (!strncmp($key, "diagnosis_code", 14)) {
                        $v_array = explode(" ", $v, 2);
                        $v = $v_array[0];
                    }
                }

// if ($v != '')
                $encounter[$key] = $v;
            }
            //zw
            /*
              for ($i = 2; $i <= 6; $i++) {
              $CPT_code = 'CPT_code_' . $i;
              $secondary_CPT = 'secondary_CPT_code_' . $i;

              if (($encounter[$CPT_code] == '' || $encounter[$CPT_code] == null) && ($encounter[$secondary_CPT] == '' || $encounter[$secondary_CPT] == null)) {

              if ($encounter['start_date_' . $i] != '')
              $encounter['start_date_' . $i] = '';
              if ($encounter['start_time_' . $i] != '')
              $encounter['start_time_' . $i] = '';
              if ($encounter['end_date_' . $i] != '')
              $encounter['end_date_' . $i] = '';
              if ($encounter['end_time_' . $i] != '')
              $encounter['end_time_' . $i] = '';
              if ($encounter['modifier1_' . $i] != '')
              $encounter['modifier1_' . $i] = '';
              //$tmp['modifier_unit_' . $i] = 'modifier_unit_' . $i;
              if ($encounter['days_or_units_' . $i] != '')
              $encounter['days_or_units_' . $i] = '';
              if ($encounter['place_of_service_' . $i] != '')
              $encounter['place_of_service_' . $i] = '';
              if ($encounter['charges_' . $i] != '')
              $encounter['charges_' . $i] = '';
              if ($encounter['expected_payment_' . $i] != '')
              $encounter['expected_payment_' . $i] = '';
              if ($encounter['diagnosis_pointer_' . $i] != '')
              $encounter['diagnosis_pointer_' . $i] = '';
              if ($encounter['EMG_' . $i] != '')
              $encounter['EMG_' . $i] = '';
              $needvalidate_array[$i] = false;
              }
              }
             */
            //
            //$selfpay = $this->getRequest()->getPost('self_pay');
            //if($selfpay[0] != null)
            //$encounter['selfpay'] = '1';

            $key = $encounter['outside_lab'];
            if ($key[0] != null)
                $encounter['outside_lab'] = '1';
            else
                $encounter['outside_lab'] = '0';

            $key2 = $encounter['accept_assignment'];
            if ($key2[0] != null)
                $encounter['accept_assignment'] = '1';
            else
                $encounter['accept_assignment'] = '0';
            session_start();

//$this->write_session('encounter', $encounter);
            //zw Added this check to prevent encounter data from getting lost when user switched UI too fast.
            //move the two lines below to the end after validation. Non-validated values should not be written to $encounter
            //if($encounter['provider_id'] != null && $encounter['provider_id'] !="")
            //    $_SESSION['encounter_data'] = $encounter;
            //2013-3-17
            //$insurance = $_SESSION['insurance_data'];
            //to jude the claim status 
            if ($invalid_services_data != 1) {
                $claim_data = $_SESSION['claim_data'];

                //Fix the bug 2013-03-17
                $encounterinsured = $_SESSION['encounterinsured_data'];

                $insurance_data = $_SESSION['insurance_data'];
            } else {
                $claim_data = $_SESSION['invalid_services_data']['claim_data'];

                //Fix the bug 2013-03-17
                $encounterinsured = $_SESSION['invalid_services_data']['encounterinsured_data'];
                // $insured_data = $_SESSION['invalid_services_data']['insured_data'];
                $insurance_data = $_SESSION['invalid_services_data']['insurance_data'];
            }
            $insured_data = $_SESSION['insured_data'];

            /// zw <
            /*             * **************Add encounterinsured data******************* */

            //this part blew were merged, james, 2015/06/21:start
//            $tp_primary_insurance = $this->getRequest()->getPost('primary_insurance');
//            $tp_secondary_insurance = $this->getRequest()->getPost('secondary_insurance');
//            $tp_tertiary_insurance = $this->getRequest()->getPost('tertiary_insurance');
//            $tp_other_insurance = $this->getRequest()->getPost('other_insurance');
            /** added to fix the validation bug:
              #29：If POS = 21, need Hospitalization start
              author:james
             */
//            $tp_place_of_service_1 = $this->getRequest()->getPost('place_of_service_1');
//            $tp_hospitalization_from_date = $this->getRequest()->getPost('hospitalization_from_date');
//            if($tp_place_of_service_1 == "21"){
//                if($tp_hospitalization_from_date == null){
//                    session_start();
//                    $_SESSION['invalid_services_data']['services']['invalid_flag'] = 1;
//                    $_SESSION['invalid_services_data']['encounter_data'] = $encounter;
//                    if ($tp_primary_insurance != null)
//                        $_SESSION['invalid_services_data']['encounterinsured_data'] = $encounterinsured;
//                    $ret = 'hospitalization_from_date';
//                    $this->_redirect('/biller/claims/services/nullcheck/' . $ret);
//                }
//            }
            //fix the insurance code validation bugs,james
//            if ($tp_secondary_insurance == $tp_primary_insurance && $tp_primary_insurance != null) {
//                session_start();
//                //$_SESSION['invalid_services_data']['claim_data'] = $_SESSION['claim_data'];
//                $_SESSION['invalid_services_data']['services']['invalid_flag'] = 1;
//               /// if ($encounter['provider_id'] != null && $encounter['provider_id'] != "")
//                $_SESSION['invalid_services_data']['encounter_data'] = $encounter;
//                if ($tp_primary_insurance != null)
//                $_SESSION['invalid_services_data']['encounterinsured_data'] = $encounterinsured;
//                $_SESSION['invalid_services_data']['insurance_data'] = $insurance_data;
//               /// $_SESSION['invalid_services_data']['claim_data']['total_charge'] = $total_charge;
//                $ret = 'secondary_insurance';
//                $this->_redirect('/biller/claims/services/nullcheck/' . $ret);
//            }
//            if (($tp_other_insurance == $tp_primary_insurance || $tp_other_insurance == $tp_secondary_insurance) && $tp_other_insurance != null) {
//                session_start();
//                //$_SESSION['invalid_services_data']['claim_data'] = $_SESSION['claim_data'];
//                $_SESSION['invalid_services_data']['services']['invalid_flag'] = 1;
//               // if ($encounter['provider_id'] != null && $encounter['provider_id'] != "")
//                $_SESSION['invalid_services_data']['encounter_data'] = $encounter;
//                //$_SESSION['invalid_services_data']['claim_data']['expected_payment'] = $expected_payment;
//                if ($tp_primary_insurance != null)
//                    $_SESSION['invalid_services_data']['encounterinsured_data'] = $encounterinsured;
//                $_SESSION['invalid_services_data']['insurance_data'] = $insurance_data;
//                //$_SESSION['invalid_services_data']['claim_data']['total_charge'] = $total_charge;
//                $ret = 'other_insurance';
//                $this->_redirect('/biller/claims/services/nullcheck/' . $ret);
//            }
            //this part above merged, james, 2015/06/21:end

            $tp_primary_insurance = $this->getRequest()->getPost('primary_insurance');
            $tp_secondary_insurance = $this->getRequest()->getPost('secondary_insurance');
            $tp_other_insurance = $this->getRequest()->getPost('other_insurance');

            $other_insurance = null;
            $primary_insurance = null;
            $secondary_insurance = null;
            if ($tp_primary_insurance != null && $tp_primary_insurance != "") {
                //if(substr($tp_primary_insurance, 0, 4) == 'temp')
                //    $primary_insurance = "primary";
                //else
                $primary_insurance = $tp_primary_insurance;
            }
            if ($tp_secondary_insurance != null && $tp_secondary_insurance != "") {
                //if(substr($tp_secondary_insurance, 0, 4) == 'temp')
                //    $secondary_insurance = "secondary";
                //else
                $secondary_insurance = $tp_secondary_insurance;
            }
//        if($tp_tertiary_insurance != null && $tp_tertiary_insurance != "") {
//            //if(substr($tp_tertiary_insurance, 0, 4) == 'temp')
//            //    $tertiary_insurance = "tertiary";
//            //else
//                $tertiary_insurance = $tp_tertiary_insurance;              
//        }
            if ($tp_other_insurance != null && $tp_other_insurance != "") {
                //if(substr($tp_other_insurance, 0, 4) == 'temp')
                //    $other_insurance = "other";
                //else
                $other_insurance = $tp_other_insurance;
            }

            $primary_flag = 0;
            $secondary_flag = 0;
            $tertiary_flag = 0;
            $other_flag = 0;

            $index = 0;
            for ($i = 0; $i < count($encounterinsured); $i++) {
                if ($encounterinsured[$i]['type'] == 'primary') {
                    $primary_flag = 1;
                    if ($primary_insurance != null && $encounterinsured[$i]['insured_id'] != $primary_insurance) {
                        if ($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "") {
                            if ($encounterinsured[$i]['change_flag'] == 2)
                                $encounterinsured[$i]['insured_id'] = $primary_insurance;
                        } else if ($encounterinsured[$i]['change_flag'] == 0 || $encounterinsured[$i]['change_flag'] == 1) {
                            $encounterinsured[$i]['insured_id'] = $primary_insurance;
                            $encounterinsured[$i]['change_flag'] = 1;
                        }
                    }
                } else if ($encounterinsured[$i]['type'] == 'secondary') {
                    $secondary_flag = 1;
                    if ($secondary_insurance == null || $secondary_insurance == "") {
                        if ($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
                            $encounterinsured[$i]['change_flag'] = 4;
                        else
                            $encounterinsured[$i]['change_flag'] = 3;
                    } else if ($encounterinsured[$i]['insured_id'] != $secondary_insurance) {
                        if ($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
                            $encounterinsured[$i]['insured_id'] = $secondary_insurance; ///change_flag must be 2 here
                        else {
                            $encounterinsured[$i]['insured_id'] = $secondary_insurance;
                            $encounterinsured[$i]['change_flag'] = 1;
                        }
                    }
                }
//            else if($encounterinsured[$i]['type'] ==  'tertiary') {
//                $tertiary_flag = 1;
//                if($tertiary_insurance == null || $tertiary_insurance == "") {
//                    if($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
//                        $encounterinsured[$i]['change_flag'] = 4;
//                    else
//                        $encounterinsured[$i]['change_flag'] =  3;
//                } else if($encounterinsured[$i]['insured_id'] != $tertiary_insurance) {
//                    if($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
//                        $encounterinsured[$i]['insured_id'] = $tertiary_insurance; ///change_flag must be 2 here
//                    else {
//                        $encounterinsured[$i]['insured_id'] = $tertiary_insurance;
//                        $encounterinsured[$i]['change_flag'] =  1;
//                    }
//                }                
//            }
                else if ($encounterinsured[$i]['type'] == 'other') {
                    $other_flag = 1;
                    if ($other_insurance == null || $other_insurance == "") {
                        if ($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
                            $encounterinsured[$i]['change_flag'] = 4;
                        else
                            $encounterinsured[$i]['change_flag'] = 3;
                    } else if ($encounterinsured[$i]['insured_id'] != $other_insurance) {
                        if ($encounterinsured[$i]['id'] == null || $encounterinsured[$i]['id'] == "")
                            $encounterinsured[$i]['insured_id'] = $other_insurance;
                        else {
                            $encounterinsured[$i]['insured_id'] = $other_insurance;
                            $encounterinsured[$i]['change_flag'] = 1;
                        }
                    }
                }
                $index++;
            }

            if ($primary_flag == 0 && $primary_insurance != null && $primary_insurance != "") {
                $encounterinsured[$index]['insured_id'] = $primary_insurance;
                $encounterinsured[$index]['encounter_id'] = $encounter_id;
                $encounterinsured[$index]['type'] = 'primary';
                $encounterinsured[$index]['change_flag'] = 2;
                $index++;
            }
            if ($secondary_flag == 0 && $secondary_insurance != null && $secondary_insurance != "") {
                $encounterinsured[$index]['insured_id'] = $secondary_insurance;
                $encounterinsured[$index]['encounter_id'] = $encounter_id;
                $encounterinsured[$index]['type'] = 'secondary';
                $encounterinsured[$index]['change_flag'] = 2;
                $index++;
            }
//        if($tertiary_flag == 0 && $tertiary_insurance != null && $tertiary_insurance != "") {
//            $encounterinsured[$index]['insured_id'] = $tertiary_insurance;
//            $encounterinsured[$index]['encounter_id'] = $encounter_id;
//            $encounterinsured[$index]['type'] = 'tertiary';
//            $encounterinsured[$index]['change_flag'] = 2;
//            $index++;
//        }
            if ($other_flag == 0 && $other_insurance != null && $other_insurance != "") {
                $encounterinsured[$index]['insured_id'] = $other_insurance;
                $encounterinsured[$index]['encounter_id'] = $encounter_id;
                $encounterinsured[$index]['type'] = 'other';
                $encounterinsured[$index]['change_flag'] = 2;
                $index++;
            }
//        if ($primary_insurance != null)    
//            $_SESSION['encounterinsured_data'] = $encounterinsured;
            /*             * **************Add encounterinsured data******************* */

            //Add change for the insurance change information
            //foreach($encounterinsured as $erow) {
            //    if($erow['type'] ==  'primary')
            foreach ($insured_data as $irow) {
                if ($irow['id'] == $primary_insurance) {
                    $tmp_insurance_id = $irow['insurance_id'];
                    break;
                }
            }

            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('id = ?', $tmp_insurance_id);
            $insurance_data = $db_insurance->fetchRow($where);
//        $_SESSION['insurance_data'] = $insurance_data;                

            /*             * * zw <
              if($insurance_data == null) {
              if(count($encounterinsured_data) == 0)
              {
              foreach($insured_data as $row)
              {
              if($row['insured_insurance_type'] == 'primary')
              {
              $insurance_id = $row['insurance_id'];
              $db_insurance = new Application_Model_DbTable_Insurance();
              $db = $db_insurance->getAdapter();
              $where = $db->quoteInto('id=?', $insurance_id);
              $insurance_data = $db_insurance->fetchRow($where);
              }
              }
              }
              else
              {
              foreach($encounterinsured_data as $row)
              {
              if($row['type']== 'primary')
              {
              $tmp_insured_id = $row['insured_id'];

              $db_insured = new Application_Model_DbTable_Insured();
              $db = $db_insured->getAdapter();
              $where = $db->quoteInto('id=?', $tmp_insured_id);
              $tmp_insured = $db_insured->fetchRow($where);

              $tmp_insurance_id = $tmp_insured['insurance_id'];

              $db_insurance = new Application_Model_DbTable_Insurance();
              $db = $db_insurance->getAdapter();
              $where = $db->quoteInto('id=?', $tmp_insurance_id);
              $insurance_data = $db_insurance->fetchRow($where);
              }
              }
              }
              }
              else
             * 
              > */
            $insurance_id = $insurance_data['id'];

            $future_service = false;

            $start_date_1 = format($encounter['start_date_1'], 1);
            if ($start_date_1 == false) {
                $future_service = true;
            } else {
                $currentTime = date("m/d/Y");
                list($month1, $day1, $year1) = explode("/", $start_date_1);
                list($month2, $day2, $year2) = explode("/", $currentTime);
                $start_date_1 = mktime(0, 0, 0, $month1, $day1, $year1);
                $currentTime = mktime(0, 0, 0, $month2, $day2, $year2);
                $time_difference = $currentTime - $start_date_1;
                if ($time_difference < 0) {
                    $future_service = true;
                }
            }
            /* if ($future_service == true) {
              $claim_status = 'inactive_future_service';
              $_SESSION['claim_data']['claim_status'] = $claim_status;
              } else {
              if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] != null && $insurance_data['payer_type'] != 'MM' && $insurance_data['payer_type'] != 'SP') {
              //$claim_status = 'open_ready_primary_bill'; 此处如此修改之后的话可能会出现claim_data[claimstatus]=null的情况
              $_SESSION['claim_data']['bill_status'] = "bill_ready_bill_primary";
              //$claim_data['date_creation'] = date("Y-m-d");
              }
              if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] == 'MM') {
              //$claim_status = 'open_ready_delayed_primary_bill';
              $_SESSION['claim_data']['bill_status'] = "bill_ready_bill_delayed_primary";
              //$claim_data['date_creation'] = date("Y-m-d");
              }
              if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] == 'SP') {
              $claim_status = 'inactive_selfpay';
              $_SESSION['claim_data']['claim_status'] = $claim_status;
              //$claim_data['date_creation'] = date("Y-m-d");
              }
              if ($claim_data['claim_status'] != null&&$claim_data['claim_status'] !='inactive_future_service'){
              $claim_status = $claim_data['claim_status'];
              $_SESSION['claim_data']['claim_status'] = $claim_status;
              }
              } */
            //$_SESSION['claim_data']['claim_status'] = $claim_status;                                                                     
            $expected_payment = null;
            $total_charge = null;
            if (numbercheck($encounter['charges_1']) || numbercheck($encounter['charges_2']) || numbercheck($encounter['charges_3']) || numbercheck($encounter['charges_4']) || numbercheck($encounter['charges_5']) || numbercheck($encounter['charges_6'])) {
                $total_charge = $encounter['charges_1'] + $encounter['charges_2'] + $encounter['charges_3'] + $encounter['charges_4'] + $encounter['charges_5'] + $encounter['charges_6'];
                $provider = null;
                if ($encounter['provider_id'] != '' && $encounter['provider_id'] != null) {
                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('id=?', $encounter['provider_id']);

                    try {
                        $provider = $db_provider->fetchRow($where);
                    } catch (Exception $e) {
                        echo "errormessage:" + $e->getMessage();
                    }
                }
                $options = null;
                if ($provider['options_id'] != '' && $provider['options_id'] != null) {
                    $db_options = new Application_Model_DbTable_Options();
                    $db = $db_options->getAdapter();
                    $where = $db->quoteInto('id=?', $provider['options_id']);
                    $options = $db_options->fetchRow($where);
                    try {
                        $options = $db_options->fetchRow($where);
                    } catch (Exception $e) {
                        echo "errormessage:" + $e->getMessage();
                    }
                }

                if ($insurance_data['payer_type'] == 'MM') {
                    $db_innetworkpayers = new Application_Model_DbTable_Innetworkpayers();
                    $db = $db_innetworkpayers->getAdapter();
                    $innetworkpayers = null;
                    if ($insurance_data['id'] != null && $insurance_data['id'] != '' && $encounter['renderingprovider_id'] != null && $encounter['renderingprovider_id'] != '') {
                        $search = 'insurance_id=' . $insurance_data['id'] . ' And ' . 'renderingprovider_id=' . $encounter['renderingprovider_id'];
                        $where = $db->quoteInto($search);
                        $innetworkpayers = $db_innetworkpayers->fetchAll($where);
                    }
                    if ($innetworkpayers != null) {
                        $count_temp = $innetworkpayers->count();
                    }
                    if ($count_temp > 0) {
                        $db_contractrates = new Application_Model_DbTable_Contractrates();
                        $db = $db_contractrates->getAdapter();
                        $search = 'insurance_id=' . $insurance_data['id'] . ' And ' . 'provider_id=' . $encounter['provider_id'];
                        $where = $db->quoteInto($search);
                        $contractrates = $db_contractrates->fetchRow($where);

                        if ($contractrates['rates'] != null) {
                            $expected_payment = ($encounter['days_or_units_1'] + $encounter['days_or_units_2'] + $encounter['days_or_units_3'] + $encounter['days_or_units_4'] + $encounter['days_or_units_5'] + $encounter['days_or_units_6']) * $contractrates['rates'];
                        }
                    } else {
//                        $t=currency($encounter['charges_1']);
//                        $s=currency($encounter['charges_2']);
//                        $w=currency($encounter['charges_1']) + currency($encounter['charges_2']) + currency($encounter['charges_3']) + currency($encounter['charges_4']) + currency($encounter['charges_5']) + currency($encounter['charges_6']);
//                        $l=$options['non_par_expected_pay'];
//                        $_SESSION['claim_data']['expected_payment'] = (currency($encounter['expexted_payment_1']) + currency($encounter['expexted_payment_2']) + currency($encounter['expexted_payment_3']) + currency($encounter['expexted_payment_4']) + currency($encounter['expexted_payment_5']) + currency($encounter['expexted_payment_6'])) * $options['non_par_expected_pay'];

                        $expected_payment = ($encounter['charges_1'] + $encounter['charges_2'] + $encounter['charges_3'] + $encounter['charges_4'] + $encounter['charges_5'] + $encounter['charges_6']) * $options['non_par_expected_pay'];
                    }
                } else {
                    $test = ($encounter['days_or_units_1'] + $encounter['days_or_units_2'] + $encounter['days_or_units_3'] + $encounter['days_or_units_4'] + $encounter['days_or_units_5'] + $encounter['days_or_units_6']);
                    $p = $options['PIP_rate'];
                    $expected_payment = ($encounter['days_or_units_1'] + $encounter['days_or_units_2'] + $encounter['days_or_units_3'] + $encounter['days_or_units_4'] + $encounter['days_or_units_5'] + $encounter['days_or_units_6']) * $options['PIP_rate'];
                }

                $expected_payment = (currency($encounter['expected_payment_1']) + currency($encounter['expected_payment_2']) + currency($encounter['expected_payment_3']) + currency($encounter['expected_payment_4']) + currency($encounter['expected_payment_5']) + currency($encounter['expected_payment_6']));
            }

            $submitType = $this->getRequest()->getParam('submit');

            //ZW:Validate field common to all service lines
            if ($encounter['CPT_code_1'] != null || $encounter['secondary_CPT_code_1'] != null) {
                if ($tp_primary_insurance == null) {
                    $ret = 'primary_insurance';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if ($tp_secondary_insurance == $tp_primary_insurance && $tp_primary_insurance != null) {
                    $ret = 'secondary_insurance';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if (($tp_other_insurance == $tp_primary_insurance || $tp_other_insurance == $tp_secondary_insurance) && $tp_other_insurance != null) {
                    $ret = 'other_insurance';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }

                $rendering = $encounter['renderingprovider_id'];
                $referring = $encounter['referringprovider_id'];
                $facility_id = $encounter['facility_id'];
                $provider_id = $encounter['provider_id'];
                if ($provider_id == '' || $provider_id == null) {
                    $ret = 'provider_id';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if ($rendering == '' || $rendering == null) {
                    $ret = 'renderingprovider_id';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if ($facility_id == '') {
                    $ret = 'facility_id';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if ($encounter['diagnosis_code1'] == null || $encounter['diagnosis_code1'] == '') {
                    $ret = 'diagnosis_code1';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if ($encounter['diagnosis_code2'] == $encounter['diagnosis_code1'] && $encounter['diagnosis_code2'] != '') {
                    $ret = 'diagnosis_code2';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if (($encounter['diagnosis_code3'] == $encounter['diagnosis_code2'] || $encounter['diagnosis_code3'] == $encounter['diagnosis_code1']) && $encounter['diagnosis_code3'] != '') {
                    $ret = 'diagnosis_code3';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
                if (($encounter['diagnosis_code4'] == $encounter['diagnosis_code1'] || $encounter['diagnosis_code4'] == $encounter['diagnosis_code3'] ||
                        $encounter['diagnosis_code4'] == $encounter['diagnosis_code2']) && $encounter['diagnosis_code4'] != '') {
                    $ret = 'diagnosis_code4';
                    $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                }
            }
            //Validate service line fields


            $unit = array();
            $time = array();
            $timeunit = 0;
            for ($k = 1; $k <= 6; $k++) {
                if ($k > 1 && ($encounter['CPT_code_' . $k] == null && $encounter['secondary_CPT_code_' . $k] == null)) {
                    break;
                }

                if ($encounter['CPT_code_' . $k] != null && $encounter['secondary_CPT_code_' . $k] == null) {
                    //ZW: start and end_date
                    if ($encounter['start_date_' . $k] == '') {
                        $ret = 'start_date_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    if ($encounter['end_date_' . $k] == '') {
                        $ret = 'end_date_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    //ZW: Expected payment
                    $unit[$k] = $encounter['days_or_units_' . $k];
                    $cptonly = true;
                    $db_cptcode = new Application_Model_DbTable_Cptcode();
                    $db = $db_cptcode->getAdapter();
                    $where = $db->quoteInto('CPT_code = ?', $encounter['CPT_code_' . $k]) . $db->quoteInto('AND provider_id=?', $provider['id']);
                    $cptcode_data = $db_cptcode->fetchRow($where);
                    $cpt_code = $cptcode_data['CPT_code'];
                    $cpt_rate = $cptcode_data['charge_amount'];
                    $payment_expected = $cptcode_data['payment_expected'];
                    //if ($payment_expected != $encounter['expected_payment_' . $k] && $needvalidate_array[$k]) {
                    //zw: check for all
                    if ($payment_expected != $encounter['expected_payment_' . $k]) {
                        $ret = 'expected_payment_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    //merge validations under this logic, james 2015/06/19 :start
                    //validate charge_$k is not null and charge_$k's calculation is ok/fix bug:james/2015.6.29
                    if ($needvalidate_array[$k] && (($encounter['charges_' . $k] < $unit[$k] * $cpt_rate) ||
                            ($encounter['charges_' . $k] == null) || ($encounter['charges_' . $k] == "") ||
                            ((float) $encounter['charges_' . $k] == 0) )) {
                        $ret = 'charges_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    //validate if($tp_place_of_service_$k) == "21", hospitalization_from_date must be set
                    $tp_place_of_service = $this->getRequest()->getPost('place_of_service_' . $k);
                    if ($tp_place_of_service == "21") {
                        $tp_hospitalization_from_date = $this->getRequest()->getPost('hospitalization_from_date');
                        if ($tp_hospitalization_from_date == null) {
//                            session_start();
//                            $_SESSION['invalid_services_data']['services']['invalid_flag'] = 1;
//                            $_SESSION['invalid_services_data']['encounter_data'] = $encounter;
//                            if ($tp_primary_insurance != null )
//                                $_SESSION['invalid_services_data']['encounterinsured_data'] = $encounterinsured;
                            $ret = 'hospitalization_from_date';
                            $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                        }
                    }
                } else if ($encounter['secondary_CPT_code_' . $k] != null) {
                    //ZW: start and end date
                    if ($encounter['start_date_' . $k] == '') {
                        $ret = 'start_date_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    if ($encounter['end_date_' . $k] == '') {
                        $ret = 'end_date_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
                    $db = $db_crosswalk->getAdapter();
                    $where = $db->quoteInto('anesthesia_code = ?', $encounter['secondary_CPT_code_' . $k]) . $db->quoteInto('AND provider_id=?', $provider['id']);
                    $crosswalk_data = $db_crosswalk->fetchRow($where);
                    $crosswalk_code = $crosswalk_data['anesthesia_code'];

                    $base_unit = $crosswalk_data['base_unit'];

                    $time[$k] = computetime($encounter['start_date_' . $k], $encounter['end_date_' . $k], $encounter['start_time_' . $k], $encounter['end_time_' . $k]);
                    $unit[$k] = $encounter['days_or_units_' . $k];

                    //ZW: start and end time etc only for service line 1
                    if ($k == 1) {
                        if ($k == 1 && $encounter['start_time_' . $k] == '') {
                            $ret = 'start_time_' . $k;
                            $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                        }
                        if ($encounter['end_time_' . $k] == '') {
                            $ret = 'end_time_' . $k;
                            $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                        }
                        if ($time[$k] <= 0) {
                            $ret = 'end_time_' . $k;
                            $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                        }

                        //Get all service data for this rendering provider
                        $intervalbetweenservices = $_SESSION['options']['min_gap'];
                        if ($intervalbetweenservices != -1) {
                            $db_encounter = new Application_Model_DbTable_Encounter();
                            $db = $db_encounter->getAdapter();
                            $currentencounter_id = ($encounter['id'] != null && $encounter['id'] != "") ? $encounter['id'] : 0;
                            $sameday_search = 'renderingprovider_id=' . $encounter['renderingprovider_id'] . ' And id <> ' . $currentencounter_id . ' And start_date_1= \'' . $encounter['start_date_1'] . '\' And end_time_1 > subtime(\'' . $encounter['start_time_1'] . '\' , \'0:' . $intervalbetweenservices . ':0.0\') And start_time_1 < addtime(\'' . $encounter['end_time_1'] . '\' , \'0:' . $intervalbetweenservices . ':0.0\')';
                            $where = $db->quoteInto($sameday_search);
                            $sameday_services = $db_encounter->fetchAll($where)->toArray();

                            if (count($sameday_services) > 0) {
                                $ret = 'start_time_1';
                                $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                            }
                        }
                    }
                    //ZW: Validate for all service lines
                    if ($options['anesthesia_unit_rounding'] == 'always round up')
                        $timeunit = ceil($time[$k] / 15);
                    else
                        $timeunit = bcdiv($time[$k], 15, 1);

                    $modifier_unit = $this->getRequest()->getPost('modifier_unit_' . $k);
                    if ($timeunit + $base_unit + $modifier_unit <> $unit[$k] && $needvalidate_array[$k]) {
                        $ret = 'days_or_units_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }

                    //validate charge_$k is not null and charge_$k's calculation is ok/fix bug:james/2015.6.29
                    if ($needvalidate_array[$k] && (($encounter['charges_' . $k] < $unit[$k] * $options['anesthesia_billing_rate_for_non_par']) ||
                            ($encounter['charges_' . $k] == null) || ($encounter['charges_' . $k] == "") ||
                            ((float) $encounter['charges_' . $k] == 0) )) {
                        $ret = 'charges_' . $k;
                        $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                    }
                    //merge validations under this logic, james 2015/06/20 :start
                    //validate if($tp_place_of_service_1 == "21") hospitalization_from_date must be set
                    $tp_place_of_service = $this->getRequest()->getPost('place_of_service_' . $k);
                    if ($tp_place_of_service == "21") {
                        $tp_hospitalization_from_date = $this->getRequest()->getPost('hospitalization_from_date');
                        if ($tp_hospitalization_from_date == null) {
                            $ret = 'hospitalization_from_date';
                            $this->validation_fail($ret, $encounter, $encounterinsured, $insurance_data);
                        }
                    }
                } //else { //bothe CPT and secondary_CPT code are null
                //  $ret = 'secondary_CPT_code_' . $k;
                //  $this->_redirect('/biller/claims/services/nullcheck/' . $ret);                   
                //}
            }

            $encounterinsured_data = $_SESSION['encounterinsured_data'];
            $insure = $_SESSION['insured_data'];
            $insure_id = array();
            for ($i = 0; $i < count($encounterinsured_data); $i++) {
                $insure_id[$i] = $encounterinsured_data[$i]['insured_id'];
            }
            $insurance_name = array();
            for ($i = 0; $i < count($insure); $i++) {
                if (true == in_array($insure[$i]['id'], $insure_id)) {
                    $insurance_name[$i] = $insure[$i]['insurance_name'];
                }
            }
            $rendering = $encounter['renderingprovider_name']; //zzNeed New, Referring Provider
            $referring = $encounter['referringprovider_name']; //zzNeed New, Rendering Provider
            $secondary_CPT_code_text = array();
            $CPT_code_text = array();
            for ($k = 0; $k < 6; $k++) {
                $temp_k = $k + 1;
                $secondary_CPT_code_text[$temp_k] = $encounter['secondary_CPT_code_' . $temp_k . '_text'];
                $CPT_code_text[$temp_k] = $encounter['CPT_code_' . $temp_k . '_text'];
            }
            $diagnosis_code_text = array();
            for ($k = 0; $k < 4; $k++) {
                $temp_k = $k + 1;
                $diagnosis_code_text[$temp_k] = $encounter['diagnosis_code' . $temp_k . '_text'];
            }
            $name_temp = 'Need New Insurance';
            $needNew = "no";
            if (true == in_array($name_temp, $insurance_name) || $rendering == 'Need New, Rendering Provider' || 'Need New, Referring Provider' == $referring || true == in_array('Need New', $secondary_CPT_code_text) || true == in_array('Need New Need New CPT Code', $CPT_code_text) || true == in_array('Need New Need New Diagnosis Code', $diagnosis_code_text)) {
                $needNew = "yes";
                /* session_start();
                  $interactionlogs_date=$_SESSION['interactionlogs_data'];
                  $claim=$_SESSION['claim_data'];
                  $oldclaimstatus= $claim['claim_status'];

                  $claim['claim_status']='inactive_missing_data';
                  session_start();
                  $_SESSION['claim_data']=$claim;
                  $user = Zend_Auth::getInstance()->getIdentity();
                  $user_name =  $user->user_name;
                  $data_now = date("Y-m-d H:i:s");
                  $data_now = format($data_now,7);
                  $log=$user_name.': Change Claim Status from '.$oldclaimstatus.' to inactive_missing_data';
                  $count=count($interactionlogs_date);
                  $claimstatusarray= array();
                  $interactionlogs_date[$count]['claim_id']=$claim['id'];
                  $interactionlogs_date[$count]['log']=$log;
                  $interactionlogs_date[$count]['date_and_time']=$data_now;
                  session_start();
                  if($oldclaimstatus!='inactive_missing_data')
                  $_SESSION['interactionlogs_data']=$interactionlogs_date; */
            }
            if (isset($_SESSION['claim_data']['mannual_flag']) && $_SESSION['claim_data']['mannual_flag'] == "no") {
                if ($future_service == true) {
                    $claim_status = "inactive_future_service";
                    $bill_status = null;
                    //$statement_status = null;
                } else {
                    if ($needNew == "yes") {
                        $claim_status = "inactive_missing_data";
                        $bill_status = null;
                        $statement_status = null;
                    } else {
                        $claim_status = "open_new_claim";
                        $insurance_payer_type = $insurance_data['payer_type'];
                        if ($insurance_payer_type == "SP") {
                            if ($options['SI_selfpay'] == "1") {
                                $bill_status = Null;
                                $statement_status = "stmt_ready_selfpay";
                            } else {
                                $claim_status = "closed_write_off";
                                $bill_status = Null;
                                $statement_status = Null;
                            }
                        } else {
                            $insurance_tag = $insurance_data['tags'];
                            $result_array = array();
                            $result_array = parse_tag($insurance_tag);
                            if (array_key_exists("delaybilling", $result_array)) {
                                $bill_status = "bill_ready_bill_delayed_primary";
                                $statement_status = Null;
                            } else {
                                $bill_status = "bill_ready_bill_primary";
                                $statement_status = Null;
                            }
                        }
                    }
                }
                $_SESSION["claim_data"]['claim_status'] = $claim_status;
                $_SESSION["claim_data"]['bill_status'] = $bill_status;
                $_SESSION["claim_data"]['statement_status'] = $statement_status;
            }
            // if ($encounter['provider_id'] != null && $encounter['provider_id'] != "")
            $_SESSION['encounter_data'] = $encounter;                             //0223
            $_SESSION['claim_data']['expected_payment'] = $expected_payment;
            //if ($primary_insurance != null)
            $_SESSION['encounterinsured_data'] = $encounterinsured;
            $_SESSION['insurance_data'] = $insurance_data;
            $_SESSION['claim_data']['total_charge'] = $total_charge;
            //zw
            unset($_SESSION['invalid_services_data']);
            $this->_redirect('/biller/claims/' . $this->navigation($submitType, 2));
        }
    }

    public function servicesinputAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $invalid_services_data = $_SESSION['invalid_services_data']['services']['invalid_flag'];
        if ($invalid_services_data != 1) {
            $encounter_data = $_SESSION['encounter_data'];
            $encounterinsured_data = $_SESSION['encounterinsured_data'];
            //
            // $need_save=$_SESSION['claim_data']['needsave'];
        } else {
            $encounter_data = $_SESSION['invalid_services_data']['encounter_data'];
            $encounterinsured_data = $_SESSION['invalid_services_data']['encounterinsured_data'];
            //$insured_data = $_SESSION['invalid_services_data']['insured_data'];
            //$need_save=$_SESSION['invalid_services_data']['claim_data']['needsave'];
        }
        //$encounter_data = $_SESSION['encounter_data'];
        $insured_data = $_SESSION['insured_data'];
        $patient_data = $_SESSION['patient_data']; //add by qiao 09/27/2011
        $options = $_SESSION['options'];
        $need_save = $_SESSION['claim_data']['needsave'];
        /// zw <
        /*         * *****************Add encounterinsured data******************** */
        // $encounterinsured_data =  $_SESSION['encounterinsured_data'];
        ///$insured_data = $_SESSION['insured_data'];
        /*         * *****************Add encounterinsured data******************** */

        $encounter_id = $encounter_data['id'];

        if ($encounter_id != '' && $encounter_id != null) { /// Existing claim           
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('encounterinsured', array('id as encounterinsured_id', 'type'));
            $select->join('insured', 'encounterinsured.insured_id = insured.id ', array('id as insured_id', 'insurance_id', 'ID_number'));
            $select->join('insurance', 'insured.insurance_id = insurance.id', array('insurance_name', 'id as insurance_id'));
            $select->where('encounterinsured.encounter_id = ?', $encounter_id);
            $insuredinsuranceList = $db->fetchAll($select);

            //$sizeofencounterinsured = sizeof($insuredinsuranceList);
            //$sizeofpatientinsured = sizeof($insured_data);
            //if($sizeofencounterinsured == $sizeofpatientinsured) {
            //foreach($insuredinsuranceList as $row) {
            foreach ($encounterinsured_data as $row) {
                switch ($row['type']) {
                    case 'primary':
                        if ($row['change_flag'] != 3) {
                            $primary_insurance = $row['insurance_id'];
                            $primary_insured = $row['insured_id'];
                        }
                        break;
                    case 'secondary':
                        if ($row['change_flag'] != 3) {
                            $secondary_insurance = $row['insurance_id'];
                            $secondary_insured = $row['insured_id'];
                        }
                        break;
                    case 'other':
                        if ($row['change_flag'] != 3) {
                            $other_insurance = $row['insurance_id'];
                            $other_insured = $row['insured_id'];
                        }
                        break;
                    default:
                        $primary_insurance = null;
                        $primary_insured = null;
                }
            }
        } else {  /// New claim. Query patientinsured no matter what, if result empty, new claim
            $dd = 0;

            $patient_id = $patient_data['id'];
            if ($patient_id != null && $patient_id != "") {/// New claim, existing patient
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounterinsured', array('id as encounterinsured_id', 'type as type'));
                $select->join('insured', 'encounterinsured.insured_id = insured.id ', array('id as insured_id', 'insurance_id'));
                $select->join('encounter', 'encounterinsured.encounter_id = encounter.id', array('id as encounter_id', 'start_date_1 as DOS', 'start_time_1 as TOS'));
                $select->join('insurance', 'insured.insurance_id = insurance.id', array('insurance_name', 'id as insurance_id'));
                $select->where('encounter.patient_id = ?', $patient_id);
                $select->order('DOS DESC');
                $select->order('TOS DESC');
                //$select->limit(1);
                $insuredinsuranceList = $db->fetchAll($select);
                $lastEncounterID = $insuredinsuranceList[0]['encounter_id'];
                $p_set = 0;
                $s_set = 0;
                $t_set = 0;
                $o_set = 0;
                if (count($insuredinsuranceList) != 0 && count($encounterinsured_data) == 0) { /// patient has previous claims, use that as default insurance            
                    foreach ($insuredinsuranceList as $row) {
                        if ($row['encounter_id'] == $lastEncounterID) {
                            switch ($row['type']) {
                                case 'primary':
                                    if ($p_set == 0) {
                                        $primary_insurance = $row['insurance_id'];
                                        $primary_insured = $row['insured_id'];
                                        $p_set = 1;
                                    }
                                    break;
                                case 'secondary':
                                    if ($s_set == 0) {
                                        $secondary_insurance = $row['insurance_id'];
                                        $secondary_insured = $row['insured_id'];
                                        $s_set = 1;
                                    }
                                    break;
                                case 'other':
                                    if ($o_set == 0) {
                                        $other_insurance = $row['insurance_id'];
                                        $other_insured = $row['insured_id'];
                                        $o_set = 1;
                                    }
                                    break;
                                default:
                                    $primary_insurance = null;
                                    $primary_insured = null;
                            }
                        }
                    }
                }/// else: existing patient does not have previous claims. could not happen in our designNeed need to set data   
                else { /// New insured
                    foreach ($encounterinsured_data as $row) {
                        if ($row['type'] == 'primary') {
                            //$data['primary_insurance'] = $row['insurance_id'];
                            $primary_insured = $row['insured_id'];
                        } else if ($row['type'] == 'secondary') {
                            //$data['secondary_insurance'] = $row['insurance_id'];
                            $secondary_insured = $row['insured_id'];
                        } else if ($row['type'] == 'other') {
                            //$data['other_insurance'] = $row['insurance_id'];
                            $other_insured = $row['insured_id'];
                        }
                    }
                }
            } /// else: New patient. Since there is no type in insured, set ins data from encounterinsured
            else { /// New insured
                foreach ($encounterinsured_data as $row) {
                    if ($row['type'] == 'primary') {
                        //$data['primary_insurance'] = $row['insurance_id'];
                        $primary_insured = $row['insured_id'];
                    } else if ($row['type'] == 'secondary') {
                        //$data['secondary_insurance'] = $row['insurance_id'];
                        $secondary_insured = $row['insured_id'];
                    } else if ($row['type'] == 'other') {
                        //$data['other_insurance'] = $row['insurance_id'];
                        $other_insured = $row['insured_id'];
                    }
                }
            }
        }
        /// > zw

        if ($encounter_data != null) {
            $date_of_current_illness_or_injury = format($encounter_data['date_of_current_illness_or_injury'], 1);
            $date_same_illness = format($encounter_data['date_same_illness'], 1);
            $not_able_to_work_from_date = format($encounter_data['not_able_to_work_from_date'], 1);
            $not_able_to_work_to_date = format($encounter_data['not_able_to_work_to_date'], 1);
            $hospitalization_from_date = format($encounter_data['hospitalization_from_date'], 1);
            $hospitalization_to_date = format($encounter_data['hospitalization_to_date'], 1);

            
             $start_date = Array();
             $end_date=Array();
             $start_time=Array();
             $end_time=Array();
             $EMG=Array();
             $EPSDT=Array();
             $CPT_code=Array();
             $renderingproviderId=Array();
             $days_or_units=Array();
             $charges=Array();
            $diagnosis_pointer=Array();
               for ($k = 1; $k < 7; $k ++) {
             $start_date[$k] = format($encounter_data['start_date_'.$k], 1);
             $end_date[$k] = format($encounter_data['end_date_'.$k], 1);
             $start_time[$k] = format($encounter_data['start_time_'.$k], 3);
             $end_time[$k] = format($encounter_data['end_time_'.$k], 3);
             $EMG[$k]=$encounter_data['EMG_'.$k];
             $EPSDT[$k]=$encounter_data['EPSDT_'.$k];
             $CPT_code[$k]= $encounter_data['CPT_code_'.$k];
             $renderingproviderId[$k]= $encounter_data['renderingprovider_id_'.$k];
             $days_or_units[$k]= $encounter_data['days_or_units_'.$k];
              $charges[$k]= $encounter_data['charges_'.$k];
            $diagnosis_pointer[$k]= $encounter_data['diagnosis_pointer_'.$k];
              }
            


            $modifier1_1 = $encounter_data['modifier1_1'];
            $modifier1 = array();
            $place_of_services = array();
            for ($k = 0; $k < 6; $k ++) {
                //$modifier1_1 = modifier($encounter_data['modifier1_1'], $options['default_modifier']);
                $modifier1[$k]['modifier'] = $encounter_data['modifier1_' . ($k + 1)];
                if ($modifier1[$k]['units'] != null && $modifier1[$k]['units'] != '')
                    $modifier1[$k]['units'] = $this->get_modifier_units($modifier1[$k]['modifier']);
                else
                    $modifier1[$k]['units'] = 0;
                $modifier1[$k]['modifier'] = str_replace('|', ' ', $modifier1[$k]['modifier']);
                $place_of_services[$k] = $encounter_data['place_of_service_' . ($k + 1)];
            }
            $time=Array();
            for($k=1;$k<7;$k++){
                $time[$k]=computetime($encounter_data['start_date_'.$k], $encounter_data['end_date_'.$k], $encounter_data['start_time_'.$k], $encounter_data['end_time_'.$k]);
            }
//            $time1 = computetime($encounter_data['start_date_1'], $encounter_data['end_date_1'], $encounter_data['start_time_1'], $encounter_data['end_time_1']);
//            $time2 = computetime($encounter_data['start_date_2'], $encounter_data['end_date_2'], $encounter_data['start_time_2'], $encounter_data['end_time_2']);
//            $time3 = computetime($encounter_data['start_date_3'], $encounter_data['end_date_3'], $encounter_data['start_time_3'], $encounter_data['end_time_3']);
//            $time4 = computetime($encounter_data['start_date_4'], $encounter_data['end_date_4'], $encounter_data['start_time_4'], $encounter_data['end_time_4']);
//            $time5 = computetime($encounter_data['start_date_5'], $encounter_data['end_date_5'], $encounter_data['start_time_5'], $encounter_data['end_time_5']);
//            $time6 = computetime($encounter_data['start_date_6'], $encounter_data['end_date_6'], $encounter_data['start_time_6'], $encounter_data['end_time_6']);

            $diag_code = array();
            if ($encounter_data['diagnosis_code1'] != '' || $encounter_data['diagnosis_code2'] != '' || $encounter_data['diagnosis_code3'] != '' || $encounter_data['diagnosis_code4'] != '') {

                $dos = $encounter_data['start_date_1'];
                if (compare_time($dos)) {
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode10();
                } else {
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                }

                $db = $db_diagnosiscode->getAdapter();
                $search = "diagnosis_code IN (";

                for ($i = 1; $i < 5; $i++) {
                    if ($encounter_data['diagnosis_code' . $i] != null && $encounter_data['diagnosis_code' . $i] != '')
                        $search = $search . "'" . $encounter_data['diagnosis_code' . $i] . "', ";
                }
                $search = substr($search, 0, strlen($search) - 2) . ")";
                $where = $db->quoteInto($search);
                $diag_code = $db_diagnosiscode->fetchAll($where)->toArray();
            }
            $d_code = array();
            for ($i = 1; $i < 5; $i++) {
                //if($diag_code[$i]['diagnosis_code'] !== null && $diag_code[$i]['diagnosis_code'] !== '')
                $d_code[$i - 1] = '';
                for ($j = 0; $j < sizeof($diag_code); $j++) {
                    if ($encounter_data['diagnosis_code' . $i] == $diag_code[$j]['diagnosis_code'])
                        $d_code[$i - 1] = $diag_code[$j]['diagnosis_code'] . ' ' . $diag_code[$j]['description'];
                }
            }
            /// add $primary_insured, etc
            /// add $resubmission_number, etc,by james
               $data = array('id' => $encounter_data['id'], 'date_of_current_illness_or_injury' => $date_of_current_illness_or_injury, 'accept_assignment' => $encounter_data['accept_assignment'],
                'date_same_illness' => $date_same_illness, 'not_able_to_work_from_date' => $not_able_to_work_from_date, 'not_able_to_work_to_date' => $not_able_to_work_to_date,
                'hospitalization_from_date' => $hospitalization_from_date, 'hospitalization_to_date' => $hospitalization_to_date, 'outside_lab' => $encounter_data['outside_lab'], 'charge' => $encounter_data['charge'],
                'resubmission_code' => $encounter_data['resubmission_code'], 'ref_number' => $encounter_data['ref_number'], 'authorization_number' => $encounter_data['authorization_number'],
                'accept_assignment' => $encounter_data['accept_assignment'], 'primary_insurance' => $primary_insurance, 'secondary_insurance' => $secondary_insurance, 'other_insurance' => $other_insurance,
                'primary_insured' => $primary_insured, 'secondary_insured' => $secondary_insured, 'other_insured' => $other_insured,
                   'secondary_CPT_code_1' => $encounter_data['secondary_CPT_code_1'],
                   //'minutes_1' => $time1,
                   'modifier1_1' => $modifier1[0]['modifier'], 
                   'modifier_unit_1' => $modifier1[0]['units'],
        //          'place_of_service_1' => $place_of_services[0],
             //   'charges_1' => $encounter_data['charges_1'], 
     //            'diagnosis_pointer_1' => $encounter_data['diagnosis_pointer_1'],
                   'expected_payment_1' => $encounter_data['expected_payment_1'],
      //        'place_of_service_2' => $place_of_services[1], 
                   'secondary_CPT_code_2' => $encounter_data['secondary_CPT_code_2'],
            //     'charges_2' => $encounter_data['charges_2'], 
      //           'diagnosis_pointer_2' => $encounter_data['diagnosis_pointer_2'],
              //     'minutes_2' => $time2, 
                   'modifier1_2' => $modifier1[1]['modifier'],
                   'modifier_unit_2' => $modifier1[1]['units'],
                   'expected_payment_2' => $encounter_data['expected_payment_2'],
        //      'place_of_service_3' => $place_of_services[2], 
                   'secondary_CPT_code_3' => $encounter_data['secondary_CPT_code_3'],
             //    'charges_3' => $encounter_data['charges_3'], 
      //           'diagnosis_pointer_3' => $encounter_data['diagnosis_pointer_3'],
             //      'minutes_3' => $time3, 
                   'modifier1_3' => $modifier1[2]['modifier'], 'modifier_unit_3' => $modifier1[2]['units'], 'expected_payment_3' => $encounter_data['expected_payment_3'],
        //       'place_of_service_4' => $place_of_services[3], 
                   'secondary_CPT_code_4' => $encounter_data['secondary_CPT_code_4'],
               // 'charges_4' => $encounter_data['charges_4'], 
       //            'diagnosis_pointer_4' => $encounter_data['diagnosis_pointer_4'],
               //    'minutes_4' => $time4, 
                   'modifier1_4' => $modifier1[3]['modifier'], 'modifier_unit_4' => $modifier1[3]['units'], 'expected_payment_4' => $encounter_data['expected_payment_4'],
       //        'place_of_service_5' => $place_of_services[4], 
                   'secondary_CPT_code_5' => $encounter_data['secondary_CPT_code_5'],
               //  'charges_5' => $encounter_data['charges_5'], 
      //             'diagnosis_pointer_5' => $encounter_data['diagnosis_pointer_5'], 
               //    'minutes_5' => $time5,
                   'modifier1_5' => $modifier1[4]['modifier'], 'modifier_unit_5' => $modifier1[4]['units'], 'expected_payment_5' => $encounter_data['expected_payment_5'],
     //         'place_of_service_6' => $place_of_services[5],
                   'secondary_CPT_code_6' => $encounter_data['secondary_CPT_code_6'],
             //    'charges_6' => $encounter_data['charges_6'],
       //         'diagnosis_pointer_6' => $encounter_data['diagnosis_pointer_6'],
              //     'minutes_6' => $time6, 
                   'modifier1_6' => $modifier1[5]['modifier'], 'modifier_unit_6' => $modifier1[5]['units'],  'expected_payment_6' => $encounter_data['expected_payment_6'],
                'file_path_service_sheet' => $encounter_data['file_path_service_sheet'], 'file_path_anesthesia_time_sheet_image' => $encounter_data['file_path_anesthesia_time_sheet_image'], 'notes' => $encounter_data['notes'], 'facility_id' => $encounter_data['facility_id']
                , 'renderingprovider_id' => $encounter_data['renderingprovider_id'], 'provider_id' => $encounter_data['provider_id'], 'referringprovider_id' => $encounter_data['referringprovider_id'], 'selfpay' => $encounter_data['selfpay']
                    ,'bill_by_1'=>$encounter_data["bill_by_1"],'bill_by_2'=>$encounter_data["bill_by_2"],'bill_by_3'=>$encounter_data["bill_by_3"],'bill_by_4'=>$encounter_data["bill_by_4"],'bill_by_5'=>$encounter_data["bill_by_5"],'bill_by_6'=>$encounter_data["bill_by_6"],'toomanyminutes'=>$options['toomanyminutes']
                    ,'start_date' => $start_date,
                    'end_date'=>$end_date,
                    'start_time'=>$start_time,
                    'end_time'=>$end_time,
                    'EMG'=>$EMG,
                   'EPSDT'=>$EPSDT,
                    'CPT_code' => $CPT_code,
                   'renderingproviderId'=>$renderingproviderId,
                    'diagnosis_code' => $d_code,
                   'days_or_units' =>$days_or_units,
                   'place_of_service'=>$place_of_services,
                   'charges'=>$charges,
                  'diagnosis_pointer'=>$diagnosis_pointer,
                   'minutes'=>$time
                      );
            $data;
            $data['needsave'] = $need_save;
            $data['first_severe_flag'] = $_SESSION['first_severe_flag'];
            $data['first_claim_flag'] = $_SESSION['first_claim_flag'];
            $data['new_claim_flag'] = $_SESSION['new_claim_flag'];
            $data['alert_patient_alert_info'] = $patient_data['alert'];
            $data['alert_patient_name'] = $patient_data['last_name'] . ', ' . $patient_data['first_name'];
            $_SESSION['first_severe_flag'] = 2;
            $_SESSION['first_claim_flag'] = 2;

            $json = Zend_Json::encode($data);
            echo $json;
        } else {
//$data = array('start_date_1' => date('m/d/Y'), 'end_date_1' => date('m/d/Y'), 'accept_assignment' => 1);
            $data = array('accept_assignment' => 1);
            $json = Zend_Json::encode($data);
            echo $json;
        }
    }

    public function getlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $encounter_data = $_SESSION['encounter_data'];
        $provider_id = $encounter_data['provider_id'];

//get renderingprovider list
        if ($provider_id != null && $provider_id != '') {
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
            $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
            $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
            $select->where('provider.id = ?', $provider_id);
            $select->group('renderingprovider.id');
            $select->order('last_name ASC');
            $renderingproviderList = $db->fetchAll($select);
            $data['renderingproviderList'] = $renderingproviderList;

            /* cici */
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
            $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
            $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');
            $select->where('provider.id = ?', $provider_id);
            $select->group('diagnosiscode_10.diagnosis_code');
            $select->order('diagnosis_code ASC');
            $diagnosis_codeList = $db->fetchAll($select);
            $data['diagnosis_codeList'] = $diagnosis_codeList;




            $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
            $select = $db->select();
            $select->from('facility', array('facility.id as fid', 'facility_name'));
            $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
            $select->join('provider', 'providerhasfacility.provider_id = provider.id');
            $select->where('provider.id = ?', $provider_id);
            $select->group('facility.id');
            $select->order('facility_name ASC');
            $facilityList = $db->fetchAll($select);
            $data['facilityList'] = $facilityList;
//referringprovider
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
            $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
            $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
            $select->where('provider.id = ?', $provider_id);
            $select->group('referringprovider.id');
            $select->order('last_name ASC');
            $referringproviderList = $db->fetchAll($select);
            $data['referringproviderList'] = $referringproviderList;
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function getusernamefromidAction() {
        $this->_helper->viewRenderer->setNoRender();
        $user_id = $_POST['user_id'];

        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('id = ?', $user_id);
        $user_data = $db_user->fetchRow($where)->toArray();
        $data['user_name'] = $user_data['user_name'];
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function sortdocsAction() {
        $this->_helper->viewRenderer->setNoRender();
        $type = $_POST['type'];
        $doc = $_POST['doc'];
        Zend_Session::start();
        if ($doc == "patient") {
            $this->view->cur_service_info = $this->get_cur_service_info('claim');
            session_start();
            $en_data = $_SESSION['encounter_data'];
            $provider_id = $en_data['provider_id'];

            $billingcompany_id = $this->billingcompany_id;
            $claim_id = $_SESSION['claim_data']['id'];
            $patient_id = $_SESSION['patient_data']['id'];
            $patient_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/' . $patient_id;
            $patient_paths = array();
            if (is_dir($patient_dir)) {
                foreach (glob($patient_dir . '/*.*') as $filename) {
                    array_push($patient_paths, $filename);
                }
            }
            $patient_doc_list = array();
            $sortdate = array();
            $j = 0;
            $temp2;
            foreach ($patient_paths as $path) {
                $temp2 = explode("/", $path);
                $temp2 = explode(".", $temp2[count($temp2) - 1]);
                $filename = $temp2[0];
                $temp2 = explode("-", $filename);
                $data_time = $temp2[0];
                $sortdate[$j] = $data_time;

                $year = substr($data_time, 0, 4);
                $month = substr($data_time, 4, 2);
                $day = substr($data_time, 6, 2);
                $hour = substr($data_time, 8, 2);
                $min = substr($data_time, 10, 2);
                $sec = substr($data_time, 12, 2);
                $data = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $patient_doc_list[$j]['data'] = $data;
                $patient_doc_list[$j]['description'] = $temp2[1];
                $patient_doc_list[$j]['description_source'] = $temp2[2];
                $length = count($temp2);
                $patient_doc_list[$j]['owner'] = $temp2[3];
                $patient_doc_list[$j]['changeid'] = "#patient_doc_" . $j;
                $n = 4;
                if ($length > 4) {
                    for ($n; $n < $length; $n++) {
                        $patient_doc_list[$j]['owner'] = $patient_doc_list[$j]['owner'] . '-' . $temp2[$n];
                    }
                } else {
                    $patient_doc_list[$j]['owner'] = $temp2[3];
                }
                $patient_doc_list[$j]['url'] = $path;
                $j++;
            }
            if ($type == "date") {
                array_multisort($sortdate, SORT_ASC, SORT_STRING, $patient_doc_list);
                foreach ($patient_doc_list as $key => $values) {
                    $patient_doc_list[$key]['changeid'] = "#patient_doc_" . $key;
                }
                $data = array();
                $data['patient_doc_list'] = $patient_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "desc_type") {
                $sort_desc_type = array();
                foreach ($patient_doc_list as $key => $values) {
                    $sort_desc_type[$key] = $values['description'];
                }
                array_multisort($sort_desc_type, SORT_ASC, SORT_STRING, $patient_doc_list);
                foreach ($patient_doc_list as $key => $values) {
                    $patient_doc_list[$key]['changeid'] = "#patient_doc_" . $key;
                }
                $data = array();
                $data['patient_doc_list'] = $patient_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "desc_source") {
                foreach ($patient_doc_list as $key => $values) {
                    $sort_desc_source[$key] = $values['description_source'];
                }
                array_multisort($sort_desc_source, SORT_ASC, SORT_STRING, $patient_doc_list);
                foreach ($patient_doc_list as $key => $values) {
                    $patient_doc_list[$key]['changeid'] = "#patient_doc_" . $key;
                }
                $data = array();
                $data['patient_doc_list'] = $patient_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "owner") {
                //$sort_owner = array();
                foreach ($patient_doc_list as $key => $values) {
                    $sort_owner[$key] = $values['owner'];
                }
                array_multisort($sort_owner, SORT_ASC, SORT_STRING, $patient_doc_list);
                foreach ($patient_doc_list as $key => $values) {
                    $patient_doc_list[$key]['changeid'] = "#patient_doc_" . $key;
                }
                $data = array();
                $data['patient_doc_list'] = $patient_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
        }
        if ($doc == "claim") {
            $this->view->cur_service_info = $this->get_cur_service_info('claim');
            session_start();
            $en_data = $_SESSION['encounter_data'];
            $provider_id = $en_data['provider_id'];

            $billingcompany_id = $this->billingcompany_id;
            $claim_id = $_SESSION['claim_data']['id'];
            $patient_id = $_SESSION['patient_data']['id'];
            $claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
            $claim_paths = array();
            if (is_dir($claim_dir)) {
                foreach (glob($claim_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            $claim_doc_list = array();
            $sortdate = array();
            $i = 0;
            $temp;
            foreach ($claim_paths as $path) {
                $temp = explode("/", $path);
                $temp = explode(".", $temp[count($temp) - 1]);
                $filename = $temp[0];
                $temp = explode("-", $filename);
                $data_time = $temp[0];
                $sortdate[$i] = $date_time;

                $year = substr($data_time, 0, 4);
                $month = substr($data_time, 4, 2);
                $day = substr($data_time, 6, 2);
                $hour = substr($data_time, 8, 2);
                $min = substr($data_time, 10, 2);
                $sec = substr($data_time, 12, 2);
                $data = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $claim_doc_list[$i]['data'] = $data;
                $claim_doc_list[$i]['description'] = $temp[1];
                $claim_doc_list[$i]['description_source'] = $temp[2];
                $length = count($temp);
                $claim_doc_list[$i]['owner'] = $temp[3];
                $claim_doc_list[$i]['changeid'] = "#patient_doc_" . $i;
                $n = 4;
                if ($length > 4) {
                    for ($n; $n < $length; $n++) {
                        $claim_doc_list[$i]['owner'] = $claim_doc_list[$i]['owner'] . '-' . $temp[$n];
                    }
                } else {
                    $claim_doc_list[$i]['owner'] = $temp[3];
                }
                $claim_doc_list[$i]['url'] = $path;
                $i++;
            }

            if ($type == "date") {
                array_multisort($sortdate, SORT_ASC, SORT_STRING, $claim_doc_list);
                foreach ($claim_doc_list as $key => $values) {
                    $claim_doc_list[$key]['changeid'] = "#claim_doc_" . $key;
                }
                $data = array();
                $data['claim_doc_list'] = $claim_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "desc_type") {
                $sort_desc_type = array();
                foreach ($claim_doc_list as $key => $values) {
                    $sort_desc_type[$key] = $values['description'];
                }
                array_multisort($sort_desc_type, SORT_ASC, SORT_STRING, $claim_doc_list);
                foreach ($claim_doc_list as $key => $values) {
                    $claim_doc_list[$key]['changeid'] = "#claim_doc_" . $key;
                }
                $data = array();
                $data['claim_doc_list'] = $claim_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "desc_source") {
                foreach ($claim_doc_list as $key => $values) {
                    $sort_desc_source[$key] = $values['description_source'];
                }
                array_multisort($sort_desc_source, SORT_ASC, SORT_STRING, $claim_doc_list);
                foreach ($claim_doc_list as $key => $values) {
                    $claim_doc_list[$key]['changeid'] = "#claim_doc_" . $key;
                }
                $data = array();
                $data['claim_doc_list'] = $claim_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
            if ($type == "owner") {
                //$sort_owner = array();
                foreach ($claim_doc_list as $key => $values) {
                    $sort_owmer[$key] = $values['owner'];
                }
                array_multisort($sort_owner, SORT_ASC, SORT_STRING, $claim_doc_list);

                foreach ($claim_doc_list as $key => $values) {
                    $claim_doc_list[$key]['changeid'] = "#claim_doc_" . $key;
                }
                $data = array();
                $data['claim_doc_list'] = $claim_doc_list;

                $json = Zend_Json::encode($data);
                echo $json;
            }
        }
    }

    public function interactionAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $followups_data = $_SESSION['followups_data'];
        $claim_data = $_SESSION['claim_data'];
        $insurance_data = $_SESSION['insurance_data'];
        $encounterinsured_data = $_SESSION['encounterinsured_data'];
        $insured_data = $_SESSION['insured_data'];
        $claim_id = $_SESSION['claim_data']['id'];

        //$db_interactionlog = new Application_Model_DbTable_Interactionlog();  
        //$db = $db_interactionlog->getAdapter(); 
        //$quote_id = $claim_id;  
        //$quote = 'claim_id = ' . $quote_id;  
        //$where = $db->quoteInto($quote);  
        //$interactionlogdata = $db_interactionlog ->fetchAll($where);
        //$data['interactionlogs'] = $interactionlogdata;
        $data = array();
        $data['interactionlogs'] = $_SESSION['interactionlogs_data'];
        for ($i = 0; $i < count($data['interactionlogs']); $i++) {
            $datetemp = format($data['interactionlogs'][$i]['date_and_time'], 4);
            if ($datetemp != null) {
                $data['interactionlogs'][$i]['date_and_time'] = $datetemp;
            }
        }

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /*
     * used for the Document Tab
     * add by peng
     * display the exsit documents related to the patient or the claim with its details
     * provide the function to upload a document with selected type and source 
     * add the log when upload or delete a doc file 
     */

    public function documentsAction() {
        if (!$this->getRequest()->isPost()) {
            Zend_Session::start();
            $cur_service_info = $this->get_cur_service_info('claim');
            $this->view->cur_service_info = $cur_service_info;
            $insurance_id = $cur_service_info['insurance_id'];
            $insurance_display = $cur_service_info['insurance_display'];
            if ('Self Pay' == $insurance_display) {
                $ctaiList = array();
            } else {
                $issue = array();
                $second_order = array();
                $ctaiList = $this->getTAIList($insurance_id, $issue, $second_order);
                array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $ctaiList);
            }
            $this->view->taiList = $ctaiList;
            //get the patient_doc_types, patient_doc_sources, claim_doc_types, cliam_doc_sources to dispaly on the DocumentUI
            //the doc_types just comes from the billingcompany table
            //the doc_sources comes from the billingcompany table and the encounterinsured insurances or patientinsured insurance
            session_start();
            $needsave = $_SESSION['claim_data']['needsave'];
            $new_claim_flag = $_SESSION['new_claim_flag'];
            $this->view->new_claim_flag = $new_claim_flag;
            $en_data = $_SESSION['encounter_data'];
            $provider_id = $en_data['provider_id'];
            $billingcompanydata = $_SESSION['billingcompany_data'];
            $billingcompany_id = $billingcompanydata['id'];
            $patientdoctypestr = $billingcompanydata['patientdoctypes'];
            $patientdoctypes = explode('|', $patientdoctypestr);
            $this->view->patientdoctypes = $patientdoctypes;
            $patientdocsourcestr = $billingcompanydata['patientdocsources'];
            $patientdocsourcesend = explode('|', $patientdocsourcestr);
            $claimdoctypestr = $billingcompanydata['claimdoctypes'];
            $claimdoctypes = explode('|', $claimdoctypestr);
            $this->view->claimdoctypes = $claimdoctypes;
            $this->view->needsave = $needsave;
            $claimdocsourcestr = $billingcompanydata['claimdocsources'];
            $claimdocsourcesend = explode('|', $claimdocsourcestr);

            $db = Zend_Registry::get('dbAdapter');
            $encounter_id = $en_data['id'];
            /* $sql = <<<SQL
              select distinct insurance.insurance_name
              from insured
              left join insurance on insurance.id = insured.insurance_id
              where insured.id in
              (
              select insured_id
              from encounterinsured
              where encounterinsured.encounter_id = ?
              )
              SQL
              ; */
            $sql = <<<SQL
select distinct insurance.insurance_name 
from insured 
left join insurance on insurance.id = insured.insurance_id 
where insured.id in 
(
	select insured_id
    from patientinsured
    where patientinsured.patient_id = ?
)
SQL
            ;
            $patient_id = $_SESSION['patient_data']['id'];
            $result = $db->query($sql, array($patient_id));
            //获取所有行
            $insurance_data = $result->fetchAll();
            $claimdocsources = array();

            $num = count($insurance_data);
            foreach ($insurance_data as $key => $value) {
                $claimdocsources[$key] = $value['insurance_name'];
            }

            foreach ($claimdocsourcesend as $key => $value) {
                if ($value != "" && $value != NULL) {
                    $claimdocsources[$key + $num] = $value;
                    $num = $num + 1;
                }
            }
            $this->view->claimdocsources = $claimdocsources;

            $db = Zend_Registry::get('dbAdapter');
            $patient_id = $_SESSION['patient_data']['id'];
            $sql = <<<SQL
select distinct insurance.insurance_name 
from insured 
left join insurance on insurance.id = insured.insurance_id 
where insured.id in 
(
	select insured_id
    from patientinsured
    where patientinsured.patient_id = ?
)
SQL
            ;
            $result = $db->query($sql, array($patient_id));
            //获取所有行
            $insurance_data = $result->fetchAll();

            $patientdocsources = array();

            $num = count($insurance_data);
            foreach ($insurance_data as $key => $value) {
                $patientdocsources[$key] = $value['insurance_name'];
            }
            foreach ($patientdocsourcesend as $key => $value) {
                if ($value != "" && $value != NULL) {
                    $patientdocsources[$key + $num] = $value;
                    $num = $num + 1;
                }
            }
            $this->view->patientdocsources = $patientdocsources;


            // get the exist doc paths(both claim docs and patient docs)
            $claim_id = $_SESSION['claim_data']['id'];
            $patient_id = $_SESSION['patient_data']['id'];
            $claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
            $patient_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/patient/' . $patient_id;
            $claim_paths = array();
            $patient_paths = array();
            if (is_dir($claim_dir)) {
                foreach (glob($claim_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            if (is_dir($patient_dir)) {
                foreach (glob($patient_dir . '/*.*') as $filename) {
                    array_push($patient_paths, $filename);
                }
            }
            $path = array();
            $path = get_docfile_paths($claim_id);
            //analyze the doc paths and put them into the doc list which has the date, type, source, and user  
            $claim_doc_list = array();
            $i = 0;
            $temp;
            foreach ($claim_paths as $path) {
                $temp = explode("/", $path);
                $temp = explode(".", $temp[count($temp) - 1]);
                $filename = $temp[0];
                $temp = explode("-", $filename);
                $data_time = $temp[0];

                $year = substr($data_time, 0, 4);
                $month = substr($data_time, 4, 2);
                $day = substr($data_time, 6, 2);
                $hour = substr($data_time, 8, 2);
                $min = substr($data_time, 10, 2);
                $sec = substr($data_time, 12, 2);
                $data = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $claim_doc_list[$i]['data'] = $data;
                $claim_doc_list[$i]['description'] = $temp[1];
                $claim_doc_list[$i]['description_source'] = $temp[2];
                $length = count($temp);
                $claim_doc_list[$i]['owner'] = $temp[3];
                $n = 4;
                if ($length > 4) {
                    for ($n; $n < $length; $n++) {
                        $claim_doc_list[$i]['owner'] = $claim_doc_list[$i]['owner'] . '-' . $temp[$n];
                    }
                } else {
                    $claim_doc_list[$i]['owner'] = $temp[3];
                }
                $claim_doc_list[$i]['url'] = $path;
                $i++;
            }
            $patient_doc_list = array();
            $j = 0;
            $temp2;
            foreach ($patient_paths as $path) {
                $temp2 = explode("/", $path);
                $temp2 = explode(".", $temp2[count($temp2) - 1]);
                $filename = $temp2[0];
                $temp2 = explode("-", $filename);
                $data_time = $temp2[0];

                $year = substr($data_time, 0, 4);
                $month = substr($data_time, 4, 2);
                $day = substr($data_time, 6, 2);
                $hour = substr($data_time, 8, 2);
                $min = substr($data_time, 10, 2);
                $sec = substr($data_time, 12, 2);
                $data = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $patient_doc_list[$j]['data'] = $data;
                $patient_doc_list[$j]['description'] = $temp2[1];
                $patient_doc_list[$j]['description_source'] = $temp2[2];
                $length = count($temp2);
                $patient_doc_list[$j]['owner'] = $temp2[3];
                $n = 4;
                if ($length > 4) {
                    for ($n; $n < $length; $n++) {
                        $patient_doc_list[$j]['owner'] = $patient_doc_list[$j]['owner'] . '-' . $temp2[$n];
                    }
                } else {
                    $patient_doc_list[$j]['owner'] = $temp2[3];
                }
                $patient_doc_list[$j]['url'] = $path;
                $j++;
            }
            $this->view->claim_doc_list = $claim_doc_list;
            $this->view->patient_doc_list = $patient_doc_list;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $this->view->user_name = $user_name;
        }
        if ($this->getRequest()->isPost()) {
            session_start();
            $new_claim_flag = $_SESSION['new_claim_flag'];
            //get the submit type and tabke different operationes according to different submit type
            $submitType = $this->getRequest()->getParam('submit');
            //submit for jump to another tab
            if (($submitType == "Patient") || ($submitType == "Insurance") || ($submitType == "Service") || ($submitType == "Claim") || ($submitType == "Previous")) {
                if ($new_claim_flag == 1) {
                    $adapter = new Zend_File_Transfer_Adapter_Http();
                    $fileInfo = $adapter->getFileInfo();
                    $yes_or_no = 0;
                    foreach ($fileInfo as $file => $info) {
                        if ($adapter->isValid($file)) {
                            $today = date("Y-m-d H:i:s");
                            $date = explode(' ', $today);
                            $time0 = explode('-', $date[0]);
                            $time1 = explode(':', $date[1]);
                            $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                            $user = Zend_Auth::getInstance()->getIdentity();
                            $user_name = $user->user_name;
                            $dir = $this->sysdoc_path . '/' . 'newbillfile';
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            $file_name = $time . $user_name . '.pdf';
                            $adapter->addFilter('Rename', array('target' => $file_name), $file);
                            $adapter->setDestination($dir);
                            $adapter->receive($file);
                            $_SESSION['new_upload_file'] = $file_name;
                            $yes_or_no = 1;
                        }
                    }
                    if ($yes_or_no == 0) {
                        $_SESSION['new_upload_file'] = null;
                    }
                }
                //the function navigation_nosave give out the path to jump
                $this->_redirect('/biller/claims/' . $this->navigation_nosave($submitType, 4));
            }
            if ($submitType == "Documents") {
                $this->_redirect('/biller/claims/' . $this->navigation_nosave($submitType, 4));
            }
            // submit to save or finish the cliam
            if (($submitType == "Finish Claim") || ($submitType == "Save Claim")) {
                if ($new_claim_flag == 1) {
                    $adapter = new Zend_File_Transfer_Adapter_Http();
                    $fileInfo = $adapter->getFileInfo();
                    $yes_or_no = 0;
                    foreach ($fileInfo as $file => $info) {
                        if ($adapter->isValid($file)) {
                            $today = date("Y-m-d H:i:s");
                            $date = explode(' ', $today);
                            $time0 = explode('-', $date[0]);
                            $time1 = explode(':', $date[1]);
                            $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                            $user = Zend_Auth::getInstance()->getIdentity();
                            $user_name = $user->user_name;
                            $dir = $this->sysdoc_path . '/' . 'newbillfile';
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            $file_name = $time . $user_name . '.pdf';
                            $adapter->addFilter('Rename', array('target' => $file_name), $file);
                            $adapter->setDestination($dir);
                            $adapter->receive($file);
                            $_SESSION['new_upload_file'] = $file_name;
                            $yes_or_no = 1;
                        }
                    }
                    if ($yes_or_no == 0) {
                        $_SESSION['new_upload_file'] = null;
                    }
                }
                $this->_redirect('/biller/claims/' . $this->navigation($submitType, 4));
            }
            //when submit to imigrate the docs 
            if ($submitType == "imigrate") {
                $i = 0;
                $billingcompany_id = 1;
                //$billingcompany_id = 2;
                for ($i = 0; $i < 2; $i++) {
                    if ($i == 0) {
                        //$provider_id = 3;
                        $provider_id = 1;
                    } else {
                        //$provider_id = 5;
                        $provider_id = 2;
                    }
                    $db = Zend_Registry::get('dbAdapter');
                    $sql = <<<SQL
select id
from encounter
where provider_id = ?
SQL
                    ;
                    $result = $db->query($sql, array($provider_id));
                    //获取所有行
                    $encounter_data = $result->fetchAll();
                    $encounters = array();

                    foreach ($encounter_data as $key => $value) {
                        $encounters[$key] = $value['id'];
                    }
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    foreach ($encounters as $key => $encounter_id) {
                        $where = $db->quoteInto("id = ?", $encounter_id);
                        //$exist = $db_encounter ->fetchAll($where)->toArray();
                        $exist = $db_encounter->fetchAll($where);
                        $claim_id = $exist[0]['claim_id'];
                        //imigrate the claim docs
                        //imigrate the claim docs except one_off docs
                        $claim_dir = $this->sysdoc_path . '/document/claim/' . $claim_id;
                        $claim_paths = array();
                        if (is_dir($claim_dir)) {
                            foreach (glob($claim_dir . '/*.*') as $filename) {
                                array_push($claim_paths, $filename);
                            }
                        }
                        $doc_counts = count($claim_paths);
                        if ($doc_counts >= 1) {
                            $billingcompany_dir = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($billingcompany_dir)) {
                                mkdir($billingcompany_dir);
                            }
                            $provider_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($provider_dir)) {
                                mkdir($provider_dir);
                            }
                            $provider_dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($provider_dir_claim)) {
                                mkdir($provider_dir_claim);
                            }
                            $new_claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                            if (!is_dir($new_claim_dir)) {
                                $anw = mkdir($new_claim_dir);
                            }
                            foreach ($claim_paths as $key => $file_path) {
                                $filedate = date("Ymdhis", filemtime($file_path));
                                $file_path_ex = explode('/', $file_path);
                                $file_name_withexten = $file_path_ex[count($file_path_ex) - 1];
                                $file_name_ex = explode('.', $file_name_withexten);
                                $file_name = $file_name_ex[0];
                                if (fnmatch("Combo*", $file_name)) {
                                    continue;
                                }
                                $file_extension = $file_name_ex[1];
                                $file_name_new = $filedate . "-" . $file_name . "-OldDoc-" . "Lin" . "." . $file_extension;
                                $file_path_new = $new_claim_dir . '/' . $file_name_new;
                                if (copy($file_path, $file_path_new)) {
                                    //unlink($file_path);
                                }
                            }
                        }
                        //imigrate the one_off claim docs
                        $claim_dir_one_off = $this->sysdoc_path . '/document/claim/' . $claim_id . '/' . 'one-off';
                        $claim_paths_one_off = array();
                        if (is_dir($claim_dir_one_off)) {
                            foreach (glob($claim_dir_one_off . '/*.*') as $filename) {
                                array_push($claim_paths_one_off, $filename);
                            }
                        }
                        $doc_counts_one_off = count($claim_paths_one_off);
                        if ($doc_counts_one_off >= 1) {
                            $billingcompany_dir = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($billingcompany_dir)) {
                                mkdir($billingcompany_dir);
                            }
                            $provider_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($provider_dir)) {
                                mkdir($provider_dir);
                            }
                            $provider_dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($provider_dir_claim)) {
                                mkdir($provider_dir_claim);
                            }
                            $new_claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                            if (!is_dir($new_claim_dir)) {
                                mkdir($new_claim_dir);
                            }
                            foreach ($claim_paths_one_off as $key => $file_path) {
                                $filedate = date("Ymdhis", filemtime($file_path));
                                $file_path_ex = explode('/', $file_path);
                                $file_name_withexten = $file_path_ex[count($file_path_ex) - 1];
                                $file_name_ex = explode('.', $file_name_withexten);
                                $file_name = $file_name_ex[0];
                                if (fnmatch("Combo*", $file_name)) {
                                    continue;
                                }
                                $file_extension = $file_name_ex[1];
                                $file_name_new = $filedate . "-" . $file_name . "-OldDoc-" . "Lin" . "." . $file_extension;
                                $file_path_new = $new_claim_dir . '/' . $file_name_new;
                                if (copy($file_path, $file_path_new)) {
                                    //unlink($file_path);
                                }
                            }
                        }

                        //imigrate the encounter docs 
                        //imigrate the encounter docs except the one-off docs
                        $encounter_dir = $this->sysdoc_path . '/document/encounter/' . $encounter_id;
                        $encounter_paths = array();
                        if (is_dir($encounter_dir)) {
                            foreach (glob($encounter_dir . '/*.*') as $filename) {
                                array_push($encounter_paths, $filename);
                            }
                        }
                        $doc_counts_enc = count($encounter_paths);
                        if ($doc_counts_enc >= 1) {
                            $billingcompany_dir = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($billingcompany_dir)) {
                                mkdir($billingcompany_dir);
                            }
                            $provider_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($provider_dir)) {
                                mkdir($provider_dir);
                            }
                            $provider_dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($provider_dir_claim)) {
                                mkdir($provider_dir_claim);
                            }
                            $encounter_dir_new = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                            if (!is_dir($encounter_dir_new)) {
                                mkdir($encounter_dir_new);
                            }
                            foreach ($encounter_paths as $key => $file_path) {
                                $filedate = date("Ymdhis", filemtime($file_path));
                                $file_path_ex = explode('/', $file_path);
                                $file_name_withexten = $file_path_ex[count($file_path_ex) - 1];
                                $file_name_ex = explode('.', $file_name_withexten);
                                $file_name = $file_name_ex[0];
                                if (fnmatch("Combo*", $file_name)) {
                                    continue;
                                }
                                $file_extension = $file_name_ex[1];
                                $file_name_new = $filedate . "-" . $file_name . "-OldDoc-" . "Lin" . "." . $file_extension;
                                $file_path_new = $encounter_dir_new . '/' . $file_name_new;
                                if (copy($file_path, $file_path_new)) {
                                    //unlink($file_path);
                                }
                            }
                        }
                        //imigrate the one-off encounter docs
                        $encounter_dir_one_off = $this->sysdoc_path . '/document/encounter/' . $encounter_id . '/one-off';
                        $encounter_paths_one_off = array();
                        if (is_dir($encounter_dir_one_off)) {
                            foreach (glob($encounter_dir_one_off . '/*.*') as $filename) {
                                array_push($encounter_paths_one_off, $filename);
                            }
                        }
                        $doc_counts_enc_oneoff = count($encounter_paths_one_off);
                        if ($doc_counts_enc_oneoff >= 1) {
                            $billingcompany_dir = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($billingcompany_dir)) {
                                mkdir($billingcompany_dir);
                            }
                            $provider_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($provider_dir)) {
                                mkdir($provider_dir);
                            }
                            $provider_dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($provider_dir_claim)) {
                                mkdir($provider_dir_claim);
                            }
                            $encounter_dir_new = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                            if (!is_dir($encounter_dir_new)) {
                                mkdir($encounter_dir_new);
                            }
                            foreach ($encounter_paths_one_off as $key => $file_path) {
                                $filedate = date("Ymdhis", filemtime($file_path));
                                $file_path_ex = explode('/', $file_path);
                                $file_name_withexten = $file_path_ex[count($file_path_ex) - 1];
                                $file_name_ex = explode('.', $file_name_withexten);
                                $file_name = $file_name_ex[0];
                                if (fnmatch("Combo*", $file_name)) {
                                    continue;
                                }
                                $file_extension = $file_name_ex[1];
                                $file_name_new = $filedate . "-" . $file_name . "-OldDoc-" . "Lin" . "." . $file_extension;
                                $file_path_new = $encounter_dir_new . '/' . $file_name_new;
                                if (copy($file_path, $file_path_new)) {
                                    //unlink($file_path);
                                }
                            }
                        }
                    }
                }
                $this->_redirect('/biller/claims/' . $this->navigation_nosave("Documents", 4));
            }
            //when submit to upload a file 
            if ($submitType == "UPLOAD_P" || $submitType == "UPLOAD_C") {
                //get the information of the uploaded doc
                if ($submitType == "UPLOAD_P") {
                    $file_type = "Patient";
                    $doc_desc = $this->getRequest()->getPost('doc_desc_1');
                    $new_description = $this->getRequest()->getPost('new_description_type_1');
                    $doc_desc_source = $this->getRequest()->getPost('doc_desc_source_1_select');
                    $new_description_source = $this->getRequest()->getPost('new_description_source_1');
                }
                if ($submitType == "UPLOAD_C") {
                    $file_type = "Claim";
                    $doc_desc = $this->getRequest()->getPost('doc_desc_2');
                    $new_description = $this->getRequest()->getPost('new_description_type_2');
                    $doc_desc_source = $this->getRequest()->getPost('doc_desc_source_2_select');
                    $new_description_source = $this->getRequest()->getPost('new_description_source_2');
                }
                // if doc_type and doc_source isn't selected, just do nothing
                if (($doc_desc == "") || (($doc_desc == "selfdefinition") && ($new_description == "")))
                    $this->_redirect('/biller/claims/documents');
                if (($doc_desc_source == "") || (($doc_desc_source == "selfdefinition") && ($new_description_source == "")))
                    $this->_redirect('/biller/claims/documents');


                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    $id = '';
                    $type;
                    session_start();
                    //get and define the dir to save the uploaded file
                    $en_data = $_SESSION['encounter_data'];
                    $provider_id = $en_data['provider_id'];
                    $billingcompany_id = $this->billingcompany_id;
                    $claim_id = $_SESSION['claim_data']['id'];
                    $patient_id = $_SESSION['patient_data']['id'];
                    $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                    if (!is_dir($dir_billingcompany)) {
                        mkdir($dir_billingcompany);
                    }
                    $dir_provider = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                    if (!is_dir($dir_provider)) {
                        mkdir($dir_provider);
                    }
                    if ($file_type == 'Patient') {
                        $dir_patient = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/patient';
                        if (!is_dir($dir_patient)) {
                            mkdir($dir_patient);
                        }
                        $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/patient/' . $patient_id;
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                    }
                    if ($file_type == 'Claim') {
                        $dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                        if (!is_dir($dir_claim)) {
                            mkdir($dir_claim);
                        }
                        $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                    }
                    //define the file_name of the uploaded file
                    $today = date("Y-m-d H:i:s");
                    $date = explode(' ', $today);
                    $time0 = explode('-', $date[0]);
                    $time1 = explode(':', $date[1]);
                    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name = $user->user_name;
                    if ($doc_desc == 'selfdefinition' && $doc_desc_source == 'selfdefinition') {
                        $file_name = $time . '-' . $new_description . '-' . $new_description_source . '-' . $user_name;
                    }
                    if ($doc_desc != 'selfdefinition' && $doc_desc_source != 'selfdefinition') {
                        $file_name = $time . '-' . $doc_desc . '-' . $doc_desc_source . '-' . $user_name;
                        $file_name_claim_payment = $time . '-' . "Payment" . '-' . $doc_desc_source . '-' . $user_name;
                        $file_name_claim_eob = $time . '-' . "EOB" . '-' . $doc_desc_source . '-' . $user_name;
                    }
                    if ($doc_desc == 'selfdefinition' && $doc_desc_source != 'selfdefinition') {
                        $file_name = $time . '-' . $new_description . '-' . $doc_desc_source . '-' . $user_name;
                    }
                    if ($doc_desc != 'selfdefinition' && $doc_desc_source == 'selfdefinition') {
                        $file_name = $time . '-' . $doc_desc . '-' . $new_description_source . '-' . $user_name;
                        $file_name_claim_payment = $time . '-' . "Payment" . '-' . $new_description_source . '-' . $user_name;
                        $file_name_claim_eob = $time . '-' . "EOB" . '-' . $new_description_source . '-' . $user_name;
                    }
                    $old_filename = $adapter->getFileName();
                    $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                    //save the uploaded file 
                    $folder = new Zend_Search_Lucene_Storage_Directory_Filesystem($dir);
                    $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                    $adapter->setDestination($dir);
                    //add log for uploading the file
                    $today = date("Y-m-d H:i:s");
                    $claim_id = $_SESSION['encounter_data']['claim_id'];
                    $interactionlogs_data['claim_id'] = $claim_id;
                    $interactionlogs_data['date_and_time'] = $today;
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name = $user->user_name;
                    session_start();
                    $interactions = $_SESSION["interactionlogs_data"];
                    $count_log = count($interactions);
                    $interactions[$count_log]['claim_id'] = $claim_id;
                    $interactions[$count_log]['date_and_time'] = $today;
                    //if the doc_type id combosheets, we add the log in a different way
                    if (($doc_desc != 'ClaimComboSheets') && ($doc_desc != 'ServiceComboSheets')) {
                        $interactionlogs_data['log'] = $user_name . ": Document upload: " . $file_type . ": " . $file_name;
                        if ($submitType == 'UPLOAD_P') {
                            $patient_logs = $_SESSION['patientlogs'];
                            $count_patientlogs = count($patient_logs);
                            $patient_logs[$count_patientlogs]['patient_id'] = $claim_id;
                            $patient_logs[$count_patientlogs]['date_and_time'] = $today;
                            $patient_logs[$count_patientlogs]['log'] = $interactionlogs_data['log'];
                            array_splice($patient_logs, $count_patientlogs + 1);
                            $_SESSION['patientlogs'] = $patient_logs;
                            session_start();
                            $_SESSION['claim_data']['needsave'] = 1;
                        } else {
                            $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                            array_splice($interactions, $count_log + 1);
                            $_SESSION['interactionlogs_data'] = $interactions;
                            session_start();
                            $_SESSION['claim_data']['needsave'] = 1;
                        }
                        //mysql_insert('interactionlog', $interactionlogs_data);
                    } else if ($doc_desc == 'ClaimComboSheets') {
                        //if the doc_type id the CliamComboSheet, add the real saved file name in the log
                        $interactionlogs_data['log'] = $user_name . ": Document_ClaimComboSheets upload: " . $file_type . ": " . $file_name_claim_payment;
                        $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                        array_splice($interactions, $count_log + 1);
                        $_SESSION['interactionlogs_data'] = $interactions;
                        //mysql_insert('interactionlog', $interactionlogs_data);
                        $count_log = $count_log + 1;
                        $interactions[$count_log]['claim_id'] = $claim_id;
                        $interactions[$count_log]['date_and_time'] = $today;
                        $interactionlogs_data['log'] = $user_name . ": Document_ClaimComboSheets upload: " . $file_type . ": " . $file_name_claim_eob;
                        $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                        array_splice($interactions, $count_log + 1);
                        $_SESSION['interactionlogs_data'] = $interactions;
                        session_start();
                        $_SESSION['claim_data']['needsave'] = 1;
                        //mysql_insert('interactionlog', $interactionlogs_data);
                    }

                    if (!$adapter->receive()) {
                        $messages = $adapter->getMessages();
                        echo implode("n", $messages);
                    } else {

                        if ($doc_desc == 'ClaimComboSheets') {// if the doc_type is CliamComboSheets, split the file in two file Payment and EOB
                            $sourcefile = $dir . '/' . $file_name . $file_extension;
                            $page_count = get_page_count($sourcefile);
                            $split_options = array();
                            array_push($split_options, array($file_name_claim_payment . $file_extension, 1, 1));
                            if ($page_count == 1)
                                array_push($split_options, array($file_name_claim_eob . $file_extension, 1, 1));
                            else
                                array_push($split_options, array($file_name_claim_eob . $file_extension, 2));
                            splitpdf($sourcefile, $dir, $split_options);
                            unlink($sourcefile);
                        }
                        if ($doc_desc == 'ServiceComboSheets') {// if the doc_type is ServiceComboSheets, split the file according the service_doc_pages in the facility table 
                            //and add the real saved files in the log 
                            $sourcefile = $dir . '/' . $file_name . $file_extension;
                            $page_count = get_page_count($sourcefile);
                            $split_options = array();
                            $facility_id = '';
                            session_start();
                            if ($_SESSION['tmp']['cur_service_info'] == null)
                                $facility_id = $_SESSION['encounter_data']['facility_id'];
                            else
                                $facility_id = $_SESSION['tmp']['cur_service_info']['facility_id'];
                            $combo = array();
                            if ($facility_id > 0)
                                $combo = get_service_doc_pages($facility_id);
                            $combo_counts = count($combo);
                            //for($i = 0;$i<$combo_counts;$i++)
                            //{if($combo[$i] == "Facility Sheet") $combo[$i] = "FacilitySheet";}
                            for ($i = 0; $i < $combo_counts - 1; $i++) {
                                if ($doc_desc_source != 'selfdefinition') {
                                    $new_filename = $time . '-' . $combo[$i] . '-' . $doc_desc_source . '-' . $user_name;
                                } else {
                                    $new_filename = $time . '-' . $combo[$i] . '-' . $new_description_source . '-' . $user_name;
                                }
                                $interactionlogs_data['claim_id'] = $claim_id;
                                $interactionlogs_data['date_and_time'] = $today;
                                $interactionlogs_data['log'] = $user_name . ": Document_ServiceComboSheets upload: " . $file_type . ":" . $new_filename;
                                $interactions[$count_log]['claim_id'] = $claim_id;
                                $interactions[$count_log]['date_and_time'] = $today;
                                $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                                array_splice($interactions, $count_log + 1);
                                $_SESSION['interactionlogs_data'] = $interactions;
                                //mysql_insert('interactionlog', $interactionlogs_data);
                                $count_log = $count_log + 1;
                                array_push($split_options, array($new_filename . $file_extension, $i + 1, '1'));
                                session_start();
                                $_SESSION['claim_data']['needsave'] = 1;
                            }
                            if ($doc_desc_source != 'selfdefinition') {
                                $new_filename = $time . '-' . $combo[$combo_counts - 1] . '-' . $doc_desc_source . '-' . $user_name;
                            } else {
                                $new_filename = $time . '-' . $combo[$combo_counts - 1] . '-' . $new_description_source . '-' . $user_name;
                            }
                            $interactionlogs_data['claim_id'] = $claim_id;
                            $interactionlogs_data['date_and_time'] = $today;
                            $interactions[$count_log]['claim_id'] = $claim_id;
                            $interactions[$count_log]['date_and_time'] = $today;
                            $interactionlogs_data['log'] = $user_name . ": Document_ServiceComboSheets upload: " . $file_type . ":" . $new_filename;
                            $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                            array_splice($interactions, $count_log + 1);
                            $_SESSION['interactionlogs_data'] = $interactions;
                            session_start();
                            $_SESSION['claim_data']['needsave'] = 1;
                            //mysql_insert('interactionlog', $interactionlogs_data);
                            array_push($split_options, array($new_filename . $file_extension, count($combo)));
                            splitpdf($sourcefile, $dir, $split_options);
                            unlink($sourcefile);
                        }
                    }
                }

                $this->_redirect('/biller/claims/documents');
            }
        }
    }

    public function deletedocsAction() {
        $this->_helper->viewRenderer->setNoRender();
        $url = $_POST['url'];
        $data = array();
        Zend_Session::start();
        if (file_exists($url)) {
            unlink($url);
            $fileparas = explode('/', $url);
            $length = count($fileparas);
            $file_name = $fileparas[$length - 1];
            $id = $fileparas[$length - 2];

            $result = "true";
            $today = date("Y-m-d H:i:s");
            $claim_id = $_SESSION['encounter_data']['claim_id'];
            if ($id == $claim_id) {
                $file_type = "Claim";
            } else {
                $file_type = "Patient";
            }
            $interactionlogs_data['claim_id'] = $claim_id;
            $interactionlogs_data['date_and_time'] = $today;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $interactionlogs_data['log'] = $user_name . ": Document delete: " . $file_type . ": " . $file_name;
            session_start();
            if ($file_type == "Patient") {
                $patient_logs = $_SESSION['patientlogs'];
                $count_patientlogs = count($patient_logs);
                $patient_logs[$count_patientlogs]['patient_id'] = $id;
                $patient_logs[$count_patientlogs]['date_and_time'] = $today;
                $patient_logs[$count_patientlogs]['log'] = $interactionlogs_data['log'];
                array_splice($patient_logs, $count_patientlogs + 1);
                $_SESSION['patientlogs'] = $patient_logs;
            } else {
                $interactions = $_SESSION['interactionlogs_data'];
                $count_log = count($interactions);
                $interactions[$count_log]['claim_id'] = $cliam_id;
                $interactions[$count_log]['date_and_time'] = $today;
                $interactions[$count_log]['log'] = $interactionlogs_data['log'];
                array_splice($interactions, $count_log + 1);
                $_SESSION['interactionlogs_data'] = $interactions;
            }
        } else {
            $result = "false";
        }

        $data['result'] = $result;


        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function navigation_nosave($btn_val, $cur_page) {
        $uis = array('patient', 'insurance', 'services', 'claim', 'documents');
        $redirect_page = 0;
        $redirect = strtolower($btn_val);
        switch ($redirect) {
            case 'previous':
                $redirect_page = $cur_page - 1;
                break;
            case 'patient':
                $redirect_page = 0;
                break;
            case 'insurance':
                $redirect_page = 1;
                break;
            case 'service':
                $redirect_page = 2;
                break;
            case 'claim':
                $redirect_page = 3;
                break;
            case 'documents':
                $redirect_page = 4;
                break;
            case 'next':
                $redirect_page = $cur_page + 1;
                break;
            default:
                break;
        }
        return $uis[$redirect_page];
    }

    public function claimAction() {

        $dd = 0;
        //echo "hello";
        if (!$this->getRequest()->isPost()) {
            //echo "hello";
            Zend_Session::start();
            session_start();
            if (!$_SESSION['first_claim_flag'])
                $_SESSION['first_claim_flag'] = 1;
            // echo "hello";
            $cur_service_info = $this->get_cur_service_info('claim');
            // echo "hello";
            $this->view->cur_service_info = $cur_service_info;

            $this->view->nullcheck = $this->getRequest()->getParam('nullcheck');
            $user = Zend_Auth::getInstance()->getIdentity();
            $this->view->assign('user_name', $user->user_name);
            $this->view->invalid = $_GET["invalid"];
            /*             * ********************Add encounterinsured data****************** */
            //$encounterinsured = $_SESSION['encounterinsured_data'];
            //$dd = 0;
            /*             * ********************Add encounterinsured data****************** */
            $insurance_id = $cur_service_info['insurance_id'];
            $insurance_display = $cur_service_info['insurance_display'];
            if ('Self Pay' == $insurance_display) {
                $ctaiList = array();
            } else {
                $issue = array();
                $second_order = array();
                $ctaiList = $this->getTAIList($insurance_id, $issue, $second_order);
                array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $ctaiList);
            }
            $this->view->taiList = $ctaiList;

            //$colorTags = parse_tag($_SESSION['billingcompany_data']['tags']);
            //$colorAlerts = array();
            //$k = 0;
            //foreach ($colorTags as $key => $val) {
            //    $colorAlerts[$k]['RGB'] = strtoupper($key);
            //    $colorAlerts[$k]['alert'] = $val;
            //    if($_SESSION['claim_data']['color_code'] == $colorAlerts[$k]['RGB'])
            //        $colorAlerts[$k]['select'] = 1; 
            //    $k++;
            //}
            $this->view->colorAlertList = $this->get_coloralerts();
            $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
            $select = $db->select();
            $select->from('user', array('user.id as user_id', 'user.user_name as user_name'));
            $select->join('biller', 'biller.biller_name=user.user_name');
            $select->where('biller.billingcompany_id = ?', $this->billingcompany_id);
            $select->where('user.role != ?', 'admin');
            $user = $db->fetchAll($select);
            $this->view->billerList = $user;

            /*             * **************************************************************************** */
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('patientcorrespondence');
            $select->where('patientcorrespondence.billingcompany_id = ?', $this->get_billingcompany_id());
            $select->order('patientcorrespondence.id');
            try {
                $pcList = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $pcOptionList = array();
            for ($i = 0; $i < count($pcList); $i++) {
                $pcOptionList[$i]['id'] = $pcList[$i]['id'];
                $pcOptionList[$i]['template'] = $pcList[$i]['template'];
            }
            $this->view->pcList = $pcOptionList;
            /*             * **************************************************************************** */
            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status_display');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->claimstatusList = $claimstatuslist;

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('statementstatus');
            $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
            $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->get_billingcompany_id());
            $select->group('statementstatus.id');
            $select->order('statementstatus.statement_status_display');
            try {
                $statementstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->statementstatusList = $statementstatuslist;

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('billstatus');
            $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
            $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->get_billingcompany_id());
            $select->group('billstatus.id');
            $select->order('billstatus.bill_status_display');
            try {
                $billstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $bill_status_count = count($billstatuslist);
            for ($i = 0; $i < $bill_status_count; $i++) {
                $billstatuslist[$i]['bill_status'] = trim($billstatuslist[$i]['bill_status']);
                $billstatuslist[$i]['bill_status_display'] = trim($billstatuslist[$i]['bill_status_display']);
            }
            $this->view->billstatusList = $billstatuslist;

            session_start();
            $new_claim_flag = $_SESSION['new_claim_flag'];
            $this->view->new_claim_flag = $new_claim_flag;
            $patient_id = $_SESSION['patient_data']['id'];
            if ($patient_id != "" && $patient_id != null) {
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('guarantor', array('id as g_id', 'first_name', 'last_name'));
                $select->join('claim', 'claim.guarantor_id = guarantor.id', array('claim.id as c_id'));
                $select->join('encounter', 'encounter.claim_id = claim.id', array('encounter.id as e_id'));
                $select->where('encounter.patient_id = ?', $patient_id);
                $select->group('guarantor.id');
                $guarantorList = $db->fetchAll($select);
                $this->view->guarantor_list = $guarantorList;
            } else {
                $this->view->guarantor_list = null;
            }
            $_SESSION['claimstatusList'] = $claimstatuslist;
            $_SESSION['statementstatusList'] = $statementstatuslist;
            // get the paymentFrom
            /* $db = Zend_Registry::get('dbAdapter');
              $select = $db->select();
              $select->from('billingcompany');
              $select->where('id = ?', $this->billingcompany_id);
              $billingcompany = $db->fetchAll();
              $paymentFrom = $billingcompany[0]['paymentfrom'];
              $this->view->paymentFrom = $paymentFrom;
              $paymentFromList = explode('|', $paymentFrom);
              $this->view->paymentFromList = $paymentFromList; */
            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $where = $db->quoteInto("id=?", $this->billingcompany_id);
            $billingcompany = $db_billingcompany->fetchRow($where);
            $paymentFrom = $billingcompany['paymentfrom'];
            $this->view->paymentFrom = $paymentFrom;
            if (isset($_SESSION['options']["autobilleradjustment"])) {
                $this->view->autobiller = $_SESSION['options']["autobilleradjustment"];
            } else {
                $this->view->autobiller = "0";
            }
            $insured_data = $_SESSION['insured_data'];
            $i = 0;
            $primary_check = 0;
            $secondary_check = 0;
            $other_check = 0;
            $encounterinsured_data = $_SESSION['encounterinsured_data'];
            foreach ($encounterinsured_data as $value) {
                if ($value['type'] == "primary") {
                    $primary_insured_id = $value['insured_id'];
                    $primary_check = 1;
                }
                if ($value['type'] == "secondary") {
                    $secondary_check = 1;
                }
                if ($value['type'] == "other") {
                    $other_check = 1;
                }
            }
            $db_insurance = new Application_Model_DbTable_Insurance();
            foreach ($insured_data as $row) {
                $db = $db_insurance->getAdapter();
                $where = $db->quoteInto('id = ?', $row['insurance_id']);
                $tmp_insurance_data = $db_insurance->fetchRow($where);
                if ($tmp_insurance_data['insurance_name'] == "Self Pay" && $row['id'] == $primary_insured_id) {
                    $primary_check = 0;
                }
                //$where = $db->quoteInto("insured_id=",$row['id']);
                //$tmp_encounterinsured = $db_encounterinsured->fetchRow($where);
                //$insuredinsuranceList[$i]['insured_id'] = $row['id'];
                //$insuredinsuranceList[$i]['insurance_id'] = $row['insurance_id'];
                //$insuredinsuranceList[$i]['ID_number'] = $row['ID_number'];
                //$insuredinsuranceList[$i]['insured_insurance_type'] = $row['insured_insurance_type'];
                //$insuredinsuranceList[$i]['last_name'] = $row['last_name'];
                //$insuredinsuranceList[$i]['first_name'] = $row['first_name'];
                //$insuredinsuranceList[$i]['insured_insurance_type'] = $row['insured_insurance_type'];
                //$insuredinsuranceList[$i]['insurance_display'] = $tmp_insurance_data['insurance_display'];
                if ($i == 0) {
                    $insurance_list_save = $tmp_insurance_data['insurance_display'];
                } else {
                    $insurance_list_save = $insurance_list_save . "|" . $tmp_insurance_data['insurance_display'];
                }
                $i++;
            }
            //$this->view->insuredinsuranceList = $insuredinsuranceList;
            $this->view->primary_check = $primary_check;
            $this->view->secondary_check = $secondary_check;
            $this->view->other_check = $other_check;
            $this->view->insurance_list_save = $insurance_list_save;
            $encounter_data = $_SESSION['encounter_data'];
            $eob_datas = array();
            for ($index = 0; $index < 6; $index++) {
                if (($encounter_data['secondary_CPT_code_' . ($index + 1)] != "" && $encounter_data['secondary_CPT_code_' . ($index + 1)] != null) || ($encounter_data['CPT_code_' . ($index + 1)] != "" && $encounter_data['CPT_code_' . ($index + 1)] != null)) {
                    if ($encounter_data['secondary_CPT_code_' . ($index + 1)] != "" && $encounter_data['secondary_CPT_code_' . ($index + 1)] != null) {
                        $eob_datas[$index] = $encounter_data['secondary_CPT_code_' . ($index + 1)] . "|" . $encounter_data['charges_' . ($index + 1)] . "|" . $encounter_data['expected_payment_' . ($index + 1)];
                    } else {
                        $eob_datas[$index] = $encounter_data['CPT_code_' . ($index + 1)] . "|" . $encounter_data['charges_' . ($index + 1)] . "|" . $encounter_data['expected_payment_' . ($index + 1)];
                    }
                }
            }
            $this->view->service_data = $eob_datas;
            //$this->view->newclaimflag=$_SESSION['new_claim_flag'];
            /*
              $db_patientinsured = new Application_Model_DbTable_Patientinsured();
              $db = $db_patientinsured->getAdapter();
              $where = $db->quoteInto('patient_id = ?', $patient_id);
              $insuranceList = $db_patientinsured->fetchAll($where)->toArray();
             */
            /*             * * zw <
              $db = Zend_Registry::get('dbAdapter');
              $select = $db->select();
              $select->from('patientinsured', array('id as patientinsured_id'));
              $select->join('insured', 'patientinsured.insured_id = insured.id ', array('id as insured_id', 'insurance_id','insured_insurance_type','last_name','first_name','ID_number') );
              $select->join('insurance', 'insured.insurance_id = insurance.id' ,array('insurance_display', 'id as insurance_id'));
              $select->where('patientinsured.patient_id = ?', $patient_id);

              $insuredinsuranceList = $db->fetchAll($select);

              if(count($insuredinsuranceList)!=0)
              {
              //                $last_name = $insuredinsuranceList['last_name'];
              //                $first_name = $insuredinsuranceList['first_name'];
              //                $insuredinsuranceList['name'] = $first_name.', '.$last_name;
              $this->view->insuredinsuranceList = $insuredinsuranceList;
              }
              else
              {
              $dd = 0;
              $insured_data = $_SESSION['insured_data'];
              $i = 0;

              foreach($insured_data as $row)
              {
              $db_insurance = new Application_Model_DbTable_Insurance();
              $db = $db_insurance->getAdapter();
              $where = $db->quoteInto('id = ?', $row['insurance_id']);
              $tmp_insurance_data = $db_insurance->fetchRow($where);
              $insuredinsuranceList[$i]['insurance_id'] = $row['insurance_id'];
              $insuredinsuranceList[$i]['insured_insurance_type'] = $row['insured_insurance_type'];
              $insuredinsuranceList[$i]['insurance_display'] = $tmp_insurance_data['insurance_display'];
              $i++;
              }
              $this->view->insuredinsuranceList = $insuredinsuranceList;
              $dd = 0;
              }
             * 
              > */
            //$tp_insurancelist = $insuranceList;
        }
        if ($this->getRequest()->isPost()) {
            session_start();
            $invalid_flag = $_SESSION['invalid_data']['claim']['invalid_flag'];
            if ($invalid_flag != 1) {
                $patient = $_SESSION['patient_data'];
                $patient_id = $patient['id'];
                $claim = $_SESSION['claim_data'];
                $encounter = $_SESSION['encounter_data'];
                $insurancepayments = $_SESSION['insurancepayments_data'];
                $patientpayments = $_SESSION['patientpayments_data'];
                $billeradjustments = $_SESSION['billeradjustments_data'];
                $interactionlogs = $_SESSION['interactionlogs_data'];
                $insurance_data = $_SESSION['insurance_data'];

                $payments_bk = $_SESSION['payments_data'];
                $payments = $_SESSION['payments_data'];
                $assignedclaims = $_SESSION['assignedclaims_data'];
            } else {
                $patient = $_SESSION['patient_data'];
                $patient_id = $patient['id'];
                $claim = $_SESSION['invalid_data']['claim_data'];
                $encounter = $_SESSION['encounter_data'];
                $insurancepayments = $_SESSION['invalid_data']['insurancepayments_data'];
                $patientpayments = $_SESSION['invalid_data']['patientpayments_data'];
                $billeradjustments = $_SESSION['invalid_data']['billeradjustments_data'];
                $interactionlogs = $_SESSION['invalid_data']['interactionlogs_data'];
                $insurance_data = $_SESSION['insurance_data'];

                $payments_bk = $_SESSION['invalid_data']['payments_data'];
                $payments = $_SESSION['invalid_data']['payments_data'];
                $assignedclaims = $_SESSION['invalid_data']['assignedclaims_data'];

                /*                 * ********************Add encounterinsured data****************** */
                $encounterinsured = $_SESSION['encounterinsured_data'];
            }
            /*             * ********************Add encounterinsured data****************** */

            $encounter_id = $encounter['id'];
//            $assigned_user_id = $this->getRequest()->getParam('assigned_user_id');
// get post QXW 18.02.2012
            foreach (get_ui2claim() as $key => $val) {
                $v = $this->getRequest()->getPost($val);
//if ($v != '') 
                if ($key == "claim_status") {
                    if ($v != $claim['claim_status']) {
                        if (isset($_SESSION['claim_data']['mannual_flag'])) {
                            $_SESSION['claim_data']['mannual_flag'] = "yes";
                            $claim['mannual_flag'] = "yes";
                        }
                    }
                }
                $claim[$key] = $v;
            }
//statement  26/02/2012
//Zend_Session::start();
//$statement = $_SESSION['patient_data']['statement'];
            $key = $this->getRequest()->getPost('is_the_issued_payment_sent_to_patient');
//$key = $claim['is_the_issued_payment_sent_to_patient'];
            if ($key[0] != null)
                $claim['is_the_issued_payment_sent_to_patient'] = '1';
            /*
              switch ($claim['statement_status']) {
              case 'stmt_ready_payment_sent_to_patient' :
              $claim['is_the_issued_payment_sent_to_patient'] = '1';
              break;
              case 'stmt_ready_coinsurance' :
              //$claim['is_the_issued_payment_sent_to_patient'] = '0';
              if ($claim['EOB_co_insurance'] == nul || $claim['EOB_co_insurance'] == '') {
              $ret = 'EOB_co_insurance';
              $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
              }
              break;
              case 'stmt_ready_deductible' :
              //$claim['is_the_issued_payment_sent_to_patient'] = '0';
              if ($claim['EOB_deductable'] == nul || $claim['EOB_deductable'] == '') {
              $ret = 'EOB_deductable';
              $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
              }
              break;
              case 'stmt_ready_selfpay' :
              //$claim['is_the_issued_payment_sent_to_patient'] = '0';
              break;
              default:
              //$claim['is_the_issued_payment_sent_to_patient'] = '0';
              break;
              }
             */
            $invalid_flag = $_SESSION['invalid_data']['claim']['invalid_flag'];
            session_start();
            if ($invalid_flag != 1) {
                $followups = $_SESSION['followups_data'];
            } else {
                $followups = $_SESSION['invalid_data']['followups_data'];
            }
// get post QXW 18.02.2012
            foreach (get_ui2followup() as $key => $val) {
                $v = $this->getRequest()->getPost($val);
//if ($v != '')
                $followups[$key] = $v;
            }
            // guarantor data
            $guarantorGet = array();
            foreach (get_ui2guarantor() as $key => $val) {
                $guarantorGet[$key] = $this->getRequest()->getPost($val);
            }
            $_SESSION['guarantor_data'] = $guarantorGet;
            $count2 = intval($this->getRequest()->getParam('count2'));
            $count3 = intval($this->getRequest()->getParam('count3'));
            $count4 = intval($this->getRequest()->getParam('count4'));
            $count5 = intval($this->getRequest()->getParam('count5'));
            $count_payment = intval($this->getRequest()->getParam('count_payment'));
            $claim_id = $this->getRequest()->getParam('id');

            $j = 0;
            $oldcount = count($interactionlogs);
            if ($count5 > 0) {

                for ($i = 0; $i < $count5; $i++) {
                    $v = $this->getRequest()->getPost('date_and_time_real_' . ($i + 1));
                    if ($v != null && $v != "") {
                        $interactionlogs[$j]['claim_id'] = $claim_id;
//                        $insurancepayments[$j]['id'] = '';
                        foreach (get_ui2interactionlogs() as $key => $val) {
                            $v = $this->getRequest()->getPost($val . ($i + 1));
                            $interactionlogs[$j][$key] = $v;
                            if ($i >= $oldcount) {
                                $interactionlogs[$j]['notsave'] = 1;
                            }
                        }
                        $j++;
                    }
                }
            }
            array_splice($interactionlogs, $j);
            $count_payments_bk = count($payments_bk);
            for ($tem_i = 0; $tem_i < $count_payments_bk; $tem_i++) {
                $payments_bk[$tem_i]['datetime'] = format($payments_bk[$tem_i]['datetime'], 7);
            }
            $last_take = 1;
            $j = 0;
            $log_index = 0;
            $log_payment = array();
            $delete_time = $this->getRequest()->getPost('delete_time');
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            if ($count_payment > 0) {
                if ($payments == null) {
                    $payments = array();
                }
                for ($i = 0; $i < $count_payment; $i++) {
                    $v = $this->getRequest()->getPost('payment_amount_' . ($i + 1));
                    $cor_num = $this->getRequest()->getPost('payment_realnum_' . ($i + 1));

                    if ($v != null && $v != "") {
                        if ($payments[$j] == null) {
                            $payments[$j] = array();
                        }
                        $payments[$j]['claim_id'] = $claim_id;
//                      $insurancepayments[$j]['id'] = '';
                        foreach (get_ui2payments() as $key => $val) {
                            if ($val == "payment_from_") {
                                $v = $this->getRequest()->getPost($val . ($i + 1));
                                if ($v == "Misc") {
                                    $v_new = $this->getRequest()->getPost("payment_from_self_" . ($i + 1));
                                    $payments[$j][$key] = $v_new;
                                } else {
                                    $payments[$j][$key] = $v;
                                }
                            } else if ($val == "payment_amount_") {
                                $positive = $this->getRequest()->getPost("payment_amount_positive_" . ($i + 1));
                                if ($positive == "negative") {
                                    $v = "-" . $this->getRequest()->getPost($val . ($i + 1));
                                    $payments[$j][$key] = $v;
                                } else {
                                    $v = $this->getRequest()->getPost($val . ($i + 1));
                                    $payments[$j][$key] = $v;
                                }
                            } else {
                                $v = $this->getRequest()->getPost($val . ($i + 1));
                                $payments[$j][$key] = $v;
                            }
                        }
                        $payments[$j]["type"] = $this->getRequest()->getPost("payment_type_" . ($i + 1));
                        if ($payments[$j]["type"] == "EOB") {
                            $eob_count = $this->getRequest()->getPost("service_count");
                            $payments[$j]['services'] = array();
                            for ($service_i = 1; $service_i <= $eob_count; $service_i++) {
                                $payments[$j]['services'][$service_i - 1]["amount"] = $this->getRequest()->getPost("service_" . ($i + 1) . "_" . $service_i . "_payment");
                                $payments[$j]['services'][$service_i - 1]["service_payment_id"] = $this->getRequest()->getPost("service_" . ($i + 1) . "_" . $service_i . "_payment_id");
                            }
                        }
                        if ($cor_num <= $count_payments_bk) {
                            if ($cor_num == $last_take) {
                                //check_change
                                $paymentParas = Array('amount', 'datetime', 'from', 'notes', 'internal_notes');
                                foreach ($paymentParas as $para) {
                                    if ($payments_bk[$cor_num - 1][$para] != $payments[$j][$para]) {
                                        $log_payment[$log_index] = Array();
                                        $log_payment[$log_index]['claim_id'] = $claim_id;
                                        $log_payment[$log_index]['date_and_time'] = date("m/d/Y H:i:s");
                                        $log_payment[$log_index]['log'] = $user_name . ": Changed Payment " . $payments_bk[$cor_num - 1]['amount'] . '|' . $payments_bk[$cor_num - 1]['datetime'] . '|' . $payments_bk[$cor_num - 1]['from'] . '|' . $payments_bk[$cor_num - 1]['notes'] . '|' . $payments_bk[$cor_num - 1]['internal_notes'] . " to " . $payments[$j]['amount'] . '|' . $payments[$j]['datetime'] . '|' . $payments[$j]['from'] . '|' . $payments[$j]['notes'] . '|' . $payments[$j]['internal_notes'];

                                        $log_index++;
                                        break;
                                    }
                                }
                                $last_take++;
                            }
                        } else if ($cor_num > $count_payments_bk) {
                            if ($last_take <= $count_payments_bk) {
                                //remove
                                for (; $last_take <= $count_payments_bk; $last_take++) {
                                    $log_payment[$log_index] = Array();
                                    $log_payment[$log_index]['claim_id'] = $claim_id;
                                    $log_payment[$log_index]['date_and_time'] = $delete_time;
                                    $log_payment[$log_index]['log'] = $user_name . ": Removed Payment" . $payments_bk[$last_take - 1]['amount'] . '|' . $payments_bk[$last_take - 1]['datetime'] . '|' . $payments_bk[$last_take - 1]['from'] . '|' . $payments_bk[$last_take - 1]['notes'] . '|' . $payments_bk[$last_take - 1]['internal_notes'];

                                    $log_index++;
                                }
                            }
                            //add
                            $log_payment[$log_index] = Array();
                            $log_payment[$log_index]['claim_id'] = $claim_id;
                            $log_payment[$log_index]['date_and_time'] = $payments[$j]["datetime"];
                            $log_payment[$log_index]['log'] = $user_name . ": Added Payment " . $payments[$j]['amount'] . '|' . $payments[$j]['datetime'] . '|' . $payments[$j]['from'] . '|' . $payments[$j]['notes'] . '|' . $payments[$j]['internal_notes'];

                            $log_index++;
                        }
                        $j++;
                    }
                }
            }
            if ($last_take <= $count_payments_bk) {
                //remove
                for (; $last_take <= $count_payments_bk; $last_take++) {
                    $log_payment[$log_index] = Array();
                    $log_payment[$log_index]['claim_id'] = $claim_id;
                    $log_payment[$log_index]['date_and_time'] = $delete_time;
                    $log_payment[$log_index]['log'] = $user_name . ": Removed Payment " . $payments_bk[$last_take - 1]['amount'] . '|' . $payments_bk[$last_take - 1]['datetime'] . '|' . $payments_bk[$last_take - 1]['from'] . '|' . $payments_bk[$last_take - 1]['notes'] . '|' . $payments_bk[$last_take - 1]['internal_notes'];

                    $log_index++;
                }
            }
            $amount_paid_bk = $this->getRequest()->getPost("amount_paid_bk");
            $amount_paid_cur = $this->getRequest()->getPost("amount_paid");
            $balance_due_bk = $this->getRequest()->getPost("balance_due_bk");
            $balance_due_cur = $this->getRequest()->getPost("balance_due");
            if ($amount_paid_bk != $amount_paid_cur || $balance_due_bk != $balance_due_cur) {
                $change_time = $this->getRequest()->getPost("amount_paid_time");
                if ($change_time != "" || $change_time != null) {
                    $log_payment[$log_index] = Array();
                    $log_payment[$log_index]['claim_id'] = $claim_id;
                    $log_payment[$log_index]['date_and_time'] = $change_time;
                    $log_payment[$log_index]['log'] = $user_name . ": Changed Amount Paid|Balance Due from " . $amount_paid_bk . '|' . $balance_due_bk . ' to ' . $amount_paid_cur . '|' . $balance_due_cur;
                    $log_index++;
                }
            }
            /* if($balance_due_bk!=$balance_due_cur){
              if($change_time!="" || $change_time!=null){
              $change_time = $this->getRequest()->getPost("balance_due_time");
              $log_payment[$log_index] = Array();
              $log_payment[$log_index]['claim_id'] = $claim_id;
              $log_payment[$log_index]['date_and_time'] = $change_time;
              $log_payment[$log_index]['log'] = $user_name . ": Changed Balance Due from " . $balance_due_bk . 'to' . $balance_due_cur;

              $log_index++;
              }
              } */
            array_splice($payments, $j);
            //add log
            $count_interactionlogs = count($interactionlogs);
            $count_paymentlogs = count($log_payment);
            $indexLog = 0;
            for ($indexLog = 0; $indexLog < $count_paymentlogs; $indexLog++) {

                $interactionlogs[$count_interactionlogs + $indexLog] = $log_payment[$indexLog];
            }
            $guarantor_log = array();
            $guarantor_log_index = 0;
            $guarantor_time = date("m/d/Y H:i:s");
            if (!isset($_SESSION['guarantor_data_BK_log'])) {
                $_SESSION['guarantor_data_BK_log'] = $_SESSION['guarantor_data_BK'];
            }
            if ($_SESSION['guarantor_data_BK_log'] == "no") {
                unset($_SESSION['guarantor_data_BK_log']);
            }
            if (isset($_SESSION['guarantor_data_BK_log'])) {
                $guarantor = session2DB_patient($guarantorGet);
                $guarantor_bk = $_SESSION['guarantor_data_BK_log'];
                if ($guarantor["id"] == "no") {
                    $guarantor_log[$guarantor_log_index] = Array();
                    $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                    $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                    $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Deleted Guarantor " . $guarantor_bk["last_name"] . ', ' . $guarantor_bk["first_name"];
                    $guarantor_log_index++;
                } else if ($guarantor["id"] == "new") {
                    if ($guarantor_bk["id"] != "new") {
                        $guarantor_log[$guarantor_log_index] = Array();
                        $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                        $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                        $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Added New Guarantor " . $guarantor["last_name"] . ', ' . $guarantor["first_name"];
                        $guarantor_log_index++;
                    }
                } else {
                    if ($guarantor["id"] == $guarantor_bk["id"]) {
                        foreach (get_ui2guarantor() as $key => $val) {
                            if ($guarantor[$key] != $guarantor_bk[$key]) {
                                $guarantor_log[$guarantor_log_index] = Array();
                                $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                                $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                                $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Updated Guarantor from ";
                                foreach (get_ui2guarantor() as $key => $val) {
                                    $guarantor_log[$guarantor_log_index]['log'] = $guarantor_log[$guarantor_log_index]['log'] . $guarantor_bk[$key] . "|";
                                }
                                $guarantor_log[$guarantor_log_index]['log'] = $guarantor_log[$guarantor_log_index]['log'] . " to ";
                                foreach (get_ui2guarantor() as $key => $val) {
                                    $guarantor_log[$guarantor_log_index]['log'] = $guarantor_log[$guarantor_log_index]['log'] . $guarantor_bk[$key] . "|";
                                }
                                $guarantor_log_index++;
                                break;
                            }
                        }
                    } else {
                        $guarantor_log[$guarantor_log_index] = Array();
                        $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                        $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                        $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Changed Guarantor from" . $guarantor_bk["last_name"] . ', ' . $guarantor_bk["first_name"] . " to " . $guarantor["last_name"] . ', ' . $guarantor["first_name"];
                        $guarantor_log_index++;
                    }
                }
            } else {
                $guarantor = session2DB_patient($guarantorGet);
                if ($guarantor["id"] != "no") {
                    //these two is same now, but one is for a real new added  new guarantor, one is link  this claim to a exsit guarantor, these may be different in future
                    if ($guarantor["id"] == "new") {
                        $guarantor_log[$guarantor_log_index] = Array();
                        $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                        $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                        $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Added New Guarantor " . $guarantor["last_name"] . ', ' . $guarantor["first_name"];
                        $guarantor_log_index++;
                    } else {
                        $guarantor_log[$guarantor_log_index] = Array();
                        $guarantor_log[$guarantor_log_index]['claim_id'] = $claim_id;
                        $guarantor_log[$guarantor_log_index]['date_and_time'] = $guarantor_time;
                        $guarantor_log[$guarantor_log_index]['log'] = $user_name . ": Added New Guarantor " . $guarantor["last_name"] . ', ' . $guarantor["first_name"];
                        $guarantor_log_index++;
                    }
                }
            }
            if ($guarantor["id"] != "no") {
                $_SESSION["guarantor_data_BK_log"] = $guarantor;
            } else {
                $_SESSION["guarantor_data_BK_log"] = "no";
            }
            $count_guarantorlogs = count($guarantor_log);
            $indexLog2 = 0;
            for ($indexLog2 = 0; $indexLog2 < $count_guarantorlogs; $indexLog2++) {
                $interactionlogs[$count_interactionlogs + $indexLog] = $guarantor_log[$indexLog2];
                $indexLog++;
            }
            $new_checked_change_log_index = $count_paymentlogs + $count_interactionlogs + $count_guarantorlogs;
            //$new_checked_change_log_index = $count_paymentlogs + $count_interactionlogs;
            $amount_initial_offer = $this->getRequest()->getPost("amount_initial_offer");
            $date_initial_offer = $this->getRequest()->getPost("date_initial_offer");
            $last_amount_initial_offer = $this->getRequest()->getPost("last_amount_initial_offer");
            $last_date_initial_offer = $this->getRequest()->getPost("last_date_initial_offer");
            if (($amount_initial_offer != $last_amount_initial_offer) || ($date_initial_offer != $last_date_initial_offer)) {
                $interactionlogs[$new_checked_change_log_index] = array();
                $interactionlogs[$new_checked_change_log_index]["claim_id"] = $claim_id;
                $interactionlogs[$new_checked_change_log_index]["date_and_time"] = date("m/d/Y H:i:s");
                if($last_amount_initial_offer==null&&$last_date_initial_offer==null){
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Added Proposed Amount|Proposed Date " . $amount_initial_offer . "|" . $date_initial_offer;
                }
                else{
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Changed Proposed Amount|Proposed Date From " . $last_amount_initial_offer . "|" . $last_date_initial_offer . " to " . $amount_initial_offer . "|" . $date_initial_offer;
                }
                $new_checked_change_log_index = $new_checked_change_log_index + 1;
            }
            $negotiated_payment_amount = $this->getRequest()->getPost("negotiated_payment_amount");
            $date_negotiated_amount_reached = $this->getRequest()->getPost("date_negotiated_amount_reached");
            $last_negotiated_payment_amount = $this->getRequest()->getPost("last_negotiated_payment_amount");
            $last_date_negotiated_amount_reached = $this->getRequest()->getPost("last_date_negotiated_amount_reached");
            if (($negotiated_payment_amount != $last_negotiated_payment_amount) || ($date_negotiated_amount_reached != $last_date_negotiated_amount_reached)) {
                $interactionlogs[$new_checked_change_log_index] = array();
                $interactionlogs[$new_checked_change_log_index]["claim_id"] = $claim_id;
                $interactionlogs[$new_checked_change_log_index]["date_and_time"] = date("m/d/Y H:i:s");
                if($last_negotiated_payment_amount==null&&$last_date_negotiated_amount_reached==null){
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Added Agreed Amount|Agreement Date " . $negotiated_payment_amount . "|" . $date_negotiated_amount_reached;
                }else{
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Changed Agreed Amount|Agreement Date From " . $last_negotiated_payment_amount . "|" . $last_date_negotiated_amount_reached . " to " . $negotiated_payment_amount . "|" . $date_negotiated_amount_reached;
                }
                $new_checked_change_log_index = $new_checked_change_log_index + 1;
            }
            $amount_insurance_payment_issued = $this->getRequest()->getPost("amount_insurance_payment_issued");
            $date_insurance_payment_issued = $this->getRequest()->getPost("date_insurance_payment_issued");
            $last_amount_insurance_payment_issued = $this->getRequest()->getPost("last_amount_insurance_payment_issued");
            $last_date_insurance_payment_issued = $this->getRequest()->getPost("last_date_insurance_payment_issued");
            if (($amount_insurance_payment_issued != $last_amount_insurance_payment_issued) || ($date_insurance_payment_issued != $last_date_insurance_payment_issued)) {
                $interactionlogs[$new_checked_change_log_index] = array();
                $interactionlogs[$new_checked_change_log_index]["claim_id"] = $claim_id;
                $interactionlogs[$new_checked_change_log_index]["date_and_time"] = date("m/d/Y H:i:s");
                if($last_amount_insurance_payment_issued==null&&$last_date_insurance_payment_issued==null){
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Added Issued Amount|Issued Date "  . $amount_insurance_payment_issued . "|" . $date_insurance_payment_issued;
                }else{
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Changed Issued Amount|Issued Date From " . $last_amount_insurance_payment_issued . "|" . $last_date_insurance_payment_issued . " to " . $amount_insurance_payment_issued . "|" . $date_insurance_payment_issued;
                }
                $new_checked_change_log_index = $new_checked_change_log_index + 1;
            }
            $eob_paras = array("EOB_allowed_amount", "EOB_not_allowed_amount", "EOB_co_insurance", "EOB_deductable", "EOB_reduction", "EOB_other_reduction", "EOB_adjustment_reason");
            foreach ($eob_paras as $eob_para) {
                $cur_eob = $this->getRequest()->getPost($eob_para);
                $last_eob = $this->getRequest()->getPost("last_" . $eob_para);
                if ($cur_eob != $last_eob) {
                    $interactionlogs[$new_checked_change_log_index] = array();
                    $interactionlogs[$new_checked_change_log_index]["claim_id"] = $claim_id;
                    $interactionlogs[$new_checked_change_log_index]["date_and_time"] = date("m/d/Y H:i:s");
                    if($last_eob==null){
                        $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Added EOB ";
                        foreach($eob_paras as $eob_para_tmp){
                            if($eob_para_tmp=="EOB_adjustment_reason"){
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp);
                            }else{
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp) ."|";
                            }    
                        }
                    }else{
                        $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Changed EOB From ";
                        foreach($eob_paras as $eob_para_tmp){
                            if($eob_para_tmp=="EOB_adjustment_reason"){
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost("last_".$eob_para_tmp);
                            }else{
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost("last_".$eob_para_tmp) ."|";
                            }    
                        }
                        $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] ." to ";
                        foreach($eob_paras as $eob_para_tmp){
                            if($eob_para_tmp=="EOB_adjustment_reason"){
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp);
                            }else{
                                $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp) ."|";
                            }    
                        }
                    }
                    $new_checked_change_log_index = $new_checked_change_log_index + 1;
                    break;
                }
            }
            $eob_paras = array("benefit_OOP", "benefit_OOP_remaining", "benefit_deductible", "benefit_co_insurance", "benefit_date_taken");
            foreach ($eob_paras as $eob_para) {
                $cur_eob = $this->getRequest()->getPost($eob_para);
                $last_eob = $this->getRequest()->getPost("last_" . $eob_para);
                if ($cur_eob != $last_eob) {
                    $interactionlogs[$new_checked_change_log_index] = array();
                    $interactionlogs[$new_checked_change_log_index]["claim_id"] = $claim_id;
                    $interactionlogs[$new_checked_change_log_index]["date_and_time"] = date("m/d/Y H:i:s");
                    $interactionlogs[$new_checked_change_log_index]["log"] = $user_name . ": Changed Benifits From ";
                    foreach ($eob_paras as $eob_para_tmp) {
                        if ($eob_para_tmp == "EOB_adjustment_reason") {
                            $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost("last_" . $eob_para_tmp);
                        } else {
                            $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost("last_" . $eob_para_tmp) . "|";
                        }
                    }
                    $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . " to ";
                    foreach ($eob_paras as $eob_para_tmp) {
                        if ($eob_para_tmp == "EOB_adjustment_reason") {
                            $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp);
                        } else {
                            $interactionlogs[$new_checked_change_log_index]["log"] = $interactionlogs[$new_checked_change_log_index]["log"] . $this->getRequest()->getPost($eob_para_tmp) . "|";
                        }
                    }
                    $new_checked_change_log_index = $new_checked_change_log_index + 1;
                    break;
                }
            }
            //array_splice($interactionlogs, $indexLog+$count_interactionlogs);
            array_splice($interactionlogs, $new_checked_change_log_index);
            /* Remove the change below as it is not the root cause */
            //if($_SESSION['new_claim_flag'])
            //{
            //    $_SESSION['interactionlogs_data'] = $interactionlogs;
            //}
            $assigned_user_id = $this->getRequest()->getPost('assigned_user_id');
            $assignedclaims['assignee'] = $assigned_user_id;

            $length_fup = strlen($followups['date_of_next_followup']);
            if (strstr($claim['claim_status'], 'open_follow_up')) {
                if ($length_fup == 0) {

                    if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                        session_start();
                        $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                        $_SESSION['invalid_data']['claim_data'] = $claim;
                        $_SESSION['invalid_data']['followups_data'] = $followups;
                        $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                        $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                        $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                        $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                        $_SESSION['invalid_data']['payments_data'] = $payments;
                        $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                    }
                    $ret = 'date_of_next_followup';
                    $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
                }
            }
            if (strlen($followups['date_of_next_followup']) != 10 && strlen($followups['date_of_next_followup']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_of_next_followup';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['date_secondary_insurance_billed']) != 10 && strlen($claim['date_secondary_insurance_billed']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_secondary_insurance_billed';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['benefit_date_taken']) != 10 && strlen($claim['benefit_date_taken'] > 0)) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'benefit_date_taken';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['date_billed']) != 10 && strlen($claim['date_billed']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_billed';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['date_last_billed']) != 10 && strlen($claim['date_last_billed']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_last_billed';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['date_creation']) != 10 && strlen($claim['date_creation']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_creation';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($claim['date_closed']) != 10 && strlen($claim['date_closed']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_closed';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }

            if (strlen($claim['benefit_date_taken']) != 10 && strlen($claim['benefit_date_taken']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'benefit_date_taken';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }

            $service_count = $this->getRequest()->getPost("service_count");
            for ($i = 0; $i < $count_payment; $i++) {
                $sum = 0;
                for ($j = 0; $j < $service_count; $j++) {
                    $v = "service_" . ($i + 1) . "_" . ($j + 1) . "_payment";
                    $payment_service = $this->getRequest()->getPost($v);
                    if ($payment_service == '') {
                        $payment_service = 0;
                    }
                    $sum+=$payment_service;
                }
                $ret = "payment_amount_" . ($i + 1);
                if ($payments[$i]['amount'] != $sum && $sum != 0) {
                    // $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
                    if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                        session_start();
                        $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                        $_SESSION['invalid_data']['claim_data'] = $claim;
                        $_SESSION['invalid_data']['followups_data'] = $followups;
                        $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                        $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                        $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                        $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                        $_SESSION['invalid_data']['payments_data'] = $payments;
                        $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                    }
                    $this->_redirect('/biller/claims/claim?invalid=amount_' . ($i + 1));
                }
                if ($payments[$i]['amount'] != null) {
                    if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                        session_start();
                        $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                        $_SESSION['invalid_data']['claim_data'] = $claim;
                        $_SESSION['invalid_data']['followups_data'] = $followups;
                        $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                        $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                        $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                        $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                        $_SESSION['invalid_data']['payments_data'] = $payments;
                        $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                    }

                    if (strlen($payments[$i]['from']) == 0) {
                        // $ret='payment_from_'.($i+1);
                        $this->_redirect('/biller/claims/claim?invalid=from_' . ($i + 1));
                    }
                }
            }
//            if(strlen($claim['date_insurance_payment_issued'])!=10&&strlen($claim['date_insurance_payment_issued'])>0){
//                $ret='date_insurance_payment_issued';
//                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
//            }
//            if(strlen($claim['date_insurance_payment_received'])!=10&&strlen($claim['date_insurance_payment_received'])>0){
//                $ret='date_insurance_payment_received';
//                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
//            }
            if (strlen($followups['delivery_date']) != 10 && strlen($followups['delivery_date']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'delivery_date';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if (strlen($followups['date_negotiated_amount_reached']) != 10 && strlen($followups['date_negotiated_amount_reached']) > 0) {
                if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                    session_start();
                    $_SESSION['invalid_data']['claim']['invalid_flag'] = 1;
                    $_SESSION['invalid_data']['claim_data'] = $claim;
                    $_SESSION['invalid_data']['followups_data'] = $followups;
                    $_SESSION['invalid_data']['insurancepayments_data'] = $insurancepayments;
                    $_SESSION['invalid_data']['patientpayments_data'] = $patientpayments;
                    $_SESSION['invalid_data']['billeradjustments_data'] = $billeradjustments;
                    $_SESSION['invalid_data']['interactionlogs_data'] = $interactionlogs;
                    $_SESSION['invalid_data']['payments_data'] = $payments;
                    $_SESSION['invalid_data']['assignedclaims_data'] = $assignedclaims;
                }
                $ret = 'date_negotiated_amount_reached';
                $this->_redirect('/biller/claims/claim/nullcheck/' . $ret);
            }
            if ($claim['claim_status'] != null && $claim['claim_status'] != "" && $claim['claim_status'] != -1) {
                session_start();

                $_SESSION['claim_data'] = $claim;
                $_SESSION['followups_data'] = $followups;
                $_SESSION['insurancepayments_data'] = $insurancepayments;
                $_SESSION['patientpayments_data'] = $patientpayments;
                $_SESSION['billeradjustments_data'] = $billeradjustments;
                $_SESSION['interactionlogs_data'] = $interactionlogs;
                $_SESSION['payments_data'] = $payments;
                $_SESSION['assignedclaims_data'] = $assignedclaims;
                unset($_SESSION['invalid_data']);
            }
            $submitType = $this->getRequest()->getParam('submit');
            $this->_redirect('/biller/claims/' . $this->navigation($submitType, 3));
        }
    }

    public function dropdownAction() {
        $patient_id = $_GET['patient_id'];
        $encounter_id = $_GET['encounter_id'];
        $type = $_GET['type'];

        if ($patient_id != null) {
            $this->initsession($patient_id, $encounter_id);
        }
        if ($type == "claim") {
            $this->_redirect('/biller/claims/claim');
        } else if ($type == "services") {
            $this->_redirect('/biller/claims/services');
        } else if ($type == "patient") {
            $this->_redirect('/biller/claims/patient');
        } else if ($type == "insurance") {
            $this->_redirect('/biller/claims/insurance');
        } else if ($type == "documents") {
            $this->_redirect('/biller/claims/documents');
        }
    }

    public function needsaveAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $_SESSION['claim_data']['needsave'] = 1;
    }

    public function getguarantorfromidAction() {
        $this->_helper->viewRenderer->setNoRender();
        $guarantor_id = $this->getRequest()->getPost('guarantor_id');
        $db_guarantor = new Application_Model_DbTable_Guarantor();
        $db = $db_guarantor->getAdapter();
        $where = $db->quoteInto('id = ?', $guarantor_id);
        $guarantor = $db_guarantor->fetchRow($where)->toArray();
        $data = array();
        //$data["last_name"] = $guarantor["last_name"];
        if ($guarantor != "no" && $guarantor != "" && $guarantor != null) {
            $guarantor['DOB'] = format($guarantor['DOB'], 1);
            $guarantor['SSN'] = ssn($guarantor['SSN']);
            $guarantor['zip'] = zip($guarantor['zip']);
            $guarantor['phone_number'] = phone($guarantor['phone_number']);
            $guarantor['second_phone_number'] = phone($guarantor['second_phone_number']);
        }
        $data = $guarantor;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function claiminputAction() {

        $this->_helper->viewRenderer->setNoRender();
        session_start();
//       require_once($this->getRequest()->getBaseUrl());
        $invalid_flag = $_SESSION['invalid_data']['claim']['invalid_flag'];
        if ($invalid_flag == 1) {
            $followups_data = $_SESSION['invalid_data']['followups_data'];
            $claim_data = $_SESSION['invalid_data']['claim_data'];
            $payments_data = $_SESSION['invalid_data']['payments_data'];
        } else {
            $followups_data = $_SESSION['followups_data'];
            $claim_data = $_SESSION['claim_data'];
            $payments_data = $_SESSION['payments_data'];
        }
        //$insured_data = $_SESSION['insured_data'];
        $insurance_data = $_SESSION['insurance_data'];
        $patient_data = $_SESSION['patient_data']; //add by qiao 09/27/2011

        $need_save = $_SESSION['claim_data']['needsave'];
        $payments_count = count($payments_data);
        for ($i = 0; $i < $payments_count; $i++) {
            $payments_data[$i]['datetime'] = format($payments_data[$i]['datetime'], 7);
        }
        /*         * *****************Add encounterinsured data******************** */
        $encounterinsured_data = $_SESSION['encounterinsured_data'];
        $insured_data = $_SESSION['insured_data'];
        /*         * *****************Add encounterinsured data******************** */
        $data = array();
        if (isset($_SESSION['claim_data']['mannual_flag']) && ($_SESSION['claim_data']['mannual_flag'] == "no")) {
            $data['mannual_flag'] = 0;
        } else {
            $data['mannual_flag'] = 1;
        }

        $patient_id = $patient_data['id'];
        if ($invalid_flag == 1) {
            $insurancepayments_data = $_SESSION['invalid_data']['insurancepayments_data'];
        } else {
            $insurancepayments_data = $_SESSION['insurancepayments_data'];
        }
        $data['insurance_name'] = $insurance_data['insurance_name'];
        $data['payer_type'] = $insurance_data['payer_type'];
        $data['payments'] = $payments_data;
        //$data['insurancepayments'] = $_SESSION['insurancepayments_data'];
        //$data['patientpayments'] = $_SESSION['patientpayments_data'];
        //$data['billeradjustments'] = $_SESSION['billeradjustments_data'];
        if ($invalid_flag == 1) {
            $interactionlogs = $_SESSION['invalid_data']['interactionlogs_data'];
            $data['interactionlogs'] = $_SESSION['invalid_data']['interactionlogs_data'];
            $assignedclaims = $_SESSION['invalid_data']['assignedclaims_data'];
        } else {
            $interactionlogs = $_SESSION['interactionlogs_data'];
            $data['interactionlogs'] = $_SESSION['interactionlogs_data'];
            $assignedclaims = $_SESSION['assignedclaims_data'];
        }
        $data['assigned_user_id'] = $assignedclaims['assignee'];

        for ($i = 0; $i < count($data['insurancepayments']); $i++) {
//            if(strlen($insurancepayments_data[$i]['date'])==10)
            $datetemp = format($insurancepayments_data[$i]['date'], 1);
//            else
//                $datetemp=$insurancepayments_data[$i]['date'];
            if ($datetemp != null) {
                $data['insurancepayments'][$i]['date'] = $datetemp;
            }
        }

        for ($i = 0; $i < count($data['patientpayments']); $i++) {
//            if(strlen($data['patientpayments'][$i]['date'])==10)
            $datetemp = format($data['patientpayments'][$i]['date'], 1);
//            else
//                $datetemp=$data['patientpayments'][$i]['date'];
            if ($datetemp != null) {
                $data['patientpayments'][$i]['date'] = $datetemp;
            }
        }

        for ($i = 0; $i < count($data['billeradjustments']); $i++) {

//            if(strlen($data['billeradjustments'][$i]['date'])==10)
            $datetemp = format($data['billeradjustments'][$i]['date'], 1);
//            else
//                $datetemp=$data['billeradjustments'][$i]['date'];
            if ($datetemp != null) {
                $data['billeradjustments'][$i]['date'] = $datetemp;
            }
        }
        $date_last = null;
        for ($i = 0; $i < count($data['interactionlogs']); $i++) {
            $datetemp = format($data['interactionlogs'][$i]['date_and_time'], 7);
            if ($datetemp != null) {
                if ($i == 0)
                    $date_last = $datetemp;
                if ($date_last > $datetemp)
                    $date_last = $datetemp;
                $data['interactionlogs'][$i]['date_and_time'] = $datetemp;
            }
        }
        $interactionlogs = $data['interactionlogs'];
        //add the patient logs which should show up in the claimlog 
        $patientlogs = $_SESSION['patientlogs'];
        $count_patientlogs = count($patientlogs);
        $j = 0;
        $patientlogs_show = array();
        for ($i = 0; $i < $count_patientlogs; $i++) {
            $date_sort[$i] = $patientlogs[$i]['date_and_time'];
            $temp = date('m/d/Y H:i:s', strtotime($date_sort[$i]));
            if (strtotime($temp) >= strtotime($date_last)) {
                $patientlogs_show[$j] = $patientlogs[$i];
                $patientlogs_show[$j]['date_and_time'] = $temp;
                $j++;
            }
        }
        $data['patientlogs_show'] = $patientlogs_show;
        // sort the patient_log and claim_log together according to the time
        $time_array = array();
        $logs_together = array();
        $m = 0;
        foreach ($interactionlogs as $row) {
            $logs_together[$m] = $row;
            $logs_together[$m]['type'] = 'claim';
            $time_array[$m] = strtotime($row['date_and_time']);
            $m++;
        }
        foreach ($patientlogs_show as $row) {
            $logs_together[$m] = $row;
            $logs_together[$m]['type'] = 'patient';
            $time_array[$m] = strtotime($row['date_and_time']);
            $m++;
        }
        array_multisort($time_array, SORT_ASC, $logs_together);
        $data['logs_together'] = $logs_together;
        /*         * *****************Add 4 Custom fields By Yu Lang******************** */
        $encounter_data = $_SESSION['encounter_data'];
        $provider_id = $encounter_data['provider_id'];

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id = ?', $provider_id);
        $provider_data = $db_provider->fetchRow($where);

        $options_id = $provider_data['options_id'];

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('id = ?', $options_id);
        $options_data = $db_options->fetchRow($where);

        $data['custom_label_1'] = $options_data['custom_label_1'];
        $data['custom_label_2'] = $options_data['custom_label_2'];
        $data['custom_label_3'] = $options_data['custom_label_3'];
        $data['custom_label_4'] = $options_data['custom_label_4'];

        for ($i = 1; $i < 5; $i++) {
            if (strstr($options_data['custom_label_' . $i . ''], ':')) {
                $custom_label_name = strstr($options_data['custom_label_' . $i . ''], ':', TRUE);
                $custom_label_str = explode(':', $options_data['custom_label_' . $i . '']);
                $custom_label_content = $custom_label_str[1];
                $custom_label_flag = 1;
                $tp_custom_List = explode('|', $custom_label_content);
                $mark = 0;
                foreach ($tp_custom_List as $row) {
                    $custom_List[$mark]['value'] = $row;
                    $mark ++;
                }
                $data['custom_label_' . $i . '_name'] = $custom_label_name;
                $data['custom_label_' . $i . '_flag'] = $custom_label_flag;
                $data['custom_' . $i . '_List'] = $custom_List;
            }
        }

        $exclude_payment = parse_tag($options_data['tags']);
        $data['exclude_coinsurance'] = ($exclude_payment['exclude_coinsurance'] === 'yes') ? '1' : '0';
        $data['exclude_deductible'] = ($exclude_payment['exclude_deductible'] === 'yes') ? '1' : '0';
        $data['exclude_reduction'] = ($exclude_payment['exclude_reduction'] === 'yes') ? '1' : '0';
        $data['exclude_otherreduction'] = ($exclude_payment['exclude_otherreduction'] === 'yes') ? '1' : '0';

        $data['custom_1'] = $claim_data['custom_1'];
        $data['custom_2'] = $claim_data['custom_2'];
        $data['custom_3'] = $claim_data['custom_3'];
        $data['custom_4'] = $claim_data['custom_4'];

        /*         * *****************Add 4 Custom fields By Yu Lang******************** */


        $tv = strlen($followups_data['date_of_next_followup']);
        if ($tv == 10) {
            $date_of_next_followup = format($followups_data['date_of_next_followup'], 1);
        } else {
            $date_of_next_followup = $followups_data['date_of_next_followup'];
        }
        if (strlen($claim_data['date_last_checked_with_attorney']) == 10) {
            $date_last_checked_with_attorney = format($claim_data['date_last_checked_with_attorney'], 1);
        } else {
            $date_last_checked_with_attorney = $claim_data['date_last_checked_with_attorney'];
        }
        if (strlen($followups_data['delivery_date']) == 10)
            $delivery_date = format($followups_data['delivery_date'], 1);
        else
            $delivery_date = $followups_data['delivery_date'];
//        if(strlen($followups_data['date_negotiated_amount_reached'])==10)
        $date_negotiated_amount_reached = format($followups_data['date_negotiated_amount_reached'], 1);
//        else
//            $date_negotiated_amount_reached=$followups_data['date_negotiated_amount_reached'];
//        if(strlen($followups_data['date_initial_offer'])==10)
        $date_initial_offer = format($followups_data['date_initial_offer'], 1);
//        else
//            $date_initial_offer=$followups_data['date_initial_offer'];
//        if(strlen($followups_data['negotiated_payment_amount']==10))
//            $negotiated_amount_reached = format($followups_data['negotiated_payment_amount'], 1);
//        else
//            $negotiated_amount_reached=$followups_data['negotiated_payment_amount'];
//        $date_negotiated_amount_reached = format($followups_data['date_negotiated_amount_reached'], 1);
//        $date_initial_offer = format($followups_data['date_initial_offer'], 1);

        $data['followup_id'] = $followups_data['id'];
        $interaction = array();
        $date_sort = array();
        for ($i = 1; $i <= 8; $i++) {
            $date_sort[$i - 1] = strtotime($followups_data['date_and_time_' . $i]);
            $interaction[$i - 1]['date'] = format($followups_data['date_and_time_' . $i], 4);
            $interaction[$i - 1]['log'] = $followups_data['log_' . $i];
        }
        array_multisort($date_sort, SORT_DESC, $interaction, SORT_DESC);
        for ($i = 1; $i <= 8; $i++) {
//            $data['date_and_time_' . $i] = format($followups_data['date_and_time_' . $i], 4);
            $data['date_and_time_' . $i] = $interaction[$i - 1]['date'];
            $temp = date('m/d/Y H:i:s', $date_sort[$i - 1]);
            $data['hidden_date_and_time_' . $i] = $temp;
            /* delete the who_number_and_ref_number_ 2012/7/24 */
//            $data['who_number_and_ref_number_' . $i] = $followups_data['who_number_and_ref_number_' . $i];
//            $data['log_' . $i] = $followups_data['log_' . $i];
            $data['log_' . $i] = $interaction[$i - 1]['log'];
        }
        $data['date_of_next_followup'] = $date_of_next_followup;
        $data['date_last_checked_with_attorney'] = $date_last_checked_with_attorney;
        $data['delivery_date'] = $delivery_date;
        $data['notes'] = $followups_data['notes'];
        $data['negotiated_payment_amount'] = $followups_data['negotiated_payment_amount'];
        $data['date_negotiated_amount_reached'] = $date_negotiated_amount_reached;
        $data['amount_initial_offer'] = $followups_data['amount_initial_offer'];
        $data['date_initial_offer'] = $date_initial_offer;

        if (strlen($claim_data['date_creation']) == 10)
            $date_creation = format($claim_data['date_creation'], 1);
        else
            $date_creation = $claim_data['date_creation'];

        if (strlen($claim_data['date_billed']) == 10)
            $date_billed = format($claim_data['date_billed'], 1);
        else
            $date_billed = $claim_data['date_billed'];

        if (strlen($claim_data['date_last_billed']) == 10)
            $date_last_billed = format($claim_data['date_last_billed'], 1);
        else
            $date_last_billed = $claim_data['date_last_billed'];
        /*         * ********Date secondary billed By<Yu Lang>************* */
        if (strlen($claim_data['date_secondary_insurance_billed']) == 10) {
            $date_secondary_insurance_billed = format($claim_data['date_secondary_insurance_billed'], 1);
        } else {
            $date_secondary_insurance_billed = $claim_data['date_secondary_insurance_billed'];
        }
        if (strlen($claim_data['date_closed']) == 10) {
            $date_closed = format($claim_data['date_closed'], 1);
        } else {
            $date_closed = $claim_data['date_closed'];
        }
        if (strlen($claim_data['benefit_date_taken']) == 10) {
            $benefit_date_taken = format($claim_data['benefit_date_taken'], 1);
        } else {
            $benefit_date_taken = $claim_data['benefit_date_taken'];
        }
//        if(strlen($claim_data['date_insurance_payment_issued'])==10){
        $date_insurance_payment_issued = format($claim_data['date_insurance_payment_issued'], 1);
        ;
//        }else{
//            $date_insurance_payment_issued=$claim_data['date_insurance_payment_issued'];
//        }
        if (strlen($claim_data['date_insurance_payment_received']) == 10) {
            $date_insurance_payment_received = format($claim_data['date_insurance_payment_received'], 1);
        } else {
            $date_insurance_payment_received = $claim_data['date_insurance_payment_received'];
        }

        /*         * ********Date secondary billed By<Yu Lang>************* */
//        $date_closed = format($claim_data['date_closed'], 1);
//        $benefit_date_taken = format($claim_data['benefit_date_taken'], 1);
//        $date_insurance_payment_issued = format($claim_data['date_insurance_payment_issued'], 1);
//        $date_insurance_payment_received = format($claim_data['date_insurance_payment_received'], 1);

        /* add the new claim_status by PanDazhao 2012/7/25 */
        $future_service = false;

        $start_date_1 = format($encounter_data['start_date_1'], 1);
        if ($start_date_1 == false) {
            $future_service = true;
        } else {
            $currentTime = date("m/d/Y");
            list($month1, $day1, $year1) = explode("/", $start_date_1);
            list($month2, $day2, $year2) = explode("/", $currentTime);
            $start_date_1 = mktime(0, 0, 0, $month1, $day1, $year1);
            $currentTime = mktime(0, 0, 0, $month2, $day2, $year2);
            $time_difference = $currentTime - $start_date_1;
            if ($time_difference < 0) {
                $future_service = true;
            }
        }

        /* if ($future_service == true) {
          $claim_status = 'inactive_future_service';
          } else {
          if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] != null && $insurance_data['payer_type'] != 'MM' && $insurance_data['payer_type'] != 'SP') {
          //$claim_status = 'open_ready_primary_bill';
          $bill_status = 'bill_ready_bill_primary';
          }
          if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] == 'MM') {
          //$claim_status = 'open_ready_delayed_primary_bill';
          $bill_status = 'bill_ready_bill_delayed_primary';
          }
          if (($claim_data['claim_status'] == null||$claim_data['claim_status'] =='inactive_future_service') && $insurance_data['payer_type'] == 'SP') {
          $claim_status = 'inactive_selfpay';
          }
          if ($claim_data['claim_status'] != null&&$claim_data['claim_status'] !='inactive_future_service')
          $claim_status = $claim_data['claim_status'];
          } */
        $new_claim_flag = $_SESSION['new_claim_flag'];
        $patient_id = $_SESSION['patient_data']['id'];
        if ($patient_id != "" && $patient_id != null) {
            if ($new_claim_flag == 0) {
                $guarantor = $_SESSION['guarantor_data'];
            } else {
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('claim', array('id as c_id', 'guarantor_id'));
                $select->join('encounter', 'encounter.claim_id = claim.id', array('encounter.id as e_id'));
                $select->where('encounter.patient_id = ?', $patient_id);
                $select->order('claim.id DESC');
                $patienthasclaim = $db->fetchAll($select);
                $guarantor_id = $patienthasclaim[0]["guarantor_id"];
                if ($guarantor_id != null && $guarantor_id != "") {
                    $db_guarantor = new Application_Model_DbTable_Guarantor();
                    $db = $db_guarantor->getAdapter();
                    $where = $db->quoteInto('id = ?', $guarantor_id);
                    $guarantor = $db_guarantor->fetchRow($where)->toArray();
                } else {
                    $guarantor = "no";
                }
            }
        } else {
            $guarantor = "no";
        }
        if ($guarantor != "no" && $guarantor != "" && $guarantor != null) {
            $guarantor['DOB'] = format($guarantor['DOB'], 1);
            $guarantor['SSN'] = ssn($guarantor['SSN']);
            $guarantor['zip'] = zip($guarantor['zip']);
            $guarantor['phone_number'] = phone($guarantor['phone_number']);
            $guarantor['second_phone_number'] = phone($guarantor['second_phone_number']);
        }
        $data['guarantor'] = $guarantor;
        $data['id'] = $claim_data['id'];
        $data['is_the_issued_payment_sent_to_patient'] = $claim_data['is_the_issued_payment_sent_to_patient'];
        $data['total_charge'] = $claim_data['total_charge'];
        $data['amount_paid'] = $claim_data['amount_paid'];
        $data['balance_due'] = $claim_data['balance_due'];
//        $data['claim_status'] = $claim_data['claim_status'];
        //$data['claim_status'] = $claim_status;
        $data['claim_status'] = $claim_data['claim_status'];
        $data['bill_status'] = $claim_data['bill_status'];
        $data['statement_status'] = $claim_data['statement_status'];
        $data['expected_payment'] = $claim_data['expected_payment'];
        $data['date_billed'] = $date_billed;
        $data['date_last_billed'] = $date_last_billed;
        /*         * ********Date secondary billed By<Yu Lang>************* */
        $data['date_secondary_insurance_billed'] = $date_secondary_insurance_billed;
        /*         * ********Date secondary billed By<Yu Lang>************* */
        $data['date_closed'] = $date_closed;
        $data['date_creation'] = $date_creation;
        $data['amount_insurance_payment_issued'] = $claim_data['amount_insurance_payment_issued'];
        $data['date_insurance_payment_issued'] = $date_insurance_payment_issued;
        $data['EOB_co_insurance'] = $claim_data['EOB_co_insurance'];
        $data['EOB_deductable'] = $claim_data['EOB_deductable'];


        /*         * ********Add two field for the Claim By<Yu Lang>************* */
        $data['EOB_reduction'] = $claim_data['EOB_reduction'];
        $data['EOB_other_reduction'] = $claim_data['EOB_other_reduction'];
        /*         * ********Add two field for the Claim By<Yu Lang>************* */

        $data['EOB_allowed_amount'] = $claim_data['EOB_allowed_amount'];
        $data['EOB_not_allowed_amount'] = $claim_data['EOB_not_allowed_amount'];
        $data['EOB_adjustment_reason'] = $claim_data['EOB_adjustment_reason'];
        $data['benefit_OOP'] = $claim_data['benefit_OOP'];
        $data['benefit_OOP_remaining'] = $claim_data['benefit_OOP_remaining'];
        $data['benefit_deductible'] = $claim_data['benefit_deductible'];
        $data['benefit_deductible_remaining'] = $claim_data['benefit_deductible_remaining'];
        $data['benefit_co_insurance'] = $claim_data['benefit_co_insurance'];
        $data['benefit_date_taken'] = $benefit_date_taken;

        $data['document_dir_avaiable'] = is_dir($dir);
        $data['needsave'] = $need_save;
        $data['color_code'] = $claim_data['color_code'];
        //pc part
        $value_array = explode("|", $claim_data['patientcorrespondence']);
        $data['patientcorrespondence'] = $value_array;
        $encounter_id = $encounter_data['id'];

        $dd = 0;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function userfocusonproviderAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();

        $data = array();
        $data['userfocusonprovider'] = $userfocusonprovider;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function existpatientAction() {
        $this->_helper->viewRenderer->setNoRender();
        $name = $_POST['name'];
        if ($name == null) {
            $last_name = null;
            $first_name = null;
        } else {
            $count = substr_count($name, ",");
            if ($count == 0) {
                $last_name = trim($name);
                $first_name = null;
            }
            if ($count == 1) {
                $totalname = explode(",", $name);
                if ($totalname[0] == "") {
                    $last_name = null;
                } else {
                    $last_name = trim($totalname[0]);
                }
                if ($totalname[1] == "") {
                    $first_name = null;
                } else {
                    $first_name = trim($totalname[1]);
                }
            }
        }




        $account_number = $_POST['account_number'];
        $phone_num = $_POST['phone_num'];
        $DOB = $_POST['DOB'];
        $count = substr_count($DOB, "-");
        if ($count == 0) {
            $min_DOB = $DOB;
            $max_DOB = null;
            $min_DOBArray = explode("/", $min_DOB);
            $year = $min_DOBArray[2];
            array_pop($min_DOBArray);
            array_unshift($min_DOBArray, $year);
            $min_DOB = implode("-", $min_DOBArray);
        } else {
            $DOBArray = explode("-", $DOB);
            $min_DOB = $DOBArray[0];
            $max_DOB = $DOBArray[1];

            $min_DOBArray = explode("/", $min_DOB);
            $year = $min_DOBArray[2];
            array_pop($min_DOBArray);
            array_unshift($min_DOBArray, $year);
            $min_DOB = implode("-", $min_DOBArray);

            $max_DOBArray = explode("/", $max_DOB);
            $year = $max_DOBArray[2];
            array_pop($max_DOBArray);
            array_unshift($max_DOBArray, $year);
            $max_DOB = implode("-", $max_DOBArray);
        }

        /* cici */
        $DOP = $_POST['DOP'];
        $count = substr_count($DOP, "-");
        if ($count == 0) {
            $min_DOP = $DOP;
            $max_DOP = null;
            $min_DOPArray = explode("/", $min_DOP);
            $year = $min_DOPArray[2];
            array_pop($min_DOPArray);
            array_unshift($min_DOPArray, $year);
            $min_DOP = implode("-", $min_DOPArray);
        } else {
            $DOPArray = explode("-", $DOP);
            $min_DOP = $DOPArray[0];
            $max_DOP = $DOPArray[1];

            $min_DOPArray = explode("/", $min_DOP);
            $year = $min_DOPArray[2];
            array_pop($min_DOPArray);
            array_unshift($min_DOPArray, $year);
            $min_DOP = implode("-", $min_DOPArray);

            $max_DOPArray = explode("/", $max_DOP);
            $year = $max_DOPArray[2];
            array_pop($max_DOPArray);
            array_unshift($max_DOPArray, $year);
            $max_DOP = implode("-", $max_DOPArray);
        }

        /* cici */
        $DONF = $_POST['DONF'];
        $count = substr_count($DONF, "-");
        if ($count == 0) {
            $min_DONF = $DONF;
            $max_DONF = null;
            $min_DONFArray = explode("/", $min_DONF);
            $year = $min_DONFArray[2];
            array_pop($min_DONFArray);
            array_unshift($min_DONFArray, $year);
            $min_DONF = implode("-", $min_DONFArray);
        } else {
            $DONFArray = explode("-", $DONF);
            $min_DONF = $DONFArray[0];
            $max_DONF = $DONFArray[1];

            $min_DONFArray = explode("/", $min_DONF);
            $year = $min_DONFArray[2];
            array_pop($min_DONFArray);
            array_unshift($min_DONFArray, $year);
            $min_DONF = implode("-", $min_DONFArray);

            $max_DONFArray = explode("/", $max_DONF);
            $year = $max_DONFArray[2];
            array_pop($max_DONFArray);
            array_unshift($max_DONFArray, $year);
            $max_DONF = implode("-", $max_DONFArray);
        }

        /* cici */
        $DBC = $_POST['DBC'];
        $count = substr_count($DBC, "-");
        if ($count == 0) {
            $min_DBC = $DBC;
            $max_DBC = null;
            $min_DBCArray = explode("/", $min_DBC);
            $year = $min_DBCArray[2];
            array_pop($min_DBCArray);
            array_unshift($min_DBCArray, $year);
            $min_DBC = implode("-", $min_DBCArray);
        } else {
            $DBCArray = explode("-", $DBC);
            $min_DBC = $DBCArray[0];
            $max_DBC = $DBCArray[1];

            $min_DBCArray = explode("/", $min_DBC);
            $year = $min_DBCArray[2];
            array_pop($min_DBCArray);
            array_unshift($min_DBCArray, $year);
            $min_DBC = implode("-", $min_DBCArray);

            $max_DBCArray = explode("/", $max_DBC);
            $year = $max_DBCArray[2];
            array_pop($max_DBCArray);
            array_unshift($max_DBCArray, $year);
            $max_DBC = implode("-", $max_DBCArray);
        }

        /* cici */
        $date_billed = $_POST['date_billed'];
        $count = substr_count($date_billed, "-");
        if ($count == 0) {
            $min_date_billed = $date_billed;
            $max_date_billed = null;
            $min_date_billedArray = explode("/", $min_date_billed);
            $year = $min_date_billedArray[2];
            array_pop($min_date_billedArray);
            array_unshift($min_date_billedArray, $year);
            $min_date_billed = implode("-", $min_date_billedArray);
        } else {
            $date_billedArray = explode("-", $date_billed);
            $min_date_billed = $date_billedArray[0];
            $max_date_billed = $date_billedArray[1];

            $min_date_billedArray = explode("/", $min_date_billed);
            $year = $min_date_billedArray[2];
            array_pop($min_date_billedArray);
            array_unshift($min_date_billedArray, $year);
            $min_date_billed = implode("-", $min_date_billedArray);

            $max_date_billedArray = explode("/", $max_date_billed);
            $year = $max_date_billedArray[2];
            array_pop($max_date_billedArray);
            array_unshift($max_date_billedArray, $year);
            $max_date_billed = implode("-", $max_date_billedArray);
        }

        /* cici */
        $date_closed = $_POST['date_closed'];
        $count = substr_count($date_closed, "-");
        if ($count == 0) {
            $min_date_closed = $date_closed;
            $max_date_closed = null;
            $min_date_closedArray = explode("/", $min_date_closed);
            $year = $min_date_closedArray[2];
            array_pop($min_date_closedArray);
            array_unshift($min_date_closedArray, $year);
            $min_date_closed = implode("-", $min_date_closedArray);
        } else {
            $date_closedArray = explode("-", $date_closed);
            $min_date_closed = $date_closedArray[0];
            $max_date_closed = $date_closedArray[1];

            $min_date_closedArray = explode("/", $min_date_closed);
            $year = $min_date_closedArray[2];
            array_pop($min_date_closedArray);
            array_unshift($min_date_closedArray, $year);
            $min_date_closed = implode("-", $min_date_closedArray);

            $max_date_closedArray = explode("/", $max_date_closed);
            $year = $max_date_closedArray[2];
            array_pop($max_date_closedArray);
            array_unshift($max_date_closedArray, $year);
            $max_date_closed = implode("-", $max_date_closedArray);
        }


        /*     if ($min_DOB != null)
          $min_DOB  = date("Y-m-d", strtotime('-' . $min_DOB . ' day'));
          if ($max_DOB != null)
          $max_DOB  = date("Y-m-d", strtotime('-' . $max_DOB . ' day')); */

        /* $DOBArray = explode("/", $DOB);
          $year = $DOBArray[2];
          array_pop($DOBArray);
          array_unshift($DOBArray, $year);
          $DOB = implode("-", $DOBArray); */

        $renderingprovider_id = $_POST['renderingprovider_id'];
        $referringprovider_id = $_POST['referringprovider_id'];
        $last_days = $_POST['last_days'];


        $total_charge = $_POST['total_charge'];
        if ($total_charge == null) {
            $min_charge = null;
            $max_charge = null;
        } else {
            $count = substr_count($total_charge, "-");
            if ($count == 0) {
                $min_charge = trim($total_charge);
                $max_charge = null;
            }
            if ($count == 1) {
                $total_charge = explode("-", $total_charge);
                if ($total_charge[0] == "") {
                    $min_charge = null;
                } else {
                    $min_charge = trim($total_charge[0]);
                }
                if ($total_charge[1] == "") {
                    $max_charge = null;
                } else {
                    $max_charge = trim($total_charge[1]);
                }
            }
        }

        $amount_paid = $_POST['amount_paid'];
        if ($amount_paid == null) {
            $min_paid = null;
            $max_paid = null;
        } else {
            $count = substr_count($amount_paid, "-");
            if ($count == 0) {
                $min_paid = trim($amount_paid);
                $max_paid = null;
            }
            if ($count == 1) {
                $amount_paid = explode("-", $amount_paid);
                if ($amount_paid[0] == "") {
                    $min_paid = null;
                } else {
                    $min_paid = trim($amount_paid[0]);
                }
                if ($amount_paid[1] == "") {
                    $max_paid = null;
                } else {
                    $max_paid = trim($amount_paid[1]);
                }
            }
        }

        $DOS = $_POST['DOS'];
        $count = substr_count($DOS, "-");
        if ($count == 0) {
            $start_date = $DOS;
            $end_date = null;
            $start_dateArray = explode("/", $start_date);
            $year = $start_dateArray[2];
            array_pop($start_dateArray);
            array_unshift($start_dateArray, $year);
            $start_date = implode("-", $start_dateArray);
        } else {
            $DOSArray = explode("-", $DOS);
            $start_date = $DOSArray[0];
            $end_date = $DOSArray[1];

            $start_dateArray = explode("/", $start_date);
            $year = $start_dateArray[2];
            array_pop($start_dateArray);
            array_unshift($start_dateArray, $year);
            $start_date = implode("-", $start_dateArray);

            $end_dateArray = explode("/", $end_date);
            $year = $end_dateArray[2];
            array_pop($end_dateArray);
            array_unshift($end_dateArray, $year);
            $end_date = implode("-", $end_dateArray);
        }


        $color_code = $_POST['color_code']; /* CICI */
        $anesthesia_id_array = $_POST['anesthesia_id_array'];
        $claim_status_array = $_POST['claim_status_array'];
        $statement_status_array = $_POST['statement_status_array'];
        $cptcode_array = $_POST['cptcode_array'];
        $diagnosisid_array = $_POST['diagnosisid_array']; /* cici */
        $bill_status_array = $_POST['bill_status_array'];
        $insurance_id_array = $_POST['insurance_id_array'];
        //  $end_date = $_POST['end_date'];cici
        $provider_id_array = $_POST['provider_id_array'];
        $insurance_type_array = $_POST['insurance_type_array'];
        $biller_array = $_POST['biller_array']; //cici

        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        $providerList_start = array();
        for ($i = 0; $i < count($userfocusonprovider); $i++) {
            $providerList_start[$i] = $userfocusonprovider[$i]['provider_id'];
        }
        $this->clearsession();
        $this->unset_options_session();
//                $provider_id_array = $this->getRequest()->getPost('provider_id_array');
//                $renderingprovider_id = $this->getRequest()->getPost('renderingprovider_id');
//                $referringprovider_id = $this->getRequest()->getPost('referringprovider_id');
//                $last_name = $this->getRequest()->getPost('last_name');
//                /*             * *********Add the inqiry for the first name************* */
//                $first_name = $this->getRequest()->getPost('first_name');
//                /*             * *********Add the inquiry for the first name************* */
//                $facility_id = $this->getRequest()->getPost('facility_id');
//    //            $insurance_id = $this->getRequest()->getPost('insurance_id');
//    //            $claim_status = $this->getRequest()->getPost('claim_status');
//                $insurance_id_array = $this->getRequest()->getPost('insurance_id_array');
//                $insurance_type_array = $this->getRequest()->getPost('insurance_type_array');
//                $anesthesia_id_array = $this->getRequest()->getPost('anesthesia_id_array');
//                $claim_status_array = $this->getRequest()->getPost('claim_status_array');
//                $cptcode_array = $this->getRequest()->getPost('cptcode_array');
//                $account_number = $this->getRequest()->getPost('account_number');
//                $bill_status_array=$this->getRequest()->getPost('bill_status_array');
//                $statement_status_array=$this->getRequest()->getPost('statement_status_array');
        $last_date = null;
        $last_days = $this->getRequest()->getPost("last_days");
        if ($last_days != null) {
            $last_date = date("Y-m-d", strtotime('-' . $last_days . ' day'));
        }


        $provider_id = array();
        $insurance_id = array();
        $anesthesia_id = array();
        $claim_status = array();
        $bill_status = array();
        $statement_status = array();
        $cpt_code = array();
        $diagnosis_code = array(); /* cici */
        $insurance_type = array();
        $biller_name = array(); //cici
        if (strlen($bill_status_array) > 0) {
            $bill_status = explode(',', $bill_status_array);
        }
        if (strlen($statement_status_array) > 0) {
            $statement_status = explode(',', $statement_status_array);
        }
        if (strlen($insurance_type_array) > 0) {
            $insurance_type = explode(',', $insurance_type_array);
        }
        if (strlen($cptcode_array) > 0) {
            $cpt_code = explode(',', $cptcode_array);
        }
        if (strlen($diagnosisid_array) > 0) { /* cc */
            $diagnosis_code = explode(',', $diagnosisid_array);
        }

        if (strlen($insurance_id_array) > 0) {
            $insurance_id = explode(',', $insurance_id_array);
        }
        if (strlen($anesthesia_id_array) > 0) {
            $anesthesia_id = explode(',', $anesthesia_id_array);
        }
        if (strlen($claim_status_array) > 0) {
            $claim_status = explode(',', $claim_status_array);
        }

        if (strlen($provider_id_array) > 0) {
            $provider_id = explode(',', $provider_id_array);
        }
        //cici
//        if ($start_date != null)
//            $start_date = format($start_date, 0);
//        if ($end_date != null)
//            $end_date = format($end_date, 0);
//            
//                 if ($this->getRequest()->getPost('max_charge') != null)
//                    $max_charge = $this->getRequest()->getPost('max_charge');
//                if ($this->getRequest()->getPost('min_charge') != null)
//                    $min_charge = $this->getRequest()->getPost('min_charge');
//                 if ($this->getRequest()->getPost('max_paid') != null)
//                    $max_paid = $this->getRequest()->getPost('max_paid');
//                if ($this->getRequest()->getPost('min_paid') != null)
//                    $min_paid = $this->getRequest()->getPost('min_paid');
//                                    
//                $phone_num = $this->getRequest()->getPost('phone_num');
        if ($phone_num != null) {
            if ($phone_num[0] == '(') {
                $find = array('(', ')', '-');
                $phone_num_tmp = str_replace($find, null, $phone_num);
                $phone_num = $phone_num_tmp;
            }
        }
        //cici
        if (strlen($biller_array) > 0) {
            $biller_name = explode(',', $biller_array);
        }

        /*         * ********************Add limit inquiry results*********************** */
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('billingcompany');
        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        $tmp_billingcompany = $db->fetchRow($select);
        $claim_inquiry_results_limit = $tmp_billingcompany['claim_inquiry_results_limit'];
        $limit_number = $claim_inquiry_results_limit + 1;
        /*         * ********************Add limit inquiry results*********************** */


        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('renderingprovider', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('encounter.start_date_1', 'encounter.secondary_CPT_code_1 as anes_code', 'CPT_code_1', 'CPT_code_2', 'CPT_code_3', 'CPT_code_4', 'CPT_code_5', 'CPT_code_6', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id', 'diagnosis_code1', 'diagnosis_code2', 'diagnosis_code3', 'diagnosis_code4'));
        /*         * ********************Add fields***************** */
        $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id', array('referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name'));
        /*         * ********************Add fields***************** */
        $select->join('claim', 'claim.id=encounter.claim_id');
        //cici
        $select->joinLeft('payments', 'claim.id=payments.claim_id', array('datetime', 'claim_id'));
        $select->joinLeft('followups', 'claim.id = followups.claim_id');
        ///yuanlai      $select->joinLeft('interactionlog','claim.id = interactionlog.claim_id');
        $select->joinLeft('interactionlog', 'interactionlog.claim_id=claim.id', array('date_and_time', 'trim(substring(log,1,locate(\':\',log)-1)) as the_biller', 'claim_id'));



        $select->joinLeft('claimstatus', 'claimstatus.claim_status=claim.claim_status', array('CONCAT(\'\',claim_status_display,\'\') AS claim_status_display'));
        $select->joinLeft('billstatus', 'billstatus.bill_status=claim.bill_status', array('CONCAT(\'\',bill_status_display,\'\') AS bill_status_display'));
        $select->joinLeft('statementstatus', 'statementstatus.statement_status=claim.statement_status', array('CONCAT(\'\',statement_status_display,\'\') AS statement_status_display'));



        /*         * ******Using short_name for the facility and provider***** */
        $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));

        /*         * ******Using short_name for the facility and provider***** */

        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'phone_number', 'second_phone_number', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'patient.account_number'));

        /*         * *new for inquiry** */
        //$select->join('insured', 'insured.id=patient.insured_id');
        $select->join('encounterinsured', 'encounterinsured.encounter_id =encounter.id');
        $select->join('insured', 'encounterinsured.insured_id =insured.id');
        /*         * *new for inquiry** */


        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        $select->where('billingcompany.id=?', $this->get_billingcompany_id());

        /* cici */
        $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
        $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
        $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');

        //cici new
        //   $sql="SELECT replace(substring(log,1,locate(':',log)-1),' ','') FROM interactionlog";
        //$select->where('encounterinsured.type = ?',  'primary');

        if ($provider_id != null) {
            $select->where('provider.id IN (?)', $provider_id);
        } else {
            $role = $this->get_user_role();
            if ($role == 'guest')
                $select->where('provider.id IN (?)', $providerList_start);
        }
        if ($claim_status != null) {
            $claim_status_1 = "";
            $claim_status_2 = array();
            for ($i = 0; $i < count($claim_status); $i++) {
                if ($claim_status[$i] == 'all' || $claim_status[$i] == 'open' || $claim_status[$i] == 'closed' || $claim_status[$i] == 'inactive') {
                    $claim_status_1 = $claim_status[$i];
                } else {
                    array_push($claim_status_2, $claim_status[$i]);
                }
            }
            if ($claim_status_1 == 'all')
                $select->where('claim.claim_status LIKE ?', '' . '%');
            if ($claim_status_1 == 'open' || $claim_status_1 == 'closed' || $claim_status_1 == 'inactive') {
                $select->where('claim.claim_status LIKE ?', $claim_status_1 . '%');
            } else {
                $select->where('claim.claim_status IN (?)', $claim_status_2);
            }
        }
        if ($bill_status != null) {
            $select->where('claim.bill_status IN (?)', $bill_status);
        }
        if ($statement_status != null) {
            $select->where('claim.statement_status IN (?)', $statement_status);
        }
        if ($renderingprovider_id != null)
            $select->where('renderingprovider.id=?', $renderingprovider_id);
        if ($referringprovider_id != null)
            $select->where('encounter.referringprovider_id=?', $referringprovider_id);
        if ($insurance_id != null) {
            /* */

            /*  */
            $select->where('insurance.id IN(?)', $insurance_id);
        }
        if ($insurance_type != null)
            $select->where('encounterinsured.type in (?)', $insurance_type);
        if ($anesthesia_id != null) {
            $select->where('encounter.secondary_CPT_code_1 IN(?) OR encounter.secondary_CPT_code_2 IN(?) OR encounter.secondary_CPT_code_3 IN(?) OR encounter.secondary_CPT_code_4 IN(?) OR encounter.secondary_CPT_code_5 IN(?) OR encounter.secondary_CPT_code_6 IN(?)', $anesthesia_id);
        }

        if ($min_DOB != null && $max_DOB == null) {
            $select->where('patient.DOB=?', $min_DOB);
        }
        if ($min_DOB != null && $max_DOB != null) {
            //$select->where('encounter.start_date_1=?', $start_date);
            $select->where('patient.DOB>=?', $min_DOB);
            $select->where('patient.DOB<=?', $max_DOB);
        }
        if ($min_DOP != null && $max_DOP == null) {
            $select->where('payments.datetime LIKE ?', $min_DOP . '%');
        }
        if ($min_DOP != null && $max_DOP != null) {
            $select->where('payments.datetime>=?', $min_DOP);
            $select->where('payments.datetime<=?', $max_DOP);
        }
        if ($min_DONF != null && $max_DONF == null) {
            $select->where('followups.date_of_next_followup=?', $min_DONF . '%');
        }
        if ($min_DONF != null && $max_DONF != null) {
            $select->where('followups.date_of_next_followup>=?', $min_DONF);
            $select->where('followups.date_of_next_followup<=?', $max_DONF);
        }

        if ($min_DBC != null && $max_DBC == null) {
            $select->where('claim.date_creation=?', $min_DBC);
        }
        if ($min_DBC != null && $max_DBC != null) {
            $select->where('claim.date_creation>=?', $min_DBC);
            $select->where('claim.date_creation<=?', $max_DBC);
        }
        if ($min_date_billed != null && $max_date_billed == null) {
            $select->where('claim.date_billed=?', $min_date_billed);
        }
        if ($min_date_billed != null && $max_date_billed != null) {
            $select->where('claim.date_billed>=?', $min_date_billed);
            $select->where('claim.date_billed<=?', $max_date_billed);
        }
        if ($min_date_closed != null && $max_date_closed == null) {
            $select->where('claim.date_closed=?', $min_date_closed);
        }
        if ($min_date_closed != null && $max_date_closed != null) {
            $select->where('claim.date_closed>=?', $min_date_closed);
            $select->where('claim.date_closed<=?', $max_date_closed);
        }

        if ($last_name != null)
            $select->where('patient.last_name LIKE ?', $last_name . '%');
        //$select->where('patient.last_name=?', $last_name);

        /*         * *******Add the inquiry for the first name************** */
        if ($first_name != null)
            $select->where('patient.first_name LIKE ?', $first_name . '%');
        /*         * *******Add the inquiry for the first name************** */

        if ($facility_id != null) {
            $select->where('facility.id=?', $facility_id);
        }
        if ($end_date == null && $start_date != null) {
            $select->where('encounter.start_date_1=?', $start_date);
        }
        if ($end_date != null && $start_date != null) {
            //$select->where('encounter.start_date_1=?', $start_date);
            $select->where('encounter.start_date_1>=?', $start_date);
            $select->where('encounter.start_date_1<=?', $end_date);
        }
        if ($last_date != null) {
            $select->where('claim.update_time>=?', $last_date);
        }
        if ($max_charge == null && $min_charge != null) {
            $select->where('claim.total_charge=?', $min_charge);
        } else if ($max_charge != null && $min_charge != null) {
            //$select->where('encounter.start_date_1=?', $start_date);
            $select->where('claim.total_charge>=?', $min_charge);
            $select->where('claim.total_charge<=?', $max_charge);
        } else if ($max_charge != null && $min_charge == null) {
            //return null;
            $select->where('claim.total_charge<=?', $max_charge);
        }
        if ($max_paid == null && $min_paid != null) {
            $select->where('claim.amount_paid=?', $min_paid);
        } else if ($max_paid != null && $min_paid != null) {
            //$select->where('encounter.start_date_1=?', $start_date);
            $select->where('claim.amount_paid>=?', $min_paid);
            $select->where('claim.amount_paid<=?', $max_paid);
        } else if ($max_paid != null && $min_paid == null) {
            $select->where('claim.amount_paid<=?', $max_paid);
            // return null;
        }
        /* CICI */
        if ($color_code == 'FFFFFF') {
            $color_code = null;
        }
        if ($color_code) {
            $select->where('claim.color_code=?', $color_code);
        }


        //changed to input a part of account number to inquiry all accounts contains that part of account number.by james
        if ($account_number != null) {
            //$select->where('patient.account_number=?', $account_number);
            $select->where('patient.account_number LIKE ?', '%' . $account_number . '%');
        }
        if (count($cpt_code) > 0) {
            $select->where('encounter.CPT_code_1 IN(?) OR encounter.CPT_code_2 IN(?) OR encounter.CPT_code_3 IN(?) OR encounter.CPT_code_4 IN(?) OR encounter.CPT_code_5 IN(?) OR encounter.CPT_code_6 IN(?)', $cpt_code);
        }
        if (count($diagnosis_code) > 0) {
            // $select->where('diagnosiscode_10.id  IN (?)', $diagnosis_code);
            $select->where('encounter.diagnosis_code1 IN(?) OR encounter.diagnosis_code2 IN(?) OR encounter.diagnosis_code3 IN(?) OR encounter.diagnosis_code4 IN(?)', $diagnosis_code);
        }
        if (count($biller_name) > 0) {
//                    $lognameArray = explode("/", $min_date_closed);
//                    if($last_days){
//                        $select->where('');
//                          
//                    }
//                    else{
            //   $select->where('replace(substring(log,1,locate(':',log)-1),' ','') IN(?)',$biller_name);
            $select->where('trim(substring(log,1,locate(\':\',interactionlog.log)-1)) IN(?)', $biller_name);
            //      $select->where('interactionlog.the_biller IN(?)', $biller_name);
            $select->group('the_biller');
            $select->order('interactionlog.date_and_time DESC');
            //      $select->limit($limit_num);
            //  }
        }

        //$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB', 'patient.account_number'));
        $select->order(array('patient.last_name', 'patient.first_name', 'encounter.start_date_1 DESC'));
        // this is used to elimenate the repeated claim
        $select->group('encounter.id');

        /*         * ****************Add limilt for the inquiry*************** */
        //$select->limit($limit_number);
        /*         * ****************Add limilt for the inquiry*************** */
        if ($phone_num != null) {
            $select->where('patient.phone_number=? OR patient.second_phone_number=?', $phone_num);
        }
        if (($cptcode_array == null) && ($phone_num == null)) {
            $select->limit($limit_number);
        }
        $patient = Array();
        if ('guest' == $role) {
            if ($providerList_start != null) {
                $patient = $db->fetchAll($select);
            }
        } else {
            $patient = $db->fetchAll($select);
        }
        if ($patient != null) {
            $data = 1;
        } else {
            $data = 0;
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function predefinedAction() {
        $this->_helper->viewRenderer->setNoRender();
        $predefinedinquiry = "";
        $Name = $this->getRequest()->getPost("Name");
        if ($Name) {
            $predefinedinquiry.="Name=" . $Name;
        }
        $predefinedinquiry.="|Default=no";
        $name = $this->getRequest()->getPost("name");
        if ($name != null) {
            $predefinedinquiry.="|name=" . $name;
        }
        $account_number = $this->getRequest()->getPost("account_number"); //MRN
        if ($account_number) {
            $predefinedinquiry.="|account_number=" . $account_number;
        }
        $phone_num = $this->getRequest()->getPost('phone_num');
        if ($phone_num) {
            $predefinedinquiry.="|phone_num=" . $phone_num;
        }
        $DOB = $this->getRequest()->getPost("DOB");
        if ($DOB) {
            $predefinedinquiry.= "|DOB=" . $DOB;
        }
        $provider_id_array = $this->getRequest()->getPost("provider_id_array");
        if (strlen($provider_id_array) > 0) {
            $predefinedinquiry.="|provider_id=" . $provider_id_array."*";
        }
        $renderingprovider_id = $this->getRequest()->getPost("renderingprovider_id");
        if ($renderingprovider_id) {
            $predefinedinquiry.="|renderingprovider_id=" . $renderingprovider_id;
        }
        $referringprovider_id = $this->getRequest()->getPost("referringprovider_id");
        if ($referringprovider_id) {
            $predefinedinquiry.="|referringprovider_id=" . $referringprovider_id;
        }
        $facility_id = $this->getRequest()->getPost("facility_id");
        if ($facility_id) {
            $predefinedinquiry.="|facility_id=" . $facility_id;
        }
        $claim_status_array = $this->getRequest()->getPost("claim_status_array");
        if (strlen($claim_status_array) > 0) {
            $predefinedinquiry.="|claim_status=" . $claim_status_array."*";
        }
        $bill_status_array = $this->getRequest()->getPost("bill_status_array");
        if (strlen($bill_status_array) > 0) {
            $predefinedinquiry.="|bill_status=" . $bill_status_array."*";
        }
        $statement_status_array = $this->getRequest()->getPost("statement_status_array");
        if (strlen($statement_status_array)) {
            $predefinedinquiry.="|statement_status=" . $statement_status_array."*";
        }
        $colorselector_1 = $this->getRequest()->getPost("color_code");  //"color_code" instead of "colorselector_1" 
        if ($colorselector_1) {
            $predefinedinquiry.="|colorselector_1=" . $colorselector_1;
        }
        $insurance_id_array = $this->getRequest()->getPost("insurance_id_array");
        if (strlen($insurance_id_array) > 0) {
            $predefinedinquiry.="|insurance_id=" . $insurance_id_array."*";
        }
        $insurance_type_array = $this->getRequest()->getPost("insurance_type_array");
        if (strlen($insurance_type_array) > 0) {
            $predefinedinquiry.="|insurance_type=" . $insurance_type_array."*";
        }
        $diagnosisid_array = $this->getRequest()->getPost('diagnosisid_array');
        if (strlen($diagnosisid_array) > 0) {
            $predefinedinquiry.="|diagnosis_id=" . $diagnosisid_array."*";
        }
        $cptcode_array = $this->getRequest()->getPost('cptcode_array');
        if (strlen($cptcode_array) > 0) {
            $predefinedinquiry.="|cpt_code=" . $cptcode_array."*";
        }
        $anesthesia_id_array = $this->getRequest()->getPost("anesthesia_id_array");
        if (strlen($anesthesia_id_array) > 0) {
            $predefinedinquiry.="|anesthesia_id=" . $anesthesia_id_array."*";
        }
        $total_charge = $this->getRequest()->getPost("total_charge");
        if ($total_charge) {
            $predefinedinquiry.="|total_charge=" . $total_charge;
        }
        $amount_paid = $this->getRequest()->getPost("amount_paid");
        if ($amount_paid) {
            $predefinedinquiry.="|amount_paid=" . $amount_paid;
        }
        $DOP = $this->getRequest()->getPost("DOP");
        if ($DOP) {
            $predefinedinquiry.="|DOP=" . $DOP;
        }
        $DONF = $this->getRequest()->getPost("DONF");
        if ($DONF) {
            $predefinedinquiry.="|DONF=" . $DONF;
        }
        $DOS = $this->getRequest()->getPost("DOS");
        if ($DOS) {
            $predefinedinquiry.="|DOS=" . $DOS;
        }
        $DBC = $this->getRequest()->getPost("DBC");
        if ($DBC) {
            $predefinedinquiry.="|DBC=" . $DBC;
        }
        $date_billed = $this->getRequest()->getPost("date_billed");
        if ($date_billed) {
            $predefinedinquiry.="|date_billed=" . $date_billed;
        }
        $date_closed = $this->getRequest()->getPost("date_closed");
        if ($date_closed) {
            $predefinedinquiry.="|date_closed=" . $date_closed;
        }
        $last_days = $this->getRequest()->getPost("last_days");
        if ($last_days) {
            $predefinedinquiry.="|last_days=" . $last_days;
        }
        $biller_array = $this->getRequest()->getPost('biller_array');
        if (strlen($biller_array) > 0) {
            $predefinedinquiry.="|biller=" . $biller_array."*";
        }
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('biller', 'predefinedinquiry');
        $select->where('biller.id = ?', $biller_id);
        $pd = $db->fetchAll($select);
        if ($pd[0]['predefinedinquiry'] != "" || $pd[0]['predefinedinquiry'] != null) {
            $predefinedinquiry = $pd[0]['predefinedinquiry'] . "|" . $predefinedinquiry;
        }
        $set = array('predefinedinquiry' => $predefinedinquiry);
        $where = $db->quoteInto('id = ?', $biller_id);
        $rows_affected = $db_biller->update($set, $where);
        $result = array();
        if ($rows_affected < 0) {
            $result= null;
        } else {
            $resultTags = parse_tagRe($predefinedinquiry); 
            $i = 0;
            foreach ($resultTags as $resultTag) {
           //     foreach ($resultTag as $key => $value) {
           //         $result[$i] = $resultTag['Name'];
           //         $i++;
          //      }
                $result[$i]=$resultTag['Name'];
                $i++;
            }
        }
        $json = Zend_Json::encode($result);
        echo $json;
    }

    public function predefineddefaultAction() {
        $this->_helper->viewRenderer->setNoRender();
        $predefinedName = $this->getRequest()->getPost("predefinedName");
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('biller', 'predefinedinquiry');
        $select->where('biller.id = ?', $biller_id);
        $pd = $db->fetchAll($select);
        $result = array();
        $resultTags = parse_tagRe($pd[0]['predefinedinquiry']);
        $i = 0;
        foreach ($resultTags as $resultTag) {
            foreach ($resultTag as $key => $value) {
                if ($resultTag['Name'] == $predefinedName) {
                    $resultTag['Default'] = "yes";
                } else {
                    $resultTag['Default'] = "no";
                }
            }
            foreach ($resultTag as $key => $value) {
                if ($result[$i] == "") {
                    $result[$i].=$key . "=" . $value;
                } else {
                    $result[$i].="|" . $key . "=" . $value;
                }
            }
            $i++;
        }
        $strs = implode("|", $result);

        $set = array('predefinedinquiry' => $strs);
        $where = $db->quoteInto('id = ?', $biller_id);
        $rows_affected = $db_biller->update($set, $where);
        if ($rows_affected < 0) {
            $data = 0;
        } else {
            $data = 1;
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }
public function predefineddeleteAction(){
      $this->_helper->viewRenderer->setNoRender();
        $predefinedName = $this->getRequest()->getPost("predefinedName");
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('biller', 'predefinedinquiry');
        $select->where('biller.id = ?', $biller_id);
        $pd = $db->fetchAll($select);
        $resultTags = parse_tagRe($pd[0]['predefinedinquiry']);
        for($i=0;$i<count($resultTags);$i++){
            if ($resultTags[$i]['Name'] == $predefinedName) {
                   unset($resultTags[$i]);
            }
        }
        if(count($resultTags)==0){
            $strs=null;
        }
        else{
        $result = array();
        $i = 0;
        foreach ($resultTags as $resultTag) {
         foreach ($resultTag as $key => $value) {
                if ($result[$i] == "") {
                    $result[$i].=$key . "=" . $value;
                } else {
                    $result[$i].="|" . $key . "=" . $value;
                }
            }
                 $i++;
        }
        $strs = implode("|", $result);
        }
        $set = array('predefinedinquiry' => $strs);
        $where = $db->quoteInto('id = ?', $biller_id);
        $rows_affected = $db_biller->update($set, $where);
        $data = array();
        if ($rows_affected < 0) {
            $data= null;
        } else {
            if($strs!=null){
            $resultTags = parse_tagRe($strs); 
            $i = 0;
            foreach ($resultTags as $resultTag) {
                $data[$i]=$resultTag['Name'];
                $i++;
            }
            }
        }
        $json = Zend_Json::encode($data);
        echo $json;
}

    public function poppredefinedAction() {
        $this->_helper->viewRenderer->setNoRender();
        $predefinedName = $this->getRequest()->getPost("predefinedName");
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('biller', 'predefinedinquiry');
        $select->where('biller.id = ?', $biller_id);
        $pd = $db->fetchAll($select);
        $resultTags = parse_tagRe($pd[0]['predefinedinquiry']);
        $result = array();
        for ($i = 0; $i < count($resultTags); $i++) {
            if ($resultTags[$i]['Name'] == $predefinedName) {
                $result = $resultTags[$i];
                break;
            }
        }
        $json = Zend_Json::encode($result);
        echo $json;
    }

    public function predefinedchangeAction() {
         $this->_helper->viewRenderer->setNoRender();
        $predefinedName = $this->getRequest()->getPost("predefinedName");
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $user_id = $user_data['id'];
        $biller_id = $user_data['reference_id'];
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('biller', 'predefinedinquiry');
        $select->where('biller.id = ?', $biller_id);
        $pd = $db->fetchAll($select);
        $resultTags = parse_tagRe($pd[0]['predefinedinquiry']);
        $result = array();
        for($i=0;$i<count($resultTags);$i++){
            if ($resultTags[$i]['Name'] == $predefinedName) {
                  $result=$resultTags[$i];
                  break;
            }
        }
        $json = Zend_Json::encode($result);
        echo $json;
    }
    public function inquiryAction() {
        $this->clearsession();
        $this->view->colorAlertList = $this->get_coloralerts();
        if (!$this->getRequest()->isPost()) {

            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $role = $this->get_user_role();
            $whereprovider = null;
            $providerList = array();
            if ($role == 'guest') {
                $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                $db = $db_userfocusonprovider->getAdapter();
                $where = $db->quoteInto('user_id = ?', $this->user_id);
                $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
                $providerList_start = array();
                for ($i = 0; $i < count($userfocusonprovider); $i++) {
                    $providerList_start[$i] = $userfocusonprovider[$i]['provider_id'];
                }
                // $providerList_start[1]=2;
                if (isset($providerList_start[0])) {
                    $whereprovider = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id()) . $db->quoteInto('and id in (?)', $providerList_start);
                    ;
                    $providerList = $db_provider->fetchAll($whereprovider, "provider_name ASC");
                }
            } else {
                $whereprovider = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id());
                $providerList = $db_provider->fetchAll($whereprovider, "provider_name ASC");
            }
            // $providerList = $db_provider->fetchAll($whereprovider, "provider_name ASC");
            $this->view->providerList = $providerList;
            if ($providerList != null)
                $this->view->biller_provider = $providerList->toArray();

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status_display');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->claimstatusList = $claimstatuslist;

            //cici
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('biller');
            $select->where('biller.billingcompany_id = ?', $this->get_billingcompany_id());
            $select->order('biller.biller_name');
            try {
                $billernamelist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->billernameList = $billernamelist;

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('billstatus');
            $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
            $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('billstatus.requried = ?', 1);
            $select->group('billstatus.id');
            $select->order('billstatus.bill_status_display');
            try {
                $billstatus = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->billstatusList = $billstatus;

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('statementstatus');
            $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
            $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('statementstatus.requried = ?', 1);
            $select->group('statementstatus.id');
            $select->order('statementstatus.statement_status_display');
            try {
                $statementstatus = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->statementstatusList = $statementstatus;

//referringprovider

            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id());
            $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
            //$this->movetotop($insuranceList);
            movetotop($insuranceList);
            $this->view->insuranceList = $insuranceList;

            if ($providerList != null)
                $provider_array = $providerList->toArray();
            $provider_id_list = array();
            $i = 0;
            foreach ($provider_array as $provider) {
                $provider_id_list[$i] = $provider['id'];
                $i++;
            }
            if (isset($provider_array[0])) {
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('anesthesiacode', 'anesthesiacode.anesthesia_code as anesthesia_id');
                $select->where("provider_id IN (?)", $provider_id_list);
                $select->group('anesthesiacode.anesthesia_code');
                $select->order('anesthesia_code ASC');
                $anesthesiacodeList = $db->fetchAll($select);
                $this->view->anesthesiaList = $anesthesiacodeList;

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('cptcode');
                $select->where("provider_id IN (?)", $provider_id_list);
                $select->group('CPT_code');
                $select->order('CPT_code ASC');
                $cptcodelist = $db->fetchAll($select);
                $this->view->cptcodelist = $cptcodelist;

                /* cici */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
                $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
                $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');
                $select->where("providerhasdiagnosiscode_10.provider_id IN (?)", $provider_id_list);
                $select->group('diagnosiscode_10.diagnosis_code');
                $select->order('diagnosis_code ASC');
                $diagnosis_codeList = $db->fetchAll($select);
                $this->view->diagnosis_codeList = $diagnosis_codeList;



                $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
                $select = $db->select();
                $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
                $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
                $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
                $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
                $select->where('providerhasrenderingprovider.provider_id in (?)', $provider_array);
                $select->order('last_name ASC');
                $select->group('renderingprovider.id');
                $renderingproviderList = $db->fetchAll($select);

                $this->view->renderingproviderList = $renderingproviderList;

                $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
                $select = $db->select();
                $select->from('facility', array('facility.id as fid', 'facility_display'));
                $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
                $select->join('provider', 'providerhasfacility.provider_id = provider.id');
                $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
                $select->where('providerhasfacility.provider_id in (?)', $provider_array);
                $select->group('facility.id');
                $select->order('facility_display ASC');
                $facilityList = $db->fetchAll($select);

                $this->view->facilityList = $facilityList;
                $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
                $select = $db->select();
                $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
                $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
                $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
                $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
                $select->where('providerhasreferringprovider.provider_id in (?)', $provider_array);
                $select->group('referringprovider.id');
                $select->order('last_name ASC');
                $referringproviderList = $db->fetchAll($select);
                $this->view->referringproviderList = $referringproviderList;
            }
            session_start();
            $sort_flag = (isset($_SESSION['cliam_recent_sort'])) ? $_SESSION['cliam_recent_sort'] : 0;
            if ($sort_flag != 1) {
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                $limit_num = 10;
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('renderingprovider', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('encounter.start_date_1', 'encounter.secondary_CPT_code_1 as anes_code', 'CPT_code_1', 'CPT_code_2', 'CPT_code_3', 'CPT_code_4', 'CPT_code_5', 'CPT_code_6', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id'));
                /*                 * ********************Add fields***************** */
                $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id', array('referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name'));
                /*                 * ********************Add fields***************** */
                //$select->join('claim', 'claim.id=encounter.claim_id' , array('update_user','update_time'));
                $select->join('claim', 'claim.id=encounter.claim_id', array('balance_due', 'amount_paid', 'total_charge', 'color_code'));
                $select->joinLeft('claimstatus', 'claimstatus.claim_status=claim.claim_status', array('CONCAT(\'\',claim_status_display,\'\') AS claim_status_display'));
                $select->joinLeft('billstatus', 'billstatus.bill_status=claim.bill_status', array('CONCAT(\'\',bill_status_display,\'\') AS bill_status_display'));
                $select->joinLeft('statementstatus', 'statementstatus.statement_status=claim.statement_status', array('CONCAT(\'\',statement_status_display,\'\') AS statement_status_display'));


                /*                 * ******Using short_name for the facility and provider***** */
                $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
                /*                 * ******Using short_name for the facility and provider***** */
                $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
                $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'phone_number', 'second_phone_number', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'patient.account_number'));
                /*                 * *new for inquiry** */
                //$select->join('insured', 'insured.id=patient.insured_id');
                $select->join('encounterinsured', 'encounterinsured.encounter_id =encounter.id');
                $select->join('insured', 'encounterinsured.insured_id =insured.id');
                /*                 * *new for inquiry** */
                $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
                $select->where('billingcompany.id=?', $this->get_billingcompany_id());
                $select->where('update_user =?', $user_name);
                $select->group('encounter.id');
                $select->order('update_time DESC');
                $select->limit($limit_num);
                $patient = $db->fetchAll($select);
                for ($i = 0; $i < count($patient); $i++) {
                    $patient[$i]['count_flag'] = 0;
                    $amount_paid = $patient[$i]['amount_paid'];
                    $total_charge = $patient[$i]['total_charge'];
                    $per = $amount_paid / $total_charge;
                    $per = round($per, 2) * 100;
                    if ($per == 0)
                        $patient[$i]['percentage'] = "";
                    else
                        $patient[$i]['percentage'] = $per;
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('interactionlog');
                    $select->where('interactionlog.claim_id=?', $patient[$i]['claim_id']);
                    $select->order('interactionlog.date_and_time DESC');
                    $tmp_interactionlogs = $db->fetchAll($select);
                    $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                    $start_date = date("Y-m-d");
                    $noactiondays = 99;
                    if ($end_date != null && $end_date != "") {
                        $noactiondays = days($start_date, $end_date);
                    } else {
                        $temp_days = 99;
                        if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_last_billed'], $patient[$i]['date_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_last_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = max($patient[$i]['date_billed'], $patient[$i]['date_last_billed']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = $patient[$i]['date_rebilled'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = $patient[$i]['date_last_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = $patient[$i]['date_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        }
                        $noactiondays = $temp_days;
                    }
                    if ($noactiondays <= 99)
                        $patient[$i]['last'] = $noactiondays;
                    else
                        $patient[$i]['last'] = 99;
                }
                $claim_recent = $patient;
                $count_claim = count($claim_recent);

                $colorAlerts = $this->get_coloralerts();

                for ($i = 0; $i < $count_claim; $i++) {
                    $row = $claim_recent[$i];
                    for ($j = 0; $j < count($colorAlerts); $j++) {
                        if ($row['color_code'] == $colorAlerts[$j]['RGB'])
                            $claim_recent[$i]['alert'] = $colorAlerts[$j]['alert'];
                    }
                    if ($row['type'] == 'primary') {
                        //$patientList[$count]['insurance_name'] = $row['insurance_name'];
                        //$patientList[$count]['insurance_display'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                        $type_tmp = 'secondary';
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $claim_recent[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                            $claim_recent[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $claim_recent[$i]['insurance_s_name'] = null;
                            $claim_recent[$i]['insurance_s_display'] = null;
                        }
                    } else if ($row['type'] == 'secondary') {
                        //$patientList[$count]['insurance_s_name'] = $row['insurance_name'];
                        //$patientList[$count]['insurance_s_display'] = $row['insurance_display'];
                        $claim_recent[$i]['insurance_s_name'] = $row['insurance_name'];
                        $claim_recent[$i]['insurance_s_display'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        $type_tmp = 'primary';
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $claim_recent[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                            $claim_recent[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $claim_recent[$i]['insurance_name'] = null;
                            $claim_recent[$i]['insurance_display'] = null;
                        }
                    } else {
                        $claim_recent[$i]['insurance_name_other'] = $row['insurance_name'];
                        $claim_recent[$i]['insurance_display_other'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        $type_tmp = 'primary';
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $claim_recent[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                            $claim_recent[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $claim_recent[$i]['insurance_name'] = null;
                            $claim_recent[$i]['insurance_display'] = null;
                        }
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                        $type_tmp = 'secondary';
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $claim_recent[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                            $claim_recent[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $claim_recent[$i]['insurance_s_name'] = null;
                            $claim_recent[$i]['insurance_s_display'] = null;
                        }
                    }
                }
                $claim_recent_list = getclaimList($claim_recent);
                $_SESSION['claim_recent'] = $claim_recent;
            } else {
                $claim_recent_sorted = $_SESSION['claim_recent'];
                $claim_recent_list = getclaimList($claim_recent_sorted);
            }
            //$claim_recent_list = getclaimList($claim_recent);
            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }

            /*             * **************Add claimstatus for ***************** */


            /*             * *********************translate the claim_status**************** */
            /* No need since claim_status_display is used
              foreach($claimstatuslist as $row)
              {
              $translate_claim_status[$row['claim_status']]  = $row['claim_status_display'];
              }
              for($i = 0; $i < count($claim_recent_list); $i++)
              {
              $tmp_claim_status = $claim_recent_list[$i]['claim_status'];
              $claim_recent_list[$i]['claim_status'] = $translate_claim_status[$tmp_claim_status];

              }
             * 
             */
            $this->view->claim_recent_list = $claim_recent_list;
            $_SESSION['cliam_recent_sort'] = 0;

            //cici part3
            $user = Zend_Auth::getInstance()->getIdentity();
            $db_user = new Application_Model_DbTable_User();
            $db = $db_user->getAdapter();
            $where = $db->quoteInto('user_name = ?', $user->user_name);
            $user_data = $db_user->fetchRow($where);
            $user_id = $user_data['id'];
            $biller_id = $user_data['reference_id'];
            $db_biller = new Application_Model_DbTable_Biller();
            $db = $db_biller->getAdapter();
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('biller', 'predefinedinquiry');
            $select->where('biller.id = ?', $biller_id);
            $select->order('biller.predefinedinquiry');
            try {
                $preInqNameList = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $result = array();
            $resultTags = parse_tagRe($preInqNameList[0]['predefinedinquiry']);
            $i = 0;
            foreach ($resultTags as $resultTag) {
                foreach ($resultTag as $key => $value) {
                    if ($resultTag[0] . $key == "Name") {
                        $result[$i] = $resultTag[0] . $value;
                        $i++;
                    }
                    if($resultTag[1].$key=="Default"&&$resultTag[1].$value=="yes"){
                        $defaultName=$resultTag["Name"];
                    }
                }
            }
           $this->view->preInqNameList = $result;
            $this->view->defaultName=$defaultName;
        }
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            $temp = $this->getRequest()->getPost('my_submit');
            $postType = $this->getRequest()->getParam('post');
            $form_c = $this->getRequest()->getParam('form_c');
            // $this->getRequest()->getPost('edi') != ""
            if ($temp == 'is') {
                $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                $db = $db_userfocusonprovider->getAdapter();
                $where = $db->quoteInto('user_id = ?', $this->user_id);
                $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
                $providerList_start = array();
                for ($i = 0; $i < count($userfocusonprovider); $i++) {
                    $providerList_start[$i] = $userfocusonprovider[$i]['provider_id'];
                }
                $this->clearsession();
                $this->unset_options_session();
                $provider_id_array = $this->getRequest()->getPost('provider_id_array');
                $renderingprovider_id = $this->getRequest()->getPost('renderingprovider_id');
                $referringprovider_id = $this->getRequest()->getPost('referringprovider_id');
                /* cici */
                $DOB = $this->getRequest()->getPost('DOB');
                $count = substr_count($DOB, "-");
                if ($count == 0) {
                    $min_DOB = $DOB;
                    $max_DOB = null;
                    $min_DOBArray = explode("/", $min_DOB);
                    $year = $min_DOBArray[2];
                    array_pop($min_DOBArray);
                    array_unshift($min_DOBArray, $year);
                    $min_DOB = implode("-", $min_DOBArray);
                } else {
                    $DOBArray = explode("-", $DOB);
                    $min_DOB = $DOBArray[0];
                    $max_DOB = $DOBArray[1];

                    $min_DOBArray = explode("/", $min_DOB);
                    $year = $min_DOBArray[2];
                    array_pop($min_DOBArray);
                    array_unshift($min_DOBArray, $year);
                    $min_DOB = implode("-", $min_DOBArray);

                    $max_DOBArray = explode("/", $max_DOB);
                    $year = $max_DOBArray[2];
                    array_pop($max_DOBArray);
                    array_unshift($max_DOBArray, $year);
                    $max_DOB = implode("-", $max_DOBArray);
                }

                /* cici */
                $DBC = $this->getRequest()->getPost('DBC');
                $count = substr_count($DBC, "-");
                if ($count == 0) {
                    $min_DBC = $DBC;
                    $max_DBC = null;
                    $min_DBCArray = explode("/", $min_DBC);
                    $year = $min_DBCArray[2];
                    array_pop($min_DBCArray);
                    array_unshift($min_DBCArray, $year);
                    $min_DBC = implode("-", $min_DBCArray);
                } else {
                    $DBCArray = explode("-", $DBC);
                    $min_DBC = $DBCArray[0];
                    $max_DBC = $DBCArray[1];

                    $min_DBCArray = explode("/", $min_DBC);
                    $year = $min_DBCArray[2];
                    array_pop($min_DBCArray);
                    array_unshift($min_DBCArray, $year);
                    $min_DBC = implode("-", $min_DBCArray);

                    $max_DBCArray = explode("/", $max_DBC);
                    $year = $max_DBCArray[2];
                    array_pop($max_DBCArray);
                    array_unshift($max_DBCArray, $year);
                    $max_DBC = implode("-", $max_DBCArray);
                }

                /*             if ($min_DOB != null)
                  $min_DOB  = date("Y-m-d", strtotime('-' . $min_DOB . ' day'));
                  if ($max_DOB != null)
                  $max_DOB  = date("Y-m-d", strtotime('-' . $max_DOB . ' day')); */
                /*        $DOBArray = explode("/", $DOB);
                  $year = $DOBArray[2];
                  array_pop($DOBArray);
                  array_unshift($DOBArray, $year);
                  $DOB = implode("-", $DOBArray); */



                $name = $this->getRequest()->getPost('name');
                if ($name == null) {
                    $last_name = null;
                    $first_name = null;
                } else {
                    $count = substr_count($name, ",");
                    if ($count == 0) {
                        $last_name = trim($name);
                        $first_name = null;
                    }
                    if ($count == 1) {
                        $totalname = explode(",", $name);
                        if ($totalname[0] == "") {
                            $last_name = null;
                        } else {
                            $last_name = trim($totalname[0]);
                        }
                        if ($totalname[1] == "") {
                            $first_name = null;
                        } else {
                            $first_name = trim($totalname[1]);
                        }
                    }
                }
                //  $last_name = $this->getRequest()->getPost('last_name');
                /*                 * *********Add the inquiry for the first name************* */
                //     $first_name = $this->getRequest()->getPost('first_name');
                /*                 * *********Add the inquiry for the first name************* */
                $facility_id = $this->getRequest()->getPost('facility_id');
                //            $insurance_id = $this->getRequest()->getPost('insurance_id');
                //            $claim_status = $this->getRequest()->getPost('claim_status');
                $insurance_id_array = $this->getRequest()->getPost('insurance_id_array');
                $insurance_type_array = $this->getRequest()->getPost('insurance_type_array');
                $anesthesia_id_array = $this->getRequest()->getPost('anesthesia_id_array');
                $claim_status_array = $this->getRequest()->getPost('claim_status_array');
                $cptcode_array = $this->getRequest()->getPost('cptcode_array');
                /* cic */
                $diagnosisid_array = $this->getRequest()->getPost('diagnosisid_array');

                $account_number = $this->getRequest()->getPost('account_number');
                $bill_status_array = $this->getRequest()->getPost('bill_status_array');
                $statement_status_array = $this->getRequest()->getPost('statement_status_array');
                $last_date = null;
                $last_days = $this->getRequest()->getPost("last_days");
                if ($last_days != null) {
                    $last_date = date("Y-m-d", strtotime('-' . $last_days . ' day'));
                }
                //cici
                $biller_array = $this->getRequest()->getPost('biller_array');
                $provider_id = array();
                $insurance_id = array();
                $anesthesia_id = array();
                $diagnosis_code = array(); //cici
                $claim_status = array();
                $bill_status = array();
                $statement_status = array();
                $cpt_code = array();
                $insurance_type = array();
                $biller_name = array(); //cici
                if (strlen($bill_status_array) > 0) {
                    $bill_status = explode(',', $bill_status_array);
                }
                if (strlen($statement_status_array) > 0) {
                    $statement_status = explode(',', $statement_status_array);
                }
                if (strlen($insurance_type_array) > 0) {
                    $insurance_type = explode(',', $insurance_type_array);
                }
                if (strlen($cptcode_array) > 0) {
                    $cpt_code = explode(',', $cptcode_array);
                }
                /* cici */
                if (strlen($diagnosisid_array) > 0) {
                    $diagnosis_code = explode(',', $diagnosisid_array);
                }
                if (strlen($insurance_id_array) > 0) {
                    $insurance_id = explode(',', $insurance_id_array);
                }
                if (strlen($anesthesia_id_array) > 0) {
                    $anesthesia_id = explode(',', $anesthesia_id_array);
                }
                if (strlen($claim_status_array) > 0) {
                    $claim_status = explode(',', $claim_status_array);
                }

                if (strlen($provider_id_array) > 0) {
                    $provider_id = explode(',', $provider_id_array);
                }
                //cici
                if (strlen($biller_array) > 0) {
                    $biller_name = explode(',', $biller_array);
                }
                /*  if ($this->getRequest()->getPost('start_date') != null)
                  $start_date = format($this->getRequest()->getPost('start_date'), 0);
                  if ($this->getRequest()->getPost('end_date') != null)
                  $end_date = format($this->getRequest()->getPost('end_date'), 0); */
                if ($this->getRequest()->getPost('total_charge') != null)
                    $total_charge = $this->getRequest()->getPost('total_charge');
                if ($total_charge == null) {
                    $min_charge = null;
                    $max_charge = null;
                } else {
                    $count = substr_count($total_charge, "-");
                    if ($count == 0) {
                        $min_charge = trim($total_charge);
                        $max_charge = null;
                    }
                    if ($count == 1) {
                        $total_charge = explode("-", $total_charge);
                        if ($total_charge[0] == "") {
                            $min_charge = null;
                        } else {
                            $min_charge = trim($total_charge[0]);
                        }
                        if ($total_charge[1] == "") {
                            $max_charge = null;
                        } else {
                            $max_charge = trim($total_charge[1]);
                        }
                    }
                }

                if ($this->getRequest()->getPost('amount_paid') != null) {
                    $amount_paid = $this->getRequest()->getPost('amount_paid');
                }
                if ($amount_paid == null) {
                    $min_paid = null;
                    $max_paid = null;
                } else {
                    $count = substr_count($amount_paid, "-");
                    if ($count == 0) {
                        $min_paid = trim($amount_paid);
                        $max_paid = null;
                    }
                    if ($count == 1) {
                        $amount_paid = explode("-", $amount_paid);
                        if ($amount_paid[0] == "") {
                            $min_paid = null;
                        } else {
                            $min_paid = trim($amount_paid[0]);
                        }
                        if ($amount_paid[1] == "") {
                            $max_paid = null;
                        } else {
                            $max_paid = trim($amount_paid[1]);
                        }
                    }
                }




                /* cici */
                $DOP = $this->getRequest()->getPost('DOP');
                $count = substr_count($DOP, "-");
                if ($count == 0) {
                    $min_DOP = $DOP;
                    $max_DOP = null;
                    $min_DOPArray = explode("/", $min_DOP);
                    $year = $min_DOPArray[2];
                    array_pop($min_DOPArray);
                    array_unshift($min_DOPArray, $year);
                    $min_DOP = implode("-", $min_DOPArray);
                } else {
                    $DOPArray = explode("-", $DOP);
                    $min_DOP = $DOPArray[0];
                    $max_DOP = $DOPArray[1];

                    $min_DOPArray = explode("/", $min_DOP);
                    $year = $min_DOPArray[2];
                    array_pop($min_DOPArray);
                    array_unshift($min_DOPArray, $year);
                    $min_DOP = implode("-", $min_DOPArray);

                    $max_DOPArray = explode("/", $max_DOP);
                    $year = $max_DOPArray[2];
                    array_pop($max_DOPArray);
                    array_unshift($max_DOPArray, $year);
                    $max_DOP = implode("-", $max_DOPArray);
                }


                /* cici */
                $DONF = $this->getRequest()->getPost('DONF');
                $count = substr_count($DONF, "-");
                if ($count == 0) {
                    $min_DONF = $DONF;
                    $max_DONF = null;
                    $min_DONFArray = explode("/", $min_DONF);
                    $year = $min_DONFArray[2];
                    array_pop($min_DONFArray);
                    array_unshift($min_DONFArray, $year);
                    $min_DONF = implode("-", $min_DONFArray);
                } else {
                    $DONFArray = explode("-", $DONF);
                    $min_DONF = $DONFArray[0];
                    $max_DONF = $DONFArray[1];

                    $min_DONFArray = explode("/", $min_DONF);
                    $year = $min_DONFArray[2];
                    array_pop($min_DONFArray);
                    array_unshift($min_DONFArray, $year);
                    $min_DONF = implode("-", $min_DONFArray);

                    $max_DONFArray = explode("/", $max_DONF);
                    $year = $max_DONFArray[2];
                    array_pop($max_DONFArray);
                    array_unshift($max_DONFArray, $year);
                    $max_DONF = implode("-", $max_DONFArray);
                }


                $DOS = $this->getRequest()->getPost("DOS");
                $count = substr_count($DOS, "-");
                if ($count == 0) {
                    $start_date = $DOS;
                    $end_date = null;
                    $start_dateArray = explode("/", $start_date);
                    $year = $start_dateArray[2];
                    array_pop($start_dateArray);
                    array_unshift($start_dateArray, $year);
                    $start_date = implode("-", $start_dateArray);
                } else {
                    $DOSArray = explode("-", $DOS);
                    $start_date = $DOSArray[0];
                    $end_date = $DOSArray[1];

                    $start_dateArray = explode("/", $start_date);
                    $year = $start_dateArray[2];
                    array_pop($start_dateArray);
                    array_unshift($start_dateArray, $year);
                    $start_date = implode("-", $start_dateArray);

                    $end_dateArray = explode("/", $end_date);
                    $year = $end_dateArray[2];
                    array_pop($end_dateArray);
                    array_unshift($end_dateArray, $year);
                    $end_date = implode("-", $end_dateArray);
                }

                /* cici */
                $date_billed = $this->getRequest()->getPost('date_billed');
                $count = substr_count($date_billed, "-");
                if ($count == 0) {
                    $min_date_billed = $date_billed;
                    $max_date_billed = null;
                    $min_date_billedArray = explode("/", $min_date_billed);
                    $year = $min_date_billedArray[2];
                    array_pop($min_date_billedArray);
                    array_unshift($min_date_billedArray, $year);
                    $min_date_billed = implode("-", $min_date_billedArray);
                } else {
                    $date_billedArray = explode("-", $date_billed);
                    $min_date_billed = $date_billedArray[0];
                    $max_date_billed = $date_billedArray[1];

                    $min_date_billedArray = explode("/", $min_date_billed);
                    $year = $min_date_billedArray[2];
                    array_pop($min_date_billedArray);
                    array_unshift($min_date_billedArray, $year);
                    $min_date_billed = implode("-", $min_date_billedArray);

                    $max_date_billedArray = explode("/", $max_date_billed);
                    $year = $max_date_billedArray[2];
                    array_pop($max_date_billedArray);
                    array_unshift($max_date_billedArray, $year);
                    $max_date_billed = implode("-", $max_date_billedArray);
                }

                /* cici */
                $date_closed = $this->getRequest()->getPost('date_closed');
                $count = substr_count($date_closed, "-");
                if ($count == 0) {
                    $min_date_closed = $date_closed;
                    $max_date_closed = null;
                    $min_date_closedArray = explode("/", $min_date_closed);
                    $year = $min_date_closedArray[2];
                    array_pop($min_date_closedArray);
                    array_unshift($min_date_closedArray, $year);
                    $min_date_closed = implode("-", $min_date_closedArray);
                } else {
                    $date_closedArray = explode("-", $date_closed);
                    $min_date_closed = $date_closedArray[0];
                    $max_date_closed = $date_closedArray[1];

                    $min_date_closedArray = explode("/", $min_date_closed);
                    $year = $min_date_closedArray[2];
                    array_pop($min_date_closedArray);
                    array_unshift($min_date_closedArray, $year);
                    $min_date_closed = implode("-", $min_date_closedArray);

                    $max_date_closedArray = explode("/", $max_date_closed);
                    $year = $max_date_closedArray[2];
                    array_pop($max_date_closedArray);
                    array_unshift($max_date_closedArray, $year);
                    $max_date_closed = implode("-", $max_date_closedArray);
                }

                //cici  
                if ($this->getRequest()->getPost('colorselector_1') != null) {
                    $color_code = $this->getRequest()->getPost('colorselector_1');
                    if ($color_code == "FFFFFF") {
                        $color_code = null;
                    }
                }


                $phone_num = $this->getRequest()->getPost('phone_num');
                if ($phone_num != null) {
                    if ($phone_num[0] == '(') {
                        $find = array('(', ')', '-');
                        $phone_num_tmp = str_replace($find, null, $phone_num);
                        $phone_num = $phone_num_tmp;
                    }
                }


                /*                 * ********************Add limit inquiry results*********************** */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('billingcompany');
                $select->where('billingcompany.id=?', $this->get_billingcompany_id());
                $tmp_billingcompany = $db->fetchRow($select);
                $claim_inquiry_results_limit = $tmp_billingcompany['claim_inquiry_results_limit'];
                $limit_number = $claim_inquiry_results_limit + 1;
                /*                 * ********************Add limit inquiry results*********************** */


                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('renderingprovider', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('encounter.start_date_1', 'encounter.secondary_CPT_code_1 as anes_code', 'CPT_code_1', 'CPT_code_2', 'CPT_code_3', 'CPT_code_4', 'CPT_code_5', 'CPT_code_6', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id'));
                /*                 * ********************Add fields***************** */
                $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id', array('referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name'));
                /*                 * ********************Add fields***************** */
                $select->join('claim', 'claim.id=encounter.claim_id', array('balance_due', 'amount_paid', 'total_charge', 'claim_status', 'bill_status', 'statement_status', 'color_code'));
                //cici
                $select->joinLeft('payments', 'claim.id=payments.claim_id', array('datetime', 'claim_id'));
                $select->joinLeft('followups', 'claim.id = followups.claim_id');

                $select->joinLeft('claimstatus', 'claimstatus.claim_status=claim.claim_status', array('CONCAT(\'\',claim_status_display,\'\') AS claim_status_display'));
                $select->joinLeft('billstatus', 'billstatus.bill_status=claim.bill_status', array('CONCAT(\'\',bill_status_display,\'\') AS bill_status_display'));
                $select->joinLeft('statementstatus', 'statementstatus.statement_status=claim.statement_status', array('CONCAT(\'\',statement_status_display,\'\') AS statement_status_display'));


                /*                 * ******Using short_name for the facility and provider***** */
                $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));

                /*                 * ******Using short_name for the facility and provider***** */

                $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
                $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'phone_number', 'second_phone_number', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'patient.account_number'));

                /*                 * *new for inquiry** */
                //$select->join('insured', 'insured.id=patient.insured_id');
                $select->join('encounterinsured', 'encounterinsured.encounter_id =encounter.id');
                $select->join('insured', 'encounterinsured.insured_id =insured.id');
                /*                 * *new for inquiry** */


                $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
                $select->join('interactionlog', 'interactionlog.claim_id=claim.id', array('date_and_time', 'trim(substring(log,1,locate(\':\',log)-1)) as the_biller', 'claim_id'));
                $select->where('billingcompany.id=?', $this->get_billingcompany_id());

                //$select->where('encounterinsured.type = ?',  'primary');



                $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
                $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
                $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');




                if ($provider_id != null) {
                    $select->where('provider.id IN (?)', $provider_id);
                } else {
                    $role = $this->get_user_role();
                    if ($role == 'guest')
                        $select->where('provider.id IN (?)', $providerList_start);
                }
                if ($claim_status != null) {
                    $claim_status_1 = "";
                    $claim_status_2 = array();
                    for ($i = 0; $i < count($claim_status); $i++) {
                        if ($claim_status[$i] == 'all' || $claim_status[$i] == 'open' || $claim_status[$i] == 'closed' || $claim_status[$i] == 'inactive') {
                            $claim_status_1 = $claim_status[$i];
                        } else {
                            array_push($claim_status_2, $claim_status[$i]);
                        }
                    }
                    if ($claim_status_1 == 'all')
                        $select->where('claim.claim_status LIKE ?', '' . '%');
                    if ($claim_status_1 == 'open' || $claim_status_1 == 'closed' || $claim_status_1 == 'inactive') {
                        $select->where('claim.claim_status LIKE ?', $claim_status_1 . '%');
                    } else {
                        $select->where('claim.claim_status IN (?)', $claim_status_2);
                    }
                }
                if ($bill_status != null) {
                    $select->where('claim.bill_status IN (?)', $bill_status);
                }
                if ($statement_status != null) {
                    $select->where('claim.statement_status IN (?)', $statement_status);
                }
                if ($renderingprovider_id != null)
                    $select->where('renderingprovider.id=?', $renderingprovider_id);
                if ($referringprovider_id != null)
                    $select->where('encounter.referringprovider_id=?', $referringprovider_id);
                if ($insurance_id != null) {
                    /* */

                    /*  */
                    $select->where('insurance.id IN(?)', $insurance_id);
                }
                if ($insurance_type != null)
                    $select->where('encounterinsured.type in (?)', $insurance_type);
                if ($anesthesia_id != null) {
                    $select->where('encounter.secondary_CPT_code_1 IN(?) OR encounter.secondary_CPT_code_2 IN(?) OR encounter.secondary_CPT_code_3 IN(?) OR encounter.secondary_CPT_code_4 IN(?) OR encounter.secondary_CPT_code_5 IN(?) OR encounter.secondary_CPT_code_6 IN(?)', $anesthesia_id);
                }

                if ($min_DOB != null && $max_DOB == null) {
                    $select->where('patient.DOB=?', $min_DOB);
                }
                if ($min_DOB != null && $max_DOB != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('patient.DOB>=?', $min_DOB);
                    $select->where('patient.DOB<=?', $max_DOB);
                }



                if ($last_name != null)
                    $select->where('patient.last_name LIKE ?', $last_name . '%');
                //$select->where('patient.last_name=?', $last_name);

                /*                 * *******Add the inquiry for the first name************** */
                if ($first_name != null)
                    $select->where('patient.first_name LIKE ?', $first_name . '%');
                /*                 * *******Add the inquiry for the first name************** */

                if ($facility_id != null) {
                    $select->where('facility.id=?', $facility_id);
                }
                if ($end_date == null && $start_date != null) {
                    $select->where('encounter.start_date_1=?', $start_date);
                }
                if ($end_date != null && $start_date != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('encounter.start_date_1>=?', $start_date);
                    $select->where('encounter.start_date_1<=?', $end_date);
                }
                if ($last_date != null) {
                    $select->where('interactionlog.date_and_time>=?', $last_date);
                }
                if ($max_charge == null && $min_charge != null) {
                    $select->where('claim.total_charge=?', $min_charge);
                } else if ($max_charge != null && $min_charge != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('claim.total_charge>=?', $min_charge);
                    $select->where('claim.total_charge<=?', $max_charge);
                } else if ($max_charge != null && $min_charge == null) {
                    $select->where('claim.total_charge<=?', $max_charge);
                    //  return null;
                }
                if ($max_paid == null && $min_paid != null) {
                    $select->where('claim.amount_paid=?', $min_paid);
                } else if ($max_paid != null && $min_paid != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('claim.amount_paid>=?', $min_paid);
                    $select->where('claim.amount_paid<=?', $max_paid);
                } else if ($max_paid != null && $min_paid == null) {
                    $select->where('claim.amount_paid<=?', $max_paid);
                }

                //cici
                if ($color_code == 'FFFFFF') {
                    $color_code = null;
                }
                if ($color_code) {
                    $select->where('claim.color_code=?', $color_code);
                }


                if ($min_DOP != null && $max_DOP == null) {
                    $select->where('payments.datetime LIKE ?', $min_DOP . '%');
                }
                if ($min_DOP != null && $max_DOP != null) {
                    $select->where('payments.datetime>=?', $min_DOP);
                    $select->where('payments.datetime<=?', $max_DOP);
                }

                if ($min_DONF != null && $max_DONF == null) {
                    $select->where('followups.date_of_next_followup=?', $min_DONF . '%');
                }
                if ($min_DONF != null && $max_DONF != null) {
                    $select->where('followups.date_of_next_followup>=?', $min_DONF);
                    $select->where('followups.date_of_next_followup<=?', $max_DONF);
                }

                if ($min_DBC != null && $max_DBC == null) {
                    $select->where('claim.date_creation=?', $min_DBC);
                }
                if ($min_DBC != null && $max_DBC != null) {
                    $select->where('claim.date_creation>=?', $min_DBC);
                    $select->where('claim.date_creation<=?', $max_DBC);
                }
                if ($min_date_billed != null && $max_date_billed == null) {
                    $select->where('claim.date_billed=?', $min_date_billed);
                }
                if ($min_date_billed != null && $max_date_billed != null) {
                    $select->where('claim.date_billed>=?', $min_date_billed);
                    $select->where('claim.date_billed<=?', $max_date_billed);
                }
                if ($min_date_closed != null && $max_date_closed == null) {
                    $select->where('claim.date_closed=?', $min_date_closed);
                }
                if ($min_date_closed != null && $max_date_closed != null) {
                    $select->where('claim.date_closed>=?', $min_date_closed);
                    $select->where('claim.date_closed<=?', $max_date_closed);
                }
                //changed to input a part of account number to inquiry all accounts contains that part of account number.by james
                if ($account_number != null) {
                    //$select->where('patient.account_number=?', $account_number);
                    $select->where('patient.account_number LIKE ?', '%' . $account_number . '%');
                }
                if (count($cpt_code) > 0) {
                    $select->where('encounter.CPT_code_1 IN(?) OR encounter.CPT_code_2 IN(?) OR encounter.CPT_code_3 IN(?) OR encounter.CPT_code_4 IN(?) OR encounter.CPT_code_5 IN(?) OR encounter.CPT_code_6 IN(?)', $cpt_code);
                }
                if (count($diagnosis_code) > 0) {
                    //           $select->where('diagnosiscode_10.id  IN (?)', $diagnosis_code);   
                    $select->where('encounter.diagnosis_code1 IN(?) OR encounter.diagnosis_code2 IN(?) OR encounter.diagnosis_code3 IN(?) OR encounter.diagnosis_code4 IN(?)', $diagnosis_code);
                }
                //cici

                if (count($biller_name) > 0) {
//                    if ($last_days) {
//                        $select->where('trim(substring(log,1,locate(\':\',interactionlog.log)-1)) IN(?)', $biller_name);
//                    }
//                    else{
                    //   $select->where('replace(substring(log,1,locate(':',log)-1),' ','') IN(?)',$biller_name);
                    $select->where('trim(substring(log,1,locate(\':\',interactionlog.log)-1)) IN(?)', $biller_name);
                    //  $select->where('the_biller IN(?)', $biller_name);
                    $select->group('the_biller');
                    $select->order('interactionlog.date_and_time DESC');
//                    if ($last_days) {
//                      $select->limit($last_days);
//                    }
                }

                //$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB', 'patient.account_number'));
                $select->order(array('patient.last_name', 'patient.first_name', 'encounter.start_date_1 DESC'));
                // this is used to elimenate the repeated claim
                $select->group('encounter.id');

                /*                 * ****************Add limilt for the inquiry*************** */
                //$select->limit($limit_number);
                /*                 * ****************Add limilt for the inquiry*************** */
                if ($phone_num != null) {
                    $select->where('patient.phone_number=? OR patient.second_phone_number=?', $phone_num);
                }
                if (($cptcode_array == null) && ($phone_num == null)) {
                    $select->limit($limit_number);
                }
                $patient = Array();
                if ('guest' == $role) {
                    if ($providerList_start != null) {
                        $patient = $db->fetchAll($select);
                    }
                } else {
                    $patient = $db->fetchAll($select);
                }

                $tmp_billingcompany_id = $this->get_billingcompany_id();

                $colorAlerts = $this->get_coloralerts();

                $dd = 0;
                $count_patient = count($patient);
                for ($i = 0; $i < $count_patient; $i++) {
                    $row = $patient[$i];

                    for ($j = 0; $j < count($colorAlerts); $j++) {
                        if ($row['color_code'] == $colorAlerts[$j]['RGB'])
                            $patient[$i]['alert'] = $colorAlerts[$j]['alert'];
                    }

                    if ($row['type'] == 'primary') {
                        //$patientList[$count]['insurance_name'] = $row['insurance_name'];
                        //$patientList[$count]['insurance_display'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                        $type_tmp = 'secondary';
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $patient[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                            $patient[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $patient[$i]['insurance_s_name'] = null;
                            $patient[$i]['insurance_s_display'] = null;
                        }
                    } else if ($row['type'] == 'secondary') {
                        //$patientList[$count]['insurance_s_name'] = $row['insurance_name'];
                        //$patientList[$count]['insurance_s_display'] = $row['insurance_display'];
                        $patient[$i]['insurance_s_name'] = $row['insurance_name'];
                        $patient[$i]['insurance_s_display'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        $type_tmp = 'primary';
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $patient[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                            $patient[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $patient[$i]['insurance_name'] = null;
                            $patient[$i]['insurance_display'] = null;
                        }
                    } else {
                        $patient[$i]['insurance_name_other'] = $row['insurance_name'];
                        $patient[$i]['insurance_display_other'] = $row['insurance_display'];
                        $encounter_id = $row['encounter_id'];
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        $type_tmp = 'primary';
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $patient[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                            $patient[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $patient[$i]['insurance_name'] = null;
                            $patient[$i]['insurance_display'] = null;
                        }
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                        $type_tmp = 'secondary';
                        $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                        $result_temp = $db_encounterinsured->fetchRow($where);
                        if ($result_temp != null) {
                            $insured_s_id = $result_temp['insured_id'];
                            $db_insured = new Application_Model_DbTable_Insured();
                            $db = $db_insured->getAdapter();
                            $where = $db->quoteInto('id=?', $insured_s_id);
                            $insured_tmp = $db_insured->fetchRow($where);
                            $insurance_s_id = $insured_tmp['insurance_id'];
                            $db_insurance = new Application_Model_DbTable_Insurance();
                            $db = $db_insurance->getAdapter();
                            $where = $db->quoteInto('id=?', $insurance_s_id);
                            $insurance_tmp = $db_insurance->fetchRow($where);
                            $patient[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                            $patient[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $patient[$i]['insurance_s_name'] = null;
                            $patient[$i]['insurance_s_display'] = null;
                        }
                    }
                }
                for ($i = 0; $i < count($patient); $i++) {
                    $patient[$i]['count_flag'] = 0;
                    $amount_paid = $patient[$i]['amount_paid'];
                    $total_charge = $patient[$i]['total_charge'];
                    $per = $amount_paid / $total_charge;
                    $per = round($per, 2) * 100;
                    if ($per == 0)
                        $patient[$i]['percentage'] = "";
                    else
                        $patient[$i]['percentage'] = $per;
                    //                if($patient[$i]['balance_due']==null ||$patient[$i]['balance_due']=="")
                    //                    $patient[$i]['balance_due']="0.00";
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('interactionlog');
                    $select->where('interactionlog.claim_id=?', $patient[$i]['claim_id']);
                    $select->order('interactionlog.date_and_time DESC');
                    $tmp_interactionlogs = $db->fetchAll($select);
                    $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                    $start_date = date("Y-m-d");
                    $noactiondays = 99;
                    if ($end_date != null && $end_date != "") {
                        $noactiondays = days($start_date, $end_date);
                    } else {
                        $temp_days = 99;
                        if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_last_billed'], $patient[$i]['date_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_last_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = max($patient[$i]['date_billed'], $patient[$i]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = max($patient[$i]['date_billed'], $patient[$i]['date_last_billed']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] != null) {
                            $temp_end_date = $patient[$i]['date_rebilled'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] != null && $patient[$i]['date_billed'] == null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = $patient[$i]['date_last_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($patient[$i]['date_last_billed'] == null && $patient[$i]['date_billed'] != null && $patient[$i]['date_rebilled'] == null) {
                            $temp_end_date = $patient[$i]['date_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        }
                        $noactiondays = $temp_days;
                    }
                    if ($noactiondays <= 99)
                        $patient[$i]['last'] = $noactiondays;
                    else
                        $patient[$i]['last'] = 99;
                }
                if (count($patient) > $claim_inquiry_results_limit) {
                    $tmp_patient = $patient;
                    unset($patient);
                    for ($i = 0; $i < $claim_inquiry_results_limit; $i++) {
                        $patient[$i] = $tmp_patient[$i];
                    }
                    for ($i = 0; $i < $claim_inquiry_results_limit; $i++)
                        $patient[$i]['count_flag'] = 1;
                }


                $dd = 0;

                //set session option
                if ($provider_id)
                    $this->set_options_session($provider_id, '', $this->get_billingcompany_id());
                else
                    $this->set_options_session('', '', $this->get_billingcompany_id());
                session_start();
                $options = $_SESSION['options'];

                if ($patient == null) {
                    $this->clearsession();

                    $patient_data['last_name'] = $last_name;
                    $patient_data['first_name'] = $first_name;
                    $patient_data['account_number'] = getmrn($this->get_billingcompany_id(), 0);
                    //session_start();

                    $default = getdefault();
                    if ($default['provider'] != null) {
                        $encounter['provider_id'] = $default['provider'];
                        $encounter['renderingprovider_id'] = $default['renderingprovider'];
                        $encounter['facility_id'] = $default['facility'];
                        $pos = $this->get_pos($encounter['facility_id']);

                        $encounter['referringprovider_id'] = $default['referringprovider'];

                        $encounter['place_of_service_1'] = $pos;
                        $encounter['place_of_service_2'] = $pos;
                        $encounter['place_of_service_3'] = $pos;
                        $encounter['place_of_service_4'] = $pos;
                        $encounter['place_of_service_5'] = $pos;
                        $encounter['place_of_service_6'] = $pos;
                    } else {
                        $encounter['provider_id'] = '';
                        $encounter['renderingprovider_id'] = '';
                        $encounter['facility_id'] = '';
                        $encounter['referringprovider_id'] = '';
                        $encounter['place_of_service_1'] = '';
                    }
                    $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];
                    session_start();

                    $_SESSION['encounter_data'] = $encounter;
                    $_SESSION['patient_data'] = $patient_data;
                    $_SESSION['claim_data']['mannual_flag'] = "no";
                    $_SESSION['new_claim_flag'] = 1;
                    $_SESSION['new_patient_flag'] = 1;
                    //echo "<script> if(confirm( '请选择跳转页面，是跳转到yes.html  否跳转到no.html？ '))  location.href='yes.html';else location.href='no.html'; </script>"; 


                    $this->_redirect('/biller/claims/patient');
                } else {
                    $this->clearsession();
                    $patient_data['last_name'] = $last_name;
                    $patient_data['first_name'] = $first_name;
                    session_start();
                    $_SESSION['patient_data'] = $patient_data;

                    /*                     * ******************Add inquiry_last_name***************** */
                    $_SESSION['inquiry_last_name'] = $last_name;
                    /*                     * ******************Add inquiry_last_name***************** */
                }
                session_start();
                unset($_SESSION['tmp']);
                $_SESSION['tmp']['patient'] = $patient;
                $dd = 0;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == 'open') {
                
            }
            session_start();
            $claim_recent = $_SESSION['claim_recent'];
            foreach ($claim_recent as $key => $row) {
                $mypatient[$key] = $row['patient_last_name'] . $row['patient_first_name'];
                //Add reange of the added fields
                $dob[$key] = $row['patient_DOB'];
                $totalcharge[$key] = $row['total_charge'];
                $amtpaid[$key] = $row['amount_paid'];
                $percentage[$key] = $row['percentage'];
                $due[$key] = $row['balance_due'];
                $referringprovider[$key] = $row['referringprovider_last_name'] . $row['referringprovider_first_name'];
                $insurance[$key] = $row['insurance_display'];
                $insurance_s[$key] = $row['insurance_s_display'];
                $cptcode[$key] = $row['CPT_code_1'];
                $anescode[$key] = $row['anes_code'];
                $provider[$key] = $row['provider_name'];
                $facility[$key] = $row['facility_name'];
                $dos[$key] = format($row['start_date_1'], 0);
                $mrn[$key] = $row['account_number'];
                $last[$key] = $row['last'];
                $status[$key] = $row['claim_status_display'];
                $statement_status_display[$key] = $row['statement_status_display'];
                $bill_status_display[$key] = $row['bill_status_display'];
                $renderingprovider[$key] = $row['renderingprovider_last_name'] . $row['renderingprovider_first_name'];
            }

            //get the claim_status list
            /* No need since claim_status_display is used
              $db = Zend_Registry::get('dbAdapter');
              $select = $db->select();
              $select->from('claimstatus');
              $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
              $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?',1);
              $select->group('claimstatus.id');
              $select->order('claimstatus.claim_status_display');
              try {
              $claimstatuslist = $db->fetchAll($select);
              } catch (Exception $e) {
              echo "errormessage:" + $e->getMessage();
              }
              foreach($claimstatuslist as $row)
              {
              $translate_claim_status[$row['claim_status']]  = $row['claim_status_display'];
              }
              for($i = 0; $i < count($status); $i++)
              {
              $tmp_claim_status = $status[$i];
              $status[$i] = $translate_claim_status[$tmp_claim_status];
              }
             */
            if ($postType == 'Name') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);

                if ($mypatient_slowercase[0] >= $mypatient_slowercase[sizeof($mypatient_slowercase) - 1]) {
                    array_multisort($mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $claim_recent);
                } else {
                    array_multisort($mypatient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;

                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'DOB') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($dob[0] >= $dob[sizeof($dob) - 1]) {

                    array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'MRN') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {

                    array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }

                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'DOS') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($dos[0] >= $dos[sizeof($dos) - 1]) {

                    array_multisort($dos, SORT_ASC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Charge') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($totalcharge[0] <= $totalcharge[sizeof($totalcharge) - 1]) {
                    array_multisort($totalcharge, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {
                    array_multisort($totalcharge, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Paid') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($amtpaid[0] >= $amtpaid[sizeof($amtpaid) - 1]) {

                    array_multisort($amtpaid, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {
                    array_multisort($amtpaid, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == '%') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($percentage[0] <= $percentage[sizeof($percentage) - 1]) {

                    array_multisort($percentage, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($percentage, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }

                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Due') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($due[0] <= $due[sizeof($due) - 1]) {

                    array_multisort($due, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($due, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                }

                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'P_Insurance') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                $insurance_slowercase = array_map('strtolower', $insurance);
                if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'S_Insurance') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                $insurance_slowercase = array_map('strtolower', $insurance_s);
                if ($_SESSION['cliam_recent_sort_S_Insurance_reverse'] == 0) {
                    $_SESSION['cliam_recent_sort_S_Insurance_reverse'] = 1;
                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {
                    $_SESSION['cliam_recent_sort_S_Insurance_reverse'] = 0;
                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }

                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'CPT') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($cptcode[0] >= $cptcode[sizeof($cptcode) - 1]) {

                    array_multisort($cptcode, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($cptcode, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'A') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($anescode[0] >= $anescode[sizeof($anescode) - 1]) {

                    array_multisort($anescode, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($anescode, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Provider') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($provider[0] >= $provider[sizeof($provider) - 1]) {

                    array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($provider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Referring P') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1]) {

                    array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Rendering P') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {

                    array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Facility') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($facility[0] >= $facility[sizeof($facility) - 1]) {
                    array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {
                    array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'L') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($last[0] <= $last[sizeof($last) - 1]) {
                    array_multisort($last, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {
                    $_SESSION['cliam_recent_sort_Last_reverse'] = 0;
                    array_multisort($last, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'Claim Status') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($status[0] >= $status[sizeof($status) - 1]) {

                    array_multisort($status, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($status, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'B S') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($bill_status_display[0] >= $bill_status_display[sizeof($bill_status_display) - 1]) {

                    array_multisort($bill_status_display, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($bill_status_display, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }
            if ($postType == 'S S') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($statement_status_display[0] >= $statement_status_display[sizeof($statement_status_display) - 1]) {

                    array_multisort($statement_status_display, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $claim_recent);
                } else {

                    array_multisort($statement_status_display, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_DESC, SORT_STRING, $claim_recent);
                }
                $_SESSION['claim_recent'] = $claim_recent;
                $_SESSION['cliam_recent_sort'] = 1;
                $this->_redirect('/biller/claims/inquiry');
            }

            $patient_id = $this->getRequest()->getPost('patient_id');
            $encounter_id = $this->getRequest()->getPost('encounter_id');
            $submit_by = $this->getRequest()->getPost('submit_by');
            
            $db_encounter = new Application_Model_DbTable_Encounter();  
            $db = $db_encounter->getAdapter();  
            $where = $db->quoteInto("id = ?", $encounter_id);
            $the_encounter = $db_encounter ->fetchAll($where);
            $the_provider_id = $the_encounter[0]['provider_id'];
            if($submit_by === 'new')
                $encounter_id = "";
            
            $this->set_options_session($the_provider_id,'','');
            session_start();                        
            $options = $_SESSION['options'];            
            
            if ($patient_id != null) {
                $this->initsession($patient_id, $encounter_id);
                $patient_data = $_SESSION['patient_data'];
                $start_date_I = format($patient_data['date_statement_I'], 0);
                $start_date_II = format($patient_data['date_statement_II'], 0);
                $end_date = date("Y-m-d");
                if ($start_date_II != null) {
                    $days = days($start_date_II, $end_date);
                    if ($days >= $options['patient_statement_interval']) {
                        $_SESSION['patient_data']['statement'] = '3';
                        $_SESSION['patient_data']['statement_trigger'] = '5';
                    }
                } else {
                    if ($start_date_I != null) {
                        $days = days($start_date_I, $end_date);
                        if ($days >= $options['patient_statement_interval']) {
                            $_SESSION['patient_data']['statement'] = '2';
                            $_SESSION['patient_data']['statement_trigger'] = '5';
                        }
                    }
                }
                $_SESSION['from_top_ten'] = 1;
                if ($submit_by === 'exist')
                    $this->_redirect('/biller/claims/claim');
                else {
                    /*                     * *Add check for the new claim** */
                    session_start();
                    $_SESSION['new_claim_flag'] = 1;
                    $_SESSION['new_upload_file'] = null;
                    /*                     * *Add check for the new claim** */
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('patient_id = ?', $patient_id);
                    $encounter_data = $db_encounter->fetchAll($where, "start_date_1 DESC");

                    $encounter['provider_id'] = $encounter_data[0]['provider_id'];
                    $encounter['renderingprovider_id'] = $encounter_data[0]['renderingprovider_id'];

                    $encounter['facility_id'] = $encounter_data[0]['facility_id'];
                    $pos = $this->get_pos($encounter['facility_id']);

                    $encounter['referringprovider_id'] = $encounter_data[0]['referringprovider_id'];

                    $encounter['place_of_service_1'] = $pos;
                    $encounter['place_of_service_2'] = $pos;
                    $encounter['place_of_service_3'] = $pos;
                    $encounter['place_of_service_4'] = $pos;
                    $encounter['place_of_service_5'] = $pos;
                    $encounter['place_of_service_6'] = $pos;
                    $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];


                    $_SESSION['encounter_data'] = $encounter;
                    unset($_SESSION['sql']);
                    $this->_redirect('/biller/claims/services');
                }
            } else {
                $dd = 0;

                $this->clearsession();
                $default = getdefault();
                if ($default['provider'] != null) {
                    $encounter['provider_id'] = $default['provider'];
                    $encounter['renderingprovider_id'] = $default['renderingprovider'];
                    $encounter['facility_id'] = $default['facility'];
                    $pos = $this->get_pos($encounter['facility_id']);

                    $encounter['referringprovider_id'] = $default['referringprovider'];

                    $encounter['place_of_service_1'] = $pos;
                    $encounter['place_of_service_2'] = $pos;
                    $encounter['place_of_service_3'] = $pos;
                    $encounter['place_of_service_4'] = $pos;
                    $encounter['place_of_service_5'] = $pos;
                    $encounter['place_of_service_6'] = $pos;
                } else {
                    $encounter['provider_id'] = '';
                    $encounter['renderingprovider_id'] = '';
                    $encounter['facility_id'] = '';
                    $encounter['referringprovider_id'] = '';
                    $encounter['place_of_service_1'] = '';
                }
                $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];
                session_start();
                $_SESSION['encounter_data'] = $encounter;
                $_SESSION['patient_data']['account_number'] = getmrn($this->get_billingcompany_id(), 0);
                $_SESSION['claim_data']['mannual_flag'] = "no";
                $_SESSION['new_claim_flag'] = 1;
                $_SESSION['new_upload_file'] = null;
                $_SESSION['new_patient_flag'] = 1;
                $this->_redirect('/biller/claims/patient');
            }
        }
    }

    public function providerhasAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
        $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
        $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id = ?', $provider_id);
//        else
//            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('renderingprovider.id');
        $select->order('last_name ASC');
        $renderingproviderList = $db->fetchAll($select);
        $this->movetobot($renderingproviderList, 'last_name', 'Need New');
        $data['renderingproviderList'] = $renderingproviderList;




        /* cici */
        /*    $db = Zend_Registry::get('dbAdapter');
          $select = $db->select();
          $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
          $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
          $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');
          $select->where('provider.id = ?', $provider_id);
          $select->group('diagnosiscode_10.diagnosis_code');
          $select->order('diagnosis_code ASC');
          $diagnosis_codeList = $db->fetchAll($select);
          $data['diagnosis_codeList'] = $diagnosis_codeList; */


        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('facility', array('facility.id as fid', 'facility_display'));
        $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
        $select->join('provider', 'providerhasfacility.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id = ?', $provider_id);
//        else
//            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('facility.id');
        $select->order('facility_display ASC');
        $facilityList = $db->fetchAll($select);
        $data['facilityList'] = $facilityList;
//referringprovider
        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
        $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
        $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id = ?', $provider_id);
//        else
//            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('referringprovider.id');
        $select->order('last_name ASC');
        $referringproviderList = $db->fetchAll($select);
        $this->movetobot($referringproviderList, 'last_name', 'Need New');
        $data['referringproviderList'] = $referringproviderList;


        $db_cpt = new Application_Model_DbTable_Cptcode();
        $db = $db_cpt->getAdapter();
        $where = $db->quoteInto('provider_id = ?', $provider_id);
        //$cptcodeList = $db_cpt->fetchAll($where)->toArray();
        $cptcodeList = $db_cpt->fetchAll($where, 'CPT_code ASC')->toArray();
        $cpt_a = array();
        $i = 0;
        foreach ($cptcodeList as $cpt_code) {
            if ($cpt_code["anesthesiacode_id"] != null) {
                $cpt_a[$i] = $cpt_code;
                $cpt_a[$i]["CPT_code"] = "*" . $cpt_a[$i]["CPT_code"];
                $i++;
            }
        }
        foreach ($cptcodeList as $key => $cpt_code) {
            if ($cpt_code["CPT_code"] == "Need New") {
                $cpt_a[$i] = $cpt_code;
                unset($cptcodeList[$key]);
            }
        }
        $count = count($cptcodeList);
        foreach ($cpt_a as $cpt_a_item) {
            $cptcodeList[$count] = $cpt_a_item;
            $count++;
        }
        //$this->movetobot($CptcodeList, 'CPT_code','Need New');
        $data['cptcodeList'] = $cptcodeList;


        /*        $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
          $db = $db_diagnosiscode->getAdapter();
          $where = $db->quoteInto('provider_id = ?', $provider_id);
          $diagnosiscodeList = $db_diagnosiscode->fetchAll($where, 'diagnosis_code ASC')->toArray();
          $data['diagnosiscodeList'] = $diagnosiscodeList; */
        $dbdiag = Zend_Registry::get('dbAdapter');
        $sqlold = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode join diagnosiscode
on providerhasdiagnosiscode.diagnosiscode_id = diagnosiscode.id
where provider_id = ?
order by diagnosis_code
SQL;

        $sqlnew = <<<SQL
select diagnosis_code,description from providerhasdiagnosiscode_10 join diagnosiscode_10 
on providerhasdiagnosiscode_10.diagnosiscode_10_id = diagnosiscode_10.id
where provider_id = ?
order by diagnosis_code
SQL;
        $dos = $encounter_data['start_date_1'];
        if (compare_time($dos)) {
            $sql = $sqlnew;
        } else {
            $sql = $sqlold;
        }
        $paras = array($provider_id);
        $result = $dbdiag->query($sql, $paras);
        //获取所有行
        $diagnosiscode_data = $result->fetchAll();
        $this->movetobot($diagnosiscode_data, 'diagnosis_code', 'Need New');

        $diagnosiscodeList = array();
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['diagnosis_code'] . " " . $row['description'];
            $idx++;
        }
        $data['diagnosiscodeList'] = $diagnosiscodeList;
        //将获取值赋值给providerlist
        //$this->view->diagnosiscodeList = $diagnosiscodeList;
        //zw
        /*
          $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
          $db = $db_anesthesia->getAdapter();
          $where = $db->quoteInto('provider_id=?', $provider_id) . $db->quoteInto('AND surgery_code=?', null);
          $AnesthesiaList = $db_anesthesia->fetchAll($where, 'anesthesia_code ASC')->toArray();
         * 
         */
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('anesthesiacode');
        $select->where('provider_id = ?', $provider_id);
        $select->group('anesthesia_code');
        $select->order('anesthesia_code ASC');
        $AnesthesiaList = $db->fetchAll($select);
        $this->movetobot($CptcodeList, 'anesthesia_code', 'Need New');
        $data['anesthesiaList'] = $AnesthesiaList;

//set options session
//        $this->unset_options_session();
//        $this->set_options_session($provider_id, '', '');
//        session_start();
//        $data['facility_id'] = $_SESSION['options']['default_facility'];
//        $data['modifier'] = $_SESSION['options']['default_modifier'];
//        $data['place_of_service'] = $_SESSION['options']['default_place_of_service'];
//echo renderingprovider list
        $json = Zend_Json::encode($data);
        echo $json;
//        unset($_SESSION['options']);
//
//
//
//        $select->from('billingcompany');
//        $select->join('provider', 'billingcompany.id=provider.billingcompany_id ');
//        $select->join('options', 'options.id=provider.options_id');
//        $select->where('billingcompany.id=?', $billingcompany_id);
////            $db_options = new Application_Model_DbTable_Options();
////            $db = $db_options->getAdapter();
////            $where = $db->quoteInto('id', $provider_id);
//        $options_data = $db->fetchRow($select);
////$options_data = $db_options->fetchRow($where);
//        $_SESSION['options'] = $options_data;
    }

    public function multiproviderhasAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
//        $provider_id=array();
//        if(strlen(provider_id_array)>0){
//            $provider_id=explode(',',$provider_id_array);
//        }
//        $db = Zend_Registry::get('dbAdapter');
////get renderingprovider list
//        $select = $db->select();
//        $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
//        $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
//        $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
//        if ($provider_id)
//            $select->where('provider.id = IN(?)', $provider_id);
////        else
////            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
//        $select->group('renderingprovider.id');
//        $select->order('last_name ASC');
//        $renderingproviderList = $db->fetchAll($select);
//        $data['renderingproviderList'] = $renderingproviderList;

        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('provider');
        $select->join('providerhasrenderingprovider', 'providerhasrenderingprovider.provider_id = provider.id');
        $select->join('renderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id', array('renderingprovider.id as rid', 'last_name', 'first_name'));
//        $select->from('renderingprovider', array('renderingprovider.id as rid', 'last_name', 'first_name'));
//        $select->join('providerhasrenderingprovider', 'renderingprovider.id = providerhasrenderingprovider.renderingprovider_id');
//        $select->join('provider', 'providerhasrenderingprovider.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id IN(?)', $provider_id);
        else
            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('renderingprovider.id');
        $select->order('last_name ASC');
        $renderingproviderList = $db->fetchAll($select);
        $data['renderingproviderList'] = $renderingproviderList;


        /* cici */
        $db = Zend_Registry::get('dbAdapter');

        $select = $db->select();
        $select->from('diagnosiscode_10', array('diagnosiscode_10.id as did', 'diagnosis_code'));
        $select->join('providerhasdiagnosiscode_10', 'diagnosiscode_10.id=providerhasdiagnosiscode_10.diagnosiscode_10_id');
        $select->join('provider', 'providerhasdiagnosiscode_10.provider_id = provider.id');
        if ($provider_id) {
            //$select->where("providerhasdiagnosiscode_10.provider_id IN (?)", $provider_id);
            $select->where('provider.id IN(?)', $provider_id);
        } else {
            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        }
        $select->group('diagnosiscode_10.diagnosis_code');
        $select->order('diagnosis_code ASC');
        $diagnosis_codeList = $db->fetchAll($select);
        $data['diagnosis_codeList'] = $diagnosis_codeList;




        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('facility', array('facility.id as fid', 'facility_display'));
        $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
        $select->join('provider', 'providerhasfacility.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id IN(?)', $provider_id);
        else
            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('facility.id');
        $select->order('facility_display ASC');
        $facilityList = $db->fetchAll($select);
        $data['facilityList'] = $facilityList;
//referringprovider
        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('referringprovider', array('referringprovider.id as rid', 'last_name', 'first_name'));
        $select->join('providerhasreferringprovider', 'referringprovider.id = providerhasreferringprovider.referringprovider_id');
        $select->join('provider', 'providerhasreferringprovider.provider_id = provider.id');
        if ($provider_id)
            $select->where('provider.id IN(?)', $provider_id);
        else
            $select->where('provider.billingcompany_id = ?', $this->get_billingcompany_id());
        $select->group('referringprovider.id');
        $select->order('last_name ASC');
        $referringproviderList = $db->fetchAll($select);
        $data['referringproviderList'] = $referringproviderList;

//set options session
//        $this->unset_options_session();
//        $this->set_options_session($provider_id, '', '');
//        session_start();
//        $data['facility_id'] = $_SESSION['options']['default_facility'];
//        $data['modifier'] = $_SESSION['options']['default_modifier'];
//        $data['place_of_service'] = $_SESSION['options']['default_place_of_service'];
//echo renderingprovider list
        $json = Zend_Json::encode($data);
        echo $json;
//        unset($_SESSION['options']);
//
//
//
//        $select->from('billingcompany');
//        $select->join('provider', 'billingcompany.id=provider.billingcompany_id ');
//        $select->join('options', 'options.id=provider.options_id');
//        $select->where('billingcompany.id=?', $billingcompany_id);
////            $db_options = new Application_Model_DbTable_Options();
////            $db = $db_options->getAdapter();
////            $where = $db->quoteInto('id', $provider_id);
//        $options_data = $db->fetchRow($select);
////$options_data = $db_options->fetchRow($where);
//        $_SESSION['options'] = $options_data;
    }

    public function facilityposAction() {
        $this->_helper->viewRenderer->setNoRender();
        $facility_id = $_POST['facility_id'];
        $db = Zend_Registry::get('dbAdapter');

        //$db_facility = new Application_Model_DbTable_Facility();
        //$db = $db_facility->getAdapter();
        //$where = $db->quoteInto('id = ?', $facility_id);
        //$facility_data = $db_facility->fetchRow($where);
        $data['POS'] = $this->get_pos($facility_id);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function inquiryresultsAction() {

        /*         * **************************test for patient_data*************************
          session_start();
          $tmp_patient_data =  $_SESSION['patient_data'];
          /****************************test for patient_data************************ */
        $this->clearsession();
        if (!$this->getRequest()->isPost()) {
            session_start();
            $patient = $_SESSION['tmp']['patient'];

            /*             * **************************test for patient_data************************ */
            //$_SESSION['patient_data'] = $tmp_patient_data;
            $tmp_patient_data = $_SESSION['patient_data'];

            $inquiry_last_name = $_SESSION['inquiry_last_name'];

            $list_size = sizeof($patient);
            for ($i = 0; $i < $list_size; $i++)
                $patient[$i]['inquiry_last_name'] = $inquiry_last_name;
            /*             * **************************test for patient_data************************ */


            $patientList = getpatientList($patient);

            /*
              for($i = 0; $i < count($patientList); $i++)
              {
              $tmp = $patientList[$i]['name'];
              $patientList[$i]['name'] = substr($tmp,0,15);
              }
             */

            $dd = 0;

            /*             * **************Add claimstatus for ***************** */
            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }

            /*             * **************Add claimstatus for ***************** */


            /*             * *********************translate the claim_status**************** */

            foreach ($claimstatuslist as $row) {
                $translate_claim_status[$row['claim_status']] = $row['claim_status_display'];
            }
            for ($i = 0; $i < count($patientList); $i++) {
                $tmp_claim_status = $patientList[$i]['claim_status'];
                $patientList[$i]['claim_status'] = $translate_claim_status[$tmp_claim_status];
            }

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('billstatus');
            $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
            $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('billstatus.requried = ?', 1);
            $select->group('billstatus.id');
            $select->order('billstatus.bill_status');
            try {
                $billstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }

            /*             * **************Add claimstatus for ***************** */


            /*             * *********************translate the claim_status**************** */

            foreach ($billstatuslist as $row) {
                $translate_bill_status[$row['bill_status']] = $row['bill_status_display'];
            }
            for ($i = 0; $i < count($patientList); $i++) {
                $tmp_bill_status = $patientList[$i]['bill_status'];

                $patientList[$i]['bill_status'] = $translate_bill_status[$tmp_bill_status];
            }

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('statementstatus');
            $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
            $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('statementstatus.requried = ?', 1);
            $select->group('statementstatus.id');
            $select->order('statementstatus.statement_status');
            try {
                $billstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }

            /*             * **************Add claimstatus for ***************** */


            /*             * *********************translate the claim_status**************** */

            foreach ($billstatuslist as $row) {
                $translate_bill_status[$row['statement_status']] = $row['statement_status_display'];
            }
            for ($i = 0; $i < count($patientList); $i++) {
                $tmp_bill_status = $patientList[$i]['statement_status'];

                $patientList[$i]['statement_status'] = $translate_bill_status[$tmp_bill_status];
            }
            /*             * *********************translate the claim_status**************** */

            $this->view->patientList = $patientList;
        }

        if ($this->getRequest()->isPost()) {

            $postType = $this->getRequest()->getParam('post');
            session_start();
            $patient = $_SESSION['tmp']['patient'];
            foreach ($patient as $key => $row) {
                $mypatient[$key] = $row['patient_last_name'] . $row['patient_first_name'];

                //Add reange of the added fields

                $dob[$key] = format($row['patient_DOB'], 0);
                $totalcharge[$key] = $row['total_charge'];
                $amtpaid[$key] = $row['amount_paid'];
                $percentage[$key] = $row['percentage'];
                $due[$key] = $row['balance_due'];
                $referringprovider[$key] = $row['referringprovider_last_name'] . $row['referringprovider_first_name'];

                //Add reange of the added fields
                // $insurance[$key] = $row['insurance_name'];
                $insurance[$key] = $row['insurance_display'];
                $insurance_s[$key] = $row['insurance_s_display'];
                $cptcode[$key] = $row['CPT_code_1'];
                $anescode[$key] = $row['anes_code'];
                $provider[$key] = $row['provider_name'];
                $facility[$key] = $row['facility_name'];
                $dos[$key] = format($row['start_date_1'], 0);
                $mrn[$key] = $row['account_number'];
                $last[$key] = $row['last'];
                $status[$key] = $row['claim_status'];
                $bill_status_display[$key] = $row['bill_status_display'];
                $statement_status_display[$key] = $row['statement_status_display'];
                $renderingprovider[$key] = $row['renderingprovider_last_name'] . $row['renderingprovider_first_name'];
            }

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->get_billingcompany_id())or where('claimstatus.requried = ?', 1);
            $select->group('claimstatus.id');
            $select->order('claimstatus.claim_status_display');
            try {
                $claimstatuslist = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }


            foreach ($claimstatuslist as $row) {
                $translate_claim_status[$row['claim_status']] = $row['claim_status_display'];
            }
            for ($i = 0; $i < count($status); $i++) {
                $tmp_claim_status = $status[$i];
                $status[$i] = $translate_claim_status[$tmp_claim_status];
            }


            //Add reange of the added fields
            if ($postType == "DOB") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($dob[0] >= $dob[sizeof($dob) - 1]) {

                    array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Charge") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($totalcharge[0] <= $totalcharge[sizeof($totalcharge) - 1]) {

                    array_multisort($totalcharge, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($totalcharge, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Paid") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($amtpaid[0] >= $amtpaid[sizeof($amtpaid) - 1]) {

                    array_multisort($amtpaid, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($amtpaid, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "%") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($percentage[0] <= $percentage[sizeof($percentage) - 1]) {

                    array_multisort($percentage, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($percentage, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Due") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($due[0] <= $due[sizeof($due) - 1]) {
                    $_SESSION['inqury_r_Due'] = 1;
                    array_multisort($due, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {
                    $_SESSION['inqury_r_Due'] = 0;
                    array_multisort($due, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Referring P") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1]) {

                    array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            //Add reange of the added fields

            if ($postType == "Name") {

                /*                 * *****************sort the insurance****************** */
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($mypatient_slowercase[0] >= $mypatient_slowercase[sizeof($mypatient_slowercase) - 1]) {

                    array_multisort($mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $patient);
                } else {

                    array_multisort($mypatient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $patient);
                }
                //array_multisort($mypatient, SORT_ASC, $patient);
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Facility") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($facility[0] >= $facility[sizeof($facility) - 1]) {

                    array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "DOS") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($dos[0] >= $dos[sizeof($dos) - 1]) {

                    array_multisort($dos, SORT_ASC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $patient);
                } else {

                    array_multisort($dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "MRN") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {

                    array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "L") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($last[0] <= $last[sizeof($last) - 1]) {

                    array_multisort($last, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($last, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Claim Status") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($status[0] >= $status[sizeof($status) - 1]) {
                    array_multisort($status, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {
                    array_multisort($status, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "B S") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($bill_status_display[0] >= $bill_status_display[sizeof($bill_status_display) - 1]) {
                    array_multisort($bill_status_display, SORT_ASC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $patient);
                } else {
                    array_multisort($bill_status_display, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "S S") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($statement_status_display[0] >= $statement_status_display[sizeof($statement_status_display) - 1]) {
                    array_multisort($statement_status_display, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {
                    array_multisort($statement_status_display, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "P_Insurance") {
                /*                 * *****************sort the insurance****************** */
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                $insurance_slowercase = array_map('strtolower', $insurance);
                if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {
                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == 'S_Insurance') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                $insurance_slowercase = array_map('strtolower', $insurance_s);
                if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == 'CPT') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($cptcode[0] >= $cptcode[sizeof($cptcode) - 1]) {

                    array_multisort($cptcode, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($cptcode, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == 'A Code') {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($anescode[0] >= $anescode[sizeof($anescode) - 1]) {

                    array_multisort($anescode, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($anescode, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($postType == "Provider") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($provider[0] >= $provider[sizeof($provider) - 1]) {

                    array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($provider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }
                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }

            if ($postType == "Rendering P") {
                $mypatient_slowercase = array_map('strtolower', $mypatient);
                if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {

                    array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                } else {

                    array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $mypatient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_ASC, $patient);
                }

                $_SESSION['tmp']['patient'] = $patient;
                $this->_redirect('/biller/claims/inquiryresults');
            }




            $patient_id = $this->getRequest()->getPost('patient_id');
            $encounter_id = $this->getRequest()->getPost('encounter_id');
            $this->view->providerList = $providerList;
            $this->set_options_session();
            session_start();
            $options = $_SESSION['options'];

            if ($patient_id != null) {

                $this->initsession($patient_id, $encounter_id);
//statement triger 5 status not close               

                $patient_data = $_SESSION['patient_data'];
                $start_date_I = format($patient_data['date_statement_I'], 0);
                $start_date_II = format($patient_data['date_statement_II'], 0);
                $end_date = date("Y-m-d");
                if ($start_date_II != null) {
                    $days = days($start_date_II, $end_date);
                    if ($days >= $options['patient_statement_interval']) {
                        $_SESSION['patient_data']['statement'] = '3';
                        $_SESSION['patient_data']['statement_trigger'] = '5';
                    }
                } else {
                    if ($start_date_I != null) {
                        $days = days($start_date_I, $end_date);
                        if ($days >= $options['patient_statement_interval']) {
                            $_SESSION['patient_data']['statement'] = '2';
                            $_SESSION['patient_data']['statement_trigger'] = '5';
                        }
                    }
                }

                if ($encounter_id != null)
                    $this->_redirect('/biller/claims/claim');
                else {
                    /*                     * *Add check for the new claim** */
                    session_start();
                    $_SESSION['new_claim_flag'] = 1;
                    $_SESSION['new_upload_file'] = null;
                    /*                     * *Add check for the new claim** */
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('patient_id = ?', $patient_id);
                    $encounter_data = $db_encounter->fetchAll($where, "start_date_1 DESC");

                    $encounter['provider_id'] = $encounter_data[0]['provider_id'];
                    $encounter['renderingprovider_id'] = $encounter_data[0]['renderingprovider_id'];
                    $encounter['facility_id'] = $encounter_data[0]['facility_id'];
                    $pos = $this->get_pos($encounter['facility_id']);

                    $encounter['referringprovider_id'] = $encounter_data[0]['referringprovider_id'];

                    $encounter['place_of_service_1'] = $pos;
                    $encounter['place_of_service_2'] = $pos;
                    $encounter['place_of_service_3'] = $pos;
                    $encounter['place_of_service_4'] = $pos;
                    $encounter['place_of_service_5'] = $pos;
                    $encounter['place_of_service_6'] = $pos;
                    $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];


                    $_SESSION['encounter_data'] = $encounter;
                    $this->_redirect('/biller/claims/services');
                }
            } else {
                $dd = 0;

                $this->clearsession();
                $default = getdefault();
                if ($default['provider'] != null) {
                    $encounter['provider_id'] = $default['provider'];
                    $encounter['renderingprovider_id'] = $default['renderingprovider'];
                    $encounter['facility_id'] = $default['facility'];
                    $pos = $this->get_pos($encounter['facility_id']);

                    $encounter['referringprovider_id'] = $default['referringprovider'];

                    $encounter['place_of_service_1'] = $pos;
                    $encounter['place_of_service_2'] = $pos;
                    $encounter['place_of_service_3'] = $pos;
                    $encounter['place_of_service_4'] = $pos;
                    $encounter['place_of_service_5'] = $pos;
                    $encounter['place_of_service_6'] = $pos;
                } else {
                    $encounter['provider_id'] = '';
                    $encounter['renderingprovider_id'] = '';
                    $encounter['facility_id'] = '';
                    $encounter['referringprovider_id'] = '';
                    $encounter['place_of_service_1'] = '';
                }
                $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];
                session_start();
                $_SESSION['encounter_data'] = $encounter;
                $_SESSION['patient_data']['account_number'] = getmrn($this->get_billingcompany_id(), 0);
                $_SESSION['claim_data']['mannual_flag'] = "no";
                $_SESSION['new_claim_flag'] = 1;
                $_SESSION['new_patient_flag'] = 1;
                $_SESSION['new_upload_file'] = null;
                $this->_redirect('/biller/claims/patient');
            }
        }
    }

    public function billlistAction() {

        /* generate the patientList<By Yu Lang> */
        $this->_helper->viewRenderer->setNoRender();

        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        $providerList = array();
        for ($i = 0; $i < count($userfocusonprovider); $i++) {
            $providerList[$i] = $userfocusonprovider[$i]['provider_id'];
        }


        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
        $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('options', 'options.id = provider.options_id');
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        //Add the referringprovider
        $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
        //Add the referringprovider
        $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid', 'bill_status'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB as p_DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        //$select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
        $select->where('provider.billingcompany_id=?', $this->get_billingcompany_id());
        /* Add the second insurance not billed <By YuLang> */ /* Add the status open_not_rebilled claim status */
        //$select->where('claim.claim_status IN(?)', array('open_ready_primary_bill', 'open_ready_delayed_primary_bill', 'open_not_rebilled'));
        $select->where('claim.bill_status IN(?)', array('bill_ready_bill_primary', 'bill_ready_bill_delayed_primary'));
        $select->where('claim.claim_status LIKE ?', "open%");
        /*         * *New insurance change 2013-03-17** */
        $select->where('encounterinsured.type = ?', 'primary');
        /*         * *New insurance change** */

        /* Add the second insurance not billed <By YuLang> */
        if ($providerList != null) {
            $select->where('provider.id IN(?)', $providerList);
        }

        $select->order(array('encounter.start_date_1', 'p_last_name', 'p_first_name', 'p_DOB'));
        $patient = $db->fetchAll($select);


        /*         * **********************for the second insurance By<YuLang>*************** */
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
        $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name as provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('options', 'options.id = provider.options_id');
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        //Add the referringprovider
        $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
        //Add the referringprovider
        $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid', 'bill_status'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB as p_DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        //$select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id', array('insurance_display', 'insurance.claim_submission_preference AS means'));
        $select->where('provider.billingcompany_id=?', $this->get_billingcompany_id());
        //$select->where('claim.claim_status IN(?) ', array('open_ready_secondary_bill'));
        $select->where('claim.bill_status IN(?) ', array('bill_ready_bill_secondary'));
        $select->where('claim.claim_status LIKE ?', "open%");
        $select->order(array('encounter.start_date_1', 'p_last_name', 'p_first_name', 'p_DOB'));
        /*         * *New insurance change 2013-03-17** */
        $select->where('encounterinsured.type = ?', 'secondary');
        /*         * *New insurance change 20130-03-17** */
        $patient_sec = $db->fetchAll($select);
        /*         * **********************For the second insurance By<YuLang>********************* */
        /*     For the other insurance by Peng            */
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
        $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name as provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('options', 'options.id = provider.options_id');
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        //Add the referringprovider
        $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
        //Add the referringprovider
        $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid', 'bill_status'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB as p_DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        //$select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id', array('insurance_display', 'insurance.claim_submission_preference AS means'));
        $select->where('provider.billingcompany_id=?', $this->get_billingcompany_id());
        //$select->where('claim.claim_status IN(?) ', array('open_ready_secondary_bill'));
        $select->where('claim.bill_status IN(?) ', array('bill_ready_bill_other'));
        $select->where('claim.claim_status LIKE ?', "open%");
        $select->order(array('encounter.start_date_1', 'p_last_name', 'p_first_name', 'p_DOB'));
        /*         * *New insurance change 2013-03-17** */
        $select->where('encounterinsured.type = ?', 'other');
        /*         * *New insurance change 20130-03-17** */
        $patient_other = $db->fetchAll($select);
        /*     For the other insurance by Peng            */
        $patientList = array();
        $count = 0;


        foreach ($patient_sec as $row) {
            //strtolower($row['claim_status']) == 'open_ready_secondary_bill'
            if (strtolower($row['bill_status']) == 'bill_ready_bill_secondary') {
                $patientList[$count]['Comment'] = 'Secondary Billing';
            }
            $patientList[$count]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
            $patientList[$count]['encounter_id'] = $row['encounter_id'];
            $patientList[$count]['patient_id'] = $row['patient_id'];
            $patientList[$count]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
            $patientList[$count]['MRN'] = $row['account_number'];
            $patientList[$count]['insurance_name'] = $row['insurance_name'];
            $patientList[$count]['insurance_display'] = $row['insurance_display'];
            $patientList[$count]['facility_name'] = $row['facility_name'];
            $patientList[$count]['provider_name'] = $row['provider_name'];

            /*             * ****************Add fields for bill claim lists********************** */
            $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
            $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
            $patientList[$count]['total_charge'] = $row['total_charge'];
            $patientList[$count]['amount_paid'] = $row['amount_paid'];
            if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
            else
                $patientList[$count]['referringprovider_name'] = '';
            /*             * ****************Add fields for bill claim lists********************** */

            $patientList[$count]['DOB'] = format($row['p_DOB'], 1);
            /*             * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
            $patientList[$count]['means'] = 'MAIL';
            /*             * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
            $patientList[$count]['claim_id'] = $row['claim_id'];
            $count = $count + 1;
        }

        foreach ($patient_other as $row) {
            //strtolower($row['claim_status']) == 'open_ready_secondary_bill'
            if (strtolower($row['bill_status']) == 'bill_ready_bill_other') {
                $patientList[$count]['Comment'] = 'Other Billing';
            }
            $patientList[$count]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
            $patientList[$count]['encounter_id'] = $row['encounter_id'];
            $patientList[$count]['patient_id'] = $row['patient_id'];
            $patientList[$count]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
            $patientList[$count]['MRN'] = $row['account_number'];
            $patientList[$count]['insurance_name'] = $row['insurance_name'];
            $patientList[$count]['insurance_display'] = $row['insurance_display'];
            $patientList[$count]['facility_name'] = $row['facility_name'];
            $patientList[$count]['provider_name'] = $row['provider_name'];

            /*             * ****************Add fields for bill claim lists********************** */
            $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
            $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
            $patientList[$count]['total_charge'] = $row['total_charge'];
            $patientList[$count]['amount_paid'] = $row['amount_paid'];
            if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
            else
                $patientList[$count]['referringprovider_name'] = '';
            /*             * ****************Add fields for bill claim lists********************** */

            $patientList[$count]['DOB'] = format($row['p_DOB'], 1);
            /*             * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
            $patientList[$count]['means'] = 'MAIL';
            /*             * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
            $patientList[$count]['claim_id'] = $row['claim_id'];
            $count = $count + 1;
        }

        foreach ($patient as $row) {
            $flag = 0;
            /* if (strtolower($row['claim_status']) == 'open_ready_primary_bill') {
              $flag = 1;
              $patientList[$count]['Comment'] = 'New Bill';
              } */
            if (strtolower($row['bill_status']) == 'bill_ready_bill_primary') {
                $flag = 1;
                $patientList[$count]['Comment'] = 'New Bill';
            }
            /*             * **********************Add the billlist of the rebilled************************ */

            /*             * **********************Add the billlist of the rebilled************************ */
            //strtolower($row['claim_status']) == 'open_ready_delayed_primary_bill'
            foreach ($insured_data as $irow) {
                if ($irow['id'] == $primary_insurance) {
                    $tmp_insurance_id = $irow['insurance_id'];
                    break;
                }
            }
            //$tmp_insurance_id = $row['insurance_id'];            
            //if($tmp_insurance_id == null || $tmp_insurance_id == "" || substr($tmp_insurance_id, 0, 4) == "Temp") {
            //    foreach($insured_data as $row) {
            //    if($row['id'] ==  $primary_insurance)
            //        $tmp_insurance_id = $row['insurance_id'];            
            //    }
            //}
            $tmp_insurance_id = $row[insurance_id];
            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('id = ?', $tmp_insurance_id);
            $insurance_data = $db_insurance->fetchRow($where);
            $exist_tags = $insurance_data['tags'];
            $exist_tags_List = explode('|', $exist_tags);
            foreach ($exist_tags_List as $tags_row) {
                $temp_number = 0;
                $tp_exist_tags = explode('=', $tags_row);
                if ($tp_exist_tags[0] == "delaybilling") {
                    $temp_number = $tp_exist_tags[1];
                    break;
                }
            }
            $number_of_days_for_delayed_bill_generation = $row["number_of_days_for_delayed_bill_generation" . $temp_number];

            if (strtolower($row['bill_status']) == 'bill_ready_bill_delayed_primary') {
                $delay_days = $number_of_days_for_delayed_bill_generation - day_diff(date('Y-m-d'), $row['start_date_1']);
                if ($delay_days <= 0) {
                    $flag = 1;
                    $delay_days = -$delay_days;
                    $patientList[$count]['Comment'] = 'Passed delay time ' . $delay_days . ' days';
                }
            }
            if ($flag == 1) {
                $patientList[$count]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
                $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
                $patientList[$count]['encounter_id'] = $row['encounter_id'];
                $patientList[$count]['patient_id'] = $row['patient_id'];
                $patientList[$count]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
                $patientList[$count]['MRN'] = $row['account_number'];
                $patientList[$count]['insurance_name'] = $row['insurance_name'];
                $patientList[$count]['insurance_display'] = $row['insurance_display'];
                $patientList[$count]['facility_name'] = $row['facility_name'];
                $patientList[$count]['provider_name'] = $row['provider_name'];

                /*                 * ****************Add fields for bill claim lists********************** */
                $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
                $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
                $patientList[$count]['total_charge'] = $row['total_charge'];
                $patientList[$count]['amount_paid'] = $row['amount_paid'];
                if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                    $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                else
                    $patientList[$count]['referringprovider_name'] = '';
                /*                 * ****************Add fields for bill claim lists********************** */


                $patientList[$count]['DOB'] = format($row['p_DOB'], 1);
                /*                 * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                $patientList[$count]['means'] = strtoupper($row['means']);
                /*                 * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                $patientList[$count]['claim_id'] = $row['claim_id'];
                $count = $count + 1;
            }
        }

        session_start();

        unset($_SESSION['tmp']);
        $_SESSION['tmp']['billList'] = $patientList;
        //$_SESSION['actionList'] = $taiList;
        $this->_redirect('/biller/claims/bill');
        //$this->_redirect('/biller/claims/newinsurance');

        /*         * *generate the patientList<By Yu Lang>** */
    }

    /*     * **Add for testing insurance*** */

    public function newinsuranceAction() {
        // $this->_redirect('/biller/claims/newinsurance');
    }

    /*     * **Add for testing insurance*** */

    public function billAction() {
        if (!$this->getRequest()->isPost()) {
            //error_log( "Accendo Log -- beore retrieve bills data:" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
            session_start();
            $patientList = $_SESSION['tmp']['billList'];
            /*            $db_provider = new Application_Model_DbTable_Provider();
              $db = $db_provider ->getAdapter();

              foreach($patientList as $key => $patient)
              {
              $provider_name = $patient[provider_name];
              $where = $db->quoteInto('provider_name=?', $provider_name);
              $exist = $db_provider->fetchRow($where);
              $provider_id = $exist["id"];

              } */
            $billingcompany_id = $this->get_billingcompany_id();
            $cms_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/cms1500';
            $claim_paths = array();
            $new_path_cms = array();
            if (is_dir($cms_dir)) {
                foreach (glob($cms_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            $display = array();
            for ($i = 0; $i < count($claim_paths); $i++) {
                $new_path_cms[$i]['path'] = $claim_paths[$i];

                $claim_paths_array = explode('/', $claim_paths[$i]);
                $new_path_cms[$i]['display'] = $claim_paths_array[count($claim_paths_array) - 1];
                $display[$i] = $new_path_cms[$i]['display'];
            }
            array_multisort($display, SORT_DESC, $new_path_cms);
            $this->view->cmsList = $new_path_cms;
            $this->view->patientList = $patientList;

            $cms_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/edi';
            $claim_paths = array();
            $new_path_edi = array();
            if (is_dir($cms_dir)) {
                foreach (glob($cms_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            $display = array();
            for ($i = 0; $i < count($claim_paths); $i++) {
                $new_path_edi[$i]['path'] = $claim_paths[$i];

                $claim_paths_array = explode('/', $claim_paths[$i]);
                $new_path_edi[$i]['display'] = $claim_paths_array[count($claim_paths_array) - 1];
                $display[$i] = $new_path_edi[$i]['display'];
            }
            array_multisort($display, SORT_DESC, $new_path_edi);
            //error_log( "Accendo Log -- after get bills list:" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
            $this->view->ediList = $new_path_edi; 
        }

        if ($this->getRequest()->isPost()) {
            $document_feature = false;
            $billingcompany_id = $this->get_billingcompany_id();
            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $where = $db->quoteInto('id = ?', $billingcompany_id);
            $company_data = $db_billingcompany->fetchRow($where);
            if ($company_data['document_feature'] == 1) {
                $document_feature = true;
            }
            $sec_flag = 0;

            if ($this->getRequest()->getPost('sec_cms2') != "" || $this->getRequest()->getPost('cms2') != "" || $this->getRequest()->getPost('edi') != "" || $this->getRequest()->getPost('cms') != "" || $this->getRequest()->getPost('fax') != "" || $this->getRequest()->getPost('sec_cms') != "") {
                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $claim_id_string = $this->getRequest()->getPost('claim_id');
                $encounter_id_array = array();
                $claim_id_array = array();
                $options = array();
                $provider_id_array = array();
                $billingcompany_id = $this->billingcompany_id;
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                if (strlen($claim_id_string) > 0)
                    $claim_id_array = explode(',', $claim_id_string);
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $option = array('x12_partner_id' => 1, 'encounter_id' => $encounter_id);
                    array_push($options, $option);
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                }
                if (count($options) <= 0) {
                    $patientList = $_SESSION['tmp']['billList'];
                    $this->view->patientList = $patientList;
                    return;
                }
                $ret = array();
                /* the edi and cmi return parameter<PanDazhao> */
                $re = array();
                if (($this->getRequest()->getPost('edi')) != "") {

                    $gen_log_flag = 2;
                    gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id);
                    $re = gen_x12_837($options, $this->sysdoc_path, $billingcompany_id);

                    $ret = $re['ret'];
                    $filename = $re['file_name'];
                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;

                        $interactionlogs_data['log'] = $username . ": Bill generation EDI File";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }
                if (($this->getRequest()->getPost('cms')) != "") {
                    /**
                     * cms genetarion status
                     * Author:Qiao
                     * Time:09/01/2011
                     */
                    $gen_log_flag = 1;
                    $re = gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id, false, true);
                    $ret = $re['ret'];
                    $filename = $re['file_name'];
                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;
                        $interactionlogs_data['log'] = $username . ": Bill generation CMS1500 File";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }
                if (($this->getRequest()->getPost('cms2')) != "") {
                    /**
                     * cms genetarion status
                     * Author:Qiao
                     * Time:09/01/2011
                     */
                    $gen_log_flag = 1;
                    $re = gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id, false, false);
                    $ret = $re['ret'];
                    $filename = $re['file_name'];
                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;
                        $interactionlogs_data['log'] = $username . ": Bill generation CMS1500 File";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }
                /* sec_cms genetarion status By<Yu Lang> */

                if (($this->getRequest()->getPost('sec_cms')) != "") {
                    $sec_flag = 1;
                    $gen_log_flag = 1;

                    $re = gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id);
                    $ret = $re['ret'];
                    $filename = $re['file_name'];
                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;
                        $interactionlogs_data['log'] = $username . ": Bill generation Secondary/Other File";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }
                if (($this->getRequest()->getPost('sec_cms2')) != "") {
                    $sec_flag = 1;
                    $gen_log_flag = 1;

                    $re = gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id, false, false);
                    $ret = $re['ret'];
                    $filename = $re['file_name'];
                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;
                        $interactionlogs_data['log'] = $username . ": Bill generation Secondary/Other File";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }
                /* sec_cms genetarion status By<Yu Lang> */


                if (($this->getRequest()->getPost('fax')) != "") {

                    $gen_log_flag = 0;

                    gen_hcfa_1500_pdf($document_feature, $options, $this->sysdoc_path, $sec_flag, $gen_log_flag, $provider_id_array, $billingcompany_id);
                    $ret = gen_fax_pages($options, $this->sysdoc_path, $provider_id_array, $billingcompany_id);

                    $today = date("Y-m-d H:i:s");
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    foreach ($claim_id_array as $key => $claim_id) {
                        $interactionlogs_data['claim_id'] = $claim_id;
                        $interactionlogs_data['date_and_time'] = $today;

                        $interactionlogs_data['log'] = $username . ": Bill generation Fax";
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                    //$this->_redirect('/biller/claims/bill');
                }
                $success_claims_id = array();
                foreach ($ret as $index => $status)
                    if ($status == 1)
                        array_push($success_claims_id, $claim_id_array[$index]);

                $db = Zend_Registry::get('dbAdapter');
                $db_claim = new Application_Model_DbTable_Claim();

                /*                 * *update the status By <Yu Lang>** */
                foreach ($success_claims_id as $keyID) {
                    $rowset = $db_claim->find($keyID);
                    $claimTemp = $rowset->current()->toArray();
                    //strtolower($claimTemp['claim_status']) == 'open_ready_secondary_bill'
                    if (strtolower($claimTemp['bill_status']) == 'bill_ready_bill_secondary') {
                        $set = array(
                            //'claim_status' => 'open_billed_secondary',
                            'bill_status' => 'bill_billed_secondary',
                            /*                             * *wait to be changed !** */
                            'date_secondary_insurance_billed' => date('Y-m-d'));
                        /*                         * *wait to be changed ok!** */
                        //strtolower($claimTemp['claim_status']) == 'open_ready_primary_bill'
                    } elseif (strtolower($claimTemp['bill_status']) == 'bill_ready_bill_other') {
                        $set = array(
                            'bill_status' => 'bill_billed_other',
                            'date_billed' => date('Y-m-d'));
                    } elseif (strtolower($claimTemp['bill_status']) == 'bill_ready_bill_primary') {
                        $set = array(
                            //'claim_status' => 'open_billed_primary',
                            'bill_status' => 'bill_billed_primary',
                            'date_billed' => date('Y-m-d'));
                    } elseif (strtolower($claimTemp['claim_status']) == 'open_not_rebilled') {
                        $set = array(
                            'claim_status' => 'open_rebilled',
                            'date_last_billed' => date('Y-m-d'));
                    } else {
                        $set = array(
                            //'claim_status' => 'open_billed_primary',
                            'bill_status' => 'bill_billed_primary',
                            'date_billed' => date('Y-m-d'));
                    }
                    $where = $db->quoteInto('id = ?', $keyID);
                    $rows_affected = $db_claim->update($set, $where);
                }
                /*                 * *update the status By <Yu Lang>** */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
                $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name', 'provider.short_name AS provider_short_name'));
                $select->join('options', 'options.id = provider.options_id');
                $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
                $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid', 'bill_status'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
                $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

                /*                 * *New insurance change  2013-03-17** */
                $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
                $select->join('insured', 'insured.id=encounterinsured.insured_id');
                //$select->join('insured', 'insured.id=patient.insured_id');
                /*                 * *New insurance change  2013-03-17** */


                $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
                $select->where('provider.billingcompany_id=?', $this->get_billingcompany_id());
                /* Add the second insurance not billed <By YuLang> */ /* Add the status open_not_rebilled claim status */
                //$select->where('claim.claim_status IN(?)', array('open_ready_primary_bill', 'open_ready_delayed_primary_bill', 'open_not_rebilled'));
                $select->where('claim.bill_status IN(?)', array('bill_ready_bill_primary', 'bill_ready_bill_delayed_primary'));
                $select->where('claim.claim_status LIKE ?', "open%");
                $select->where('encounterinsured.type=? ', 'primary');
                /* Add the second insurance not billed <By YuLang> */
                $select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
                $patient = $db->fetchAll($select);


                /*                 * **********************for the second insurance By<YuLang>*************** */
                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
                $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name'));
                $select->join('options', 'options.id = provider.options_id');
                $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'bill_status'));
                $select->join('facility', 'facility.id=encounter.facility_id', 'facility_name');
                $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

                /*                 * *New insurance change  2013-03-17** */
                $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
                $select->join('insured', 'insured.id=encounterinsured.insured_id');
                //$select->join('insured', 'insured.id=patient.insured_id');
                /*                 * *New insurance change  2013-03-17** */

                $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
                $select->where('provider.billingcompany_id=?', $this->get_billingcompany_id());
                $select->where('claim.bill_status IN(?) ', array('bill_ready_bill_secondary'));
                $select->where('claim.claim_status LIKE ?', "open%");
                /*                 * ***2013-03-17**** */
                $select->where('encounterinsured.type=? ', 'secondary');
                /*                 * ***2013-03-17**** */
                $select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
                $patient_sec = $db->fetchAll($select);
                /*                 * **********************For the second insurance By<YuLang>********************* */

                $patientList = array();
                $count = 0;


                foreach ($patient_sec as $row) {
                    //strtolower($row['claim_status']) == 'open_ready_secondary_bill'
                    if (strtolower($row['bill_status']) == 'bill_ready_bill_secondary') {
                        $patientList[$count]['Comment'] = 'Secondary Billing';
                    }
                    $patientList[$count]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
                    $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
                    $patientList[$count]['encounter_id'] = $row['encounter_id'];
                    $patientList[$count]['patient_id'] = $row['patient_id'];
                    $patientList[$count]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
                    $patientList[$count]['MRN'] = $row['account_number'];
                    $patientList[$count]['insurance_name'] = $row['insurance_name'];
                    $patientList[$count]['insurance_display'] = $row['insurance_display'];
                    $patientList[$count]['facility_name'] = $row['facility_name'];
                    $patientList[$count]['provider_name'] = $row['provider_name'];
                    $patientList[$count]['DOB'] = format($row['DOB'], 1);
                    $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
                    $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
                    $patientList[$count]['total_charge'] = $row['total_charge'];
                    $patientList[$count]['amount_paid'] = $row['amount_paid'];
                    if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                        $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                    else
                        $patientList[$count]['referringprovider_name'] = '';
                    /*                     * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                    $patientList[$count]['means'] = 'MAIL';
                    /*                     * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                    $patientList[$count]['claim_id'] = $row['claim_id'];
                    $count = $count + 1;
                }

                foreach ($patient as $row) {
                    $flag = 0;
                    //strtolower($row['claim_status']) == 'open_ready_primary_bill'
                    if (strtolower($row['bill_status']) == 'bill_ready_bill_primary') {
                        $flag = 1;
                        $patientList[$count]['Comment'] = 'New Bill';
                    }

                    /*                     * **********************Add the billlist of the rebilled************************ */
                    if (strtolower($row['claim_status']) == 'open_not_rebilled') {
                        $flag = 1;
                        $patientList[$count]['Comment'] = 'Rebill';
                    }
                    /*                     * **********************Add the billlist of the rebilled************************ */
                    //strtolower($row['claim_status']) == 'open_ready_delayed_primary_bill'
                    $tmp_insurance_id = $row[insurance_id];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id = ?', $tmp_insurance_id);
                    $insurance_data = $db_insurance->fetchRow($where);
                    $exist_tags = $insurance_data['tags'];
                    $exist_tags_List = explode('|', $exist_tags);
                    foreach ($exist_tags_List as $tags_row) {
                        $temp_number = 0;
                        $tp_exist_tags = explode('=', $tags_row);
                        if ($tp_exist_tags[0] == "delaybilling") {
                            $temp_number = $tp_exist_tags[1];
                            break;
                        }
                    }
                    $number_of_days_for_delayed_bill_generation = $row["number_of_days_for_delayed_bill_generation" . $temp_number];

                    if (strtolower($row['bill_status']) == 'bill_ready_bill_delayed_primary') {
                        $delay_days = $number_of_days_for_delayed_bill_generation - day_diff(date('Y-m-d'), $row['start_date_1']);
                        if ($delay_days <= 0) {
                            $flag = 1;
                            $delay_days = -$delay_days;
                            $patientList[$count]['Comment'] = 'Passed delay time ' . $delay_days . ' days';
                        }
                    }
                    if ($flag == 1) {
                        $patientList[$count]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
                        $patientList[$count]['start_date_1'] = format($row['start_date_1'], 1);
                        $patientList[$count]['encounter_id'] = $row['encounter_id'];
                        $patientList[$count]['patient_id'] = $row['patient_id'];
                        $patientList[$count]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
                        $patientList[$count]['MRN'] = $row['account_number'];
                        $patientList[$count]['insurance_name'] = $row['insurance_name'];
                        $patientList[$count]['insurance_display'] = $row['insurance_display'];
                        $patientList[$count]['facility_name'] = $row['facility_name'];
                        $patientList[$count]['provider_name'] = $row['provider_name'];
                        $patientList[$count]['DOB'] = format($row['DOB'], 1);
                        $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
                        $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
                        $patientList[$count]['total_charge'] = $row['total_charge'];
                        $patientList[$count]['amount_paid'] = $row['amount_paid'];
                        if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                            $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                        else
                            $patientList[$count]['referringprovider_name'] = '';
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['means'] = strtoupper($row['means']);
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['claim_id'] = $row['claim_id'];
                        $count = $count + 1;
                    }
                }

                session_start();

                unset($_SESSION['tmp']);
                $_SESSION['tmp']['billList'] = $patientList;
                $this->view->patientList = $patientList;
                /*  to add the download filename<PanDazhao> */
                $_SESSION['downloadfilename'] = $filename;
                $this->_redirect('/biller/claims/bill');
            } else {
                $postType = $this->getRequest()->getParam('post');
                session_start();
                $billList = $_SESSION['tmp']['billList'];
                foreach ($billList as $key => $row) {
                    $patient[$key] = $row['name'];
                    $insurance[$key] = $row['insurance_display'];
                    $provider[$key] = $row['provider_name'];
                    $facility[$key] = $row['facility_name'];
                    $mrn[$key] = $row['account_number'];
                    $dos[$key] = format($row['start_date_1'], 0);
                    $means[$key] = $row['means'];
                    $comment[$key] = $row['Comment'];
                    $renderingprovider[$key] = $row['renderingprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                    $total_charge[$key] = $row['total_charge'];
                    $dob[$key] = format($row['DOB'], 0);
                    ;
                    $amount_paid[$key] = $row['amount_paid'];
                    $referringprovider[$key] = $row['referringprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                }

                /*                 * **************************Add check log Action************************ */

                /*                 * ***Add fax log check function******** */
                if ($this->getRequest()->getPost('fax_log') != "") {
                    $billingcompany_id = $this->get_billingcompany_id();
                    $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/billlog';
                    $log_file_name = $log_file_name . '/BillFax.csv';
                    if (file_exists($log_file_name)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                        //ob_clean();
                        //flush();
                        readfile($log_file_name);
                        exit;
                    }
                    $this->_redirect('/biller/claims/bill');
                }
                /*                 * ***Add fax log check function******** */

                /*                 * ***Add edi log check function******** */
                if ($this->getRequest()->getPost('edi_log') != "") {
                    $billingcompany_id = $this->get_billingcompany_id();
                    $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/billlog';
                    $log_file_name = $log_file_name . '/BillEDI.csv';
                    if (file_exists($log_file_name)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                        //ob_clean();
                        //flush();
                        readfile($log_file_name);
                        exit;
                    }
                    $this->_redirect('/biller/claims/bill');
                }
                /*                 * ***Add edi log check function******** */

                /*                 * ***Add cms log check function******** */
                if ($this->getRequest()->getPost('cms_log') != "") {
                    $billingcompany_id = $this->get_billingcompany_id();
                    $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/billlog';
                    $log_file_name = $log_file_name . '/BillCMS1500.csv';
                    if (file_exists($log_file_name)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                        //ob_clean();
                        //flush();
                        readfile($log_file_name);
                        exit;
                    }
                    $this->_redirect('/biller/claims/bill');
                }
                /*                 * ***Add cms log check function******** */

                /*                 * ***Add sec log check function******** */
                if ($this->getRequest()->getPost('sec_log') != "") {
                    $billingcompany_id = $this->get_billingcompany_id();
                    $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/billlog';
                    $log_file_name = $log_file_name . '/BillSecond.csv';
                    if (file_exists($log_file_name)) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                        //ob_clean();
                        //flush();
                        readfile($log_file_name);
                        exit;
                    }
                    $this->_redirect('/biller/claims/bill');
                }
                /*                 * ***Add sec log check function******** */


                /*                 * **************************Add check log Action************************ */
                if ($postType == "Open CMS1500") {
                    $filename = $this->getRequest()->getPost('cms_dir');
                    $_SESSION['downloadfilename'] = $filename;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "Open EDI") {
                    $filename = $this->getRequest()->getPost('edi_dir');
                    $_SESSION['downloadfilename'] = $filename;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "Name") {

                    /*                     * *****************sort the insurance****************** */
                    $patient_slowercase = array_map('strtolower', $patient);
                    if ($patient_slowercase[0] >= $patient_slowercase[sizeof($patient_slowercase) - 1]) {
                        array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($patient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }
                    //array_multisort($patient, SORT_ASC, $billList);
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "Facility") {
                    if ($facility[0] >= $facility[sizeof($facility) - 1]) {
                        array_multisort($facility, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($facility, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "MRN") {
                    if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {
                        array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "M") {
                    if ($means[0] >= $means[sizeof($means) - 1]) {
                        array_multisort($means, SORT_ASC, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($means, SORT_DESC, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }

                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Charge") {
                    if ($total_charge[0] <= $total_charge[sizeof($total_charge) - 1]) {
                        array_multisort($total_charge, SORT_DESC, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($total_charge, SORT_ASC, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }

                if ($postType == "Paid") {
                    if ($amount_paid[0] >= $amount_paid[sizeof($amount_paid) - 1]) {
                        array_multisort($amount_paid, SORT_ASC, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($amount_paid, SORT_DESC, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }

                if ($postType == "Referring Provider") {
                    $referringprovider_slowercase = array_map('strtolower', $referringprovider);
                    if ($referringprovider_slowercase[0] >= $referringprovider_slowercase[sizeof($referringprovider_slowercase) - 1]) {


                        array_multisort($referringprovider_slowercase, SORT_ASC, SORT_STRING, $billList);
                    } else {

                        array_multisort($referringprovider_slowercase, SORT_DESC, SORT_STRING, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }

                if ($postType == "DOB") {
                    if ($dob[0] >= $dob[sizeof($dob) - 1]) {
                        array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                /*                 * ************For sorting of Add fields*************** */

                if ($postType == "Comment") {
                    if ($comment[0] >= $comment[sizeof($comment) - 1]) {
                        array_multisort($comment, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($comment, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "DOS") {
                    if ($dos[0] >= $dos[sizeof($dos) - 1]) {
                        array_multisort($dos, SORT_ASC, $billList);
                    } else {
                        array_multisort($dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "Insurance") {

                    /*                     * *****************sort the insurance****************** */
                    $insurance_slowercase = array_map('strtolower', $insurance);
                    if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                        array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {

                        array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }

                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                if ($postType == "Provider") {
                    if ($provider[0] >= $provider[sizeof($provider) - 1]) {
                        array_multisort($provider, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($provider, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }

                if ($postType == "Rendering Provider") {
                    if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {
                        array_multisort($renderingprovider, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $billList);
                    } else {
                        array_multisort($renderingprovider, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $billList);
                    }
                    $_SESSION['tmp']['billList'] = $billList;
                    $this->_redirect('/biller/claims/bill');
                }
                $encounter_id = $this->getRequest()->getPost('id');

                if ($encounter_id != null) {
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('id = ?', $encounter_id);
                    $encounter_data = $db_encounter->fetchRow($where);
                    $patient_id = $encounter_data['patient_id'];
                    $this->initsession($patient_id, $encounter_id);

                    $this->set_options_session($encounter_data['provider_id'], '', '');
                }
                $this->_redirect('/biller/claims/claim');
            }
        }
    }

    /*     * ********************Add EDI install Action********************* */

    public function era835processAction() {
        if (!$this->getRequest()->isPost()) {
            
        }

        if ($this->getRequest()->isPost()) {
            $error_fields = array('era835_files', 'error_info', 'date');
            $error_display_fields = array('ERA835 FILES', 'ERROR INFO', 'DATE');
            $error_log_data = array();

            $os_type = PHP_OS;
            $cmd_type = 1;
            $absolute_path = get_path();

            $success_trans_files = array();
            $error_trans_files = array();

            if (substr($os_type, 0, 3) == "WIN")
                $cmd_type = 2;
            else if (substr($os_type, 0, 4) == "Unix")
                $cmd_type = 1;
            else if (substr($os_type, 0, 5) == "Linux")
                $cmd_type = 1;

            $upload_dir = $this->sysdoc_path . "/billingcompany";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir);
            }

            $upload_dir = $upload_dir . '/' . $this->get_billingcompany_id();
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir);
            }

            $upload_dir = $upload_dir . '/ERA835';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir);
            }

            $jar_run_path = $absolute_path . "/edireader/edireader-4.7.3.jar";
            $abs_save_path = $absolute_path . "/billingcompany/" . $this->get_billingcompany_id() . '/ERA835';


            /*             * *******Test for no type limit for file uploading*********** */
            //$type = array('gif', 'jpg', 'png', 'zip', 'rar', 'txt','edi');         
            $type = array();
            /*             * *******Test for no type limit for file uploading*********** */

            $upload = new UploadFile($_FILES['user_upload_file'], $upload_dir, 1000000, $type);
            $num = $upload->upload();
            $info = $upload->getSaveInfo();


            $index = 0;
            for ($i = 0; $i < sizeof($info); $i++) {
                $orignal_file_name = $info[$i]["name"];
                $tmp_up_name = $abs_save_path . '/' . $info[$i]["saveas"];
                $tmp_xml_name = $abs_save_path . '/' . $info[$i]["add_name"] . ".xml";
                $whatc = exec("java -cp $jar_run_path com.berryworks.edireader.demo.EDItoXML $tmp_up_name -o $tmp_xml_name", $out, $status);
                $current_time = date('Y-m-d H:i:s');

                if ($status == 0)
                    array_push($success_trans_files, $tmp_xml_name);


                else if ($status == 1) {
                    $error_log_data[$index]['era835_files'] = $orignal_file_name;
                    $error_log_data[$index]['error_info'] = 'Fail to translate to xml file';
                    $error_log_data[$index]['date'] = $current_time;
                    $index = $index + 1;
                }
            }

            $parse_info = parsexml($success_trans_files);
        }
        return;
    }

    /*     * ********************Add EDI install Action********************* */

    public function statementslistAction() {
        $this->_helper->viewRenderer->setNoRender();
        //error_log( "Accendo Log -- beore retrieve statements data:" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        $providerList = array();
        for ($i = 0; $i < count($userfocusonprovider); $i++) {
            $providerList[$i] = $userfocusonprovider[$i]['provider_id'];
        }

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('billingcompany');
        $select->join('provider', 'billingcompany.id=provider.billingcompany_id', array('provider.id as provider_id', 'provider.provider_name as provider_name', 'provider.short_name as provider_short_name', 'provider.street_address as prd_street_address', 'provider.city as provider_city', 'provider.state as provider_state', 'provider.zip as provider_zip', 'provider.phone_number as provider_phoneNumber',
            'provider.billing_provider_name as provider_b_name', 'provider.billing_street_address as provider_b_street_address', 'provider.billing_city as provider_b_city',
            'provider.billing_state as provider_b_state', 'provider.billing_zip as provider_b_zip', 'provider.billing_phone_number as provider_b_phoneNumber', 'provider.id as provider_id'));
        $select->join('options', 'options.id=provider.options_id');
        //
        //$select->join('mypatient', 'provider.id = mypatient.provider_id');
        //$select->join('patient', 'mypatient.patient_id = patient.id', array('patient.id as patient_id', 'patient.account_number', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name', 'patient.street_address as patient_street_address', 'patient.city as patient_city', 'patient.zip as patient_zip', 'patient.phone_number as patient_phoneNumber', 'patient.state as patient_state'));
        //$select->join('encounter', 'patient.id = encounter.patient_id');
        //
        $select->join('encounter', 'provider.id = encounter.provider_id', array('start_date_1')); 
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'patient.account_number', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name', 'patient.street_address as patient_street_address', 'patient.city as patient_city', 'patient.zip as patient_zip', 'patient.phone_number as patient_phoneNumber', 'patient.state as patient_state'));
        /***New insurance change***/
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id',array('insured_id'));
        $select->join('insured', 'insured.id=encounterinsured.insured_id', array('DOB'));
        //$select->join('insured', 'insured.id=patient.insured_id');
        /***New insurance change***/
        
        $select->join('insurance', 'insurance.id = insured.insurance_id', array('insurance_display'));
//        $select->join('encounter', 'patient.id = encounter.patient_id');
//        $select->join('encounter', 'provider.id = encounter.provider_id');
        $select->join('statement', 'encounter.id = statement.encounter_id', array('encounter.id as encounter_id', 'next_statement', 'statement.id as statement_id', 'statement.date as statement_date', 'statement_type', 'trigger', 'statement.remark as remark'));
//        $select->group('statement.id');
//          $select->where('billingcompany.id=?', $this->get_billingcompany_id());
//        $temp=$db->fetchAll($select);
        $select->join('facility', 'facility.id = encounter.facility_id', array('facility_name', 'short_name'));
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        //Add the referringprovider
        $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
        //Add the referringprovider
        $select->join('claim', 'encounter.claim_id = claim.id', array('claim.balance_due as balance_due', 'total_charge', 'amount_paid', 'claim.statement_status'));
        $select->join('followups', 'claim.id = followups.claim_id');
        $select->where('claim.claim_status LIKE ?', 'open%');

        /*         * *New insurance change** */
        $select->where('encounterinsured.type = ?', 'primary');
        /*         * *New insurance change** */

        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        if ($providerList != null) {
            $select->where('provider.id IN (?)', $providerList);
        }


        $select->where('encounter.provider_id=provider.id');
        //$select->order('statement_id DESC');
        $select->group('statement.id');
        $statement_data = $db->fetchAll($select);

        //error_log( "Accendo Log -- after retrieve statements data :" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
        foreach ($statement_data as $row) {
            $start_date = date("Y-m-d");
            $end_date = format($row['statement_date'], 0);
            if ($end_date != null && $row['next_statement'] == null) {
                $elapsed = days($start_date, $end_date);
                if ($row['patient_statement_interval'] != null && $row['patient_statement_interval'] <= $elapsed) {
                    if ($row['statement_type'] == '1' && $row['statement_status'] == 'stmt_i' && ($row['trigger'] == '1' || $row['trigger'] == '2' || $row['trigger'] == '3' || $row['trigger'] == '4')) {
                        $statement = array();
                        $statement['statement_type'] = '2';
                        $statement['trigger'] = $row['trigger'];
                        $statement['remark'] = $row['statement_II_' . $row['trigger']];
                        $statement['encounter_id'] = $row['encounter_id'];
                        //statement_insert('statement', $statement);
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $db_statement->insert($statement);

                        $statement2 = array();
                        $statement2['date'] = $row['statement_date'];
                        $statement2['statement_type'] = $row['statement_type'];
                        $statement2['trigger'] = $row['trigger'];
                        $statement2['remark'] = $row['remark'];
                        $statement2['encounter_id'] = $row['encounter_id'];
                        $statement2['next_statement'] = 1;
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $where = $db->quoteInto('id = ?', $row['statement_id']);
                        $db_statement->update($statement2, $where);
                    }
                    if ($row['statement_type'] == '2' && $row['statement_status'] == 'stmt_ii' && ($row['trigger'] == '1' || $row['trigger'] == '2' || $row['trigger'] == '3' || $row['trigger'] == '4')) {
                        $statement = array();
                        $statement['statement_type'] = '3';
                        $statement['trigger'] = $row['trigger'];
                        $statement['remark'] = $row['statement_III_' . $row['trigger']];
                        $statement['encounter_id'] = $row['encounter_id'];
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $db_statement->insert($statement);

                        $statement2 = array();
                        $statement2['date'] = $row['statement_date'];
                        $statement2['statement_type'] = $row['statement_type'];
                        $statement2['trigger'] = $row['trigger'];
                        $statement2['remark'] = $row['remark'];
                        $statement2['encounter_id'] = $row['encounter_id'];
                        $statement2['next_statement'] = 1;
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $where = $db->quoteInto('id = ?', $row['statement_id']);
                        $db_statement->update($statement2, $where);
                    }
                    if ($row['statement_status'] == 'stmt_installment') { //&& ($row['trigger'] == '1' || $row['trigger'] == '2' || $row['trigger'] == '3' || $row['trigger'] == '4')) {
                        $statement = array();
                        $statement['statement_type'] = '5';
                        $statement['trigger'] = $row['trigger'];
                        $statement['remark'] = $row['remark'];
                        $statement['encounter_id'] = $row['encounter_id'];
                        //statement_insert('statement', $statement);
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $db_statement->insert($statement);

                        $statement2 = array();
                        $statement2['date'] = $row['statement_date'];
                        $statement2['statement_type'] = $row['statement_type'];
                        $statement2['trigger'] = $row['trigger'];
                        $statement2['remark'] = $row['remark'];
                        $statement2['encounter_id'] = $row['encounter_id'];
                        $statement2['next_statement'] = 1;
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $where = $db->quoteInto('id = ?', $row['statement_id']);
                        $db_statement->update($statement2, $where);
                    }
                }
            }
        }
        $select->where('isnull(statement.date)');
        $statement = $db->fetchAll($select);
        
        //error_log( "Accendo Log -- after retrieve statements data 2nd time :" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
        $statement_type = array('1' => 'Statement I', '2' => 'Statement II', '3' => 'Statement III', '4' => 'Statement', '5' => 'Installment');
        //$trigger = array('1' => 'T1', '2' => 'T2', '3' => 'T3', '4' => 'T4', '15' => 'T15', '25' => 'T25', '35' => 'T35', '45' => 'T45', '16' => 'T16', '26' => 'T26', '36' => 'T36', '46' => 'T46');

        $statementList = array();
        $mystatement = array();
        $index = 0;
        foreach ($statement as $row) {
            $statementList[$index]['statement_id'] = $row['statement_id'];
            $statementList[$index]['statement_type'] = $statement_type[$row['statement_type']];
            $statementList[$index]['statement'] = $row['statement_type'];
            $statementList[$index]['DOS'] = format($row['start_date_1'], 1);
            $statementList[$index]['encounter_id'] = $row['encounter_id'];
            $statementList[$index]['provider_name'] = $row['provider_name'];
            //$statementList[$index]['insurance_name'] = $row['insurance_name'];
            $statementList[$index]['insurance_display'] = $row['insurance_display'];
            $statementList[$index]['facility_name'] = $row['facility_name'];

            /*             * ****************Add fields for bill claim lists********************** */
            $statementList[$index]['provider_short_name'] = $row['provider_short_name'];
            //wait to change
            $statementList[$index]['facility_short_name'] = $row['short_name'];
            $statementList[$index]['total_charge'] = $row['total_charge'];
            $statementList[$index]['amount_paid'] = $row['amount_paid'];
            if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                $statementList[$index]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
            else
                $statementList[$index]['referringprovider_name'] = '';
            $statementList[$index]['DOB'] = format($row['DOB'], 1);
            /*             * ****************Add fields for bill claim lists********************** */

            $statementList[$index]['name'] = $row['p_last_name'] . ', ' . $row['p_first_name'];
            $statementList[$index]['MRN'] = $row['account_number'];
            $statementList[$index]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];
            $mystatement[$row['statement_id']] = $row;
            $index = $index + 1;
        }
        //error_log( "Accendo Log -- after building statements list:" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
        session_start();
        unset($_SESSION['tmp']);
        unset($_SESSION['mystatement']);
        $_SESSION['tmp']['statementList'] = $statementList;
        $_SESSION['mystatement'] = $mystatement;
        //error_log( "Accendo Log -- after adding statements list to session :" .(memory_get_peak_usage(true)/1024/1024)." MiB\n\n" );
        $this->_redirect('/biller/claims/statements');
    }

    public function patientcorrespondencelistAction() {
        $this->_helper->viewRenderer->setNoRender();

        $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
        $db = $db_userfocusonprovider->getAdapter();
        $where = $db->quoteInto('user_id = ?', $this->user_id);
        $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        $providerList = array();
        for ($i = 0; $i < count($userfocusonprovider); $i++) {
            $providerList[$i] = $userfocusonprovider[$i]['provider_id'];
        }

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('billingcompany');
        $select->join('provider', 'billingcompany.id=provider.billingcompany_id', array('provider.id as provider_id', 'provider.provider_name as provider_name', 'provider.short_name as provider_short_name', 'provider.street_address as prd_street_address', 'provider.city as provider_city', 'provider.state as provider_state', 'provider.zip as provider_zip', 'provider.phone_number as provider_phoneNumber',
            'provider.billing_provider_name as provider_b_name', 'provider.billing_street_address as provider_b_street_address', 'provider.billing_city as provider_b_city',
            'provider.billing_state as provider_b_state', 'provider.billing_zip as provider_b_zip', 'provider.billing_phone_number as provider_b_phoneNumber', 'provider.id as provider_id'));
        $select->join('options', 'options.id=provider.options_id');
        $select->join('encounter', 'provider.id = encounter.provider_id', array('encounter.start_date_1 As encounter_start_date_1'));
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'patient.DOB As patient_DOB', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.street_address as patient_street_address', 'patient.city as patient_city', 'patient.zip as patient_zip', 'patient.phone_number as patient_phone_number', 'patient.state as patient_state', 'patient.account_number As patient_account_number'));
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');

        $select->join('insurance', 'insurance.id = insured.insurance_id', array('insurance.insurance_display As insurance_insurance_display'));

        $select->join('facility', 'facility.id = encounter.facility_id', array('facility.short_name As facility_short_name'));
        $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        //Add the referringprovider
        $select->joinLeft('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
        //Add the referringprovider
        $select->join('claim', 'encounter.claim_id = claim.id', array('claim.id as claim_id', 'claim.total_charge as claim_total_charge', 'claim.amount_paid as claim_amount_paid', 'claim.balance_due as claim_balance_due', 'claim.patientcorrespondence as claim_patientcorrespondence'));
        $select->join('followups', 'claim.id = followups.claim_id');
        $select->where('claim.patientcorrespondence is not null');

        $select->where('encounterinsured.type = ?', 'primary');

        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        if ($providerList != null) {
            $select->where('provider.id IN (?)', $providerList);
        }

        $select->where('encounter.provider_id=provider.id');

        $select->group('claim.id');
        $pc_data = $db->fetchAll($select);

        $statement_type = array('1' => 'Statement I', '2' => 'Statement II', '3' => 'Statement III', '4' => 'Statement', '5' => 'Installment');

        $pcList = array();
        $templates = array();
        $index = 0;
        foreach ($pc_data as $row) {
            if ($row['claim_patientcorrespondence'] == null || $row['claim_patientcorrespondence'] == '') {
                //problem
            } else {
                $templates = explode('|', $row['claim_patientcorrespondence']); // 这里是id
                $db_templates = new Application_Model_DbTable_Patientcorrespondence();
                $db = $db_templates->getAdapter();
                //$select = $db->select();
                $where = $db->quoteInto('billingcompany_id=?', $this->get_billingcompany_id());
                $where = $db->quoteInto('id IN (?)', $templates);
                //$select->where('billingcompany.id=?', $this->get_billingcompany_id());
                $templateArray = $db_templates->fetchAll($where)->toArray();
                for ($i = 0; $i < count($templateArray); $i++) {
                    $pcList[$index]['MRN'] = $row['patient_account_number'];
                    $pcList[$index]['DOB'] = $row['patient_DOB'];
                    $pcList[$index]['DOS'] = $row['encounter_start_date_1'];
                    $pcList[$index]['encounter_id'] = $row['encounter_id']; //新加入获得encounter_id
                    $pcList[$index]['total_charge'] = $row['claim_total_charge'];
                    $pcList[$index]['amount_paid'] = $row['claim_amount_paid'];
                    $pcList[$index]['insurance_display'] = $row['insurance_insurance_display'];
                    $pcList[$index]['facility_short_name'] = $row['facility_short_name'];
                    if ($row['referringprovider_last_name'] !== null && $row['referringprovider_last_name'] !== '')
                        $pcList[$index]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                    else
                        $pcList[$index]['referringprovider_name'] = '';
                    $pcList[$index]['name'] = $row['patient_last_name'] . ', ' . $row['patient_first_name'];
                    $pcList[$index]['renderingprovider_name'] = $row['renderingprovider_last_name'] . ', ' . $row['renderingprovider_first_name'];

                    $pcList[$index]['template_id'] = $templateArray[$i]['id'];
                    $pcList[$index]['template'] = $templateArray[$i]['template'];
                    $pcList[$index]['variable'] = $templateArray[$i]['variable'];
                    $index++;
                }
            }
        }
        session_start();
        unset($_SESSION['tmp']);
        unset($_SESSION['mystatement']);
        $_SESSION['tmp']['pcList'] = $pcList;
        //$_SESSION['mystatement'] = $mystatement;

        $this->_redirect('/biller/claims/patientcorrespondence');
    }

    public function statementsAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $statementList = $_SESSION['tmp']['statementList'];
            $this->view->statementList = $statementList;
            $billingcompany_id = $this->get_billingcompany_id();
            $claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/statements';
            $claim_paths = array();
            $new_path = array();
            if (is_dir($claim_dir)) {
                foreach (glob($claim_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            $display = array();
            for ($i = 0; $i < count($claim_paths); $i++) {
                $new_path[$i]['path'] = $claim_paths[$i];

                $claim_paths_array = explode('/', $claim_paths[$i]);
                $new_path[$i]['display'] = $claim_paths_array[count($claim_paths_array) - 1];
                $display[$i] = $new_path[$i]['display'];
            }
            array_multisort($display, SORT_DESC, $new_path);
            $this->view->filelist = $new_path;
        }
        if ($this->getRequest()->isPost()) {

            if ($this->getRequest()->getPost('statementI') != "") {

                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $statement_id_string = $this->getRequest()->getPost('statement_id');
                $encounter_id_array = array();
                $statement_id_array = array();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                if (strlen($statement_id_string) > 0)
                    $statement_id_array = explode(',', $statement_id_string);


                session_start();
                $mystatement = $_SESSION['mystatement'];
                $datas = array();
                $ledgers = array();

                $patient_id_array = array();
                $provider_id_array = array();
                $claim_id_array = array();
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                    array_push($patient_id_array, $exist["patient_id"]);
                    array_push($claim_id_array, $exist["claim_id"]);
                }

                //foreach ($statement_id_array as $key => $statement_id) {
                for ($i = 0; $i < count($statement_id_array); $i++) {
                    //data for print
                    $statement_id = $statement_id_array[$i];
                    $data = $mystatement[$statement_id];
                    array_push($datas, $data);
                    //ledger
                    $ledger = ledger2($data['patient_id'], $data['number_of_days_include_in_ledger'], "statement", null, $claim_id_array[$i], $provider_id_array[$i]);
                    array_push($ledgers, $ledger);
                    //  gen_statement_pdf($data,$ledger,$this->sysdoc_path);
                    $statement['date'] = date("Y-m-d");
                    $db_statement = new Application_Model_DbTable_Statement();
                    $db = $db_statement->getAdapter();
                    $where = $db->quoteInto('id = ?', $statement_id);
                    $db_statement->update($statement, $where);

                    $claim['statement_status'] = "stmt_i";
                    $db_claim = new Application_Model_DbTable_Claim();
                    $db = $db_claim->getAdapter();
                    $where = $db->quoteInto('id = ?', $claim_id_array[$i]);
                    $db_claim->update($claim, $where);
                }

                if (sizeof($datas) != 0) {
                    $billingcompany_id = $this->billingcompany_id;
                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id, $provider_id_array, $patient_id_array, $claim_id_array);
                    $_SESSION['downloadfilename'] = $filename;
                }



                unset($_SESSION['mystatement']);
//                      if (file_exists($filename)) {
//                    header('Content-Description: File Transfer');
//                    header('Content-Type: application/octet-stream');
//                    header('Content-Disposition: attachment; filename=' . basename($filename));
//                    header('Content-Transfer-Encoding: binary');
//                    header('Expires: 0');
//                    header('Cache-Control: must-revalidate');
//                    header('Pragma: public');
//                    header('Content-Length: ' . filesize($filename) . ' bytes');
//                    ob_clean();
//                    flush();
//                    readfile($filename);
//                   
//                    exit;
//                }              
                if (!$this->generate_statement_log($encounter_id_array, 1)) {
                    echo "Generate the statement logs failed!";
                    return;
                }

                $this->_redirect('/biller/claims/statementsList');
            }

            /*             * ********************Statement 2 PDF ******************* */ else if ($this->getRequest()->getPost('statementII') != "") {

                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $statement_id_string = $this->getRequest()->getPost('statement_id');
                $encounter_id_array = array();
                $statement_id_array = array();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                if (strlen($statement_id_string) > 0)
                    $statement_id_array = explode(',', $statement_id_string);
                session_start();
                $mystatement = $_SESSION['mystatement'];
                $datas = array();
                $ledgers = array();

                $patient_id_array = array();
                $provider_id_array = array();
                $claim_id_array = array();
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                    array_push($patient_id_array, $exist["patient_id"]);
                    array_push($claim_id_array, $exist["claim_id"]);
                }

                //foreach ($statement_id_array as $key => $statement_id) {
                for ($i = 0; $i < count($statement_id_array); $i++) {
                    //data for print
                    $statement_id = $statement_id_array[$i];
                    $data = $mystatement[$statement_id];
                    array_push($datas, $data);
                    //ledger
                    $ledger = ledger2($data['patient_id'], $data['number_of_days_include_in_ledger'], "statement", null, $claim_id_array[$i], $provider_id_array[$i]);
                    array_push($ledgers, $ledger);
                    //  gen_statement_pdf($data,$ledger,$this->sysdoc_path);
                    $statement['date'] = date("Y-m-d");
                    $db_statement = new Application_Model_DbTable_Statement();
                    $db = $db_statement->getAdapter();
                    $where = $db->quoteInto('id = ?', $statement_id);
                    $db_statement->update($statement, $where);

                    $claim['statement_status'] = "stmt_ii";
                    $db_claim = new Application_Model_DbTable_Claim();
                    $db = $db_claim->getAdapter();
                    $where = $db->quoteInto('id = ?', $claim_id_array[$i]);
                    $db_claim->update($claim, $where);
                }

                if (sizeof($datas) != 0) {
                    $billingcompany_id = $this->billingcompany_id;
                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id, $provider_id_array, $patient_id_array, $claim_id_array);
                    $_SESSION['downloadfilename'] = $filename;
                }
                unset($_SESSION['mystatement']);
//                      if (file_exists($filename)) {
//                    header('Content-Description: File Transfer');
//                    header('Content-Type: application/octet-stream');
//                    header('Content-Disposition: attachment; filename=' . basename($filename));
//                    header('Content-Transfer-Encoding: binary');
//                    header('Expires: 0');
//                    header('Cache-Control: must-revalidate');
//                    header('Pragma: public');
//                    header('Content-Length: ' . filesize($filename) . ' bytes');
//                    ob_clean();
//                    flush();
//                    readfile($filename);
//                   
//                    exit;
//                } 

                if (!$this->generate_statement_log($encounter_id_array, 2)) {
                    echo "Generate the statement logs failed!";
                    return;
                }

                $this->_redirect('/biller/claims/statementsList');
            }
            /*             * ********************Statement 2 PDF ******************* */

            /*             * ********************Statement 3 PDF ******************* */ else if ($this->getRequest()->getPost('statementIII') != "") {

                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $statement_id_string = $this->getRequest()->getPost('statement_id');
                $encounter_id_array = array();
                $statement_id_array = array();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                if (strlen($statement_id_string) > 0)
                    $statement_id_array = explode(',', $statement_id_string);
                session_start();
                $mystatement = $_SESSION['mystatement'];
                $datas = array();
                $ledgers = array();

                $patient_id_array = array();
                $provider_id_array = array();
                $claim_id_array = array();
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                    array_push($patient_id_array, $exist["patient_id"]);
                    array_push($claim_id_array, $exist["claim_id"]);
                }

                //foreach ($statement_id_array as $key => $statement_id) {
                for ($i = 0; $i < count($statement_id_array); $i++) {
                    //data for print
                    $statement_id = $statement_id_array[$i];
                    $data = $mystatement[$statement_id];
                    array_push($datas, $data);
                    //ledger
                    $ledger = ledger2($data['patient_id'], $data['number_of_days_include_in_ledger'], "statement", null, $claim_id_array[$i], $provider_id_array[$i]);
                    array_push($ledgers, $ledger);
                    //  gen_statement_pdf($data,$ledger,$this->sysdoc_path);
                    $statement['date'] = date("Y-m-d");
                    $db_statement = new Application_Model_DbTable_Statement();
                    $db = $db_statement->getAdapter();
                    $where = $db->quoteInto('id = ?', $statement_id);
                    $db_statement->update($statement, $where);

                    $claim['statement_status'] = "stmt_iii";
                    $db_claim = new Application_Model_DbTable_Claim();
                    $db = $db_claim->getAdapter();
                    $where = $db->quoteInto('id = ?', $claim_id_array[$i]);
                    $db_claim->update($claim, $where);
                }

                if (sizeof($datas) != 0) {
                    $billingcompany_id = $this->billingcompany_id;
                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id, $provider_id_array, $patient_id_array, $claim_id_array);
                    $_SESSION['downloadfilename'] = $filename;
                }
                unset($_SESSION['mystatement']);

                if (!$this->generate_statement_log($encounter_id_array, 3)) {
                    echo "Generate the statement logs failed!";
                    return;
                }


                $this->_redirect('/biller/claims/statementsList');
            }

            /*             * ********************Statement 3 PDF ******************* */ else if ($this->getRequest()->getPost('installment') != "") {

                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $statement_id_string = $this->getRequest()->getPost('statement_id');
                $encounter_id_array = array();
                $statement_id_array = array();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                if (strlen($statement_id_string) > 0)
                    $statement_id_array = explode(',', $statement_id_string);
                session_start();
                $mystatement = $_SESSION['mystatement'];
                $datas = array();
                $ledgers = array();

                $patient_id_array = array();
                $provider_id_array = array();
                $claim_id_array = array();
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                    array_push($patient_id_array, $exist["patient_id"]);
                    array_push($claim_id_array, $exist["claim_id"]);
                }

                //foreach ($statement_id_array as $key => $statement_id) {
                for ($i = 0; $i < count($statement_id_array); $i++) {
                    $statement_id = $statement_id_array[$i];
                    //data for print
                    $data = $mystatement[$statement_id];
                    array_push($datas, $data);
                    //ledger
                    $ledger = ledger2($data['patient_id'], $data['number_of_days_include_in_ledger'], "statement", null, $claim_id_array[$i], $provider_id_array[$i]);
                    array_push($ledgers, $ledger);
                    //  gen_statement_pdf($data,$ledger,$this->sysdoc_path);
                    $statement['date'] = date("Y-m-d");
                    $db_statement = new Application_Model_DbTable_Statement();
                    $db = $db_statement->getAdapter();
                    $where = $db->quoteInto('id = ?', $statement_id);
                    $db_statement->update($statement, $where);

                    $claim['statement_status'] = "stmt_installment";
                    $db_claim = new Application_Model_DbTable_Claim();
                    $db = $db_claim->getAdapter();
                    $where = $db->quoteInto('id = ?', $claim_id_array[$i]);
                    $db_claim->update($claim, $where);
                }

                if (sizeof($datas) != 0) {
                    $billingcompany_id = $this->billingcompany_id;
                    //$filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path);
                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id, $provider_id_array, $patient_id_array, $claim_id_array);
                    $_SESSION['downloadfilename'] = $filename;
                }
                unset($_SESSION['mystatement']);
//                      if (file_exists($filename)) {
//                    header('Content-Description: File Transfer');
//                    header('Content-Type: application/octet-stream');
//                    header('Content-Disposition: attachment; filename=' . basename($filename));
//                    header('Content-Transfer-Encoding: binary');
//                    header('Expires: 0');
//                    header('Cache-Control: must-revalidate');
//                    header('Pragma: public');
//                    header('Content-Length: ' . filesize($filename) . ' bytes');
//                    ob_clean();
//                    flush();
//                    readfile($filename);
//                   
//                    exit;
//                }

                if (!$this->generate_statement_log($encounter_id_array, 5)) {
                    echo "Generate the statement logs failed!";
                    return;
                }

                $this->_redirect('/biller/claims/statementsList');
            }
            /*             * ********************Statement 3 PDF ******************* */


            /*             * ********************Statement 1 log ******************* */ else if ($this->getRequest()->getPost('sta1_log') != "") {
                $billingcompany_id = $this->get_billingcompany_id();
                $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_statementlog = $dir_billingcompany . '/statementlog';
                if (!is_dir($dir_bc_statementlog)) {
                    mkdir($dir_bc_statementlog);
                }
                //  $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/statementlog';
                $log_file_name = $dir_bc_statementlog . '/StatementI.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    readfile($log_file_name);
                    exit;
                }
            }
            /*             * ********************Statement 1 log ******************* */

            /*             * ********************Statement 2 log ******************* */ else if ($this->getRequest()->getPost('sta2_log') != "") {

                $billingcompany_id = $this->get_billingcompany_id();
                $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_statementlog = $dir_billingcompany . '/statementlog';
                if (!is_dir($dir_bc_statementlog)) {
                    mkdir($dir_bc_statementlog);
                }
                //$log_file_name = $this->sysdoc_path . '/billingcompany/' . $billingcompany_id . '/statementlog';
                $log_file_name = $dir_bc_statementlog . '/StatementII.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    readfile($log_file_name);
                    exit;
                }
            }
            /*             * ********************Statement 2 log ******************* */

            /*             * ********************Statement 3 log ******************* */ else if ($this->getRequest()->getPost('sta3_log') != "") {

                $billingcompany_id = $this->get_billingcompany_id();
                $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_statementlog = $dir_billingcompany . '/statementlog';
                if (!is_dir($dir_bc_statementlog)) {
                    mkdir($dir_bc_statementlog);
                }
                //$log_file_name = $this->sysdoc_path . '/billingcompany/' . $billingcompany_id . '/statementlog';
                $log_file_name = $dir_bc_statementlog . '/StatementIII.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    readfile($log_file_name);
                    exit;
                }
            }
            /*             * ********************Statement 3 log ******************* */

            /*             * ********************Installment log ******************* */ else if ($this->getRequest()->getPost('ins_log') != "") {
                $billingcompany_id = $this->get_billingcompany_id();
                $log_file_name = $this->sysdoc_path . '/billingcompany/' . $billingcompany_id . '/statementlog';
                $log_file_name = $log_file_name . '/Installment.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    readfile($log_file_name);
                    exit;
                }
            }
            /*             * ********************Installment log******************* */ else {
                $postType = $this->getRequest()->getParam('post');
                session_start();
                $statementList = $_SESSION['tmp']['statementList'];
                foreach ($statementList as $key => $row) {
                    $patient[$key] = $row['name'];
                    $insurance[$key] = $row['insurance_display'];
                    $provider[$key] = $row['provider_name'];
                    $facility[$key] = $row['facility_name'];
                    $dos[$key] = format($row['DOS'], 0);
                    $mrn[$key] = $row['MRN'];
                    $statementtype[$key] = $row['statement_type'];
                    $renderingprovider[$key] = $row['renderingprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                    $total_charge[$key] = $row['total_charge'];
                    $amount_paid[$key] = $row['amount_paid'];
                    $dob[$key] = format($row['DOB'], 0);
                    $referringprovider[$key] = $row['referringprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                }





                if ($postType == "Name") {

                    /*                     * *****************sort the insurance****************** */
                    $patient_slowercase = array_map('strtolower', $patient);
                    if ($patient_slowercase[0] >= $patient_slowercase[sizeof($patient_slowercase) - 1]) {
                        array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($patient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $statementList);
                    }

                    //array_multisort($patient, SORT_ASC, $statementList);
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }

                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Charge") {
                    if ($total_charge[0] <= $total_charge[sizeof($total_charge) - 1]) {
                        array_multisort($total_charge, SORT_DESC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($total_charge, SORT_ASC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }

                if ($postType == "Paid") {
                    if ($amount_paid[0] >= $amount_paid[sizeof($amount_paid) - 1]) {
                        $_SESSION['cliam_recent_sort_Paid_reverse_statement'] = 1;
                        array_multisort($amount_paid, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        $_SESSION['cliam_recent_sort_Paid_reverse_statement'] = 0;
                        array_multisort($amount_paid, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }

                if ($postType == "Referring Provider") {
                    if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1]) {

                        array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }

                if ($postType == "DOB") {
                    if ($dob[0] >= $dob[sizeof($dob) - 1]) {
                        array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Open") {
                    $filename = $this->getRequest()->getPost('statement_dir');
                    $_SESSION['downloadfilename'] = $filename;
                    $this->_redirect('/biller/claims/statements');
                }

                if ($postType == "Insurance") {

                    /*                     * *****************sort the insurance****************** */
                    $insurance_slowercase = array_map('strtolower', $insurance);
                    if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                        array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $statementList);
                    } else {

                        array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $statementList);
                    }

                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                if ($postType == "DOS") {
                    if ($dos[0] >= $dos[sizeof($dos) - 1]) {
                        array_multisort($dos, SORT_ASC, $statementList);
                    } else {
                        array_multisort($dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                if ($postType == "MRN") {
                    if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {
                        array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                if ($postType == "Statement") {
                    if ($statementtype[0] >= $statementtype[sizeof($statementtype) - 1]) {
                        array_multisort($statementtype, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($statementtype, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                if ($postType == "Facility") {
                    if ($facility[0] >= $facility[sizeof($facility) - 1]) {
                        array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                if ($postType == "Provider") {
                    if ($provider[0] >= $provider[sizeof($provider) - 1]) {
                        array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($provider, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }

                if ($postType == "Rendering Provider") {
                    if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {
                        array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $statementList);
                    } else {
                        array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $statementList);
                    }
                    $_SESSION['tmp']['statementList'] = $statementList;
                    $this->_redirect('/biller/claims/statements');
                }
                $encounter_id = $this->getRequest()->getPost('id');
                if ($encounter_id != null) {
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('id = ?', $encounter_id);
                    $encounter_data = $db_encounter->fetchRow($where);
                    $patient_id = $encounter_data['patient_id'];
                    $this->initsession($patient_id, $encounter_id);

                    $this->set_options_session($encounter_data['provider_id'], '', '');
                }
                $this->_redirect('/biller/claims/claim');
            }
        }
    }

    public function pcgenlogAction() {
        $data = array();
        $lastvalue = $this->getRequest()->get('lastvalue');
        $newvalue = $this->getRequest()->get('newvalue');
        if ($newvalue == "0|") {
            $newvalue = "0";
        }

        $result_array = array();
        $lv_array = explode('|', $lastvalue);
        $nv_array = explode("|", $newvalue);

        $db_pc = new Application_Model_DbTable_Patientcorrespondence();
        $db = $db_pc->getAdapter();

        if (count($lv_array) > count($nv_array)) {
            $result_array = array_diff($lv_array, $nv_array);
            $value = reset($result_array);
            //除去了某个值
//            echo $value." remove from Patient Correspondence";

            $where = $db->quoteInto('id=?', $value);
            $result = $db_pc->fetchRow($where);
            $my_template = $result['template'];
            $data['log'] = $my_template . " remove from Patient Correspondence";
        } else {
            $result_array = array_diff($nv_array, $lv_array);

            $value = reset($result_array);
            //添加了某个值
//            echo $value." added to Patient Correspondence";
            $where = $db->quoteInto('id=?', $value);
            $result = $db_pc->fetchRow($where);
            $my_template = $result['template'];
            $data['log'] = $my_template . " added to Patient Correspondence";
        }

        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function pcvaluecheckAction() {
        $this->_helper->viewRenderer->setNoRender();
        $data = array();
        $data['flag'] = 1;
        $encounter_id = $this->getRequest()->get('encounter_id');
        $template_id_string = $this->getRequest()->get('template_id');

        $template_id_array = explode("|", $template_id_string);

        $db_encounter = new Application_Model_DbTable_Encounter();
        $db = $db_encounter->getAdapter();
        $where = $db->quoteInto('id=?', $encounter_id);
        $exist = $db_encounter->fetchRow($where);
        $provider_id = $exist["provider_id"];
        $patient_id = $exist["patient_id"];
        $claim_id = $exist["claim_id"];

        $db_pc = new Application_Model_DbTable_Patientcorrespondence();
        $db = $db_pc->getAdapter();
        foreach ($template_id_array as $key => $template_id) {

            $where = $db->quoteInto('id=?', $template_id);
            $result = $db_pc->fetchRow($where);
            $my_template = $result['template'];
            $my_variable = $result['variables'];

            $varivable_message_array = array();
            $varivable_message_array = parse_tag($my_variable);
            //根据=号右侧表名和列名进行检索，获得具体值
            $db_info = array(); //辅助数组，用于解析=右边内容
            $fieldValues = array();
            foreach ($varivable_message_array as $key_final => $value) {
                //解析value 
                $db_info = explode('.', $value);
                $table = $db_info[0];
                $column = $db_info[1];


                $table_name = 'Application_Model_DbTable_' . ucfirst($table);
                $db_retrive = new $table_name();
                $db = $db_retrive->getAdapter();

                //提取 ID
//                $table_id_array = $$table_id_array_string;
                $table_id_string = $table . '_id';
                $where = $db->quoteInto('id=?', $$table_id_string);
                $result = $db_retrive->fetchRow($where);
                $retrieve_value = $result[$column];
//                $fieldValues[$key_final] = $retrieve_value;
                if ($retrieve_value == null || $retrieve_value = '') {
                    $data['flag'] = 0;
                    $data['mergefield'] = $column;
                }
            }
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function patientcorrespondenceAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $pcList = $_SESSION['tmp']['pcList'];
            $this->view->pcList = $pcList;
            $billingcompany_id = $this->get_billingcompany_id();
            $claim_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/correspondence';
            $claim_paths = array();
            $new_path = array();
            if (is_dir($claim_dir)) {
                foreach (glob($claim_dir . '/*.*') as $filename) {
                    array_push($claim_paths, $filename);
                }
            }
            $display = array();
            for ($i = 0; $i < count($claim_paths); $i++) {
                $new_path[$i]['path'] = $claim_paths[$i];

                $claim_paths_array = explode('/', $claim_paths[$i]);
                $new_path[$i]['display'] = $claim_paths_array[count($claim_paths_array) - 1];
                $display[$i] = $new_path[$i]['display'];
            }
            array_multisort($display, SORT_DESC, $new_path); //路径进行排序
            $this->view->filelist = $new_path;
        }
        if ($this->getRequest()->isPost()) {

            $billingcompany_id = $this->get_billingcompany_id();
            if ($this->getRequest()->getPost('correspondence') != "") { //Do mail merge
                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                //$template_id_string 是UI 界面选中的行的template的 id。
                $template_id_string = $this->getRequest()->getPost('template_id');

                $encounter_id_array = array();
                $template_id_array = array();
                if (strlen($encounter_id_string) > 0)
                    $encounter_id_array = explode(',', $encounter_id_string);
                //后续再修改，encounter_id 这个页面应该只有一个
//                $encounterId = $encounter_id_array[0];
                if (strlen($template_id_string) > 0)
                    $template_id_array = explode(',', $template_id_string);
                //MM: The code below need to be modified for each selected template entry in the patientcorrespondence list 
                //MM: Loop to get each template selected by user from the patientcorrespondence list
                //MM: Get the DB table and column names from the variable column of patientcorrespondence table
                //MM: Retrieve corresponding data from various tables
                //MM: Set such as $fieldValues = array ('Last_Name' => 'Smith', 'First_Name' => 'Jane', 'Amount' => '2,500.00', 'Some_Text' => 'such as ...');
                //MM: where keys are from the variable column of patientcorrespondence table's key part, and values are from the corresponding table columns specified by the value part of the "variable" column            
                //MM: call mailmerge($template, $fieldValues) to do a merge of the current template            

                session_start();

                //准备数据
                $patient_id_array = array();
                $provider_id_array = array();
                $claim_id_array = array();
                $db_encounter = new Application_Model_DbTable_Encounter();
                $db = $db_encounter->getAdapter();
                foreach ($encounter_id_array as $key => $encounter_id) {
                    $where = $db->quoteInto('id=?', $encounter_id);
                    $exist = $db_encounter->fetchRow($where);
                    //$provider_id_array[$key] = $exsit["provider_id"];
                    array_push($provider_id_array, $exist["provider_id"]);
                    array_push($patient_id_array, $exist["patient_id"]);
                    array_push($claim_id_array, $exist["claim_id"]);
                }

                $template_array = array();
                $variable_array = array(); //实际上存这个数组是没有用的
                $db_pc = new Application_Model_DbTable_Patientcorrespondence();
                $db = $db_pc->getAdapter();

                $count = array();
                $filenames = array();
                //这里 $patient_id_array 等数组的key 值和 $template_id是对上的
                foreach ($template_id_array as $key_id => $template_id) {

                    $where = $db->quoteInto('id=?', $template_id);
                    $result = $db_pc->fetchRow($where);
                    $my_template = $result['template'];
                    $my_variable = $result['variables'];
                    array_push($template_array, $result['template']);
                    array_push($variable_array, $result['variables']);

                    if (array_key_exists($my_template, $count)) {
                        $count[$my_template] ++;
                    } else {
                        $count[$my_template] = 1;
                    }
                    //处理variable部分
                    $varivable_message_array = array();
                    //解析每一个$variable 串，提取=号两侧的值
                    $varivable_message_array = parse_tag($my_variable);
                    //根据=号右侧表名和列名进行检索，获得具体值
                    $db_info = array(); //辅助数组，用于解析=右边内容
                    $fieldValues = array();
                    foreach ($varivable_message_array as $key_final => $value) {
                        //解析value 
                        $db_info = explode('.', $value);
                        $table = $db_info[0];
                        $column = $db_info[1];

                        //$patient_id_array
                        //$provider_id_array
                        //$claim_id_array
                        //Application_Model_DbTable_Patient
                        $table_model = ucfirst($table);
                        $table_name = 'Application_Model_DbTable_' . $table_model;
                        $db_retrive = new $table_name();
//                        $db_retrive = new Application_Model_DbTable_Patient();//随后删除
                        $db = $db_retrive->getAdapter();

                        //提取 ID
                        $table_id_array_string = $table . '_id_array';
                        $table_id_array = $$table_id_array_string;
                        $where = $db->quoteInto('id=?', $table_id_array[$key_id]);
                        $result = $db_retrive->fetchRow($where);
                        $retrieve_value = $result[$column];
                        $fieldValues[$key_final] = $retrieve_value;
                    }

                    //邮件合并
                    $filename = $this->mailmerge($my_template, $fieldValues, $count[$my_template]);
                    //$filenames = array();
                    $filenames[] = $filename;
                    //更新claim.pc
                    // 更新步骤如下：
                    //1. 找到该claim id,
                    $db_delete = new Application_Model_DbTable_Claim();
                    $db1 = $db_delete->getAdapter();
                    //获取 claim id
                    $delete_claim_id = $claim_id_array[$key_id];
                    $where = $db1->quoteInto('id=?', $delete_claim_id);
                    $result1 = $db_delete->fetchRow($where);
                    //2. 删除该 claim pc 域中存储的刚刚打印的那个 template的 id
                    $str_temp = $result1['patientcorrespondence'];
                    $arr_temp = explode('|', $str_temp);
                    $delete_value = $template_id;
                    unset($arr_temp[array_search($delete_value, $arr_temp)]);
                    if (count($arr_temp) == 0) {
                        $str_temp = "";
                    } else {
                        $str_temp = join($arr_temp, "|");
                    }
                    $set = array('patientcorrespondence' => $str_temp);
                    //将 $str_temp 写入数据库
                    $rows_affected = $db_delete->update($set, $where); //返回更新的行数
                    //向 interactionlog 插入新数据
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $username = $user->user_name;
                    $today = date("Y-m-d H:i:s");
                    $interactionlogs_data['claim_id'] = $delete_claim_id;
                    $interactionlogs_data['date_and_time'] = $today;
                    $interactionlogs_data['log'] = $username . ": Patient Correspondence generated for " . $my_template;
                    $the_last_id = mysql_insert('interactionlog', $interactionlogs_data);
                    if ($the_last_id) {
//                            echo 'MailMerge generate success';
                    } else {
//                            echo 'MailMerge generation error';
                    }

                    //copy 一份到其他目录
                    gen_claimcorr($filename, $this->sysdoc_path, $billingcompany_id, $provider_id_array[$key_id], $claim_id_array[$key_id], $my_template);
                }

                //zip 压缩过程
//              $billingcompany_id = $this->get_billingcompany_id();
                $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_correspondence = $dir_billingcompany . '/correspondence';
                if (!is_dir($dir_bc_correspondence)) {
                    mkdir($dir_bc_correspondence);
                }

                $zip_name = $dir_bc_correspondence . '/' . date("YmdHis") . '.zip';
                $result = create_zip($filenames, $zip_name);

                if ($result) {
//                    echo 'success'; //压缩成功
                    //压缩成功后删除文件
                    foreach ($filenames as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                    //document 生成过程
//                gen_corr_zip($zip_name,$this->sysdoc_path, $billingcompany_id,$provider_id,$claim_id,$template);
                } else {
//                    echo 'error';
                }



//                $varivable_message_array = array();
//                foreach($variable_array as $variable){
//                    //解析每一个$variable 串，提取=号两侧的值
//                    $varivable_message_array = parse_tag($variable);
//                    //根据=号右侧表名和列名进行检索，获得具体值
//                    $db_info = array();
//                    $fieldValues = array();
//                    foreach($varivable_message_array as $key=>$value){
//                        //解析value 
//                        $db_info = explode('.',$value);
//                        $table = $db_info[0];
//                        $column = $db_info[1];
//                        
//                        $table_name = 'Application_Model_DbTable_'.ucfirst($table);
//                        $db_retrive = new $table_name();
//                        $db = $db_retrive->getAdapter();
//                        $table_id = $table.'_id';
//                        $where = $db->quoteInto('id=?', $$table_id);
//                        $result = $db_info->fetchRow($where);
//                        $retrieve_value = $result[$column];
//                        $fieldValues[$key] = $retrieve_value;
//                    }
//                    //进行邮件合并
//                    
//                }
//                session_start();
//                $mystatement = $_SESSION['mystatement'];
//                $datas = array();
//                $ledgers = array();
//                
//                
//                //foreach ($statement_id_array as $key => $statement_id) {
//                for ($i = 0; $i < count($statement_id_array); $i++ ) {
//                    //data for print
//                    $statement_id = $statement_id_array[$i];
//                    $data = $mystatement[$statement_id];
//                    array_push($datas, $data);
//                    //ledger
//                    $ledger = ledger2($data['patient_id'], $data['number_of_days_include_in_ledger'], "statement", null, 0);
//                    array_push($ledgers, $ledger);
//                    //  gen_statement_pdf($data,$ledger,$this->sysdoc_path);
//                    $statement['date'] = date("Y-m-d");
//                    $db_statement = new Application_Model_DbTable_Statement();
//                    $db = $db_statement->getAdapter();
//                    $where = $db->quoteInto('id = ?', $statement_id);
//                    $db_statement->update($statement, $where);
//                    
//                    $claim['statement_status'] = "stmt_i";
//                    $db_claim = new Application_Model_DbTable_Claim();
//                    $db = $db_claim->getAdapter();
//                    $where = $db->quoteInto('id = ?', $claim_id_array[$i]);
//                    $db_claim->update($claim, $where);
//                }
//
//                if (sizeof($datas) != 0) {
//                    $billingcompany_id = $this->billingcompany_id;
//                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id,$provider_id_array,$patient_id_array,$claim_id_array);
//                    $_SESSION['downloadfilename'] = $filename;
//                }
//                unset($_SESSION['mystatement']);


                if (sizeof($template_array) != 0) {
//                    $billingcompany_id = $this->billingcompany_id;
//                    $filename = gen_statement_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id,$provider_id_array,$patient_id_array,$claim_id_array);
//                    $filename = ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "sysdoc" . DIRECTORY_SEPARATOR . $billingcompany_id . DIRECTORY_SEPARATOR . "patientcorrespondence" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR. $templateFileName."Temp.docx";
                    $filename = $zip_name;
                    $_SESSION['downloadfilename'] = $filename;
                }
                // This need to be modified for mail merge log
                if (!$this->generate_pc_log($encounter_id_array)) {
                    echo "Generate the pc logs failed!";
                    return;
                }

                $this->_redirect('/biller/claims/patientcorrespondenceList');
            }
            /*             * ********************Correspondence log ******************* */
            //MM: This post processing is to generate the log of the template merged
            // 进行 log 信息的输出
            else if ($this->getRequest()->getPost('pc_log') != "") {
                $billingcompany_id = $this->get_billingcompany_id();
                $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_statementlog = $dir_billingcompany . '/pclog';
                if (!is_dir($dir_bc_statementlog)) {
                    mkdir($dir_bc_statementlog);
                }
                //  $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/statementlog';
                $log_file_name = $dir_bc_statementlog . '/Correspondence.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    readfile($log_file_name);
                    exit;
                }
            } else {
                //MM: This post processing is to various posts like sorting the patientcorrespondence lsit by columns
                $postType = $this->getRequest()->getParam('post');
                session_start();
                $pcList = $_SESSION['tmp']['pcList'];
                foreach ($pcList as $key => $row) {
                    $patient[$key] = $row['name'];
                    $insurance[$key] = $row['insurance_display'];
                    $provider[$key] = $row['provider_name'];
                    $facility[$key] = $row['facility_name'];
                    $dos[$key] = format($row['DOS'], 0);
                    $mrn[$key] = $row['MRN'];
//                    $statementtype[$key] = $row['statement_type'];
                    $renderingprovider[$key] = $row['renderingprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                    $total_charge[$key] = $row['total_charge'];
                    $amount_paid[$key] = $row['amount_paid'];
                    $dob[$key] = format($row['DOB'], 0);
                    $referringprovider[$key] = $row['referringprovider_name'];
                    $correspondence[$key] = $row['template'];
                    /*                     * ************For sorting of Add fields*************** */
                }
                if ($postType == "Name") {

                    /*                     * *****************sort the insurance****************** */
                    $patient_slowercase = array_map('strtolower', $patient);
                    if ($patient_slowercase[0] >= $patient_slowercase[sizeof($patient_slowercase) - 1]) {
                        array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($patient_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    }

                    //array_multisort($patient, SORT_ASC, $pcList);
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Charge") {
                    if ($total_charge[0] <= $total_charge[sizeof($total_charge) - 1]) {
                        array_multisort($total_charge, SORT_DESC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($total_charge, SORT_ASC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                if ($postType == "Paid") {
                    if ($amount_paid[0] >= $amount_paid[sizeof($amount_paid) - 1]) {
                        $_SESSION['cliam_recent_sort_Paid_reverse_statement'] = 1;
                        array_multisort($amount_paid, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        $_SESSION['cliam_recent_sort_Paid_reverse_statement'] = 0;
                        array_multisort($amount_paid, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                if ($postType == "Referring Provider") {
                    if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1]) {

                        array_multisort($referringprovider, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($referringprovider, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                if ($postType == "DOB") {
                    if ($dob[0] >= $dob[sizeof($dob) - 1]) {
                        array_multisort($dob, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($dob, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                //MM: post type "Open" is to retrieve the previously merged templates list 
                if ($postType == "Open") {
                    $filename = $this->getRequest()->getPost('statement_dir');
                    $_SESSION['downloadfilename'] = $filename;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                if ($postType == "Insurance") {

                    /*                     * *****************sort the insurance****************** */
                    $insurance_slowercase = array_map('strtolower', $insurance);
                    if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1]) {

                        array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    } else {

                        array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    }

                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                if ($postType == "DOS") {
                    if ($dos[0] >= $dos[sizeof($dos) - 1]) {
                        array_multisort($dos, SORT_ASC, $pcList);
                    } else {
                        array_multisort($dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                if ($postType == "MRN") {
                    if ($mrn[0] >= $mrn[sizeof($mrn) - 1]) {
                        array_multisort($mrn, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($mrn, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                if ($postType == "Correspondence") {

                    /*                     * *****************sort the insurance****************** */
                    $correspondence_slowercase = array_map('strtolower', $correspondence);
                    if ($correspondence_slowercase[0] >= $correspondence_slowercase[sizeof($correspondence_slowercase) - 1]) {

                        array_multisort($correspondence_slowercase, SORT_ASC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    } else {

                        array_multisort($correspondence_slowercase, SORT_DESC, SORT_STRING, $dos, SORT_DESC, $pcList);
                    }

                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                if ($postType == "Facility") {
                    if ($facility[0] >= $facility[sizeof($facility) - 1]) {
                        array_multisort($facility, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($facility, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                if ($postType == "Provider") {
                    if ($provider[0] >= $provider[sizeof($provider) - 1]) {
                        array_multisort($provider, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($provider, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }

                if ($postType == "Rendering Provider") {
                    if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1]) {
                        array_multisort($renderingprovider, SORT_ASC, $dos, SORT_DESC, $pcList);
                    } else {
                        array_multisort($renderingprovider, SORT_DESC, $dos, SORT_DESC, $pcList);
                    }
                    $_SESSION['tmp']['pcList'] = $pcList;
                    $this->_redirect('/biller/claims/patientcorrespondence');
                }
                $encounter_id = $this->getRequest()->getPost('id');
                if ($encounter_id != null) {
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('id = ?', $encounter_id);
                    $encounter_data = $db_encounter->fetchRow($where);
                    $patient_id = $encounter_data['patient_id'];
                    $this->initsession($patient_id, $encounter_id);

                    $this->set_options_session($encounter_data['provider_id'], '', '');
                }
                $this->_redirect('/biller/claims/claim');
            }
        }
    }

    public function patientAction() {
        if (!$this->getRequest()->isPost()) {

            $dd = 0;

            $relationshipList = array();
            $relationshipList[0]['relationship'] = 'Child';
            $relationshipList[1]['relationship'] = 'Other';
            $relationshipList[2]['relationship'] = 'Self';
            $relationshipList[3]['relationship'] = 'Spouse';

            session_start();
            $options = $_SESSION['options'];
            $new_claim_flag = $_SESSION['new_claim_flag'];
            $this->view->new_claim_flag = $new_claim_flag;
            $cur_service_info = $this->get_cur_service_info('patient');
            $this->view->cur_service_info = $cur_service_info;
            $this->view->nullcheck = $this->getRequest()->getParam('nullcheck');
            $insurance_id = $cur_service_info['insurance_id'];
            $insurance_display = $cur_service_info['insurance_display'];
            if ('Self Pay' == $insurance_display) {
                $ctaiList = array();
            } else {
                $issue = array();
                $second_order = array();
                $ctaiList = $this->getTAIList($insurance_id, $issue, $second_order);
                array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $ctaiList);
            }
            $this->view->taiList = $ctaiList;
            $dd = 0;

            $index = 0;
            for ($i = 0; $i < 4; $i++) {
                if (strtolower($relationshipList[$i]['relationship']) == strtolower($options['default_patient_relationship_to_insured'])) {
                    $index = $i;
                    break;
                }
            }
            $relationshipList[$index]['relationship'] = $relationshipList[0]['relationship'];
            $relationshipList[0]['relationship'] = ucwords($options['default_patient_relationship_to_insured']);
            $this->view->relationshipList = $relationshipList;

            $dd = 0;

            $patient_id = $_SESSION['patient_data']['id'];
            $payments = $_SESSION['payments_data'];
            $claim_id = $_SESSION['claim_data']['id'];
            $number_of_days_include_in_ledger = $_SESSION['options']['number_of_days_include_in_ledger'];
            if ($patient_id != null) {
                //$ledgerList1 = ledger($patient_id, $number_of_days_include_in_ledger, "patient");
                $ledgerList = ledger2($patient_id, $number_of_days_include_in_ledger, 'patient', $payments, $claim_id, 0);
                $this->view->ledgerList = $ledgerList;
            }

            $dd = 0;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $this->view->user_name = $user_name;
        }
        if ($this->getRequest()->isPost()) {
            session_start();

            $dd = 0;

            /*             * *************************Add inquiry_last_name**************************** */
            $inquiry_last_name = $_SESSION['$_SESSION'];
            /*             * *************************Add inquiry_last_name**************************** */
            $patientlogs = $_SESSION['patientlogs'];
            $patient = $_SESSION['patient_data'];
            $statement = $_SESSION['statement_data'];
            $patient_id = $_SESSION['patient_data']['id'];
// get post QXW 18.02.2012
//            foreach (get_ui2patient() as $key => $val) {
//                    $v = $this->getRequest()->getPost($val);
//                $patient[$key] = $v;
//            }
//haoqiang, 16.09.2015
            foreach (get_ui2patient() as $key => $val) {
                if ($val == "SSN") {
                    $prim_v = $this->getRequest()->getPost($val);
                    if (substr($prim_v, 0, 1) != "*") {
                        $patient[$key] = $prim_v;
                    }
                    continue;
                }
                $v = $this->getRequest()->getPost($val);
                $patient[$key] = $v;
            }
            foreach (get_ui2statement() as $key => $val) {
                $v = $this->getRequest()->getPost($val);
//if ($v != '')
                $statement[$key] = $v;
            }
            $j = 0;
            $count5 = intval($this->getRequest()->getParam('count5'));
            $count_temp_c = count($patientlogs);
            $j = $count_temp_c;
            if ($count5 > $count_temp_c) {

                for ($i = $count_temp_c; $i < $count5; $i++) {
                    $v = $this->getRequest()->getPost('date_and_time_' . ($i + 1));
                    if ($v != null && $v != "") {
                        $patientlogs[$j]['notsave'] = 1;
                        $patientlogs[$j]['patient_id'] = $patient_id;
//                        $insurancepayments[$j]['id'] = '';
                        foreach (get_ui2patientlogs() as $key => $val) {
                            $v = $this->getRequest()->getPost($val . ($i + 1));
                            $patientlogs[$j][$key] = $v;
                        }
                        $j++;
                    }
                }
            }
            array_splice($patientlogs, $j);
            $condition = '000';
            $key = $patient['condition_related_to'];
            for ($i = 0; $i < 3; $i++) {
                if ($key[$i] == "employment")
                    $condition[0] = '1';
                if ($key[$i] == "auto")
                    $condition[1] = '1';
                if ($key[$i] == "other")
                    $condition[2] = '1';
            }
            $patient['condition_related_to'] = $condition;
            session_start();
            $_SESSION['patientlogs'] = $patientlogs;
            $_SESSION['patient_data'] = $patient;
            $_SESSION['statement_data'] = $statement;
//            $this->write_session('patient', $patient);
//            $this->write_session('statement', $statement);
            session_start();
            if ($_SESSION['patient_data']['diff']['relationship_to_insured'] == 'self') {
                $insured = array();
                foreach (get_common_patient_and_insured() as $val)
                    $insured[$val] = $_SESSION['patient_data']['$val'];
                $_SESSION['insured_data'] = $insured;
//  $this->write_session('insured', $insured);
            }
//            if(strlen($patient['DOB'])<10&&strlen($patient['DOB'])!=0){
//                        $ret = 'DOB';
//                        $this->_redirect('/biller/claims/patient/nullcheck/' . $ret);
//            }
            $dd = 0;
            $submitType = $this->getRequest()->getParam('submit');
            $this->_redirect('/biller/claims/' . $this->navigation($submitType, 0));
        }
    }

    public function deletependingstatementAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $statement = $_SESSION['statement_data'];
        $statement_id = $statement['statement_id2'];
        $db_statement = new Application_Model_DbTable_Statement();
        $db = $db_statement->getAdapter();
        $where = $db->quoteInto('id = ?', $statement_id);
        $temp = $db_statement->delete($where);
        $newstatement = array();
        foreach (get_ui2statement() as $key) {
            $newstatement[$key] = null;
        }
        $newstatement['statement_id2'] = null;
        $newstatement['statement_id1'] = null;
        $_SESSION['statement_data'] = $newstatement;
        $_SESSION['statement_data_BK'] = $_SESSION['statement_data'];
    }

    public function zip2citystateAction() {
        $this->_helper->viewRenderer->setNoRender();
        $zip = $_POST['zip'];

        $db_zip2citystate = new Application_Model_DbTable_Zip2citystate();
        $db = $db_zip2citystate->getAdapter();
        $where = $db->quoteInto('zip = ?', $zip);
        $zip2citystate_data = $db_zip2citystate->fetchRow($where);
        $data = array();
        $data = array('city' => $zip2citystate_data['city'], 'state' => $zip2citystate_data['state'],);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function differmrnAction() {
        $this->_helper->viewRenderer->setNoRender();
        $mrn = $_POST['mrn'];

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $billingcompany_id = $this->get_billingcompany_id();
        $select->from('patient');
        $select->join('encounter', 'patient_id = patient.id');
        //$select->join('mypatient', 'patient.id = mypatient.patient_id');
        $select->join('provider', 'provider.id=encounter.provider_id');
        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->where('billingcompany.id=?', $billingcompany_id);
        $select->where('patient.account_number=?', $mrn);
        $patient_data = $db->fetchAll($select);

//        $patient = new Application_Model_DbTable_Patient();
//        $db = $patient->getAdapter();
//        $where = $db->quoteInto('account_number = ?', $mrn);
//        $patient_data = $patient->fetchAll($where);
        if ($patient_data != null)
            $count = 1;
//$patient_data->count();

        $data = array();
        $data = array('count' => $count);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function remarkAction() {
        $this->_helper->viewRenderer->setNoRender();
        $statement = $_POST['statement'];
        $trigger = $_POST['trigger'];

        session_start();
        $options_data = $_SESSION['options'];
        $encounter_data = $_SESSION['encounter_data'];
        $statementindex['1'] = 'I';
        $statementindex['2'] = 'II';
        $statementindex['3'] = 'III';
        $statementindex['5'] = 'V';

        $remark = $options_data['statement_' . $statementindex[$statement] . '_' . $trigger];
        if ($remark != null)
            $claim = format($encounter_data['start_date_1'], 1) . ' ' . $encounter_data['CPT_code_1'];
        $data = array();
        $data = array('remark' => $remark, 'claim' => $claim);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function showrealssnAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();

        $index = $this->_request->get('index');

        $insured = $_SESSION['insured_data'];
        $patient_data = $_SESSION['patient_data'];

        if (is_null($index)) {
            $SSN = ssn($patient_data['SSN']);
        } else {
            $SSN = ssn($insured[$index - 1]["SSN"]);
        }

        $data['ssn'] = $SSN;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function patientinputAction() {
        $this->_helper->viewRenderer->setNoRender();

        session_start();

        /*         * ******************Add the inquiry name********************** */
        $need_save = $_SESSION['claim_data']['needsave'];
        $inquiry_last_name = $_SESSION['inquiry_last_name'];
        /*         * ******************Add the inquiry name********************** */
        $patient_data = $_SESSION['patient_data'];
        $statement_data = $_SESSION['statement_data'];
        $patientlogs = $_SESSION['patientlogs'];
        $count_patientlogs = count($patientlogs);
        for ($i = 0; $i < $count_patientlogs; $i++) {
            $date_sort[$i] = $patientlogs[$i]['date_and_time'];
            $temp = date('m/d/Y H:i:s', strtotime($date_sort[$i]));
            $patientlogs[$i]['date_and_time'] = $temp;
        }
        //array_multisort($date_sort, SORT_DESC, $patientlogs, SORT_DESC);
        $new_patient_flag = $_SESSION['new_patient_flag'];

        $data = array();

        $date1 = format($statement_data['date1'], 1);
        $date2 = format($statement_data['date2'], 1);
        $DOB = format($patient_data['DOB'], 1);
//        $date_statement_I = format($patient_data['date_statement_I'], 1);
//        $date_statement_II = format($patient_data['date_statement_II'], 1);
//        $date_statement_III = format($patient_data['date_statement_III'], 1);
//        $encode_SSN = $patient_data['SSN'];
//        $decode_SSN = decodeSSN($encode_SSN);

        $SSN = ssn($patient_data['SSN']);
        //新patient一直不需要mask SSN
        if ($new_patient_flag != 1) {
            $SSN = maskSSN($SSN);
        }

//        $SSN = ssn($decode_SSN);
//        $last_four = substr($SSN, -4, 4);
//        $SSN = "****-****-" . $last_four;

        $zip = zip($patient_data['zip']);
        $phone_number = phone($patient_data['phone_number']);
        $second_phone_number = phone($patient_data['second_phone_number']);

        $data = array('id' => $patient_data['id'], 'sex' => $patient_data['sex'], 'SSN' => $SSN, 'last_name' => $patient_data['last_name'], 'first_name' => $patient_data['first_name'], 'DOB' => $DOB, 'insurance_card_image' => $patient_data['insurance_card_image'],
            'street_address' => $patient_data['street_address'], 'state' => $patient_data['state'], 'city' => $patient_data['city'], 'zip' => $zip, 'statement_type1' => $statement_data['statement_type1'], 'statement_type2' => $statement_data['statement_type2'], //'date_statement_II' => $date_statement_II,
            'phone_number' => $phone_number, 'second_phone_number' => $second_phone_number, 'account_number' => $patient_data['account_number'], 'date1' => $date1, 'date2' => $date2, 'trigger1' => $statement_data['trigger1'], 'trigger2' => $statement_data['trigger2'],
            'remark1' => $statement_data['remark1'], 'remark2' => $statement_data['remark2'], 'encounter_id1' => $statement_data['encounter_id1'], 'encounter_id2' => $statement_data['encounter_id2'], 'claim1' => $statement_data['claim1'], 'claim2' => $statement_data['claim2'],
            'statement_id1' => $statement_data['statement_id1'], 'statement_id2' => $statement_data['statement_id2'],
            'notes' => $patient_data['notes'], 'status' => $patient_data['status'], 'relationship_to_insured' => $patient_data['relationship_to_insured'], 'condition_related_to' => $patient_data['condition_related_to'], 'one_off_paths' => $one_off_paths, 'inquiry_last_name' => $inquiry_last_name,
            //Add patient alert
            'patient_alert_info' => $patient_data['alert'], 'patientlogs' => $patientlogs, 'new_patient_flag' => $new_patient_flag, 'needsave' => $need_save
        );

        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function insuranceAction() {
        if (!$this->getRequest()->isPost()) {

            $dd = 0;

            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();

            Zend_Session::start();

            $cur_service_info = $this->get_cur_service_info('insurance');
            $this->view->cur_service_info = $cur_service_info;
            $insurance_id = $cur_service_info['insurance_id'];
            $insurance_display = $cur_service_info['insurance_display'];
            if ('Self Pay' == $insurance_display) {
                $ctaiList = array();
            } else {
                $issue = array();
                $second_order = array();
                $ctaiList = $this->getTAIList($insurance_id, $issue, $second_order);
                array_multisort($issue, SORT_DESC, $second_order, SORT_ASC, SORT_NUMERIC, $ctaiList);
            }
            $this->view->taiList = $ctaiList;
            $this->view->nullcheck = $this->getRequest()->getParam('nullcheck');
            $where = $db->quoteInto('billingcompany_id = ?', $this->get_billingcompany_id());
            $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
            movetotop($insuranceList); ////if($insuranceList[$i]['insurance_name'] == 'Need New Insurance') {
            $this->movetobot($insuranceList, 'insurance_name', 'Need New Insurance');
            $this->view->insuranceList = $insuranceList;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $this->view->user_name = $user_name;
            session_start();
            $new_claim_flag = $_SESSION['new_claim_flag'];
            $this->view->new_claim_flag = $new_claim_flag;
        }

        if ($this->getRequest()->isPost()) {
            $dd = 0;
            session_start();
            $insured = $_SESSION['insured_data'];
            //$session_array_count = $insured[0]['array_count'];
            $session_array_count = count($insured);
            $insured_info_count = $this->getRequest()->getPost('insured_info_count');
            $insured_info_count = (int) $insured_info_count;
            $tp_insured = array();
            // collect the insured_info after sumbit
            for ($i = 0; $i < $insured_info_count; $i++) {
                foreach (get_ui2insured() as $key => $val) {
//                    if ($val == "SSN") {
//                        $prim_v = $this->getRequest()->getPost($val);
//                        if (substr($prim_v, 0, 1) != "*") {
//                            $tp_insured[$i][$key] = $prim_v;
//                        }
//                            continue;
//                    }
                    $v = $this->getRequest()->getPost($val . '_' . ($i + 1));
                    if (($v != null) && ($v != '')) {
                        if ($key == 'insured_insurance_type') {
                            $tp_insured[$i][$key] = strtolower($v);
                        } elseif ($key == 'SSN') {
                            if ($insured !== null) {
                                $primSSN = $insured[$i][$key];
                            } else {
                                $primSSN = $_SESSION['patient_data']['SSN'];
                            }

                            if (substr($v, 0, 1) != "*") {
                                $tp_insured[$i][$key] = $v;
                            } else {
                                $tp_insured[$i][$key] = $primSSN;
                            }
                        } else {
                            $tp_insured[$i][$key] = $v;
                        }
                    }
                }
                $tp_insured[$i]['insured_order_' . ($i + 1)] = $this->getRequest()->getPost('insured_order_' . ($i + 1));
            }

            $dd = 0;

            //sort the insurd_info according to the insure_order
            for ($i = 0; $i < $insured_info_count; $i++) {
                for ($j = $i + 1; $j < $insured_info_count; $j++) {
                    if ($tp_insured[i]['insured_order_' . ($i + 1)] > $tp_insured[$j]['insured_order_' . ($j + 1)]) {
                        $tmp = $tp_insured[$i];
                        $tp_insured[$i] = $tp_insured[$j];
                        $tp_insured[$j] = $tmp;
                    }
                }
            }
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $this->view->user_name = $user_name;
            $patient_id = $_SESSION['patient_data']['id'];
            $dd = 0;
            $index = $session_array_count;
            $j = 0;
            //array_splice($patientlogs, $j);
            $patientlogs = $_SESSION['patientlogs'];
            $count_patient_logs = count($patientlogs);
            $index_patient_logs = $count_patient_logs;
            $new_patient_flag = $_SESSION['new_patient_flag'];
            $count5 = intval($this->getRequest()->getParam('count5'));
            if ($count5 > 0) {

                for ($i = 0; $i < $count5; $i++) {
                    $v = $this->getRequest()->getPost('date_and_time_' . ($i + 1));
                    if ($v != null && $v != "") {
                        $patientlogs[$index_patient_logs]['patient_id'] = $patient_id;
//                        $insurancepayments[$j]['id'] = '';
                        foreach (get_ui2patientlogs() as $key => $val) {
                            $v = $this->getRequest()->getPost($val . ($i + 1));
                            $patientlogs[$index_patient_logs][$key] = $v;
                        }
                        $index_patient_logs++;
                    }
                }
            }
            $insurance_array = array();
            for ($i = 0; $i < $insured_info_count; $i++) {
                $insurance_array[$i] = $tp_insured[$i]['insurance_id'];
                ;
                if ($tp_insured[$i]['insured_order_' . ($i + 1)] == 0) {
                    $insured[$index] = $tp_insured[$i];
                    $insured[$index]['id'] = "temp_0" . $i;
                    $index++;
                    $_SESSION['insured_data_change_flag'] = 1;
                    if ($new_patient_flag != 1) {
                        $date = date("m/d/Y H:i:s");
                        //$date = date("Y-m-d H:i:s");
                        //$type = $tp_insured[$i]['insured_insurance_type'];
                        $insuredId = $tp_insured[$i]['ID_number'];
                        $insurance_id = $tp_insured[$i]['insurance_id'];

                        $insured_name = $tp_insured[$i]['last_name'] . ", " . $tp_insured[$i]['first_name'];
                        ;
                        $db_insurance = new Application_Model_DbTable_Insurance();
                        $db = $db_insurance->getAdapter();
                        $where = $db->quoteInto("id=?", $insurance_id);
                        $insurance_data = $db_insurance->fetchRow($where);
                        $display_name = $insurance_data['insurance_display'];
                        //$log = $user_name . ': Added '. $display_name . '/' . $insuredId . '/' . $insured_name . ' as ' . $type;
                        $log = $user_name . ': Added ' . $display_name . '/' . $insuredId . '/' . $insured_name;
                        //$patientlogs[$index_patient_logs]['date_and_time'] = $date;
                        //$patientlogs[$index_patient_logs]['log'] = $log;
                        //$patientlogs[$index_patient_logs]['patient_id'] =$patient_id;
                        //$index_patient_logs++;
                    }
                    //$date = date()
                } else { //existing insurance
                    $insured[$j]["DOB"] = format($insured[$j]["DOB"], 0);
                    foreach (get_ui2insured_2_1() as $key => $val) {
                        if (($key == 'DOB') || ($key == 'insured_insurance_expiration_date')) {
                            $tp_insured[$i][$key] = format($tp_insured[$i][$key], 0);
                        }
                        if (($key == 'phone_number' ) || ($key == "second_phone_number")) {
                            $find = array('(', ')', '-');
                            $phone_num_tmp = str_replace($find, null, $tp_insured[$i][$key]);
                            $tp_insured[$i][$key] = $phone_num_tmp;
                            $phone_num = str_replace($find, null, $insured[$j][$key]);
                            $insured[$j][$key] = $phone_num;
                        }
                        if (($key == 'SSN') || ($key == 'zip' )) {
                            $tp_insured[$i][$key] = str_replace(array("-", "(", ")"), array("", "", ""), $tp_insured[$i][$key]);
                        }
                        if ($key == "relationship_to_patient") {
                            if ($tp_insured[$i][$key] == '' || $tp_insured[$i][$key] == null) {
                                $tp_insured[$i][$key] = 'self';
                            }
                        }
                    }
                    foreach (get_ui2insured_2_1() as $key => $val) {
                        if (($tp_insured[$i][$key] != '' && $tp_insured[$i][$key] != null ) || ($insured[$j][$key] != null && $insured[$j][$key] != '')) {
                            if ($insured[$j][$key] != $tp_insured[$i][$key]) {
                                if ($new_patient_flag != 1) {
                                    $date = date("m/d/Y H:i:s");
                                    //$date = date("Y-m-d H:i:s");
                                    $type_last = $insured[$j]['insured_insurance_type'];
                                    $type_cur = $tp_insured[$i]['insured_insurance_type'];
                                    $insuredId = $tp_insured[$i]['ID_number'];
                                    $insurance_id = $tp_insured[$i]['insurance_id'];
                                    $insured_name = $tp_insured[$i]['last_name'] . ", " . $tp_insured[$i]['first_name'];
                                    ;
                                    $db_insurance = new Application_Model_DbTable_Insurance();
                                    $db = $db_insurance->getAdapter();
                                    $where = $db->quoteInto("id=?", $insurance_id);
                                    $insurance_data = $db_insurance->fetchRow($where);
                                    $display_name = $insurance_data['insurance_display'];
                                    $log = $user_name . ': Changed Insured(' . $tp_insured[$i]["insured_order_" . ($i + 1)] . ") from ";
//                                        foreach(get_ui2insured_2_1() as $key_tmp => $val_tmp){
//                                            if($key_tmp=="relationship_to_patient"){
//                                                $log = $log . $insured[$j][$key_tmp]; 
//                                            }else{
//                                                $log = $log . $insured[$j][$key_tmp] . "|";
//                                            }
//                                        }
//                                        $log = $log ." to ";
//                                        foreach(get_ui2insured_2_1() as $key_tmp => $val_tmp){
//                                            if($key_tmp=="relationship_to_patient"){
//                                                $log = $log . $tp_insured[$j][$key_tmp]; 
//                                            }else{
//                                                $log = $log . $tp_insured[$j][$key_tmp] . "|";
//                                            }
//                                        }
                                    foreach (get_ui2insured_2_1() as $key_tmp => $val_tmp) {
                                        if ($key_tmp == "relationship_to_patient") {
                                            $log = $log . $insured[$j][$key_tmp];
                                        } else if ($key_tmp == "SSN") {
                                            $maskSSN = maskSSN($insured[$j][$key_tmp]);
                                            $log = $log . $maskSSN . "|";
                                        } else {
                                            $log = $log . $insured[$j][$key_tmp] . "|";
                                        }
                                    }
                                    $log = $log . " to ";
                                    foreach (get_ui2insured_2_1() as $key_tmp => $val_tmp) {
                                        if ($key_tmp == "relationship_to_patient") {
                                            $log = $log . $tp_insured[$j][$key_tmp];
                                        } else if ($key_tmp == "SSN") {
                                            $maskSSN = maskSSN($tp_insured[$j][$key_tmp]);
                                            $log = $log . $maskSSN . "|";
                                        } else {
                                            $log = $log . $tp_insured[$j][$key_tmp] . "|";
                                        }
                                    }
                                    $patientlogs[$index_patient_logs]['date_and_time'] = $date;
                                    $patientlogs[$index_patient_logs]['log'] = $log;
                                    $patientlogs[$index_patient_logs]['patient_id'] = $patient_id;
                                    $index_patient_logs++;
                                }
                                break;
                            }
                        }
                    }
                    foreach (get_ui2insured_2_2() as $key => $val) {
                        if (($tp_insured[$i][$key] != '' && $tp_insured[$i][$key] != null ) || ($insured[$j][$key] != null && $insured[$j][$key] != '')) {
                            if ($insured[$j][$key] != $tp_insured[$i][$key]) {
                                if ($new_patient_flag != 1) {
                                    $date = date("m/d/Y H:i:s");
                                    //$date = date("Y-m-d H:i:s");
                                    $insurance_id = $tp_insured[$i]['insurance_id'];
                                    $db_insurance = new Application_Model_DbTable_Insurance();
                                    $db = $db_insurance->getAdapter();
                                    $where = $db->quoteInto("id=?", $insurance_id);
                                    $insurance_data = $db_insurance->fetchRow($where);
                                    $display_name = $insurance_data['insurance_display'];
                                    $where2 = $db->quoteInto("id=?", $insured[$j]["insurance_id"]);
                                    $insurance_data_bk = $db_insurance->fetchRow($where2);
                                    $display_name_bk = $insurance_data_bk['insurance_display'];
                                    $log = $user_name . ': Changed Insurance(' . $tp_insured[$i]["insured_order_" . ($i + 1)] . ") from ";
                                    foreach (get_ui2insured_2_2() as $key_tmp => $val_tmp) {
                                        if ($key_tmp == "insurance_id") {
                                            $log = $log . $display_name_bk;
                                        } else {
                                            $log = $log . $insured[$j][$key_tmp] . "|";
                                        }
                                    }
                                    $log = $log . " to ";
                                    foreach (get_ui2insured_2_2() as $key_tmp => $val_tmp) {
                                        if ($key_tmp == "insurance_id") {
                                            $log = $log . $display_name;
                                        } else {
                                            $log = $log . $tp_insured[$j][$key_tmp] . "|";
                                        }
                                    }
                                    $patientlogs[$index_patient_logs]['date_and_time'] = $date;
                                    $patientlogs[$index_patient_logs]['log'] = $log;
                                    $patientlogs[$index_patient_logs]['patient_id'] = $patient_id;
                                    $index_patient_logs++;
                                }
                                break;
                            }
                        }
                    }
                    foreach (get_ui2insured_2_3() as $key => $val) {
                        if (($tp_insured[$i][$key] != '' && $tp_insured[$i][$key] != null ) || ($insured[$j][$key] != null && $insured[$j][$key] != '')) {
                            if ($insured[$j][$key] != $tp_insured[$i][$key]) {
                                if ($new_patient_flag != 1) {
                                    $date = date("m/d/Y H:i:s");
                                    //$date = date("Y-m-d H:i:s");
                                    $log = $user_name . ': Changed Insurance Notes(' . $tp_insured[$i]["insured_order_" . ($i + 1)] . ") from ";
                                    foreach (get_ui2insured_2_3() as $key_tmp => $val_tmp) {
                                        if ($insured[$j][$key_tmp]) {
                                            $log = $log . $insured[$j][$key_tmp];
                                        } else {
                                            $log = $log . " null ";
                                        }
                                    }
                                    $log = $log . " to ";
                                    foreach (get_ui2insured_2_3() as $key_tmp => $val_tmp) {
                                        $log = $log . $tp_insured[$j][$key_tmp];
                                    }
                                    $patientlogs[$index_patient_logs]['date_and_time'] = $date;
                                    $patientlogs[$index_patient_logs]['log'] = $log;
                                    $patientlogs[$index_patient_logs]['patient_id'] = $patient_id;
                                    $index_patient_logs++;
                                }
                                break;
                            }
                        }
                    }
                    foreach (get_ui2insured_2() as $key => $val) {
                        if ($key == 'insured_insurance_expiration_date') {
                            $tp_insured[$i][$key] = format($tp_insured[$i][$key], 0);
                        }
                        if (($tp_insured[$i][$key] != '' && $tp_insured[$i][$key] != null ) || ($insured[$j][$key] != null && $insured[$j][$key] != '')) {
                            if ($insured[$j][$key] != $tp_insured[$i][$key]) {
                                /*                                 * * zw < 
                                  if($new_patient_flag != 1 && $key=='insured_insurance_type'){
                                  $date = date("m/d/Y h:i:s");
                                  //$date = date("Y-m-d H:i:s");
                                  $type_last = $insured[$j]['insured_insurance_type'];
                                  $type_cur = $tp_insured[$i]['insured_insurance_type'];
                                  $insuredId = $tp_insured[$i]['ID_number'];
                                  $insurance_id =  $tp_insured[$i]['insurance_id'];
                                  $insured_name =  $tp_insured[$i]['last_name'] . ", " .  $tp_insured[$i]['first_name'];;
                                  $db_insurance = new Application_Model_DbTable_Insurance();
                                  $db = $db_insurance->getAdapter();
                                  $where = $db->quoteInto("id=?",$insurance_id);
                                  $insurance_data = $db_insurance->fetchRow($where);
                                  $display_name = $insurance_data['insurance_display'];
                                  $log = $user_name . ': Changed ' . $display_name . '/' . $insuredId . '/' . $insured_name . ' from ' . $type_last . ' to ' . $type_cur;
                                  //$patientlogs[$index_patient_logs]['date_and_time'] = $date;
                                  //$patientlogs[$index_patient_logs]['log'] = $log;
                                  //$patientlogs[$index_patient_logs]['patient_id'] =$patient_id;
                                  //$index_patient_logs++;
                                  }
                                 * 
                                  > */
                                $insured[$j][$key] = $tp_insured[$i][$key];
                                $_SESSION['insured_data_change_flag'] = 1;
                            }
                        }
                    }
                    $j++;
                }
            }


            array_splice($patientlogs, $index_patient_logs);
            $_SESSION['patientlogs'] = $patientlogs;
            /*
              $insurance = array();
              foreach (get_ui2insurance() as $key => $val) {
              $v = $this->getRequest()->getPost($val);

              $insurance[$key] = $v;
              }
              $_SESSION['insurance_data'] = $insurance;
             */
            /*             * new add at 10/2 */
//            for($i = 0; $i < $insured_info_count; $i++){
//                $insurance_id_here=$insured[$i]['insurance_id'];
//                $db_insurance = new Application_Model_DbTable_Insurance();
//                $db = $db_insurance->getAdapter();
//                $where = $db->quoteInto("id=?",$insurance_id_here);
//                $insurance_data = $db_insurance->fetchRow($where);
//                $display_name = $insurance_data['insurance_name'];
//                $insured[$i]['insurance_name']=$display_name;
//            }
            /* 222 */
            session_start();

            $_SESSION['insured_data'] = $insured;


            $tmp_encounterinsured_data = $_SESSION['encounterinsured_data'];

            if ($tmp_encounterinsured_data == null) {
                /*                 * * zw <
                  foreach($insured as $row)
                  {
                  if($row['insured_insurance_type'] == 'primary')
                  $tmp_insurance_id = $row['insurance_id'];
                  }
                  $db_insurance = new Application_Model_DbTable_Insurance();
                  $db = $db_insurance->getAdapter();
                  $where = $db->quoteInto('id = ?',$tmp_insurance_id);
                  $insurance_data = $db_insurance->fetchRow($where);

                  $_SESSSION['insurance_data'] = $insurance_data;
                 * 
                  > */
                $_SESSSION['insurance_data'] = null;
            }

//          $this->write_session('insured', $insured);          
//          $this->write_session('insurance', $insurance);
            $index_next = $this->doeshasrepetitionArray($insurance_array);
            if ($index_next != null) {
                $index_next_array = split(',', $index_next);
                $first = $index_next_array[0];
                $second = $index_next_array[1];

                $DOB_first = format($tp_insured[$first]['DOB'], 0);
                $DOB_second = format($tp_insured[$second]['DOB'], 0);

                if ($tp_insured[$first]['last_name'] == $tp_insured[$second]['last_name'] && $tp_insured[$first]['first_name'] == $tp_insured[$second]['first_name'] && $DOB_first == $DOB_second && $tp_insured[$first]['ID_number'] == $tp_insured[$second]['ID_number'] && $DOB_first == $DOB_second) {
                    $ret = 'last_name_' . ($index_next_array[1] + 1);
                    $this->_redirect('/biller/claims/insurance/nullcheck/' . $ret);
                }
            }
            $submitType = $this->getRequest()->getParam('submit');
            $this->_redirect('/biller/claims/' . $this->navigation($submitType, 1));
        }
    }

    public function doeshasrepetitionArray($theArray) {

        for ($i = 0; $i < count($theArray) - 1; $i++) {
            for ($j = $i + 1; $j < count($theArray); $j++) {
                if ($theArray[$i] == $theArray[$j]) {
                    return $i . ',' . $j;
                }
            }
        }
        return 0;
    }

    public function navigation($btn_val, $cur_page) {

        $uis = array('patient', 'insurance', 'services', 'claim', 'documents', 'saveclaim/pageno/' . $cur_page, 'finishclaim');
//        if ($cur_page < 2 && $btn_val == 'finish claim')
//            return $uis($cur_page);
        $redirect_page = 0;
        $redirect = strtolower($btn_val);
        switch ($redirect) {
            case 'previous':
                $redirect_page = $cur_page - 1;
                break;
            case 'patient':
                $redirect_page = 0;
                break;
            case 'insurance':
                $redirect_page = 1;
                break;
            case 'service':
                $redirect_page = 2;
                break;
            case 'claim':
                $redirect_page = 3;
                break;
            case 'documents':
                $redirect_page = 4;
                break;
            case 'next':
                $redirect_page = $cur_page + 1;
                break;
            case 'save claim':
                $redirect_page = 5;
                break;
            case 'finish claim':
                $redirect_page = 6;
                break;

            default:
                break;
        }
        return $uis[$redirect_page];
    }

    public function claiminsuranceinfoAction() {

        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $_SESSION['claim_data']['needsave'] = 1;
        $insurance_id = $_POST['insurance_id'];

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
//$where = $db->quoteInto('last_name=? AND first_name=? AND DOB=?',$last_name,$first_name,$DOB);
//$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
        $where = $db->quoteInto('id = ?', $insurance_id); //
//$order = "insurance_name DESC";
        $insurance_data = $db_insurance->fetchAll($where);

        $count = $insurance_data->count();

        if ($count > 0) {
            $data = array();
            foreach ($insurance_data as $row) {
                $fax_number = phone($row->fax_number);
                $data = array('insurance_id' => $row->id, 'insurance_name' => $row->insurance_name, 'insurance_display' => $row->insurance_display, 'insurance_zip' => $row->zip, 'fax_number' => $row->fax_number, 'payer_type' => $row->payer_type,
                    'insurance_type' => strtoupper($row->insurance_type),
                    'insurance_street_address' => $row->street_address, 'insurance_state' => $row->state, 'insurance_city' => $row->city, 'anesthesia_bill_rate' => $row->anesthesia_bill_rate,
                    'insurance_phone_number' => phone($row->phone_number), 'EDI_number' => $row->EDI_number, 'claim_submission_preference' => $row->claim_submission_preference, 'PID_interpretation' => $row->PID_interpretation, 'navinet_web_support_number' => $row->navinet_web_support_number,
                    'appeal' => $row->appeal, 'reconsideration' => $row->reconsideration, 'claim_filing_deadline' => $row->claim_filing_deadline, 'EFT' => $row->EFT, 'claim_status_lookup' => $row->claim_status_lookup, 'benefit_lookup' => $row->benefit_lookup, 'anesthesia_crosswalk_overwrite' => $row->anesthesia_crosswalk_overwrite,
                    'fax_number' => $fax_number, 'insurance_notes' => $row->notes, 'alert' => $row->alert);
            }

            $json = Zend_Json::encode($data);
            echo $json;
        } else {
            return;
        }
    }

    public function insuranceinfoAction() {

        $this->_helper->viewRenderer->setNoRender();
        $insurance_id = $_POST['insurance_id'];

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
//$where = $db->quoteInto('last_name=? AND first_name=? AND DOB=?',$last_name,$first_name,$DOB);
//$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
        $where = $db->quoteInto('id = ?', $insurance_id); //
//$order = "insurance_name DESC";
        $insurance_data = $db_insurance->fetchAll($where);

        $count = $insurance_data->count();

        if ($count > 0) {
            $data = array();
            foreach ($insurance_data as $row) {
                $fax_number = phone($row->fax_number);
                $data = array('insurance_id' => $row->id, 'insurance_name' => $row->insurance_name, 'insurance_display' => $row->insurance_display, 'insurance_zip' => $row->zip, 'fax_number' => $row->fax_number, 'payer_type' => $row->payer_type,
                    'insurance_type' => strtoupper($row->insurance_type),
                    'insurance_street_address' => $row->street_address, 'insurance_state' => $row->state, 'insurance_city' => $row->city, 'anesthesia_bill_rate' => $row->anesthesia_bill_rate,
                    'insurance_phone_number' => phone($row->phone_number), 'EDI_number' => $row->EDI_number, 'claim_submission_preference' => $row->claim_submission_preference, 'PID_interpretation' => $row->PID_interpretation, 'navinet_web_support_number' => $row->navinet_web_support_number,
                    'appeal' => $row->appeal, 'reconsideration' => $row->reconsideration, 'claim_filing_deadline' => $row->claim_filing_deadline, 'EFT' => $row->EFT, 'claim_status_lookup' => $row->claim_status_lookup, 'benefit_lookup' => $row->benefit_lookup, 'anesthesia_crosswalk_overwrite' => $row->anesthesia_crosswalk_overwrite,
                    'fax_number' => $fax_number, 'insurance_notes' => $row->notes, 'alert' => $row->alert);
            }
            $json = Zend_Json::encode($data);
            echo $json;
        } else {
            return;
        }
    }

    public function patientinfoAction() {

        /*
          $this->_helper->viewRenderer->setNoRender();
          $patient_id = $_POST['patient_id'];

          $db_patient = new Application_Model_DbTable_Patient();
          $db = $db_patient->getAdapter();
          //$where = $db->quoteInto('last_name=? AND first_name=? AND DOB=?',$last_name,$first_name,$DOB);
          //$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
          $where = $db->quoteInto('id = ?', $patient_id); //
          //$order = "insurance_name DESC";
          $patient_data = $db_patient->fetchAll($where);

          $count = $patient_data->count();

          if ($count > 0) {
          $data = array();
          foreach ($patient_data as $row) {
          $data = array('patient_id' => $row->id, 'last_name' => $row->last_name, 'first_name' => $row->first_name, 'DOB' => format($row->DOB, 1), 'street_address' => $row->street_address,
          'city' => $row->city, 'state' => $row->state, 'zip' => $row->zip, 'phone_number' => $row->phone_number, 'second_phone_number' => $row->second_phone_number,
          'sex' => $row->sex, 'SSN' => $row->SSN, 'relationship_to_insured' => $row->relationship_to_insured,'SSN' => $row->SSN
          );
          }
          $json = Zend_Json::encode($data);
          echo $json;
          } else {
          return;
          }
         */

        $this->_helper->viewRenderer->setNoRender();
        $patient_id = $_POST['patient_id'];

        session_start();
        $patient_data = $_SESSION['patient_data'];

        $data = array('patient_id' => $patient_data['id'], 'last_name' => $patient_data['last_name'], 'first_name' => $patient_data['first_name'], 'DOB' => format($patient_data['DOB'], 1), 'street_address' => $patient_data['street_address'],
            'city' => $patient_data['city'], 'state' => $patient_data['state'], 'zip' => $patient_data['zip'], 'phone_number' => $patient_data['phone_number'], 'second_phone_number' => $patient_data['second_phone_number'],
            'sex' => $patient_data['sex'], 'SSN' => $patient_data['SSN'], 'relationship_to_insured' => $patient_data['relationship_to_insured']
        );

        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function insuranceinputAction() {
        $this->_helper->viewRenderer->setNoRender();

        session_start();
        $patient_data = $_SESSION['patient_data'];
        $new_patient_falg = $_SESSION['new_patient_flag'];
        /*         * **************Have been changed***************** */
        $insured_array_data = $_SESSION['insured_data'];
        /*         * **************Have been changed***************** */

        $primary_insurance_data = $_SESSION['insurance_data'];
        $need_save = $_SESSION['claim_data']['needsave'];

        /*         * *****************Get the patient relationship with insured*************** */
        $tp_patient_id = $patient_data['id'];

        //$db_mypatient = new Application_Model_DbTable_Mypatient();
        //$db = $db_mypatient->getAdapter();
        //$where = $db->quoteInto('patient_id = ?', $tp_patient_id);
        //$mypatient_data = $db_mypatient->fetchRow($where);
        //$tp_provider_id = $mypatient_data['provider_id'];
        $tp_provider_id = $_SESSION['encounter_data']['provider_id'];

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id = ?', $tp_provider_id);
        $provider_data = $db_provider->fetchRow($where);

        $tp_options_id = $provider_data['options_id'];

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('id = ?', $tp_options_id);
        $options_data = $db_options->fetchRow($where);

        $options_relationship = $options_data['default_patient_relationship_to_insured'];
        /*         * *****************Get the patient relationship with insured*************** */


        $insurance_array_data = array();


        $index = 0;
        for ($i = 0; $i < count($insured_array_data); $i++) {
            $tp_insurance_id = $insured_array_data[$i]['insurance_id'];
            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_insurance_id); //
            $insurance = $db_insurance->fetchRow($where);
            $insurance_array_data[$i] = $insurance;
            $index++;
        }



        $data = array();

        //$insured_array_count = $insured_array_data[0]['array_count'];
        $insured_array_count = count($insured_array_data);

        $primary_insured_exist_flag = 0;

        /*         * ******* wait for deal with******* */
        if ($insured_array_count == 0) {
            $data[0]['DOB'] = format($patient_data['DOB'], 1);
            $data[0]['first_name'] = $patient_data['first_name'];
            $data[0]['last_name'] = $patient_data['last_name'];
            $data[0]['sex'] = $patient_data['sex'];
            $data[0]['street_address'] = $patient_data['street_address'];
            $data[0]['zip'] = $patient_data['zip'];
            $data[0]['city'] = $patient_data['city'];
            $data[0]['state'] = $patient_data['state'];
            $data[0]['SSN'] = $patient_data['SSN'];
            $data[0]['SSN'] = maskSSN($data[0]['SSN']); //mask SSN, by haoqiang

            /*             * ****************************Change the phone format By Yu Lang*************************** */
            $data[0]['second_phone_number'] = phone($patient_data['second_phone_number']);
            $data[0]['phone_number'] = phone($patient_data['phone_number']);
            $data[0]['array_count'] = 1;
            /*             * ****************************Change the phone format By Yu lang*************************** */
        }
        /*         * ******* wait for deal with******* */

        for ($i = 0; $i < $insured_array_count; $i++) {
            $insured_data = $insured_array_data[$i];
            $insurance_data = $insurance_array_data[$i];
            $insurance_id = $insurance_data['id'];
            $insured_id = $insured_data['id'];

            if ($insured_data != null) {

                $tp_flag_for_patient_info = false;

                if (strtolower($insured_data['relationship_to_patient']) == 'self') {
                    $tp_flag_for_patient_info = true;
                } else if (strtolower($insured_data['relationship_to_patient']) == 'other') {
                    $tp_flag_for_patient_info = false;
                } else if (strtolower($insured_data['relationship_to_patient']) == "" || strtolower($insured_data['relationship_to_patient']) == null) {
                    $tp_flag_for_patient_info = true;
                } else if ($insured_data['relationship_to_patient'] == null || $insured_data['relationship_to_patient'] == '') {
                    if (strtolower($options_relationship) == 'self')
                        $tp_flag_for_patient_info = true;
                }

                if ($insured_data['last_name'] != null && $insured_data['last_name'] != '') {
                    $data[$i]['DOB'] = format($insured_data['DOB'], 1);
                    $data[$i]['last_name'] = $insured_data['last_name'];
                    $data[$i]['first_name'] = $insured_data['first_name'];
                    $data[$i]['SSN'] = ssn($insured_data['SSN']);
                    $data[$i]['SSN'] = maskSSN($data[$i]['SSN']); //mask SSN, by haoqiang

                    $data[$i]['sex'] = $insured_data['sex'];
                    $data[$i]['street_address'] = $insured_data['street_address'];
                    $data[$i]['state'] = $insured_data['state'];
                    $data[$i]['city'] = $insured_data['city'];
                    $data[$i]['zip'] = zip($insured_data['zip']);
                    $data[$i]['phone_number'] = phone($insured_data['phone_number']);
                    $data[$i]['second_phone_number'] = phone($insured_data['second_phone_number']);

                    //Add at the date of 2013-03-05
                    $data[$i]['relationship_to_patient'] = $insured_data['relationship_to_patient'];
                } else if ($tp_flag_for_patient_info) {



                    $data[$i]['DOB'] = format($patient_data['DOB'], 1);
                    $data[$i]['first_name'] = $patient_data['first_name'];
                    $data[$i]['last_name'] = $patient_data['last_name'];
                    $data[$i]['sex'] = $patient_data['sex'];
                    $data[$i]['street_address'] = $patient_data['street_address'];
                    $data[$i]['zip'] = $patient_data['zip'];
                    $data[$i]['city'] = $patient_data['city'];
                    $data[$i]['state'] = $patient_data['state'];
                    $data[$i]['SSN'] = $patient_data['SSN'];
                    $data[$i]['SSN'] = maskSSN($data[$i]['SSN']); //mask SSN, by haoqiang

                    /*                     * ****************************Change the phone format By Yu Lang*************************** */
                    $data[$i]['second_phone_number'] = phone($patient_data['second_phone_number']);
                    $data[$i]['phone_number'] = phone($patient_data['phone_number']);

                    $data[$i]['relationship_to_patient'] = 'self';
                    /*                     * ****************************Change the phone format By Yu lang*************************** */
                } else {
                    $data[$i]['DOB'] = format($insured_data['DOB'], 1);
                    $data[$i]['last_name'] = $insured_data['last_name'];
                    $data[$i]['first_name'] = $insured_data['first_name'];
                    $data[$i]['SSN'] = ssn($insured_data['SSN']);
                    $data[$i]['SSN'] = maskSSN($data[$i]['SSN']); //mask SSN, by haoqiang

                    $data[$i]['sex'] = $insured_data['sex'];
                    $data[$i]['street_address'] = $insured_data['street_address'];
                    $data[$i]['state'] = $insured_data['state'];
                    $data[$i]['city'] = $insured_data['city'];
                    $data[$i]['zip'] = zip($insured_data['zip']);
                    $data[$i]['phone_number'] = phone($insured_data['phone_number']);
                    $data[$i]['second_phone_number'] = phone($insured_data['second_phone_number']);

                    $data[$i]['relationship_to_patient'] = $insured_data['relationship_to_patient'];
                }

                $data[$i]['id'] = $insured_data['id'];
                $data[$i]['other_insured_DOB'] = format($insured_data['other_insured_DOB'], 1);
                $data[$i]['employer_or_school_name'] = $insured_data['employer_or_school_name'];
                $data[$i]['ID_number'] = $insured_data['ID_number'];
                $data[$i]['policy_group_or_FECA_number'] = $insured_data['policy_group_or_FECA_number'];
                $data[$i]['notes'] = $insured_data['notes'];

                //$data[$i]['insured_insurance_type'] = $insured_data['insured_insurance_type'];
                //Add insured_insurance_expiration_date
                $data[$i]['insured_insurance_expiration_date'] = format($insured_data['insured_insurance_expiration_date'], 1);

                $temp_expiration_date = format($insured_data['insured_insurance_expiration_date'], 0);

                $dd = 0;
                if ($temp_expiration_date != null && $temp_expiration_date != "") {
                    $today_date = date('Ymd');
                    $tmp_expiration_date = date('Ymd', strtotime($insured_data['insured_insurance_expiration_date']));
                    $today_date = (int) $today_date;
                    $tmp_expiration_date = (int) $tmp_expiration_date;
                    if ($tmp_expiration_date < $today_date) {
                        $dd = 0;
                        $data[$i]['insured_insurance_type'] = 'expired';
                    }
                }

                if ($data[$i]['insured_insurance_type'] == 'primary')
                    $primary_insured_exist_flag = 1;

                $data[$i]['insured_name'] = $data[$i]['last_name'] . ', ' . $data[$i]['first_name'];
                $data[$i]['insured_order'] = $i + 1;

                /*                 * ***************To change insured_data By Yu Lang****************** */
                //$data['insurance_type'] = $insured_data['insurance_type'];          
                $insurance_type_upper = strtoupper($insurance_data['insurance_type']);
                $data[$i]['insurance_type'] = $insurance_type_upper;
                /*                 * ***************To change insured_data By Yu Lang****************** */
                $data[$i]['array_count'] = $insured_array_count;
            }
            else {
                if (strtolower($patient_data['relationship_to_insured']) == 'self') {
                    $DOB = format($patient_data['DOB'], 1);
                    $data[$i] = array('last_name' => $patient_data['last_name'], 'first_name' => $patient_data['first_name'], 'DOB' => $DOB, 'sex' => $patient_data['sex'], 'SSN' => $patient_data['SSN'],
                        '   street_address' => $patient_data['street_address'], 'state' => $patient_data['state'], 'city' => $patient_data['city'], 'zip' => $patient_data['zip'],
                        '   phone_number' => $patient_data['phone_number'], 'second_phone_number' => $patient_data['second_phone_number'], 'other_insurance_id' => $other_insurance_id);
                }
            }

            if ($insurance_id != null) {
                foreach (get_ui2insurance() as $db_seg => $ui_seg) {
                    if ($db_seg == "fax_number" || $db_seg == "phone_number") {
                        $data[$i][$ui_seg] = phone($insurance_data[$db_seg]);
                    } else {
                        $data[$i][$ui_seg] = $insurance_data[$db_seg];
                    }
                }
            } else if ($insured_id != null) {
                $db_insured = new Application_Model_DbTable_Insured();
                $db = $db_insured->getAdapter();
                $where = $db->quoteInto('id = ?', $insured_id); //

                $insured = $db_insured->fetchRow($where);
                $insurance_id = $insured['insurance_id'];
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db = $db_insurance->getAdapter();
                $where = $db->quoteInto('id = ?', $insurance_id);
                $insurance_data = $db_insurance->fetchRow($where);
                foreach (get_ui2insurance() as $db_seg => $ui_seg) {
                    $data[$i][$ui_seg] = $insurance_data[$db_seg];
                }
            }
        }

        // $this->view->arraycount = $insured_array_count;
        //$_SESSION['insured_array_data'] = $data;
        if ($insured_array_count != 0) {
            $type_sort = array();
            //$sort_data =array('primary', 'secondary','tertiary','expired');
            for ($i = 0; $i < $insured_array_count; $i++) {
                if ($data[$i]['insured_insurance_type'] == 'expired') {
                    $data[$i]['insured_insurance_type'] = 'zexpired';
                }
                if ($data[$i]['insured_insurance_type'] == 'other') {
                    $data[$i]['insured_insurance_type'] = 'yother';
                }
                //$type_sort[$i] = $data[$i]['insured_insurance_type'];
                $type_sort[$i] = $data[$i]['id'];
            }

            array_multisort($type_sort, SORT_ASC, $data);

            for ($i = 0; $i < $insured_array_count; $i++) {
                if ($data[$i]['insured_insurance_type'] == 'zexpired') {
                    $data[$i]['insured_insurance_type'] = 'expired';
                }
                if ($data[$i]['insured_insurance_type'] == 'yother') {
                    $data[$i]['insured_insurance_type'] = 'other';
                }
            }
        }

        for ($i = 0; $i < $insured_array_count; $i++) {
            $data[$i]['primary_insured_exist_flag'] = $primary_insured_exist_flag;
        }


        $dd = 0;
        // $this->insuredcount
        $data['needsave'] = $need_save;
        $json = Zend_Json::encode($data);
        echo $json;
        //$this->_redirect('/biller/claims/insurance');
    }

    function newuploadAction() {
        if (!$this->getRequest()->isPost()) {
            $cur_service_info = array();
            $facility_id = '';
            session_start();
            if ($_SESSION['tmp']['cur_service_info'] == null) {
                $cur_service_info = $this->get_cur_service_info('encounter');
                $facility_id = $_SESSION['encounter_data']['facility_id'];
            } else {
                $cur_service_info = $_SESSION['tmp']['cur_service_info'];
                $facility_id = $cur_service_info['facility_id'];
            }
            $this->view->cur_service_info = $cur_service_info;
        }
        if ($this->getRequest()->isPost()) {
            $file_type = $this->getRequest()->getPost('file_type');
            $doc_desc = $this->getRequest()->getPost('doc_desc');
            $new_description = $this->getRequest()->getPost('new_description');
            $doc_desc_source = $this->getRequest()->getPost('doc_desc_source');
            $new_description_source = $this->getRequest()->getPost('new_description_source');

            $dir = $this->getRequest()->getPost('dir');

            $adapter = new Zend_File_Transfer_Adapter_Http();
            if ($adapter->isUploaded()) {
                $id = '';
                $type;
                session_start();
                $en_data = $_SESSION['encounter_data'];
                $provider_id = $en_data['provider_id'];
                $billingcompany_id = $this->billingcompany_id;
                $claim_id = $_SESSION['claim_data']['id'];
                $patient_id = $_SESSION['patient_data']['id'];

                if ($file_type == '' || $doc_desc == '') {
                    return;
                }
                if ($file_type == 'Patient') {
                    $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/patient/' . $patient_id;
                }
                if ($file_type == 'Claim') {
                    $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $claim_id;
                }
                $today = date("Y-m-d H:i:s");
                $date = explode(' ', $today);
                $time0 = explode('-', $date[0]);
                $time1 = explode(':', $date[1]);
                $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                if ($doc_desc == 'selfdefinition' && $doc_desc_source == 'selfdefinition') {
                    $file_name = $time . '-' . $new_description . '-' . $new_description_source . '-' . $user_name;
                }
                if ($doc_desc != 'selfdefinition' && $doc_desc_source != 'selfdefinition') {
                    $file_name = $time . '-' . $doc_desc . '-' . $doc_desc_source . '-' . $user_name;
                }
                if ($doc_desc == 'selfdefinition' && $doc_desc_source != 'selfdefinition') {
                    $file_name = $time . '-' . $new_description . '-' . $doc_desc_source . '-' . $user_name;
                }
                if ($doc_desc != 'selfdefinition' && $doc_desc_source == 'selfdefinition') {
                    $file_name = $time . '-' . $doc_desc . '-' . $new_description_source . '-' . $user_name;
                }
                $old_filename = $adapter->getFileName();
                $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                $folder = new Zend_Search_Lucene_Storage_Directory_Filesystem($dir);
//  $file_name = $adapter->getFileName(null, false);
                $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                $adapter->setDestination($dir);
// $adapter->addValidator('Extension', FALSE/* , array('jpg', 'gif', 'png', 'jpeg', 'pdf') */);
                if (!$adapter->receive()) {
                    $messages = $adapter->getMessages();
                    echo implode("n", $messages);
                } else {
                    
                }
            }
            $this->_redirect('/biller/claims/documents');
        }
    }

    function uploadAction() {
        if (!$this->getRequest()->isPost()) {
            $pageno = $this->getRequest()->getParam('pageno');
            $cur_service_info = array();
            $facility_id = '';
            session_start();
            if ($_SESSION['tmp']['cur_service_info'] == null) {
                $cur_service_info = $this->get_cur_service_info('encounter');
                $facility_id = $_SESSION['encounter_data']['facility_id'];
            } else {
                $cur_service_info = $_SESSION['tmp']['cur_service_info'];
                $facility_id = $cur_service_info['facility_id'];
            }
            $this->view->pageno = $pageno;
            $this->view->cur_service_info = $cur_service_info;

            $file_type_list = array();
            $combo = array();
            if ($pageno == 3)
                if ($facility_id > 0)
                    $combo = get_service_doc_pages($facility_id);
            $file_type_list = get_file_list($pageno, $combo);
            $this->view->file_type_list = $file_type_list;
        }
        if ($this->getRequest()->isPost()) {
            $ui = array("patient", "insurance", "service", "claim");
            $file_type = $this->getRequest()->getPost('file_type');
            $pageno = $this->getRequest()->getPost('pageno');
            $dir = $this->getRequest()->getPost('dir');
            $adapter = new Zend_File_Transfer_Adapter_Http();
            if ($adapter->isUploaded()) {
                $subfolders = get_subfolders();
                $subfolder = $subfolders[$pageno - 1];
                $id = '';
//last new claim
                session_start();
                if ($_SESSION['tmp']['cur_service_info'] != null)
                    $id = $_SESSION['tmp']['cur_service_info']['encounter_id'];
                else
                    $id = $_SESSION[$subfolder . '_data']['id'];
                $dir = $this->sysdoc_path . '/document/' . $subfolder . '/' . $id;
                $file_name = $file_type;
                $old_filename = $adapter->getFileName();
                $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));

                if ($file_type == 'one-off') {
                    $file_name = $this->getRequest()->getPost('one_off_name');
                    $dir = $dir . '/one-off';
                }
                $folder = new Zend_Search_Lucene_Storage_Directory_Filesystem($dir);
//  $file_name = $adapter->getFileName(null, false);
                $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                $adapter->setDestination($dir);
// $adapter->addValidator('Extension', FALSE/* , array('jpg', 'gif', 'png', 'jpeg', 'pdf') */);
                if (!$adapter->receive()) {
                    $messages = $adapter->getMessages();
                    echo implode("n", $messages);
                } else {
                    if ($file_type == 'ComboSheets') {
                        $sourcefile = $dir . '/ComboSheets.pdf';
                        $page_count = get_page_count($sourcefile);
//split file
                        $split_options = array();
                        if ($pageno == 3) {
                            $facility_id = '';
                            session_start();
                            if ($_SESSION['tmp']['cur_service_info'] == null)
                                $facility_id = $_SESSION['encounter_data']['facility_id'];
                            else
                                $facility_id = $_SESSION['tmp']['cur_service_info']['facility_id'];
                            $combo = array();
                            if ($facility_id > 0)
                                $combo = get_service_doc_pages($facility_id);

                            for ($i = 0; $i < count($combo) - 1; $i++)
                                array_push($split_options, array($combo[$i] . '.pdf', $i + 1, '1'));
                            array_push($split_options, array($combo[count($combo) - 1] . '.pdf',
                                count($combo)));
                        }
                        if ($pageno == 4) {
                            array_push($split_options, array('Payment.pdf', 1, 1));
                            if ($page_count == 1)
                                array_push($split_options, array('EOB.pdf', 1, 1));
                            else
                                array_push($split_options, array('EOB.pdf', 2));
                        }
                        splitpdf($dir . '/ComboSheets.pdf', $dir, $split_options);
//delete combosheets                        
                    }
                }
                //Add log
                $today = date("Y-m-d H:i:s");
                $claim_id = $_SESSION['encounter_data']['claim_id'];
                $interactionlogs_data['claim_id'] = $claim_id;
                $interactionlogs_data['date_and_time'] = $today;
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                $interactionlogs_data['log'] = $user_name . ": Document upload: " . ucfirst($ui[$pageno - 1]) . ": " . $file_type . ":" . $file_name;
                mysql_insert('interactionlog', $interactionlogs_data);
            }
        }
    }

    function iseobfileexistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $insurance_name = null;
        $insurance_id = null;

        session_start();
        $billingcompany_data = $_SESSION['billingcompany_data'];
        $document_feature = $billingcompany_data['document_feature'];
        $insured_data = $_SESSION['insured_data'];
        $encounterinsured = $_SESSION['encounterinsured_data'];
        $insured_id = $encounterinsured[0]['insured_id'];
//        for($i=0;$i<count($insured_data);$i++){
//           if($insured_data[$i]['id']==$insured_id) {
//               $insurance_name= $insured_data[$i]['insurance_name'];
//               $insurance_id=$insured_data[$i]['insurance_id'];
//           }
//        }
//        if($insurance_name==null) {    
//            $db_insurance = new Application_Model_DbTable_Insurance();
//            $db = $db_insurance->getAdapter();
//            $where = $db->quoteInto('id=?', $insurance_id);
//            $insurance_data = $db_insurance->fetchRow($where)->toArray();
//
//            $insurance_name=$insurance_data['insurance_name'];
//        }
        //$dir = $this->sysdoc_path . '/document/claim/' . $_SESSION['claim_data']['id'];
        $dir = $this->sysdoc_path . '/' . $_SESSION['billingcompany_data']['id'] . '/' . $_SESSION['encounter_data']['provider_id'] . '/claim/' . $_SESSION['claim_data']['id'];
        if (sizeof(glob($dir . '/*EOB*.*'))) {
            $eobfile['exist'] = 'true';
        } else
            $eobfile['exist'] = 'false';
        $eobfile['document_feature'] = $document_feature;
//        $eobfile['insurance_name']=$insurance_name;
        $json = Zend_Json::encode($eobfile);
        echo $json;
    }

    public function timeoutAction() {
        $this->_helper->viewRenderer->setNoRender();
        session_start();
        $test = $_SESSION['time_out'];
        $session_life = time() - $_SESSION['time_out'];
        $time_out = "no";
        $test_temp = $this->get_inactive();
        if ($this->get_inactive() > 0) {
            if ($session_life > $this->get_inactive()) {
                $time_out = "yes";
                unset($_SESSION['time_out']);
            }
        }


        if ($_SESSION['time_out'] == null) {
            $time_out = "yes";
        }

        if ($time_out == "no") {
            $_SESSION['time_out'] = time();
        }
        echo $time_out;
    }

    /*     * *Add patient alert for the new claim** */

    function patientalertAction() {
        $this->_helper->viewRenderer->setNoRender();
        $patient_id = $this->getRequest()->getParam('patientid');
        $db_patient = new Application_Model_DbTable_Patient();
        $db = $db_patient->getAdapter();
        $where = $db->quoteInto('id=?', $patient_id);
        $patient_data = $db_patient->fetchRow($where);

        session_start();
        $prompt_data = array();
        $prompt_data['first_severe_flag'] = $_SESSION['first_severe_flag'];
        $prompt_data['new_claim_flag'] = $_SESSION['new_claim_flag'];
        $prompt_data['patient_alert_info'] = $patient_data['alert'];
        $prompt_data['patient_name'] = $patient_data['last_name'] . ', ' . $patient_data['first_name'];
        $_SESSION['first_severe_flag'] = 2;
        //$_SESSION['new_claim_flag'] = 0; 
        $cd = 0;
        $json = Zend_Json::encode($prompt_data);
        echo $json;
    }

    /*     * *Add patient alert for the new claim** */

    /*     * *Add insurance alert for the new claim** */

    function newclaiminsurancealertAction() {
        //$fortest = "ds";


        $this->_helper->viewRenderer->setNoRender();
        $insurance_id = $this->getRequest()->getParam('insuranceid');
        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('id=?', $insurance_id);
        $insurance_data = $db_insurance->fetchRow($where);

        session_start();
        $prompt_data = array();
        $prompt_data['new_claim_flag'] = $_SESSION['new_claim_flag'];
        $prompt_data['insurance_alert_info'] = $insurance_data['alert'];
        $prompt_data['insurance_name'] = $insurance_data['insurance_name'];
        //$_SESSION['new_claim_flag'] = 0; 
        $cd = 0;
        $json = Zend_Json::encode($prompt_data);
        echo $json;
    }

    /*     * *Add insurance alert for the new claim** */





    /*     * *Add insurance alert for the new claim** */

    function insurancealertAction() {
        $this->_helper->viewRenderer->setNoRender();
        $other_insurance_id = $_POST['other_insurance_id'];

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
//$where = $db->quoteInto('last_name=? AND first_name=? AND DOB=?',$last_name,$first_name,$DOB);
//$select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
        $where = $db->quoteInto('id = ?', $other_insurance_id); //
//$order = "insurance_name DESC";
        $insurance_data = $db_insurance->fetchAll($where);

        $count = $insurance_data->count();

        if ($count > 0) {
            $data = array();
            foreach ($insurance_data as $row) {
                $data = array('insurance_id' => $row->id, 'insurance_name' => $row->insurance_name, 'alert' => $row->alert);
            }
            $json = Zend_Json::encode($data);
            echo $json;
        } else {
            return;
        }
    }

    /*     * *Add insurance alert for the new claim** */

    function checkdobandsecAction() {
        $this->_helper->viewRenderer->setNoRender();

        session_start();
        $insured_data = $_SESSION['insured_data'];
        $encounterinsured_data = $_SESSION['encounterinsured_data'];

        $prompt_data = array();
        //$dir = $this->sysdoc_path . '/document/claim/' . $_SESSION['claim_data']['id'];
        $dir = $this->sysdoc_path . '/' . $_SESSION['billingcompany_data']['id'] . '/' . $_SESSION['encounter_data']['provider_id'] . '/claim/' . $_SESSION['claim_data']['id'];
        if (sizeof(glob($dir . '/*EOB*.*'))) {
            $prompt_data['exist'] = 'true';
        } else
            $prompt_data['exist'] = 'false';


        /*
          if ($_SESSION['insured_data']['is_there_another_plan'] == '0') {
          $prompt_data['otherplan'] = 'false';
          }
          else
          $prompt_data['otherplan'] = 'true';

          if ($_SESSION['insured_data']['other_insured_policy_or_group_number'] == '' || $_SESSION['insured_data']['other_insured_policy_or_group_number'] == null) {
          $prompt_data['policynumber'] = 'false';
          }
          else
          $prompt_data['policynumber'] = 'true';



          if ($_SESSION['insured_data']['other_insurance_id'] == "89" || $_SESSION['insured_data']['other_insurance_id'] == null) {
          $prompt_data['insuranceplan'] = 'false';
          }
          else
          $prompt_data['insuranceplan'] = 'true';

         */
        $prompt_data['secondaryinfo'] = 'false';
        foreach ($encounterinsured_data as $row) {
            if ($row['type'] == 'secondary')
                $prompt_data['secondaryinfo'] = 'true';
        }

        $json = Zend_Json::encode($prompt_data);

        echo $json;
    }

    function accessbuttonstatusAction() {
        $this->_helper->viewRenderer->setNoRender();
        $pageno = $this->getRequest()->getParam('pageno');
        $file_paths = array();
        $one_off_paths = array();
        $subfolders = get_subfolders();
        $subfolder = $subfolders[$pageno - 1];
        session_start();
//one-off paths
        $subfolder_dir = $this->sysdoc_path . '/document/' . $subfolder . '/' . $_SESSION[$subfolder . '_data']['id'];
        if (!is_dir($subfolder_dir))
            return;
        $one_off_dir = $subfolder_dir . '/one-off';
        if (is_dir($one_off_dir)) {
            foreach (glob($one_off_dir . '/*.*') as $filename) {
// $tmp = basename($filename);
// array_push($one_off_paths, substr($tmp, 0, strripos($tmp, '.')));
                array_push($one_off_paths, $filename);
            }
        }
        if ($pageno == 2) {
            if (file_exists($subfolder_dir . '/insurance.pdf')) {
                $file_paths['Insurance_path'] = $subfolder_dir . '/Insurance.pdf';
            }
        }
        if ($pageno == 3) {
            $combo = array();
            $facility_id = $_SESSION['encounter_data']['facility_id'];
            if ($facility_id > 0)
                $combo = get_service_doc_pages($facility_id);
            $combo_path = array();
            $combo_btn = array();
            for ($i = 0; $i < count($combo); $i++) {
                array_push($combo_btn, $combo[$i]);
                $aa = $subfolder_dir . '/' . $combo[$i] . '.pdf';
                if (file_exists($subfolder_dir . '/' . $combo[$i] . '.pdf'))
                    array_push($combo_path, $subfolder_dir . '/' . $combo[$i] . '.pdf');
                else
                    array_push($combo_path, '');
            }
            $file_paths['combo_btn'] = $combo_btn;
            $file_paths['combo_path'] = $combo_path;
        }
        if ($pageno == 4) {
            $files = array('CMS1500_path' => 'CMS1500.pdf', 'Agreement_path' => 'Agreement.pdf',
                'EOB_path' => 'EOB.pdf', 'Payment_path' => 'Payment.pdf',
                'Statement_1_path' => 'StatementI.pdf', 'Statement_2_path' => 'StatementII.pdf', 'Statement_3_path' => 'StatementIII.pdf',);
            foreach ($files as $path => $file) {
                if (file_exists($subfolder_dir . '/' . $file))
                    $file_paths[$path] = $subfolder_dir . '/' . $file;
            }
        }
        $file_paths['one_off_paths'] = $one_off_paths;
        $json = Zend_Json::encode($file_paths);
        echo $json;
    }

    /*     * *******************used for db tools********************* */

    function dbtoolsAction() {
        if (!$this->getRequest()->isPost()) {

            session_start();

            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('insured');
            $select->joinLeft('patient', 'insured.id = patient.insured_id ', 'id as patient_id');
            $patientinsuredList = $db->fetchAll($select);

            $dd = 0;

            foreach ($patientinsuredList as $row) {
                if ($row['patient_id'] == '' || $row['patient_id'] == null)
                    continue;
                $last_patientinsuredid = $this->dbtool_dealwithpatientinsured($row['patient_id'], $row['id']);

                $dd = 0;

                if ($row['is_there_another_plan'] == '1' && $row['other_insurance_id'] != '' && $row['other_insurance_id'] != '0' && $row['other_insurance_id'] != null) {
                    $last_insured_id = $this->dbtool_dealwithinsured($row);
                    $last_patientinsuredid = $this->dbtool_dealwithpatientinsured($row['patient_id'], $last_insured_id);

                    $dd = 0;
                } else {
                    $db = Zend_Registry::get('dbAdapter');
                    $data = array('insured_insurance_type' => 'primary');
                    $result = $db->update('insured', $data, 'id = ' . $row['id']);
                }
            }

            $dd = 0;

            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('patient', 'id');
            $select->join('encounter', 'encounter.patient_id = patient.id ', 'id as encounter_id');
            $select->join('patientinsured', 'patient.id = patientinsured.patient_id ', 'insured_id');
            $select->join('insured', 'insured.id = patientinsured.insured_id ', 'insured_insurance_type');
            $encounterinsuredList = $db->fetchAll($select);

            $dd = 0;

            foreach ($encounterinsuredList as $row) {
                if ($row['encounter_id'] == '' || $row['encounter_id'] == null)
                    continue;
                if ($row['insured_id'] == '' || $row['insured_id'] == null)
                    continue;
                if ($row['insured_insurance_type'] == '' || $row['insured_insurance_type'] == null)
                    continue;
                $last_insert_id = $this->dbtool_encounterinsured($row);
            }

            $dd = 0;
        }
    }

    function dbtool_encounterinsured($row) {
        $db = Zend_Registry::get('dbAdapter');
        $data = array('encounter_id' => $row['encounter_id'], 'insured_id' => $row['insured_id'], 'type' => $row['insured_insurance_type']);
        $db->insert('encounterinsured', $data);
        $last_insert_id = $db->lastInsertId();
        return $last_insert_id;
    }

    function dbtool_dealwithpatientinsured($patient_id, $insured_id) {
        $db = Zend_Registry::get('dbAdapter');
        $data = array('patient_id' => $patient_id, 'insured_id' => $insured_id);
        $db->insert('patientinsured', $data);
        $last_insert_id = $db->lastInsertId();
        return $last_insert_id;
    }

    function dbtool_dealwithinsured($row) {
        $db = Zend_Registry::get('dbAdapter');
        $data = array(
            'last_name' => $row['last_name'], 'first_name' => $row['first_name'], 'DOB' => $row['DOB'],
            'street_address' => $row['street_address'], 'city' => $row['city'], 'state' => $row['state'], 'zip' => $row['zip'],
            'phone_number' => $row['phone_number'], 'second_phone_number' => $row['second_phone_number'], 'sex' => $row['sex'],
            'SSN' => $row['SSN'], 'insured_insurance_type' => 'secondary', 'ID_number' => $row['ID_number'],
            'policy_group_or_FECA_number' => $row['policy_group_or_FECA_number'],
            'employer_or_school_name' => $row['employer_or_school_name'], 'plan_or_program_name' => $row['plan_or_program_name'],
            'is_there_another_plan' => $row['is_there_another_plan'], 'signature' => $row['signature'], 'notes' => $row['notes'],
            'other_insured_last_name' => $row['other_insured_last_name'], 'other_insured_first_name' => $row['other_insured_first_name'],
            'other_insured_policy_or_group_number' => $row['other_insured_policy_or_group_number'],
            'other_insured_DOB' => $row['other_insured_DOB'], 'other_insured_sex' => $row['other_insured_sex'],
            'other_insured_employer_name' => $row['other_insured_employer_name'], 'other_insurance_name_or_program_name' => $row['other_insurance_name_or_program_name'],
            'file_path_to_ID_card' => $row['file_path_to_ID_card'],
            'relationship_to_patient' => $row['relationship_to_patient'], 'insurance_id' => $row['other_insurance_id'], 'other_insurance_id' => '1'
        );

        $db->insert('insured', $data);
        $last_insert_id = $db->lastInsertId();

        $db = Zend_Registry::get('dbAdapter');
        $data = array('insured_insurance_type' => 'primary', 'other_insurance_id' => '1');
        $result = $db->update('insured', $data, 'id = ' . $row['id']);

        return $last_insert_id;
    }

    /*     * *******************used for db tools********************* */

    function save_data() {                    //0223

        $ret = $this->validation();

        if (count($ret) > 0) {
            foreach ($ret as $key => $value) {
                $this->_redirect('/biller/claims/' . $key . '/nullcheck/' . $value);
            }
        }

        $dd = 0;
        $flag_changed = 0;
        session_start();
        if ($_SESSION['insured_data_change_flag'] == 1) {
            $flag_changed = 1;
            $_SESSION['insured_data_change_flag'] = 0;
        }

        $tmp_diff_patient_data = $this->get_diff_session('patient');
        $patient_data = session2DB_patient($this->get_diff_session('patient'));
        if ($patient_data['SSN']) {
            $patient_data['SSN'] = encodeSSN($patient_data['SSN']);
        }
        $statement_data = session2DB_statement($this->get_diff_session('statement'));

        /*         * ****************************Change*********************************** */
//        $insured_data = session2DB_insured($this->get_diff_session('insured'));       
        //$insured_data = session2DB_insured($this->get_diff_insured_session());

        $insured_data = $_SESSION['insured_data'];

        $dd = 0;

        $count_insured_data = count($insured_data);


        for ($i = 0; $i < $count_insured_data; $i++) {
            //$insured_data[$i] = $this->format_insured_session($insured_data[$i]);
            $field_list_filter = array('SSN', 'zip', 'phone_number', 'second_phone_number');
            foreach ($field_list_filter as $field) {
                if ($insured_data[$i][$field] != null)
                    $insured_data[$i][$field] = str_replace(array("-", "(", ")"), array("", "", ""), $insured_data[$i][$field]);
            }
            if ($insured_data[$i]['DOB'] != null)
                $insured_data[$i]['DOB'] = format($insured_data[$i]['DOB'], 0);
            //Add the insured_insurance_expiration_date
            if ($insured_data[$i]['insured_insurance_expiration_date'] != null)
                $insured_data[$i]['insured_insurance_expiration_date'] = format($insured_data[$i]['insured_insurance_expiration_date'], 0);
        }
        /*         * ****************************Change*********************************** */

        $insurance_data = $this->get_diff_session('insurance');
        $encounter_data = session2DB_encounter($this->get_diff_session('encounter'));  //0223
        $claim_data = session2DB_claim($this->get_diff_session('claim'));
        $followups_data = session2DB_followups($this->get_diff_session('followups'));
        $insurancepayments_data = $this->get_diff_session2('insurancepayments');
        $patientpayments_data = $this->get_diff_session2('patientpayments');
        $billeradjustments_data = $this->get_diff_session2('billeradjustments');
//        $interactionlogs_data = $this->get_diff_session2('interactionlogs');
        $options_data = $_SESSION['options'];
        $assignedclaims = $_SESSION['assignedclaims_data'];
        $assignedclaims_bk = $_SESSION['assignedclaims_data_BK'];

        $patient_id = $patient_data['id'];


        $dd = 0;

        /*         * ****************************Change*********************************** */
        //$insured_id = $insured_data['id'];
        $encounterinsured_data = $_SESSION['encounterinsured_data'];

        /*         * ****************************Change*********************************** */

        $insurance_id = $insurance_data['id'];
        $encounter_id = $encounter_data['id'];
        $claim_id = $claim_data['id'];
        $followups_id = $followups_data['id'];

        //zw $db_mypatient = new Application_Model_DbTable_Mypatient();
        $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();

        $dd = 0;

        //update patient
        if ($patient_id != null && (count($patient_data) != 1)) {
            $rows_affected = mysql_update_by_id('patient', $patient_data, $patient_id);
            $flag_changed = 1;
//Save Statements
        } elseif ($patient_id == null || $patient_id == "") {
            $dd = 0;
            $tmp_tmp = $insured_id;
            $patient_data['insured_id'] = 1;
            $patient_data['account_number'] = getmrn($this->get_billingcompany_id(), 1);
            $patient_id = mysql_insert('patient', $patient_data);
            //$flag_changed = 1;
            $dd = 0;
        }


        $dd = 0;
//update insured
        //if insured_data only have id,not update.
        /*         * ****************************Change*********************************** */
        for ($i = 0; $i < $count_insured_data; $i++) {
            $is_data = $insured_data[$i];

            if ($insured_data[$i]['id'] != null && (count($insured_data[$i]) != 1) && is_numeric($insured_data[$i]['id'])) {
                //try fix bug
                if ($insured_data[$i]['relationship_to_patient'] == null || $insured_data[$i]['relationship_to_patient'] == '')
                    $insured_data[$i]['relationship_to_patient'] = 'self';

                $db = Zend_Registry::get('dbAdapter');
                //encryption SSN in insured table
                if ($insured_data[$i]['SSN']) {
                    $insured_data[$i]['SSN'] = encodeSSN($insured_data[$i]['SSN']);
                }
                $data = array('last_name' => $insured_data[$i]['last_name'],
                    'first_name' => $insured_data[$i]['first_name'],
                    'DOB' => $insured_data[$i]['DOB'],
                    'street_address' => $insured_data[$i]['street_address'],
                    'city' => $insured_data[$i]['city'],
                    'state' => $insured_data[$i]['state'],
                    'zip' => $insured_data[$i]['zip'],
                    'phone_number' => $insured_data[$i]['phone_number'],
                    'second_phone_number' => $insured_data[$i]['second_phone_number'],
                    'sex' => $insured_data[$i]['sex'],
                    'SSN' => $insured_data[$i]['SSN'],
                    'insured_insurance_type' => $insured_data[$i]['insured_insurance_type'],
                    //try fix bug
                    'relationship_to_patient' => $insured_data[$i]['relationship_to_patient'],
                    //Add insured_insurance_expiration_date
                    'insured_insurance_expiration_date' => $insured_data[$i]['insured_insurance_expiration_date'],
                    'ID_number' => $insured_data[$i]['ID_number'],
                    'policy_group_or_FECA_number' => $insured_data[$i]['policy_group_or_FECA_number'],
                    'employer_or_school_name' => $insured_data[$i]['employer_or_school_name'],
                    'insurance_id' => $insured_data[$i]['insurance_id'],
                    'notes' => $insured_data[$i]['notes']
                );

                $dd = 0;
                $result = $db->update('insured', $data, 'id = ' . $insured_data[$i]['id']);
                //insured always be updated
                //$flag_changed = 1;
                $dd = 0;
            } elseif ($insured_data[$i]['id'] == null || !is_numeric($insured_data[$i]['id'])) {
                $tp_insure_data = $insured_data[$i];

                //try fix bug
                if ($insured_data[$i]['relationship_to_patient'] == null || $insured_data[$i]['relationship_to_patient'] == '')
                    $insured_data[$i]['relationship_to_patient'] = 'self';

                $db = Zend_Registry::get('dbAdapter');
                //encryption SSN in insured table
                if ($insured_data[$i]['SSN']) {
                    $insured_data[$i]['SSN'] = encodeSSN($insured_data[$i]['SSN']);
                }
                $data = array(
                    'last_name' => $insured_data[$i]['last_name'],
                    'first_name' => $insured_data[$i]['first_name'],
                    'DOB' => $insured_data[$i]['DOB'],
                    'street_address' => $insured_data[$i]['street_address'],
                    'city' => $insured_data[$i]['city'],
                    'state' => $insured_data[$i]['state'],
                    'zip' => $insured_data[$i]['zip'],
                    'phone_number' => $insured_data[$i]['phone_number'],
                    'second_phone_number' => $insured_data[$i]['second_phone_number'],
                    'sex' => $insured_data[$i]['sex'],
                    'SSN' => $insured_data[$i]['SSN'],
                    'insured_insurance_type' => $insured_data[$i]['insured_insurance_type'],
                    //try fix bug
                    'relationship_to_patient' => $insured_data[$i]['relationship_to_patient'],
                    //Add insured_insurance_expiration_date
                    'insured_insurance_expiration_date' => $insured_data[$i]['insured_insurance_expiration_date'],
                    'ID_number' => $insured_data[$i]['ID_number'],
                    'policy_group_or_FECA_number' => $insured_data[$i]['policy_group_or_FECA_number'],
                    'employer_or_school_name' => $insured_data[$i]['employer_or_school_name'],
                    'insurance_id' => $insured_data[$i]['insurance_id'],
                    'notes' => $insured_data[$i]['notes'],
                    /*                     * **************************************************** */
                    'other_insurance_id' => '1',
                    'signature' => 'Signature on file',
                );

                $db->insert('insured', $data);
                //$flag_changed = 1;
                $last_insert_id = $db->lastInsertId();
                //Fix the bug                
                for ($k = 0; $k < count($encounterinsured_data); $k++) {
                    if ($insured_data[$i]['id'] == $encounterinsured_data[$k]['insured_id'])
                        $encounterinsured_data[$k]['insured_id'] = $last_insert_id;
                }

                $insured_data[$i]['id'] = $last_insert_id;

                $db = Zend_Registry::get('dbAdapter');
                $data = array('patient_id' => $patient_id,
                    'insured_id' => $last_insert_id);
                $db->insert('patientinsured', $data);
                $dd = 0;
            }
        }

//update encounter claim followup
        if ($encounter_id != null && $encounter_id != "") {      //0223

            $dd = 0;
//Statement Trigger
            switch ($claim_data['statement_status']) {
                case 'stmt_ready_payment_sent_to_patient' :
                    $trigger = '1';
                    break;
                case 'stmt_ready_coinsurance' :
                    $trigger = 2;
                    break;
                case 'stmt_ready_deductible' :
                    $trigger = 3;
                    break;
                case 'stmt_ready_selfpay' :
                    $trigger = 4;
                    break;
                default:
                    $trigger = 0;
                    break;
            }
            $db_statement = new Application_Model_DbTable_Statement();
            $db = $db_statement->getAdapter();
            //$where = $db->quoteInto('encounter_id = ?', $encounter_id).$db -> quoteInto(' AND statement_type = 1').$db -> quoteInto(' AND statement.trigger = ?', $trigger);            
            $where = $db->quoteInto('encounter_id = ?', $encounter_id) . $db->quoteInto(' AND statement.date is null');
            $statement_db = $db_statement->fetchRow($where);

            $db_claim = new Application_Model_DbTable_Claim();
            $db = $db_claim->getAdapter();
            $where = $db->quoteInto('id = ?', $claim_id);
            $claim_db = $db_claim->fetchRow($where);
//1.Send to patient
            if ($options_data['SI_send_to_patient'] == '1' && $claim_data['statement_status'] == 'stmt_ready_payment_sent_to_patient' && $statement_db == null) {
                $statement['statement_type'] = '1';
                $statement['trigger'] = '1';
                $statement['remark'] = $options_data['statement_I_1'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
                $flag_changed = 1;
            }
//2.Co-Insurance
            if ($options_data['SI_co-insurance'] == '1' && $claim_data['statement_status'] == 'stmt_ready_coinsurance' && $statement_db == null) {
                $statement['statement_type'] = '1';
                $statement['trigger'] = '2';
                $statement['remark'] = $options_data['statement_I_2'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
                $flag_changed = 1;
            }
//3.Deductible
            if ($options_data['SI_deductible'] == '1' && $claim_data['statement_status'] == 'stmt_ready_deductible' && $statement_db == null) {
                $statement['statement_type'] = '1';
                $statement['trigger'] = '3';
                $statement['remark'] = $options_data['statement_I_3'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
                $flag_changed = 1;
            }
//4.Self Pay

            if ($options_data['SI_selfpay'] == '1' && $claim_data['statement_status'] == 'stmt_ready_selfpay' && $statement_db == null) {
                $statement['statement_type'] = '1';
                $statement['trigger'] = '4';
                $statement['remark'] = $options_data['statement_I_4'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
                $flag_changed = 1;
            }

            if ($claim_data['statement_status'] == 'stmt_ready_installment' && $statement_db == null) {
                $statement['statement_type'] = '5';
                $statement['trigger'] = $trigger;
                $statement['remark'] = $options_data['statement_V_1'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
                $flag_changed = 1;
            }
//5.Biller manual entry
//6.Statement interval elapse
//update encounter
            if ($encounter_data['start_date_1']) {
                $encounter_data['patient_signature_date'] = $encounter_data['start_date_1'];
                $encounter_data['rendering_provider_signature_date'] = $encounter_data['start_date_1'];
            }
            if (count($encounter_data) != 1) {
                $rows_affected = mysql_update_by_id('encounter', $encounter_data, $encounter_id);  //0223
                $flag_changed = 1;
            }
//update claim
            if (count($claim_data) != 1) {
                /* $data_now = date("Y-m-d H:i:s");
                  $claim_data['update_time'] = $data_now;
                  $user = Zend_Auth::getInstance()->getIdentity();
                  $user_name =  $user->user_name;
                  $claim_data['update_user'] = $user_name; */
                $rows_affected = mysql_update_by_id('claim', $claim_data, $claim_id);
                $flag_changed = 1;
            }

            /*             * **************Add update encounterinsured**************** */
//Add new claim

            if (count($encounterinsured_data) > 0) {
                for ($i = 0; $i < count($encounterinsured_data); $i++) {
                    if ($encounterinsured_data[$i]['change_flag'] == 4)
                        continue;
                    if ($encounterinsured_data[$i]['change_flag'] == 1) {
                        $db = Zend_Registry::get('dbAdapter');
                        $data = array(
                            'insured_id' => $encounterinsured_data[$i]['insured_id'],
                            'type' => $encounterinsured_data[$i]['type'],
                        );
                        $result = $db->update('encounterinsured', $data, 'id = ' . $encounterinsured_data[$i]['id']);
                        $flag_changed = 1;
                    } else if ($encounterinsured_data[$i]['change_flag'] == 2) {
                        $db = Zend_Registry::get('dbAdapter');
                        $data = array('encounter_id' => $encounter_id,
                            'insured_id' => $encounterinsured_data[$i]['insured_id'],
                            'type' => $encounterinsured_data[$i]['type'],);
                        $db->insert('encounterinsured', $data);
                        $flag_changed = 1;
                        $last_insert_id = $db->lastInsertId();
                    } else if ($encounterinsured_data[$i]['change_flag'] == 3) {
                        $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                        $db = $db_encounterinsured->getAdapter();
                        $where = $db->quoteInto('insured_id = ?', $encounterinsured_data[$i]['insured_id']) . $db->quoteInto(' AND encounter_id = ?', $encounterinsured_data[$i]['encounter_id']) . $db->quoteInto(' AND type = ?', $encounterinsured_data[$i]['type']);
                        $tt = $db_encounterinsured->delete($where);
                        $flag_changed = 1;
                    }
                }
            }
            /*             * **************Add update encounterinsured**************** */
//update followpus
            if (count($followups_data) != 1) {
                $rows_affected = mysql_update_by_id('followups', $followups_data, $followups_id);
                $flag_changed = 1;
            }

//Save Statement
            for ($i = 1; $i <= 2; $i++) {
                if ($i == 1) {
                    $statement1['statement_type'] = $statement_data['statement_type' . $i];
                    if ($statement_data['date' . $i] != null && $statement_data['date' . $i] != '')
                        $statement1['date'] = $statement_data['date' . $i];
                    $statement1['trigger'] = $statement_data['trigger' . $i];
                    $statement1['remark'] = $statement_data['remark' . $i];
                    $statement1['encounter_id'] = $encounter_id;
                    if ($statement1['statement_type'] != null && $statement1['statement_type'] != '0') {
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $db_statement->insert($statement1);
                        $flag_changed = 1;
                    }
                } else {
                    if ($statement_data['statement_type' . $i] == '0')
                        $statement2['statement_type'] = $statement_data['statement_type' . $i];
                    if ($statement_data['date' . $i] != null && $statement_data['date' . $i] != '')
                        $statement2['date'] = $statement_data['date' . $i];
                    // $statement2['trigger'] = $statement_data['trigger' . $i];
                    $statement2['remark'] = $statement_data['remark' . $i];
                    $statement_id = $statement_data['statement_id' . $i];
                    $statement['encounter_id'] = $statement_data['encounter_id' . $i];
                    if ($statement_id != null) {
                        $db_statement = new Application_Model_DbTable_Statement();
                        $db = $db_statement->getAdapter();
                        $where = $db->quoteInto('id = ?', $statement_id);
                        if ($statement2['statement_type'] != null || $statement2['remark'] != null)
                            if ($statement2['statement_type'] != '0') {
                                $db_statement->update($statement2, $where);
                                $flag_changed = 1;
                            } else {
                                $db_statement->delete($where);
                                $flag_changed = 1;
                            }
                    }
                }
            }
        } else {
            $dd = 0;
//insert claim
            $claim_data['date_creation'] = date("Y-m-d");
            if ($claim_data['amount_paid'] == null || $claim_data['amount_paid'] == 0.0 || $claim_data['amount_paid'] == 0) {
                $claim_data['amount_paid'] = 0.0;
                $claim_data['balance_due'] = $claim_data['total_charge'];
            }

            $data_now = date("Y-m-d H:i:s");
            $claim_data['update_time'] = $data_now;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $claim_data['update_user'] = $user_name;

            $claim_id = mysql_insert('claim', $claim_data);
            $today = date("Y-m-d H:i:s");
            $interactionlogs_data['claim_id'] = $claim_id;
            $interactionlogs_data['date_and_time'] = $today;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $interactionlogs_data['log'] = $user_name . ": Claim created";
            mysql_insert('interactionlog', $interactionlogs_data);

//insert encounter
            $encounter_data['patient_id'] = $patient_id;
            $encounter_data['claim_id'] = $claim_id;

            $dd = 0;

            $encounter_id = mysql_insert('encounter', $encounter_data);

            $dd = 0;

            $default = array();
            $default['facility'] = $encounter_data['facility_id'];
            $default['provider'] = $encounter_data['provider_id'];
            $default['renderingprovider'] = $encounter_data['renderingprovider_id'];
            $default['referringprovider'] = $encounter_data['referringprovider_id'];
            $default['place'] = $encounter_data['place_of_service_1'];
            setdefault($default);

            $dd = 0;

            /// zw use encounterinsured instead of insured as we decoupled insured from encounter

            for ($i = 0; $i < count($encounterinsured_data); $i++) {
                $dd = 0;
                $db = Zend_Registry::get('dbAdapter');
                $data = array('encounter_id' => $encounter_id,
                    'insured_id' => $encounterinsured_data[$i]['insured_id'],
                    'type' => $encounterinsured_data[$i]['type'],);
                $db->insert('encounterinsured', $data);
                $last_insert_id = $db->lastInsertId();
            }


//insert followups
            $followups_data['claim_id'] = $claim_id;
            $followups_id = mysql_insert('followups', $followups_data);
//4.Self Pay. No need to check statement_db since it is new encounter
            if ($options_data['SI_selfpay'] == '1' && $claim_data['statement_status'] == 'stmt_ready_selfpay') {
                $statement['statement_type'] = '1';
                $statement['trigger'] = '4';
                $statement['remark'] = $options_data['statement_I_4'];
                $statement['encounter_id'] = $encounter_id;
                statement_insert('statement', $statement);
            }
        }

        //zw the following should be done after new encounter has been added
        if ($assignedclaims['assignee'] != $assignedclaims_bk['assignee']) {
            $db_assignedclaims = new Application_Model_DbTable_Assignedclaims();
            $db = $db_assignedclaims->getAdapter();
            $assignedclaims['assignor'] = $this->get_user_id();
            $assignedclaims['encounter'] = $encounter_id;
            if ($assignedclaims_bk['assignee'] == null) {
                $db_assignedclaims->insert($assignedclaims);
                $flag_changed = 1;
            }
            if ($assignedclaims_bk['assignee'] != null && $assignedclaims['assignee'] != null) {
                $where = $db->quoteInto('encounter = ?', $encounter_id);
                $db_assignedclaims->update($assignedclaims, $where);
                $flag_changed = 1;
            }
            if ($assignedclaims_bk['assignee'] != null && $assignedclaims['assignee'] == null) {
                $where = $db->quoteInto('id = ?', $assignedclaims_bk['id']);
                try {
                    $db_assignedclaims->delete($where);
                    $flag_changed = 1;
                } catch (Exception $e) {
                    echo 'Message: ' . $e->getMessage();
                }
            }
        }

        $payments_data = $this->get_diff_session2('payments');
        if ($payments_data != 0) {
            $payments_data_len = count($payments_data);
            for ($i = 0; $i < $payments_data_len; $i++) {
                $payments_data[$i]['claim_id'] = $claim_id;
                if ($payments_data[$i]['id'] != null || $payments_data[$i]['id'] != "") {
                    $insert_data = array();
                    $insert_data["amount"] = $payments_data[$i]["amount"];
                    $insert_data["datetime"] = $payments_data[$i]["datetime"];
                    $insert_data["from"] = $payments_data[$i]["from"];
                    $insert_data["notes"] = $payments_data[$i]["notes"];
                    $insert_data["internal_notes"] = $payments_data[$i]["internal_notes"];
                    $insert_data["claim_id"] = $payments_data[$i]["claim_id"];
                    $insert_data["serviceid"] = 0;
                    $insert_data["paymentid"] = 0;
//                    $insert_data["paymentid"] = $payments_data[$i]['id'] ; 
                    mysql_update_by_id('payments', $insert_data, $payments_data[$i]['id']);
                    $flag_changed = 1;
                    if ($payments_data[$i]["type"] == "EOB") {
                        $services = $payments_data[$i]["services"];
                        for ($s_i = 0; $s_i < count($services); $s_i++) {
                            $update_data = array();
                            $update_data["amount"] = $services[$s_i]["amount"];
                            $update_data["datetime"] = $payments_data[$i]["datetime"];
                            $update_data["from"] = $payments_data[$i]["from"];
                            $update_data["notes"] = $payments_data[$i]["notes"];
                            $update_data["internal_notes"] = $payments_data[$i]["internal_notes"];
                            $update_data["claim_id"] = $payments_data[$i]["claim_id"];
                            $update_data["serviceid"] = ($s_i + 1);
                            $update_data["paymentid"] = $payments_data[$i]['id'];
                            if (isset($services[$s_i]["service_payment_id"]) && $services[$s_i]["service_payment_id"] != "") {
                                mysql_update_by_id('payments', $update_data, $services[$s_i]["service_payment_id"]);
                            } else {
                                mysql_insert('payments', $update_data);
                            }
                        }
                    }
                } else {
                    $insert_data = array();
                    $insert_data["amount"] = $payments_data[$i]["amount"];
                    $insert_data["datetime"] = $payments_data[$i]["datetime"];
                    $insert_data["from"] = $payments_data[$i]["from"];
                    $insert_data["notes"] = $payments_data[$i]["notes"];
                    $insert_data["internal_notes"] = $payments_data[$i]["internal_notes"];
                    $insert_data["claim_id"] = $payments_data[$i]["claim_id"];
                    $insert_data["serviceid"] = 0;
                    $insert_data["paymentid"] = 0;
                    $last_payment_id = mysql_insert('payments', $insert_data);
//                     $insert_data["paymentid"] = $last_payment_id;
                    $flag_changed = 1;
                    if ($payments_data[$i]["type"] == "EOB") {
                        $services = $payments_data[$i]["services"];
                        for ($s_i = 0; $s_i < count($services); $s_i++) {
                            $insert_data = array();
                            $insert_data["amount"] = $services[$s_i]["amount"];
                            $insert_data["datetime"] = $payments_data[$i]["datetime"];
                            $insert_data["from"] = $payments_data[$i]["from"];
                            $insert_data["notes"] = $payments_data[$i]["notes"];
                            $insert_data["internal_notes"] = $payments_data[$i]["internal_notes"];
                            $insert_data["claim_id"] = $payments_data[$i]["claim_id"];
                            $insert_data["serviceid"] = ($s_i + 1);
                            $insert_data["paymentid"] = $last_payment_id;
                            mysql_insert('payments', $insert_data);
                        }
                    }
                }
            }
        }
        //update interactionlogs
        $interactionlogs_data = $this->get_diff_session2('interactionlogs');
        if ($interactionlogs_data != 0) {
            $interactionlogs_data_len = count($interactionlogs_data);
            for ($i = 0; $i < $interactionlogs_data_len; $i++) {
                $interactionlogs_data[$i]['claim_id'] = $claim_id;
                if ($interactionlogs_data[$i]['notsave'] == 1) {
                    unset($interactionlogs_data[$i]['notsave']);
                }
                if ($interactionlogs_data[$i]['id'] != null || $interactionlogs_data[$i]['id'] != "") {

                    mysql_update_by_id('interactionlog', $interactionlogs_data[$i], $interactionlogs_data[$i]['id']);
                    $flag_changed = 1;
                } else {

                    mysql_insert('interactionlog', $interactionlogs_data[$i]);
                    $flag_changed = 1;
                }
            }
        }
        $patientlogs_data = $this->get_diff_session2('patientlogs');
        if ($patientlogs_data != 0) {
            $patientlogs_data_len = count($patientlogs_data);
            for ($i = 0; $i < $patientlogs_data_len; $i++) {
                if ($patientlogs_data[$i]['notsave'] == 1) {
                    unset($patientlogs_data[$i]['notsave']);
                }
                $patientlogs_data[$i]['patient_id'] = $patient_id;
                if ($patientlogs_data[$i]['id'] != null || $patientlogs_data[$i]['id'] != "") {
                    mysql_update_by_id('patientlog', $patientlogs_data[$i], $patientlogs_data[$i]['id']);
                    $flag_changed = 1;
                } else {

                    mysql_insert('patientlog', $patientlogs_data[$i]);
                    $flag_changed = 1;
                }
            }
        }
        if (isset($_SESSION['guarantor_data_BK'])) {
            $guarantor = session2DB_patient($_SESSION['guarantor_data']);
            $guarantor_bk = $_SESSION['guarantor_data_BK'];
            if ($guarantor["id"] == "no") {
                $claim_update = array();
                $claim_update["guarantor_id"] = null;
                mysql_update_by_id('claim', $claim_update, $claim_id);
            } else if ($guarantor["id"] == "new") {
                $guarantor_inset = array();
                foreach (get_ui2guarantor() as $key => $val) {
                    if ($key != "id") {
                        $guarantor_inset[$key] = $guarantor[$key];
                    }
                }
                $quarantor_id = mysql_insert("guarantor", $guarantor_inset);
                $claim_update = array();
                $claim_update["guarantor_id"] = $quarantor_id;
                mysql_update_by_id('claim', $claim_update, $claim_id);
            } else {
                if ($guarantor["id"] == $guarantor_bk["id"]) {
                    $guarantor_update = array();
                    foreach (get_ui2guarantor() as $key => $val) {
                        if ($key != "id") {
                            $guarantor_update[$key] = $guarantor[$key];
                        }
                    }
                    $quarantor_id = mysql_update_by_id("guarantor", $guarantor_update, $guarantor["id"]);
                } else {
                    $claim_update = array();
                    $claim_update["guarantor_id"] = $guarantor['id'];
                    mysql_update_by_id('claim', $claim_update, $claim_id);
                }
            }
        } else {
            $guarantor = session2DB_patient($_SESSION['guarantor_data']);
            if ($guarantor["id"] == "new") {
                $guarantor_inset = array();
                foreach (get_ui2guarantor() as $key => $val) {
                    if ($key != "id") {
                        $guarantor_inset[$key] = $guarantor[$key];
                    }
                }
                $quarantor_id = mysql_insert("guarantor", $guarantor_inset);
                $claim_update = array();
                $claim_update["guarantor_id"] = $quarantor_id;
                mysql_update_by_id('claim', $claim_update, $claim_id);
            } else if ($guarantor["id"] != "no") {
                $claim_update = array();
                $claim_update["guarantor_id"] = $guarantor['id'];
                mysql_update_by_id('claim', $claim_update, $claim_id);
            }
        }
        $new_claim_flag = $_SESSION['new_claim_flag'];
        if ($new_claim_flag == 1) {
            $new_bill_upload_file_name = $_SESSION['new_upload_file'];
            if ($new_bill_upload_file_name != '' && $new_bill_upload_file_name != null) {
                $old_path = $this->sysdoc_path . '/' . 'newbillfile';
                $old_file = $old_path . '/' . $new_bill_upload_file_name;
                if (file_exists($old_file)) {
                    $billingcompany_id = $this->billingcompany_id;
                    $new_path = $this->sysdoc_path . '/' . $billingcompany_id;
                    if (!is_dir($new_path)) {
                        mkdir($new_path);
                    }
                    $new_path = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $encounter_data['provider_id'];
                    if (!is_dir($new_path)) {
                        mkdir($new_path);
                    }
                    $new_path = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $encounter_data['provider_id'] . '/claim';
                    if (!is_dir($new_path)) {
                        mkdir($new_path);
                    }
                    $new_path = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $encounter_data['provider_id'] . '/claim/' . $claim_id;
                    if (!is_dir($new_path)) {
                        mkdir($new_path);
                    }
                    $today = date("Y-m-d H:i:s");
                    $date = explode(' ', $today);
                    $time0 = explode('-', $date[0]);
                    $time1 = explode(':', $date[1]);
                    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name = $user->user_name;
                    $new_name = $time . '-ServiceComboSheets-Faciliy-' . $user_name . '.pdf';
                    $new_file = $new_path . '/' . $new_name;
                    $result = copy($old_file, $new_file);
                    $interactions = $_SESSION["interactionlogs_data"];
                    $count_log = count($interactions);
                    if ($result == 1) {
                        $split_options = array();
                        unlink($old_file);
                        $facility_id = '';
                        $facility_id = $_SESSION['encounter_data']['facility_id'];
                        $combo = array();
                        if ($facility_id > 0)
                            $combo = get_service_doc_pages($facility_id);
                        $combo_counts = count($combo);
                        //for($i = 0;$i<$combo_counts;$i++)
                        //{if($combo[$i] == "Facility Sheet") $combo[$i] = "FacilitySheet";}
                        if ($combo_counts >= 1) {
                            for ($i = 0; $i < $combo_counts - 1; $i++) {
                                $new_filename = $time . '-' . $combo[$i] . '-' . 'Facility' . '-' . $user_name . '.pdf';
                                $interactionlogs_temp['claim_id'] = $claim_id;
                                $interactionlogs_temp['date_and_time'] = $today;
                                $interactionlogs_temp['log'] = $user_name . ": Document_ServiceComboSheets upload: " . 'ClaimRelated' . ":" . $new_filename;
                                $interactions[$count_log]['claim_id'] = $claim_id;
                                $interactions[$count_log]['date_and_time'] = $today;
                                $interactions[$count_log]['log'] = $interactionlogs_temp['log'];
                                array_splice($interactions, $count_log + 1);
                                //$_SESSION['interactionlogs_data'] = $interactions;
                                mysql_insert('interactionlog', $interactionlogs_temp);
                                $count_log = $count_log + 1;
                                array_push($split_options, array($new_filename, $i + 1, '1'));
                            }
                            $new_filename = $time . '-' . $combo[$combo_counts - 1] . '-' . 'Facility' . '-' . $user_name . '.pdf';
                            $interactionlogs_temp['claim_id'] = $claim_id;
                            $interactionlogs_temp['date_and_time'] = $today;
                            $interactions[$count_log]['claim_id'] = $claim_id;
                            $interactions[$count_log]['date_and_time'] = $today;
                            $interactionlogs_temp['log'] = $user_name . ": Document_ServiceComboSheets upload: " . 'ClaimRelated' . ":" . $new_filename;
                            $interactions[$count_log]['log'] = $interactionlogs_temp['log'];
                            array_splice($interactions, $count_log + 1);
                            $_SESSION['interactionlogs_data'] = $interactions;
                            mysql_insert('interactionlog', $interactionlogs_temp);
                            array_push($split_options, array($new_filename, count($combo)));
                            splitpdf($new_file, $new_path, $split_options);
                            unlink($new_file);
                        }
                    }
                }
            }
        }

        if ($flag_changed == 1) {
            $data_now = date("Y-m-d H:i:s");
            $claim_data_update['update_time'] = $data_now;
            $user = Zend_Auth::getInstance()->getIdentity();
            $user_name = $user->user_name;
            $claim_data_update['update_user'] = $user_name;
            $rows_affected = mysql_update_by_id('claim', $claim_data_update, $claim_id);
        }

        $dd = 0;

        $id = $_SESSION['encounter_data']['id'];
        $is_new_claim = ((is_null($id)) || ($id == ''));
        if ($is_new_claim) {
            session_start();
            foreach (get_session_name() as $val) {
                $tmp = $val . '_id';
                $id = $$tmp;
                $_SESSION[$val . '_data']['id'] = $id;
            }
        }
        session_start();
        unset($_SESSION['claim_data']['needsave']);
//return is new?
        return $is_new_claim;
    }

//saveclaim
    function saveclaimAction() {
        $this->_helper->viewRenderer->setNoRender();
        $this->save_data();
        $pageno = $this->getRequest()->getParam('pageno');
        $pages = array('patient', 'insurance', 'services', 'claim', 'documents');
        session_start();
        $patient_id = $_SESSION['patient_data']['id'];
        $encounter_id = $_SESSION['encounter_data']['id'];
        $this->initsession($patient_id, $encounter_id);
        $this->_redirect('/biller/claims/' . $pages[$pageno]);
    }

    function finishclaimAction() {
        $this->_helper->viewRenderer->setNoRender();
        /*         * write to check insuracne name */

        session_start();
        $encounterinsured_data = $_SESSION['encounterinsured_data'];
        $encounter = $_SESSION['encounter_data'];
        $new_claim_flag = $_SESSION['new_claim_flag'];
        $insure = $_SESSION['insured_data'];
        $insure_id = array();
        for ($i = 0; $i < count($encounterinsured_data); $i++) {
            $insure_id[$i] = $encounterinsured_data[$i]['insured_id'];
        }

        if ($new_claim_flag == 1) {

            $insurance_name = array();
            for ($i = 0; $i < count($insure); $i++) {
                if (true == in_array($insure[$i]['id'], $insure_id)) {
                    $insurance_name[$i] = $insure[$i]['insurance_name'];
                }
            }
            $rendering = $encounter['renderingprovider_name']; //zzNeed New, Referring Provider
            $referring = $encounter['referringprovider_name']; //zzNeed New, Rendering Provider
            $secondary_CPT_code_text = array();
            $CPT_code_text = array();
            for ($k = 0; $k < 6; $k++) {
                $temp_k = $k + 1;
                $secondary_CPT_code_text[$temp_k] = $encounter['secondary_CPT_code_' . $temp_k . '_text'];
                $CPT_code_text[$temp_k] = $encounter['CPT_code_' . $temp_k . '_text'];
            }
            $diagnosis_code_text = array();
            for ($k = 0; $k < 4; $k++) {
                $temp_k = $k + 1;
                $diagnosis_code_text[$temp_k] = $encounter['diagnosis_code' . $temp_k . '_text'];
            }
//            $secondary_CPT_code_text_1=$encounter['secondary_CPT_code_1_text'];
//            $secondary_CPT_code_text_2=$encounter['secondary_CPT_code_2_text'];
//            $secondary_CPT_code_text_3=$encounter['secondary_CPT_code_3_text'];
//            $secondary_CPT_code_text_4=$encounter['secondary_CPT_code_4_text'];
//            $secondary_CPT_code_text_5=$encounter['secondary_CPT_code_5_text'];
//            $secondary_CPT_code_text_6=$encounter['secondary_CPT_code_6_text'];//zzNeed New [0]
//            
//            $CPT_code_text_1=$encounter['CPT_code_1_text'];//zzNeed New Need New CPT Code
//            $CPT_code_text_2=$encounter['CPT_code_2_text'];
//            $CPT_code_text_3=$encounter['CPT_code_3_text'];
//            $CPT_code_text_4=$encounter['CPT_code_4_text'];
//            $CPT_code_text_5=$encounter['CPT_code_5_text'];
//            $CPT_code_text_6=$encounter['CPT_code_6_text'];
//            
//            $diagnosis_code1_text=$encounter['diagnosis_code1_text'];
//            $diagnosis_code2_text=$encounter['diagnosis_code2_text'];
//            $diagnosis_code3_text=$encounter['diagnosis_code3_text'];
//            $diagnosis_code4_text=$encounter['diagnosis_code4_text'];//zzNeed New Need New Diagnosis Code

            $name_temp = 'Need New Insurance';
            if (true == in_array($name_temp, $insurance_name) || $rendering == 'Need New, Rendering Provider' || 'Need New, Referring Provider' == $referring || true == in_array('Need New', $secondary_CPT_code_text) || true == in_array('Need New Need New CPT Code', $CPT_code_text) || true == in_array('Need New Need New Diagnosis Code', $diagnosis_code_text)) {

                session_start();
                $interactionlogs_date = $_SESSION['interactionlogs_data'];
                $claim = $_SESSION['claim_data'];
                $oldclaimstatus = $claim['claim_status'];

                $claim['claim_status'] = 'inactive_missing_data';
                session_start();
                $_SESSION['claim_data'] = $claim;
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                $data_now = date("Y-m-d H:i:s");
                $data_now = format($data_now, 7);
                $log = $user_name . ': Change Claim Status from ' . $oldclaimstatus . ' to inactive_missing_data';
                $count = count($interactionlogs_date);
                $claimstatusarray = array();
                $interactionlogs_date[$count]['claim_id'] = $claim['id'];
                $interactionlogs_date[$count]['log'] = $log;
                $interactionlogs_date[$count]['date_and_time'] = $data_now;
                session_start();
                if ($oldclaimstatus != 'inactive_missing_data')
                    $_SESSION['interactionlogs_data'] = $interactionlogs_date;
            }
        }
        /*         * *****    *****    ***** */
        $is_new_claim = $this->save_data();

        session_start();
        $encounter_id = $encounter['id'];
        $facility_id = $encounter['facility_id'];
        $is_from_top_ten = $_SESSION['from_top_ten'];
//save cur service info
        $cur_service_info = $this->get_cur_service_info('encounter');
        $this->clearsession();
        $this->unset_options_session();
        if ($is_new_claim) {
            session_start();

            unset($cur_service_info['docbtn_vavaiable']);
            $cur_service_info['facility_id'] = $facility_id;
            $cur_service_info['encounter_id'] = $encounter_id;
            session_start();
            $_SESSION['tmp']['cur_service_info'] = $cur_service_info;
            /* echo "<script language='JavaScript' type='text/javascript'>
              window.open( 'upload/pageno/3 ','upload',
              'height=220,width=500,status=no,toolbar=no,menubar=no,location=no,scrollbars=no')
              </script>"; */

//            if ($_SESSION['tmp']['patient'] != null)
//                $this->_redirect('/biller/claims/inquiry');
        } else {
            session_start();
            if ($_SESSION["batch_flag"] == 1) {
                $_SESSION["batch_flag"] = 0;
                $this->_redirect('/biller/claims/mannualpaymentbatch');
            }
            if ($_SESSION["era_state"] == 1) {
                $_SESSION["era_state"] = 0;
                $this->_redirect('/biller/claims/erapaymentbatch');
            }
            if ($is_from_top_ten == 1) {
                $this->_redirect('/biller/claims/inquiry');
            }
            /*             * ********************Fix the bug that not updated in time By Yu Lang************* */
            if ($_SESSION['tmp']['actionList'] != null) {
                $this->actionlistAction();
//                $this->_redirect('/biller/claims/action');
            }
            if ($_SESSION['tmp']['patient'] != null) {
                $plist = $_SESSION['tmp']['patient'];
                $new_plist = $this->get_tmp_patient($plist);
                $_SESSION['tmp']['patient'] = $new_plist;
                $this->_redirect('/biller/claims/inquiryresults');
            }
            if ($_SESSION['tmp']['billList'] != null) {
                $this->_redirect('/biller/claims/billlist');
            }
            if ($_SESSION['tmp']['statementList'] != null) {
                $this->_redirect('/biller/claims/statementslist');
            }
            if ($_SESSION['tmp']['assignedclaims_data'] != null) {
                $this->_redirect('/biller/claims/assignedclaims');
            }
            if ($_SESSION['tmp']['turnoverList'] != null) {
                $this->_redirect('/biller/data/turnover');
            }
            if ($_SESSION['tmp']['appealList'] != null) {
                $this->_redirect('/biller/data/appeal');
            }


            /*             * ********************Fix the bug that not updated in time By Yu Lang************* */
        }
        echo "<script language='JavaScript' type='text/javascript'>
		window.location.href='inquiry';
		</script>";
    }

    function getinsurednumber($insurance_id, $patient_id) {
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('patient', array('id as patient_id'));
        $select->join('patientinsured', 'patientinsured.patient_id = patient.id', array('insured_id as insured_id'));
        $select->join('insured', 'insured.id = patientinsured.insured_id', array('insurance_id'));
        //$select->join('insurance', 'insurance.id = insured.insurance_id', array('insurance_id'));
        $select->where('patient.id=?', $patient_id);
        $select->where('insured.insurance_id =?', $insurance_id);
        $result = $db->fetchAll($select);
        return $result[0]['insured_id'];
    }

    /**
     * return first page like array('patient'=>'last_name')
     */
    function validation() {
        session_start();
        $patient_data = $_SESSION['patient_data'];
        $insured_data = $_SESSION['insured_data'];
        $insurance_data = $_SESSION['insurance_data'];
        $encounter_data = $_SESSION['encounter_data'];
        $claim_data = $_SESSION['claim_data'];

        $patient_unnull_field = array('last_name', 'first_name', 'DOB', 'sex', 'street_address', 'zip', 'city', 'state', 'phone_number', 'account_number');
        $insurance_unnull_field = array(/* 'insurance_name',  'street_address', 'city', 'state', 'zip',  'claim_submission_preference', 'payer_type', */);
        $encounter_unnull_field = array(/* 'diagnosis_code1', 'start_date_1', 'end_date_1', 'start_time_1', 'end_time_1', 'place_of_service_1', 'CPT_code_1', 'diagnosis_pointer_1', 'charges_1', 'days_or_units_1',
                  'accept_assignment', 'provider_id', 'renderingprovider_id', 'facility_id' */
        );
        $encounter_unnull_field = array();
        $encounter_unnull_field = array('diagnosis_code1');
        //$encounter_unnull_field = array('diagnosis_code1', 'start_date_1', 'end_date_1', 'place_of_service_1', 'diagnosis_pointer_1', 'charges_1', 'days_or_units_1');
        $claim_unnull_field = array('total_charge', 'claim_status');
        $ret = $this->validation_field($patient_data, $patient_unnull_field);
        if ($ret != '')
            return array('patient' => $ret);
        for ($i = 0; $i < count($insured_data); $i++) {
            if ($insured_data[$i]['payer_type'] == null || $insured_data[$i]['payer_type'] == '') {
                if ($insurance_data['id'] == $insured_data[$i]['insurance_id'])
                    $insured_data[$i]['payer_type'] = $insurance_data['payer_type'];
            }
            if ($insured_data[$i]['payer_type'] == 'SP' || $insured_data[$i]['payer_type'] == null || $insured_data[$i]['payer_type'] == '')
                $insured_unnull_field = array('insurance_id');
            else
                $insured_unnull_field = array('ID_number', 'insurance_id');

            $ret = $this->validation_field($insured_data[$i], $insured_unnull_field);
            if ($ret != '')
                return array('insurance' => $ret . '_' . ($i + 1));
        }

        $ret = $this->validation_field($insurance_data, $insurance_unnull_field);
        if ($ret != '')
            return array('insurance' => $ret);

        //zw
        for ($j = 1; $j <= 6; $j++) {
            if ($j > 1 && ($encounter_data['CPT_code_' . $j] == null && $encounter_data['secondary_CPT_code_' . $j] == null))
                return array();
            else if ($encounter_data['secondary_CPT_code_' . $j] == "")
                $encounter_unnull_field = array('diagnosis_code1', 'start_date_' . $j, 'end_date_' . $j, 'place_of_service_' . $j, 'diagnosis_pointer_' . $j, 'charges_' . $j);
            else if ($j === 1)
                $encounter_unnull_field = array('diagnosis_code1', 'start_date_' . $j, 'end_date_' . $j, 'place_of_service_' . $j, 'diagnosis_pointer_' . $j, 'days_or_units_' . $j, 'charges_' . $j);
            else
                $encounter_unnull_field = array('diagnosis_code1', 'start_date_' . $j, 'end_date_' . $j, 'place_of_service_' . $j, 'diagnosis_pointer_' . $j);
            $ret = $this->validation_field($encounter_data, $encounter_unnull_field);
            if ($ret != '')
                return array('services' => $ret);
        }
        $ret = $this->validation_field($claim_data, $claim_unnull_field);
        if ($ret != '')
            return array('claim' => $ret);
        return array();
    }

    /*     * *******************get log info by encount_id************ */

    function generate_statement_log($encount_array, $statement_type) {
        /*         * ************************Generate Log file************************** */
        $data = array();
        $fields = array('num', 'date', 'time', 'name', 'mrn', 'dos', 'insurance');
        $display_fields = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS', 'Insurance');
        /*         * ************************Generate Log file************************** */

        $index = 0;
        foreach ($encount_array as $en_id) {
            $db_encount = new Application_Model_DbTable_Encounter();
            $db = $db_encount->getAdapter();
            $where = $db->quoteInto('id = ?', $en_id);
            $encounter_db = $db_encount->fetchRow($where);
            $tp_patient_id = $encounter_db['patient_id'];
            $tp_encounter_id = $encounter_db['id'];
            //
            $tp_claim_id = $encounter_db['claim_id'];

            $db_claim = new Application_Model_DbTable_Claim();
            $db = $db_claim->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_claim_id);
            $claim_db = $db_claim->fetchRow($where);

            $db_patient = new Application_Model_DbTable_Patient();
            $db = $db_patient->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_patient_id);
            $patient_db = $db_patient->fetchRow($where);

            //$tp_insured_id = $patient_db['insured_id'];
            //zw
            $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
            $db = $db_encounterinsured->getAdapter();
            $where = $db->quoteInto('encounter_id = ?', $tp_encounter_id) . $db->quoteInto(' AND type = ?', 'primary');
            //$where = $db->quoteInto('type = ?', 'primary');
            $encounterinsured_db = $db_encounterinsured->fetchRow($where);

            $tp_insured_id = $encounterinsured_db['insured_id'];
            //zw

            $db_insured = new Application_Model_DbTable_Insured();
            $db = $db_insured->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_insured_id);
            $insured_db = $db_insured->fetchRow($where);

            //zw if ($claim_db['claim_status'] == 'open_ready_secondary_bill')
            $tp_insurance_id = $insured_db['insurance_id'];
            //else
            //    $tp_insurance_id = $insured_db['insurance_id'];

            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_insurance_id);
            $insurance_db = $db_insurance->fetchRow($where);



            $data[$index]['num'] = $index + 1;
            $data[$index]['date'] = date('m/d/Y');
            $data[$index]['time'] = date('H:i', time());
            $data[$index]['name'] = $patient_db['last_name'] . ' ' . $patient_db['first_name'];
            $data[$index]['mrn'] = "=\"" . $patient_db['account_number'] . "\"";
            ;
            $data[$index]['dos'] = $encounter_db['start_date_1'];
            $data[$index]['insurance'] = $insurance_db['insurance_display'];
            $index++;
        }
        /*         * **********************Get log dir and log file name*********************** */
        $log_dir = $this->sysdoc_path;


        $log_dir = $log_dir . '/' . $this->get_billingcompany_id();
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $log_dir = $log_dir . '/statementlog';
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $stat_type = array('StatementI.csv', 'StatementII.csv', 'StatementIII.csv', 'SelfPay.csv', 'Installment.csv');

        $log_file_name = $log_dir . '/' . $stat_type[$statement_type - 1];


        $final_length = sizeof($fields);

        if (file_exists($log_file_name)) {
            $rp = fopen($log_file_name, 'r');
            $tmp_log_file = $log_dir . '/tmp.csv';
            $wp = fopen($tmp_log_file, 'w');


            for ($i = 0; $i < $final_length; $i++) {
                fwrite($wp, $display_fields[$i] . ",");
            }
            fwrite($wp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
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


            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length; $j++) {
                    $ttt = $data[$i][$fields[$j]];
                    fwrite($fp, $data[$i][$fields[$j]] . ",");
                }
                fwrite($fp, "\r\n");
            }
            fclose($fp);
        }

        /*         * **********************Get log dir and log file name*********************** */
        $cdd = 'ddddddd';
        return true;
    }

    /*     * *******************get log info by encount_id************ */

    /*     * *************Get tmp_function  By Yu Lang************** */

    function generate_pc_log($encount_array) {
        /*         * ************************Generate Log file************************** */
        $data = array();
        $fields = array('num', 'date', 'time', 'name', 'mrn', 'dos', 'insurance');
        $display_fields = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS', 'Insurance');
        /*         * ************************Generate Log file************************** */

        $index = 0;
        foreach ($encount_array as $en_id) {
            $db_encount = new Application_Model_DbTable_Encounter();
            $db = $db_encount->getAdapter();
            $where = $db->quoteInto('id = ?', $en_id);
            $encounter_db = $db_encount->fetchRow($where);
            $tp_patient_id = $encounter_db['patient_id'];
            $tp_encounter_id = $encounter_db['id'];
            //
            $tp_claim_id = $encounter_db['claim_id'];

            $db_claim = new Application_Model_DbTable_Claim();
            $db = $db_claim->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_claim_id);
            $claim_db = $db_claim->fetchRow($where);

            $db_patient = new Application_Model_DbTable_Patient();
            $db = $db_patient->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_patient_id);
            $patient_db = $db_patient->fetchRow($where);

            //$tp_insured_id = $patient_db['insured_id'];
            //zw
            $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
            $db = $db_encounterinsured->getAdapter();
            $where = $db->quoteInto('encounter_id = ?', $tp_encounter_id) . $db->quoteInto(' AND type = ?', 'primary');
            //$where = $db->quoteInto('type = ?', 'primary');
            $encounterinsured_db = $db_encounterinsured->fetchRow($where);

            $tp_insured_id = $encounterinsured_db['insured_id'];
            //zw

            $db_insured = new Application_Model_DbTable_Insured();
            $db = $db_insured->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_insured_id);
            $insured_db = $db_insured->fetchRow($where);

            //zw if ($claim_db['claim_status'] == 'open_ready_secondary_bill')
            $tp_insurance_id = $insured_db['insurance_id'];
            //else
            //    $tp_insurance_id = $insured_db['insurance_id'];

            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_insurance_id);
            $insurance_db = $db_insurance->fetchRow($where);



            $data[$index]['num'] = $index + 1;
            $data[$index]['date'] = date('m/d/Y');
            $data[$index]['time'] = date('H:i', time());
            $data[$index]['name'] = $patient_db['last_name'] . ' ' . $patient_db['first_name'];
            $data[$index]['mrn'] = "=\"" . $patient_db['account_number'] . "\"";
            ;
            $data[$index]['dos'] = $encounter_db['start_date_1'];
            $data[$index]['insurance'] = $insurance_db['insurance_display'];
            $index++;
        }
        /*         * **********************Get log dir and log file name*********************** */
        $log_dir = $this->sysdoc_path;


        $log_dir = $log_dir . '/' . $this->get_billingcompany_id();
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $log_dir = $log_dir . '/pclog';
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        //$stat_type = array('StatementI.csv', 'StatementII.csv', 'StatementIII.csv', 'SelfPay.csv', 'Installment.csv');

        $log_file_name = $log_dir . '/' . 'Correspondence.csv';


        $final_length = sizeof($fields);

        if (file_exists($log_file_name)) {
            $rp = fopen($log_file_name, 'r');
            $tmp_log_file = $log_dir . '/tmp.csv';
            $wp = fopen($tmp_log_file, 'w');


            for ($i = 0; $i < $final_length; $i++) {
                fwrite($wp, $display_fields[$i] . ",");
            }
            fwrite($wp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
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


            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length; $j++) {
                    $ttt = $data[$i][$fields[$j]];
                    fwrite($fp, $data[$i][$fields[$j]] . ",");
                }
                fwrite($fp, "\r\n");
            }
            fclose($fp);
        }

        /*         * **********************Get log dir and log file name*********************** */
//        $cdd = 'ddddddd';
        return true;
    }

    function get_tmp_patient($list) {
        $id = array();
        for ($j = 0; $j < count($list); $j++) {
            $id[$j] = $list[$j]['encounter_id'];
        }

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('renderingprovider', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
        $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('encounter.start_date_1', 'encounter.secondary_CPT_code_1 as anes_code', 'CPT_code_1', 'CPT_code_2', 'CPT_code_3', 'CPT_code_4', 'CPT_code_5', 'CPT_code_6', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id'));
        /*         * ********************Add fields***************** */
        $select->joinLeft('referringprovider', 'referringprovider.id = encounter.referringprovider_id', array('referringprovider.last_name AS referringprovider_last_name', 'referringprovider.first_name AS referringprovider_first_name'));
        /*         * ********************Add fields***************** */
        $select->join('claim', 'claim.id=encounter.claim_id');


        /*         * ******Using short_name for the facility and provider***** */
        $select->join('provider', 'provider.id=encounter.provider_id', array('provider_name', 'provider.short_name AS provider_short_name'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));

        /*         * ******Using short_name for the facility and provider***** */

        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'phone_number', 'second_phone_number', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'patient.account_number'));

        /*         * *new for inquiry** */
        //$select->join('insured', 'insured.id=patient.insured_id');
        $select->join('encounterinsured', 'encounterinsured.encounter_id =encounter.id');
        $select->join('insured', 'encounterinsured.insured_id =insured.id');
        /*         * *new for inquiry** */
        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        $select->where('billingcompany.id=?', $this->get_billingcompany_id());
        $select->where('encounterinsured.type=?', 'primary');
        $select->where('encounter.id IN(?)', $id);
        $new_list = $db->fetchAll($select);
//        $select->order(new Zend_Db_Expr("FIELD(encounter.id,$id)"));
        $temp = $db->fetchAll($select);
        $new_list = array();
        for ($i = 0; $i < count($id); $i++) {
            for ($j = 0; $j < count($temp); $j++) {
                if ($temp[$j]['encounter_id'] == $id[$i]) {
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('interactionlog');
                    $select->where('interactionlog.claim_id=?', $temp[$j]['claim_id']);
                    $select->order('interactionlog.date_and_time DESC');
                    $tmp_interactionlogs = $db->fetchAll($select);
                    $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                    $start_date = date("Y-m-d");
                    $noactiondays = 99;
                    if ($end_date != null && $end_date != "") {
                        $noactiondays = days($start_date, $end_date);
                    } else {
                        $temp_days = 99;
                        if ($temp[$j]['date_last_billed'] != null && $temp[$j]['date_billed'] != null && $temp[$j]['date_rebilled'] != null) {
                            $temp_end_date = max($temp[$j]['date_last_billed'], $temp[$j]['date_billed'], $temp[$j]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] != null && $temp[$j]['date_billed'] == null && $temp[$j]['date_rebilled'] != null) {
                            $temp_end_date = max($temp[$j]['date_last_billed'], $temp[$j]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] == null && $temp[$j]['date_billed'] != null && $temp[$j]['date_rebilled'] != null) {
                            $temp_end_date = max($temp[$j]['date_billed'], $temp[$j]['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] != null && $temp[$j]['date_billed'] != null && $temp[$j]['date_rebilled'] == null) {
                            $temp_end_date = max($temp[$j]['date_billed'], $temp[$j]['date_last_billed']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] == null && $temp[$j]['date_billed'] == null && $temp[$j]['date_rebilled'] != null) {
                            $temp_end_date = $temp[$j]['date_rebilled'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] != null && $temp[$j]['date_billed'] == null && $temp[$j]['date_rebilled'] == null) {
                            $temp_end_date = $temp[$j]['date_last_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($temp[$j]['date_last_billed'] == null && $temp[$j]['date_billed'] != null && $temp[$j]['date_rebilled'] == null) {
                            $temp_end_date = $temp[$j]['date_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        }
                        $noactiondays = $temp_days;
                    }
                    if ($noactiondays <= 99)
                        $temp[$j]['last'] = $noactiondays;
                    else
                        $temp[$j]['last'] = 99;
                    $amount_paid = $temp[$j]['amount_paid'];
                    $total_charge = $temp[$j]['total_charge'];
                    $per = $amount_paid / $total_charge;
                    $per = round($per, 2) * 100;
                    if ($per == 0)
                        $temp[$j]['percentage'] = "";
                    else
                        $temp[$j]['percentage'] = $per;
                    $new_list[$i] = $temp[$j];
                    break;
                }
            }
        }
        $count_new_list = count($new_list);
        $colorAlerts = $this->get_coloralerts();
        for ($i = 0; $i < $count_new_list; $i++) {
            $row = $new_list[$i];
            for ($j = 0; $j < count($colorAlerts); $j++) {
                if ($row['color_code'] == $colorAlerts[$j]['RGB'])
                    $new_list[$i]['alert'] = $colorAlerts[$j]['alert'];
            }
            if ($row['type'] == 'primary') {
                //$patientList[$count]['insurance_name'] = $row['insurance_name'];
                //$patientList[$count]['insurance_display'] = $row['insurance_display'];
                $encounter_id = $row['encounter_id'];
                $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                $db = $db_encounterinsured->getAdapter();
                //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                $type_tmp = 'secondary';
                $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                $result_temp = $db_encounterinsured->fetchRow($where);
                if ($result_temp != null) {
                    $insured_s_id = $result_temp['insured_id'];
                    $db_insured = new Application_Model_DbTable_Insured();
                    $db = $db_insured->getAdapter();
                    $where = $db->quoteInto('id=?', $insured_s_id);
                    $insured_tmp = $db_insured->fetchRow($where);
                    $insurance_s_id = $insured_tmp['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $insurance_s_id);
                    $insurance_tmp = $db_insurance->fetchRow($where);
                    $new_list[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                    $new_list[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                } else {
                    $new_list[$i]['insurance_s_name'] = null;
                    $new_list[$i]['insurance_s_display'] = null;
                }
            } else if ($row['type'] == 'secondary') {
                //$patientList[$count]['insurance_s_name'] = $row['insurance_name'];
                //$patientList[$count]['insurance_s_display'] = $row['insurance_display'];
                $new_list[$i]['insurance_s_name'] = $row['insurance_name'];
                $new_list[$i]['insurance_s_display'] = $row['insurance_display'];
                $encounter_id = $row['encounter_id'];
                $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                $db = $db_encounterinsured->getAdapter();
                $type_tmp = 'primary';
                //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                $result_temp = $db_encounterinsured->fetchRow($where);
                if ($result_temp != null) {
                    $insured_s_id = $result_temp['insured_id'];
                    $db_insured = new Application_Model_DbTable_Insured();
                    $db = $db_insured->getAdapter();
                    $where = $db->quoteInto('id=?', $insured_s_id);
                    $insured_tmp = $db_insured->fetchRow($where);
                    $insurance_s_id = $insured_tmp['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $insurance_s_id);
                    $insurance_tmp = $db_insurance->fetchRow($where);
                    $new_list[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                    $new_list[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                } else {
                    $new_list[$i]['insurance_name'] = null;
                    $new_list[$i]['insurance_display'] = null;
                }
            } else {
                $new_list[$i]['insurance_name_other'] = $row['insurance_name'];
                $new_list[$i]['insurance_display_other'] = $row['insurance_display'];
                $encounter_id = $row['encounter_id'];
                $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                $db = $db_encounterinsured->getAdapter();
                $type_tmp = 'primary';
                //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = ' . $type_tmp;
                $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                $result_temp = $db_encounterinsured->fetchRow($where);
                if ($result_temp != null) {
                    $insured_s_id = $result_temp['insured_id'];
                    $db_insured = new Application_Model_DbTable_Insured();
                    $db = $db_insured->getAdapter();
                    $where = $db->quoteInto('id=?', $insured_s_id);
                    $insured_tmp = $db_insured->fetchRow($where);
                    $insurance_s_id = $insured_tmp['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $insurance_s_id);
                    $insurance_tmp = $db_insurance->fetchRow($where);
                    $new_list[$i]['insurance_name'] = $insurance_tmp['insurance_name'];
                    $new_list[$i]['insurance_display'] = $insurance_tmp['insurance_display'];
                } else {
                    $new_list[$i]['insurance_name'] = null;
                    $new_list[$i]['insurance_display'] = null;
                }
                $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                $db = $db_encounterinsured->getAdapter();
                //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                $type_tmp = 'secondary';
                $where = $db->quoteInto('encounter_id=?', $encounter_id) . ' AND ' . $db->quoteInto('type=?', $type_tmp);
                $result_temp = $db_encounterinsured->fetchRow($where);
                if ($result_temp != null) {
                    $insured_s_id = $result_temp['insured_id'];
                    $db_insured = new Application_Model_DbTable_Insured();
                    $db = $db_insured->getAdapter();
                    $where = $db->quoteInto('id=?', $insured_s_id);
                    $insured_tmp = $db_insured->fetchRow($where);
                    $insurance_s_id = $insured_tmp['insurance_id'];
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto('id=?', $insurance_s_id);
                    $insurance_tmp = $db_insurance->fetchRow($where);
                    $new_list[$i]['insurance_s_name'] = $insurance_tmp['insurance_name'];
                    $new_list[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                } else {
                    $new_list[$i]['insurance_s_name'] = null;
                    $new_list[$i]['insurance_s_display'] = null;
                }
            }
        }
        return $new_list;
    }

    /*     * *************Get tmp_function  By Yu Lang************** */

    function validation_field($data, $field) {
        foreach ($field as $key => $value) {
            if ($data[$value] == null || ($data[$value] == ''))
                return $value;
        }
        return '';
    }

    function write_session($s_name, $set) {
        session_start();
        foreach ($set as $key => $val) {
            if ($val != $_SESSION[$s_name . '_data'][$key] || ($_SESSION[$s_name . '_data'][$key] == null)) {
                $_SESSION[$s_name . '_data'][$key] = $val;
                $_SESSION[$s_name . '_data']['diff'][$key] = $val;
            }
        }
    }

    function format_insured_session($data) {
        $new_data = array();
        $fileds = get_fields('insured');
        foreach ($fileds as $field) {
            $new_data[$filed] = $data[$filed];
        }
        return $new_data;
    }

    function get_diff_session($s_name) {
        session_start();
        $myfield = array('renderingprovider_name', 'referringprovider_name', 'secondary_CPT_code_1_text', 'secondary_CPT_code_2_text', 'secondary_CPT_code_3_text', 'secondary_CPT_code_4_text', 'secondary_CPT_code_5_text', 'secondary_CPT_code_6_text',
            'CPT_code_1_text', 'CPT_code_2_text',
            'CPT_code_3_text', 'CPT_code_4_text', 'CPT_code_5_text', 'CPT_code_6_text',
            'diagnosis_code1_text', 'diagnosis_code2_text', 'diagnosis_code3_text', 'diagnosis_code4_text');
        $s_data_old = $_SESSION[$s_name . '_data_BK'];
        $s_data_new = session2DB($_SESSION[$s_name . '_data'], $s_name);
        $diff_data = array();
        $diff_data['id'] = $s_data_new['id'];
        $fileds = get_fields($s_name);


        foreach ($fileds as $field) {
            if ($s_data_old[$field] != $s_data_new[$field] && false == in_array($field, $myfield)) {
                $diff_data[$field] = $s_data_new[$field];
            }
        }
        if ($s_name == 'statement') {
            $diff_data['statement_id2'] = $s_data_old['statement_id2'];
//            $diff_data['statement_type2'] = $s_data_new['statement_type2'];
        }
        return $diff_data;
    }

    function get_diff_session2($s_name) {
        session_start();
        $fields = array();
        if ($s_name == "insurancepayments") {
            $db_sts = new Application_Model_DbTable_Insurancepayments();
            $fields = array('id', 'amount', 'date', 'notes');
        }

        if ($s_name == "patientpayments") {
            $db_sts = new Application_Model_DbTable_Patientpayments();
            $fields = array('id', 'amount', 'date', 'notes');
        }

        if ($s_name == "billeradjustments") {
            $db_sts = new Application_Model_DbTable_Billeradjustments();
            $fields = array('id', 'amount', 'date', 'notes');
        }

        if ($s_name == "interactionlogs") {
            $db_sts = new Application_Model_DbTable_Interactionlog();
            $fields = array('id', 'date_and_time', 'log');
        }
        if ($s_name == 'patientlogs') {
            $db_sts = new Application_Model_DbTable_Patientlog();
            $fields = array('id', 'date_and_time', 'log');
        }
        if ($s_name == 'payments') {
            $db_sts = new Application_Model_DbTable_Payments();
            $fields = array('id', 'amount', 'datetime', 'from', 'notes', 'internal_notes', 'type');
        }
        $dd = 0;

        $db = $db_sts->getAdapter();
        if ($s_name != 'patientlogs') {
            if ($s_name == 'payments') {
                $s_data_old = $_SESSION[$s_name . '_data_BK'];
                $s_data_new = session2DB($_SESSION[$s_name . '_data'], 'newpayments');
                $len_new = count($s_data_new);
                $len_old = count($s_data_old);
                $data = array();
                if ($len_new == 0 && $len_old > 0) {
                    $where = $db->quoteInto('claim_id = ?', $s_data_old[0]['claim_id']);
                    $tt = $db_sts->delete($where);
                    return 0;
                }
            } else {
                $s_data_old = $_SESSION[$s_name . '_data_BK'];
                $s_data_new = session2DB($_SESSION[$s_name . '_data'], $s_name);
                $len_new = count($s_data_new);
                $len_old = count($s_data_old);
                $data = array();
                if ($len_new == 0 && $len_old > 0) {
                    $where = $db->quoteInto('claim_id = ?', $s_data_old[0]['claim_id']);
                    $tt = $db_sts->delete($where);
                    return 0;
                }
            }
        } else {
            $s_data_old = $_SESSION[$s_name . '_BK'];
            $s_data_new = session2DB($_SESSION[$s_name], $s_name);
            $len_new = count($s_data_new);
            $len_old = count($s_data_old);
            $data = array();
            if ($len_new == 0 && $len_old > 0) {
                $where = $db->quoteInto('patient_id = ?', $s_data_old[0]['patient_id']);
                $tt = $db_sts->delete($where);
                return 0;
            }
        }
        if ($len_new == $len_old) {
            $j = 0;
            for ($i = 0; $i < $len_new; $i++) {

                foreach ($fields as $key) {
                    if ($s_data_old[$i][$key] != $s_data_new[$i][$key]) {
                        $data[$j] = $s_data_new[$i];
                        $j++;
                        break;
                    }
                }
            }
            return $data;
        }

        if ($len_new < $len_old) {
            $j = 0;
            for ($i = 0; $i < $len_new; $i++) {

                foreach ($fields as $key) {
                    if ($s_data_old[$i][$key] != $s_data_new[$i][$key]) {

                        $data[$j] = $s_data_new[$i];
                        $j++;
                        break;
                    }
                }
            }
            for ($i = $len_new; $i < $len_old; $i++) {
                $where = $db->quoteInto('id = ?', $s_data_old[$i]['id']);
                $tt = $db_sts->delete($where);
                $where = $db->quoteInto('paymentid = ?', $s_data_old[$i]['id']);
                $tt = $db_sts->delete($where);
            }


            return $data;
        }

        if ($len_new > $len_old) {
            $j = 0;
            for ($i = 0; $i < $len_old; $i++) {

                foreach ($fields as $key) {
                    if ($s_data_old[$i][$key] != $s_data_new[$i][$key]) {
                        $data[$j] = $s_data_new[$i];
                        $j++;
                        break;
                    }
                }
            }

            for ($i = $j; $i < $len_new; $i++) {
                $data[$i] = $s_data_new[$i];
            }
            return $data;
        }
    }

    public function downloadAction() {
        //   $this->_helper->viewRenderer->setNoRender();
        session_start();
        $filename = $_SESSION['downloadfilename'];

//          $_SESSION['downloadfilename'] = $filename;
        if (file_exists($filename)) {
//                    header('Content-Description: File Transfer');
//                    header('Content-Type: application/octet-stream');
//                    header('Content-Disposition: attachment; filename=' . basename($filename));
//                    header('Content-Transfer-Encoding: binary');
//                    header('Expires: 0');
//                    header('Cache-Control: must-revalidate');
//                    header('Pragma: public');
//                    header('Content-Length: ' . filesize($filename) . ' bytes');
//                    ob_clean();
//                    flush();
//                    readfile($filename);
            $hail_zip = substr($filename, 5);
            $filename = $config->dir->document_root . $hail_zip;
//                          $filename="http://localhost/sysdoc/statements/test.txt";
            echo $filename;
            unset($_SESSION['downloadfilename']);

            exit;
        }
    }

    public function mannualpaymentbatchAction() {
        if (!$this->getRequest()->isPost()) {
            $billingcompany_id = $this->get_billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto("billingcompany_id = ?", $billingcompany_id);
            $providers = $db_provider->fetchAll($where)->toArray();
            $providerlist = array();
            $i = 0;
            foreach ($providers as $row) {
                $providerlist[$i]['display'] = $row["provider_name"];
                $providerlist[$i]['id'] = $row['id'];
                $i++;
            }

            function compareProvider($a, $b) {
                if ($a['display'] == $b['display'])
                    return 0;
                $result = ($a['display'] > $b['display']) ? 1 : -1;
                return $result;
            }

            uasort($providerlist, "compareProvider");
            $this->view->providerlist = $providerlist;
            $insurancelist = array();
            $i = 0;
            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto("billingcompany_id = ?", $billingcompany_id);
            $insurances = $db_insurance->fetchAll($where)->toArray();
            foreach ($insurances as $row) {
                $insurancelist[$i]['name'] = $row['insurance_display'];
                $i++;
            }

            function compareInsurance($a, $b) {
                if ($a['name'] == $b['name'])
                    return 0;
                $result = ($a['name'] > $b['name']) ? 1 : -1;
                return $result;
            }

            uasort($insurancelist, "compareInsurance");
            $this->view->insurancelist = $insurancelist;
            session_start();
            if (isset($_SESSION['batch_state'])) {
                $this->view->state = $_SESSION['batch_state'];
                $this->view->results = $_SESSION['batch_results'];
                $tmp_payments_data = $_SESSION['batch_payments_data'];
                $i = 0;
                foreach ($tmp_payments_data as $row) {
                    $tmp_payments_data[$i++]["DOS"] = format($row["DOS"], 1);
                }
                $this->view->payments = $tmp_payments_data;
                $this->view->providerId = $_SESSION['batch_provider_id'];
                $this->view->insurance = $_SESSION['batch_insurance'];
                $this->view->total = $_SESSION["batch_total"];
                if ($_SESSION['batch_state'] == "post") {
                    $results = $_SESSION['batch_results'];
                    $post_results = array();
                    $index = 0;
                    foreach ($results as $result) {
                        if ($result['status'] == "Success") {
                            $claim_id = $result['claim_id'];
                            $db_claim = new Application_Model_DbTable_Claim();
                            $db = $db_claim->getAdapter();
                            $where = $db->quoteInto("id=?", $claim_id);
                            $claim_data = $db_claim->fetchAll($where)->toArray();
                            $claim = $claim_data[0];
                            $post_results[$index] = array();
                            $post_results[$index]['Name'] = $result['Name'];
                            $post_results[$index]['DOB'] = $result['DOB'];
                            $post_results[$index]['MRN'] = $result['MRN'];
                            $post_results[$index]['DOS'] = $result['DOS'];
                            $claim_status_tmp = $claim['claim_status'];
                            $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                            $db_c = $db_claimstatus->getAdapter();
                            $where = $db_c->quoteInto("claim_status = ?", $claim_status_tmp);
                            $status_row = $db_claimstatus->fetchRow($where);
                            $post_results[$index]['CS'] = $status_row['claim_status_display'];
                            //$post_results[$index]['CS'] = $claim['claim_status'];
                            $post_results[$index]['total_charge'] = floatval($claim['total_charge']);
                            //$post_results[$index]['posted_amount'] = $result['postingAmount'];
                            $payment_id = $result['payment_id'];
                            $db_payment = new Application_Model_DbTable_Payments();
                            $db = $db_payment->getAdapter();
                            $where = $db->quoteInto("id=?", $payment_id);
                            $payment_data = $db_payment->fetchAll($where)->toArray();
                            $post_results[$index]['posted_amount'] = isset($payment_data[0]['amount']) ? floatval($payment_data[0]['amount']) : 0;
                            $post_results[$index]['allowed_amount'] = isset($claim['EOB_allowed_amount']) ? floatval($claim['EOB_allowed_amount']) : null;
                            $post_results[$index]['amount_paid'] = floatval($claim['amount_paid']);
                            $post_results[$index]['balance_due'] = floatval($claim['balance_due']);
                            $post_results[$index]['status'] = $result['status'];
                        } else {
                            $post_results[$index] = array();
                            $post_results[$index]['Name'] = $result['Name'];
                            $post_results[$index]['DOB'] = $result['DOB'];
                            $post_results[$index]['MRN'] = $result['MRN'];
                            $post_results[$index]['DOS'] = $result['DOS'];
                            $post_results[$index]['status'] = $result['status'];
                        }
                        $index++;
                    }
                    $_SESSION['batch_state_bk'] = $_SESSION['batch_state'];
                    $this->view->post_results = $post_results;
                }
                $_SESSION['batch_state_bk'] = $_SESSION['batch_state'];
                unset($_SESSION['batch_state']);
            } else {
                $this->view->state = "Normal";
            }
        }
        if ($this->getRequest()->isPost()) {
            $paras = array("MRN" => "payment_MRN_", "DOS" => "payment_DOS_", "Received" => "payment_Received_", "Allowed" => "payment_Allowed_",
                "Not Allowed" => "payment_Not_Allowed_", "Co Insurance" => "payment_Co_Insurance_", "Deducible" => "payment_Deducible_", "EOB Reduction" => "payment_EOB_Reduction_",
                "EOB Other Reduction" => "payment_EOB_Other_Reduction_", "Adjustment Reason" => "payment_EOB_Adjustment_Reason_", "Notes" => "payment_EOB_Notes_", "Other Notes" => "payment_EOB_Internal_Notes_");
            $type = $_POST['submit'];
            session_start();
            if ($type == "Look Up") {
                $payments_data = array();
                $amount = $_POST["paymentcount"];
                if ($amount > 0) {
                    $j = 0;
                    for ($i = 1; $i <= $amount; $i++) {
                        $index_mrn = "payment_MRN_" . $i;
                        $mrn_tmp = $_POST[$index_mrn];
                        if ($mrn_tmp != "" && $mrn_tmp != null) {
                            $payments_data[$j] = array();
                            foreach ($paras as $key => $val) {
                                if ($key == "DOS") {
                                    $index_tmp = $val . $i;
                                    $tmp_date = $_POST[$index_tmp];
                                    $payments_data[$j][$key] = format($tmp_date, 0);
                                } else {
                                    $index_tmp = $val . $i;
                                    $payments_data[$j][$key] = $_POST[$index_tmp];
                                }
                            }
                            $j++;
                        }
                    }
                    $_SESSION['batch_payments_data'] = $payments_data;
                }
                $provider_id = $_POST['Provider_id'];
                $insurance = $_POST['insurance'];
                $payment_count = count($payments_data);
                $i = 0;
                $total = 0;
                $result = array();
                foreach ($payments_data as $payment) {
                    $result[$i] = array();
                    $MRN = $payment["MRN"];
                    $DOS = $payment["DOS"];
                    $paras_test = array("Allowed", "Not Allowed", "Co Insurance", "Deducible", "EOB Reduction", "EOB Other Reduction", "Adjustment Reason");
                    $test_flag = 0;
                    for ($tmp = 1; $tmp < count($paras_test); $tmp++) {
                        if ($payment[$paras_test[$tmp]] != '' && $payment[$paras_test[$tmp]] != null) {
                            $test_flag = 1;
                        }
                    }
                    $db_patient = new Application_Model_DbTable_Patient();
                    $db = $db_patient->getAdapter();
                    $where = $db->quoteInto("account_number = ?", $MRN);
                    $patient_datas = $db_patient->fetchAll($where)->toArray();
                    $patient_data = array();
                    $index_patient = 0;
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    foreach ($patient_datas as $patient_row) {
                        $patient_id_row = $patient_row['id'];
                        $where = $db->quoteInto("patient_id=?", $patient_id_row) . $db->quoteInto("AND provider_id=?", $provider_id);
                        $encounter_data = $db_encounter->fetchAll($where)->toArray();
                        if (count($encounter_data) > 0) {
                            $patient_data[$index_patient] = $patient_row;
                            $index_patient++;
                        }
                    }
                    if (count($patient_data) > 1) {
                        $result[$i]['status'] = 'Repeated Patient Error';
                        $result[$i]['postingAmount'] = 0;
                        $i++;
                        continue;
                    }
                    if (count($patient_data) == 0) {
                        $result[$i]['status'] = 'No Patient Error';
                        $result[$i]['postingAmount'] = 0;
                        $i++;
                        continue;
                    }
                    $patient = $patient_data[0];
                    $patient_id = $patient['id'];
                    //match right patient and save the matched patient info
                    $result[$i]['Name'] = $patient['last_name'] . ', ' . $patient['first_name'];
                    $result[$i]['DOB'] = format($patient['DOB'], 1);
                    $result[$i]['MRN'] = $MRN;
                    $result[$i]['patient_id'] = $patient_id;
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto("patient_id=?", $patient_id) . $db->quoteInto("AND start_date_1=?", $DOS) . $db->quoteInto("AND provider_id=?", $provider_id);
                    $encounter_data = $db_encounter->fetchAll($where)->toArray();
                    if (count($encounter_data) > 1) {
                        $result[$i]['status'] = 'Repeated Claim Error';
                        $result[$i]['postingAmount'] = 0;
                        $i++;
                        continue;
                    }
                    if (count($encounter_data) == 0) {
                        $result[$i]['status'] = 'No Service Error';
                        $result[$i]['postingAmount'] = 0;
                        $i++;
                        continue;
                    }
                    $encounter = $encounter_data[0];
                    $claim_id = $encounter['claim_id'];
                    $db_claim = new Application_Model_DbTable_Claim();
                    $db = $db_claim->getAdapter();
                    $where = $db->quoteInto("id=?", $claim_id);
                    $claim_data = $db_claim->fetchAll($where)->toArray();
                    if (count($claim_data) == 1) {
                        $claim = $claim_data[0];
                        //match the right claim and save the matched claim info
                        $result[$i]['DOS'] = format($DOS, 1);
                        $result[$i]['encounter_id'] = $encounter['id'];
                        $result[$i]['claim_id'] = $claim_id;
                        //check Allowed/Not Allowed Amount
                        if ($test_flag == 1) {
                            if (($claim["EOB_not_allowed_amount"] != null && $claim["EOB_not_allowed_amount"] != '') || ($claim["EOB_allowed_amount"] != null && $claim["EOB_allowed_amount"] != '')) {
                                $result[$i]['status'] = "EOB Conflict";
                                $result[$i]['postingAmount'] = 0;
                                $result[$i]['allowed_amount'] = $claim['EOB_allowed_amount'];
                                $i++;
                                continue;
                            }
                        }
                        $result[$i]['provider_id'] = $provider_id;
                        $result[$i]['insurance'] = $insurance;
                        $result[$i]['patient_id'] = $patient_id;
                        $result[$i]['Name'] = $patient['last_name'] . ', ' . $patient['first_name'];
                        $result[$i]['DOB'] = format($patient['DOB'], 1);
                        $result[$i]['MRN'] = $MRN;
                        $result[$i]['postingAmount'] = abs(floatval($payment['Received']));
                        $claim_status_tmp = $claim['claim_status'];
                        $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                        $db = $db_claimstatus->getAdapter();
                        $where = $db->quoteInto("claim_status = ?", $claim_status_tmp);
                        $status_row = $db_claimstatus->fetchRow($where);
                        $result[$i]['CS'] = $status_row['claim_status_display'];
                        //caculate the related amount
                        $result[$i]['total_charge'] = floatval($claim['total_charge']);
                        //$result[$i]['allowed_amount'] = $claim['EOB_allowed_amount'];
                        $result[$i]['allowed_amount'] = $payment['allowed'];
                        $result[$i]['amount_paid_bf'] = floatval($claim['amount_paid']);
                        $result[$i]['balance_due_bf'] = floatval($claim['balance_due']);
                        $result[$i]['amount_paid'] = floatval($claim['amount_paid']) + abs(floatval($payment['Received']));
                        $result[$i]['balance_due'] = floatval($claim['total_charge']) - $result[$i]['amount_paid'];
                        $result[$i]['status'] = "Success";
                        $total = $total + abs(floatval($payment['Received']));
                    } else {
                        $result[$i]['status'] = "Claim Error";
                    }
                    $i++;
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                $fileInfo = $adapter->getFileInfo();
                foreach ($fileInfo as $file => $info) {
                    if ($adapter->isValid($file)) {
                        $today = date("Y-m-d H:i:s");
                        $date = explode(' ', $today);
                        $time0 = explode('-', $date[0]);
                        $time1 = explode(':', $date[1]);
                        $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $dir = $this->sysdoc_path . '/' . 'tmpfile';
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                        $file_name = $time . $user_name . '.pdf';
                        $adapter->addFilter('Rename', array('target' => $file_name), $file);
                        $adapter->setDestination($dir);
                        $adapter->receive($file);
                        $_SESSION['batch_new_upload_file'] = $file_name;
                    }
                }
                $_SESSION['batch_results'] = $result;
                $_SESSION['batch_total'] = $total;
                $_SESSION['batch_provider_id'] = $provider_id;
                $_SESSION['batch_insurance'] = $insurance;
                $_SESSION['batch_state'] = "Look Up";
                $this->_redirect('/biller/claims/mannualpaymentbatch');
            } else if ($type == "POST") {
                session_start();
                $results = $_SESSION['batch_results'];
                $payments = $_SESSION['batch_payments_data'];
                $provider_id = $_SESSION['batch_provider_id'];
                $insurance = $_SESSION['batch_insurance'];
                $filepath = $_SESSION['batch_new_upload_file'];
                $total = $_SESSION['batch_total'];
                $count_result = count($results);
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto("id=?", $provider_id);
                $providerData = $db_provider->fetchRow($where);
                $provider_name = $providerData['provider_name'];
                $this->set_options_session($provider_id, $provider_name, $this->get_billingcompany_id());
                $autoposting = $_SESSION['options']['autoposting'];
                for ($i = 0; $i < $count_result; $i++) {
                    if ($results[$i]['status'] == "Success") {
                        $payment_add['amount'] = ($payments[$i]["Received"] == "") ? 0 : floatval($payments[$i]['Received']);
                        $payment_add['datetime'] = date("Y-m-d H:i:s");
                        $time_in_log = format($payment_add['datetime'], 7);
                        $payment_add['from'] = isset($insurance) ? $insurance : "";
                        $payment_add['notes'] = $payments[$i]['Notes'];
                        $payment_add['internal_notes'] = $payments[$i]['Other Notes'];
                        $payment_add['claim_id'] = $results[$i]['claim_id'];
                        $table_name = "payments";
                        $db_payment = new Application_Model_DbTable_Payments();
                        $db_interaction = new Application_Model_DbTable_Interactionlog();
                        if ($payment_add['amount'] != "" && $payment_add['amount'] != null) {
                            $db = $db_payment->getAdapter();
                            $db->insert($table_name, $payment_add);
                            $results[$i]['payment_id'] = $db->lastInsertId();
                            $payment_log_save['date_and_time'] = $payment_add['datetime'];
                            $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                            $payment_log_save['log'] = $user_name . ": Added Payment " . $payment_add['amount'] . '|' . $time_in_log . '|' . $payment_add['from'] . (isset($payment_add['notes']) ? ('|' . $payment_add['notes']) : "") . (isset($payment_add['internal_notes']) ? ('|' . $payment_add['internal_notes']) : "");
                            $db = $db_interaction->getAdapter();
                            $table_name = 'interactionlog';
                            $db->insert($table_name, $payment_log_save);
                        }
                        /* $EOB_Update = array("EOB_co_insurance"=>$payments[$i]['Co Insurance'],"EOB_deductable"=>$payments[$i]['Deducible'],
                          "EOB_reduction"=>$payments[$i]['EOB Reduction'],"EOB_other_reduction"=>$payments[$i]['EOB Other Reduction'],
                          "EOB_adjustment_reason"=>$payments[$i]['Adjustment Reason'],); */
                        $db_claim = new Application_Model_DbTable_Claim();
                        $db = $db_claim->getAdapter();
                        $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                        $claim_data = $db_claim->fetchRow($where);
                        $total_charge = $claim_data['total_charge'];
                        $new_amount_paid = floatval($claim_data['amount_paid']) + $payment_add['amount'];
                        $old_balance_due = floatval($claim_data['balance_due']);
                        $biller_adjustment = $old_balance_due + floatval($claim_data['amount_paid']) - floatval($total_charge);
                        $new_balance_due = floatval($total_charge) - $new_amount_paid + $biller_adjustment;
                        $claim_change_flag = 0;
                        if ($autoposting == "1" || $autoposting == "2") {
                            $expected = floatval($claim_data['expected_payment']);
                            $allowed = floatval($claim_data['EOB_allowed_amount']);
                            if (($expected != null && $expected != "" && $new_amount_paid >= $expected) || ($allowed != null && $allowed != '' && $new_amount_paid >= $allowed)) {
                                $billeramount = floatval($total_charge) - $new_amount_paid;
                                $billerpayment = array();
                                $billerpayment['amount'] = -$billeramount;
                                $billerpayment['datetime'] = date("Y-m-d H:i:s");
                                $time_in_log = format($billerpayment['datetime'], 7);
                                $billerpayment['from'] = "Biller Adjustment";
                                $billerpayment['notes'] = "";
                                $billerpayment['internal_notes'] = "";
                                $billerpayment['claim_id'] = $results[$i]['claim_id'];
                                $db_p = $db_payment->getAdapter();
                                $db_p->insert("payments", $billerpayment);
                                $payment_log_save['date_and_time'] = $billerpayment['datetime'];
                                $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                                $payment_log_save['log'] = $user_name . ": Added Payment " . $billerpayment['amount'] . '|' . $time_in_log . '|' . $billerpayment['from'];
                                $db_i = $db_interaction->getAdapter();
                                $db_i->insert("interactionlog", $payment_log_save);
                                $new_balance_due = 0;
                                if ($autoposting == "2") {
                                    $update = array("claim_status" => "closed_payment_as_expected");
                                    $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                                    $db->update("claim", $update, $where);
                                    $claim_change_flag = 1;
                                    $tmp_interactionlog['claim_id'] = $results[$i]['claim_id'];
                                    $tmp_interactionlog['date_and_time'] = date("Y-m-d H:i:s");
                                    $tmp_interactionlog['log'] = $user_name . ": Changed Claim Status from " . $results[$i]['CS'] . " to Closed-payment as expected";
                                    $db_i->insert("interactionlog", $tmp_interactionlog);
                                }
                            }
                        }
                        if ($autoposting == "3" || $autoposting == "2,3") {
                            $billeramount = floatval($total_charge) - $new_amount_paid;
                            $billerpayment = array();
                            $billerpayment['amount'] = -$billeramount;
                            $billerpayment['datetime'] = date("Y-m-d H:i:s");
                            $time_in_log = format($billerpayment['datetime'], 7);
                            $billerpayment['from'] = "Biller Adjustment";
                            $billerpayment['notes'] = "";
                            $billerpayment['internal_notes'] = "";
                            $billerpayment['claim_id'] = $results[$i]['claim_id'];
                            $db_p = $db_payment->getAdapter();
                            $db_p->insert("payments", $billerpayment);
                            $payment_log_save['date_and_time'] = $billerpayment['datetime'];
                            $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                            $payment_log_save['log'] = $user_name . ": Added Payment " . $billerpayment['amount'] . '|' . $time_in_log . '|' . $billerpayment['from'];
                            $db_i = $db_interaction->getAdapter();
                            $db_i->insert("interactionlog", $payment_log_save);
                            $new_balance_due = 0;
                            if ($autoposting == "2,3") {
                                $update = array("claim_status" => "closed_payment_as_expected");
                                $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                                $db->update("claim", $update, $where);
                                $claim_change_flag = 1;
                                $tmp_interactionlog['claim_id'] = $results[$i]['claim_id'];
                                $tmp_interactionlog['date_and_time'] = date("Y-m-d H:i:s");
                                $tmp_interactionlog['log'] = $user_name . ": Changed Claim Status from " . $results[$i]['CS'] . " to Closed-payment as expected";
                                $db_i->insert("interactionlog", $tmp_interactionlog);
                            }
                        }
                        $table_name = "claim";
                        //$db->update($table_name,$EOB_Update,$where);
                        if ($payment_add['amount'] != 0) {
                            $update = array("amount_paid" => $new_amount_paid, "balance_due" => $new_balance_due);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['Co Insurance'] != null && $payments[$i]['Co Insurance'] != "") {
                            $EOB_Update = array("EOB_co_insurance" => $payments[$i]['Co Insurance']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['Deducible'] != null && $payments[$i]['Deducible'] != "") {
                            $EOB_Update = array("EOB_deductable" => $payments[$i]['Deducible']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['EOB Reduction'] != null && $payments[$i]['EOB Reduction'] != "") {
                            $EOB_Update = array("EOB_reduction" => $payments[$i]['EOB Reduction']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['EOB Other Reduction'] != null && $payments[$i]['EOB Other Reduction'] != "") {
                            $EOB_Update = array("EOB_other_reduction" => $payments[$i]['EOB Other Reduction']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['Adjustment Reason'] != null && $payments[$i]['Adjustment Reason'] != "") {
                            $EOB_Update = array("EOB_adjustment_reason" => $payments[$i]['Adjustment Reason']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['Allowed'] != null && $payments[$i]['Allowed'] != "") {
                            $EOB_Update = array("EOB_allowed_amount" => $payments[$i]['Allowed']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($payments[$i]['Not Allowed'] != null && $payments[$i]['Not Allowed'] != "") {
                            $EOB_Update = array("EOB_not_allowed_amount" => $payments[$i]['Not Allowed']);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($claim_change_flag == 1) {
                            $cur_time = date("Y-m-d H:i:s");
                            $claim_change_update['update_time'] = $cur_time;
                            $claim_change_update['update_user'] = $user_name;
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $claim_change_update, $where);
                            $claim_change_flag = 0;
                        }
                        if (isset($filepath)) {
                            $billingcompany_id = $this->get_billingcompany_id();
                            $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($dir_billingcompany)) {
                                mkdir($dir_billingcompany);
                            }
                            $dir_provider = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($dir_provider)) {
                                mkdir($dir_provider);
                            }
                            $dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($dir_claim)) {
                                mkdir($dir_claim);
                            }
                            $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $results[$i]['claim_id'];
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            $old_file = $this->sysdoc_path . '/' . "tmpfile/" . $filepath;
                            $today = date("Y-m-d H:i:s");
                            $date = explode(' ', $today);
                            $time0 = explode('-', $date[0]);
                            $time1 = explode(':', $date[1]);
                            $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                            $user = Zend_Auth::getInstance()->getIdentity();
                            $user_name = $user->user_name;
                            $new_name = $time . '-EOB-' . $user_name . '.pdf';
                            $new_file = $dir . '/' . $new_name;
                            $result = copy($old_file, $new_file);
                            if ($result && $i == ($count_result - 1)) {
                                unlink($old_file);
                            }
                        }
                    }
                }
                $root_dir = $this->sysdoc_path;
                /* $log_dir = $root_dir. '/billingcompany';
                  if (!is_dir($log_dir)) {
                  mkdir($log_dir);
                  } */
                $log_dir = $root_dir;
                $log_dir = $log_dir . '/' . $this->billingcompany_id;
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }
                $log_dir = $log_dir . '/batchlog';
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }
                $log_file_name = $log_dir . '/ManualBatchLog.csv';
                $display_fields = array('Name', 'DOB', 'MRN', 'DOS', 'Claim Status', 'Posted Amount', 'Look Status');
                $fields = array('Name', 'DOB', 'MRN', 'DOS', 'CS', 'postingAmount', 'status');
                $final_length = count($display_fields);
                if ($count_result > 0) {
                    if (file_exists($log_file_name)) {
                        $rp = fopen($log_file_name, 'r');
                        $tmp_log_file = $log_dir . '/tmp.csv';
                        $wp = fopen($tmp_log_file, 'w');
                        //write date and time
                        $cur_time = date("Y-m-d H:i:s");
                        fwrite($wp, "Date and Time: ," . $cur_time . ',');
                        fwrite($wp, "\r\n");
                        //wirte the titles
                        for ($i = 0; $i < $final_length; $i++) {
                            fwrite($wp, $display_fields[$i] . ",");
                        }
                        fwrite($wp, "\r\n");
                        //write the results
                        for ($i = 0; $i < $count_result; $i++) {
                            for ($j = 0; $j < $final_length; $j++) {
                                //$ttt = $data[$i][$fields[$j]];
                                if ($j == 0) {
                                    $name = str_replace(',', '', $results[$i][$fields[$j]]);
                                    fwrite($wp, $name . ',');
                                } else if ($j == 2) {
                                    $mrn = "=\"" . $results[$i][$fields[$j]] . "\"";
                                    fwrite($wp, $mrn . ',');
                                } else {
                                    fwrite($wp, strval($results[$i][$fields[$j]]) . ",");
                                }
                            }
                            fwrite($wp, "\r\n");
                        }
                        fwrite($wp, "Total: " . "," . strval($total) . ',');
                        fwrite($wp, "\r\n");
                        fwrite($wp, "\r\n");
                        $read_old_log = fgets($rp);
                        while (!feof($rp)) {
                            fwrite($wp, $read_old_log);
                            $read_old_log = fgets($rp);
                        }
                        fwrite($wp, $read_old_log);
                        fclose($wp);
                        fclose($rp);

                        if (!unlink($log_file_name))
                            echo ("Error deleting $log_file_name");
                        else
                            echo ("Deleted $log_file_name");

                        rename($tmp_log_file, $log_file_name);
                    }else {
                        $fp = fopen($log_file_name, 'w');
                        //write date and time
                        $cur_time = date("Y-m-d H:i:s");
                        fwrite($fp, "Date and Time: ," . $cur_time . ',');
                        fwrite($fp, "\r\n");
                        //write fields
                        for ($i = 0; $i < $final_length; $i++) {
                            fwrite($fp, $display_fields[$i] . ",");
                        }
                        fwrite($fp, "\r\n");

                        //write results
                        for ($i = 0; $i < $count_result; $i++) {
                            for ($j = 0; $j < $final_length; $j++) {
                                //$ttt = $data[$i][$fields[$j]];
                                if ($j == 0) {
                                    $name = str_replace(',', '', $results[$i][$fields[$j]]);
                                    fwrite($fp, $name . ',');
                                } else if ($j == 2) {
                                    $mrn = $results[$i][$fields[$j]];
                                    fwrite($wp, $mrn . ',');
                                } else {
                                    fwrite($fp, strval($results[$i][$fields[$j]]) . ",");
                                }
                            }
                            fwrite($fp, "\r\n");
                        }
                        //write total amount
                        fwrite($fp, "Total: " . "," . strval($total) . ',');
                        fwrite($fp, "\r\n");
                        fclose($fp);
                    }
                }
                /* unset($_SESSION['batch_results']);
                  unset($_SESSION['batch_total']);
                  unset($_SESSION['batch_state']);
                  unset($_SESSION['batch_payments_data']);
                  unset($_SESSION['batch_provider_id']);
                  unset($_SESSION['batch_insurance']); */
                $_SESSION['batch_results'] = $results;
                $_SESSION['batch_state'] = "post";
                $this->_redirect('/biller/claims/mannualpaymentbatch');
            } else if ($type == "JUMP") {
                $index = $_POST['jump_index'];
                $results = $_SESSION['batch_results'];
                $encounter_id = $results[$index]['encounter_id'];
                $patient_id = $results[$index]['patient_id'];
                $this->set_options_session();
                session_start();
                $options = $_SESSION['options'];
                $_SESSION['batch_flag'] = 1;
                $_SESSION['batch_state'] = $_SESSION['batch_state_bk'];
                if ($patient_id != null) {
                    $this->initsession($patient_id, $encounter_id);
                    //statement triger 5 status not close 
                    $patient_data = $_SESSION['patient_data'];
                    $start_date_I = format($patient_data['date_statement_I'], 0);
                    $start_date_II = format($patient_data['date_statement_II'], 0);
                    $end_date = date("Y-m-d");
                    if ($start_date_II != null) {
                        $days = days($start_date_II, $end_date);
                        if ($days >= $options['patient_statement_interval']) {
                            $_SESSION['patient_data']['statement'] = '3';
                            $_SESSION['patient_data']['statement_trigger'] = '5';
                        }
                    } else {
                        if ($start_date_I != null) {
                            $days = days($start_date_I, $end_date);
                            if ($days >= $options['patient_statement_interval']) {
                                $_SESSION['patient_data']['statement'] = '2';
                                $_SESSION['patient_data']['statement_trigger'] = '5';
                            }
                        }
                    }
                    if ($encounter_id != null)
                        $this->_redirect('/biller/claims/claim');
                    else {
                        /*                         * *Add check for the new claim** */
                        session_start();
                        $_SESSION['new_claim_flag'] = 1;
                        $_SESSION['new_upload_file'] = null;
                        /*                         * *Add check for the new claim** */
                        $db_encounter = new Application_Model_DbTable_Encounter();
                        $db = $db_encounter->getAdapter();
                        $where = $db->quoteInto('patient_id = ?', $patient_id);
                        $encounter_data = $db_encounter->fetchAll($where, "start_date_1 DESC");

                        $encounter['provider_id'] = $encounter_data[0]['provider_id'];
                        $encounter['renderingprovider_id'] = $encounter_data[0]['renderingprovider_id'];
                        $encounter['facility_id'] = $encounter_data[0]['facility_id'];
                        $pos = $this->get_pos($encounter['facility_id']);

                        $encounter['referringprovider_id'] = $encounter_data[0]['referringprovider_id'];

                        $encounter['place_of_service_1'] = $pos;
                        $encounter['place_of_service_2'] = $pos;
                        $encounter['place_of_service_3'] = $pos;
                        $encounter['place_of_service_4'] = $pos;
                        $encounter['place_of_service_5'] = $pos;
                        $encounter['place_of_service_6'] = $pos;
                        $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];

                        $_SESSION['encounter_data'] = $encounter;
                        $this->_redirect('/biller/claims/services');
                    }
                } else {
                    $dd = 0;
                    $this->clearsession();
                    $default = getdefault();
                    if ($default['provider'] != null) {
                        $encounter['provider_id'] = $default['provider'];
                        $encounter['renderingprovider_id'] = $default['renderingprovider'];
                        $encounter['facility_id'] = $default['facility'];
                        $pos = $this->get_pos($encounter['facility_id']);

                        $encounter['referringprovider_id'] = $default['referringprovider'];

                        $encounter['place_of_service_1'] = $pos;
                        $encounter['place_of_service_2'] = $pos;
                        $encounter['place_of_service_3'] = $pos;
                        $encounter['place_of_service_4'] = $pos;
                        $encounter['place_of_service_5'] = $pos;
                        $encounter['place_of_service_6'] = $pos;
                    } else {
                        $encounter['provider_id'] = '';
                        $encounter['renderingprovider_id'] = '';
                        $encounter['facility_id'] = '';
                        $encounter['referringprovider_id'] = '';
                        $encounter['place_of_service_1'] = '';
                    }
                    $encounter['accept_assignment'] = $options['yes_for_assingment_of_benefits'];
                    session_start();
                    $_SESSION['encounter_data'] = $encounter;
                    $_SESSION['patient_data']['account_number'] = getmrn($this->get_billingcompany_id(), 0);
                    $_SESSION['claim_data']['mannual_flag'] = "no";
                    $_SESSION['new_claim_flag'] = 1;
                    $_SESSION['new_patient_flag'] = 1;
                    $_SESSION['new_upload_file'] = null;
                    $this->_redirect('/biller/claims/patient');
                }
            } else if ($type == "Check Manual Batch Log") {
                $billingcompany_id = $this->get_billingcompany_id();
                //$log_file_name = $this->sysdoc_path . '/billingcompany/' . $billingcompany_id . '/batchlog';
                $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/batchlog';
                $log_file_name = $log_file_name . '/ManualBatchLog.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    //ob_clean();
                    //flush();
                    readfile($log_file_name);
                    exit;
                }
                $this->_redirect('/biller/claims/mannualpaymentbatch');
            } else if ($type == "Migrate") {
                $db_insurancepayment = new Application_Model_DbTable_Insurancepayments();
                $insurancePayments = $db_insurancepayment->fetchAll()->toArray();
                $payment_length = count($insurancePayments);
                $db_payments = new Application_Model_DbTable_Payments();
                $db = $db_payments->getAdapter();
                $table_name = "payments";
                $migrateData = array();
                for ($i = 0; $i < $payment_length; $i++) {
                    $migrateData['claim_id'] = $insurancePayments[$i]['claim_id'];
                    $migrateData['internal_notes'] = $insurancePayments[$i]['notes'];
                    $migrateData['from'] = "insurance";
                    $migrateData['datetime'] = $insurancePayments[$i]['date'];
                    $migrateData['amount'] = $insurancePayments[$i]['amount'];
                    $migrateData['notes'] = "";
                    $db->insert($table_name, $migrateData);
                }
                $db_patientpayment = new Application_Model_DbTable_Patientpayments();
                $patientPayments = $db_patientpayment->fetchAll()->toArray();
                $payment_length = count($patientPayments);
                $db_payments = new Application_Model_DbTable_Payments();
                $db = $db_payments->getAdapter();
                $table_name = "payments";
                $migrateData = array();
                for ($i = 0; $i < $payment_length; $i++) {
                    $migrateData['claim_id'] = $patientPayments[$i]['claim_id'];
                    $migrateData['internal_notes'] = $patientPayments[$i]['notes'];
                    $migrateData['from'] = "patient";
                    $migrateData['datetime'] = $patientPayments[$i]['date'];
                    $migrateData['amount'] = $patientPayments[$i]['amount'];
                    $migrateData['notes'] = "";
                    $db->insert($table_name, $migrateData);
                }
                $db_bapayment = new Application_Model_DbTable_Billeradjustments();
                $baPayments = $db_bapayment->fetchAll()->toArray();
                $payment_length = count($baPayments);
                $db_payments = new Application_Model_DbTable_Payments();
                $db = $db_payments->getAdapter();
                $table_name = "payments";
                $migrateData = array();
                for ($i = 0; $i < $payment_length; $i++) {
                    $migrateData['claim_id'] = $baPayments[$i]['claim_id'];
                    $migrateData['internal_notes'] = $baPayments[$i]['notes'];
                    $migrateData['from'] = "billeradjustment";
                    $migrateData['datetime'] = $baPayments[$i]['date'];
                    $migrateData['amount'] = $baPayments[$i]['amount'];
                    $migrateData['notes'] = "";
                    $db->insert($table_name, $migrateData);
                }
            }
        }
    }

    public function getclaiminfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $MRN = $_POST['mrn'];
        $DOS = $_POST['dos'];
        $DOS = format($DOS, 0);
        $provider_id = $_POST['provider_id'];
        $db_patient = new Application_Model_DbTable_Patient();
        $db = $db_patient->getAdapter();
        $where = $db->quoteInto("account_number = ?", $MRN);
        $patient_datas = $db_patient->fetchAll($where)->toArray();
        $patient_data = array();
        $index_patient = 0;
        $db_encounter = new Application_Model_DbTable_Encounter();
        $db = $db_encounter->getAdapter();
        foreach ($patient_datas as $patient_row) {
            $patient_id_row = $patient_row['id'];
            $where = $db->quoteInto("patient_id=?", $patient_id_row) . $db->quoteInto("AND provider_id=?", $provider_id);
            $encounter_data = $db_encounter->fetchAll($where)->toArray();
            if (count($encounter_data) > 0) {
                $patient_data[$index_patient] = $patient_row;
                $index_patient++;
            }
        }
        $data = array();
        $data['result'] = "error";
        if (count($patient_data) == 1) {
            $patient = $patient_data[0];
            $patient_id = $patient['id'];
            $db_encounter = new Application_Model_DbTable_Encounter();
            $db = $db_encounter->getAdapter();
            $where = $db->quoteInto("patient_id=?", $patient_id) . $db->quoteInto("AND start_date_1=?", $DOS) . $db->quoteInto("AND provider_id=?", $provider_id);
            $encounter_data = $db_encounter->fetchAll($where)->toArray();
            if (count($encounter_data) == 1) {
                $encounter = $encounter_data[0];
                $claim_id = $encounter['claim_id'];
                $db_claim = new Application_Model_DbTable_Claim();
                $db = $db_claim->getAdapter();
                $where = $db->quoteInto("id=?", $claim_id);
                $claim_data = $db_claim->fetchAll($where)->toArray();
                if ($claim_data) {
                    $data['result'] = $claim_data[0]['total_charge'];
                }
            }
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function erapaymentbatchAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            //if(isset($_SESSION['transfer_state'])&&$_SESSION['transfer_state']==true){
            if (isset($_SESSION['transfer_state'])) {
                $this->view->transfer_state = $_SESSION['transfer_state'];
                $this->view->provider_name = $_SESSION['provider_data']['provider_name'];
                $this->view->insurance_name = $_SESSION['insurance_name'];
                $this->view->payments = $_SESSION['results'];
                $this->view->total = $_SESSION['total'];
                if ($_SESSION['transfer_state'] == "Posted") {
                    $results = $_SESSION['results'];
                    $post_results = array();
                    $index = 0;
                    foreach ($results as $result) {
                        if ($result['status'] == "Success") {
                            $claim_id = $result['claim_id'];
                            $db_claim = new Application_Model_DbTable_Claim();
                            $db = $db_claim->getAdapter();
                            $where = $db->quoteInto("id=?", $claim_id);
                            $claim_data = $db_claim->fetchAll($where)->toArray();
                            $claim = $claim_data[0];
                            $post_results[$index] = array();
                            $post_results[$index]['Name'] = $result['Name'];
                            $post_results[$index]['DOB'] = $result['DOB'];
                            $post_results[$index]['MRN'] = $result['MRN'];
                            $post_results[$index]['DOS'] = $result['DOS'];
                            $claim_status_tmp = $claim['claim_status'];
                            $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                            $db_c = $db_claimstatus->getAdapter();
                            $where = $db_c->quoteInto("claim_status = ?", $claim_status_tmp);
                            $status_row = $db_claimstatus->fetchRow($where);
                            $post_results[$index]['CS'] = $status_row['claim_status_display'];
                            //$post_results[$index]['CS'] = $claim['claim_status'];
                            $post_results[$index]['total_charge'] = floatval($claim['total_charge']);
                            //$post_results[$index]['posted_amount'] = $result['postingAmount'];
                            $payment_id = $result['payment_id'];
                            $db_payment = new Application_Model_DbTable_Payments();
                            $db = $db_payment->getAdapter();
                            $where = $db->quoteInto("id=?", $payment_id);
                            $payment_data = $db_payment->fetchAll($where)->toArray();
                            $post_results[$index]['posted_amount'] = isset($payment_data[0]['amount']) ? floatval($payment_data[0]['amount']) : 0;
                            $post_results[$index]['allowed_amount'] = isset($claim['EOB_allowed_amount']) ? floatval($claim['EOB_allowed_amount']) : null;
                            $post_results[$index]['amount_paid'] = floatval($claim['amount_paid']);
                            $post_results[$index]['balance_due'] = floatval($claim['balance_due']);
                            $post_results[$index]['service_lines'] = $result['service_lines'];
                            $post_results[$index]['eob_datas'] = $result['eob_datas'];
                            $post_results[$index]['status'] = $result['status'];
                        } else {
                            $post_results[$index] = array();
                            $post_results[$index]['Name'] = $result['Name'];
                            $post_results[$index]['DOB'] = $result['DOB'];
                            $post_results[$index]['MRN'] = $result['MRN'];
                            $post_results[$index]['DOS'] = $result['DOS'];
                            $post_results[$index]['status'] = $result['status'];
                            if ($result['claim_id']) {
                                $claim_id = $result['claim_id'];
                                $db_claim = new Application_Model_DbTable_Claim();
                                $db = $db_claim->getAdapter();
                                $where = $db->quoteInto("id=?", $claim_id);
                                $claim_data = $db_claim->fetchAll($where)->toArray();
                                $claim = $claim_data[0];
                                $post_results[$index]['allowed_amount'] = isset($claim['EOB_allowed_amount']) ? floatval($claim['EOB_allowed_amount']) : null;
                            }
                        }
                        $index++;
                    }
                    $this->view->post_results = $post_results;
                }
                $_SESSION['transfer_state_bk'] = $_SESSION['transfer_state'];
                unset($_SESSION['transfer_state']);
            } else {
                $this->view->transfer_state = false;
                unset($_SESSION['transfer_state']);
                unset($_SESSION['results']);
            }
        }
        if ($this->getRequest()->isPost()) {
            $submit_type = $_POST['submit'];
            if ($submit_type == "Look Up") {
                session_start();
                $os_type = PHP_OS;
                $cmd_type = 1;
                $absolute_path = get_path();
                if (substr($os_type, 0, 3) == "WIN")
                    $cmd_type = 2;
                else if (substr($os_type, 0, 4) == "Unix")
                    $cmd_type = 1;
                else if (substr($os_type, 0, 5) == "Linux")
                    $cmd_type = 1;
                /* $upload_dir = $this->sysdoc_path . "/billingcompany";
                  if (!is_dir($upload_dir)) {
                  mkdir($upload_dir);
                  } */
                $upload_dir = $this->sysdoc_path;
                $upload_dir = $upload_dir . '/' . $this->get_billingcompany_id();
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir);
                }

                $upload_dir = $upload_dir . '/ERA835';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir);
                }
                //if($cmd_type == 2){
                //    $absolute_path = str_replace('\\', '/', $absolute_path);
                //}
                //$jar_run_path = $absolute_path . "/edireader/edireader-4.7.3.jar";
                $app_path = getcwd();
                $jar_run_path = $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "library" . DIRECTORY_SEPARATOR . "edireader.jar" . PATH_SEPARATOR . $app_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "library";
                //$abs_save_path = $absolute_path . "/billingcompany/" . $this->get_billingcompany_id() . '/ERA835';
                //$abs_save_path = $absolute_path . "/" . $this->get_billingcompany_id() . '/ERA835';
                $abs_save_path = $absolute_path . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . $this->document_root . DIRECTORY_SEPARATOR . $this->get_billingcompany_id() . '/ERA835';
                $type = array();

                $curTime = date("YmdHis");
                $eob_file = $_FILES['eobFile'];
                $temp_file_name_for_ext = $eob_file['name'];
                $file_extension = substr($temp_file_name_for_ext, strripos($temp_file_name_for_ext, '.'), strlen($temp_file_name_for_ext) - strripos($temp_file_name_for_ext, '.'));
                $eob_file_type = "no";
                $eob_file_des = $upload_dir . '/' . $curTime . '_tmpeob' . $file_extension;
                if (is_uploaded_file($eob_file['tmp_name'])) {
                    if (move_uploaded_file($eob_file['tmp_name'], $eob_file_des)) {
                        $eob_file_type = $eob_file_des;
                    }
                }
                $_SESSION['eob_file_path'] = $eob_file_type;
                $era_file = $_FILES['eraFile'];
                $era_file_name = $curTime . '_era.edi';
                $era_file_des = $upload_dir . '/' . $curTime . '_era.edi';
                if (is_uploaded_file($era_file['tmp_name'])) {
                    if (move_uploaded_file($era_file['tmp_name'], $era_file_des)) {
                        $_SESSION['era_file_path'] = $era_file_des;
                        $readfile = fopen($era_file_des, "r");
                        $content = fread($readfile, 100000);
                        fclose($readfile);
                        $matchcount = preg_match_all("/GE\*/i", $content, $matchs, PREG_OFFSET_CAPTURE);
                        if ($matchcount > 0) {
                            $offset = $matchs[0][$matchcount - 1][1] + 3;
                            $matchcount2 = preg_match_all("/\*/i", $content, $matchs_2, PREG_OFFSET_CAPTURE, $offset);
                            $end = $matchs_2[0][0][1];
                            $new_content = substr($content, 0, $offset) . '1' . substr($content, $end);
                            $newfilepath = $upload_dir . '/' . $curTime . '_eraBK.edi';
                            $writeFile = fopen($newfilepath, 'w');
                            fwrite($writeFile, $new_content);
                            fclose($writeFile);
                            unlink($era_file_des);
                            rename($newfilepath, $era_file_des);
                        }
                        $tmp_up_name = $abs_save_path . '/' . $era_file_name;
                        $tmp_xml_name = $abs_save_path . '/' . $curTime . ".xml";
                        //$jar_run_path = $absolute_path . "/edireader/edireader.jar";
                        //$whatc = exec("java -cp $jar_run_path com.berryworks.edireader.demo.EDItoXML $tmp_up_name -o $tmp_xml_name", $out, $status);
                        $whatc = exec("java -cp $jar_run_path EDItoXML $tmp_up_name -o $tmp_xml_name", $out, $status);
                        $current_time = date('Y-m-d H:i:s');
                        if ($status == 0) {
                            $_SESSION['transfer_state'] = "LookUp";
                            $_SESSION['xml_file_path'] = $tmp_xml_name;
                            $dom = new DOMDocument();
                            $dom->load($tmp_xml_name);
                            $root = $dom->documentElement;
                            $loops = $root->getElementsByTagName("loop"); //这是对 xml 进行解析
                            $loopflag = 0;
                            $NPI = null;
                            $TIN = null;
                            foreach ($loops as $loop) {
                                if ($loop->getAttribute('Id') == "1000") {
                                    if ($loopflag == 1) {
                                        $B1000 = $loop;
                                        $loopflag = 2;
                                    } else if ($loopflag == 0) {
                                        $A1000 = $loop;
                                        $loopflag = 1;
                                    }
                                }
                                if ($loop->getAttribute('Id') == "2000") {
                                    $l2000 = $loop;
                                }
                            }
                            $B1000Segments = $B1000->getElementsByTagName("segment");
                            foreach ($B1000Segments as $B1000Segment) {
                                if ($B1000Segment->getAttribute('Id') == "N1") {
                                    $B1000N1Segment = $B1000Segment;
                                }
                                if ($B1000Segment->getAttribute('Id') == "REF") {
                                    $B1000REFSegment = $B1000Segment;
                                }
                            }
                            $B1000N1SegmentElements = $B1000N1Segment->getElementsByTagName("element");
                            $B1000REFSegmentElements = $B1000REFSegment->getElementsByTagName("element");
                            foreach ($B1000N1SegmentElements as $B1000N1SegmentElement) {
                                if ($B1000N1SegmentElement->getAttribute('Id') == "N103") {
                                    $N103 = $B1000N1SegmentElement->nodeValue;
                                }
                                if ($B1000N1SegmentElement->getAttribute('Id') == "N104" && $N103 == 'XX') {
                                    //$PIN = $B1000N1SegmentElement->nodeValue;
                                    $NPI = $B1000N1SegmentElement->nodeValue;
                                }
                                if ($B1000N1SegmentElement->getAttribute('Id') == "N104" && $N103 == 'FI') {
                                    //$PIN = $B1000N1SegmentElement->nodeValue;
                                    $TIN = $B1000N1SegmentElement->nodeValue;
                                }
                            }
                            foreach ($B1000REFSegmentElements as $B1000REFSegmentElement) {
                                if ($B1000REFSegmentElement->getAttribute('Id') == "REF01") {
                                    $REF01 = $B1000REFSegmentElement->nodeValue;
                                }
                                if ($B1000REFSegmentElement->getAttribute('Id') == "REF02" && $REF01 == 'TJ') {
                                    //$PIN = $B1000N1SegmentElement->nodeValue;
                                    $TIN = $B1000REFSegmentElement->nodeValue;
                                }
                            }
                            // 查找
                            $db_provider = new Application_Model_DbTable_Provider();
                            $db = $db_provider->getAdapter();

                            $billingcompany_id = $this->get_billingcompany_id();
//                            $where = $db->quoteInto("billing_provider_NPI = ?", $PIN);
//                            $where = $db->quoteInto('billing_provider_NPI='.$PIN.'AND billingcompany_id='.$billingcompany_id);
                            $where = $db->quoteInto("billing_provider_NPI = ?", $NPI) . $db->quoteInto("AND billingcompany_id=?", $billingcompany_id);
                            $providers = $db_provider->fetchAll($where)->toArray();
                            if (count($providers) == 1) {
                                $provider_data = $providers[0];
                            } else {
                                //$where = $db->quoteInto("tax_ID_number = ?", $TIN);
//                                $where = $db->quoteInto('tax_ID_number ='.$PIN.'AND billingcompany_id='.$billingcompany_id);
                                $where = $db->quoteInto("tax_ID_number = ?", $TIN) . $db->quoteInto("AND billingcompany_id=?", $billingcompany_id);
                                $providers = $db_provider->fetchAll($where)->toArray();
                                if (count($providers) == 1) {
                                    $provider_data = $providers[0];
                                } else {
                                    $provider_data = array();
                                    $provider_data['provider_name'] = "no exist provider";
                                }
                            }
                            $_SESSION['provider_data'] = $provider_data;
                            $provider_id = $provider_data['id'];
                            $A1000Segments = $A1000->getElementsByTagName("segment");
                            foreach ($A1000Segments as $A1000Segment) {
                                if ($A1000Segment->getAttribute('Id') == "N1") {
                                    $A1000N1Segment = $A1000Segment;
                                }
                            }
                            $A1000N1SegmentElements = $A1000N1Segment->getElementsByTagName("element");
                            foreach ($A1000N1SegmentElements as $A1000N1SegmentElement) {
                                if ($A1000N1SegmentElement->getAttribute('Id') == "N102")
                                    $insurance = $A1000N1SegmentElement->nodeValue;
                            }
                            $_SESSION['insurance_name'] = $insurance;
                            $l2000loops = $l2000->getElementsByTagName("loop");
                            $l2100loops = array();
                            $i = 0;
                            foreach ($l2000loops as $l2000loop) {
                                if ($l2000loop->getAttribute('Id') == "2100") {
                                    $l2100loops[$i++] = $l2000loop;
                                }
                            }
                            $claims = array();
                            $i = 0;
                            foreach ($l2100loops as $l2100loop) {
                                $claims[$i] = array();
                                $claims[$i]['from'] = $insurance;
                                $l2100loopSegments = $l2100loop->getElementsByTagName("segment");
                                foreach ($l2100loopSegments as $l2100loopSegment) {
                                    if ($l2100loopSegment->getAttribute('Id') == "CLP") {
                                        $clp = $l2100loopSegment;
                                        $clpElements = $clp->getElementsByTagName("element");
                                        foreach ($clpElements as $clpElement) {
                                            if ($clpElement->getAttribute("Id") == "CLP01") {
                                                $claims[$i]['patient_mrn'] = $clpElement->nodeValue;
                                            }
                                            if ($clpElement->getAttribute("Id") == "CLP03") {
                                                $claims[$i]['total_charge'] = $clpElement->nodeValue;
                                            }
                                            if ($clpElement->getAttribute("Id") == "CLP04") {
                                                $claims[$i]['payment_amount'] = $clpElement->nodeValue;
                                            }
                                            if ($clpElement->getAttribute("Id") == "CLP05") {
                                                //$claims[$i]['co_insurance'] = $clpElement->nodeValue;
                                            }
                                        }
                                    }
                                    /* if($l2100loopSegment->getAttribute('Id')=="DTM"){
                                      $dtm = $l2100loopSegment;
                                      $dtmElements = $dtm->getElementsByTagName("element");
                                      foreach($dtmElements as $dtmElement){
                                      if($dtmElement->getAttribute("Id")=="DTM02"){
                                      $claims[$i]['date_of_service'] = $dtmElement->nodeValue;
                                      }
                                      }
                                      } */
                                    if ($l2100loopSegment->getAttribute('Id') == "AMT") {
                                        $amt = $l2100loopSegment;
                                        $amtElements = $amt->getElementsByTagName("element");
                                        foreach ($amtElements as $amtElement) {
                                            if ($amtElement->getAttribute("Id") == "AMT02") {
                                                $claims[$i]['allowed_amount'] = $amtElement->nodeValue;
                                                $claims[$i]['not_allowed_amount'] = $claims[$i]['total_charge'] - $claims[$i]['allowed_amount'];
                                            }
                                        }
                                    }
                                }
                                $l2110loops = $l2100loop->getElementsByTagName("loop");
                                $serviceloops = array();
                                $serviceLoopCount = 0;
                                foreach ($l2110loops as $l2110loop) {
                                    if ($l2110loop->getAttribute("Id") == "2110") {
                                        if ($serviceLoopCount == 0) {
                                            $l2110A = $l2110loop;
                                        }
                                        $serviceloops[$serviceLoopCount] = $l2110loop;
                                        $serviceLoopCount++;
                                    }
                                }
                                $l2110Asegments = $l2110A->getElementsByTagName("segment");
                                foreach ($l2110Asegments as $l2110Asegment) {
                                    if ($l2110Asegment->getAttribute("Id") == "SVC") {
                                        $svcs = $l2110Asegment->getElementsByTagName("element");
                                        foreach ($svcs as $svc) {
                                            if ($svc->getAttribute("Id") == "SVC01") {
                                                $subelements = $svc->getElementsByTagName('subelement');
                                                foreach ($subelements as $subelement) {
                                                    if ($subelement->getAttribute("Sequence") == '2') {
                                                        $claims[$i]['code_billed'] = $subelement->nodeValue; //cpt code
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if ($l2110Asegment->getAttribute("Id") == "CAS") {
                                        $cass = $l2110Asegment->getElementsByTagName("element");
                                        foreach ($cass as $cas) {
                                            if ($cas->getAttribute("Id") == "CAS02") {
                                                $claims[$i]['adjustment_reason'] = $cas->nodeValue;
                                            }
                                        }
                                    }
                                    if ($l2110Asegment->getAttribute("Id") == "DTM") {
                                        $dtms = $l2110Asegment->getElementsByTagName("element");
                                        foreach ($dtms as $dtm) {
                                            if ($dtm->getAttribute("Id") == "DTM02") {
                                                $claims[$i]['date_of_service'] = $dtm->nodeValue;
                                            }
                                        }
                                    }
                                }
                                //下面是往 service 里面写数据,都是写到session里面了，在look up 部分就已经写了。
                                $claims[$i]['service_lines'] = array();
                                $serviceLineIndex = 0;
                                $cas_flag = 0;
                                //这里一共只能写3行， 最后一个 根本不是从post 里面写的
                                foreach ($serviceloops as $serviceloop) {
                                    $claims[$i]['service_lines'][$serviceLineIndex] = array();
                                    $serviceSegments = $serviceloop->getElementsByTagName("segment");
                                    foreach ($serviceSegments as $serviceSegment) {
                                        if ($serviceSegment->getAttribute("Id") == "SVC") {
                                            $s_svcs = $serviceSegment->getElementsByTagName("element");
                                            foreach ($s_svcs as $s_svc) {
                                                if ($s_svc->getAttribute("Id") == "SVC03") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['payment'] = $s_svc->nodeValue;
                                                }
                                            }
                                        }
                                        if ($serviceSegment->getAttribute("Id") == "AMT") {
                                            $s_amts = $serviceSegment->getElementsByTagName("element");
                                            foreach ($s_amts as $s_amt) {
                                                if ($s_amt->getAttribute("Id") == "AMT02") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['allowed_amount'] = $s_amt->nodeValue;
                                                }
                                            }
                                        }
                                        if ($serviceSegment->getAttribute("Id") == "CAS") {
                                            $s_cass = $serviceSegment->getElementsByTagName("element");
                                            foreach ($s_cass as $s_cas) {
                                                if ($s_cas->getAttribute("Id") == "CAS01") {
                                                    $s_cas1 = $s_cas->nodeValue;
                                                }
                                                if ($s_cas->getAttribute("Id") == "CAS02") {
                                                    $s_cas2 = $s_cas->nodeValue;
                                                }
                                                if ($s_cas->getAttribute("Id") == "CAS03") {
                                                    $s_cas3 = $s_cas->nodeValue;
                                                }
                                                if ($s_cas->getAttribute("Id") == "CAS05") {
                                                    $s_cas5 = $s_cas->nodeValue;
                                                }
                                                if ($s_cas->getAttribute("Id") == "CAS06") {
                                                    $s_cas6 = $s_cas->nodeValue;
                                                }
                                            }
                                            $serviceLineData = array();
                                            if ($s_cas1 == "CO") {
                                                if ($s_cas2 == "45") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['not_allowed_amount'] = $s_cas3;
                                                } else {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['reduction'] = $s_cas3;
                                                    if (isset($claims[$i]["reduction"])) {
                                                        $claims[$i]["reduction"] = $claims[$i]["reduction"] + floatval($s_cas3);
                                                    } else {
                                                        $claims[$i]["reduction"] = floatval($s_cas3);
                                                    }
                                                }
                                                $claims[$i]['service_lines'][$serviceLineIndex]['adjustment_reason'] = $s_cas2;
                                                if (isset($s_cas5)) {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['reduction'] = $s_cas6;
                                                    if (isset($claims[$i]["reduction"])) {
                                                        $claims[$i]["reduction"] = $claims[$i]["reduction"] + floatval($s_cas6);
                                                    } else {
                                                        $claims[$i]["reduction"] = floatval($s_cas6);
                                                    }
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['adjustment_reason'] = $s_cas2 . "/" . $s_cas5;
                                                }
                                            }
                                            if ($s_cas1 == "PR") {
                                                if ($s_cas2 == "2") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['co_insurance'] = $s_cas3;
                                                    if (isset($claims[$i]["co_insurance"])) {
                                                        $claims[$i]["co_insurance"] = $claims[$i]["co_insurance"] + floatval($s_cas3);
                                                    } else {
                                                        $claims[$i]["co_insurance"] = floatval($s_cas3);
                                                    }
                                                }
                                                if ($s_cas2 == "1") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['deductible'] = $s_cas3;
                                                    if (isset($claims[$i]["deductible"])) {
                                                        $claims[$i]["deductible"] = $claims[$i]["deductible"] + floatval($s_cas3);
                                                    } else {
                                                        $claims[$i]["deductible"] = floatval($s_cas3);
                                                    }
                                                }
                                                if ($s_cas5 == "1") {
                                                    $claims[$i]['service_lines'][$serviceLineIndex]['deductible'] = $s_cas6;
                                                    if (isset($claims[$i]["deductible"])) {
                                                        $claims[$i]["deductible"] = $claims[$i]["deductible"] + floatval($s_cas3);
                                                    } else {
                                                        $claims[$i]["deductible"] = floatval($s_cas3);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $serviceLineIndex++;
                                }
                                $i++;
                            }
                            $result = array();
                            $i = 0;
                            $total = 0;
                            foreach ($claims as $claim) {
                                $result[$i] = array();
                                $MRN = $claim['patient_mrn'];
                                $DOS = $claim['date_of_service'];
                                $db_patient = new Application_Model_DbTable_Patient();
                                $db = $db_patient->getAdapter();
                                $where = $db->quoteInto("account_number = ?", $MRN);
                                $patient_datas = $db_patient->fetchAll($where)->toArray();
                                $patient_data = array();
                                $index_patient = 0;
                                $db_encounter = new Application_Model_DbTable_Encounter();
                                $db = $db_encounter->getAdapter();
                                foreach ($patient_datas as $patient_row) {
                                    $patient_id_row = $patient_row['id'];
                                    $where = $db->quoteInto("patient_id=?", $patient_id_row) . $db->quoteInto("AND provider_id=?", $provider_id);
                                    $encounter_data = $db_encounter->fetchAll($where)->toArray();
                                    if (count($encounter_data) > 0) {
                                        $patient_data[$index_patient] = $patient_row;
                                        $index_patient++;
                                    }
                                }
                                if (count($patient_data) > 1) {
                                    $result[$i]['status'] = 'Repeated Patient Error';
                                    $result[$i]['postingAmount'] = 0;
                                    $i++;
                                    continue;
                                }
                                if (count($patient_data) == 0) {
                                    $result[$i]['status'] = 'No Patient Error';
                                    $result[$i]['postingAmount'] = 0;
                                    $i++;
                                    continue;
                                }
                                $patient = $patient_data[0];
                                $patient_id = $patient['id'];
                                //match right patient and save the matched patient info
                                $result[$i]['Name'] = $patient['last_name'] . ', ' . $patient['first_name'];
                                $result[$i]['DOB'] = format($patient['DOB'], 1);
                                $result[$i]['MRN'] = $MRN;
                                $result[$i]['patient_id'] = $patient_id;
                                $db_encounter = new Application_Model_DbTable_Encounter();
                                $db = $db_encounter->getAdapter();
                                $where = $db->quoteInto("patient_id=?", $patient_id) . $db->quoteInto("AND start_date_1=?", $DOS) . $db->quoteInto("AND provider_id=?", $provider_id);
                                $encounter_data = $db_encounter->fetchAll($where)->toArray();
                                if (count($encounter_data) > 1) {
                                    $result[$i]['status'] = 'Repeated Claim Error';
                                    $result[$i]['postingAmount'] = 0;
                                    $i++;
                                    continue;
                                }
                                if (count($encounter_data) == 0) {
                                    $result[$i]['status'] = 'No Service Error';
                                    $result[$i]['postingAmount'] = 0;
                                    $i++;
                                    continue;
                                }
                                $encounter = $encounter_data[0];
                                if ($encounter["cpt_code_1"] != $claim['code_billed'] && $encounter["secondary_CPT_code_1"] != $claim['code_billed']) {
                                    $result[$i]['status'] = 'CPT Match Error';
                                    $result[$i]['postingAmount'] = 0;
                                    $i++;
                                    continue;
                                }
                                $result[$i]['encounter_data'] = $encounter;
                                $encounter_data = $encounter;
                                $eob_datas = array();
                                for ($index = 0; $index < 6; $index++) {
                                    if (($encounter_data['secondary_CPT_code_' . ($index + 1)] != "" && $encounter_data['secondary_CPT_code_' . ($index + 1)] != null) || ($encounter_data['CPT_code_' . ($index + 1)] != "" && $encounter_data['CPT_code_' . ($index + 1)] != null)) {
                                        if ($encounter_data['secondary_CPT_code_' . ($index + 1)] != "" && $encounter_data['secondary_CPT_code_' . ($index + 1)] != null) {
                                            $eob_datas[$index]['cpt_code'] = $encounter_data['secondary_CPT_code_' . ($index + 1)];
                                            $eob_datas[$index]['charge'] = $encounter_data['charges_' . ($index + 1)];
                                            $eob_datas[$index]['ep'] = $encounter_data['expected_payment_' . ($index + 1)];
                                        } else {
                                            $eob_datas[$index]['cpt_code'] = $encounter_data['CPT_code_' . ($index + 1)];
                                            $eob_datas[$index]['charge'] = $encounter_data['charges_' . ($index + 1)];
                                            $eob_datas[$index]['ep'] = $encounter_data['expected_payment_' . ($index + 1)];
                                        }
                                    }
                                }
                                $result[$i]['eob_datas'] = $eob_datas;
                                $result[$i]['encounter_id'] = $encounter['id'];
                                $claim_id = $encounter['claim_id'];
                                $db_claim = new Application_Model_DbTable_Claim();
                                $db = $db_claim->getAdapter();
                                $where = $db->quoteInto("id=?", $claim_id);
                                $claim_data = $db_claim->fetchAll($where)->toArray();
                                if (count($claim_data) == 1) {
                                    if (floatval($claim['total_charge']) != floatval($claim_data[0]['total_charge'])) {
                                        $result[$i]['status'] = 'Total Charge Match Error';
                                        $result[$i]['postingAmount'] = 0;
                                        $i++;
                                        continue;
                                    }
                                    $claim_bk = $claim_data[0];
                                    //match the right claim and save the matched claim info
                                    $result[$i]['DOS'] = format($DOS, 1);
                                    $result[$i]['claim_id'] = $claim_id;
                                    //$result[$i]['allowed_amount'] = $claim['allowed_amount'];
                                    //check Allowed/Not Allowed Amount
                                    if (($claim_bk["EOB_not_allowed_amount"] != null && $claim_bk["EOB_not_allowed_amount"] != '' && $claim_bk["EOB_not_allowed_amount"] != "0") || ($claim_bk["EOB_allowed_amount"] != null && $claim_bk["EOB_allowed_amount"] != '' && $claim_bk["EOB_allowed_amount"] != '0')) {
                                        $result[$i]['status'] = "EOB Conflict";
                                        $result[$i]['postingAmount'] = 0;
                                        $result[$i]['allowed_amount'] = (isset($claim_bk['EOB_allowed_amount'])) ? floatval($claim_bk['EOB_allowed_amount']) : null;
                                        $i++;
                                        continue;
                                    }
                                    $result[$i]['provider_id'] = $provider_id;
                                    $result[$i]['insurance'] = $insurance;
                                    $result[$i]['patient_id'] = $patient_id;
                                    $result[$i]['Name'] = $patient['last_name'] . ', ' . $patient['first_name'];
                                    $result[$i]['DOB'] = format($patient['DOB'], 1);
                                    $result[$i]['MRN'] = $MRN;
                                    $result[$i]['postingAmount'] = abs(floatval($claim['payment_amount']));
                                    $claim_status_tmp = $claim_bk['claim_status'];
                                    $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                                    $db = $db_claimstatus->getAdapter();
                                    $where = $db->quoteInto("claim_status = ?", $claim_status_tmp);
                                    $status_row = $db_claimstatus->fetchRow($where);
                                    $result[$i]['CS'] = $status_row['claim_status_display'];
                                    //caculate the related amount
                                    $result[$i]['total_charge'] = floatval($claim_bk['total_charge']);
                                    //$result[$i]['allowed_amount'] = $claim_bk['EOB_allowed_amount'];
                                    $result[$i]['allowed_amount'] = $claim['allowed_amount'];
                                    $result[$i]['amount_paid_bf'] = floatval($claim_bk['amount_paid']);
                                    $result[$i]['balance_due_bf'] = floatval($claim_bk['balance_due']);
                                    $result[$i]['amount_paid'] = floatval($claim_bk['amount_paid']) + abs(floatval($claim['payment_amount']));
                                    $result[$i]['balance_due'] = floatval($claim['total_charge']) - $result[$i]['amount_paid'];
                                    $result[$i]['payment_data'] = $claim;
                                    $result[$i]['service_lines'] = $claim['service_lines'];
                                    $result[$i]['co_insurance'] = $claim["co_insurance"];
                                    $result[$i]['deductible'] = $claim["deductible"];
                                    $result[$i]['status'] = "Success";
                                    $total = $total + abs(floatval($claim['payment_amount']));
                                } else {
                                    $result[$i]['status'] = "Claim Error";
                                }
                                $i++;
                            }
                            $_SESSION['results'] = $result;
                            $_SESSION['total'] = $total;
                        } else if ($status == 1) {
                            $_SESSION['transfer_state'] = true;
                            $index = $index + 1;
                        }
                        $this->_redirect('/biller/claims/erapaymentbatch');
                    }
                }
            } else if ($submit_type == "Post") {
                session_start();
                $provider_data = $_SESSION['provider_data'];
                if ($provider_data['provider_name'] == "no exist provider") {
                    unset($_SESSION['provider_data']);
                    unset($_SESSION['insurance_name']);
                    unset($_SESSION['results']);
                    unset($_SESSION['eob_file_path']);
                    unset($_SESSION['total']);
                    unset($_SESSION['xml_file_path']);
                    $this->_redirect('/biller/claims/erapaymentbatch');
                }
                $user = Zend_Auth::getInstance()->getIdentity();
                $user_name = $user->user_name;
                $insurance = $_SESSION['insurance_name'];
                $results = $_SESSION['results'];
                $provider_id = $provider_data['id'];
                if ($_SESSION['eob_file_path'] != "no") {
                    $eod_file_path = $_SESSION['eob_file_path'];
                }
                $total = $_SESSION['total'];
                $count_result = count($results);
                $provider_name = $provider_data['provider_name'];
                $this->set_options_session($provider_id, $provider_name, $this->get_billingcompany_id());
                $autoposting = $_SESSION['options']['autoposting'];
                for ($i = 0; $i < $count_result; $i++) {
                    if ($results[$i]['status'] == "Success") {
                        //初始化
                        $payment_add['amount'] = ($results[$i]['postingAmount'] == "") ? 0 : floatval($results[$i]['postingAmount']);
                        $payment_add['datetime'] = date("Y-m-d H:i:s");
                        $time_in_log = format($payment_add['datetime'], 7);
                        $payment_add['from'] = isset($insurance) ? $insurance : "";
                        $payment_add['notes'] = "";
                        $payment_add['internal_notes'] = "";
                        $payment_add['claim_id'] = $results[$i]['claim_id'];
                        $payment_add['paymentid'] = 0;
                        $payment_add['serviceid'] = 0;
                        $payment_add['co_insurance'] = $results[$i]["co_insurance"];
                        $payment_add['deductible'] = $results[$i]["deductible"];
                        $table_name = "payments";
                        $db_payment = new Application_Model_DbTable_Payments();
                        $db_interaction = new Application_Model_DbTable_Interactionlog();
                        if ($payment_add['amount'] != "" && $payment_add['amount'] != null) {
                            $db = $db_payment->getAdapter();
                            $db->insert($table_name, $payment_add);
                            $results[$i]['payment_id'] = $db->lastInsertId();
                            if (count($results[$i]["service_lines"]) > 0) {
                                $service_line_index = 1;
                                //往payment 数据库写数据，问题就出在这里
                                foreach ($results[$i]["service_lines"] as $service_line) {
                                    $service_payment = array();
                                    $service_payment["amount"] = $service_line["payment"];
                                    $service_payment["datetime"] = $payment_add['datetime'];
                                    $service_payment["from"] = $payment_add['from'];
                                    $service_payment["notes"] = "";
                                    $service_payment["internal_notes"] = "";
                                    $service_payment["serviceid"] = $service_line_index;
                                    $service_payment["paymentid"] = $results[$i]['payment_id'];
                                    $service_payment["not_allowed_amount"] = $service_line["not_allowed_amount"];
                                    $service_payment["reduction"] = $service_line["reduction"];
                                    $service_payment["adjustment_reason"] = $service_line["adjustment_reason"];
                                    $service_payment["co_insurance"] = $service_line["co_insurance"];
                                    $service_payment["deductible"] = $service_line["deductible"];
                                    $service_payment["claim_id"] = $payment_add['claim_id'];
                                    $db->insert($table_name, $service_payment);
                                    $results[$i]["service_lines"][($service_line_index - 1)]['saved_payment_id'] = $db->lastInsertId();
                                    $service_line_index++;
                                }
                            }
                            $payment_log_save['date_and_time'] = $payment_add['datetime'];
                            $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                            $payment_log_save['log'] = $user_name . ": Added Payment " . $payment_add['amount'] . '|' . $time_in_log . '|' . $payment_add['from'];
                            $db = $db_interaction->getAdapter();
                            $table_name = 'interactionlog';
                            $db->insert($table_name, $payment_log_save);
                        }
                        /* $EOB_Update = array("EOB_co_insurance"=>$payments[$i]['Co Insurance'],"EOB_deductable"=>$payments[$i]['Deducible'],
                          "EOB_reduction"=>$payments[$i]['EOB Reduction'],"EOB_other_reduction"=>$payments[$i]['EOB Other Reduction'],
                          "EOB_adjustment_reason"=>$payments[$i]['Adjustment Reason'],); */
                        $db_claim = new Application_Model_DbTable_Claim();
                        $db = $db_claim->getAdapter();
                        $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                        $claim_data = $db_claim->fetchRow($where);
                        $total_charge = $claim_data['total_charge'];
                        $new_amount_paid = floatval($claim_data['amount_paid']) + $payment_add['amount'];
                        $old_balance_due = floatval($claim_data['balance_due']);
                        $biller_adjustment = $old_balance_due + floatval($claim_data['amount_paid']) - floatval($total_charge);
                        $new_balance_due = floatval($total_charge) - $new_amount_paid + $biller_adjustment;
                        $claim_change_flag = 0;
                        if ($autoposting == "1" || $autoposting == "2") {
                            $expected = floatval($claim_data['expected_payment']);
                            $allowed = floatval($claim_data['EOB_allowed_amount']);
                            if (($expected != null && $expected != "" && $new_amount_paid >= $expected) || ($allowed != null && $allowed != '' && $new_amount_paid >= $allowed)) {
                                $billeramount = floatval($total_charge) - $new_amount_paid;
                                $billerpayment = array();
                                $billerpayment['amount'] = -$billeramount;
                                $billerpayment['datetime'] = date("Y-m-d H:i:s");
                                $time_in_log = format($billerpayment['datetime'], 7);
                                $billerpayment['from'] = "Biller Adjustment";
                                $billerpayment['notes'] = "";
                                $billerpayment['internal_notes'] = "";
                                $billerpayment['claim_id'] = $results[$i]['claim_id'];
                                $db_p = $db_payment->getAdapter();
                                $db_p->insert("payments", $billerpayment);
                                $payment_log_save['date_and_time'] = $billerpayment['datetime'];
                                $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                                $payment_log_save['log'] = $user_name . ": Added Payment " . $billerpayment['amount'] . '|' . $time_in_log . '|' . $billerpayment['from'];
                                $db_i = $db_interaction->getAdapter();
                                $db_i->insert("interactionlog", $payment_log_save);
                                $new_balance_due = 0;
                                if ($autoposting == "2") {
                                    $update = array("claim_status" => "closed_payment_as_expected");
                                    $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                                    $db->update("claim", $update, $where);
                                    $claim_change_flag = 1;
                                    $tmp_interactionlog['claim_id'] = $results[$i]['claim_id'];
                                    $tmp_interactionlog['date_and_time'] = date("Y-m-d H:i:s");
                                    $tmp_interactionlog['log'] = $user_name . ": Changed Claim Status from " . $results[$i]['CS'] . " to Closed-payment as expected";
                                    $db_i->insert("interactionlog", $tmp_interactionlog);
                                }
                            }
                        }
                        if ($autoposting == "3" || $autoposting == "2,3") {
                            $billeramount = floatval($total_charge) - $new_amount_paid;
                            $billerpayment = array();
                            $billerpayment['amount'] = -$billeramount;
                            $billerpayment['datetime'] = date("Y-m-d H:i:s");
                            $time_in_log = format($billerpayment['datetime'], 7);
                            $billerpayment['from'] = "Biller Adjustment";
                            $billerpayment['notes'] = "";
                            $billerpayment['internal_notes'] = "";
                            $billerpayment['claim_id'] = $results[$i]['claim_id'];
                            $db_p = $db_payment->getAdapter();
                            $db_p->insert("payments", $billerpayment);
                            $payment_log_save['date_and_time'] = $billerpayment['datetime'];
                            $payment_log_save['claim_id'] = $results[$i]['claim_id'];
                            $payment_log_save['log'] = $user_name . ": Added Payment " . $billerpayment['amount'] . '|' . $time_in_log . '|' . $billerpayment['from'];
                            $db_i = $db_interaction->getAdapter();
                            $db_i->insert("interactionlog", $payment_log_save);
                            $new_balance_due = 0;
                            if ($autoposting == "2,3") {
                                $update = array("claim_status" => "closed_payment_as_expected");
                                $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                                $db->update("claim", $update, $where);
                                $claim_change_flag = 1;
                                $tmp_interactionlog['claim_id'] = $results[$i]['claim_id'];
                                $tmp_interactionlog['date_and_time'] = date("Y-m-d H:i:s");
                                $tmp_interactionlog['log'] = $user_name . ": Changed Claim Status from " . $results[$i]['CS'] . " to Closed-payment as expected";
                                $db_i->insert("interactionlog", $tmp_interactionlog);
                            }
                        }
                        $table_name = "claim";
                        //$db->update($table_name,$EOB_Update,$where);
                        if ($payment_add['amount'] != 0) {
                            $update = array("amount_paid" => $new_amount_paid, "balance_due" => $new_balance_due);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $update, $where);
                            $claim_change_flag = 1;
                        }
                        $co_insurance = $results[$i]['payment_data']['co_insurance'];
                        if ($co_insurance != null && $co_insurance != "") {
                            $EOB_Update = array("EOB_co_insurance" => $co_insurance);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        $reduction = $results[$i]['payment_data']['reduction'];
                        if ($reduction != null && $reduction != "") {
                            $EOB_Update = array("EOB_reduction" => $reduction);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        $deductible = $results[$i]['payment_data']['deductible'];
                        if ($deductible != null && $deductible != "") {
                            $EOB_Update = array("EOB_deductable" => $deductible);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        $adjustment_reason = $results[$i]['payment_data']['adjustment_reason'];
                        if ($adjustment_reason != null && $adjustment_reason != "") {
                            $EOB_Update = array("EOB_adjustment_reason" => $adjustment_reason);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        $allowed_amount = $results[$i]['payment_data']['allowed_amount'];
                        if ($allowed_amount != null && $allowed_amount != "") {
                            $EOB_Update = array("EOB_allowed_amount" => $allowed_amount);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        $not_allowed_amount = $results[$i]['payment_data']['not_allowed_amount'];
                        if ($not_allowed_amount != null && $not_allowed_amount != "") {
                            $EOB_Update = array("EOB_not_allowed_amount" => $not_allowed_amount);
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $EOB_Update, $where);
                            $claim_change_flag = 1;
                        }
                        if ($claim_change_flag == 1) {
                            $cur_time = date("Y-m-d H:i:s");
                            $claim_change_update['update_time'] = $cur_time;
                            $claim_change_update['update_user'] = $user_name;
                            $where = $db->quoteInto("id=?", $results[$i]['claim_id']);
                            $db->update($table_name, $claim_change_update, $where);
                            $claim_change_flag = 0;
                        }
                        if (isset($eod_file_path)) {
                            $billingcompany_id = $this->get_billingcompany_id();
                            $dir_billingcompany = $this->sysdoc_path . '/' . $billingcompany_id;
                            if (!is_dir($dir_billingcompany)) {
                                mkdir($dir_billingcompany);
                            }
                            $dir_provider = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id;
                            if (!is_dir($dir_provider)) {
                                mkdir($dir_provider);
                            }
                            $dir_claim = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim';
                            if (!is_dir($dir_claim)) {
                                mkdir($dir_claim);
                            }
                            $dir = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/claim/' . $results[$i]['claim_id'];
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            //$old_file = $this->sysdoc_path . '/' . "tmpfile/" . $filepath;
                            $today = date("Y-m-d H:i:s");
                            $date = explode(' ', $today);
                            $time0 = explode('-', $date[0]);
                            $time1 = explode(':', $date[1]);
                            $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                            $user = Zend_Auth::getInstance()->getIdentity();
                            $user_name = $user->user_name;
                            $insurance_name = $_SESSION['insurance_name'];
                            if (substr($insurance_name, -1) == '.') {
                                $new_insurance_name = substr($insurance_name, 0, strlen($insurance_name) - 1);
                            } else {
                                $new_insurance_name = $insurance_name;
                            }
                            $new_file_extension = substr($eod_file_path, strripos($eod_file_path, '.'), strlen($eod_file_path) - strripos($eod_file_path, '.'));
                            $new_name = $time . '-EOB-' . $new_insurance_name . '-' . $user_name . $new_file_extension;
                            $new_file = $dir . '/' . $new_name;
                            $result = copy($eod_file_path, $new_file);
                            if ($result && $i == ($count_result - 1)) {
                                unlink($eod_file_path);
                            }
                        }
                    }
                }
                $root_dir = $this->sysdoc_path;
                /* $log_dir = $root_dir. '/billingcompany';
                  if (!is_dir($log_dir)) {
                  mkdir($log_dir);
                  } */
                $log_dir = $root_dir;
                $log_dir = $log_dir . '/' . $this->billingcompany_id;
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }
                $log_dir = $log_dir . '/batchlog';
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }
                $log_file_name = $log_dir . '/EDIBatchLog.csv';
                $display_fields = array('Name', 'DOB', 'MRN', 'DOS', 'Claim Status', 'Posted Amount', 'Look Status');
                $fields = array('Name', 'DOB', 'MRN', 'DOS', 'CS', 'postingAmount', 'status');
                $final_length = count($display_fields);
                if ($count_result > 0) {
                    if (file_exists($log_file_name)) {
                        $rp = fopen($log_file_name, 'r');
                        $tmp_log_file = $log_dir . '/tmp.csv';
                        $wp = fopen($tmp_log_file, 'w');
                        //write date and time
                        $cur_time = date("Y-m-d H:i:s");
                        fwrite($wp, "Date and Time: ," . $cur_time . ',');
                        fwrite($wp, "\r\n");
                        //wirte the titles
                        for ($i = 0; $i < $final_length; $i++) {
                            fwrite($wp, $display_fields[$i] . ",");
                        }
                        fwrite($wp, "\r\n");
                        //write the results
                        for ($i = 0; $i < $count_result; $i++) {
                            for ($j = 0; $j < $final_length; $j++) {
                                //$ttt = $data[$i][$fields[$j]];
                                if ($j == 0) {
                                    $name = str_replace(',', '', $results[$i][$fields[$j]]);
                                    fwrite($wp, $name . ',');
                                } else if ($j == 2) {
                                    $mrn = "=\"" . $results[$i][$fields[$j]] . "\"";
                                    fwrite($wp, $mrn . ',');
                                } else {
                                    fwrite($wp, strval($results[$i][$fields[$j]]) . ",");
                                }
                            }
                            fwrite($wp, "\r\n");
                        }
                        fwrite($wp, "Total: " . "," . strval($total) . ',');
                        fwrite($wp, "\r\n");
                        fwrite($wp, "\r\n");
                        $read_old_log = fgets($rp);
                        while (!feof($rp)) {
                            fwrite($wp, $read_old_log);
                            $read_old_log = fgets($rp);
                        }
                        fwrite($wp, $read_old_log);
                        fclose($wp);
                        fclose($rp);

                        if (!unlink($log_file_name))
                            echo ("Error deleting $log_file_name");
                        else
                            echo ("Deleted $log_file_name");

                        rename($tmp_log_file, $log_file_name);
                    }else {
                        $fp = fopen($log_file_name, 'w');
                        //write date and time
                        $cur_time = date("Y-m-d H:i:s");
                        fwrite($fp, "Date and Time: ," . $cur_time . ',');
                        fwrite($fp, "\r\n");
                        //write fields
                        for ($i = 0; $i < $final_length; $i++) {
                            fwrite($fp, $display_fields[$i] . ",");
                        }
                        fwrite($fp, "\r\n");

                        //write results
                        for ($i = 0; $i < $count_result; $i++) {
                            for ($j = 0; $j < $final_length; $j++) {
                                //$ttt = $data[$i][$fields[$j]];
                                if ($j == 0) {
                                    $name = str_replace(',', '', $results[$i][$fields[$j]]);
                                    fwrite($fp, $name . ',');
                                } else if ($j == 2) {
                                    $mrn = "=\"" . $results[$i][$fields[$j]] . "\"";
                                    fwrite($wp, $mrn . ',');
                                } else {
                                    fwrite($fp, strval($results[$i][$fields[$j]]) . ",");
                                }
                            }
                            fwrite($fp, "\r\n");
                        }
                        //write total amount
                        fwrite($fp, "Total: " . "," . strval($total) . ',');
                        fwrite($fp, "\r\n");
                        fclose($fp);
                    }
                }
                /* unset($_SESSION['provider_data']);
                  unset($_SESSION['insurance_name']);
                  unset($_SESSION['results']);
                  unset($_SESSION['eob_file_path']);
                  unset($_SESSION['total']);
                  unset($_SESSION['xml_file_path']); */
                $_SESSION['results'] = $results;
                $_SESSION['transfer_state'] = "Posted";
                $this->_redirect('/biller/claims/erapaymentbatch');
            } else if ($submit_type == "JUMP") {
                $index = $_POST['jump_index'];
                $results = $_SESSION['results'];
                $encounter_id = $results[$index]['encounter_id'];
                $patient_id = $results[$index]['patient_id'];
                $this->set_options_session();
                session_start();
                $_SESSION['transfer_state'] = $_SESSION['transfer_state_bk'];
                $options = $_SESSION['options'];
                $_SESSION['era_state'] = 1;
                if ($patient_id != null) {
                    $this->initsession($patient_id, $encounter_id);
                    //statement triger 5 status not close 
                    $patient_data = $_SESSION['patient_data'];
                    $start_date_I = format($patient_data['date_statement_I'], 0);
                    $start_date_II = format($patient_data['date_statement_II'], 0);
                    $end_date = date("Y-m-d");
                    if ($start_date_II != null) {
                        $days = days($start_date_II, $end_date);
                        if ($days >= $options['patient_statement_interval']) {
                            $_SESSION['patient_data']['statement'] = '3';
                            $_SESSION['patient_data']['statement_trigger'] = '5';
                        }
                    } else {
                        if ($start_date_I != null) {
                            $days = days($start_date_I, $end_date);
                            if ($days >= $options['patient_statement_interval']) {
                                $_SESSION['patient_data']['statement'] = '2';
                                $_SESSION['patient_data']['statement_trigger'] = '5';
                            }
                        }
                    }
                    if ($encounter_id != null) {
                        $this->_redirect('/biller/claims/claim');
                    }
                }
            } else if ($submit_type == "Check EDI 835 Batch Log") {
                $billingcompany_id = $this->get_billingcompany_id();
                //$log_file_name = $this->sysdoc_path . '/billingcompany/' . $billingcompany_id . '/batchlog';
                $log_file_name = $this->sysdoc_path . '/' . $billingcompany_id . '/batchlog';
                $log_file_name = $log_file_name . '/EDIBatchLog.csv';
                if (file_exists($log_file_name)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($log_file_name));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($log_file_name) . ' bytes');
                    //ob_clean();
                    //flush();
                    readfile($log_file_name);
                    exit;
                }
                $this->_redirect('/biller/claims/erapaymentbatch');
            }
        }
    }

}
