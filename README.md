# ModelElastic
ModelElastic is an ElasticSearch model adapter for Phalcon php, it could do basic operations of phalcon model like find, findFirst, save, count.

## How to use ModelElastic ##
Make sure you have offical [elasticsearch php](https://github.com/elastic/elasticsearch-php) library installed.

In bootstrap or module, add elasticsearch php as DI.

```php
// you can change di name 'elastic', but you'll also need to change getConnection in ModelElastic.php
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

If you have another connection/index/type, you can overwrite in your model with initialize

```php
   public function initialize(){
	$this->setConnection('<DIFFERENT_CONNECTION>');
	$this->setIndex('<DIFFERENT_INDEX>');
	$this->setSource('<DIFFERENT_TYPE>');
    }
```

Most of phalcon model methods like validation, beforeValidation, beforeValidationOnUpdate are supported.

```php

   public function beforeValidationOnUpdate(){
	$this->update_at = time();
    }
```

Find/FindFirst
```php
        $c =[];
        $q =[];
        $c['bool']['filter'][]['term']['email'] = 'abc@test.com';
        $c['bool']['filter'][]['term']['status'] = 1;
        $q['query'] = $c;
	$user = Users::findFirst($q);
	
	if($user){
	  echo $user->id;
	}
```

Find

```php
	$c = [];
        $q = [];
        $q['query']     = $c;
        $q['sort']      = ['update_at'=>'desc'];
        $q['max_total'] = 2000; //max return 2000 items
        $q['page']      = 1;
        $q['limit']     = 40; //items per page
	$user = Users::find($q);
	
	if($user){
		echo "total items: ". $user->total_items."<br>";
		echo "next page: ". $user->next."<br>";
		foreach($user->items as $u){
		         echo "user id is ".$u->id."<br>";
		}
	}

```

FindById

```php
	$userId = 10;
	$user   = Users::findById($userId);
	echo $user->id;
```
count

```php
	$q = [];
	$q['bool']['filter'][]['term']['username'] = 'abc';
	$count = Users::count(['query'=>$q]);
	echo $count;

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

scroll

```php
        $c = [];
        $q = [];
        $q['query']  = $c;
        $q['size'] = 100; 
        $q['scroll'] = "20s"; //within 20s
        $users = Users::find($q);

        while (TRUE) {
            foreach($users->items as $row){
                echo "username is ".$row->username;
                $row->username = $row->username ."_extra_text";  
                $row->save() //delete row
            }//end foreach

            $users = Users::scroll([
                        'scroll_id'=> $users->scroll_id,
                        'scroll'   => '20s',
                        ]);
            if(empty($users->items)){
                break;
            }
        }//end while
```
                



