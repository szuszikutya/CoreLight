<?PHP

class Core_Boot
{
    protected $_db = null;

    function __construct () {}

    public function run ()
    {
        if ( method_exists( $this, 'initDb') ) $this->initDb();
        if ( method_exists( $this, 'initSession') ) $this->initSession();
        if ( method_exists( $this, 'initLanguage') ) $this->initLanguage();
        if ( method_exists( $this, 'initDispatcher') ) $this->initDispatcher();
        if ( method_exists( $this, 'initController') ) $this->initController();
        if ( method_exists( $this, 'initView') ) $this->initView();
    }

    public function initDb ()
    {
        if (class_exists ('Db_MySqli')) {
            if (!empty (Core::$_CONFIG ['AUTOINIT'] ['class'] ['sql'])) {
                Db_MySqli::$_BASE_CONNECTION = new Db_Adapter_MySqli(Core::$_CONFIG ['AUTOINIT'] ['class'] ['sql']);
                $this->_db = &Db_MySqli::$_BASE_CONNECTION;
            }
        }
        return $this->_db;
    }

    public function initDispatcher ()
    {
        if (empty (Core::$_CONFIG ['dispatcherPlugin'])) Core::$_TMP = new Core_Dispatcher;
        elseif (file_exists (Core::$_DIRS->APPLICATION.'/'.Core::$_CONFIG ['dispatcherPlugin'])) {
            require_once (Core::$_DIRS->APPLICATION.'/'.Core::$_CONFIG ['dispatcherPlugin']);
            Core::$_TMP = new Dispatcher_Plugin ();
            Core::$_TMP->plugin ();
        } else {
            Core::$_TMP = new Core_Dispatcher;
        }
        Core::$_TMP = null;
    }

    public function initController ()
    {
        Core::$_STDOUT = (object) array('_CONTROLLER' => null, '_ACTION' => null);
        Core::$_DISPATCHER->_CALL_COUNT = 0;
        do {
            Core::$_VIEW->_initalised = false;
            Core::$_STDOUT->_CONTROLLER = null;
            $userFile = Core::$_DIRS->CONTROLLER.'/'.Core::$_DISPATCHER->_CONTROLLER.'Controller.php';
            $indxFile = Core::$_DIRS->CONTROLLER.'/indexController.php';
            if (file_exists ($userFile)) {
                require_once ($userFile);
                $tmp = Core::$_DISPATCHER->_CONTROLLER.'Controller';
                Core::$_TMP = new $tmp ();
            } elseif (file_exists ($indxFile)) {
                Core::$_DISPATCHER->_CONTROLLER = 'index';
                Core::$_DISPATCHER->_ACTION = 'index';
                header ('HTTP/1.1 301 Moved Permanently');
                require_once ($indxFile);
                $tmp = Core::$_DISPATCHER->_CONTROLLER.'Controller';
                Core::$_TMP = new $tmp ();
            } else {
                die ('Not found any controller at: '.Core::$_DIRS->CONTROLLER.'/'.Core::$_DISPATCHER->_CONTROLLER.'Controller.php');
            }
        } while (!empty (Core::$_DISPATCHER->_RECALL) && Core::$_DISPATCHER->_CALL_COUNT < 10);
        Core::$_TMP = null;
    }

    public function initView ()
    {
        Core::$_VIEW->__build ();
    }
}
