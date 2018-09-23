<?php

	/*
     * Interface for database Adapters
	 */

	interface Db_iAdapter
    {
		public function connection ($connection);								// connect to database
		public function getError ();											// get error message
		public function setDatabase ($database = null);							// set database
		public function fetch ($response_type = 2);								// get the next resource record (type = 1:indexed array; 2:object; 3:hash array)
		public function fetchAll ($response_type = 2);							// get all records from resources (type = see fetch methode)
		public function resetResource();										// seek resource pointer to first record
		
		public function query ($query, $is_unbuffered = false );				// execute SQL query
		public function script ($query);										// execute multiple line SQL query. Not all adapter type can handle
		public function cursorTo ($record_number=0);							// seek resource pointer to
		public function insert ($table = null, $value = null);					// db insert (table name, array('col name' => 'value',...))
		public function update ($table = null, $value = null, $where = null);	// db update (table name, array('col_name' => 'value',...), 'id = 34')
		public function remove ($table = null, $value = null);					// db delete (table name, 'id = 34')
	}
