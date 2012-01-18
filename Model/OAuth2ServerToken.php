<?php

class OAuth2ServerToken extends AppModel {

	public $primaryKey = 'token';

	public $belongsTo = array(
		'OAuth2ServerClient' => array(
			'className' => 'OAuth2ServerClient',
			'foreignKey' => 'client_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
}