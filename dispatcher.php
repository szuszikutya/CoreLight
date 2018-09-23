<?PHP

/*
 * Custom router plugin sample
 */

class Dispatcher_Plugin extends Core_Dispatcher
{
	private $UrlWithOutPage = null;

	public function plugin ()
	{
		$url = explode ('?', $_SERVER ['REQUEST_URI']);
		$this->UrlWithOutPage = $url [0];

		if ($url [0] == '' || $url [0] == '/') {
			self::$_CONTROLLER = 'index';               // directly set the controller
			self::$_ACTION = 'index';                   // directly set action of controller
			return;                                     // quit plugin
		}

		/* 
			Set dynamic controllers or alias here, from database or something else
			...
			...
		*/

		self::$_NAVIGATE = (object) array ('baseUrl' => $this->UrlWithOutPage);  // set your site specific route object
	}
}