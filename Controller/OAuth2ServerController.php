<?php
class OAuth2ServerController extends OAuth2ServerAppController {

	public $uses = array(
		'OAuth2Server.OAuth2ServerClient',
		'OAuth2Server.OAuth2ServerCode',
		'OAuth2Server.OAuth2ServerToken'
	);

	/**
	 * isAuthorized() callback.
	 * Allow anonymous access to all actions of this controller.
	 */
	public function isAuthorized() {
		return true;
	}

	/**
	 * Issue a new access_token to a formerly anonymous user.
	 * Used by apps to authenticate via RESTful APIs.
	 */
	public function access_token() {
		try {
			if ($this->request->is('post')) {
				$this->OAuth2Lib->grantAccessToken();
			}
		} catch(Exception $e) {
			$this->fail($e);
		}
	}

	/**
	 * Display an HTML login form to end-user.
	 * Used by third-party apps to authenticate via web browser. (Part 1 of 2)
	 */
	public function login() {
		$this->helpers[] = 'Form';
	}

	/**
	 * Issue a new access_token to a formerly anonymous user.
	 * Used by third-party apps to authenticate via web browser. (Part 2 of 2)
	 */
	public function authorize() {
		try {
			$authenticationStatus = $this->OAuth2Lib->checkUserCredentials(
										$this->data['client_id'], 
										$this->data['username'], 
										$this->data['password']
									);
									
			$this->OAuth2Lib->finishClientAuthorization(
				(boolean) $authenticationStatus,
				array(
					'response_type' => $this->data['response_type'],
					'client_id' => $this->data['client_id'],
					'redirect_uri' => $this->data['redirect_uri'],
					'state' => $this->data['state'],
					'scope' => $this->data['scope'],
					'username' => $this->data['username']
				)
			);
		} catch(Exception $e) {
			$this->fail($e);
		}
	}
}