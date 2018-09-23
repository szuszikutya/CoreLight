<?php

/*
 * Minimalist DB_iAdapter for PDO connection
 */


class Db_Adapter_Pdo
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
            if (!in_array ($key, array ('user', 'dbname', 'host', 'password', 'charset', 'dns'))) continue;
            $this->_CONNECTION->$key = $value;
        }
        try {
            $this->_CONNECTION->link = new PDO (@$this->_CONNECTION->dns, @$this->_CONNECTION->user, @$this->_CONNECTION->password, array
                (
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    //PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$this->_CONNECTION->charset}'"
                )
            );
        } catch (PDOException $e) {
            $this->_ERROR = $e->getMessage();
            die ($this->_ERROR);
        }
        if (!empty($this->_CONNECTION->db)) $this->setDatabase ($this->_CONNECTION->db);
    }

    public function getError () {
        $tmp = PDO::errorInfo ( void );
        $this->_ERROR = (empty($tmp) || empty($tmp[0]) || empty ($tmp[2])) ? '' : $tmp [2];
        return $this->_ERROR;
    }

    public function setDatabase ($database = null ) {
        try {
            $this->_CONNECTION->link->query('USE '.addslashes($database));
        } catch (PDOExecption $e) { die ($e->getMessage()); }
    }

    public function query ($query, $is_unbuffered = false) {
        if ($is_unbuffered) die ('PDO is not supported unbuffered query');
        try {
            $this->_RESOURCE = $this->_CONNECTION->link->prepare ($query, array (PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
            $affectedRows = $this->_RESOURCE->execute ();
            if (!empty($affectedRows)) {
                $this->num_rows = $this->_RESOURCE->rowCount();
                $this->_RESOURCE->num_rows = $this->num_rows;
                $this->AFFECTED_ROWS = $this->num_rows;
            }
        } catch (PDOExecption $e) { die ($e->getMessage()); }
        return $affectedRows;
    }

    public function fetch ($response_type = 2) {
        if (empty($this->_RESOURCE)) return false;

        if ( $this->_PAGINA != null && isset($this->_PAGINA->Enabled) && $this->_PAGINA->Enabled) {
            if ( $this->_PAGINA->Current_Element == $this->_PAGINA->Last_Element ) return false;
            switch ($response_type) {

                case 3 : return $this->_RESOURCE->fetch (PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $this->_PAGINA->Current_Element ++); break;
                case 2 : return $this->_RESOURCE->fetch (PDO::FETCH_OBJ, PDO::FETCH_ORI_ABS, $this->_PAGINA->Current_Element ++); break;
                default: return $this->_RESOURCE->fetch (PDO::FETCH_NUM, PDO::FETCH_ORI_ABS, $this->_PAGINA->Current_Element ++); break;
            }
        }

        switch ($response_type) {
            case 3 : return $this->_RESOURCE->fetch (PDO::FETCH_ASSOC); break;
            case 2 : return $this->_RESOURCE->fetch (PDO::FETCH_OBJ); break;
            default: return $this->_RESOURCE->fetch (PDO::FETCH_NUM); break;
        }
    }

    public function fetchAll ($response_type = 2) {
        if (empty($this->_RESOURCE)) return false;

        $value = array ();
        $this->resetResource();
        while ($row = $this->fetch ($response_type)) $value [] = $row;
        return $value;
    }

    public function resetResource() { if ($this->_PAGINA != null && isset($this->_PAGINA->Enabled) && $this->_PAGINA->Enabled) { $this->_PAGINA->reset(); } }

    public function script ($query){ return $this->query($query); }

    public function cursorTo ($record_number=0) {
        if ($this->_PAGINA != null && isset($this->_PAGINA->Enabled) && $this->_PAGINA->Enabled) { $this->_PAGINA->Current_Element = $record_number; }
    }

    public function insert ($table = null, $value = null) {
        if ($table == null || $value == null ) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        $vals = array(); $keys = array();
        foreach ( $value as $key => $val ) {

            $keys [] = '"'.str_replace('"','',$key).'"';
            $type = gettype( $val );
            if ( $type == "string" ) $vals [] = "'".$this->_CONNECTION->link->quote($val)."'";
            if ( $type == "boolean" ) { $val = ( $val ) ? 1 : 0; }
            if ( $type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = $val;
            if ( $type == "NULL" ) { $vals[] = 'NULL'; }
        }

        $this->query ("INSERT INTO {$table} (". implode (",", $keys). ") VALUES (". implode (",",$vals) .")");
        $this->INSERTED_ID = PDO::lastInsertId ();
        $this->AFFECTED_ROWS = $this->_RESOURCE->rowCount();
        return $this->INSERTED_ID;
    }

    public function update ($table = null, $value = null, $where = null) {
        if ($table == null || $value == null ) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        $vals = array();
        foreach ($value as $key => $val)
        {
            $type = gettype($val);
            if ($type == "string" ) $vals [] = '"'. str_replace ('"', '', $key)."\"='".$this->_CONNECTION->link->quote($val)."'";
            if ($type == "boolean" ) $val = ( $val ) ? 1 : 0;
            if ($type == "boolean" || $type == "integer" || $type == "float" || $type == "double" ) $vals [] = '"'. str_replace ('"', '', $key)."\"=".$val;
            if ($type == "NULL" ) $vals[] = '"'. str_replace ('"', '', $key)."\"= NULL";
        }
        if (!empty($where)) $where = ' WHERE '.$where;

        $this->query ("UPDATE {$table} SET ". implode (",", $vals).$where);
        $this->AFFECTED_ROWS = $this->_RESOURCE->rowCount();
        return $this->AFFECTED_ROWS;
    }

    public function remove ($table = null, $where = null) {
        if ($table == null) return false;

        $tmp = explode('.', str_replace ("\"", "", $table));
        $table = '"'.implode('"."', $tmp).'"';

        if (!empty($where)) $where = ' WHERE '.$where;

        $this->query("DELETE FROM {$table}".$where);

        $this->AFFECTED_ROWS = $this->_RESOURCE->rowCount();
        return $this->AFFECTED_ROWS;
    }

}