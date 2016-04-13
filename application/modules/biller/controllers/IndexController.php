<?php

class Biller_IndexController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
//            $default = new Zend_Registry(array(), ArrayObject::ARRAY_AS_PROPS);
//            Zend_Registry::setInstance($default);
//            $default->tree = 'apple';
//        $default = new Zend_Registry(array('defualt_facility' => 0));
//        Zend_Registry::setInstance($default);
        $this->view->baseUrl = $this->getRequest()->getBaseUrl();

    }

    function preDispatch() {
        $auth = Zend_Auth::getInstance();
        if (!$auth->hasIdentity()) {
            $this->_redirect('auth/login');
        } else {
            $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
            $user = Zend_Auth::getInstance()->getIdentity();
            $db_user = new Application_Model_DbTable_User();
            $db = $db_user->getAdapter();
            $where = $db->quoteInto('user_name = ?', $user->user_name);
            $user_data = $db_user->fetchRow($where);
            $this->view->assign('user_name', $user->user_name);
            $this->view->assign('role', $user_data['role']);


        }
    }

    public function indexAction() {
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
    }

    public function mainAction() {
        // action body
    }

    public function menuAction() {
//        $user = Zend_Auth::getInstance()->getIdentity();
//        $db_user = new Application_Model_DbTable_User();
//        $db = $db_user->getAdapter();
//        $where = $db->quoteInto('user_name = ?', $user->user_name);
//        $user_data = $db_user->fetchRow($where);
//        $this->view->role = $user_data['role'];
    }

    public function topAction() {
        // action body
    }

}

