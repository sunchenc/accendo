<?php

require_once 'helper.php';
require_once 'makeproviderbilling.php';
require_once 'gen_report.php';
require_once 'mysqlhelper.php';
require_once 'Zend/Pdf.php';
require_once 'service.php';
require_once 'claimhelper.php';
require_once 'appeal.php';
require_once "Excel/reader.php";
require_once 'doc2txt.class.php';

class Biller_DataController extends Zend_Controller_Action {

    protected $billingcompany_id = '';
    protected $user_role = '';
    protected $appeal = array();
    protected $user_id = '';

    public function billingcompany_id() {
        return $this->billingcompany_id;
    }

    
    public function set_user_role($user_role) {
        $this->user_role = $user_role;
    }

    public function get_user_role() {
        return $this->user_role;
    }

    public function get_user_id() {
        return $this->user_id;
    }

    public function set_user_id($user_id) {
        $this->user_id = $user_id;
    }

    public function init() {
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
        $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
        $this->sysdoc_path = '../../' . $config->dir->document_root;


        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
        $user = Zend_Auth::getInstance()->getIdentity();
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user->user_name);
        $user_data = $db_user->fetchRow($where);
        $biller_id = $user_data['reference_id'];
        $this->set_user_role($user_data['role']);
        $this->set_user_id($user_data['id']);
        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $where = $db->quoteInto('id = ?', $biller_id);
        $biller_data = $db_biller->fetchRow($where);
        $this->billingcompany_id = $biller_data['billingcompany_id'];
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $where = $db->quoteInto('id=?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where);
        if ($billingcompany_data['appeal1']) {
            $this->appeal['appeal1'] = $billingcompany_data['appeal1'];
        }
        if ($billingcompany_data['appeal2']) {
            $this->appeal['appeal2'] = $billingcompany_data['appeal2'];
        }
        if ($billingcompany_data['appeal3']) {
            $this->appeal['appeal3'] = $billingcompany_data['appeal3'];
        }
    }

    /**
     * excel2array
     *
     * extract data from excel
     *
     * @author james
     * @version 5/19/2015
     *
     * @param string $filepath
     * @param string $sheetIndex, to indicate which sheet do you want to read
     */
    public function excel2array($filepath, $sheetIndex) {
        //tested under: ../library/ICD9_DX_Codes.xls
        $xl_reader = new Spreadsheet_Excel_Reader();
        $xl_reader->read($filepath);

        $data = array();
        $data = $xl_reader->sheets;
        $data = $data[$sheetIndex];
        $shellNames = $data[cells][1];
        $shellContent = array_slice($data[cells], 1);

        $excelData = array();
        $excelData = array('shellNames' => $shellNames, 'shellContent' => $shellContent);

        return $excelData;
    }

    public function deletenew(&$insuranceList, $outkey, $newstring) {
        $temp = count($insuranceList);
        for ($i = 0; $i < count($insuranceList); $i++) {
            //if($insuranceList[$i]['insurance_name'] == 'Need New Insurance') {
            if ($insuranceList[$i][$outkey] == $newstring) {
                $key = $i;
                $temp = array($key => $insuranceList[$key]);
                unset($insuranceList[$key]);
                for ($j = $key; $j < count($insuranceList) - 1; $j++)
                    $insuranceList[$j] = $insuranceList[$j + 1];
                unset($insuranceList[$j]);
                break;
            }
        }
    }

    public function indexAction() {
// action body
        $this->clearsession();
    }

    public function initsession($patientid, $encounterid) {
        $this->clearsession();
        if ($patientid == null)
            return;

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
        $_SESSION['insurancepayments_data'] = $service->get_insuracnepayments();
        $_SESSION['patientpayments_data'] = $service->get_patientpayments();
        $_SESSION['billeradjustments_data'] = $service->get_billeradjustments();
        $_SESSION['interactionlogs_data'] = $service->get_interactionlogs();
        $_SESSION['assignedclaims_data'] = $service->get_assignedclaims();

        /*         * ******************Add encounter insured************************ */
        $encounterinsured_data = $service->get_encounterinsured();
        for ($i = 0; $i < count($encounterinsured_data); $i++) {
            $encounterinsured_data[$i]['change_flag'] = 0;
        }
        $_SESSION['encounterinsured_data'] = $encounterinsured_data;
        /*         * ******************Add encounter insured************************ */


        $dd = 0;

        $TT = $service->get_assignedclaims();
        $datas = array('patient', 'insured', 'insurance', 'encounter', 'claim', 'followups', 'insurancepayments');

        session_start();
        $tmp_patient_data = $_SESSION['patient_data'];

        $_SESSION['patient_data_BK'] = $_SESSION['patient_data'];
        $_SESSION['insured_data_BK'] = $_SESSION['insured_data'];
        $_SESSION['insurance_data_BK'] = $_SESSION['insurance_data'];
        $_SESSION['encounter_data_BK'] = $_SESSION['encounter_data'];
        $_SESSION['claim_data_BK'] = $_SESSION['claim_data'];
        $_SESSION['statement_data_BK'] = $_SESSION['statement_data'];
        $_SESSION['followups_data_BK'] = $_SESSION['followups_data'];
        $_SESSION['insurancepayments_data_BK'] = $_SESSION['insurancepayments_data'];
        $_SESSION['billeradjustments_data_BK'] = $_SESSION['billeradjustments_data'];
        $_SESSION['interactionlogs_data_BK'] = $_SESSION['interactionlogs_data'];
        $_SESSION['patientpayments_data_BK'] = $_SESSION['patientpayments_data'];
        $_SESSION['assignedclaims_data_BK'] = $_SESSION['assignedclaims_data'];

        /*         * *******************Add encounter insured data ***************** */
        $_SESSION['encounterinsured_data_BK'] = $_SESSION['encounterinsured_data'];
        $_SESSION['patientinsured_data_BK'] = $_SESSION['patientinsured_data'];
        /*         * *******************Add encounter insured data ***************** */


        $test = $_SESSION['assignedclaims_data_BK'];

        $insurance = $_SESSION['insurance_data'];

        if ($_SESSION['claim_data']['claim_status'] == null || $_SESSION['claim_data']['bill_status'] == null) {
            if ($_SESSION['claim_data']['bill_status'] == null) {
                $bill_status = '';
                if (strtoupper($insurance['payer_type']) == 'MM')
                    $bill_status = 'bill_ready_bill_delayed_primary';
                else {
                    if (!strtoupper($insurance['payer_type']) == 'SP')
                        $bill_status = 'bill_ready_bill_primary';
                }
                $_SESSION['claim_data']['bill_status'] = $bill_status;
            }else {
                if (strtoupper($insurance['payer_type']) == 'SP')
                    $_SESSION['claim_data']['claim_status'] = "inactive_selfpay";
            }
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
    }

    /**
     * set_options_session
     *
     * set options session
     *
     * @author Xia Shanshan
     * @version 4/26/2014
     *
     * @param string $providerid provider.id
     * @param string $providername provider.provider_name
     * @param string $billingcompanyid billingcompany.id
     */
    function adddatalog($provider_id, $table_name, $DBfield, $oldvalue, $newvalue) {
        //$this->_helper->viewRenderer->setNoRender();
        if (!$newvalue) {
            $newvalue = NULL;
        } else {
            
        }
        if (!$oldvalue) {
            $oldvalue = NULL;
        } else {
            
        }

        $db_provider1 = new Application_Model_DbTable_Provider();
        $db1 = $db_provider1->getAdapter();


        if ($newvalue !== $oldvalue) {
            $user = Zend_Auth::getInstance()->getIdentity();
            $db = Zend_Registry::get('dbAdapter');

            $user_name = $user->user_name;

            $Now_time = date("Y-m-d H:i:s");

            $logs['data_name'] = $table_name;
            $logs['user'] = $user_name;
            $logs['data_and_time'] = $Now_time;
            $logs['dbfield'] = $DBfield;
            $logs['oldvalue'] = $oldvalue;
            $logs['newvalue'] = $newvalue;

            $db_datalogs = new Application_Model_DbTable_Datalog();
            $dblog = $db_datalogs->getAdapter();
            if ($provider_id == 0) {
                $billingcompany_id = $this->billingcompany_id();
                $where = $db1->quoteInto('billingcompany_id=?', $billingcompany_id);
                $provider_data = $db_provider1->fetchAll($where)->toArray();
                for ($i = 0; $i < count($provider_data); $i++) {
                    $provider_name = $provider_data[$i]['provider_name'];
                    $logs['provider'] = $provider_name;
                    $db_datalogs->insert($logs);
                }
                //$provider_name = 'ALL';
            } else {
                $where1 = $db1->quoteInto('id = ?', $provider_id);
                // $oldprovider[0]=$db_provider->geta
                //   $where = $db->quoteInto('id = ?', $provider_id);
                $oldprovider = $db_provider1->fetchAll($where1)->toArray();
                $provider_name = $oldprovider[0]['provider_name'];
                $logs['provider'] = $provider_name;
                $db_datalogs->insert($logs);
            }

            return 1;
        } else {
            return 0;
        }
    }

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
            $select->where('billingcompany.id=?', $this->billingcompany_id());
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
     * @author Qiao Xia Shanshan
     * @version 4/26/2014
     */
    public function unset_options_session() {
        session_start();
        unset($_SESSION['options']);
    }

    /**
     * a function chage number.
     * @author caijun.
     * @version 01/6/2014
     */
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
    
    /**
     * get the location of a certain char in  a certain string
     *input:file path
     * output:file content
     * @author james.
     * @version 08/10/2015
     */
    public function getCharP($str, $char) {
        $j = 0;
        $arr = array();
        $count = substr_count($str, $char);
        for ($i = 0; $i < $count; $i++) {
            $j = strpos($str, $char, $j);
            $arr[] = $j;
            $j = $j + 1;
        }
        return $arr;
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

    /**
     * reportsAction
     * a function generating reports.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function reportsAction() {
        $billingcompany_id = $this->billingcompany_id();
        $this->view->year = date('Y');
        $this->view->mouth = date('m');
        if (!$this->getRequest()->isPost()) {
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $role = $this->get_user_role();
            $whereprovider = array();
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
                // $providerList_start[1]=2;  ,
                if (isset($providerList_start[0])) {
                    $whereprovider = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()) . $db->quoteInto('and id in (?)', $providerList_start);
                    ;
                    $providerList = $db_provider->fetchAll($whereprovider, "provider_name ASC");
                }
            } else {
                $whereprovider = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
                $providerList = $db_provider->fetchAll($whereprovider, "provider_name ASC");
            }

            $this->view->providerList = $providerList;
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
                //get renderingprovider list
                $select = $db->select();
                $select->from('facility', array('facility.id as fid', 'facility_display'));
                $select->join('providerhasfacility', 'facility.id = providerhasfacility.facility_id');
                $select->join('provider', 'providerhasfacility.provider_id = provider.id');
                $select->where('provider.billingcompany_id = ?', $this->billingcompany_id());
                $select->where("providerhasfacility.provider_id IN (?)", $provider_id_list);
                $select->group('facility.id');
                $select->order('facility_display ASC');
                $facilityList = $db->fetchAll($select);
                $this->view->facilityList = $facilityList;



                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('anesthesiacode', 'anesthesiacode.anesthesia_code as anesthesia_id');
                $select->where("provider_id IN (?)", $provider_id_list);
                $select->group('anesthesiacode.anesthesia_code');
                $select->order('anesthesia_code ASC');
                $anesthesiacodeList = $db->fetchAll($select);
                $this->view->anesthesiaList = $anesthesiacodeList;
                $billingcompany_id = $this->billingcompany_id;
                $report_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/reports';
                $report_paths = array();
                $new_path_report = array();
                if (is_dir($report_dir)) {
                    foreach (glob($report_dir . '/*.pdf') as $filename) {
                        array_push($report_paths, $filename);
                    }
                }
                $display = array();
                for ($i = 0; $i < count($report_paths); $i++) {
                    $new_path_report[$i]['path'] = $report_paths[$i];

                    $report_paths_array = explode('/', $report_paths[$i]);
                    $new_path_report[$i]['display'] = $report_paths_array[count($report_paths_array) - 1];
                    $display[$i] = $new_path_report[$i]['display'];
                }
                array_multisort($display, SORT_DESC, $new_path_report);
                $this->view->reportsummaryList = $new_path_report;
                $billingcompany_id = $this->billingcompany_id;
                $report_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/reports';
                $report_paths = array();
                $new_path_report = array();
                if (is_dir($report_dir)) {
                    foreach (glob($report_dir . '/*.csv') as $filename) {
                        array_push($report_paths, $filename);
                    }
                }
                $display = array();
                for ($i = 0; $i < count($report_paths); $i++) {
                    $new_path_report[$i]['path'] = $report_paths[$i];

                    $report_paths_array = explode('/', $report_paths[$i]);
                    $new_path_report[$i]['display'] = $report_paths_array[count($report_paths_array) - 1];
                    $display[$i] = $new_path_report[$i]['display'];
                }
                array_multisort($display, SORT_DESC, $new_path_report);
                $this->view->reportcustomList = $new_path_report;

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('cptcode');
                $select->where("provider_id IN (?)", $provider_id_list);
                $select->group('CPT_code');
                $select->order('CPT_code ASC');
                $cptcodelist = $db->fetchAll($select);
                $this->view->cptcodelist = $cptcodelist;
            }
            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $billingcompany_id);
            $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
            movetotop($insuranceList);
            $this->view->insuranceList = $insuranceList;

            $db = Zend_Registry::get('dbAdapter');
            //get the claim_status list
            $select = $db->select();
            $select->from('claimstatus');
            $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
            $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->billingcompany_id) or where('claimstatus.requried = ?', 1);
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
            $select->from('billstatus');
            $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
            $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->billingcompany_id)or where('billstatus.requried = ?', 1);
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
            $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->billingcompany_id)or where('statementstatus.requried = ?', 1);
            $select->group('statementstatus.id');
            $select->order('statementstatus.statement_status_display');
            try {
                $statementstatus = $db->fetchAll($select);
            } catch (Exception $e) {
                echo "errormessage:" + $e->getMessage();
            }
            $this->view->statementstatusList = $statementstatus;
        }
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Generate Summary Report") {

                $provider_id = $this->getRequest()->getPost('provider_id1');
                $time = $this->getRequest()->getPost('Time');
                $time_explode = explode("/", $time);
                $month = $time_explode[0];
                $year = $time_explode[1];
                if (!$month || $month == "MM") {
                    $month = date('m');
                }
                if (!$year || $year == "YYYY") {
                    $year = date('Y');
                }
                $user_id = $this->get_user_id();
                $role = $this->get_user_role();
//                $year=$this->getRequest()->getPost('year');
//                $month=$this->getRequest()->getPost('month');
                $filename = gen_report($billingcompany_id, $provider_id, $year, $month, $this->sysdoc_path, $user_id, $role);
                session_start();
                $_SESSION['downloadfilename'] = $filename;
                $this->_redirect('/biller/data/reports');
            }
            if ($submitType == "Open Summary Report") {
                $filename = $this->getRequest()->getPost('reportsummary_dir');
                $_SESSION['downloadfilename'] = $filename;
                $this->_redirect('/biller/data/reports');
            }
            if ($submitType == "Open Custom Report") {
                $filename = $this->getRequest()->getPost('reportcustom_dir');
                if (file_exists($filename)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($filename));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($filename) . ' bytes');
                    //ob_clean();
                    //flush();
                    readfile($filename);
                    exit;
                }
                $this->_redirect('/biller/data/reports');
            }
            if ($submitType == "Generate   Custom   Report") {
                $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                $db = $db_userfocusonprovider->getAdapter();
                $where = $db->quoteInto('user_id = ?', $this->user_id);
                $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
                $provider_id_list = array();
                for ($i = 0; $i < count($userfocusonprovider); $i++) {
                    $provider_id_list[$i] = $userfocusonprovider[$i]['provider_id'];
                }
                $provider_id_array = $this->getRequest()->getPost('provider_id_array');
                $renderingprovider_id = $this->getRequest()->getPost('renderingprovider_id');
                $referringprovider_id = $this->getRequest()->getPost('referringprovider_id');
                $last_name = $this->getRequest()->getPost('last_name');
                /*                 * **************************Add first_name custom report****************** */
                $first_name = $this->getRequest()->getPost('first_name');
                /*                 * **************************Add first_name custom report****************** */
                $facility_id = $this->getRequest()->getPost('facility_id');
                $insurance_id = $this->getRequest()->getPost('insurance_id');
                $claim_status = $this->getRequest()->getPost('claim_status');
                $account_number = $this->getRequest()->getPost('account_number');

                $demographics = $this->getRequest()->getPost('demographics');
                $Extended_demographics = $this->getRequest()->getPost('Extended_demographics');

                $provider = $this->getRequest()->getPost('provider');
                $Extended_provider = $this->getRequest()->getPost('Extended_provider');
                $Extended_services = $this->getRequest()->getPost('Extended_services');
                $Extended_claimdetails = $this->getRequest()->getPost('Extended_claimdetails');
                $bill_status_array = $this->getRequest()->getPost('bill_status_array');
                $statement_status_array = $this->getRequest()->getPost('statement_status_array');
                $services = $this->getRequest()->getPost('services');
                $claimdetails = $this->getRequest()->getPost('claimdetails');
                $payments = $this->getRequest()->getPost('payments');
                $insurance_id_array = $this->getRequest()->getPost('insurance_id_array');
                $claim_status_array = $this->getRequest()->getPost('claim_status_array');
                $anesthesia_id_array = $this->getRequest()->getPost('anesthesia_id_array');
                $cptcode_array = $this->getRequest()->getPost('cptcode_array');
                $insurance_type_array = $this->getRequest()->getPost('insurance_type_array');
                $logs = $this->getRequest()->getPost('logs');
                if ($this->getRequest()->getPost('max_charge') != null)
                    $max_charge = $this->getRequest()->getPost('max_charge');
                if ($this->getRequest()->getPost('min_charge') != null)
                    $min_charge = $this->getRequest()->getPost('min_charge');
                if ($this->getRequest()->getPost('max_paid') != null)
                    $max_paid = $this->getRequest()->getPost('max_paid');
                if ($this->getRequest()->getPost('min_paid') != null)
                    $min_paid = $this->getRequest()->getPost('min_paid');
                $last_date = null;
                $last_days = $this->getRequest()->getPost("last_days");
                if ($last_days != null) {
                    $last_date = date("Y-m-d", strtotime('-' . $last_days . ' day'));
                }

                $phone_num = $this->getRequest()->getPost('phone_num');
                if ($phone_num != null) {
                    if ($phone_num[0] == '(') {
                        $find = array('(', ')', '-');
                        $phone_num_tmp = str_replace($find, null, $phone_num);
                        $phone_num = $phone_num_tmp;
                    }
                }
                $provider_id = array();
                $insurance_id = array();
                $claim_status = array();
                $cpt_code = array();
                $anesthesia_id = array();
                $insurance_type = array();
                if (strlen($insurance_id_array) > 0) {
                    $insurance_id = explode(',', $insurance_id_array);
                }
                if (strlen($claim_status_array) > 0) {
                    $claim_status = explode(',', $claim_status_array);
                }
                if (strlen($bill_status_array) > 0) {
                    $bill_status = explode(',', $bill_status_array);
                }
                if (strlen($statement_status_array) > 0) {
                    $statement_status = explode(',', $statement_status_array);
                }

                if (strlen($provider_id_array) > 0) {
                    $provider_id = explode(',', $provider_id_array);
                }
                if (strlen($anesthesia_id_array) > 0) {
                    $anesthesia_id = explode(',', $anesthesia_id_array);
                }
                if (strlen($insurance_type_array) > 0) {
                    $insurance_type = explode(',', $insurance_type_array);
                }
                if (strlen($cptcode_array) > 0) {
                    $cpt_code = explode(',', $cptcode_array);
                }


                if ($this->getRequest()->getPost('start_date') != null)
                    $start_date = format($this->getRequest()->getPost('start_date'), 0);
                if ($this->getRequest()->getPost('end_date') != null)
                    $end_date = format($this->getRequest()->getPost('end_date'), 0);

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();

                $select->from('renderingprovider', array('CONCAT(\'"\',renderingprovider.last_name,\', \',renderingprovider.first_name,\'"\') As renderingprovider', 'renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name', 'CONCAT(\'"\',renderingprovider.NPI, \'"\') AS renderingprovider_NPI'));
                //$select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id');
                $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('CONCAT(\'="\',diagnosis_code1, \'"\') As diagnosis_code1', 'CONCAT(\'="\', diagnosis_code2, \'"\')  As diagnosis_code2', 'CONCAT(\'="\', diagnosis_code3, \'"\') as diagnosis_code4', 'CONCAT(\'="\', diagnosis_code4, \'"\')', 'encounter.id AS encounter_id', 'claim_id', 'start_date_1', 'days_or_units_1', 'CONCAT(\'="\',CPT_code_1, \'"\')  AS CPT_code_1', 'CONCAT(\'="\',secondary_CPT_code_1, \'"\')  AS secondary_CPT_code_1', 'charges_1', 'start_date_2', 'days_or_units_2', 'CONCAT(\'="\',CPT_code_2, \'"\')  AS CPT_code_2', 'CONCAT(\'="\',secondary_CPT_code_2, \'"\')  AS secondary_CPT_code_2', 'charges_2', 'start_date_3', 'days_or_units_3', 'CONCAT(\'="\',CPT_code_3, \'"\')  AS CPT_code_3', 'CONCAT(\'="\',secondary_CPT_code_3, \'"\')  AS secondary_CPT_code_3', 'charges_3', 'start_date_4', 'days_or_units_4', 'CONCAT(\'="\',CPT_code_4, \'"\')  AS CPT_code_4', 'CONCAT(\'="\',secondary_CPT_code_4, \'"\')  AS secondary_CPT_code_4', 'charges_4', 'start_date_5', 'days_or_units_5', 'CONCAT(\'="\',CPT_code_5, \'"\') AS CPT_code_5', 'CONCAT(\'="\',secondary_CPT_code_5, \'"\') AS secondary_CPT_code_5', 'charges_5', 'start_date_6', 'days_or_units_6', 'CONCAT(\'="\',CPT_code_6, \'"\') AS CPT_code_6', 'CONCAT(\'="\',secondary_CPT_code_6, \'"\') AS secondary_CPT_code_6', 'charges_6', 'CONCAT(\'"\',encounter.notes, \'"\') AS encounter_notes', 'end_date_1', 'end_date_2', 'end_date_3', 'end_date_4', 'end_date_5', 'end_date_6', 'start_time_1', 'start_time_2', 'start_time_3', 'start_time_4', 'start_time_5', 'start_time_6', 'end_time_1', 'end_time_2', 'end_time_3', 'end_time_4', 'end_time_5', 'end_time_6', 'place_of_service_1', 'place_of_service_2', 'place_of_service_3', 'place_of_service_4', 'place_of_service_5', 'place_of_service_6', 'diagnosis_pointer_1', 'diagnosis_pointer_2', 'diagnosis_pointer_3', 'diagnosis_pointer_4', 'diagnosis_pointer_5', 'diagnosis_pointer_6')); //,' CPT_code_1 As CPT_code_1', 'secondary_CPT_code_1 As secondary_CPT_code_1','CPT_code_2 As CPT_code_2', 'secondary_CPT_code_2 As secondary_CPT_code_2','CPT_code_3 As CPT_code_3', 'secondary_CPT_code_3 As secondary_CPT_code_3','CPT_code_4 As CPT_code_4', 'secondary_CPT_code_4 As secondary_CPT_code_4','CPT_code_5 As CPT_code_5', 'secondary_CPT_code_5 As secondary_CPT_code_5','CPT_code_6 As CPT_code_6', 'secondary_CPT_code_6 As secondary_CPT_code_6'));
                //$select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('claim_id', 'start_date_1', 'days_or_units_1', 'CONCAT(\'="\', CPT_code_1, \'"\') As CPT_code_1', 'CONCAT(\'="\', secondary_CPT_code_1, \'"\') As secondary_CPT_code_1', 'charges_1', 'start_date_2', 'days_or_units_2', 'CONCAT(\'="\', CPT_code_2, \'"\') As CPT_code_2', 'CONCAT(\'="\', secondary_CPT_code_2, \'"\') As secondary_CPT_code_2', 'charges_2', 'start_date_3', 'days_or_units_3', 'CONCAT(\'="\', CPT_code_3, \'"\') As CPT_code_3', 'CONCAT(\'="\', secondary_CPT_code_3, \'"\') As secondary_CPT_code_3', 'charges_3', 'start_date_4', 'days_or_units_4', 'CONCAT(\'="\', CPT_code_4, \'"\') As CPT_code_4', 'CONCAT(\'="\', secondary_CPT_code_4, \'"\') As secondary_CPT_code_4', 'charges_4', 'start_date_5', 'days_or_units_5', 'CONCAT(\'="\', CPT_code_5, \'"\') As CPT_code_5', 'CONCAT(\'="\', secondary_CPT_code_5, \'"\') As secondary_CPT_code_5', 'charges_5', 'start_date_6', 'days_or_units_6', 'CONCAT(\'="\', CPT_code_6, \'"\') As CPT_code_6', ' secondary_CPT_code_6, \'"\') As secondary_CPT_code_6', 'charges_6'));
                $select->join('referringprovider', 'encounter.referringprovider_id=referringprovider.id', array('CONCAT(\'"\',referringprovider.last_name,\', \',referringprovider.first_name,\'"\') As referringprovider', 'CONCAT(\'"\',referringprovider.NPI, \'"\') AS referringprovider_NPI'));
                $select->join('claim', 'claim.id=encounter.claim_id', array('total_charge', 'amount_paid', 'balance_due', 'expected_payment', 'date_creation', 'date_billed', 'date_last_billed', 'date_secondary_insurance_billed', 'date_closed', 'CONCAT(\'"\',claim.notes,\'"\') AS claim_notes'));

                $select->joinLeft('claimstatus', 'claimstatus.claim_status=claim.claim_status', array('CONCAT(\'"\',claim_status_display,\'"\') AS claim_status_display'));
                $select->joinLeft('billstatus', 'billstatus.bill_status=claim.bill_status', array('CONCAT(\'\',bill_status_display,\'\') AS bill_status_display'));
                $select->joinLeft('statementstatus', 'statementstatus.statement_status=claim.statement_status', array('CONCAT(\'\',statement_status_display,\'\') AS statement_status_display'));
//$select->join('billingcompanyclaimstatus','claimstatus.id=billingcompanyclaimstatus.claimstatus_id');

                $select->join('provider', 'provider.id=encounter.provider_id', array('CONCAT(\'"\',provider_name,\'"\') AS provider_name', 'CONCAT(\'"\',provider.billing_street_address,\'"\') AS provider_street_address', 'CONCAT(\'"\',billing_state,\'"\') AS provider_state', 'provider.billing_zip AS provider_zip ', 'CONCAT(\'"\',provider.notes,\'"\') as provider_notes', 'CONCAT(\'"\',provider.billing_provider_NPI,\'"\')  as provider_NPI'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('CONCAT(\'"\',facility.facility_name,\'"\') AS facility_name', 'CONCAT(\'"\',facility.street_address,\'"\') AS facility_street_address', 'facility.state as facility_state', 'facility.zip AS facility_zip', 'CONCAT(\'"\',facility.NPI,\'"\') AS facility_NPI', 'CONCAT(\'"\',facility.notes,\'"\') as facility_notes'));
                $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
                $select->join('patient', 'encounter.patient_id = patient.id', array('CONCAT(\'"\',patient.last_name,\', \',patient.first_name,\'"\') As patient_name', 'patient.last_name as patient_last_name', 'patient.first_name as patient_first_name', 'patient.DOB as patient_DOB', 'CONCAT(\'="\',patient.account_number,\'"\') As MRN', 'patient.sex as patient_sex', 'CONCAT(\'"\',patient.street_address,\'"\') as patient_street_address', 'CONCAT(\'"\',patient.city,\'"\') as patient_city', 'CONCAT(\'"\',patient.state,\'"\') as patient_state', 'patient.zip As patient_zip', 'patient.SSN As patient_SSN', 'patient.phone_number AS phone_number', 'patient.second_phone_number AS second_phone_number', 'patient.phone_number As patient_phone_number', 'patient.second_phone_number As patient_second_phone_number', 'CONCAT(\'"\',patient.notes,\'"\') As patient_notes', 'CONCAT(\'"\',patient.alert,\'"\') As patient_alerts'));

                $select->join('encounterinsured', 'encounterinsured.encounter_id =encounter.id', array('encounterinsured.type As encounterinsured_type'));
                $select->join('insured', 'encounterinsured.insured_id =insured.id', array('encounterinsured.encounter_id AS MY_encounter_id'));
                /*                 * *new for inquiry** */

                $select->join('insurance', 'insurance.id=insured.insurance_id', array('CONCAT(\'"\',insurance_display,\'"\') As insurance_display'));
                $select->join('anesthesiacode', 'anesthesiacode.provider_id=provider.id', array('CONCAT(\'="\', anesthesia_code,\'"\') As anesthesia_code'));
                //$select->join('cptcode', 'cptcode.anesthesiacode_id=anesthesiacode.id', array('CONCAT(\'="\', CPT_code,\'"\') As CPT_code'));
                $select->join('followups', 'followups.claim_id=claim.id', array('followups.amount_initial_offer AS amount_initial_offer', 'followups.date_initial_offer AS date_initial_offer', 'followups.negotiated_payment_amount AS negotiated_payment_amount', 'followups.date_negotiated_amount_reached AS date_negotiated_amount_reached'));

                if ($insurance_type != null)
                    $select->where('encounterinsured.type in (?)', $insurance_type);
                //$select->where('billingcompanyclaimstatus.billingcompany_id=?',$this->billingcompany_id());
                $select->where('billingcompany.id=?', $this->billingcompany_id());
                $role = $this->get_user_role();
                $user_id = $this->get_user_id();
                if ($role != 'guest') {
                    if ($provider_id != null)
                        $select->where('provider.id IN (?)', $provider_id);
                }
                else {
                    if ($provider_id != null) {
                        $select->where('provider.id IN (?)', $provider_id);
                    } else {
                        // $providerList_start[1]=2;                            
                        //   $this->view->providerList = $providerList;                      
                        if ($provider_id_list != null)
                            $select->where('provider.id IN (?)', $provider_id_list);
                    }
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
//                 if ($provider_id != null)
//                    $select->where('anesthesiacode.anesthesia_code IN (?)', $anesthesia_id);
//                  if ($provider_id != null)
//                    $select->where('cptcode.CPT_code IN (?)', $cptcode);
                if ($insurance_id != null)
                    $select->where('insurance.id IN(?)', $insurance_id);
//                if($insurance_type !=null)
//                    $select->where('insurance.insurance_type IN(?)', $insurance_type);
                if ($anesthesia_id != null) {
                    $select->where('encounter.secondary_CPT_code_1 IN(?) OR encounter.secondary_CPT_code_2 IN(?) OR encounter.secondary_CPT_code_3 IN(?) OR encounter.secondary_CPT_code_4 IN(?) OR encounter.secondary_CPT_code_5 IN(?) OR encounter.secondary_CPT_code_6 IN(?)', $anesthesia_id);
                }
                if ($last_name != null)
                    $select->where('patient.last_name LIKE ?', $last_name . '%');
                /*                 * **************************Add first_name custom report****************** */
                if ($first_name != null)
                    $select->where('patient.first_name LIKE ?', $first_name . '%');
                /*                 * **************************Add first_name custom report****************** */

                if ($facility_id != null) {
                    $select->where('facility.id=?', $facility_id);
                }
                if ($end_date == null && $start_date != null) {
                    $select->where('encounter.start_date_1=?', $start_date);
                }
                if ($end_date != null && $start_date != null) {
                    $select->where('encounter.start_date_1>=?', $start_date);
                    $select->where('encounter.start_date_1<=?', $end_date);
                }
                if ($max_charge == null && $min_charge != null) {
                    $select->where('claim.total_charge=?', $min_charge);
                } else if ($max_charge != null && $min_charge != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('claim.total_charge>=?', $min_charge);
                    $select->where('claim.total_charge<=?', $max_charge);
                } else if ($max_charge != null && $min_charge == null) {
                    return null;
                }
                if ($max_paid == null && $min_paid != null) {
                    $select->where('claim.amount_paid=?', $min_paid);
                } else if ($max_paid != null && $min_paid != null) {
                    //$select->where('encounter.start_date_1=?', $start_date);
                    $select->where('claim.amount_paid>=?', $min_paid);
                    $select->where('claim.amount_paid<=?', $max_paid);
                } else if ($max_paid != null && $min_paid == null) {
                    return null;
                }
                if ($last_date != null) {
                    $select->where('claim.update_time>=?', $last_date);
                }

                /*                 * changed to allow  MRN partial search under Reports#james */
//                if ($account_number != null) {
//                    $select->where('patient.account_number=?', $account_number);
//                }
                if ($account_number != null) {
                    $select->where('patient.account_number LIKE ?', '%' . $account_number . '%');
                }
                if ($phone_num != null) {
                    $select->where('patient.phone_number=? OR patient.second_phone_number=?', $phone_num);
                }
                if (count($cpt_code) > 0) {
                    $select->where('encounter.CPT_code_1 IN(?) OR encounter.CPT_code_2 IN(?) OR encounter.CPT_code_3 IN(?) OR encounter.CPT_code_4 IN(?) OR encounter.CPT_code_5 IN(?) OR encounter.CPT_code_6 IN(?)', $cpt_code);
                }
                $select->group('MY_encounter_id');
                $select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB', 'patient.account_number'));
                $patient = array();
                if ('guest' != $role)
                    $patient = $db->fetchAll($select);
                else {
                    if ($provider_id_list != null)
                        $patient = $db->fetchAll($select);
                }

                $tmp_billingcompany_id = $this->billingcompany_id;

                $dd = 0;
                $count_patient = count($patient);
                for ($i = 0; $i < $count_patient; $i++) {
                    $row = $patient[$i];
                    // if($row['encounterinsured_type']=='primary'){
                    //$patientList[$count]['insurance_name'] = $row['insurance_name'];
                    //$patientList[$count]['insurance_display'] = $row['insurance_display'];
                    $encounter_id = $row['encounter_id'];
                    $db_encounterinsured = new Application_Model_DbTable_Encounterinsured();
                    $db = $db_encounterinsured->getAdapter();
                    //$quote = 'encounter_id = ' .  $encounter_id . 'AND' . 'type = secondary';
                    $type_tmp_array = array('primary', 'secondary', 'tertiary', 'other');
                    for ($j = 0; $j < 4; $j++) {
                        $type_tmp = $type_tmp_array[$j];
                        $typename = 'insurance_' . $type_tmp;
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

                            $temp_text = $insurance_tmp['insurance_display'] . '/' . $insured_tmp['ID_number'] . '/' . $insured_tmp['last_name'] . ', ' . $insured_tmp['first_name'];
                            // $ttt = $data[$i][$fields[$j]];
                            $t = "\t{$temp_text}";
                            $ttt = '"' . $t . '"';
                            $patient[$i][$typename] = $ttt;
                            //$patient[$i]['insurance_s_display'] = $insurance_tmp['insurance_display'];
                        } else {
                            $patient[$i][$typename] = NULL;
                        }
                    }
                }

                $data = array();

                $fields = array('patient_name', 'patient_DOB', 'MRN', 'insurance_primary', 'total_charge', 'amount_paid', 'claim_status_display', 'bill_status_display', 'statement_status_display', 'start_date_1');
                //$fields = array('patient_last_name', 'patient_first_name', 'patient_DOB', 'MRN', 'insurance_name', 'total_charge', 'amount_paid', 'claim_status', 'facility_name', 'start_date_1');
                $display_fields = array('Name', 'DOB', 'MRN', 'Primary Insurance', 'Total Charge', 'Amount Paid', 'Claim Status', 'Bill Status', 'Statement Status', 'DOS');
                if ($Extended_demographics[0] == 'on' || $demographics[0] == 'on') {
                    $patient_fields = array('patient_sex', 'patient_street_address', 'patient_city', 'patient_state', 'patient_zip', 'patient_phone_number', 'patient_second_phone_number');
                    $display_patient_fields = array('Sex', 'Street Address', 'City', 'State', 'Zip', 'Phone Number', 'Second Phone Number');
                    $fields = array_merge_recursive($fields, $patient_fields);
                    $display_fields = array_merge_recursive($display_fields, $display_patient_fields);
                }
                if ($Extended_demographics[0] == 'on') {
                    //'Sex', 'Street Address', 'City', 'State', 'Zip', 'SSN', 'Phone Number', 'Second
                    //                Phone Number', ‘Primary Insurance’, ‘Secondary Insurance’, ‘Tertiary Insurance’, ‘Other
                    //                Insurance’, ‘Expired Insurance’ (insurance info comes from patientinsured table, type comes
                    //                from insured.insured_insurance_type)
                    $Extended_patient_fields = array('patient_notes', 'patient_alerts', 'patient_SSN');
                    $Extended_display_patient_fields = array('Notes', 'Alert', 'SSN');
                    $fields = array_merge_recursive($fields, $Extended_patient_fields);
                    $display_fields = array_merge_recursive($display_fields, $Extended_display_patient_fields);
                }
                //[Default Fields], ‘Provider’, ‘Street Address’ (use Billing_), ‘State’, ‘Zip’, ‘NPI’, ‘Rendering
                //Provider’, ‘NPI, ‘Referring Provider’, ‘NPI’, ‘Facility’, ‘Street Address’, ‘State’, ‘Zip’, ’NPI’
                if ($Extended_provider[0] == 'on' || $provider[0] == 'on') {
                    $provider_fields = array('provider_name', 'provider_street_address', 'provider_state', 'provider_zip', 'provider_NPI', 'renderingprovider', 'renderingprovider_NPI', 'referringprovider', 'referringprovider_NPI', 'facility_name', 'facility_street_address', 'facility_state', 'facility_zip', 'facility_NPI');
                    $display_provider_fields = array('Provider', 'Street Address', 'State', 'Zip', 'NPI', 'Renderingprovider', 'NPI', 'Referringprovider', 'NPI', 'Facility', 'Street Address', 'State', 'Zip', 'NPI');
                    $fields = array_merge_recursive($fields, $provider_fields);
                    $display_fields = array_merge_recursive($display_fields, $display_provider_fields);
                }
                if ($Extended_provider[0] == 'on') {
                    $Extended_provider_fields = array('provider_notes', 'facility_notes');
                    $Extended_display_provider_fields = array('Provide Notes', 'Facility Notes');
                    $fields = array_merge_recursive($fields, $Extended_provider_fields);
                    $display_fields = array_merge_recursive($display_fields, $Extended_display_provider_fields);
                }

                if ($Extended_services[0] == 'on' || $services[0] == 'on') {


                    ///if($provider[0] != 'on'){
                    $services_provider_fields = array('provider_name', 'renderingprovider', 'referringprovider', 'facility_name', 'insurance_primary', 'insurance_secondary', 'insurance_tertiary', 'insurance_other');
                    $services_display_provider_fields = array('Provider', 'Renderingprovider', 'Referringprovider', 'Facility', 'Primary Insurance', 'Secondary Insurance', 'Tertiary Insurance', 'Other insurance');
                    $fields = array_merge_recursive($fields, $services_provider_fields);
                    $display_fields = array_merge_recursive($display_fields, $services_display_provider_fields);
                    ///}
                    $services_fields_1 = array('diagnosis_code1', 'diagnosis_code2', 'diagnosis_code3', 'diagnosis_code4');
                    $display_services_fields_1 = array('Diagnostic code1', 'Diagnostic code2', 'Diagnostic code3', 'Diagnostic code4');
                    $fields = array_merge_recursive($fields, $services_fields_1);
                    $display_fields = array_merge_recursive($display_fields, $display_services_fields_1);
                    $services_fields = array();
                    $display_services_fields = array();

                    for ($i = 1; $i <= 6; $i++) {
                        array_push($services_fields, 'start_date_' . $i);
                        array_push($services_fields, 'end_date_' . $i);
                        array_push($services_fields, 'start_time_' . $i);
                        array_push($services_fields, 'end_time_' . $i);
                        array_push($services_fields, 'minutes_' . $i);

                        array_push($services_fields, 'days_or_units_' . $i);
                        array_push($services_fields, 'CPT_code_' . $i);
                        array_push($services_fields, 'secondary_CPT_code_' . $i);
                        array_push($services_fields, 'place_of_service_' . $i);
                        array_push($services_fields, 'diagnosis_pointer_' . $i);
                        array_push($services_fields, 'charges_' . $i);

                        array_push($display_services_fields, 'Start Date' . $i);
                        array_push($display_services_fields, 'End Date' . $i);
                        array_push($display_services_fields, 'Start Time' . $i);
                        array_push($display_services_fields, 'End Time' . $i);
                        array_push($display_services_fields, 'Minutes' . $i);
                        array_push($display_services_fields, 'Units' . $i);
                        array_push($display_services_fields, 'CPT Code' . $i);
                        array_push($display_services_fields, 'Anesthesia Crosswalk' . $i);
                        array_push($display_services_fields, 'Place of Service' . $i);
                        array_push($display_services_fields, 'Diagnostic Code Pointer' . $i);
                        array_push($display_services_fields, 'Charge' . $i);
                    }
                    $fields = array_merge_recursive($fields, $services_fields);
                    $display_fields = array_merge_recursive($display_fields, $display_services_fields);
                }
                if ($Extended_services[0] == 'on') {
                    $Extended_services_fields = array('encounter_notes');
                    $Extended_display_services_fields = array('Encounter Notes');
                    $fields = array_merge_recursive($fields, $Extended_services_fields);
                    $display_fields = array_merge_recursive($display_fields, $Extended_display_services_fields);
                }
                ///‘Proposed Amount’,Proposal Date’, ‘Agreed Amount’, ‘Agreement Date’, ‘Primary Insurance’, ‘Secondary Insurance’, ‘Tertiary Insurance’, ‘Other insurance’
                /*                 * ******add date_secondary_insurance_billed time  By <Yu Lang>***** */
                if ($Extended_claimdetails[0] == 'on' || $claimdetails[0] == 'on') {

                    $claimdetails_fields = array('balance_due', 'expected_payment', 'date_creation', 'date_billed', 'date_last_billed', 'date_secondary_insurance_billed', 'date_closed', 'amount_initial_offer', 'date_initial_offer', 'negotiated_payment_amount', 'date_negotiated_amount_reached');
                    $display_claimdetails_fields = array('Balance Due', 'Payment Expected', 'Bill Created Date', 'Billed Date', 'Last Billed Date', 'Date Secondary Insurance Billed', 'Bill Closed Date', 'Proposed Amount', 'Proposal Date', 'Agreed Amount', 'Agreement Date');
                    $fields = array_merge_recursive($fields, $claimdetails_fields);
                    $display_fields = array_merge_recursive($display_fields, $display_claimdetails_fields);
                }
                if ($Extended_claimdetails[0] == 'on') {

                    $Extended_claimdetails_fields = array('encounter_notes', 'claim_notes');
                    $Extended_display_claimdetails_fields = array('Encounter Notes', 'Claim Notes');
                    $fields = array_merge_recursive($fields, $Extended_claimdetails_fields);
                    $display_fields = array_merge_recursive($display_fields, $Extended_display_claimdetails_fields);
                }

                $length = sizeof($fields);

                $payments_fields = array('amount_payment_received', 'datetime_payment_received', 'notes_payment', 'internal_notes', 'from'
                );
                $display_payments_fields = array(
                    'Payment Received Amount', 'Payment Received Datetime', 'Payment Notes', 'Internal Notes', 'From'
                );

                if ($payments[0] == 'on') {
                    $fields = array_merge_recursive($fields, $payments_fields);
                    $display_fields = array_merge_recursive($display_fields, $display_payments_fields);
                }
                if ($Extended_claimdetails[0] == 'on') {
                    $log_fields = array('logs');
                    $log_display_fields = array('Logs');
                    $fields = array_merge_recursive($fields, $log_fields);
                    $display_fields = array_merge_recursive($display_fields, $log_display_fields);
                }
                $index = 0;

                foreach ($patient as $row) {
                    /*                     * **********************change the date format************************ */
                    $claim_id = $row['claim_id'];
                    for ($i = 0; $i < $length; $i++) {
                        if (strpos($fields[$i], "date") || strpos($fields[$i], "DOB"))
                            $data[$index][$fields[$i]] = format($row[$fields[$i]], 1);
                        else
                            $data[$index][$fields[$i]] = $row[$fields[$i]];
                    }

                    $temp_index = $index;
                    /*                     * **********************change the date format************************ */

                    if ($payments[0] == 'on') {
                        //$claim_id = $row['claim_id'];

                        $db_payments = new Application_Model_DbTable_Payments;
                        $db = $db_payments->getAdapter();
                        $exclude = 'billeradjustment';
                        $where = $db->quoteInto('claim_id = ? ', $claim_id) . $db->quoteInto('and serviceid = ? ', 0);
                        $payments_my = $db_payments->fetchAll($where, "datetime DESC");
                        $payments_length = $payments_my->count();
                        $payments_flag = true;
                        if ($payments_length == 0) {
                            $payments_flag = false;
                        }
                        $tmp_data = $data[$index];
                        for ($i = 0; $i < $payments_length; $i++) {
                            $data[$index] = $tmp_data;
                            $data[$index]['amount_payment_received'] = $payments_my[$i]['amount'];
                            $data[$index]['datetime_payment_received'] = format($payments_my[$i]['datetime'], 6);
                            $data[$index]['notes_payment'] = $payments_my[$i]['notes'];
                            $data[$index]['internal_notes'] = $payments_my[$i]['internal_notes'];
                            $data[$index]['from'] = $payments_my[$i]['from'];
                            $index = $index + 1;
                        }

                        /*                         * ******About the patient payment and insurancepayment  By <Yu Lang>***** */
                    }
                    if ($Extended_claimdetails[0] == 'on') {
                        //$claim_id = $row['claim_id'];

                        $db_logs = new Application_Model_DbTable_Interactionlog();
                        $db = $db_logs->getAdapter();
                        $where = $db->quoteInto('claim_id = ? ', $claim_id);
                        $logs = $db_logs->fetchAll($where, "date_and_time DESC");
                        $logs_length = $logs->count();
                        $logs_data = "";
                        for ($i = 0; $i < $logs_length; $i++) {
                            if ($i > 0)
                                $logs_data = $logs_data . ' ||';
                            $logs_data = $logs_data . format($logs[$i]['date_and_time'], 1) . ' ' . $logs[$i]['log'];
                        }
                        $data[$temp_index]['logs'] = '"' . $logs_data . '"';
                    }

                    if (!$payments_flag) {
                        $index = $index + 1;
                    }
                }

                for ($i = 0; $i < count($data); $i++) {
                    //'date_initial_offer','negotiated_payment_amount','date_negotiated_amount_reached'
                    //date_creation', 'date_billed', 'date_last_billed', 'date_secondary_insurance_billed patient_DOB
                    //$data[$i]['start_date_1'] = format($data[$i]['start_date_1'], 1);
                    //$data[$i]['patient_DOB'] = format($data[$i]['patient_DOB'], 1);
                    $data[$i]['date_creation'] = format($data[$i]['date_creation'], 1);
                    $data[$i]['date_billed'] = format($data[$i]['date_billed'], 1);
                    $data[$i]['date_last_billed'] = format($data[$i]['date_last_billed'], 1);
                    $data[$i]['date_closed'] = format($data[$i]['date_closed'], 1);
                    $data[$i]['date_secondary_insurance_billed'] = format($data[$i]['date_secondary_insurance_billed'], 1);
                    $data[$i]['date_initial_offer'] = format($data[$i]['date_initial_offer'], 1);
                    $data[$i]['date_negotiated_amount_reached'] = format($data[$i]['date_negotiated_amount_reached'], 1);
                    //'patient_phone_number', 'patient_second_phone_number'facility_zipprovider_zip
                    $data[$i]['patient_SSN'] = ssn($data[$i]['patient_SSN'], 1);
                    $t = "\t{$data[$i]['patient_SSN']}";
                    $data[$i]['patient_SSN'] = '"' . $t . '"';
                    $data[$i]['patient_zip'] = zip($data[$i]['patient_zip'], 1);
                    $t = "\t{$data[$i]['patient_zip']}";
                    $data[$i]['patient_zip'] = '"' . $t . '"';
                    //$data[$i]['charges_2'] = zip($data[$i]['charges_2'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_1']}";
                    $data[$i]['diagnosis_pointer_1'] = '"' . $t . '"';
                    //$data[$i]['charges_1'] = zip($data[$i]['charges_1'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_2']}";
                    $data[$i]['diagnosis_pointer_2'] = '"' . $t . '"';
                    // $data[$i]['charges_3'] = zip($data[$i]['charges_3'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_3']}";
                    $data[$i]['diagnosis_pointer_3'] = '"' . $t . '"';
                    //$data[$i]['charges_4'] = zip($data[$i]['charges_4'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_4']}";
                    $data[$i]['diagnosis_pointer_4'] = '"' . $t . '"';
                    //$data[$i]['charges_5'] = zip($data[$i]['charges_5'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_5']}";
                    $data[$i]['diagnosis_pointer_5'] = '"' . $t . '"';
                    //$data[$i]['charges_6'] = zip($data[$i]['charges_6'], 1);
                    $t = "\t{$data[$i]['diagnosis_pointer_6']}";
                    $data[$i]['diagnosis_pointer_6'] = '"' . $t . '"';
                    //$data[$i]['total_charge'] = zip($data[$i]['total_charge'], 1);
//                     $t="\t{$data[$i]['total_charge']}";
//                     $data[$i]['total_charge']  = '"'.$t.'"';


                    $data[$i]['patient_phone_number'] = phone($data[$i]['patient_phone_number'], 1);
                    $t = "\t{$data[$i]['patient_phone_number']}";
                    $data[$i]['patient_phone_number'] = '"' . $t . '"';
                    $data[$i]['patient_second_phone_number'] = phone($data[$i]['patient_second_phone_number'], 1);
                    $t = "\t{$data[$i]['patient_second_phone_number']}";
                    $data[$i]['patient_second_phone_number'] = '"' . $t . '"';
                    $data[$i]['facility_zip'] = zip($data[$i]['facility_zip'], 1);
                    $t = "\t{$data[$i]['facility_zip']}";
                    $data[$i]['facility_zip'] = '"' . $t . '"';
                    $data[$i]['provider_zip'] = zip($data[$i]['provider_zip'], 1);
                    $t = "\t{$data[$i]['provider_zip']}";
                    $data[$i]['provider_zip'] = '"' . $t . '"';
                    for ($j = 1; $j < 7; $j++) {
//                    {       array_push($services_fields, 'start_date_' . $i);
//                            array_push($services_fields, 'end_date_' . $i);
//                            array_push($services_fields, 'start_time_' . $i);
//                            array_push($services_fields, 'end_time_' . $i);
//                            array_push($services_fields, 'minutes_' . $i);
                        $E = $data[$i]['end_date_' . $j] . ' ' . $data[$i]['end_time_' . $j];
                        $S = $data[$i]['start_date_' . $j] . ' ' . $data[$i]['start_time_' . $j];
                        $M = strtotime($E) - strtotime($S);


                        $data[$i]['minutes_' . $j] = ($M / 60) . 'min';
                    }
                }
                $final_length = sizeof($fields);
                $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                if (!is_dir($dir_billingcompany)) {
                    mkdir($dir_billingcompany);
                }
                $dir_bc_reports = $dir_billingcompany . '/reports';
                if (!is_dir($dir_bc_reports)) {
                    mkdir($dir_bc_reports);
                }
                $filename = $dir_bc_reports . "/" . date('Ymdhi') . ".csv";
                $fp = fopen($filename, 'w');

                for ($i = 0; $i < $final_length; $i++) {
                    fwrite($fp, $display_fields[$i] . ",");
                }
                fwrite($fp, "\r\n");


                for ($i = 0; $i < $index; $i++) {
                    for ($j = 0; $j < $final_length; $j++) {
//                                $ttt = $data[$i][$fields[$j]];
//                                $t="\t{$data[$i][$fields[$j]]}";
//                              $ttt = '"'.$t.'"';
//                              
//                              fwrite($fp,$ttt. ",");
                        fwrite($fp, $data[$i][$fields[$j]] . ",");
                    }
                    fwrite($fp, "\r\n");
                }

                fclose($fp);


                if (file_exists($filename)) {
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename=' . basename($filename));
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($filename) . ' bytes');
                    //ob_clean();
                    //flush();
                    readfile($filename);
                    exit;
                }
                $this->_redirect('/biller/data/reports');
            }
        }
    }

    /**
     * claiminsuranceAction
     * a function returning the insurance list.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function claiminsuranceAction() {
        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('billingcompany_id=?', $this->billingcompany_id());
        $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
        $this->deletenew($insuranceList, 'insurance_name', 'Need New Insurance');
        $this->deletenew($insuranceList, 'insurance_name', 'Self Pay');
//        $insuranceList = $db_insurance->fetchAll(null, "insurance_name ASC");
        $this->view->insuranceList = $insuranceList;
    }
    
    /**
     * modifierAction
     * a function processing the modifier data.
     * @author James.
     * @version 09/03/2015
     */
    public function modifierAction(){
        //get billingcompany id
        $billingcompany_id = $this->billingcompany_id();
        
        //get all modifier data corresponding to this billingcompany from db
        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?',$billingcompany_id);
        $modifiers = $db_modifier->fetchAll($where)->toArray();
        
        //handling data and send data to UI
        //show the modifier, the description, and the unit in one item
        $modifiers_data = array();
        $mark = 0;
        foreach ($modifiers as $rows) {
            $modifiers_data[$mark]['data'] = $rows['modifier'] . '-' . $rows['description'] . '-' . $rows['unit'];
            $modifiers_data[$mark]['id'] = $rows['id'];
            $mark ++;
        }
        $this->view->modifierList = $modifiers_data;

        //handling Update and New
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            $modifierexisting = $this->getRequest()->getParam('modifierexisting');
            if ($modifierexisting == "existing"){
                $this->_redirect('/biller/data/modifier');
            }
            if ($submitType == "Update") {
                $modifier = array();
                $modifier_id = $this->getRequest()->getPost('modifier_id');
                $modifier['modifier'] = $this->getRequest()->getPost('modifier');
                $modifier['description'] = $this->getRequest()->getPost('description');
                $modifier['unit'] = $this->getRequest()->getPost('unit');
                $modifier['status'] = $this->getRequest()->getPost('status');
                
                $db_modifier = new Application_Model_DbTable_Modifier();
                $db = $db_modifier->getAdapter();
                $where = $db->quoteInto('id = ?', $modifier_id);
                $oldmodifier = $db_modifier->fetchAll($where)->toArray();
                if ($db_modifier->update($modifier, $where)) {
                    // TODO: add log
                }
                $this->_redirect('/biller/data/modifier');
            }
            if ($submitType == "New") {
                $this->_redirect('/biller/data/newmodifier');
            }
        }
    }
    
    /**
     * modifierinfoAction
     * a function that can get modifier data.
     * @author James.
     * @version 09/08/2015
     */
    public function modifierinfoAction(){
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['modifier_id'];
        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $modifier_data = $db_modifier->fetchRow($where)->toArray();
        $data = array();
        $data = array('modifier' => $modifier_data['modifier'], 
            'description' => $modifier_data['description'],
            'unit' => $modifier_data['unit'],'status'=>$modifier_data['status']
        );
        $json = Zend_Json::encode($data);
        echo $json;        
    }
    
     /**
     * newmodifierAction
     * new modifier.
     * @author James.
     * @version 09/08/2015
     */
    public function newmodifierAction() {
        if ($this->getRequest()->isPost()) {
            $modifierexisting = $this->getRequest()->getParam('modifierexisting');
            if ($modifierexisting == "existing") {
                $this->_redirect('/biller/data/modifier');
            } else {
                $modifier['billingcompany_id'] = $this->billingcompany_id();
                $modifier['modifier'] = $this->getRequest()->getPost('modifier');
                $modifier['description'] = $this->getRequest()->getPost('description');
                $modifier['unit'] = $this->getRequest()->getPost('unit');
                //$modifier['status'] = $this->getRequest()->getPost('status');
                $modifier['status'] = "active";

                if (($modifier['modifier'] != "") && ($modifier['modifier'] != null) && ($modifier['description'] != "") && ($modifier['description'] != null) && ($modifier['unit'] != "") && ($modifier['unit'] != null)) {
                    $db_modifier = new Application_Model_DbTable_Modifier();
                    $db = $db_modifier->getAdapter();
                    if ($db_modifier->insert($modifier)) {
////                  function adddatalog($provider_id, $table_name, $DBfield, $oldvalue, $newvalue) {
//                    $this-adddatalog(-1, )
////                  $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.zip', NULL, $insurance['zip']);
                    }
                }
                $this->_redirect('/biller/data/modifier');
            }
        }
    }

    /**
     * modifierexistingAction
     * test if modifier inputed by user in front end is existing.
     * @author James.
     * @version 09/11/2015
     */
    public function modifierexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        $billingcompany_id = $this->billingcompany_id();
        $modifier_name = $_POST['modifier'];
        $modifier_id = $_POST['modifier_id'];
        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $billingcompany_id);
        $modifier_data = $db_modifier->fetchAll($where)->toArray();
        $soilder = "";
        for($mark = 0; $mark < count($modifier_data); $mark ++){
            $test1 = $modifier_data[$mark]['id'];
            $test2 = $modifier_data[$mark]['modifier'];
            if(($modifier_data[$mark]['modifier'] != "") && ($modifier_data[$mark]['modifier'] != null) 
                    && ($modifier_data[$mark]['modifier'] == $modifier_name) && ($modifier_id != $modifier_data[$mark]['id']) && (!($modifier_id < 0))){
                $soilder = "existing";
                break;
            }
        }
        $data = array();
        $data = array('ifexisting' => $soilder,);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * insuranceAction
     * a function processing the insurance company data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function insuranceAction() {
        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('billingcompany_id=?', $this->billingcompany_id()) . ' AND ' . $db->quoteInto('insurance_name not in (\'Self Pay\',\'Need New Insurance\')');
        $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
        //$this->deletenew($insuranceList,'insurance_name','Need New Insurance') ;
        //$this->deletenew($insuranceList,'insurance_name','Self Pay');
        $this->view->insuranceList = $insuranceList;
        $billingcompany_id = $this->billingcompany_id();

        //added to support #29: Data Management tool enhancement for tags/james:start
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where);
        $insurance_tags = $billingcompany_data['insurancetags'];
        $insurance_tags_List = explode('|', $insurance_tags);
        $mark = 0;
        foreach ($insurance_tags_List as $row) {
            $tp_tags = explode('=', $row);
            $tag_names[$mark] = $tags[$mark]['tag_name'] = $tp_tags[0];
            $tags[$mark]['tag_type'] = $tp_tags[1];
            $mark ++;
        }
        session_start();
        $_SESSION['tags_in_billingcompany']['tags'] = $tags;
        $_SESSION['tags_in_billingcompany']['tag_names'] = $tag_names;
        $_SESSION['tags_in_billingcompany']['tag_count'] = $mark;
        //added to support #29: Data Management tool enhancement for tags/james:end

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {

                $insurance = array();
                $id = $this->getRequest()->getPost('insurance_id');
                $myinsurance_display = $this->getRequest()->getPost('insurance_display');
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db = $db_insurance->getAdapter();

                $where = $db->quoteInto('insurance_display = ?', $myinsurance_display);

                $whereid = $db->quoteInto('id = ?', $id);
                $insurance_data_id = $db_insurance->fetchRow($whereid);
                $insurance_data = $db_insurance->fetchRow($where);
                $db_contractrates = new Application_Model_DbTable_Contractrates();

                $db1 = $db_contractrates->getAdapter();
                $wherecontractrates = $db1->quoteInto("insurance_id=?", $id);

                $oldcontract = $db_contractrates->fetchAll($wherecontractrates)->toArray();

                if ($insurance_data && $insurance_data_id['insurance_display'] != $myinsurance_display) {

                    echo '<span style="color:red;font-size:16px">Sorry, the name you entered exists already, please enter a different name</span>';

                    //$this->_redirect('/biller/data/insurance');
                    //each ($this->getRequest()->getPost('insurance_display'))."is exit in the db,play chage the complay display name";
                } else {
                    $insurance['insurance_name'] = $this->getRequest()->getPost('insurance_name');
                    $insurance['insurance_display'] = $this->getRequest()->getPost('insurance_display');
                    $faxTest = $this->getRequest()->getPost('fax_number');
                    $phoneTest = $this->getRequest()->getPost('insurance_phone_number');
                    $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
                    $newfax = preg_replace($pattern, "\\1\\2\\3", $faxTest);
                    $newphone = preg_replace($pattern, "\\1\\2\\3", $phoneTest);
                    $insurance['fax_number'] = $newfax;
                    $insurance['phone_number'] = $newphone;
                    $insurance['street_address'] = $this->getRequest()->getPost('insurance_street_address');
                    $insurance['zip'] = $this->getRequest()->getPost('insurance_zip');
                    $insurance['state'] = $this->getRequest()->getPost('insurance_state');
                    $insurance['city'] = $this->getRequest()->getPost('insurance_city');
                    //                $insurance['tags'] = $this->getRequest()->getPost('tags');
                    //added to support #29: Data Management tool enhancement for tags/james:start
                    session_start();
                    $tp_tags = $_SESSION['tags_in_billingcompany']['tags'];
                    $tags_from_page = "";
                    $loop_mark = 0;
                    $tags_size = count($tp_tags);
                    foreach ($tp_tags as $row) {
                        $loop_mark ++;
                        $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                        if ($row[tag_type] == "binary") {
                            if ($tp_value_from_page == "yes") {
                                if ($loop_mark != $tags_size) {
                                    $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                                } else {
                                    $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                                }
                            }
                        } else if ($row[tag_type] == "other") {
                            if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                                if ($loop_mark != $tags_size) {
                                    $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                                } else {
                                    $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                                }
                            }
                        }
                    }
                    if (substr($tags_from_page, -1) == "|") {
                        $tags_from_page = substr($tags_from_page, 0, -1);
                    }
                    //added to support #29: Data Management tool enhancement for tags/james:end
                    $insurance['tags'] = $tags_from_page;
                    $insurance['payer_type'] = $this->getRequest()->getPost('payer_type');
                    $insurance['anesthesia_bill_rate'] = $this->getRequest()->getPost('anesthesia_bill_rate');
                    $insurance['EDI_number'] = $this->getRequest()->getPost('EDI_number');
                    $insurance['claim_submission_preference'] = $this->getRequest()->getPost('claim_submission_preference');
                    $insurance['notes'] = $this->getRequest()->getPost('insurance_notes');

                    $insurance['PID_interpretation'] = $this->getRequest()->getPost('PID_interpretation');
                    $insurance['navinet_web_support_number'] = $this->getRequest()->getPost('navinet_web_support_number');
                    $insurance['appeal'] = $this->getRequest()->getPost('appeal');
                    $insurance['reconsideration'] = $this->getRequest()->getPost('reconsideration');
                    $insurance['claim_filing_deadline'] = $this->getRequest()->getPost('claim_filing_deadline');
                    $insurance['EFT'] = $this->getRequest()->getPost('EFT');
                    $insurance['claim_status_lookup'] = $this->getRequest()->getPost('claim_status_lookup');
                    $insurance['benefit_lookup'] = $this->getRequest()->getPost('benefit_lookup');
                    $insurance['anesthesia_bill_rate'] = $this->getRequest()->getPost('anesthesia_bill_rate');
                    $insurance['anesthesia_crosswalk_overwrite'] = $this->getRequest()->getPost('anesthesia_crosswalk_overwrite');
                    $insurance['insurance_type'] = $this->getRequest()->getPost('insurance_type');
                    //添加status属性
                    $insurance['status'] = $this->getRequest()->getPost('status');
                    if ($insurance['payer_type'] == "-1") {
                        $insurance['payer_type'] = "";
                    }
                    $payertype = $insurance['payer_type'];
                    if ($insurance['payer_type'] != NULL && !$oldcontract) {
                        for ($provide_num = 0; $provide_num < count($provider_data); $provide_num++) {
                            $provider_id = $provider_data[$provide_num]['id'];

                            $db_provider = new Application_Model_DbTable_Provider();
                            $db = $db_provider->getAdapter();
                            $quote = 'id=' . $provider_id;
                            $where = $db->quoteInto($quote);
                            $provider_datas = $db_provider->fetchRow($where);
                            $options_id = $provider_datas['options_id'];

                            $db_options = new Application_Model_DbTable_Options();
                            $db = $db_options->getAdapter();
                            $quote = 'id=' . $options_id;
                            $where = $db->quoteInto($quote);
                            $options_data = $db_options->fetchRow($where);
                            $default_rate_set = $options_data['default_pay_rate'];


                            //        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                            //        $db = $db_billingcompany->getAdapter();
                            //        $where = $db->quoteInto('id = ?', $this->billingcompany_id()); //
                            //        $billingcompany_data = $db_billingcompany->fetchRow($where);
                            //        $billingcompany_name = $billingcompany_data['billingcompany_name'];
                            //        $db_options = new Application_Model_DbTable_Options();
                            //        $db = $db_options->getAdapter();
                            //        $where = $db->quoteInto('option_name LIKE ?', '%-' . $billingcompany_name); //
                            //        $options_data = $db_options->fetchAll($where, 'option_name ASC');
                            //       $default_rate_set = $options_data[0]['default_pay_rate'];

                            $default_rates = explode('|', $default_rate_set);
                            $index = 0;
                            $default_rate_spilt = array();
                            for ($index = 0; $index < 5; $index++) {
                                $rate_temp = $default_rates[$index];
                                $temp = explode(':', $rate_temp);
                                $default_rate_spilt[$temp[0]] = $temp[1];
                            }
                            $default_rate = $default_rate_spilt[$payertype];
                            $contractrates = array();
                            $contractrates['provider_id'] = $provider_id;
                            $contractrates['insurance_id'] = $id;
                            $contractrates['rates'] = $default_rate;
                            //                    $db_insurance = new Application_Model_DbTable_Insurance();
                            //                    $dbinsurance = $db_insurance->getAdapter();
                            //                    $whereinsurance = $dbinsurance->quoteInto('id = ? ', $contractrates['insurance_id'] );
                            //                    $insuranceList = $db_insurance->fetchAll($whereinsurance)->toArray();

                            $db_contractrates = new Application_Model_DbTable_Contractrates();

                            $db1 = $db_contractrates->getAdapter();
                            //                    $wherecontractrates=$db1->quoteInto('provider_id = ?', $provider_id).$db->quoteInto("and insurance_id=?",$contractrates['insurance_id']);
                            //
    //                    $oldcontract= $db_contractrates->fetchAll($wherecontractrates)->toArray();
                            try {
                                if ($db_contractrates->insert($contractrates)) {
                                    $this->adddatalog($provider_id, $insurance['insurance_display'], 'contractrates.rates', NULL, $contractrates['rates']);
                                }
                            } catch (Exception $e) {
                                echo 'Message: ' . $e->getMessage();
                            }
                        }
                    }
                    $oldinsurance = $db_insurance->fetchAll($whereid)->toArray();
                    if ($db_insurance->update($insurance, $whereid)) {
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_name', $oldinsurance[0]['insurance_name'], $insurance['insurance_name']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_display', $oldinsurance[0]['insurance_display'], $insurance['insurance_display']);
                        //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['fax_number'],$insurance['fax_number']);
                        //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['phone_number'],$insurance['phone_number']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.street_address', $oldinsurance[0]['street_address'], $insurance['street_address']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.city', $oldinsurance[0]['city'], $insurance['city']);

                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.state', $oldinsurance[0]['state'], $insurance['state']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.zip', $oldinsurance[0]['zip'], $insurance['zip']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_number', $oldinsurance[0]['phone_number'], $insurance['phone_number']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_extension_for_claims', $oldinsurance[0]['phone_extension_for_claims'], $insurance['phone_extension_for_claims']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_extension_for_benefits', $oldinsurance[0]['phone_extension_for_benefits'], $insurance['phone_extension_for_benefits']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_status_lookup', $oldinsurance[0]['claim_status_lookup'], $insurance['claim_status_lookup']);

                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.second_phone_number', $oldinsurance[0]['second_phone_number'], $insurance['second_phone_number']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.fax_number', $oldinsurance[0]['fax_number'], $insurance['fax_number']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.EDI_number', $oldinsurance[0]['EDI_number'], $insurance['EDI_number']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_submission_preference', $oldinsurance[0]['claim_submission_preference'], $insurance['claim_submission_preference']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.payer_type', $oldinsurance['payer_type'], $insurance['payer_type']);

                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_type', $oldinsurance[0]['insurance_type'], $insurance['insurance_type']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.notes', $oldinsurance[0]['notes'], $insurance['notes']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.anesthesia_bill_rate', $oldinsurance[0]['anesthesia_bill_rate'], $insurance['anesthesia_bill_rate']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.anesthesia_crosswalk_overwrite', $oldinsurance[0]['anesthesia_crosswalk_overwrite'], $insurance['anesthesia_crosswalk_overwrite']);
                        // $this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['claim_filing_deadline'],$insurance['claim_filing_deadline']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.EFT', $oldinsurance[0]['EFT'], $insurance['EFT']);

                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.reconsideration', $oldinsurance[0]['reconsideration'], $insurance['reconsideration']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_filing_deadline', $oldinsurance[0]['claim_filing_deadline'], $insurance['claim_filing_deadline']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.appeal', $oldinsurance[0]['appeal'], $insurance['appeal']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.benefit_lookup', $oldinsurance[0]['benefit_lookup'], $insurance['benefit_lookup']);
                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.navinet_web_support_number', $oldinsurance[0]['navinet_web_support_number'], $insurance['navinet_web_support_number']);

                        $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.PID_interpretation', $oldinsurance[0]['PID_interpretation'], $insurance['PID_interpretation']);
                        //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['number_of_days_without_activities_i'],$insurance['number_of_days_without_activities_i']);
                        //  $this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['number_of_days_no_payment_issued_i'],$insurance['number_of_days_no_payment_issued_i']);
                        //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['alert'],$insurance['alert']);
                        // $this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['billingcompany_id'],$insurance['billingcompany_id']);
                    }

                    $this->_redirect('/biller/data/insurance');
                }
            }
            if ($submitType == "Delete") {
                $id = $this->getRequest()->getPost('insurance_id');
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db = $db_insurance->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldinsurance = $db_insurance->fetchAll($where)->toArray();
                if ($db_insurance->delete($where)) {
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_name', $oldinsurance[0]['insurance_name'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_display', $oldinsurance[0]['insurance_display'], NULL);
                    //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['fax_number'],$insurance['fax_number']);
                    //$this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['phone_number'],$insurance['phone_number']);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.street_address', $oldinsurance[0]['street_address'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.city', $oldinsurance[0]['city'], NULL);

                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.state', $oldinsurance[0]['state'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.zip', $oldinsurance[0]['zip'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_number', $oldinsurance[0]['phone_number'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_extension_for_claims', $oldinsurance[0]['phone_extension_for_claims'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.phone_extension_for_benefits', $oldinsurance[0]['phone_extension_for_benefits'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_status_lookup', $oldinsurance[0]['claim_status_lookup'], NULL);

                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.second_phone_number', $oldinsurance[0]['second_phone_number'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.fax_number', $oldinsurance[0]['fax_number'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.EDI_number', $oldinsurance[0]['EDI_number'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_submission_preference', $oldinsurance[0]['claim_submission_preference'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.payer_type', $oldinsurance[0]['payer_type'], NULL);

                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.insurance_type', $oldinsurance[0]['insurance_type'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.notes', $oldinsurance[0]['notes'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.anesthesia_bill_rate', $oldinsurance[0]['anesthesia_bill_rate'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.anesthesia_crosswalk_overwrite', $oldinsurance[0]['anesthesia_crosswalk_overwrite'], NULL);
                    // $this->adddatalog(0,$oldinsurance[0]['insurance_display'],'insurance.insurance_display',$oldinsurance['claim_filing_deadline'],$insurance['claim_filing_deadline']);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.EFT', $oldinsurance[0]['EFT'], NULL);

                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.reconsideration', $oldinsurance[0]['reconsideration'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.claim_filing_deadline', $oldinsurance[0]['claim_filing_deadline'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.appeal', $oldinsurance[0]['appeal'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.benefit_lookup', $oldinsurance[0]['benefit_lookup'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.navinet_web_support_number', $oldinsurance[0]['navinet_web_support_number'], NULL);

                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.PID_interpretation', $oldinsurance[0]['PID_interpretation'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.number_of_days_without_activities_i', $oldinsurance[0]['number_of_days_without_activities_i'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.number_of_days_no_payment_issued_i', $oldinsurance[0]['number_of_days_no_payment_issued_i'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.alert', $oldinsurance[0]['alert'], NULL);
                    $this->adddatalog(-1, $oldinsurance[0]['insurance_display'], 'insurance.billingcompany_id', $oldinsurance[0]['billingcompany_id'], NULL);
                }
                $this->_redirect('/biller/data/insurance');
            }
            if ($submitType == "New") {
                $this->_redirect('/biller/data/newinsurance');
            }
            if ($submitType == "UPLOAD") {
                $insurance_id = $this->getRequest()->getPost('insurance_id');
                $desc = $this->getRequest()->getParam('desc');
                if ($desc == "" || $desc == null) {
                    $this->_redirect('/biller/data/insurance');
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                    if (!is_dir($dir_billingcompany)) {
                        mkdir($dir_billingcompany);
                    }
                    $dir_bc_insurance = $this->sysdoc_path . '/' . $this->billingcompany_id . '/insurance';
                    if (!is_dir($dir_bc_insurance)) {
                        mkdir($dir_bc_insurance);
                    }
                    $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/insurance/' . $insurance_id;
                    if (!is_dir($dir)) {
                        mkdir($dir);
                    }
                    $today = date("Y-m-d H:i:s");
                    $date = explode(' ', $today);
                    $time0 = explode('-', $date[0]);
                    $time1 = explode(':', $date[1]);
                    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name = $user->user_name;
                    $file_name = $time . '-' . $desc . '-' . $user_name;
                    $old_filename = $adapter->getFileName();
                    $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                    $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                    $adapter->setDestination($dir);
                    $db_insurance = new Application_Model_DbTable_Insurance();
                    $db = $db_insurance->getAdapter();
                    $where = $db->quoteInto("id=?", $insurance_id);
                    $insurance_data = $db_insurance->fetchRow($where);
                    $log_insurance_name = $insurance_data['insurance_display'];
                    $log_dbfield = $desc;
                    $log_newvalue = 'Document Uploaded';
                    if (!$adapter->receive()) {
                        $messages = $adapter->getMessages();
                        echo implode("n", $messages);
                    } else {
                        $this->adddatalog(-1, $log_insurance_name, $log_dbfield, null, $log_newvalue);
                        $this->_redirect('/biller/data/insurance');
                    }
                }
            }
        }
    }

    /**
     * newinsuranceAction
     * a function for test a new insuance company is ex.
     * @author CaiJun.
     * @version 05/15/2012
     */
    function insuranceexistingAction() {
        $this->_helper->viewRenderer->setNoRender();

        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $provider_ids = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $provider_ids[$i] = $provider_data[$i]['id'];
        }
        $insurance = array();
        $myinsurance_display = $this->getRequest()->getPost('insurance_display');
        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('insurance_display = ?', $myinsurance_display) . $db->quoteInto("and billingcompany_id = ?", $billingcompany_id);
        $insurance_data = $db_insurance->fetchRow($where)->toArray();
        $data = array();
        if (isset($insurance_data)) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newinsuranceAction
     * a function for creating a new insuance company.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newinsuranceAction() {
        //added to support #29: Data Management tool enhancement for tags/james
        session_start();
        $tags = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_counts = $_SESSION['tags_in_billingcompany']['tag_count'];
        $this->view->tagList = $tags;
        $this->view->tagNames = $tag_names;
        $this->view->tagNumber = $tag_counts;

        if ($this->getRequest()->isPost()) {
            $insurance = array();
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
            $p_data = $db_provider->fetchAll($where, "provider_name ASC");
            $provider_data = $p_data->toArray();
            $myinsurance_display = $this->getRequest()->getPost('insurance_display');
            $db_insurance = new Application_Model_DbTable_Insurance();
            $db = $db_insurance->getAdapter();
            $where = $db->quoteInto('insurance_display = ?', $myinsurance_display) . $db->quoteInto("and billingcompany_id = ?", $billingcompany_id);
            //$where = $db->quoteInto('insurance_display = ?', $myinsurance_display );
            $insurance_data = $db_insurance->fetchRow($where);
            if ($insurance_data) {
                echo '<span style="color:red;font-size:16px">Sorry, the name you entered exists already, please enter a different name</span>';

                // $this->_redirect('/biller/data/insurance');
                //each ($this->getRequest()->getPost('insurance_display'))."is exit in the db,play chage the complay display name";
            } else {
                $insurance['insurance_name'] = $this->getRequest()->getPost('insurance_name');
                $insurance['insurance_display'] = $this->getRequest()->getPost('insurance_display');
                $faxTest = $this->getRequest()->getPost('fax_number');
                $phoneTest = $this->getRequest()->getPost('insurance_phone_number');
                $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
                $newfax = preg_replace($pattern, "\\1\\2\\3", $faxTest);
                $newphone = preg_replace($pattern, "\\1\\2\\3", $phoneTest);
                $insurance['fax_number'] = $newfax;
                $insurance['phone_number'] = $newphone;
                $insurance['street_address'] = $this->getRequest()->getPost('insurance_street_address');
                $insurance['zip'] = $this->getRequest()->getPost('insurance_zip');
                $insurance['state'] = $this->getRequest()->getPost('insurance_state');
                $insurance['city'] = $this->getRequest()->getPost('insurance_city');
//                $insurance['tags'] = $this->getRequest()->getPost('tags');
                //added to support #29: Data Management tool enhancement for tags/james:start
                $tags_from_page = "";
                $loop_mark = 0;
                $tags_size = count($tags);
                foreach ($tags as $row) {
                    $loop_mark ++;
                    $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                    if ($row[tag_type] == "binary") {
                        if ($tp_value_from_page == "yes") {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                            }
                        }
                    } else if ($row[tag_type] == "other") {
                        if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                            }
                        }
                    }
                }
                if (substr($tags_from_page, -1) == "|") {
                    $tags_from_page = substr($tags_from_page, 0, -1);
                }
                //added to support #29: Data Management tool enhancement for tags/james:end
                $insurance['tags'] = $tags_from_page;
                $insurance['payer_type'] = $this->getRequest()->getPost('payer_type');
                $insurance['anesthesia_bill_rate'] = $this->getRequest()->getPost('anesthesia_bill_rate');
                $insurance['EDI_number'] = $this->getRequest()->getPost('EDI_number');
                $insurance['claim_submission_preference'] = $this->getRequest()->getPost('claim_submission_preference');
                $insurance['notes'] = $this->getRequest()->getPost('insurance_notes');
                $insurance['PID_interpretation'] = $this->getRequest()->getPost('PID_interpretation');
                $insurance['navinet_web_support_number'] = $this->getRequest()->getPost('navinet_web_support_number');
                $insurance['appeal'] = $this->getRequest()->getPost('appeal');
                $insurance['reconsideration'] = $this->getRequest()->getPost('reconsideration');
                $insurance['claim_filing_deadline'] = $this->getRequest()->getPost('claim_filing_deadline');
                $insurance['EFT'] = $this->getRequest()->getPost('EFT');
                $insurance['claim_status_lookup'] = $this->getRequest()->getPost('claim_status_lookup');
                $insurance['benefit_lookup'] = $this->getRequest()->getPost('benefit_lookup');
                $insurance['anesthesia_bill_rate'] = $this->getRequest()->getPost('anesthesia_bill_rate');
                $insurance['anesthesia_crosswalk_overwrite'] = $this->getRequest()->getPost('anesthesia_crosswalk_overwrite');
                //$insurance['fax_number'] = $this->getRequest()->getPost('fax_number');
                $insurance['insurance_type'] = $this->getRequest()->getPost('insurance_type');
                if ($insurance['payer_type'] == "-1") {
                    $insurance['payer_type'] = "";
                }
                $insurance['billingcompany_id'] = $this->billingcompany_id();
                //try

                $payertype = $insurance['payer_type'];
                $isinsert = $db_insurance->insert($insurance);
                if ($payertype != NULL) {
                    for ($provide_num = 0; $provide_num < count($provider_data); $provide_num++) {
                        $provider_id = $provider_data[$provide_num]['id'];

                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();
                        $quote = 'id=' . $provider_id;
                        $where = $db->quoteInto($quote);
                        $provider_datas = $db_provider->fetchRow($where);
                        $options_id = $provider_datas['options_id'];

                        $db_options = new Application_Model_DbTable_Options();
                        $db = $db_options->getAdapter();
                        $quote = 'id=' . $options_id;
                        $where = $db->quoteInto($quote);
                        $options_data = $db_options->fetchRow($where);
                        $default_rate_set = $options_data['default_pay_rate'];

                        $default_rates = explode('|', $default_rate_set);
                        $index = 0;
                        $default_rate_spilt = array();
                        for ($index = 0; $index < 5; $index++) {
                            $rate_temp = $default_rates[$index];
                            $temp = explode(':', $rate_temp);
                            $default_rate_spilt[$temp[0]] = $temp[1];
                        }
                        $default_rate = $default_rate_spilt[$payertype];
                        $contractrates = array();
                        $contractrates['provider_id'] = $provider_id;
                        $contractrates['insurance_id'] = $isinsert;
                        $contractrates['rates'] = $default_rate;

                        $db_contractrates = new Application_Model_DbTable_Contractrates();

                        $db1 = $db_contractrates->getAdapter();
                        //                    $wherecontractrates=$db1->quoteInto('provider_id = ?', $provider_id).$db->quoteInto("and insurance_id=?",$contractrates['insurance_id']);
                        //
    //                    $oldcontract= $db_contractrates->fetchAll($wherecontractrates)->toArray();
                        try {
                            if ($db_contractrates->insert($contractrates)) {
                                $this->adddatalog($provider_id, $insurance['insurance_display'], 'contractrates.rates', NULL, $contractrates['rates']);
                            }
                        } catch (Exception $e) {
                            echo 'Message: ' . $e->getMessage();
                        }
                    }
                }
                if ($isinsert) {
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.insurance_name', NULL, $insurance['insurance_name']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.insurance_display', NULL, $insurance['insurance_display']);
                    //$this->adddatalog(0,$insurance['insurance_display'],'insurance.insurance_display',$insurance['fax_number'],$insurance['fax_number']);
                    //$this->adddatalog(0,$insurance['insurance_display'],'insurance.insurance_display',$oldinsurance['phone_number'],$insurance['phone_number']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.street_address', NULL, $insurance['street_address']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.city', NULL, $insurance['city']);

                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.state', NULL, $insurance['state']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.zip', NULL, $insurance['zip']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.phone_number', NULL, $insurance['phone_number']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.phone_extension_for_claims', NULL, $insurance['phone_extension_for_claims']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.phone_extension_for_benefits', NULL, $insurance['phone_extension_for_benefits']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.claim_status_lookup', NULL, $insurance['claim_status_lookup']);

                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.second_phone_number', NULL, $insurance['second_phone_number']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.fax_number', NULL, $insurance['fax_number']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.EDI_number', NULL, $insurance['EDI_number']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.claim_submission_preference', NULL, $insurance['claim_submission_preference']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.payer_type', NULL, $insurance['payer_type']);

                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.insurance_type', NULL, $insurance['insurance_type']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.notes', NULL, $insurance['notes']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.anesthesia_bill_rate', NULL, $insurance['anesthesia_bill_rate']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.anesthesia_crosswalk_overwrite', NULL, $insurance['anesthesia_crosswalk_overwrite']);
                    // $this->adddatalog(0,$insurance['insurance_display'],'insurance.insurance_display',$insurance['claim_filing_deadline'],$insurance['claim_filing_deadline']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.EFT', NULL, $insurance['EFT']);

                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.reconsideration', NULL, $insurance['reconsideration']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.claim_filing_deadline', NULL, $insurance['claim_filing_deadline']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.appeal', NULL, $insurance['appeal']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.benefit_lookup', NULL, $insurance['benefit_lookup']);
                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.navinet_web_support_number', NULL, $insurance['navinet_web_support_number']);

                    $this->adddatalog(-1, $insurance['insurance_display'], 'insurance.PID_interpretation', NULL, $insurance['PID_interpretation']);
                }
                //}catch(Exception $e){
                //    echo "errormessage:"+ $e->getMessage();
                //}
                $this->_redirect('/biller/data/insurance');
            }
        }
    }

    /**
     * insuranceinfoAction
     * a function returning the insurance data for displaying on the page.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function insuranceinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $insurance_id = $_POST['insurance_id'];
        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('id = ?', $insurance_id);
        $insurance_data = $db_insurance->fetchRow($where);
        $phone_number = phone($insurance_data['phone_number']);
        $fax_number = phone($insurance_data['fax_number']);
        $exist_tags = $insurance_data['tags'];
        if($exist_tags !==null && $exist_tags !== '') {
            $exist_tags_List = explode('|', $exist_tags);
            $mark_exists = 0;
            foreach ($exist_tags_List as $row) {
                $tp_exist_tags = explode('=', $row);
                $exist_tag_names[$mark_exists] = $tags[$mark_exists]['tag_name'] = $tp_exist_tags[0];
                $tags[$mark_exists]['tag_type'] = $tp_exist_tags[1];
                $mark_exists ++;
            }
            $tag_count_exists = $mark_exists;
        } else {
            $tags = null;
        }
        

        session_start();
        $tags_in_billingcompany = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_count_in_billingcompany = $_SESSION['tags_in_billingcompany']['tag_count'];

        $insurance_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/insurance/' . $insurance_id;
        $doc_paths = array();
        if (is_dir($insurance_doc_path)) {
            foreach (glob($insurance_doc_path . '/*.*') as $filename) {
                array_push($doc_paths, $filename);
            }
        }
        $insurance_doc_list = array();
        $i = 0;
        foreach ($doc_paths as $path) {
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
            $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
            $insurance_doc_list[$i]['date'] = $date;
            $insurance_doc_list[$i]['desc'] = $temp[1];
            $insurance_doc_list[$i]['user'] = $temp[2];
            $count = count($temp);
            $n = 3;
            if ($count > 3) {
                for ($n; $n < $count; $n++) {
                    $insurance_doc_list[$i]['user'] = $insurance_doc_list[$i]['user'] . '-' . $temp[$n];
                }
            }
            $insurance_doc_list[$i]['url'] = $path;
            $i++;
        }

        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;

        $data = array();
        $data = array('insurance_id' => $insurance_data['id'], 'insurance_name' => $insurance_data['insurance_name'], 'insurance_zip' => $$insurance_data['zip'], 'fax_number' => $insurance_data['fax_number'], 'payer_type' => $insurance_data['payer_type'],
            'insurance_street_address' => $insurance_data['street_address'], 'insurance_state' => $insurance_data['state'], 'insurance_city' => $insurance_data['city'], 'insurance_zip' => $insurance_data['zip'], 'anesthesia_bill_rate' => $insurance_data['anesthesia_bill_rate'],
            'insurance_phone_number' => $phone_number, 'EDI_number' => $insurance_data['EDI_number'], 'claim_submission_preference' => $insurance_data['claim_submission_preference'], 'PID_interpretation' => $insurance_data['PID_interpretation'], 'navinet_web_support_number' => $insurance_data['navinet_web_support_number'],
            'appeal' => $insurance_data['appeal'], 'reconsideration' => $insurance_data['reconsideration'], 'claim_filing_deadline' => $insurance_data['claim_filing_deadline'], 'EFT' => $insurance_data['EFT'], 'claim_status_lookup' => $insurance_data['claim_status_lookup'], 'benefit_lookup' => $insurance_data['benefit_lookup'], 'anesthesia_crosswalk_overwrite' => $insurance_data['anesthesia_crosswalk_overwrite'],
            'fax_number' => $fax_number, 'insurance_notes' => $insurance_data['notes'], 'insurance_type' => $insurance_data['insurance_type'], 'insurance_doc_list' => $insurance_doc_list, 'user_name' => $user_name, 'tags' => $tags,
            'tags_in_billingcompany' => $tags_in_billingcompany, 'tag_names' => $tag_names, 'tag_number' => $tag_count_in_billingcompany, 'exist_tag_number' => $tag_count_exists,
            'exist_tag_names' => $exist_tag_names,'status'=>$insurance_data['status']);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * optionAction
     * a function for processing the option data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function optionAction() {

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id()); //
        $billingcompany_data = $db_billingcompany->fetchRow($where);

        $billingcompany_name = $billingcompany_data['billingcompany_name'];

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        //     $where = $db->quoteInto('option_name LIKE ?', '%-' . $billingcompany_name); //
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $options_data = $db_options->fetchAll($where, 'option_name ASC');
        $this->view->optionsList = $options_data;

        $relationshipList = array();
        $relationshipList[0]['relationship'] = 'Child';
        $relationshipList[1]['relationship'] = 'Other';
        $relationshipList[2]['relationship'] = 'Self';
        $relationshipList[3]['relationship'] = 'Spouse';
        $this->view->relationshipList = $relationshipList;

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $where = $db->quoteInto('billingcompany_id=?', $this->billingcompany_id());
-       $modifier_data = $db_modifier->fetchAll($where, 'modifier ASC');
        $this->view->modifierList = $modifier_data;

        $db_placeofservice = new Application_Model_DbTable_Placeofservice();
        $db = $db_placeofservice->getAdapter();
        $placeofservice_data = $db_placeofservice->fetchAll(null, 'pos ASC');
        $this->view->placeofserviceList = $placeofservice_data;

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $providerList = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $providerList;

        //added to support #29: Data Management tool enhancement for tags/james:start
        $billingcompany_id = $this->billingcompany_id();
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where);
        $options_tags = $billingcompany_data['optionstags'];
        $options_tags_List = explode('|', $options_tags);
        $mark = 0;
        foreach ($options_tags_List as $row) {
            $tp_tags = explode('=', $row);
            $tag_names[$mark] = $tags[$mark]['tag_name'] = $tp_tags[0];
            $tags[$mark]['tag_type'] = $tp_tags[1];
            $mark ++;
        }
        session_start();
        $_SESSION['tags_in_billingcompany']['tags'] = $tags;
        $_SESSION['tags_in_billingcompany']['tag_names'] = $tag_names;
        $_SESSION['tags_in_billingcompany']['tag_count'] = $mark;
        //added to support #29: Data Management tool enhancement for tags/james:end


        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $options = array();
                $options_id = $this->getRequest()->getPost('option_id');
                $options['anesthesia_unit_rounding'] = $this->getRequest()->getPost('anesthesia_unit_rounding');

                $options['anesthesia_billing_rate_for_non_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_non_par');
                $options['patient_statement_interval'] = $this->getRequest()->getPost('patient_statement_interval');
                $key = $this->getRequest()->getPost('default_end_date_to_start_date');
                if ($key[0] != null)
                    $options['default_end_date_to_start_date'] = '1';
                else
                    $options['default_end_date_to_start_date'] = '0';
                $options['default_patient_relationship_to_insured'] = $this->getRequest()->getPost('default_patient_relationship_to_insured');
                $options['number_of_days_for_delayed_bill_generation1'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation1');
                $options['number_of_days_for_delayed_bill_generation2'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation2');
                $options['number_of_days_for_delayed_bill_generation3'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation3');

                $options['number_of_days_without_activities'] = $this->getRequest()->getPost('number_of_days_without_activities');
                $key = $this->getRequest()->getPost('yes_for_assingment_of_benefits');
                if ($key[0] != null)
                    $options['yes_for_assingment_of_benefits'] = '1';
                else
                    $options['yes_for_assingment_of_benefits'] = '0';
                $options['number_of_days_AR_outstanding'] = $this->getRequest()->getPost('number_of_days_AR_outstanding');
                $options['provider_invoice_rate'] = $this->getRequest()->getPost('provider_invoice_rate');
                $key = $this->getRequest()->getPost('use_DOS_for_all_dates');
                if ($key[0] != null)
                    $options['use_DOS_for_all_dates'] = '1';
                else
                    $options['use_DOS_for_all_dates'] = '0';
                $key = $this->getRequest()->getPost('signature_on_file_for_all_signatures');
                if ($key[0] != null)
                    $options['signature_on_file_for_all_signatures'] = '1';
                else
                    $options['signature_on_file_for_all_signatures'] = '0';
                $key = $this->getRequest()->getPost('auto_populate_diagnosis_pointer');
                if ($key[0] != null)
                    $options['auto_populate_diagnosis_pointer'] = '1';
                else
                    $options['auto_populate_diagnosis_pointer'] = '0';
                $options['PIP_rate'] = $this->getRequest()->getPost('PIP_rate');
                $options['number_of_days_no_payment_after_agreed'] = $this->getRequest()->getPost('number_of_days_no_payment_after_agreed');
                $options['number_of_days_for_litigation_followup'] = $this->getRequest()->getPost('number_of_days_for_litigation_followup');
                $options['number_of_days_bill_has_not_been_generated'] = $this->getRequest()->getPost('number_of_days_bill_has_not_been_generated');
                $options['default_modifier'] = $this->getRequest()->getPost('default_modifier');
                $options['number_of_days_after_issued_but_not_received'] = $this->getRequest()->getPost('number_of_days_after_issued_but_not_received');
                $options['default_rendering_provider'] = $this->getRequest()->getPost('default_rendering_provider');
                $options['default_facility'] = $this->getRequest()->getPost('default_facility');
                $options['number_of_days_no_payment_issued'] = $this->getRequest()->getPost('number_of_days_no_payment_issued');
                $options['default_provider'] = $this->getRequest()->getPost('default_provider');
                $options['default_place_of_service'] = $this->getRequest()->getPost('default_place_of_service');
                $options['close_to_claim_filing_deadline'] = $this->getRequest()->getPost('close_to_claim_filing_deadline');
                $options['patient_statement_interval'] = $this->getRequest()->getPost('patient_statement_interval');
                $options['non_par_expected_pay'] = $this->getRequest()->getPost('non_par_expected_pa');
                $options['invoice_delivery_preference'] = $this->getRequest()->getPost('invoice_delivery_preferenc');
                $options['anesthesia_billing_rate_for_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_par');
                $options['in_network_contract_rates'] = $this->getRequest()->getPost('in_network_contract_rates');
                $options['reports_delivery_preference'] = $this->getRequest()->getPost('reports_delivery_preference');
                $options['number_of_days_offered_but_not_agreed'] = $this->getRequest()->getPost('number_of_days_offered_but_not_agreed');
                $options['number_of_days_no_payment_issued'] = $this->getRequest()->getPost('number_of_days_no_payment_issued');
                $options['anesthesia_billing_rate_for_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_par');
                $options['statement_I_1'] = $this->getRequest()->getPost('statement_I_1');
                $options['statement_I_2'] = $this->getRequest()->getPost('statement_I_2');
                $options['statement_I_3'] = $this->getRequest()->getPost('statement_I_3');
                $options['statement_I_4'] = $this->getRequest()->getPost('statement_I_4');
                $options['statement_II_1'] = $this->getRequest()->getPost('statement_II_1');
                $options['statement_II_2'] = $this->getRequest()->getPost('statement_II_2');
                $options['statement_II_3'] = $this->getRequest()->getPost('statement_II_3');
                $options['statement_II_4'] = $this->getRequest()->getPost('statement_II_4');
                $options['statement_III_1'] = $this->getRequest()->getPost('statement_III_1');
                $options['statement_III_2'] = $this->getRequest()->getPost('statement_III_2');
                $options['statement_III_3'] = $this->getRequest()->getPost('statement_III_3');
                $options['statement_III_4'] = $this->getRequest()->getPost('statement_III_4');
                $options['statement_V_1'] = $this->getRequest()->getPost('statement_V_1');

                $options['number_of_days_include_in_ledger'] = $this->getRequest()->getPost('number_of_days_include_in_ledger');
                $options['SI_send_to_patient'] = $this->getRequest()->getPost('SI_send_to_patient');
                $options['SI_co-insurance'] = $this->getRequest()->getPost('SI_co_insurance');
                $options['SI_deductible'] = $this->getRequest()->getPost('SI_deductible');
                $options['SI_selfpay'] = $this->getRequest()->getPost('SI_selfpay');
                $options['installment_statement_date'] = $this->getRequest()->getPost('installment_statement_date');
                $options['custom_label_1'] = $this->getRequest()->getPost('custom_label_1');
                $options['custom_label_2'] = $this->getRequest()->getPost('custom_label_2');
                $options['custom_label_3'] = $this->getRequest()->getPost('custom_label_3');
                $options['custom_label_4'] = $this->getRequest()->getPost('custom_label_4');
                $options['default_pay_rate'] = $this->getRequest()->getPost('default_pay_rate');
                $options['min_gap'] = $this->getRequest()->getPost('min_gap');
                $options['autoposting'] = $this->getRequest()->getPost('autoposting');
                $options['billingcompany_id'] = $this->billingcompany_id();
                $options['toomanyminutes']=$this->getRequest()->getPost('toomanyminutes');
                //added to support #29: Data Management tool enhancement for tags/james:start
                session_start();
                $tp_tags = $_SESSION['tags_in_billingcompany']['tags'];
                $tags_from_page = "";
                $loop_mark = 0;
                $tags_size = count($tp_tags);
                foreach ($tp_tags as $row) {
                    $loop_mark ++;
                    $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                    if ($row[tag_type] == "binary") {
                        if ($tp_value_from_page == "yes") {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                            }
                        }
                    } else if ($row[tag_type] == "other") {
                        if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                            }
                        }
                    }
                }
                if (substr($tags_from_page, -1) == "|") {
                    $tags_from_page = substr($tags_from_page, 0, -1);
                }
                //added to support #29: Data Management tool enhancement for tags/james:end
                $options['tags'] = $tags_from_page;
                /*                 * added to support "Tags" field in front page by james */
//                $options['tags'] = $this->getRequest()->getPost('tags');
                //$options['']
                $db_options = new Application_Model_DbTable_Options();
                $db = $db_options->getAdapter();
                $where = $db->quoteInto('id = ?', $options_id);
                $oldoption = $db_options->fetchAll($where)->toArray();
                if ($db_options->update($options, $where)) {
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_unit_rounding', $oldoption[0]['anesthesia_unit_rounding'], $options['anesthesia_unit_rounding']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_billing_rate_for_non_par', $oldoption[0]['anesthesia_billing_rate_for_non_par'], $options['anesthesia_billing_rate_for_non_par']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.patient_statement_interval', $oldoption[0]['patient_statement_interval'], $options['patient_statement_interval']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_end_date_to_start_date', $oldoption[0]['default_end_date_to_start_date'], $options['default_end_date_to_start_date']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_patient_relationship_to_insured', $oldoption[0]['default_patient_relationship_to_insured'], $options['default_patient_relationship_to_insured']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_delayed_bill_generation1', $oldoption[0]['number_of_days_for_delayed_bill_generation1'], $options['number_of_days_for_delayed_bill_generation1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_delayed_bill_generation2', $oldoption[0]['number_of_days_for_delayed_bill_generation2'], $options['number_of_days_for_delayed_bill_generation2']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_delayed_bill_generation3', $oldoption[0]['number_of_days_for_delayed_bill_generation3'], $options['number_of_days_for_delayed_bill_generation3']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_without_activities', $oldoption[0]['number_of_days_without_activities'], $options['number_of_days_without_activities']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.yes_for_assingment_of_benefits', $oldoption[0]['yes_for_assingment_of_benefits'], $options['yes_for_assingment_of_benefits']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_AR_outstanding', $oldoption[0]['number_of_days_AR_outstanding'], $options['number_of_days_AR_outstanding']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.provider_invoice_rate', $oldoption[0]['provider_invoice_rate'], $options['provider_invoice_rate']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.use_DOS_for_all_dates', $oldoption[0]['use_DOS_for_all_dates'], $options['use_DOS_for_all_dates']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.signature_on_file_for_all_signatures', $oldoption[0]['signature_on_file_for_all_signatures'], $options['signature_on_file_for_all_signatures']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.auto_populate_diagnosis_pointer', $oldoption[0]['auto_populate_diagnosis_pointer'], $options['auto_populate_diagnosis_pointer']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.PIP_rate', $oldoption[0]['PIP_rate'], $options['PIP_rate']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_no_payment_after_agreed', $oldoption[0]['number_of_days_no_payment_after_agreed'], $options['number_of_days_no_payment_after_agreed']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_litigation_followup', $oldoption[0]['number_of_days_for_litigation_followup'], $options['number_of_days_for_litigation_followup']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_bill_has_not_been_generated', $oldoption[0]['number_of_days_bill_has_not_been_generated'], $options['number_of_days_bill_has_not_been_generated']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_modifier', $oldoption[0]['default_modifier'], $options['default_modifier']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_after_issued_but_not_received', $oldoption[0]['number_of_days_after_issued_but_not_received'], $options['number_of_days_after_issued_but_not_received']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_rendering_provider', $oldoption[0]['default_rendering_provider'], $options['default_rendering_provider']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_facility', $oldoption[0]['default_facility'], $options['default_facility']);
                    // $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.number_of_days_no_payment_issued',$oldoption[0]['number_of_days_no_payment_issued'],$options['number_of_days_no_payment_issued']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_provider', $oldoption[0]['default_provider'], $options['default_provider']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_place_of_service', $oldoption[0]['default_place_of_service'], $options['default_place_of_service']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.close_to_claim_filing_deadline', $oldoption[0]['close_to_claim_filing_deadline'], $options['close_to_claim_filing_deadline']);
                    // $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.patient_statement_interval',$oldoption[0]['patient_statement_interval'],$options['patient_statement_interval']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.non_par_expected_pay', $oldoption[0]['non_par_expected_pay'], $options['non_par_expected_pay']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.invoice_delivery_preference', $oldoption[0]['invoice_delivery_preference'], $options['invoice_delivery_preference']);
                    //$this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.anesthesia_billing_rate_for_par',$oldoption[0]['anesthesia_billing_rate_for_par'],$options['anesthesia_billing_rate_for_par']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.in_network_contract_rates', $oldoption[0]['in_network_contract_rates'], $options['in_network_contract_rates']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.reports_delivery_preference', $oldoption[0]['reports_delivery_preference'], $options['reports_delivery_preference']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_offered_but_not_agreed', $oldoption[0]['number_of_days_offered_but_not_agreed'], $options['number_of_days_offered_but_not_agreed']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_no_payment_issued', $oldoption[0]['number_of_days_no_payment_issued'], $options['number_of_days_no_payment_issued']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_billing_rate_for_par', $oldoption[0]['anesthesia_billing_rate_for_par'], $options['anesthesia_billing_rate_for_par']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_1', $oldoption[0]['statement_I_1'], $options['statement_I_1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_2', $oldoption[0]['statement_I_2'], $options['statement_I_2']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_3', $oldoption[0]['statement_I_3'], $options['statement_I_3']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_4', $oldoption[0]['statement_I_4'], $options['statement_I_4']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_1', $oldoption[0]['statement_II_1'], $options['statement_II_1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_2', $oldoption[0]['statement_II_2'], $options['statement_II_2']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_3', $oldoption[0]['statement_II_3'], $options['statement_II_3']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_4', $oldoption[0]['statement_II_4'], $options['statement_II_4']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_1', $oldoption[0]['statement_III_1'], $options['statement_III_1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_2', $oldoption[0]['statement_III_2'], $options['statement_III_2']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_3', $oldoption[0]['statement_III_3'], $options['statement_III_3']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_4', $oldoption[0]['statement_III_4'], $options['statement_III_4']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_V_1', $oldoption[0]['statement_V_1'], $options['statement_V_1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_include_in_ledger', $oldoption[0]['number_of_days_include_in_ledger'], $options['number_of_days_include_in_ledger']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_send_to_patient', $oldoption[0]['SI_send_to_patient'], $options['SI_send_to_patient']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_co-insurance', $oldoption[0]['SI_co-insurance'], $options['SI_co-insurance']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_deductible', $oldoption[0]['SI_deductible'], $options['SI_deductible']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_selfpay', $oldoption[0]['SI_selfpay'], $options['SI_selfpay']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.installment_statement_date', $oldoption[0]['installment_statement_date'], $options['installment_statement_date']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_1', $oldoption[0]['custom_label_1'], $options['custom_label_1']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_2', $oldoption[0]['custom_label_2'], $options['custom_label_2']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_3', $oldoption[0]['custom_label_3'], $options['custom_label_3']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_4', $oldoption[0]['custom_label_4'], $options['custom_label_4']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_pay_rate', $oldoption[0]['default_pay_rate'], $options['default_pay_rate']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.min_gap', $oldoption[0]['min_gap'], $options['min_gap']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.autoposting', $oldoption[0]['autoposting'], $options['autoposting']);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.toomanyminutes', $oldoption[0]['toomanyminutes'], $options['toomanyminutes']);
                }

                /*                 * add to let the option page show the record that have been added just now. james#datatoolbehavior */
//                session_start();
//                $_SESSION['data_management']['option_id'] = $options_id;

                $this->_redirect('/biller/data/option');
            }
            if ($submitType == "Delete") {
                $options_id = $this->getRequest()->getPost('option_id');
                $db_options = new Application_Model_DbTable_Options();
                $db = $db_options->getAdapter();
                $where = $db->quoteInto('id = ?', $options_id);
                $oldoption = $db_options->fetchAll($where)->toArray();
                if ($db_options->delete($where)) {
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.option_name', $oldoption[0]['option_name'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_unit_rounding', $oldoption[0]['anesthesia_unit_rounding'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_billing_rate_for_non_par', $oldoption[0]['anesthesia_billing_rate_for_non_par'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.patient_statement_interval', $oldoption[0]['patient_statement_interval'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_end_date_to_start_date', $oldoption[0]['default_end_date_to_start_date'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_patient_relationship_to_insured', $oldoption[0]['default_patient_relationship_to_insured'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_delayed_bill_generation', $oldoption[0]['number_of_days_for_delayed_bill_generation'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_without_activities', $oldoption[0]['number_of_days_without_activities'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.yes_for_assingment_of_benefits', $oldoption[0]['yes_for_assingment_of_benefits'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_AR_outstanding', $oldoption[0]['number_of_days_AR_outstanding'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.provider_invoice_rate', $oldoption[0]['provider_invoice_rate'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.use_DOS_for_all_dates', $oldoption[0]['use_DOS_for_all_dates'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.signature_on_file_for_all_signatures', $oldoption[0]['signature_on_file_for_all_signatures'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.auto_populate_diagnosis_pointer', $oldoption[0]['auto_populate_diagnosis_pointer'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.PIP_rate', $oldoption[0]['PIP_rate'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_no_payment_after_agreed', $oldoption[0]['number_of_days_no_payment_after_agreed'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_for_litigation_followup', $oldoption[0]['number_of_days_for_litigation_followup'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_bill_has_not_been_generated', $oldoption[0]['number_of_days_bill_has_not_been_generated'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_modifier', $oldoption[0]['default_modifier'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_after_issued_but_not_received', $oldoption[0]['number_of_days_after_issued_but_not_received'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_rendering_provider', $oldoption[0]['default_rendering_provider'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_facility', $oldoption[0]['default_facility'], NULL);
                    // $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.number_of_days_no_payment_issued',$oldoption[0]['number_of_days_no_payment_issued'],NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_provider', $oldoption[0]['default_provider'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_place_of_service', $oldoption[0]['default_place_of_service'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.close_to_claim_filing_deadline', $oldoption[0]['close_to_claim_filing_deadline'], NULL);
                    //  $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.patient_statement_interval',$oldoption[0]['patient_statement_interval'],NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.non_par_expected_pay', $oldoption[0]['non_par_expected_pay'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.invoice_delivery_preference', $oldoption[0]['invoice_delivery_preference'], NULL);
                    //$this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.anesthesia_billing_rate_for_par',$oldoption[0]['anesthesia_billing_rate_for_par'],NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.in_network_contract_rates', $oldoption[0]['in_network_contract_rates'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.reports_delivery_preference', $oldoption[0]['reports_delivery_preference'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_offered_but_not_agreed', $oldoption[0]['number_of_days_offered_but_not_agreed'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_no_payment_issued', $oldoption[0]['number_of_days_no_payment_issued'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.anesthesia_billing_rate_for_par', $oldoption[0]['anesthesia_billing_rate_for_par'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_1', $oldoption[0]['statement_I_1'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_2', $oldoption[0]['statement_I_2'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_3', $oldoption[0]['statement_I_3'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_I_4', $oldoption[0]['statement_I_4'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_1', $oldoption[0]['statement_II_1'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_2', $oldoption[0]['statement_II_2'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_3', $oldoption[0]['statement_II_3'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_II_4', $oldoption[0]['statement_II_4'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_1', $oldoption[0]['statement_III_1'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_2', $oldoption[0]['statement_III_2'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_3', $oldoption[0]['statement_III_3'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_III_4', $oldoption[0]['statement_III_4'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.statement_V_1', $oldoption[0]['statement_V_1'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.number_of_days_include_in_ledger', $oldoption[0]['number_of_days_include_in_ledger'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_send_to_patient', $oldoption[0]['SI_send_to_patient'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_co-insurance', $oldoption[0]['SI_co-insurance'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_deductible', $oldoption[0]['SI_deductible'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.SI_selfpay', $oldoption[0]['SI_selfpay'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.installment_statement_date', $oldoption[0]['installment_statement_date'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_1', $oldoption[0]['custom_label_1'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_2', $oldoption[0]['custom_label_2'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_3', $oldoption[0]['custom_label_3'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.custom_label_4', $oldoption[0]['custom_label_4'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.default_pay_rate', $oldoption[0]['default_pay_rate'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.min_gap', $oldoption[0]['min_gap'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.autoposting', $oldoption[0]['autoposting'], NULL);
                    $this->adddatalog($provider_id, $oldoption[0]['option_name'], 'options.billingcompany_id', $oldoption[0]['billingcompany_id'], NULL);
//                     $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.billingcompany_id',$oldoption[0]['billingcompany_id'],NULL);
//                     $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.billingcompany_id',$oldoption[0]['billingcompany_id'],NULL);
//                     $this->adddatalog($provider_id,$oldoption[0]['option_name'],'options.billingcompany_id',$oldoption[0]['billingcompany_id'],NULL);
                }
                $this->_redirect('/biller/data/option');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newoption');
        }
    }

    function optionexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $options['option_name'] = $this->getRequest()->getPost('option_name');
        $options['billingcompany_id'] = $this->billingcompany_id();
        $where = $db->quoteInto("billingcompany_id = ?", $options['billingcompany_id']) . $db->quoteInto("and option_name = ? ", $options['option_name']);
        $dataexisting = $db_options->fetchAll($where)->toArray();

        $data = array();
        if (isset($dataexisting[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * paitentAction
     * a function for creating a new option.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newoptionAction() {

        $relationshipList = array();
        $relationshipList[0]['relationship'] = 'Child';
        $relationshipList[1]['relationship'] = 'Other';
        $relationshipList[2]['relationship'] = 'Self';
        $relationshipList[3]['relationship'] = 'Spouse';
        $this->view->relationshipList = $relationshipList;
        //added to support #29: Data Management tool enhancement for tags/james
        session_start();
        $tags = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_counts = $_SESSION['tags_in_billingcompany']['tag_count'];
        $this->view->tagList = $tags;
        $this->view->tagNames = $tag_names;
        $this->view->tagNumber = $tag_counts;
        

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $where = $db->quoteInto('billingcompany_id=?', $this->billingcompany_id());
        $modifier_data = $db_modifier->fetchAll($where, 'modifier ASC');
        $this->view->modifierList = $modifier_data;

        $db_placeofservice = new Application_Model_DbTable_Placeofservice();
        $db = $db_placeofservice->getAdapter();
        $placeofservice_data = $db_placeofservice->fetchAll(null, 'pos ASC');
        $this->view->placeofserviceList = $placeofservice_data;

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id()); //
        $billingcompany_data = $db_billingcompany->fetchRow($where);

        $billingcompany_name = $billingcompany_data['billingcompany_name'];

        if ($this->getRequest()->isPost()) {
            $options = array();
            $options['option_name'] = $this->getRequest()->getPost('option_name');
            $options['anesthesia_unit_rounding'] = $this->getRequest()->getPost('anesthesia_unit_rounding');
            $options['anesthesia_billing_rate_for_non_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_non_par');
            $options['patient_statement_interval'] = $this->getRequest()->getPost('patient_statement_interval');
            $key = $this->getRequest()->getPost('default_end_date_to_start_date');
            if ($key[0] != null)
                $options['default_end_date_to_start_date'] = '1';
            else
                $options['default_end_date_to_start_date'] = '0';
            $options['default_patient_relationship_to_insured'] = $this->getRequest()->getPost('default_patient_relationship_to_insured');
            $options['number_of_days_for_delayed_bill_generation1'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation1');
            $options['number_of_days_for_delayed_bill_generation2'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation2');
            $options['number_of_days_for_delayed_bill_generation3'] = $this->getRequest()->getPost('number_of_days_for_delayed_bill_generation3');
            $options['number_of_days_without_activities'] = $this->getRequest()->getPost('number_of_days_without_activities');
            $key = $this->getRequest()->getPost('yes_for_assingment_of_benefits');
            if ($key[0] != null)
                $options['yes_for_assingment_of_benefits'] = '1';
            else
                $options['yes_for_assingment_of_benefits'] = '0';
            $options['number_of_days_AR_outstanding'] = $this->getRequest()->getPost('number_of_days_AR_outstanding');
            $options['provider_invoice_rate'] = $this->getRequest()->getPost('provider_invoice_rate');
            $key = $this->getRequest()->getPost('use_DOS_for_all_dates');
            if ($key[0] != null)
                $options['use_DOS_for_all_dates'] = '1';
            else
                $options['use_DOS_for_all_dates'] = '0';
            $key = $this->getRequest()->getPost('signature_on_file_for_all_signatures');
            if ($key[0] != null)
                $options['signature_on_file_for_all_signatures'] = '1';
            else
                $options['signature_on_file_for_all_signatures'] = '0';
            $key = $this->getRequest()->getPost('auto_populate_diagnosis_pointer');
            if ($key[0] != null)
                $options['auto_populate_diagnosis_pointer'] = '1';
            else
                $options['auto_populate_diagnosis_pointer'] = '0';
            $options['PIP_rate'] = $this->getRequest()->getPost('PIP_rate');
            $options['number_of_days_no_payment_after_agreed'] = $this->getRequest()->getPost('number_of_days_no_payment_after_agreed');
            $options['number_of_days_for_litigation_followup'] = $this->getRequest()->getPost('number_of_days_for_litigation_followup');
            $options['number_of_days_bill_has_not_been_generated'] = $this->getRequest()->getPost('number_of_days_bill_has_not_been_generated');
            $options['default_modifier'] = $this->getRequest()->getPost('default_modifier');
            $options['number_of_days_after_issued_but_not_received'] = $this->getRequest()->getPost('number_of_days_after_issued_but_not_received');
            $options['default_rendering_provider'] = $this->getRequest()->getPost('default_rendering_provider');
            $options['default_facility'] = $this->getRequest()->getPost('default_facility');
            $options['number_of_days_no_payment_issued'] = $this->getRequest()->getPost('number_of_days_no_payment_issued');
            $options['default_provider'] = $this->getRequest()->getPost('default_provider');
            $options['default_place_of_service'] = $this->getRequest()->getPost('default_place_of_service');
            $options['close_to_claim_filing_deadline'] = $this->getRequest()->getPost('close_to_claim_filing_deadline');
            $options['patient_statement_interval'] = $this->getRequest()->getPost('patient_statement_interval');
            $options['non_par_expected_pay'] = $this->getRequest()->getPost('non_par_expected_pa');
            $options['invoice_delivery_preference'] = $this->getRequest()->getPost('invoice_delivery_preferenc');
            $options['anesthesia_billing_rate_for_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_par');
            $options['in_network_contract_rates'] = $this->getRequest()->getPost('in_network_contract_rates');
            $options['reports_delivery_preference'] = $this->getRequest()->getPost('reports_delivery_preference');
            $options['number_of_days_offered_but_not_agreed'] = $this->getRequest()->getPost('number_of_days_offered_but_not_agreed');

            $options['number_of_days_no_payment_issued'] = $this->getRequest()->getPost('number_of_days_no_payment_issued');
            $options['anesthesia_billing_rate_for_par'] = $this->getRequest()->getPost('anesthesia_billing_rate_for_par');
            $options['statement_I_1'] = $this->getRequest()->getPost('statement_I_1');
            $options['statement_I_2'] = $this->getRequest()->getPost('statement_I_2');
            $options['statement_I_3'] = $this->getRequest()->getPost('statement_I_3');
            $options['statement_I_4'] = $this->getRequest()->getPost('statement_I_4');
            $options['statement_II_1'] = $this->getRequest()->getPost('statement_II_1');
            $options['statement_II_2'] = $this->getRequest()->getPost('statement_II_2');
            $options['statement_II_3'] = $this->getRequest()->getPost('statement_II_3');
            $options['statement_II_4'] = $this->getRequest()->getPost('statement_II_4');
            $options['statement_III_1'] = $this->getRequest()->getPost('statement_III_1');
            $options['statement_III_2'] = $this->getRequest()->getPost('statement_III_2');
            $options['statement_III_3'] = $this->getRequest()->getPost('statement_III_3');
            $options['statement_III_4'] = $this->getRequest()->getPost('statement_III_4');
            $options['statement_V_1'] = $this->getRequest()->getPost('statement_V_1');

            $options['number_of_days_include_in_ledger'] = $this->getRequest()->getPost('number_of_days_include_in_ledger');
            $options['SI_send_to_patient'] = $this->getRequest()->getPost('SI_send_to_patient');
            $options['SI_co-insurance'] = $this->getRequest()->getPost('SI_co_insurance');
            $options['SI_deductible'] = $this->getRequest()->getPost('SI_deductible');
            $options['SI_selfpay'] = $this->getRequest()->getPost('SI_selfpay');
            $options['installment_statement_date'] = $this->getRequest()->getPost('installment_statement_date');
            $options['custom_label_1'] = $this->getRequest()->getPost('custom_label_1');
            $options['custom_label_2'] = $this->getRequest()->getPost('custom_label_2');
            $options['custom_label_3'] = $this->getRequest()->getPost('custom_label_3');
            $options['custom_label_4'] = $this->getRequest()->getPost('custom_label_4');
            $options['default_pay_rate'] = $this->getRequest()->getPost('default_pay_rate');
            $options['autoposting'] = $this->getRequest()->getPost('autoposting');
            /*             * added to support new options Tags field in front page */
//            $options['tags'] = $this->getRequest()->getPost('tags');
            //added to support #29: Data Management tool enhancement for tags/james:start
            $tags_from_page = "";
            $loop_mark = 0;
            $tags_size = count($tags);
            foreach ($tags as $row) {
                $loop_mark ++;
                $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                if ($row[tag_type] == "binary") {
                    if ($tp_value_from_page == "yes") {
                        if ($loop_mark != $tags_size) {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                        } else {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                        }
                    }
                } else if ($row[tag_type] == "other") {
                    if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                        if ($loop_mark != $tags_size) {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                        } else {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                        }
                    }
                }
            }
            if (substr($tags_from_page, -1) == "|") {
                $tags_from_page = substr($tags_from_page, 0, -1);
            }
            //added to support #29: Data Management tool enhancement for tags/james:end
            $options['tags'] = $tags_from_page;            
            $options['min_gap'] = $this->getRequest()->getPost('min_gap');
            $options['billingcompany_id'] = $this->billingcompany_id();
            $db_options = new Application_Model_DbTable_Options();
            $db = $db_options->getAdapter();
            $where = $db->quoteInto("billingcompany_id = ?", $options['billingcompany_id']) . $db->quoteInto("and option_name = ? ", $options['option_name']);
            $dataexisting = $db_options->fetchAll($where)->toArray();
            if (isset($dataexisting[0])) {
                echo '<span style="color:red;font-size:16px">Sorry ! The Option Name is existing , please rewrite !</span>';
            } else {
                if ($db_options->insert($options)) {
                    $this->adddatalog($provider_id, $options['option_name'], 'options.option_name', NULL, $options['option_name']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.anesthesia_unit_rounding', NULL, $options['anesthesia_unit_rounding']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.anesthesia_billing_rate_for_non_par', NULL, $options['anesthesia_billing_rate_for_non_par']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.patient_statement_interval', NULL, $options['patient_statement_interval']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_end_date_to_start_date', NULL, $options['default_end_date_to_start_date']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_patient_relationship_to_insured', NULL, $options['default_patient_relationship_to_insured']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_for_delayed_bill_generation1', NULL, $options['number_of_days_for_delayed_bill_generation1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_for_delayed_bill_generation2', NULL, $options['number_of_days_for_delayed_bill_generation2']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_for_delayed_bill_generation3', NULL, $options['number_of_days_for_delayed_bill_generation3']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_without_activities', NULL, $options['number_of_days_without_activities']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.yes_for_assingment_of_benefits', NULL, $options['yes_for_assingment_of_benefits']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_AR_outstanding', NULL, $options['number_of_days_AR_outstanding']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.provider_invoice_rate', NULL, $options['provider_invoice_rate']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.use_DOS_for_all_dates', NULL, $options['use_DOS_for_all_dates']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.signature_on_file_for_all_signatures', NULL, $options['signature_on_file_for_all_signatures']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.auto_populate_diagnosis_pointer', NULL, $options['auto_populate_diagnosis_pointer']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.PIP_rate', NULL, $options['PIP_rate']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_no_payment_after_agreed', NULL, $options['number_of_days_no_payment_after_agreed']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_for_litigation_followup', NULL, $options['number_of_days_for_litigation_followup']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_bill_has_not_been_generated', NULL, $options['number_of_days_bill_has_not_been_generated']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_modifier', NULL, $options['default_modifier']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_after_issued_but_not_received', NULL, $options['number_of_days_after_issued_but_not_received']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_rendering_provider', NULL, $options['default_rendering_provider']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_facility', NULL, $options['default_facility']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_no_payment_issued', NULL, $options['number_of_days_no_payment_issued']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_provider', NULL, $options['default_provider']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_place_of_service', NULL, $options['default_place_of_service']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.close_to_claim_filing_deadline', NULL, $options['close_to_claim_filing_deadline']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.patient_statement_interval', NULL, $options['patient_statement_interval']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.non_par_expected_pay', NULL, $options['non_par_expected_pay']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.invoice_delivery_preference', NULL, $options['invoice_delivery_preference']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.anesthesia_billing_rate_for_par', NULL, $options['anesthesia_billing_rate_for_par']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.in_network_contract_rates', NULL, $options['in_network_contract_rates']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.reports_delivery_preference', NULL, $options['reports_delivery_preference']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_offered_but_not_agreed', NULL, $options['number_of_days_offered_but_not_agreed']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_no_payment_issued', NULL, $options['number_of_days_no_payment_issued']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.anesthesia_billing_rate_for_par', NULL, $options['anesthesia_billing_rate_for_par']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_I_1', NULL, $options['statement_I_1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_I_2', NULL, $options['statement_I_2']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_I_3', NULL, $options['statement_I_3']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_I_4', NULL, $options['statement_I_4']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_II_1', NULL, $options['statement_II_1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_II_2', NULL, $options['statement_II_2']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_II_3', NULL, $options['statement_II_3']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_II_4', NULL, $options['statement_II_4']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_III_1', NULL, $options['statement_III_1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_III_2', NULL, $options['statement_III_2']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_III_3', NULL, $options['statement_III_3']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_III_4', NULL, $options['statement_III_4']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.statement_V_1', NULL, $options['statement_V_1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.number_of_days_include_in_ledger', NULL, $options['number_of_days_include_in_ledger']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.SI_send_to_patient', NULL, $options['SI_send_to_patient']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.SI_co-insurance', NULL, $options['SI_co-insurance']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.SI_deductible', NULL, $options['SI_deductible']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.SI_selfpay', NULL, $options['SI_selfpay']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.installment_statement_date', NULL, $options['installment_statement_date']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.custom_label_1', NULL, $options['custom_label_1']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.custom_label_2', NULL, $options['custom_label_2']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.custom_label_3', NULL, $options['custom_label_3']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.custom_label_4', NULL, $options['custom_label_4']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.default_pay_rate', NULL, $options['default_pay_rate']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.min_gap', NULL, $options['min_gap']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.autoposting', NULL, $options['autoposting']);
                    $this->adddatalog($provider_id, $options['option_name'], 'options.billingcompany_id', NULL, $options['billingcompany_id']);
                    //   $this->adddatalog($provider_id,$options['option_name'],'options.billingcompany_id',NULL,$options['billingcompany_id']);
                    //  $this->adddatalog($provider_id,$options['option_name'],'options.non_par_expected_pay',NULL,$options['non_par_expected_pay']);
                }
                /*                 * add to let the option page show the record that have been added just now. james#datatoolbehavior */
//                session_start();
//                $_SESSION['data_management']['option_name'] = $options['option_name'];

                $this->_redirect('/biller/data/option');
            }
        }
    }

    /**
     * optioninfoAction
     * a function returning the option data for displaying on the page.
     * @author Haowei.
     * @return the option data for displaying on the page
     * @version 05/15/2012
     */
    public function optioninfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $options_data = $db_options->fetchRow($where);
      //added to support #29 provider tags extend work:james/start
        $exist_tags = $options_data['tags'];
        $exist_tags_List = explode('|', $exist_tags);
        $mark_exists = 0;
        foreach ($exist_tags_List as $row) {
            $tp_exist_tags = explode('=', $row);
            $exist_tag_names[$mark_exists] = $tags[$mark_exists]['tag_name'] = $tp_exist_tags[0];
            $tags[$mark_exists]['tag_type'] = $tp_exist_tags[1];
            $mark_exists ++;
        }
        $tag_count_exists = $mark_exists;

        session_start();
        $tags_in_billingcompany = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_count_in_billingcompany = $_SESSION['tags_in_billingcompany']['tag_count'];
        //added to support #29 provider tags extend work:james/end                
        $data = array();
        $data = array('id' => $options_data['id'], 'anesthesia_unit_rounding' => $options_data['anesthesia_unit_rounding'],
            'anesthesia_billing_rate_for_non_par' => $options_data['anesthesia_billing_rate_for_non_par'],
            'PIP_rate' => $options_data['PIP_rate'], 'auto_populate_diagnosis_pointer' => $options_data['auto_populate_diagnosis_pointer'], 'signature_on_file_for_all_signatures' => $options_data['signature_on_file_for_all_signatures'],
            'use_DOS_for_all_dates' => $options_data['use_DOS_for_all_dates'], 'provider_invoice_rate' => $options_data['provider_invoice_rate'], 'number_of_days_AR_outstanding' => $options_data['number_of_days_AR_outstanding'],
            'yes_for_assingment_of_benefits' => $options_data['yes_for_assingment_of_benefits'], 'number_of_days_without_activities' => $options_data['number_of_days_without_activities'], 'number_of_days_for_delayed_bill_generation1' => $options_data['number_of_days_for_delayed_bill_generation1'],
            'number_of_days_for_delayed_bill_generation2' => $options_data['number_of_days_for_delayed_bill_generation2'], 'number_of_days_for_delayed_bill_generation3' => $options_data['number_of_days_for_delayed_bill_generation3'],
            'default_end_date_to_start_date' => $options_data['default_end_date_to_start_date'], 'number_of_days_no_payment_after_agreed' => $options_data['number_of_days_no_payment_after_agreed'], 'number_of_days_for_litigation_followup' => $options_data['number_of_days_for_litigation_followup'],
            'default_patient_relationship_to_insured' => $options_data['default_patient_relationship_to_insured'], 'number_of_days_no_payment_after_agreed' => $options_data['number_of_days_no_payment_after_agreed'], 'number_of_days_bill_has_not_been_generated' => $options_data['number_of_days_bill_has_not_been_generated'],
            'default_modifier' => $options_data['default_modifier'], 'default_provider' => $options_data['default_provider'], 'number_of_days_no_payment_issued' => $options_data['number_of_days_no_payment_issued'],
            'default_facility' => $options_data['default_facility'], 'default_rendering_provider' => $options_data['default_rendering_provider'], 'number_of_days_after_issued_but_not_received' => $options_data['number_of_days_after_issued_but_not_received'],
            'patient_statement_interval' => $options_data['patient_statement_interval'],
            'close_to_claim_filing_deadline' => $options_data['close_to_claim_filing_deadline'], 'default_place_of_service' => $options_data['default_place_of_service'], 'non_par_expected_pay' => $options_data['non_par_expected_pay'],
            'invoice_delivery_preference' => $options_data['invoice_delivery_preference'], 'in_network_contract_rates' => $options_data['in_network_contract_rates'],
            'anesthesia_billing_rate_for_par' => $options_data['anesthesia_billing_rate_for_par'], 'statement_I_1' => $options_data['statement_I_1'], 'statement_I_2' => $options_data['statement_I_2'], 'statement_I_3' => $options_data['statement_I_3'],
            'statement_I_4' => $options_data['statement_I_4'], 'statement_II_1' => $options_data['statement_II_1'], 'statement_II_2' => $options_data['statement_II_2'],
            'statement_II_3' => $options_data['statement_II_3'], 'statement_II_4' => $options_data['statement_II_4'], 'statement_III_1' => $options_data['statement_III_1'],
            'statement_III_2' => $options_data['statement_III_2'], 'statement_III_3' => $options_data['statement_III_3'], 'statement_III_4' => $options_data['statement_III_4'], 'statement_V_1' => $options_data['statement_V_1'], 'number_of_days_include_in_ledger' => $options_data['number_of_days_include_in_ledger'],
            'SI_send_to_patient' => $options_data['SI_send_to_patient'], 'SI_co_insurance' => $options_data['SI_co-insurance'], 'SI_deductible' => $options_data['SI_deductible'],
            'SI_selfpay' => $options_data['SI_selfpay'], 'installment_statement_date' => $options_data['installment_statement_date'], 'custom_label_1' => $options_data['custom_label_1'],
            'custom_label_2' => $options_data['custom_label_2'], 'custom_label_3' => $options_data['custom_label_3'], 'custom_label_4' => $options_data['custom_label_4'],
            'default_pay_rate' => $options_data['default_pay_rate'], 'min_gap' => $options_data['min_gap'], 'autoposting' => $options_data['autoposting'],'toomanyminutes'=>$options_data['toomanyminutes'],
            /*             * added to support show "Tags" in front page */
            'tags' => $tags, 'tags_in_billingcompany' => $tags_in_billingcompany, 'tag_names' => $tag_names, 'tag_number' => $tag_count_in_billingcompany,
            'exist_tag_number' => $tag_count_exists, 'exist_tag_names' => $exist_tag_names,
        );
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * claimfacilityAction
     * a function returning the facility list.
     * @author Haowei.
     * @return the facility list
     * @version 05/15/2012
     */
    public function claimfacilityAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasFac.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,facility_id FROM providerhasfacility has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasFac
ON hasFac.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive') 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active') 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumFac = array('num' => $all);
    }
    
    /**
     * by haoqiang
     */
      public function deletefacAction(){
       $this->_helper->viewRenderer->setNoRender();
       $provider_id = $this->_request->getPost('provider_id');
       $facility_id = $this->_request->getPost('facility_id');
       $status = $this->_request->getPost('status');
       $del_provider_id = $this->_request->getPost('del_provider_id');
       
       if($status=="active"){
           $data['status'] = '1';
       }else{
           
           $db_encounter = new Application_Model_DbTable_Encounter();
           $db = $db_encounter->getAdapter();
           $where = $db->quoteInto('facility_id=?',$facility_id);
           $result = $db_encounter->fetchAll($where)->toArray();
           if($result){
              //不能删除，仍有使用
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           }else{
            //可以删除，具体看哪一种情况来删除
            $data['flag'] = '0';
            //处理处于inactive状态的数据
           if($provider_id==0){
               //说明是选择的ALL 模式
               if($del_provider_id==0){
                   //说明没有选择任何一个provider，需要检测后全部删除
                    $billingcompany_id = $this->billingcompany_id();
                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                    $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();
                    
                    //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
                    $providers = array();
                    if($provider_data){
                        $len = count($provider_data);
                        for($i=0;$i<$len;$i++){
                            $providers[$i] = $provider_data[$i]['id'];
                        }
                    }
                    
                    $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
                    $db_pf = $db_providerhasfacility->getAdapter();
                    $where = $db_pf->quoteInto('facility_id=?',$facility_id).$db_pf->quoteInto('AND provider_id in(?)',$providers);
                    $db_providerhasfacility->delete($where);
               }else{
                   //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的facility
                   $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
                   $db_pf = $db_providerhasfacility->getAdapter();
                   $where = $db_pf->quoteInto('facility_id=?',$facility_id).$db_pf->quoteInto('AND provider_id =?',$del_provider_id);
                   $db_providerhasfacility->delete($where);
               }
            }else{
               //说明是需要具体处理某一个传过来的provider
               $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
               $db_pf = $db_providerhasfacility->getAdapter();
               $where = $db_pf->quoteInto('facility_id=?',$facility_id).$db_pf->quoteInto('AND provider_id=?',$provider_id);

               $db_providerhasfacility->delete($where);
           }
        }//end 可删除情况
    }
       
      
       
       $json = Zend_Json::encode($data);
       echo $json;
       
}
  
  
   /**
     * by haoqiang
     */
      public function deletemodifyAction(){
       $this->_helper->viewRenderer->setNoRender();
       $modifier_id = $this->_request->getPost('modifier_id');
       $status = $this->_request->getPost('status');
        $db_modify = new Application_Model_DbTable_Modifier();
        $db_m = $db_modify->getAdapter();
        $where_m = $db_m->quoteInto('id=?',$modifier_id);
        $result_m = $db_modify->fetchAll($where_m);
        $modifier = $result_m[0]['modifier'];
        $modifier = "%".$modifier."%";
       
       if($status=="active"){
           $data['status'] = '1';
       }else{
           $db_encounter = new Application_Model_DbTable_Encounter();
           $db = $db_encounter->getAdapter();
           $where = $db->quoteInto('modifier1_1 like ?',$modifier).$db->quoteInto(' or modifier1_2 like ?',$modifier).$db->quoteInto(' or modifier1_3 like ?',$modifier).$db->quoteInto(' or modifier1_4 like ?',$modifier).$db->quoteInto(' or modifier1_5 like ?',$modifier).$db->quoteInto(' or modifier1_6 like ?',$modifier);
           $result = $db_encounter->fetchAll($where)->toArray();
           if($result){
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           }else{
            $data['flag'] = '0';
              //$db_encounter->delete($where);
//            $db_modify = new Application_Model_DbTable_Modifier();
//            $db_m = $db_modify->getAdapter();
//            $where = $db_m->quoteInto('id=?',$modifier_id);

            $db_modify->delete($where_m);
           }
       }
       
      
       
       $json = Zend_Json::encode($data);
       echo $json;
       
  }
  
    /**
     * by haoqiang
    */
      public function deleteinsuranceAction(){
       $this->_helper->viewRenderer->setNoRender();
       $insurance_id = $this->_request->getPost('insurance_id');
       $status = $this->_request->getPost('status');
       
       if($status=="active"){
           $data['status'] = '1';
       }else{
           //根据insurance 查询 insured 表中那些关联，然后根据 insuredid 最终确定，哪些encounter 仍然在使用
           $db_insured = new Application_Model_DbTable_Insured();
           $db_is = $db_insured->getAdapter();
           $where_insured = $db_is->quoteInto('insurance_id=?',$insurance_id).$db_is->quoteInto(' OR other_insurance_id=?',$insurance_id);
           $result_insured = $db_insured->fetchAll($where_insured)->toArray();
           $encounterArray = array();
           if($result_insured){
               $len_insured = count($result_insured);
               for($i=0;$i<$len_insured;$i++){
                   $insured_id = $result_insured[$i]['id'];
                   $db_en_in = new Application_Model_DbTable_Encounterinsured();
                   $db_e_i = $db_en_in->getAdapter();
                   $where_e_i = $db_e_i->quoteInto('insured_id',$len_insured);
                   $result_e_i = $db_en_in->fetchAll($where_e_i)->toArray();
                   if($result_e_i){
                       $len_encounter = count($result_e_i);
                       for($j=0;$j<$len_encounter;$j++){
                           array_push($encounterArray,$result_e_i[$j]['encounter_id']);//最后把encounter_id 全部存起来
                       }
                   }
               }
           }
           
           
            if($encounterArray){
               //说明仍然有claim 关联
            $data['flag'] = '1';
            $encounterArray = array_unique($encounterArray);
            $db_encounter = new Application_Model_DbTable_Encounter();
            $db = $db_encounter->getAdapter();
            $where = $db->quoteInto('id in(?)',$encounterArray);
            $result = $db_encounter->fetchAll($where)->toArray();
             $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
              
           }else{
               //没有claim 关联，删除该insurance， 使用billingcompany和 id 删除
                $data['flag'] = '0';
                $db_con = new Application_Model_DbTable_Contractrates();
                $db_c = $db_con->getAdapter();
                $where_c = $db_c->quoteInto('insurance_id = ?',$insurance_id);
                $db_con->delete($where_c);
                
                
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db_delete = $db_insurance->getAdapter();
                $where_delete = $db_delete->quoteInto('id=?',$insurance_id);
                $db_insurance->delete($where_delete);
           }
           
        }
           
       
           
       $json = Zend_Json::encode($data);
       echo $json;
       
  }
  
  /**
   * by haoqiang
   */
    public function deletereferAction(){
       $this->_helper->viewRenderer->setNoRender();
       $provider_id = $this->_request->getPost('provider_id');
       $refer_id = $this->_request->getPost('refer_id');
       $status = $this->_request->getPost('status');
       $del_provider_id = $this->_request->getPost('del_provider_id');
       
       if($status=="active"){
           $data['status'] = '1';
       }else{
           $db_encounter = new Application_Model_DbTable_Encounter();
           $db = $db_encounter->getAdapter();
           $where = $db->quoteInto('referringprovider_id=?',$refer_id).$db->quoteInto('and provider_id=?',$provider_id);
           $result = $db_encounter->fetchAll($where)->toArray();
           if($result){
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           }else{
                //可以删除，具体看哪一种情况来删除
            $data['flag'] = '0';
            //处理处于inactive状态的数据
           if($provider_id==0){
               //说明是选择的ALL 模式
               if($del_provider_id==0){
                   //说明没有选择任何一个provider，需要检测后全部删除
                    $billingcompany_id = $this->billingcompany_id();
                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                    $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();
                    
                    //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
                    $providers = array();
                    if($provider_data){
                        $len = count($provider_data);
                        for($i=0;$i<$len;$i++){
                            $providers[$i] = $provider_data[$i]['id'];
                        }
                    }
                    
                    $db_providerreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
                    $db_pf = $db_providerreferringprovider->getAdapter();
                    $where = $db_pf->quoteInto('referringprovider_id=?',$refer_id).$db_pf->quoteInto('AND provider_id in(?)',$providers);
                    $db_providerreferringprovider->delete($where);
               }else{
                   //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的facility
                   $db_providerreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
                   $db_pf = $db_providerreferringprovider->getAdapter();
                   $where = $db_pf->quoteInto('referringprovider_id=?',$refer_id).$db_pf->quoteInto('AND provider_id =?',$del_provider_id);
                   $db_providerreferringprovider->delete($where);
               }
            }else{
               //说明是需要具体处理某一个传过来的provider
               $db_providerreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
               $db_pf = $db_providerreferringprovider->getAdapter();
               $where = $db_pf->quoteInto('referringprovider_id=?',$refer_id).$db_pf->quoteInto('AND provider_id=?',$provider_id);

               $db_providerreferringprovider->delete($where);
            }
               
             
              //$db_encounter->delete($where);
//            $db_providerreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
//            $db_pf = $db_providerreferringprovider->getAdapter();
//            $where = $db_pf->quoteInto('referringprovider_id=?',$refer_id).$db_pf->quoteInto('AND provider_id=?',$provider_id);
//
//            $db_providerreferringprovider->delete($where);

           }
       }
       
      
       
       $json = Zend_Json::encode($data);
       echo $json;
       
  }
  
   /**
    * by haoqiang
    */      
   public function deleterenderAction(){
       $this->_helper->viewRenderer->setNoRender();
       $provider_id = $this->_request->getPost('provider_id');
       $render_id = $this->_request->getPost('render_id');
       $status = $this->_request->getPost('status');
       $del_provider_id = $this->_request->getPost('del_provider_id');
       if($status=="active"){
           $data['status'] = '1';
       }else{
           $db_encounter = new Application_Model_DbTable_Encounter();
           $db = $db_encounter->getAdapter();
           $where = $db->quoteInto('renderingprovider_id=?',$render_id);//只需要从encount里面查 renderingprovider_id即可
           $result = $db_encounter->fetchAll($where)->toArray();
           if($result){
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           }else{
            //可以删除，具体看哪一种情况来删除
            $data['flag'] = '0';
            //处理处于inactive状态的数据
           if($provider_id==0){
               //说明是选择的ALL 模式
               if($del_provider_id==0){
                   //说明没有选择任何一个provider，需要检测后全部删除
                    $billingcompany_id = $this->billingcompany_id();
                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                    $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();
                    
                    //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
                    $providers = array();
                    if($provider_data){
                        $len = count($provider_data);
                        for($i=0;$i<$len;$i++){
                            $providers[$i] = $provider_data[$i]['id'];
                        }
                    }
                    
                    $db_providerrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                    $db_pf = $db_providerrenderingprovider->getAdapter();
                    $where = $db_pf->quoteInto('renderingprovider_id=?',$render_id).$db_pf->quoteInto('AND provider_id in(?)',$providers);
                    $db_providerrenderingprovider->delete($where);
               }else{
                   //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的facility
                   $db_providerrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                   $db_pf = $db_providerrenderingprovider->getAdapter();
                   $where = $db_pf->quoteInto('renderingprovider_id=?',$render_id).$db_pf->quoteInto('AND provider_id =?',$del_provider_id);
                   $db_providerrenderingprovider->delete($where);
               }
            }else{
               //说明是需要具体处理某一个传过来的provider
               $db_providerrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
               $db_pf = $db_providerrenderingprovider->getAdapter();
               $where = $db_pf->quoteInto('renderingprovider_id=?',$render_id).$db_pf->quoteInto('AND provider_id=?',$provider_id);

               $db_providerrenderingprovider->delete($where);
           }
           
           
//            $db_providerrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
//            $db_pf = $db_providerrenderingprovider->getAdapter();
//            $where = $db_pf->quoteInto('renderingprovider_id=?',$render_id).$db_pf->quoteInto('AND provider_id=?',$provider_id);
//
//            $db_providerrenderingprovider->delete($where);
            

           }
       }
       
      
       
       $json = Zend_Json::encode($data);
       echo $json;
       
  }
 
    /**
   * by haoqiang
   */
     public function deletecptAction(){
       $this->_helper->viewRenderer->setNoRender();
       $provider_id = $this->_request->getPost('provider_id');
       $status = $this->_request->getPost('status');
       $anes_code = $this->_request->getPost('anesthesia_code_id');//获取到的是anesthesia_code
       $cpt_code_id = $this->_request->getPost('cpt_code_id');//获取的是 cpt_id
       $del_provider_id = $this->_request->getPost('del_provider_id');//ALL 模式下，删除哪一个       
        
       if($status=="active"){
           $data['status'] = '1';
       }else{
            //根据cptid 获取到 对应的cpt_code, 因为在其他地方都是用的cpt_code 进行的操作。
            $db_cpt = new Application_Model_DbTable_Cptcode();
            $db = $db_cpt->getAdapter();
            $where = $db->quoteInto('id=?',$cpt_code_id);
            $result = $db_cpt->fetchAll($where)->toArray();
            $cpt_code = $result[0]['CPT_code'];//获取到anes 码,根据id 取，肯定只有一个
            //$cpt_code = cptcheck($cpt_code);
            $new_cpt = '*'.$cpt_code;
            $cpt_code_array = array($cpt_code,$new_cpt);//这个数组保存两种形式的 cpt_code
                        
           $db_encounter = new Application_Model_DbTable_Encounter();
           $db = $db_encounter->getAdapter();
           //$where = $db->quoteInto('CPT_code_1 in(?)',$cpt_code_array).$db->quoteInto('and provider_id=?',$provider_id).$db->quoteInto('and secondary_CPT_code_1=?',$anes_code);
           $where = $db->quoteInto('(CPT_code_1 in(?)',$cpt_code_array).$db->quoteInto(' or CPT_code_2 in(?)',$cpt_code_array).$db->quoteInto(' or CPT_code_3 in(?)',$cpt_code_array).$db->quoteInto(' or CPT_code_4 in(?)',$cpt_code_array).$db->quoteInto(' or CPT_code_5 in(?)',$cpt_code_array).$db->quoteInto(' or CPT_code_6 in(?))',$cpt_code_array);
           if($provider_id !=0){
                $where = $where . $db->quoteInto(' AND provider_id =?',$provider_id);
            }else {
                if ($del_provider_id != 0) {
                    $where = $where . $db->quoteInto(' AND provider_id =?',$del_provider_id);
                } else {
                    $where = $where . $db->quoteInto(' AND provider_id IN (?)',$providers);
                }
            }
           $result = $db_encounter->fetchAll($where)->toArray();
           //$result 为真说明有 claim 还在使用，那么就不能删除。
           if($result){
              //$result 为真，说明encounter有值，不能删除
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           } else {
               
                //可以删除，具体看哪一种情况来删除
               $data['flag'] = '0';
                //处理处于inactive状态的数据
               if($provider_id==0){
                   //说明是选择的ALL 模式
                   if($del_provider_id==0){
                       //说明没有选择任何一个provider，需要检测后将cpt 全部删除
                        $billingcompany_id = $this->billingcompany_id();
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();
                        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                        $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();

                        //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
                        $providers = array();
                        if($provider_data){
                            $len = count($provider_data);
                            for($i=0;$i<$len;$i++){
                                $providers[$i] = $provider_data[$i]['id'];
                            }
                        }

                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db_c = $db_cpt->getAdapter();
                        $where_c = $db_c->quoteInto('CPT_code=?',$cpt_code).$db_c->quoteInto('AND provider_id in(?)',$providers);
                        $db_cpt->delete($where_c);
                   }else{
                       //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的cpt_code
                       $db_cpt = new Application_Model_DbTable_Cptcode();
                       $db_c = $db_cpt->getAdapter();
                       $where_c = $db_c->quoteInto('CPT_code=?',$cpt_code).$db_c->quoteInto('AND provider_id =?',$del_provider_id);
                       $db_cpt->delete($where_c);
                   }
                }else{
                   //说明是需要具体处理某一个传过来的provider
                   $db_cpt = new Application_Model_DbTable_Cptcode();
                   $db_c = $db_cpt->getAdapter();
                   $where_c = $db_c->quoteInto('CPT_code=?',$cpt_code).$db_c->quoteInto('AND provider_id=?',$provider_id);

                   $db_cpt->delete($where_c);
               }
            }
        }

        $json = Zend_Json::encode($data);
       echo $json;
       
  }
  
  
    /**
     * by haoqiang 
     */
    public function deletediagnosisAction(){
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $this->_request->getPost('provider_id');
        $del_provider_id = $this->_request->getPost('del_provider_id');
        $diagnosis_code = $this->_request->getPost('diagnosis_code');
        $status = $this->_request->getPost('status');
        
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();

        //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
        $providers = array();
        if($provider_data){
            $len = count($provider_data);
            for($i=0;$i<$len;$i++){
                $providers[$i] = $provider_data[$i]['id'];
            }
        }
        $db_diagnosiscode_10 = new Application_Model_DbTable_Diagnosiscode10();
        $db = $db_diagnosiscode_10->getAdapter();
        $where = $db->quoteInto('diagnosis_code = \'' . $diagnosis_code . '\'');
        $diagcodeArray = $db_diagnosiscode_10->fetchAll($where)->toArray();
        $diagcode_id = $diagcodeArray[0]['id'];
       
        if($status=="active"){
            $data['status'] = '1';
        }else {
            //检查是否有claim 和 该diagnosis 相关联
            $db_encounter = new Application_Model_DbTable_Encounter();
            $db = $db_encounter->getAdapter();
            $where = $db->quoteInto('(diagnosis_code1=?',$diagnosis_code).$db->quoteInto(' OR diagnosis_code2=?',$diagnosis_code).$db->quoteInto(' OR diagnosis_code3=?',$diagnosis_code).$db->quoteInto(' OR diagnosis_code4=?',$diagnosis_code).$db->quoteInto(')');
            if($provider_id !=0){
                $where = $where . $db->quoteInto(' AND provider_id =?',$provider_id);
            }else {
                if ($del_provider_id != 0) {
                    $where = $where . $db->quoteInto(' AND provider_id =?',$del_provider_id);
                } else {
                    $where = $where . $db->quoteInto(' AND provider_id IN (?)',$providers);
                }
            } 
            $result = $db_encounter->fetchAll($where)->toArray();
            if($result){
                $data['flag'] = '1';
                $len = count($result);
                $patientinfoArray = array();
                for($i=0;$i<$len;$i++){
                    $patientid = $result[$i]['patient_id'];
                    $providerid = $result[$i]['provider_id'];
                    $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                    $db_patient = new Application_Model_DbTable_Patient();
                    $db = $db_patient->getAdapter();
                    $where = $db->quoteInto('id=?',$patientid);
                    $info = $db_patient->fetchAll($where)->toArray();
                    $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                    $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('id=?',$providerid);
                    $info1 = $db_provider->fetchAll($where)->toArray();
                    $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
                }
                $data['patientinfoArray'] = $patientinfoArray;
           }else {
                //可以删除，具体看哪一种情况来删除
                $data['flag'] = '0';
                //处理处于inactive状态的数据
                if($provider_id==0){
                    if($del_provider_id==0){
                        //说明没有选择任何一个provider，需要检测后全部删除
                        $db_pdhasdiagnosis = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                        $db_pd = $db_pdhasdiagnosis->getAdapter();
                        $where = $db_pd->quoteInto('diagnosiscode_10_id=?',$diagcode_id).$db_pd->quoteInto(' AND provider_id in(?)',$providers);
                        $db_pdhasdiagnosis->delete($where);
                    }else {
                        //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的facility
                        $db_pdhasdiagnosis = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                        $db_pd = $db_pdhasdiagnosis->getAdapter();
                        $where = $db_pd->quoteInto('diagnosiscode_10_id=?',$diagcode_id).$db_pd->quoteInto(' AND provider_id=?',$del_provider_id);
                        $db_pdhasdiagnosis->delete($where);
                    }
                }else {
                    //说明是需要具体处理某一个传过来的provider
                    $db_pdhasdiagnosis = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                    $db_pd = $db_pdhasdiagnosis->getAdapter();
                    $where = $db_pd->quoteInto('diagnosiscode_10_id=?',$diagcode_id).$db_pd->quoteInto(' AND provider_id=?',$provider_id);
                    $db_pdhasdiagnosis->delete($where);
                }

            }
        }
       
       $json = Zend_Json::encode($data);
       echo $json;
    }
  
  /**
   * by haoqiang
   */          
  public function deletestatusAction(){
       $this->_helper->viewRenderer->setNoRender();
       $id = $this->_request->getPost('id');
       $status = $this->_request->getPost('status');
       $type = $this->_request->getPost('type');
       $text = $this->_request->getPost('text');
 
       if($status=="active"){
           $data['status'] = '1';
       }else{
            $db_claim = new Application_Model_DbTable_Claim();
            $db = $db_claim->getAdapter();
            $where = $db->quoteInto("{$type} = ?",$text);
            $result = $db_claim->fetchAll($where)->toArray();
            
            if($result){
                 //$result 为真，说明encounter有值，不能删除
                $data['flag'] = '1';
                $len = count($result);
                $patientinfoArray = array();
                for($i=0;$i<$len;$i++){
                    
                    $claimid = $result[$i]['id'];
                    $db_encounter = new Application_Model_DbTable_Encounter();
                    $db = $db_encounter->getAdapter();
                    $where = $db->quoteInto('claim_id=?',$claimid);
                    $en_result = $db_encounter->fetchAll($where)->toArray();
                    
                    $patientid = $en_result[0]['patient_id'];
                    $providerid = $en_result[0]['provider_id'];
                    $patientinfoArray[$i]['dos'] = $en_result[0]['start_date_1'];

                    $db_patient = new Application_Model_DbTable_Patient();
                    $db = $db_patient->getAdapter();
                    $where = $db->quoteInto('id=?',$patientid);
                    $info = $db_patient->fetchAll($where)->toArray();
                    $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                    $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto('id=?',$providerid);
                    $info1 = $db_provider->fetchAll($where)->toArray();
                    $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
                }
                $data['patientinfoArray'] = $patientinfoArray;
            }else{
                
                $data['flag'] = '0';
                  //根据 id 来获取 对应的status
                if($type=="claim_status"){
                    $db_status = new Application_Model_DbTable_Billingcompanyclaimstatus();
                    $db_sta = $db_status->getAdapter();
                    $where_sta = $db_sta->quoteInto('claimstatus_id =?',$id).$db->quoteInto(" and billingcompany_id=?",$this->billingcompany_id);
                    $result_sta = $db_status->fetchAll($where_sta)->toArray();
                }else if($type=="bill_status"){
                    $db_status = new Application_Model_DbTable_Billingcompanybillstatus();
                    $db_sta = $db_status->getAdapter();
                    $where_sta = $db_sta->quoteInto('billstatus_id =?',$id).$db->quoteInto(" and billingcompany_id=?",$this->billingcompany_id);
                    $result_sta = $db_status->fetchAll($where_sta)->toArray();
                }else{
                    $db_status = new Application_Model_DbTable_Billingcompanystatementstatus();
                    $db_sta = $db_status->getAdapter();
                    $where_sta = $db_sta->quoteInto('statementstatus_id =?',$id).$db->quoteInto(" and billingcompany_id=?",$this->billingcompany_id);
                    $result_sta = $db_status->fetchAll($where_sta)->toArray();
                }
               
                
                $status_id = $result_sta[0]['id'];
                $where_delete = $db_sta->quoteInto("id=?",$status_id);
                $db_status->delete($where_delete);
            }
        }

        $json = Zend_Json::encode($data);
        echo $json;
  }
  
  /**
   * by haoqiang
   */
     public function deleteanesAction(){
       $this->_helper->viewRenderer->setNoRender();
       $provider_id = $this->_request->getPost('provider_id');
       $status = $this->_request->getPost('status');
       $anesthesia_code_id = $this->_request->getPost('anesthesia_code_id');
       $del_provider_id = $this->_request->getPost('del_provider_id');
       
        $db_anes = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_anes->getAdapter();
        $where = $db->quoteInto('id=?',$anesthesia_code_id);
        $result = $db_anes->fetchAll($where)->toArray();
        $anes_code = $result[0]['anesthesia_code'];//获取到anes 码,根据id 取，肯定只有一个
       
        if($status=="active"){
           $data['status'] = '1';
        }else{
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
            $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();

            //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
            $providers = array();
            if($provider_data){
                $len = count($provider_data);
                    for($i=0;$i<$len;$i++){
                        $providers[$i] = $provider_data[$i]['id'];
                    }
            }
            
            $db_encounter = new Application_Model_DbTable_Encounter();
            $db = $db_encounter->getAdapter();
           //$where = $db->quoteInto('secondary_CPT_code_1=?',$anes_code).$db->quoteInto('and provider_id=?',$provider_id);
           $where = $db->quoteInto('(secondary_CPT_code_1=?',$anes_code).$db->quoteInto(' or secondary_CPT_code_2=?',$anes_code).$db->quoteInto(' or secondary_CPT_code_3=?',$anes_code).$db->quoteInto(' or secondary_CPT_code_4=?',$anes_code).$db->quoteInto(' or secondary_CPT_code_5=?',$anes_code).$db->quoteInto(' or secondary_CPT_code_6=?)',$anes_code);
           if($provider_id !=0){
                $where = $where . $db->quoteInto(' AND provider_id =?',$provider_id);
            }else {
                if ($del_provider_id != 0) {
                    $where = $where . $db->quoteInto(' AND provider_id =?',$del_provider_id);
                } else {
                    $where = $where . $db->quoteInto(' AND provider_id IN (?)',$providers);
                }
            }
           $result = $db_encounter->fetchAll($where)->toArray();
           //$result 为真说明有 claim 还在使用，那么就不能删除。
           if($result){
              $data['flag'] = '1';
              $len = count($result);
              $patientinfoArray = array();
              for($i=0;$i<$len;$i++){
                  $patientid = $result[$i]['patient_id'];
                  $providerid = $result[$i]['provider_id'];
                  $patientinfoArray[$i]['dos'] = $result[$i]['start_date_1'];

                  $db_patient = new Application_Model_DbTable_Patient();
                  $db = $db_patient->getAdapter();
                  $where = $db->quoteInto('id=?',$patientid);
                  $info = $db_patient->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['last_name'] = $info[0]['last_name'];
                  $patientinfoArray[$i]['first_name'] = $info[0]['first_name'];

                  $db_provider = new Application_Model_DbTable_Provider();
                  $db = $db_provider->getAdapter();
                  $where = $db->quoteInto('id=?',$providerid);
                  $info1 = $db_provider->fetchAll($where)->toArray();
                  $patientinfoArray[$i]['providername'] = $info1[0]['provider_name'];
              }
              $data['patientinfoArray'] = $patientinfoArray;
           } else {
                //可以删除，但是先查看 cpt 能不能删除
                $data['flag'] = '0';
                
                //$db_encounter->delete($where);
                $cptinfo = array();
                $db_cpt = new Application_Model_DbTable_Cptcode();
                $db_c = $db_cpt->getAdapter();
                $where_c = $db_c->quoteInto('provider_id in(?)', $providers) . $db_c->quoteInto('and anesthesiacode_id=?', $anesthesia_code_id);
                $result_cpt = $db_cpt->fetchAll($where_c)->toArray();
                if ($result_cpt) {
                    $data['relat_to_cpt'] = '1';
                    $len = count($result_cpt);
                    for ($i = 0; $i < $len; $i++) {
                        $cptinfo[$i]['cpt_code'] = $result_cpt[$i]['CPT_code'];
                        $cptinfo[$i]['description'] = $result_cpt[$i]['description'];
                    }
                    $data['cpt'] = $cptinfo;//返回不能删除的cpt,在其他地方更新
                } else {
                    //和cpt 无关，直接删除anesthesia
                        //处理处于inactive状态的数据
                        if($provider_id==0){
                             //说明是选择的ALL 模式
                             if($del_provider_id==0){
                                 //说明没有选择任何一个provider，需要检测后全部删除
                                  

                                $db_anes = new Application_Model_DbTable_Anesthesiacode();
                                $db_a = $db_anes->getAdapter();
                                $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id in(?)',$providers);
                                $db_anes->delete($where_a);
                             }else{
                                 //说明选择了某一个provider, 删除的时候，只需要更改该provider关联的facility
                                 $db_anes = new Application_Model_DbTable_Anesthesiacode();
                                 $db_a = $db_anes->getAdapter();
                                 $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id =?',$del_provider_id);
                                 $db_anes->delete($where_a);
                             }
                          }else{
                             //说明是需要具体处理某一个传过来的provider
                             $db_anes = new Application_Model_DbTable_Anesthesiacode();
                             $db_a = $db_anes->getAdapter();
                             $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id=?',$provider_id);

                             $db_anes->delete($where_a);
                         }
                }//end 可以删除
            }
        }



        $json = Zend_Json::encode($data);
       echo $json;
       
  }
  /**
   * by haoqiang
   */
  public function updatecptAction(){
      $this->_helper->viewRenderer->setNoRender();
        $provider_id = $this->_request->getPost('provider_id');
        $anesthesia_code_id = $this->_request->getPost('anesthesia_code_id'); //获取anesthesia_code
        $del_provider_id = $this->_request->getPost('del_provider_id');
        
        
        //先获取一下 anes_code, 一个anes_code 可能对应多个 anesthesia_code_id
        $db_anes = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_anes->getAdapter();
        $where = $db->quoteInto('id=?',$anesthesia_code_id);
        $result = $db_anes->fetchAll($where)->toArray();
        $anes_code = $result[0]['anesthesia_code'];//获取到anes 码,根据id 取，肯定只有一个
        
        
        //开始删除操作
        if($provider_id==0){
            //选择了ALL
            if($del_provider_id==0){
                //from 处选择了 ALL，删除要从整个公司都删除
                //1.从billingcompany获取都有哪些provider
                $billingcompany_id = $this->billingcompany_id();
                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();

                //获取所有provider_id,存到一个数组中
                $providers = array();
                if($provider_data){
                    $len = count($provider_data);
                    for($i=0;$i<$len;$i++){
                        $providers[$i] = $provider_data[$i]['id'];
                    }
                }
                
                $db_anes = new Application_Model_DbTable_Anesthesiacode();
                $db_a = $db_anes->getAdapter();
                $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id in(?)',$providers);//通过这句话，能获取到三条数据
                $result_1 = $db_anes->fetchAll($where_a)->toArray();
                
                $len_result_1 = count($result_1);
                //需要对每一条数据都在cpt 表中进行更新
                for ($i=0;$i<$len_result_1;$i++){
                    //具体进cpt 表中进行update操作
                    $db_cpt = new Application_Model_DbTable_Cptcode();
                    $db_c = $db_cpt->getAdapter();
                    $value = array("anesthesiacode_id"=>NULL,);
                    $where_c = $db_c->quoteInto('provider_id=?', $result_1[$i]['provider_id']) . $db_c->quoteInto('and anesthesiacode_id=?', $result_1[$i]['anesthesia_code_id']);
                    $result = $db_cpt->fetchAll($where_c)->toArray();

                    for($i=0;$i<count($result);$i++){
                        $cpt_id = $result[$i]['id'];
                        $where = $db_c->quoteInto('id=?',$cpt_id);//update 操作需要拿着id 去操作
                        $db_cpt->update($value, $where);
                    }
                }
                //更新完毕在对anesthesia表中的数据进行删除
                 for ($i=0;$i<$len_result_1;$i++){
                    $where_delete_a = $db_a->quoteInto('id=?',$result_1[$i][id]);
                    $db_anes->delete($where_delete_a);
                 }
                 
                $data['success'] = '1';//删除成功
                
            }else{
                //from 处选择了具体的某个del_provider
                //先获取一下对应anes_code 和 del_provider 确定的那个anesthesiacode 的id
                $db_anes = new Application_Model_DbTable_Anesthesiacode();
                $db_a = $db_anes->getAdapter();
                $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id=?',$del_provider_id);//通过这句话，能获取到三条数据
                $result_1 = $db_anes->fetchAll($where_a)->toArray();//只有一条数据
                $anesthesia_code_id_1 = $result_1[0]['id'];
                
                //1. 仍然是从cpt表中，更新相关字段
                $db_cpt = new Application_Model_DbTable_Cptcode();
                $db_c = $db_cpt->getAdapter();
                $value = array("anesthesiacode_id"=>NULL,);
                $where_c = $db_c->quoteInto('provider_id=?', $del_provider_id) . $db_c->quoteInto('and anesthesiacode_id=?', $anesthesia_code_id_1);
                $result = $db_cpt->fetchAll($where_c)->toArray();

                for($i=0;$i<count($result);$i++){
                    $cpt_id = $result[$i]['id'];
                    $where = $db_c->quoteInto('id=?',$cpt_id);
                    $db_cpt->update($value, $where);
                }
                //2. 删除具体内容
                //$db_anes = new Application_Model_DbTable_Anesthesiacode();
                //$db_a = $db_anes->getAdapter();
                $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto(' AND provider_id=?',$del_provider_id);
                if($db_anes->delete($where_a)){
                    $data['success'] = '1';
                }
            }
        }else{
            //整体只要encounter 里面没有数据，都是可以删除的，就看操作者愿不愿意删除
            //说明是需要具体处理某一个传过来的provider
            $db_anes = new Application_Model_DbTable_Anesthesiacode();
            $db_a = $db_anes->getAdapter();
            $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto('AND provider_id=?',$provider_id);//通过这句话，能获取到三条数据
            $result_1 = $db_anes->fetchAll($where_a)->toArray();//只有一条数据
            $anesthesia_code_id_1 = $result_1[0]['id'];

            //1. 仍然是从cpt表中，更新相关字段
            $db_cpt = new Application_Model_DbTable_Cptcode();
            $db_c = $db_cpt->getAdapter();
            $value = array("anesthesiacode_id"=>NULL,);
            $where_c = $db_c->quoteInto('provider_id=?', $del_provider_id) . $db_c->quoteInto('and anesthesiacode_id=?', $anesthesia_code_id_1);
            $result = $db_cpt->fetchAll($where_c)->toArray();

            for($i=0;$i<count($result);$i++){
                $cpt_id = $result[$i]['id'];
                $where = $db_c->quoteInto('id=?',$cpt_id);
                $db_cpt->update($value, $where);
            }
            //2. 删除具体内容
            $where_a = $db_a->quoteInto('anesthesia_code=?',$anes_code).$db_a->quoteInto(' AND provider_id=?',$provider_id);
            if($db_anes->delete($where_a)){
                $data['success'] = '1';
            }
        }

        $json = Zend_Json::encode($data);
        echo $json;
        
    }
  
  
    /**
     * facilityAction
     * a function for processing the facility data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function facilityAction() {
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasFac.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,facility_id FROM providerhasfacility has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasFac
ON hasFac.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p,facility fac
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.facility_id=fac.id and fac.facility_name <> "Need New Facility") 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p,facility fac
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' and hs.facility_id=fac.id and fac.facility_name <> "Need New Facility") 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumFac = array('num' => $all);
        $dosdb = new Application_Model_DbTable_Placeofservice();
        $db1 = $dosdb->getAdapter();
        $dos_list = $dosdb->fetchAll();
        $this->view->DOSlist = $dos_list;
        if ($this->getRequest()->isPost()) {
//            $provider_id = $this->getRequest()->getPost('provider_id');
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $billingcompany_id = $this->billingcompany_id();
                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
                $provider_id = $this->getRequest()->getPost('provider_id');
                $facility_id = $this->getRequest()->getPost('facility_id');
                $facility_display = $this->getRequest()->getPost('facility_display');
                $db_facility = new Application_Model_DbTable_Facility();
                $db = $db_facility->getAdapter();
                $where = $db->quoteInto('id <> ?', $facility_id) . $db->quoteInto('and facility_display = ?', $facility_display);
                $exist_facility = $db_facility->fetchAll($where)->toArray();
                if (isset($exist_facility[0])) {
                    $provder_ids = array();
                    $facility_ids = array();
                    for ($j = 0; $j < count($exist_facility); $j++) {
                        $facility_ids[$j] = $exist_facility[$j]['id'];
                    }
                    $db_hasexisting = new Application_Model_DbTable_Providerhasfacility();
                    $dbexsit = $db_hasexisting->getAdapter();
                    for ($i = 0; $i < count($provider_data); $i++) {
                        $provder_ids[$i] = $provider_data[$i]['id'];
                    }
                    $wherehasexisting = $dbexsit->quoteInto("facility_id in (?)", $facility_ids) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

                    $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                }
                if (isset($exsitlast[0])) {
                    echo '<span style="color:red;font-size:16px">Sorry, the name you entered exists already, please enter a different name</span>';
                } else {
                    $facility = array();
                    $facility['street_address'] = $this->getRequest()->getPost('street_address');
                    $facility['zip'] = $this->getRequest()->getPost('zip');
                    $facility['city'] = $this->getRequest()->getPost('city');
                    $facility['state'] = $this->getRequest()->getPost('state');
                    $facility['facility_display'] = $this->getRequest()->getPost('facility_display');
                    $facility['facility_name'] = $this->getRequest()->getPost('facility_name');
                    $facility_phone_numbe = $this->getRequest()->getPost('phone_number');
                    $facility_fax_number = $this->getRequest()->getPost('fax_number');
                    $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
                    $facility['phone_number'] = preg_replace($pattern, "\\1\\2\\3", $facility_phone_numbe);
                    $facility['fax_number'] = preg_replace($pattern, "\\1\\2\\3", $facility_fax_number);
                    $facility['POS'] = $this->getRequest()->getPost('POS');
                    //                $facility['phone_number'] = $this->getRequest()->getPost('phone_number');
                    //                $facility['fax_number'] = $this->getRequest()->getPost('fax_number');
                    $facility['service_doc_first_page'] = $this->getRequest()->getPost('service_doc_first_page');
                    $facility['service_doc_second_page'] = $this->getRequest()->getPost('service_doc_second_page');
                    $facility['service_doc_third_page'] = $this->getRequest()->getPost('service_doc_third_page');
                    $facility['service_doc_forth_page'] = $this->getRequest()->getPost('service_doc_forth_page');
                    $short_name = $this->getRequest()->getPost('short_name');
                    if (strlen($short_name) >= 8) {
                        $facility['short_name'] = substr($short_name, 0, 8);
                    } else {
                        $facility['short_name'] = $short_name;
                    }

                    $facility['NPI'] = $this->getRequest()->getPost('NPI');
                    $facility['notes'] = $this->getRequest()->getPost('notes');
                    $id = $this->getRequest()->getPost('facility_id');
                    $db_provider_id = new Application_Model_DbTable_Provider();
                    $db1 = $db_provider_id->getAdapter();
                    $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                    $provider_data1 = $db_provider_id->fetchAll($where_provider);
                    $db_facility = new Application_Model_DbTable_Facility();
                    $db = $db_facility->getAdapter();
                    $where = $db->quoteInto('id = ?', $id);
                    $curdata = $db_facility->fetchAll($where)->toArray();
                    $isupdat = $db_facility->update($facility, $where);
                    if ($isupdat) {
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.facility_name', $curdata[0]['facility_name'], $facility['facility_name']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.street_address', $curdata[0]['street_address'], $facility['street_address']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.zip', $curdata[0]['zip'], $facility['zip']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.city', $curdata[0]['city'], $facility['city']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.state', $curdata[0]['state'], $facility['state']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.phone_number', $curdata[0]['phone_number'], $facility['phone_number']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.fax_number', $curdata[0]['fax_number'], $facility['fax_number']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.service_doc_first_page', $curdata[0]['service_doc_first_page'], $facility['service_doc_first_page']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.service_doc_second_page', $curdata[0]['service_doc_second_page'], $facility['service_doc_second_page']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.service_doc_third_page', $curdata[0]['service_doc_third_page'], $facility['service_doc_third_page']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.service_doc_forth_page', $curdata[0]['service_doc_forth_page'], $facility['service_doc_forth_page']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.short_name', $curdata[0]['short_name'], $facility['short_name']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.NPI', $curdata[0]['NPI'], $facility['NPI']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.notes', $curdata[0]['notes'], $facility['notes']);
                        $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'facility.POS', $curdata[0]['POS'], $facility['POS']);
                    }

                    $facilityhas = array();
                    $facilityhas['status'] = $this->getRequest()->getPost('status');
                    $db_facilityhas = new Application_Model_DbTable_Providerhasfacility();
                    $dbhas = $db_facilityhas->getAdapter();
                    if ($provider_id == 0) {
                        $del_provider_id = $this->getRequest()->getPost('del_provider_id');
                        //                      $db_provider_id = new Application_Model_DbTable_Provider();
                        //                      $db1 = $db_provider_id->getAdapter();
                        //                      $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                        //                      $provider_data = $db_provider_id->fetchAll($where_provider);
                        if ($del_provider_id == 0) {
                            $db_provider = new Application_Model_DbTable_Provider();
                            $db = $db_provider->getAdapter();

                            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                            $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provider_id1 = $provider_data[$i]['id'];
                                $db_provider_id = new Application_Model_DbTable_Provider();
                                $db1 = $db_provider_id->getAdapter();
                                $where_provider = $db1->quoteInto('id = ?', $provider_id1); //
                                $provider_data1 = $db_provider_id->fetchAll($where_provider);
                                $wherehas = $db->quoteInto('provider_id= ?', $provider_id1) . $db->quoteInto('and facility_id=?', $facility_id);
                                $facilityhasexist = $db_facilityhas->fetchAll($wherehas);
                                if ($db_facilityhas->update($facilityhas, $wherehas)) {
                                    $this->adddatalog($provider_id1, $curdata[0]['facility_name'], 'providerhasfacility.status', $facilityhasexist[0]['status'], $facilityhas['status']);
                                }
                            }
                        } else {
                            $wherehas = $db->quoteInto('provider_id= ?', $del_provider_id) . $db->quoteInto('and facility_id=?', $facility_id);
                            $facilityhasexist = $db_facilityhas->fetchAll($wherehas)->toArray();
                            if ($db_facilityhas->update($facilityhas, $wherehas)) {

                                $this->adddatalog($del_provider_id, $curdata[0]['facility_name'], 'providerhasfacility.status', $facilityhasexist[0]['status'], $facilityhas['status']);
                            }
                        }
                    } else {
                        $wherehas = $db->quoteInto('provider_id= ?', $provider_id) . $db->quoteInto('and facility_id=?', $facility_id);
                        $facilityhasexist = $db_facilityhas->fetchAll($wherehas)->toArray();
                        ;
                        if ($db_facilityhas->update($facilityhas, $wherehas)) {
                            $db_provider_id = new Application_Model_DbTable_Provider();
                            $db1 = $db_provider_id->getAdapter();
                            $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                            $provider_data = $db_provider_id->fetchAll($where_provider);
                            $this->adddatalog($provider_id, $curdata[0]['facility_name'], 'providerhasfacility.status', $facilityhasexist[0]['status'], $facilityhas['status']);
                        }
                    }
                    /*                     * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
                    session_start();
                    $_SESSION['management_data']['provider_id'] = $provider_id;

                    $this->_redirect('/biller/data/facility');
                }
            }
            if ($submitType == "New") {
                $_SESSION['_provider_id_for_new'] = $this->getRequest()->getPost('provider_id');
                $this->_redirect('/biller/data/newfacility');
            }
            if ($submitType == "UPLOAD") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $facility_id = $this->getRequest()->getPost('facility_id');
                $desc = $this->getRequest()->getParam('desc');
                if ($desc == "" || $desc == null) {
                    $this->_redirect('/biller/data/facility');
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    if ($provider_id != "0") {
                        $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                        if (!is_dir($dir_billingcompany)) {
                            mkdir($dir_billingcompany);
                        }
                        $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                        if (!is_dir($dir_provider)) {
                            mkdir($dir_provider);
                        }
                        $dir_facility = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'facility';
                        if (!is_dir($dir_facility)) {
                            mkdir($dir_facility);
                        }
                        $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'facility/' . $facility_id;
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                        $today = date("Y-m-d H:i:s");
                        $date = explode(' ', $today);
                        $time0 = explode('-', $date[0]);
                        $time1 = explode(':', $date[1]);
                        $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $file_name = $time . '-' . $desc . '-' . $user_name;
                        $old_filename = $adapter->getFileName();
                        $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                        $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                        $adapter->setDestination($dir);
                        $db_facility = new Application_Model_DbTable_Facility();
                        $db = $db_facility->getAdapter();
                        $where = $db->quoteInto("id=?", $facility_id);
                        $facility_data = $db_facility->fetchRow($where);
                        $log_facility_name = $facility_data['facility_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        if (!$adapter->receive()) {
                            $messages = $adapter->getMessages();
                            echo implode("n", $messages);
                        } else {
                            $this->adddatalog($provider_id, $log_facility_name, $log_dbfield, null, $log_newvalue);
                            $this->_redirect('/biller/data/facility');
                        }
                    }
                    if ($provider_id == "0") {
                        $billingcompany_id = $this->billingcompany_id();
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();
                        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
                        $provider_list = $db_provider->fetchAll($where);
                        $count = 0;
                        $first_copy = "";
                        $first_copy_name = "";
                        $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                        if (!is_dir($dir_billingcompany)) {
                            mkdir($dir_billingcompany);
                        }
                        foreach ($provider_list as $provider) {
                            $provider_id = $provider_list[$count]['id'];
                            $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                            if (!is_dir($dir_provider)) {
                                mkdir($dir_provider);
                            }
                            $dir_facility = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'facility';
                            if (!is_dir($dir_facility)) {
                                mkdir($dir_facility);
                            }
                            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'facility/' . $facility_id;
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            if ($count == 0) {
                                $today = date("Y-m-d H:i:s");
                                $date = explode(' ', $today);
                                $time0 = explode('-', $date[0]);
                                $time1 = explode(':', $date[1]);
                                $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                                $user = Zend_Auth::getInstance()->getIdentity();
                                $user_name = $user->user_name;
                                $file_name = $time . '-' . $desc . '-' . $user_name;
                                $old_filename = $adapter->getFileName();
                                $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                                $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                                $adapter->setDestination($dir);
                                $adapter->receive();
                                $first_copy = $dir . '/' . $file_name . $file_extension;
                                $first_copy_name = $file_name . $file_extension;
                            } else {
                                $dest = $dir . '/' . $first_copy_name;
                                copy($first_copy, $dest);
                            }
                            $count++;
                        }
                        $db_facility = new Application_Model_DbTable_Facility();
                        $db = $db_facility->getAdapter();
                        $where = $db->quoteInto("id=?", $facility_id);
                        $facility_data = $db_facility->fetchRow($where);
                        $log_facility_name = $facility_data['facility_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        $this->adddatalog(0, $log_facility_name, $log_dbfield, null, $log_newvalue);
                        $this->_redirect('/biller/data/facility');
                    }
                }
            }
        }
    }

    /**
     * selectproviderAction
     * a function for select former provider that have selected before update under data management tools.
     * @author James.
     * @version 05/8/2015
     */
    public function selectproviderAction() {
        /*         * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
        $this->_helper->viewRenderer->setNoRender();

        session_start();
        $provider_id = $_SESSION['management_data']['provider_id'];
        $type = $_SESSION['management_data']['type'];
        $_SESSION['management_data'] = null;
        if ($provider_id != null) {
            $data = array();
            $data = array('provider_id' => $provider_id, 'type' => $type);
            $json = Zend_Json::encode($data);
            echo $json;
        }
    }

    public function facilityposinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $POS = $_POST['POS'];
        $db_pos = new Application_Model_DbTable_Placeofservice();
        $db = $db_pos->getAdapter();
        $where = $db->quoteInto('id = ?', $POS);
        $pos = $db_pos->fetchRow($where);

        $data = array();
        $data = array('id' => $pos['id'],
            'description' => $pos['description']);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newfacilityAction
     * a function for creating a new facility.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newfacilityAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasFac.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,facility_id FROM providerhasfacility has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasFac
ON hasFac.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p ,facility fac
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive'and hs.facility_id=fac.id and fac.facility_name <> "Need New Facility") 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasfacility has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p ,facility fac
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' and hs.facility_id=fac.id and fac.facility_name <> "Need New Facility") 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumFac = array('num' => $all);
        $dosdb = new Application_Model_DbTable_Placeofservice();
        $db1 = $dosdb->getAdapter();
        $dos_list = $dosdb->fetchAll();
        $this->view->DOSlist = $dos_list;
        $this->view->initProvider_id = '-1';
        if (isset($_SESSION['_provider_id_for_new'])) {
            $this->view->initProvider_id = $_SESSION['_provider_id_for_new'];
            unset($_SESSION['_provider_id_for_new']);
        }

        if ($this->getRequest()->isPost()) {
            $facility = array();
            $provierhasfacility = array();
            $facility['facility_display'] = $this->getRequest()->getPost('facility_display');
            $facility['facility_name'] = $this->getRequest()->getPost('facility_name');
            $facility['street_address'] = $this->getRequest()->getPost('street_address');
            $facility['zip'] = $this->getRequest()->getPost('zip');
            $facility['city'] = $this->getRequest()->getPost('city');
            $facility['state'] = $this->getRequest()->getPost('state');
            $facility_phone_numbe = $this->getRequest()->getPost('phone_number');
            $facility_fax_number = $this->getRequest()->getPost('fax_number');
            $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
            $facility['phone_number'] = preg_replace($pattern, "\\1\\2\\3", $facility_phone_numbe);
            $facility['fax_number'] = preg_replace($pattern, "\\1\\2\\3", $facility_fax_number);
            $facility['service_doc_first_page'] = $this->getRequest()->getPost('service_doc_first_page');
            $facility['service_doc_second_page'] = $this->getRequest()->getPost('service_doc_second_page');
            $facility['service_doc_third_page'] = $this->getRequest()->getPost('service_doc_third_page');
            $facility['service_doc_forth_page'] = $this->getRequest()->getPost('service_doc_forth_page');

            $POS = $this->getRequest()->getPost('POS');
            if ($POS != NULL && $POS != "") {
                $facility['POS'] = $POS;
            }
            $facility['notes'] = $this->getRequest()->getPost('notes');
            $short_name = $this->getRequest()->getPost('short_name');
            if (strlen($short_name) >= 8) {
                $facility['short_name'] = substr($short_name, 0, 8);
            } else {
                $facility['short_name'] = $short_name;
            }
            $facility['NPI'] = $this->getRequest()->getPost('NPI');
            $import_or_new = $this->getRequest()->getPost('import_or_new');

            if ($import_or_new == 'new') {

                $db_facility = new Application_Model_DbTable_Facility();
                $db = $db_facility->getAdapter();
                // $provierhasfacility['facility_id'] = $db_facility->insert($facility);
                //            $provierhasfacility['provider_id'] = $this->getRequest()->getPost('provider_id');
                $provider_id = $this->getRequest()->getPost('provider_id');
                $facility_name_get = $this->getRequest()->getPost('facility_display');
                $provierhasfacility = Array();
                $provierhasfacility['status'] = $this->getRequest()->getPost('status');

                if ($provider_id >= 0 && $facility_name_get != "") {

                    $where = $db->quoteInto("facility_display = ?", $facility_name_get);
                    $exsit = $db_facility->fetchAll($where)->toArray();
                    // $provderDatas=$provider_data->toArray();
                    $exsitlast = array();
                    if ($exsit[0]) {
                        $provder_ids = array();
                        $db_hasexisting = new Application_Model_DbTable_Providerhasfacility();
                        $dbexsit = $db_hasexisting->getAdapter();
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $provder_ids[$i] = $provider_data[$i]['id'];
                        }
                        $wherehasexisting = $dbexsit->quoteInto("facility_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

                        $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                    }
                    //if there exsit the diagnosiscode,get the diagnosiscode_id
                    if (isset($exsitlast[0])) {
                        //$provierhasfacility['facility_id']=$exsit[0]['id'];
                        echo '<span style="color:red;font-size:16px">Sorry ! The Facility Name is existing , please use Import !</span>';
                    }

                    //if there isn't the diagnosiscode, insert the diagnosiscode and get it's id
                    else {

                        $provierhasfacility['facility_id'] = $db_facility->insert($facility);
                        if ($provierhasfacility['facility_id']) {
                            $db_provider_id = new Application_Model_DbTable_Provider();
                            $db1 = $db_provider_id->getAdapter();
                            $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                            $provider_data1 = $db_provider_id->fetchAll($where_provider)->toArray();

                            //    $this->adddatalog($provider_data1[0]['provider_name'], $facility_name_get,'facility.provider_id',NULL,$facility['provider_id']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.facility_name', NULL, $facility['facility_name']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.street_address', NULL, $facility['street_address']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.zip', NULL, $facility['zip']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.state', NULL, $facility['state']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.city', NULL, $facility['city']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.phone_number', NULL, $facility['phone_number']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.fax_number', NULL, $facility['fax_number']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.service_doc_first_page', NULL, $facility['service_doc_first_page']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.service_doc_second_page', NULL, $facility['service_doc_second_page']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.service_doc_third_page', NULL, $facility['service_doc_third_page']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.service_doc_forth_page', NULL, $facility['service_doc_forth_page']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.short_name', NULL, $facility['short_name']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.NPI', NULL, $facility['NPI']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.notes', NULL, $facility['notes']);
                            $this->adddatalog($provider_id, $facility_name_get, 'facility.POS ', NULL, $facility['POS ']);
                        }

                        if ($provider_id != 0) {
                            $db_provider_id = new Application_Model_DbTable_Provider();
                            $db1 = $db_provider_id->getAdapter();
                            $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                            $provider_data1 = $db_provider_id->fetchAll($where_provider);
                            $provierhasfacility['provider_id'] = $provider_id;
                            $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
                            $db = $db_providerhasfacility->getAdapter();
                            $wherehas = $db->quoteInto("facility_id = ?", $provierhasfacility['facility_id']) . $db->quoteInto("and provider_id = ?", $provierhasfacility['provider_id']);
                            $exithas = $db_providerhasfacility->fetchAll($wherehas)->toArray();
                            if (!isset($exithas[0])) {
                                $insert_has_id = $db_providerhasfacility->insert($provierhasfacility);
                                if ($insert_has_id) {
                                    //$this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.facility_id',NULL,$provierhasfacility['facility_id']);
                                    //  $this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.provider_id',NULL,$provierhasfacility['provider_id']);
                                    $this->adddatalog($provider_id, $facility_name_get, 'providerhasfacility.status', NULL, $provierhasfacility['status']);
                                }
                            } else {
                                if ($db_providerhasfacility->update($provierhasfacility, $wherehas)) {
                                    $db_provider_id = new Application_Model_DbTable_Provider();
                                    $db1 = $db_provider_id->getAdapter();
                                    $where_provider = $db1->quoteInto('id = ?', $provider_id); //
                                    $provider_data1 = $db_provider_id->fetchAll($where_provider);
                                    $this->adddatalog($provider_id, $facility_name_get, 'providerhasfacility.status', $exithas[0]['status'], $provierhasfacility['status']);
                                }
                            }
                        } else {
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $db_provider_id = new Application_Model_DbTable_Provider();
                                $db1 = $db_provider_id->getAdapter();
                                $where_provider = $db1->quoteInto('id = ?', $provider_data[$i]['id']); //
                                $provider_data1 = $db_provider_id->fetchAll($where_provider);
                                $provierhasfacility['provider_id'] = $provider_data[$i]['id'];

                                $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
                                $db = $db_providerhasfacility->getAdapter();
                                $wherehas = $db->quoteInto("facility_id = ?", $provierhasfacility['facility_id']) . $db->quoteInto("and provider_id = ?", $provierhasfacility['provider_id']);
                                $exithas = $db_providerhasfacility->fetchAll($wherehas)->toArray();
                                if (!isset($exithas[0])) {
                                    $insert_has_id = $db_providerhasfacility->insert($provierhasfacility);
                                    if ($insert_has_id) {
                                        // $this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.facility_id',NULL,$provierhasfacility['facility_id']);
                                        //$this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.provider_id',NULL,$provierhasfacility['provider_id']);
                                        $this->adddatalog($provierhasfacility['provider_id'], $facility_name_get, 'providerhasfacility.status', NULL, $provierhasfacility['status']);
                                    }
                                } else {
                                    if ($db_providerhasfacility->update($provierhasfacility, $wherehas)) {
                                        $db_provider_id = new Application_Model_DbTable_Provider();
                                        $db1 = $db_provider_id->getAdapter();
                                        $where_provider = $db1->quoteInto('id = ?', $provider_data[$i]['id']); //
                                        $provider_data1 = $db_provider_id->fetchAll($where_provider);
                                        $this->adddatalog($provider_data[$i]['id'], $facility_name_get, 'providerhasfacility.status', $exithas[0]['status'], $provierhasfacility['status']);
                                    }
                                }
                            }
                        }
                        $this->_redirect('/biller/data/facility');
                    }
                }
            } else if ($import_or_new == 'import') {
                $provider = $this->getRequest()->getPost('provider_id');
                $add_facility_id = $this->getRequest()->getPost('add_facility_id');
                if ($provider == -1 || empty($add_facility_id)) {
                    // $this->_redirect('/biller/data/newfacility');
                    return;
                }
                // echo $provider.'fff';
                // echo $add_facility_id;
                // die();
                if ($provider != 0) {
                    $db_has = new Application_Model_DbTable_Providerhasfacility();
                    $db = $db_has->getAdapter();
                    $where = $db->quoteInto('provider_id = ?', $provider) . $db->quoteInto(' and facility_id = ?', $add_facility_id);
                    $ext = $db_has->fetchAll($where);
                    if (isset($ext[0])) {
                        // $this->_redirect('/biller/data/newfacility');
                    } else {
                        $providerhasfacility = array();
                        $providerhasfacility['provider_id'] = $provider;
                        $providerhasfacility['facility_id'] = $add_facility_id;
                        $providerhasfacility['status'] = $this->getRequest()->getPost('status');
                        //  $wherehas=$db->quoteInto("facility_id = ?",  $provierhasfacility['facility_id']).$db->quoteInto("and provider_id = ?",  $provierhasfacility['provider_id']);
                        // $exithas= $db_providerhasfacility->fetchAll($wherehas)->toArray();
                        $insert_has_id = $db_has->insert($providerhasfacility);
                        if ($insert_has_id) {
                            $db_facility = new Application_Model_DbTable_Facility();
                            $db1 = $db_facility->getAdapter();
                            $where_f = $db1->quoteInto('id = ?', $add_facility_id); //
                            $facil_data1 = $db_facility->fetchAll($where_f)->toArray();
                            //  $this->adddatalog($provider,$facil_data1[0]['facility_name'],'providerhasfacility.facility_id',NULL,$providerhasfacility['facility_id']);
                            // $this->adddatalog($provider,$facil_data1[0]['facility_name'],'providerhasfacility.provider_id',NULL,$providerhasfacility['provider_id']);
                            $this->adddatalog($provider, $facil_data1[0]['facility_name'], 'providerhasfacility.status', NULL, $providerhasfacility['status']);
                        }

                        // $this->_redirect('/biller/data/newfacility');
                    }
                } else {
                    for ($i = 0; $i < count($provider_data); $i++) {
                        $providerhasfacility = array();
                        $providerhasfacility['provider_id'] = $provider_data[$i]['id'];
                        $providerhasfacility['facility_id'] = $add_facility_id;
                        $providerhasfacility['status'] = $this->getRequest()->getPost('status');
                        $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
                        $db_one = $db_providerhasfacility->getAdapter();
                        $wherehas = $db_one->quoteInto("facility_id = ?", $add_facility_id) . $db_one->quoteInto("and provider_id = ?", $provider_data[$i]['id']);
                        $exithas = $db_providerhasfacility->fetchAll($wherehas)->toArray();
                        if (!isset($exithas[0])) {
                            $insert_has_id = $db_providerhasfacility->insert($providerhasfacility);
                            if ($insert_has_id) {
                                $db_facility = new Application_Model_DbTable_Facility();
                                $db1 = $db_facility->getAdapter();
                                $where_f = $db1->quoteInto('id = ?', $add_facility_id); //
                                $facil_data1 = $db_facility->fetchAll($where_f);
                                //$this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.facility_id',NULL,$providerhasfacility['facility_id']);
                                //  $this->adddatalog($provider_data1[0]['provider_name'],$facility_name_get,'providerhasfacility.provider_id',NULL,$providerhasfacility['provider_id']);
                                $this->adddatalog($provider_data[$i]['id'], $facil_data1[0]['facility_name'], 'providerhasfacility.status', NULL, $providerhasfacility['status']);
                            }
                        } else {
                            if ($db_providerhasfacility->update($providerhasfacility, $wherehas)) {
                                $db_facility = new Application_Model_DbTable_Facility();
                                $db1 = $db_facility->getAdapter();
                                $where_f = $db1->quoteInto('id = ?', $add_facility_id); //
                                $facil_data1 = $db_facility->fetchAll($where_f);
                                $this->adddatalog($provider_data1[$i]['id'], $facil_data1[0]['facility_name'], 'providerhasfacility.status', $exithas[0]['status'], $providerhasfacility['status']);
                            }
                        }
                    }
                }
                $this->_redirect('/biller/data/facility');
            }

//            $db_providerhasfacility->insert($provierhasfacility);
        }
    }

    /**
     * facilityLsitAction
     * a function to return the list of the facility
     *  @author dazhao
     * @version 15/09/2012
     */
    public function facilitylistAction() {

        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];

        if ($provider_id == 0) {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数


            $sql = <<<SQL
SELECT min(has.provider_id),has.facility_id FROM  providerhasfacility has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.facility_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $facilityhas_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.facility_id FROM  providerhasfacility has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.facility_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $facilityhas_data_active = $result->fetchAll();
            $facility = array();
            $index = 0;
            for ($i = 0; $i < count($facilityhas_data); $i++) {
                $facility[$index] = $facilityhas_data[$i]['facility_id'];
                $index++;
            }

            for ($k = 0; $k < count($facilityhas_data_active); $k++) {

                $facility[$index] = $facilityhas_data_active[$k]['facility_id'];
                $index++;
            }
        } else {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数


            $sql = <<<SQL
SELECT min(has.provider_id),has.facility_id FROM  providerhasfacility has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.facility_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $facilityhas_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.facility_id FROM  providerhasfacility has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.facility_id in 
(
SELECT t.id FROM 
(
    SELECT facility_id id ,count(*) counts, status FROM providerhasfacility hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY facility_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.facility_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $facilityhas_data_active = $result->fetchAll();
            $facilityall = array();
            $index = 0;
            for ($i = 0; $i < count($facilityhas_data); $i++) {
                $facilityall[$index] = $facilityhas_data[$i]['facility_id'];
                $index++;
            }

            for ($k = 0; $k < count($facilityhas_data_active); $k++) {

                $facilityall[$index] = $facilityhas_data_active[$k]['facility_id'];
                $index++;
            }

            $db_providerhasfacilitysingle = new Application_Model_DbTable_Providerhasfacility();
            $db = $db_providerhasfacilitysingle->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $provider_id); //
            $facilityhas_data = $db_providerhasfacilitysingle->fetchAll($where);

            $facility = array();

            for ($i = 0; $i < count($facilityhas_data); $i++) {
                $facility[$i] = $facilityhas_data[$i]['facility_id'];
            }
        }


        $db_ref = new Application_Model_DbTable_Facility();
        $db = $db_ref->getAdapter();
        if (empty($facility)) {
            $facility[0] = 0;
        }
        if (empty($facilityall)) {
            $facilityall[0] = 0;
        }
        $wheres = $db->quoteInto('id IN(?)', $facility) . $db->quoteInto('AND id NOT IN(?)', $facilityall); //
        $facility_data = $db_ref->fetchAll($wheres, 'facility_display ASC')->toArray();

        if ($provider_id == 0) {
            session_start();
            $_SESSION['facilityList'] = $facility_data;
        }
        $data = array();
        $data['facilityList'] = $facility_data;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * facilityAddLsitAction
     * a function to return the list of the facility can be added
     *  @author YueZhao
     * @version 28/07/2013
     */
    public function facilityaddlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        //$provider_id = $this->getRequest()->getPost('diagnosis_code');
        if (empty($provider_id) && $provider_id != '0') {
            $data = array();
            $exist_data = array();
            $data['exist_data'] = $exist_data;
            $json = Zend_Json::encode($data);
            echo $json;
            return;
        }
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();

        $sql = <<<SQL
   select min(provider_id),facility.id,facility_display
   from facility left join providerhasfacility on facility_id = facility.id
   where facility.id not in 
   (
        select facility_id from providerhasfacility where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   group by facility.id
   order by facility.facility_display
        

SQL;
        $paras = array($provider_id, $billingcompany_id);
        $result = $db->query($sql, $paras);

        $exist_data = $result->fetchAll();

        $data = array();
        if ($provider_id == '0') {

            session_start();
            $all_facility_data = $_SESSION['facilityList'];
            for ($mark = 0; $mark < count($all_facility_data); $mark ++) {
                $all_facility_id_List[$mark] = $all_facility_data[$mark]['id'];
            }
            $tp_exist_data = array();
            $addmark = 0;
            for ($mark = 0; $mark < count($exist_data); $mark ++) {
                if (!(in_array($exist_data[$mark]['id'], $all_facility_id_List))) {
//                    array_splice($exist_data, $mark, 1); 
                    $tp_exist_data[$addmark] = $exist_data[$mark];
                    $addmark ++;
                }
            }
            $data['exist_data'] = $tp_exist_data;
        } else {
            $data['exist_data'] = $exist_data;
        }

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * facilityexistingAction
     * a function to return the list of the facility can be added
     *  @author YueZhao
     * @version 28/07/2013
     */
    function facilityexistingAction() {
        $this->_helper->viewRenderer->setNoRender();

        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");

        $db_facility = new Application_Model_DbTable_Facility();
        $db = $db_facility->getAdapter();
        $facility_name_get = $this->getRequest()->getPost('facility_name');
        $where = $db->quoteInto("facility_display = ?", $facility_name_get);
        $exsit = $db_facility->fetchAll($where)->toArray();
        // $provderDatas=$provider_data->toArray();
        $exsitlast = array();
        if ($exsit[0]) {
            $provder_ids = array();
            $exsit_ids = array();
            for ($j = 0; $j < count($exsit); $j++) {
                $exsit_ids[$j] = $exsit[$j]['id'];
            }
            $db_hasexisting = new Application_Model_DbTable_Providerhasfacility();
            $dbexsit = $db_hasexisting->getAdapter();
            for ($i = 0; $i < count($provider_data); $i++) {
                $provder_ids[$i] = $provider_data[$i]['id'];
            }
            $wherehasexisting = $dbexsit->quoteInto("facility_id in (?)", $exsit_ids) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
        }
        $data = array();
        if (isset($exsitlast[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * paitentAction
     * a function returning the facility data for displaying on the page.
     * @author Haowei.
     * @return the facility data for displaying on the page
     * @version 05/15/2012
     */
    public function facilityinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $facility_id = $_POST['facility_id'];

        $db_facility = new Application_Model_DbTable_Facility();
        $db = $db_facility->getAdapter();
        $where = $db->quoteInto('id = ?', $facility_id); //

        $facility_data = $db_facility->fetchRow($where);
        $provider_id = $_POST['provider_id'];

        if ($provider_id != 0) {
            $facility_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/facility/' . $facility_id;
            $doc_paths = array();
            if (is_dir($facility_doc_path)) {
                foreach (glob($facility_doc_path . '/*.*') as $filename) {
                    array_push($doc_paths, $filename);
                }
            }
            $facility_doc_list = array();
            $i = 0;
            foreach ($doc_paths as $path) {
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
                $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $facility_doc_list[$i]['date'] = $date;
                $facility_doc_list[$i]['desc'] = $temp[1];
                $facility_doc_list[$i]['user'] = $temp[2];
                $count = count($temp);
                $n = 3;
                if ($count > 3) {
                    for ($n; $n < $count; $n++) {
                        $facility_doc_list[$i]['user'] = $facility_doc_list[$i]['user'] . '-' . $temp[$n];
                    }
                }
                $facility_doc_list[$i]['url'] = $path;
                $i++;
            }
        } else {
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
            $provider_list_temp = $db_provider->fetchAll($where);
            $facility_doc_list = array();
            $i = 0;
            $j = 0;
            foreach ($provider_list_temp as $provider_temp) {
                $provider_id_temp = $provider_temp['id'];
                $facility_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id_temp . '/facility/' . $facility_id;
                $doc_paths = array();
                if (is_dir($facility_doc_path)) {
                    foreach (glob($facility_doc_path . '/*.*') as $filename) {
                        array_push($doc_paths, $filename);
                    }
                }
                foreach ($doc_paths as $path) {
                    $temp = explode("/", $path);
                    $temp = explode(".", $temp[count($temp) - 1]);
                    $filename = $temp[0];
                    $temp = explode("-", $filename);
                    if ($j == 0) {
                        $facility_doc_list[$i] = $this->getDocPara($temp, $path);
                        $facility_doc_list[$i]['filename'] = $filename;
                        $i++;
                    } else {
                        $judge = 1;
                        foreach ($facility_doc_list as $facility_test) {
                            if ($facility_test['filename'] == $filename) {
                                $judge = 0;
                            }
                        }
                        if ($judge == 1) {
                            $facility_doc_list[$i] = $this->getDocPara($temp, $path);
                            $facility_doc_list[$i]['filename'] = $filename;
                            $i++;
                        }
                    }
                }
                $j++;
            }
        }

        if ($provider_id == 0) {
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
            $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
            $provider_id = $provider_data[0]['id'];
        }
        $db_providerhasfacility = new Application_Model_DbTable_Providerhasfacility();
        $db = $db_providerhasfacility->getAdapter();
        $wherehas = $db->quoteInto('facility_id = ?', $facility_id) . $db->quoteInto('and provider_id= ?', $provider_id);

        $providerhasfacility_data = $db_providerhasfacility->fetchRow($wherehas);
        $phone_number = phone($facility_data['phone_number']);
        $fax_number = phone($facility_data['fax_number']);

        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;

        $data = array('id' => $facility_data['id'], 'provider_id' => $providerhasfacility_data['provider_id'], 'zip' => $facility_data['zip'], 'fax_number' => $fax_number,
            'service_doc_first_page' => $facility_data['service_doc_first_page'], 'service_doc_second_page' => $facility_data['service_doc_second_page'],
            'service_doc_third_page' => $facility_data['service_doc_third_page'], 'service_doc_forth_page' => $facility_data['service_doc_forth_page'],
            'street_address' => $facility_data['street_address'], 'state' => $facility_data['state'], 'city' => $facility_data['city'], 'facility_name' => $facility_data['facility_name'],
            'phone_number' => $phone_number, 'NPI' => $facility_data['NPI'], 'description' => $pos['description'], 'POS' => $facility_data['POS'], 'short_name' => $facility_data['short_name'], 'notes' => $facility_data['notes'], 'status' => $providerhasfacility_data['status'], 'facility_doc_list' => $facility_doc_list, 'user_name' => $user_name);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * claimproviderAction
     * a function returning the option, and provider list.
     * @author Haowei.
     * @return the option and provider list
     * @version 05/15/2012
     */
    public function claimproviderAction() {
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompanyList = $db_billingcompany->fetchAll($where);

        $this->view->billingcompanyList = $billingcompanyList;

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $optionsList = $db_options->fetchAll(null, 'option_name ASC');
        $this->view->optionsList = $optionsList;

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $providerList = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $providerList;
    }

    /**
     * providerAction
     * a function for processing the provider data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function providerAction() {

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompanyList = $db_billingcompany->fetchAll($where);

        $this->view->billingcompanyList = $billingcompanyList;

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $providerList = $db_provider->fetchAll($where, 'provider_name ASC');

        $this->view->providerList = $providerList;

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $options_data = $db_options->fetchAll($where, 'option_name ASC');
        $this->view->optionsList = $options_data;

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $where = $db->quoteInto('billingcompany_id=?', $this->billingcompany_id());
        $insuranceList = $db_insurance->fetchAll($where, "insurance_display ASC")->toArray();
//         $this->deletenew($insuranceList,'insurance_name','Need New Insurance');
//     
        $this->view->insuranceList = $insuranceList;

        //added to support #29: Data Management tool enhancement for tags/james:start
        $billingcompany_id = $this->billingcompany_id();
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where);
        $provider_tags = $billingcompany_data['providertags'];
        $provider_tags_List = explode('|', $provider_tags);
        $mark = 0;
        foreach ($provider_tags_List as $row) {
            $tp_tags = explode('=', $row);
            $tag_names[$mark] = $tags[$mark]['tag_name'] = $tp_tags[0];
            $tags[$mark]['tag_type'] = $tp_tags[1];
            $mark ++;
        }
        session_start();
        $_SESSION['tags_in_billingcompany']['tags'] = $tags;
        $_SESSION['tags_in_billingcompany']['tag_names'] = $tag_names;
        $_SESSION['tags_in_billingcompany']['tag_count'] = $mark;
        //added to support #29: Data Management tool enhancement for tags/james:end

        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $provider = array();
                $provider_id = $this->getRequest()->getPost('provider_id');
                $provider['street_address'] = $this->getRequest()->getPost('street_address');
                $provider['zip'] = $this->getRequest()->getPost('zip');
                $provider['id1'] = $this->getRequest()->getPost('id1');
                $provider['id2'] = $this->getRequest()->getPost('id2');
                $provider['taxonomy_code'] = $this->getRequest()->getPost('taxonomy_code');
                $provider['city'] = $this->getRequest()->getPost('city');
                $provider['state'] = $this->getRequest()->getPost('state');
                $provider['phone_number'] = $this->getRequest()->getPost('phone_number');
                $provider['fax_number'] = $this->getRequest()->getPost('fax_number');
                $provider['secondary_phone_number'] = $this->getRequest()->getPost('secondary_phone_number');
                //  $provider['fax_number'] = $this->getRequest()->getPost('fax_number');
                $provider['email_address'] = $this->getRequest()->getPost('email_address');
                $provider['tax_ID_number'] = $this->getRequest()->getPost('tax_ID_number');
                $provider['billing_provider_name'] = $this->getRequest()->getPost('billing_provider_name');
                $provider['billing_provider_NPI'] = $this->getRequest()->getPost('billing_provider_NPI');
                $provider['billing_street_address'] = $this->getRequest()->getPost('billing_street_address');
                $provider['billing_city'] = $this->getRequest()->getPost('billing_city');
                $provider['billing_state'] = $this->getRequest()->getPost('billing_state');
                $provider['billing_zip'] = $this->getRequest()->getPost('billing_zip');
                $provider['billing_email'] = $this->getRequest()->getPost('billing_email');
                $provider['billing_phone_number'] = $this->getRequest()->getPost('billing_phone_number');
                $provider['billing_fax'] = $this->getRequest()->getPost('billing_fax');
                $provider['options_id'] = $this->getRequest()->getPost('options_id');
                $provider['billingcompany_id'] = $this->billingcompany_id();
                $provider['notes'] = $this->getRequest()->getPost('notes');
                $short_name = $this->getRequest()->getPost('short_name');

                //added to support #29: Data Management tool enhancement for tags/james:start
                session_start();
                $tp_tags = $_SESSION['tags_in_billingcompany']['tags'];
                $tags_from_page = "";
                $loop_mark = 0;
                $tags_size = count($tp_tags);
                foreach ($tp_tags as $row) {
                    $loop_mark ++;
                    $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                    if ($row[tag_type] == "binary") {
                        if ($tp_value_from_page == "yes") {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                            }
                        }
                    } else if ($row[tag_type] == "other") {
                        if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                            }
                        }
                    }
                }
                if (substr($tags_from_page, -1) == "|") {
                    $tags_from_page = substr($tags_from_page, 0, -1);
                }
                //added to support #29: Data Management tool enhancement for tags/james:end
                $provider['tags'] = $tags_from_page;

                if (strlen($short_name) >= 8) {
                    $provider['short_name'] = substr($short_name, 0, 8);
                } else {
                    $provider['short_name'] = $short_name;
                }

                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto('id = ?', $provider_id);
                // $oldprovider[0]=$db_provider->geta
                //   $where = $db->quoteInto('id = ?', $provider_id);
                $oldprovider = $db_provider->fetchAll($where)->toArray();

                if ($db_provider->update($provider, $where)) {
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.street_address', $oldprovider[0]['street_address'], $provider['street_address']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.zip', $oldprovider[0]['zip'], $provider['zip']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.city', $oldprovider[0]['city'], $provider['city']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.state', $oldprovider[0]['state'], $provider['state']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.phone_number', $oldprovider[0]['phone_number'], $provider['phone_number']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.fax_number', $oldprovider[0]['fax_number'], $provider['fax_number']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.secondary_phone_number', $oldprovider[0]['secondary_phone_number'], $provider['secondary_phone_number']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.email_address', $oldprovider[0]['email_address'], $provider['email_address']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.tax_ID_number', $oldprovider[0]['tax_ID_number'], $provider['tax_ID_number']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_provider_name', $oldprovider[0]['billing_provider_name'], $provider['billing_provider_name']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_provider_NPI', $oldprovider[0]['billing_provider_NPI'], $provider['billing_provider_NPI']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_street_address', $oldprovider[0]['billing_street_address'], $provider['billing_street_address']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_city', $oldprovider[0]['billing_city'], $provider['billing_city']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_state', $oldprovider[0]['billing_state'], $provider['billing_state']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_zip', $oldprovider[0]['billing_zip'], $provider['billing_zip']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_email', $oldprovider[0]['billing_email'], $provider['billing_email']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_phone_number', $oldprovider[0]['billing_phone_number'], $provider['billing_phone_number']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billing_fax', $oldprovider[0]['billing_fax'], $provider['billing_fax']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.options_id', $oldprovider[0]['options_id'], $provider['options_id']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.billingcompany_id', $oldprovider[0]['billingcompany_id'], $provider['billingcompany_id']);
                    $this->adddatalog($provider_id, $oldprovider[0]['provider_name'], 'provider.notes', $oldprovider[0]['notes'], $provider['notes']);
//                   $this->adddatalog('provider.street_address',$oldprovider[0][0]['street_address'],$provider['street_address']);
//                   $this->adddatalog('provider.street_address',$oldprovider[0][0]['street_address'],$provider['street_address']);
                }
//                $db_provider->update($provider, $where);
                $contractrates = array();
                $contractrates['provider_id'] = $this->getRequest()->getPost('provider_id');
                $contractrates['insurance_id'] = $this->getRequest()->getPost('payer_id');
                $contractrates['rates'] = $this->getRequest()->getPost('rates');
                $db_insurance = new Application_Model_DbTable_Insurance();
                $dbinsurance = $db_insurance->getAdapter();
                $whereinsurance = $dbinsurance->quoteInto('id = ? ', $contractrates['insurance_id']);
                $insuranceList = $db_insurance->fetchAll($whereinsurance)->toArray();

                $db_contractrates = new Application_Model_DbTable_Contractrates();

                $db1 = $db_contractrates->getAdapter();
                $wherecontractrates = $db1->quoteInto('provider_id = ?', $provider_id) . $db1->quoteInto("and insurance_id=?", $contractrates['insurance_id']);

                $oldcontract = $db_contractrates->fetchAll($wherecontractrates)->toArray();
                try {
                    if ($db_contractrates->update($contractrates, $wherecontractrates)) {
                        $this->adddatalog($provider_id, $insuranceList[0]['insurance_display'], 'contractrates.notes', $oldcontract[0]['rates'], $contractrates['rates']);
                    }
                } catch (Exception $e) {
                    echo 'Message: ' . $e->getMessage();
                }
                $this->_redirect('/biller/data/provider');
            }
            if ($submitType == "Delete") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                $db = $db_providerhasrenderingprovider->getAdapter();
                $where = $db->quoteInto('provider_id = ?', $provider_id);
                $oldprovider = $db_providerhasrenderingprovider->fetchAll($where)->toArray();
                if ($oldprovider[0])
                    if ($db_providerhasrenderingprovider->delete($where)) {
                        /* $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.street_address',$oldprovider[0]['provider_name'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.street_address',$oldprovider[0]['street_address'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.zip',$oldprovider[0]['zip'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.city',$oldprovider[0]['city'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.state',$oldprovider[0]['state'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.phone_number',$oldprovider[0]['phone_number'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.fax_number',$oldprovider[0]['fax_number'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.secondary_phone_number',$oldprovider[0]['secondary_phone_number'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.email_address',$oldprovider[0]['email_address'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.tax_ID_number',$oldprovider[0]['tax_ID_number'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_provider_name',$oldprovider[0]['billing_provider_name'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_provider_NPI',$oldprovider[0]['billing_provider_NPI'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_street_address',$oldprovider[0]['billing_street_address'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_city',$oldprovider[0]['billing_city'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_state',$oldprovider[0]['billing_state'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_zip',$oldprovider[0]['billing_zip'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_email',$oldprovider[0]['billing_email'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_phone_number',$oldprovider[0]['billing_phone_number'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billing_fax',$oldprovider[0]['billing_fax'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.options_id',$oldprovider[0]['options_id'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.billingcompany_id',$oldprovider[0]['billingcompany_id'],NULL);
                          $this->adddatalog($oldprovider[0]['provider_name'],$oldprovider[0]['provider_name'],'provider.notes',$oldprovider[0]['notes'],NULL); */
                    }

                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto('id = ?', $provider_id);
                $db_provider->delete($where);
                $this->_redirect('/biller/data/provider');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newprovider');
            if ($submitType == "Add Payer") {
                $contractrates = array();
                $contractrates['provider_id'] = $this->getRequest()->getPost('provider_id');
                $contractrates['insurance_id'] = $this->getRequest()->getPost('insurance_id');
                $contractrates['rates'] = $this->getRequest()->getPost('rates');

                $db_insurance = new Application_Model_DbTable_Insurance();
                $dbinsurance = $db_insurance->getAdapter();
                $whereinsurance = $dbinsurance->quoteInto('id = ? ', $contractrates['insurance_id']);
                $insuranceList = $db_insurance->fetchAll($whereinsurance)->toArray();

                $db_contractrates = new Application_Model_DbTable_Contractrates();
                $db = $db_contractrates->getAdapter();
                try {
                    if ($db_contractrates->insert($contractrates)) {
                        $this->adddatalog($contractrates['provider_id'], $insuranceList[0]['insurance_display'], 'contractrates.rotes', NULL, $contractrates['rates']);
                    };
                } catch (Exception $e) {
                    echo 'Message: ' . $e->getMessage();
                }
                $this->_redirect('/biller/data/provider');
            }
            if ($submitType == "Delete Payer") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $insurance_id = $this->getRequest()->getPost('payer_id');



                $db_insurance = new Application_Model_DbTable_Insurance();
                $dbinsurance = $db_insurance->getAdapter();
                $whereinsurance = $dbinsurance->quoteInto('id = ? ', $insurance_id);
                $insuranceList = $db_insurance->fetchAll($whereinsurance)->toArray();

                $db_contractrates = new Application_Model_DbTable_Contractrates();
                $db = $db_contractrates->getAdapter();

                $quote = 'provider_id=' . $provider_id . ' AND ' . 'insurance_id=' . $insurance_id;
                $where = $db->quoteInto($quote);
                $oldcontractrates = $db_contractrates->fetchAll($where)->toArray();
                try {
                    if ($db_contractrates->delete($where)) {
                        $this->adddatalog($provider_id, $insuranceList[0]['insurance_display'], 'contractrates.rotes', $oldcontractrates[0]['rates'], NULL);
                    }
                } catch (Exception $e) {
                    echo 'Message: ' . $e->getMessage();
                }
                $this->_redirect('/biller/data/provider');
            }
            if ($submitType == "UPLOAD") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $desc = $this->getRequest()->getParam('desc');
                if ($desc == "" || $desc == null) {
                    $this->_redirect('/biller/data/provider');
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                    if (!is_dir($dir_billingcompany)) {
                        mkdir($dir_billingcompany);
                    }
                    $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                    if (!is_dir($dir_provider)) {
                        mkdir($dir_provider);
                    }
                    $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'doc';
                    if (!is_dir($dir)) {
                        mkdir($dir);
                    }
                    $today = date("Y-m-d H:i:s");
                    $date = explode(' ', $today);
                    $time0 = explode('-', $date[0]);
                    $time1 = explode(':', $date[1]);
                    $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                    $user = Zend_Auth::getInstance()->getIdentity();
                    $user_name = $user->user_name;
                    $file_name = $time . '-' . $desc . '-' . $user_name;
                    $old_filename = $adapter->getFileName();
                    $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                    $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                    $adapter->setDestination($dir);
                    $db_provider = new Application_Model_DbTable_Provider();
                    $db = $db_provider->getAdapter();
                    $where = $db->quoteInto("id = ?", $provider_id);
                    $provider_data = $db_provider->fetchRow($where);
                    $log_provider_name = $provider_data['provider_name'];
                    $log_provider_id = $provider_id;
                    $log_db_field = $desc;
                    $log_newvalue = "UPLOAD";
                    if (!$adapter->receive()) {
                        $messages = $adapter->getMessages();
                        echo implode("n", $messages);
                    } else {
                        $this->adddatalog($log_provider_id, $log_provider_name, $log_db_field, null, $log_newvalue);
                        $this->_redirect('/biller/data/provider');
                    }
                }
            }
        }
    }

    /**
     * paitentAction
     * a function for creating a new provider.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newproviderAction() {

        //added to support #29: Data Management tool enhancement for tags/james
        session_start();
        $tags = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_counts = $_SESSION['tags_in_billingcompany']['tag_count'];
        $this->view->tagList = $tags;
        $this->view->tagNames = $tag_names;
        $this->view->tagNumber = $tag_counts;

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompanyList = $db_billingcompany->fetchAll($where);
        $this->view->billingcompanyList = $billingcompanyList;

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $providerList = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $providerList;

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $options_data = $db_options->fetchAll($where, 'option_name ASC');
        $this->view->optionsList = $options_data;

        if ($this->getRequest()->isPost()) {
            $provider = array();
            $provider['provider_name'] = $this->getRequest()->getPost('provider_name');
            $provider['street_address'] = $this->getRequest()->getPost('street_address');
            $provider['zip'] = $this->getRequest()->getPost('zip');
            $provider['city'] = $this->getRequest()->getPost('city');
            $provider['id1'] = $this->getRequest()->getPost('id1');
            $provider['id2'] = $this->getRequest()->getPost('id2');
            $provider['taxonomy_code'] = $this->getRequest()->getPost('taxonomy_code');
            $provider['state'] = $this->getRequest()->getPost('state');
            $provider['phone_number'] = $this->getRequest()->getPost('phone_number');
            $provider['fax_number'] = $this->getRequest()->getPost('fax_number');
            $provider['secondary_phone_number'] = $this->getRequest()->getPost('secondary_phone_number');
            $provider['fax_number'] = $this->getRequest()->getPost('fax_number');
            $provider['email_address'] = $this->getRequest()->getPost('email_address');
            $provider['tax_ID_number'] = $this->getRequest()->getPost('tax_ID_number');
            $provider['billing_provider_name'] = $this->getRequest()->getPost('billing_provider_name');
            $provider['billing_provider_NPI'] = $this->getRequest()->getPost('billing_provider_NPI');
            $provider['billing_street_address'] = $this->getRequest()->getPost('billing_street_address');
            $provider['billing_city'] = $this->getRequest()->getPost('billing_city');
            $provider['billing_state'] = $this->getRequest()->getPost('billing_state');
            $provider['billing_zip'] = $this->getRequest()->getPost('billing_zip');
            $provider['billing_email'] = $this->getRequest()->getPost('billing_email');
            $provider['billing_phone_number'] = $this->getRequest()->getPost('billing_phone_number');
            $provider['billing_fax'] = $this->getRequest()->getPost('billing_fax');
            $provider['options_id'] = $this->getRequest()->getPost('options_id');
            $provider['billingcompany_id'] = $this->billingcompany_id();
            $provider['notes'] = $this->getRequest()->getPost('notes');
            //added to support #29: Data Management tool enhancement for tags/james:start
            $tags_from_page = "";
            $loop_mark = 0;
            $tags_size = count($tags);
            foreach ($tags as $row) {
                $loop_mark ++;
                $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                if ($row[tag_type] == "binary") {
                    if ($tp_value_from_page == "yes") {
                        if ($loop_mark != $tags_size) {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                        } else {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                        }
                    }
                } else if ($row[tag_type] == "other") {
                    if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                        if ($loop_mark != $tags_size) {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                        } else {
                            $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                        }
                    }
                }
            }
            if (substr($tags_from_page, -1) == "|") {
                $tags_from_page = substr($tags_from_page, 0, -1);
            }
            //added to support #29: Data Management tool enhancement for tags/james:end
            $provider['tags'] = $tags_from_page;
            $short_name = $this->getRequest()->getPost('short_name');
            if (strlen($short_name) >= 8) {
                $provider['short_name'] = substr($short_name, 0, 8);
            } else {
                $provider['short_name'] = $short_name;
            }

            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $db_provider->insert($provider);

            $this->_redirect('/biller/data/provider');
        }
    }

    /**
     * providerinfoAction
     * a function returning the provider data for displaying on the page.
     * @author Haowei.
     * @return the provider data for displaying on the page
     * @version 05/15/2012
     */
    public function providerinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id = ?', $provider_id);
        $provider_data = $db_provider->fetchRow($where);

        //added to support #29 provider tags extend work:james/start
        $exist_tags = $provider_data['tags'];
        $exist_tags_List = explode('|', $exist_tags);
        $mark_exists = 0;
        foreach ($exist_tags_List as $row) {
            $tp_exist_tags = explode('=', $row);
            $exist_tag_names[$mark_exists] = $tags[$mark_exists]['tag_name'] = $tp_exist_tags[0];
            $tags[$mark_exists]['tag_type'] = $tp_exist_tags[1];
            $mark_exists ++;
        }
        $tag_count_exists = $mark_exists;

        session_start();
        $tags_in_billingcompany = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_count_in_billingcompany = $_SESSION['tags_in_billingcompany']['tag_count'];
        //added to support #29 provider tags extend work:james/end

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('provider');
        $select->join('contractrates', 'provider.id = contractrates.provider_id');
        $select->join('insurance', 'contractrates.insurance_id = insurance.id', array('insurance.id as insurance_id', 'insurance_display'));
        $select->group('insurance.id');
        $select->where('provider.id=?', $provider_id);
        $select->order('insurance_display ASC');
        $innetworkpayersList = $db->fetchAll($select);

        $provider_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/doc';
        $doc_paths = array();
        if (is_dir($provider_doc_path)) {
            foreach (glob($provider_doc_path . '/*.*') as $filename) {
                array_push($doc_paths, $filename);
            }
        }
        $provider_doc_list = array();
        $i = 0;
        foreach ($doc_paths as $path) {
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
            $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
            $provider_doc_list[$i]['date'] = $date;
            $provider_doc_list[$i]['desc'] = $temp[1];
            $provider_doc_list[$i]['user'] = $temp[2];
            $count = count($temp);
            $n = 3;
            if ($count > 3) {
                for ($n; $n < $count; $n++) {
                    $provider_doc_list[$i]['user'] = $provider_doc_list[$i]['user'] . '-' . $temp[$n];
                }
            }
            $provider_doc_list[$i]['url'] = $path;
            $i++;
        }

        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;

        $data = array();
        $data = array('innetworkpayersList' => $innetworkpayersList, 'id' => $provider_data['id'], 'zip' => $provider_data['zip'], 'fax_number' => $provider_data['fax_number'],
            'street_address' => $provider_data['street_address'], 'state' => $provider_data['state'], 'city' => $provider_data['city'],
            'secondary_phone_number' => $provider_data['secondary_phone_number'], 'fax_number' => $provider_data['fax_number'], 'email_address' => $provider_data['email_address'],
            'tax_ID_number' => $provider_data['tax_ID_number'], 'billing_provider_name' => $provider_data['billing_provider_name'], 'billing_provider_NPI' => $provider_data['billing_provider_NPI'],
            'billing_street_address' => $provider_data['billing_street_address'], 'billing_city' => $provider_data['billing_city'], 'billing_state' => $provider_data['billing_state'],
            'billing_zip' => $provider_data['billing_zip'], 'billing_email' => $provider_data['billing_email'], 'billing_phone_number' => $provider_data['billing_phone_number'],
            'billing_fax' => $provider_data['billing_fax'], 'short_name' => $provider_data['short_name'], 'id1' => $provider_data['id1'], 'id2' => $provider_data['id2'], 'taxonomy_code' => $provider_data['taxonomy_code'],
            'phone_number' => $provider_data['phone_number'], 'notes' => $provider_data['notes'], 'options_id' => $provider_data['options_id'], 'billingcompany_id' => $provider_data['billingcompany_id'],
            'provider_doc_list' => $provider_doc_list, 'user_name' => $user_name, 'tags' => $tags, 'tags_in_billingcompany' => $tags_in_billingcompany, 'tag_names' => $tag_names, 'tag_number' => $tag_count_in_billingcompany,
            'exist_tag_number' => $tag_count_exists, 'exist_tag_names' => $exist_tag_names);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * ratesAction
     * a function returning the bill rete.
     * @author Haowei.
     * @return the bill rate for computing the charge
     * @version 05/15/2012
     */
    public function ratesAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $payer_id = $_POST['payer_id'];

        $db_contractrates = new Application_Model_DbTable_Contractrates();
        $db = $db_contractrates->getAdapter();
        $quote = 'provider_id=' . $provider_id . ' AND ' . 'insurance_id=' . $payer_id;
        $where = $db->quoteInto($quote);
        $contractrates_data = $db_contractrates->fetchRow($where);

        $data = array();
        $data = array('rates' => $contractrates_data['rates']);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function defaultratesAction() {
        $this->_helper->viewRenderer->setNoRender();
        $insurance_id = $_POST['insurance_id'];
        $provider_id = $_POST['provider_id'];

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();
        $quote = 'id=' . $insurance_id;
        $where = $db->quoteInto($quote);
        $insurance_data = $db_insurance->fetchRow($where);
        $payertype = $insurance_data['payer_type'];

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $quote = 'id=' . $provider_id;
        $where = $db->quoteInto($quote);
        $provider_data = $db_provider->fetchRow($where);
        $options_id = $provider_data['options_id'];

        $db_options = new Application_Model_DbTable_Options();
        $db = $db_options->getAdapter();
        $quote = 'id=' . $options_id;
        $where = $db->quoteInto($quote);
        $options_data = $db_options->fetchRow($where);
        $default_rate_set = $options_data['default_pay_rate'];

        $default_rates = explode('|', $default_rate_set);
        $index = 0;
        $default_rate_spilt = array();
        for ($index = 0; $index < 5; $index++) {
            $rate_temp = $default_rates[$index];
            $temp = explode(':', $rate_temp);
            $default_rate_spilt[$temp[0]] = $temp[1];
        }
        $default_rate = $default_rate_spilt[$payertype];

        $data = array();
        $data = array('rates' => $default_rate);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * claimrenderingproviderAction
     * a function returning the renderingprovider list.
     * @author Haowei.
     * @return the renderingprovider list
     * @version 05/15/2012
     */
    public function claimrenderingproviderAction() {
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRend.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,renderingprovider_id FROM providerhasrenderingprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRend
ON hasRend.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasrenderingprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive') 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasrenderingprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active') 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumRend = array('num' => $all);
    }

    /**
     * renderingproviderAction
     * a function for processing the renderingprovider.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function renderingproviderAction() {
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRend.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,renderingprovider_id FROM providerhasrenderingprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRend
ON hasRend.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;

        session_start();
        $_SESSION['provider_data'] = $provider_data;

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数


        $sql = <<<SQL
SELECT min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.renderingprovider_id 

SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $providerhasrender_data = $result->fetchAll();
        $sql = <<<SQL
SELECT  min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.renderingprovider_id 

SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $providerhasrender_data_active = $result->fetchAll();
        $renderring = array();
        $index = 0;
        for ($i = 0; $i < count($providerhasrender_data); $i++) {
            $renderring[$index] = $providerhasrender_data[$i]['renderingprovider_id'];
            $index++;
        }

        for ($k = 0; $k < count($providerhasrender_data_active); $k++) {

            $renderring[$index] = $providerhasrender_data_active[$k]['renderingprovider_id'];
            $index++;
        }

        $db_ref = new Application_Model_DbTable_Renderingprovider();
        $db = $db_ref->getAdapter();
        if (empty($renderring)) {
            $renderring[0] = 0;
        }
        if (empty($renderringall)) {
            $renderringall[0] = 0;
        }
        $wheres = $db->quoteInto('id IN(?)', $renderring) . $db->quoteInto('and id not IN(?)', $renderringall); //
        // $where = $db->order('last_name');
        $rendering_all_providerList = $db_ref->fetchAll($wheres, 'last_name ASC', 'first_name ASC')->toArray();
        $all = count($rendering_all_providerList);
//        $all = $alldata + $alldata1;
        $this->view->allNumRend = array('num' => $all);

        session_start();
        $_SESSION['rendering_all_providerList'] = $rendering_all_providerList;

        //change to debug for #31:james:end

        $db_insurance = new Application_Model_DbTable_Insurance();
        $db = $db_insurance->getAdapter();

        $where = $db->quoteInto('payer_type = ?', 'MM') . $db->quoteInto('and billingcompany_id = ?', $billingcompany_id);
        $insuranceList = $db_insurance->fetchAll($where, 'insurance_display ASC')->toArray();
        //$this->deletenew($insuranceList,'insurance_name','Need New Insurance');
        $this->view->insuranceList = $insuranceList;
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        $salutations = $billingcompany_data['salutations'];

        $salutationsList = explode('|', $salutations);
        $enth = strlen($salutationsList);
        if ($salutationsList[0] == null && strlen($salutationsList) == 0) {
            $salutationsList = null;
        }
        $this->view->SalutationsList = $salutationsList;

        //added to support #29: Data Management tool enhancement for tags/james:start
        $billingcompany_id = $this->billingcompany_id();
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where);
        $renderingprovider_tags = $billingcompany_data['renderingprovidertags'];
        $provider_tags_List = explode('|', $renderingprovider_tags);
        $mark = 0;
        foreach ($provider_tags_List as $row) {
            $tp_tags = explode('=', $row);
            $tag_names[$mark] = $tags[$mark]['tag_name'] = $tp_tags[0];
            $tags[$mark]['tag_type'] = $tp_tags[1];
            $mark ++;
        }
        session_start();
        $_SESSION['tags_in_billingcompany']['tags'] = $tags;
        $_SESSION['tags_in_billingcompany']['tag_names'] = $tag_names;
        $_SESSION['tags_in_billingcompany']['tag_count'] = $mark;
        //added to support #29: Data Management tool enhancement for tags/james:end


        if ($this->getRequest()->isPost()) {
            $id = $this->getRequest()->getPost('rendering_provider_id');
            $provider_id = $this->getRequest()->getPost('provider_id');
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $renderingprovider = array();
                $id = $this->getRequest()->getPost('rendering_provider_id');
                $provider_id = $this->getRequest()->getPost('provider_id');

                session_start();
                $_SESSION['management_data']['provider_id'] = $provider_id;

                $renderingprovider['street_address'] = $this->getRequest()->getPost('street_address');
                $renderingprovider['zip'] = $this->getRequest()->getPost('zip');
                $renderingprovider['city'] = $this->getRequest()->getPost('city');
                $renderingprovider['NPI'] = $this->getRequest()->getPost('NPI');
                $renderingprovider['salutation'] = $this->getRequest()->getPost('salutation');
                $renderingprovider['state'] = $this->getRequest()->getPost('state');
                $renderingprovider['id1'] = $this->getRequest()->getPost('id1');
                $renderingprovider['id2'] = $this->getRequest()->getPost('id2');

                //added to support #29: Data Management tool enhancement for tags/james:start
                session_start();
                $tp_tags = $_SESSION['tags_in_billingcompany']['tags'];
                $tags_from_page = "";
                $loop_mark = 0;
                $tags_size = count($tp_tags);
                foreach ($tp_tags as $row) {
                    $loop_mark ++;
                    $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                    if ($row[tag_type] == "binary") {
                        if ($tp_value_from_page == "yes") {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                            }
                        }
                    } else if ($row[tag_type] == "other") {
                        if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                            }
                        }
                    }
                }
                if (substr($tags_from_page, -1) == "|") {
                    $tags_from_page = substr($tags_from_page, 0, -1);
                }
                //added to support #29: Data Management tool enhancement for tags/james:end
                $renderingprovider['tags'] = $tags_from_page;

                $renderingprovider_phone_numbe = $this->getRequest()->getPost('phone_number');
                $renderingprovider_secondary_phone_number = $this->getRequest()->getPost('secondary_phone_number');
                $renderingprovider_fax_number = $this->getRequest()->getPost('fax_number');

                $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
                $renderingprovider['phone_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_phone_numbe);
                $renderingprovider['secondary_phone_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_secondary_phone_number);
                $renderingprovider['fax_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_fax_number);

                $renderingprovider['notes'] = $this->getRequest()->getPost('notes');
                $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
                $db = $db_renderingprovider->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldrenderprovider = $db_renderingprovider->fetchAll($where)->toArray();

                if ($db_renderingprovider->update($renderingprovider, $where)) {
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.street_address', $oldrenderprovider[0]['street_address'], $renderingprovider['street_address']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.zip', $oldrenderprovider[0]['zip'], $renderingprovider['zip']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.city', $oldrenderprovider[0]['city'], $renderingprovider['city']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.NPI', $oldrenderprovider[0]['NPI'], $renderingprovider['NPI']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.state', $oldrenderprovider[0]['state'], $renderingprovider['state']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.phone_number', $oldrenderprovider[0]['phone_number'], $renderingprovider['phone_number']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.secondary_phone_number', $oldrenderprovider[0]['secondary_phone_number'], $renderingprovider['secondary_phone_number']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.fax_number', $oldrenderprovider[0]['fax_number'], $renderingprovider['fax_number']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.notes', $oldrenderprovider[0]['notes'], $renderingprovider['notes']);
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'renderingprovider.notes', $oldrenderprovider[0]['salutations'], $renderingprovider['salutations']);
                }
                $providerhasrenderingprovider['provider_id'] = $provider_id;
                $providerhasrenderingprovider['renderingprovider_id'] = $id;
                $providerhasrenderingprovider['status'] = $this->getRequest()->getPost('status');
                if ($provider_id != 0) {
                    $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                    $db = $db_providerhasrenderingprovider->getAdapter();
                    $where = $db->quoteInto('provider_id = ?', $providerhasrenderingprovider['provider_id']) . $db->quoteInto('and renderingprovider_id=?', $providerhasrenderingprovider['renderingprovider_id']);
                    $oldhas = $db_providerhasrenderingprovider->fetchAll($where)->toArray();
                    if ($db_providerhasrenderingprovider->update($providerhasrenderingprovider, $where)) {
                        $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'providerhasrenderingprovider.status', $oldhas[0]['status'], $providerhasrenderingprovider['status']);
                    }
                } else {
                    $del_provider_id = $this->getRequest()->getPost('del_provider_id');
                    if ($del_provider_id == 0) {
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();

                        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                        $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
                        $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                        $db = $db_providerhasrenderingprovider->getAdapter();
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $provider_id = $provider_data[$i]['id'];
                            $providerhasrenderingprovider['provider_id'] = $provider_id;
                            $wherehas = $db->quoteInto('provider_id = ?', $provider_id) . $db->quoteInto('and renderingprovider_id=?', $providerhasrenderingprovider['renderingprovider_id']);
                            $oldhas = $db_providerhasrenderingprovider->fetchAll($wherehas)->toArray();
                            if ($db_providerhasrenderingprovider->update($providerhasrenderingprovider, $wherehas)) {

                                $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'providerhasrenderingprovider.status', $oldhas[0]['status'], $providerhasrenderingprovider['status']);
                            }
                        }
                    } else {
                        $providerhasrenderingprovider['provider_id'] = $del_provider_id;
                        $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
                        $db = $db_providerhasrenderingprovider->getAdapter();
                        $where = $db->quoteInto('provider_id = ?', $del_provider_id) . $db->quoteInto('and renderingprovider_id=?', $providerhasrenderingprovider['renderingprovider_id']);
                        $oldhas = $db_providerhasrenderingprovider->fetchAll($where)->toArray();
                        if ($db_providerhasrenderingprovider->update($providerhasrenderingprovider, $where)) {

                            $this->adddatalog($del_provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'providerhasrenderingprovider.status', $oldhas[0]['status'], $providerhasrenderingprovider['status']);
                        }
                    }
                }
                $this->_redirect('/biller/data/renderingprovider');
            }

            if ($submitType == "New")
                $this->_redirect('/biller/data/newrenderingprovider');
            if ($submitType == "Add Payer") {
                $innetworkpayers = array();
                $innetworkpayers['renderingprovider_id'] = $this->getRequest()->getPost('id');
                $innetworkpayers['insurance_id'] = $this->getRequest()->getPost('insurance_id');
                $provider_id = $this->getRequest()->getPost('provider_id');
                $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
                $db1 = $db_renderingprovider->getAdapter();
                $where1 = $db1->quoteInto('id = ?', $innetworkpayers['renderingprovider_id']);
                $oldrenderprovider = $db_renderingprovider->fetchAll($where1)->toArray();
                $db_insurace = new Application_Model_DbTable_Insurance();
                $db2 = $db_insurace->getAdapter();
                $where2 = $db2->quoteInto('id = ?', $innetworkpayers['insurance_id']);
                $oldinsurance = $db_insurace->fetchAll($where2)->toArray();
                $db_innetworkpayers = new Application_Model_DbTable_Innetworkpayers();
                $db = $db_innetworkpayers->getAdapter();

                if ($db_innetworkpayers->insert($innetworkpayers)) {
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'Innetworkpayers.insurance_id', NULL, $oldinsurance[0]['insurance_display']);
                }
                $this->_redirect('/biller/data/renderingprovider');
            }
            if ($submitType == "Delete Payer") {
                $id = $this->getRequest()->getPost('rendering_provider_id');
                $payer_id = $this->getRequest()->getPost('payer_id');

                $provider_id = $this->getRequest()->getPost('provider_id');



                $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
                $db1 = $db_renderingprovider->getAdapter();
                $where1 = $db1->quoteInto('id = ?', $id);
                $oldrenderprovider = $db_renderingprovider->fetchAll($where1)->toArray();
                $db_insurace = new Application_Model_DbTable_Insurance();
                $db2 = $db_insurace->getAdapter();
                $where2 = $db2->quoteInto('id = ?', $payer_id);
                $oldinsurance = $db_insurace->fetchAll($where2)->toArray();

                $db_innetworkpayers = new Application_Model_DbTable_Innetworkpayers();
                $db = $db_innetworkpayers->getAdapter();
                $quote = 'insurance_id = ' . $payer_id . ' AND ' . 'renderingprovider_id = ' . $id;
                $where = $db->quoteInto($quote);

                if ($db_innetworkpayers->delete($where)) {
                    $this->adddatalog($provider_id, $oldrenderprovider[0]['last_name'] . ' ' . $oldrenderprovider[0]['first_name'], 'Innetworkpayers.insurance_id', $oldinsurance[0]['insurance_display'], NULL);
                }
                $this->_redirect('/biller/data/renderingprovider');
            }
            if ($submitType == "UPLOAD") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $desc = $this->getRequest()->getParam('desc');
                if ($desc == "" || $desc == null) {
                    $this->_redirect('/biller/data/renderingprovider');
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                    if (!is_dir($dir_billingcompany)) {
                        mkdir($dir_billingcompany);
                    }
                    if ($provider_id != '0') {
                        $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                        if (!is_dir($dir_provider)) {
                            mkdir($dir_provider);
                        }
                        $dir_facility = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'renderprovider';
                        if (!is_dir($dir_facility)) {
                            mkdir($dir_facility);
                        }
                        $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'renderprovider/' . $id;
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                        $today = date("Y-m-d H:i:s");
                        $date = explode(' ', $today);
                        $time0 = explode('-', $date[0]);
                        $time1 = explode(':', $date[1]);
                        $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $file_name = $time . '-' . $desc . '-' . $user_name;
                        $old_filename = $adapter->getFileName();
                        $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                        $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                        $adapter->setDestination($dir);
                        $db_rprovider = new Application_Model_DbTable_Renderingprovider();
                        $db = $db_rprovider->getAdapter();
                        $where = $db->quoteInto("id=?", $id);
                        $rprovider_data = $db_rprovider->fetchRow($where);
                        $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        if (!$adapter->receive()) {
                            $messages = $adapter->getMessages();
                            echo implode("n", $messages);
                        } else {
                            $this->adddatalog($provider_id, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
                            $this->_redirect('/biller/data/renderingprovider');
                        }
                    } else {
                        $billingcompany_id = $this->billingcompany_id();
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();
                        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
                        $provider_list = $db_provider->fetchAll($where);
                        $count = 0;
                        $first_copy = "";
                        $first_copy_name = "";
                        foreach ($provider_list as $provider) {
                            $provider_id = $provider_list[$count]['id'];
                            $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                            if (!is_dir($dir_provider)) {
                                mkdir($dir_provider);
                            }
                            $dir_referprovider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'renderprovider';
                            if (!is_dir($dir_referprovider)) {
                                mkdir($dir_referprovider);
                            }
                            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'renderprovider/' . $id;
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            if ($count == 0) {
                                $today = date("Y-m-d H:i:s");
                                $date = explode(' ', $today);
                                $time0 = explode('-', $date[0]);
                                $time1 = explode(':', $date[1]);
                                $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                                $user = Zend_Auth::getInstance()->getIdentity();
                                $user_name = $user->user_name;
                                $file_name = $time . '-' . $desc . '-' . $user_name;
                                $old_filename = $adapter->getFileName();
                                $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                                $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                                $adapter->setDestination($dir);
                                $adapter->receive();
                                $first_copy = $dir . '/' . $file_name . $file_extension;
                                $first_copy_name = $file_name . $file_extension;
                            } else {
                                $dest = $dir . '/' . $first_copy_name;
                                copy($first_copy, $dest);
                            }
                            $count++;
                        }
                        $db_rprovider = new Application_Model_DbTable_Renderingprovider();
                        $db = $db_rprovider->getAdapter();
                        $where = $db->quoteInto("id=?", $id);
                        $rprovider_data = $db_rprovider->fetchRow($where);
                        $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        $this->adddatalog(0, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
                        $this->_redirect('/biller/data/renderingprovider');
                    }
                }
            }
        }
    }

    /**
     * innetworkpayersAction
     * a function returning the innetwork payers list.
     * @author Haowei.
     * @return the innetwork payers list
     * @version 05/15/2012
     */
    public function innetworkpayersAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('renderingprovider');
        $select->join('innetworkpayers', 'renderingprovider.id = innetworkpayers.renderingprovider_id');
        $select->join('insurance', 'innetworkpayers.insurance_id = insurance.id');
        $select->group('insurance.id');
        $select->order('insurance_name ASC');
        $innetworkpayersList = $db->fetchAll($select);
        $data['innetworkpayersList'] = $innetworkpayersList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newrenderngproviderAction
     * a function for creating a new renderingprovider.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newrenderingproviderAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRend.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,renderingprovider_id FROM providerhasrenderingprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRend
ON hasRend.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;

        session_start();
        $rendering_all_providerList = $_SESSION['rendering_all_providerList'];
        $_SESSION['provider_list'] = $provider_data;
        $all = count($rendering_all_providerList);
        $this->view->allNumRend = array('num' => $all);

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        $salutations = $billingcompany_data['salutations'];

        $salutationsList = explode('|', $salutations);
        $enth = strlen($salutationsList);
        if ($salutationsList[0] == null && strlen($salutationsList) == 0) {
            $salutationsList = null;
        }
        $this->view->SalutationsList = $salutationsList;
        //added to support #29: Data Management tool enhancement for tags/james
        session_start();
        $tags = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_counts = $_SESSION['tags_in_billingcompany']['tag_count'];
        $this->view->tagList = $tags;
        $this->view->tagNames = $tag_names;
        $this->view->tagNumber = $tag_counts;


        if ($this->getRequest()->isPost()) {
            $providerhasrenderingprovider = array();
            $new_or_import = $this->getRequest()->getPost('import_or_new');
            $db_Renderingprovider = new Application_Model_DbTable_Renderingprovider();
            $db = $db_Renderingprovider->getAdapter();
            $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
            $db_has = $db_providerhasrenderingprovider->getAdapter();
            if ($new_or_import == 'new') {

                $renderingprovider = array();
                $provider_id = $this->getRequest()->getPost('provider_id');

                $renderingprovider['last_name'] = $this->getRequest()->getPost('add_last_name');
                $renderingprovider['first_name'] = $this->getRequest()->getPost('add_first_name');
                $renderingprovider['street_address'] = $this->getRequest()->getPost('street_address');
                $renderingprovider['zip'] = $this->getRequest()->getPost('zip');
                $renderingprovider['NPI'] = $this->getRequest()->getPost('NPI');
                $renderingprovider['salutation'] = $this->getRequest()->getPost('salutation');
                $renderingprovider['city'] = $this->getRequest()->getPost('city');
                $renderingprovider['state'] = $this->getRequest()->getPost('state');
                $renderingprovider['id1'] = $this->getRequest()->getPost('id1');
                $renderingprovider['id2'] = $this->getRequest()->getPost('id2');
                $renderingprovider_phone_numbe = $this->getRequest()->getPost('phone_number');
                $renderingprovider_secondary_phone_number = $this->getRequest()->getPost('secondary_phone_number');
                $renderingprovider_fax_number = $this->getRequest()->getPost('fax_number');
                $pattern = "/\((\d{3})\)(\d{3})\-(\d{4})/";
                $renderingprovider['phone_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_phone_numbe);
                $renderingprovider['secondary_phone_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_secondary_phone_number);
                $renderingprovider['fax_number'] = preg_replace($pattern, "\\1\\2\\3", $renderingprovider_fax_number);

                //added to support #29: Data Management tool enhancement for tags/james:start
                $tags_from_page = "";
                $loop_mark = 0;
                $tags_size = count($tags);
                foreach ($tags as $row) {
                    $loop_mark ++;
                    $tp_value_from_page = $this->getRequest()->getPost($row[tag_name]);
                    if ($row[tag_type] == "binary") {
                        if ($tp_value_from_page == "yes") {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=yes";
                            }
                        }
                    } else if ($row[tag_type] == "other") {
                        if (($tp_value_from_page != "") || ($tp_value_from_page != null)) {
                            if ($loop_mark != $tags_size) {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page . "|";
                            } else {
                                $tags_from_page = $tags_from_page . $row[tag_name] . "=" . $tp_value_from_page;
                            }
                        }
                    }
                }
                if (substr($tags_from_page, -1) == "|") {
                    $tags_from_page = substr($tags_from_page, 0, -1);
                }
                //added to support #29: Data Management tool enhancement for tags/james:end
                $renderingprovider['tags'] = $tags_from_page;

                $renderingprovider['notes'] = $this->getRequest()->getPost('notes');
                $renderingprovide_get = $renderingprovider['last_name'];
                $providerid_get = $this->getRequest()->getPost('provider_id');
                if ($providerid_get >= 0 && $renderingprovide_get != '') {

                    //  $where = $db->quoteInto("last_name = ?",$renderingprovider['last_name']).$db->quoteInto("and first_name = ?",$renderingprovider['first_name']);
                    //  $exsit = $db_Renderingprovider->fetchAll($where);
                    $where = $db->quoteInto("last_name = ?", $renderingprovider['last_name']) . $db->quoteInto("and first_name = ?", $renderingprovider['first_name']) . $db->quoteInto("and NPI = ?", $renderingprovider['NPI']);
                    $exsit = $db_Renderingprovider->fetchAll($where);
                    $exsithas = array();
                    $provder_ids = array();

                    if (isset($exsit[0])) {
                        $db_hasexisting = new Application_Model_DbTable_Providerhasrenderingprovider();
                        $dbexsit = $db_hasexisting->getAdapter();
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $provder_ids[$i] = $provider_data[$i]['id'];
                        }
                        $wherehasexisting = $dbexsit->quoteInto("renderingprovider_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);
                        $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                    }
//                        //if there exsit the diagnosiscode,get the diagnosiscode_id
                    if (isset($exsitlast[0])) {
//                            //$provierhasfacility['facility_id']=$exsit[0]['id'];
                        echo '<span style="color:red;font-size:16px">Sorry ! The Renderingprovider is existing , please use Import !</span>';
                    } else {

                        $providerhasrenderingprovider['renderingprovider_id'] = $db_Renderingprovider->insert($renderingprovider);
                        if ($providerhasrenderingprovider['renderingprovider_id']) {
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.last_name', NULL, $renderingprovider['last_name']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.first_name', NULL, $renderingprovider['first_name']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.street_address', NULL, $renderingprovider['street_address']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.city', NULL, $renderingprovider['city']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.state', NULL, $renderingprovider['state']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.zip', NULL, $renderingprovider['zip']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.phone_number', NULL, $renderingprovider['phone_number']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.secondary_phone_number', NULL, $renderingprovider['secondary_phone_number']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.fax_number', NULL, $renderingprovider['fax_number']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.NPI', NULL, $renderingprovider['NPI']);
                            $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'renderingprovider.notes', NULL, $renderingprovider['notes']);
                        }
                        //   }
                        //complete the new providerhasrenderingprovider
                        $providerhasrenderingprovider['provider_id'] = $this->getRequest()->getPost('provider_id');
                        $providerhasrenderingprovider['status'] = $this->getRequest()->getPost('status');

                        //insert the $providerhasrenderingprovider
                        if ($providerhasrenderingprovider['provider_id'] == 0) {
                            //if insert the renderingprovider to all provider
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provider_id = $provider_data[$i]['id'];
                                $providerhasrenderingprovider['provider_id'] = $provider_data[$i]['id'];
                                //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                                $where = $db_has->quoteInto('provider_id = ?', $providerhasrenderingprovider['provider_id']) . $db_has->quoteInto("and renderingprovider_id = ?", $providerhasrenderingprovider['renderingprovider_id']);
                                $ext = $db_providerhasrenderingprovider->fetchAll($where);
                                $whererender = $db->quoteInto('id = ? ', $providerhasrenderingprovider['renderingprovider_id']);
                                $oldrender = $db_Renderingprovider->fetchAll($whererender)->toArray();
                                if (isset($ext[0])) {
                                    
                                } else {
                                    if ($db_providerhasrenderingprovider->insert($providerhasrenderingprovider)) {
                                        $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'providerhasrenderingprovider.status', NULL, $providerhasrenderingprovider['status']);
                                    }
                                }
                            }
                        } else {
                            $providerhasrenderingprovider['provider_id'] = $this->getRequest()->getPost('provider_id');
                            $provider_id = $providerhasrenderingprovider['provider_id'];
//                        //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                            $where = $db_has->quoteInto("provider_id = ?", $providerhasrenderingprovider['provider_id']) . $db_has->quoteInto("and renderingprovider_id = ?", $providerhasrenderingprovider['renderingprovider_id']);
                            $extfer = $db_providerhasrenderingprovider->fetchAll($where);
                            $TEMP = isset($extfer[0]);
                            if (!$TEMP) {
                                if ($db_providerhasrenderingprovider->insert($providerhasrenderingprovider)) {
                                    $whererender = $db->quoteInto('id = ? ', $providerhasrenderingprovider['renderingprovider_id']);
                                    $oldrender = $db_Renderingprovider->fetchAll($whererender)->toArray();
                                    $this->adddatalog($provider_id, $renderingprovider['last_name'] . ' ' . $renderingprovider['first_name'], 'providerhasrenderingprovider.status', NULL, $providerhasrenderingprovider['status']);
                                }
                            }
                        }
                        $this->_redirect('/biller/data/renderingprovider');
                    }
                } else {
                    $this->_redirect('/biller/data/newrenderingprovider');
                }
            }
            //if(new)
            if ($new_or_import == 'import') {
                $db_providerhasrenderingprovider = new Application_Model_DbTable_providerhasrenderingprovider();
                $db = $db_providerhasrenderingprovider->getAdapter();

                $providerhasrenderingprovider['renderingprovider_id'] = $this->getRequest()->getPost('last_name');
                $renderingprovider_get = $providerhasrenderingprovider['renderingprovider_id'];
                $providerid_get = $this->getRequest()->getPost('provider_id');
                $providerhasrenderingprovider['provider_id'] = $providerid_get;
                $providerhasrenderingprovider['status'] = $this->getRequest()->getPost('status');
                $db_rederringprovider = new Application_Model_DbTable_Renderingprovider();
                $dbref = $db_rederringprovider->getAdapter();
                $where_ref = $dbref->quoteInto('id = ?', $providerhasrenderingprovider['renderingprovider_id']);
                $oldref = $db_rederringprovider->fetchAll($where_ref);
                //$table = 'providerhasdiagnosiscode'; 
                if ($providerid_get >= 0 && $renderingprovider_get) {
                    if ($providerhasrenderingprovider['provider_id'] == 0) {
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $providerhasrenderingprovider['provider_id'] = $provider_data[$i]['id'];
                            $provider_id = $provider_data[$i]['id'];
                            $where = $db->quoteInto("provider_id = ?", $providerhasrenderingprovider['provider_id']) . $db->quoteInto("and renderingprovider_id = ?", $providerhasrenderingprovider['renderingprovider_id']);
                            $ext = $db_providerhasrenderingprovider->fetchAll($where);
                            if (isset($ext[0])) {
                                if ($db_providerhasrenderingprovider->update($providerhasrenderingprovider, $where)) {
                                    $this->adddatalog($provider_id, $oldref[0]['last_name'] . ' ' . $oldref[0]['first_name'], 'providerhasrenderingprovider.status', NULL, $providerhasrenderingprovider['status']);
                                }
                            } else {
                                if ($db_providerhasrenderingprovider->insert($providerhasrenderingprovider)) {
                                    $this->adddatalog($provider_id, $oldref[0]['last_name'] . ' ' . $oldref[0]['first_name'], 'providerhasrenderingprovider.status', NULL, $providerhasrenderingprovider['status']);
                                }
                            }
                            //$db->insert($table,$providerhasdiagnosiscode);
                        }
                    } else {
                        $providerhasrenderingprovider['provider_id'] = $providerid_get;
                        //$db->insert($table,$providerhasdiagnosiscode);
                        if ($db_providerhasrenderingprovider->insert($providerhasrenderingprovider)) {
                            $this->adddatalog($providerid_get, $oldref[0]['last_name'] . ' ' . $oldref[0]['first_name'], 'providerhasrenderingprovider.status', NULL, $providerhasrenderingprovider['status']);
                        }
                    }
                } else {
                    $this->_redirect('/biller/data/newrenderingprovider');
                }
                $this->_redirect('/biller/data/renderingprovider');
            }
        }
    }

    public function referringlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];

        if ($provider_id == 0) {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
            //全对应facility：该facility对应着改组provider中的每一个

            $sql = <<<SQL
SELECT min(has.provider_id),has.referringprovider_id FROM  providerhasreferringprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.referringprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrefer_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.referringprovider_id FROM  providerhasreferringprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.referringprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrefer_data_active = $result->fetchAll();
            $referring = array();
            $index = 0;
            for ($i = 0; $i < count($providerhasrefer_data); $i++) {
                $referring[$index] = $providerhasrefer_data[$i]['referringprovider_id'];
                $index++;
            }

            for ($k = 0; $k < count($providerhasrefer_data_active); $k++) {

                $referring[$index] = $providerhasrefer_data_active[$k]['referringprovider_id'];
                $index++;
            }
        } else {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
            //全对应facility：该facility对应着改组provider中的每一个

            $sql = <<<SQL
SELECT min(has.provider_id),has.referringprovider_id FROM  providerhasreferringprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.referringprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrefer_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.referringprovider_id FROM  providerhasreferringprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.referringprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrefer_data_active = $result->fetchAll();
            $referringall = array();
            $index = 0;
            for ($i = 0; $i < count($providerhasrefer_data); $i++) {
                $referringall[$index] = $providerhasrefer_data[$i]['referringprovider_id'];
                $index++;
            }

            for ($k = 0; $k < count($providerhasrefer_data_active); $k++) {

                $referringall[$index] = $providerhasrefer_data_active[$k]['referringprovider_id'];
                $index++;
            }

            $db_providerhasrefersingle = new Application_Model_DbTable_Providerhasreferringprovider();
            $db = $db_providerhasrefersingle->getAdapter();
            $wheres = $db->quoteInto('provider_id = ?', $provider_id); //
            $providerhasrefer_data_single = $db_providerhasrefersingle->fetchAll($wheres);

            $referring = array();

            for ($i = 0; $i < count($providerhasrefer_data_single); $i++) {
                $referring[$i] = $providerhasrefer_data_single[$i]['referringprovider_id'];
            }
        }


        $db_ref = new Application_Model_DbTable_Referringprovider();
        $db = $db_ref->getAdapter();
        if (empty($referring)) {
            $referring[0] = 0;
        }
        if (empty($referringall)) {
            $referringall[0] = 0;
        }
        $where = $db->quoteInto('id IN(?)', $referring) . $db->quoteInto('and id not IN(?)', $referringall); //
        // $where = $db->order('last_name');
        $referringproviderList = $db_ref->fetchAll($where, 'last_name ASC', 'first_name ASC')->toArray();


        if ($provider_id == '0') {
            session_start();
            $_SESSION['referringall'] = $referring;
        }

        $data = array();
//        $this->deletenew($referringproviderList, 'last_name', 'Need New');
        $data['referringproviderList'] = $referringproviderList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function renderinglistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];

        if ($provider_id == 0) {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数


            $sql = <<<SQL
SELECT min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.renderingprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrender_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.renderingprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrender_data_active = $result->fetchAll();
            $renderring = array();
            $index = 0;
            for ($i = 0; $i < count($providerhasrender_data); $i++) {
                $renderring[$index] = $providerhasrender_data[$i]['renderingprovider_id'];
                $index++;
            }

            for ($k = 0; $k < count($providerhasrender_data_active); $k++) {

                $renderring[$index] = $providerhasrender_data_active[$k]['renderingprovider_id'];
                $index++;
            }
        } else {
            $user = Zend_Auth::getInstance()->getIdentity();
            //获取数据库适配器
            $db = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数


            $sql = <<<SQL
SELECT min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='inactive' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
group by has.renderingprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrender_data = $result->fetchAll();
            $sql = <<<SQL
SELECT  min(has.provider_id),has.renderingprovider_id FROM  providerhasrenderingprovider has,provider p  
WHERE has.provider_id = p.id AND p.billingcompany_id =? AND status='active' AND has.renderingprovider_id in 
(
SELECT t.id FROM 
(
    SELECT renderingprovider_id id ,count(*) counts, status FROM providerhasrenderingprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' ) 
    GROUP BY renderingprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
group by has.renderingprovider_id 

SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $providerhasrender_data_active = $result->fetchAll();
            $renderringall = array();
            $index = 0;
            for ($i = 0; $i < count($providerhasrender_data); $i++) {
                $renderringall[$index] = $providerhasrender_data[$i]['renderingprovider_id'];
                $index++;
            }

            for ($k = 0; $k < count($providerhasrender_data_active); $k++) {

                $renderringall[$index] = $providerhasrender_data_active[$k]['renderingprovider_id'];
                $index++;
            }

            $db_providerhasrefersingle = new Application_Model_DbTable_Providerhasrenderingprovider();
            $db = $db_providerhasrefersingle->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $provider_id); //
            $providerhasrender_data = $db_providerhasrefersingle->fetchAll($where);

            $renderring = array();

            for ($i = 0; $i < count($providerhasrender_data); $i++) {
                $renderring[$i] = $providerhasrender_data[$i]['renderingprovider_id'];
            }
        }


        $db_ref = new Application_Model_DbTable_Renderingprovider();
        $db = $db_ref->getAdapter();
        if (empty($renderring)) {
            $renderring[0] = 0;
        }
        if (empty($renderringall)) {
            $renderringall[0] = 0;
        }
        $wheres = $db->quoteInto('id IN(?)', $renderring) . $db->quoteInto('and id not IN(?)', $renderringall); //

        $renderingproviderList = $db_ref->fetchAll($wheres, 'last_name ASC', 'first_name ASC')->toArray();

        $data = array();

        if ($provider_id == 0) {
            session_start();
            $_SESSION['renderingproviderList'] = $renderingproviderList;
        }
        $data['renderingproviderList'] = $renderingproviderList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * paitentAction
     * a function returning the renderingprovider data for displaying on the page.
     * @author Haowei.
     * @return the renderingprovider data for displaying on the page
     * @version 05/15/2012
     */
    public function renderingproviderinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $provider_id = $_POST['provider_id'];
        if ($provider_id != '0') {
            $rprovider_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/renderprovider/' . $id;
            $doc_paths = array();
            if (is_dir($rprovider_doc_path)) {
                foreach (glob($rprovider_doc_path . '/*.*') as $filename) {
                    array_push($doc_paths, $filename);
                }
            }
            $rprovider_doc_list = array();
            $i = 0;
            foreach ($doc_paths as $path) {
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
                $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $rprovider_doc_list[$i]['date'] = $date;
                $rprovider_doc_list[$i]['desc'] = $temp[1];
                $rprovider_doc_list[$i]['user'] = $temp[2];
                $count = count($temp);
                $n = 3;
                if ($count > 3) {
                    for ($n; $n < $count; $n++) {
                        $rprovider_doc_list[$i]['user'] = $rprovider_doc_list[$i]['user'] . '-' . $temp[$n];
                    }
                }
                $rprovider_doc_list[$i]['url'] = $path;
                $i++;
            }
        } else {
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
            $provider_list_temp = $db_provider->fetchAll($where);
            $rprovider_doc_list = array();
            $i = 0;
            $j = 0;
            foreach ($provider_list_temp as $provider_temp) {
                $provider_id_temp = $provider_temp['id'];
                $rprovider_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id_temp . '/renderprovider/' . $id;
                $doc_paths = array();
                if (is_dir($rprovider_doc_path)) {
                    foreach (glob($rprovider_doc_path . '/*.*') as $filename) {
                        array_push($doc_paths, $filename);
                    }
                }
                foreach ($doc_paths as $path) {
                    $temp = explode("/", $path);
                    $temp = explode(".", $temp[count($temp) - 1]);
                    $filename = $temp[0];
                    $temp = explode("-", $filename);
                    if ($j == 0) {
                        $rprovider_doc_list[$i] = $this->getDocPara($temp, $path);
                        $rprovider_doc_list[$i]['filename'] = $filename;
                        $i++;
                    } else {
                        $judge = 1;
                        foreach ($rprovider_doc_list as $rprovider_test) {
                            if ($rprovider_test['filename'] == $filename) {
                                $judge = 0;
                            }
                        }
                        if ($judge == 1) {
                            $rprovider_doc_list[$i] = $this->getDocPara($temp, $path);
                            $rprovider_doc_list[$i]['filename'] = $filename;
                            $i++;
                        }
                    }
                }
                $j++;
            }
        }
        $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
        $db = $db_renderingprovider->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $renderingprovider_data = $db_renderingprovider->fetchRow($where);

        //added to support #29 provider tags extend work:james/start
        $exist_tags = $renderingprovider_data['tags'];
        $exist_tags_List = explode('|', $exist_tags);
        $mark_exists = 0;
        foreach ($exist_tags_List as $row) {
            $tp_exist_tags = explode('=', $row);
            $exist_tag_names[$mark_exists] = $tags[$mark_exists]['tag_name'] = $tp_exist_tags[0];
            $tags[$mark_exists]['tag_type'] = $tp_exist_tags[1];
            $mark_exists ++;
        }
        $tag_count_exists = $mark_exists;

        session_start();
        $tags_in_billingcompany = $_SESSION['tags_in_billingcompany']['tags'];
        $tag_names = $_SESSION['tags_in_billingcompany']['tag_names'];
        $tag_count_in_billingcompany = $_SESSION['tags_in_billingcompany']['tag_count'];
        //added to support #29 provider tags extend work:james/end

        if ($provider_id == 0) {
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
            $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
            $provider_id = $provider_data[0]['id'];
        }
        $db_providerhasrenderingprovider = new Application_Model_DbTable_Providerhasrenderingprovider();
        $db = $db_providerhasrenderingprovider->getAdapter();
        $where = $db->quoteInto('renderingprovider_id = ?', $id) . $db->quoteInto('and provider_id=?', $provider_id);
        $providerhasrenderingprovider_data = $db_providerhasrenderingprovider->fetchRow($where);
        // $provider_id = $providerhasrenderingprovider_data['provider_id'];
        $status = $providerhasrenderingprovider_data['status'];
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('id = ?', $provider_id);
        $provider_data = $db_provider->fetchRow($where);
        $provider_name = $provider_data['provider_name'];

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('renderingprovider');
        $select->join('innetworkpayers', 'renderingprovider.id = innetworkpayers.renderingprovider_id');
        $select->join('insurance', 'innetworkpayers.insurance_id = insurance.id', array('insurance.id as insurance_id', 'insurance_display'));
        $select->group('insurance.id');
        $select->where('renderingprovider.id=?', $id);
        $select->order('insurance_name ASC');
        $innetworkpayersList = $db->fetchAll($select);
        $phone_number = phone($renderingprovider_data['phone_number']);
        $secondary_phone_number = phone($renderingprovider_data['secondary_phone_number']);
        $fax_number = phone($renderingprovider_data['fax_number']);



        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;

        $data = array();
        $data = array('provider_name' => $provider_name, 'zip' => $renderingprovider_data['zip'], 'salutation' => $renderingprovider_data['salutation'], 'innetworkpayersList' => $innetworkpayersList, 'fax_number' => $fax_number, 'notes' => $renderingprovider_data['notes'],
            'street_address' => $renderingprovider_data['street_address'], 'state' => $renderingprovider_data['state'], 'city' => $renderingprovider_data['city'], 'NPI' => $renderingprovider_data['NPI'], 'id1' => $renderingprovider_data['id1'], 'id2' => $renderingprovider_data['id2'],
            'phone_number' => $phone_number, 'secondary_phone_number' => $secondary_phone_number, 'status' => $status, 'provider_id' => $provider_id, 'rprovider_doc_list' => $rprovider_doc_list, 'user_name' => $user_name,
            'tags' => $tags, 'tags_in_billingcompany' => $tags_in_billingcompany, 'tag_names' => $tag_names, 'tag_number' => $tag_count_in_billingcompany,
            'exist_tag_number' => $tag_count_exists, 'exist_tag_names' => $exist_tag_names);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * claimreferringproviderAction
     * a function returning the referringprovider list.
     * @author Haowei.
     * @return the referringprovider list
     * @version 05/15/2012
     */
    public function claimreferringproviderAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRef.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,referringprovider_id FROM providerhasreferringprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRef
ON hasRef.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive') 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active') 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumRef = array('num' => $all);
    }

    public function providerbillinglistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('provider', array('provider.id as provider_id', 'provider.bill_date as bill_date'));
        $select->join('options', 'options.id = provider.options_id', array('options.provider_invoice_rate as provider_invoice_rate'));
        $select->where('provider.billingcompany_id = ?', $this->billingcompany_id);
        try {
            $provider_data = $db->fetchAll($select);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $db = Zend_Registry::get('dbAdapter');
        //foreach ($provider_data as $key => $provider) {
        $providerList = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $providerList[$i]['id'] = $provider_data[$i]['provider_id'];
            $providerList[$i]['bill_date'] = $provider_data[$i]['bill_date'];
            $providerList[$i]['provider_invoice_rate'] = $provider_data[$i]['provider_invoice_rate'];
        }

        foreach ($providerList as $row) {
            $id = $row['id'];
            $provider_invoice_rate = $row['provider_invoice_rate'];

            $sql = 'SELECT DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' ) AS Month,
                SUM(co_amount.amount_paid) AS Collection 

                FROM (
                SELECT payments.amount AS amount_paid, payments.datetime AS amount_paid_date FROM payments
                WHERE payments.serviceid=0 and payments.from <> \'Biller Adjustment\' AND claim_id in
                (SELECT claim_id FROM encounter WHERE provider_id = ' . $id . ')
                )AS co_amount

             
                GROUP BY DATE_FORMAT( co_amount.amount_paid_date , \'%c/%Y\' )
                  order by Month  desc';
            $result = $db->query($sql, array());
            $rows = $result->fetchAll();
            $temp = $rows;
            for ($i = 0; $i < count($temp); $i++) {
                $month = $temp[$i]['Month'];
                $db_providerbilling = new Application_Model_DbTable_Providerbilling();
                $db = $db_providerbilling->getAdapter();
                $where = $db->quoteInto('bill_period=?', $month) . $db->quoteInto('AND provider_id = ?', $id);
                $tag = $db_providerbilling->fetchRow($where);

                if ($temp[$i]['Collection'] != null) {
                    $providerbilling = array();
                    $providerbilling['bill_period'] = $month;
                    $providerbilling['amount_collected'] = $temp[$i]['Collection'] == null ? 0 : $temp[$i]['Collection'];
                    $providerbilling['amount_billed'] = $providerbilling['amount_collected'] * $provider_invoice_rate;
                    $providerbilling['provider_id'] = $id;
                    if ($tag == null) {
                        try {
                            if ($db_providerbilling->insert($providerbilling)) {
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.bill_period', NULL, $providerbilling['bill_period']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.amount_collected', NULL, $providerbilling['amount_collected']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.amount_billed', NULL, $providerbilling['amount_billed']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.provider_id', NULL, $providerbilling['provider_id']);
                            }
                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                    } else {
                        try {
                            if ($db_providerbilling->update($providerbilling, $where)) {
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.bill_period', $tag['bill_period'], $providerbilling['bill_period']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.amount_collected', $tag['amount_collected'], $providerbilling['amount_collected']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.amount_billed', $tag['amount_billed'], $providerbilling['amount_billed']);
                                $this->adddatalog($providerbilling['provider_id'], $providerbilling['bill_period'], 'providerbilling.provider_id', $tag['provider_id'], $providerbilling['provider_id']);
                            }
                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                    }
                }
            }
        }

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('billingcompany');
        $select->join('provider', 'billingcompany.id=provider.billingcompany_id', array('provider.provider_name as provider_name'));
        $select->join('providerbilling', 'providerbilling.provider_id=provider.id', array('providerbilling.bill_period as bill_period', 'providerbilling.amount_collected as amount_collected', 'providerbilling.amount_billed as amount_billed',
            'providerbilling.amount_paid as amount_paid', 'providerbilling.id as providerbilling_id', 'providerbilling.notes as notes'));
        $select->where('billingcompany.id=?', $this->billingcompany_id);
        $select->where('isnull(providerbilling.date_billed)');
        try {
            $providerbillingList = $db->fetchAll($select);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        for ($i = 0; $i < count($providerbillingList); $i++) {
            $bill_period_Array = explode('/', $providerbillingList[$i]['bill_period']);
            $providerbillingList[$i]['bill_period_data'] = $bill_period_Array[0] + $bill_period_Array[1] * 100;
        }
        foreach ($providerbillingList as $key => $value) {
            $bill_period_data[$key] = $value['bill_period_data'];
            $provider_name[$key] = $value['provider_name'];
        }
        array_multisort($provider_name, SORT_STRING, SORT_ASC, $bill_period_data, SORT_NUMERIC, SORT_DESC, $providerbillingList);

        session_start();
        unset($_SESSION['tmp']);
        $_SESSION['tmp']['providerbillingList'] = $providerbillingList;
        $this->_redirect('/biller/data/providerbilling');
    }

    public function providerbillingAction() {
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id);
        $provider_data = $db_provider->fetchAll($where)->toArray();
        $this->view->providerlist = $provider_data;
        $myprovider_data = array();
        $myprovider_id_array = array();
        $index = 0;
        foreach ($provider_data as $key => $provider) {
            $myprovider_data[$provider['id']] = $provider;
            $myprovider_id_array[$index] = $provider['id'];
            $index++;
        }

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        if (!$this->getRequest()->isPost()) {


            session_start();
            $providerbillingList = $_SESSION['tmp']['providerbillingList'];
            $this->view->providerbillingList = $providerbillingList;
        }
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "providerbilling") {
                $providerbilling_ids = $this->getRequest()->getParam('providerbilling_ids');
                $providerbilling_notes = $this->getRequest()->getParam('notes_array');
                $providerbilling_internal_notes = $this->getRequest()->getParam('internal_notes_array');
                // $provider_id_array = array();
                $notes_array = array();
                $internal_notes_array = array();
                if (strlen($providerbilling_ids) > 0) {
                    $providerbilling_ids_array = explode(',', $providerbilling_ids);
                    $notes_array = explode(',', $providerbilling_notes);
                    $internal_notes_array = explode(',', $providerbilling_internal_notes);
                }
                $datas = array();
                $provider_id = 0;
                for ($i = 0; $i < count($providerbilling_ids_array); $i++) {
                    $providerbilling_id = $providerbilling_ids_array[$i];
                    $notes = $notes_array[$i];
                    $internal_notes = $internal_notes_array[$i];
                    $providerbilling['date_billed'] = date("Y-m-d");
                    $providerbilling['notes'] = $notes;
                    $providerbilling['internal_notes'] = $internal_notes;
                    $db_providerbilling = new Application_Model_DbTable_Providerbilling;
                    $db = $db_providerbilling->getAdapter();
                    $where = $db->quoteInto('id = ?', $providerbilling_id);
                    $providerbilling_data = $db_providerbilling->fetchRow($where);

                    try {
                        if ($db_providerbilling->update($providerbilling, $where)) {
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.date_billed', $tag['date_billed'], $providerbilling['date_billed']);
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.notes', $tag['notes'], $providerbilling['notes']);
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.internal_notes', $tag['internal_notes'], $providerbilling['internal_notes']);
                        }
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                }
                for ($k = 0; $k < count($myprovider_id_array); $k++) {
                    $provider_id = $myprovider_id_array[$k];
                    //  $provider_id = $providerbilling_data['provider_id'];
                    $data = $myprovider_data[$provider_id];
                    try {
                        $data['billingcompany_data'] = $billingcompany_data;
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                    array_push($datas, $data);
                }
                $ledgers = array();
                foreach ($datas as $data) {
                    $bill_period_data = array();
                    $provider_id = $data['id'];
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('providerbilling');
                    $select->where('providerbilling.amount_billed-providerbilling.amount_paid>0');
                    $select->where('providerbilling.provider_id = ?', $provider_id);
                    $select->where('providerbilling.date_billed >= ?', (date('Y') - 1) . '-' . date('m') . '-1');
                    //   $select->group('providerbilling.date_billed');
                    try {
                        $providerbillingList = $db->fetchAll($select);
                    } catch (Exception $e) {
                        echo $e->message();
                    }
                    $ledger = array();
                    $ledger = $providerbillingList;
                    $total_amount_billed = 0;
                    $total_amount_paid = 0;
                    $due = 0;
                    for ($i = 0; $i < count($ledger); $i++) {
                        $dif = $ledger[$i]['amount_billed'] - $ledger[$i]['amount_paid'];
                        if ($dif <= 0) {
                            
                        } else {
                            $due+=$dif;
                        }
                    }
                    for ($i = 0; $i < count($ledger); $i++) {
                        $bill_period_Array = explode('/', $ledger[$i]['bill_period']);
                        $ledger[$i]['bill_period_data'] = $bill_period_Array[0] + $bill_period_Array[1] * 100;
                    }
                    foreach ($ledger as $key => $value) {
                        $bill_period_data[$key] = $value['bill_period_data'];
                        // $provider_name[$key]=$value['provider_name'];
                    }

                    array_multisort($bill_period_data, SORT_NUMERIC, SORT_DESC, $ledger);
                    $ledger[count($ledger)]['balance'] = $due;
                    array_push($ledgers, $ledger);
                }



                if (sizeof($datas) != 0) {
//                    for($m=0;$m<count($data);$m){
//                        $temp_providerbillingList=$ledgers[$m];
//                        for($i=0;$i<count($temp_providerbillingList);$i++)
//                       {
//                          $bill_period_Array=explode('/', $temp_providerbillingList[$i]['bill_period']);
//                          $temp_providerbillingList[$i]['bill_period_data']=$bill_period_Array[0]+$bill_period_Array[1]*100;
//                       }  
//                       foreach($temp_providerbillingList as $key => $value ){
//                           $bill_period_data[$key] = $value['bill_period_data']; 
//                           $provider_name[$key]=$value['provider_name'];
//                       }
//                       array_multisort($provider_name,SORT_STRING,SORT_ASC,$bill_period_data,SORT_NUMERIC,SORT_DESC,$temp_providerbillingList);
//                       $ledgers[$m]=$temp_providerbillingList;
//                    }
                    $billingcompany_id = $this->billingcompany_id;
                    $filename = gen_providerbilling_pdf($datas, $ledgers, $this->sysdoc_path, $billingcompany_id);
                    $_SESSION['downloadfilename'] = $filename;
                }
                $this->_redirect('/biller/data/providerbillinglist');
            } else if ($submitType == "save") {
                $count = $this->getRequest()->getParam('count');
                for ($i = 0; $i < $count; $i++) {
                    $providerbilling_id = $this->getRequest()->getPost('providerbilling_id_' . $i);
                    $amount_paid = $this->getRequest()->getPost('amount_paid_' . $i);
                    $date_paid = $this->getRequest()->getPost('date_paid_' . $i);
                    $notes = $this->getRequest()->getPost('notes_' . $i);
                    $internal_notes = $this->getRequest()->getPost('internal_notes_' . $i);
                    $providerbilling = array();
                    $db_providerbilling = new Application_Model_DbTable_Providerbilling;
                    $db = $db_providerbilling->getAdapter();
                    $where = $db->quoteInto('id = ?', $providerbilling_id);
                    $tag = $db_providerbilling->fetchRow($where);
                    $providerbilling['notes'] = $notes;
                    $providerbilling['amount_paid'] = $amount_paid;
                    $providerbilling['date_paid'] = format($date_paid, 0);
                    $providerbilling['internal_notes'] = $internal_notes;
                    try {
                        if ($db_providerbilling->update($providerbilling, $where)) {
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.notes', $tag['notes'], $providerbilling['notes']);
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.amount_paid', $tag['amount_paid'], $providerbilling['amount_paid']);
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.date_paid', $tag['date_paid'], $providerbilling['date_paid']);
                            $this->adddatalog($tag['provider_id'], $tag['bill_period'], 'providerbilling.internal_notes', $tag['internal_notes'], $providerbilling['internal_notes']);
                        }
                    } catch (Exception $e) {
                        echo $e->getMessage();
                    }
                }
                $this->_redirect('/biller/data/providerbillinglist');
            }
        }
    }

    function providerbillinginfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $temp = $this->data_format_month($rows, 0);
        $month = array();
        for ($i = 0; $i < 12; $i++) {
            $month[$i] = $temp[$i]['Month'];
        }
        $provider_id = $_POST['id'];
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('providerbilling');
        $select->where('providerbilling.provider_id = ?', $provider_id);
        $select->where('providerbilling.date_billed >= ?', (date('Y') - 1) . '-' . date('m') . '-1');
        // $select->group('providerbilling.date_billed');
        try {
            $providerbillingList = $db->fetchAll($select);
        } catch (Exception $e) {
            echo $e->message();
        }

        $data = array();
        $data = $providerbillingList;
        $total_amount_billed = 0;
        $total_amount_paid = 0;
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]["date_billed"] = format($data[$i]["date_billed"], 1);
            $data[$i]["date_paid"] = format($data[$i]["date_paid"], 1);
            if (($data[$i]['amount_billed'] - $data[$i]['amount_paid']) <= 0) {
                
            } else {
                $dif = $data[$i]['amount_billed'] - $data[$i]['amount_paid'];
                $due+=$dif;
            }
        }
        for ($i = 0; $i < count($data); $i++) {
            $bill_period_Array = explode('/', $data[$i]['bill_period']);
            $data[$i]['bill_period_data'] = $bill_period_Array[0] + $bill_period_Array[1] * 100;
        }
        foreach ($data as $key => $value) {
            $bill_period_data[$key] = $value['bill_period_data'];
            //$provider_name[$key]=$value['provider_name'];
        }
        array_multisort($bill_period_data, SORT_NUMERIC, SORT_DESC, $data);
//        $due = $total_amount_billed - $total_amount_paid;
        $data[count($data)]['due'] = $due;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * referringproviderAction
     * a function for processing the referringprovider.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function referringproviderAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRef.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,referringprovider_id FROM providerhasreferringprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRef
ON hasRef.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p ,referringprovider ref 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.referringprovider_id=ref.id) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        //the where statement in sql above should be :        WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.referringprovider_id=ref.id and ref.last_name <> 'Need New') 
        //then to fix bug 31, deleted "and ref.last_name <> 'Need New'"
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p,referringprovider ref 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' and hs.referringprovider_id=ref.id) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        //the where statement in sql above should be :    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' and hs.referringprovider_id=ref.id and ref.last_name <> 'Need New') 
        //then to fix bug 31, deleted "and ref.last_name <> 'Need New'"
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;

        $this->view->allNumRef = array('num' => $all);
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        $salutations = $billingcompany_data['salutations'];

        $salutationsList = explode('|', $salutations);
        $enth = strlen($salutationsList);
        if ($salutationsList[0] == null && strlen($salutationsList) == 0) {
            $salutationsList = null;
        }
        $this->view->SalutationsList = $salutationsList;
        if ($this->getRequest()->isPost()) {
//            $provider_id = $this->getRequest()->getPost('provider_id');
            $submitType = $this->getRequest()->getParam('submit');
            $provider_id = $this->getRequest()->getPost('provider_id');
            $reffering_id = $this->getRequest()->getPost('id');
            if ($submitType == "Update") {
                $provider_id = $this->getRequest()->getPost('provider_id');

                session_start();
                $_SESSION['management_data']['provider_id'] = $provider_id;

                $reffering_id = $this->getRequest()->getPost('id');
                $referringprovider = array();
                $id = $this->getRequest()->getPost('id');
                $referringprovider['NPI'] = $this->getRequest()->getPost('NPI');
                $referringprovider['salutation'] = $this->getRequest()->getPost('salutation');
                $db_referringprovider = new Application_Model_DbTable_Referringprovider();
                $db = $db_referringprovider->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldrefer = $db_referringprovider->fetchAll($where)->toArray();

                if ($db_referringprovider->update($referringprovider, $where)) {
                    $this->adddatalog($provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'referringprovider.NPI', $oldrefer[0]['NPI'], $referringprovider['NPI']);
                }
                $referringproviderhas = array();
                $referringproviderhas['status'] = $this->getRequest()->getPost('status');
                $db_referringproviderhas = new Application_Model_DbTable_Providerhasreferringprovider();
                if ($provider_id == 0) {
                    $del_provider_id = $this->getRequest()->getPost('del_provider_id');

                    if ($del_provider_id == 0) {
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();

                        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                        $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $provider_id = $provider_data[$i]['id'];
                            $wherehas = $db->quoteInto('provider_id= ?', $provider_id) . $db->quoteInto('and referringprovider_id=?', $reffering_id);
                            $oldreferhas = $db_referringproviderhas->fetchAll($wherehas)->toArray();

                            if ($db_referringproviderhas->update($referringproviderhas, $wherehas)) {
                                $this->adddatalog($provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', $oldreferhas[0]['status'], $referringproviderhas['status']);
                            }
                        }
                    } else {
                        $wherehas = $db->quoteInto('provider_id= ?', $del_provider_id) . $db->quoteInto('and referringprovider_id=?', $reffering_id);
                        $oldreferhas = $db_referringproviderhas->fetchAll($wherehas)->toArray();
                        if ($db_referringproviderhas->update($referringproviderhas, $wherehas)) {
                            $this->adddatalog($del_provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', $oldreferhas[0]['status'], $referringproviderhas['status']);
                        }
                    }
                } else {

                    $wherehas = $db->quoteInto('provider_id= ?', $provider_id) . $db->quoteInto('and referringprovider_id=?', $reffering_id);
                    $oldreferhas = $db_referringproviderhas->fetchAll($wherehas)->toArray();

                    if ($db_referringproviderhas->update($referringproviderhas, $wherehas)) {
                        $this->adddatalog($provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', $oldreferhas[0]['status'], $referringproviderhas['status']);
                    }
                }
                $this->_redirect('/biller/data/referringprovider');
            }

            if ($submitType == "New")
                $this->_redirect('/biller/data/newreferringprovider');
            if ($submitType == "UPLOAD") {
                $provider_id = $this->getRequest()->getPost('provider_id');
                $desc = $this->getRequest()->getParam('desc');
                if ($desc == "" || $desc == null) {
                    $this->_redirect('/biller/data/referringprovider');
                }
                $adapter = new Zend_File_Transfer_Adapter_Http();
                if ($adapter->isUploaded()) {
                    $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                    if (!is_dir($dir_billingcompany)) {
                        mkdir($dir_billingcompany);
                    }
                    if ($provider_id != '0') {
                        $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                        if (!is_dir($dir_provider)) {
                            mkdir($dir_provider);
                        }
                        $dir_referprovider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'referringprovider';
                        if (!is_dir($dir_referprovider)) {
                            mkdir($dir_referprovider);
                        }
                        $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'referringprovider/' . $reffering_id;
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                        $today = date("Y-m-d H:i:s");
                        $date = explode(' ', $today);
                        $time0 = explode('-', $date[0]);
                        $time1 = explode(':', $date[1]);
                        $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $file_name = $time . '-' . $desc . '-' . $user_name;
                        $old_filename = $adapter->getFileName();
                        $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                        $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                        $adapter->setDestination($dir);
                        $db_rprovider = new Application_Model_DbTable_Referringprovider();
                        $db = $db_rprovider->getAdapter();
                        $where = $db->quoteInto("id=?", $reffering_id);
                        $rprovider_data = $db_rprovider->fetchRow($where);
                        $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        if (!$adapter->receive()) {
                            $messages = $adapter->getMessages();
                            echo implode("n", $messages);
                        } else {
                            $this->adddatalog($provider_id, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
                            $this->_redirect('/biller/data/referringprovider');
                        }
                    } else {
                        $billingcompany_id = $this->billingcompany_id();
                        $db_provider = new Application_Model_DbTable_Provider();
                        $db = $db_provider->getAdapter();
                        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
                        $provider_list = $db_provider->fetchAll($where);
                        $count = 0;
                        $first_copy = "";
                        $first_copy_name = "";
                        foreach ($provider_list as $provider) {
                            $provider_id = $provider_list[$count]['id'];
                            $dir_provider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id;
                            if (!is_dir($dir_provider)) {
                                mkdir($dir_provider);
                            }
                            $dir_referprovider = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'referringprovider';
                            if (!is_dir($dir_referprovider)) {
                                mkdir($dir_referprovider);
                            }
                            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id . '/' . 'referringprovider/' . $reffering_id;
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            if ($count == 0) {
                                $today = date("Y-m-d H:i:s");
                                $date = explode(' ', $today);
                                $time0 = explode('-', $date[0]);
                                $time1 = explode(':', $date[1]);
                                $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                                $user = Zend_Auth::getInstance()->getIdentity();
                                $user_name = $user->user_name;
                                $file_name = $time . '-' . $desc . '-' . $user_name;
                                $old_filename = $adapter->getFileName();
                                $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                                $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                                $adapter->setDestination($dir);
                                $adapter->receive();
                                $first_copy = $dir . '/' . $file_name . $file_extension;
                                $first_copy_name = $file_name . $file_extension;
                            } else {
                                $dest = $dir . '/' . $first_copy_name;
                                copy($first_copy, $dest);
                            }
                            $count++;
                        }
                        $db_rprovider = new Application_Model_DbTable_Referringprovider();
                        $db = $db_rprovider->getAdapter();
                        $where = $db->quoteInto("id=?", $reffering_id);
                        $rprovider_data = $db_rprovider->fetchRow($where);
                        $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                        $log_dbfield = $desc;
                        $log_newvalue = 'Document Uploaded';
                        $this->adddatalog(0, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
                        $this->_redirect('/biller/data/referringprovider');
                    }
                }
            }
        }
    }

    /**
     * renderingproviderexistlist
     * a function for creating a new referringprovider.
     * @author caijun.
     * @version 05/15/2012
     */
    public function renderingproviderexistlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        //$provider_id = $this->getRequest()->getPost('diagnosis_code');
        if (empty($provider_id) && $provider_id != '0') {
            $data = array();
            $exist_data = array();
            $data['exist_data'] = $exist_data;
            $json = Zend_Json::encode($data);
            echo $json;
            return;
        }
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();

        $sql = <<<SQL
    select min(provider_id),renderingprovider.id,last_name,first_name
   from renderingprovider left join providerhasrenderingprovider on renderingprovider_id = renderingprovider.id
   where renderingprovider.id not in 
   (
        select renderingprovider_id from providerhasrenderingprovider where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   group by renderingprovider.id
   order by last_name,first_name

SQL;
        $paras = array($provider_id, $billingcompany_id);
        $result = $db->query($sql, $paras);
        $exist_data = $result->fetchAll();

        $data = array();
        if ($provider_id == '0') {
            session_start();
            $all_renderingproviderList = $_SESSION['renderingproviderList'];
            for ($mark = 0; $mark < count($all_renderingproviderList); $mark ++) {
                $all_renderingprovider_id_List[$mark] = $all_renderingproviderList[$mark]['id'];
            }

            $tp_exist_data = array();
            $addmark = 0;
            for ($mark = 0; $mark < count($exist_data); $mark ++) {
                if (!(in_array($exist_data[$mark]['id'], $all_renderingprovider_id_List))) {
//                    array_splice($exist_data, $mark, 1);
                    $tp_exist_data[$addmark] = $exist_data[$mark];
                    $addmark++;
                }
            }

            $data['exist_data'] = $tp_exist_data;
        } else {
            $data['exist_data'] = $exist_data;
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * referringproviderexistlistAction
     * a function for creating a new referringprovider.
     * @author caijun.
     * @version 05/15/2012
     */
    public function referringproviderexistlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        //$provider_id = $this->getRequest()->getPost('diagnosis_code');
        if (empty($provider_id) && $provider_id != '0') {
            $data = array();
            $exist_data = array();
            $data['exist_data'] = $exist_data;
            $json = Zend_Json::encode($data);
            echo $json;
            return;
        }
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();

        $sql = <<<SQL

   select min(provider_id),referringprovider.id,last_name,first_name
   from referringprovider left join providerhasreferringprovider on referringprovider_id = referringprovider.id
   where referringprovider.id not in 
   (
        select referringprovider_id from providerhasreferringprovider where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   group by referringprovider.id
   order by last_name,first_name
SQL;
        $paras = array($provider_id, $billingcompany_id);
        $result = $db->query($sql, $paras);

        $exist_data = $result->fetchAll();


//        for($mark = 0; $mark < count($all_referringproviderList); $mark ++){
//            $all_referringprovide_id_List[$mark] = $all_referringproviderList[$mark]['id'];
//        }
        $data = array();

        if ($provider_id == '0') {
            session_start();
            $all_referringproviderList = $_SESSION['referringall'];
            $final_exist_data = array();
            $add_mark = 0;
            for ($mark = 0; $mark < count($exist_data); $mark ++) {
                if (!(in_array($exist_data[$mark]['id'], $all_referringproviderList))) {
//                    array_splice($exist_data, $mark, 1); 
                    $final_exist_data[$add_mark] = $exist_data[$mark];
                    $add_mark ++;
                }
            }
            $data['exist_data'] = $final_exist_data;
        } else {
            $data['exist_data'] = $exist_data;
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newreferringproviderAction
     * a function for creating a new referringprovider.
     * @author caijun.
     * @version 05/15/2012
     */
    public function newreferringproviderAction() {
        //$this->view->providerList = $provider;
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasRef.provider_id) num
FROM provider p LEFT JOIN 
(
    SELECT provider_id,referringprovider_id FROM providerhasreferringprovider has ,provider p 
    WHERE( has.provider_id = p.id AND p.billingcompany_id =?)
    
       ORDER BY provider_id
) hasRef
ON hasRef.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='inactive'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p ,referringprovider ref 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.referringprovider_id=ref.id ) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='inactive' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        //the where statement in the SQL above should be :
        //    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.referringprovider_id=ref.id and ref.last_name <> 'Need New') 
        // to modify, deleted and ref.last_name <> 'Need New'
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasreferringprovider has ON p.id = has.provider_id 
WHERE billingcompany_id = ? and status='active'
AND has.referringprovider_id in 
(
SELECT t.id FROM 
(
    SELECT referringprovider_id id ,count(*) counts, status FROM providerhasreferringprovider hs,provider p ,referringprovider ref 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='active' and hs.referringprovider_id=ref.id) 
    GROUP BY referringprovider_id
) t 
WHERE 
(t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?) and status='active' )
) 
GROUP BY p.id LIMIT 1
SQL
        ;
        //the where statement in the SQL above should be :
        //    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and status='inactive' and hs.referringprovider_id=ref.id and ref.last_name <> 'Need New') 
        // to modify, deleted and ref.last_name <> 'Need New'
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data1 = $result->fetchAll();
        if (isset($all_data[0])) {
            $alldata = $all_data[0][num];
        } else {
            $alldata = 0;
        }
        if (isset($all_data1[0])) {
            $alldata1 = $all_data1[0][num];
        } else {
            $alldata1 = 0;
        }
        $all = $alldata + $alldata1;
        $this->view->allNumRef = array('num' => $all);
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id);
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        $salutations = $billingcompany_data['salutations'];

        $salutationsList = explode('|', $salutations);
        $enth = strlen($salutationsList);
        if ($salutationsList[0] == null && strlen($salutationsList) == 0) {
            $salutationsList = null;
        }
        $this->view->SalutationsList = $salutationsList;
        if ($this->getRequest()->isPost()) {
            $new_or_import = $this->getRequest()->getPost('import_or_new');

            if ($new_or_import == 'import') {
                $db_providerhasreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
                $db = $db_providerhasreferringprovider->getAdapter();
                $providerhasreferringprovider = array();
                $providerhasreferringprovider['referringprovider_id'] = $this->getRequest()->getPost('last_name');
                $referringprovider_get = $providerhasreferringprovider['referringprovider_id'];
                $providerid_get = $this->getRequest()->getPost('provider_id');
                $providerhasreferringprovider['provider_id'] = $providerid_get;
                $providerhasreferringprovider['status'] = $this->getRequest()->getPost('status');
                $db_referringprovider = new Application_Model_DbTable_Referringprovider();
                $dbrefer = $db_referringprovider->getAdapter();
                $whererefer = $dbrefer->quoteInto('id = ?', $referringprovider_get);
                $oldrefer = $db_referringprovider->fetchAll($whererefer)->toArray();

                //$table = 'providerhasdiagnosiscode'; 
                if ($providerid_get >= 0 && $referringprovider_get) {
                    if ($providerhasreferringprovider['provider_id'] == 0) {
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $providerhasreferringprovider['provider_id'] = $provider_data[$i]['id'];
//                            $db_provider = new Application_Model_DbTable_Provider();
//                            $db = $db_provider->getAdapter();
//                            $wherein=$db->quoteInto('id = ?',$provider_data[$i]['id']); 
//                            $oldprovider=$db_provider->fetchAll($wherein)->toArray();
                            $where = $db->quoteInto("provider_id = ?", $providerhasreferringprovider['provider_id']) . $db->quoteInto("and referringprovider_id = ?", $providerhasreferringprovider['referringprovider_id']);
                            $ext = $db_providerhasreferringprovider->fetchAll($where);
                            if (isset($ext[0])) {
                                if ($db_providerhasreferringprovider->update($providerhasreferringprovider, $where)) {

                                    $this->adddatalog($provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', $ext[0]['status'], $providerhasreferringprovider['status']);
                                }
                            } else {
                                if ($db_providerhasreferringprovider->insert($providerhasreferringprovider)) {
                                    $this->adddatalog($provider_id, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', NULL, $providerhasreferringprovider['status']);
                                }
                            }
                        }
                    } else {
                        $providerhasreferringprovider['provider_id'] = $providerid_get;
                        //$db->insert($table,$providerhasdiagnosiscode);
                        if ($db_providerhasreferringprovider->insert($providerhasreferringprovider)) {
                            $this->adddatalog($providerid_get, $oldrefer[0]['last_name'] . ' ' . $oldrefer[0]['first_name'], 'providerhasreferringprovider.status', NULL, $providerhasreferringprovider['status']);
                        }
                    }
                } else {
                    $this->_redirect('/biller/data/newreferringprovider');
                }
            }
            if ($new_or_import == 'new') {
                $db_ferringprovider = new Application_Model_DbTable_Referringprovider();
                $db = $db_ferringprovider->getAdapter();
                //get the new diagnosiscode 
                $ferringprovidernew = array();
                $ferringprovidernew['last_name'] = $this->getRequest()->getPost('add_last_name');
                $ferringprovidernew['first_name'] = $this->getRequest()->getPost('add_first_name');
                $ferringprovidernew_get = $ferringprovidernew['last_name'];
                $providerid_get = $this->getRequest()->getPost('provider_id');
                $ferringprovidernew['NPI'] = $this->getRequest()->getPost('NPI');
                $ferringprovidernew['salutation'] = $this->getRequest()->getPost('salutation');
                //$providerhasreferringprovidernew['status'] = $this->getRequest()->getPost('status');
                //juge if exist the diagnosiscode in the diagnosiscode table
                if ($providerid_get >= 0 && $ferringprovidernew_get != '') {
                    $where = $db->quoteInto("last_name = ?", $ferringprovidernew['last_name']) . $db->quoteInto("and first_name = ?", $ferringprovidernew['first_name']) . $db->quoteInto("and NPI = ?", $ferringprovidernew['NPI']);
                    $exsit = $db_ferringprovider->fetchAll($where);
                    $exsitlast = array();
                    $provder_ids = array();
                    if (isset($exsit[0])) {
                        $db_hasexisting = new Application_Model_DbTable_Providerhasreferringprovider();
                        $dbexsit = $db_hasexisting->getAdapter();
                        for ($i = 0; $i < count($provider_data); $i++) {
                            $provder_ids[$i] = $provider_data[$i]['id'];
                        }
                        $wherehasexisting = $dbexsit->quoteInto("referringprovider_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

                        $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                    }
                    //if there exsit the diagnosiscode,get the diagnosiscode_id
                    if (isset($exsitlast[0])) {
                        //$provierhasfacility['facility_id']=$exsit[0]['id'];
                        echo '<span style="color:red;font-size:16px">Sorry ! The  Referringprovider is existing , please use Import !</span>';
                    } else {
 
                        $providerhasreferringprovider['referringprovider_id'] = $db_ferringprovider->insert($ferringprovidernew);
                        if ($providerhasreferringprovider['referringprovider_id']) {

                            $this->adddatalog($providerid_get, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'referringprovider.last_name', NULL, $ferringprovidernew['last_name']);
                            $this->adddatalog($providerid_get, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'referringprovider.first_name', NULL, $ferringprovidernew['first_name']);
                            $this->adddatalog($providerid_get, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'referringprovider.NPI', NULL, $ferringprovidernew['NPI']);
                            $this->adddatalog($providerid_get, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'referringprovider.salutation', NULL, $ferringprovidernew['salutation']);
                        }
//                    }
                        //complete the new providerhasdiagnosiscode
                        $providerhasreferringprovider['provider_id'] = $this->getRequest()->getPost('provider_id');
                        $providerhasreferringprovider['status'] = $this->getRequest()->getPost('status');
                        $db_providerhasreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
                        $db = $db_providerhasreferringprovider->getAdapter();

                        if ($providerhasreferringprovider['provider_id'] == 0) {

                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provider_id = $provider_data[$i]['id'];
                                $providerhasreferringprovider['provider_id'] = $provider_data[$i]['id'];

                                $where = $db->quoteInto("provider_id = ?", $providerhasreferringprovider['provider_id']) . $db->quoteInto("and referringprovider_id = ?", $providerhasreferringprovider['referringprovider_id']);
                                $ext = $db_providerhasreferringprovider->fetchAll($where);
                                if (isset($ext[0])) {
                                    
                                } else {
                                    if ($db_providerhasreferringprovider->insert($providerhasreferringprovider)) {
                                        $this->adddatalog($provider_id, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'providerhasreferringprovider.status', NULL, $providerhasreferringprovider['status']);
                                    }
                                }
                            }
                        } else {
                            $provider_id = $this->getRequest()->getPost('provider_id');
                            $providerhasreferringprovider['provider_id'] = $this->getRequest()->getPost('provider_id');

                            $where = $db->quoteInto("provider_id = ?", $providerhasreferringprovider['provider_id']) . $db->quoteInto("and referringprovider_id = ?", $providerhasreferringprovider['referringprovider_id']);
                            $extfer = $db_providerhasreferringprovider->fetchAll($where);
                            $TEMP = isset($extfer[0]);
                            if (!$TEMP) {
                                if ($db_providerhasreferringprovider->insert($providerhasreferringprovider)) {
                                    $this->adddatalog($provider_id, $ferringprovidernew['last_name'] . ' ' . $ferringprovidernew['first_name'], 'providerhasreferringprovider.status', NULL, $providerhasreferringprovider['status']);
                                }
                            }
                        }
                        $this->_redirect('/biller/data/referringprovider');
                    }
                } else {
                    $this->_redirect('/biller/data/newreferringprovider');
                }
            }
        }
    }

    /**
     * referringproviderAction
     * a function returning the referringprovider data for displaying on the page.
     * @author Haowei.
     * @return the referringprovider data for displaying on the page
     * @version 05/15/2012
     */
    public function referringproviderinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['provider_id'];
        $rid = $_POST['id'];
        if ($id != 0) {
            $rprovider_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $id . '/referringprovider/' . $rid;
            $doc_paths = array();
            if (is_dir($rprovider_doc_path)) {
                foreach (glob($rprovider_doc_path . '/*.*') as $filename) {
                    array_push($doc_paths, $filename);
                }
            }
            $rprovider_doc_list = array();
            $i = 0;
            foreach ($doc_paths as $path) {
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
                $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
                $rprovider_doc_list[$i]['date'] = $date;
                $rprovider_doc_list[$i]['desc'] = $temp[1];
                $rprovider_doc_list[$i]['user'] = $temp[2];
                $count = count($temp);
                $n = 3;
                if ($count > 3) {
                    for ($n; $n < $count; $n++) {
                        $rprovider_doc_list[$i]['user'] = $rprovider_doc_list[$i]['user'] . '-' . $temp[$n];
                    }
                }
                $rprovider_doc_list[$i]['url'] = $path;
                $i++;
            }
        } else {
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
            $provider_list_temp = $db_provider->fetchAll($where);
            $rprovider_doc_list = array();
            $i = 0;
            $j = 0;
            foreach ($provider_list_temp as $provider_temp) {
                $provider_id_temp = $provider_temp['id'];
                $rprovider_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . $provider_id_temp . '/referringprovider/' . $rid;
                $doc_paths = array();
                if (is_dir($rprovider_doc_path)) {
                    foreach (glob($rprovider_doc_path . '/*.*') as $filename) {
                        array_push($doc_paths, $filename);
                    }
                }
                foreach ($doc_paths as $path) {
                    $temp = explode("/", $path);
                    $temp = explode(".", $temp[count($temp) - 1]);
                    $filename = $temp[0];
                    $temp = explode("-", $filename);
                    if ($j == 0) {
                        $rprovider_doc_list[$i] = $this->getDocPara($temp, $path);
                        $rprovider_doc_list[$i]['filename'] = $filename;
                        $i++;
                    } else {
                        $judge = 1;
                        foreach ($rprovider_doc_list as $rprovider_test) {
                            if ($rprovider_test['filename'] == $filename) {
                                $judge = 0;
                            }
                        }
                        if ($judge == 1) {
                            $rprovider_doc_list[$i] = $this->getDocPara($temp, $path);
                            $rprovider_doc_list[$i]['filename'] = $filename;
                            $i++;
                        }
                    }
                }
                $j++;
            }
        }

        if ($id == 0) {
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
            $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
            $id = $provider_data[0]['id'];
        }
        $db_referringprovider = new Application_Model_DbTable_Referringprovider();
        $db = $db_referringprovider->getAdapter();
        $where = $db->quoteInto('id = ?', $rid);
        $referringprovider_data = $db_referringprovider->fetchRow($where);

        $db_providerhasreferringprovider = new Application_Model_DbTable_Providerhasreferringprovider();
        $db = $db_providerhasreferringprovider->getAdapter();
        $wherehas = $db->quoteInto('provider_id = ?', $id) . $db->quoteInto('and referringprovider_id= ?', $rid);
        $providerhasreferringprovider_data = $db_providerhasreferringprovider->fetchRow($wherehas);



        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;

        $data = array();
        $data = array('provider_id' => $providerhasreferringprovider_data['provider_id'], 'status' => $providerhasreferringprovider_data['status'], 'last_name' => $referringprovider_data['last_name'], 'salutation' => $referringprovider_data['salutation'], 'first_name' => $referringprovider_data['first_name'], 'NPI' => $referringprovider_data['NPI'], 'rprovider_doc_list' => $rprovider_doc_list, 'user_name' => $user_name);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function renderingexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
        $provider_ids = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $provider_ids[$i] = $provider_data[$i]['id'];
        }
        $db_Renderingprovider = new Application_Model_DbTable_Renderingprovider();
        $db = $db_Renderingprovider->getAdapter();

        $renderingprovider = array();
        $provider_id = $this->getRequest()->getPost('provider_id');

        $renderingprovider['last_name'] = $this->getRequest()->getPost('add_last_name');
        $renderingprovider['first_name'] = $this->getRequest()->getPost('add_first_name');
        //$renderingprovider['street_address'] = $this->getRequest()->getPost('street_address');
        // $renderingprovider['zip'] = $this->getRequest()->getPost('zip');
        $renderingprovider['NPI'] = $this->getRequest()->getPost('NPI');
        $renderingprovider['salutation'] = $this->getRequest()->getPost('salutation');
        $where = $db->quoteInto("last_name = ?", $renderingprovider['last_name']) . $db->quoteInto("and first_name = ?", $renderingprovider['first_name']) . $db->quoteInto("and NPI = ?", $renderingprovider['NPI']) . $db->quoteInto("and salutation = ?", $renderingprovider['salutation']);
        $exsit = $db_Renderingprovider->fetchAll($where);
        $exsithas = array();
        $provder_ids = array();
        $exsitlast = array();
        if (isset($exsit[0])) {
            $db_hasexisting = new Application_Model_DbTable_Providerhasrenderingprovider();
            $dbexsit = $db_hasexisting->getAdapter();
            for ($i = 0; $i < count($provider_data); $i++) {
                $provder_ids[$i] = $provider_data[$i]['id'];
            }
            $wherehasexisting = $dbexsit->quoteInto("renderingprovider_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provider_ids);
            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
        }
//                        //if there exsit the diagnosiscode,get the diagnosiscode_id
        $data = array();
        if (isset($exsitlast[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function referringexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        //get provider data 
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
        $provider_ids = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $provider_ids[$i] = $provider_data[$i]['id'];
        }
        //test existing or not 
        $db_ferringprovider = new Application_Model_DbTable_Referringprovider();
        $db = $db_ferringprovider->getAdapter();

        $ferringprovidernew = array();
        $ferringprovidernew['last_name'] = $this->getRequest()->getPost('add_last_name');
        $ferringprovidernew['first_name'] = $this->getRequest()->getPost('add_first_name');
        $ferringprovidernew_get = $ferringprovidernew['last_name'];
        $providerid_get = $this->getRequest()->getPost('provider_id');
        $ferringprovidernew['NPI'] = $this->getRequest()->getPost('NPI');
        $ferringprovidernew['salutation'] = $this->getRequest()->getPost('salutation');
        $where = $db->quoteInto("last_name = ?", $ferringprovidernew['last_name']) . $db->quoteInto("and first_name = ?", $ferringprovidernew['first_name']) . $db->quoteInto("and NPI = ?", $ferringprovidernew['NPI']) . $db->quoteInto("and salutation = ?", $ferringprovidernew['salutation']);
        $exsit = $db_ferringprovider->fetchAll($where);
        $exsithas = array();
        $provder_ids = array();

        if (isset($exsit[0])) {
            $db_hasexisting = new Application_Model_DbTable_Providerhasreferringprovider();
            $dbexsit = $db_hasexisting->getAdapter();
            for ($i = 0; $i < count($provider_data); $i++) {
                $provder_ids[$i] = $provider_data[$i]['id'];
            }
            $wherehasexisting = $dbexsit->quoteInto("referringprovider_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);
            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
        }
        //if there exsit the diagnosiscode,get the diagnosiscode_id

        $data = array();
        if (isset($exsitlast[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function claimbillingcompanyAction() {
        $billingcompany_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'doc';
        $doc_paths = array();
        if (is_dir($billingcompany_doc_path)) {
            foreach (glob($billingcompany_doc_path . '/*.*') as $filename) {
                array_push($doc_paths, $filename);
            }
        }
        $billingcompany_doc_list = array();
        $i = 0;
        foreach ($doc_paths as $path) {
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
            $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
            $billingcompany_doc_list[$i]['date'] = $date;
            $billingcompany_doc_list[$i]['desc'] = $temp[1];
            $billingcompany_doc_list[$i]['user'] = $temp[2];
            $count = count($temp);
            $n = 3;
            if ($count > 3) {
                for ($n; $n < $count; $n++) {
                    $billingcompany_doc_list[$i]['user'] = $billingcompany_doc_list[$i]['user'] . '-' . $temp[$n];
                }
            }
            $billingcompany_doc_list[$i]['url'] = $path;
            $i++;
        }
        $this->view->billingcompany_doc_list = $billingcompany_doc_list;
    }

    /**
     * billingcompanyAction
     * a function for processing the billingcompany data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function deletebillingdocsAction() {
        $this->_helper->viewRenderer->setNoRender();
        $url = $_POST['url'];
        $type = $_POST['type'];
        $subtype = $_POST['subtype'];
//                "patientcorrespondence"
        $data = array();
        if (file_exists($url)) {
            unlink($url);
            $fileparas = explode('/', $url);
            $length = count($fileparas);
            $file_name = $fileparas[$length - 1];
            $file_name_paras = explode('-', $file_name);
            $file_name_paras_length = count($file_name_paras);
            $desc = $file_name_paras[1];
            $result = "true";
//            $billingcompany_id = $this->
            if (($type == 'billingcompany') && ($subtype != "patientcorrespondence")) {
                $billingcompany_id = $fileparas[$length - 3];
                $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                $db = $db_billingcompany->getAdapter();
                $where = $db->quoteInto('id = ?', $billingcompany_id);
                $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
                $log_billingcompany_name = $billingcompany_data['billingcompany_name'];
                $log_db_field = $desc;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog(-1, $log_billingcompany_name, $log_db_field, null, $log_newvalue);
            }
            if (($type == 'billingcompany') && ($subtype == "patientcorrespondence")) {
                $billingcompany_id = $fileparas[$length - 4];
                $name = $_POST['deletefilename'];
                $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                $db = $db_billingcompany->getAdapter();
                $where = $db->quoteInto('id = ?', $billingcompany_id);
                $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
                $log_billingcompany_name = $billingcompany_data['billingcompany_name'];
                $log_db_field = $name;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog(-1, $log_billingcompany_name, $log_db_field, null, $log_newvalue);
                
                $db_Patientcorrespondence = new Application_Model_DbTable_Patientcorrespondence();
                $db = $db_Patientcorrespondence->getAdapter();
                $where = $db->quoteInto('template  =?', $name) . $db->quoteInto(' and billingcompany_id =?', $billingcompany_id);
                $db_Patientcorrespondence->delete($where);

            }
            if ($type == 'insurance') {
                $insurance_id = $fileparas[$length - 2];
                $db_insurance = new Application_Model_DbTable_Insurance();
                $db = $db_insurance->getAdapter();
                $where = $db->quoteInto("id=?", $insurance_id);
                $insurance_data = $db_insurance->fetchRow($where);
                $log_insurance_name = $insurance_data['insurance_display'];
                $log_dbfield = $desc;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog(-1, $log_insurance_name, $log_dbfield, null, $log_newvalue);
            }
            if ($type == 'provider') {
                $provider_id = $fileparas[$length - 3];
                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto("id = ?", $provider_id);
                $provider_data = $db_provider->fetchRow($where);
                $log_provider_name = $provider_data['provider_name'];
                $log_provider_id = $provider_id;
                $log_db_field = $desc;
                $log_newvalue = "DELETE";
                $this->adddatalog($log_provider_id, $log_provider_name, $log_db_field, null, $log_newvalue);
            }
            if ($type == 'facility') {
                $facility_id = $fileparas[$length - 2];
                $provider_id = $fileparas[$length - 4];
                $db_facility = new Application_Model_DbTable_Facility();
                $db = $db_facility->getAdapter();
                $where = $db->quoteInto("id=?", $facility_id);
                $facility_data = $db_facility->fetchRow($where);
                $log_facility_name = $facility_data['facility_name'];
                $log_dbfield = $desc;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog($provider_id, $log_facility_name, $log_dbfield, null, $log_newvalue);
            }
            if ($type == "render") {
                $provider_id = $fileparas[$length - 4];
                $render_id = $fileparas[$length - 2];
                $db_rprovider = new Application_Model_DbTable_Renderingprovider();
                $db = $db_rprovider->getAdapter();
                $where = $db->quoteInto("id=?", $render_id);
                $rprovider_data = $db_rprovider->fetchRow($where);
                $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                $log_dbfield = $desc;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog($provider_id, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
            }
            if ($type == "refer") {
                $provider_id = $fileparas[$length - 4];
                $refer_id = $fileparas[$length - 2];
                $db_rprovider = new Application_Model_DbTable_Referringprovider();
                $db = $db_rprovider->getAdapter();
                $where = $db->quoteInto("id=?", $refer_id);
                $rprovider_data = $db_rprovider->fetchRow($where);
                $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
                $log_dbfield = $desc;
                $log_newvalue = 'Document Deleted';
                $this->adddatalog($provider_id, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
            }
            /* insert the log or not?
             * $today = date("Y-m-d H:i:s");
              $interactionlogs_data['date_and_time'] = $today;
              $user = Zend_Auth::getInstance()->getIdentity();
              $user_name =  $user->user_name;
              $interactionlogs_data['log'] = $user_name.": BillingCompany Document delete" . $file_name;
              mysql_insert('interactionlog', $interactionlogs_data); */
        } else {
            $result = "false";
        }
        $data = array("result" => $result);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function deletefacilitydocallAction() {
        $this->_helper->viewRenderer->setNoRender();
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
        $provider_list_temp = $db_provider->fetchAll($where);
        $url = $_POST['url'];
        $temp = explode('/', $url);
        $count = count($temp);
        $filename = $temp[$count - 1];
        $file_name_paras = explode('-', $filename);
        $file_name_paras_length = count($file_name_paras);
        $desc = $file_name_paras[1];
        $facility_id = $temp[$count - 2];
        $data = array();
        $result = 'false';
        foreach ($provider_list_temp as $provider) {
            $provider_id = $provider['id'];
            $url_temp = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/facility/' . $facility_id . '/' . $filename;
            if (file_exists($url_temp)) {
                $result = unlink($url_temp);
            }
        }
        if ($result == 1) {
            $db_facility = new Application_Model_DbTable_Facility();
            $db = $db_facility->getAdapter();
            $where = $db->quoteInto("id=?", $facility_id);
            $facility_data = $db_facility->fetchRow($where);
            $log_facility_name = $facility_data['facility_name'];
            $log_dbfield = $desc;
            $log_newvalue = 'Document Deleted';
            $this->adddatalog(0, $log_facility_name, $log_dbfield, null, $log_newvalue);
            $result = 'true';
        }
        $data = array("result" => $result);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function deletereferproviderdocallAction() {
        $this->_helper->viewRenderer->setNoRender();
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
        $provider_list_temp = $db_provider->fetchAll($where);
        $url = $_POST['url'];
        $temp = explode('/', $url);
        $count = count($temp);
        $filename = $temp[$count - 1];
        $file_name_paras = explode('-', $filename);
        $file_name_paras_length = count($file_name_paras);
        $desc = $file_name_paras[1];
        $referprovider_id = $temp[$count - 2];
        $data = array();
        $result = 'false';
        foreach ($provider_list_temp as $provider) {
            $provider_id = $provider['id'];
            $url_temp = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/referringprovider/' . $referprovider_id . '/' . $filename;
            if (file_exists($url_temp)) {
                $result = unlink($url_temp);
            }
        }
        if ($result == 1) {
            $refer_id = $referprovider_id;
            $db_rprovider = new Application_Model_DbTable_Referringprovider();
            $db = $db_rprovider->getAdapter();
            $where = $db->quoteInto("id=?", $refer_id);
            $rprovider_data = $db_rprovider->fetchRow($where);
            $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
            $log_dbfield = $desc;
            $log_newvalue = 'Document Deleted';
            $this->adddatalog(0, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
            $result = 'true';
        }
        $data = array("result" => $result);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function deleterenderproviderdocallAction() {
        $this->_helper->viewRenderer->setNoRender();
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto("billingcompany_id=?", $billingcompany_id);
        $provider_list_temp = $db_provider->fetchAll($where);
        $url = $_POST['url'];
        $temp = explode('/', $url);
        $count = count($temp);
        $filename = $temp[$count - 1];
        $file_name_paras = explode('-', $filename);
        $file_name_paras_length = count($file_name_paras);
        $desc = $file_name_paras[1];
        $renderprovider_id = $temp[$count - 2];
        $data = array();
        $result = 'false';
        foreach ($provider_list_temp as $provider) {
            $provider_id = $provider['id'];
            $url_temp = $this->sysdoc_path . '/' . $billingcompany_id . '/' . $provider_id . '/renderprovider/' . $renderprovider_id . '/' . $filename;
            if (file_exists($url_temp)) {
                $result = unlink($url_temp);
            }
        }
        if ($result == 1) {
            $render_id = $renderprovider_id;
            $db_rprovider = new Application_Model_DbTable_Renderingprovider();
            $db = $db_rprovider->getAdapter();
            $where = $db->quoteInto("id=?", $render_id);
            $rprovider_data = $db_rprovider->fetchRow($where);
            $log_rprovider_name = $rprovider_data['last_name'] . ' ' . $rprovider_data['first_name'];
            $log_dbfield = $desc;
            $log_newvalue = 'Document Deleted';
            $this->adddatalog(0, $log_rprovider_name, $log_dbfield, null, $log_newvalue);
            $result = 'true';
        }
        $data = array("result" => $result);
        $json = Zend_Json::encode($data);
        echo $json;
    }
    
    public function patientcorrespondenceupdateAction() {
        $this->_helper->viewRenderer->setNoRender();
        $old_name = $this->getRequest()->getParam('oldname');
        $template = $this->getRequest()->getParam('newname');
        $url = $this->getRequest()->getParam('url');
        $variables = $this->getRequest()->getParam('variables');

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('patientcorrespondence', 'template');
        $select->where('patientcorrespondence.billingcompany_id=?', $this->billingcompany_id);
        $existingnamelist = $db->fetchAll($select);
        $existing_mark = "notExisting";
        for ($mark = 0; $mark < count($existingnamelist); $mark ++) {
            if ($template == $existingnamelist[$mark][template]) {
                $existing_mark = "existing";
                break;
            }
        }
        if ($existing_mark == "existing") {
            $data = array("result" => "nameexisting","templatename"=>$old_name);
            $json = Zend_Json::encode($data);
            echo $json;
        } else {
            $fileparas = explode('/', $url);
            $length = count($fileparas);
//        $file_name = $fileparas[$length - 1];
            $billingcompany_id = $fileparas[$length - 4];

            $db_Patientcorrespondence = new Application_Model_DbTable_Patientcorrespondence();
            $db = $db_Patientcorrespondence->getAdapter();
            $patientcorrespondence['template'] = $template;
            $patientcorrespondence['variables'] = $variables;
            $where = $db->quoteInto('template  =?', $old_name) . $db->quoteInto(' and billingcompany_id =?', $billingcompany_id);
            $db_Patientcorrespondence->update($patientcorrespondence, $where);

            $folder_path = substr($url, 0, strripos($url, '/'));
            rename($url, $folder_path . "/" . $template . ".docx");

            $data = array("result" => "success");
            $json = Zend_Json::encode($data);
            echo $json;
        }
    }
   
    public function billingcompanyAction() {

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
        $this->view->billingcompanyData = $billingcompany_data;

        $billingcompany_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'doc';
        $doc_paths = array();
        if (is_dir($billingcompany_doc_path)) {
            foreach (glob($billingcompany_doc_path . '/*.*') as $filename) {
                array_push($doc_paths, $filename);
            }
        }
        $billingcompany_doc_list = array();
        $i = 0;
        foreach ($doc_paths as $path) {
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
            $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
            $billingcompany_doc_list[$i]['date'] = $date;
            $billingcompany_doc_list[$i]['desc'] = $temp[1];
            $billingcompany_doc_list[$i]['user'] = $temp[2];
            $count = count($temp);
            $n = 3;
            if ($count > 3) {
                for ($n; $n < $count; $n++) {
                    $billingcompany_doc_list[$i]['user'] = $billingcompany_doc_list[$i]['user'] . '-' . $temp[$n];
                }
            }
            $billingcompany_doc_list[$i]['url'] = $path;
            $i++;
        }
        $this->view->billingcompany_doc_list = $billingcompany_doc_list;

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('patientcorrespondence');
        $select->where('patientcorrespondence.billingcompany_id=?', $this->billingcompany_id);
        $temp_result = $db->fetchAll($select);
//        $temp_merge_field = $db->fetchAll($select);
        $ptc_doc_paths = array();
        $ptc_variables = array();
        $patientcorrespondence_doc_path = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'patientcorrespondence' .'/'. 'templates';
        
        for($mark = 0; $mark < count($temp_result); $mark ++){
            $filename = $patientcorrespondence_doc_path . "/" . $temp_result[$mark]['template'] . ".docx";
            array_push($ptc_doc_paths, $filename);
            array_push($ptc_variables, $temp_result[$mark]['variables']);
        }

        $patientcorrespondence_doc_list = array();
        $i = 0;
        $temp_mark = 0;
        foreach ($ptc_doc_paths as $path) {
            $temp = explode("/", $path);
            $temp = explode(".", $temp[count($temp) - 1]);
            $filename = $temp[0];
            $patientcorrespondence_doc_list[$i]['patientcorrespondence_name'] = $filename;
            $patientcorrespondence_doc_list[$i]['url'] = $path;

            $temp_merge_field = $ptc_variables[$temp_mark];
            $temp_mark += 1;
            $tp_merge = explode('|', $temp_merge_field);

            for($mark = 0; $mark < count($tp_merge); $mark ++){
                $tp_value = explode("=",$tp_merge[$mark]);
                $tp_merge_name[$mark + 1] = $tp_value[0];
                $tp_merge_value[$mark + 1] = $tp_value[1];
            }
            if (($tp_merge == null) || ($tp_merge == "")) {
                $tp_value = explode("=", $temp_merge_field);
                $tp_merge_name[1] = $tp_value[0];
                $tp_merge_value[1] = $tp_value[1];
            }

            $content = "";
            $zip = zip_open($path);
            if (!$zip || is_numeric($zip))
                return false;
            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry) == FALSE)
                    continue;
                if (zip_entry_name($zip_entry) != "word/document.xml")
                    continue;
                $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                zip_entry_close($zip_entry);
            }// end while
            zip_close($zip);

            $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
            $content = str_replace('</w:r></w:p>', "\r\n", $content);
            $striped_content = strip_tags($content);
            $temp_txt = $striped_content;
            $purpose_txt = "MERGEFIELD";
            $temp_length = strlen($purpose_txt);
            $txt_length = strlen($temp_txt);
            $merge_field_list_from_docx = array();
            $mark_list_length = 0;
            while ($tp_pos = strpos($temp_txt, $purpose_txt)) {
                $temp_txt = substr($temp_txt, $tp_pos + $temp_length);
                $tp_pos_inner_start = strpos($temp_txt, "\"");
                $temp_txt = substr($temp_txt, $tp_pos_inner_start + 1);
                $tp_pos_inner_end = strpos($temp_txt, "\"");
                $need_save = substr($temp_txt, 0, $tp_pos_inner_end);
                $temp_txt = substr($temp_txt, $tp_pos_inner_end);
                $merge_field_list_from_docx[$mark_list_length] = $need_save;
                $mark_list_length ++;
            }
//            $tp_merge_name[$mark] = $tp_value[0];
//            $tp_merge_value[$mark] = $tp_value[1];
            $merge_fields = array();
            for($mark = 0; $mark < count($merge_field_list_from_docx); $mark ++){
                $merge_fields[$mark]['name'] = $merge_field_list_from_docx[$mark];
                if($position = array_search($merge_field_list_from_docx[$mark],$tp_merge_name)){
                    $merge_fields[$mark]['value'] = $tp_merge_value[$position];
                }else{
                    $merge_fields[$mark]['value'] = "";
                }
            }
            
            $patientcorrespondence_doc_list[$i]['merge_fields'] = $merge_fields;

            $i++;
        }                
        
        $this->view->patientcorrespondence_doc_list = $patientcorrespondence_doc_list;
        session_start();
        $merge_field_list = $_SESSION['merge_field_data']['merge_field_list'];
        $front_page_file_name = $_SESSION['merge_field_data']['front_page_file_name'];
        $name_for_template = $_SESSION['merge_field_data']['name_for_template'];
        $merge_flag = $_SESSION['merge_field_data']['mark'];
        $scroll_pos = $_SESSION['merge_field_data']['scroll_pos'];
        if($merge_flag == "merge_flag"){
            $this->view->merge_flag = "merge_flag";
            $this->view->merge_field_list = $merge_field_list;
            $this->view->name_for_template = $name_for_template;
            $this->view->front_page_file_name = $front_page_file_name;
            $this->view->scroll_pos = $scroll_pos;
            $_SESSION['merge_field_data'] = null;
        }
        //james: add patientcorrespondence tool:end
        
        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;
        $this->view->user_name = $user_name;
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $billingcompany = array();
                $billingcompany_id = $this->billingcompany_id();

                $billingcompany['street_address'] = $this->getRequest()->getPost('street_address');
                $billingcompany['city'] = $this->getRequest()->getPost('city');
                $billingcompany['zip'] = $this->getRequest()->getPost('zip');
                $billingcompany['state'] = $this->getRequest()->getPost('state');
                $billingcompany['phone_number'] = $this->getRequest()->getPost('phone_number');
                $billingcompany['fax_number'] = $this->getRequest()->getPost('fax_number');
                $billingcompany['notes'] = $this->getRequest()->getPost('notes');
                $billingcompany['default_provider'] = $this->getRequest()->getPost('default_provider');
                $billingcompany['session_timeout'] = $this->getRequest()->getPost('time_out');
                $billingcompany['paymentfrom'] = $this->getRequest()->getPost('paymentfrom');
                $billingcompany['patientdoctypes'] = $this->getRequest()->getPost('patient_doc_types');
                $billingcompany['claimdoctypes'] = $this->getRequest()->getPost('claim_doc_types');
                $billingcompany['patientdocsources'] = $this->getRequest()->getPost('patient_doc_sources');
                $billingcompany['claimdocsources'] = $this->getRequest()->getPost('claim_doc_sources');
                $billingcompany['appeal1'] = $this->getRequest()->getPost('appeal1');
                $billingcompany['appeal2'] = $this->getRequest()->getPost('appeal2');
                $billingcompany['appeal3'] = $this->getRequest()->getPost('appeal3');
                $billingcompany['salutations'] = $this->getRequest()->getPost('salutations');
                $billingcompany['tags'] = $this->getRequest()->getPost('tags');
                $billingcompany['document_feature'] = $this->getRequest()->getPost('document_feature');
                $billingcompany['insurancetags'] = $this->getRequest()->getPost('insurancetags');
                $billingcompany['providertags'] = $this->getRequest()->getPost('providertags');
                $billingcompany['renderingprovidertags'] = $this->getRequest()->getPost('renderingprovidertags');
                $billingcompany['optionstags'] = $this->getRequest()->getPost('optionstags');                
                $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                $db = $db_billingcompany->getAdapter();
                $where = $db->quoteInto('id = ?', $billingcompany_id);
                $oldbilling = $db_billingcompany->fetchAll($where)->toArray();
                if ($db_billingcompany->update($billingcompany, $where)) {
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.street_address', $oldbilling[0]['street_address'], $billingcompany['street_address']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.city', $oldbilling[0]['city'], $billingcompany['city']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.zip', $oldbilling[0]['zip'], $billingcompany['zip']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.state', $oldbilling[0]['state'], $billingcompany['state']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.phone_number', $oldbilling[0]['phone_number'], $billingcompany['phone_number']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.fax_number', $oldbilling[0]['fax_number'], $billingcompany['fax_number']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.notes', $oldbilling[0]['notes'], $billingcompany['notes']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.default_provider', $oldbilling[0]['default_provider'], $billingcompany['default_provider']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.session_timeout', $oldbilling[0]['session_timeout'], $billingcompany['session_timeout']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.patientdoctypes', $oldbilling[0]['patientdoctypes'], $billingcompany['patientdoctypes']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.claimdoctypes', $oldbilling[0]['claimdoctypes'], $billingcompany['claimdoctypes']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.patientdocsources', $oldbilling[0]['patientdocsources'], $billingcompany['patientdocsources']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.claimdocsources', $oldbilling[0]['claimdocsources'], $billingcompany['claimdocsources']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.appeal1', $oldbilling[0]['appeal1'], $billingcompany['appeal1']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.appeal2', $oldbilling[0]['appeal2'], $billingcompany['appeal2']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.appeal3', $oldbilling[0]['appeal3'], $billingcompany['appeal3']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.paymentfrom', $oldbilling[0]['paymentfrom'], $billingcompany['paymentfrom']);
                    $this->adddatalog(-1, $oldbilling[0]['billingcompany_name'], 'billingcompany.salutations', $oldbilling[0]['salutations'], $billingcompany['salutations']);
                }
                $this->_redirect('/biller/data/billingcompany');
            }
            if ($submitType == "UPLOAD") {
                $uploadtype = $this->getRequest()->getParam('uploadtype');
                $uploadmerge = $this->getRequest()->getParam('getmergelist');
                if ($uploadtype == "documents") {
                    $desc = $this->getRequest()->getParam('desc');
                    if ($desc == "" || $desc == null) {
                        $this->_redirect('/biller/data/billingcompany');
                    }
                    $adapter = new Zend_File_Transfer_Adapter_Http();
                    if ($adapter->isUploaded()) {
                        $billingcompany_id = $this->billingcompany_id;
                        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                        $db = $db_billingcompany->getAdapter();
                        $where = $db->quoteInto('id = ?', $billingcompany_id);
                        $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
                        $log_billingcompany_name = $billingcompany_data['billingcompany_name'];
                        $log_db_field = $desc;
                        $log_newvalue = 'Document Uploaded';
                        $dir_billingcompany = $this->sysdoc_path . '/' . $this->billingcompany_id;
                        if (!is_dir($dir_billingcompany)) {
                            mkdir($dir_billingcompany);
                        }
                        $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'doc';
                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }
                        $today = date("Y-m-d H:i:s");
                        $date = explode(' ', $today);
                        $time0 = explode('-', $date[0]);
                        $time1 = explode(':', $date[1]);
                        $time = $time0[0] . $time0[1] . $time0[2] . $time1[0] . $time1[1] . $time1[2];
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $file_name = $time . '-' . $desc . '-' . $user_name;
                        $old_filename = $adapter->getFileName();
                        $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                        $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                        $adapter->setDestination($dir);
                        if (!$adapter->receive()) {
                            $messages = $adapter->getMessages();
                            echo implode("n", $messages);
                        } else {
                            $this->adddatalog(-1, $log_billingcompany_name, $log_db_field, null, $log_newvalue);
                            $this->_redirect('/biller/data/billingcompany');
                        }
                    }
                } else if ($uploadtype == "patientcorrespondence" && $uploadmerge != "GETMERGELIST") {
                    //name<=>template in db
                    $name = $this->getRequest()->getParam("patientcorrespondencename");
                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('patientcorrespondence','template');
                    $select->where('patientcorrespondence.billingcompany_id=?', $this->billingcompany_id);
                    $existingnamelist = $db->fetchAll($select);
                    $existing_mark = "notExisting";
                    for($mark = 0; $mark < count($existingnamelist); $mark ++){
                        if($name == $existingnamelist[$mark][template]){
                            $existing_mark = "existing";
                            break;
                        }
                    }
                    if ($name == "" || $name == null || $existing_mark == "existing") {
                        $this->_redirect('/biller/data/billingcompany');
                    }
                    //billingcompany_id<=>billingcompany_id
                    $billingcompany_id = $this->billingcompany_id;
                    session_start();
                    $merge_field_list = $_SESSION['merge_field_to_save']['merge_field_list'];
                    $merge_fields_varibles = "";
                    for ($mark = 0; $mark < count($merge_field_list); $mark ++) {
                        $temp_varible = $this->getRequest()->getParam($merge_field_list[$mark]);
                        if(($temp_varible != null) && ($temp_varible != "")){
                            $merge_fields_varibles = $merge_fields_varibles . $merge_field_list[$mark] . "=" . $temp_varible . "|";
                        }
                    }
                    if(substr($merge_fields_varibles, -1) == "|"){
                        $merge_fields_varibles = substr($merge_fields_varibles,0,strlen($merge_fields_varibles)-1); 
                    }

                    $db_patientcorrespondence = new Application_Model_DbTable_Patientcorrespondence();
                    $db = $db_patientcorrespondence->getAdapter();
                    //get the new diagnosiscode 
                    $patientcorrespondencenew = array();
                    $patientcorrespondencenew['template'] = $name;
                    $patientcorrespondencenew['variables'] = $merge_fields_varibles;
                    $patientcorrespondencenew['billingcompany_id'] = $billingcompany_id;
                    $db_patientcorrespondence->insert($patientcorrespondencenew);

                    session_start();
                    $temp_folder = $_SESSION['merge_field_to_save']['temp_folder'];
                    $temp_filepath = $_SESSION['merge_field_to_save']['temp_filepath'];
                    $save_folder = $_SESSION['merge_field_to_save']['save_folder'];
                    copy($temp_filepath,$save_folder."/".$name.".docx");
                    if (file_exists($temp_filepath)) {
                        unlink($temp_filepath);
                    }
                    if (is_dir($temp_folder)) {
                        rmdir($temp_folder);
                    }
                    $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                    $db = $db_billingcompany->getAdapter();
                    $where = $db->quoteInto('id = ?', $billingcompany_id);
                    $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
                    $log_billingcompany_name = $billingcompany_data['billingcompany_name'];
                    $log_db_field = $name;
                    $log_newvalue = 'Patient Correspondence Document Uploaded';
                    $this->adddatalog(-1, $log_billingcompany_name, $log_db_field, null, $log_newvalue);
                    
                    $this->_redirect('/biller/data/billingcompany');
                    
                }else if($uploadtype == "patientcorrespondence" && $uploadmerge=="GETMERGELIST"){
//                        $filepath_front =  $this->getRequest()->getParam("uploadfilepath");
                        $name_for_template = $this->getRequest()->getParam("patientcorrespondencename");
                        $scroll_pos = $this->getRequest()->getParam("scroll_pos");
                        $temp_filepath = "";
//                        session_start();
//                        $uploaded = $_SESSION['merge_field_data']['upload_mark'];
//                        if()
                        $name = "temp";
                        $adapter = new Zend_File_Transfer_Adapter_Http();
                        if ($adapter->isUploaded()) {
                            $billingcompany_id = $this->billingcompany_id;
                            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id;
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'patientcorrespondence';
                            if (!is_dir($dir)) {
                                mkdir($dir);
                            }
                            $dest_folder = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'patientcorrespondence' . '/' . 'templates';
                            if (!is_dir($dest_folder)) {
                                mkdir($dest_folder);
                            }
                            $save_folder = $dest_folder;
                            $dest_folder = $this->sysdoc_path . '/' . $this->billingcompany_id . '/' . 'patientcorrespondence' . '/' . 'templates' .'/temp';
                            if (!is_dir($dest_folder)) {
                                mkdir($dest_folder);
                            }
                            $file_name = $name;
                            $old_filename = $adapter->getFileName();
                            $file_extension = substr($old_filename, strripos($old_filename, '.'), strlen($old_filename) - strripos($old_filename, '.'));
                            $front_page_file_name = substr($old_filename,(strripos($old_filename,'\\') + 1),strlen($old_filename) - strlen($file_extension));
                            $temp_filepath = $dest_folder . "/temp" . $file_extension;
                            if (file_exists($temp_filepath)) {
                                unlink($temp_filepath);
                            }
                            $adapter->addFilter('Rename', array('target' => $file_name . $file_extension));
                            $adapter->setDestination($dest_folder);
                            $adapter->receive();
                        }
                        session_start();
                        $_SESSION['merge_field_to_save']['temp_filepath'] = $temp_filepath;
                        $_SESSION['merge_field_to_save']['temp_folder'] = $dest_folder;
                        $_SESSION['merge_field_to_save']['save_folder'] = $save_folder;

                        $content = "";
                        $zip = zip_open($temp_filepath);
                        if (!$zip || is_numeric($zip))
                            return false;
                        while ($zip_entry = zip_read($zip)) {
                            if (zip_entry_open($zip, $zip_entry) == FALSE)
                                continue;
                            if (zip_entry_name($zip_entry) != "word/document.xml")
                                continue;
                            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            zip_entry_close($zip_entry);
                        }// end while
                        zip_close($zip);

                        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
                        $content = str_replace('</w:r></w:p>', "\r\n", $content);
                        $striped_content = strip_tags($content);
                        $temp_txt = $striped_content;
                        //                        if (file_exists($temp_filepath)) {
//                            unlink($temp_filepath);
//                        }
                        //process the content from docx file and get the merge field list
                        $purpose_txt = "MERGEFIELD";
                        $temp_length = strlen($purpose_txt);
                        $txt_length = strlen($temp_txt);
                        $merge_field_list = array();
                        $mark_list_length = 0;
                        while($tp_pos = strpos($temp_txt, $purpose_txt)){

                            $temp_txt = substr($temp_txt, $tp_pos + $temp_length);
                            $tp_pos_inner_start = strpos($temp_txt, "\"") ;
                            $temp_txt = substr($temp_txt, $tp_pos_inner_start + 1);
                            $tp_pos_inner_end = strpos($temp_txt, "\"");
                            $need_save = substr($temp_txt, 0, $tp_pos_inner_end);
                            $temp_txt = substr($temp_txt,$tp_pos_inner_end);
                            $merge_field_list[$mark_list_length] = $need_save;
                            $mark_list_length ++;
                        }
                        session_start();
//                        $_SESSION['merge_field_data']['upload_mark'] = "uploaded";
                        $_SESSION['merge_field_data']['merge_field_list'] = $merge_field_list;    
                        $_SESSION['merge_field_data']['front_page_file_name'] = $front_page_file_name;
                        $_SESSION['merge_field_data']['name_for_template'] = $name_for_template;
                        $_SESSION['merge_field_data']['mark'] = "merge_flag";
                        $_SESSION['merge_field_data']['scroll_pos'] = "$scroll_pos";
                        $_SESSION['merge_field_to_save']['merge_field_list'] = $merge_field_list;
                        $this->_redirect('/biller/data/billingcompany');
                }
            }
            if ($submitType == "Data Log") {

                $db_datalog = new Application_Model_DbTable_Datalog();
                $db = $db_datalog->getAdapter();
                $data = $db_datalog->fetchAll()->toArray();
                $fields = array('Data&Time', 'User', 'Provider', 'Record', 'DBField', 'OldValue', 'NewValue');
                $display_fields = array('data_and_time', 'user', 'provider', 'data_name', 'dbfield', 'oldvalue', 'newvalue');
                $log_dir_all = get_docfile_paths(0);
                $log_dir = $log_dir_all['billingcompany_doc_path'];
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }

                //  $log_dir = $log_dir . '/' . $this->billingcompany_id();
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }

                $log_dir = $log_dir . '/datalog';
                if (!is_dir($log_dir)) {
                    mkdir($log_dir);
                }

                $log_file_name = $log_dir . '/datalog.csv';
                $final_length = sizeof($fields);
                $index = count($data);

                $fp = fopen($log_file_name, 'w');

                for ($i = 0; $i < $final_length; $i++) {
                    fwrite($fp, $fields[$i] . ",");
                }
                fwrite($fp, "\r\n");


                for ($i = $index - 1; $i >= 0; $i--) {
                    for ($j = 0; $j < $final_length; $j++) {
                        //  if()
                        $t = "\t{$data[$i][$display_fields[$j]]}";
                        $ttt = '"' . $t . '"';

                        fwrite($fp, $ttt . ",");
                    }
                    fwrite($fp, "\r\n");
                }
                fclose($fp);

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
            }
        }
    }

    /**
     * newbillingcompanyAction
     * a function for creating a new billingcompany.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newbillingcompanyAction() {
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $billingcompany_data = $db_billingcompany->fetchAll();
        $this->view->billingcompanyList = $billingcompany_data;
        if ($this->getRequest()->isPost()) {
            $billingcompany = array();
            $billingcompany['billingcompany_name'] = $this->billingcompany_id();
            $billingcompany['street_address'] = $this->getRequest()->getPost('street_address');
            $billingcompany['city'] = $this->getRequest()->getPost('city');
            $billingcompany['zip'] = $this->getRequest()->getPost('zip');
            $billingcompany['state'] = $this->getRequest()->getPost('state');
            $billingcompany['phone_number'] = $this->getRequest()->getPost('phone_number');
            $billingcompany['fax_number'] = $this->getRequest()->getPost('fax_number');
            $billingcompany['notes'] = $this->getRequest()->getPost('notes');
            $billingcompany['default_provider'] = $this->getRequest()->getPost('default_provider');
            $billingcompany['session_timeout'] = $this->getRequest()->getPost('time_out');

            $billingcompany['patientdoctypes'] = $this->getRequest()->getPost('patient_doc_types');
            $billingcompany['claimdoctypes'] = $this->getRequest()->getPost('claim_doc_types');
            $billingcompany['patientdocsources'] = $this->getRequest()->getPost('patient_doc_sources');
            $billingcompany['claimdocsources'] = $this->getRequest()->getPost('claim_doc_sources');

            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $db_billingcompany->insert($billingcompany);

            $this->_redirect('/biller/data/billingcompany');
        }
    }

    function parse_tag($tag_string) {
        $pairs = explode("|", $tag_string);
        $result = array();
        foreach ($pairs as $pair) {
            $name_value = explode("=", $pair);
            $result[$name_value[0]] = $name_value[1];
        }
        return $result;
    }

    /**
     * billingcompanyinputAction
     * a function returning the billingcompany data for displaying on the page.
     * @author Haowei.
     * @return the billingcompany data for displaying on the page
     * @version 05/15/2012
     */
    public function billingcompanyinputAction() {
        $this->_helper->viewRenderer->setNoRender();
        // $id = $_POST['billingcompany_id'];
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompany_data = $db_billingcompany->fetchRow($where);


        $tags_str = $billingcompany_data['tags'];
        $tags_arr = parse_tag($tags_str);



        $data = array(
            'billingcompany_name' => $billingcompany_data['billingcompany_name'], 'street_address' => $billingcompany_data['street_address'], 'city' => $billingcompany_data['city'], 'zip' => $billingcompany_data['zip'],
            'phone_number' => $billingcompany_data['phone_number'], 'state' => $billingcompany_data['state'], 'fax_number' => $billingcompany_data['fax_number'], 'appeal1' => $billingcompany_data['appeal1'], 'appeal2' => $billingcompany_data['appeal2'], 'appeal3' => $billingcompany_data['appeal3'], 'salutations' => $billingcompany_data['salutations'], 'tags_str' => $tags_str, 'tags' => $tags_arr,
            'insurancetags' => $billingcompany_data['insurancetags'], 'providertags' => $billingcompany_data['providertags'], 'renderingprovidertags' => $billingcompany_data['renderingprovidertags'],'optionstags' => $billingcompany_data['optionstags'],
            'notes' => $billingcompany_data['notes'], 'salutations' => $billingcompany_data['salutations'], 'paymentfrom' => $billingcompany_data['paymentfrom'], 'default_provider' => $billingcompany_data['default_provider'], 'claim_doc_types' => $billingcompany_data['claimdoctypes'], 'patient_doc_types' => $billingcompany_data['patientdoctypes'], 'patient_doc_sources' => $billingcompany_data['patientdocsources'], 'claim_doc_sources' => $billingcompany_data['claimdocsources'], 'time_out' => $billingcompany_data['session_timeout'], 'document_feature' => $billingcompany_data['document_feature']);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * billingcompanyinfoAction
     * a function returning the billingcompany data for displaying on the page.
     * @author Haowei.
     * @return the billingcompany data for displaying on the page
     * @version 05/15/2012
     */
    public function billingcompanyinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $this->billingcompany_id());
        $billingcompany_data = $db_billingcompany->fetchRow($where);

        $data = array(
            'billingcompany_name' => $billingcompany_data['billingcompany_name'], 'street_address' => $billingcompany_data['street_address'], 'city' => $billingcompany_data['city'], 'zip' => $billingcompany_data['zip'],
            'phone_number' => $billingcompany_data['phone_number'], 'state' => $billingcompany_data['state'], 'fax_number' => $billingcompany_data['fax_number'],
            'notes' => $billingcompany_data['notes'], 'paymentfrom' => $billingcompany_data['paymentfrom'], 'default_provider' => $billingcompany_data['default_provider'], 'document_feature' => $billingcompany_data['document_feature']);

        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function x12partnerAction() {
// action body
        if ($this->getRequest()->isPost()) {
            $x12_partner = array();

            $x12_partner['name'] = filter($this->getRequest()->getPost('partner_name'));
//verify partner name:exist?
            $db_x12_partner = new Application_Model_DbTable_X12partners();
            if ($db_x12_partner->is_partner_name_exist($x12_partner['name'])) {             //user exist
                $this->view->assign('name_info', 'partner name exist!');
                return;
            }
            $x12_partner['id_number'] = filter($this->getRequest()->getPost('id_number'));
            $isa05 = filter($this->getRequest()->getPost('ISA05'));
            $x12_partner['x12_isa05'] = $this->parseISA05_ISA07ByName($isa05, 'ISA05');
            $x12_partner['sender_id'] = filter($this->getRequest()->getPost('sender_id'));
            $isa07 = filter($this->getRequest()->getPost('ISA07'));
            $x12_partner['x12_isa07'] = $this->parseISA05_ISA07ByName($isa07, 'ISA07');
            $x12_partner['receiver_id'] = filter($this->getRequest()->getPost('receiver_id'));
            $isa14 = filter($this->getRequest()->getPost('ISA14'));
            $x12_partner['x12_isa14'] = ($isa14 == 'Yes' ? 1 : 0);
            $isa15 = filter($this->getRequest()->getPost('ISA15'));
            $x12_partner['x12_isa15'] = ($isa15 == 'Production' ? P : T);
            $x12_partner['x12_gs02'] = filter($this->getRequest()->getPost('App_sender_code'));
            $x12_partner['x12_per06'] = filter($this->getRequest()->getPost('submitter_EDI'));
            $x12_partner['x12_version'] = filter($this->getRequest()->getPost('version'));

            $db_x12_partner->insert($x12_partner);
//another action showing x12-partners list is need.
//here needs a redirect
        }
    }

    /**
     * paitentsearchAction
     * a function returning the inquiryed patient data.
     * @author Qiaoxinwang.
     * @return the patient data
     * @version 05/15/2012
     */
    public function patientsearchAction() {
        $this->_helper->viewRenderer->setNoRender();
        $last_name = $_POST['last_name'];
        $first_name = $_POST['first_name'];
        $provider_id = $_POST['provider_id'];
        $DOS = $_POST['DOS'];
        $MRN = $_POST['MRN'];
        if ($DOS != null)
            $DOS = date('Y-m-d', strtotime($this->getRequest()->getPost('DOS')));

        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('patient', array('patient.id as patient_id', 'patient.last_name', 'patient.first_name', 'DATE_FORMAT(patient.DOB, \'%m/%d/%Y\' ) as DOB', 'patient.SSN', 'patient.sex', 'patient.account_number'));
        $select->join('encounter', 'encounter.patient_id = patient.id', array('encounter.start_date_1', 'encounter.id as encounter_id'));
        $select->join('renderingprovider', 'renderingprovider.id = encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
//            $select->join('claim', 'claim.id=encounter.claim_id');
        $select->join('provider', 'provider.id=encounter.provider_id');
        $select->join('facility', 'facility.id=encounter.facility_id', 'facility_name');
        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->join('patientinsured', 'patientinsured.patient_id = patient.id');
        $select->join('insured', 'insured.id=patientinsured.insured_id');
        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        $select->where('billingcompany.id=?', $this->billingcompany_id());
//            $select->where('encounter.patient_id = patient.id');



        if ($provider_id != null)
            $select->where('provider.id=?', $provider_id);
        if ($last_name != null)
            $select->where('patient.last_name LIKE ?', $last_name . '%');
        if ($first_name != null)
            $select->where('patient.first_name LIKE ?', $first_name . '%');
        if ($MRN != null)
            $select->where('patient.account_number=?', $MRN);
        if ($DOS != null) {
            $select->where('encounter.start_date_1=?', $DOS);
        }
        $select->group('patient.id');
        $select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
        $patients = $db->fetchAll($select);
        if (count($patients) > 0) {
            for ($i = 0; $i < count($patients); $i++) {
                $patients[$i]['start_date_1'] = format($patients[$i]['start_date_1'], 1);
            }
            $json = Zend_Json::encode($patients);
            echo $json;
        } else {
            return;
        }
    }

    /**
     * paitentdeleteAction
     * a function deleting the patient.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function patientdeleteAction() {
        $this->_helper->viewRenderer->setNoRender();
        $patient_id_string = $_POST['patient_id'];
        $patient_id_array = array();
        if (strlen($patient_id_string) > 0)
            $patient_id_array = explode(',', $patient_id_string);

//$claim_id_array
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('encounter', 'encounter.claim_id');
        $select->where('patient_id IN (?)', $patient_id_array);
        $select->group('encounter.claim_id');
        $rows = $db->fetchAll($select);
        $claim_id_array = array();
        foreach ($rows as $row) {
            array_push($claim_id_array, $row['claim_id']);
        }

        //get insured id
        $select = $db->select();
        $select->from('patientinsured', 'insured_id');
        $select->where('patient_id IN (?)', $patient_id_array);
        $select->group('patientinsured.insured_id');
        $insured_rows = $db->fetchAll($select);
        $insured_id_array = array();
        foreach ($insured_rows as $row) {
            array_push($insured_id_array, $row['insured_id']);
        }

        if (count($claim_id_array) > 0) {
            try {
                $db_claim = new Application_Model_DbTable_Claim();
                $where = $db->quoteInto('id IN (?)', $claim_id_array);
                $rows_affected = $db_claim->delete($where);
            } catch (Exception $e) {
                echo 'Message: ' . $e->getMessage();
            }
        }

        try {
            $db_patient = new Application_Model_DbTable_Patient();
            $where = $db->quoteInto('id IN (?)', $patient_id_array);
            $rows_affected = $db_patient->delete($where);
        } catch (Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
        //delete insured
        if (count($insured_id_array) > 0) {
            try {
                $db_insured = new Application_Model_DbTable_Insured();
                //$insureddb = $db_insured->getAdapter();
                $where = $db->quoteInto('id IN (?)', $insured_id_array);
                $rows_affected = $db_insured->delete($where);
            } catch (Exception $e) {
                echo 'Message: ' . $e->getMessage();
            }
        }
    }

    /**
     * paitentAction
     * a function returning the provider list.
     * @author Qiaoxinwang.
     * @return the provider list
     * @version 05/15/2012
     */
    public function patientAction() {
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $provider_data;
    }

    /**
     * servicessearchAction
     * a function returning the inquiryed service data.
     * @author Qiaoxinwang.
     * @return the service data
     * @version 05/15/2012
     */
    public function servicessearchAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $last_name = $_POST['last_name'];
        $first_name = $_POST['first_name'];
        $DOS = $_POST['DOS'];
        $MRN = $_POST['MRN'];
        if ($DOS != null)
            $DOS = date('Y-m-d', strtotime($this->getRequest()->getPost('DOS')));
//        if ($DOB != null)
//            $DOB = date('Y-m-d', strtotime($this->getRequest()->getPost('DOB')));
        $user = Zend_Auth::getInstance()->getIdentity();
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $billingcompany_id = $this->billingcompany_id();
        $select->from('renderingprovider', 'renderingprovider.last_name as renderingprovider_last_name');
        $select->join('encounter', 'renderingprovider.id = encounter.renderingprovider_id', array('DATE_FORMAT(encounter.start_date_1, \'%m/%d/%Y\' ) as DOS', 'encounter.id as encounter_id', 'encounter.claim_id as claim_id'));
        $select->join('claim', 'claim.id=encounter.claim_id', array('claim_status'));
        $select->join('provider', 'provider.id=encounter.provider_id');
        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->join('patient', 'encounter.patient_id = patient.id', array('patient.id as patient_id', 'patient.last_name', 'patient.first_name', 'DATE_FORMAT(patient.DOB, \'%m/%d/%Y\' ) as DOB', 'patient.account_number'));
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id', array('encounterinsured.id as encounter_insured_id'));
        $select->join('insured', 'insured.id=encounterinsured.insured_id', array('insured.id as insured_id'));
        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_name');
        $select->where('billingcompany.id=?', $billingcompany_id);

        if ($provider_id != null)
            $select->where('provider.id=?', $provider_id);
        if ($last_name != null)
            $select->where('patient.last_name LIKE ?', $last_name . '%');
        if ($first_name != null)
            $select->where('patient.first_name LIKE ?', $first_name . '%');
        if ($DOS != null) {
            $select->where('encounter.start_date_1=?', $DOS);
        }
        if ($MRN != null)
            $select->where('patient.account_number=?', $MRN);
        $select->where('encounterinsured.type=\'primary\'');
        $select->order(array('patient.last_name', 'patient.first_name', 'patient.DOB'));
        $services = $db->fetchAll($select);
        if (count($services) > 0) {
            $json = Zend_Json::encode($services);
            echo $json;
        } else {
            return;
        }
    }

    /**
     * servicesdeleteAction
     * a function for deleting the services.
     * @author Qiaoxinwang.
     * @version 05/15/2012
     */
    public function servicesdeleteAction() {
        $this->_helper->viewRenderer->setNoRender();
        $encounter_id_string = $_POST['encounter_id'];
        $encounter_id_array = array();
        if (strlen($encounter_id_string) > 0)
            $encounter_id_array = explode(',', $encounter_id_string);
//$claim_id_array
        $db = Zend_Registry::get('dbAdapter');
        $select = $db->select();
        $select->from('encounter', 'encounter.claim_id');
        $select->where('id IN (?)', $encounter_id_array);
        $select->group('encounter.claim_id');
        $rows = $db->fetchAll($select);
        $claim_id_array = array();
        foreach ($rows as $row) {
            array_push($claim_id_array, $row['claim_id']);
        }

        $db_claim = new Application_Model_DbTable_Claim();
        $where = $db->quoteInto('id IN (?)', $claim_id_array);
        $rows_affected = $db_claim->delete($where);
    }

    /**
     * servicesAction
     * a function returning the renderingprovider list.
     * @author Haowei.
     * @return the renderingprovider list
     * @version 05/15/2012
     */
    public function servicesAction() {
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $provider_data;
    }

    public function dpatientinsurancesAction() {
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $provider_data;
    }

    public function insuredsearchAction() {
        $this->_helper->viewRenderer->setNoRender();
        $MSN = $_POST['MSN'];
//       /$etest=$this->getRequest()->getBaseUrl();
        $db = Zend_Registry::get('dbAdapter');
        $existing = array();
        $isexisting = 1;
        $select = $db->select();
        //Patient Name, MRN,DOB, Insured Name, DOB, Insurance ID, Insurance display name
        $billingcompany_id = $this->billingcompany_id();
        $select->from('insured', array('insured.id as insured_id', 'insured.last_name AS ilast_name', 'insured.first_name AS ifirst_name', 'DATE_FORMAT(insured.DOB, \'%m/%d/%Y\' ) as insureDOB', 'insured.ID_number AS insurance_ID'));
        $select->join('patientinsured', 'insured.id = patientinsured.insured_id');
        $select->join('patient', 'patient.id = patientinsured.patient_id', array('patient.id AS patient_id', 'patient.last_name AS plast_name', 'patient.first_name AS pfirst_name', 'patient.account_number as MSN', 'DATE_FORMAT(patient.DOB, \'%m/%d/%Y\' ) as patientDOB'));
        $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        // $select->join('provider', 'provider.id=insurance.provider_id');
//        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        $select->where('insurance.billingcompany_id=?', $billingcompany_id);

        $select->where('patient.account_number=?', $MSN);
        $select->order(array('insured.last_name', 'insured.first_name', 'insured.DOB'));
        $insured = $db->fetchAll($select);
        if ($insured) {
            for ($j = 0; $j < count($insured); $j++) {
                $db_Encounterinsured = new Application_Model_DbTable_Encounterinsured();
                $db = $db_Encounterinsured->getAdapter();
                $where = $db->quoteInto('insured_id = ?', $insured[$j]['insured_id']); //
                $existing = $db_Encounterinsured->fetchAll($where)->toArray();

                if ($existing) {

                    $insured[$j]['exist'] = 1;
                } else {
                    $insured[$j]['exist'] = 0;
                    $isexisting = 0;
                }
            }
        }
        if (count($insured) > 0) {
            $index = count($insured);
            $insured[$index]['ilast_name'] = $isexisting;
            $json = Zend_Json::encode($insured);
            echo $json;
        } else {
            return;
        }
    }

    /**
     * insureddeleteAction
     * a function for deleting the insured.
     * @author Qiaoxinwang.
     * @version 05/15/2012
     */
    public function insureddeleteAction() {
        $this->_helper->viewRenderer->setNoRender();
        $patient_id_string = $_POST['patient_id'];
        $insured_id_string = $_POST['insured_id'];
        $insured_id_array = array();
        $patient_id_array = array();
        if (strlen($patient_id_string) > 0)
            $patient_id_array = explode(',', $patient_id_string);
        if (strlen($insured_id_string) > 0)
            $insured_id_array = explode(',', $insured_id_string);

//$claim_id_array

        if ($patient_id_array != null) {

            for ($i = 0; $i < count($insured_id_array); $i++) {
                $db_Patientinsured = new Application_Model_DbTable_Patientinsured();
                $db = $db_Patientinsured->getAdapter();
                $where = $db->quoteInto('patient_id  =?', $patient_id_array[$i]) . $db->quoteInto(' and insured_id =?', $insured_id_array[$i]); //
                //$where = $db->quoteInto('insured_id in =', $insured_id_array[$i]); //
                $existing = $db_Patientinsured->fetchAll($where)->toArray();
                $insured_array = array();
                for ($i = 0; $i < count($existing); $i++) {
                    $insured_array = $existing[$i]['insured_id'];
                }
                if ($insured_array != null) {
                    $db_Encounterinsured = new Application_Model_DbTable_Encounterinsured();
                    $db = $db_Encounterinsured->getAdapter();
                    $wheresecond = $db->quoteInto('insured_id in (?)', $insured_array); //
                    $existing_sencond = $db_Encounterinsured->fetchAll($wheresecond)->toArray();
                    if ($existing_sencond == null) {
                        $db_Patientinsured->delete($where);
                    }
                }
            }
        }

        $this->_redirect('/biller/data/insured');
    }

    /**
     * insuredAction
     * a function returning the provider list.
     * @author Qiaoxinwang.
     * @return the provider list
     * @version 05/15/2012
     */
    public function statementdeleteAction() {
        $this->_helper->viewRenderer->setNoRender();
//        $patient_id_string = $_POST['patient_id'];
        $statement_id_string = $_POST['statement_id'];
        $statement_id_array = array();
        $patient_id_array = array();
//        if (strlen($patient_id_string) > 0)
//            $patient_id_array = explode(',', $patient_id_string);
        if (strlen($statement_id_string) > 0)
            $statement_id_array = explode(',', $statement_id_string);

//$claim_id_array

        if ($statement_id_array != null) {
            $db_Statement = new Application_Model_DbTable_Statement();
            $db = $db_Statement->getAdapter();
            $where = $db->quoteInto('id  =?', $statement_id_string); //
            //$where = $db->quoteInto('insured_id in =', $insured_id_array[$i]); //
            $existing = $db_Statement->fetchAll($where)->toArray();
            $encounter_id = $existing[0]['encounter_id'];
            $statement_type = $existing[0]['statement_type'];
            if ($existing != NULL && $statement_type != 4) {
                $whereencounter = $db->quoteInto('encounter_id  =?', $encounter_id); //
                $encounter_array = $db_Statement->fetchAll($whereencounter, "id")->toArray();
                $per = $encounter_array[0]['id'];
                $cur = $per;
                if ($cur != $statement_id_string) {

                    for ($i = 1; $i < count($encounter_array); $i++) {
                        if ($encounter_array[$i]['id'] != $statement_id_string) {
                            $per = $cur;
                            $cur = $encounter_array[$i]['id'];
                        } else {
                            $per = $cur;
                            break;
                        }
                    }
                    $wherenull = $db->quoteInto('id  =?', $per); //
                    $statement = array();
                    $statement['next_statement'] = NULL;
                    $db_Statement->update($statement, $wherenull);
                }
            }

            $whereis = $db->quoteInto('id  =?', $statement_id_string); //
            $existing2 = $db_Statement->fetchAll($whereis)->toArray();
            if ($existing2) {
                $db_Statement->delete($whereis);
            }
        }

        $this->_redirect('/biller/data/insured');
    }

    public function insuredAction() {
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $provider_data;
    }

    public function statementsearchAction() {
        $this->_helper->viewRenderer->setNoRender();
        $MSN = $_POST['MSN'];
//       /$etest=$this->getRequest()->getBaseUrl();
        $db = Zend_Registry::get('dbAdapter');
        $existing = array();
        $isexisting = 1;
        $select = $db->select();
        //Patient Name, MRN,DOB, Insured Name, DOB, Insurance ID, Insurance display name
        $billingcompany_id = $this->billingcompany_id();
        //Type, Date, Trigger, Remark and Claim, which is expressed as DOS and anesthesia code
        $select->from('patient', array('patient.id AS patient_id', 'patient.last_name AS plast_name', 'patient.first_name AS pfirst_name', 'patient.account_number as MSN'));
        $select->join('encounter', 'patient.id =  encounter.patient_id', array('encounter.secondary_CPT_code_1 AS code', 'DATE_FORMAT(encounter.start_date_1, \'%m/%d/%Y\' ) as DOS'));
        $select->join('statement', 'encounter.id = statement.encounter_id', array('statement.id AS statement_id', 'statement.statement_type AS type', 'DATE_FORMAT(statement.date, \'%m/%d/%Y\' ) AS date', 'statement.trigger as trigger', 'statement.remark as remark'));

        // $select->join('insurance', 'insurance.id=insured.insurance_id', 'insurance_display');
        // $select->join('provider', 'provider.id=insurance.provider_id');
//        $select->join('billingcompany', 'billingcompany.id=provider.billingcompany_id ');
        //$select->where('insurance.billingcompany_id=?', $billingcompany_id);

        $select->where('patient.account_number=?', $MSN);
        $select->where('date is not null ');
        $select->order(array('type'));
        $insured = $db->fetchAll($select);
      
        if (count($insured) > 0) {

            $json = Zend_Json::encode($insured);
            echo $json;
        } else {
            return;
        }
    }

    public function statementAction() {
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $provider_data;
    }

    public function claimstatusAction() {
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('claimstatus', array('claimstatus.id AS cid', 'claimstatus.claim_status AS claim_status', 'claimstatus.claim_status_display AS claim_status_display'));
        $select->join('billingcompanyclaimstatus', 'billingcompanyclaimstatus.claimstatus_id = claimstatus.id');
        $select->where('billingcompanyclaimstatus.billingcompany_id = ?', $this->billingcompany_id());
        $select->group('claimstatus.id');
        $select->order('claimstatus.claim_status');
        try {
            $claimstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }
        $this->view->claimstatusList = $claimstatuslist;

        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('billstatus', array('billstatus.id AS bid', 'billstatus.bill_status AS bill_status', 'billstatus.bill_status_display AS bill_status_display'));
        $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
        $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->billingcompany_id());
        $select->group('billstatus.id');
        $select->order('billstatus.bill_status');
        try {
            $billstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }
        $this->view->billstatusList = $billstatuslist;

        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('statementstatus', array('statementstatus.id AS sid', 'statementstatus.statement_status AS statement_status', 'statementstatus.statement_status_display AS statement_status_display'));
        $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
        $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->billingcompany_id());
        $select->group('statementstatus.id');
        $select->order('statementstatus.statement_status');
        try {
            $statementstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }
        $this->view->statementstatusList = $statementstatuslist;



        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update Claim Status") {
                $id = $this->getRequest()->getPost('claim_status_id');
                $claimstatus['claim_status_display'] = $this->getRequest()->getPost('claimdescription');
                $claimstatus['id'] = $id;
 
                $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                $db = $db_claimstatus->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldclaim = $db_claimstatus->fetchAll($where)->toArray();
                if ($db_claimstatus->update($claimstatus, $where)) {
                    $this->adddatalog(-1, $oldclaim[0]['claim_status_display'], 'claimstatus.claim_status_display', $oldclaim[0]['claim_status_display'], $claimstatus['claim_status_display']);
                }
                
                $bcclaimstatus['status'] = $this->getRequest()->getPost('csstatus');
                $db_bcs = new Application_Model_DbTable_Billingcompanyclaimstatus();
                $db_cs = $db_bcs->getAdapter();
                $where_cs = $db_cs->quoteInto('claimstatus_id = ?', $id).$db->quoteInto(' and billingcompany_id=?',$this->billingcompany_id);
                $bcsclaim = $db_bcs->fetchAll($where_cs)->toArray();
                $bcsid = $bcsclaim[0]['id'];
                $where_cs_id = $db->quoteInto('id = ?', $bcsid);
                $db_bcs->update($bcclaimstatus,$where_cs_id);
                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "Delete Claim Status") {

                $id = $this->getRequest()->getPost('claim_status_id');
                $billingcompanyclaimstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanyclaimstatus['claimstatus_id'] = $id;

                $db_billingcompanyclaimstatus = New Application_Model_DbTable_Billingcompanyclaimstatus();
                $db = $db_billingcompanyclaimstatus->getAdapter();
                $db_claimstatus = new Application_Model_DbTable_Claimstatus();
                $db1 = $db_claimstatus->getAdapter();
                $where = $db1->quoteInto('id = ?', $id);
                $oldclaim = $db_claimstatus->fetchAll($where)->toArray();
                $wherehas = $db->quoteInto('billingcompany_id =' . $billingcompanyclaimstatus['billingcompany_id'] . ' AND claimstatus_id=' . $billingcompanyclaimstatus['claimstatus_id']);
                try {


                    if ($db_billingcompanyclaimstatus->delete($wherehas)) {
                        // $this->adddatalog(-1,$oldclaim[0]['claim_status_display'],'billingcompanyclaimstatus.connection','connection',NULL);
                        // $this->adddatalog(0,$oldclaim[0]['claim_status_display'],'claimstatus.claim_status_display',$oldclaim[0]['claim_status_display'],$claimstatus['claim_status_display']);
                    }
                } catch (Exception $ex) {
                    
                }

                try {
                    if ($db_claimstatus->delete($where)) {
                        $this->adddatalog(-1, $oldclaim[0]['claim_status_display'], 'claimstatus.claim_status_display', $oldclaim[0]['claim_status_display'], NULL);
                        $this->adddatalog(-1, $oldclaim[0]['claim_status_display'], 'claimstatus.required', $oldclaim[0]['required'], NULL);
                        $this->adddatalog(-1, $oldclaim[0]['claim_status_display'], 'claimstatus.claim_status', $oldclaim[0]['claim_status'], NULL);
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "Update Bill Status") {
                $id = $this->getRequest()->getPost('bill_status_id');
                $billstatus['bill_status_display'] = $this->getRequest()->getPost('billdescription');
                $db_billstatus['id'] = $id;

                $db_billstatus = new Application_Model_DbTable_Billstatus();
                $db = $db_billstatus->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldbill = $db_billstatus->fetchAll($where)->toArray();
                if ($db_billstatus->update($billstatus, $where)) {
                    $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status_display', $oldbill[0]['bill_status_display'], $billstatus['bill_status_display']);
                }
                
                $bcbillstatus['status'] = $this->getRequest()->getPost('bsstatus');
                $db_bbs = new Application_Model_DbTable_Billingcompanybillstatus();
                $db_bs = $db_bbs->getAdapter();
                $where_bs = $db_bs->quoteInto('billstatus_id = ?', $id).$db->quoteInto(' and billingcompany_id=?',$this->billingcompany_id);
                $bbsclaim = $db_bbs->fetchAll($where_bs)->toArray();
                $bbsid = $bbsclaim[0]['id'];
                $where_bs_id = $db->quoteInto('id = ?', $bbsid);
                $db_bbs->update($bcbillstatus,$where_bs_id);

                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "Delete Bill Status") {

                $id = $this->getRequest()->getPost('bill_status_id');
                $billingcompanybillstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanybillstatus['billstatus_id'] = $id;

                $db_billingcompanybillstatus = New Application_Model_DbTable_Billingcompanybillstatus();
                $db = $db_billingcompanybillstatus->getAdapter();
                $db_billstatus = new Application_Model_DbTable_Billstatus();
                $db1 = $db_billstatus->getAdapter();
                $where = $db1->quoteInto('id = ?', $id);
                $oldbill = $db_billstatus->fetchAll($where)->toArray();
                $wherehas = $db->quoteInto('billingcompany_id =' . $billingcompanybillstatus['billingcompany_id'] . ' AND billstatus_id=' . $billingcompanybillstatus['billstatus_id']);
                try {


                    if ($db_billingcompanybillstatus->delete($wherehas)) {
                        // $this->adddatalog(-1,$oldbill[0]['bill_status_display'],'billingcompanybillstatus.connection','connection',NULL);
                        // $this->adddatalog(0,$oldbill[0]['bill_status_display'],'billstatus.bill_status_display',$oldbill[0]['bill_status_display'],$billstatus['bill_status_display']);
                    }
                } catch (Exception $ex) {
                    
                }

                try {
                    if ($db_billstatus->delete($where)) {
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status_display', $oldbill[0]['bill_status_display'], NULL);
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.required', $oldbill[0]['required'], NULL);
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status', $oldbill[0]['bill_status'], NULL);
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "Update Statement Status") {
                $id = $this->getRequest()->getPost('statement_status_id');
                $statementstatus['statement_status_display'] = $this->getRequest()->getPost('statementdescription');
                $db_statementstatus['id'] = $id;

                $db_statementstatus = new Application_Model_DbTable_Statementstatus();
                $db = $db_statementstatus->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldstatement = $db_statementstatus->fetchAll($where)->toArray();
                if ($db_statementstatus->update($statementstatus, $where)) {
                    $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status_display', $oldstatement[0]['statement_status_display'], $statementstatus['statement_status_display']);
                }
                
                $bcstatementstatus['status'] = $this->getRequest()->getPost('ssstatus');
                $db_bss = new Application_Model_DbTable_Billingcompanystatementstatus();
                $db_ss = $db_bss->getAdapter();
                $where_ss = $db_ss->quoteInto('statementstatus_id = ?', $id).$db->quoteInto(' and billingcompany_id=?',$this->billingcompany_id);
                $bssclaim = $db_bss->fetchAll($where_ss)->toArray();
                $bssid = $bssclaim[0]['id'];
                $where_ss_id = $db->quoteInto('id = ?', $bssid);
                $db_bss->update($bcstatementstatus,$where_ss_id);

                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "Delete Statement Status") {

                $id = $this->getRequest()->getPost('statement_status_id');
                $billingcompanystatementstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanystatementstatus['statementstatus_id'] = $id;

                $db_billingcompanystatementstatus = New Application_Model_DbTable_Billingcompanystatementstatus();
                $db = $db_billingcompanystatementstatus->getAdapter();
                $db_statementstatus = new Application_Model_DbTable_Statementstatus();
                $db1 = $db_statementstatus->getAdapter();
                $where = $db1->quoteInto('id = ?', $id);
                $oldstatement = $db_statementstatus->fetchAll($where)->toArray();
                $wherehas = $db->quoteInto('billingcompany_id =' . $billingcompanystatementstatus['billingcompany_id'] . ' AND statementstatus_id=' . $billingcompanystatementstatus['statementstatus_id']);
                try {


                    if ($db_billingcompanystatementstatus->delete($wherehas)) {
                        // $this->adddatalog(-1,$oldbill[0]['bill_status_display'],'billingcompanybillstatus.connection','connection',NULL);
                        // $this->adddatalog(0,$oldbill[0]['bill_status_display'],'billstatus.bill_status_display',$oldbill[0]['bill_status_display'],$billstatus['bill_status_display']);
                    }
                } catch (Exception $ex) {
                    
                }

                try {
                    if ($db_statementstatus->delete($where)) {
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status_display', $oldstatement[0]['statement_status_display'], NULL);
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.required', $oldstatement[0]['required'], NULL);
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status', $oldstatement[0]['statement_status'], NULL);
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $this->_redirect('/biller/data/claimstatus');
            }
            if ($submitType == "New") {
                $this->_redirect('/biller/data/newclaimstatus');
            }
        }
    }

    public function claimstatusexistingAction() {

        $this->_helper->viewRenderer->setNoRender();
        $claimstatus['claim_status_display'] = $this->getRequest()->getPost('claim_status_display');

        $db_claimstatus = New Application_Model_DbTable_Claimstatus();
        $db = $db_claimstatus->getAdapter();
        $where = $db->quoteInto("claim_status_display = ?", $claimstatus['claim_status_display']);
        $dbexisting = $db_claimstatus->fetchAll($where);

        $status_ids = array();
        for ($i = 0; $i < count($dbexisting); $i++) {
            $status_ids[$i] = $dbexisting[$i]['id'];
        }
        $existlast = array();
        if (isset($dbexisting[0])) {
            $db_Billingcompanyclaimstatusexist = New Application_Model_DbTable_Billingcompanyclaimstatus();
            $dbexisthas = $db_Billingcompanyclaimstatusexist->getAdapter();
            $wherehas = $dbexisthas->quoteInto("claimstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
            $existlast = $db_Billingcompanyclaimstatusexist->fetchAll($wherehas)->toArray();
        }
        $data = array();
        if ($existlast) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function newclaimstatusAction() {
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == 'Save Claim Status') {
                // $claimstatus['claim_status'] = $this->getRequest()->getPost('claim_status');
                $claimstatus['claim_status_display'] = $this->getRequest()->getPost('claim_status_display');
                $claim_status = str_replace(' ', '_', $claimstatus['claim_status_display']);
                $claim_status = str_replace('-', '_', $claim_status);
                $claimstatus['claim_status'] = strtolower($claim_status);
                $db_claimstatus = New Application_Model_DbTable_Claimstatus();
                $db = $db_claimstatus->getAdapter();
                $where = $db->quoteInto("claim_status_display = ?", $claimstatus['claim_status_display']);
                $dbexisting = $db_claimstatus->fetchAll($where);
                $status_ids = array();
                for ($i = 0; $i < count($dbexisting); $i++) {
                    $status_ids[$i] = $dbexisting[$i]['id'];
                }
                $existlast = array();
                if (isset($dbexisting[0])) {
                    $db_Billingcompanyclaimstatusexist = New Application_Model_DbTable_Billingcompanyclaimstatus();
                    $dbexisthas = $db_Billingcompanyclaimstatusexist->getAdapter();
                    $wherehas = $dbexisthas->quoteInto("claimstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
                    $existlast = $db_Billingcompanyclaimstatusexist->fetchAll($wherehas)->toArray();
                }
                if (isset($existlast[0])) {
                    echo '<span style="color:red;font-size:16px">Sorry ! The Claim Status Display is existing , please rewrite!</span>';
                } else {
                    $id = $db_claimstatus->insert($claimstatus);
                    if ($id) {
                        $this->adddatalog(-1, $claimstatus['claim_status_display'], 'claimstatus.claim_status_display', NULL, $claimstatus['claim_status_display']);
                        $this->adddatalog(-1, $claimstatus['claim_status_display'], 'claimstatus.claim_status', NULL, $claimstatus['claim_status']);
                        //$this->adddatalog(0,$claimstatus['claim_status_display'],'claimstatus.claim_status',NULL,$claimstatus['']);
                    }
                    $billingcompanyclaimstatus['billingcompany_id'] = $this->billingcompany_id;
                    $billingcompanyclaimstatus['claimstatus_id'] = $id;

                    $db_billingcompanyclaimstatus = New Application_Model_DbTable_Billingcompanyclaimstatus();
                    $db = $db_billingcompanyclaimstatus->getAdapter();
                    if ($db_billingcompanyclaimstatus->insert($billingcompanyclaimstatus)) {
                        // $this->adddatalog(-1,$claimstatus['claim_status_display'],'billingcompanyclaimstatus.connection',NULL,'connection');
                    }

                    $this->_redirect('/biller/data/claimstatus');
                }
            }
            if ('Save Bill Status' == $submitType) {

                // $claimstatus['claim_status'] = $this->getRequest()->getPost('claim_status');
                $billstatus['bill_status_display'] = $this->getRequest()->getPost('bill_status_display');
                $bill_status = str_replace(' ', '_', $billstatus['bill_status_display']);
                $bill_status = str_replace('-', '_', $bill_status);
                $billstatus['bill_status'] = strtolower($bill_status);
                $db_billstatus = New Application_Model_DbTable_Billstatus();
                $db = $db_billstatus->getAdapter();
                $where = $db->quoteInto("bill_status_display = ?", $billstatus['bill_status_display']);
                $dbexisting = $db_billstatus->fetchAll($where);
                $status_ids = array();
                for ($i = 0; $i < count($dbexisting); $i++) {
                    $status_ids[$i] = $dbexisting[$i]['id'];
                }
                $existlast = array();
                if (isset($dbexisting[0])) {
                    $db_Billingcompanybillstatusexist = New Application_Model_DbTable_Billingcompanybillstatus();
                    $dbexisthas = $db_Billingcompanybillstatusexist->getAdapter();
                    $wherehas = $dbexisthas->quoteInto("billstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
                    $existlast = $db_Billingcompanybillstatusexist->fetchAll($wherehas)->toArray();
                }
                if (isset($existlast[0])) {
                    echo '<span style="color:red;font-size:16px">Sorry ! The Bill Status Display is existing , please rewrite!</span>';
                } else {
                    $id = $db_billstatus->insert($billstatus);
                    if ($id) {
                        $this->adddatalog(-1, $billstatus['bill_status_display'], 'billstatus.bill_status_display', NULL, $billstatus['bill_status_display']);
                        $this->adddatalog(-1, $billstatus['bill_status_display'], 'billstatus.bill_status', NULL, $billstatus['bill_status']);
                        //$this->adddatalog(0,$billstatus['bill_status_display'],'billstatus.bill_status',NULL,$billstatus['']);
                    }
                    $billingcompanybillstatus['billingcompany_id'] = $this->billingcompany_id;
                    $billingcompanybillstatus['billstatus_id'] = $id;

                    $db_billingcompanybillstatus = New Application_Model_DbTable_Billingcompanybillstatus();
                    $db = $db_billingcompanybillstatus->getAdapter();
                    if ($db_billingcompanybillstatus->insert($billingcompanybillstatus)) {
                        // $this->adddatalog(-1,$billstatus['bill_status_display'],'billingcompanybillstatus.connection',NULL,'connection');
                    }

                    $this->_redirect('/biller/data/claimstatus');
                }
            }
            if ('Save Statement Status' == $submitType) {
                $statementstatus['statement_status_display'] = $this->getRequest()->getPost('statement_status_display');
                $statement_status = str_replace(' ', '_', $statementstatus['statement_status_display']);
                $statement_status = str_replace('-', '_', $statement_status);
                $statementstatus['statement_status'] = strtolower($statement_status);
                $db_statementstatus = New Application_Model_DbTable_Statementstatus();
                $db = $db_statementstatus->getAdapter();
                $where = $db->quoteInto("statement_status_display = ?", $statementstatus['statement_status_display']);
                $dbexisting = $db_statementstatus->fetchAll($where);
                $status_ids = array();
                for ($i = 0; $i < count($dbexisting); $i++) {
                    $status_ids[$i] = $dbexisting[$i]['id'];
                }
                $existlast = array();
                if (isset($dbexisting[0])) {
                    $db_Billingcompanystatementstatusexist = New Application_Model_DbTable_Billingcompanystatementstatus();
                    $dbexisthas = $db_Billingcompanystatementstatusexist->getAdapter();
                    $wherehas = $dbexisthas->quoteInto("statementstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
                    $existlast = $db_Billingcompanystatementstatusexist->fetchAll($wherehas)->toArray();
                }
                if (isset($existlast[0])) {
                    echo '<span style="color:red;font-size:16px">Sorry ! The Statement Status Display is existing , please rewrite!</span>';
                } else {
                    $id = $db_statementstatus->insert($statementstatus);
                    if ($id) {
                        $this->adddatalog(-1, $statementstatus['statement_status_display'], 'statementstatus.statement_status_display', NULL, $statementstatus['statement_status_display']);
                        $this->adddatalog(-1, $statementstatus['statement_status_display'], 'statementstatus.statement_status', NULL, $statementstatus['statement_status']);
                        //$this->adddatalog(0,$billstatus['bill_status_display'],'billstatus.bill_status',NULL,$billstatus['']);
                    }
                    $billingcompanystatementstatus['billingcompany_id'] = $this->billingcompany_id;
                    $billingcompanystatementstatus['statementstatus_id'] = $id;

                    $db_billingcompanystatementstatus = New Application_Model_DbTable_Billingcompanystatementstatus();
                    $db = $db_billingcompanystatementstatus->getAdapter();
                    if ($db_billingcompanystatementstatus->insert($billingcompanystatementstatus)) {
                        // $this->adddatalog(-1,$billstatus['bill_status_display'],'billingcompanybillstatus.connection',NULL,'connection');
                    }

                    $this->_redirect('/biller/data/claimstatus');
                }
            }
        }
    }

    function claimstatusinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db_claimstatus = new Application_Model_DbTable_Claimstatus();
        $db = $db_claimstatus->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $claimstatus = $db_claimstatus->fetchRow($where);
        
        $db_bcs = new Application_Model_DbTable_Billingcompanyclaimstatus();
        $db_cs = $db_bcs->getAdapter();
        $where_cs = $db_cs->quoteInto('billingcompany_id =? ',$this->billingcompany_id).$db_cs->quoteInto('and claimstatus_id=?',$id);
        $bcsresult = $db_bcs->fetchAll($where_cs)->toArray();
        $status = $bcsresult[0]['status'];
        
        $data = array();
        $data = array('id' => $claimstatus['id'],
            'description' => $claimstatus['claim_status_display'],
            'required' => $claimstatus['required'],'status' => $status);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function billstatusAction() {
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('billstatus', array('billstatus.id AS bid', 'billstatus.bill_status AS bill_status', 'billstatus.bill_status_display AS bill_status_display'));
        $select->join('billingcompanybillstatus', 'billingcompanybillstatus.billstatus_id = billstatus.id');
        $select->where('billingcompanybillstatus.billingcompany_id = ?', $this->billingcompany_id());
        $select->group('billstatus.id');
        $select->order('billstatus.bill_status');
        try {
            $billstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }
        $this->view->billstatusList = $billstatuslist;



        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $id = $this->getRequest()->getPost('status_id');
                $billstatus['bill_status_display'] = $this->getRequest()->getPost('description');
                $db_billstatus['id'] = $id;
 
                $db_billstatus = new Application_Model_DbTable_Billstatus();
                $db = $db_billstatus->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldbill = $db_billstatus->fetchAll($where)->toArray();
                if ($db_billstatus->update($billstatus, $where)) {
                    $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status_display', $oldbill[0]['bill_status_display'], $billstatus['bill_status_display']);
                }

                $this->_redirect('/biller/data/billstatus');
            }
            if ($submitType == "Delete") {

                $id = $this->getRequest()->getPost('status_id');
                $billingcompanybillstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanybillstatus['billstatus_id'] = $id;

                $db_billingcompanybillstatus = New Application_Model_DbTable_Billingcompanybillstatus();
                $db = $db_billingcompanybillstatus->getAdapter();
                $db_billstatus = new Application_Model_DbTable_Billstatus();
                $db1 = $db_billstatus->getAdapter();
                $where = $db1->quoteInto('id = ?', $id);
                $oldbill = $db_billstatus->fetchAll($where)->toArray();
                $wherehas = $db->quoteInto('billingcompany_id =' . $billingcompanybillstatus['billingcompany_id'] . ' AND billstatus_id=' . $billingcompanybillstatus['billstatus_id']);
                try {


                    if ($db_billingcompanybillstatus->delete($wherehas)) {
                        // $this->adddatalog(-1,$oldbill[0]['bill_status_display'],'billingcompanybillstatus.connection','connection',NULL);
                        // $this->adddatalog(0,$oldbill[0]['bill_status_display'],'billstatus.bill_status_display',$oldbill[0]['bill_status_display'],$billstatus['bill_status_display']);
                    }
                } catch (Exception $ex) {
                    
                }

                try {
                    if ($db_billstatus->delete($where)) {
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status_display', $oldbill[0]['bill_status_display'], NULL);
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.required', $oldbill[0]['required'], NULL);
                        $this->adddatalog(-1, $oldbill[0]['bill_status_display'], 'billstatus.bill_status', $oldbill[0]['bill_status'], NULL);
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $this->_redirect('/biller/data/billstatus');
            }
            if ($submitType == "New") {
                $this->_redirect('/biller/data/newbillstatus');
            }
        }
    }

    public function newbillstatusAction() {
        if ($this->getRequest()->isPost()) {

            // $claimstatus['claim_status'] = $this->getRequest()->getPost('claim_status');
            $billstatus['bill_status_display'] = $this->getRequest()->getPost('bill_status_display');
            $bill_status = str_replace(' ', '_', $billstatus['bill_status_display']);
            $bill_status = str_replace('-', '_', $bill_status);
            $billstatus['bill_status'] = strtolower($bill_status);
            $db_billstatus = New Application_Model_DbTable_Billstatus();
            $db = $db_billstatus->getAdapter();
            $where = $db->quoteInto("bill_status_display = ?", $billstatus['bill_status_display']);
            $dbexisting = $db_billstatus->fetchAll($where);
            $status_ids = array();
            for ($i = 0; $i < count($dbexisting); $i++) {
                $status_ids[$i] = $dbexisting[$i]['id'];
            }
            $existlast = array();
            if (isset($dbexisting[0])) {
                $db_Billingcompanybillstatusexist = New Application_Model_DbTable_Billingcompanybillstatus();
                $dbexisthas = $db_Billingcompanybillstatusexist->getAdapter();
                $wherehas = $dbexisthas->quoteInto("billstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
                $existlast = $db_Billingcompanybillstatusexist->fetchAll($wherehas)->toArray();
            }
            if (isset($existlast[0])) {
                echo '<span style="color:red;font-size:16px">Sorry ! The Bill Status Display is existing , please rewrite!</span>';
            } else {
                $id = $db_billstatus->insert($billstatus);
                if ($id) {
                    $this->adddatalog(-1, $billstatus['bill_status_display'], 'billstatus.bill_status_display', NULL, $billstatus['bill_status_display']);
                    $this->adddatalog(-1, $billstatus['bill_status_display'], 'billstatus.bill_status', NULL, $billstatus['bill_status']);
                    //$this->adddatalog(0,$billstatus['bill_status_display'],'billstatus.bill_status',NULL,$billstatus['']);
                }
                $billingcompanybillstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanybillstatus['billstatus_id'] = $id;

                $db_billingcompanybillstatus = New Application_Model_DbTable_Billingcompanybillstatus();
                $db = $db_billingcompanybillstatus->getAdapter();
                if ($db_billingcompanybillstatus->insert($billingcompanybillstatus)) {
                    // $this->adddatalog(-1,$billstatus['bill_status_display'],'billingcompanybillstatus.connection',NULL,'connection');
                }

                $this->_redirect('/biller/data/billstatus');
            }
        }
    }

    public function billstatusexistingAction() {

        $this->_helper->viewRenderer->setNoRender();
        $billstatus['bill_status_display'] = $this->getRequest()->getPost('bill_status_display');

        $db_billstatus = New Application_Model_DbTable_Billstatus();
        $db = $db_billstatus->getAdapter();
        $where = $db->quoteInto("bill_status_display = ?", $billstatus['bill_status_display']);
        $dbexisting = $db_billstatus->fetchAll($where);

        $status_ids = array();
        for ($i = 0; $i < count($dbexisting); $i++) {
            $status_ids[$i] = $dbexisting[$i]['id'];
        }
        $existlast = array();
        if (isset($dbexisting[0])) {
            $db_Billingcompanybillstatusexist = New Application_Model_DbTable_Billingcompanybillstatus();
            $dbexisthas = $db_Billingcompanybillstatusexist->getAdapter();
            $wherehas = $dbexisthas->quoteInto("billstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
            $existlast = $db_Billingcompanybillstatusexist->fetchAll($wherehas)->toArray();
        }
        $data = array();
        if ($existlast) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function billstatusinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db_billstatus = new Application_Model_DbTable_Billstatus();
        $db = $db_billstatus->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $billstatus = $db_billstatus->fetchRow($where);

        $db_bbs = new Application_Model_DbTable_Billingcompanybillstatus();
        $db_bs = $db_bbs->getAdapter();
        $where_bs = $db_bs->quoteInto('billingcompany_id =? ',$this->billingcompany_id).$db_bs->quoteInto('and billstatus_id=?',$id);
        $bbsresult = $db_bbs->fetchAll($where_bs)->toArray();
        $status = $bbsresult[0]['status'];
        
        $data = array();
        $data = array('id' => $billstatus['id'],
            'description' => $billstatus['bill_status_display'],
            'required' => $billstatus['required'],'status'=>$status);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    public function statementstatusAction() {
        $db = Zend_Registry::get('dbAdapter');
        //get the claim_status list
        $select = $db->select();
        $select->from('statementstatus', array('statementstatus.id AS sid', 'statementstatus.statement_status AS statement_status', 'statementstatus.statement_status_display AS statement_status_display'));
        $select->join('billingcompanystatementstatus', 'billingcompanystatementstatus.statementstatus_id = statementstatus.id');
        $select->where('billingcompanystatementstatus.billingcompany_id = ?', $this->billingcompany_id());
        $select->group('statementstatus.id');
        $select->order('statementstatus.statement_status');
        try {
            $statementstatuslist = $db->fetchAll($select);
        } catch (Exception $e) {
            echo "errormessage:" + $e->getMessage();
        }
        $this->view->statementstatusList = $statementstatuslist;



        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $id = $this->getRequest()->getPost('status_id');
                $statementstatus['statement_status_display'] = $this->getRequest()->getPost('description');
                $db_statementstatus['id'] = $id;

                $db_statementstatus = new Application_Model_DbTable_Statementstatus();
                $db = $db_statementstatus->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $oldstatement = $db_statementstatus->fetchAll($where)->toArray();
                if ($db_statementstatus->update($statementstatus, $where)) {
                    $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status_display', $oldstatement[0]['statement_status_display'], $statementstatus['statement_status_display']);
                }

                $this->_redirect('/biller/data/statementstatus');
            }
            if ($submitType == "Delete") {

                $id = $this->getRequest()->getPost('status_id');
                $billingcompanystatementstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanystatementstatus['statementstatus_id'] = $id;

                $db_billingcompanystatementstatus = New Application_Model_DbTable_Billingcompanystatementstatus();
                $db = $db_billingcompanystatementstatus->getAdapter();
                $db_statementstatus = new Application_Model_DbTable_Statementstatus();
                $db1 = $db_statementstatus->getAdapter();
                $where = $db1->quoteInto('id = ?', $id);
                $oldstatement = $db_statementstatus->fetchAll($where)->toArray();
                $wherehas = $db->quoteInto('billingcompany_id =' . $billingcompanystatementstatus['billingcompany_id'] . ' AND statementstatus_id=' . $billingcompanystatementstatus['statementstatus_id']);
                try {


                    if ($db_billingcompanystatementstatus->delete($wherehas)) {
                        // $this->adddatalog(-1,$oldbill[0]['bill_status_display'],'billingcompanybillstatus.connection','connection',NULL);
                        // $this->adddatalog(0,$oldbill[0]['bill_status_display'],'billstatus.bill_status_display',$oldbill[0]['bill_status_display'],$billstatus['bill_status_display']);
                    }
                } catch (Exception $ex) {
                    
                }

                try {
                    if ($db_statementstatus->delete($where)) {
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status_display', $oldstatement[0]['statement_status_display'], NULL);
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.required', $oldstatement[0]['required'], NULL);
                        $this->adddatalog(-1, $oldstatement[0]['statement_status_display'], 'statementstatus.statement_status', $oldstatement[0]['statement_status'], NULL);
                    }
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                $this->_redirect('/biller/data/statementstatus');
            }
            if ($submitType == "New") {
                $this->_redirect('/biller/data/newstatementstatus');
            }
        }
    }

    public function newstatementstatusAction() {
        if ($this->getRequest()->isPost()) {

            // $claimstatus['claim_status'] = $this->getRequest()->getPost('claim_status');
            $statementstatus['statement_status_display'] = $this->getRequest()->getPost('statement_status_display');
            $statement_status = str_replace(' ', '_', $statementstatus['statement_status_display']);
            $statement_status = str_replace('-', '_', $statement_status);
            $statementstatus['statement_status'] = strtolower($statement_status);
            $db_statementstatus = New Application_Model_DbTable_Statementstatus();
            $db = $db_statementstatus->getAdapter();
            $where = $db->quoteInto("statement_status_display = ?", $statementstatus['statement_status_display']);
            $dbexisting = $db_statementstatus->fetchAll($where);
            $status_ids = array();
            for ($i = 0; $i < count($dbexisting); $i++) {
                $status_ids[$i] = $dbexisting[$i]['id'];
            }
            $existlast = array();
            if (isset($dbexisting[0])) {
                $db_Billingcompanystatementstatusexist = New Application_Model_DbTable_Billingcompanystatementstatus();
                $dbexisthas = $db_Billingcompanystatementstatusexist->getAdapter();
                $wherehas = $dbexisthas->quoteInto("statementstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
                $existlast = $db_Billingcompanystatementstatusexist->fetchAll($wherehas)->toArray();
            }
            if (isset($existlast[0])) {
                echo '<span style="color:red;font-size:16px">Sorry ! The Statement Status Display is existing , please rewrite!</span>';
            } else {
                $id = $db_statementstatus->insert($statementstatus);
                if ($id) {
                    $this->adddatalog(-1, $statementstatus['statement_status_display'], 'statementstatus.statement_status_display', NULL, $statementstatus['statement_status_display']);
                    $this->adddatalog(-1, $statementstatus['statement_status_display'], 'statementstatus.statement_status', NULL, $statementstatus['statement_status']);
                    //$this->adddatalog(0,$billstatus['bill_status_display'],'billstatus.bill_status',NULL,$billstatus['']);
                }
                $billingcompanystatementstatus['billingcompany_id'] = $this->billingcompany_id;
                $billingcompanystatementstatus['statementstatus_id'] = $id;

                $db_billingcompanystatementstatus = New Application_Model_DbTable_Billingcompanystatementstatus();
                $db = $db_billingcompanystatementstatus->getAdapter();
                if ($db_billingcompanystatementstatus->insert($billingcompanystatementstatus)) {
                    // $this->adddatalog(-1,$billstatus['bill_status_display'],'billingcompanybillstatus.connection',NULL,'connection');
                }

                $this->_redirect('/biller/data/statementstatus');
            }
        }
    }

    public function statementstatusexistingAction() {

        $this->_helper->viewRenderer->setNoRender();
        $statementstatus['statement_status_display'] = $this->getRequest()->getPost('statement_status_display');

        $db_statementstatus = New Application_Model_DbTable_Statementstatus();
        $db = $db_statementstatus->getAdapter();
        $where = $db->quoteInto("statement_status_display = ?", $statementstatus['statement_status_display']);
        $dbexisting = $db_statementstatus->fetchAll($where);

        $status_ids = array();
        for ($i = 0; $i < count($dbexisting); $i++) {
            $status_ids[$i] = $dbexisting[$i]['id'];
        }
        $existlast = array();
        if (isset($dbexisting[0])) {
            $db_Billingcompanystatementstatusexist = New Application_Model_DbTable_Billingcompanystatementstatus();
            $dbexisthas = $db_Billingcompanystatementstatusexist->getAdapter();
            $wherehas = $dbexisthas->quoteInto("statementstatus_id in (?)", $status_ids) . $dbexisthas->quoteInto("and billingcompany_id = ?", $this->billingcompany_id);
            $existlast = $db_Billingcompanystatementstatusexist->fetchAll($wherehas)->toArray();
        }
        $data = array();
        if ($existlast) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function statementstatusinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db_statementstatus = new Application_Model_DbTable_Statementstatus();
        $db = $db_statementstatus->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $statementstatus = $db_statementstatus->fetchRow($where);

        $db_bss = new Application_Model_DbTable_Billingcompanystatementstatus();
        $db_ss = $db_bss->getAdapter();
        $where_ss = $db_ss->quoteInto('billingcompany_id =? ',$this->billingcompany_id).$db_ss->quoteInto('and statementstatus_id=?',$id);
        $bssresult = $db_bss->fetchAll($where_ss)->toArray();
        $status = $bssresult[0]['status'];
        
        $data = array();
        $data = array('id' => $statementstatus['id'],
            'description' => $statementstatus['statement_status_display'],
            'required' => $statementstatus['required'],"status"=>$status);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * diagnosiscodeAction
     * a function for processing the diagnosiscode data.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function diagnosiscodeAction() {
        //this is used for test if input excel file function works.
//        $data = array();
//        $filepath = "../library/ICD9_DX_Codes.xls";
//        $data = $this->excel2array($filepath,0);
//
//get icd_9 related data:start
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasDiag.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM providerhasdiagnosiscode has WHERE has.diagnosiscode_id IN 
        (
            SELECT t.id FROM 
                (
                    SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
                    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY diagnosiscode_id
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasDiag
ON hasDiag.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasdiagnosiscode has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.diagnosiscode_id in 
(
SELECT t.id FROM 
(
    SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p ,diagnosiscode diag
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and hs.diagnosiscode_id=diag.id and diag.diagnosis_code <> 'Need New') 
    GROUP BY diagnosiscode_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $this->view->allNum = isset($all_data[0]) ? $all_data[0] : array('num' => 0);
     
//get icd_9 related data:end
        
//get icd_10 related data:start
//        $user = Zend_Auth::getInstance()->getIdentity();
//        //获取数据库适配器
//        $db = Zend_Registry::get('dbAdapter');
        //sql语句
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasDiag.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM providerhasdiagnosiscode_10 has WHERE has.diagnosiscode_10_id IN 
        (
            SELECT t.id FROM 
                (
                    SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p 
                    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY diagnosiscode_10_id
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasDiag
ON hasDiag.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $icd10_result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $icd10_provider_data = $icd10_result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->icd10_providerList = $icd10_provider_data;
        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasdiagnosiscode_10 has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.diagnosiscode_10_id in 
(
SELECT t.id FROM 
(
    SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p ,diagnosiscode_10 diag
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and hs.diagnosiscode_10_id=diag.id and diag.diagnosis_code <> 'Need New') 
    GROUP BY diagnosiscode_10_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
GROUP BY p.id LIMIT 1
SQL
        ;
        $icd10_result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        $icd10_all_data = $icd10_result->fetchAll();
        $this->view->icd10_allNum = isset($icd10_all_data[0]) ? $icd10_all_data[0] : array('num' => 0);
//get icd_10 related data:end

        if ($this->getRequest()->isPost()) {
            $codeType = $this->getRequest()->getParam('code_type');
            if ($codeType == 'icd9') {
                $submitType = $this->getRequest()->getParam('submit');
                session_start();
                $diagnosis_code = $this->getRequest()->getPost('diagnosis_code');
                $diagnosis_code = strtok($diagnosis_code, ' ');
                $diagnosiscodeList = $_SESSION['diagnosiscodeList'];
                $_SESSION['diagnosiscodeList'] = null;
                $id = 0;
                foreach ($diagnosiscodeList as $row) {
                    if ($row['diagnosis_code'] == $diagnosis_code) {
                        $id = $row['id'];
                        break;
                    }
                }
                if ($submitType == "Update") {
                    $diagnosiscode['description'] = $this->getRequest()->getPost('customized_description');
                    $description = $diagnosiscode['description'];
                    $provider_id = $this->getRequest()->getPost('provider_id');
                    if ($id != '' && $description != '') {
                        $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                        $db = $db_diagnosiscode->getAdapter();
                        $where = $db->quoteInto('id =' . $id);
                        $olddiag = $db_diagnosiscode->fetchAll($where)->toArray();
                        if ($db_diagnosiscode->update($diagnosiscode, $where)) {
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], $description);
                        }

                        /*                         * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
                        session_start();
                        $_SESSION['management_data']['provider_id'] = $provider_id;

                        $this->_redirect('/biller/data/diagnosiscode');
                    }
                }
                if ($submitType == "Delete") {

                    $provider_id = $this->getRequest()->getPost('provider_id');
                    $del_provider_id = $this->getrequest()->getPost("del_provider_id");
                    $db_phasdiag = new Application_Model_DbTable_Providerhasdiagnosiscode();
                    $db = $db_phasdiag->getAdapter();
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                    $db_diag = $db_diagnosiscode->getAdapter();
                    if (empty($id))
                        $this->_redirect('/biller/data/facility');
                    if ($provider_id != '0') {
                        $where = $db->quoteInto('provider_id =' . $provider_id . ' AND diagnosiscode_id=' . $id);
                        $whereold = $db_diag->quoteInto('id=?', $id);
                        $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();

                        if ($db_phasdiag->delete($where)) {
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                            // $this->adddatalog($provider_id,$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                        }
                    } else {
                        if ($del_provider_id != '0') {
                            //echo '111';
                            //die($del_provider_id);
                            $where = $db->quoteInto('provider_id =' . $del_provider_id . ' AND diagnosiscode_id=' . $id);
                            $whereold = $db_diag->quoteInto('id=?', $id);
                            $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();

                            if ($db_phasdiag->delete($where)) {
                                $this->adddatalog($del_provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                                $this->adddatalog($del_provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                                //  $this->adddatalog($del_provider_id,$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                            }
                        } else {
                            // echo '222';
                            // die($del_provider_id);
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $where = $db->quoteInto('provider_id =' . $provider_data[$i]['id'] . ' AND diagnosiscode_id=' . $id);
                                $whereold = $db_diag->quoteInto('id=?', $id);
                                $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();
                                if ($db_phasdiag->delete($where)) {
                                    $this->adddatalog($provider_data[$i]['id'], $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                                    $this->adddatalog($provider_data[$i]['id'], $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                                    // $this->adddatalog($provider_data[$i]['id'],$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                                }
                            }
                        }
                    }
                    session_start();
                    $_SESSION['management_data']['provider_id'] = $provider_id;

                    $this->_redirect('/biller/data/diagnosiscode');
                    //$db_diagnosiscode->delete($where);
//                    $this->_redirect('/biller/data/diagnosiscode');
                }

                if ($submitType == "New"){
                    session_start();
                    $_SESSION['newtype'] = "icd9";
                    $this->_redirect('/biller/data/newdiagnosiscode');
                }
            }else if($codeType == 'icd10'){
                $submitType = $this->getRequest()->getParam('submit');
                session_start();
                $diagnosis_code = $this->getRequest()->getPost('icd10_diagnosis_code');
                $diagnosis_code = strtok($diagnosis_code, ' ');
//                        $_SESSION['icd10_diagnosiscodeList'] = $diagnosiscode_data;
                $diagnosiscodeList = $_SESSION['icd10_diagnosiscodeList'];
                $_SESSION['icd10_diagnosiscodeList'] = null;
                $id = 0;
                foreach ($diagnosiscodeList as $row) {
                    if ($row['diagnosis_code'] == $diagnosis_code) {
                        $id = $row['id'];
                        break;
                    }
                }
                if ($submitType == "Update") {
                    $diagnosiscode['description'] = $this->getRequest()->getPost('icd10_customized_description');
                    $description = $diagnosiscode['description'];
                    $provider_id = $this->getRequest()->getPost('icd10_provider_id');
                    $diagnosiscode_data['status'] = $this->getRequest()->getPost('status'); 
                    $icd10_del_provider_id = $this->getRequest()->getPost('icd10_del_provider_id'); 
                    
                    if($provider_id==0){
                        
                        if($icd10_del_provider_id==0){
                             //到providerhas 表中，拿provider和diagnosis 进行相应的更新
                            $billingcompany_id = $this->billingcompany_id();
                            $db_provider = new Application_Model_DbTable_Provider();
                            $db = $db_provider->getAdapter();
                            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                            $provider_data = $db_provider->fetchAll($where, "provider_name ASC")->toArray();

                            //获取所有provider_id 因为可能不同公司同时把 某个provider设置为ALL 模式
                            $providers = array();
                            if($provider_data){
                                $len = count($provider_data);
                                for($i=0;$i<$len;$i++){
                                    $providers[$i] = $provider_data[$i]['id'];
                                }
                            }
                            $db_phasdia_10 = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                            $db_pd_10 = $db_phasdia_10->getAdapter();
                            for($i=0;$i<count($providers);$i++){
                                $where_update_pre = $db->quoteInto('provider_id =?' ,$providers[$i]).$db->quoteInto(' AND diagnosiscode_10_id =?',$id);
                                $result_dia_10 = $db_phasdia_10->fetchAll($where_update_pre)->toArray();
                                $len = count($result_dia_10);
                                $where_update = $db->quoteInto('id=?' ,$result_dia_10[0]['id']);
                                $db_phasdia_10->update($diagnosiscode_data, $where_update);
                            }         
                        }else{
                            $db_phasdia_10 = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                            $db_pd_10 = $db_phasdia_10->getAdapter();
                            $where_update_pre = $db->quoteInto('provider_id =?' ,$icd10_del_provider_id).$db->quoteInto(' AND diagnosiscode_10_id =?',$id);
                            $result_dia_10 = $db_phasdia_10->fetchAll($where_update_pre)->toArray();
                            $len = count($result_dia_10);
                            $where_update = $db->quoteInto('id=?' ,$result_dia_10[0]['id']);
                            $db_phasdia_10->update($diagnosiscode_data, $where_update);
                        }
                           
                    }
                        
                    if ($id != '' && $description != '') { 
                    //if ($id != '') {
                        $db_diagnosiscode_10 = new Application_Model_DbTable_Diagnosiscode10();
                        $db = $db_diagnosiscode_10->getAdapter();
                        $where = $db->quoteInto('id =' . $id);
                        $olddiag = $db_diagnosiscode_10->fetchAll($where)->toArray();
                        if ($db_diagnosiscode_10->update($diagnosiscode, $where)) {
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode_10.description', $olddiag[0]['description'], $description);
                        }

                        /*                         * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
                        session_start();
                        $_SESSION['management_data']['provider_id'] = $provider_id;
                        $_SESSION['management_data']['type'] = "icd10";
                        $this->_redirect('/biller/data/diagnosiscode');
                    }
                }
                if ($submitType == "Delete") {

                    $provider_id = $this->getRequest()->getPost('icd10_provider_id');
                    $del_provider_id = $this->getrequest()->getPost("icd10_del_provider_id");
                    $db_phasdiag = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                    $db = $db_phasdiag->getAdapter();
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode10();
                    $db_diag = $db_diagnosiscode->getAdapter();
                    if (empty($id))
                        $this->_redirect('/biller/data/diagnosiscode');
                    if ($provider_id != '0') {
                        $where = $db->quoteInto('provider_id =' . $provider_id . ' AND diagnosiscode_10_id=' . $id);
                        $whereold = $db_diag->quoteInto('id=?', $id);
                        $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();

                        if ($db_phasdiag->delete($where)) {
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                            $this->adddatalog($provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                            // $this->adddatalog($provider_id,$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                        }
                    } else {
                        if ($del_provider_id != '0') {
                            //echo '111';
                            //die($del_provider_id);
                            $where = $db->quoteInto('provider_id =' . $del_provider_id . ' AND diagnosiscode_10_id=' . $id);
                            $whereold = $db_diag->quoteInto('id=?', $id);
                            $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();

                            if ($db_phasdiag->delete($where)) {
                                $this->adddatalog($del_provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                                $this->adddatalog($del_provider_id, $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                                //  $this->adddatalog($del_provider_id,$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                            }
                        } else {
                            // echo '222';
                            // die($del_provider_id);
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $where = $db->quoteInto('provider_id =' . $provider_data[$i]['id'] . ' AND diagnosiscode_10_id=' . $id);
                                $whereold = $db_diag->quoteInto('id=?', $id);
                                $olddiag = $db_diagnosiscode->fetchAll($whereold)->toArray();
                                if ($db_phasdiag->delete($where)) {
                                    $this->adddatalog($provider_data[$i]['id'], $olddiag[0]['diagnosis_code'], 'diagnosiscode.diagnosis_code', $olddiag[0]['diagnosis_code'], NULL);
                                    $this->adddatalog($provider_data[$i]['id'], $olddiag[0]['diagnosis_code'], 'diagnosiscode.description', $olddiag[0]['description'], NULL);
                                    // $this->adddatalog($provider_data[$i]['id'],$olddiag[0]['diagnosis_code'],'diagnosiscode.providerhasdiagnosiscode','connection',NULL);
                                }
                            }
                        }
                    }
                    session_start();
                    $_SESSION['management_data']['provider_id'] = $provider_id;
                    $_SESSION['management_data']['type'] = "icd10";

                    $this->_redirect('/biller/data/diagnosiscode');
                    //$db_diagnosiscode->delete($where);
//                    $this->_redirect('/biller/data/diagnosiscode');
                }

                if ($submitType == "New"){
                    session_start();
                    $_SESSION['newtype'] = "icd10";
                    $this->_redirect('/biller/data/newdiagnosiscode');
                }
            }
        }
    }

    /**
     * by haoqiang
     * 返回diagnosis 的状态
     */
    public function diacheckstatusAction(){
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $this->_request->getPost('provider_id');
        $diagnosis_code = $this->_request->getPost('diagnosis_code');
        
        $db_dia = new Application_Model_DbTable_Diagnosiscode10();
        $db = $db_dia->getAdapter();
        $where = $db->quoteInto('diagnosis_code=?',$diagnosis_code);
        $result = $db_dia->fetchAll($where)->toArray();
        $diagnosis_id = $result[0]['id'];
        
        $db_providerhasdia = new Application_Model_DbTable_Providerhasdiagnosiscode10();
        $db_p = $db_providerhasdia->getAdapter();
        $where_p = $db_p->quoteInto('diagnosiscode_10_id=?',$diagnosis_code).$db_p->quoteInto(' AND provider_id=?',$provider_id);
        $pro_result = $db_providerhasdia->fetchAll($where_p)->toArray();
        
        $data['status'] = $pro_result[0]['status'];
        $json = Zend_Json::encode($data);
        echo $json;
    }
    function diagnosiscodeAction1() {
        /*  by pandazhao 2012/7/29 */
//        $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
//        $db = $db_diagnosiscode->getAdapter();
//        $diagnosiscode_data = $db_diagnosiscode->fetchAll(null, 'diagnosis_code  ASC');
//
//        $this->view->diagnosiscodeList = $diagnosiscode_data;


        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;

        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $id = $this->getRequest()->getPost('diagnosis_code');
                $diagnosiscode['description'] = $this->getRequest()->getPost('description');
                $provider_id = $this->getRequest()->getPost('provider_id');


                $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                $db = $db_diagnosiscode->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                if ($provider_id != 0) {
//                $diagnosiscode['provider_id'] = $provider_id;
                } else {
                    $temp = $db_diagnosiscode->fetchRow($where);
//                        $diagnosiscode['diagnosis_code']= $temp['diagnosis_code'];
                    for ($i = 0; $i < count($provider); $i++) {

                        $where = $db->quoteInto('diagnosis_code = ?', $temp['diagnosis_code']) . $db->quoteInto('AND provider_id = ?', $provider[$i]['id']);
//                        $diagnosiscode['provider_id'] = $provider[$i]['id'];


                        $db_diagnosiscode->update($diagnosiscode, $where);
                    }
                }
                $db_diagnosiscode->update($diagnosiscode, $where);
                $this->_redirect('/biller/data/diagnosiscode');
            }
            if ($submitType == "Delete") {
                $id = $this->getRequest()->getPost('diagnosis_code');
                $provider_id = $this->getRequest()->getPost('provider_id');
                $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                $db = $db_diagnosiscode->getAdapter();
                $where = $db->quoteInto('id = ?', $id);

                if ($provider_id != 0) {
//                $diagnosiscode['provider_id'] = $provider_id;
                } else {
                    $temp = $db_diagnosiscode->fetchRow($where);
//                        $diagnosiscode['diagnosis_code']= $temp['diagnosis_code'];
                    for ($i = 0; $i < count($provider); $i++) {

                        $where = $db->quoteInto('diagnosis_code = ?', $temp['diagnosis_code']) . $db->quoteInto('AND provider_id = ?', $provider[$i]['id']);
//                        $diagnosiscode['provider_id'] = $provider[$i]['id'];


                        $db_diagnosiscode->delete($where);
                    }
                }

                $db_diagnosiscode->delete($where);
                $this->_redirect('/biller/data/diagnosiscode');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newdiagnosiscode');
        }
    }

    /**
     * completedxcodesAction
     * a to get a list of all diagnosis codes in DB imported from excel.
     * @author james.
     * @version 05/22/2015
     */
//select all
    public function completedxcodesAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db = Zend_Registry::get('dbAdapter');
        session_start();
        $code_type = $_SESSION['newtype'];
        if($code_type == "icd9"){
            $sql = <<<SQL
   select code,description
   from completedxcodes
SQL;
        //查询并获取结果
        $result = $db->query($sql);
        //获取查询数据
        $diagnosiscode_data = $result->fetchAll();
        $diagnosiscodeList = array();
        $diagnosiscodeInfo['diagnosis_code'] = $diagnosiscode_data['code'];
        $diagnosiscodeInfo['description'] = $diagnosiscode_data['description'];
        session_start();
        $_SESSION['diagnosiscodeList'] = $diagnosiscodeInfo;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['code'] . " " . $row['description'];
            $idx++;
        }
        $data = array();
        $data['exist_data_excel'] = $diagnosiscodeList;
        $json = Zend_Json::encode($data);
        echo $json;
    }else if($code_type == "icd10"){
        $sql = <<<SQL
   select code,description
   from completedxcodes_10
SQL;
        //查询并获取结果
        $result = $db->query($sql);
        //获取查询数据
        $diagnosiscode_data = $result->fetchAll();
        $diagnosiscodeList = array();
        $diagnosiscodeInfo['diagnosis_code'] = $diagnosiscode_data['code'];
        $diagnosiscodeInfo['description'] = $diagnosiscode_data['description'];
        session_start();
        $_SESSION['diagnosiscodeList'] = $diagnosiscodeInfo;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['code'] . " " . $row['description'];
            $idx++;
        }
        $data = array();
        $data['exist_data_excel'] = $diagnosiscodeList;
        $json = Zend_Json::encode($data);
        echo $json;
    }
  }

  /**
     * exceldiagnosiscodesAction
     * a to get a list of all diagnosis code from excel.
     * @author james.
     * @version 05/19/2015
     */
    public function exceldiagnosiscodesAction() {
        $this->_helper->viewRenderer->setNoRender();

        $file = '../library/ICD9_DX_Codes.xls';
        $diagnosiscode_data = $this->excel2array($file, 0);
        $diagnosiscode_data = $diagnosiscode_data[shellContent];

        $diagnosiscodeList = array();
        session_start();
        $_SESSION['diagnosiscodeList'] = $diagnosiscode_data;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row[1] . " " . $row[2];
            $idx++;
        }

        $data = array();
        $data['exist_data_excel'] = $diagnosiscodeList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * getexceldiagnosiscodes
     * a to get a list of all diagnosis code from excel.
     * @author james.
     * @version 05/19/2015
     */
    public function getexceldiagnosiscodes() {

        $file = '../library/ICD9_DX_Codes.xls';
        $diagnosiscode_data = $this->excel2array($file, 0);
        $diagnosiscode_data = $diagnosiscode_data[shellContent];

        $diagnosiscodeList = array();
        session_start();
        $_SESSION['diagnosiscodeList'] = $diagnosiscode_data;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row[1] . " " . $row[2];
            $idx++;
        }
        return $diagnosiscodeList;
    }

    /**
     * 
     * a to get a list of all diagnosis code.
     * @author james.
     * @version 05/19/2015
     */
    public function allproviderhaveAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        //$provider_id = $this->getRequest()->getPost('diagnosis_code');
        if (empty($provider_id) && $provider_id != '0') {
            $data = array();
            $exist_data = array();
            $data['exist_data'] = $exist_data;
            $json = Zend_Json::encode($data);
            echo $json;
            return;
        }
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        session_start();
        $code_type = $_SESSION['newtype'];
        if ($code_type == "icd9") {
            $sql = <<<SQL
   select diagnosiscode.id,diagnosis_code,description
   from diagnosiscode left join providerhasdiagnosiscode on diagnosiscode_id = diagnosiscode.id
   where diagnosiscode.id <> ANY 
   (
        select diagnosiscode_id from providerhasdiagnosiscode where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   order by    diagnosis_code
SQL;
            $paras = array($provider_id, $billingcompany_id);
            //查询并获取结果
            $result = $db->query($sql, $paras);
            //获取查询数据
            $diagnosiscode_data = $result->fetchAll();
            $diagnosiscodeList = array();
            session_start();
            $_SESSION['diagnosiscodeList'] = $diagnosiscode_data;
            $idx = 0;
            foreach ($diagnosiscode_data as $row) {
                $temp_value = $row['diagnosis_code'] . " " . $row['description'];
                if(!in_array($temp_value, $diagnosiscodeList)){
                    $diagnosiscodeList[$idx] = $temp_value;
                    $idx++;
                }
            }
            $data = array();
            $data['exist_data'] = $diagnosiscodeList;
            $json = Zend_Json::encode($data);
            echo $json;
        } else if ($code_type == "icd10") {
            $sql = <<<SQL
   select diagnosiscode_10.id,diagnosis_code,description
   from diagnosiscode_10 left join providerhasdiagnosiscode_10 on diagnosiscode_10_id = diagnosiscode_10.id
   where diagnosiscode_10.id <> ANY 
   (
        select diagnosiscode_10_id from providerhasdiagnosiscode_10 where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   order by    diagnosis_code
SQL;
            $paras = array($provider_id, $billingcompany_id);
            //查询并获取结果
            $result = $db->query($sql, $paras);
            //获取查询数据
            $diagnosiscode_data = $result->fetchAll();
            $diagnosiscodeList = array();
            session_start();
            $_SESSION['diagnosiscodeList'] = $diagnosiscode_data;
            $idx = 0;
            foreach ($diagnosiscode_data as $row) {
                $temp_value = $row['diagnosis_code'] . " " . $row['description'];
                if(!in_array($temp_value, $diagnosiscodeList)){
                    $diagnosiscodeList[$idx] = $temp_value;
                    $idx++;
                }
            }
            $data = array();
            $data['exist_data'] = $diagnosiscodeList;
            $json = Zend_Json::encode($data);
            echo $json;
        }
    }

    public function diagnosiscodeexistlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        //$provider_id = $this->getRequest()->getPost('diagnosis_code');
        if (empty($provider_id) && $provider_id != '0') {
            $data = array();
            $exist_data = array();
            $data['exist_data'] = $exist_data;
            $json = Zend_Json::encode($data);
            echo $json;
            return;
        }
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        $sql = <<<SQL
   select diagnosiscode.id,diagnosis_code 
   from diagnosiscode left join providerhasdiagnosiscode on diagnosiscode_id = diagnosiscode.id
   where diagnosiscode.id not in 
   (
        select diagnosiscode_id from providerhasdiagnosiscode where provider_id = ?
   ) and provider_id in 
   (
       select id from provider where billingcompany_id = ?
   )
   order by    diagnosis_code
SQL;
        $paras = array($provider_id, $billingcompany_id);
        $result = $db->query($sql, $paras);
        $exist_data = $result->fetchAll();
        $data = array();
        $data['exist_data'] = $exist_data;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function diagnosiscodeexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        //get provider data 
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, 'provider_name ASC');
        $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
        $db = $db_diagnosiscode->getAdapter();
        //get the new diagnosiscode 
        $diagnosiscodenew = array();
        $diagnosiscodenew['diagnosis_code'] = $this->getRequest()->getPost('add_diagnosis_code');
        $diagnosiscode_get = $diagnosiscodenew['diagnosis_code'];
        $providerid_get = $this->getRequest()->getPost('provider_id');

        //juge if exist the diagnosiscode in the diagnosiscode table

        $where = $db->quoteInto("diagnosis_code = ?", $diagnosiscodenew['diagnosis_code']);
        $exsit = $db_diagnosiscode->fetchAll($where);
//                    $where = $db->quoteInto("diagnosis_code = ?",$diagnosiscodenew['diagnosis_code']);
//                    $exsit = $db_ferringprovider->fetchAll($where);
        $exsithas = array();
        $provder_ids = array();

        if (isset($exsit[0])) {
            $db_hasexisting = new Application_Model_DbTable_Providerhasdiagnosiscode();
            $dbexsit = $db_hasexisting->getAdapter();
            for ($i = 0; $i < count($provider_data); $i++) {
                $provder_ids[$i] = $provider_data[$i]['id'];
            }
            $wherehasexisting = $dbexsit->quoteInto("diagnosiscode_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
        }
        //if there exsit the diagnosiscode,get the diagnosiscode_id
        $data = array();
        if (isset($exsitlast[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newdiagnosiscodeAction
     * a function for creating a new diagnosiscode.
     * @author Haowei.
     * @version 05/15/2012
     */
    function newdiagnosiscodeAction() {

        session_start();
        $newtype = $_SESSION['newtype'];
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        if ($newtype == "icd9") {

            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
            //全对应facility：该facility对应着改组provider中的每一个
            $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasDiag.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM providerhasdiagnosiscode has WHERE has.diagnosiscode_id IN 
        (
            SELECT t.id FROM 
                (
                    SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
                    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY diagnosiscode_id
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasDiag
ON hasDiag.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            //获取所有行
            $provider_data = $result->fetchAll();
            //将获取值赋值给providerlist
            $this->view->providerList = $provider_data;
            $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasdiagnosiscode has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.diagnosiscode_id in 
(
SELECT t.id FROM 
(
     SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p ,diagnosiscode diag
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and hs.diagnosiscode_id=diag.id and diag.diagnosis_code <> 'Need New') 
    GROUP BY diagnosiscode_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
GROUP BY p.id LIMIT 1
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $all_data = $result->fetchAll();
            $this->view->allNum = isset($all_data[0]) ? $all_data[0] : array('num' => 0);
            $this->view->newTypeMark = "icd9";
        }else if($newtype == "icd10"){
            
            //sql语句
            //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
            //全对应facility：该facility对应着改组provider中的每一个
            $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasDiag.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM providerhasdiagnosiscode_10 has WHERE has.diagnosiscode_10_id IN 
        (
            SELECT t.id FROM 
                (
                    SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p 
                    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY diagnosiscode_10_id
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasDiag
ON hasDiag.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            //获取所有行
            $provider_data = $result->fetchAll();
            //将获取值赋值给providerlist
            $this->view->providerList = $provider_data;
            $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN providerhasdiagnosiscode_10 has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.diagnosiscode_10_id in 
(
SELECT t.id FROM 
(
     SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p ,diagnosiscode_10 diag
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =? and hs.diagnosiscode_10_id=diag.id and diag.diagnosis_code <> 'Need New') 
    GROUP BY diagnosiscode_10_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
GROUP BY p.id LIMIT 1
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $all_data = $result->fetchAll();
            $this->view->allNum = isset($all_data[0]) ? $all_data[0] : array('num' => 0);
            $this->view->newTypeMark = "icd10";
        }
        if ($this->getRequest()->isPost()) {
            $new_type = $this->getRequest()->getPost('code_type');
            if ($new_type == "icd9") {
                $new_or_import = $this->getRequest()->getPost('import_or_new');
                if ($new_or_import == 'import') {
                    $db_providerhasdiagnosiscode = new Application_Model_DbTable_Providerhasdiagnosiscode();
                    $db = $db_providerhasdiagnosiscode->getAdapter();

                    $providerhasdiagnosiscode = array();

                    session_start();
                    $diagnosis_code = $this->getRequest()->getPost('diagnosis_code');
                    $diagnosis_code = strtok($diagnosis_code, ' ');
                    $diagnosiscodeList = $_SESSION['diagnosiscodeList'];
                    $_SESSION['diagnosiscodeList'] = null;
                    $id = 0;
                    foreach ($diagnosiscodeList as $row) {
                        if ($row['diagnosis_code'] == $diagnosis_code) {
                            $id = $row['id'];
                            break;
                        }
                    }
                    $providerhasdiagnosiscode['diagnosiscode_id'] = $id;

                    $where = $db->quoteInto('diagnosiscode_id = ?', $providerhasdiagnosiscode['diagnosiscode_id']);

                    $olddiag = $db_providerhasdiagnosiscode->fetchAll($where)->toArray();
                    $diagnosiscodeid_get = $providerhasdiagnosiscode['diagnosiscode_id'];
                    $providerid_get = $this->getRequest()->getPost('provider_id');
                    $providerhasdiagnosiscode['provider_id'] = $providerid_get;
                    //$table = 'providerhasdiagnosiscode';
                    if ($providerid_get >= 0 && $diagnosiscodeid_get) {
                        if ($providerhasdiagnosiscode['provider_id'] == 0) {
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $providerhasdiagnosiscode['provider_id'] = $provider_data[$i]['id'];
                                //$db->insert($table,$providerhasdiagnosiscode);
                                $wherehas = $db->quoteInto('diagnosiscode_id = ?', $providerhasdiagnosiscode['diagnosiscode_id']) . $db->quoteInto('and provider_id = ?', $providerhasdiagnosiscode['provider_id']);
                                $existing = $db_providerhasdiagnosiscode->fetchAll($wherehas)->toArray();
                                if (!$existing[0]) {
                                    if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                        // $this->adddatalog($providerhasdiagnosiscode['provider_id'],$olddiag[0]['diagnosis_code'],'providerhasdiagnosiscode.connection',NULL,'connection');
                                    }
                                }
                            }
                        } else {
                            $providerhasdiagnosiscode['provider_id'] = $providerid_get;
                            //$db->insert($table,$providerhasdiagnosiscode);
                            if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                //  $this->adddatalog($providerhasdiagnosiscode['provider_id'],$olddiag[0]['diagnosis_code'],'Providerhasdiagnosiscode.connection',NULL,'connection');
                            }
                        }
                    } else {
                        $this->_redirect('/biller/data/newdiagnosiscode');
                    }
                }
                if ($new_or_import == 'new') {
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
                    $db = $db_diagnosiscode->getAdapter();
                    //get the new diagnosiscode 
                    $diagnosiscodenew = array();
                    $temp_diagnosis_code = $this->getRequest()->getPost('add_diagnosis_code');
                    $diagnosis_code = strtok($temp_diagnosis_code, ' ');
                    $diagnosiscodenew['diagnosis_code'] = $diagnosis_code;
                    $diagnosiscodenew['description'] = $this->getRequest()->getPost('description');
                    if ($diagnosiscodenew['description'] == null) {
                        $space_place = strpos($temp_diagnosis_code, ' ');
                        $description = substr($temp_diagnosis_code, ($space_place + 1));
                        $diagnosiscodenew['description'] = $description;
                    }
                    $diagnosiscode_get = $diagnosiscodenew['diagnosis_code'];
                    $providerid_get = $this->getRequest()->getPost('provider_id');
                    //juge if exist the diagnosiscode in the diagnosiscode table
                    if ($providerid_get >= 0 && $diagnosiscode_get != '') {
                        $where = $db->quoteInto("diagnosis_code = ?", $diagnosiscodenew['diagnosis_code']);
                        $exsit = $db_diagnosiscode->fetchAll($where);
//                    $where = $db->quoteInto("diagnosis_code = ?",$diagnosiscodenew['diagnosis_code']);
//                    $exsit = $db_ferringprovider->fetchAll($where);
                        $exsithas = array();
                        $provder_ids = array();

                        if (isset($exsit[0])) {
                            $db_hasexisting = new Application_Model_DbTable_Providerhasdiagnosiscode();
                            $dbexsit = $db_hasexisting->getAdapter();
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provder_ids[$i] = $provider_data[$i]['id'];
                            }
                            $wherehasexisting = $dbexsit->quoteInto("diagnosiscode_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

                            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                        }
                        //if there exsit the diagnosiscode,get the diagnosiscode_id
                        if (isset($exsitlast[0])) {
                            //$provierhasfacility['facility_id']=$exsit[0]['id'];
                            echo '<span style="color:red;font-size:16px">Sorry ! The  Diagnosis Code is existing , please rewrite !</span>';
                        } else {

                            $providerhasdiagnosiscode['diagnosiscode_id'] = $db_diagnosiscode->insert($diagnosiscodenew);
                            if ($providerhasdiagnosiscode['diagnosiscode_id']) {
                                $this->adddatalog($providerid_get, $diagnosiscodenew['diagnosis_code'], 'options.diagnosis_code', NULL, $diagnosiscodenew['diagnosis_code']);
                                $this->adddatalog($providerid_get, $diagnosiscodenew['diagnosis_code'], 'options.description', NULL, $diagnosiscodenew['description']);
                            }
//                    }
                            //complete the new providerhasdiagnosiscode
                            $providerhasdiagnosiscode['provider_id'] = $this->getRequest()->getPost('provider_id');
                            $db_providerhasdiagnosiscode = new Application_Model_DbTable_Providerhasdiagnosiscode();
                            $db = $db_providerhasdiagnosiscode->getAdapter();
                            //insert the providerhasdiagnosiscode
                            //if insert the diagnosiscode to all provider
                            if ($providerhasdiagnosiscode['provider_id'] == 0) {
                                for ($i = 0; $i < count($provider_data); $i++) {
                                    $providerhasdiagnosiscode['provider_id'] = $provider_data[$i]['id'];
                                    //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                                    $where = $db->quoteInto("provider_id = ?", $providerhasdiagnosiscode['provider_id']) . $db->quoteInto("and diagnosiscode_id = ?", $providerhasdiagnosiscode['diagnosiscode_id']);
                                    $ext = $db_providerhasdiagnosiscode->fetchAll($where);
                                    if (isset($ext[0])) {
                                        
                                    } else {
                                        if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                            // $this->adddatalog($providerhasdiagnosiscode['provider_id'],$diagnosiscodenew['diagnosis_code'] ,'Providerhasdiagnosiscode.connection',NULL,'connection');
                                        }
                                    }
                                }
                            } else {
                                $providerhasdiagnosiscode['provider_id'] = $this->getRequest()->getPost('provider_id');
                                //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                                $where = $db->quoteInto("provider_id = ?", $providerhasdiagnosiscode['provider_id']) . $db->quoteInto("and diagnosiscode_id = ?", $providerhasdiagnosiscode['diagnosiscode_id']);
                                $ext = $db_providerhasdiagnosiscode->fetchAll($where);
                                if (isset($ext[0])) {
                                    
                                } else {
                                    if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                        //$this->adddatalog($providerhasdiagnosiscode['provider_id'],$diagnosiscodenew['diagnosis_code'] ,'Providerhasdiagnosiscode.connection',NULL,'connection');
                                    }
                                }
                            }
                        }
                        $this->_redirect('/biller/data/newdiagnosiscode');
                    } else {
                        $this->_redirect('/biller/data/newdiagnosiscode');
                    }
                }
            } else if ($new_type == "icd10") {
                $new_or_import = $this->getRequest()->getPost('import_or_new');
                if ($new_or_import == 'import') {
                    $db_providerhasdiagnosiscode_10 = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                    $db = $db_providerhasdiagnosiscode_10->getAdapter();

                    $providerhasdiagnosiscode = array();

                    session_start();
                    $diagnosis_code = $this->getRequest()->getPost('diagnosis_code');
                    $diagnosis_code = strtok($diagnosis_code, ' ');
                    $diagnosiscodeList = $_SESSION['diagnosiscodeList'];
                    $_SESSION['diagnosiscodeList'] = null;
                    $id = 0;
                    foreach ($diagnosiscodeList as $row) {
                        if ($row['diagnosis_code'] == $diagnosis_code) {
                            $id = $row['id'];
                            break;
                        }
                    }
                    $providerhasdiagnosiscode['diagnosiscode_10_id'] = $id;

                    $where = $db->quoteInto('diagnosiscode_10_id = ?', $providerhasdiagnosiscode['diagnosiscode_10_id']);

                    $olddiag = $db_providerhasdiagnosiscode_10->fetchAll($where)->toArray();
                    $diagnosiscodeid_get = $providerhasdiagnosiscode['diagnosiscode_10_id'];
                    $providerid_get = $this->getRequest()->getPost('provider_id');
                    $providerhasdiagnosiscode['provider_id'] = $providerid_get;
                    //$table = 'providerhasdiagnosiscode';
                    if ($providerid_get >= 0 && $diagnosiscodeid_get) {
                        if ($providerhasdiagnosiscode['provider_id'] == 0) {
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $providerhasdiagnosiscode['provider_id'] = $provider_data[$i]['id'];
                                //$db->insert($table,$providerhasdiagnosiscode);
                                $wherehas = $db->quoteInto('diagnosiscode_10_id = ?', $providerhasdiagnosiscode['diagnosiscode_10_id']) . $db->quoteInto('and provider_id = ?', $providerhasdiagnosiscode['provider_id']);
                                $existing = $db_providerhasdiagnosiscode_10->fetchAll($wherehas)->toArray();
                                if (!$existing[0]) {
                                    if ($db_providerhasdiagnosiscode_10->insert($providerhasdiagnosiscode)) {
                                        // $this->adddatalog($providerhasdiagnosiscode['provider_id'],$olddiag[0]['diagnosis_code'],'providerhasdiagnosiscode.connection',NULL,'connection');
                                    }
                                }
                            }
                        } else {
                            $providerhasdiagnosiscode['provider_id'] = $providerid_get;
                            //$db->insert($table,$providerhasdiagnosiscode);
                            if ($db_providerhasdiagnosiscode_10->insert($providerhasdiagnosiscode)) {
                                //  $this->adddatalog($providerhasdiagnosiscode['provider_id'],$olddiag[0]['diagnosis_code'],'Providerhasdiagnosiscode.connection',NULL,'connection');
                            }
                        }
                    
                            $this->_redirect('/biller/data/newdiagnosiscode');

                            } else {
                        $this->_redirect('/biller/data/newdiagnosiscode');
                    }
                }
                if ($new_or_import == 'new') {
                    $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode10();
                    $db = $db_diagnosiscode->getAdapter();
                    //get the new diagnosiscode 
                    $diagnosiscodenew = array();
                    $temp_diagnosis_code = $this->getRequest()->getPost('add_diagnosis_code');
                    $diagnosis_code = strtok($temp_diagnosis_code, ' ');
                    $diagnosiscodenew['diagnosis_code'] = $diagnosis_code;
                    $diagnosiscodenew['description'] = $this->getRequest()->getPost('description');
                    if ($diagnosiscodenew['description'] == null) {
                        $space_place = strpos($temp_diagnosis_code, ' ');
                        $description = substr($temp_diagnosis_code, ($space_place + 1));
                        $diagnosiscodenew['description'] = $description;
                    }
                    $diagnosiscode_get = $diagnosiscodenew['diagnosis_code'];
                    $providerid_get = $this->getRequest()->getPost('provider_id');
                    //juge if exist the diagnosiscode in the diagnosiscode table
                    if ($providerid_get >= 0 && $diagnosiscode_get != '') {
                        $where = $db->quoteInto("diagnosis_code = ?", $diagnosiscodenew['diagnosis_code']);
                        $exsit = $db_diagnosiscode->fetchAll($where);
//                    $where = $db->quoteInto("diagnosis_code = ?",$diagnosiscodenew['diagnosis_code']);
//                    $exsit = $db_ferringprovider->fetchAll($where);
                        $exsithas = array();
                        $provder_ids = array();

                        if (isset($exsit[0])) {
                            $db_hasexisting = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                            $dbexsit = $db_hasexisting->getAdapter();
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provder_ids[$i] = $provider_data[$i]['id'];
                            }
                            $wherehasexisting = $dbexsit->quoteInto("diagnosiscode_10_id = ?", $exsit[0]['id']) . $dbexsit->quoteInto("and provider_id in (?)", $provder_ids);

                            $exsitlast = $db_hasexisting->fetchAll($wherehasexisting)->toArray();
                        }
                        //if there exsit the diagnosiscode,get the diagnosiscode_id
                        if (isset($exsitlast[0])) {
                            //$provierhasfacility['facility_id']=$exsit[0]['id'];
                            echo '<span style="color:red;font-size:16px">Sorry ! The  Diagnosis Code is existing , please rewrite !</span>';
                        } else {
                            //if there exsit the diagnosiscode,get the diagnosiscode_id
//                    if(isset($exsit[0]))
//                    {
//                        $providerhasdiagnosiscode['diagnosiscode_id']=$exsit[0]['id'];
//                    }
//                    //if there isn't the diagnosiscode, insert the diagnosiscode and get it's id
//                    else $diagnosiscodenew['diagnosis_code'] 
//                    {
                            $providerhasdiagnosiscode['diagnosiscode_10_id'] = $db_diagnosiscode->insert($diagnosiscodenew);
                            if ($providerhasdiagnosiscode['diagnosiscode_10_id']) {
                                $this->adddatalog($providerid_get, $diagnosiscodenew['diagnosis_code'], 'options.diagnosis_code', NULL, $diagnosiscodenew['diagnosis_code']);
                                $this->adddatalog($providerid_get, $diagnosiscodenew['diagnosis_code'], 'options.description', NULL, $diagnosiscodenew['description']);
                            }
//                    }
                            //complete the new providerhasdiagnosiscode
                            $providerhasdiagnosiscode['provider_id'] = $this->getRequest()->getPost('provider_id');
                            $db_providerhasdiagnosiscode = new Application_Model_DbTable_Providerhasdiagnosiscode10();
                            $db = $db_providerhasdiagnosiscode->getAdapter();
                            //insert the providerhasdiagnosiscode
                            //if insert the diagnosiscode to all provider
                            if ($providerhasdiagnosiscode['provider_id'] == 0) {
                                for ($i = 0; $i < count($provider_data); $i++) {
                                    $providerhasdiagnosiscode['provider_id'] = $provider_data[$i]['id'];
                                    //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                                    $where = $db->quoteInto("provider_id = ?", $providerhasdiagnosiscode['provider_id']) . $db->quoteInto("and diagnosiscode_10_id = ?", $providerhasdiagnosiscode['diagnosiscode_id']);
                                    $ext = $db_providerhasdiagnosiscode->fetchAll($where);
                                    if (isset($ext[0])) {
                                        
                                    } else {
                                        if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                            // $this->adddatalog($providerhasdiagnosiscode['provider_id'],$diagnosiscodenew['diagnosis_code'] ,'Providerhasdiagnosiscode.connection',NULL,'connection');
                                        }
                                    }
                                }
                            } else {
                                $providerhasdiagnosiscode['provider_id'] = $this->getRequest()->getPost('provider_id');
                                //judge if the providerhasdiagnosiscode in the providerhasdiagnosiscode table
                                $where = $db->quoteInto("provider_id = ?", $providerhasdiagnosiscode['provider_id']) . $db->quoteInto("and diagnosiscode_10_id = ?", $providerhasdiagnosiscode['diagnosiscode_id']);
                                $ext = $db_providerhasdiagnosiscode->fetchAll($where);
                                if (isset($ext[0])) {
                                    
                                } else {
                                    if ($db_providerhasdiagnosiscode->insert($providerhasdiagnosiscode)) {
                                        //$this->adddatalog($providerhasdiagnosiscode['provider_id'],$diagnosiscodenew['diagnosis_code'] ,'Providerhasdiagnosiscode.connection',NULL,'connection');
                                    }
                                }
                            }
                            $this->_redirect('/biller/data/newdiagnosiscode');
                        }
                    } else {
                        $this->_redirect('/biller/data/newdiagnosiscode');
                    }
                }                
            }
        }
    }

    function newdiagnosiscodeAction1() {
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;

        if ($this->getRequest()->isPost()) {


            $diagnosiscode['diagnosis_code'] = $this->getRequest()->getPost('diagnosis_code');
            $diagnosiscode['description'] = $this->getRequest()->getPost('description');

            $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
            $db = $db_diagnosiscode->getAdapter();
            $provider_id = $this->getRequest()->getPost('provider_id');
            if ($provider_id != 0) {
                $diagnosiscode['provider_id'] = $provider_id;
                $db_diagnosiscode->insert($diagnosiscode);
            } else {
                for ($i = 0; $i < count($provider); $i++) {
                    $diagnosiscode['provider_id'] = $provider[$i]['id'];
                    $db_diagnosiscode->insert($diagnosiscode);
                }
            }


            $this->_redirect('/biller/data/diagnosiscode');
        }
    }

    /**
     * diagnosiscodeinfoAction
     * a function returning the diagnosiscode data for displaying on the page.
     * @author Haowei.
     * @return the diagnosiscode data for displaying on the page
     * @version 05/15/2012
     */
    function diagnosiscodeinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];

        /* $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
          $db = $db_diagnosiscode->getAdapter();
          $where = $db->quoteInto('id = ?', $id);
          $diagnosiscode = $db_diagnosiscode->fetchRow($where); */
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        $sql = <<<SQL
select id,diagnosis_code,description
from diagnosiscode
where diagnosiscode.id = ?
SQL;
        $paras = array($id);
        //查询并获取结果
        $result = $db->query($sql, $paras);
        //获取查询数据
        $diagnosiscode_info = $result->fetchAll();

        $data = array();
        $data['diagnosiscode_info'] = $diagnosiscode_info;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * providerhasAction
     * a function used to reture json data that can be used to do autocomplete in front UI page.
     * @author james.
     * @return the diagnosiscode data for autocomplete in front UI page
     * @version 05/18/2015
     */
    function providerhasAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        $sql = <<<SQL
SELECT diagnosiscode_id id,diagnosis_code,description
from diagnosiscode diag
LEFT JOIN providerhasdiagnosiscode phas 
on phas.diagnosiscode_id = diag.id 
WHERE diagnosiscode_id IN
    (
        SELECT t.id FROM 
            (
                SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
                WHERE (hs.provider_id = p.id AND p.billingcompany_id =?)    
                GROUP BY diagnosiscode_id
            )t 
        WHERE 
            t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
    )
AND phas.provider_id = ?
order by        diagnosis_code
SQL
        ;
        $paras = array($billingcompany_id, $billingcompany_id, $provider_id);
        //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
        //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）
        if ($provider_id == 0) {
            $sql = <<<SQL
SELECT diagnosiscode_id id,diagnosis_code,description
from diagnosiscode diag LEFT JOIN providerhasdiagnosiscode pr on pr.diagnosiscode_id = diag.id 
WHERE diagnosiscode_id IN
(
SELECT t.id FROM 
(
    SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    GROUP BY diagnosiscode_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id =?)
)
GROUP BY diagnosiscode_id
order by        diagnosis_code
SQL
            ;
            $paras = array($billingcompany_id, $billingcompany_id);
        }
        //查询并获取结果
        $result = $db->query($sql, $paras);
        //获取查询数据
        $diagnosiscode_data = $result->fetchAll();
        $diagnosiscodeList = array();
        session_start();
        $_SESSION['diagnosiscodeList'] = $diagnosiscode_data;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['diagnosis_code'] . " " . $row['description'];
            $idx++;
        }
        $data = array();
        $data['diagnosiscodeList'] = $diagnosiscodeList;
        $json = Zend_Json::encode($data);
        echo $json;
    }
    
    
     /**
     * providerhasicd10Action
     * a function used to reture json data that can be used to do autocomplete in front UI page.
     * @author james.
     * @return the icd10_diagnosiscode data for autocomplete in front UI page
     * @version 08/31/2015
     */
    function providerhasicd10Action() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        $sql = <<<SQL
SELECT diagnosiscode_10_id id,diagnosis_code,description
from diagnosiscode_10 diag
LEFT JOIN providerhasdiagnosiscode_10 phas 
on phas.diagnosiscode_10_id = diag.id 
WHERE diagnosiscode_10_id IN
    (
        SELECT t.id FROM 
            (
                SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p 
                WHERE (hs.provider_id = p.id AND p.billingcompany_id =?)    
                GROUP BY diagnosiscode_10_id
            )t 
        WHERE 
            t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
    )
AND phas.provider_id = ?
order by        diagnosis_code
SQL
        ;
        $paras = array($billingcompany_id, $billingcompany_id, $provider_id);
        //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
        //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）
        if ($provider_id == 0) {
            $sql = <<<SQL
SELECT diagnosiscode_10_id id,diagnosis_code,description
from diagnosiscode_10 diag LEFT JOIN providerhasdiagnosiscode_10 pr on pr.diagnosiscode_10_id = diag.id 
WHERE diagnosiscode_10_id IN
(
SELECT t.id FROM 
(
    SELECT diagnosiscode_10_id id ,count(*) counts FROM providerhasdiagnosiscode_10 hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    GROUP BY diagnosiscode_10_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id =?)
)
GROUP BY diagnosiscode_10_id
order by        diagnosis_code
SQL
            ;
            $paras = array($billingcompany_id, $billingcompany_id);
        }
        //查询并获取结果
        $result = $db->query($sql, $paras);
        //获取查询数据
        $diagnosiscode_data = $result->fetchAll();
        $diagnosiscodeList = array();
        session_start();
        $_SESSION['icd10_diagnosiscodeList'] = $diagnosiscode_data;
        $idx = 0;
        foreach ($diagnosiscode_data as $row) {
            $diagnosiscodeList[$idx] = $row['diagnosis_code'] . " " . $row['description'];
            $idx++;
        }
        $data = array();
        $data['diagnosiscodeList'] = $diagnosiscodeList;
        
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function diagnosiscodeinfoAction1() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
        $db = $db_diagnosiscode->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $diagnosiscode = $db_diagnosiscode->fetchRow($where);

        $data = array();
        $data = array('id' => $diagnosiscode['id'],
            'description' => $diagnosiscode['description']);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /*     * diagnosiscodelistAction
     * to get the diagnosiscodelist for the provider
     * 2012/07/10
     * by PanDazhao
     */

    public function diagnosiscodelistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        $sql = <<<SQL
SELECT diagnosiscode_id id,diagnosis_code 
from diagnosiscode diag
LEFT JOIN providerhasdiagnosiscode phas 
on phas.diagnosiscode_id = diag.id 
WHERE diagnosiscode_id IN
    (
        SELECT t.id FROM 
            (
                SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
                WHERE (hs.provider_id = p.id AND p.billingcompany_id =?)    
                GROUP BY diagnosiscode_id
            )t 
        WHERE 
            t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
    )
AND phas.provider_id = ?
order by        diagnosis_code
SQL
        ;
        $paras = array($billingcompany_id, $billingcompany_id, $provider_id);
        //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
        //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）
        if ($provider_id == 0) {
            $sql = <<<SQL
SELECT diagnosiscode_id id,diagnosis_code 
from diagnosiscode diag LEFT JOIN providerhasdiagnosiscode pr on pr.diagnosiscode_id = diag.id 
WHERE diagnosiscode_id IN
(
SELECT t.id FROM 
(
    SELECT diagnosiscode_id id ,count(*) counts FROM providerhasdiagnosiscode hs,provider p 
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    GROUP BY diagnosiscode_id
) t 
WHERE 
t.counts >= (SELECT count(*) c FROM provider where billingcompany_id =?)
)
GROUP BY diagnosiscode_id
order by        diagnosis_code
SQL
            ;
            $paras = array($billingcompany_id, $billingcompany_id);
        }
        //查询并获取结果
        $result = $db->query($sql, $paras);
        //获取查询数据
        $diagnosiscode_data = $result->fetchAll();
        $data = array();
        $this->deletenew($diagnosiscode_data, 'diagnosis_code', 'Need New');
        $data['diagnosiscodeList'] = $diagnosiscode_data;
        //使用json传输
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function diagnosiscodelistAction1() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['provider_id'];
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;

        if ($id != 0) {
            $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
            $db = $db_diagnosiscode->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id);
            $diagnosiscodeList = $db_diagnosiscode->fetchAll($where)->toArray();
        } else {
            $db_diagnosiscode = new Application_Model_DbTable_Diagnosiscode();
            $db = $db_diagnosiscode->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $provider[0]['id']);
            $diagnosiscodeList = $db_diagnosiscode->fetchAll($where)->toArray();
        }

        $data['diagnosiscodeList'] = $diagnosiscodeList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * cptcodeAction
     * a function for processing the cptcode data.
     * @author Haowei.
     * @version 05/15/2012
     */
    function cptcodeAction() {
        $db_cptcode = new Application_Model_DbTable_Cptcode();
        $db = $db_cptcode->getAdapter();
        $cptcode_data = $db_cptcode->fetchAll(null, 'CPT_code ASC');
        $this->view->cptcodeList = $cptcode_data;
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $id = $this->getRequest()->getPost('id');
                $cptcode['description'] = $this->getRequest()->getPost('description');

                $db_cptcode = new Application_Model_DbTable_Cptcode();
                $db = $db_cptcode->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $db_cptcode->update($cptcode, $where);
                $this->_redirect('/biller/data/cptcode');
            }
            if ($submitType == "Delete") {
                $id = $this->getRequest()->getPost('id');
                $db_cptcode = new Application_Model_DbTable_Cptcode();
                $db = $db_cptcode->getAdapter();
                $where = $db->quoteInto('id = ?', $id);
                $db_cptcode->delete($where);
                $this->_redirect('/biller/data/cptcode');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newcptcode');
        }
    }

    /**
     * newcptcodeAction
     * a function for creating a new cptcode.
     * @author Haowei.
     * @version 05/15/2012
     */
    function newcptcodeAction() {
        if ($this->getRequest()->isPost()) {
            $cptcode['CPT_code'] = $this->getRequest()->getPost('CPT_code');
            $cptcode['description'] = $this->getRequest()->getPost('description');

            $db_cptcode = new Application_Model_DbTable_Cptcode();
            $db = $db_cptcode->getAdapter();
            $db_cptcode->insert($cptcode);

            $this->_redirect('/biller/data/cptcode');
        }
    }

    /**
     * cptcodeinfoAction
     * a function returning the cptcode data for displaying on the page.
     * @author Haowei.
     * @return the cptcode data for displaying on the page
     * @version 05/15/2012
     */
    function cptcodeinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $id = $_POST['id'];
        $db_cptcode = new Application_Model_DbTable_Cptcode();
        $db = $db_cptcode->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $cptcode = $db_cptcode->fetchRow($where);

        $data = array();
        $data = array('id' => $cptcode['id'],
            'description' => $cptcode['description']);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function anesthesiacodeAction() {

        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasAnes.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM anesthesiacode ane WHERE ane.id IN 
        (
            SELECT t.aid FROM 
                (
                    SELECT anes.id  aid,count(*) counts FROM anesthesiacode anes,provider p 
                    WHERE (anes.anesthesia_code <> 'Need New' AND anes.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY aid
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasAnes
ON hasAnes.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;

        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN anesthesiacode has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.anesthesia_code  in 
(
SELECT anesthesia_code FROM 
(
  
    SELECT anesthesia_code, description, base_unit 
     ,count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?   and hs.anesthesia_code <> "Need New") 
    group by anesthesia_code, description, base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
        
AND has.description  in 
(
SELECT description FROM 
(
  
    SELECT anesthesia_code, description, base_unit,
      count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code, description, base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
AND has.base_unit  in 
(
SELECT base_unit FROM 
(
  
    SELECT anesthesia_code, description, base_unit, 
    count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code, description, base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
        
t.total >= (SELECT count(*) c FROM provider where billingcompany_id= ?)
)
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $this->view->allNumAne = isset($all_data[0]) ? $all_data[0] : array('num' => 0);
        //将获取值赋值给providerlist
        // $this->view->providerList = $provider_data;   





        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
//        $this->view->providerList = $provider;

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $modifier = $db_modifier->fetchAll();
        $this->view->modifierList = $modifier;
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            $provider_id = $this->getRequest()->getPost('provider_id');

            if ($submitType == "Update") {
                //$provider_id1 = $this->getRequest()->getPost('provider_id1');
                //$anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');

                $anesthesia_code_id = $this->getRequest()->getPost('anesthesia_code_id');
                $anesthesia['base_unit'] = $this->getRequest()->getPost('base_unit');
                $anesthesia['description'] = $this->getRequest()->getPost('description');
                $anesthesia['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                $anesthesia['status'] = $this->getRequest()->getPost('status');
                
                if ($anesthesia['default_modifier_1'] == '') {
                    $anesthesia['default_modifier_1'] = NULL;
                }
                $anesthesia['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                if ($anesthesia['default_modifier_2'] == '') {
                    $anesthesia['default_modifier_2'] = NULL;
                }
                $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                $db = $db_surgeryanesthesiacrosswalk->getAdapter();

                if ($provider_id != 0) {
                    $provider_id = $this->getRequest()->getPost('provider_id');

                    $wherehas = $db->quoteInto('provider_id = ?', $provider_id) . $db->quoteInto('AND id = ?', $anesthesia_code_id);
                    $i = 0;
                    $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherehas)->toArray();
//                   //$temp = $db_surgeryanesthesiacrosswalk->fetchRow($where);
                    if ($db_surgeryanesthesiacrosswalk->update($anesthesia, $wherehas)) {
                        // $this->adddatalog($oldanes[0]['provider_id'],$oldanes[0]['anesthesia_code'],'anesthesiacode.anesthesiacode_code',$oldanes[0]['anesthesia_code'],NULL);
                        $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.description', $oldanes[0]['description'], $anesthesia['description']);
                        $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.base_unit', $oldanes[0]['base_unit'], $anesthesia['base_unit']);
                        $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_1', $oldanes[0]['default_modifier_1'], $anesthesia['default_modifier_1']);
                        $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_2', $oldanes[0]['default_modifier_2'], $anesthesia['default_modifier_2']);
                    }
                } else {
                    $del_provider_id = $this->getRequest()->getPost('del_provider_id');
                    if ($del_provider_id == 0) {
                        $db_AnsCode = new Application_Model_DbTable_Anesthesiacode();
                        $db = $db_AnsCode->getAdapter();
                        $where = $db->quoteInto("id = ?", $anesthesia_code_id);
                        $Anes = $db_AnsCode->fetchAll($where)->toArray();
                        $Anes_code = $Anes[0]['anesthesia_code'];
                        $description = $Anes[0]['description'];
                        $base_unit = $Anes[0]['base_unit'];
                        $wheretwo = $db->quoteInto("anesthesia_code = ?", $Anes_code) . $db->quoteInto("and description = ?", $description) . $db->quoteInto(" and base_unit = ?", $base_unit);
                        $Anestwo = $db_AnsCode->fetchAll($wheretwo)->toArray();
                        $Anes_id = array();
                        for ($j = 0; $j < count($Anestwo); $j++) {
                            $Anes_id[$j] = $Anestwo[$j]['id'];
                        }

                        for ($i = 0; $i < count($provider); $i++) {
                            if ($Anes_id) {
                                $where = $db->quoteInto('id in (?)', $Anes_id) . $db->quoteInto('AND provider_id=?', $provider[$i]['id']);
                                $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();
                                if ($db_surgeryanesthesiacrosswalk->update($anesthesia, $where)) {
                                    $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.description', $oldanes[0]['description'], $anesthesia['description']);
                                    $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.base_unit', $oldanes[0]['base_unit'], $anesthesia['base_unit']);
                                    $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_1', $oldanes[0]['default_modifier_1'], $anesthesia['default_modifier_1']);
                                    $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_2', $oldanes[0]['default_modifier_2'], $anesthesia['default_modifier_2']);
                                }
                            }
                        }
                    } else {
                        $db_AnsCode = new Application_Model_DbTable_Anesthesiacode();
                        $db = $db_AnsCode->getAdapter();
                        $where = $db->quoteInto("id = ?", $anesthesia_code_id);
                        $Anes = $db_AnsCode->fetchAll($where)->toArray();
                        $Anes_code = $Anes[0]['anesthesia_code'];
                        $description = $Anes[0]['description'];
                        $base_unit = $Anes[0]['base_unit'];
                        $wheretwo = $db->quoteInto("anesthesia_code = ?", $Anes_code) . $db->quoteInto("and description = ?", $description) . $db->quoteInto(" and base_unit = ?", $base_unit) . $db->quoteInto("and provider_id = ?", $del_provider_id);
                        $Anestwo = $db_AnsCode->fetchAll($wheretwo)->toArray();
                        $Anes_id = array();
                        for ($j = 0; $j < count($Anestwo); $j++) {
                            $Anes_id[$j] = $Anestwo[$j]['id'];
                        }
                        if ($Anes_id) {
                            $where = $db->quoteInto('id in (?)', $Anes_id) . $db->quoteInto('AND provider_id=?', $del_provider_id);
                            $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();
                            if ($db_surgeryanesthesiacrosswalk->update($anesthesia, $where)) {
                                $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.description', $oldanes[0]['description'], $anesthesia['description']);
                                $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.base_unit', $oldanes[0]['base_unit'], $anesthesia['base_unit']);
                                $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_1', $oldanes[0]['default_modifier_1'], $anesthesia['default_modifier_1']);
                                $this->adddatalog($oldanes[0]['provider_id'], $oldanes[0]['anesthesia_code'], 'anesthesiacode.default_modifier_2', $oldanes[0]['default_modifier_2'], $anesthesia['default_modifier_2']);
                            }
                        }
                    }
                }

                /*                 * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
                session_start();
                $_SESSION['management_data']['provider_id'] = $provider_id;

                $this->_redirect('/biller/data/anesthesiacode');
            }


            if ($submitType == "New")
                $this->_redirect('/biller/data/newanesthesiacode');
        }
    }

    /**
     * anethesiacodeAction
     * a function for processing the anethesiacode data.
     * @author Caijun.
     * @version 05/15/2012
     */
    function surgerycodeAction() {
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasSurg.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM cptcode cpt WHERE cpt.id IN 
        (
            SELECT t.aid FROM 
                (
                    SELECT cpts.id  aid,count(*) counts FROM cptcode cpts,provider p 
                    WHERE (cpts.CPT_code <> 'Need New' and cpts.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY aid
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasSurg
ON hasSurg.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;


        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
//        $this->view->providerList = $provider;

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $modifier = $db_modifier->fetchAll();
        $this->view->modifierList = $modifier;

        $sql = <<<SQL
SELECT count(CPT_code) as num  FROM (
SELECT CPT_code, count( cptcode.provider_id ) AS total
FROM cptcode, provider,anesthesiacode
WHERE cptcode.provider_id = provider.id and anesthesiacode.id=cptcode.anesthesiacode_id 
AND billingcompany_id =? and cptcode.CPT_code <> "Need New"
GROUP BY CPT_code,cptcode.description,charge_amount, payment_expected,anesthesia_code,cptcode.default_modifier_1,cptcode.default_modifier_2
)t
WHERE t.total >= ( 
SELECT count( * ) 
FROM provider
WHERE billingcompany_id =? )
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
        SELECT CPT_code, count( CPT_code ) AS num, provider_id
FROM (

SELECT CPT_code, count( cptcode.provider_id ) AS total, provider_id
FROM cptcode, provider
WHERE cptcode.provider_id = provider.id and CPT_code <>"Need New"
AND billingcompany_id =?
GROUP BY CPT_code,description,charge_amount, payment_expected,anesthesiacode_id,default_modifier_1,default_modifier_2
)t
WHERE t.total >= ( 
SELECT count( * ) 
FROM provider
WHERE billingcompany_id =? ) 
GROUP BY provider_id
SQL
        ;
        $result1 = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        $all_data1 = $result1->fetchAll();
        $num1 = 0;
        $num2 = 0;
        if (isset($all_data[0]))
            $num1 = $all_data[0]['num'];
        if (isset($all_data1[0]))
            $num2 = $all_data1[0]['num'];
        $number = $num1 + $num2;
        $this->view->allNumSur = array('num' => $number);

        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            $provider_id = $this->getRequest()->getPost('provider_id');

            $provider_ids = array();
            $billingcompany_id = $this->billingcompany_id();
            $db_provider = new Application_Model_DbTable_Provider();
            $dbp = $db_provider->getAdapter();
            $where = $dbp->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
            $provider_data1 = $db_provider->fetchAll($where, "provider_name ASC");
            for ($i = 0; $i < count($provider_data1); $i++) {

                $provider_ids[$i] = $provider_data1[$i]['id'];
            }
            $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
            $db_anesthesia = $db_surgeryanesthesiacrosswalk->getAdapter();
//            $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
//            $db_anesthesia = $db_surgeryanesthesiacrosswalk->getAdapter();
            if ($submitType == "Update") {
                $CPT_code = $this->getRequest()->getPost('cpt_code');
                // $anesthesia_code_id = $this->getRequest()->getPost('anesthesia_code_id');
                //$anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');
                $anesthesia['description'] = $this->getRequest()->getPost('description');
                $anesthesia['base_unit'] = $this->getRequest()->getPost('base_unit');
                $anesthesia['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                if ($anesthesia['default_modifier_1'] == '') {
                    $anesthesia['default_modifier_1'] = NULL;
                }
                $anesthesia['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                if ($anesthesia['default_modifier_2'] == '') {
                    $anesthesia['default_modifier_1'] = NULL;
                }
                $anesthesiacode_id = '';
                $anesthesiacode = $this->getRequest()->getPost('anesthesia_code_id');
                


                $CPT['description'] = $this->getRequest()->getPost('description');
                $CPT['charge_amount'] = $this->getRequest()->getPost('charge');
                $CPT['payment_expected'] = $this->getRequest()->getPost('expected_amount');
                $CPT['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                $CPT['status'] = $this->getRequest()->getPost('status');//这个status 应该是针对 cpt 而言的
                if (!$CPT['default_modifier_1']) {
                    $CPT['default_modifier_1'] = NULL;
                }
                $CPT['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                if (!$CPT['default_modifier_2']) {
                    $CPT['default_modifier_2'] = NULL;
                }
                $db_cptcode = new Application_Model_DbTable_Cptcode();
                $db = $db_cptcode->getAdapter();
//                $where = $db->quoteInto('CPT_code = ?', $CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);

                if ($provider_id != 0) {

                    $temp = array();
                    if ($anesthesiacode) {
                        $where = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id = ?', $provider_id);
                        $temp = $db_surgeryanesthesiacrosswalk->fetchRow($where)->toArray();
                    }
                    if ($temp) {
                        $anesthesiacode_id = $temp['id'];
                    } else {

                        $where1 = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id in(?)', $provider_ids);
                        $temp1 = $db_surgeryanesthesiacrosswalk->fetchRow($where1);
                        if ($temp1[0]) {
                            $anes['anesthesia_code'] = $temp1[0]['anesthesia_code'];
                            $anes['description'] = $temp1[0]['description'];
                            $anes['base_unit'] = $temp1[0]['base_unit'];
                            $anes['default_modifier_1'] = $temp1[0]['default_modifier_1'];
                            $anes['default_modifier_2'] = $temp1[0]['default_modifier_2'];
                            $anes['provider_id'] = $provider_id;

                            $anesthesiacode_id = $db_surgeryanesthesiacrosswalk->insert($anes);
                            if ($anesthesiacode_id) {
                                $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anes['anesthesia_code']);
                                $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.description', NULL, $anes['description']);
                                $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anes['base_unit']);
                                $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anes['default_modifier_1']);
                                $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anes['default_modifier_2']);

                            }
                        }
                    }
                    if ($anesthesiacode_id != '') {
                        $CPT['anesthesiacode_id'] = $anesthesiacode_id;
                    } else {
                        $CPT['anesthesiacode_id'] = NULL;
                    }

                    $where = $db->quoteInto('id  = ?', $CPT_code) . $db->quoteInto('AND provider_id=?', $provider_id);
                    $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                    $wherold = $db_anesthesia->quoteInto('id= ?', $oldcptcode[0]['anesthesiacode_id']);
                    $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                    if ($db_cptcode->update($CPT, $where)) {
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], $CPT['description']);
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], $CPT['charge_amount']);
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], $CPT['payment_expected']);
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], $CPT['default_modifier_1']);
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], $CPT['default_modifier_2']);
                        $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], $anesthesiacode);
                    }
                } else {
                    $del_provider_id = $this->getRequest()->getPost('del_provider_id');
                    if ($del_provider_id == 0) {

                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_cpt->getAdapter();
                        $where = $db->quoteInto('id  = ?', $CPT_code);
                        $cpt = $db_cpt->fetchAll($where)->toArray();
                        $CPTCode = $cpt[0]['CPT_code'];
                        $CPTdescription = $cpt[0]['description'];
                        $charge_amount = $cpt[0]['charge_amount'];
                        $payment_expected = $cpt[0]['payment_expected'];

                        for ($i = 0; $i < count($provider); $i++) {

                            $temp = array();
                            if ($anesthesiacode) {
                                $where = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id = ?', $provider[$i]['id']);
                                $temp = $db_surgeryanesthesiacrosswalk->fetchRow($where);
                            }
                            if ($temp) {
                                $anesthesiacode_id = $temp['id'];
                            } else {
                                $temp1 = array();
                                if ($anesthesiacode && $provider_ids) {
                                    $where1 = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id in(?)', $provider_ids);
                                    $temp1 = $db_surgeryanesthesiacrosswalk->fetchRow($where1);
                                }
                                if ($temp1[0]) {
                                    $anes['anesthesia_code'] = $temp1[0]['anesthesia_code'];
                                    $anes['description'] = $temp1[0]['description'];
                                    $anes['base_unit'] = $temp1[0]['base_unit'];
                                    $anes['default_modifier_1'] = $temp1[0]['default_modifier_1'];
                                    $anes['default_modifier_2'] = $temp1[0]['default_modifier_2'];
                                    $anes['provider_id'] = $provider_id;

                                    $anesthesiacode_id = $db_surgeryanesthesiacrosswalk->insert($anes);
                                    if ($anesthesiacode_id) {
                                        $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anes['anesthesia_code']);
                                        $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.description', NULL, $anes['description']);
                                        $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anes['base_unit']);
                                        $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anes['default_modifier_1']);
                                        $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anes['default_modifier_2']);
                                    }
                                }
                            }
                            if ($anesthesiacode_id) {
                                $CPT['anesthesiacode_id'] = $anesthesiacode_id;
                            } else {
                                $CPT['anesthesiacode_id'] = NULL;
                            }

                            $where = $db->quoteInto('CPT_code =?', $CPTCode) . $db->quoteInto(' AND provider_id=?', $provider[$i]['id']) . $db->quoteInto(' AND description=?', $CPTdescription) . $db->quoteInto(' AND charge_amount=?', $charge_amount) . $db->quoteInto(' AND payment_expected=?', $payment_expected);
                            $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                            $wherold = $db_anesthesia->quoteInto('id= ?', $oldcptcode[0]['anesthesiacode_id']);
                            $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                            if ($db_cptcode->update($CPT, $where)) {
                                $provider_id = $provider[$i]['id'];
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], $CPT['description']);
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], $CPT['charge_amount']);
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], $CPT['payment_expected']);
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], $CPT['default_modifier_1']);
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], $CPT['default_modifier_2']);
                                $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], $anesthesiacode);
                            }
                        }
                    } else {
                        $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                        $db_anesthesia = $db_surgeryanesthesiacrosswalk->getAdapter();
                        $where = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id = ?', $del_provider_id);
                        $temp = $db_surgeryanesthesiacrosswalk->fetchRow($where);
                        if ($temp) {
                            $anesthesiacode_id = $temp['id'];
                        } else {
                            $where1 = $db_anesthesia->quoteInto('anesthesia_code = ?', $anesthesiacode) . $db_anesthesia->quoteInto('AND provider_id in(?)', $provider_ids);
                            $temp1 = $db_surgeryanesthesiacrosswalk->fetchRow($where1);
                            if ($temp1[0]) {
                                $anes['anesthesia_code'] = $temp1[0]['anesthesia_code'];
                                $anes['description'] = $temp1[0]['description'];
                                $anes['base_unit'] = $temp1[0]['base_unit'];
                                $anes['default_modifier_1'] = $temp1[0]['default_modifier_1'];
                                $anes['default_modifier_2'] = $temp1[0]['default_modifier_2'];
                                $anes['provider_id'] = $provider_id;

                                $anesthesiacode_id = $db_surgeryanesthesiacrosswalk->insert($anes);
                                if ($anesthesiacode_id) {
                                    $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anes['anesthesia_code']);
                                    $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.description', NULL, $anes['description']);
                                    $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anes['base_unit']);
                                    $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anes['default_modifier_1']);
                                    $this->adddatalog($anes['provider_id'], $anes['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anes['default_modifier_2']);
                                }
                            }
                        }
                        if ($anesthesiacode_id != '') {
                            $CPT['anesthesiacode_id'] = $anesthesiacode_id;
                        } else {
                            $CPT['anesthesiacode_id'] = NULL;
                        }

                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_cpt->getAdapter();
                        $where = $db->quoteInto('id  = ?', $CPT_code);
                        $cpt1 = $db_cpt->fetchAll($where)->toArray();
                        $CPTCode = $cpt1[0]['CPT_code'];
                        $CPTdescription = $cpt1[0]['description'];
                        $charge_amount = $cpt1[0]['charge_amount'];
                        $payment_expected = $cpt1[0]['payment_expected'];
                        $where = $db->quoteInto('CPT_code =?', $CPTCode) . $db->quoteInto(' AND provider_id=?', $del_provider_id) . $db->quoteInto(' AND description=?', $CPTdescription) . $db->quoteInto(' AND charge_amount= ? ', $charge_amount) . $db->quoteInto(' AND payment_expected= ? ', $payment_expected);
                        //$oldcptcode=$db_cptcode->fetchAll($where)->toArray();
                        $wherold = $db_anesthesia->quoteInto('id= ?', $cpt1[0]['anesthesiacode_id']);
                        $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                        if ($db_cptcode->update($CPT, $where)) {
                            //   $provider_id=$provider[$i]['id'];
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.description', $cpt1[0]['description'], $CPT['description']);
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.charge_amount', $cpt1[0]['charge_amount'], $CPT['charge_amount']);
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.payment_expected', $cpt1[0]['payment_expected'], $CPT['payment_expected']);
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.default_modifier_1', $cpt1[0]['default_modifier_1'], $CPT['default_modifier_1']);
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.default_modifier_2', $cpt1[0]['default_modifier_2'], $CPT['default_modifier_2']);
                            $this->adddatalog($del_provider_id, $cpt1[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], $anesthesiacode);
                        }
                    }
                }

//                $db_surgeryanesthesiacrosswalk->update($CPT, $where);
//                }
                /*                 * add to let the facility page show the select provider that have been selected before update james#datatoolbehavior */
                session_start();
                $_SESSION['management_data']['provider_id'] = $provider_id;

                $this->_redirect('/biller/data/surgerycode');
            }

            if ($submitType == "Delete") {
                $CPT_code = $this->getRequest()->getPost('cpt_code');

                $anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');

                if ($CPT_code != '') {
                    $db_cptcode = new Application_Model_DbTable_Cptcode();
                    $db = $db_cptcode->getAdapter();
//                 $where = $db->quoteInto('CPT_code = ?', $CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);

                    if ($provider_id != 0) {
                        $where = $db->quoteInto('id = ?', $CPT_code) . $db->quoteInto('AND provider_id=?', $provider_id);
                        $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                        $wherold = $db_anesthesia->quoteInto('id= ?', $cpt1[0]['anesthesiacode_id']);
                        $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                        if ($db_cptcode->delete($where)) {
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.CPT_code', $oldcptcode[0]['CPT_code'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], NULL);
                        }
                    } else {
                        $del_provider_id = $this->getRequest()->getPost('del_provider_id');
                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_cpt->getAdapter();
                        $where = $db->quoteInto('id  = ?', $CPT_code);
                        $cpt = $db_cpt->fetchAll($where)->toArray();
                        $CPTCode = $cpt[0]['CPT_code'];
                        if ($del_provider_id == 0) {

                            for ($i = 0; $i < count($provider); $i++) {
                                $where = $db->quoteInto('CPT_code=?', $CPTCode) . $db->quoteInto(' AND provider_id=?', $provider[$i]['id']);
                                $provider_id = $provider[$i]['id'];
                                $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                                $wherold = $db_anesthesia->quoteInto('id= ?', $cpt1[0]['anesthesiacode_id']);
                                $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                                if ($db_cptcode->delete($where)) {
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.CPT_code', $oldcptcode[0]['CPT_code'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], NULL);
                                    $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], NULL);
                                }
                            }
                        } else {

                            $where = $db->quoteInto('CPT_code =?', $CPTCode) . $db->quoteInto(' AND provider_id=?', $del_provider_id);
                            $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                            $wherold = $db_anesthesia->quoteInto('id= ?', $oldcptcode[0]['anesthesiacode_id']);
                            $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                            if ($db_cptcode->delete($where)) {
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.CPT_code', $oldcptcode[0]['CPT_code'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], NULL);
                                $this->adddatalog($del_provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], NULL);
                            }
                        }
                    }
                    $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                    $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                    $where = $db->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db->quoteInto('AND provider_id = ?', $provider_id);

                } else {

                    $db_cptcode = new Application_Model_DbTable_Cptcode();
                    $db = $db_cptcode->getAdapter();
//                $where = $db->quoteInto('CPT_code = ?', $CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);

                    if ($provider_id != 0) {
                        $where = $db->quoteInto('CPT_code = ?', $CPT_code) . $db->quoteInto('AND provider_id=?', $provider_id);
                        $oldcptcode = $db_cptcode->fetchAll($where)->toArray();
                        $wherold = $db_anesthesia->quoteInto('id= ?', $oldcptcode[0]['anesthesiacode_id']);
                        $oldanes = $db_surgeryanesthesiacrosswalk->fetchAll($wherold)->toArray();
                        if ($db_cptcode->delete($where)) {
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.CPT_code', $oldcptcode[0]['CPT_code'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.description', $oldcptcode[0]['description'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.charge_amount', $oldcptcode[0]['charge_amount'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.payment_expected', $oldcptcode[0]['payment_expected'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_1', $oldcptcode[0]['default_modifier_1'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.default_modifier_2', $oldcptcode[0]['default_modifier_2'], NULL);
                            $this->adddatalog($provider_id, $oldcptcode[0]['CPT_code'], 'cptcode.anesthesiacode_id', $oldanes[0]['anesthesia_code'], NULL);
                        }
                    }
                }

                $this->_redirect('/biller/data/surgerycode');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newsurgery');
        }
    }

    /**
     * newsurgeryAction
     * a function for creating a new surgerycode.
     * @author Caijun.
     * @version 05/15/2012
     */
    function newsurgeryAction() {
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasSurg.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM cptcode cpt WHERE cpt.id IN 
        (
            SELECT t.aid FROM 
                (
                    SELECT cpts.id  aid,count(*) counts FROM cptcode cpts,provider p 
                    WHERE (cpts.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY aid
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasSurg
ON hasSurg.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;


        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
//        $this->view->providerList = $provider;

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $modifier = $db_modifier->fetchAll();
        $this->view->modifierList = $modifier;

        $sql = <<<SQL
SELECT count(CPT_code) as num  FROM (
SELECT CPT_code, count( cptcode.provider_id ) AS total
FROM cptcode, provider,anesthesiacode
WHERE cptcode.provider_id = provider.id and anesthesiacode.id=cptcode.anesthesiacode_id and CPT_code <>"Need New"
AND billingcompany_id =?
GROUP BY CPT_code,cptcode.description,charge_amount, payment_expected,anesthesia_code,cptcode.default_modifier_1,cptcode.default_modifier_2
)t
WHERE t.total >= ( 
SELECT count( * ) 
FROM provider
WHERE billingcompany_id =? )
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $sql = <<<SQL
        SELECT CPT_code, count( CPT_code ) AS num, provider_id
FROM (

SELECT CPT_code, count( cptcode.provider_id ) AS total, provider_id
FROM cptcode, provider
WHERE cptcode.provider_id = provider.id and CPT_code <>"Need New"
AND billingcompany_id =?
GROUP BY CPT_code,description,charge_amount, payment_expected,anesthesiacode_id,default_modifier_1,default_modifier_2
)t
WHERE t.total >= ( 
SELECT count( * ) 
FROM provider
WHERE billingcompany_id =? ) 
GROUP BY provider_id
SQL
        ;
        $result1 = $db->query($sql, array($billingcompany_id, $billingcompany_id));
        $all_data1 = $result1->fetchAll();
        $num1 = 0;
        $num2 = 0;
        if (isset($all_data[0]))
            $num1 = $all_data[0]['num'];
        if (isset($all_data1[0]))
            $num2 = $all_data1[0]['num'];
        $number = $num1 + $num2;
        $this->view->allNumSur = array('num' => $number);
        if ($this->getRequest()->isPost()) {
            $provider_id = $this->getRequest()->getPost('provider_id');
            $new_or_import = $this->getRequest()->getPost('import_or_new');

            if ($new_or_import == 'import') {
                $provider_ids = array();
                $billingcompany_id = $this->billingcompany_id();
                $db_provider = new Application_Model_DbTable_Provider();
                $db = $db_provider->getAdapter();
                $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
                for ($i = 0; $i < count($provider_data); $i++) {
                    if ($provider_data[$i]['id'] != $provide_id)
                        $provider_ids[$i] = $provider_data[$i]['id'];
                }
                $CPT_id = $this->getRequest()->getPost('add_surgery_code');
                $db_surgeryexisting = new Application_Model_DbTable_Cptcode();
                $db_exist = $db_surgeryexisting->getAdapter();
                $wherecpt = $db_exist->quoteInto('id = ?', $CPT_id);
                $tempexisting = $db_surgeryexisting->fetchAll($wherecpt)->toArray();
                if (($tempexisting[0])) {
                    $CPT['CPT_code'] = $tempexisting[0]['CPT_code'];
                }
                $where = $db_exist->quoteInto('CPT_code = ?', $CPT['CPT_code']) . $db_exist->quoteInto('and provider_id in (?)', $provider_ids);
                $existingCpt = $db_surgeryexisting->fetchAll($where)->toArray();
                $anescode_id = $existingCpt[0]['anesthesiacode_id'];
                $db_anescodeinsert = new Application_Model_DbTable_Anesthesiacode();
                $db_anes_exist = $db_anescodeinsert->getAdapter();
                $whereanes = $db_anes_exist->quoteInto('id =  ?', $anescode_id) . $db_anes_exist->quoteInto('and provider_id in (?)', $provider_ids);
                $anescodeArray = $db_anescodeinsert->fetchAll($whereanes)->toArray();

                $CPT['description'] = $this->getRequest()->getPost('description_CPT');

                $CPT['charge_amount'] = $this->getRequest()->getPost('charge');
                $CPT['payment_expected'] = $this->getRequest()->getPost('expected_amount');

                $CPT['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                if (!$CPT['default_modifier_1']) {
                    $CPT['default_modifier_1'] = NULL;
                }
                $CPT['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                if (!$CPT['default_modifier_2']) {
                    $CPT['default_modifier_2'] = NULL;
                }
                if ($provider_id != 0) {
                    $newcode = array();
                    $anesthesiaCode = 0;

                    $db_cpt = new Application_Model_DbTable_Cptcode();
                    $db = $db_cpt->getAdapter();
                    $where = $db->quoteInto('CPT_code = ?', $CPT['CPT_code']) . $db_exist->quoteInto('and provider_id = ?', $provider_id);
                    $existingCpt1 = $db_surgeryexisting->fetchAll($where)->toArray();
                    if ($existingCpt1[0]) {
                        $db_provider1 = new Application_Model_DbTable_Provider();
                        $dbprovider = $db_provider1->getAdapter();
                        $whereprovider = $dbprovider->quoteInto('id = ?', $provider_id); //
                        $provider_data1 = $db_provider1->fetchAll($whereprovider, "provider_name ASC");
                        echo '<span style="color:red;font-size:16px">Sorry ! The Surgery Code of ' . $provider_data1[0]['provider_name'] . ' is existing , please use new and rewrite!</span>';
                    } else {
                        if ($anescodeArray[0]) {
                            $CPT['provider_id'] = $provider_id;
                            $db_anescodeinsert1 = new Application_Model_DbTable_Anesthesiacode();
                            $db_anes_exist1 = $db_anescodeinsert1->getAdapter();
                            $whereinsert1 = $db_anes_exist1->quoteInto('anesthesia_code =  ?', $anescodeArray[0]['anesthesia_code']) . $db_anes_exist->quoteInto('and provider_id = ?', $provider_id);

                            $exit_temp = $db_anescodeinsert1->fetchAll($whereinsert1)->toArray();
                            if (!$exit_temp[0]) {
                                $newcode['anesthesia_code'] = $anescodeArray[0]['anesthesia_code'];
                                $newcode['description'] = $anescodeArray[0]['description'];
                                $newcode['base_unit'] = $anescodeArray[0]['base_unit'];
                                $newcode['default_modifier_1'] = $anescodeArray[0]['default_modifier_1'];
                                if ($newcode['default_modifier_1'] == '') {
                                    $newcode['default_modifier_1'] = NULL;
                                }
                                $newcode['default_modifier_2'] = $anescodeArray[0]['default_modifier_2'];
                                if ($newcode['default_modifier_2'] == '') {
                                    $newcode['default_modifier_2'] = NULL;
                                }
                                $newcode['provider_id'] = $provider_id;


                                $anesthesiaCode = $db_anescodeinsert->insert($newcode);
                                if ($anesthesiaCode) {
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $newcode['anesthesia_code']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.description', NULL, $newcode['description']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $newcode['base_unit']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $newcode['default_modifier_1']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $newcode['default_modifier_2']);
                                }
                            } else {
                                $anesthesiaCode = $exit_temp[0]['id'];
                            }
                        }

                        if ($anesthesiaCode)
                            $CPT['anesthesiacode_id'] = $anesthesiaCode;
                        else
                            $CPT['anesthesiacode_id'] = NULL;
                        $CPT['provider_id'] = $provider_id;

                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_cpt->getAdapter();
                        $db_anescodeinsert1 = new Application_Model_DbTable_Anesthesiacode();
                        $db_anes_exist1 = $db_anescodeinsert1->getAdapter();
                        $whereanes = $db_anes_exist1->quoteInto(' id = ? ', $anesthesiaCode);
                        $anesthesia_code = $db_anescodeinsert1->fetchAll($whereanes)->toArray();
                        if ($db_cpt->insert($CPT)) {
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.CPT_code', NULL, $CPT['CPT_code']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.description', NULL, $CPT['description']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.charge_amount', NULL, $CPT['charge_amount']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.payment_expected', NULL, $CPT['payment_expected']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.default_modifier_1', NULL, $CPT['default_modifier_1']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.default_modifier_2', NULL, $CPT['default_modifier_2']);
                            $this->adddatalog($provider_id, $CPT['CPT_code'], 'cptcode.anesthesiacode_id', NULL, $anesthesia_code);
                        }
                        $this->_redirect('/biller/data/surgerycode');
                    }
                } else {

                    for ($i = 0; $i < count($provider); $i++) {
                        $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_cpt->getAdapter();
                        $where2 = $db->quoteInto('CPT_code = ?', $CPT['CPT_code']) . $db_exist->quoteInto('and provider_id = ?', $provider[$i]['id']);
                        $existingCpt1 = $db_surgeryexisting->fetchAll($where2)->toArray();
                        if ($existingCpt1[0]) {
                            
                        } else {
                            $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                            $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                            $where = $db->quoteInto('anesthesia_code = ?', $anescodeArray[0]['anesthesia_code']);
                            $anesthesiacode = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();

//                                        $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
//                                        $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                            if ($anesthesiacode[0]) {
                                $mytemp = $anesthesiacode[0]['anesthesia_code'];
                                $where = $db->quoteInto('anesthesia_code = ?', $mytemp) . $db->quoteInto('AND provider_id = ?', $provider[$i]['id']);
                                $temp = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();
                                //$anesthesia['provider_id'] = $provider[$i]['id'];
                                $newcode = array();
                                if ($temp[0]) {
                                    $CPT['anesthesiacode_id'] = $temp[0]['id'];
                                } else {

                                    $newcode['anesthesia_code'] = $anescodeArray[0]['anesthesia_code'];
                                    $newcode['description'] = $anescodeArray[0]['description'];
                                    $newcode['base_unit'] = $anescodeArray[0]['base_unit'];
                                    $newcode['default_modifier_1'] = $anescodeArray[0]['default_modifier_1'];
                                    if ($newcode['default_modifier_1'] == '') {
                                        $newcode['default_modifier_1'] = NULL;
                                    }
                                    $newcode['default_modifier_2'] = $anescodeArray[0]['default_modifier_2'];
                                    if ($newcode['default_modifier_2'] == '') {
                                        $newcode['default_modifier_2'] = NULL;
                                    }
                                    $newcode['provider_id'] = $provider[$i]['id'];

                                    $CPT['anesthesiacode_id'] = $db_surgeryanesthesiacrosswalk->insert($newcode);
                                    if ($CPT['anesthesiacode_id']) {
                                        $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $newcode['anesthesia_code']);
                                        $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.description', NULL, $newcode['description']);
                                        $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $newcode['base_unit']);
                                        $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $newcode['default_modifier_1']);
                                        $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $newcode['default_modifier_2']);
                                    }
                                }
                            }
                            if ($CPT['anesthesiacode_id'])
                                $CPT['anesthesiacode_id'] = $anesthesiaCode;
                            else
                                $CPT['anesthesiacode_id'] = NULL;
                            $CPT['provider_id'] = $provider[$i]['id'];
                            $db_anescodeinsert1 = new Application_Model_DbTable_Anesthesiacode();
                            $db_anes_exist1 = $db_anescodeinsert1->getAdapter();
                            $whereanes = $db_anes_exist1->quoteInto(' id = ? ', $anesthesiaCode);
                            $anesthesia_code = $db_anescodeinsert1->fetchAll($whereanes)->toArray();

                            //$db_surgeryanesthesiacrosswalk->insert($anesthesia);
                            if ($db_cpt->insert($CPT)) {
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.CPT_code', NULL, $CPT['CPT_code']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.description', NULL, $CPT['description']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.charge_amount', NULL, $CPT['charge_amount']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.payment_expected', NULL, $CPT['payment_expected']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_1', NULL, $CPT['default_modifier_1']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_2', NULL, $CPT['default_modifier_2']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.anesthesiacode_id', NULL, $anesthesia_code[0]['anesthesia_code']);
                            }
                        }
                    }
                    $this->_redirect('/biller/data/surgerycode');
                }
//                    }
            } else {//new
                $CPT['CPT_code'] = $this->getRequest()->getPost('surgery_code');

                $anesthesiaCode = $this->getRequest()->getPost('anesthesia_code_id');
                $db_anescode = new Application_Model_DbTable_Anesthesiacode();
                $db_anes = $db_anescode->getAdapter();
                $where_anes = $db_anes->quoteInto('id = ?', $anesthesiaCode);
                $anes_data = $db_anescode->fetchAll($where_anes)->toArray();

                $db_surgeryexisting = new Application_Model_DbTable_Cptcode();
                $db_exist = $db_surgeryexisting->getAdapter();

                if ($anesthesiaCode)
                    $CPT['anesthesiacode_id'] = $anesthesiaCode;
                else
                    $CPT['anesthesiacode_id'] = NULL;

                $CPT['description'] = $this->getRequest()->getPost('description_CPT');
                //$CPT['anesthesiacode_id']=$anesthesiaCode;
                $CPT['charge_amount'] = $this->getRequest()->getPost('charge');
                $CPT['payment_expected'] = $this->getRequest()->getPost('expected_amount');
                $CPT['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                if (!$CPT['default_modifier_1']) {
                    $CPT['default_modifier_1'] = NULL;
                }
                $CPT['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                if (!$CPT['default_modifier_2']) {
                    $CPT['default_modifier_2'] = NULL;
                }

                //p判断CPT_code和provider_id是否存在
                $where = $db_exist->quoteInto('CPT_code = ?', $CPT['CPT_code']) . $db_exist->quoteInto('and provider_id = ?', $provider_id);
                $existingCpt = $db_surgeryexisting->fetchAll($where)->toArray();
                if ($provider_id != 0) {
                    if ($existingCpt[0]) {
                        $db_provider1 = new Application_Model_DbTable_Provider();
                        $dbprovider = $db_provider1->getAdapter();
                        $whereprovider = $dbprovider->quoteInto('id = ?', $provider_id); //
                        $provider_data1 = $db_provider1->fetchAll($whereprovider, "provider_name ASC");
                        echo '<span style="color:red;font-size:16px">Sorry ! The Surgery Code of ' . $provider_data1[0]['provider_name'] . ' is existing , please rewrite!</span>';
                    } else {
                        $db = Zend_Registry::get('dbAdapter');
                        $billingcompany_id = $this->billingcompany_id();
                        //sql语句
                        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
                        //全对应facility：该facility对应着改组provider中的每一个
                        $sql = <<<SQL
SELECT * 
FROM cptcode, anesthesiacode, provider
WHERE anesthesiacode_id = anesthesiacode.id
AND provider.id = cptcode.provider_id
AND provider.billingcompany_id =?
AND cptcode.description =?
AND cptcode.charge_amount =?
AND cptcode.payment_expected =?
AND cptcode.default_modifier_1 =?
AND cptcode.default_modifier_2 =?
AND anesthesiacode.anesthesia_code =?
SQL
                        ;
                        $result1 = $db->query($sql, array($billingcompany_id, $CPT['description'], $CPT['charge_amount'], $CPT['payment_expected'], $CPT['default_modifier_1'], $CPT['default_modifier_2'], $anes_data[0]['anesthesia_code']));
                        //获取所有行
                        $cptcode1 = $result1->fetchAll();
                        $sql = <<<SQL
SELECT * 
FROM cptcode,  provider
WHERE  provider.id = cptcode.provider_id
AND provider.billingcompany_id =?
AND cptcode.description =?
AND cptcode.charge_amount =?
AND cptcode.payment_expected =?
AND cptcode.default_modifier_1 =?
AND cptcode.default_modifier_2 =?
AND cptcode.anesthesiacode_id =?
SQL
                        ;
                        $result2 = $db->query($sql, array($billingcompany_id, $CPT['description'], $CPT['charge_amount'], $CPT['payment_expected'], $CPT['default_modifier_1'], $CPT['default_modifier_2'], $CPT['anesthesiacode_id']));
                        //获取所有行
                        $cptcode2 = $result2->fetchAll();
                        if ($cptcode1 || $cptcode2) {

                            echo '<span style="color:red;font-size:16px">Sorry ! The Surgery Code  is existing , please use import!</span>';
                        } else {


                            $db_cpt = new Application_Model_DbTable_Cptcode();
                            $db = $db_cpt->getAdapter();
                            $CPT['provider_id'] = $provider_id;
                            $db_anescodeinsert1 = new Application_Model_DbTable_Anesthesiacode();
                            $db_anes_exist1 = $db_anescodeinsert1->getAdapter();
                            $whereanes = $db_anes_exist1->quoteInto(' id = ? ', $anesthesiaCode);
                            $anesthesia_code = $db_anescodeinsert1->fetchAll($whereanes)->toArray();
                            if ($db_cpt->insert($CPT)) {
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.CPT_code', NULL, $CPT['CPT_code']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.description', NULL, $CPT['description']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.charge_amount', NULL, $CPT['charge_amount']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.payment_expected', NULL, $CPT['payment_expected']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_1', NULL, $CPT['default_modifier_1']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_2', NULL, $CPT['default_modifier_2']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.anesthesiacode_id', NULL, $anesthesia_code[0]['anesthesia_code']);
                            }
                            $this->_redirect('/biller/data/surgerycode');
                        }
                    }
                } else {
                    $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                    $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                    $where = $db->quoteInto('id = ?', $CPT['anesthesiacode_id']);
                    $anesthesiacode = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();

                    $newcode['anesthesia_code'] = $anesthesiacode[0]['anesthesia_code'];
                    $newcode['description'] = $anesthesiacode[0]['description'];
                    $newcode['base_unit'] = $anesthesiacode[0]['base_unit'];
                    $newcode['default_modifier_1'] = $anesthesiacode[0]['default_modifier_1'];
                    if ($newcode['default_modifier_1'] == '') {
                        $newcode['default_modifier_1'] = NULL;
                    }
                    $newcode['default_modifier_2'] = $anesthesiacode[0]['default_modifier_2'];
                    if (!$newcode['default_modifier_2'] == '') {
                        $newcode['default_modifier_2'] = NULL;
                    }



                    //  $anesthesiaCode= $db_anescodeinsert->insert($newcode);

                    for ($i = 0; $i < count($provider); $i++) {

                        if ($anesthesiacode[0]) {
                            $mytemp = $anesthesiacode[0]['anesthesia_code'];
                            $where = $db->quoteInto('anesthesia_code = ?', $mytemp) . $db->quoteInto('AND provider_id = ?', $provider[$i]['id']);
                            $temp = $db_surgeryanesthesiacrosswalk->fetchAll($where)->toArray();
                            //$anesthesia['provider_id'] = $provider[$i]['id'];
                            if ($temp[0]) {
                                $CPT['anesthesiacode_id'] = $temp[0]['id'];
                            } else {
                                $newcode['provider_id'] = $provider[$i]['id'];

                                if ($CPT['anesthesiacode_id']->insert($newcode)) {
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $newcode['anesthesia_code']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.description', NULL, $newcode['description']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $newcode['base_unit']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $newcode['default_modifier_1']);
                                    $this->adddatalog($newcode['provider_id'], $newcode['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $newcode['default_modifier_2']);
                                }
                            }
                        }
                        $CPT['provider_id'] = $provider[$i]['id'];
                        $db_surgeryexisting = new Application_Model_DbTable_Cptcode();
                        $db_exist = $db_surgeryexisting->getAdapter();
                        $where = $db_exist->quoteInto('CPT_code = ?', $CPT['CPT_code']) . $db_exist->quoteInto('and provider_id = ?', $CPT['provider_id']);
                        $existingCpt = $db_surgeryexisting->fetchAll($where)->toArray();
                        //$db_surgeryanesthesiacrosswalk->insert($anesthesia);
                        if ($existingCpt) {
                            
                        } else {
//    78
                            // $CPT['provider_id'] = $provider_id;
                            $db_anescodeinsert1 = new Application_Model_DbTable_Anesthesiacode();
                            $db_anes_exist1 = $db_anescodeinsert1->getAdapter();
                            $whereanes = $db_anes_exist1->quoteInto(' id = ? ', $CPT['anesthesiacode_id']);
                            $anesthesia_code = $db_anescodeinsert1->fetchAll($whereanes)->toArray();
                            if ($db_surgeryexisting->insert($CPT)) {
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.CPT_code', NULL, $CPT['CPT_code']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.description', NULL, $CPT['description']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.charge_amount', NULL, $CPT['charge_amount']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.payment_expected', NULL, $CPT['payment_expected']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_1', NULL, $CPT['default_modifier_1']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.default_modifier_2', NULL, $CPT['default_modifier_2']);
                                $this->adddatalog($CPT['provider_id'], $CPT['CPT_code'], 'cptcode.anesthesiacode_id', NULL, $anesthesia_code[0]['anesthesia_code']);
                            }

//                                        }
                        }
                    }
                    $this->_redirect('/biller/data/surgerycode');
                }
            }
        }
    }

    /**
     * anesthesiacodeaddlistAction
     * @Aution Caijun
     * @version 03/04/2014
     */
    function anesthesiacodeaddlistAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provide_id = $_POST['provider_id'];
        $provider_ids = array();
        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        for ($i = 0; $i < count($provider_data); $i++) {
            if ($provider_data[$i]['id'] != $provide_id)
                $provider_ids[$i] = $provider_data[$i]['id'];
        }
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db_exist = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT * FROM anesthesiacode where provider_id <> ? and provider_id in
        ( select id from provider where billingcompany_id = ?)
        and anesthesia_code not in 
        ( select anesthesia_code from 
        ( select anesthesia_code,count(provider_id) as total from anesthesiacode,provider
        where anesthesiacode.provider_id = provider.id and billingcompany_id=?
        group by anesthesia_code, description, base_unit,default_modifier_1,default_modifier_2
        )t
        where
        t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
        )
    

SQL
        ;
        $result = $db_exist->query($sql, array($provide_id, $billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $existingAnes = $result->fetchAll();
//            $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
//            $db_exist = $db_anesthesia->getAdapter();
//            $where = $db_exist->quoteInto('provider_id <> ?',   $provide_id).$db_exist->quoteInto('and provider_id in(?)', $provider_ids);
//            $existingAnes=$db_anesthesia->fetchAll($where,"")->toArray();

        $data = array();

        $data['ane_exist_data'] = $existingAnes;
        $data['crosswalkList'] = $existingAnes;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newanesthesiacodeAction
     * a function for creating a new anethesiacode.
     * @author CAIJUN.
     * @version 04/03/2014
     */
    function newanesthesiacodeinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $provider_id = $_POST['provider_id'];
        $anesthesia = $_POST['anesthesia_code'];
        $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_crosswalk->getAdapter();
        $where = $db->quoteInto('anesthesia_code = ?', $anesthesia);
        $temp = $db_crosswalk->fetchRow($where)->toArray();
        $data = array();
        $data['description'] = $temp['description'];
        $data['base_unit'] = $temp['base_unit'];
        $data['modifier_1'] = $temp['default_modifier_1'];
        $data['modifier_2'] = $temp['default_modifier_2'];
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newanesthesiacodeAction
     * a function for creating a new anethesiacode.
     * @author Haowei.
     * @version 05/15/2012
     */
    function newanesthesiacodeAction() {
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
//        $this->view->providerList = $provider;
        $user = Zend_Auth::getInstance()->getIdentity();
        //获取数据库适配器
        $db = Zend_Registry::get('dbAdapter');
        $billingcompany_id = $this->billingcompany_id();
        //sql语句
        //实现查该billingcommpany所有的provider列表，以及其所拥有的非全对应facility的个数
        //全对应facility：该facility对应着改组provider中的每一个
        $sql = <<<SQL
SELECT p.id,p.provider_name,p.street_address,COUNT(hasAnes.provider_id) num 
FROM provider p LEFT JOIN 
(
    SELECT provider_id FROM anesthesiacode ane WHERE ane.id IN 
        (
            SELECT t.aid FROM 
                (
                    SELECT anes.id  aid,count(*) counts FROM anesthesiacode anes,provider p 
                    WHERE (anes.provider_id = p.id AND p.billingcompany_id =?) 
                    GROUP BY aid
                ) t 
            WHERE 
                t.counts < (SELECT count(*) c FROM provider where billingcompany_id =?)
        )
) hasAnes
ON hasAnes.provider_id = p.id
WHERE billingcompany_id =? 
GROUP BY p.id
order by provider_name
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
        //获取所有行
        $provider_data = $result->fetchAll();
        //将获取值赋值给providerlist
        $this->view->providerList = $provider_data;

        $sql = <<<SQL
SELECT COUNT(*) num FROM provider p RIGHT JOIN anesthesiacode has ON p.id = has.provider_id 
WHERE billingcompany_id = ?
AND has.anesthesia_code  in 
(
SELECT anesthesia_code FROM 
(
  
    SELECT anesthesia_code, description, base_unit 
     ,count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) AND  hs.anesthesia_code <> "Need New"
    group by anesthesia_code, description, base_unit,default_modifier_1,default_modifier_2
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
        
AND has.description  in 
(
SELECT description FROM 
(
  
    SELECT anesthesia_code, description, base_unit,
      count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code, description, base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
)
AND has.base_unit  in 
(
SELECT base_unit FROM 
(
  
    SELECT anesthesia_code, description, base_unit, 
    count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code, description, base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
        
t.total >= (SELECT count(*) c FROM provider where billingcompany_id= ?)
)
GROUP BY p.id LIMIT 1
SQL
        ;
        $result = $db->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id, $billingcompany_id));
        $all_data = $result->fetchAll();
        $this->view->allNumAne = isset($all_data[0]) ? $all_data[0] : array('num' => 0);

        $db_modifier = new Application_Model_DbTable_Modifier();
        $db = $db_modifier->getAdapter();
        $modifier = $db_modifier->fetchAll();
        $this->view->modifierList = $modifier;

        if ($this->getRequest()->isPost()) {
            $provider_id = $this->getRequest()->getPost('provider_id');

            $import_or_new = $this->getRequest()->getPost('import_or_new');

            if ($import_or_new == 'new') {
                //$anesthesia['surgery_code'] = $this->getRequest()->getPost('surgery_code');
                $anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');
                $db_anesthesiaexisting = new Application_Model_DbTable_Anesthesiacode();
                $db_exist = $db_anesthesiaexisting->getAdapter();
                $where = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db_exist->quoteInto('and provider_id = ?', $provider_id);
                $existingAnes = $db_anesthesiaexisting->fetchAll($where)->toArray();
                //$anesthesia['surgery_code'] = $this->getRequest()->getPost('surgery_code');
                //$anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');
                if ($existingAnes) {
                    echo '<span style="color:red;font-size:16px">Sorry ! The Anesthesia Code is existing , please rewrite!</span>';
                } else {


                    //$CPT['CPT_code'] = $this->getRequest()->getPost('surgery_code');
//            $CPT['description'] = $this->getRequest()->getPost('description');
//            $CPT['charge_amount'] = $this->getRequest()->getPost('charge');
//            $CPT['payment_expected'] = $this->getRequest()->getPost('expected_amount');
//            $CPT['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
//            $CPT['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                    if ($anesthesia['anesthesia_code'] != '') {

                        $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                        //  $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                        $anesthesia['description'] = $this->getRequest()->getPost('description');
                        $anesthesia['base_unit'] = $this->getRequest()->getPost('base_unit');
                        $anesthesia['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                        //$anesthesia['status'] = $this->getRequest()->getPost('status');
                        if ($anesthesia['default_modifier_1'] == '') {
                            $anesthesia['default_modifier_1'] = null;
                        }
                        $anesthesia['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                        if ($anesthesia['default_modifier_2'] == '') {
                            $anesthesia['default_modifier_2'] = null;
                        }
                        if ($provider_id != 0) {
                            $db_provider = new Application_Model_DbTable_Provider();
                            $db = $db_provider->getAdapter();
                            $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
                            $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
                            $provider_ids = array();
                            for ($i = 0; $i < count($provider_data); $i++) {
                                $provider_ids[$i] = $provider_data[$i]['id'];
                            }

                            $where = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db_exist->quoteInto('and description = ?', $anesthesia['description']) . $db_exist->quoteInto('and base_unit = ?', $anesthesia['base_unit']) . $db_exist->quoteInto('and default_modifier_1 = ?', $anesthesia['default_modifier_1']) . $db_exist->quoteInto('and default_modifier_2 = ?', $anesthesia['default_modifier_2']) . $db_exist->quoteInto('and provider_id in (?)', $provider_ids);
                            $existingAnes = $db_anesthesiaexisting->fetchAll($where)->toArray();
                            if ($existingAnes[0]) {
                                echo '<span style="color:red;font-size:16px">Sorry ! The Anesthesia Code is existing , please user import!</span>';
                            } else {
                                $anesthesia['provider_id'] = $provider_id;
                                // $CPT['provider_id'] = $provider_id;
                                if ($db_surgeryanesthesiacrosswalk->insert($anesthesia)) {
                                    $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anesthesia['anesthesia_code']);
                                    $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.description', NULL, $anesthesia['description']);
                                    $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anesthesia['base_unit']);
                                    $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anesthesia['default_modifier_1']);
                                    $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anesthesia['default_modifier_2']);
                                }
                                $this->_redirect('/biller/data/anesthesiacode');
                            }
                            //$db_cpt->insert($CPT);
                        } else {
                            for ($i = 0; $i < count($provider); $i++) {
                                $anesthesia['provider_id'] = $provider[$i]['id'];
                                // $db_exist = $db_anesthesiaexisting->getAdapter();
                                $whereinsert = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db_exist->quoteInto('and provider_id = ?', $anesthesia['provider_id']);
                                $existinginsert = $db_anesthesiaexisting->fetchAll($whereinsert)->toArray();

                                // $CPT['provider_id'] = $provider[$i]['id'];
                                if ($existinginsert) {
                                    $db_provider1 = new Application_Model_DbTable_Provider();
                                    $dbprovider = $db_provider1->getAdapter();
                                    $whereprovider = $dbprovider->quoteInto('id = ?', $provider[$i]['id']); //
                                    $provider_data1 = $db_provider1->fetchAll($whereprovider, "provider_name ASC");
                                    echo '<span style="color:red;font-size:16px">Sorry ! The Anesthesiacode Code of ' . $provider_data1[0]['provider_name'] . ' is existing , please use new and rewrite!</span>';
                                    //do noting
                                } else {
                                    if ($db_surgeryanesthesiacrosswalk->insert($anesthesia)) {
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anesthesia['anesthesia_code']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.description', NULL, $anesthesia['description']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anesthesia['base_unit']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anesthesia['default_modifier_1']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anesthesia['default_modifier_2']);
                                    }
                                }
                                //$db_cpt->insert($CPT);
                            }
                            $this->_redirect('/biller/data/anesthesiacode');
                        }
                    }
                }
            } else {

                //$anesthesia['surgery_code'] = $this->getRequest()->getPost('surgery_code');
                $anesthesia['anesthesia_code'] = $this->getRequest()->getPost('add_anesthesia_code');
                $db_anesthesiaexisting = new Application_Model_DbTable_Anesthesiacode();
                $db_exist = $db_anesthesiaexisting->getAdapter();
                $where = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db_exist->quoteInto('and provider_id = ?', $provider_id);
                $existingAnes = $db_anesthesiaexisting->fetchAll($where)->toArray();
                //$anesthesia['surgery_code'] = $this->getRequest()->getPost('surgery_code');
                //$anesthesia['anesthesia_code'] = $this->getRequest()->getPost('anesthesia_code');
                if ($existingAnes) {
                    echo '<span style="color:red;font-size:16px">Sorry ! The Anesthesia Code is existing , please use new and rewrite!</span>';
                } else {

                    $whereget = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']);
                    $existingAnesget = $db_anesthesiaexisting->fetchAll($whereget)->toArray();
                    $anesthesia['base_unit'] = $existingAnesget[0]['base_unit'];
                    $anesthesia['description'] = $existingAnesget[0]['description'];
                    $anesthesia['default_modifier_1'] = $existingAnesget[0]['default_modifier_1'];
                    if ($anesthesia['default_modifier_1'] == '') {
                        $anesthesia['default_modifier_1'] = null;
                    }
                    $anesthesia['default_modifier_2'] = $existingAnesget[0]['default_modifier_2'];
                    if ($anesthesia['default_modifier_2'] == '') {
                        $anesthesia['default_modifier_2'] = null;
                    }
                    //$CPT['CPT_code'] = $this->getRequest()->getPost('surgery_code');
                    //            $CPT['description'] = $this->getRequest()->getPost('description');
                    //            $CPT['charge_amount'] = $this->getRequest()->getPost('charge');
                    //            $CPT['payment_expected'] = $this->getRequest()->getPost('expected_amount');
                    //            $CPT['default_modifier_1'] = $this->getRequest()->getPost('modifier_1');
                    //            $CPT['default_modifier_2'] = $this->getRequest()->getPost('modifier_2');
                    if ($anesthesia['anesthesia_code'] != '') {
                        $db_surgeryanesthesiacrosswalk = new Application_Model_DbTable_Anesthesiacode();
                        //  $db_cpt = new Application_Model_DbTable_Cptcode();
                        $db = $db_surgeryanesthesiacrosswalk->getAdapter();
                        if ($provider_id != 0) {
                            $anesthesia['provider_id'] = $provider_id;
                            // $CPT['provider_id'] = $provider_id;
                            if ($db_surgeryanesthesiacrosswalk->insert($anesthesia)) {
                                $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anesthesia['anesthesia_code']);
                                $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.description', NULL, $anesthesia['description']);
                                $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anesthesia['base_unit']);
                                $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anesthesia['default_modifier_1']);
                                $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anesthesia['default_modifier_2']);
                            }
                            $this->_redirect('/biller/data/anesthesiacode');
                            //$db_cpt->insert($CPT);
                        } else {
                            for ($i = 0; $i < count($provider); $i++) {
                                $anesthesia['provider_id'] = $provider[$i]['id'];
                                // $db_exist = $db_anesthesiaexisting->getAdapter();
                                $whereinsert = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia['anesthesia_code']) . $db_exist->quoteInto('and provider_id = ?', $anesthesia['provider_id']);
                                $existinginsert = $db_anesthesiaexisting->fetchAll($whereinsert)->toArray();

                                // $CPT['provider_id'] = $provider[$i]['id'];
                                if ($existinginsert) {
                                    //do noting
                                } else {
                                    if ($db_surgeryanesthesiacrosswalk->insert($anesthesia)) {
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.anesthesiacode_code', NULL, $anesthesia['anesthesia_code']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.description', NULL, $anesthesia['description']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.base_unit', NULL, $anesthesia['base_unit']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_1', NULL, $anesthesia['default_modifier_1']);
                                        $this->adddatalog($anesthesia['provider_id'], $anesthesia['anesthesia_code'], 'anesthesiacode.default_modifier_2', NULL, $anesthesia['default_modifier_2']);
                                    }
                                }
                                //$db_cpt->insert($CPT);
                            }
                            $this->_redirect('/biller/data/anesthesiacode');
                        }
                    }
                }
            }
        }
    }

    function anesthesiacodeexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        $anesthesia_code = $_POST['anesthesia_code'];
        $provider_id = $_POST['provider_id'];
        $existingCpt = array();

        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $provider_ids = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $provider_ids[$i] = $provider_data[$i]['id'];
        }
        if ($anesthesia_code != "" && $provider_id != "") {
            $db_surgeryexisting = new Application_Model_DbTable_Anesthesiacode();
            $db_exist = $db_surgeryexisting->getAdapter();
            $where = $db_exist->quoteInto('anesthesia_code = ?', $anesthesia_code) . $db_exist->quoteInto('and provider_id in (?)', $provider_ids);
            $existingCpt = $db_surgeryexisting->fetchAll($where)->toArray();
        }
        $data = array();
        if (isset($existingCpt[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * surgeryexistingAction
     * a function returning the anesthesiacode data for displaying on the page.
     * @author Haowei.
     * @return the anesthesiacode data for displaying on the page
     * @version 04/01/2014
     */
    function surgeryexistingAction() {
        $this->_helper->viewRenderer->setNoRender();
        $CPT_code = $_POST['cpt_code'];
        $provider_id = $_POST['provider_id'];
        $existingCpt = array();

        $billingcompany_id = $this->billingcompany_id();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id()); //
        $provider_data = $db_provider->fetchAll($where, "provider_name ASC");
        $provider_ids = array();
        for ($i = 0; $i < count($provider_data); $i++) {
            $provider_ids[$i] = $provider_data[$i]['id'];
        }
        if ($CPT_code != "" && $provider_id != "") {
            $db_surgeryexisting = new Application_Model_DbTable_Cptcode();
            $db_exist = $db_surgeryexisting->getAdapter();
            $where = $db_exist->quoteInto('CPT_code = ?', $CPT_code) . $db_exist->quoteInto('and provider_id in (?)', $provider_ids);
            $existingCpt = $db_surgeryexisting->fetchAll($where)->toArray();
        }
        $data = array();
        if (isset($existingCpt[0])) {
            $data = array('existing' => "1");
        }
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * anesthesiainfoAction
     * a function returning the anesthesiacode data for displaying on the page.
     * @author Haowei.
     * @return the anesthesiacode data for displaying on the page
     * @version 05/15/2012
     */
    function surgeryinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;


        $CPT_code = $_POST['cpt_code'];
        $provider_id = $_POST['provider_id'];


        $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
        $dbanes = $db_anesthesia->getAdapter();
        //$where_anesthesia = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());

        $db_cptcode = new Application_Model_DbTable_Cptcode();
        $db = $db_cptcode->getAdapter();
        if ($provider_id != 0) {
            $where = $db->quoteInto('id = ?', $CPT_code) . $db->quoteInto('AND provider_id=?', $provider_id);
            $where_anesthesia = $dbanes->quoteInto('provider_id = ?', $provider_id);
        } else {
            $where = $db->quoteInto('id = ?', $CPT_code);
            $where_anesthesia = $dbanes->quoteInto('provider_id = ?', $provider[$i]['id']);
        }
//            $where = $db->quoteInto('CPT_code = ?', $CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);
        //$anesthesiaList = $db_anesthesia->fetchRow($where_anesthesia)->toArray();

        $cptcode_data = $db_cptcode->fetchRow($where);
        $anesthesiacode = $cptcode_data['anesthesiacode_id'];
        $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
        $dbs = $db_crosswalk->getAdapter();
//            $search = 'surgery_code ='.$CPT_code.'AND'.' provider_id='.$provider_id;
        if ($provider_id != 0) {
            $where = $dbanes->quoteInto('id = ?', $anesthesiacode);
        } else {
            $where = $dbanes->quoteInto('id = ?', $anesthesiacode);
        }
//            $where = $db->quoteInto('surgery_code = ?',$CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);
        $crosswalk_data = $db_crosswalk->fetchRow($where);
        $crosswalk_code = $crosswalk_data['anesthesia_code'];
        $base_unit = $crosswalk_data['base_unit'];
        $description_anes = $crosswalk_data['description'];
        $description_cpt = $cptcode_data['description'];
        $charge_amount = $cptcode_data['charge_amount'];
        $payment_expected = $cptcode_data['payment_expected'];
//        if ($crosswalk_code == null) {
        $modifier1 = $cptcode_data['default_modifier_1'];
        $modifier2 = $cptcode_data['default_modifier_2'];
        $status = $cptcode_data['status'];

//        } else {
//            $modifier1 = $crosswalk_data['default_modifier_1'];
//            $modifier2 = $crosswalk_data['default_modifier_2'];
//        }
        $data = array();
        $data = array('description_cpt' => $description_cpt, 'description_anes' => $description_anes, 'anesthesia_code' => $crosswalk_code, 'base_unit' => $base_unit, 'charge_amount' => $charge_amount,
            'payment_expected' => $payment_expected, 'modifier_1' => $modifier1, 'modifier_2' => $modifier2, 'anesthesia' => $anesthesiaList,'status'=>$status);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function newsurgeryaddinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_cpt = new Application_Model_DbTable_Cptcode();
        $db = $db_cpt->getAdapter();
        $cpt_code_id = $_POST['cpt_code_id'];
        $where_cpt = $db->quoteInto('id=?', $cpt_code_id);
        $cpt = $db_cpt->fetchRow($where_cpt)->toArray();
        $data = array();
        $data = $cpt;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function newsurgeryinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_anesthesia->getAdapter();
        $anesthesia_code_id = $_POST['anesthesia_code_id'];

        $where_anesthesia = $db->quoteInto('id=?', $anesthesia_code_id);
        $anesthesia = $db_anesthesia->fetchRow($where_anesthesia)->toArray();
        $data = array();
        $data = array('base_unit' => $anesthesia['base_unit'], 'description' => $anesthesia['description'], 'modifier_1' => $anesthesia['modifier_1'], 'modifier_2' => $anesthesia['modifier_1']);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function surgeryinfooAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;


        $CPT_code = $_POST['cpt_code'];
        $provider_id = $_POST['provider_id'];
        $anesthesia_code_id = $_POST['anesthesia_code_id'];

        $db_anesthesia = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_anesthesia->getAdapter();
        //$where_anesthesia = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());

        $db_cptcode = new Application_Model_DbTable_Cptcode();
        $db = $db_cptcode->getAdapter();
        if ($provider_id != 0) {
            $where = $db->quoteInto('id = ?', $CPT_code);
            $where_anesthesia = $db->quoteInto('provider_id = ?', $provider_id);
        } else {
            $where = $db->quoteInto('id = ?', $CPT_code);
            $where_anesthesia = $db->quoteInto('provider_id = ?', $provider[0]['id']);
        }
//            $where = $db->quoteInto('CPT_code = ?', $CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);
        $anesthesiaList = $db_anesthesia->fetchRow($where_anesthesia)->toArray();

        $cptcode_data = $db_cptcode->fetchRow($where);
        $anesthesiacode = $anesthesia_code_id;
        $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_crosswalk->getAdapter();
//            $search = 'surgery_code ='.$CPT_code.'AND'.' provider_id='.$provider_id;
        if ($provider_id != 0) {
            $where = $db->quoteInto('anesthesia_code= ?', $anesthesiacode);
        } else {
            $where = $db->quoteInto('anesthesia_code = ?', $anesthesiacode);
        }
//            $where = $db->quoteInto('surgery_code = ?',$CPT_code).$db->quoteInto('AND provider_id=?',$provider_id);
        $crosswalk_data = $db_crosswalk->fetchRow($where);
        $crosswalk_code = $crosswalk_data['id'];
        $base_unit = $crosswalk_data['base_unit'];
        $description_anes = $crosswalk_data['description'];
        $description_cpt = $cptcode_data['description'];
        $charge_amount = $cptcode_data['charge_amount'];
        $payment_expected = $cptcode_data['payment_expected'];
//        if ($crosswalk_code == null) {
        $modifier1 = $cptcode_data['default_modifier_1'];
        $modifier2 = $cptcode_data['default_modifier_2'];
        //$status = $cptcode_data['status'];
//        } else {
//            $modifier1 = $crosswalk_data['default_modifier_1'];
//            $modifier2 = $crosswalk_data['default_modifier_2'];
//        }
        $data = array();
        $data = array('description_cpt' => $description_cpt, 'description_anes' => $description_anes, 'anesthesia_code' => $crosswalk_code, 'base_unit' => $base_unit, 'charge_amount' => $charge_amount,
            'payment_expected' => $payment_expected, 'modifier_1' => $modifier1, 'modifier_2' => $modifier2, 'anesthesia' => $anesthesiaList);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /* anesthesiaInfooACTION()
     * TO THE THREE TYPE OF THE ANESTHESIA
     */

    function anesthesiainfooAction() {
        $this->_helper->viewRenderer->setNoRender();
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;


        $provider_id = $_POST['provider_id'];
        $anesthesia = $_POST['anesthesia_code'];
        $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
        $db = $db_crosswalk->getAdapter();
        if ($provider_id != 0) {

            $where = $db->quoteInto('provider_id = ?', $provider_id) . $db->quoteInto('AND id = ?', $anesthesia);
        } else {
            $where = $db->quoteInto('provider_id = ?', $provider[0]['id']) . $db->quoteInto('AND id = ?', $anesthesia);
        }
        $temp = $db_crosswalk->fetchRow($where)->toArray();
        $data = array();
        $data['description'] = $temp['description'];
        $data['base_unit'] = $temp['base_unit'];
        $data['modifier_1'] = $temp['default_modifier_1'];
        $data['modifier_2'] = $temp['default_modifier_2'];
        $data['status'] = $temp['status'];
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /*     * diagnosiscodelistAction
     * to get the diagnosiscodelist for the provider
     * 2012/07/10
     * by PanDazhao
     */

    function myarray_intersect($souce, $tage) {
        $result = array();
        $k = 0;
        for ($i = 0; $i < count($souce); $i++) {

            for ($j = 0; $j < count($tage); $j++) {
                if ($souce[$i] == $tage[$j]) {
                    $result[$k] = $souce[$i];
                    $k++;
                }
            }
        }
        return $result;
    }

    function anesthesianlistAction() {
        $this->_helper->viewRenderer->setNoRender();

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;
        $billingcompany_id = $this->billingcompany_id();

        $id = $_POST['provider_id'];
        if ($id != 0) {
            $sql = <<<SQL
        SELECT CPT_code,description,charge_amount,payment_expected FROM 
(
  
    SELECT CPT_code, description, charge_amount,payment_expected 
     ,count(provider_id) as total FROM cptcode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by CPT_code, description, charge_amount,payment_expected 
    order by count(provider_id) desc, CPT_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
          ORDER BY CPT_code ASC 
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $recptcode = $result->fetchAll();
            $mycptCode = array();
            $Mydescription = array();
            $Mycharge_amount = array();
            $Mypayment_expected = array();
            for ($d = 0; $d < count($recptcode); $d++) {
                $mycptCode[$d] = $recptcode[$d]['CPT_code'];
                $Mydescription[$d] = $recptcode[$d]['description'];
                $Mycharge_amount[$d] = $recptcode[$d]['charge_amount'];
                $Mypayment_expected[$d] = $recptcode[$d]['payment_expected'];
            }
            //将获取值赋值给providerlist
            //  $this->view->providerList = $provider_data;
            if (empty($mycptCode))
                $mycptCode[0] = '0';
            if (empty($Mydescription))
                $Mydescription[0] = '-1';
            if (empty($Mycharge_amount))
                $Mycharge_amount[0] = '-1';
            if (empty($Mypayment_expected))
                $Mypayment_expected[0] = '-1';

            $db_cpt = new Application_Model_DbTable_Cptcode();
            $db = $db_cpt->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id) . $db->quoteInto('and (CPT_code not in (?)', $mycptCode) . $db->quoteInto('or description not in(?)', $Mydescription) . $db->quoteInto(' or charge_amount not in(?)', $Mycharge_amount) . $db->quoteInto(' or payment_expected not in(?))', $Mypayment_expected);
            $cptcodeList = $db_cpt->fetchAll($where)->toArray();

            $sql = <<<SQL
    SELECT anesthesia_code,description,base_unit FROM
(
  
    SELECT anesthesia_code,description,base_unit
     ,count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code,description,base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
                          ORDER BY anesthesia_code ASC 
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $anscode = $result->fetchAll();
            $myansCode = array();
            $Mydescription = array();
            $Mybase_unit = array();
            //  $Mypayment_expected=array();
            for ($d = 0; $d < count($anscode); $d++) {
                $myansCode[$d] = $anscode[$d]['anesthesia_code'];
                $Mydescription[$d] = $anscode[$d]['description'];
                $Mybase_unit[$d] = $anscode[$d]['base_unit'];
            }
            if (empty($myansCode))
                $myansCode[0] = '0';
            if (empty($Mydescription))
                $Mydescription[0] = '-1';
            if (empty($Mybase_unit))
                $Mybase_unit[0] = '-1';
            $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
            $db = $db_crosswalk->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id) . $db->quoteInto('and (anesthesia_code not in (?)', $myansCode) . $db->quoteInto('or description not in(?)', $Mydescription) . $db->quoteInto(' or base_unit not in(?))', $Mybase_unit);
            $crosswalkList = $db_crosswalk->fetchAll($where)->toArray();
        }
        else {
            $cptcode = array();
            $k = 0;
            for ($i = 0; $i < count($provider); $i++) {


                //获取数据库适配器
                $dbs = Zend_Registry::get('dbAdapter');

                $sql = <<<SQL
SELECT anesthesia_code,description,base_unit
FROM anesthesiacode
WHERE provider_id =? 
order by anesthesia_code
SQL
                ;
                $result = $dbs->query($sql, array($provider[$i]['id']));
                $cptcode[$i] = $result->fetchAll();
            }
            $MyResult = array();
            $MyResult = $cptcode[0];
            for ($mx = 1; $mx < count($cptcode); $mx++) {   //
                $temp = $MyResult;

                $souce = $MyResult;
                $tage = $cptcode[$mx];
                $result = array();
                $k = 0;
                for ($i = 0; $i < count($souce); $i++) {

                    for ($j = 0; $j < count($tage); $j++) {
                        if ($souce[$i] == $tage[$j]) {
                            $result[$k] = $souce[$i];
                            $k++;
                        }
                    }
                }
                $MyResult = $result;
            }
            for ($my = 0; $my < count($MyResult); $my++) {
                $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
                $db = $db_crosswalk->getAdapter();
                $where = $db->quoteInto('anesthesia_code = ?', $MyResult[$my][anesthesia_code]) . $db->quoteInto(' and description = ?', $MyResult[$my][description]) . $db->quoteInto(' and base_unit = ?', $MyResult[$my][base_unit]) . $db->quoteInto(' and provider_id = ?', $provider[0]['id']);
                $mytemp = $db_crosswalk->fetchAll($where)->toArray();
                if (!in_array($mytemp, $MyResult))
                    $crosswalkList[$my] = $mytemp[0];
            }



            ///////
            $cptcode = array();
            $k = 0;
            for ($i = 0; $i < count($provider); $i++) {


                //获取数据库适配器
                $dbs = Zend_Registry::get('dbAdapter');

                $sql = <<<SQL
SELECT CPT_code,description,charge_amount,payment_expected
FROM cptcode
WHERE provider_id =? 
ORDER BY CPT_code
SQL
                ;
                $result = $dbs->query($sql, array($provider[$i]['id']));
                $cptcode[$i] = $result->fetchAll();
            }
            $MyResult = array();
            $MyResult = $cptcode[0];
            for ($mx = 1; $mx < count($cptcode); $mx++) {
                $temp = $MyResult;

                $souce = $MyResult;
                $tage = $cptcode[$mx];
                $result = array();
                $k = 0;
                for ($i = 0; $i < count($souce); $i++) {

                    for ($j = 0; $j < count($tage); $j++) {
                        if ($souce[$i] == $tage[$j]) {
                            $result[$k] = $souce[$i];
                            $k++;
                        }
                    }
                }
                $MyResult = $result;
            }
            for ($my = 0; $my < count($MyResult); $my++) {
                $db_crosswalk = new Application_Model_DbTable_Cptcode();
                $db = $db_crosswalk->getAdapter();
                // CPT_code,description,charge_amount,,anesthesiacode_id
                // if($MyResult[$my][anesthesiacode_id]=='')
                $where = $db->quoteInto('CPT_code = ?', $MyResult[$my][CPT_code]) . $db->quoteInto(' and description = ?', $MyResult[$my][description]) . $db->quoteInto(' and charge_amount = ?', $MyResult[$my][charge_amount]) . $db->quoteInto(' and provider_id = ?', $provider[0]['id']) . $db->quoteInto(' and payment_expected = ?', $MyResult[$my][payment_expected]) . $db->quoteInto(' and payment_expected = ?', $MyResult[$my][payment_expected]);
                //else
                //  $where = $db->quoteInto('CPT_code = ?', $MyResult[$my][CPT_code]).$db->quoteInto(' and description = ?', $MyResult[$my][description]).$db->quoteInto(' and charge_amount = ?', $MyResult[$my][charge_amount]).$db->quoteInto(' and provider_id = ?', $provider[0]['id']).$db->quoteInto(' and anesthesiacode_id = ?', $MyResult[$my][anesthesiacode_id]).$db->quoteInto(' and payment_expected = ?', $MyResult[$my][payment_expected])  ;
                $mytemp = $db_crosswalk->fetchAll($where)->toArray();
                if (!in_array($mytemp, $MyResult))
                    $cptcodeList[$my] = $mytemp[0];
            }
        }



        //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
        //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）

        $data = array();
        //$this->deletenew($cptcodeList, 'CPT_code', 'Need New');
        //$this->deletenew($crosswalkList, 'anesthesia_code', 'Need New');
        $data['cptcodeList'] = $cptcodeList;
        $data['crosswalkList'] = $crosswalkList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function surgerlistAction() {
        $this->_helper->viewRenderer->setNoRender();

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;
        $billingcompany_id = $this->billingcompany_id();

        $id = $_POST['provider_id'];
        if ($id != 0) {
            $sql = <<<SQL
  SELECT CPT_code,description,charge_amount,payment_expected FROM 

(

  

 SELECT CPT_code, hs.description, charge_amount, payment_expected, count( hs.provider_id ) AS total

FROM cptcode hs, provider p, anesthesiacode anes

WHERE (

hs.provider_id = p.id

AND p.billingcompany_id =?

AND anes.id = hs.anesthesiacode_id 

)

GROUP BY CPT_code, description, charge_amount, payment_expected,anes.anesthesia_code,hs.default_modifier_1,hs.default_modifier_2

) t 

WHERE 

t.total >=(SELECT count(*) c FROM provider where billingcompany_id=?)
              ORDER BY CPT_code ASC 
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $recptcode = $result->fetchAll();

            $sql = <<<SQL
  SELECT CPT_code,description,charge_amount,payment_expected,anesthesiacode_id,total,default_modifier_1,default_modifier_2 FROM 
(
  
 SELECT CPT_code, hs.description,anesthesiacode_id, charge_amount, payment_expected,default_modifier_1,default_modifier_2 ,count( hs.provider_id ) AS total
FROM cptcode hs, provider p
WHERE 
hs.provider_id = p.id
AND p.billingcompany_id =?


GROUP BY CPT_code, description, charge_amount, payment_expected,anesthesiacode_id,hs.default_modifier_1,hs.default_modifier_2
) t 
WHERE 
t.total >=(SELECT count(*) c FROM provider where billingcompany_id=?)
                         ORDER BY CPT_code ASC 
SQL
            ;
            $result1 = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $recptcode1 = $result1->fetchAll();
            $mycptCode = array();
            $Mydescription = array();
            $Mycharge_amount = array();
            $Mypayment_expected = array();
            $index = 0;
            for ($d = 0; $d < count($recptcode); $d++) {
                $mycptCode[$index] = $recptcode[$d]['CPT_code'];
                $Mydescription[$index] = $recptcode[$d]['description'];
                $Mycharge_amount[$index] = $recptcode[$d]['charge_amount'];
                $Mypayment_expected[$index] = $recptcode[$d]['payment_expected'];
                $index++;
            }
            for ($d = 0; $d < count($recptcode1); $d++) {
                $mycptCode[$index] = $recptcode1[$d]['CPT_code'];
                $Mydescription[$index] = $recptcode1[$d]['description'];
                $Mycharge_amount[$index] = $recptcode1[$d]['charge_amount'];
                $Mypayment_expected[$index] = $recptcode1[$d]['payment_expected'];
                $index++;
            }
            //将获取值赋值给providerlist
            //  $this->view->providerList = $provider_data;
            if (empty($mycptCode))
                $mycptCode[0] = '0';
            if (empty($Mydescription))
                $Mydescription[0] = '-1';
            if (empty($Mycharge_amount))
                $Mycharge_amount[0] = '-1';
            if (empty($Mypayment_expected))
                $Mypayment_expected[0] = '-1';

            $db_cpt = new Application_Model_DbTable_Cptcode();
            $db = $db_cpt->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id) . $db->quoteInto('and CPT_code <> \'Need New\'') . $db->quoteInto('and (CPT_code not in (?)', $mycptCode) . $db->quoteInto('or description not in(?)', $Mydescription) . $db->quoteInto(' or charge_amount not in(?)', $Mycharge_amount) . $db->quoteInto(' or payment_expected not in(?))', $Mypayment_expected);
            $cptcodeList = $db_cpt->fetchAll($where)->toArray();

            $sql = <<<SQL
    SELECT anesthesia_code,description,base_unit FROM
(
  
    SELECT anesthesia_code,description,base_unit
     ,count(provider_id) as total FROM anesthesiacode hs ,provider p
    WHERE (hs.provider_id = p.id AND p.billingcompany_id =?) 
    group by anesthesia_code,description,base_unit
    order by count(provider_id) desc, anesthesia_code asc
) t 
WHERE 
t.total >= (SELECT count(*) c FROM provider where billingcompany_id=?)
                     ORDER BY anesthesia_code ASC 
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $anscode = $result->fetchAll();
            $myansCode = array();
            $Mydescription = array();
            $Mybase_unit = array();
            //  $Mypayment_expected=array();
            for ($d = 0; $d < count($anscode); $d++) {
                $myansCode[$d] = $anscode[$d]['anesthesia_code'];
                $Mydescription[$d] = $anscode[$d]['description'];
                $Mybase_unit[$d] = $anscode[$d]['base_unit'];
            }
            if (empty($myansCode))
                $myansCode[0] = '0';
            if (empty($Mydescription))
                $Mydescription[0] = '-1';
            if (empty($Mybase_unit))
                $Mybase_unit[0] = '-1';
            $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
            $db = $db_crosswalk->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id). $db->quoteInto('and anesthesia_code <> \'Need New\'');
            //.$db->quoteInto('and (anesthesia_code not in (?)', $myansCode).$db->quoteInto('or description not in(?)', $Mydescription).$db->quoteInto(' or base_unit not in(?))', $Mybase_unit) ;
            $crosswalkList = $db_crosswalk->fetchAll($where)->toArray();
        }//provider_id!=0
        else {
            $cptcode = array();
            $k = 0;
            for ($i = 0; $i < count($provider); $i++) {


                //获取数据库适配器
                $dbs = Zend_Registry::get('dbAdapter');

                $sql = <<<SQL
SELECT anesthesia_code,description,base_unit
FROM anesthesiacode
WHERE provider_id =? 
order by anesthesia_code
        
SQL
                ;
                $result = $dbs->query($sql, array($provider[$i]['id']));
                $cptcode[$i] = $result->fetchAll();
            }
            $MyResult = array();
            $MyResult = $cptcode[0];
            for ($mx = 1; $mx < count($cptcode); $mx++) {   //
                $temp = $MyResult;

                $souce = $MyResult;
                $tage = $cptcode[$mx];
                $result = array();
                $k = 0;
                for ($i = 0; $i < count($souce); $i++) {

                    for ($j = 0; $j < count($tage); $j++) {
                        if ($souce[$i] == $tage[$j]) {
                            $result[$k] = $souce[$i];
                            $k++;
                        }
                    }
                }
                $MyResult = $result;
            }
            for ($my = 0; $my < count($MyResult); $my++) {
                $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
                $db = $db_crosswalk->getAdapter();
                $where = $db->quoteInto('anesthesia_code = ?', $MyResult[$my][anesthesia_code]) . $db->quoteInto(' and description = ?', $MyResult[$my][description]) . $db->quoteInto(' and base_unit = ?', $MyResult[$my][base_unit]) . $db->quoteInto(' and provider_id = ?', $provider[0]['id']);
                $mytemp = $db_crosswalk->fetchAll($where)->toArray();
                if (!in_array($mytemp, $MyResult))
                    $crosswalkList[$my] = $mytemp[0];
            }

            $sql = <<<SQL
  SELECT id,CPT_code,description,charge_amount,payment_expected FROM 

(

  

 SELECT hs.id as id ,CPT_code, hs.description, charge_amount, payment_expected, count( hs.provider_id ) AS total

FROM cptcode hs, provider p, anesthesiacode anes

WHERE (

hs.provider_id = p.id

AND p.billingcompany_id =?

AND anes.id = hs.anesthesiacode_id 

)

GROUP BY CPT_code, description, charge_amount, payment_expected,anes.anesthesia_code,hs.default_modifier_1,hs.default_modifier_2

) t 

WHERE 

t.total >=(SELECT count(*) c FROM provider where billingcompany_id=?)
                 ORDER BY CPT_code ASC 
SQL
            ;
            $result = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $recptcode = $result->fetchAll();

            $sql = <<<SQL
  SELECT id,CPT_code,description,charge_amount,payment_expected,anesthesiacode_id,total,default_modifier_1,default_modifier_2 FROM 
(
  
 SELECT hs.id as id , CPT_code, hs.description,anesthesiacode_id, charge_amount, payment_expected,default_modifier_1,default_modifier_2 ,count( hs.provider_id ) AS total
FROM cptcode hs, provider p
WHERE 
hs.provider_id = p.id
AND p.billingcompany_id =?


GROUP BY CPT_code, description, charge_amount, payment_expected,anesthesiacode_id,hs.default_modifier_1,hs.default_modifier_2
) t 
WHERE 
t.total >=(SELECT count(*) c FROM provider where billingcompany_id=?)
                    ORDER BY CPT_code ASC 
SQL
            ;
            $result1 = $db->query($sql, array($billingcompany_id, $billingcompany_id));
            //获取所有行
            $recptcode1 = $result1->fetchAll();
            $mycptCode = array();

            $index = 0;
            for ($d = 0; $d < count($recptcode); $d++) {
                $mycptCode[$index] = $recptcode[$d];
                $index++;
            }
            for ($d = 0; $d < count($recptcode1); $d++) {
                $mycptCode[$index] = $recptcode1[$d];
                $index++;
            }
            $vals = array();
            $nums = array();
            foreach ($mycptCode as $key => $row) {

                $vals[$key] = $row['CPT_code'];
            }
            array_multisort($vals, SORT_ASC, $mycptCode);


            $cptcodeList = $mycptCode;
        }



        //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
        //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）

        $data = array();
        $this->deletenew($cptcodeList, 'CPT_code', 'Need New');
        $this->deletenew($crosswalkList, 'anesthesia_code', 'Need New');
        $cptcodeList = array_values($cptcodeList);
        $data['cptcodeList'] = $cptcodeList;
        $crosswalkList = array_values($crosswalkList);
        $data['crosswalkList'] = $crosswalkList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * newanesthesianlistAction
     * a function for processing the user data.
     * @author caijun.
     * @version 05/15/2012
     */
    function newanesthesianlistAction() {
        $this->_helper->viewRenderer->setNoRender();

        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id());
        $provider = $db_provider->fetchAll($where, 'provider_name ASC');
        $this->view->providerList = $provider;
        $billingcompany_id = $this->billingcompany_id();

        $id = $_POST['provider_id'];
        if ($id != 0) {
            $dbs = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            $sql = <<<SQL
SELECT cptcode.id AS id, CPT_code, provider_name
FROM cptcode, provider
WHERE cptcode.provider_id = provider.id
AND billingcompany_id =?
AND CPT_code NOT 
IN (

SELECT CPT_code
FROM cptcode, provider
WHERE cptcode.provider_id =?
AND cptcode.provider_id = provider.id
AND billingcompany_id =?
ORDER BY CPT_code ASC 
)
ORDER BY CPT_code
        
 
SQL
            ;
            $result = $dbs->query($sql, array($billingcompany_id, $id, $billingcompany_id));
            $existingcCPT = $result->fetchAll();

            $db_cpt = new Application_Model_DbTable_Cptcode();
            $db = $db_cpt->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id);
            $cptcodeList = $db_cpt->fetchAll($where)->toArray();

            $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
            $db = $db_crosswalk->getAdapter();
            $where = $db->quoteInto('provider_id = ?', $id);
            $crosswalkList = $db_crosswalk->fetchAll($where)->toArray();
        } else {
            $cptcode = array();
            $k = 0;
            for ($i = 0; $i < count($provider); $i++) {


                //获取数据库适配器
                $dbs = Zend_Registry::get('dbAdapter');

                $sql = <<<SQL
SELECT anesthesia_code,description,base_unit
FROM anesthesiacode
WHERE provider_id =? 
        ORDER BY anesthesia_code
SQL
                ;
                $result = $dbs->query($sql, array($provider[$i]['id']));
                $cptcode[$i] = $result->fetchAll();
            }
            $MyResult = array();
            $MyResult = $cptcode[0];
            for ($mx = 1; $mx < count($cptcode); $mx++) {   //
                $temp = $MyResult;

                $souce = $MyResult;
                $tage = $cptcode[$mx];
                $result = array();
                $k = 0;
                for ($i = 0; $i < count($souce); $i++) {

                    for ($j = 0; $j < count($tage); $j++) {
                        if ($souce[$i] == $tage[$j]) {
                            $result[$k] = $souce[$i];
                            $k++;
                        }
                    }
                }
                $MyResult = $result;
            }
            for ($my = 0; $my < count($MyResult); $my++) {
                $db_crosswalk = new Application_Model_DbTable_Anesthesiacode();
                $db = $db_crosswalk->getAdapter();
                $where = $db->quoteInto('anesthesia_code = ?', $MyResult[$my][anesthesia_code]) . $db->quoteInto(' and description = ?', $MyResult[$my][description]) . $db->quoteInto(' and base_unit = ?', $MyResult[$my][base_unit]) . $db->quoteInto(' and provider_id = ?', $provider[0]['id']);
                $mytemp = $db_crosswalk->fetchAll($where)->toArray();
                if (!in_array($mytemp, $MyResult))
                    $crosswalkList[$my] = $mytemp[0];
            }



            ///////
            $cptcode = array();
            $k = 0;
            for ($i = 0; $i < count($provider); $i++) {


                //获取数据库适配器
                $dbs = Zend_Registry::get('dbAdapter');

                $sql = <<<SQL
SELECT CPT_code,cptcode.description,charge_amount,payment_expected,anesthesia_code 
FROM cptcode,anesthesiacode 
WHERE cptcode.provider_id =? and anesthesiacode.id=cptcode.anesthesiacode_id
ORDER BY CPT_code
SQL
                ;
                $result = $dbs->query($sql, array($provider[$i]['id']));
                $cptcode[$i] = $result->fetchAll();
            }
            $MyResult = array();
            $MyResult = $cptcode[0];
            for ($mx = 1; $mx < count($cptcode); $mx++) {
                $temp = $MyResult;

                $souce = $MyResult;
                $tage = $cptcode[$mx];
                $result = array();
                $k = 0;
                for ($i = 0; $i < count($souce); $i++) {

                    for ($j = 0; $j < count($tage); $j++) {
                        if ($souce[$i] == $tage[$j]) {
                            $result[$k] = $souce[$i];
                            $k++;
                        }
                    }
                }
                $MyResult = $result;
            }
            for ($my = 0; $my < count($MyResult); $my++) {

                $db_crosswalk = new Application_Model_DbTable_Cptcode();
                $db = $db_crosswalk->getAdapter();
                // CPT_code,description,charge_amount,,anesthesiacode_id
                // if($MyResult[$my][anesthesiacode_id]=='')
                $where = $db->quoteInto('CPT_code = ?', $MyResult[$my][CPT_code]) . $db->quoteInto(' and description = ?', $MyResult[$my][description]) . $db->quoteInto(' and charge_amount = ?', $MyResult[$my][charge_amount]) . $db->quoteInto(' and provider_id = ?', $provider[0]['id']) . $db->quoteInto(' and payment_expected = ?', $MyResult[$my][payment_expected]);
                //else
                //  $where = $db->quoteInto('CPT_code = ?', $MyResult[$my][CPT_code]).$db->quoteInto(' and description = ?', $MyResult[$my][description]).$db->quoteInto(' and charge_amount = ?', $MyResult[$my][charge_amount]).$db->quoteInto(' and provider_id = ?', $provider[0]['id']).$db->quoteInto(' and anesthesiacode_id = ?', $MyResult[$my][anesthesiacode_id]).$db->quoteInto(' and payment_expected = ?', $MyResult[$my][payment_expected])  ;
                $mytemp = $db_crosswalk->fetchAll($where)->toArray();
                if (!in_array($mytemp, $MyResult))
                    $cptcodeList[$my] = $mytemp[0];
            }





            //当provider_id等于0时，也就是说，选择ALL选项时，设置单独的SQL语句
            //该SQL实现获取与本账单公司服务的所有provider都对应的facility（即全对应facility）
            $provide_id = $_POST['provider_id'];

            $dbs = Zend_Registry::get('dbAdapter');
            $billingcompany_id = $this->billingcompany_id();
            $sql = <<<SQL
SELECT cptcode.id, CPT_code,provider_name
FROM cptcode, provider
WHERE cptcode.provider_id = provider.id
AND provider.billingcompany_id =?
AND cptcode.CPT_code NOT 
IN (

SELECT CPT_code
FROM (

SELECT cptcode.id AS id, CPT_code, cptcode.description, charge_amount, payment_expected, anesthesia_code, count( cptcode.CPT_code ) AS total
FROM cptcode, anesthesiacode, provider
WHERE cptcode.provider_id = provider.id
AND (
anesthesiacode.id = cptcode.anesthesiacode_id
)
AND provider.billingcompany_id =?
GROUP BY CPT_code)t
WHERE t.total >= ( 
SELECT count( * ) c
FROM provider
WHERE billingcompany_id =? )
    )
ORDER BY CPT_code ASC 
        
 
SQL
            ;
            $result = $dbs->query($sql, array($billingcompany_id, $billingcompany_id, $billingcompany_id));
            $existingcCPT = $result->fetchAll();
        }
        $provider_ids = array();
        $billingcompany_id = $this->billingcompany_id();

        for ($i = 0; $i < count($provider); $i++) {
            if ($provider[$i]['id'] != $provide_id)
                $provider_ids[$i] = $provider[$i]['id'];
        }

        $provider_ids_add = array();
        for ($i = 0; $i < count($provider); $i++) {

            $provider_ids_add[$i] = $provider[$i]['id'];
        }
        $db_addanes = new Application_Model_DbTable_Anesthesiacode();
        $db_add = $db_addanes->getAdapter();
        $whereadd = $db_add->quoteInto('provider_id in(?)', $provider_ids_add);
        $addcpt = $db_addanes->fetchAll($whereadd)->toArray();
        $data = array();
        $data['addcrosswalkList'] = $addcpt;
        $data['cptcodeList'] = $existingcCPT;
        $data['crosswalkList'] = $crosswalkList;
        $json = Zend_Json::encode($data);
        echo $json;
    }

    /**
     * assignmentAction
     * a function for processing the user data.
     * @author Haowei.
     * @version 05/15/2012
     */
    function assignmentAction() {

//          $db_biller = new Application_Model_DbTable_Biller();
//        $db = $db_biller->getAdapter();
//        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id);
//        $user = $db_biller->fetchAll($where, 'biller_name ASC');
//        $this->view->billerList = $user;
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id);
        $providerList = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $providerList;

        $db = Zend_Registry::get('dbAdapter');
//get renderingprovider list
        $select = $db->select();
        $select->from('user', array());
        $select->join('biller', 'biller.biller_name=user.user_name');
        $select->where('biller.billingcompany_id = ?', $this->billingcompany_id);
        $select->where('user.role != ?', 'admin');
        $user = $db->fetchAll($select);

        $this->view->billerList = $user;

        $this->getRequest()->isPost();
        if ($this->getRequest()->isPost()) {
            $submitType = $this->getRequest()->getParam('submit');
            if ($submitType == "Update") {
                $user_data = array();
                $user_name = $this->getRequest()->getPost('user_name');
                $user_data['role'] = $this->getRequest()->getPost('role');
                $provider_id_array = $this->getRequest()->getPost('provider_id_array');
                $provider_id = array();
                if (strlen($provider_id_array) > 0) {
                    $provider_id = explode(',', $provider_id_array);
                }

                $db_user = new Application_Model_DbTable_User();
                $db = $db_user->getAdapter();
                $where = $db->quoteInto('user_name = ?', $user_name);
                $db_user->update($user_data, $where);
                $temp = $db_user->fetchRow($where);
                if ('guest' == $user_data['role']) {
                    $userfocusonprovider['user_id'] = $temp['id'];
                    $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                    $db = $db_userfocusonprovider->getAdapter();
                    $where = $db->quoteInto('user_id = ?', $temp['id']);
                    $db_userfocusonprovider->delete($where);
                    if (count($provider_id) > 0) {
                        for ($i = 0; $i < count($provider_id); $i++) {
                            $userfocusonprovider['provider_id'] = $provider_id[$i];
                            $db_userfocusonprovider->insert($userfocusonprovider);
                        }
                    }
                }
//                $billingcompany_name = $this->getRequest()->getPost('billingcompany_name');
//                $db_billingcompany = new Application_Model_DbTable_Billingcompany();
//                $db = $db_billingcompany->getAdapter();
//                $where = $db->quoteInto('billingcompany_name = ?', $billingcompany_name);
//                $billingcompany_data = $db_billingcompany->fetchRow($where);

                $biller_data['billingcompany_id'] = $this->billingcompany_id;
                $db_biller = new Application_Model_DbTable_Biller();
                $db = $db_biller->getAdapter();
                $where = $db->quoteInto('biller_name = ?', $user_name);
                $db_biller->update($biller_data, $where);
                $this->_redirect('/biller/data/assignment');
            }
            if ($submitType == "Delete") {
                $user_name = $this->getRequest()->getPost('user_name');

                $db_user = new Application_Model_DbTable_User();
                $db = $db_user->getAdapter();
                $where = $db->quoteInto('user_name = ?', $user_name);
                $db_user->delete($where);

                $db_biller = new Application_Model_DbTable_Biller();
                $db = $db_biller->getAdapter();
                $where = $db->quoteInto('biller_name = ?', $user_name);
                $db_biller->delete($where);
                $this->_redirect('/biller/data/assignment');
            }
            if ($submitType == "New")
                $this->_redirect('/biller/data/newassignment');
        }
    }

    /**
     * newassignmentAction
     * a function for creating a new user and assign the authority.
     * @author Haowei.
     * @version 05/15/2012
     */
    public function newassignmentAction() {
        $db_provider = new Application_Model_DbTable_Provider();
        $db = $db_provider->getAdapter();
        $where = $db->quoteInto('billingcompany_id = ?', $this->billingcompany_id);
        $providerList = $db_provider->fetchAll($where, "provider_name ASC");
        $this->view->providerList = $providerList;
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $billingcompany_data = $db_billingcompany->fetchAll(null, 'billingcompany_name ASC');
        $this->view->billingcompanyList = $billingcompany_data;
        if ($this->getRequest()->isPost()) {

            $biller_data['billingcompany_id'] = $this->billingcompany_id;
            $biller_data['biller_name'] = $this->getRequest()->getPost('biller_name');
            ;
            $db_biller = new Application_Model_DbTable_Biller();
            $db = $db_biller->getAdapter();
            $reference_id = $db_biller->insert($biller_data);
            $provider_id_array = $this->getRequest()->getPost('provider_id_array');
            $provider_id = array();
            if (strlen($provider_id_array) > 0) {
                $provider_id = explode(',', $provider_id_array);
            }
            $user_data = array();
            $user_data['user_name'] = $this->getRequest()->getPost('biller_name');
            $user_data['role'] = $this->getRequest()->getPost('role');
            $user_data['password'] = md5($this->getRequest()->getPost('password'));
            $user_data['reference_id'] = $reference_id;

            $db_user = new Application_Model_DbTable_User();
            $db = $db_user->getAdapter();
            $user_id = $db_user->insert($user_data);
            if ('guest' == $user_data['role']) {
                $userfocusonprovider['user_id'] = $user_id;
                $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
                $db = $db_userfocusonprovider->getAdapter();
                if (count($provider_id) > 0) {
                    for ($i = 0; $i < count($provider_id); $i++) {
                        $userfocusonprovider['provider_id'] = $provider_id[$i];
                        $db_userfocusonprovider->insert($userfocusonprovider);
                    }
                }
            }

            $this->_redirect('/biller/data/assignment');
        }
    }

    /**
     * userinfoAction
     * a function returning the user data for displaying on the page.
     * @author Haowei.
     * @return the user data for displaying on the page
     * @version 05/15/2012
     */
    public function userinfoAction() {
        $this->_helper->viewRenderer->setNoRender();
        $user_name = $_POST['user_name'];
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('user_name = ?', $user_name);
        $user_data = $db_user->fetchRow($where);

        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $where = $db->quoteInto('biller_name = ?', $user_name);
        $biller_data = $db_biller->fetchRow($where);

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $where = $db->quoteInto('id = ?', $biller_data['billingcompany_id']);
        $billingcompany_data = $db_billingcompany->fetchRow($where);

        $data = array();
        $data = array('billingcompany_name' => $billingcompany_data['billingcompany_name'], 'role' => $user_data['role']);
        $json = Zend_Json::encode($data);
        echo $json;
    }

    function parseISA05_ISA07ByName($str, $type = 'ISA05') {
        if (is_nan($str)) {
            if ($type == 'ISA05')
                return 'ZZ';
            else {
                return '01';
            }
        }
        switch ($str) {
            case 'Duns(Dun & Bradstreet)':
                break;
            case 'Duns Plus Suffix"':
                return '14';
                break;
            case 'Health Industry Number':
                return '20';
                break;
            case 'Carrier ID from HCFA':
                return '27';
                break;
            case 'Fiscal Intermediary ID from HCFA':
                return '28';
                break;
            case 'Medicare ID from HCFA':
                return '29';
                break;
            case 'U.S.Federal Tax ID Number':
                return '30';
                break;
            case 'NAIC Company Code':
                return '33';
                break;
            case 'Mutually Defined':
                return 'ZZ';
                break;
            default:
                break;
        }
    }

    function arraySort(array &$arr, $sortArg, $sortType) {
        if (count($arr) == 0)
            return true;
        foreach ($arr as $key => $value) {
            $temp[$key] = $value[$sortArg];
        }
        $ret = array_multisort($temp, $sortType, $arr);
        return $ret;
    }

    protected function data_format_month($rows, $type) {
        $cur_year = date('Y');
        $cur_month = date('m');
        $data = array();
        //totals:table-1
        $cliams_total = 0;
        $Amount_Billed_total = 0;
        $Amount_Collected_total = 0;
        //totals:table-2
        $Collection_total = 0;
        for ($i = 1; $i <= 12; $i++) {
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
//                        if ($r['Amount_Billed'] == 0)
//                            $r['Amount_Billed'] = '';
//                        else
//                            $r['Amount_Billed'] = '$' . number_format($r['Amount_Billed'], 2);
//                        if ($r['Amount_Collected'] == 0)
//                            $r['Amount_Collected'] = '';
//                        else
//                            $r['Amount_Collected'] = '$' . number_format($r['Amount_Collected'], 2);
                    }
                    if ($type == 1) {
                        $Collection_total = $Collection_total + $r['Collection'];
//                        $r['Collection'] = '$' . number_format($r['Collection'], 2);
                    }
                    break;
                }
            }
            if ($flag == 1)
                array_push($data, $r);
            else {
                if ($type == 0)
                    array_push($data, array(
                        'Month' => $tmp,
                        'Claims' => '',
                        'Amount_Billed' => '',
                        'Amount_Collected' => ''
                    ));
                if ($type == 1)
                    array_push($data, array(
                        'Month' => $tmp,
                        'Collection' => ''
                    ));
            }
            //totals
            if ($i == 12) {
                if ($type == 0) {
                    array_push($data, array(
                        'Month' => 'Totals',
                        'Claims' => $cliams_total,
                        'Amount_Billed' => '$' . number_format($Amount_Billed_total, 2),
                        'Amount_Collected' => '$' . number_format($Amount_Collected_total, 2)
                    ));
                }
                if ($type == 1) {
                    array_push($data, array(
                        'Month' => 'Totals',
                        'Collection' => '$' . number_format($Collection_total, 2)
                    ));
                }
            }
        }


        return $data;
    }

    public function patienttomergeAction() {
        //
        if (!$this->getRequest()->isPost()) {

            // for ()
        }
        if ($this->getRequest()->isPost()) {

            $account_number_1 = $this->getRequest()->getPost('account_number_1');
            $account_number_2 = $this->getRequest()->getPost('account_number_2');
            session_start();

            $_SESSION['duppatient_account_number'] = $account_number_1;
            $_SESSION['keeppatient_account_number'] = $account_number_2;

            $this->_redirect('/biller/data/mergepatient');
        }
    }

    public function mergepatientAction() {
        if (!$this->getRequest()->isPost()) {
            $duppatient_account_number = $_SESSION['duppatient_account_number'];
            $keeppatient_account_number = $_SESSION['keeppatient_account_number'];
            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('patient', array('id', 'last_name', 'first_name', 'date_format(dob, \'%m/%d/%Y\') As dob', 'account_number'));
            $select->where('patient.account_number=?', $duppatient_account_number);
            $duppatientList = $db->fetchAll($select);
            $count = sizeof($duppatientList);
            $_SESSION['duppatient_count'] = $count;
            $_SESSION['duppatientlist'] = $duppatientList;
            $this->view->duppatientList = $duppatientList;

            $select = $db->select();
            $select->from('patient', array('id', 'last_name', 'first_name', 'date_format(dob, \'%m/%d/%Y\') as dob', 'account_number'));

            $select->where('patient.account_number=?', $keeppatient_account_number);
            $keeppatientList = $db->fetchAll($select);
            $count = sizeof($keeppatientList);
            $_SESSION['keeppatient_count'] = $count;
            $_SESSION['keeppatientlist'] = $keeppatientList;
            $this->view->keeppatientList = $keeppatientList;
            if ($keeppatientList[0]['dob'] !== $duppatientList[0]['dob'])
                $this->view->dobdiff = "DOB";
        }
        else {
            //$count = $_SESSION['duppatient_count'];
            $duppatientList = $_SESSION['duppatientlist'];
            $keeppatientList = $_SESSION['keeppatientlist'];
            $duppatient_account_number = $_SESSION['duppatient_account_number'];
            $keeppatient_account_number = $_SESSION['keeppatient_account_number'];

            //$count = sizeof($duppatientList);
            $index = $this->getRequest()->getPost('checkbox_index');
            if ($index === "2") {//Cancel
                $this->_redirect('/biller/data/patienttomerge');
            } else {
                $patientIDToKeep = $keeppatientList[0]['id'];
                $patientIDToMerge = $duppatientList[0]['id'];

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounter', array('encounter.id as encounterIDToMerge'));
                $select->join('encounterinsured', 'encounter.id = encounterinsured.encounter_id', array('type'));
                $select->join('claim', 'encounter.claim_id = claim.id', array('claim.id As claim_id'));
                $select->where('encounter.patient_id=?', $patientIDToMerge);
                //$select->order('insured_id ASC');
                $encounterList = $db->fetchAll($select);
                $encounterCount = sizeof($encounterList);

                if ($encounterCount > 0) {
                    $encounterdb = Zend_Registry::get('dbAdapter');
                    $encounterData = array('patient_id' => $patientIDToKeep);
                    $result = $encounterdb->update('encounter', $encounterData, 'patient_id = ' . $patientIDToMerge);
                    // No update for insured for now   

                    for ($j = 0; $j < $encounterCount; $j++) {
                        //$encounterIDToMerge = $encounterList[$j]['encounterIDToMerge'];                                                                       
                        //$encounterinsureddb = Zend_Registry::get('dbAdapter');
                        //$encounterinsuredData = array('insured_id' => $insuredIDToKeep);
                        //$result = $encounterinsureddb->update('encounterinsured', $encounterinsuredData,  'encounter_id = '.$encounterIDToMerge);
                        //Add log
                        $today = date("Y-m-d H:i:s");
                        $interactionlogs_data['claim_id'] = $encounterList[$j]['claim_id'];
                        $interactionlogs_data['date_and_time'] = $today;
                        $user = Zend_Auth::getInstance()->getIdentity();
                        $user_name = $user->user_name;
                        $interactionlogs_data['log'] = $user_name . ": This claim of patient " . $duppatient_account_number . " has been merged into claims of patient " . $keeppatient_account_number;
                        mysql_insert('interactionlog', $interactionlogs_data);
                    }
                }

                $db_statement = new Application_Model_DbTable_Patient();
                $patientdb = $db_statement->getAdapter();
                $where = $patientdb->quoteInto('id = ?', $patientIDToMerge);
                $temp = $db_statement->delete($where);
                /*
                  $db_statement = new Application_Model_DbTable_Insured();
                  $insureddb = $db_statement->getAdapter();
                  $where = $insureddb->quoteInto('id = ?', $insuredIDToMerge);
                  $temp = $db_statement->delete($where);
                 */
                $this->_redirect('/biller/claims/inquiry');
            }
        }
    }

    public function appealclaimAction() {

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
        $select->join('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
//        //Add the referringprovider
        $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        /// $select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
        $select->where('provider.billingcompany_id=?', $this->billingcompany_id());
        $select->where('claim.claim_status=?', 'open-follow_up_pending_appeal');

        /*         * *New insurance change 2013-03-17** */
        $select->where('encounterinsured.type = ?', 'primary');
        /*         * *New insurance change** */

        /* Add the second insurance not billed <By YuLang> */
        if ($providerList != null) {
            $select->where('provider.id IN(?)', $providerList);
        }

        //$select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
        $patient = $db->fetchAll($select);

        $patientList = array();
        $count = 0;

        foreach ($patient as $row) {

            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('interactionlog');
            $select->where('interactionlog.claim_id=?', $row['claim_id']);
            $select->order('interactionlog.date_and_time DESC');
            $tmp_interactionlogs = $db->fetchAll($select);
            $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
            $start_date = date("Y-m-d");
            $noactiondays = 99;
            if ($end_date != null && $end_date != "") {
                $noactiondays = days($start_date, $end_date);
            } else {
                $temp_days = 99;
                if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_last_billed'], $row['date_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_last_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                    $temp_end_date = max($row['date_billed'], $row['date_last_billed']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                    $temp_end_date = $row['date_rebilled'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] == null) {
                    $temp_end_date = $row['date_last_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                    $temp_end_date = $row['date_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                }
                $noactiondays = $temp_days;
            }
            if ($noactiondays <= 99)
                $patientList[$count]['last'] = $noactiondays;
            else
                $patientList[$count]['last'] = 99;

            $flag = 0;
            if (strtolower($row['claim_status']) == 'open-follow_up_pending_appeal') {
                $flag = 1;
                $patientList[$count]['Comment'] = 'Appeal Generation';
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
                $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                /*                 * ****************Add fields for bill claim lists********************** */

                $patientList[$count]['DOB'] = format($row['DOB'], 1);

//                $patientList[$count]['means'] = strtoupper($row['means']);

                $patientList[$count]['claim_id'] = $row['claim_id'];
                $count = $count + 1;
            }
        }

        $this->view->patientList = $patientList;
        session_start();

        unset($_SESSION['tmp']);
        $_SESSION['tmp']['appealList'] = $patientList;
        //$_SESSION['actionList'] = $taiList;
        $this->_redirect('/biller/data/appeal');
    }

    //
    public function appealAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $patientList = $_SESSION['tmp']['appealList'];
            $this->view->patientList = $patientList;
            $this->view->appeal = $this->appeal;
        }

        if ($this->getRequest()->isPost()) {
            $sec_flag = 0;
            if ($this->getRequest()->getPost('appeal1') != "" || $this->getRequest()->getPost('appeal2') != "" || $this->getRequest()->getPost('appeal3') != "") {
                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $claim_id_string = $this->getRequest()->getPost('claim_id');
                $encounter_id_array = array();
                $claim_id_array = array();
                $options = array();
                $provider_id_array = array();
                $patient_id_array = array();
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
                    $patientList = $_SESSION['tmp']['appealList'];
                    $this->view->patientList = $patientList;
                    $this->view->appeal = $this->appeal;
                    return;
                }


                $today = date("Y-m-d H:i:s");
                $user = Zend_Auth::getInstance()->getIdentity();
                $username = $user->user_name;
                foreach ($claim_id_array as $key => $claim_id) {
                    //get the file path                    
                    $interactionlogs_data['claim_id'] = $claim_id;
                    $interactionlogs_data['date_and_time'] = $today;

                    $interactionlogs_data['log'] = $username . ": Change Status from Open_pending_appeal to inactive-in appeal";
                    mysql_insert('interactionlog', $interactionlogs_data);
                }

                $db = Zend_Registry::get('dbAdapter');
                $db_claim = new Application_Model_DbTable_Claim();

                foreach ($claim_id_array as $key => $claim_id) {

                    $set = array(
                        'claim_status' => 'inactive-in appeal',
                        'date_billed' => date('Y-m-d'));

                    $where = $db->quoteInto('id = ?', $claim_id);
                    $rows_affected = $db_claim->update($set, $where);
                }
                if ($this->getRequest()->getPost('appeal1') != "") {
                    $download_file = $this->generate_appeal_log($encounter_id_array, 1);
                }
                if ($this->getRequest()->getPost('appeal2') != "") {
                    $download_file = $this->generate_appeal_log($encounter_id_array, 2);
                }

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
                $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name', 'provider.short_name AS provider_short_name'));
                $select->join('options', 'options.id = provider.options_id');
                $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                //Add the referringprovider
                $select->join('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
                //        //Add the referringprovider
                $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
                $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

                /*                 * *New insurance change** */
                $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
                $select->join('insured', 'insured.id=encounterinsured.insured_id');
                //$select->join('insured', 'insured.id=patient.insured_id');
                /*                 * *New insurance change** */

                $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
                $select->where('provider.billingcompany_id=?', $this->billingcompany_id());
                $select->where('claim.claim_status=?', 'open-follow_up_pending_appeal');

                /*                 * *New insurance change 2013-03-17** */
                $select->where('encounterinsured.type = ?', 'primary');
                /*                 * *New insurance change** */


                //$select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
                $patient = $db->fetchAll($select);

                $patientList = array();
                $count = 0;

                foreach ($patient as $row) {

                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('interactionlog');
                    $select->where('interactionlog.claim_id=?', $row['claim_id']);
                    $select->order('interactionlog.date_and_time DESC');
                    $tmp_interactionlogs = $db->fetchAll($select);
                    $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                    $start_date = date("Y-m-d");
                    $noactiondays = 99;
                    if ($end_date != null && $end_date != "") {
                        $noactiondays = days($start_date, $end_date);
                    } else {
                        $temp_days = 99;
                        if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_last_billed'], $row['date_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_last_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                            $temp_end_date = max($row['date_billed'], $row['date_last_billed']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                            $temp_end_date = $row['date_rebilled'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] == null) {
                            $temp_end_date = $row['date_last_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                            $temp_end_date = $row['date_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        }
                        $noactiondays = $temp_days;
                    }
                    if ($noactiondays <= 99)
                        $patientList[$count]['last'] = $noactiondays;
                    else
                        $patientList[$count]['last'] = 99;

                    $flag = 0;
                    if (strtolower($row['claim_status']) == 'open-follow_up_pending_appeal') {
                        $flag = 1;
                        $patientList[$count]['Comment'] = 'Appeal Generation';
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

                        /*                         * ****************Add fields for bill claim lists********************** */
                        $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
                        $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
                        $patientList[$count]['total_charge'] = $row['total_charge'];
                        $patientList[$count]['amount_paid'] = $row['amount_paid'];
                        $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                        /*                         * ****************Add fields for bill claim lists********************** */


                        $patientList[$count]['DOB'] = format($row['DOB'], 1);
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['means'] = strtoupper($row['means']);
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['claim_id'] = $row['claim_id'];
                        $count = $count + 1;
                    }
                }

                session_start();

                unset($_SESSION['tmp']);
                $_SESSION['tmp']['appealList'] = $patientList;
                $this->view->patientList = $patientList;
                $this->view->appeal = $this->appeal;
                /*  to add the download filename<PanDazhao> */
                $_SESSION['downloadfilename'] = $download_file;
                unset($download_file);
                $this->_redirect('/biller/data/appeal');
            } else {
                $postType = $this->getRequest()->getParam('post');
                session_start();
                $appealList = $_SESSION['tmp']['appealList'];
                foreach ($appealList as $key => $row) {
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
                    $dob[$key] = $row['DOB'];
                    $amount_paid[$key] = $row['amount_paid'];
                    $referringprovider[$key] = $row['referringprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                }
                /*                 * ***Add fax log check function******** */
                if ($this->getRequest()->getPost('appeal1_log') != "") {

                    $log_file_name = $this->sysdoc_path . '/' . $this->billingcompany_id() . '/appeallog/' . $this->appeal['appeal1'] . '.csv';
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
                    $this->_redirect('/biller/data/appeal');
                } else if ($this->getRequest()->getPost('appeal2_log') != "") {

                    $log_file_name = $this->sysdoc_path . '/' . $this->billingcompany_id() . '/appeallog/' . $this->appeal['appeal2'] . '.csv';
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
                    $this->_redirect('/biller/data/appeal');
                }

                /*                 * **************************Add check log Action************************ */

                if ($postType == "Name") {

                    /*                     * *****************sort the insurance****************** */
                    $patient_slowercase = array_map('strtolower', $patient);
                    array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $appealList);

                    //array_multisort($patient, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "Facility") {
                    array_multisort($facility, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "MRN") {
                    array_multisort($mrn, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "M") {
                    array_multisort($means, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }

                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Charge") {
                    array_multisort($total_charge, SORT_DESC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }

                if ($postType == "Paid") {
                    array_multisort($amount_paid, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }

                if ($postType == "Referring Provider") {
                    array_multisort($referringprovider, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }

                if ($postType == "DOB") {
                    array_multisort($dob, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                /*                 * ************For sorting of Add fields*************** */

                if ($postType == "Comment") {
                    array_multisort($comment, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "DOS") {
                    array_multisort($dos, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "Insurance") {

                    /*                     * *****************sort the insurance****************** */
                    $insurance_slowercase = array_map('strtolower', $insurance);
                    array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $appealList);

                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }
                if ($postType == "Provider") {
                    array_multisort($provider, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
                }

                if ($postType == "Rendering Provider") {
                    array_multisort($renderingprovider, SORT_ASC, $appealList);
                    $_SESSION['tmp']['appealList'] = $appealList;
                    $this->_redirect('/biller/data/appeal');
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

    public function turnoverAction() {

        /* generate the patientList<By Yu Lang> */
        //$this->_helper->viewRenderer->setNoRender();

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
        $select->join('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
//        //Add the referringprovider
        $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid'));
        $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
        $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

        /*         * *New insurance change** */
        $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
        $select->join('insured', 'insured.id=encounterinsured.insured_id');
        /// $select->join('insured', 'insured.id=patient.insured_id');
        /*         * *New insurance change** */

        $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
        $select->where('provider.billingcompany_id=?', $this->billingcompany_id());
        $select->where('claim.claim_status=?', 'open_pending_collection');

        /*         * *New insurance change 2013-03-17** */
        $select->where('encounterinsured.type = ?', 'primary');
        /*         * *New insurance change** */

        /* Add the second insurance not billed <By YuLang> */
        if ($providerList != null) {
            $select->where('provider.id IN(?)', $providerList);
        }

        //$select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
        $patient = $db->fetchAll($select);

        $patientList = array();
        $count = 0;

        foreach ($patient as $row) {

            $db = Zend_Registry::get('dbAdapter');
            $select = $db->select();
            $select->from('interactionlog');
            $select->where('interactionlog.claim_id=?', $row['claim_id']);
            $select->order('interactionlog.date_and_time DESC');
            $tmp_interactionlogs = $db->fetchAll($select);
            $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
            $start_date = date("Y-m-d");
            $noactiondays = 99;
            if ($end_date != null && $end_date != "") {
                $noactiondays = days($start_date, $end_date);
            } else {
                $temp_days = 99;
                if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_last_billed'], $row['date_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_last_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                    $temp_end_date = max($row['date_billed'], $row['date_rebilled']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                    $temp_end_date = max($row['date_billed'], $row['date_last_billed']);
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                    $temp_end_date = $row['date_rebilled'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] == null) {
                    $temp_end_date = $row['date_last_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                    $temp_end_date = $row['date_billed'];
                    $temp_days = days($start_date, $temp_end_date);
                }
                $noactiondays = $temp_days;
            }
            if ($noactiondays <= 99)
                $patientList[$count]['last'] = $noactiondays;
            else
                $patientList[$count]['last'] = 99;

            $flag = 0;
            if (strtolower($row['claim_status']) == 'open_pending_collection') {
                $flag = 1;
                $patientList[$count]['Comment'] = 'Collection';
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
                $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                /*                 * ****************Add fields for bill claim lists********************** */


                $patientList[$count]['DOB'] = format($row['DOB'], 1);

//                $patientList[$count]['means'] = strtoupper($row['means']);

                $patientList[$count]['claim_id'] = $row['claim_id'];
                $count = $count + 1;
            }
        }
        $this->view->patientList = $patientList;
        session_start();

        unset($_SESSION['tmp']);
        $_SESSION['tmp']['turnoverList'] = $patientList;
        //$_SESSION['actionList'] = $taiList;
        $this->_redirect('/biller/data/collection');
    }

    //
    public function collectionAction() {
        if (!$this->getRequest()->isPost()) {
            session_start();
            $patientList = $_SESSION['tmp']['turnoverList'];
            $billingcompany_id = $this->billingcompany_id();
            $coll_dir = $this->sysdoc_path . '/' . $billingcompany_id . '/collectionlog';
            $coll_paths = array();
            $new_path_coll = array();
            if (is_dir($coll_dir)) {
                foreach (glob($coll_dir . '/*.*') as $filename) {
                    array_push($coll_paths, $filename);
                }
            }

            $display = array();
            for ($i = 0; $i < count($coll_paths); $i++) {
                $new_path_coll[$i]['path'] = $coll_paths[$i];
                $coll_paths_array = explode('/', $coll_paths[$i]);
                $new_path_coll[$i]['display'] = $coll_paths_array[count($coll_paths_array) - 1];
                $display[$i] = $new_path_coll[$i]['display'];
            }
            array_multisort($display, SORT_DESC, $new_path_coll);
            $this->view->collList = $new_path_coll;
            $this->view->patientList = $patientList;
        }

        if ($this->getRequest()->isPost()) {
            $sec_flag = 0;
            if ($this->getRequest()->getPost('collection') != "") {
                $encounter_id_string = $this->getRequest()->getPost('encounter_id');
                $claim_id_string = $this->getRequest()->getPost('claim_id');
                $encounter_id_array = array();
                $claim_id_array = array();
                $options = array();
                $provider_id_array = array();
                $patient_id_array = array();
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
                    $patientList = $_SESSION['tmp']['turnoverList'];
                    $this->view->patientList = $patientList;
                    return;
                }


                $today = date("Y-m-d H:i:s");
                $user = Zend_Auth::getInstance()->getIdentity();
                $username = $user->user_name;
                foreach ($claim_id_array as $key => $claim_id) {

                    $interactionlogs_data['claim_id'] = $claim_id;
                    $interactionlogs_data['date_and_time'] = $today;

                    $interactionlogs_data['log'] = $username . ": Bill generation Collection Turnover File";
                    mysql_insert('interactionlog', $interactionlogs_data);
                }

                $files = $this->generate_collection_log($encounter_id_array);

                $csvName = $files['file_path'];
                $db = Zend_Registry::get('dbAdapter');
                $db_claim = new Application_Model_DbTable_Claim();

                foreach ($claim_id_array as $key => $claim_id) {

                    $set = array(
                        'claim_status' => 'inactive_in_collection',
                        'date_billed' => date('Y-m-d'));

                    $where = $db->quoteInto('id = ?', $claim_id);
                    $rows_affected = $db_claim->update($set, $where);
                }

                $db = Zend_Registry::get('dbAdapter');
                $select = $db->select();
                $select->from('encounter', array('start_date_1', 'encounter.id as encounter_id'));
                $select->join('provider', 'provider.id =encounter.provider_id', array('provider.provider_name', 'provider.short_name AS provider_short_name'));
                $select->join('options', 'options.id = provider.options_id');
                $select->join('renderingprovider', 'renderingprovider.id=encounter.renderingprovider_id', array('renderingprovider.last_name as renderingprovider_last_name', 'renderingprovider.first_name as renderingprovider_first_name'));
                //Add the referringprovider
                $select->join('referringprovider', 'referringprovider.id=encounter.referringprovider_id', array('referringprovider.last_name as referringprovider_last_name', 'referringprovider.first_name as referringprovider_first_name'));
                //        //Add the referringprovider
                $select->join('claim', 'claim.id=encounter.claim_id ', array('claim.claim_status AS claim_status', 'claim.date_creation AS date_creation', 'claim.id as claim_id', 'total_charge', 'amount_paid'));
                $select->join('facility', 'facility.id=encounter.facility_id', array('facility_name', 'facility.short_name AS facility_short_name'));
                $select->join('patient', 'patient.id=encounter.patient_id ', array('patient.id as patient_id', 'patient.account_number', 'patient.DOB', 'patient.last_name as p_last_name', 'patient.first_name as p_first_name'));

                /*                 * *New insurance change** */
                $select->join('encounterinsured', 'encounterinsured.encounter_id = encounter.id');
                $select->join('insured', 'insured.id=encounterinsured.insured_id');
                //$select->join('insured', 'insured.id=patient.insured_id');
                /*                 * *New insurance change** */

                $select->join('insurance', 'insurance.id=insured.insurance_id ', array('insurance_display', 'insurance.claim_submission_preference AS means'));
                $select->where('provider.billingcompany_id=?', $this->billingcompany_id());
                $select->where('claim.claim_status=?', 'open_pending_collection');

                /*                 * *New insurance change 2013-03-17** */
                $select->where('encounterinsured.type = ?', 'primary');
                /*                 * *New insurance change** */


                //$select->order(array('encounter.start_date_1', 'patient.last_name', 'patient.first_name', 'patient.DOB'));
                $patient = $db->fetchAll($select);

                $patientList = array();
                $count = 0;

                foreach ($patient as $row) {

                    $db = Zend_Registry::get('dbAdapter');
                    $select = $db->select();
                    $select->from('interactionlog');
                    $select->where('interactionlog.claim_id=?', $row['claim_id']);
                    $select->order('interactionlog.date_and_time DESC');
                    $tmp_interactionlogs = $db->fetchAll($select);
                    $end_date = format($tmp_interactionlogs[0]['date_and_time'], 0);
                    $start_date = date("Y-m-d");
                    $noactiondays = 99;
                    if ($end_date != null && $end_date != "") {
                        $noactiondays = days($start_date, $end_date);
                    } else {
                        $temp_days = 99;
                        if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_last_billed'], $row['date_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_last_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] != null) {
                            $temp_end_date = max($row['date_billed'], $row['date_rebilled']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                            $temp_end_date = max($row['date_billed'], $row['date_last_billed']);
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] == null && $row['date_rebilled'] != null) {
                            $temp_end_date = $row['date_rebilled'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] != null && $row['date_billed'] == null && $row['date_rebilled'] == null) {
                            $temp_end_date = $row['date_last_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        } else if ($row['date_last_billed'] == null && $row['date_billed'] != null && $row['date_rebilled'] == null) {
                            $temp_end_date = $row['date_billed'];
                            $temp_days = days($start_date, $temp_end_date);
                        }
                        $noactiondays = $temp_days;
                    }
                    if ($noactiondays <= 99)
                        $patientList[$count]['last'] = $noactiondays;
                    else
                        $patientList[$count]['last'] = 99;

                    $flag = 0;
                    if (strtolower($row['claim_status']) == 'open_pending_collection') {
                        $flag = 1;
                        $patientList[$count]['Comment'] = 'Collection';
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

                        /*                         * ****************Add fields for bill claim lists********************** */
                        $patientList[$count]['provider_short_name'] = $row['provider_short_name'];
                        $patientList[$count]['facility_short_name'] = $row['facility_short_name'];
                        $patientList[$count]['total_charge'] = $row['total_charge'];
                        $patientList[$count]['amount_paid'] = $row['amount_paid'];
                        $patientList[$count]['referringprovider_name'] = $row['referringprovider_last_name'] . ', ' . $row['referringprovider_first_name'];
                        /*                         * ****************Add fields for bill claim lists********************** */


                        $patientList[$count]['DOB'] = format($row['DOB'], 1);
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['means'] = strtoupper($row['means']);
                        /*                         * ************************Change to upper string By Yu Lang 2012-08-09****************************** */
                        $patientList[$count]['claim_id'] = $row['claim_id'];
                        $count = $count + 1;
                    }
                }

                session_start();

                unset($_SESSION['tmp']);
                $_SESSION['tmp']['turnoverList'] = $patientList;
                $this->view->patientList = $patientList;
                /*  to add the download filename<PanDazhao> */
                $_SESSION['downloadfilename'] = $csvName;
                // unset($zipName);
                $this->_redirect('/biller/data/collection');
            } else {
                $postType = $this->getRequest()->getParam('post');
                session_start();
                $turnoverList = $_SESSION['tmp']['turnoverList'];
                foreach ($turnoverList as $key => $row) {
                    $patient[$key] = $row['name'];
                    $insurance[$key] = $row['insurance_display'];
                    $provider[$key] = $row['provider_name'];
                    $facility[$key] = $row['facility_name'];
                    $mrn[$key] = $row['MRN'];
                    $dos[$key] = format($row['start_date_1'], 0);
                    $means[$key] = $row['means'];
                    $comment[$key] = $row['Comment'];
                    $renderingprovider[$key] = $row['renderingprovider_name'];
                    /*                     * ************For sorting of Add fields*************** */
                    $total_charge[$key] = $row['total_charge'];
                    $dob[$key] = format($row['DOB'], 0);
                    $amount_paid[$key] = $row['amount_paid'];
                    $referringprovider[$key] = $row['referringprovider_name'];
                    $last[$key] = $row['last'];
                    /*                     * ************For sorting of Add fields*************** */
                }
                /*                 * ***Add fax log check function******** */
                if ($this->getRequest()->getPost('collection_log') != "") {

                    $log_file_name = $this->sysdoc_path . '/' . $this->billingcompany_id() . '/collectionlog/TurnOver.csv';
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
                    $this->_redirect('/biller/data/collection');
                }

                /*                 * **************************Add check log Action************************ */

                if ($postType == "Name") {

                    /*                     * *****************sort the insurance****************** */
                    $patient_slowercase = array_map('strtolower', $patient);
                    if ($patient_slowercase[0] >= $patient_slowercase[sizeof($patient_slowercase) - 1])
                        array_multisort($patient_slowercase, SORT_ASC, SORT_STRING, $turnoverList);
                    else
                        array_multisort($patient_slowercase, SORT_DESC, SORT_STRING, $turnoverList);

                    //array_multisort($patient, SORT_ASC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "Facility") {
                    if ($facility[0] >= $facility[sizeof($facility) - 1])
                        array_multisort($facility, SORT_ASC, $turnoverList);
                    else
                        array_multisort($facility, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "MRN") {
                    if ($mrn[0] >= $mrn[sizeof($mrn) - 1])
                        array_multisort($mrn, SORT_ASC, $turnoverList);
                    else
                        array_multisort($mrn, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "M") {
                    if ($means[0] >= $means[sizeof($means) - 1])
                        array_multisort($means, SORT_ASC, $turnoverList);
                    else
                        array_multisort($means, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }

                /*                 * ************For sorting of Add fields*************** */
                if ($postType == "Charge") {
                    if ($total_charge[0] >= $total_charge[sizeof($total_charge) - 1])
                        array_multisort($total_charge, SORT_ASC, $turnoverList);
                    else
                        array_multisort($total_charge, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }

                if ($postType == "Paid") {
                    if ($amount_paid[0] >= $amount_paid[sizeof($amount_paid) - 1])
                        array_multisort($amount_paid, SORT_ASC, $turnoverList);
                    else
                        array_multisort($amount_paid, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }

                if ($postType == "Referring Provider") {
                    if ($referringprovider[0] >= $referringprovider[sizeof($referringprovider) - 1])
                        array_multisort($referringprovider, SORT_ASC, $turnoverList);
                    else
                        array_multisort($referringprovider, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }

                if ($postType == "DOB") {
                    if ($dob[0] >= $dob[sizeof($dob) - 1])
                        array_multisort($dob, SORT_ASC, $turnoverList);
                    else
                        array_multisort($dob, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                /*                 * ************For sorting of Add fields*************** */

                if ($postType == "Comment") {
                    if ($comment[0] >= $comment[sizeof($comment) - 1])
                        array_multisort($comment, SORT_ASC, $turnoverList);
                    else
                        array_multisort($comment, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "DOS") {
                    if ($dos[0] >= $dos[sizeof($dos) - 1])
                        array_multisort($dos, SORT_ASC, $turnoverList);
                    else
                        array_multisort($dos, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "Insurance") {

                    /*                     * *****************sort the insurance****************** */
                    $insurance_slowercase = array_map('strtolower', $insurance);
                    if ($insurance_slowercase[0] >= $insurance_slowercase[sizeof($insurance_slowercase) - 1])
                        array_multisort($insurance_slowercase, SORT_ASC, SORT_STRING, $turnoverList);
                    else
                        array_multisort($insurance_slowercase, SORT_DESC, SORT_STRING, $turnoverList);

                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "Provider") {
                    if ($provider[0] >= $provider[sizeof($provider) - 1])
                        array_multisort($provider, SORT_ASC, $turnoverList);
                    else
                        array_multisort($provider, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }

                if ($postType == "Rendering Provider") {
                    if ($renderingprovider[0] >= $renderingprovider[sizeof($renderingprovider) - 1])
                        array_multisort($renderingprovider, SORT_ASC, $turnoverList);
                    else
                        array_multisort($renderingprovider, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "Last") {
                    if ($last[0] >= $last[sizeof($last) - 1])
                        array_multisort($last, SORT_ASC, $turnoverList);
                    else
                        array_multisort($last, SORT_DESC, $turnoverList);
                    $_SESSION['tmp']['turnoverList'] = $turnoverList;
                    $this->_redirect('/biller/data/collection');
                }
                if ($postType == "Open Turnover") {
                    $filename = $this->getRequest()->getPost('coll_dir');
                    $_SESSION['downloadfilename'] = $filename;
                    $this->_redirect('/biller/data/collection');
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

    function generate_data_log($data) {

        return;
    }

    function getDocPara($temp, $path) {
        $doc = array();
        $data_time = $temp[0];
        $year = substr($data_time, 0, 4);
        $month = substr($data_time, 4, 2);
        $day = substr($data_time, 6, 2);
        $hour = substr($data_time, 8, 2);
        $min = substr($data_time, 10, 2);
        $sec = substr($data_time, 12, 2);
        $date = $month . "/" . $day . "/" . $year . "  " . $hour . ":" . $min . ":" . $sec;
        $doc['date'] = $date;
        $doc['desc'] = $temp[1];
        $doc['user'] = $temp[2];
        $count = count($temp);
        $n = 3;
        if ($count > 3) {
            for ($n; $n < $count; $n++) {
                $doc['user'] = $doc['user'] . '-' . $temp[$n];
            }
        }
        $doc['url'] = $path;
        return $doc;
    }

    function generate_appeal_log($encount_array, $type) {
        /*         * ************************Generate Log file************************** */
        $data = array();
        $fields = array('num', 'name', 'dob', 'dos', 'address', 'phone', 'phone 2', 'ssn', 'rpn', 'pn', 'date');
        $display_fields = array('Num', 'Name', 'DOB', 'DOS', 'Address', 'PhoneNumber', 'PhoneNumber2', 'SSN', 'RenderingProviderName', 'ProviderName', 'Date');
        /*         * ************************Generate Log file************************** */
        $data2 = array();
        $fields2 = array('num', 'date', 'time', 'name', 'mrn', 'dos');
        $display_fields2 = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS');
        $index = 0;
        $claim_pdf_paths = array();
        $user = Zend_Auth::getInstance()->getIdentity();
        $user_name = $user->user_name;
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

            //the provider
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('id = ?', $encounter_db['provider_id']);
            $provider_db = $db_provider->fetchRow($where);

            //the renderingprovider
            $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
            $db = $db_renderingprovider->getAdapter();
            $where = $db->quoteInto('id = ?', $encounter_db['renderingprovider_id']);
            $renderingprovider_db = $db_renderingprovider->fetchRow($where);

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
            $data[$index]['name'] = "\"" . $patient_db['last_name'] . ', ' . $patient_db['first_name'] . "\"";
            $data[$index]['dob'] = format($patient_db['DOB'], 1);
            $data[$index]['dos'] = $encounter_db['start_date_1'];
            $data[$index]['address'] = "\"" . $patient_db['street_address'] . ', ' . $patient_db['city'] . ', ' . $patient_db['state'] . "\"";
            $data[$index]['phone'] = $patient_db['phone_number'];
            $data[$index]['phone 2'] = $patient_db['second_phone_number'];
            $data[$index]['ssn'] = "=\"" . $patient_db['SSN'] . "\"";
            $data[$index]['rpn'] = "\"" . $renderingprovider_db['last_name'] . ', ' . $renderingprovider_db['first_name'] . "\"";
            $data[$index]['pn'] = $provider_db['provider_name'];
            $data[$index]['date'] = date('m/d/Y');

            $data2[$index]['num'] = $index + 1;
            $data2[$index]['date'] = date('m/d/Y');
            $data2[$index]['time'] = date('H:i', time());
            $data2[$index]['name'] = "\"" . $patient_db['last_name'] . ', ' . $patient_db['first_name'] . "\"";
            $data2[$index]['mrn'] = "=\"" . $patient_db['account_number'] . "\"";
            $data2[$index]['dos'] = $encounter_db['start_date_1'];

            $index++;

            $appeal = new appeal($provider_db, $patient_db, $insured_db, $insurance_db, $encounter_db, $claim_db, $this->billingcompany_id());
            if ($this->billingcompany_id() == 1) {
                if ($type == 1) {
                    $pdf = $appeal->gen_PIPAppeal(1);
                } else if ($type == 2) {
                    $pdf = $appeal->gen_PIPFeeAppeal(1);
                }
            }
            $doc_paths = get_docfile_paths($tp_claim_id);
            $doc_dir = $doc_paths['claim_doc_path'];
            if (!is_dir($doc_dir)) {
                mkdir($doc_dir);
            }

            if ($type == 1) {
                $appealValue = $this->appeal['appeal1'];
            } else if ($type == 2) {
                $appealValue = $this->appeal['appeal2'];
            }
            $filename = $doc_dir . '/' . date("YmdHis") . '-' . $appealValue . '-Insurance-' . $user_name . '.pdf';

            $pdf->Output($filename);
            $claim_pdf_paths[] = $filename;
        }

        /*         * **********************Get log dir and log file name*********************** */

        $log_dir = $this->sysdoc_path . '/' . $this->billingcompany_id();
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $log_dir = $log_dir . '/appeallog';
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        //first button download csv/changing
        $time = date("YmdHis");
        $pdf_file = $log_dir . '/' . $time . '.pdf';
        //generate pdf
        if (sizeof($claim_pdf_paths) > 0) {
            $pdfMerged = new Zend_Pdf();
            for ($i = 0; $i < sizeof($claim_pdf_paths); $i++) {
                $pdf = Zend_Pdf::load($claim_pdf_paths[$i]);
                foreach ($pdf->pages as $page) {
                    $clonedPage = clone $page;
                    $pdfMerged->pages[] = $clonedPage;
                }
                unset($clonedPage);
            }
            $pdfMerged->save($pdf_file);
        }
        //$log_file_name = $log_dir . '/'.$time.'.csv';
        //second button download csv
        if ($type == 1) {
            $log_file_name2 = $log_dir . '/' . $this->appeal['appeal1'] . '.csv';
        } else if ($type == 2) {
            $log_file_name2 = $log_dir . '/' . $this->appeal['appeal2'] . '.csv';
        }

        /*         * the second button's log file name***** */
        $final_length2 = sizeof($fields2);

        if (file_exists($log_file_name2)) {
            $rp = fopen($log_file_name2, 'r');
            $tmp_log_file = $log_dir . '/tmp.csv';
            $wp = fopen($tmp_log_file, 'w');

            for ($i = 0; $i < $final_length2; $i++) {
                fwrite($wp, $display_fields2[$i] . ",");
            }
            fwrite($wp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length2; $j++) {

                    fwrite($wp, $data2[$i][$fields2[$j]] . ",");
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

            if (!unlink($log_file_name2))
                echo ("Error deleting $log_file_name2");
            else
                echo ("Deleted $log_file_name2");

            rename($tmp_log_file, $log_file_name2);
        }
        else {//if not exists
            $fp = fopen($log_file_name2, 'w');

            for ($i = 0; $i < $final_length2; $i++) {
                fwrite($fp, $display_fields2[$i] . ",");
            }
            fwrite($fp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length2; $j++) {
//                     $t="\t{$data2[$i][$fields2[$j]]}";
//                              $ttt = '"'.$t.'"';
//                              
//                              fwrite($fp,$ttt. ",");
                    fwrite($fp, $data2[$i][$fields2[$j]] . ",");
                }
                fwrite($fp, "\r\n");
            }
            fclose($fp);
        }
        return $pdf_file;
    }

    //generate the csv file of collection turnover
    function generate_collection_log($encount_array) {
        /*         * ************************Generate Log file************************** */
        $data = array();


        $fields = array('num', 'name', 'dob', 'dos', 'address', 'phone', 'phone 2', 'ssn', 'gname', 'gdob', 'gaddress', 'gphone', 'gphone 2', 'gssn', 'rpn', 'pn', 'date', 'notes');
        $display_fields = array('Num', 'Name', 'DOB', 'DOS', 'Address', 'PhoneNumber', 'PhoneNumber2', 'SSN', 'G Name', 'G DOB', 'G Address', 'G PhoneNumber', 'G PhoneNumber2', 'G SSN', 'RenderingProviderName', 'ProviderName', 'Turnover Date', 'Notes');

//           $fields = array('num', 'name', 'dob', 'dos', 'address', 'phone', 'phone 2', 'ssn', 'rpn', 'pn', 'date', 'notes', 'gname', 'gdob', 'gaddress', 'gphone', 'gphone 2', 'gssn');
//        $display_fields = array('Num', 'Name', 'DOB', 'DOS', 'Address', 'PhoneNumber', 'PhoneNumber2', 'SSN', 'RenderingProviderName', 'ProviderName', 'Turnover Date', 'Notes', 'GName', 'GDOB', 'GAddress', 'GPhoneNumber', 'GPhoneNumber2', 'GSSN');
        /*         * ************************Generate Log file************************** */
        $data2 = array();
        $fields2 = array('num', 'date', 'time', 'name', 'mrn', 'dos');
        $display_fields2 = array('Num', 'Date', 'Time', 'Name', 'MRN', 'DOS');
        $index = 0;
        $claim_pdf_paths = array();
        foreach ($encount_array as $en_id) {
            $guarantor_flag = false;
            $db_encount = new Application_Model_DbTable_Encounter();
            $db = $db_encount->getAdapter();
            $where = $db->quoteInto('id = ?', $en_id);
            $encounter_db = $db_encount->fetchRow($where);
            $tp_patient_id = $encounter_db['patient_id'];
            $tp_encounter_id = $encounter_db['id'];
            $tp_claim_id = $encounter_db['claim_id'];

            $db_claim = new Application_Model_DbTable_Claim();
            $db = $db_claim->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_claim_id);
            $claim_db = $db_claim->fetchRow($where);

            //get guarantor info
            $tp_guarantor_id = $claim_db['guarantor_id'];
            $db_guarantor = new Application_Model_DbTable_Guarantor();
            $db = $db_guarantor->getAdapter();
            $where = $db->quoteInto('id= ?', $tp_guarantor_id); //quoteInto 查找信息
            $guarantor_db = $db_guarantor->fetchRow($where);
            if (count($guarantor_db) != 0) {
                $guarantor_flag = true;
            }

            $db_patient = new Application_Model_DbTable_Patient();
            $db = $db_patient->getAdapter();
            $where = $db->quoteInto('id = ?', $tp_patient_id);
            $patient_db = $db_patient->fetchRow($where);

            //the provider
            $db_provider = new Application_Model_DbTable_Provider();
            $db = $db_provider->getAdapter();
            $where = $db->quoteInto('id = ?', $encounter_db['provider_id']);
            $provider_db = $db_provider->fetchRow($where);

            //the renderingprovider
            $db_renderingprovider = new Application_Model_DbTable_Renderingprovider();
            $db = $db_renderingprovider->getAdapter();
            $where = $db->quoteInto('id = ?', $encounter_db['renderingprovider_id']);
            $renderingprovider_db = $db_renderingprovider->fetchRow($where);

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
            $data[$index]['name'] = "\"" . $patient_db['last_name'] . ', ' . $patient_db['first_name'] . "\"";
            $data[$index]['dob'] = format($patient_db['DOB'], 1);
            $data[$index]['dos'] = $encounter_db['start_date_1'];
            $data[$index]['address'] = "\"" . $patient_db['street_address'] . ', ' . $patient_db['city'] . ', ' . $patient_db['state'] . " " . $patient_db['zip'] . "\"";
            $substrphone = $patient_db['phone_number'];
            $data[$index]['phone'] = "\"(" . substr($substrphone, 0, 3) . ")" . substr($substrphone, 3, 3) . "-" . substr($substrphone, 6, 4) . "\"";
            $substrphone_two = $patient_db['second_phone_number'];
            $data[$index]['phone 2'] == "\"(" . substr($substrphone_two, 0, 3) . ")" . substr($substrphone_two, 3, 3) . "-" . substr($substrphone_two, 6, 4) . "\"";
            $ssnsbstr = $patient_db['SSN'];
            $data[$index]['ssn'] = "=\"" . substr($ssnsbstr, 0, 3) . "-" . substr($ssnsbstr, 3, 2) . "-" . substr($ssnsbstr, 5, 4) . "\"";


            if ($guarantor_flag) {
                //添加担保人信息
                $data[$index]['gname'] = "\"" . $guarantor_db['last_name'] . ', ' . $guarantor_db['first_name'] . "\"";
                $data[$index]['gdob'] = format($guarantor_db['DOB'], 1);
                $data[$index]['gaddress'] = "\"" . $guarantor_db['street_address'] . ', ' . $guarantor_db['city'] . ', ' . $guarantor_db['state'] . " " . $guarantor_db['zip'] . "\"";
                $substrphone = $guarantor_db['phone_number'];
                $data[$index]['gphone'] = "\"(" . substr($substrphone, 0, 3) . ")" . substr($substrphone, 3, 3) . "-" . substr($substrphone, 6, 4) . "\"";
                $substrphone_two = $guarantor_db['second_phone_number'];
                $data[$index]['gphone 2'] == "\"(" . substr($substrphone_two, 0, 3) . ")" . substr($substrphone_two, 3, 3) . "-" . substr($substrphone_two, 6, 4) . "\"";
                $ssnsbstr = $guarantor_db['SSN'];
                $data[$index]['gssn'] = "=\"" . substr($ssnsbstr, 0, 3) . "-" . substr($ssnsbstr, 3, 2) . "-" . substr($ssnsbstr, 5, 4) . "\"";
            }

            $data[$index]['rpn'] = "\"" . $renderingprovider_db['last_name'] . ', ' . $renderingprovider_db['first_name'] . "\"";
            $data[$index]['pn'] = $provider_db['provider_name'];
            $data[$index]['date'] = date('m/d/Y');
            $data[$index]['notes'] = "\"" . $patient_db['notes'] . "\"";


            $data2[$index]['num'] = $index + 1;
            $data2[$index]['date'] = date('m/d/Y');
            $data2[$index]['time'] = date('H:i', time());
            $data2[$index]['name'] = "\"" . $patient_db['last_name'] . ', ' . $patient_db['first_name'] . "\"";
            $data2[$index]['mrn'] = "=\"" . $patient_db['account_number'] . "\"";
            $data2[$index]['dos'] = $encounter_db['start_date_1'];

            //claim pdfs dir
            $dir = $this->sysdoc_path . '/' . $this->billingcompany_id() . '/' . $encounter_db['provider_id'] . '/claim' . '/' . $tp_claim_id;
            //get claim pdf filename
            if (is_dir($dir) && is_readable($dir)) {
                $handle = opendir($dir);
                while (false !== ($filename = readdir($handle))) {
                    $file_path = $filename;
                }
                closedir($header);
            }/* else{
              exit('no StatementIII file generated for '.$data[$index]['name'].'!');
              } */
            if (!empty($file_path)) {
                $claim_pdf_paths[] = $dir . '/' . $file_path;
            }
            unset($file_path);
            $index++;
        }

        /*         * **********************Get log dir and log file name*********************** */

        $log_dir = $this->sysdoc_path . '/' . $this->billingcompany_id();
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        $log_dir = $log_dir . '/collectionlog';
        if (!is_dir($log_dir)) {
            mkdir($log_dir);
        }

        //first button download csv/changing
        $time = date("YmdHis");
        //generate pdf
        if (sizeof($claim_pdf_paths) > 0) {
            $pdfMerged = new Zend_Pdf();
            for ($i = 0; $i < sizeof($claim_pdf_paths); $i++) {
                $pdf = Zend_Pdf::load($claim_pdf_paths[$i]);
                foreach ($pdf->pages as $page) {
                    $clonedPage = clone $page;
                    $pdfMerged->pages[] = $clonedPage;
                }
                unset($clonedPage);
            }
            $pdfMerged->save($log_dir . '/' . $time . '.pdf');
        }
        $log_file_name = $log_dir . '/' . $time . '.csv';
        //second button download csv
        $log_file_name2 = $log_dir . '/TurnOver.csv';

        $final_length = sizeof($fields);
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
        /*         * the second button's log file name***** */
        $final_length2 = sizeof($fields2);

        if (file_exists($log_file_name2)) {
            $rp = fopen($log_file_name2, 'r');
            $tmp_log_file = $log_dir . '/tmp.csv';
            $wp = fopen($tmp_log_file, 'w');

            for ($i = 0; $i < $final_length2; $i++) {
                fwrite($wp, $display_fields2[$i] . ",");
            }
            fwrite($wp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length2; $j++) {

                    fwrite($wp, $data2[$i][$fields2[$j]] . ",");
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

            if (!unlink($log_file_name2))
                echo ("Error deleting $log_file_name2");
            else
                echo ("Deleted $log_file_name2");

            rename($tmp_log_file, $log_file_name2);
        }
        else {//if not exists
            $fp = fopen($log_file_name2, 'w');

            for ($i = 0; $i < $final_length2; $i++) {
                fwrite($fp, $display_fields2[$i] . ",");
            }
            fwrite($fp, "\r\n");

            for ($i = 0; $i < $index; $i++) {
                for ($j = 0; $j < $final_length2; $j++) {
                    ;
                    fwrite($fp, $data2[$i][$fields2[$j]] . ",");
                }
                fwrite($fp, "\r\n");
            }
            fclose($fp);
        }


        //return $log_file_name;
        $files = array("file_path" => $log_file_name, "file_name" => $time . '.csv');
        return $files;
    }

    public function downloadAction() {
        //   $this->_helper->viewRenderer->setNoRender();
        session_start();
        $filename = $_SESSION['downloadfilename'];
        if (count($filename) > 0) {
            $hail_zip = substr($filename, 5);
            $filename = $config->dir->document_root . $hail_zip;
            echo $filename;
            unset($_SESSION['downloadfilename']);

            exit;
        }
    }

    public function cmsadjustmentAction() {
        if (!$this->getRequest()->isPost()) {
            $billingcompany_id = $this->billingcompany_id();
            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $where = $db->quoteInto("id=?", $billingcompany_id);
            $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
            $offsets = explode("|", $billingcompany_data["cms1500_offset_p1"]);
            $this->view->offsets = $offsets;
        }
        if ($this->getRequest()->isPost()) {
            $offset_x = $this->getRequest()->getPost("offsetx");
            $offset_y = $this->getRequest()->getPost("offsety");
            $offset = $offset_x . "|" . $offset_y;
            $billingcompany_id = $this->billingcompany_id();
            $db_billingcompany = new Application_Model_DbTable_Billingcompany();
            $db = $db_billingcompany->getAdapter();
            $where = $db->quoteInto("id=?", $billingcompany_id);
            $billingcompany_data = $db_billingcompany->fetchRow($where)->toArray();
            $offset_bk = $billingcompany_data["cms1500_offset_p1"];
            if ($offset != $offset_bk) {
                $change["cms1500_offset_p1"] = $offset;
                $db_billingcompany->update($change, $where);
            }
            $this->_redirect('/biller/data/cmsadjustment');
        }
    }

}
