<?php

// GET /oauth/login
Router::connect('/oauth/login', 
	array(
		'plugin' => 'OAuth2Server', 
		'controller' => 'OAuth2Server', 
		'action' => 'login', 
		'[method]' => 'GET'
	)
);

// POST /oauth/authorize
Router::connect('/oauth/authorize', 
	array(
		'plugin' => 'OAuth2Server', 
		'controller' => 'OAuth2Server', 
		'action' => 'authorize', 
		'[method]' => 'POST'
	)
);

// POST /oauth/access_token
Router::connect('/oauth/access_token', 
	array(
		'plugin' => 'OAuth2Server', 
		'controller' => 'OAuth2Server', 
		'action' => 'access_token', 
		//'[method]' => 'POST'
	)
);