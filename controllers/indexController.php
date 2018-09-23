<?PHP

class indexController extends Core_Controller {

	public function init() 
	{
	    // this is the controller constructor
	}
	
	public function indexAction ()
	{ 
		$db = new Db_Adapter_MySqli (Core::$_CONFIG ['Sample_DBs'] ['mysql']);
		$db->query ("SELECT * FROM ".TABLE_SAMP_01." limit 50");

		$this->view->dbResource_01 = clone ($db);
		$this->view->configInfo = (isset ($_REQUEST ['details'])) ? Core::$_CONFIG ['Whatever'] : null;
	}

	public function error404Action ()
	{ 
		header("HTTP/1.1 404 Not Found"); 
	}

}