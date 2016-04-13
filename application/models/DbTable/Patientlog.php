<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'DbTable.php';

class Application_Model_DbTable_Patientlog extends DbTable {

    protected $_name = 'patientlog';
    protected $fields = array(////fields populated by the UI
        'id',
        'patient_id',
        'date_and_time',
        'log',
    );

}