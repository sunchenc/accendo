<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {

    protected function _initdbAdapter() {
        $resource = $this->getPluginResource('db');
        $dbAdapter = $resource->getDbAdapter();
        Zend_Db_Table::setDefaultAdapter($dbAdapter);
        Zend_Registry::set('dbAdapter', $dbAdapter);

//        $default = new Zend_Registry(array(), ArrayObject::ARRAY_AS_PROPS);
//        Zend_Registry::setInstance($default);
//        $default->tree = 'apple';
    }

}

