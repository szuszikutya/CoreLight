<?PHP

class Core_Controller
{
    protected $_db = null;
    protected $view = null;
    protected $_VARS = null;
    protected $_ROUTE = null;

    public function indexAction (){ }

    public function error404Action () {}

    public function __construct ()
    {
        Core::$_DISPATCHER->_RECALL = null;
        Core::$_DISPATCHER->_CALL_COUNT ++;
        $this->_ROUTE = &Core::$_DISPATCHER->_NAVIGATE;
        $this->_VARS = &Core::$_DISPATCHER->_PARAMETERS;
        $this->view = new Core_Data_Store;
        header ('Content-type: text/html; charset=utf-8');
        ob_start ();
        if (method_exists ($this, 'init' ) ) $this->init ();
        if (method_exists ($this, Core::$_DISPATCHER->_ACTION.'Action')) {
            $tmp = Core::$_DISPATCHER->_ACTION.'Action';
            $this->$tmp( Core::$_DISPATCHER->_PARAMETERS );
        } else {
            //header('HTTP/1.1 301 Moved Permanently');
            Core::$_DISPATCHER->_ACTION = 'error404';
            $this->error404Action (Core::$_DISPATCHER->_PARAMETERS);
        }
        Core::$_STDOUT->CONTROLLER = ob_get_contents ();
        ob_clean ();
    }

    protected function _getParams ($key = null, $defVal = null)
    {
        return (isset (Core::$_DISPATCHER->_PARAMETERS[$key])) ? Core::$_DISPATCHER->_PARAMETERS [$key] : $defVal;
    }

    protected function loop ($file = null, $value=null)
    {
        return Core::$_VIEW->loop ($file, $value);
    }

    protected function _jump ($controller = 'index', $action = 'index', $isSoftRedirect = false, $isRewriteUrl = false)
    {
        Core::$_DISPATCHER->_CONTROLLER = $controller;
        Core::$_DISPATCHER->_ACTION = $action;
        Core::$_DISPATCHER->_RECALL = true;
        $protocol = ($_SERVER ['SERVER_PORT'] != 443) ? 'http' : 'https';
        //if ($isSoftRedirect) header("HTTP/1.1 301 Moved Permanently");
        if ($isRewriteUrl === true) header ("Location: {$protocol}://{$_SERVER ['HTTP_HOST']}".$this->_url());
        if ($isRewriteUrl) header ("Location: {$protocol}://{$_SERVER ['HTTP_HOST']}".$isRewriteUrl);
    }

    protected function _url () { return '/'.Core::$_DISPATCHER->_CONTROLLER.'/'.Core::$_DISPATCHER->_ACTION; }
}
