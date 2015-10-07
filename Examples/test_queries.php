<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/Client.php';

## Get Config 
    $config = json_decode(file_get_contents("config.json"),true);
    if (empty($config['client_id']) || empty($config['client_secret']))
        die("Configuration Issue!");
##

try {
    $acenda = new Acenda\Client($config['client_id'], $config['client_secret'], $config['store_name']);
    
    var_dump($acenda->get('/product', [
        'limit' => 1
    ])->code);

} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

?>