<?PHP

class Core {

    static $_DIRS;
    static $_CONFIG = array();
    static $_DISPATCHER = null;
    static $_BOOT = null;
    static $_VIEW = null;
    static $_TMP = null;
    static $_STDOUT = null;
    static $_TEMPLATE_DATA = null;
		
	static function Make ($config_file = null) {
		
		if ( !defined('LIBRARY_PATH') ) die ( "Not set LIBRARY_PATH");
        
		self::$_DIRS = (object) array (
            'LIB' => realpath (LIBRARY_PATH),
            'APPLICATION' => realpath (SYSTEM_PATH),
            'VIEW' => realpath (SYSTEM_PATH."/views"),
            'CONFIG' => realpath (SYSTEM_PATH."/configs"),
            'MODEL' => realpath (SYSTEM_PATH."/models"),
            'CONTROLLER' => realpath (SYSTEM_PATH."/controllers")
        );
        
		spl_autoload_register (array ('Core','_autoloader'));
        self::$_CONFIG = array ();
        
		if ($config_file && file_exists (self::$_DIRS->CONFIG.'/'.$config_file)) {
            
			$ini = parse_ini_file (self::$_DIRS->CONFIG.'/'.$config_file, true);
            
			foreach ( $ini as $key => $val )
			{
                if (in_array (strtoupper ($key), array ('AUTOINIT', 'ENGINE'))) $key = strtoupper ($key);
                if (is_array ($val)) {
                    foreach ($val as $key2 => $val2 )
                    {
                        $tmp = explode ('.', $key2);
                        if (count ($tmp) > 1 ) eval ('self::$_CONFIG[$key]["'.implode('"]["',$tmp).'"] = $val2;');
                        else self::$_CONFIG [$key] [$key2] = $val2;
                    }
                } else {
                    $tmp = explode ('.', $key);
                    if (count ($tmp) > 1 ) eval ( 'self::$_CONFIG["'.implode('"]["',$tmp).'"] = $val;' );
                    else self::$_CONFIG [$key] = $val;
                }
            }
        }
        if (!empty (self::$_CONFIG ['DEFINE'] ) ) {
			foreach (self::$_CONFIG ['DEFINE'] as $key => $val) {
				eval ("\ndefine( \"{$key}\", \"{$val}\");");
			}
		}
		if (!empty (self::$_CONFIG ['ENGINE'])) {
			foreach (self::$_CONFIG ['ENGINE'] as $key => $val )
			{
				$key = strtolower($key);
				
                if ($key == 'session' && $val) session_start ();
				if ($key == 'dir' && is_array ($val) ) {
					foreach ($val as $k => $v) self::$_DIRS->$k = $v;
				}
                if ($key == 'errorlevel') error_reporting ($val);
				if ($key == 'debugruntime' && $val ) self::$_RUNTIME_LOG = true;
 				if ($key == 'define') {
					foreach ($val as $k => $v) eval("\ndefine( \"{$k}\", \"{$v}\");");
				}
				if ($key == 'bootstrap' ) {
                    require_once ( self::$_DIRS->APPLICATION.'/'.$val );
                    self::$_BOOT = new boot();
				}
				if ($key == 'addon') self::$_DIRS->ADDON = realpath ('/'.$val);
                if ($key == 'dispatcherplugin' ) self::$_CONFIG ['dispatcherPlugin'] = $val;
				if ($key == 'extension') {
                    $tmp = explode (',',$val);
                    foreach ($tmp as $extend) set_include_path (get_include_path () . PATH_SEPARATOR . self::$_DIRS->LIB. '/'.$extend.'/');
                }
            }
        }
		
        if (self::$_BOOT == null) self::$_BOOT = new Core_Boot ();
        self::$_VIEW = new Core_View;
        self::$_BOOT->run ();
        return null;
    }
	
	static function _autoloader ( $class )
    {
		$class = str_replace ('_', '/', $class);

		if (file_exists (self::$_DIRS->LIB.'/'.$class.'.php')) {
            require_once (self::$_DIRS->LIB.'/'.$class.'.php');
				if (!empty (self::$_CONFIG ['AUTOINIT'] ['class'] [$class])) {
					if (method_exists ($class,'autoInit')) {
						foreach (self::$_CONFIG['AUTOINIT'] ['class'] [$class] as $key => $val)
						{
                            eval ($class .'::autoInit( $key, $val );');
                        }
                    }
					else echo "Can't init class: $class becouse 'autoInit' function is not present.\n";
            }
            return true;
        }
		if (file_exists (self::$_DIRS->MODEL.'/'.$class.'.php' ) ) {
            require_once (self::$_DIRS->MODEL.'/'.$class.'.php' );
            return true;
        }
		if (isset (self::$_DIRS->ADDON) && file_exists (self::$_DIRS->ADDON.'/'.$class.'.php' )) {
            require_once (self::$_DIRS->ADDON.'/'.$class.'.php');
            return true;
        }		
        return false;
    }
}

class Core_Data_Store
{
	function __set ($name, $value)
    {
        if (Core::$_TEMPLATE_DATA == null) Core::$_TEMPLATE_DATA = (object) array ($name => $value);
        else Core::$_TEMPLATE_DATA->$name = $value;
    }

	function __get ($name)
    {
        if (isset (core::$_TEMPLATE_DATA->$name)) return core::$_TEMPLATE_DATA->$name;
        return null;
    }
	
	function __isset ($name) { return isset (core::$_TEMPLATE_DATA->$name); }
}
