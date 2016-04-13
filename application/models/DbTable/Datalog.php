<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'DbTable.php';

class Application_Model_DbTable_Datalog extends DbTable {

    protected $_name = 'datalog';
    protected $fields = array(////fields populated by the UI
        'id',
        'data_and_time',
        'user_id',
        'dbfield',
        'oldvalue',
        'newvalue',
    );

}