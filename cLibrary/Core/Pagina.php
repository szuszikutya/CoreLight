<?php

class Core_Pagina
{
	public $Page_Per_Element = 0; 	//$page_per_item = null;

	public $Cursor = null; 			//$current_page;
	public $Count = 0; 				//$max_page = null;
	public $Count_Element = 0;		//$max_item = null;

	public $First_Element = 0;		//$down_limit = null;
	public $Last_Element = 0;		//up_limit = null;
	public $Current_Element = 0;	//up_limit = null;
	public $Enabled = false;		//$is_pager = false;

	private $_resource = null;
	
	public function __construct( $object = null )
    {
		$type = getType($object);
		
		if ($type == 'object') $this->setObject($object);
		if ($type == 'integer') $this->setPageHeight($object);
	}
	
	public function setObject($object)
    {
		$tmp = get_class ($object);
		if (in_array($tmp, array('Db_Adapter_MySql', 'Db_Adapter_MySqli'))) {
			
			if (getType($object->_RESOURCE) != 'object') { 
				$this->_resource = null;
				$this->reset ();
				$this->Enabled = false;
				return false;
			}
			$this->_resource = &$object;
			$this->Count_Element = $this->_resource->num_rows;
			$this->refresh();
		}
	}
	
	public function refresh ()
    {
		if (empty($this->Page_Per_Element) || empty($this->Count_Element)) {
			$this->Enabled = false;
			return;
		}
		$this->Count = floor($this->Count_Element / $this->Page_Per_Element);
		if ( $this->Page_Per_Element == $this->Count_Element ) $this->Count = 0;
		$this->Enabled = ( $this->Count > 0 ) ? true : false;
	}
	
	public function setPage ($page_number = null)
    {
		if (!empty($page_number) && is_numeric($page_number) && $page_number >= 0) $this->Cursor = $page_number;
		$this->refresh ();
		if ($this->Enabled) {
			
			if ( ($this->Page_Per_Element*$this->Cursor) > $this->Count ) $this->Cursor = $this->Count;
			$this->First_Element = ( $this->Page_Per_Element * $this->Cursor );
			$this->Last_Element = $this->First_Element + $this->Page_Per_Element;
			$this->Current_Element = $this->First_Element;
			if ( $this->Last_Element > $this->Count_Element ) $this->Last_Element = $this->Count_Element;
			
			if ($this->_resource != null) $this->_resource->cursorTo ($this->First_Element);
		}
	}
	
	public function setPageHeight ($item_count)
    {
		if (empty($item_count) || !is_numeric($item_count) || $item_count < 1) return;
		$this->Enabled = ($item_count > 0 ) ? true : false;
		if ($this->Enabled) $this->Page_Per_Element = $item_count;
	}
	
	public function reset ()
    {
		$this->Cursor = (!empty($this->First_Element)) ? 0 : null;
		$this->Current_Element = $this->First_Element;
	}
	
}