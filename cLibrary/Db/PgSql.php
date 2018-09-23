<?php

/*
 * Static class for PgSql Adapter.
 * This class will be running the real database operations
 */

class Db_PgSql
{
    static $_NOW_IS_MULTI_CONNECTION;
    static $_BASE_CONNECTION;
    static $_ADAPTER;

    public function __construct( $connection = null )
    {
        Db_PgSql::$_NOW_IS_MULTI_CONNECTION = false;
        Db_PgSql::$_ADAPTER = null;
        if ( $connection == null ) $connection = Db_PgSql::$_BASE_CONNECTION;
        Db_PgSql::connection ( $connection );
        return null;
    }

    static function connection ( $adapter = null )
    {
        if ($adapter == null && getType(self::$_BASE_CONNECTION) == 'object' && get_class (self::$_BASE_CONNECTION) == 'Db_Adapter_PgSql') {

            self::$_ADAPTER = self::$_BASE_CONNECTION;
            return true;
        }
        if (getType($adapter) == 'object' && get_class ($adapter) == 'Db_Adapter_PgSql') {

            self::$_ADAPTER = $adapter;
            return true;
        }
        if (!empty($adapter)) {

            self::$_ADAPTER = new Db_Adapter_PgSql ($adapter);
            return (self::$_ADAPTER) ? true : false;
        }
        return false;
    }

    static function seek ( $cursor = 0)
    {
        if ( self::$_ADAPTER->_RESOURCE == null ) return false;
        return @mysqli_data_seek(self::$_ADAPTER->_RESOURCE, $cursor);
    }

    static function query ($queryIn = null, $is_unbuffered = false )
    {
        if (empty($queryIn) || !self::$_ADAPTER ) return false;


        self::$_ADAPTER->_RESOURCE = @pg_query (self::$_ADAPTER->_CONNECTION->link, $queryIn);

        if (!self::$_ADAPTER->_RESOURCE) {
            echo "\nInvalid query: ". pg_last_error (self::$_ADAPTER->_CONNECTION->link);
            echo "\nWhole query: \n{$queryIn}\n";
            exit;
        }

        return true;
    }

    static function multi_query ($queryIn)
    {
        if (empty($queryIn) || !self::$_ADAPTER ) return false;
        $responseLog = "";
        if (pg_send_query ( self::$_ADAPTER->_CONNECTION->link, $queryIn )) {
            while ($result = pg_get_result (self::$_ADAPTER->_CONNECTION->link)) {
                $responseLog .= pg_num_rows($result);
                pg_free_result ($result);
            }
        }
        return $responseLog;
    }

    static function uquery ($queryIn = null) { die ('unbuffered query not support'); }

    static function fetch( $response_type = 2 )
    {
        if ( self::$_ADAPTER->_RESOURCE == null ) return false;
        if ( self::$_ADAPTER->_PAGINA != null && isset(self::$_ADAPTER->_PAGINA->Enabled) && self::$_ADAPTER->_PAGINA->Enabled) {
            if ( self::$_ADAPTER->_PAGINA->Current_Element == self::$_ADAPTER->_PAGINA->Last_Element ) return false;
            self::$_ADAPTER->_PAGINA->Current_Element ++;
        }

        switch ($response_type)
        {
            case 3 : return pg_fetch_assoc (self::$_ADAPTER->_RESOURCE); break;
            case 2 : return pg_fetch_object (self::$_ADAPTER->_RESOURCE); break;
            default: return pg_fetch_row (self::$_ADAPTER->_RESOURCE); break;
        }
    }

    static function fetchAll ($response_type = 2)
    {
        if (self::$_ADAPTER->_RESOURCE == null) return false;
        $value = array();

        if ( self::$_ADAPTER->_PAGINA != null && isset(self::$_ADAPTER->_PAGINA->Enabled) && self::$_ADAPTER->_PAGINA->Enabled) {
            if ( self::seek (self::$_ADAPTER->_PAGINA->First_Element) === false ) return null;
            for ( $i=self::$_ADAPTER->_PAGINA->First_Element; $i<self::$_ADAPTER->_PAGINA->Last_Element; $i++ ) {
                switch ($response_type) {
                    case 3 : $value [] = pg_fetch_assoc (self::$_ADAPTER->_RESOURCE); break;
                    case 2 : $value [] = pg_fetch_object (self::$_ADAPTER->_RESOURCE); break;
                    default : $value [] = pg_fetch_row (self::$_ADAPTER->_RESOURCE); break;
                }
            }
        } else {
            switch ($response_type)
            {
                case 3 : while ($row = pg_fetch_assoc (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
                case 2 : while ($row = pg_fetch_object (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
                default : while ($row = pg_fetch_row (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
            }
        }
        return $value;
    }

    static function update ( $table = null, $value = null, $where = null )
    {
        if ($table == null || $value == null ) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        $vals = array();
        foreach ($value as $key => $val)
        {
            $type = gettype($val);
            if ($type == "string" ) $vals [] = '"'. str_replace ('"', '', $key)."\"='". pg_escape_string (self::$_ADAPTER->_CONNECTION->link, $val)."'";
            if ($type == "boolean" ) $val = ( $val ) ? 1 : 0;
            if ($type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = '"'. str_replace ('"', '', $key)."\"=".$val;
            if ($type == "NULL" ) $vals[] = '"'. str_replace ('"', '', $key)."\"= NULL";
        }
        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("UPDATE $table SET ". implode (",", $vals).$where);
    }

    static function insert ( $table = null, $value = null )
    {
        if ($table == null || $value == null ) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        $vals = array(); $keys = array();
        foreach ( $value as $key => $val )
        {
            $keys [] = '"'.str_replace('"','',$key).'"';
            $type = gettype( $val );
            if ( $type == "string" ) $vals [] = "'".pg_escape_string(self::$_ADAPTER->_CONNECTION->link, $val )."'";
            if ( $type == "boolean" ) { $val = ( $val ) ? 1 : 0; }
            if ( $type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = $val;
            if ( $type == "NULL" ) { $vals[] = 'NULL'; }
        }
        return self::query ("INSERT INTO {$table} (". implode (",", $keys). ") VALUES (". implode (",",$vals) .")");
    }

    static function delete ($table = null, $where = null)
    {
        if ($table == null) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("DELETE FROM {$table}".$where);
    }

}
