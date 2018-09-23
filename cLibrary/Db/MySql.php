<?php

	/*
	 * Static class for MySql Adapter.
	 * This class will be running the real database operations
	 */

class Db_MySql {

    static $_NOW_IS_MULTI_CONNECTION;
    static $_BASE_CONNECTION;
    static $_ADAPTER;

    public function __construct( $connection = null ) {
        Db_MySql::$_NOW_IS_MULTI_CONNECTION = false;
        Db_MySql::$_ADAPTER = null;
        if ( $connection == null ) $connection = Db_MySql::$_BASE_CONNECTION;
        Db_MySql::connection ( $connection );
        return null;
    }

    static function connection ( $adapter = null ) {
        if ($adapter == null && getType(self::$_BASE_CONNECTION) == 'object' && get_class (self::$_BASE_CONNECTION) == 'Db_Adapter_MySql')
        {
            self::$_ADAPTER = self::$_BASE_CONNECTION;
            return true;
        }
        if (getType($adapter) == 'object' && get_class ($adapter) == 'Db_Adapter_MySql')
        {
            self::$_ADAPTER = $adapter;
            return true;
        }
        if (!empty($adapter)) {
            self::$_ADAPTER = new Db_Adapter_MySql ($adapter);
            return (self::$_ADAPTER) ? true : false;
        }
        return false;
    }

    static function seek ( $cursor = 0) {
        if ( self::$_ADAPTER->_RESOURCE == null ) return false;
        return @mysql_data_seek(self::$_ADAPTER->_RESOURCE, $cursor);
    }

    static function query ($queryIn = null, $is_unbuffered = false ) {
        if (empty($queryIn) || !self::$_ADAPTER ) return false;

        self::$_ADAPTER->_RESOURCE = (!$is_unbuffered) ? mysql_query ($queryIn, self::$_ADAPTER->_CONNECTION->link) : mysql_query ($queryIn, self::$_ADAPTER->_CONNECTION->link, mysql_USE_RESULT);
        if (!self::$_ADAPTER->_RESOURCE) {
            echo "Invalid query: ". mysql_error (self::$_ADAPTER->_CONNECTION->link) ."\n";
            echo "Whole query: {$queryIn}\n";
            exit;
        }
        return true;
    }

    static function multi_query($queryIn) {
        echo "Többsoros lekérés nem támogatott. A következő scriptet szükséges lefuttatnod közvetlenül az adatbázisban: \n\n";
        echo str_replace(
            array('__DB__', '__HOST__', '__USER__'),
            array(self::$_ADAPTER->_CONNECTION->db, self::$_ADAPTER->_CONNECTION->host, self::$_ADAPTER->_CONNECTION->user), $queryIn);
        return null;
    }

    static function uquery ($queryIn = null) {
        return self::query( $queryIn, true );
    }

    static function fetch( $response_type = 3 ) {
        if ( self::$_ADAPTER->_RESOURCE == null ) return false;

        switch ($response_type)
        {
            case 3 : return mysql_fetch_assoc (self::$_ADAPTER->_RESOURCE); break;
            case 2 : return mysql_fetch_object (self::$_ADAPTER->_RESOURCE); break;
            default: return mysql_fetch_row (self::$_ADAPTER->_RESOURCE); break;
        }
    }

    static function fetchAll ($response_type = 3) {
        if (self::$_ADAPTER->_RESOURCE == null) return false;
        $value = array();

        switch ($response_type)
        {
            case 3 : while ($row = mysql_fetch_assoc (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
            case 2 : while ($row = mysql_fetch_object (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
            default : while ($row = mysql_fetch_row (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
        }
        return $value;
    }

    static function update ( $table = null, $value = null, $where = null ) {
        if ($table == null || $value == null ) return false;

        $table = '`'. str_replace ("`", "", $table).'`';
        $vals = array();
        foreach ($value as $key => $val)
        {
            $type = gettype($val);
            if ($type == "string" ) $vals [] = '`'. str_replace ('`', '', $key)."`='". mysql_real_escape_string ($val, self::$_ADAPTER->_CONNECTION->link)."'";
            if ($type == "boolean" ) $val = ( $val ) ? 1 : 0;
            if ($type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = '`'. str_replace ('`', '', $key)."`=".$val;
            if ($type == "NULL" ) $vals[] = '`'. str_replace ('`', '', $key)."`= NULL";
        }
        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("UPDATE $table SET ". implode (",", $vals).$where);
    }

    static function insert ( $table = null, $value = null ) {
        if ($table == null || $value == null ) return false;

        $table = '`'.str_replace("`","", $table).'`';
        $vals = array(); $keys = array();
        foreach ( $value as $key => $val )
        {
            $keys [] = '`'.str_replace('`','',$key).'`';
            $type = gettype( $val );
            if ( $type == "string" ) $vals [] = "'".mysql_real_escape_string( $val, self::$_ADAPTER->_CONNECTION->link )."'";
            if ( $type == "boolean" ) { $val = ( $val ) ? 1 : 0; }
            if ( $type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = $val;
            if ( $type == "NULL" ) { $vals[] = 'NULL'; }
        }
        return self::query ("INSERT INTO {$table} (". implode (",", $keys). ") VALUES (". implode (",",$vals) .")");
    }

    static function delete ($table = null, $where = null) {
        if ($table == null) return false;

        $table = '`'.str_replace("`","", $table).'`';
        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("DELETE FROM {$table}".$where);
    }

}
