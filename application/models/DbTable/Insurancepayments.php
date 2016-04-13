<?php

require_once 'DbTable.php';
class Application_Model_DbTable_Insurancepayments extends DbTable
{

    protected $_name = 'insurancepayments';
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
