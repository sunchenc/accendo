<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DbTable
 * parent class of all Zend_Db_Table in this project
 * define some common functions in this class
 * every child class inherit from this class should contains three member variable
 * *<variable>table name</variable>
 * *<variable>primary key field</variable>
 * *<variable>fields array</variable> 
 * @author Qiao Xinwang
 * 2011-04-01
 */
class DbTable extends Zend_Db_Table_Abstract {

    //put your code here    
    public function getFields() {
        return $this->fields;
    }

    public function fetchByPage($where, $order, $pageno,$count=10 ) {
        $offset = ($pageno - 1) * $count;
        return $this->fetchAll($where, $order, $count, $offset); // Zend_Db_Table_Rowset
    }

    public function getPageCount($where,$num=10) {    //num:the numbers of entries of a page
        $total = $this->fetchAll($where)->count();
        $pageCount = ceil($total / $num);
        return $pageCount;
    }

}

