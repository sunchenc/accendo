<?php
require_once 'DbTable.php';
class Application_Model_DbTable_X12partners extends DbTable
{

    protected $_name = 'x12partners';

    public function is_partner_name_exist($name) {
        $dbAdapter = $this->getAdapter();
        $where = $dbAdapter->quoteInto('name = ?', $name);
        $result = $this->fetchAll($where);
        $count = $result->count();
        if ($count > 0)
            return true;
        return false;
    }

}

