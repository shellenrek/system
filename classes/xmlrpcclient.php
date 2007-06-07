<?php

/**
 * Create an XMLRPC client connection
 * 
 * To Use:
 * <code>
 * // Create the object using the XMLRPC entrypoint and scope.
 * $rpc= new XMLRPCClient('http://rpc.pingomatic.com', 'weblogUpdates');
 * // Make a weblogUpdates.ping call on the client.
 * $result= $rpc->ping('Blog name', 'http://example.com');
 * 
 * // Change the scope on the existing client object.
 * $rpc->set_scope('system');
 * // Make a system.listMethods call on the client.
 * $methods= $rpc->listMethods();
 * </code>
 *
 * @package Habari
 * @todo Handle XMLRPC fault results by returning some error value. 
 */

class XMLRPCClient
{
	private $entrypoint;
	private $scope = 'system';

	/**
	 * Create the XMLRPCClient
	 * 
	 * @param string $xmlrpc_entrypoint The entrypoint of te remote server
	 */
	public function __construct($xmlrpc_entrypoint, $scope = null) 
	{
		$this->entrypoint = $xmlrpc_entrypoint;
		if(isset($scope)) {
			$this->scope = $scope;
		}
	}
	
	/**
	 * Set the scope of any subsequent function calls
	 * The default scope is 'system'.
	 * 
	 * @param string $scope The scope to use
	 */
	public function set_scope($scope) 
	{
		$this->scope = $scope;
	}
	
	/**
	 * Allow method overloading for this class.
	 * This method allows any method name to be called on this object.  The method
	 * called is the method called via RPC, within the scope defined in $this->scope.
	 * 
	 * @param string $fname The function name to call
	 * @param array $args An array of arguments that were called with the function
	 * @return array The result array 
	 */
	public function __call($fname, $args) 
	{
		if($this->scope != '') {
			$rpc_method = "{$this->scope}.{$fname}";
		}
		else {
			$rpc_method = $fname;
		}
	
		$rpx = new SimpleXMLElement('<methodCall/>');
		$rpx->addChild('methodName', $rpc_method);
		if(count($args) > 0) {
			$params = $rpx->addchild('params');
			foreach($args as $arg) {
				$param = $params->addchild('param');
				XMLRPCUtils::encode_arg($param, $arg);
			}
		}

		$request= new RemoteRequest($this->entrypoint, 'POST');
		$request->add_header('Content-Type: text/xml');
		$request->set_body($rpx->asXML());
		
		if($request->execute()) {
			$response = $request->get_response_body();
			$enc = mb_detect_encoding($response);
			$responseutf8 = mb_convert_encoding($response, 'UTF-8', $enc);
			try {
				$bit = ini_get('error_reporting');
				error_reporting($bit && !E_WARNING);
				$responsexml = new SimpleXMLElement($responseutf8);
				error_reporting($bit);
				return XMLRPCUtils::decode_args(reset($responsexml->xpath('/methodResponse/params/param/value')));
			}
			catch (Exception $e){
				Utils::debug($response, $e);
				error_reporting($bit);
				return false;
			}
		}
	}
}

?>
