<?php

/*
 * Minimalist DB_iAdapter for MySQLi connection
 */

class Db_Adapter_MySqli implements Db_iAdapter
{
    public $_CONNECTION = null;
    public $_RESOURCE = null;
    public $_ERROR = null;
    public $_PAGINA = null;

    public $INSERTED_ID = null;
    public $AFFECTED_ROWS = null;

    public function __construct( $connection = null ) {
        if ( is_array($connection) && count($connection) > 0 ) $this->connection ($connection);
        return $this;
    }

    public function connection( $connection ) {

        $this->_CONNECTION = (object) array ('charset' => "UTF8");
        $this->_RESOURCE = null;
        foreach ($connection as $key => $value )
        {
            $key = strtolower( $key );
            if (!in_array ($key, array ('user', 'db', 'host', 'password', 'charset'))) continue;
            $this->_CONNECTION->$key = $value;
        }
        $this->_CONNECTION->link = new mysqli (@$this->_CONNECTION->host, @$this->_CONNECTION->user, @$this->_CONNECTION->password );
        if (!$this->_CONNECTION->link )
        {
            $this->_ERROR = mysqli_error ($this->_CONNECTION->link);
            return null;
        }
        Db_MySqli::$_NOW_IS_MULTI_CONNECTION = true;
        if (!empty($this->_CONNECTION->db)) $this->setDatabase ($this->_CONNECTION->db);
    }

    public function getError () {
        $this->_ERROR = mysqli_error ($this->_CONNECTION->link);
        return $this->_ERROR;
    }

    public function setDatabase ($database = null ) {
        if (!mysqli_select_db($this->_CONNECTION->link, $database)) {
            $this->_ERROR = mysqli_error($this->_CONNECTION->link);
            return false;
        }
        $this->_CONNECTION->db = $database;
        mysqli_query( $this->_CONNECTION->link, 'SET NAMES '.$this->_CONNECTION->charset );
        return true;
    }

    public function fetch ($response_type = 2) {
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::fetch ( $response_type );
    }

    public function fetchAll ($response_type = 2) {
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::fetchAll ( $response_type );
    }

    public function resetResource() { return $this->cursorTo(0); }

    public function query ($query, $is_unbuffered = false) {
        Db_MySqli::$_ADAPTER = &$this;
        $return = Db_MySqli::query ($query, $is_unbuffered);
        $this->_LENGTH = $this->_CONNECTION->link->affected_rows;
        return $return;
    }

    public function script ($query){
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::multi_query ($query);
    }

    public function cursorTo ($record_number=0) {
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::seek(0);
    }

    public function insert ($table = null, $value = null) {
        Db_MySqli::$_ADAPTER = &$this;
        $return = Db_MySqli::insert ( $table, $value );
        $this->INSERTED_ID = $this->_CONNECTION->link->insert_id;
        return $return;
    }

    public function update ($table = null, $value = null, $where = null) {
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::update ( $table, $value, $where );
    }

    public function remove ($table = null, $value = null) {
        Db_MySqli::$_ADAPTER = &$this;
        return Db_MySqli::delete ( $table, $value );
    }

}