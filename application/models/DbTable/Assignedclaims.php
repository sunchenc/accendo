<?php
require_once 'DbTable.php';
class Application_Model_DbTable_Assignedclaims extends DbTable
{

    protected $_name = 'assignedclaims';
        protected $fields = array(////fields populated by the UI
        'id',
        'assignee',
        'assignor',
        'encounter',
    );



}
        
?>
