<?PHP

class CoreServer
{
    static $_DIRS;
    static $_CONFIG=array();
    static $_MEMORY_DATA = null;

    static function Make ($config_file = null)
    {
        if ( !defined('LIBRARY_PATH') ) die ( "Not set LIBRARY_PATH");

        self::$_DIRS = (object) array(
            'LIB' => realpath(LIBRARY_PATH),
            'APPLICATION' => realpath(SYSTEM_PATH),
            'CONFIG' => realpath(SYSTEM_PATH.'/configs'),
            'MODEL' => realpath(SYSTEM_PATH.'/models'),
        );
        spl_autoload_register('CoreServer::_autoloader');
        self::$_CONFIG = array();
        if ( $config_file && is_file( self::$_DIRS->CONFIG.'/'.$config_file) ) {

            $ini = parse_ini_file( self::$_DIRS->CONFIG.'/'.$config_file, true );

            foreach ( $ini as $key => $val )
            {
                if ( in_array(strtoupper($key), array('AUTOINIT', 'ENGINE')) ) $key = strtoupper($key);
                if ( is_array($val) ) {
                    foreach ( $val as $key2 => $val2 )
                    {
                        $tmp = explode('.', $key2);
                        if ( count($tmp) > 1 ) eval( 'self::$_CONFIG[$key]["'.implode('"]["',$tmp).'"] = $val2;' );
                        else self::$_CONFIG[$key][$key2] = $val2;
                    }
                } else {
                    $tmp = explode('.', $key);
                    if ( count($tmp) > 1 ) eval( 'self::$_CONFIG["'.implode('"]["',$tmp).'"] = $val;' );
                    else self::$_CONFIG[$key] = $val;
                }
            }
        }
        if ( !empty( self::$_CONFIG['ENGINE'] ) ) {

            foreach ( self::$_CONFIG['ENGINE'] as $key => $val )
            {
                $key = strtolower($key);
                if ( $key == 'session' && $val ) session_start();
                if ( $key == 'errorlevel' ) error_reporting( $val );
                if ( $key == 'define') foreach ($val as $k => $v) { $v = addslashes($v); eval("\ndefine( \"{$k}\", \"{$v}\");"); }
            }
        }

        return null;
    }

    static function _autoloader( $class )
    {
        $class = str_replace('_', '/', $class);

        if ( file_exists( self::$_DIRS->LIB.'/'.$class.'.php' ) ) {
            require_once( self::$_DIRS->LIB.'/'.$class.'.php' );
                if ( !empty(self::$_CONFIG['AUTOINIT']['class'][$class]) ) {
                    if ( method_exists($class,'autoInit') ) {
                        foreach( self::$_CONFIG['AUTOINIT']['class'][$class] as $key => $val ) {
                            eval ($class .'::autoInit( $key, $val );');
                        }
                    }
                    else echo "Can't init class: $class becouse 'autoInit' function is not present.\n";
            }
            return true;
        }

        if ( file_exists(self::$_DIRS->MODEL.'/'.$class.'.php')) {
            require_once(self::$_DIRS->MODEL.'/'.$class.'.php');
            return true;
        }

        if ( isset(self::$_DIRS->ADDON) && file_exists(self::$_DIRS->ADDON.'/'.$class.'.php')) {
            require_once( self::$_DIRS->ADDON.'/'.$class.'.php');
            return true;
        }

        return false;
    }
}

class CoreServerData {

    function __set( $name, $value ){ CoreServer::$_MEMORY_DATA->$name = $value; }

    function __get( $name ){ if ( isset(CoreServer::$_MEMORY_DATA->$name) ) return CoreServer::$_MEMORY_DATA->$name; return null; }

    function __isset( $name ){ return isset(CoreServer::$_MEMORY_DATA->$name); }

    function __unset( $name ){ unset(CoreServer::$_MEMORY_DATA->$name); }
}
