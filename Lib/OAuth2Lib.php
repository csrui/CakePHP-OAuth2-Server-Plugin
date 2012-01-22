<?php
// include plugin configuration
Configure::load('OAuth2Server.config');

// include Tim Ridgeley's class
App::import('Vendor', 'OAuth2Server.OAuth2', array('file' => 'oauth2-php'. DS .'lib'. DS .'OAuth2.inc'));

// extend with overloaded customizations
class OAuth2Lib extends OAuth2 {
	/**
	 * Persistent reference to controller invoking this component.
	 */
	public $controller;

	/**
	 * Make sure that the client id is valid
	 * If a secret is required, check that they've given the right one
	 * Must return false if the client credentials are invalid
	 */
	public function checkClientCredentials($client_id, $client_secret = null) {
		return (boolean) $this->controller->OAuth2ServerClient->field('id', array(
			'id' => $client_id,
			'secret' => $client_secret
		));
	}

	/**
	 * OAuth says we should store request URIs for each registered client
	 * Implement this function to grab the stored URI for a given client id
	 * Must return false if the given client does not exist or is invalid
	 */
	protected function getRedirectUri($client_id) {
		return $this->controller->OAuth2ServerClient->field('redirect_uri', array(
			'id' => $client_id
		));
	}

	/**
	 * We need to store and retrieve access token data as we create and verify tokens
	 * Look up the supplied token id from storage, and return an array like:
	 */
	protected function getAccessToken($oauth_token) {
		// cache this request because it can get called a lot
		static $tokens = array();
		if (isset($tokens[$oauth_token])) {
			return $tokens[$oauth_token];
		}

		$result = $this->controller->OAuth2ServerToken->find('first', array(
			'fields' => array(
				'token',
				'client_id',
				'expires',
				'scope',
				'username'
			),
			'conditions' => array(
				'token' => $oauth_token
			)
		));
		
		if ($result) {
			return $tokens[$oauth_token] = $result['OAuth2ServerToken'];
		}
		return null;
	}

	/**
	 * Store the supplied values
	 */
	protected function setAccessToken($oauth_token, $client_id, $expires, $scope = null, $username = null) {
		$data = array(
			'token' => $oauth_token,
			'client_id' => $client_id,
			'expires' => $expires,
			'scope' => $scope,
			'username' => $username
		);
		
		if (isset($_REQUEST['device_id'])) {
			$data['device_id'] = &$_REQUEST['device_id'];
		}
		
		$this->controller->OAuth2ServerToken->save($data, true, array(
			'token',
			'client_id',
			'expires',
			'scope',
			'username',
			'device_id'
		));
	}

	/**
	 *
	 */
	protected function getSupportedGrantTypes() {
		return array(
			OAUTH2_GRANT_TYPE_AUTH_CODE,
			OAUTH2_GRANT_TYPE_USER_CREDENTIALS,
			CLIENT_CREDENTIALS_GRANT_TYPE,
			//ASSERTION_GRANT_TYPE,
			OAUTH2_GRANT_TYPE_REFRESH_TOKEN,
			//NONE_GRANT_TYPE
		);
	}

	/**
	 *
	 */
	protected function getSupportedAuthResponseTypes() {
		return array(
			AUTH_CODE_AUTH_RESPONSE_TYPE,
			ACCESS_TOKEN_AUTH_RESPONSE_TYPE,
			CODE_AND_TOKEN_AUTH_RESPONSE_TYPE
		);
	}

	/**
	 *
	 */
	protected function getSupportedScopes() {
		return array();
	}

	/**
	 *
	 */
	protected function authorizeClientResponseType($client_id, $response_type) {
		return true;
	}

	/**
	 *
	 */
	protected function authorizeClient($client_id, $grant_type) {
		return true;
	}

	/* Functions that help grant access tokens for various grant types */

	/**
	 * Fetch authorization code data (probably the most common grant type)
	 * IETF Draft 4.1.1: http://tools.ietf.org/html/draft-ietf-oauth-v2-08#section-4.1.1
	 * Required for AUTH_CODE_GRANT_TYPE
	 */
	protected function getStoredAuthCode($code) {

		$result = $this->controller->OAuth2ServerCode->find('first', array(
			'fields' => array(
				'access_code',
				'client_id',
				'redirect_uri',
				'expires',
				'scope'
				),
			'conditions' => array(
				'access_code' => $code
				)
			));

			if ($result) {
				return array(
					'client_id' => $result[0]['OAuth2ServerCode']['client_id'],
					'redirect_uri' => $result[0]['OAuth2ServerCode']['redirect_uri'],
					'expires' => $result[0]['OAuth2ServerCode']['expires'],
					'scope' => $result[0]['OAuth2ServerCode']['scope']
				);
			}
			else {
				return null;
			}
	}

	/**
	 * Take the provided authorization code values and store them somewhere (db, etc.)
	 * Required for AUTH_CODE_GRANT_TYPE
	 */
	protected function storeAuthCode($code, $client_id, $redirect_uri, $expires, $scope = null) {

		$this->controller->OAuth2ServerCode->save(array(
			'access_code' => $code,
			'client_id' => $client_id,
			'redirect_uri' => $redirect_uri,
			'expires' => $expires,
			'scope' => $scope
		)) or die('Unknown error saving oauth access code.');
	}

	/**
	 * Grant access tokens for basic user credentials
	 * IETF Draft 4.1.2: http://tools.ietf.org/html/draft-ietf-oauth-v2-08#section-4.1.2
	 * Required for USER_CREDENTIALS_GRANT_TYPE
	 */
	public function checkUserCredentials($client_id, $username, $password) {

		App::import('Controller/Component', 'AuthComponent');

		$conditions = array(
			'email' => $this->controller->request->data('username'),
			'password' => AuthComponent::password($this->controller->request->data('password'))
		);

		$user = $this->controller->User->find('first', $conditions);

		// Checks if user exists
		if (empty($user)) return false;

		// Checks if user has a client configured
		$conditions = array(
			'user_id' => $user['User']['id'],
			'id' => $client_id,
			'active' => 1
		);

		return (boolean) $this->controller->OAuth2ServerClient->find('first', compact('conditions'));

	}

	/**
	 * Grant refresh access tokens
	 * IETF Draft 4.1.4: http://tools.ietf.org/html/draft-ietf-oauth-v2-08#section-4.1.4
	 * Required for REFRESH_TOKEN_GRANT_TYPE
	 */
	protected function getRefreshToken($refresh_token) {
		// for now, we're storing these in the same way as access tokens
		return $this->getAccessToken($refresh_token);
	}

	/**
	 * Store refresh access tokens
	 * Required for REFRESH_TOKEN_GRANT_TYPE
	 */
	protected function storeRefreshToken($token, $client_id, $expires, $scope = null, $username = null) {
		// for now, we're storing these in the same way as access tokens
		return $this->storeAccessToken($token, $client_id, $expires, $scope, $username); // @TODO: infer username from previous token
	}

	/**
	 * Expire a used refresh token.
	 * This is not explicitly required in the spec, but is almost implied. After granting a new refresh token,
	 * the old one is no longer useful and so should be forcibly expired in the data store so it can't be used again.
	 */
	public function expireRefreshToken($token) {

		$this->controller->OAuth2ServerToken->delete($token) or die('failed to expire refresh token.');
	}

	/**
	 *
	 */
	protected function getDefaultAuthenticationRealm() {
		return 'API Server';
	}

	/**
	 * Get full token record from database,
	 * matching by access_token.
	 *
	 * @return Array Token record.
	 */
	public function getToken() {
		$token_param = $this->getAccessTokenParam();
		return $this->getAccessToken($token_param);
	}

	/**
	 * Get individual field value from User record in database, 
	 * matching by access_token.
	 *
	 * @return Array User record.
	 */
	public function getTokenUser($field) {
		$token = $this->getToken();
		
		debug($token);
		
		die();
		
		if ($token !== null && !empty($token['username'])) {

			throw new Exception("Function still relies in obsolete method loadModel", 1);
			$this->loadModel('User');
			return $this->User->field($field, array(
			  Configure::read('OAuth2Server.Auth.fields.username') => $token['username']
			));
		}
	}
	
	private static function one($mixed) {
		return is_array($mixed) ? $mixed[0] : $mixed;
	} 
}