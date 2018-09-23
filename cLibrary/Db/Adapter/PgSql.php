<?php

	/*
	 * Minimalist DB_iAdapter for PgSql connection
	 */

	class Db_Adapter_PgSql implements Db_iAdapter {
	
	
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
			$connection_string = '';
			foreach ($connection as $key => $value ) {
			
				$key = strtolower( $key );
				if (!in_array ($key, array ('user', 'password', 'host', 'dbname', 'charset', 'port'))) continue;
				$this->_CONNECTION->$key = $value;
				$connection_string .= ' '.$key.'='.$value;
			}

			$this->_CONNECTION->link = pg_connect ($connection_string);
			if (!$this->_CONNECTION->link ) {
			
				$this->_ERROR = pg_last_error ($this->_CONNECTION->link); 
				return null; 
			}
			Db_PgSql::$_NOW_IS_MULTI_CONNECTION = true;
			if (!empty($this->_CONNECTION->db)) $this->setDatabase ($this->_CONNECTION->db);		
		}
		
		public function getError () {
			$this->_ERROR = pg_last_error ($this->_CONNECTION->link); 
			return $this->_ERROR;
		}
		
		public function setDatabase ($database = null ) {
			if (!mysqli_select_db($this->_CONNECTION->link, $database)) { 
				$this->_ERROR = pg_last_error($this->_CONNECTION->link); 
				return false;
			}
			$this->_CONNECTION->db = $database;
			mysqli_query( $this->_CONNECTION->link, 'SET NAMES '.$this->_CONNECTION->charset );
			return true;
		}
		
		public function fetch ($response_type = 2) { 
			Db_PgSql::$_ADAPTER = &$this; 
			return Db_PgSql::fetch ( $response_type ); 
		}
		
		public function fetchAll ($response_type = 2) { 
			Db_PgSql::$_ADAPTER = &$this; 
			return Db_PgSql::fetchAll ( $response_type ); 
		}

		public function resetResource() { return $this->cursorTo (0); }

		public function query ($query, $is_unbuffered = false) {
			Db_PgSql::$_ADAPTER = &$this;
			$return = Db_PgSql::query ($query, $is_unbuffered);
			//$this->_LENGTH = (!is_resource($this->_CONNECTION->link)) ? 0 : pg_affected_rows ($this->_CONNECTION->link);
			return $return;
		}
		
		public function script ($query){
			Db_PgSql::$_ADAPTER = &$this;
			return Db_PgSql::multi_query ($query);
		}
		
		public function cursorTo ($record_number=0) {
			Db_PgSql::$_ADAPTER = &$this;
			return Db_PgSql::seek(0);
		}
		
		public function insert ($table = null, $value = null) { 
			Db_PgSql::$_ADAPTER = &$this;
			$return = Db_PgSql::insert ( $table, $value ); 
			//$this->INSERTED_ID = $this->_CONNECTION->link->insert_id; 
			return $return;
		}		
		
		public function update ($table = null, $value = null, $where = null) { 
			Db_PgSql::$_ADAPTER = &$this; 
			return Db_PgSql::update ( $table, $value, $where ); 
		}
				
		public function remove ($table = null, $value = null) { 
			Db_PgSql::$_ADAPTER = &$this; 
			return Db_PgSql::delete ( $table, $value ); 
		}
		
		public function __getInsertedId ($seq_name) {
			Db_PgSql::$_ADAPTER = &$this;
			$return = Db_PgSql::query ("SELECT currval('".addslashes($seq_name)."') AS last_value", $is_unbuffered);
		   return $return;		
        }
		
	}