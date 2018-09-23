<?php

/*
 * Static class for MySqli Adapter.
 * This class will be running the real database operations
 */


class Db_MySqli
{
    static $_NOW_IS_MULTI_CONNECTION;
    static $_BASE_CONNECTION;
    static $_ADAPTER;

    public function __construct( $connection = null )
    {
        Db_MySqli::$_NOW_IS_MULTI_CONNECTION = false;
        Db_MySqli::$_ADAPTER = null;
        if ( $connection == null ) $connection = Db_MySqli::$_BASE_CONNECTION;
        Db_MySqli::connection ( $connection );
        return null;
    }

    static function connection ( $adapter = null )
    {
        if ($adapter == null && getType(self::$_BASE_CONNECTION) == 'object' && get_class (self::$_BASE_CONNECTION) == 'Db_Adapter_MySqli')
        {
            self::$_ADAPTER = self::$_BASE_CONNECTION;
            return true;
        }
        if (getType($adapter) == 'object' && get_class ($adapter) == 'Db_Adapter_MySqli')
        {
            self::$_ADAPTER = $adapter;
            return true;
        }
        if (!empty($adapter)) {
            self::$_ADAPTER = new Db_Adapter_MySqli ($adapter);
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

        self::$_ADAPTER->_RESOURCE = (!$is_unbuffered) ? mysqli_query (self::$_ADAPTER->_CONNECTION->link, $queryIn) : mysqli_query (self::$_ADAPTER->_CONNECTION->link, $queryIn, MYSQLI_USE_RESULT);

        if (!self::$_ADAPTER->_RESOURCE) {
            echo "\nInvalid query: ". mysqli_error (self::$_ADAPTER->_CONNECTION->link) ."\n";
            echo "\nWhole query: \n{$queryIn}\n";
            exit;
        }
        return true;
    }

    static function multi_query($queryIn)
    {
        if (empty($queryIn) || !self::$_ADAPTER ) return false;
        $responseLog = "";
        if (mysqli_multi_query ( self::$_ADAPTER->_CONNECTION->link, $queryIn )) {
            do {
                if ($result = mysqli_store_result (self::$_ADAPTER->_CONNECTION->link)) {
                    while ($row = $result->fetch_row()) $responseLog .= $row[0];
                    $result->free();
                }
            } while (mysqli_next_result(self::$_ADAPTER->_CONNECTION->link));
        }
        return $responseLog;
    }

    static function uquery ($queryIn = null)
    {
        return self::query( $queryIn, true );
    }

    static function fetch( $response_type = 3 )
    {
        if ( self::$_ADAPTER->_RESOURCE == null ) return false;
        if ( self::$_ADAPTER->_PAGINA != null && isset(self::$_ADAPTER->_PAGINA->Enabled) && self::$_ADAPTER->_PAGINA->Enabled) {
            if ( self::$_ADAPTER->_PAGINA->Current_Element == self::$_ADAPTER->_PAGINA->Last_Element ) return false;
            self::$_ADAPTER->_PAGINA->Current_Element ++;
        }

        switch ($response_type)
        {
            case 3 : return mysqli_fetch_assoc (self::$_ADAPTER->_RESOURCE); break;
            case 2 : return mysqli_fetch_object (self::$_ADAPTER->_RESOURCE); break;
            default: return mysqli_fetch_row (self::$_ADAPTER->_RESOURCE); break;
        }
    }

    static function fetchAll ($response_type = 3)
    {
        if (self::$_ADAPTER->_RESOURCE == null) return false;
        $value = array();

        if ( self::$_ADAPTER->_PAGINA != null && isset(self::$_ADAPTER->_PAGINA->Enabled) && self::$_ADAPTER->_PAGINA->Enabled) {
            if ( self::seek (self::$_ADAPTER->_PAGINA->First_Element) === false ) return null;
            for ( $i=self::$_ADAPTER->_PAGINA->First_Element; $i<Sql::$_ADAPTER_ADAPTER->_PAGINA->Last_Element; $i++ ) {
                switch ($response_type) {
                    case 3 : $value [] = mysqli_fetch_assoc (self::$_ADAPTER->_RESOURCE); break;
                    case 2 : $value [] = mysqli_fetch_object (self::$_ADAPTER->_RESOURCE); break;
                    default : $value [] = mysqli_fetch_row (self::$_ADAPTER->_RESOURCE); break;
                }
            }
        } else {
            switch ($response_type)
            {
                case 3 : while ($row = mysqli_fetch_assoc (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
                case 2 : while ($row = mysqli_fetch_object (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
                default : while ($row = mysqli_fetch_row (self::$_ADAPTER->_RESOURCE)) $value [] = $row; break;
            }
        }
        return $value;
    }

    static function update ( $table = null, $value = null, $where = null )
    {
        if ($table == null || $value == null ) return false;

        $table = '`'. str_replace ("`", "", $table).'`';
        $vals = array();
        foreach ($value as $key => $val)
        {
            $type = gettype($val);
            if ($type == "string" ) $vals [] = '`'. str_replace ('`', '', $key)."`='". mysqli_real_escape_string (self::$_ADAPTER->_CONNECTION->link, $val)."'";
            if ($type == "boolean" ) $val = ( $val ) ? 1 : 0;
            if ($type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = '`'. str_replace ('`', '', $key)."`=".$val;
            if ($type == "NULL" ) $vals[] = '`'. str_replace ('`', '', $key)."`= NULL";
        }
        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("UPDATE $table SET ". implode (",", $vals).$where);
    }

    static function insert ( $table = null, $value = null )
    {
        if ($table == null || $value == null ) return false;

        $table = '`'.str_replace("`","", $table).'`';
        $vals = array(); $keys = array();
        foreach ( $value as $key => $val )
        {
            $keys [] = '`'.str_replace('`','',$key).'`';
            $type = gettype( $val );
            if ( $type == "string" ) $vals [] = "'".mysqli_real_escape_string(self::$_ADAPTER->_CONNECTION->link, $val )."'";
            if ( $type == "boolean" ) { $val = ( $val ) ? 1 : 0; }
            if ( $type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = $val;
            if ( $type == "NULL" ) { $vals[] = 'NULL'; }
        }
        return self::query ("INSERT INTO {$table} (". implode (",", $keys). ") VALUES (". implode (",",$vals) .")");
    }

    static function delete ($table = null, $where = null)
    {
        if ($table == null) return false;

        $table = '`'.str_replace("`","", $table).'`';
        if (!empty($where)) $where = ' WHERE '.$where;
        return self::query ("DELETE FROM {$table}".$where);
    }

}
