<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Zip2citystate extends DbTable {

    protected $_name = 'zip2citystate';
    protected $fields = array(////fields populated by the UI
        'zip',
        'city',
        'state',
    );

}

