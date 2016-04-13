<?php

require_once 'helper.php';

class Admin_UserController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
    }

    public function indexAction() {
        // action body
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
            //password change Successfully
            if ($db_user->change_password($cur_user->user_name, $new_pwd)) {
                $cur_user->password = $new_pwd;
                $auth->getStorage()->write($cur_user);   //restore new password to storage
                $this->_redirect('admin/index/main');
            }
        }
    }

    public function adduserAction() {
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
            $user_data['role']='billingcompany';
            $user_data['password'] = md5($user_data['password']);
         //   $user_data['reference_id'] = 1; //tmp value,if create table admin,this value will be changed
            $user_data['register_time'] = date("Y-m-d");
        //    if ('billingcompany' == $user_data['role']) {
                $db_billingcompany = new Application_Model_DbTable_Billingcompany();
                $fields = $db_billingcompany->getFields();
                $billingcompany_data = get_post_value($fields, $this->getRequest());
                $user_data['reference_id'] = $db_billingcompany->insert($billingcompany_data);
       //     }
            if ($db_user->insert($user_data)) {
                $this->_redirect('admin/index/main');
            }
        }
    }

    public function manageuserAction() {
        $params = $this->getRequest()->getParams();
        $pageno = (int) $params['pageno'];
        if ($pageno == 0)
            $pageno = 1;
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('role = ?', 'billingcompany');
        $pageCount = $db_user->getPageCount($where);
        $this->view->pageCount = $pageCount;
        $order = 'user_name';
        $rowset = $db_user->fetchByPage($where, $order, $pageno);
        $this->view->users = $rowset;
    }

    public function deleteAction() {
        $params = $this->getRequest()->getParams();
        $id = (int) $params['id'];
        $db_user = new Application_Model_DbTable_User();
        $db = $db_user->getAdapter();
        $where = $db->quoteInto('id = ?', $id);
        $db_user->delete($where);
               $this->_redirect('admin/user/manageuser');
    }

}

