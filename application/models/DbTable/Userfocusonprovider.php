<?php

require_once 'DbTable.php';
class Application_Model_DbTable_Userfocusonprovider extends DbTable
{

    protected $_name = 'userfocusonprovider';
        protected $fields = array(////fields populated by the UI
        'user_id',
        'provider_id',
    );



}
