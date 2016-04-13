
<?php

require_once 'DbTable.php';

class Application_Model_DbTable_Providerhasdiagnosiscode extends DbTable {

    protected $_name = 'providerhasdiagnosiscode';
    protected $fields = array(////fields populated by the UI
        'id',
        'provider_id',
        'diagnosiscode_id',
    );

}

