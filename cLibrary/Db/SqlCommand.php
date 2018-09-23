<?PHP

/*
 * SQL Command builder
 * If define PGSQL_QUERY_FORMAT variable (with true value), the output query string will be PgSql compatible.
 * Otherwise the syntax is to MySql style.
 */



class Db_SqlCommand
{
	protected $queryObject = null; 
	
	public function __construct(){
		$this->queryObject = array( 'from' => null, 'cols' => array(), 'limit' => null );
		return $this;
	}		
	
	public function __set( $name, $value ){
		$name = strtolower($name);
		$type = gettype($value);
		
		if ($type == "string" ) {
			if ( $name == 'from' || $name == 'join' || $name == 'joinleft' || $name == 'joinright' ) $this->queryObject['from'][] = array ('table' => $value, 'type' => $type, 'on' => null, 'as' => null );
			if ( $name == 'group' || $name == 'where' || $name == 'whereor' || $name == 'order' || $name == 'having' ) {
				if ( $name == 'where' || $name == 'whereor' ) {
					$this->queryObject[$name] = array();
					$this->queryObject[$name][] = array('type' => $name, 'value' => $value, 'variable' => null );
				} else {
					$this->queryObject[$name] = $value;
				}
			}
		}
		if ($type == "integer" || $type == "float" || $type == "double" ) {	
			if ( $name == 'limit' ) $this->queryObject[$name] = $value;
			if ( $name == 'where' ) $this->queryObject['where'][] = array('type' => $name, 'value' => $value, 'variable' => null );
		}
		if ($type == "boolean" ) { }
		if ($type == "array" || $type == "object") { 
			if ( $name == 'from' || $name == 'join' || $name == 'joinleft' || $name == 'joinright' ) {
				$path = null; $joinCount = null; $inc = -1;
				foreach ( $value as $index => $val ){
					$inc ++;
					if ( $index === 0 && is_string($val) ) { 
						$table_name = (substr ($val,0,1) == '`' && substr ($val,-1) == '`') ? substr ($val, 1, -1) : $val ;					
						$this->queryObject['from'] [] = array ( 'table' => $table_name, 'type' => $name, 'on' => null, 'as' => null );
						$joinCount = count ( $this->queryObject['from'] ) - 1;
						continue;
					}
					if ( $index === 0 && is_array($val) ) { 
						$tmp = array_keys($val);
						$table_name = (substr ($tmp[0],0,1) == '`' && substr ($tmp[0],-1) == '`')  ? substr ($tmp[0], 1, -1) : $tmp[0];
						$this->queryObject['from'][] = array ( 'type' => $name, 'table' => "{$table_name}", 'as' => "{$val[$tmp[0]]}", 'on' => null ); 
						$joinCount = count ( $this->queryObject['from'] ) - 1;
						$path = '`'.$val[$tmp[0]].'`.'; 
						continue; 
					}
					if ( $index === 1 && is_string($val) ) { 
						$this->queryObject['from'][$joinCount]['on'] = "$val";
						continue;
					}
					if ( ($index === 1 || $inc == 2)&& is_array($val) ) { 
						foreach ( $val as $num => $col ) { 
							if ( is_numeric($num) ) $add = ''; else { $add = ' as '.$col; $col = $num; }
								$col = trim($col);
								if ( $col == '*' ) $this->queryObject['cols'] [] = "$path$col$add";
								elseif ( ( stripos($col,'(') === false || stripos($col,')') === false )  ) $this->queryObject['cols'] [] = "$path`$col`$add";
								else $this->queryObject['cols'] [] = "$col$add";
						}
						continue;
					}
				}
			}
			if ( $name == 'where' || $name == 'whereor' ) {
				$inc = null; 
				foreach ( $value as $key => $col ) {
					if ( $key === 0 && is_string( $col ) ) { 
						$this->queryObject['where'][] = array('type' => $name, 'value' => $col, 'variable' => null );
						$inc = count( $this->queryObject['where'] ) -1;
						continue;
					} elseif ( $inc === null ) { continue; }
					$this->queryObject['where'][$inc]['variable'] = $col;
				}
			}
			if ( $name == 'order' || $name == 'group' || $name == 'having' || $name == 'limit' ) {
				$this->queryObject[$name] = $value[0];
			}
		}
		if ($type == "NULL" || $type == "resource" || $type == "unknown type" ) {  }
	}	
	
	public function attribute ( $name, $value = null ){
		if ($value != null) $this->queryObject[$name] = $value;
		else return $this->queryObject[$name];
		return true;
	}
	
	public function get(){
		$from = ''; $join = array();
		if (!empty($this->queryObject['group'])) {
			if ( !is_array($this->queryObject['group']) ) $this->queryObject['group'] = array($this->queryObject['group']);
			$group = ' GROUP BY '.implode(',', $this->queryObject['group']).' ';
		} else { $group = ''; }
		if (!empty($this->queryObject['limit'])) {
			if ( !is_array($this->queryObject['limit']) ) $this->queryObject['limit'] = array($this->queryObject['limit']);
			$limit = ' LIMIT '.implode(',', $this->queryObject['limit']).' ';
		} else { $limit = ''; }
		if (!empty($this->queryObject['order'])) {
			if ( !is_array($this->queryObject['order']) ) $this->queryObject['order'] = array($this->queryObject['order']);
			$order = ' ORDER BY '.implode(',', $this->queryObject['order']).' ';
		} else { $order = ''; }
		$having = (!empty($this->queryObject['having'])) ? ' HAVING ('.$this->queryObject['having'].') ' : '';
        $str = '';
		for ( $i=0; $i<count($this->queryObject['from']); $i++ ){
			$tmp = &$this->queryObject['from'][$i];
			$isFrom = false;
			switch ($tmp['type']){
				case 'from' : $str = ' FROM '; $isFrom = true; break;
				case 'join' : $str = ' JOIN '; break;
				case 'joinleft' : $str = ' LEFT JOIN '; break;
				case 'joinright' : $str = ' RIGHT JOIN '; break;
			}
			if (!$isFrom) { $join [] = $str . '`'.$tmp['table'].'` '.$tmp['as'].' ON '.$tmp['on']; }
			else { 
				$from =  $str . '`'.$tmp['table'].'` '.$tmp['as'].' ';
				if ( !empty($tmp['on']) ) $this->queryObject['cols'][] = $tmp['on'];
			}
		}
		$str = '';
		for ( $i=0; $i<count(@$this->queryObject['where']); $i++ ){
			$tmp = &$this->queryObject['where'][$i];
			if ( $tmp['type'] == 'where' ) { $str .= ' AND ('; }
			else $str .= ' OR  (';
			if ( $tmp['variable'] === null ) $str .= $tmp['value'];
			else {
				if ( !is_array($tmp['variable']) ) $tmp['variable'] = array($tmp['variable']);
				$value = array();
				for ( $j=0; $j<count($tmp['variable']); $j++ ){
					$type = gettype($tmp['variable'][$j]);
					if ( $type == "string" ) { $value [] = "'".addslashes( $tmp['variable'][$j] )."'"; }
					if ( $type == "boolean" ) { $value [] = ($tmp['variable'][$j]) ? 1 : 0; }
					if ( $type == "NULL" ) { $value [] = "'NULL'"; }
					if ( $type == "integer" || $type == "float" || $type == "double" ) { $value [] = $tmp['variable'][$j]; }
				}
				$tmpStr = implode(',', $value);
				if ( !empty($value) ) $str .= str_replace( '?', $tmpStr, $tmp['value'] );
			}
			$str .= ')';
		}
		$where = (!empty($str)) ? ' WHERE '.substr( $str, 4 ) : '';
		$join = (!empty($join)) ? implode(' ',$join) : '';
		for ( $j=0; $j<count( $this->queryObject['cols'] ); $j++ ){
			if ( $this->queryObject['cols'][$j] == '*' || (stripos($this->queryObject['cols'][$j],'(') !== false && stripos($this->queryObject['cols'][$j],')') !== false) ) continue;
			$this->queryObject['cols'][$j] = addslashes($this->queryObject['cols'][$j]);
		}
		
		$response = "SELECT ".implode(',',$this->queryObject['cols']).' '.trim($from).' '.$join.' '.trim($where).$group.$having.$order.$limit;
		if (defined('PGSQL_QUERY_FORMAT') && PGSQL_QUERY_FORMAT == true) $response = str_replace('`', '"', $response);
		return $response;
	}	

	public function from (){ $tmp = func_get_args (); $this->__set ( 'from', $tmp ); }
	
	public function join (){ $tmp = func_get_args (); $this->__set ( 'join', $tmp ); }
	
	public function joinLeft (){ $tmp = func_get_args (); $this->__set ( 'joinLeft', $tmp ); }
	
	public function joinRight (){ $tmp = func_get_args (); $this->__set ( 'joinRight', $tmp ); }
	
	public function group (){ $tmp = func_get_args (); $this->__set ( 'group', $tmp ); }
	
	public function having (){ $tmp = func_get_args (); $this->__set ( 'having', @$tmp[0] ); }
	
	public function order (){ $tmp = func_get_args (); $this->__set ( 'order', $tmp ); }
	
	public function limit (){ $tmp = func_get_args (); $this->__set ( 'limit', $tmp ); }
	
	public function where (){ $tmp = func_get_args (); $this->__set ( 'where', $tmp ); }
	
	public function whereOr (){ $tmp = func_get_args (); $this->__set ( 'whereOr', $tmp ); }

}