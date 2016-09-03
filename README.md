# ModelElastic
ModelElastic is an ElasticSearch model adapter for Phalcon php, it could do the basic operation of phalcon model like find, findFirst, save, count.

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

Build your model just like phalcon mysql based model.

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

if you have another connection/index/type, you can overwrite in your model with initialize

```php
   public function initialize(){
          $this->setConnection('<DIFFERENT_CONNECTION>');
          $this->setIndex('<DIFFERENT_INDEX>');
          $this->setSource('<DIFFERENT_TYPE>');
    }
```

Most of phalcon model methods like validation, beforeValidation, beforeValidationOnUpdate are available.

```php

   public function beforeValidationOnUpdate(){
        $this->update_at = time();
    }
```

Find/FindFirst
```php
        $query =[];
        $query['bool']['filter'][]['term']['email'] = 'abc@test.com';
        $query['bool']['filter'][]['term']['status'] = 1;
	
	$user = Users::find([
                        'query'=>$query,
                            ]);
                            
        $user = Users::findFirst([
                        'query'=>$query,
                            ]);
```

FindById
```php
	$userId = 10;
	$user   = Users::findById($userId);
```

Save/Update

```php
	$userId = 10;
	$user   = Users::findById($userId);
	if(!$user){
		$user = new Users();
	}
	
	$user->username = "abc";
	if(!$user->save()){
		var_dump($user->getMessages());
	}
	
	echo $user->id;
	
	$user->email = "abc@test.com";
	$user->save();
```




