# ModelElastic
ModelElastic is an ElasticSearch model adapter for Phalcon php, it could do the basic operation like regular phalcon model like find, findFirst.

## How to use ModelElastic ##
Make sure you have [elasticsearch php](https://github.com/elastic/elasticsearch-php) library installed.

In bootstrap or module, add elasticsearch php as DI.

```php
$di->set('elastic', function() use (){
                $clientBuilder = ClientBuilder::create();
                $clientBuilder->setHosts(['127.0.0.1:9400']); 
                $client = $clientBuilder->build();
                return $client;
		});
```

Build your model just like phalcon model.

```php
<?php
use ModelElastic\ModelElastic;
class Users extends ModelElastic{
	public $id;
	public $username;
	public $email;
	public $password;
	public $create_at;
	public $update_at;
}

```

