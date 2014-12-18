<?php

require_once __DIR__.'/../acenda.php';

try {
	$acenda = new Acenda('thiebaude@torreycommerce.com', '0e4540146806d2fe2ed920bc5cabf06a', 'http://admin.acendev/preview/928b8747da068b1d5bf7174fe832be9f', 'myTestPlugin');
	$acenda->performRequest('/order', 'GET', []);
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

?>