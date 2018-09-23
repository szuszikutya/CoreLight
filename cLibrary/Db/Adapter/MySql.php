<?php

    /*
    * Minimalist DB_iAdapter for MySql connection
    */


class Db_Adapter_MySql implements Db_iAdapter
	{		
		public $_CONNECTION = null;
		public $_RESOURCE = null;
		public $_ERROR = null;
		
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
			$this->_CONNECTION->link = mysql_connect (@$this->_CONNECTION->host, @$this->_CONNECTION->user, @$this->_CONNECTION->password );
			if (!$this->_CONNECTION->link ) 
			{
				$this->_ERROR = mysql_error ($this->_CONNECTION->link); 
				return null; 
			}
			Db_MySql::$_NOW_IS_MULTI_CONNECTION = true;
			if (!empty($this->_CONNECTION->db)) $this->setDatabase ($this->_CONNECTION->db);		
		}

		public function getError () {
			$this->_ERROR = mysql_error ($this->_CONNECTION->link); 
			return $this->_ERROR;
		}

		function setDatabase ($database = null ) {
			if (!mysql_select_db($database, $this->_CONNECTION->link)) { 
				$this->_ERROR = mysql_error($this->_CONNECTION->link); 
				return false;
			}
			$this->_CONNECTION->db = $database;
			mysql_query('SET NAMES '.$this->_CONNECTION->charset, $this->_CONNECTION->link);
			return true;
		}
		
		function fetch ($response_type = 2) { 
			Db_MySql::$_ADAPTER = &$this; 
			return Db_MySql::fetch ( $response_type ); 
		}
		
		function fetchAll ($response_type = 2) { 
			Db_MySql::$_ADAPTER = &$this; 
			return Db_MySql::fetchAll ( $response_type ); 
		}

		public function resetResource() { return $this->cursorTo(0);			 }

		public function query ($query, $is_unbuffered = false) {
			Db_MySql::$_ADAPTER = &$this;
			$return = Db_MySql::query ($query, $is_unbuffered);
			$this->_LENGTH = mysql_affected_rows($this->_CONNECTION->link);
			return $return;
		}
		
		public function script ($query){
			Db_MySql::$_ADAPTER = &$this;
			return Db_MySql::multi_query ($query);
		}
		
		public function cursorTo ($record_number=0) {
			Db_MySqli::$_ADAPTER = &$this;
			return Db_MySql::seek(0);
		}		
		
		public function insert ($table = null, $value = null) { 
			Db_MySql::$_ADAPTER = &$this; 
			$return = Db_MySql::insert ( $table, $value ); 
			$this->INSERTED_ID = mysql_insert_id($this->_CONNECTION->link); 
			return $return;
		}		
		
		public function update ($table = null, $value = null, $where = null) { 
			Db_MySql::$_ADAPTER = &$this; 
			return Db_MySql::update ( $table, $value, $where ); 
		}
				
		public function remove ($table = null, $value = null) { 
			Db_MySql::$_ADAPTER = &$this; 
			return Db_MySql::delete ( $table, $value ); 
		}
		
	}