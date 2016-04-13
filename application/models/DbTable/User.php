<?php
require_once 'DbTable.php';
class Application_Model_DbTable_User extends DbTable {

    protected $_name = 'user';
    protected $fields = array('user_name', 'password'); //fields populated by the UI

    public function change_password($user_name, $password) {
        $dbAdapter = $this->getAdapter();
        $set = array(
            'password' => $password,
        );
        $where = $dbAdapter->quoteInto('user_name = ?', $user_name);
        return $this->update($set, $where);
    }


    public function is_user_exist($user_name) {
        $dbAdapter = $this->getAdapter();
        $where = $dbAdapter->quoteInto('user_name = ?', $user_name);
        $result = $this->fetchAll($where);
        $count = $result->count();
        if ($count > 0)
            return true;
        return false;
    }


    

}

