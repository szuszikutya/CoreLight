<?PHP

abstract class Core_Forms {

    public $_INIT_DATA = null;
    public $_error = array ();
    public $_validation = null;

    public function getNameSpace () { return $this->NameSpace; }

    protected $NameSpace = "Forms";
    protected $_elements = array ();
    protected $_sheet = array (
        'radio' => "\n<label>%Title%<input type=\"radio\" %property% /></label>",
        'select' => "\n<label>%Title% <select %property%>%option%</select></label>",
        'text' => "\n<label>%Title% <input type=\"text\" %property% /></label>",
        'area' => "\n<textarea %property%>%value%</textarea>",
        'hidden' => "\n<input type=\"hidden\" %property% />",
        'password' => "\n<label>%Title% <input type=\"password\" %property% /></label>",
        'file' => "\n<label>%Title% <input type=\"file\" %property% /></label>",
        'checkbox' => "\n<label>%Title% <input type=\"checkbox\" %property% /></label>",
        'button' => "\n<label>%Title% <input type=\"button\" %property% /></label>"
    );

    private $_counter = array ();
    private $_template = "%Title%%Input%%Error%";


    public function setTemplate( $string = "%Title%%Input%%Error%") { $this->_template = $string; }

    public function add( $name, $type = 'text', $parameters = array ())
    {
        $obj = array ();
        if (gettype($name) !== 'array') { $obj [] = array ($name, $type, $parameters);} else $obj =  $name;
        foreach ($obj as $input)
        {
            if (empty ($input[0]) ) continue;
            $tmp = array ('type' => $input[1], 'Title' => '', 'Values' => array ());
            if (!empty ($input[2])) {
                foreach ($input[2] as $key => $value)
                {
                    if (strtolower ($key) == 'title') {
                        if (gettype ($value) == 'array') {
                            foreach ($value as $k => $v) $tmp [$k] = $v;
                            continue;
                        } else {
                            $tmp ['Title'] = $value;
                            continue;
                        }
                    }
                    if (strtolower ($key) == 'templateindex') {
                        $tmp ['TemplateIndex'] = $value;
                        continue;
                    }
                    if (substr ($key,0,1) === '$') {
                        $key = substr ($key,1);
                        $tmp [$key] = $value;
                        continue;
                    }
                    $tmp ['Values'] [$key] = $value;
                }
            }
            $this->_elements [$input [0]] = (object) $tmp;
        }
    }

    public function renderer( $current = null )
    {
        foreach ($this->_elements as $name => $data )
        {
            $input = '';
            $property = 'name="'.$this->NameSpace.'['.$name.']" id="'.$this->NameSpace.'-'.$name.'"';
            if (empty ($data->Values)) {
                if ($data->type != 'area') {
                    $property .= ' value="'.@$current->$name.'"';
                    $input  = str_replace (array ('%Title%', '%property%'), array ($data->Title, $property),  @$this->_sheet [$data->type]);
                } else {
                    $value = (isset ($current->$name)) ? $current->$name : '';
                    $input  = str_replace (array ('%property%', '%value%'), array ($property, $value),  @$this->_sheet [$data->type]);
                }
            } else {
                $this->_counter = array ('radio' => 0, 'select' => 0, 'text' => 0, 'area' => 0, 'password' => 0, 'file' => 0, 'checkbox' => 0, 'button' => 0, 'hidden' => 0);
                $groupMode = false;
                foreach ($data->Values as $text => $value)
                {
                    $prp = '';
                    if ($data->type == 'group') { $groupMode = true;}
                    if ($groupMode) {

                        foreach ($value as $uKey => $uVal)
                        {
                            if (in_array ($uKey, array ('Title', 'Value', 'TemplateIndex'))) continue;
                            $data->$uKey = $uVal;
                        }
                        $pos = strpos ($text, '#');
                        $name = ($pos === false) ? $text : substr ($text,0,$pos);
                        $property = 'name="'.$this->NameSpace.'['.$name.']" id="'.$this->NameSpace.'-'.$name.'"';
                        $group_value = $value;

                        $text = @$value ['Title'];
                        $value = @$value ['Value'];
                    }

                    if (isset ($current->$name)) {
                        if ($data->type == 'radio' && $current->$name == $value) $prp .= ' checked="checked" ';
                        if ($data->type == 'select' && $current->$name == $value && !$groupMode) $prp .= ' selected="selected" ';
                        if ($data->type == 'checkbox' && !empty($current->$name)) $prp .= ' checked="checked" ';
                    } else {
                        if ($data->type == 'checkbox' && !empty($value)) $prp .= ' checked="checked" ';
                    }
                    if ($data->type == 'select') {
                        if ($groupMode && is_array ($value)) {
                            $_group_select = '';
                            foreach ($value as $key => $val)
                            {
                                $prp = (@$current->$name == $val) ? ' selected="selected" ' : '';
                                $_group_select .= '<option value="'.$val.'" '.$prp.'>'.$key.'</option>';
                            }
                            $data->option = $_group_select;
                            $value = @$current->$name;
                            $prp = '';
                        } else {
                            $input .= '<option value="'.$value.'" '.$prp.'>'.$text.'</option>';
                            continue;
                        }
                    }
                    if (($data->type == 'text' || $data->type == 'hidden' || $data->type == 'password' || $data->type == 'area') && isset ($current->$name) ) { $value = $current->$name; }
                    if ($data->type == 'area' || $data->type == 'checkbox') { $prp .= $property; }
                    else {
                        if (!is_array ($value)) $prp .= @$property.' value="'.@$value.'"';
                    }

                    /*
                     * Check
                     */

                    if (gettype ($text) == "integer" && !empty ($data->Title)) $text = $data->Title;
                    $id = ($data->type != 'radio') ? "id=\"L{$this->NameSpace}-{$name}\" for=\"{$this->NameSpace}-{$name}\"" : "id=\"L{$this->NameSpace}-{$name}-{$this->_counter[$data->type]}\"";
                    if ($data->type == 'radio') {
                        $prp = str_replace('id="'.$this->NameSpace.'-'.$name.'"', 'id="'.$this->NameSpace.'-'.$name.'-'.$this->_counter[$data->type].'"', $prp);
                    }
                    $rpc = array ('%Title%', '%property%', '%value%', "\n<label>"); $rpcTo = array ($text, $prp, $value, "\n<label ".$id.">" );
                    $tpl = @$this->_sheet [$data->type];
                    if (gettype ($tpl) == 'array') {
                        if (isset ($tpl [$this->_counter [$data->type]])) $tpl = $tpl [$this->_counter [$data->type]];
                        else $tpl = array_pop ($tpl);
                    }
                    if ($this->_counter [$data->type] == 0) {
                        foreach( $data as $uKey => $uVal )
                        {
                            if (in_array ($uKey, array ('type', 'Title', 'Values'))) continue;
                            $rpc [] = '%'.$uKey.'%';
                            $rpcTo [] = $uVal;
                        }
                    }
                    if ($groupMode) {
                        foreach( $group_value as $uKey => $uVal )
                        {
                            if (in_array ($uKey, array ('type', 'Title', 'Values'))) continue;
                            if (!in_array ('%'.$uKey.'%',$rpc)) {$rpc [] = '%'.$uKey.'%';$rpcTo [] = $uVal;} else {
                                for ($a=0; $a<count ($rpc); $a++) if ($rpc [$a] == '%'.$uKey.'%') $rpcTo [$a] = $uVal;
                            }
                        }
                        $err = (isset($this->_error [$name])) ? $this->_error [$name] : '';
                        if (is_array ($err)) {
                            foreach ($err as $err_key => $err_val)
                            {
                                if (!in_array ('%'.$err_key.'%',$rpc)) {$rpc [] = '%'.$err_key.'%'; $rpcTo [] = $err_val;}
                                else {
                                    for ($a=0; $a<count ($rpc); $a++) if ($rpc [$a] == '%'.$err_key.'%') $rpcTo [$a] = $err_val;
                                }
                            }
                        } else {
                            $rpc [] = '%Error%';
                            $rpcTo [] = $err;
                        }

                        if ($data->type == 'select') $tpl = str_replace ('%option%', $data->option, $tpl);
                    }
                    $input .= str_replace ($rpc, $rpcTo, "\n\n".$tpl);
                    $this->_counter [$data->type] ++;
                }
            }
            if ($data->type == 'select')
            {
                $input = str_replace (array ('%Title%', '%property%', '%option%'), array ($data->Title, $property, $input), $this->_sheet [$data->type] );
            }
            $err = (isset ($this->_error [$name])) ? $this->_error [$name] : '';

            $_template = (is_array ($this->_template)) ? $this->_template [0] : $this->_template;
            if (!empty ($data->TemplateIndex)) $_template = $this->_template [$data->TemplateIndex];
            foreach ($data as $uKey => $uVal)
            {
                if (in_array ($uKey, array ('type', 'Title', 'Values'))) continue;
                $_template = str_replace ('%'.$uKey.'%', $uVal, $_template);
            }
            echo str_replace (array ('%Title%', '%Input%', '%Error%'), array ($data->Title, $input, $err), $_template);
        }
    }

    public function validate ($current)
    {
        if (empty ($this->_validation)) return true;
        $this->_error = array ();
        $isValid = true;
        foreach ($this->_elements as $name => $data)
        {
            if ($data->type == 'group') {
                foreach ($data->Values as $key => $items)
                {
                    $validElement = $this->itemChecker ($key, $current, $items);
                    if (!$validElement) $isValid = false;
                }
            } else {
                $validElement = $this->itemChecker ($name, $current, $data);
                if (!$validElement) $isValid = false;
            }
        }
        return $isValid;
    }

    private function itemChecker ($name, &$current, &$data )
    {
        if (isset($this->_validation [$name])) {
            if (@$this->_validation [$name] ['isPhone']) {
                $record = $this->_validation [$name];
                if (!empty($current [$name]) && !preg_match ("/^\+?\d+([\- \/]*\d){5,}$/", $current [$name])) {
                    $this->_error [$name] = (isset ($record ['message'])) ? $record ['message'] : 'invalid character';
                    return false;
                }
            }
            if (@$this->_validation [$name] ['isMail']) {
                $valid = $this->checkAddress ($current [$name]);
                if ($valid === false) {
                    $this->_error [$name] = (isset ($this->_validation [$name] ['message'])) ? $this->_validation [$name] ['message'] : 'invalid mail address';
                }
                return $valid;
            }
            if (@$this->_validation [$name] ['isUrl']) {
                if (!empty ($current [$name]) && ((substr ($current [$name],0,7) !== 'http://' && substr ($current [$name],0,8) != 'https://') || strlen ($current [$name]) < 11)) {
                    $this->_error [$name] = (isset ($this->_validation [$name] ['message'])) ? $this->_validation [$name] ['message'] : 'invalid url';
                    return false;
                }
            }
            if (@$this->_validation [$name] ['isNumb']) {
                $record = $this->_validation [$name];
                if (!empty ($current [$name]) && !is_numeric ($current [$name]) ) {
                    $this->_error [$name] = (isset ($record ['message'])) ? $record ['message'] : 'invalid character';
                    return false;
                }
            }
            if (@$this->_validation [$name] ['isLen'] && strlen ($current [$name]) != $this->_validation [$name] ['isLen']) {
                $this->_error [$name] = (isset ($this->_validation [$name] ['message'])) ? $this->_validation [$name] ['message'] : 'invalid length';
                return false;
            }
            if (@$this->_validation [$name] ['isNeed']) {
                $record = $this->_validation [$name];
                if (!isset ($current [$name])) {
                    $this->_error [$name] = (isset ($record ['message'])) ? $record ['message'] : 'no data';
                    return false;
                }
                if (isset ($record ['default']) && $record ['default'] == $current [$name]) {
                    $this->_error [$name] = (isset ($record ['message'])) ? $record ['message'] : 'not select';
                    return false;
                }
                if (!empty ($data->Values) && is_array ($data->Values)) {
                    foreach ($data->Values as $key => $value) if ($value == $current [$name]) return true;
                }
                $tmp = trim ($current [$name]);
                if ($tmp == '') {
                    $this->_error [$current [$name]] = @$this->_validation [$name] ['message'];
                    return false;
                }
            }
        }
        return true;
    }

    protected function checkAddress ($mailAddress = null) {
        if (preg_match ("/^([a-zA-Z0-9\-])+([a-zA-Z0-9\._\-])*@([a-zA-Z0-9_\-])+([a-zA-Z0-9\._\-]+)+$/", $mailAddress)) {
            $domain = explode ('@', $mailAddress);
            if (empty ($domain [1]) ) return false;
            if (!checkdnsrr ($domain [1],'MX')) return false;
            return true;
        }
        return false;
    }
}
