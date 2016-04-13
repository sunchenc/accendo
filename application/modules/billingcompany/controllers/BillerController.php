<?php

require_once 'helper.php';

class Billingcompany_BillerController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
    }

    public function indexAction() {
        // action body
    }

    public function addbillerAction() {
        // action body
        if ($this->getRequest()->isPost()) {
            $password = md5(filter($this->getRequest()->getPost('password')));
            $pwd_confirm = md5(filter($this->getRequest()->getPost('pwd_confirm')));
            if (!is_pwd_validate($password, $pwd_confirm))   //The two new password are not the same.
                return;

            $db_user = new Application_Model_DbTable_User();
            $fields = $db_user->getFields();
            $user_data = get_post_value($fields, $this->getRequest());
            if ($db_user->is_user_exist($user_data['user_name'])) {             //user exist
                return;
            }
            $user_data['password'] = md5($user_data['password']);
            $user_data['role'] = 'biller';
            $user_data['register_time'] = date("Y-m-d");

            $db_biller = new Application_Model_DbTable_Biller();
            $fields = $db_biller->getFields();
            $biller_data = get_post_value($fields, $this->getRequest());
            $auth = Zend_Auth::getInstance();
            $cur_user = $auth->getIdentity();
            $biller_data['billingcompany_id'] = $cur_user->reference_id;
            $user_data['reference_id'] = $db_biller->insert($biller_data);

            if ($db_user->insert($user_data)) {
                $this->_redirect('billingcompany/index/main');
            }
        }
    }

    public function managebillerAction() {
        $params = $this->getRequest()->getParams();
        $pageno = (int) $params['pageno'];
        if ($pageno == 0)
            $pageno = 1;
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('role = ?', 'biller');
        $pageCount = $db_user->getPageCount($where);
        $this->view->pageCount = $pageCount;
        $order = 'user_name';
        $rowset = $db_user->fetchByPage($where, $order, $pageno);
        $this->view->users = $rowset;
    }

    public function deletebillerAction() {
        // action body
        $params = $this->getRequest()->getParams();
        $id = (int) $params['id'];
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $db_user->delete($where);
        $this->_redirect('billingcompany/biller/managebiller');
    }

}

