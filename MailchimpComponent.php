<?php
/**
 * This is Cakephp 2.x plugin. A Mailchimp API v3.0 wrapper in cakephp style. This is still in development mode (beta version).
 * Users are welcome to contribute their code for this plugin.
 * 
 * Usage :  public $components = array(
        'Mailchimp' => array('key' => '<your API Key>')
    );
 * For more details about API visit below url
 * 
 * @link http://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/
 * @author World Wen Technology
 * @version 1.0.0
 */

App::uses('Component', 'Controller');

class MailchimpComponent extends Component {
	/**
	 * Your Mail chimp API key
	 *
	 * @var string Your Mailchimp API key
	 */
	private $_apiKey;

	/**
	 * Your mail chimp API connection URL
	 * For more details, visit
	 * @link http://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/#resources
	 *
	 * @var url|string
	 */
	private $_endpoint = 'https://<dc>.api.mailchimp.com/3.0';
	/**
	 * Last response from API
	 *
	 * @var array Response array from API
	 */
	private $_lastResponse = array();
	/**
	 * SSL Verification
	 *
	 * @var boolean True|False
	 */
	public $verify_ssl = true;

	/**
	 * Was request success?
	 *
	 * @var boolean True|False
	 */
	private $request_successful = false;
	private $last_error         = '';

	private $last_request       = array();

	/**
 * Constructor
 *
 * @param ComponentCollection $collection A ComponentCollection this component can use to lazy load its components
 * @param array $settings Array of configuration settings.
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$this->_controller = $collection->getController();
		parent::__construct($collection, $settings);
		$this->_apiKey = $this->settings['key'];
		if($this->_apiKey == ""){
			throw new APIKeyMissingException(__d('cake_dev','Mailchimp API key is missing'));
		}
		if(strpos($this->_apiKey,'-') === false){
			throw new APIKeyInvalidException(__d('cake_dev','Your Mailchimp API key is not valid'));
		}

		list(,$dataCenter) = explode('-',$this->_apiKey);
		$this->_endpoint = str_replace('<dc>',$dataCenter,$this->_endpoint);
		$this->_lastResponse = array('headers' => null,'body' => null);
	}

	/**
	 * Initilization function
	 *
	 * @param Controller $controller
	 */

	public function initialize(Controller $controller) {
		$this->controller = $controller;
	}

	/**
     * Componenet startup function. It makes sure that Component was called from contrller and it is valid Call
     *
     * @param Controller $controller Controller instance
     */

	public function startup(Controller $controller){
		$this->controller = $controller;
	}

	/**
     * Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
     * @param   string $email The subscriber's email address
     * @return  string          Hashed version of the input
     */
	public function subscriberHash($email)
	{
		return md5(strtolower($email));
	}

	/**
     * Was the last request successful?
     * @return bool  True for success, false for failure
     */
	public function success()
	{
		return $this->request_successful;
	}

	/**
     * Get the last error returned by either the network transport, or by the API.
     * If something didn't work, this should contain the string describing the problem.
     * @return  array|false  describing the error
     */
	public function getLastError()
	{
		return $this->last_error;
	}

	/**
     * Get an array containing the HTTP headers and the body of the API response.
     * @return array  Assoc array with keys 'headers' and 'body'
     */
	public function getLastResponse()
	{
		return $this->_lastResponse;
	}

	/**
     * Get an array containing the HTTP headers and the body of the API request.
     * @return array  Assoc array
     */
	public function getLastRequest()
	{
		return $this->last_request;
	}

	/**
	 * Get Newsletter Group List id from Mailchimp
	 *
	 * @link http://developer.mailchimp.com/documentation/mailchimp/reference/lists/#read-get_lists
	 * @param string $newLetterName Name of newsletter of which Id you want
	 * @return varchar|boolean Id of newsletter
	 */

	public function getListId($newLetterName = ""){
		$getList = $this->get('lists');
		if(!empty($getList)){
			foreach ($getList['lists'] as $key => $value){
				if($value['name'] == $newLetterName){
					$listId = $value['id'];
				}
			}
			return isset($listId) ? $listId : false;
		}
		return false;
	}

	/**
	 * Get Intrest category Id from mail ching
	 * @link http://developer.mailchimp.com/documentation/mailchimp/reference/lists/interest-categories/#read-get_listsget_lists_list_id_interest_categories
	 * @param string $groupName Name of intrest category You want to get
	 * @param varchar $listId Id of newsletter list this intrest category belongs to
	 * @return varchar|boolean Id of intreset category or false
	 */

	public function getIntrestCategory($groupName = "",$listId){
		$newLetterName = "Test Newsletter";
		$getListIntrestCategory = $this->get('lists/'.$listId.'/interest-categories');
		if(!empty($getListIntrestCategory) && !empty($getListIntrestCategory['categories'])){
			foreach ($getListIntrestCategory['categories'] as $key => $value){
				if($value['title'] == $groupName && $value['list_id'] == $listId){
					$categoryId = $value['id'];
				}
			}
			return isset($categoryId) ? $categoryId : false;
		}
		return false;
	}
	
	/**
	 * Get All Categories inside of Intrest Group
	 * @link http://developer.mailchimp.com/documentation/mailchimp/reference/lists/interest-categories/interests/#read-get_lists_list_id_interest_categories_interest_category_id_interests
	 * @param varchar $listId Id of newsletter list
	 * @param varchar $categoryId Category Id
	 */
	
	public function getAllIntresetInCategory($listId,$categoryId){
		$intrestList = $this->get('lists/'.$listId.'/interest-categories/'.$categoryId.'/interests');
		if(!empty($intrestList) && isset($intrestList['interests']) && !empty($intrestList['interests'])){
			$intrestArray = array();
			foreach ($intrestList['interests'] as $key => $val){
				if($val['category_id'] == $categoryId && $val['list_id'] == $listId){
						$intrestArray[$val['id']] = $val['name'];
				}
			}
			return isset($intrestArray) && !empty($intrestArray) ? $intrestArray : false;
		}
		return false;
	}

	/**
     * Make an HTTP DELETE request - for deleting data
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (if any)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
	public function delete($method, $args = array(), $timeout = 10)
	{
		return $this->initRequest('delete', $method, $args, $timeout);
	}

	/**
     * Make HTTP GET request- for getting data
     *
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return array|false Assoc array of API response,decoded from JSON
     */

	public function get($method,$args = array(),$timeout = 10){
		return $this->initRequest('get',$method,$args,$timeout);
	}

	/**
     * Make an HTTP PATCH request - for performing partial updates
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
	public function patch($method, $args = array(), $timeout = 10)
	{
		return $this->initRequest('patch', $method, $args, $timeout);
	}

	/**
     * Make an HTTP POST request - for creating and updating items
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
	public function post($method, $args = array(), $timeout = 10)
	{
		return $this->initRequest('post', $method, $args, $timeout);
	}

	/**
     * Make an HTTP PUT request - for creating new items
     * @param   string $method URL of the API request method
     * @param   array $args Assoc array of arguments (usually your data)
     * @param   int $timeout Timeout limit for request in seconds
     * @return  array|false   Assoc array of API response, decoded from JSON
     */
	public function put($method, $args = array(), $timeout = 10)
	{
		return $this->initRequest('put', $method, $args, $timeout);
	}
	
	/**
	 * Function to send request to Mailchimp using cUrl. 
	 *
	 * @param string $http_verb Type of HTTP request, POST or GET  or PUT or DELETE
	 * @param string $method Method to use on Mailchimp, GET,PATCH,PUT or DELETE
	 * @param array $args Data array to passe to mailchimp
	 * @param int $timeout Request timeout time
	 * @return boolean Response from Mailchimp API
	 */

	private function initRequest($http_verb,$method,$args = array(),$timeout = 10){
		if(!function_exists('curl_init') || !function_exists('curl_setopt')){
			throw new cUrlException('cURL support is required, but can\'t be found.');
		}
		$url = $this->_endpoint . '/' . $method;

		$this->last_error = "";
		$this->request_successful = false;
		$response = array('header' => null,$body = null);
		$this->last_response = $response;

		$this->last_response = array(
				'method' => $http_verb,
				'path'	 => $method,
				'url'	=> $url,
				'body'  => '',
				'timeout' => $timeout
			);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/vnd.api+json',
			'Content-Type: application/vnd.api+json',
			'Authorization: apikey ' . $this->_apiKey
		));
		curl_setopt($ch, CURLOPT_USERAGENT, 'BMMC Mail chimp API Component');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);

		switch ($http_verb){
			case 'post':
				curl_setopt($ch, CURLOPT_POST, true);
				$this->attachRequestPayload($ch, $args);
				break;
			case 'get':
				$query = http_build_query($args);
				curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
				break;
			case 'delete':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;

			case 'patch':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
				$this->attachRequestPayload($ch, $args);
				break;

			case 'put':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				$this->attachRequestPayload($ch, $args);
				break;
		}
		$response['body']    = curl_exec($ch);
		$response['headers'] = curl_getinfo($ch);

		if (isset($response['headers']['request_header'])) {
			$this->last_request['headers'] = $response['headers']['request_header'];
		}

		if ($response['body'] === false) {
			$this->last_error = curl_error($ch);
		}

		curl_close($ch);

		return $this->parseResponse($response);
	}


	/**
     * Encode the data and attach it to the request
     * @param   resource $ch cURL session handle, used by reference
     * @param   array $data Assoc array of data to attach
     */
	private function attachRequestPayload(&$ch, $dataArray)
	{
		$encoded = json_encode($dataArray);
		$this->last_request['body'] = $encoded;
		curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
	}

	/**
     * Decode the response and parse any error messages for debugging
     * @param array $response The response from the curl request
     * @return array|false     The JSON decoded into an array
     */
	private function parseResponse($response)
	{
		$this->last_response = $response;

		if (!empty($response['body'])) {

			$d = json_decode($response['body'], true);

			if (isset($d['status']) && $d['status'] != '200' && isset($d['detail'])) {
				$this->last_error = sprintf('%d: %s', $d['status'], $d['detail']);
			} else {
				$this->request_successful = true;
			}

			return $d;
		}

		return false;
	}
}

class MailchimpException extends CakeException{

}

class APIKeyMissingException extends MailchimpException {
	/**
 * Constructor
 *
 * @param string $message If no message is given 'API key is missing' will be the message
 * @param integer $code Status code, defaults to 500
 */
	public function __construct($message = null, $code = 500) {
		if (empty($message)) {
			$message = 'API key is missing';
		}
		parent::__construct($message, $code);
	}

}

class APIKeyInvalidException extends MailchimpException {
	/**
	 * Constructer
	 * 
	 * @param string $message If no message is given, 'API key sis invalid' will be the message
	 * @param  integer $code Status code, defaults to 500
	 */

	public function __construct($message = null,  $code = 500){
		if(empty($message)) {
			$message = "API key is invalid";
		}

		parent::__construct($message,$code);
	}
}

class APIEndpointMissingException extends MailchimpException {
	/**
	 * Construct
	 *
	 * @param string $message If no message given,"API end point is missing" will be the message
	 * @param integer $code Status code, defaults to 500
	 */
	public function __construct($message, $code = 500){
		if(empty($message)){
			$message = "API endpoing is missing";
		}
		parent::__construct($message,$code);
	}
}

class ConnectionMethodMissingException extends MailchimpException {
	/**
	 * Construct
	 *
	 * @param string $message If no message given,"Connecion PATH is missing" will be the message
	 * @param integer $code Status code, defaults to 500
	 */
	public function __construct($message,$code = 500){
		if(empty($message))
		{
			$message = "Connecion PATH is missing";
		}
		parent::__construct($message,$code);
	}
}

class cUrlException extends CacheException  {
	/**
	 * Construct
	 *
	 * @param string $message If no message given,"cUrl is missing" will be the message
	 * @param integer $code Status code, defaults to 500
	 */
	public function __construct($message,$code = 500){
		if(empty($message))
		{
			$message = "cUrl is missing";
		}
		parent::__construct($message,$code);
	}
}