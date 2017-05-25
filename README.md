# Acenda API client

![enter image description here](https://acenda.com/images/logo-acenda@2x.png)

Acenda website: [Acenda](https://acenda.com)

Homepage: [Git repository](http://github.com/torreycommerce/acenda-php-sdk)

Author: _Acenda development Team_

[![Latest Stable Version](https://poser.pugx.org/torreycommerce/acenda-php-sdk/v/stable)](https://packagist.org/packages/torreycommerce/acenda-php-sdk) [![Total Downloads](https://poser.pugx.org/torreycommerce/acenda-php-sdk/downloads)](https://packagist.org/packages/torreycommerce/acenda-php-sdk) [![Latest Unstable Version](https://poser.pugx.org/torreycommerce/acenda-php-sdk/v/unstable)](https://packagist.org/packages/torreycommerce/acenda-php-sdk) [![License](https://poser.pugx.org/torreycommerce/acenda-php-sdk/license)](https://packagist.org/packages/torreycommerce/acenda-php-sdk)

----------

## Description

The Acenda PHP Client makes it very easy to manage the authentication  
and query to any store you would have access to.

> **Note:**
  * This client is in Alpha and doesn't have all the features needed *

--------

## Install
### Composer
Installation through composer is the easiest.  
Just add these lines to your file `composer.json`:
```json
{
    "require": {
        "torreycommerce/acenda-php-sdk": "0.3.*"
    }
}
```
--------

## How to use it
Usage should be simple enough for you to use it right away.  
After instantiation, access all the API of your store:
```php
<?php
// Autoloading of your dependecies.
include('vendor/autoload.php');
try {
    $acenda = new Acenda\Client(
        _CLIENT_ID_,
        _CLIENT_SECRET_,
        _STORE_NAME_);

    $acenda->get('/order', [
        'limit' => 1,
        'attributes' => 'id'
    ]);

    /**
    **  Response example
    **  object(Acenda\Response)#16 (2) {
    **      ["code"]=>
    **      int(200)
    **      ["body"]=>
    **      object(stdClass)#14 (4) {
    **          ["code"]=>
    **          int(200)
    **          ["status"]=>
    **          string(2) "OK"
    **          ["num_total"]=>
    **          int(1)
    **          ["result"]=>
    **          array(1) {
    **              [0]=>
    **              object(stdClass)#15 (1) {
    **                  ["id"]=>
    **                  int(3398553)
    **              }
    **          }
    **      }
    **  }
    **/
}catch (Exception $e){
    /*  
    ** Two types of exceptions are thrown,
    ** AcendaException which are Acenda HTTP request related,
    ** And Exception which are usage and PHP related.
    */
    var_dump($e);
}
```

## Examples:

### File upload

```
   $acenda->post('/import/upload', [
        'model'=>'variant'
    ],['/tmp/tempfile.csv']);

```

--------

## Contributing
Acenda highly encourages sending in pull requests.  
When submitting a pull request please:  
* Make sure your code follows the coding conventions.
* Please use soft tabs (four spaces) instead of hard tabs.
* Make sure you add appropriate test coverage for your changes.


--------

## Support
Please report bugs on the issue manager of the project on GitHub.
A forum will soon be open to answer questions.

![enter image description here](https://acenda.com/images/logo-acenda@2x.png)
