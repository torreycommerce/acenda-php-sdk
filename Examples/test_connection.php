<?php

require_once __DIR__.'/../acenda.php';

## Get Config 
	$config = json_decode(file_get_contents("config.json"),true);
	if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['store_url']))
		die("Configuration Issue!");
##

try {
	$acenda = new Acenda($config['client_id'], $config['client_secret'], $config['store_url'], @$config['myTestPlugin']);
	$acenda->performRequest('/order', 'GET', []);
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

?>