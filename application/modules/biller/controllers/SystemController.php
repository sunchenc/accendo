<?php

require_once 'gen_report.php';
require_once 'gen_report.php';
require_once 'helper.php';
class Biller_SystemController extends Zend_Controller_Action {

    public function init() {
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
        $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
        $this->document_root = '../../' . $config->dir->document_root;
    }

    public function indexAction() {
        // action body
    }

    public function reportsAction() {
        // action body
        $user = Zend_Auth::getInstance()->getIdentity();

        $db_biller = new Application_Model_DbTable_Biller();
        $db = $db_biller->getAdapter();
        $where = $db->quoteInto('biller_name = ?', $user->user_name);
        $biller_data = $db_biller->fetchRow($where);
        $billingcompany_id = $biller_data['billingcompany_id'];
        $db = Zend_Registry::get('dbAdapter');

        $select = $db->select();
        $select->from('provider');
        $select->where('provider.billingcompany_id = ?', $billingcompany_id);
        $providerList = $db->fetchAll($select);

        $this->view->providerList = $providerList;
        if ($this->getRequest()->isPost()) {
            $provider_id = $this->getRequest()->getPost('provider_id');
            gen_report($provider_id, $this->document_root);
        }
    }

    public function assignmentAction() {
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $user = $db_user->fetchAll(null, 'user_name ASC');
        $this->view->billerList = $user;

        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $billingcompany_data = $db_billingcompany->fetchAll(null, 'billingcompany_name ASC');
        $this->view->billingcompanyList = $billingcompany_data;

        $this->getRequest()->isPost();
        if ($this->getRequest()->isPost()) {
            $user_data = array();
            $user_name = $this->getRequest()->getPost('user_name');
            $user_data['role'] = $this->getRequest()->getPost('role');

            $db_user = new Application_Model_DbTable_User();
            $db = $db_user->getAdapter();
            $where = $db->quoteInto('user_name = ?', $user_name);
            $db_user->update($user_data, $where);


            $billingcompany_name = $this->getRequest()->getPost('billingcompany_name');
            $this->_redirect('/biller/system/assignment');
        }
    }

    public function newassignmentAction() {
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $db = $db_billingcompany->getAdapter();
        $billingcompany_data = $db_billingcompany->fetchAll(null, 'billingcompany_name ASC');
        $this->view->billingcompanyList = $billingcompany_data;

        if ($this->getRequest()->isPost()) {

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

         $db_userfocusonprovider = new Application_Model_DbTable_Userfocusonprovider();
         $db = $db_userfocusonprovider->getAdapter();
         $where = $db->quoteInto('user_id = ?', $user_data['id']);
         $userfocusonprovider = $db_userfocusonprovider->fetchAll($where)->toArray();
        
        $data = array();
        $data = array('billingcompany_name' => $billingcompany_data['billingcompany_name'], 'role' => $user_data['role']);
        $data['userfocusonprovider'] = $userfocusonprovider;
        $json = Zend_Json::encode($data);
        echo $json;
    }
    /**
     * passwordAction
     * a function for processing the password.
     * @author Qiaoxinwang.
     * @version 05/15/2012
     */
    public function passwordAction() {
        // action body
        if ($this->getRequest()->isPost()) {
            $old_pwd = md5(filter($this->getRequest()->getPost('old_pwd')));
            $new_pwd = md5(filter($this->getRequest()->getPost('new_pwd')));
            $new_pwd_confirm = md5(filter($this->getRequest()->getPost('new_pwd_confirm')));
            if (!is_pwd_validate($new_pwd, $new_pwd_confirm))   //The two new password are not the same.
                return;
            $auth = Zend_Auth::getInstance();
            $cur_user = $auth->getIdentity();
            if (!is_pwd_validate($old_pwd, $cur_user->password)) //old password input is not the same as the one in storage
                return;
        }
        if ($new_pwd != '') {
            $db_user = new Application_Model_DbTable_User();
            //password change successfully
            if ($db_user->change_password($cur_user->user_name, $new_pwd)) {
                $cur_user->password = $new_pwd;
                $auth->getStorage()->write($cur_user);   //restore new password to storage
                        echo "<script language='JavaScript' type='text/javascript'>
		alert('change password success!');
		</script>";
                // $this->_redirect('admin/index/main');
            }
        }
    }

}

