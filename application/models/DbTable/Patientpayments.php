<?php

require_once 'DbTable.php';
class Application_Model_DbTable_Patientpayments extends DbTable
{

    protected $_name = 'patientpayments';
        protected $fields = array(////fields populated by the UI
        'amount',
        'date',
        'notes',
        'claim_id',
    );



}


/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
?>
