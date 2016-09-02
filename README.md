# ModelElastic
ModelElastic is an ElasticSearch model adapter for Phalcon php, it could do the basic operation like regular phalcon model like find, findFirst.

## How to use ModelElastic ##
Make sure you have [elasticsearch php](https://github.com/elastic/elasticsearch-php) library installed.

In bootstrap or module, add elasticsearch php as di.

```php
$di->set('elastic', function() use ($config){
                $clientBuilder = ClientBuilder::create();
                $clientBuilder->setHosts(['127.0.0.1:9400']); 
                $client = $clientBuilder->build();
                return $client;
		});
```
