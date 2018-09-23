<?PHP

class Core_View {

    public $_FINAL_BUILD = false;
    public $_rootTemplate = '/site.phtml';

    public function renderer ($isController = true)
    {
        if ( $isController ) ob_start();
        include ( Core::$_DIRS->VIEW.'/'.Core::$_DISPATCHER->_CONTROLLER.'/'.Core::$_DISPATCHER->_ACTION.'.phtml' );
        if ( $isController) {
            Core::$_STDOUT->CONTROLLER_TEMPLATE = ob_get_contents();
            ob_clean();
        }
    }

    public function __build () { $this->_FINAL_BUILD = true; include (Core::$_DIRS->VIEW.$this->_rootTemplate ); }
    public function __get ($name ) { return (isset (Core::$_TEMPLATE_DATA->$name)) ? Core::$_TEMPLATE_DATA->$name : null; }
    public function __set ($name, $value ) { @Core::$_TEMPLATE_DATA->$name = @$value; }
    public function __isset ($name) { return isset (Core::$_TEMPLATE_DATA->$name); }

    public function getContent ()
    {
        if (!empty (Core::$_STDOUT->CONTROLLER_TEMPLATE)) return Core::$_STDOUT->CONTROLLER.Core::$_STDOUT->CONTROLLER_TEMPLATE;
        else {
            echo Core::$_STDOUT->CONTROLLER;
            $this->renderer ( false );
        }
    }

    private function __clearBuffer ()
    {
        if (!$this->_FINAL_BUILD) {
            $tmp = ob_get_contents ();
            ob_clean ();
            return $tmp;
        }
        return;
    }

    public function loop ($file = null, &$value = null)
    {
        $tmp = null;
        if (!$this->_FINAL_BUILD) ob_start ();
        if (empty ($value)) {
            echo Core::$_DIRS->VIEW.$file;
            include (Core::$_DIRS->VIEW.$file);
            return $this->__clearBuffer ();
        }

        $type = getType ($value);
        $class = ($type == 'object') ? get_class ($value) : 'undefined';
        $isAssoc = ($type == 'array' && array_keys ($value) !== range (0, count($value) - 1)) ? true : false;

        if (($type == 'object' && !in_array ($class, array ('Db_Adapter_MySqli', 'Db_Adapter_MySql', 'Db_Adapter_Pdo'))) || $isAssoc) {
            foreach ($value as $key => $val) $this->$key = $val;
            include (Core::$_DIRS->VIEW.$file);
            return $this->__clearBuffer ();
        }

        if ($type == 'array') {
            $this->_instance = (object) array (
                'Count' => count ($value),
                'Last_Element' => count ($value),
                'First_Element' => 0,
                'Cursor' => 0,
                'Page_Height' => count ($value)
            );
            $this->_index = 0;
            for ($i=0; $i<count ($value); $i++) {
                foreach ($value [$i] as $key => $val) $this->$key = $val;
                $this->_index = $i;
                $this->_instance->Cursor = $i;
                include (Core::$_DIRS->VIEW.$file);
            }
            return $this->__clearBuffer ();
        }

        if ( in_array ($class, array ('Db_Adapter_MySqli', 'Db_Adapter_MySql', 'Db_Adapter_Pdo')) && getType ($value->_RESOURCE) == 'object') {
            $this->_instance = (object) array (
                'Count' => @$value->_RESOURCE->num_rows,
                'First_Element' => (isset ($value->_PAGINA->First_Element)) ? $value->_PAGINA->First_Element : 0,
                'Last_Element' => (isset ($value->_PAGINA->Last_Element)) ?  $value->_PAGINA->Last_Element : @$value->_RESOURCE->num_rows,
                'Cursor' => (isset ($value->_PAGINA->Current_Element)) ? $value->_PAGINA->Current_Element : 0,
                'Page_Height' => (isset ($value->_PAGINA->Page_Per_Element)) ? $value->_PAGINA->Page_Per_Element : @$value->_RESOURCE->num_rows
            );
            $this->_index = 0;
            while ($raw = $value->fetch ())
            {
                foreach ($raw as $key => $val) $this->$key = $val;
                include (Core::$_DIRS->VIEW.$file);
                $this->_instance->Cursor ++;
                $this->_index ++;
            }
            return $this->__clearBuffer ();
        }
    }

    public function paginator ($file = null, $range = 5 , Core_Pagina &$value = null)
    {
        if (empty ($value->Count) || !$value->Enabled) return null;
        if (!$this->_FINAL_BUILD) ob_start ();

        $this->nextPage = null;
        $this->backPage = null;
        $this->currentPage = $value->Cursor;

        $tmp = array ();
        $backPage = ( $value->Cursor > 0 ) ? $value->Cursor - 1 : 0 ;
        $nextPage = ( $value->Cursor < $value->Count ) ? $value->Cursor + 1 : $value->Count ;
        $init = ( $value->Cursor - $range < 0 ) ? ( $value->Cursor - $range ) * -1 : 0;
        $mod = ( $range > $value->Count - $value->Cursor ) ? ($value->Count - $value->Cursor) - $range : 0;
        $c = 0;

        for ($i= $value->Cursor - $range + $mod ; $i < ($value->Cursor + $range +1); $i++)
        {
            if ($i + $init > $value->Count || $i + $init < 0 || $value->Count_Element <= 1+(($init + $i) * $value->Page_Per_Element)) continue;
            $tmp [] = (object) array('page' => $init + $i +1, 'linkNumber' => $init + $i, 'link' => '/'.Core::$_DISPATCHER->_CONTROLLER.'/'.Core::$_DISPATCHER->_ACTION.'/page/'.($init + $i));
            if (($init + $i) == $backPage ) $this->backPage = $tmp [count ($tmp)-1];
            if (($init + $i) == $nextPage ) { $this->nextPage = $tmp [count ($tmp)-1]; $nextPage = -255; }
            $c ++;
        }

        if ($c == 1) {
            $value->Count++;
            if ( $this->nextPage == 1 ) $this->nextPage = 2;
            $tmp [] = (object) array('page' => 2, 'linkNumber' => 1, 'link' => '/'.Core::$_DISPATCHER->_CONTROLLER.'/'.Core::$_DISPATCHER->_ACTION.'/page/2');
        }

        $this->lastPage = $value->Count;
        $this->pages = $tmp;
        $this->currentPage ++;
        if ($nextPage != -255) $this->nextPage = $tmp [count ($tmp)-1];

        include (Core::$_DIRS->VIEW.$file);
        return $this->__clearBuffer ();
    }

    public function url () { return '/'.Core::$_DISPATCHER->_CONTROLLER.'/'.Core::$_DISPATCHER->_ACTION; }
}