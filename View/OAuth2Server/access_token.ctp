<?php
echo
	$this->Form->create(false, array('type' => 'POST', 'url' => '/oauth/access_token')) .
	$this->Form->end(__('Obtain'));
