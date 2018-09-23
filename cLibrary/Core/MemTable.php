<?

class Core_MemTable{

	static $_BASE = null;
	static $_error = null; 
	static $_errorNumber = null;
	static $_resource = null;
	static $_expression = null;	
	static $_table_max_size = 500;
	
	static function AddTable ($name = null, $obj = null){
		if ($obj == null || $name == null) return false;
		if (isset(self::$_BASE[$name])) unset(self::$_BASE[$name]);
		$cols = array();
		$index = array();
		$data = array();
		foreach ($obj as $col => $format) {
			if ($format == 'AI') $cols[$col] = (object) array('type' => 'integer', 'AI' => true);
			elseif ($format == 'INT') $cols[$col] = (object) array('type' => 'integer', 'AI' => false);
			elseif ($format == 'STR') $cols[$col] = (object) array('type' => 'string', 'AI' => false);
			elseif ($format == 'BOL') $cols[$col] = (object) array('type' => 'boolean', 'AI' => false);
			else { return false; }
			$index[$col] = count($index);
			array_push($data, array());
		}
		self::$_BASE[$name] = (object) array('AutoIncrement' => 1, 'RowsCount' => 0,'ColsData' => (object) $cols, 'Index' => (object) $index, 'Data' => $data );
		return true;
	}
	
	static function Insert($name = null, $object = null){
		if ( $object == null || $name == null || !isset(self::$_BASE[$name]) ) return false;
		if ( self::$_BASE[$name]->RowsCount == self::$_table_max_size ) {
			self::$_errorNumber = 1;
			self::$_error = "Overflow error. Max record count each per base is: ".self::$_table_max_size;
			return false;
		}
		$clearData = array();
		foreach($object as $col => $value){
			if (!isset(self::$_BASE[$name]->ColsData->$col) ){
				self::$_errorNumber = 2;
				self::$_error = "Invalid column `$col`.";
				return false;
			}
			if ( self::$_BASE[$name]->ColsData->$col->type == 'integer' && !is_numeric($value) ) {
				self::$_errorNumber = 2;
				self::$_error = "Invalid column `$col` type.";
				return false;
			}
			if ( self::$_BASE[$name]->ColsData->$col->AI == true && in_array($value, self::$_BASE[$name]->Data[self::$_BASE[$name]->Index->$col] ) ) {
				self::$_errorNumber = 2;
				self::$_error = "Duplicated index in {$col} column";
				return false;
			}
			$clearData[$col] = $value;
			if ( self::$_BASE[$name]->ColsData->$col->type == 'boolean' ) {
				$clearData[$col] = ($value) ? true : false;
			}
		}
		foreach( self::$_BASE[$name]->ColsData as $col => $prop){
			if ( !isset($clearData[$col]) ) {
				$clearData[$col] = null;
				if ($prop->AI) {
					$clearData[$col] = self::$_BASE[$name]->AutoIncrement;
					self::$_BASE[$name]->AutoIncrement ++;
				} 
				elseif ($prop->type == 'integer') $clearData[$col] = 0;
				elseif ($prop->type == 'boolean') $clearData[$col] = false;
				else $clearData[$col] = null;
			}			
			self::$_BASE[$name]->Data[self::$_BASE[$name]->Index->$col] [] = $clearData[$col];
		}
		self::$_BASE[$name]->RowsCount ++;
		return true;
	}
	
	static function Select($name = null, $expression = null) {
		if ($name == null || !isset(self::$_BASE[$name]) ) return false;
		if ( $expression != null && !self::ExplodeExpression($name, $expression) ) return false;

		self::$_resource = (object) array('table' => $name, 'rowCount' => 0, 'counter' => 0, 'inList' => array() );
		$str = 'if (';
		foreach ( self::$_expression as $exp ){			
			$str .= 'self::$_BASE["'.$name.'"]->Data['.self::$_BASE[$name]->Index->$exp[0].'][$i]'.$exp[1].' '.$exp[2];
			if ( $exp[3] == null) break;
			$str .= " ".$exp[3]." ";
		}
		$str .= ') {array_push(self::$_resource->inList, $i); self::$_resource->rowCount ++;}';
		$cnt = count(self::$_BASE[$name]->Data[0]);
		
		for ($i=0; $i<$cnt; $i++){ eval($str); }
		return self::$_resource;
	}
	
	static function Delete($name = null, $expression = null) {
		if ($name == null || !isset(self::$_BASE[$name]) ) return false;
		if ( $expression != null && ($resource = self::Select($name, $expression)) === false ) return false;
		
		if ($resource->rowCount > 0) {
			$recordCount = self::$_BASE[$name]->RowsCount;
			for ( $i=0; $i<$recordCount; $i++) {
				$remove = false;
				if (in_array($i, array_values($resource->inList) ) ) $remove = true;
				for ( $j=0; $j<count(self::$_BASE[$name]->Data); $j++) {
					$delItem = array_shift(self::$_BASE[$name]->Data[$j]);
					if (!$remove) array_push(self::$_BASE[$name]->Data[$j], $delItem);
				}
			}
			self::$_BASE[$name]->RowsCount -= $resource->rowCount;
		}
		return true;
	}
	
	static function Update($name = null, $data = null, $expression = null) {
		if ($name == null || $data == null || !isset(self::$_BASE[$name]) ) return false;
		foreach ( $data as $key => $value) {
			if (!isset(self::$_BASE[$name]->ColsData->$key) ){
				self::$_error = "Invalid column `$key`.";
				self::$_errorNumber = 2;
				return false;
			}
			if ( self::$_BASE[$name]->ColsData->$key->type == 'integer' && !is_numeric($value) ) {
				if ($value == '++' || $value == '--') continue;
				self::$_error = "Invalid column `$key` type.";
				self::$_errorNumber = 2;
				return false;
			}
		}
		
		if ( $expression != null && ($resource = self::Select($name, $expression)) === false ) return false;
		
		print_r( $data );
		
		if ($resource->rowCount > 0) {
			foreach ($data as $key => $value) {
			
				echo "\n  - $key:$value";
				for ( $i=0; $i<$resource->rowCount; $i++) {
					
					if ($value === "++") {
						self::$_BASE[$name]->Data[self::$_BASE[$name]->Index->$key][$resource->inList[$i]] ++;
						echo '#++';
					}
					elseif ($value === "--") {
						self::$_BASE[$name]->Data[self::$_BASE[$name]->Index->$key][$resource->inList[$i]] --;
						echo '#--';
					}
					else self::$_BASE[$name]->Data[self::$_BASE[$name]->Index->$key][$resource->inList[$i]] = $value;
				}
			}
		}
		return true;
	}
	
	private function ExplodeExpression($name, $expression){
		$tmp = explode('"', $expression); $mark = array();
		if ( count($tmp) > 1) {
			for ($i=1; $i<count($tmp); $i++ ) {
				if ($i%2 == 1) { $mark['%'.(count($mark)+1)] = '"'.$tmp[$i].'"'; }
			}
			$expression = str_replace(array_values($mark), array_keys($mark), $expression);
		}

		$query = preg_replace(array("/  /", "/(\w+)/", "/(\d+)/"),array(' ', '${1} ', ' ${1} '),$expression);
		$expression = explode(' ', $query); $query = array();

		$command = 0; $tmpCommand = array(); self::$_expression = array(); $skipFlag = false;
		for ($i=0; $i<count($expression);$i++) {
			if ($skipFlag) {$skipFlag=false; continue;}
			if (empty($expression[$i])) continue;
			$teg = &$expression[$i];
			if ( $teg === '%') {
				$teg .= @$expression[$i+1];
				if (isset($mark[$teg])) $teg = $mark[$teg];
				$skipFlag = true;
			}
			if ($command == 0) {
				if (!isset(self::$_BASE[$name]->ColsData->$teg)) {
					self::$_errorNumber = 2;
					self::$_error = "Invalid column `{$teg}`.";
					return false;
				}
				$tmpCommand = array($teg,null,null,null);
			} else {
				$tmpCommand[$command] = $expression[$i];
			}
			if ($command == 3) {
				$command = -1;
				array_push(self::$_expression, $tmpCommand);
			}
			$command ++;
		}
		if ( $command >= 2) array_push(self::$_expression, $tmpCommand);
		return true;
	}
	
}

class CMt_Resource implements Iterator{
	
	private $_index = 0;
	private $_count = 0;
	private $_initialised;
	public $_list = null;
	
	function __construct( &$resource = null ) {
		$this->_initialised = false;
		$this->_index = 0; $this->_count = 0;
		if ( $resource != null ) $this->assoc($resource);
	}
	
	function assoc( &$resource = null ){
		if ( $resource != null && isset($resource->rowCount) && isset($resource->table) && isset($resource->counter) ) {
			$this->_index = $resource->counter;
			$this->_list = $resource->inList;
			$this->_initialised = $resource->table;
		}	
	}
	
	function Rewind() { $this->_index = 0; }
	
	function Current() {
		$obj = array();
		foreach ( Core_MemTable::$_BASE[$this->_initialised]->Index as $key => $keyIndex ){
			$obj [$key] = Core_MemTable::$_BASE[$this->_initialised]->Data[$keyIndex][$this->_list[$this->_index]];
		}
		return (object) $obj;
	}
	
	function Next() { $this->_index ++; }
	
	function Key() { return $this->_index; }
	
	function Valid() {  return isset($this->_list[$this->_index]);  }

}