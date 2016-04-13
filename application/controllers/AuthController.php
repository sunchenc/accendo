<?php

class AuthController extends Zend_Controller_Action {

    public function init() {
        /* Initialize action controller here */
    }

    public function indexAction() {
        // action body
    }

    public function loginAction() {
        // action body    
    //    $this->view->baseUrl = $this->getRequest()->getBaseUrl();
         $this->view->assign('baseUrl',$this->getRequest()->getBaseUrl());
   //      if ($this->getRequest()->isPost()) {$this->_redirect('admin/index/index');}
        /*useful codes follow*/
        if ($this->getRequest()->isPost()) {
            Zend_Loader::loadClass('Zend_Filter_StripTags');
            $filter = new Zend_Filter_StripTags();
            $user_name = $filter->filter($this->getRequest()->getPost('user_name'));
            $password = md5($filter->filter($this->getRequest()->getPost('password')));

            if ($user_name != '' && $password != '') {
                Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable');
                $dbAdapter = Zend_Registry::get('dbAdapter');
                $authAdapter = new Zend_Auth_Adapter_DbTable($dbAdapter);
                $authAdapter->setTableName('user');
                $authAdapter->setIdentityColumn('user_name');
                $authAdapter->setCredentialColumn('password');

                $authAdapter->setIdentity($user_name);
                $authAdapter->setCredential($password);
                $auth = Zend_Auth::getInstance();
                $result = $auth->authenticate($authAdapter);
                if ($result->isValid()) {
                    $data = $authAdapter->getResultRowObject();
                    $auth->getStorage()->write($data);
                    $data = (array) $data;
                    $role = $data['role'];
                    switch ($role) {
                        case 'admin':
                            //$this->_redirect('admin/index/index');
                            $this->_redirect('biller/index/index');
                            break;
                        case 'billingcompany':
                            //$this->_redirect('billingcompany/index/index');
                            $this->_redirect('biller/index/index');
                            break;
                        default:
                            $this->_redirect('biller/index/index');
                            break;
                    }
                    $request = $this->getRequest();
                } else {
                    $this->_helper->redirector('login');
                }
            }
        }


    }
        public function logoutAction() {
        Zend_Auth::getInstance()->clearIdentity();

       echo "<script language='JavaScript' type='text/javascript'>
		top.location.href='login';
		</script>";
    }

}

