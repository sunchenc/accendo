<?php

class Admin_IndexController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
        $this->view->baseUrl = $this->getRequest()->getBaseUrl();
    }

    public function preDispatch() {
        $auth = Zend_Auth::getInstance();
        if (!$auth->hasIdentity()) {
            $this->_redirect('auth/login');
        } else {
            $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
            $user = Zend_Auth::getInstance()->getIdentity();
            $this->view->assign('user_name', $user->user_name);
        }
    }

    public function indexAction() {
        // action body
        //   $this->view->baseUrl = $this->getRequest()->getBaseUrl();
        $this->view->assign('baseUrl', $this->getRequest()->getBaseUrl());
    }

    public function topAction() {
        // action body
        //   $this->view->render('top.phtml');
    }

    public function menuAction() {
        // action body
    }

    public function mainAction() {
        // action body
    }

}

