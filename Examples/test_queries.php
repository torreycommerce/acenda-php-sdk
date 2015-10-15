<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/Autoloader.php';

Acenda\Autoloader::register();

// Get Config 
$config = json_decode(file_get_contents("config.json"),true);
if (empty($config['client_id']) || empty($config['client_secret']))
    die("Configuration Issue!");

try {
    $acenda = new Acenda\Client($config['client_id'], $config['client_secret'], $config['store_name']);
    $acenda->get('/order', ['limit' => 1, 'attributes' => 'id']);
}catch (Exception $e){
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

?>