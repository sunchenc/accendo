<?php

require_once 'helper.php';

class Billingcompany_CompanyController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
    }

    public function indexAction() {
        // action body
    }

    public function updatecompanyAction() {
        // action body
        $db_billingcompany = new Application_Model_DbTable_Billingcompany();
        $fields = $db_billingcompany->getFields();
        $auth = Zend_Auth::getInstance();
        $cur_user = $auth->getIdentity();
        $rowset = $db_billingcompany->find($cur_user->reference_id);
        $data = rowsetToArray($fields, $rowset);
        $this->view->assign('fields', $fields);
        $this->view->assign('data', $data[0]);
        if ($this->getRequest()->isPost()) {
            $data = get_post_value($fields, $this->getRequest());
            $rows_affected=$db_billingcompany->update($data, array('id=?' => $cur_user->reference_id));
            if($rows_affected>0)
            {
                echo 'successful';
                $this->_redirect('billingcompany/index/main');
            }
        }
    }

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
                $this->_redirect('billingcompany/index/main');
            }
        }
    }

}

